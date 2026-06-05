<?php
/**
 * Google OAuth redirect — build auth URL and send user to Google
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_id = get_setting('google_client_id');
$site_url   = get_setting('site_url');

if (empty($client_id) || empty($site_url)) {
    header('Location: /auth/login.php?error=google_not_configured');
    exit;
}

$redirect_uri = rtrim($site_url, '/') . '/auth/google-callback.php';
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
