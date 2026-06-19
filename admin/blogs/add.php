<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Add Blog Post';
$active_menu = 'blogs';
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
    $author_id   = (int)($_SESSION['user_id'] ?? 0);

    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($content)) $errors[] = 'Content is required.';

    $cover_image = null;
    if (!empty($_FILES['cover_image']['tmp_name'])) {
        $uploaded = upload_file($_FILES['cover_image'], 'blogs', ['jpg','jpeg','png','webp']);
        if ($uploaded === false) {
            $errors[] = 'Cover image upload failed. Only JPG/PNG/WebP are allowed.';
        } else {
            $cover_image = $uploaded;
        }
    }

    if (empty($errors)) {
        $slug_base = slug($title);
        $slug = $slug_base;
        $check = $pdo->prepare("SELECT id FROM blogs WHERE slug = ? LIMIT 1");
        $check->execute([$slug]);
        if ($check->fetchColumn()) {
            $slug = $slug_base . '-' . time();
        }

        $published_at = $is_published ? date('Y-m-d H:i:s') : null;
        $excerpt_text = excerpt($content, 200);

        $stmt = $pdo->prepare(
            "INSERT INTO blogs (title, slug, content, excerpt, featured_image, category, tags,
             author_id, is_published, published_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $title, $slug, $content, $excerpt_text, $cover_image,
            $category, $tags, $author_id ?: null, $is_published, $published_at
        ]);

        // Auto-regenerate sitemap
        require_once ROOT . '/includes/sitemap-generator.php';
        generate_sitemap($pdo);

        header('Location: /admin/blogs/list.php?success=1');
        exit;
    }
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-plus me-2"></i>Add Blog Post</h2>
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
            <input type="text" name="title" id="blog-title" class="form-control"
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
            <div class="form-text">Slug: <code id="slug-preview"></code></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category</label>
            <input type="text" name="category" class="form-control"
                   value="<?= htmlspecialchars($_POST['category'] ?? '') ?>" placeholder="e.g. Current Affairs">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Tags</label>
            <input type="text" name="tags" class="form-control"
                   value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>" placeholder="tag1, tag2, tag3 (comma-separated)">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Cover Image</label>
            <input type="file" name="cover_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <div class="form-text">JPG/PNG/WebP only.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="is_published" class="form-select">
              <option value="0" <?= !isset($_POST['is_published'])||!$_POST['is_published']?'selected':'' ?>>Draft</option>
              <option value="1" <?= (!empty($_POST['is_published'])&&$_POST['is_published'])?'selected':'' ?>>Published</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Meta Title (SEO)</label>
            <input type="text" name="meta_title" class="form-control"
                   value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>" maxlength="160">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Meta Description (SEO)</label>
            <textarea name="meta_description" class="form-control" rows="2" maxlength="300"><?= htmlspecialchars($_POST['meta_description'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Content <span class="text-danger">*</span></label>
            <textarea name="content" class="form-control" rows="15" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Save Post
            </button>
            <a href="/admin/blogs/list.php" class="btn btn-outline-secondary ms-2">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </div>

</div>
</div>
<?php
$extra_js = '<script>
document.getElementById("blog-title").addEventListener("input", function() {
    var slug = this.value.toLowerCase().trim()
        .replace(/[^a-z0-9\s\-]/g, "")
        .replace(/[\s\-]+/g, "-")
        .replace(/^-+|-+$/g, "");
    document.getElementById("slug-preview").textContent = slug;
});
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
