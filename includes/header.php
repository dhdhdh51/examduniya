<?php
/**
 * Public site header — include at top of every public page
 * Requires: $page_title set before including, $pdo available
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
maintenance_mode_check();
$site_name = get_setting('site_name') ?: 'GovExam Portal';
$site_desc = get_setting('site_description') ?: 'Government Exam Notifications, Mock Tests & Study Material';
$ga_id     = get_setting('google_analytics_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(($page_title ?? 'Home') . ' — ' . $site_name) ?></title>
<meta name="description" content="<?= htmlspecialchars($meta_desc ?? $site_desc) ?>">
<meta name="robots" content="index,follow">
<meta name="csrf-token" content="<?= csrf_token() ?>">

<!-- Open Graph -->
<meta property="og:title" content="<?= htmlspecialchars($page_title ?? $site_name) ?>">
<meta property="og:description" content="<?= htmlspecialchars($meta_desc ?? $site_desc) ?>">
<meta property="og:type" content="website">

<!-- Preconnect for CDNs -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">

<!-- Inter Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5.3 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome 6 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Custom CSS -->
<link href="/assets/css/style.css" rel="stylesheet">

<?php if ($ga_id): ?>
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($ga_id) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?= htmlspecialchars($ga_id) ?>');
</script>
<?php endif; ?>
</head>
<body>
