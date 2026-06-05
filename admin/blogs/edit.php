<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Edit Blog Post';
$active_menu = 'blogs';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/blogs/list.php');
    exit;
}

$blog = $pdo->prepare("SELECT * FROM blogs WHERE id = ? LIMIT 1");
$blog->execute([$id]);
$blog = $blog->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    header('Location: /admin/blogs/list.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        die('CSRF token mismatch.');
    }

    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $tags        = trim($_POST['tags'] ?? '');
    $meta_title  = trim($_POST['meta_title'] ?? '');
    $meta_desc   = trim($_POST['meta_description'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $is_published= !empty($_POST['is_published']) ? 1 : 0;

    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($content)) $errors[] = 'Content is required.';

    $cover_image = $blog['featured_image'];
    if (!empty($_FILES['cover_image']['tmp_name'])) {
        $uploaded = upload_file($_FILES['cover_image'], 'blogs', ['jpg','jpeg','png','webp']);
        if ($uploaded === false) {
            $errors[] = 'Cover image upload failed. Only JPG/PNG/WebP are allowed.';
        } else {
            $cover_image = $uploaded;
        }
    }

    if (empty($errors)) {
        $published_at = $blog['published_at'];
        if ($is_published && !$blog['is_published']) {
            $published_at = date('Y-m-d H:i:s');
        }
        $excerpt_text = excerpt($content, 200);

        $stmt = $pdo->prepare(
            "UPDATE blogs SET title=?, content=?, excerpt=?, featured_image=?, category=?, tags=?,
             is_published=?, published_at=? WHERE id=?"
        );
        $stmt->execute([
            $title, $content, $excerpt_text, $cover_image,
            $category, $tags, $is_published, $published_at, $id
        ]);

        header('Location: /admin/blogs/list.php?success=1');
        exit;
    }

    $blog = array_merge($blog, [
        'title'=>$title,'category'=>$category,'tags'=>$tags,
        'content'=>$content,'is_published'=>$is_published,
    ]);
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Blog Post</h2>
    <a href="/admin/blogs/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i>Back to List
    </a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="<?= htmlspecialchars($blog['title']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category</label>
            <input type="text" name="category" class="form-control"
                   value="<?= htmlspecialchars($blog['category'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Tags</label>
            <input type="text" name="tags" class="form-control"
                   value="<?= htmlspecialchars($blog['tags'] ?? '') ?>" placeholder="tag1, tag2, tag3">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Cover Image</label>
            <input type="file" name="cover_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($blog['featured_image']): ?>
            <div class="form-text">
              Current: <img src="/uploads/blogs/<?= htmlspecialchars($blog['featured_image']) ?>"
                           height="40" class="ms-2 rounded">
            </div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="is_published" class="form-select">
              <option value="0" <?= !$blog['is_published']?'selected':'' ?>>Draft</option>
              <option value="1" <?= $blog['is_published']?'selected':'' ?>>Published</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Meta Title (SEO)</label>
            <input type="text" name="meta_title" class="form-control"
                   value="<?= htmlspecialchars($blog['title']) ?>" maxlength="160">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Meta Description (SEO)</label>
            <textarea name="meta_description" class="form-control" rows="2" maxlength="300"><?= htmlspecialchars($blog['excerpt'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Content <span class="text-danger">*</span></label>
            <textarea name="content" class="form-control" rows="15" required><?= htmlspecialchars($blog['content'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Update Post
            </button>
            <a href="/admin/blogs/list.php" class="btn btn-outline-secondary ms-2">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </div>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
