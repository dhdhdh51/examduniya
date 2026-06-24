<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$site_name  = get_setting('site_name') ?: 'Exam Duniya';
$site_url    = get_setting('site_url');
$contact_email = get_setting('smtp_from_email') ?: get_setting('smtp_username');
$page_title = 'Contact Us';
$meta_desc  = 'Get in touch with ' . $site_name;

$sent = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid session token. Please refresh and try again.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
            $error = 'Please fill in your name, a valid email, and a message.';
        } else {
            // Best-effort: email the site admin if SMTP is configured.
            if (!empty($contact_email)) {
                $body = '<p><strong>From:</strong> ' . htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ')</p>'
                      . '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
                @send_email($contact_email, 'Contact form — ' . $site_name, $body);
            }
            $sent = true;
        }
    }
}

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>
<main class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <h1 class="fw-bold mb-4">Contact Us</h1>
      <?php if ($sent): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Thanks for reaching out! We'll get back to you soon.</div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="card border-0 shadow-sm">
        <div class="card-body">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="mb-3">
            <label class="form-label">Your Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="message" rows="5" class="form-control" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-2"></i>Send Message</button>
        </div>
      </form>
      <?php if (!empty($contact_email)): ?>
        <p class="text-muted small mt-3">Or email us directly at <a href="mailto:<?= htmlspecialchars($contact_email) ?>"><?= htmlspecialchars($contact_email) ?></a></p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
