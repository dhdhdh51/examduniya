<?php
/**
 * Accept a "Report Correction" submission and store it in `corrections`.
 * Used by the Correction Policy page and the per-exam "Report Correction"
 * button. Returns JSON.
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_verify()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired session token. Please refresh and try again.']);
    exit;
}

$message = trim($_POST['message'] ?? '');
if (mb_strlen($message) < 5) {
    echo json_encode(['success' => false, 'error' => 'Please describe the correction.']);
    exit;
}

$entity_type = substr(trim($_POST['entity_type'] ?? 'general'), 0, 50);
$entity_id   = isset($_POST['entity_id']) && $_POST['entity_id'] !== '' ? (int)$_POST['entity_id'] : null;
$page_url    = substr(trim($_POST['page_url'] ?? ''), 0, 500);
$name        = substr(trim($_POST['name'] ?? ''), 0, 120);
$email       = substr(trim($_POST['email'] ?? ''), 0, 150);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}
$message = mb_substr($message, 0, 2000);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO corrections (entity_type, entity_id, page_url, name, email, message)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$entity_type, $entity_id, $page_url, $name, $email, $message]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save your report. Please try later.']);
}
