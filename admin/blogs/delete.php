<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/blogs/list.php');
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    die('CSRF token mismatch.');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /admin/blogs/list.php');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
$stmt->execute([$id]);

// Auto-regenerate sitemap
require_once ROOT . '/includes/sitemap-generator.php';
generate_sitemap($pdo);

header('Location: /admin/blogs/list.php?deleted=1');
exit;
