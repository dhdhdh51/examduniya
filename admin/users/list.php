<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Users';
$active_menu = 'users';

$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$filter_role = $_GET['role'] ?? '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_role !== '') {
    $where[] = "role = ?";
    $params[] = $filter_role;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_sql");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$pagination = paginate($total, $per_page, $page);

$stmt = $pdo->prepare(
    "SELECT id, name, email, role, plan, plan_expiry, created_at
     FROM users $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET {$pagination['offset']}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_GET['success'] ?? '';
$deleted = $_GET['deleted'] ?? '';

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-users me-2"></i>Users</h2>
    <span class="badge bg-primary fs-6"><?= $total ?> total</span>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">User updated. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>
  <?php if ($deleted): ?>
  <div class="alert alert-info alert-dismissible fade show">User deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by name or email..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-4">
          <select name="role" class="form-select form-select-sm">
            <option value="">All Roles</option>
            <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>Admin</option>
            <option value="premium" <?= $filter_role==='premium'?'selected':'' ?>>Premium</option>
            <option value="free" <?= $filter_role==='free'?'selected':'' ?>>Free</option>
          </select>
        </div>
        <div class="col-md-3">
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
              <th>#</th><th>Name</th><th>Email</th><th>Role</th>
              <th>Plan</th><th>Plan Expiry</th><th>Joined</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $u): ?>
            <tr>
              <td><?= $pagination['offset'] + $i + 1 ?></td>
              <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge <?= $u['role']==='admin'?'bg-danger':($u['role']==='premium'?'bg-warning text-dark':'bg-secondary') ?>">
                  <?= htmlspecialchars($u['role']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $u['plan']==='free'?'bg-light text-dark border':'bg-success' ?>">
                  <?= htmlspecialchars($u['plan']) ?>
                </span>
              </td>
              <td><?= $u['plan_expiry'] ? format_date($u['plan_expiry']) : '-' ?></td>
              <td><?= format_date($u['created_at']) ?></td>
              <td>
                <a href="/admin/users/edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-secondary me-1">
                  <i class="fas fa-edit"></i>
                </a>
                <?php if ($u['role'] !== 'admin'): ?>
                <form method="post" action="/admin/users/delete.php" class="d-inline"
                      onsubmit="return confirm('Delete this user? This cannot be undone.')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
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
      <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&role=<?= urlencode($filter_role) ?>">Prev</a></li>
      <?php endif; ?>
      <?php for ($p = max(1,$page-2); $p <= min($pagination['total_pages'],$page+2); $p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&role=<?= urlencode($filter_role) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
      <?php if ($pagination['has_next']): ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&role=<?= urlencode($filter_role) ?>">Next</a></li>
      <?php endif; ?>
    </ul>
    <p class="text-center text-muted small">Showing <?= count($rows) ?> of <?= $total ?> users</p>
  </nav>
  <?php endif; ?>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
