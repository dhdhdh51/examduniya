<?php
/**
 * Signup page — create account with email verification
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
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        // Validate inputs
        if (empty($name) || strlen($name) < 2) {
            $error = 'Please enter your full name (at least 2 characters).';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            // Check email uniqueness
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists. <a href="/auth/login.php">Sign in</a>.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Insert user (email_verified = 0)
                $ins = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, email_verified, role)
                     VALUES (?, ?, ?, 0, "user")'
                );
                $ins->execute([$name, $email, $hash]);

                // Generate verification token (24-hour expiry)
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $tokIns  = $pdo->prepare(
                    'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)'
                );
                $tokIns->execute([$email, $token, $expires]);

                // Send verification email
                $site_url  = get_setting('site_url') ?: '';
                $site_name = get_setting('site_name') ?: 'GovExam Portal';
                $link      = rtrim($site_url, '/') . '/auth/verify-email.php?token=' . urlencode($token);

                $html = '
                <div style="font-family:Inter,sans-serif;max-width:560px;margin:0 auto">
                  <h2 style="color:#2563EB">Welcome to ' . htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') . '!</h2>
                  <p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ', thanks for registering. Please verify your email address to activate your account.</p>
                  <p style="text-align:center;margin:2rem 0">
                    <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '"
                       style="background:#2563EB;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600">
                      Verify Email Address
                    </a>
                  </p>
                  <p style="color:#64748b;font-size:0.85rem">This link expires in 24 hours. If you did not create this account, you can safely ignore this email.</p>
                </div>';

                send_email($email, 'Verify your email — ' . $site_name, $html);

                $success = 'Account created! Please check your email and click the verification link to activate your account.';
            }
        }
    }
}

$page_title = 'Sign Up';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up &mdash; GovExam Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-bg d-flex align-items-center justify-content-center min-vh-100">

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="auth-card p-4 p-md-5 shadow-lg">

        <!-- Logo -->
        <div class="text-center mb-4">
          <div style="font-size:2.75rem;line-height:1">&#x1F4DA;</div>
          <h4 class="fw-bold mt-2 mb-0">GovExam Portal</h4>
          <p class="text-muted small mt-1">Create your free account</p>
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
            <i class="fa-solid fa-envelope-circle-check me-2"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="text-center mt-3">
            <a href="/auth/login.php" class="btn btn-outline-secondary w-100">
              <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
            </a>
          </div>
        <?php else: ?>
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

          <!-- Signup Form -->
          <form method="POST" action="" novalidate id="signupForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Full Name -->
            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="name" name="name"
                     placeholder="Full Name"
                     value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required minlength="2" autofocus>
              <label for="name"><i class="fa-solid fa-user me-1"></i>Full Name</label>
            </div>

            <!-- Email -->
            <div class="form-floating mb-3">
              <input type="email" class="form-control" id="email" name="email"
                     placeholder="email@example.com"
                     value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
              <label for="email"><i class="fa-solid fa-envelope me-1"></i>Email Address</label>
              <div id="emailMsg" class="small mt-1"></div>
            </div>

            <!-- Password -->
            <div class="mb-3">
              <div class="input-group">
                <div class="form-floating flex-grow-1">
                  <input type="password" class="form-control border-end-0" id="password"
                         name="password" placeholder="Password"
                         minlength="8" required>
                  <label for="password"><i class="fa-solid fa-lock me-1"></i>Password</label>
                </div>
                <button type="button" class="btn btn-outline-secondary border-start-0"
                        onclick="togglePassword('password', this)" title="Show/hide password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <!-- Strength bar -->
              <div class="mt-2">
                <div class="progress" style="height:5px">
                  <div id="strengthBar" class="progress-bar" style="width:0;transition:width 0.3s"></div>
                </div>
                <small id="strengthText" class="text-muted d-block mt-1"></small>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
              <div class="input-group">
                <div class="form-floating flex-grow-1">
                  <input type="password" class="form-control border-end-0" id="password2"
                         name="password2" placeholder="Confirm Password" required>
                  <label for="password2"><i class="fa-solid fa-lock me-1"></i>Confirm Password</label>
                </div>
                <button type="button" class="btn btn-outline-secondary border-start-0"
                        onclick="togglePassword('password2', this)" title="Show/hide password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <small id="matchMsg" class="d-block mt-1"></small>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3" id="submitBtn">
              <i class="fa-solid fa-user-plus me-2"></i>Create Account
            </button>
          </form>

          <div class="text-center small text-muted">
            Already have an account?
            <a href="/auth/login.php" class="text-primary fw-semibold">Sign In</a>
          </div>
        <?php endif; ?>
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

// Password strength
var pwInput   = document.getElementById('password');
var pw2Input  = document.getElementById('password2');
var bar       = document.getElementById('strengthBar');
var txt       = document.getElementById('strengthText');
var matchMsg  = document.getElementById('matchMsg');
var emailInp  = document.getElementById('email');
var emailMsg  = document.getElementById('emailMsg');

if (pwInput) {
  pwInput.addEventListener('input', function() {
    var pw = this.value;
    var score = 0;
    if (pw.length >= 8)            score++;
    if (/[A-Z]/.test(pw))          score++;
    if (/[0-9]/.test(pw))          score++;
    if (/[^A-Za-z0-9]/.test(pw))   score++;

    var pcts   = ['0', '33', '66', '100'];
    var colors = ['', '#DC2626', '#D97706', '#059669'];
    var labels = ['', 'Weak', 'Medium', 'Strong'];

    bar.style.width      = (pcts[score] || '0') + '%';
    bar.style.background = colors[score] || '#DC2626';
    txt.textContent      = labels[score] || '';
    txt.style.color      = colors[score] || '#DC2626';
    checkMatch();
  });
}

if (pw2Input) {
  pw2Input.addEventListener('input', checkMatch);
}

function checkMatch() {
  if (!pw2Input || !pwInput || !pw2Input.value) {
    matchMsg.textContent = '';
    return;
  }
  if (pwInput.value === pw2Input.value) {
    matchMsg.textContent = 'Passwords match';
    matchMsg.style.color = '#059669';
  } else {
    matchMsg.textContent = 'Passwords do not match';
    matchMsg.style.color = '#DC2626';
  }
}

// Email format validation
if (emailInp) {
  emailInp.addEventListener('input', function() {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!this.value) {
      emailMsg.textContent = '';
    } else if (re.test(this.value)) {
      emailMsg.textContent = 'Valid email format';
      emailMsg.style.color = '#059669';
    } else {
      emailMsg.textContent = 'Invalid email format';
      emailMsg.style.color = '#DC2626';
    }
  });
}

// Client-side submit guard
var form = document.getElementById('signupForm');
if (form) {
  form.addEventListener('submit', function(e) {
    if (pwInput && pw2Input && pwInput.value !== pw2Input.value) {
      e.preventDefault();
      matchMsg.textContent = 'Passwords do not match';
      matchMsg.style.color = '#DC2626';
      pw2Input.focus();
    }
  });
}
</script>
</body>
</html>
