<?php
http_response_code(503);
header('Retry-After: 3600');
$site_name = function_exists('get_setting') ? (get_setting('site_name') ?: 'Exam Duniya') : 'Exam Duniya';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Under Maintenance — <?= htmlspecialchars($site_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#1e3a8a,#2563EB,#0ea5e9);min-height:100vh;display:flex;align-items:center;justify-content:center;color:#fff;}
.card{background:rgba(255,255,255,0.12);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.2);border-radius:24px;padding:3rem 2.5rem;text-align:center;max-width:480px;width:90%;}
.icon{font-size:4rem;margin-bottom:1.5rem;animation:spin 6s linear infinite;}
@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}
h1{font-size:2rem;font-weight:700;margin-bottom:1rem;}
p{font-size:1rem;opacity:0.85;line-height:1.7;margin-bottom:1.5rem;}
.badge{display:inline-block;background:rgba(255,255,255,0.2);border-radius:50px;padding:0.4rem 1.2rem;font-size:0.85rem;letter-spacing:0.5px;}
</style>
</head>
<body>
<div class="card">
  <div class="icon">&#9881;</div>
  <h1>Under Maintenance</h1>
  <p>We're upgrading our systems to serve you better. Please check back soon!</p>
  <div class="badge">We'll be back shortly</div>
</div>
</body>
</html>
