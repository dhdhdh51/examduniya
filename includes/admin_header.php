<?php
/**
 * Admin-specific header — include at top of every admin page
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_admin();

$site_name = get_setting('site_name') ?: 'GovExam Portal';
$admin_name = $_SESSION['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(($admin_page_title ?? 'Admin') . ' — ' . $site_name . ' Admin') ?></title>
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= csrf_token() ?>">

<!-- Bootstrap 5.3 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome 6 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Inter Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Custom CSS -->
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
<div class="d-flex" id="adminWrapper">
