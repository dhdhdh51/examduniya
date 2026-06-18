<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/seo.php';
require_once ROOT . '/includes/schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

seo_set([
    'title'       => 'Editorial Policy | Exam Duniya',
    'description' => 'How Exam Duniya researches, writes, reviews and updates government exam content to keep it accurate, useful and trustworthy for aspirants.',
    'canonical'   => '/editorial-policy/',
]);

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

schema_breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Editorial Policy', 'url' => '/editorial-policy/'],
]);
?>
<main class="container my-5">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
      <li class="breadcrumb-item active">Editorial Policy</li>
    </ol>
  </nav>
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-3">Editorial Policy</h1>
      <p class="text-muted">Last updated: <?= date('d M Y') ?></p>
      <p>
        Exam Duniya is committed to publishing accurate, clear and useful information about
        government exams. This policy explains how we create and maintain our content.
      </p>

      <h2 class="h4 fw-bold mt-4">Sourcing</h2>
      <p>
        Exam details are based on official notifications and recruitment board websites.
        Where we summarise a notification, we link to the original official source so readers
        can verify the details themselves.
      </p>

      <h2 class="h4 fw-bold mt-4">Review &amp; updates</h2>
      <p>
        Posts are reviewed for accuracy before publishing. Time-sensitive posts (notifications,
        admit cards, results) are updated as the conducting body releases new information, and
        each carries a visible <strong>Last Verified</strong> date.
      </p>

      <h2 class="h4 fw-bold mt-4">Independence</h2>
      <p>
        Exam Duniya is an independent platform and is not affiliated with any government
        recruitment board. We do not publish paid notifications disguised as official updates.
      </p>

      <h2 class="h4 fw-bold mt-4">Corrections</h2>
      <p>
        If you find an error, please tell us through our
        <a href="/correction-policy/">Correction Policy</a> or the
        <a href="/contact/">contact page</a>. We act on credible correction requests promptly.
      </p>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
