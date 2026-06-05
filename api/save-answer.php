<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$test_id = (int)($input['test_id'] ?? 0);
$answers = json_encode($input['answers'] ?? []);

if (!$test_id) {
    echo json_encode(['error' => 'Invalid test']);
    exit;
}

// Upsert: check if an incomplete attempt exists
$stmt = $pdo->prepare("SELECT id FROM user_attempts WHERE user_id = ? AND test_id = ? AND completed_at IS NULL LIMIT 1");
$stmt->execute([$_SESSION['user_id'], $test_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $stmt = $pdo->prepare("UPDATE user_attempts SET answers_json = ? WHERE id = ?");
    $stmt->execute([$answers, $existing['id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO user_attempts (user_id, test_id, answers_json) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $test_id, $answers]);
}

echo json_encode(['success' => true, 'saved_at' => date('H:i:s')]);
