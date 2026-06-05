<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Comments';
$active_menu = 'comments';

$filter_blog = (int)($_GET['blog_id'] ?? 0);
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['c.is_approved = 0'];
$params = [];

if ($filter_blog > 0) {
    $where[] = 'c.blog_id = ?';
    $params[] = $filter_blog;
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$total_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM comments c $where_sql"
);
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$pagination = paginate($total, $per_page, $page);

$stmt = $pdo->prepare(
    "SELECT c.id, c.content, c.created_at, c.is_approved,
            b.title as blog_title, b.id as blog_id,
            u.name as user_name, u.email as user_email
     FROM comments c
     LEFT JOIN blogs b ON b.id = c.blog_id
     LEFT JOIN users u ON u.id = c.user_id
     $where_sql ORDER BY c.created_at DESC LIMIT $per_page OFFSET {$pagination['offset']}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-comments me-2"></i>Pending Comments
      <?php if ($total > 0): ?>
      <span class="badge bg-warning text-dark ms-2"><?= $total ?></span>
      <?php endif; ?>
    </h2>
  </div>

  <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:9999"></div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th><th>Blog Post</th><th>User</th>
              <th>Comment</th><th>Date</th><th>Actions</th>
            </tr>
          </thead>
          <tbody id="comments-tbody">
            <?php foreach ($rows as $i => $c): ?>
            <tr id="comment-row-<?= (int)$c['id'] ?>">
              <td><?= $pagination['offset'] + $i + 1 ?></td>
              <td style="max-width:150px">
                <a href="/blog/<?= (int)$c['blog_id'] ?>" target="_blank" class="text-decoration-none">
                  <?= htmlspecialchars(mb_substr($c['blog_title'] ?? '', 0, 40)) ?><?= mb_strlen($c['blog_title'] ?? '')>40?'...':'' ?>
                </a>
              </td>
              <td>
                <div><?= htmlspecialchars($c['user_name'] ?? 'Unknown') ?></div>
                <small class="text-muted"><?= htmlspecialchars($c['user_email'] ?? '') ?></small>
              </td>
              <td style="max-width:250px">
                <?= htmlspecialchars(mb_substr($c['content'], 0, 120)) ?><?= mb_strlen($c['content'])>120?'...':'' ?>
              </td>
              <td><?= format_date($c['created_at'], 'd M Y H:i') ?></td>
              <td>
                <button type="button" class="btn btn-sm btn-success me-1 approve-btn"
                        data-id="<?= (int)$c['id'] ?>" data-action="approve"
                        data-csrf="<?= csrf_token() ?>">
                  <i class="fas fa-check"></i> Approve
                </button>
                <button type="button" class="btn btn-sm btn-danger reject-btn"
                        data-id="<?= (int)$c['id'] ?>" data-action="reject"
                        data-csrf="<?= csrf_token() ?>">
                  <i class="fas fa-times"></i> Reject
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">
              <i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i>No pending comments!
            </td></tr>
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
      <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>">Prev</a></li>
      <?php endif; ?>
      <?php for ($p = max(1,$page-2); $p <= min($pagination['total_pages'],$page+2); $p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
      <?php if ($pagination['has_next']): ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>">Next</a></li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>

</div>
</div>
<?php
$extra_js = '<script>
function showToast(msg, type) {
    var tc = document.getElementById("toast-container");
    var t = document.createElement("div");
    t.className = "toast align-items-center text-white bg-" + type + " border-0 show";
    t.setAttribute("role", "alert");
    t.innerHTML = "<div class=\"d-flex\"><div class=\"toast-body\">" + msg + "</div>"
        + "<button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" onclick=\"this.closest(\'.toast\').remove()\"></button></div>";
    tc.appendChild(t);
    setTimeout(function() { t.remove(); }, 3000);
}

document.getElementById("comments-tbody").addEventListener("click", function(e) {
    var btn = e.target.closest(".approve-btn, .reject-btn");
    if (!btn) return;
    var id = btn.dataset.id;
    var action = btn.dataset.action;
    var csrf = btn.dataset.csrf;
    btn.disabled = true;

    fetch("/admin/ajax/approve-comment.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "csrf_token=" + encodeURIComponent(csrf) + "&comment_id=" + encodeURIComponent(id) + "&action=" + encodeURIComponent(action)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            var row = document.getElementById("comment-row-" + id);
            if (row) row.remove();
            showToast("Comment " + action + "d.", action === "approve" ? "success" : "secondary");
        } else {
            showToast(res.error || "Error", "danger");
            btn.disabled = false;
        }
    })
    .catch(function(e) {
        showToast("Request failed.", "danger");
        btn.disabled = false;
    });
});
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
