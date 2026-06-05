<?php
/**
 * AJAX: test a single AI provider by sending a tiny prompt via call_ai().
 * Admin only. CSRF protected. Returns JSON and stores last_test.
 */
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
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh.']);
    exit;
}

$key = trim((string)($_POST['provider_key'] ?? ''));
if ($key === '') {
    echo json_encode(['success' => false, 'error' => 'Missing provider key']);
    exit;
}

$result = call_ai('Reply with only the single word: CONNECTED', $key);

$reply = trim((string)($result['text'] ?? ''));
$out = [
    'success' => !empty($result['success']),
    'ms'      => $result['ms'] ?? 0,
    'reply'   => mb_substr($reply, 0, 60),
    'error'   => $result['error'] ?? null,
];

// Persist last-test status for the badge.
try {
    $note = $out['success'] ? ($out['ms'] . 'ms') : (string)$out['error'];
    $pdo->prepare("UPDATE ai_providers SET last_test = ? WHERE provider_key = ?")
        ->execute([json_encode(['ok' => $out['success'], 'at' => date('Y-m-d H:i:s'), 'note' => $note]), $key]);
} catch (Throwable $e) {
    // non-fatal
}

echo json_encode($out);
