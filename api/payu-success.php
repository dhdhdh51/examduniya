<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$status      = $_POST['status']    ?? '';
$txnid       = $_POST['txnid']     ?? '';
$amount      = $_POST['amount']    ?? '';
$productinfo = $_POST['productinfo'] ?? '';
$firstname   = $_POST['firstname'] ?? '';
$email       = $_POST['email']     ?? '';
$mihpayid    = $_POST['mihpayid']  ?? '';
$posted_hash = $_POST['hash']      ?? '';

$key  = get_setting('payu_merchant_key');
$salt = get_setting('payu_merchant_salt');

// Reverse hash verification: salt|status||||||||||email|firstname|productinfo|amount|txnid|key
$reverse_hash_string = "$salt|$status|||||||||||$email|$firstname|$productinfo|$amount|$txnid|$key";
$calculated_hash     = strtolower(hash('sha512', $reverse_hash_string));

if ($calculated_hash === $posted_hash && $status === 'success') {
    // Fetch the pending payment
    $stmt = $pdo->prepare("SELECT id, user_id, plan FROM payments WHERE txn_id = ?");
    $stmt->execute([$txnid]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        // Update payment record with success status and gateway response
        $gateway_response = json_encode($_POST);
        $stmt = $pdo->prepare("UPDATE payments SET status = 'success', gateway_response = ? WHERE txn_id = ?");
        $stmt->execute([$gateway_response, $txnid]);

        // Update user role to premium
        $plan     = $payment['plan'];
        $user_id  = $payment['user_id'];
        $expiry   = ($plan === 'yearly')
            ? date('Y-m-d', strtotime('+1 year'))
            : date('Y-m-d', strtotime('+1 month'));

        $stmt = $pdo->prepare("UPDATE users SET role = 'premium', plan = ?, plan_expiry = ? WHERE id = ?");
        $stmt->execute([$plan, $expiry, $user_id]);

        // Update session if this is the current user
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$user_id) {
            $_SESSION['role'] = 'premium';
        }
    }

    $_SESSION['payment_success'] = 'Payment successful! You now have premium access.';
    header('Location: /pages/tests/listing.php');
    exit;
} else {
    // Mark payment as failed
    if ($txnid) {
        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE txn_id = ?");
        $stmt->execute([$txnid]);
    }
    header('Location: /api/payu-failure.php');
    exit;
}
