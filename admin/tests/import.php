<?php
/**
 * Admin: Import mock-test questions from a CSV/Excel (.csv) file.
 * Excel users: File > Save As > CSV (Comma delimited).
 *
 * Expected columns (with a header row):
 *   question, option_a, option_b, option_c, option_d, correct, explanation
 *   - correct may be a letter (A-D), a number (1-4), or the exact option text.
 *   - explanation is optional.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Import Questions';
$active_menu = 'tests';

$errors      = [];
$row_errors  = [];
$success_msg = '';
$saved_test_id = 0;
$file_results = [];   // per-file import summary (for multi-file uploads)

/**
 * Normalise the "correct" column to a letter A-D.
 */
function resolve_correct($raw, array $options)
{
    $raw = trim((string)$raw);
    if ($raw === '') return '';

    // Letter A-D
    $up = strtoupper($raw);
    if (in_array($up, ['A', 'B', 'C', 'D'], true)) {
        return $up;
    }
    // Number 1-4
    if (in_array($raw, ['1', '2', '3', '4'], true)) {
        return ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'][$raw];
    }
    // Match the exact option text
    foreach ($options as $idx => $opt) {
        if (strcasecmp(trim($opt), $raw) === 0) {
            return ['A', 'B', 'C', 'D'][$idx];
        }
    }
    return '';
}

/**
 * Parse a single CSV file path into a list of question arrays.
 * Returns ['questions' => [...], 'row_errors' => [...], 'error' => ?string]
 */
function parse_questions_csv($tmp_path)
{
    $questions  = [];
    $row_errors = [];
    $line_no    = 0;

    $handle = fopen($tmp_path, 'r');
    if ($handle === false) {
        return ['questions' => [], 'row_errors' => [], 'error' => 'Could not read the file.'];
    }

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $line_no++;

        // Skip fully empty lines
        if (count($data) === 1 && trim((string)$data[0]) === '') {
            continue;
        }
        // Strip UTF-8 BOM from the first cell of the first row
        if ($line_no === 1 && isset($data[0])) {
            $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
        }
        // Skip header row (first row containing the word "question")
        if ($line_no === 1 && stripos((string)($data[0] ?? ''), 'question') !== false) {
            continue;
        }

        $q_text  = trim((string)($data[0] ?? ''));
        $opts    = [
            trim((string)($data[1] ?? '')),
            trim((string)($data[2] ?? '')),
            trim((string)($data[3] ?? '')),
            trim((string)($data[4] ?? '')),
        ];
        $correct = resolve_correct($data[5] ?? '', $opts);
        $expl    = trim((string)($data[6] ?? ''));
        $subj    = trim((string)($data[7] ?? ''));  // optional subject column

        if ($q_text === '') {
            $row_errors[] = "Row $line_no: missing question text — skipped.";
            continue;
        }
        if (in_array('', $opts, true)) {
            $row_errors[] = "Row $line_no: all four options are required — skipped.";
            continue;
        }
        if ($correct === '') {
            $row_errors[] = "Row $line_no: 'correct' must be A-D, 1-4, or match an option — skipped.";
            continue;
        }

        $q_entry = [
            'question'    => $q_text,
            'options'     => $opts,
            'correct'     => $correct,
            'explanation' => $expl,
        ];
        if ($subj !== '') {
            $q_entry['subject'] = $subj;
        }
        $questions[] = $q_entry;
    }
    fclose($handle);

    return ['questions' => $questions, 'row_errors' => $row_errors, 'error' => null];
}

/**
 * Create a new draft test from a list of questions. Returns the new test id.
 */
function create_test_from_questions($title, array $questions, $created_by)
{
    global $pdo;
    $slug_base = slug($title);
    $slug = $slug_base;
    $check = $pdo->prepare("SELECT id FROM mock_tests WHERE slug = ? LIMIT 1");
    $check->execute([$slug]);
    if ($check->fetchColumn()) {
        $slug = $slug_base . '-' . time() . '-' . random_int(10, 99);
    }
    $ins = $pdo->prepare(
        "INSERT INTO mock_tests (title, slug, total_questions, questions_json, is_active, created_by)
         VALUES (?, ?, ?, ?, 0, ?)"
    );
    $ins->execute([$title, $slug, count($questions), json_encode($questions), $created_by ?: null]);
    return (int)$pdo->lastInsertId();
}

/**
 * Turn an uploaded file name into a clean test title.
 * "ssc_cgl_mock 1.csv" => "Ssc Cgl Mock 1"
 */
function title_from_filename($filename)
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = str_replace(['_', '-'], ' ', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name !== '' ? ucwords($name) : ('Imported Test ' . date('Y-m-d H:i'));
}

