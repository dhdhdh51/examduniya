<?php
/**
 * Google OAuth callback — exchange code, upsert user, set session
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate state to prevent CSRF
$state = $_GET['state'] ?? '';
if (empty($state) || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    header('Location: /auth/login.php?error=invalid_state');
    exit;
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: /auth/login.php?error=no_code');
    exit;
}

$client_id     = get_setting('google_client_id');
$client_secret = get_setting('google_client_secret');
$site_url      = get_setting('site_url');
$redirect_uri  = rtrim($site_url, '/') . '/auth/google-callback.php';

// Exchange code for tokens
$token_data = [
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code',
    'code'          => $code,
];

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($token_data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$token_response = curl_exec($ch);
$token_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($token_response === false || $token_http_code !== 200) {
    header('Location: /auth/login.php?error=token_exchange_failed');
    exit;
}

$token_json = json_decode($token_response, true);
$access_token = $token_json['access_token'] ?? '';

if (empty($access_token)) {
    header('Location: /auth/login.php?error=no_access_token');
    exit;
}

// Fetch user info from Google
$ch = curl_init('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . urlencode($access_token));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$user_response = curl_exec($ch);
$user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($user_response === false || $user_http_code !== 200) {
    header('Location: /auth/login.php?error=userinfo_failed');
    exit;
}

$google_user = json_decode($user_response, true);
$google_id = $google_user['id'] ?? '';
$email     = $google_user['email'] ?? '';
$name      = $google_user['name'] ?? '';
$avatar    = $google_user['picture'] ?? '';

if (empty($email) || empty($google_id)) {
    header('Location: /auth/login.php?error=incomplete_profile');
    exit;
}

// Upsert user: match by google_id or email
$stmt = $pdo->prepare(
    'INSERT INTO users (name, email, google_id, email_verified, avatar, role)
     VALUES (?, ?, ?, 1, ?, "user")
     ON DUPLICATE KEY UPDATE
       google_id      = VALUES(google_id),
       name           = VALUES(name),
       avatar         = VALUES(avatar),
       email_verified = 1'
);
$stmt->execute([$name, $email, $google_id, $avatar]);

// Fetch the final user record
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /auth/login.php?error=db_error');
    exit;
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];
$_SESSION['avatar']  = $user['avatar'];

$redirect = $_SESSION['redirect_after_login'] ?? '/pages/user/dashboard.php';
unset($_SESSION['redirect_after_login']);
header('Location: ' . $redirect);
exit;
