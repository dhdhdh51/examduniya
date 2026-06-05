<?php
http_response_code(403);
$site_name = (function_exists('get_setting') ? get_setting('site_name') : null) ?: 'GovExam Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 Forbidden — <?= htmlspecialchars($site_name) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.error-card{text-align:center;max-width:480px;padding:3rem 2rem;}
.error-code{font-size:7rem;font-weight:800;color:#DC2626;line-height:1;}
</style>
</head>
<body>
<div class="error-card">
  <div class="error-code">403</div>
  <h2 class="fw-bold mt-3 mb-2">Access Forbidden</h2>
  <p class="text-muted mb-4">You don't have permission to access this page.</p>
  <a href="/" class="btn btn-primary">
    <i class="fa-solid fa-house me-2"></i>Go Home
  </a>
</div>
</body>
</html>
