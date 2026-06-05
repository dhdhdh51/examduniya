<?php
/**
 * Admin middleware — must be included after config/db.php and includes/functions.php
 * Blocks non-admin users with a 403 response.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    $file = defined('ROOT') ? ROOT . '/403.php' : __DIR__ . '/../403.php';
    if (file_exists($file)) {
        include $file;
    } else {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>403 Forbidden</title>'
           . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
           . '<body class="d-flex align-items-center justify-content-center min-vh-100">'
           . '<div class="text-center"><h1 class="display-1 fw-bold text-danger">403</h1>'
           . '<h2 class="fw-bold">Access Forbidden</h2>'
           . '<p class="text-muted">You do not have permission to access this page.</p>'
           . '<a href="/" class="btn btn-primary">Go Home</a></div></body></html>';
    }
    exit;
}
