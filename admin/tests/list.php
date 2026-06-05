<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Mock Tests';
$active_menu = 'tests';

$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(title LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM mock_tests $where_sql");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$pagination = paginate($total, $per_page, $page);

$stmt = $pdo->prepare(
    "SELECT id, title, category, total_questions, duration_minutes, access_type,
            negative_marking, is_active, created_at
     FROM mock_tests $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET {$pagination['offset']}"
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
    <h2 class="mb-0"><i class="fas fa-file-pen me-2"></i>Mock Tests</h2>
    <div class="d-flex gap-2">
      <a href="/admin/tests/generate.php" class="btn btn-success btn-sm">
        <i class="fas fa-robot me-1"></i>AI Generate
      </a>
      <a href="/admin/tests/import.php" class="btn btn-outline-success btn-sm">
        <i class="fas fa-file-csv me-1"></i>Import CSV
      </a>
      <a href="/admin/tests/add.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i>Add New
      </a>
    </div>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">Test saved successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>
  <?php if ($deleted): ?>
  <div class="alert alert-info alert-dismissible fade show">Test deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <!-- Search -->
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-8">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search test title or category..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">Search</button>
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
              <th>#</th><th>Title</th><th>Category</th><th>Questions</th>
              <th>Duration</th><th>Type</th><th>Neg.Mark</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $t): ?>
            <tr>
              <td><?= $pagination['offset'] + $i + 1 ?></td>
              <td style="max-width:220px">
                <span title="<?= htmlspecialchars($t['title']) ?>">
                  <?= htmlspecialchars(mb_substr($t['title'], 0, 55)) ?><?= mb_strlen($t['title'])>55?'...':'' ?>
                </span>
              </td>
              <td><span class="badge bg-secondary"><?= htmlspecialchars($t['category'] ?? '') ?></span></td>
              <td><?= (int)$t['total_questions'] ?></td>
              <td><?= (int)$t['duration_minutes'] ?> min</td>
              <td>
                <span class="badge <?= $t['access_type']==='free'?'bg-success':'bg-warning text-dark' ?>">
                  <?= ucfirst(htmlspecialchars($t['access_type'])) ?>
                </span>
              </td>
              <td><?= $t['negative_marking'] ? '-' . $t['negative_marking'] : 'None' ?></td>
              <td>
                <span class="badge <?= $t['is_active']?'bg-success':'bg-secondary' ?>">
                  <?= $t['is_active']?'Active':'Inactive' ?>
                </span>
              </td>
              <td>
                <a href="/admin/tests/edit.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="/pages/tests/attempt.php?test_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-info me-1" target="_blank" title="Preview">
                  <i class="fas fa-eye"></i>
                </a>
                <form method="post" action="/admin/tests/delete.php" class="d-inline"
                      onsubmit="return confirm('Delete this test?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No tests found.</td></tr>
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
      <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>">Prev</a></li>
      <?php endif; ?>
      <?php for ($p = max(1,$page-2); $p <= min($pagination['total_pages'],$page+2); $p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
      <?php if ($pagination['has_next']): ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>">Next</a></li>
      <?php endif; ?>
    </ul>
    <p class="text-center text-muted small">Showing <?= count($rows) ?> of <?= $total ?> tests</p>
  </nav>
  <?php endif; ?>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
