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
        $excerpt_text = $meta_desc !== '' ? $meta_desc : excerpt($content, 200);
        $read_time    = max(1, (int)round(str_word_count(strip_tags($content)) / 200));

        // Try the full insert (with SEO columns); fall back gracefully if the
        // DB has not been upgraded yet.
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO blogs (title, slug, content, excerpt, featured_image, category, tags,
                 seo_title, meta_description, read_time_minutes,
                 author_id, is_published, published_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $title, $slug, $content, $excerpt_text, $cover_image,
                $category, $tags, ($meta_title ?: null), ($meta_desc ?: null), $read_time,
                $author_id ?: null, $is_published, $published_at
            ]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare(
                "INSERT INTO blogs (title, slug, content, excerpt, featured_image, category, tags,
                 author_id, is_published, published_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $title, $slug, $content, $excerpt_text, $cover_image,
                $category, $tags, $author_id ?: null, $is_published, $published_at
            ]);
        }
        if (function_exists('log_activity')) log_activity('blog_create', 'blogs', (int)$pdo->lastInsertId(), $title);

        // Auto-notify search engines about sitemap update
        require_once ROOT . '/includes/sitemap-generator.php';
        sitemap_notify($pdo);

        header('Location: /admin/blogs/list.php?success=1');
        exit;
    }
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
$blog = ['title'=>$_POST['title']??'','category'=>$_POST['category']??'','tags'=>$_POST['tags']??'',
         'meta_title'=>$_POST['meta_title']??'','meta_description'=>$_POST['meta_description']??'',
         'content'=>$_POST['content']??'','featured_image'=>'','is_published'=>$_POST['is_published']??0];
$form_action = '';
$submit_label = 'Save Post';
include ROOT . '/admin/blogs/_form.php';
require_once ROOT . '/includes/admin_footer.php';
