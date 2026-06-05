<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$site_name     = get_setting('site_name') ?: 'GovExam Portal';
$contact_email = get_setting('smtp_from_email') ?: get_setting('smtp_username');
$page_title    = 'Refund & Cancellation Policy';
$meta_desc     = 'Refund and Cancellation Policy of ' . $site_name;

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>
<main class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-9 legal-page">
      <h1 class="mb-2">Refund &amp; Cancellation Policy</h1>
      <span class="legal-updated mb-3">Last updated: <?= date('F Y') ?></span>

      <p class="mt-4">This Refund &amp; Cancellation Policy applies to all purchases of premium plans and paid mock tests made on <?= htmlspecialchars($site_name) ?>. By making a payment, you agree to the terms below.</p>

      <h2>Digital Products</h2>
      <p>All products sold on <?= htmlspecialchars($site_name) ?> are digital services (premium access, mock tests and study material). Access is granted instantly after a successful payment.</p>

      <h2>Cancellation</h2>
      <p>Because access to premium content is delivered immediately, an order generally cannot be cancelled once payment is completed and access has been unlocked. You may choose not to renew a subscription at any time before its renewal date.</p>

      <h2>Refund Eligibility</h2>
      <p>We want you to be satisfied. A refund may be considered in the following cases:</p>
      <ul>
        <li>You were charged more than once for the same order (duplicate payment).</li>
        <li>Payment was deducted but premium access was not activated due to a technical error on our side.</li>
        <li>The purchased content is materially inaccessible and our team is unable to resolve it within a reasonable time.</li>
      </ul>
      <p>Refunds are generally <strong>not</strong> provided for change of mind, lack of usage, or after a significant portion of the paid content has been consumed.</p>

      <h2>How to Request a Refund</h2>
      <p>To request a refund, email us at
        <?php if (!empty($contact_email)): ?>
          <a href="mailto:<?= htmlspecialchars($contact_email) ?>"><?= htmlspecialchars($contact_email) ?></a>
        <?php else: ?>
          our support address
        <?php endif; ?>
        (or use the <a href="/pages/contact.php">contact page</a>) within <strong>7 days</strong> of the transaction. Please include your registered email, transaction/order ID and the reason for the request.</p>

      <h2>Processing Time</h2>
      <p>Approved refunds are processed back to the original payment method via our payment gateway (PayU). It typically takes <strong>5–7 business days</strong> for the amount to reflect in your account, depending on your bank or card issuer.</p>

      <h2>Contact Us</h2>
      <p>For any questions about this policy, please reach us through the <a href="/pages/contact.php">contact page</a>
      <?php if (!empty($contact_email)): ?> or email <a href="mailto:<?= htmlspecialchars($contact_email) ?>"><?= htmlspecialchars($contact_email) ?></a><?php endif; ?>.</p>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
