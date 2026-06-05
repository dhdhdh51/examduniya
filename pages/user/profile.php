<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();
require_login();

// Redirect to dashboard profile tab
header('Location: /pages/user/dashboard.php?tab=profile');
exit;
