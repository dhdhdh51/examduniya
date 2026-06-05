<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'error' => 'CSRF invalid']);
    exit;
}

$comment_id = (int)($_POST['comment_id'] ?? 0);
$action     = $_POST['action'] ?? '';

if (!$comment_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE comments SET is_approved = 1 WHERE id = ?");
} else {
    // Reject: delete the comment
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
}
$stmt->execute([$comment_id]);

echo json_encode(['success' => true, 'action' => $action, 'comment_id' => $comment_id]);
