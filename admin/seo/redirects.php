<?php
/** Admin — Redirect Manager (301s stored in DB, applied via 404 handler). */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Redirect Manager';
$notice = ''; $error = '';
$has_table = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $from = '/' . ltrim(trim($_POST['from_path'] ?? ''), '/');
            $to   = trim($_POST['to_path'] ?? '');
            $code = in_array((int)($_POST['status_code'] ?? 301), [301,302]) ? (int)$_POST['status_code'] : 301;
            $note = trim($_POST['note'] ?? '');
            if ($from === '/' || $to === '') {
                $error = 'Both "from" and "to" are required.';
            } elseif (rtrim($from,'/') === rtrim($to,'/')) {
                $error = 'Source and destination are identical — that would loop.';
            } else {
                // Loop guard: destination must not itself redirect back to source.
                $g = $pdo->prepare("SELECT to_path FROM redirects WHERE from_path = ? AND is_active = 1 LIMIT 1");
                $g->execute([rtrim($to,'/') ?: $to]);
                $back = $g->fetchColumn();
                if ($back && rtrim($back,'/') === rtrim($from,'/')) {
                    $error = 'That would create a redirect loop with an existing rule.';
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO redirects (from_path, to_path, status_code, note, created_by)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE to_path=VALUES(to_path), status_code=VALUES(status_code),
                         note=VALUES(note), is_active=1"
                    );
                    $stmt->execute([$from, $to, $code, $note ?: null, (int)($_SESSION['user_id']??0)?:null]);
                    log_activity('redirect_add','redirects',(int)$pdo->lastInsertId(),"$from -> $to");
                    $notice = 'Redirect saved.';
                }
            }
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE redirects SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$_POST['id']]);
            $notice = 'Updated.';
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM redirects WHERE id = ?")->execute([(int)$_POST['id']]);
            $notice = 'Deleted.';
        }
    } catch (Throwable $e) {
        $has_table = false; $error = 'The redirects table is missing. Import database/schema.sql.';
    }
}

$rows = [];
try {
    $rows = $pdo->query("SELECT * FROM redirects ORDER BY created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $has_table = false; }

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">
  <h2 class="mb-3"><i class="fas fa-route me-2"></i>Redirect Manager</h2>
  <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!$has_table): ?>
    <div class="alert alert-warning">Import <code>database/schema.sql</code> to enable redirects.</div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold"><i class="fas fa-plus me-2"></i>Add 301 / 302 Redirect</div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="col-md-4">
          <label class="form-label small">From path</label>
          <input type="text" name="from_path" class="form-control" placeholder="/old-page.php?id=5" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small">To (path or URL)</label>
          <input type="text" name="to_path" class="form-control" placeholder="/exams/new-slug/" required>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Type</label>
          <select name="status_code" class="form-select">
            <option value="301">301 Permanent</option>
            <option value="302">302 Temporary</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Add</button>
        </div>
        <div class="col-12">
          <input type="text" name="note" class="form-control form-control-sm" placeholder="Optional note">
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold">Active &amp; Inactive Redirects (<?= count($rows) ?>)</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light"><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No redirects yet.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td class="small text-break" style="max-width:260px;"><?= htmlspecialchars($r['from_path']) ?></td>
              <td class="small text-break" style="max-width:260px;"><?= htmlspecialchars($r['to_path']) ?></td>
              <td><span class="badge bg-secondary"><?= (int)$r['status_code'] ?></span></td>
              <td><?= (int)$r['hits'] ?></td>
              <td><span class="badge <?= $r['is_active']?'bg-success':'bg-secondary' ?>"><?= $r['is_active']?'Active':'Off' ?></span></td>
              <td class="text-nowrap">
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button name="action" value="toggle" class="btn btn-sm btn-outline-secondary" title="Toggle"><i class="fas fa-power-off"></i></button>
                  <button name="action" value="delete" class="btn btn-sm btn-outline-danger" title="Delete"
                          onclick="return confirm('Delete this redirect?')"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
