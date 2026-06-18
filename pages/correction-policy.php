<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/seo.php';
require_once ROOT . '/includes/schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

seo_set([
    'title'       => 'Correction Policy | Exam Duniya',
    'description' => 'Spotted an error in an exam notification or article? Learn how Exam Duniya handles correction requests and submit one in seconds.',
    'canonical'   => '/correction-policy/',
]);

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

schema_breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Correction Policy', 'url' => '/correction-policy/'],
]);
?>
<main class="container my-5">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
      <li class="breadcrumb-item active">Correction Policy</li>
    </ol>
  </nav>
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-3">Correction Policy</h1>
      <p class="text-muted">Last updated: <?= date('d M Y') ?></p>
      <p>
        We want our content to be accurate. If you believe a date, vacancy number, link or any
        other detail is wrong, please let us know. Credible correction requests are reviewed and,
        where confirmed against the official source, fixed promptly. The page's
        <strong>Last Verified</strong> date is updated when we make a correction.
      </p>

      <h2 class="h4 fw-bold mt-4">Report a correction</h2>
      <form id="correctionForm" class="card border-0 shadow-sm p-3 mt-3" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="entity_type" value="general">
        <input type="hidden" name="page_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Your name (optional)</label>
            <input type="text" name="name" class="form-control" maxlength="120">
          </div>
          <div class="col-md-6">
            <label class="form-label">Your email (optional)</label>
            <input type="email" name="email" class="form-control" maxlength="150">
          </div>
          <div class="col-12">
            <label class="form-label">Which page &amp; what is wrong? <span class="text-danger">*</span></label>
            <textarea name="message" class="form-control" rows="4" required maxlength="2000"
                      placeholder="Page URL or exam name, and the correct information with a source link if possible."></textarea>
          </div>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-flag me-2"></i>Submit Correction</button>
          <span id="correctionMsg" class="ms-2 small"></span>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
document.getElementById('correctionForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var form = e.target;
  var msg = document.getElementById('correctionMsg');
  msg.textContent = 'Sending...';
  fetch('/api/report-correction.php', { method: 'POST', body: new FormData(form) })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d && d.success) {
        msg.className = 'ms-2 small text-success';
        msg.textContent = 'Thank you! Your correction has been submitted.';
        form.reset();
      } else {
        msg.className = 'ms-2 small text-danger';
        msg.textContent = (d && d.error) ? d.error : 'Could not submit. Please try again.';
      }
    })
    .catch(function () {
      msg.className = 'ms-2 small text-danger';
      msg.textContent = 'Network error. Please try again later.';
    });
});
</script>
<?php require_once ROOT . '/includes/footer.php'; ?>
