<?php
/** Admin — Syllabus Manager. Add exam -> subject -> topics used by the
 *  AI question generator (suggestions + auto-assigned subject + full paper). */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Syllabus Manager';
$active_menu = 'tests';
$notice = ''; $error = ''; $has_table = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $exam    = trim($_POST['exam_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $topics  = trim($_POST['topics'] ?? '');
            $sort    = (int)($_POST['sort_order'] ?? 0);
            if ($exam === '' || $subject === '') {
                $error = 'Exam name and subject are required.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO syllabi (exam_name, subject, topics, sort_order) VALUES (?,?,?,?)");
                $stmt->execute([$exam, $subject, $topics, $sort]);
                log_activity('syllabus_add','syllabi',(int)$pdo->lastInsertId(),"$exam / $subject");
                $notice = 'Syllabus section added.';
            }
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM syllabi WHERE id = ?")->execute([(int)$_POST['id']]);
            $notice = 'Deleted.';
        }
    } catch (Throwable $e) {
        $has_table = false;
        $error = 'The syllabi table is missing. Import database/schema.sql.';
    }
}

$rows = [];
try {
    $rows = $pdo->query("SELECT * FROM syllabi ORDER BY exam_name, sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $has_table = false; }

// group by exam for display
$grouped = [];
foreach ($rows as $r) { $grouped[$r['exam_name']][] = $r; }

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="h4 mb-0"><i class="fas fa-book-open me-2"></i>Syllabus Manager</h2>
    <a href="/admin/tests/generate.php" class="btn btn-success btn-sm"><i class="fas fa-robot me-1"></i>AI Generator</a>
  </div>
  <p class="text-muted">Add exam syllabi here. Each <strong>subject</strong> becomes a section, and its
     <strong>topics</strong> power the AI generator's suggestions, auto-assigned subject, and "full paper" mode.</p>

  <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!$has_table): ?><div class="alert alert-warning">Import <code>database/schema.sql</code> to enable this.</div><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-plus me-2"></i>Add Subject &amp; Topics</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add">
            <div class="mb-2">
              <label class="form-label small fw-semibold">Exam Name</label>
              <input type="text" name="exam_name" class="form-control" list="examList" required placeholder="e.g. SSC CGL">
              <datalist id="examList">
                <?php foreach (get_exam_categories() as $c): ?><option value="<?= htmlspecialchars($c) ?>"></option><?php endforeach; ?>
              </datalist>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Subject / Section</label>
              <input type="text" name="subject" class="form-control" required placeholder="e.g. Quantitative Aptitude">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Topics</label>
              <textarea name="topics" class="form-control" rows="6" placeholder="One topic per line (or comma-separated)&#10;e.g.&#10;Percentage&#10;Profit & Loss&#10;Time & Work"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Sort Order</label>
              <input type="number" name="sort_order" class="form-control" value="0">
            </div>
            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Add</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <?php if (empty($grouped)): ?>
        <div class="card shadow-sm border-0"><div class="card-body text-center text-muted py-5">
          No syllabi added yet. The AI generator still uses the built-in syllabi for common exams.
        </div></div>
      <?php else: foreach ($grouped as $exam => $subs): ?>
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-header bg-white fw-semibold"><i class="fas fa-graduation-cap me-2 text-primary"></i><?= htmlspecialchars($exam) ?></div>
          <div class="card-body p-0">
            <table class="table mb-0 align-middle">
              <thead class="table-light"><tr><th>Subject</th><th>Topics</th><th style="width:50px;"></th></tr></thead>
              <tbody>
              <?php foreach ($subs as $r): ?>
                <tr>
                  <td class="fw-semibold small"><?= htmlspecialchars($r['subject']) ?></td>
                  <td class="small text-muted"><?= htmlspecialchars(mb_substr((string)$r['topics'], 0, 160)) ?><?= mb_strlen((string)$r['topics'])>160?'…':'' ?></td>
                  <td>
                    <form method="post" onsubmit="return confirm('Delete this syllabus section?')">
                      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