// Load existing tests for the "append" dropdown
$tests = $pdo->query("SELECT id, title FROM mock_tests ORDER BY created_at DESC")
             ->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($_FILES['csv_file']) || empty($_FILES['csv_file']['name'][0]) && !is_array($_FILES['csv_file']['name'])) {
        $errors[] = 'Please choose at least one CSV file to upload.';
    } else {
        $created_by     = (int)($_SESSION['user_id'] ?? 0);
        $target_test_id = (int)($_POST['target_test_id'] ?? 0);
        $new_test_name  = trim($_POST['new_test_name'] ?? '');

        // Normalise $_FILES into a simple list (works for single or multiple files).
        $raw = $_FILES['csv_file'];
        $list = [];
        if (is_array($raw['name'])) {
            $count = count($raw['name']);
            for ($i = 0; $i < $count; $i++) {
                if (($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $list[] = [
                        'name' => $raw['name'][$i],
                        'tmp'  => $raw['tmp_name'][$i],
                        'size' => $raw['size'][$i],
                    ];
                }
            }
        } elseif (($raw['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $list[] = ['name' => $raw['name'], 'tmp' => $raw['tmp_name'], 'size' => $raw['size']];
        }

        if (empty($list)) {
            $errors[] = 'No valid files were uploaded.';
        }

        $multi = count($list) > 1;

        // MULTI-FILE: each file becomes its own new draft test (named from filename).
        if ($multi) {
            $made = 0; $total_q = 0;
            foreach ($list as $f) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv', 'txt'], true)) {
                    $file_results[] = ['name' => $f['name'], 'ok' => false, 'note' => 'Not a CSV — skipped'];
                    continue;
                }
                if ($f['size'] > 2 * 1024 * 1024) {
                    $file_results[] = ['name' => $f['name'], 'ok' => false, 'note' => 'Larger than 2 MB — skipped'];
                    continue;
                }
                $parsed = parse_questions_csv($f['tmp']);
                if (empty($parsed['questions'])) {
                    $file_results[] = ['name' => $f['name'], 'ok' => false, 'note' => 'No valid questions found'];
                    continue;
                }
                $title = title_from_filename($f['name']);
                $tid = create_test_from_questions($title, $parsed['questions'], $created_by);
                $made++; $total_q += count($parsed['questions']);
                $file_results[] = [
                    'name'    => $f['name'],
                    'ok'      => true,
                    'note'    => count($parsed['questions']) . ' question(s) → "' . $title . '"',
                    'test_id' => $tid,
                ];
            }
            if ($made > 0) {
                $success_msg = "Created {$made} draft test(s) from uploaded files with {$total_q} total question(s). Review &amp; activate them below.";
            } else {
                $errors[] = 'No tests were created. Check the file format.';
            }

        // SINGLE FILE: keep original behaviour (append to existing test OR create one new test).
        } else {
            $f = $list[0];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                $errors[] = 'Only .csv files are supported. In Excel use “Save As → CSV (Comma delimited)”.';
            } elseif ($f['size'] > 2 * 1024 * 1024) {
                $errors[] = 'File too large. Maximum size is 2 MB.';
            } else {
                $parsed = parse_questions_csv($f['tmp']);
                $row_errors = $parsed['row_errors'];
                $questions  = $parsed['questions'];

                if ($parsed['error']) {
                    $errors[] = $parsed['error'];
                } elseif (empty($questions)) {
                    $errors[] = 'No valid questions were found in the file. Please check the format.';
                } else {
                    if ($target_test_id > 0) {
                        // Append to an existing test
                        $stmt = $pdo->prepare("SELECT id, questions_json FROM mock_tests WHERE id = ? LIMIT 1");
                        $stmt->execute([$target_test_id]);
                        $test = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$test) {
                            $errors[] = 'Selected test was not found.';
                        } else {
                            $existing = json_decode($test['questions_json'] ?? '', true);
                            if (!is_array($existing)) $existing = [];
                            $merged = array_merge($existing, $questions);
                            $up = $pdo->prepare("UPDATE mock_tests SET questions_json = ?, total_questions = ? WHERE id = ?");
                            $up->execute([json_encode($merged), count($merged), $target_test_id]);
                            $saved_test_id = $target_test_id;
                            $success_msg = 'Imported ' . count($questions) . ' question(s). Test now has ' . count($merged) . ' total.';
                        }
                    } else {
                        // Create a new (draft) test — title from field, else filename
                        $title = $new_test_name !== '' ? $new_test_name : title_from_filename($f['name']);
                        $saved_test_id = create_test_from_questions($title, $questions, $created_by);
                        $success_msg = 'Created draft test “' . htmlspecialchars($title) . '” with ' . count($questions) . ' question(s).';
                    }
                }
            }
        }
    }
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-file-csv me-2"></i>Import Questions (CSV / Excel)</h2>
    <a href="/admin/tests/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i>Back to Tests
    </a>
  </div>

  <?php if ($success_msg): ?>
    <div class="alert alert-success">
      <i class="fas fa-circle-check me-2"></i><?= $success_msg ?>
      <?php if ($saved_test_id): ?>
        <a href="/admin/tests/edit.php?id=<?= (int)$saved_test_id ?>" class="alert-link ms-1">Edit / activate this test</a>.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($row_errors): ?>
    <div class="alert alert-warning">
      <strong>Some rows were skipped:</strong>
      <ul class="mb-0 small"><?php foreach (array_slice($row_errors, 0, 20) as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($file_results): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="fas fa-list-check me-2"></i>Import Results (per file)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th>File</th><th>Status</th><th style="width:90px"></th></tr></thead>
            <tbody>
              <?php foreach ($file_results as $fr): ?>
              <tr>
                <td class="small"><?= htmlspecialchars($fr['name']) ?></td>
                <td class="small">
                  <?php if ($fr['ok']): ?>
                    <span class="badge bg-success">OK</span> <?= htmlspecialchars($fr['note']) ?>
                  <?php else: ?>
                    <span class="badge bg-danger">Skipped</span> <?= htmlspecialchars($fr['note']) ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($fr['test_id'])): ?>
                    <a href="/admin/tests/edit.php?id=<?= (int)$fr['test_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Upload File(s)</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
              <label class="form-label fw-semibold">CSV File(s) <span class="text-danger">*</span></label>
              <input type="file" name="csv_file[]" class="form-control" accept=".csv,text/csv" multiple required>
              <div class="form-text">
                Max 2 MB each. <strong>Select multiple files</strong> to create many tests at once —
                each file becomes its own test (named from the file name).
              </div>
            </div>

            <div id="single-only">
              <div class="mb-3">
                <label class="form-label fw-semibold">Add to existing test <span class="text-muted small">(single file only)</span></label>
                <select name="target_test_id" id="target_test_id" class="form-select">
                  <option value="0">— Create a new test —</option>
                  <?php foreach ($tests as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3" id="new_test_wrap">
                <label class="form-label fw-semibold">New Test Title <span class="text-muted small">(optional — defaults to file name)</span></label>
                <input type="text" name="new_test_name" class="form-control" placeholder="e.g. SSC CGL Mock Test 1">
                <div class="form-text">New tests are created as <strong>draft</strong> — activate them after reviewing.</div>
              </div>
            </div>

            <div id="multi-note" class="alert alert-info py-2 small" style="display:none">
              <i class="fas fa-circle-info me-1"></i>Multiple files selected — each file will become a separate draft test, named from its file name.
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="fas fa-upload me-1"></i>Import Questions
            </button>
            <a href="/admin/tests/sample-questions.php" class="btn btn-outline-success">
              <i class="fas fa-download me-1"></i>Download Sample CSV
            </a>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-circle-info me-2"></i>File Format</div>
        <div class="card-body">
          <p class="small mb-2">The first row must be a header. Columns, in order:</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered small mb-2">
              <thead class="table-light"><tr><th>Column</th><th>Required</th></tr></thead>
              <tbody>
                <tr><td><code>question</code></td><td>Yes</td></tr>
                <tr><td><code>option_a</code></td><td>Yes</td></tr>
                <tr><td><code>option_b</code></td><td>Yes</td></tr>
                <tr><td><code>option_c</code></td><td>Yes</td></tr>
                <tr><td><code>option_d</code></td><td>Yes</td></tr>
                <tr><td><code>correct</code></td><td>Yes (A-D, 1-4, or option text)</td></tr>
                <tr><td><code>explanation</code></td><td>Optional</td></tr>
                <tr><td><code>subject</code></td><td>Optional (section name, e.g. Reasoning)</td></tr>
              </tbody>
            </table>
          </div>
          <p class="small text-muted mb-0">
            Download the sample CSV to see a ready-to-fill example. Save it from Excel/Google Sheets as
            <strong>CSV</strong> before uploading.
          </p>
        </div>
      </div>
    </div>
  </div>

</div>
</div>
<?php
$extra_js = '<script>
(function(){
  var sel = document.getElementById("target_test_id");
  var wrap = document.getElementById("new_test_wrap");
  function toggle(){ if(sel && wrap){ wrap.style.display = (sel.value !== "0") ? "none" : "block"; } }
  if (sel && wrap) { sel.addEventListener("change", toggle); toggle(); }

  // Toggle single-file vs multi-file UI based on number of files chosen.
  var fileInput  = document.querySelector("input[name=\'csv_file[]\']");
  var singleOnly = document.getElementById("single-only");
  var multiNote  = document.getElementById("multi-note");
  if (fileInput) {
    fileInput.addEventListener("change", function(){
      var many = this.files && this.files.length > 1;
      if (singleOnly) singleOnly.style.display = many ? "none" : "block";
      if (multiNote)  multiNote.style.display  = many ? "block" : "none";
    });
  }
})();
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
