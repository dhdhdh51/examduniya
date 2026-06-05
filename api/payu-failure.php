<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$txnid = $_POST['txnid'] ?? $_GET['txnid'] ?? '';

if ($txnid) {
    $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE txn_id = ?");
    $stmt->execute([$txnid]);
}

$_SESSION['payment_error'] = 'Payment failed or was cancelled. Please try again.';
header('Location: /pages/tests/listing.php');
exit;
