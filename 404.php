<?php
http_response_code(404);

// This file is used both as an include (from detail pages) and directly via
// Apache ErrorDocument. Make it self-sufficient either way.
if (!defined('ROOT')) {
    define('ROOT', __DIR__);
}
if (!function_exists('get_setting')) {
    @require_once ROOT . '/config/db.php';
    @require_once ROOT . '/includes/functions.php';
}

$site_name = (function_exists('get_setting') ? get_setting('site_name') : null) ?: 'Exam Duniya';

$latest = [];
$cats   = [];
if (function_exists('get_setting') && isset($pdo)) {
    try {
        $stmt = $pdo->query(
            "SELECT title, slug FROM notifications
              WHERE (computed_status IS NULL OR computed_status <> 'closed')
              ORDER BY created_at DESC LIMIT 6"
        );
        $latest = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* ignore */ }
    if (function_exists('get_exam_categories')) {
        $cats = array_slice(array_values(array_filter(get_exam_categories(), fn($c) => strcasecmp($c, 'Other') !== 0)), 0, 8);
    }
}
$ex = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,follow">
<title>404 Not Found — <?= $ex($site_name) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;background:#f8fafc;}
.error-wrap{max-width:760px;margin:0 auto;padding:3rem 1.25rem;}
.error-code{font-size:6rem;font-weight:800;color:#2563EB;line-height:1;}
</style>
</head>
<body>
<div class="error-wrap text-center">
  <div class="error-code">404</div>
  <h1 class="h3 fw-bold mt-2 mb-2">Page Not Found</h1>
  <p class="text-muted mb-4">The page you're looking for doesn't exist or may have moved. Try searching or use the links below.</p>

  <form action="/pages/exams/listing.php" method="GET" class="mb-4">
    <div class="input-group input-group-lg shadow-sm" style="max-width:520px;margin:0 auto;">
      <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input type="search" name="q" class="form-control" placeholder="Search exams, results, admit cards...">
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
    <a href="/" class="btn btn-primary"><i class="fa-solid fa-house me-2"></i>Home</a>
    <a href="/exams/" class="btn btn-outline-primary"><i class="fa-solid fa-bell me-2"></i>Latest Notifications</a>
    <a href="/mock-tests/" class="btn btn-outline-primary"><i class="fa-solid fa-file-pen me-2"></i>Mock Tests</a>
  </div>

  <?php if (!empty($cats)): ?>
    <div class="mb-4">
      <h2 class="h6 text-uppercase text-muted mb-2">Popular Categories</h2>
      <div class="d-flex flex-wrap gap-2 justify-content-center">
        <?php foreach ($cats as $c): ?>
          <a href="/category/<?= $ex(rawurlencode($c)) ?>/" class="btn btn-sm btn-light border"><?= $ex($c === 'StatePSC' ? 'State PSC' : $c) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($latest)): ?>
    <div class="text-start mx-auto" style="max-width:520px;">
      <h2 class="h6 text-uppercase text-muted mb-2">Latest Notifications</h2>
      <ul class="list-group">
        <?php foreach ($latest as $n): ?>
          <li class="list-group-item">
            <a href="/exams/<?= $ex(rawurlencode($n['slug'])) ?>/" class="text-decoration-none"><?= $ex($n['title']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
