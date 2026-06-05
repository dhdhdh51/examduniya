<?php
/**
 * Login page — email/password + Google OAuth
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

// Preserve redirect target
if (!empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = urldecode($_GET['redirect']);
}

// Success messages from other auth flows
if (isset($_GET['verified'])) {
    $success = 'Email verified successfully. You can now sign in.';
} elseif (isset($_GET['reset'])) {
    $success = 'Password reset successfully. Please sign in with your new password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (rate_limit_check($ip)) {
            $error = 'Too many failed login attempts. Please try again in 1 hour.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['email_verified']) {
                    $error = 'Please verify your email first. <a href="/auth/resend-verification.php" class="alert-link">Resend verification email</a>.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['role']    = $user['role'];
                    $_SESSION['avatar']  = $user['avatar'];

                    $redirect = $_SESSION['redirect_after_login'] ?? '/pages/user/dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                rate_limit_record($ip);
                $error = 'Invalid email or password.';
            }
        }
    }
}

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login &mdash; GovExam Portal</title>
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
          <div style="font-size:2.75rem;line-height:1">&#x1F4DA;</div>
          <h4 class="fw-bold mt-2 mb-0">GovExam Portal</h4>
          <p class="text-muted small mt-1">Sign in to your account</p>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <?= $error ?>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <!-- Google OAuth button -->
        <a href="/auth/google-redirect.php" class="btn-google mb-3 text-decoration-none">
          <svg width="20" height="20" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            <path fill="none" d="M0 0h48v48H0z"/>
          </svg>
          Continue with Google
        </a>

        <!-- Divider -->
        <div class="auth-divider">OR</div>

        <!-- Login Form -->
        <form method="POST" action="" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- Email -->
          <div class="form-floating mb-3">
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="email@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required autofocus>
            <label for="email"><i class="fa-solid fa-envelope me-1"></i>Email Address</label>
          </div>

          <!-- Password -->
          <div class="mb-2">
            <div class="input-group">
              <div class="form-floating flex-grow-1">
                <input type="password" class="form-control border-end-0" id="password"
                       name="password" placeholder="Password" required>
                <label for="password"><i class="fa-solid fa-lock me-1"></i>Password</label>
              </div>
              <button type="button" class="btn btn-outline-secondary border-start-0"
                      onclick="togglePassword('password', this)" title="Show/hide password">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <!-- Forgot password link -->
          <div class="text-end mb-3">
            <a href="/auth/forgot-password.php" class="small text-muted">Forgot Password?</a>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
          </button>
        </form>

        <div class="text-center small text-muted">
          Don't have an account?
          <a href="/auth/signup.php" class="text-primary fw-semibold">Sign Up</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(id, btn) {
  var inp  = document.getElementById(id);
  var icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    inp.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>
</body>
</html>
