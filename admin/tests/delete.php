<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/tests/list.php');
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    die('CSRF token mismatch.');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /admin/tests/list.php');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM mock_tests WHERE id = ?");
$stmt->execute([$id]);

header('Location: /admin/tests/list.php?deleted=1');
exit;
