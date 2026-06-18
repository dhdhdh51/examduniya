<?php
/**
 * AJAX: Test the Telegram bot connection.
 * Fetches the bot username via getMe and sends a test message to every chat ID.
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

$token        = get_setting('telegram_bot_token');
$chat_ids_raw = get_setting('telegram_chat_ids');

if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Telegram bot token is not configured.']);
    exit;
}
$chat_ids = array_values(array_filter(array_map('trim', explode(',', (string)$chat_ids_raw))));
if (empty($chat_ids)) {
    echo json_encode(['success' => false, 'error' => 'No chat IDs configured.']);
    exit;
}

/**
 * Small cURL helper for Telegram API calls.
 */
function tg_call($url, $payload = null)
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || $resp === false) {
        return ['ok' => false, 'description' => $err ?: 'no response'];
    }
    return json_decode($resp, true) ?: ['ok' => false, 'description' => 'invalid response'];
}

// 1) getMe — verify the token and grab the bot username.
$me = tg_call('https://api.telegram.org/bot' . urlencode($token) . '/getMe');
if (empty($me['ok'])) {
    $msg = $me['description'] ?? 'Invalid bot token';
    set_setting('telegram_last_test', json_encode(['ok' => false, 'at' => date('Y-m-d H:i:s'), 'note' => $msg]));
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
$bot_username = $me['result']['username'] ?? '';

// 2) Send the test message to every chat ID.
$message    = 'Test message from Exam Duniya Admin Panel — Telegram is connected!';
$sent_count = 0;
$errors     = [];

foreach ($chat_ids as $chat_id) {
    $res = tg_call(
        'https://api.telegram.org/bot' . urlencode($token) . '/sendMessage',
        ['chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'HTML']
    );
    if (!empty($res['ok'])) {
        $sent_count++;
    } else {
        $errors[] = $chat_id . ': ' . ($res['description'] ?? 'failed');
    }
}

$success = $sent_count > 0;

set_setting('telegram_last_test', json_encode([
    'ok'   => $success,
    'at'   => date('Y-m-d H:i:s'),
    'note' => $success
        ? ('@' . $bot_username . ' • ' . $sent_count . '/' . count($chat_ids) . ' groups')
        : implode('; ', $errors),
]));

echo json_encode([
    'success'      => $success,
    'bot_username' => $bot_username,
    'sent_count'   => $sent_count,
    'total'        => count($chat_ids),
    'error'        => $success ? null : implode('; ', $errors),
]);
