<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$site_name  = get_setting('site_name') ?: 'GovExam Portal';
$page_title = 'Terms & Conditions';
$meta_desc  = 'Terms and Conditions of ' . $site_name;

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>
<main class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h1 class="fw-bold mb-4">Terms &amp; Conditions</h1>
      <p class="text-muted">Last updated: <?= date('F Y') ?></p>
      <p>By accessing and using <?= htmlspecialchars($site_name) ?>, you agree to these Terms &amp; Conditions. Please read them carefully.</p>
      <h5 class="fw-bold mt-4">Use of the Service</h5>
      <p>You agree to use the website lawfully and not to misuse, copy or redistribute content, including mock test questions, without permission.</p>
      <h5 class="fw-bold mt-4">Accounts</h5>
      <p>You are responsible for keeping your account credentials secure. Notifications and exam dates are provided for convenience; always verify details from the official source.</p>
      <h5 class="fw-bold mt-4">Payments &amp; Refunds</h5>
      <p>Premium features are billed as described at checkout. Payments are processed securely via our payment gateway. Refunds, if applicable, are handled on a case-by-case basis.</p>
      <h5 class="fw-bold mt-4">Disclaimer</h5>
      <p>Content is provided "as is" without warranties. We are not liable for any decisions made based on information published here.</p>
      <h5 class="fw-bold mt-4">Contact</h5>
      <p>Questions about these terms? Reach us via the <a href="/pages/contact.php">contact page</a>.</p>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
