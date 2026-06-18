<?php
/**
 * Email verification — validate token and mark user verified
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';

if (empty($token)) {
    $error = 'Invalid or missing verification token.';
} else {
    // Find token in password_resets (used for verification too)
    $stmt = $pdo->prepare(
        "SELECT pr.*, u.id AS uid
         FROM password_resets pr
         JOIN users u ON u.email = pr.email
         WHERE pr.token = ?
           AND pr.used = 0
           AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $error = 'This verification link is invalid or has expired. <a href="/auth/resend-verification.php">Request a new one</a>.';
    } else {
        // Mark email verified
        $upd = $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
        $upd->execute([$row['uid']]);

        // Mark token used
        $del = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
        $del->execute([$token]);

        $success = 'Your email has been verified successfully. You can now log in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,follow">
<title>Verify Email &mdash; Exam Duniya</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= asset('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-bg d-flex align-items-center justify-content-center min-vh-100">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="auth-card p-4 p-md-5 shadow-lg text-center">
        <div class="auth-logo mb-3">&#x1F4DA;</div>
        <h4 class="fw-bold mb-1">Exam Duniya</h4>
        <h5 class="fw-semibold mb-4">Email Verification</h5>

        <?php if ($success): ?>
          <div class="alert alert-success mb-4">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <a href="/auth/login.php" class="btn btn-primary w-100">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
          </a>
        <?php else: ?>
          <div class="alert alert-danger mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <?= $error ?>
          </div>
          <a href="/auth/login.php" class="btn btn-outline-secondary w-100">
            Back to Login
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
