<?php
/**
 * AJAX: Send a test email through the configured SMTP settings.
 * Admin only. CSRF protected. Returns JSON.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}
if (!csrf_verify()) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}

$to = trim((string)($_POST['test_email'] ?? ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please provide a valid test email address.']);
    exit;
}

$host       = get_setting('smtp_host');
$port       = (int)(get_setting('smtp_port') ?: 587);
$username   = get_setting('smtp_username');
$password   = get_setting('smtp_password');
$from_email = get_setting('smtp_from_email') ?: $username;
$from_name  = get_setting('smtp_from_name') ?: 'GovExam Portal';
$encryption = get_setting('smtp_encryption') ?: 'tls';

if (empty($host)) {
    echo json_encode(['success' => false, 'error' => 'SMTP host is not configured.']);
    exit;
}
if (empty($from_email)) {
    echo json_encode(['success' => false, 'error' => 'A "From" email (or username) must be configured.']);
    exit;
}

$now     = date('Y-m-d H:i:s');
$subject = 'SMTP Test — GovExam Portal';
$body    = '<p>Your SMTP is configured correctly. Sent at ' . htmlspecialchars($now) . '</p>';

$mail = new PHPMailer(true);
$result = ['success' => false, 'sent_to' => $to, 'error' => null];

try {
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = !empty($username);
    $mail->Username   = $username;
    $mail->Password   = $password;
    $mail->Port       = $port;
    $mail->CharSet    = 'UTF-8';

    // Map encryption setting to PHPMailer constants ("none" disables it).
    if ($encryption === 'none' || $encryption === '') {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = $encryption; // 'tls' or 'ssl'
    }

    $mail->setFrom($from_email, $from_name);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = 'Your SMTP is configured correctly. Sent at ' . $now;

    $mail->send();
    $result['success'] = true;
} catch (MailerException $e) {
    $result['error'] = $mail->ErrorInfo ?: $e->getMessage();
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

set_setting('smtp_last_test', json_encode([
    'ok'   => $result['success'],
    'at'   => $now,
    'note' => $result['success'] ? ('Sent to ' . $to) : $result['error'],
]));

echo json_encode($result);
