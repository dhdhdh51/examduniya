<?php
/**
 * Forgot Password — request a password reset link
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/user/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Invalidate old reset tokens for this email
                $upd = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = ?');
                $upd->execute([$email]);

                // Generate token (1-hour expiry)
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $ins = $pdo->prepare(
                    'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)'
                );
                $ins->execute([$email, $token, $expires]);

                $site_url  = get_setting('site_url') ?: '';
                $site_name = get_setting('site_name') ?: 'GovExam Portal';
                $link      = rtrim($site_url, '/') . '/auth/reset-password.php?token=' . urlencode($token);

                $html = '
                <div style="font-family:Inter,sans-serif;max-width:560px;margin:0 auto">
                  <h2 style="color:#2563EB">Reset Your Password</h2>
                  <p>We received a request to reset the password for your ' . htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') . ' account.</p>
                  <p style="text-align:center;margin:2rem 0">
                    <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '"
                       style="background:#2563EB;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600">
                      Reset Password
                    </a>
                  </p>
                  <p style="color:#64748b;font-size:0.85rem">This link expires in 1 hour. If you did not request a password reset, please ignore this email.</p>
                </div>';

                send_email($email, 'Password Reset — ' . $site_name, $html);
            }

            // Always show generic success (don't reveal whether email exists)
            $success = 'If that email is registered, a password reset link has been sent. Please check your inbox.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password &mdash; GovExam Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= asset('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-bg d-flex align-items-center justify-content-center min-vh-100">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="auth-card p-4 p-md-5 shadow-lg">

        <!-- Logo -->
        <div class="text-center mb-4">
          <div class="auth-logo" style="font-size:2.5rem">&#x1F4DA;</div>
          <h4 class="fw-bold mt-2">GovExam Portal</h4>
          <p class="text-muted small">Forgot your password?</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success">
            <i class="fa-solid fa-envelope-circle-check me-2"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="text-center mt-3">
            <a href="/auth/login.php" class="btn btn-outline-secondary w-100">
              <i class="fa-solid fa-arrow-left me-2"></i>Back to Login
            </a>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-4">
            Enter your registered email and we'll send you a link to reset your password.
          </p>

          <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-floating mb-4">
              <input type="email" class="form-control" id="email" name="email"
                     placeholder="email@example.com"
                     value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
              <label for="email"><i class="fa-solid fa-envelope me-1"></i>Email Address</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
              <i class="fa-solid fa-paper-plane me-2"></i>Send Reset Link
            </button>
          </form>

          <div class="text-center">
            <a href="/auth/login.php" class="text-muted small">
              <i class="fa-solid fa-arrow-left me-1"></i>Back to Login
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
