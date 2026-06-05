<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Notifications';
$active_menu = 'notifications';

$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$filter_cat = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(title LIKE ? OR conducting_body LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_cat !== '') {
    $where[] = "category = ?";
    $params[] = $filter_cat;
}
if ($filter_status !== '') {
    $where[] = "status = ?";
    $params[] = $filter_status;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $where_sql");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$pagination = paginate($total, $per_page, $page);

$stmt = $pdo->prepare(
    "SELECT id, title, category, status, vacancies, last_date_apply, telegram_sent, created_at
     FROM notifications $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET {$pagination['offset']}"
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
    <h2 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h2>
    <a href="/admin/notifications/add.php" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i>Add New
    </a>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">Notification saved successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>
  <?php if ($deleted): ?>
  <div class="alert alert-info alert-dismissible fade show">Notification deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search title or body..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
          <select name="category" class="form-select form-select-sm">
            <option value="">All Categories</option>
            <?php foreach (['SSC','UPSC','Railway','Banking','StatePSC','Defence','Other'] as $cat): ?>
            <option value="<?= $cat ?>" <?= $filter_cat===$cat?'selected':'' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <?php foreach (['upcoming','active','result','admitcard'] as $st): ?>
            <option value="<?= $st ?>" <?= $filter_status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
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
              <th>#</th>
              <th>Title</th>
              <th>Category</th>
              <th>Status</th>
              <th>Vacancies</th>
              <th>Last Date</th>
              <th>Telegram</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $n):
              $status_colors = ['upcoming'=>'bg-info','active'=>'bg-success','result'=>'bg-warning','admitcard'=>'bg-primary'];
              $sc = $status_colors[$n['status']] ?? 'bg-secondary';
            ?>
            <tr>
              <td><?= $pagination['offset'] + $i + 1 ?></td>
              <td style="max-width:250px">
                <span title="<?= htmlspecialchars($n['title']) ?>">
                  <?= htmlspecialchars(mb_substr($n['title'], 0, 60)) ?><?= mb_strlen($n['title'])>60?'...':'' ?>
                </span>
              </td>
              <td><span class="badge bg-secondary"><?= htmlspecialchars($n['category']) ?></span></td>
              <td><span class="badge <?= $sc ?>"><?= htmlspecialchars($n['status']) ?></span></td>
              <td><?= $n['vacancies'] ? number_format((int)$n['vacancies']) : '-' ?></td>
              <td><?= $n['last_date_apply'] ? format_date($n['last_date_apply']) : '-' ?></td>
              <td><span class="badge <?= $n['telegram_sent']?'bg-success':'bg-light text-dark' ?>"><?= $n['telegram_sent']?'Yes':'No' ?></span></td>
              <td>
                <a href="/admin/notifications/edit.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <form method="post" action="/admin/notifications/delete.php" class="d-inline"
                      onsubmit="return confirm('Delete this notification?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No notifications found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($pagination['total_pages'] > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
      <?php if ($pagination['has_prev']): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&category=<?= urlencode($filter_cat) ?>&status=<?= urlencode($filter_status) ?>">Prev</a>
      </li>
      <?php endif; ?>
      <?php for ($p = max(1,$page-2); $p <= min($pagination['total_pages'],$page+2); $p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&category=<?= urlencode($filter_cat) ?>&status=<?= urlencode($filter_status) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
      <?php if ($pagination['has_next']): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&category=<?= urlencode($filter_cat) ?>&status=<?= urlencode($filter_status) ?>">Next</a>
      </li>
      <?php endif; ?>
    </ul>
    <p class="text-center text-muted small">Showing <?= count($rows) ?> of <?= $total ?> notifications</p>
  </nav>
  <?php endif; ?>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
