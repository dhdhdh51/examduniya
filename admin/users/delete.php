<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/users/list.php');
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    die('CSRF token mismatch.');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /admin/users/list.php');
    exit;
}

// Safety: cannot delete admin users
$check = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$check->execute([$id]);
$row = $check->fetch(PDO::FETCH_ASSOC);
if (!$row || $row['role'] === 'admin') {
    header('Location: /admin/users/list.php?error=Cannot+delete+admin+user');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$id]);

header('Location: /admin/users/list.php?deleted=1');
exit;
