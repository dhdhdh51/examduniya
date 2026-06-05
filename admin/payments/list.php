<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Payments';
$active_menu = 'payments';

$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(p.txn_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_status !== '' && in_array($filter_status, ['pending', 'success', 'failed', 'refunded'], true)) {
    $where[] = "p.status = ?";
    $params[] = $filter_status;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p LEFT JOIN users u ON u.id = p.user_id $where_sql");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$pagination = paginate($total, $per_page, $page);

$stmt = $pdo->prepare(
    "SELECT p.id, p.txn_id, p.plan, p.amount, p.payment_gateway, p.status, p.created_at,
            u.name AS user_name, u.email AS user_email
     FROM payments p
     LEFT JOIN users u ON u.id = p.user_id
     $where_sql
     ORDER BY p.created_at DESC
     LIMIT $per_page OFFSET {$pagination['offset']}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue summary (successful payments only)
$revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success'")->fetchColumn();
$success_count = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='success'")->fetchColumn();

$status_badges = [
    'success'  => 'bg-success',
    'pending'  => 'bg-warning text-dark',
    'failed'   => 'bg-danger',
    'refunded' => 'bg-secondary',
];

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payments</h2>
    <div class="d-flex gap-2">
      <span class="badge bg-success fs-6">&#8377;<?= number_format($revenue, 2) ?> earned</span>
      <span class="badge bg-primary fs-6"><?= $success_count ?> successful</span>
      <span class="badge bg-secondary fs-6"><?= $total ?> total</span>
    </div>
  </div>

  <!-- Filters -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-6">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search txn id, name or email..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-4">
          <select name="status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <?php foreach (['success', 'pending', 'failed', 'refunded'] as $st): ?>
              <option value="<?= $st ?>" <?= $filter_status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th><th>Txn ID</th><th>User</th><th>Plan</th>
              <th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $p): ?>
            <tr>
              <td><?= $pagination['offset'] + $i + 1 ?></td>
              <td><code class="small"><?= htmlspecialchars($p['txn_id'] ?? '-') ?></code></td>
              <td>
                <div class="fw-semibold small"><?= htmlspecialchars($p['user_name'] ?? 'Unknown') ?></div>
                <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars($p['user_email'] ?? '') ?></div>
              </td>
              <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(ucfirst($p['plan'])) ?></span></td>
              <td>&#8377;<?= number_format((float)$p['amount'], 2) ?></td>
              <td class="text-uppercase small"><?= htmlspecialchars($p['payment_gateway'] ?? 'payu') ?></td>
              <td><span class="badge <?= $status_badges[$p['status']] ?? 'bg-secondary' ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td><?= format_date($p['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No payments found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($pagination['total_pages'] > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
      <?php if ($pagination['has_prev']): ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">Prev</a></li>
      <?php endif; ?>
      <?php for ($p = max(1,$page-2); $p <= min($pagination['total_pages'],$page+2); $p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
      <?php if ($pagination['has_next']): ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">Next</a></li>
      <?php endif; ?>
    </ul>
    <p class="text-center text-muted small">Showing <?= count($rows) ?> of <?= $total ?> payments</p>
  </nav>
  <?php endif; ?>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
