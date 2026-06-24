<?php
/**
 * Admin — "Report Correction" inbox.
 * Lists submissions from the corrections table; lets admin mark resolved.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Reported Corrections';
$notice = '';

// Handle "mark resolved" / "reopen".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id = (int)($_POST['id'] ?? 0);
    $new_status = ($_POST['action'] ?? '') === 'resolve' ? 'resolved' : 'new';
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE corrections SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            log_activity('correction_status', 'corrections', $id, $new_status);
            $notice = 'Updated.';
        } catch (Throwable $e) {
            $notice = 'Could not update (has the migration been imported?).';
        }
    }
}

$rows = [];
$has_table = true;
try {
    $rows = $pdo->query("SELECT * FROM corrections ORDER BY (status='new') DESC, created_at DESC LIMIT 200")
                ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $has_table = false;
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">
  <h2 class="mb-4"><i class="fas fa-flag me-2"></i>Reported Corrections</h2>
  <?php if ($notice): ?><div class="alert alert-info"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

  <?php if (!$has_table): ?>
    <div class="alert alert-warning">
      The <code>corrections</code> table does not exist yet. Import
      <code>database/schema.sql</code> to enable this feature.
    </div>
  <?php else: ?>
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr><th>When</th><th>Status</th><th>Page</th><th>Message</th><th>From</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No correction reports yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr class="<?= $r['status'] === 'new' ? 'table-warning' : '' ?>">
                <td class="text-nowrap small"><?= htmlspecialchars(format_date($r['created_at'], 'd M Y H:i')) ?></td>
                <td><span class="badge <?= $r['status'] === 'new' ? 'bg-warning text-dark' : 'bg-success' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                <td class="small">
                  <?php if (!empty($r['page_url'])): ?>
                    <a href="<?= htmlspecialchars($r['page_url']) ?>" target="_blank"><?= htmlspecialchars(mb_substr($r['page_url'], 0, 40)) ?></a>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small" style="max-width:420px;white-space:pre-line;"><?= htmlspecialchars($r['message']) ?></td>
                <td class="small">
                  <?= htmlspecialchars($r['name'] ?: 'Anonymous') ?>
                  <?php if (!empty($r['email'])): ?><br><a href="mailto:<?= htmlspecialchars($r['email']) ?>"><?= htmlspecialchars($r['email']) ?></a><?php endif; ?>
                </td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <?php if ($r['status'] === 'new'): ?>
                      <button name="action" value="resolve" class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i></button>
                    <?php else: ?>
                      <button name="action" value="reopen" class="btn btn-sm btn-outline-secondary"><i class="fas fa-rotate-left"></i></button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
