<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/seo.php';
require_once ROOT . '/includes/schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

seo_set([
    'title'       => 'Fact-Check Policy | Exam Duniya',
    'description' => 'Our fact-checking process: how Exam Duniya verifies exam dates, vacancies and official links against original recruitment-board sources before publishing.',
    'canonical'   => '/fact-check-policy/',
]);

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

schema_breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Fact-Check Policy', 'url' => '/fact-check-policy/'],
]);
?>
<main class="container my-5">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
      <li class="breadcrumb-item active">Fact-Check Policy</li>
    </ol>
  </nav>
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-3">Fact-Check Policy</h1>
      <p class="text-muted">Last updated: <?= date('d M Y') ?></p>
      <p>
        We take factual accuracy seriously, especially for dates, vacancies, eligibility and
        official links that aspirants rely on. This policy describes how we check facts.
      </p>

      <h2 class="h4 fw-bold mt-4">Primary sources first</h2>
      <p>
        Wherever possible, exam information is checked against the official notification PDF and
        the recruitment board's own website. We prefer primary sources over secondary reporting.
      </p>

      <h2 class="h4 fw-bold mt-4">What we verify</h2>
      <ul>
        <li>Application start and last dates</li>
        <li>Exam, admit card and result dates</li>
        <li>Vacancy count and eligibility summary</li>
        <li>Official website, notification PDF and apply links</li>
      </ul>

      <h2 class="h4 fw-bold mt-4">Last Verified date</h2>
      <p>
        Each notification page displays a <strong>Last Verified</strong> date and the official
        source it was checked against. If a detail cannot be confirmed, we mark it clearly
        rather than guessing.
      </p>

      <div class="alert alert-warning mt-3">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        Despite our checks, official boards may change details without notice. Always confirm
        on the official website before applying.
      </div>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
