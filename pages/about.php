<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$site_name  = get_setting('site_name') ?: 'GovExam Portal';
$page_title = 'About Us';
$meta_desc  = 'About ' . $site_name;

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>
<main class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h1 class="fw-bold mb-4">About <?= htmlspecialchars($site_name) ?></h1>
      <p class="lead text-muted"><?= htmlspecialchars($site_name) ?> helps aspirants stay on top of Government exam notifications and prepare with high-quality mock tests.</p>
      <p>We bring together the latest SSC, UPSC, Railway, Banking, Defence and State PSC notifications in one place, along with free and premium mock tests designed to mirror the real exam experience. Our blog shares preparation strategies, current affairs and study material to support your journey.</p>
      <p>Our mission is simple: make exam preparation accessible, organised and effective for every aspirant.</p>
      <div class="row g-3 mt-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center"><i class="fa-solid fa-bell fa-2x text-primary mb-2"></i><h6 class="fw-bold">Timely Notifications</h6><p class="small text-muted mb-0">Never miss an important exam date.</p></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center"><i class="fa-solid fa-file-pen fa-2x text-success mb-2"></i><h6 class="fw-bold">Realistic Mock Tests</h6><p class="small text-muted mb-0">Practice in an exam-like interface.</p></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center"><i class="fa-solid fa-newspaper fa-2x text-info mb-2"></i><h6 class="fw-bold">Helpful Blog</h6><p class="small text-muted mb-0">Strategies, tips and study material.</p></div></div></div>
      </div>
    </div>
  </div>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
