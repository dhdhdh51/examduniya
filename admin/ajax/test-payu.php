<?php
/**
 * AJAX: Verify PayU credentials look valid.
 * Generates a sample hash and checks key/salt format + mode.
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

$key  = trim((string)get_setting('payu_merchant_key'));
$salt = trim((string)get_setting('payu_merchant_salt'));
$mode = get_setting('payu_mode') === 'live' ? 'live' : 'test';

$key_length  = strlen($key);
$salt_length = strlen($salt);

$result = [
    'success'     => false,
    'mode'        => $mode,
    'key_length'  => $key_length,
    'salt_length' => $salt_length,
    'warning'     => null,
    'message'     => null,
    'error'       => null,
];

if ($key === '' || $salt === '') {
    $result['error'] = 'Merchant key and salt are both required.';
    set_setting('payu_last_test', json_encode(['ok' => false, 'at' => date('Y-m-d H:i:s'), 'note' => $result['error']]));
    echo json_encode($result);
    exit;
}

// Basic format sanity checks (PayU keys ~6-12 chars, salts ~10-64 chars).
if ($key_length < 4 || $key_length > 40) {
    $result['error'] = 'Merchant key length looks invalid (' . $key_length . ' chars).';
    set_setting('payu_last_test', json_encode(['ok' => false, 'at' => date('Y-m-d H:i:s'), 'note' => $result['error']]));
    echo json_encode($result);
    exit;
}
if ($salt_length < 8 || $salt_length > 64) {
    $result['error'] = 'Merchant salt length looks invalid (' . $salt_length . ' chars).';
    set_setting('payu_last_test', json_encode(['ok' => false, 'at' => date('Y-m-d H:i:s'), 'note' => $result['error']]));
    echo json_encode($result);
    exit;
}

// Generate a sample request hash exactly as the gateway expects it.
$txnid       = 'TEST' . time();
$amount      = '1.00';
$productinfo = 'Credential Test';
$firstname   = 'Admin';
$email       = 'admin@example.com';
$hash_string = $key . '|' . $txnid . '|' . $amount . '|' . $productinfo . '|'
             . $firstname . '|' . $email . '|||||||||||' . $salt;
$sample_hash = strtolower(hash('sha512', $hash_string));

$result['success']     = true;
$result['sample_hash'] = substr($sample_hash, 0, 24) . '…';

if ($mode === 'live') {
    $result['warning'] = 'You are in LIVE mode — real payments will be processed.';
    $result['message'] = 'PayU credentials look valid — LIVE Mode Active.';
} else {
    $result['message'] = 'PayU credentials look valid (Test Mode).';
}

set_setting('payu_last_test', json_encode([
    'ok'   => true,
    'at'   => date('Y-m-d H:i:s'),
    'note' => $result['message'],
]));

echo json_encode($result);
