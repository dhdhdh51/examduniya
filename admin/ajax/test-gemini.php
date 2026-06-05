<?php
/**
 * AJAX: Test the Gemini API connection.
 * Sends a tiny prompt and reports success, model and response time.
 * Admin only. CSRF protected. Returns JSON.
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
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}

$api_key = get_setting('gemini_api_key');
$model   = get_setting('gemini_model') ?: 'gemini-2.0-flash';

if (empty($api_key)) {
    echo json_encode(['success' => false, 'error' => 'Gemini API key is not configured.']);
    exit;
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model)
     . ':generateContent?key=' . urlencode($api_key);

$payload = json_encode([
    'contents' => [['parts' => [['text' => 'Reply with only: CONNECTED']]]],
]);

$start = microtime(true);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

$elapsed_ms = (int)round((microtime(true) - $start) * 1000);

$result = [
    'success'          => false,
    'model'            => $model,
    'response_time_ms' => $elapsed_ms,
    'error'            => null,
    'reply'            => null,
];

if ($curl_err || $response === false) {
    $result['error'] = 'Connection error: ' . ($curl_err ?: 'no response');
} elseif ($http_code !== 200) {
    $data = json_decode($response, true);
    $result['error'] = $data['error']['message'] ?? ('HTTP ' . $http_code);
} else {
    $data  = json_decode($response, true);
    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $result['success'] = true;
    $result['reply']   = trim($reply);
}

// Persist last-test outcome for the status badge.
set_setting('gemini_last_test', json_encode([
    'ok'   => $result['success'],
    'at'   => date('Y-m-d H:i:s'),
    'note' => $result['success'] ? ($result['model'] . ' • ' . $elapsed_ms . 'ms') : $result['error'],
]));

echo json_encode($result);
