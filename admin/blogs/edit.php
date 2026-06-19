<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Edit Blog Post';
$active_menu = 'blogs';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/blogs/list.php'); exit; }

$blog = $pdo->prepare("SELECT * FROM blogs WHERE id = ? LIMIT 1");
$blog->execute([$id]);
$blog = $blog->fetch(PDO::FETCH_ASSOC);
if (!$blog) { header('Location: /admin/blogs/list.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { die('CSRF token mismatch.'); }

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
        $excerpt_text = $meta_desc !== '' ? $meta_desc : excerpt($content, 200);
        $read_time    = max(1, (int)round(str_word_count(strip_tags($content)) / 200));

        try {
            $stmt = $pdo->prepare(
                "UPDATE blogs SET title=?, content=?, excerpt=?, featured_image=?, category=?, tags=?,
                 seo_title=?, meta_description=?, read_time_minutes=?,
                 is_published=?, published_at=? WHERE id=?"
            );
            $stmt->execute([
                $title, $content, $excerpt_text, $cover_image, $category, $tags,
                ($meta_title ?: null), ($meta_desc ?: null), $read_time,
                $is_published, $published_at, $id
            ]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare(
                "UPDATE blogs SET title=?, content=?, excerpt=?, featured_image=?, category=?, tags=?,
                 is_published=?, published_at=? WHERE id=?"
            );
            $stmt->execute([
                $title, $content, $excerpt_text, $cover_image, $category, $tags,
                $is_published, $published_at, $id
            ]);
        }
        if (function_exists('log_activity')) log_activity('blog_update', 'blogs', $id, $title);

        header('Location: /admin/blogs/list.php?success=1');
        exit;
    }

    $blog = array_merge($blog, [
        'title'=>$title,'category'=>$category,'tags'=>$tags,'content'=>$content,
        'seo_title'=>$meta_title,'meta_description'=>$meta_desc,'is_published'=>$is_published,
    ]);
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
$blog['meta_title'] = $blog['seo_title'] ?? $blog['title'];
$form_action = '';
$submit_label = 'Update Post';
include ROOT . '/admin/blogs/_form.php';
require_once ROOT . '/includes/admin_footer.php';
