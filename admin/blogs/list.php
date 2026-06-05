<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Blogs';
$active_menu = 'blogs';

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

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM blogs $where_sql");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$pagination = paginate($total, $per_page, $page);

$stmt = $pdo->prepare(
    "SELECT b.id, b.title, b.category, b.is_published, b.views, b.created_at,
            u.name as author_name,
            (SELECT COUNT(*) FROM comments c WHERE c.blog_id=b.id AND c.is_approved=0) as pending_comments
     FROM blogs b
     LEFT JOIN users u ON u.id = b.author_id
     $where_sql ORDER BY b.created_at DESC LIMIT $per_page OFFSET {$pagination['offset']}"
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
    <h2 class="mb-0"><i class="fas fa-newspaper me-2"></i>Blog Posts</h2>
    <a href="/admin/blogs/add.php" class="btn btn-primary btn-sm">
      <i class="fas fa-plus me-1"></i>Add New
    </a>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">Blog post saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>
  <?php if ($deleted): ?>
  <div class="alert alert-info alert-dismissible fade show">Blog post deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
      <form method="get" class="row g-2">
        <div class="col-md-9">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by title or category..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
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
              <th>#</th><th>Title</th><th>Category</th><th>Author</th>
              <th>Status</th><th>Views</th><th>Comments</th><th>Date</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $b): ?>
            <tr>
              <td><?= $pagination['offset'] + $i + 1 ?></td>
              <td style="max-width:220px">
                <span title="<?= htmlspecialchars($b['title']) ?>">
                  <?= htmlspecialchars(mb_substr($b['title'], 0, 50)) ?><?= mb_strlen($b['title'])>50?'...':'' ?>
                </span>
              </td>
              <td><?= htmlspecialchars($b['category'] ?? '-') ?></td>
              <td><?= htmlspecialchars($b['author_name'] ?? '-') ?></td>
              <td>
                <span class="badge <?= $b['is_published']?'bg-success':'bg-secondary' ?>">
                  <?= $b['is_published']?'Published':'Draft' ?>
                </span>
              </td>
              <td><?= number_format((int)$b['views']) ?></td>
              <td>
                <?php if ($b['pending_comments'] > 0): ?>
                <a href="/admin/comments/list.php?blog_id=<?= (int)$b['id'] ?>" class="badge bg-warning text-dark text-decoration-none">
                  <?= (int)$b['pending_comments'] ?> pending
                </a>
                <?php else: ?>
                <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= format_date($b['created_at']) ?></td>
              <td>
                <a href="/admin/blogs/edit.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-secondary me-1">
                  <i class="fas fa-edit"></i>
                </a>
                <form method="post" action="/admin/blogs/delete.php" class="d-inline"
                      onsubmit="return confirm('Delete this blog post?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No blog posts found.</td></tr>
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
    <p class="text-center text-muted small">Showing <?= count($rows) ?> of <?= $total ?> posts</p>
  </nav>
  <?php endif; ?>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
