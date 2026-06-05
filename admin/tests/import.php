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

// Load existing tests for the "append" dropdown
$tests = $pdo->query("SELECT id, title FROM mock_tests ORDER BY created_at DESC")
             ->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a CSV file to upload.';
    } else {
        $file = $_FILES['csv_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt'], true)) {
            $errors[] = 'Only .csv files are supported. In Excel use “Save As → CSV (Comma delimited)”.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'File too large. Maximum size is 2 MB.';
        } else {
            $questions = [];
            $line_no   = 0;

            if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                while (($data = fgetcsv($handle, 0, ',')) !== false) {
                    $line_no++;

                    // Skip fully empty lines
                    if (count($data) === 1 && trim((string)$data[0]) === '') {
                        continue;
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

                    $questions[] = [
                        'question'    => $q_text,
                        'options'     => $opts,
                        'correct'     => $correct,
                        'explanation' => $expl,
                    ];
                }
                fclose($handle);
            } else {
                $errors[] = 'Could not read the uploaded file.';
            }

            if (empty($errors) && empty($questions)) {
                $errors[] = 'No valid questions were found in the file. Please check the format.';
            }

            if (empty($errors) && !empty($questions)) {
                $target_test_id = (int)($_POST['target_test_id'] ?? 0);
                $new_test_name  = trim($_POST['new_test_name'] ?? '');
                $created_by     = (int)($_SESSION['user_id'] ?? 0);

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
                    // Create a new (draft) test
                    if ($new_test_name === '') {
                        $errors[] = 'Enter a title for the new test, or pick an existing test to append to.';
                    } else {
                        $slug_base = slug($new_test_name);
                        $slug = $slug_base;
                        $check = $pdo->prepare("SELECT id FROM mock_tests WHERE slug = ? LIMIT 1");
                        $check->execute([$slug]);
                        if ($check->fetchColumn()) {
                            $slug = $slug_base . '-' . time();
                        }

                        $ins = $pdo->prepare(
                            "INSERT INTO mock_tests (title, slug, total_questions, questions_json, is_active, created_by)
                             VALUES (?, ?, ?, ?, 0, ?)"
                        );
                        $ins->execute([
                            $new_test_name, $slug, count($questions),
                            json_encode($questions), $created_by ?: null,
                        ]);
                        $saved_test_id = (int)$pdo->lastInsertId();
                        $success_msg = 'Created draft test “' . htmlspecialchars($new_test_name) . '” with ' . count($questions) . ' question(s).';
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

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Upload File</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
              <label class="form-label fw-semibold">CSV File <span class="text-danger">*</span></label>
              <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
              <div class="form-text">Max 2 MB. Excel: use <strong>Save As &rarr; CSV (Comma delimited)</strong>.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Add to existing test</label>
              <select name="target_test_id" id="target_test_id" class="form-select">
                <option value="0">— Create a new test —</option>
                <?php foreach ($tests as $t): ?>
                  <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3" id="new_test_wrap">
              <label class="form-label fw-semibold">New Test Title</label>
              <input type="text" name="new_test_name" class="form-control" placeholder="e.g. SSC CGL Mock Test 1">
              <div class="form-text">New tests are created as <strong>draft</strong> — activate them after reviewing.</div>
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
  function toggle(){ wrap.style.display = (sel.value !== "0") ? "none" : "block"; }
  if (sel && wrap) { sel.addEventListener("change", toggle); toggle(); }
})();
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
