<?php
/**
 * Public site header — include at top of every public page
 * Requires: $page_title set before including, $pdo available
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
maintenance_mode_check();
require_once ROOT . '/includes/seo.php';
$site_name = get_setting('site_name') ?: 'Exam Duniya';
$site_desc = get_setting('site_description') ?: 'Get latest SSC, UPSC, Railway, Banking, Defence, UP Police and State Government job notifications, admit cards, results, syllabus, free mock tests and study material on Exam Duniya.';
$ga_id     = get_setting('google_analytics_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
/* No-FOUC theme init: apply saved theme before first paint (premium-3d) */
(function(){try{var t=localStorage.getItem('ed-theme');if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_render_head(); ?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php if (!empty($GLOBALS['seo_head_extra'])) { echo $GLOBALS['seo_head_extra']; } ?>

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
<link href="<?= asset('/assets/css/style.css') ?>" rel="stylesheet">

<!-- Premium 3D enhancement layer (progressive enhancement, additive) -->
<link href="<?= asset('/assets/css/premium-3d.css') ?>" rel="stylesheet">

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
