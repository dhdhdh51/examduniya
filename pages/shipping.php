<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$site_name     = get_setting('site_name') ?: 'Exam Duniya';
$contact_email = get_setting('smtp_from_email') ?: get_setting('smtp_username');
$page_title    = 'Shipping & Delivery Policy';
$meta_desc     = 'Shipping and Delivery Policy of ' . $site_name;

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>
<main class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-9 legal-page">
      <h1 class="mb-2">Shipping &amp; Delivery Policy</h1>
      <span class="legal-updated mb-3">Last updated: <?= date('F Y') ?></span>

      <p class="mt-4"><?= htmlspecialchars($site_name) ?> sells <strong>digital products and services only</strong>. There is no physical shipment involved in any purchase.</p>

      <h2>Digital Delivery</h2>
      <p>All premium plans, mock tests and study material are delivered electronically through your account on this website. No physical goods are shipped.</p>

      <h2>Delivery Time</h2>
      <p>Access is activated <strong>instantly</strong> after a successful payment. Your purchased content becomes available in your <a href="/pages/user/dashboard.php">dashboard</a> immediately. In rare cases of payment-gateway delay, activation may take up to a few minutes.</p>

      <h2>Delivery Confirmation</h2>
      <p>Once your payment is confirmed, your plan/role is upgraded automatically and you can start using the content right away. You may also receive a confirmation email if email notifications are enabled.</p>

      <h2>Didn't Get Access?</h2>
      <p>If your payment succeeded but access was not granted, please do not pay again. Contact us via the <a href="/pages/contact.php">contact page</a>
      <?php if (!empty($contact_email)): ?> or email <a href="mailto:<?= htmlspecialchars($contact_email) ?>"><?= htmlspecialchars($contact_email) ?></a><?php endif; ?>
      with your registered email and transaction/order ID, and we will resolve it promptly. See our <a href="/pages/refund.php">Refund &amp; Cancellation Policy</a> for related details.</p>

      <h2>Contact Us</h2>
      <p>For any delivery-related questions, reach us through the <a href="/pages/contact.php">contact page</a>.</p>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
