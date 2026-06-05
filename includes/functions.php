<?php
if (!defined('ROOT')) die('Direct access not allowed');

require_once ROOT . '/includes/PHPMailer/Exception.php';
require_once ROOT . '/includes/PHPMailer/SMTP.php';
require_once ROOT . '/includes/PHPMailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Build a versioned URL for a local asset (cache-busting).
 * Appends ?v=<file-modified-time> so browsers always fetch the latest
 * file after a deploy, without needing a manual hard refresh.
 *
 * @param string $path Web path, e.g. "/assets/js/main.js"
 * @return string
 */
function asset($path)
{
    $file = ROOT . '/' . ltrim($path, '/');
    $ver  = @filemtime($file);
    return $path . '?v=' . ($ver ?: '1');
}

/**
 * Retrieve a setting value from the settings table with static cache
 *
 * @param string $key Setting key
 * @return string|null
 */
function get_setting($key)
{
    static $cache = [];
    global $pdo;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $value = $row ? $row['setting_value'] : null;
    $cache[$key] = $value;
    return $value;
}

/**
 * Update a setting value in the settings table
 *
 * @param string $key   Setting key
 * @param string $value New value
 * @return bool
 */
function set_setting($key, $value)
{
    global $pdo;

    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    return $stmt->execute([$key, $value]);
}

/**
 * Call Google Gemini API with a prompt
 *
 * @param string $prompt Text prompt
 * @return string Response text or empty string on failure
 */
function call_gemini($prompt)
{
    $api_key = get_setting('gemini_api_key');
    $model = get_setting('gemini_model') ?: 'gemini-3.5-flash';

    if (empty($api_key)) {
        return '';
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model)
        . ':generateContent?key=' . urlencode($api_key);

    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $http_code !== 200) {
        return '';
    }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return $text;
}

/* ============================================================
   Multi-provider AI router (Gemini / OpenAI / Anthropic ...)
   Reads providers from the ai_providers table.
   ============================================================ */

/**
 * Fetch a provider row by key, or the default/first-enabled provider.
 *
 * @param string|null $key provider_key, or null for the default
 * @return array|null
 */
