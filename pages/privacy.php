<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$site_name  = get_setting('site_name') ?: 'Exam Duniya';
$page_title = 'Privacy Policy';
$meta_desc  = 'Privacy Policy of ' . $site_name;

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>
<main class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h1 class="fw-bold mb-4">Privacy Policy</h1>
      <p class="text-muted">Last updated: <?= date('F Y') ?></p>
      <p>This Privacy Policy explains how <?= htmlspecialchars($site_name) ?> collects, uses and protects your information when you use our website.</p>
      <h5 class="fw-bold mt-4">Information We Collect</h5>
      <p>We collect the name and email address you provide when you register, along with usage data such as test attempts and scores needed to operate the service.</p>
      <h5 class="fw-bold mt-4">How We Use Information</h5>
      <p>Your information is used to provide and improve our services, send exam notifications you opt into, and process payments for premium features.</p>
      <h5 class="fw-bold mt-4">Cookies &amp; Analytics</h5>
      <p>We use session cookies for authentication and may use analytics tools to understand how the site is used. You can disable cookies in your browser settings.</p>
      <h5 class="fw-bold mt-4">Data Security</h5>
      <p>Passwords are stored using strong one-way hashing. We take reasonable measures to protect your data, but no method of transmission over the internet is fully secure.</p>
      <h5 class="fw-bold mt-4">Contact</h5>
      <p>For privacy questions, please reach us via the <a href="/pages/contact.php">contact page</a>.</p>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
