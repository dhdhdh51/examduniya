<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

$search  = '%' . $q . '%';
$results = [];

// Search notifications
try {
    $stmt = $pdo->prepare("SELECT title, slug, short_desc, category FROM notifications WHERE (title LIKE ? OR short_desc LIKE ?) LIMIT 5");
    $stmt->execute([$search, $search]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type'    => 'notification',
            'title'   => $row['title'],
            'url'     => exam_url($row['slug']),
            'excerpt' => excerpt($row['short_desc'] ?? '', 80),
            'badge'   => $row['category'],
        ];
    }
} catch (Exception $e) {
    // silently continue
}

// Search blogs
try {
    $stmt = $pdo->prepare("SELECT title, slug, category FROM blogs WHERE (title LIKE ? OR content LIKE ?) AND is_published = 1 LIMIT 5");
    $stmt->execute([$search, $search]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type'    => 'blog',
            'title'   => $row['title'],
            'url'     => blog_url($row['slug']),
            'excerpt' => '',
            'badge'   => $row['category'],
        ];
    }
} catch (Exception $e) {
    // silently continue
}

echo json_encode($results);