function get_ai_provider($key = null)
{
    global $pdo;
    try {
        if ($key) {
            $stmt = $pdo->prepare("SELECT * FROM ai_providers WHERE provider_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
        // Prefer the enabled default; fall back to any enabled provider.
        $row = $pdo->query(
            "SELECT * FROM ai_providers
             WHERE enabled = 1
             ORDER BY is_default DESC, sort_order ASC
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Call any configured AI provider with a text prompt.
 *
 * @param string      $prompt     The prompt text
 * @param string|null $providerKey Specific provider_key, or null for default
 * @return array  ['success'=>bool, 'text'=>string, 'error'=>?string, 'provider'=>string, 'ms'=>int]
 */
function call_ai($prompt, $providerKey = null)
{
    $p = get_ai_provider($providerKey);
    if (!$p) {
        return ['success' => false, 'text' => '', 'error' => 'No AI provider is configured/enabled.', 'provider' => '', 'ms' => 0];
    }
    if (empty($p['api_key']) && $p['api_type'] !== 'gemini') {
        return ['success' => false, 'text' => '', 'error' => $p['name'] . ' API key is not set.', 'provider' => $p['provider_key'], 'ms' => 0];
    }
    if (empty($p['api_key'])) {
        return ['success' => false, 'text' => '', 'error' => $p['name'] . ' API key is not set.', 'provider' => $p['provider_key'], 'ms' => 0];
    }

    $start = microtime(true);
    switch ($p['api_type']) {
        case 'gemini':    $res = ai_call_gemini($p, $prompt);    break;
        case 'anthropic': $res = ai_call_anthropic($p, $prompt); break;
        case 'openai':
        default:          $res = ai_call_openai($p, $prompt);    break;
    }
    $res['provider'] = $p['provider_key'];
    $res['ms'] = (int) round((microtime(true) - $start) * 1000);
    return $res;
}

/**
 * Low-level cURL POST returning [body, http_code, curl_error].
 */
function ai_http_post($url, $payload, array $headers)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$body, $code, $err];
}

/** Gemini (Google Generative Language API). */
function ai_call_gemini($p, $prompt)
{
    $base  = rtrim($p['endpoint'] ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
    $model = $p['model'] ?: 'gemini-3.5-flash';
    $url   = $base . '/models/' . urlencode($model) . ':generateContent?key=' . urlencode($p['api_key']);

    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
    [$body, $code, $err] = ai_http_post($url, $payload, ['Content-Type: application/json']);

    if ($err || $body === false) {
        return ['success' => false, 'text' => '', 'error' => 'Connection error: ' . ($err ?: 'no response')];
    }
    $data = json_decode($body, true);
    if ($code !== 200) {
        return ['success' => false, 'text' => '', 'error' => $data['error']['message'] ?? ('HTTP ' . $code)];
    }
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return ['success' => true, 'text' => trim($text), 'error' => null];
}

/** OpenAI-compatible chat completions (OpenAI, DeepSeek, Grok, OpenRouter, Groq...). */
function ai_call_openai($p, $prompt)
{
    $base = rtrim($p['endpoint'] ?: 'https://api.openai.com/v1', '/');
    $url  = $base . '/chat/completions';
    $payload = json_encode([
        'model'    => $p['model'] ?: 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ]);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $p['api_key'],
    ];
    [$body, $code, $err] = ai_http_post($url, $payload, $headers);

    if ($err || $body === false) {
        return ['success' => false, 'text' => '', 'error' => 'Connection error: ' . ($err ?: 'no response')];
    }
    $data = json_decode($body, true);
    if ($code !== 200) {
        return ['success' => false, 'text' => '', 'error' => $data['error']['message'] ?? ('HTTP ' . $code)];
    }
    $text = $data['choices'][0]['message']['content'] ?? '';
    return ['success' => true, 'text' => trim($text), 'error' => null];
}

/** Anthropic Messages API (Claude). */
function ai_call_anthropic($p, $prompt)
{
    $base = rtrim($p['endpoint'] ?: 'https://api.anthropic.com/v1', '/');
    $url  = $base . '/messages';
    $payload = json_encode([
        'model'      => $p['model'] ?: 'claude-3-5-sonnet-latest',
        'max_tokens' => 4096,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $p['api_key'],
        'anthropic-version: 2023-06-01',
    ];
    [$body, $code, $err] = ai_http_post($url, $payload, $headers);

    if ($err || $body === false) {
        return ['success' => false, 'text' => '', 'error' => 'Connection error: ' . ($err ?: 'no response')];
    }
    $data = json_decode($body, true);
    if ($code !== 200) {
        return ['success' => false, 'text' => '', 'error' => $data['error']['message'] ?? ('HTTP ' . $code)];
    }
    $text = $data['content'][0]['text'] ?? '';
    return ['success' => true, 'text' => trim($text), 'error' => null];
}

/**
 * Build a normalised "fingerprint" of a question for duplicate detection.
 * Lowercases the question text and strips everything except letters/digits,
 * so minor punctuation/spacing/case differences still count as duplicates.
 *
 * @param array|string $q A question array (uses ['question']) or raw text
 * @return string
 */
function question_fingerprint($q)
{
    $text = is_array($q) ? ($q['question'] ?? '') : (string) $q;
    $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    $text = preg_replace('/[^a-z0-9\x{0900}-\x{097F}]+/u', '', $text); // keep a-z,0-9 and Devanagari
    return trim((string) $text);
}

/**
 * Remove duplicate questions from a list (by fingerprint), optionally also
 * excluding any whose fingerprint is in $existingFingerprints.
 *
 * @param array $questions            list of question arrays
 * @param array $existingFingerprints map of fingerprint => true to exclude
 * @return array [filtered_questions, $duplicates_removed_count]
 */
function dedupe_questions(array $questions, array $existingFingerprints = [])
{
    $seen = $existingFingerprints;
    $out  = [];
    $dupes = 0;
    foreach ($questions as $q) {
        if (!is_array($q) || empty($q['question'])) {
            continue;
        }
        $fp = question_fingerprint($q);
        if ($fp === '' || isset($seen[$fp])) {
            $dupes++;
            continue;
        }
        $seen[$fp] = true;
        $out[] = $q;
    }
    return [$out, $dupes];
}

/**
 * Send a Telegram message to all configured chat IDs
 *
 * @param string $message HTML or plain text message
 * @return bool True if at least one send succeeded
 */
function send_telegram($message)
{
    $bot_token = get_setting('telegram_bot_token');
    $chat_ids_raw = get_setting('telegram_chat_ids');

    if (empty($bot_token) || empty($chat_ids_raw)) {
        return false;
    }

    $chat_ids = array_filter(array_map('trim', explode(',', $chat_ids_raw)));
    if (empty($chat_ids)) {
        return false;
    }

    $success = false;
    foreach ($chat_ids as $chat_id) {
        $url = 'https://api.telegram.org/bot' . urlencode($bot_token) . '/sendMessage';

        $payload = json_encode([
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $success = true;
        }
    }

    return $success;
}

/**
 * Send email using PHPMailer via SMTP settings from DB
 *
 * @param string $to        Recipient email address
 * @param string $subject   Email subject
 * @param string $html_body HTML body
 * @return bool
 */
function send_email($to, $subject, $html_body)
{
    $host       = get_setting('smtp_host');
    $port       = (int)(get_setting('smtp_port') ?: 587);
    $username   = get_setting('smtp_username');
    $password   = get_setting('smtp_password');
    $from_email = get_setting('smtp_from_email');
    $from_name  = get_setting('smtp_from_name') ?: 'GovExam Portal';
    $encryption = get_setting('smtp_encryption') ?: 'tls';

    if (empty($host) || empty($from_email)) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = !empty($username);
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = $encryption;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_body));

        $mail->send();
        return true;
    } catch (MailerException $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generate or return existing CSRF token
 *
 * @return string CSRF token
 */
function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from POST data
 *
 * @return bool
 */
function csrf_verify()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (empty($token) || empty($session_token)) {
        return false;
    }
    return hash_equals($session_token, $token);
}

/**
 * Convert a string to a URL-safe slug
 *
 * @param string $str Input string
 * @return string Slug
 */
function slug($str)
{
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\s\-]/', '', $str);
    $str = preg_replace('/[\s\-]+/', '-', $str);
    return trim($str, '-');
}

/**
 * Calculate pagination values
 *
 * @param int $total    Total record count
 * @param int $per_page Records per page
 * @param int $page     Current page (1-indexed)
 * @return array
 */
function paginate($total, $per_page, $page)
{
    $per_page = max(1, (int)$per_page);
    $total_pages = max(1, (int)ceil($total / $per_page));
    $page = max(1, min((int)$page, $total_pages));
    $offset = ($page - 1) * $per_page;

    return [
        'total'       => (int)$total,
        'per_page'    => $per_page,
        'total_pages' => $total_pages,
        'current_page'=> $page,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $total_pages
    ];
}

/**
 * Format a date string
 *
 * @param string $date   Date string (MySQL date or timestamp)
 * @param string $format Output format
 * @return string
 */
function format_date($date, $format = 'd M Y')
{
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    return date($format, $ts);
}

/**
 * Generate a short excerpt from HTML content
 *
 * @param string $text Input text (may contain HTML)
 * @param int    $len  Max character length
 * @return string
 */
function excerpt($text, $len = 150)
{
    $text = strip_tags($text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len) . '...';
}

/**
 * Require admin role, send 403 otherwise
 */
function require_admin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        $file = defined('ROOT') ? ROOT . '/403.php' : __DIR__ . '/../403.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
        }
        exit;
    }
}

