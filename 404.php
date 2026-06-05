<?php
http_response_code(404);
$site_name = (function_exists('get_setting') ? get_setting('site_name') : null) ?: 'GovExam Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 Not Found — <?= htmlspecialchars($site_name) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.error-card{text-align:center;max-width:520px;padding:3rem 2rem;}
.error-code{font-size:7rem;font-weight:800;color:#2563EB;line-height:1;}
</style>
</head>
<body>
<div class="error-card">
  <div class="error-code">404</div>
  <h2 class="fw-bold mt-3 mb-2">Page Not Found</h2>
  <p class="text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
  <div class="d-flex gap-3 justify-content-center">
    <a href="/" class="btn btn-primary">
      <i class="fa-solid fa-house me-2"></i>Go Home
    </a>
    <a href="/pages/exams/" class="btn btn-outline-primary">
      <i class="fa-solid fa-bell me-2"></i>Browse Exams
    </a>
  </div>
</div>
</body>
</html>
