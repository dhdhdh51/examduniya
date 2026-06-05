<?php
/**
 * Reset Password — validate token and allow password update
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

$token = trim($_GET['token'] ?? '');
$error   = '';
$success = '';
$valid_token = false;
$token_row   = null;

// Validate token on both GET and POST
if (!empty($token)) {
    $stmt = $pdo->prepare(
        "SELECT * FROM password_resets
         WHERE token = ? AND used = 0 AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $token_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($token_row) {
        $valid_token = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } elseif (!$valid_token) {
        $error = 'This reset link is invalid or has expired.';
    } else {
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            // Update password
            $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
            $upd->execute([$hash, $token_row['email']]);

            // Mark token used
            $mark = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
            $mark->execute([$token]);

            header('Location: /auth/login.php?reset=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password &mdash; GovExam Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
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
          <p class="text-muted small">Reset your password</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <?php if (!$valid_token && empty($error)): ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            This password reset link is invalid or has expired.
          </div>
          <a href="/auth/forgot-password.php" class="btn btn-primary w-100 mt-2">
            Request a New Link
          </a>
        <?php elseif ($valid_token): ?>
          <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- New Password -->
            <div class="mb-3">
              <div class="input-group">
                <div class="form-floating flex-grow-1">
                  <input type="password" class="form-control border-end-0" id="password"
                         name="password" placeholder="New Password"
                         minlength="8" required>
                  <label for="password"><i class="fa-solid fa-lock me-1"></i>New Password</label>
                </div>
                <button type="button" class="btn btn-outline-secondary border-start-0"
                        onclick="togglePassword('password', this)" title="Show/hide password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div class="mt-2">
                <div class="progress" style="height:4px">
                  <div id="strengthBar" class="progress-bar" style="width:0;transition:width 0.3s"></div>
                </div>
                <small id="strengthText" class="text-muted"></small>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
              <div class="input-group">
                <div class="form-floating flex-grow-1">
                  <input type="password" class="form-control border-end-0" id="password2"
                         name="password2" placeholder="Confirm Password"
                         required>
                  <label for="password2"><i class="fa-solid fa-lock me-1"></i>Confirm Password</label>
                </div>
                <button type="button" class="btn btn-outline-secondary border-start-0"
                        onclick="togglePassword('password2', this)" title="Show/hide password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <small id="matchMsg" class="mt-1 d-block"></small>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
              <i class="fa-solid fa-key me-2"></i>Reset Password
            </button>
          </form>
        <?php endif; ?>

        <div class="text-center mt-3">
          <a href="/auth/login.php" class="text-muted small">Back to Login</a>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(id, btn) {
  var inp = document.getElementById(id);
  var icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    inp.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

var pwInput  = document.getElementById('password');
var pw2Input = document.getElementById('password2');
var bar      = document.getElementById('strengthBar');
var txt      = document.getElementById('strengthText');
var matchMsg = document.getElementById('matchMsg');

if (pwInput) {
  pwInput.addEventListener('input', function() {
    var pw = this.value;
    var score = 0;
    if (pw.length >= 8)  score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    var pct = ['0', '33', '66', '100'][score] || '0';
    var colors = ['#DC2626', '#D97706', '#2563EB', '#059669'];
    var labels = ['', 'Weak', 'Medium', 'Strong'];
    bar.style.width = pct + '%';
    bar.style.background = colors[score] || '#DC2626';
    txt.textContent = labels[score] || '';
    txt.style.color = colors[score] || '#DC2626';
    checkMatch();
  });
}

if (pw2Input) {
  pw2Input.addEventListener('input', checkMatch);
}

function checkMatch() {
  if (!pw2Input || !pwInput) return;
  if (!pw2Input.value) { matchMsg.textContent = ''; return; }
  if (pwInput.value === pw2Input.value) {
    matchMsg.textContent = 'Passwords match';
    matchMsg.style.color = '#059669';
  } else {
    matchMsg.textContent = 'Passwords do not match';
    matchMsg.style.color = '#DC2626';
  }
}
</script>
</body>
</html>
