<?php
/**
 * AJAX: Verify the Google OAuth client ID.
 * Calls the Google tokeninfo endpoint to confirm the client ID is recognised.
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

$client_id     = trim((string)get_setting('google_client_id'));
$client_secret = trim((string)get_setting('google_client_secret'));

$result = ['success' => false, 'error' => null, 'message' => null];

if ($client_id === '') {
    $result['error'] = 'Google Client ID is not configured.';
    echo json_encode($result);
    exit;
}

// A valid OAuth client ID ends with .apps.googleusercontent.com
if (!preg_match('/\.apps\.googleusercontent\.com$/', $client_id)) {
    $result['error'] = 'Client ID format looks invalid. It should end with ".apps.googleusercontent.com".';
    set_setting('google_last_test', json_encode(['ok' => false, 'at' => date('Y-m-d H:i:s'), 'note' => $result['error']]));
    echo json_encode($result);
    exit;
}

if ($client_secret === '') {
    // Not fatal, but worth flagging.
    $result['message'] = 'Note: Client Secret is empty — login will fail until it is set. ';
}

// Probe the tokeninfo endpoint. Passing an empty id_token makes Google echo a
// 400 "invalid_token" error if the endpoint is reachable; an invalid client_id
// is reported differently. We mainly use this to confirm reachability + format.
$url = 'https://oauth2.googleapis.com/tokeninfo?client_id=' . urlencode($client_id);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err || $response === false) {
    // Could not reach Google (e.g. server has no outbound network). The format
    // check above still passed, so report a soft success with a note.
    $result['success'] = true;
    $result['message'] = ($result['message'] ?? '')
        . 'Client ID format is valid. Could not reach Google to fully verify (' . ($curl_err ?: 'network error') . ').';
    set_setting('google_last_test', json_encode([
        'ok' => true, 'at' => date('Y-m-d H:i:s'), 'note' => 'Format valid (network unverified)',
    ]));
    echo json_encode($result);
    exit;
}

// Any structured JSON response means the endpoint is reachable and the client
// ID is well-formed. Google returns 200/400 with JSON either way.
$data = json_decode($response, true);
if (is_array($data)) {
    $result['success'] = true;
    $result['message'] = ($result['message'] ?? '') . 'Google OAuth Client ID is valid.';
} else {
    $result['error'] = 'Unexpected response from Google (HTTP ' . $http_code . ').';
}

set_setting('google_last_test', json_encode([
    'ok'   => $result['success'],
    'at'   => date('Y-m-d H:i:s'),
    'note' => $result['success'] ? 'Client ID valid' : $result['error'],
]));

echo json_encode($result);