/**
 * Require authenticated user, redirect to login otherwise
 */
function require_login()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /auth/login.php?redirect=' . $redirect);
        exit;
    }
}

/**
 * Upload a file with MIME type validation
 *
 * @param array  $file          $_FILES element
 * @param string $dir           Destination directory (relative to ROOT/uploads/)
 * @param array  $allowed_types Allowed extensions
 * @return string|false Filename on success, false on failure
 */
function upload_file($file, $dir, $allowed_types = ['jpg', 'png', 'pdf', 'gif'])
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types, true)) {
        return false;
    }

    // MIME validation
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed_mimes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf',
        'webp' => 'image/webp'
    ];

    if (isset($allowed_mimes[$ext]) && $allowed_mimes[$ext] !== $mime) {
        return false;
    }

    $upload_dir = defined('ROOT') ? ROOT . '/uploads/' . trim($dir, '/') : __DIR__ . '/../uploads/' . trim($dir, '/');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = uniqid('', true) . '.' . $ext;
    $dest = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }

    return $filename;
}

/**
 * Check for maintenance mode and block non-admins
 */
function maintenance_mode_check()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (get_setting('maintenance_mode') === '1') {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $file = defined('ROOT') ? ROOT . '/includes/maintenance.php' : __DIR__ . '/maintenance.php';
            if (file_exists($file)) {
                include $file;
            } else {
                http_response_code(503);
                echo '<h1>Site Under Maintenance</h1><p>We will be back shortly.</p>';
            }
            exit;
        }
    }
}

/**
 * Check if an IP is rate-limited for login
 *
 * @param string $ip             IP address
 * @param int    $max            Max allowed attempts
 * @param int    $window_minutes Time window in minutes
 * @return bool True if blocked
 */
function rate_limit_check($ip, $max = 5, $window_minutes = 60)
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT attempts, last_attempt FROM login_attempts WHERE ip = ? LIMIT 1'
    );
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    $window_ago = date('Y-m-d H:i:s', strtotime('-' . $window_minutes . ' minutes'));
    if ($row['last_attempt'] < $window_ago) {
        // Window expired, reset
        $del = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
        $del->execute([$ip]);
        return false;
    }

    return (int)$row['attempts'] >= $max;
}

/**
 * Record a failed login attempt for an IP
 *
 * @param string $ip IP address
 */
function rate_limit_record($ip)
{
    global $pdo;

    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (ip, attempts) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE attempts = attempts + 1'
    );
    $stmt->execute([$ip]);
}

/**
 * Sanitize output to prevent XSS
 *
 * @param string $str Input string
 * @return string
 */
function sanitize($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
