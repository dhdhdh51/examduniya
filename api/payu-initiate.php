<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

if (!csrf_verify()) {
    die('Invalid request');
}

$test_id = (int)($_POST['test_id'] ?? 0);
$plan    = trim($_POST['plan'] ?? 'monthly');

if (!in_array($plan, ['monthly', 'yearly'], true)) {
    $plan = 'monthly';
}

// Fetch test to show as product info
if ($test_id) {
    $stmt = $pdo->prepare("SELECT title FROM mock_tests WHERE id = ? AND is_active = 1");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $test = null;
}

$monthly_price = (float)(get_setting('plan_monthly_price') ?: 99);
$yearly_price  = (float)(get_setting('plan_yearly_price') ?: 799);
$amount_raw    = ($plan === 'yearly') ? $yearly_price : $monthly_price;

$key  = get_setting('payu_merchant_key');
$salt = get_setting('payu_merchant_salt');
$mode = get_setting('payu_mode');
$payu_url = ($mode === 'live')
    ? 'https://secure.payu.in/_payment'
    : 'https://test.payu.in/_payment';

$txnid       = 'TXN' . uniqid() . rand(1000, 9999);
$amount      = number_format($amount_raw, 2, '.', '');
$productinfo = ($test ? htmlspecialchars($test['title']) : 'Exam Duniya Premium') . ' - ' . ucfirst($plan);
$firstname   = $_SESSION['name'] ?? 'User';
$email       = $_SESSION['email'] ?? '';
$phone       = '9999999999';
$site_url    = rtrim(get_setting('site_url') ?: 'https://example.com', '/');
$surl        = $site_url . '/api/payu-success.php';
$furl        = $site_url . '/api/payu-failure.php';

// Hash generation: key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||salt
$hash_string = "$key|$txnid|$amount|$productinfo|$firstname|$email|||||||||||$salt";
$hash        = strtolower(hash('sha512', $hash_string));

// Insert pending payment
$stmt = $pdo->prepare("INSERT INTO payments (user_id, txn_id, plan, amount, payment_gateway, status) VALUES (?, ?, ?, ?, 'payu', 'pending')");
$stmt->execute([$_SESSION['user_id'], $txnid, $plan, $amount]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Redirecting to Payment Gateway...</title>
<style>
body { font-family: Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8f9fa; }
.box { text-align: center; padding: 2rem; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
.spinner { border: 4px solid #e9ecef; border-top: 4px solid #2563EB; border-radius: 50%; width: 48px; height: 48px; animation: spin 1s linear infinite; margin: 1rem auto; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body onload="document.forms[0].submit()">
<div class="box">
    <div class="spinner"></div>
    <p>Redirecting to secure payment gateway...</p>
    <p style="font-size:.85rem;color:#666;">Please do not press Back or Refresh.</p>
</div>
<form method="post" action="<?= htmlspecialchars($payu_url) ?>">
    <input type="hidden" name="key"              value="<?= htmlspecialchars($key) ?>">
    <input type="hidden" name="txnid"            value="<?= htmlspecialchars($txnid) ?>">
    <input type="hidden" name="amount"           value="<?= htmlspecialchars($amount) ?>">
    <input type="hidden" name="productinfo"      value="<?= htmlspecialchars($productinfo) ?>">
    <input type="hidden" name="firstname"        value="<?= htmlspecialchars($firstname) ?>">
    <input type="hidden" name="email"            value="<?= htmlspecialchars($email) ?>">
    <input type="hidden" name="phone"            value="<?= htmlspecialchars($phone) ?>">
    <input type="hidden" name="surl"             value="<?= htmlspecialchars($surl) ?>">
    <input type="hidden" name="furl"             value="<?= htmlspecialchars($furl) ?>">
    <input type="hidden" name="hash"             value="<?= $hash ?>">
    <input type="hidden" name="service_provider" value="payu_paisa">
    <noscript>
        <button type="submit" style="margin-top:1rem;padding:.5rem 1.5rem;">Click here to proceed to PayU</button>
    </noscript>
</form>
</body>
</html>
