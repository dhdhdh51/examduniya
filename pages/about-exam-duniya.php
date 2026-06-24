<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/seo.php';
require_once ROOT . '/includes/schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$site_name = get_setting('site_name') ?: 'Exam Duniya';

seo_set([
    'title'       => 'About Exam Duniya — Govt Exam Notifications, Mock Tests & Results',
    'description' => 'Exam Duniya provides verified government exam notifications, admit cards, results, syllabus, mock tests and study material for SSC, UPSC, Railway, Banking, Defence and State exams.',
    'canonical'   => '/about-exam-duniya/',
    'robots'      => 'index,follow',
]);

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

schema_breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'About Exam Duniya', 'url' => '/about-exam-duniya/'],
]);
?>
<main class="container my-5">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
      <li class="breadcrumb-item active">About Exam Duniya</li>
    </ol>
  </nav>

  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-3">About Exam Duniya</h1>
      <p class="lead text-muted">
        Exam Duniya is an independent education platform that brings government exam
        notifications, admit cards, results, syllabus, mock tests and study resources
        together in one trustworthy place.
      </p>

      <h2 class="h4 fw-bold mt-4">What we do</h2>
      <p>
        We publish the latest recruitment updates for SSC, UPSC, Railway, Banking, Defence,
        UP Police, UPSSSC, State PSC and other government exams. For every post we aim to
        provide important dates, vacancy details, eligibility, application fees, the official
        notification link and an application link, so aspirants can act quickly and correctly.
      </p>

      <h2 class="h4 fw-bold mt-4">How we verify information</h2>
      <p>
        Information is verified from official recruitment board websites whenever possible.
        Each notification page shows a <strong>Last Verified</strong> date and links to the
        original official source. We update posts as new admit cards, answer keys and results
        are released by the conducting bodies.
      </p>

      <div class="alert alert-warning mt-3">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <strong>Always verify before applying.</strong> Recruitment details can change at
        short notice. Please confirm important information on the official website of the
        relevant recruitment board before applying or paying any fee.
      </div>

      <h2 class="h4 fw-bold mt-4">Our commitment</h2>
      <ul>
        <li>No fake vacancies, fake dates, or misleading official links.</li>
        <li>Clear status labels — Open, Upcoming, Admit Card, Result, Closed, Exam Completed.</li>
        <li>Free mock tests and study material to support genuine preparation.</li>
        <li>A simple way to <a href="/contact/">report a correction</a> if you spot an error.</li>
      </ul>

      <p class="mt-4">
        Read our <a href="/editorial-policy/">Editorial Policy</a>,
        <a href="/fact-check-policy/">Fact-Check Policy</a> and
        <a href="/correction-policy/">Correction Policy</a> to understand how we work.
      </p>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
