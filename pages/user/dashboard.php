<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();
require_login();

$user_id = $_SESSION['user_id'];

// Fetch user data
$u_stmt = $pdo->prepare("SELECT id, name, email, role, plan, plan_expiry, avatar, created_at FROM users WHERE id = ? LIMIT 1");
$u_stmt->execute([$user_id]);
$user = $u_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}

// Handle profile update
$profile_success = '';
$profile_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';

    if ($action === 'update_profile') {
        if (!csrf_verify()) {
            $profile_error = 'Invalid security token. Please try again.';
        } else {
            $new_name = trim($_POST['name'] ?? '');
            if (mb_strlen($new_name) < 2 || mb_strlen($new_name) > 100) {
                $profile_error = 'Name must be between 2 and 100 characters.';
            } else {
                // Handle avatar upload
                $avatar_filename = $user['avatar'];
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $uploaded = upload_file($_FILES['avatar'], 'avatars', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    if ($uploaded) {
                        $avatar_filename = $uploaded;
                    } else {
                        $profile_error = 'Avatar upload failed. Only JPG/PNG/GIF images are allowed.';
                    }
                }

                if (!$profile_error) {
                    $up = $pdo->prepare("UPDATE users SET name = ?, avatar = ? WHERE id = ?");
                    $up->execute([$new_name, $avatar_filename, $user_id]);
                    $user['name']   = $new_name;
                    $user['avatar'] = $avatar_filename;
                    $_SESSION['name']   = $new_name;
                    $_SESSION['avatar'] = $avatar_filename;
                    $profile_success = 'Profile updated successfully!';
                }
            }
        }
    } elseif ($action === 'change_password') {
        if (!csrf_verify()) {
            $profile_error = 'Invalid security token. Please try again.';
        } else {
            $current  = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm  = $_POST['confirm_password'] ?? '';

            // Fetch password hash
            $pw_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $pw_stmt->execute([$user_id]);
            $pw_row = $pw_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pw_row || !password_verify($current, $pw_row['password_hash'])) {
                $profile_error = 'Current password is incorrect.';
            } elseif (mb_strlen($new_pass) < 6) {
                $profile_error = 'New password must be at least 6 characters.';
            } elseif ($new_pass !== $confirm) {
                $profile_error = 'New passwords do not match.';
            } else {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_id]);
                $profile_success = 'Password changed successfully!';
            }
        }
    }
}

// Purchased tests (via payments)
try {
    $tests_stmt = $pdo->prepare("SELECT mt.id, mt.title, mt.slug, mt.total_questions, mt.duration_minutes, p.created_at as purchase_date FROM payments p JOIN mock_tests mt ON p.plan = 'monthly' OR p.plan = 'yearly' WHERE p.user_id = ? AND p.status = 'success' ORDER BY p.created_at DESC LIMIT 20");
    $tests_stmt->execute([$user_id]);
    $purchased_tests = $tests_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $purchased_tests = [];
}

// User attempts (results)
try {
    $results_stmt = $pdo->prepare("SELECT ua.id, ua.score, ua.total_marks, ua.correct_count, ua.wrong_count, ua.unanswered_count, ua.started_at, ua.completed_at, mt.title as test_title, mt.slug as test_slug FROM user_attempts ua JOIN mock_tests mt ON ua.test_id = mt.id WHERE ua.user_id = ? ORDER BY ua.started_at DESC LIMIT 20");
    $results_stmt->execute([$user_id]);
    $results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results = [];
}

$page_title = 'My Dashboard';
$active_tab = $_GET['tab'] ?? 'tests';

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>

    <!-- Welcome Card -->
    <div class="card no-lift mb-4" style="background:linear-gradient(135deg,#2563eb,#7c3aed);border:none;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <div class="flex-shrink-0">
                    <?php if ($user['avatar']): ?>
                        <img src="/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                             class="rounded-circle border border-3 border-white" width="80" height="80" alt="avatar">
                    <?php else: ?>
                        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                             style="width:80px;height:80px;">
                            <i class="fa-solid fa-user fa-2x text-primary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-white">
                    <h4 class="text-white fw-bold mb-1">Welcome, <?= htmlspecialchars($user['name'] ?: 'User') ?>!</h4>
                    <p class="mb-1 opacity-90"><i class="fa-solid fa-envelope me-1"></i><?= htmlspecialchars($user['email']) ?></p>
                    <p class="mb-0 opacity-75 small">
                        <i class="fa-solid fa-calendar me-1"></i>Member since <?= htmlspecialchars(format_date($user['created_at'])) ?>
                    </p>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-white text-primary fw-bold px-3 py-2 rounded-pill">
                        <?= htmlspecialchars(ucfirst($user['plan'] ?: $user['role'])) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($profile_success): ?>
        <div class="alert alert-success auto-dismiss">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($profile_success) ?>
        </div>
    <?php endif; ?>
    <?php if ($profile_error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-xmark me-2"></i><?= htmlspecialchars($profile_error) ?>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $active_tab === 'tests' ? ' active fw-semibold' : '' ?>"
               href="?tab=tests">
                <i class="fa-solid fa-file-pen me-1"></i>My Tests
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $active_tab === 'results' ? ' active fw-semibold' : '' ?>"
               href="?tab=results">
                <i class="fa-solid fa-chart-column me-1"></i>My Results
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $active_tab === 'profile' ? ' active fw-semibold' : '' ?>"
               href="?tab=profile">
                <i class="fa-solid fa-user me-1"></i>My Profile
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <?php if ($active_tab === 'tests'): ?>
        <!-- My Tests Tab -->
        <?php if (empty($purchased_tests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-file-pen fa-4x mb-3 opacity-50"></i>
                <h5 class="text-muted">No tests purchased yet</h5>
                <p class="text-muted small mb-3">Explore our mock tests and start preparing.</p>
                <a href="/pages/tests/" class="btn btn-primary">Browse Mock Tests</a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($purchased_tests as $pt): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <h6 class="fw-bold mb-2"><?= htmlspecialchars($pt['title']) ?></h6>
                                <div class="text-muted small mb-3">
                                    <div><i class="fa-solid fa-circle-question me-1"></i><?= (int)$pt['total_questions'] ?> Questions</div>
                                    <div><i class="fa-solid fa-clock me-1"></i><?= (int)$pt['duration_minutes'] ?> min</div>
                                    <div><i class="fa-solid fa-calendar me-1"></i>Purchased: <?= htmlspecialchars(format_date($pt['purchase_date'])) ?></div>
                                </div>
                                <a href="/pages/tests/detail.php?slug=<?= urlencode($pt['slug'] ?? '') ?>"
                                   class="btn btn-primary btn-sm w-100 mt-auto">
                                    Start Test <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'results'): ?>
        <!-- My Results Tab -->
        <?php if (empty($results)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-chart-column fa-4x mb-3 opacity-50"></i>
                <h5 class="text-muted">No test results yet</h5>
                <p class="text-muted small">Take a mock test to see your results here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Score</th>
                            <th>Correct</th>
                            <th>Wrong</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r):
                            $percentage = $r['total_marks'] > 0 ? round(($r['score'] / $r['total_marks']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="fw-semibold small"><?= htmlspecialchars($r['test_title']) ?></td>
                                <td>
                                    <span class="fw-bold"><?= number_format((float)$r['score'], 1) ?></span>
                                    <span class="text-muted small">/ <?= number_format((float)$r['total_marks'], 1) ?></span>
                                    <span class="badge <?= $percentage >= 60 ? 'bg-success' : ($percentage >= 40 ? 'bg-warning' : 'bg-danger') ?> ms-1" style="font-size:0.65rem;">
                                        <?= $percentage ?>%
                                    </span>
                                </td>
                                <td class="text-success fw-semibold"><?= (int)$r['correct_count'] ?></td>
                                <td class="text-danger fw-semibold"><?= (int)$r['wrong_count'] ?></td>
                                <td class="text-muted small"><?= htmlspecialchars(format_date($r['started_at'], 'd M Y')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'profile'): ?>
        <!-- My Profile Tab -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card no-lift">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fa-solid fa-user-pen me-2 text-primary"></i>Edit Profile</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?tab=profile&action=update_profile" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control"
                                       value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                                       required minlength="2" maxlength="100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email (read-only)</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Avatar</label>
                                <?php if ($user['avatar']): ?>
                                    <div class="mb-2">
                                        <img src="/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                                             class="rounded-circle" width="60" height="60" alt="Current avatar">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-text">JPG, PNG, GIF, or WebP. Max 2MB.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control"
                                       value="<?= htmlspecialchars(format_date($user['created_at'])) ?>" disabled>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card no-lift">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fa-solid fa-lock me-2 text-warning"></i>Change Password</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?tab=profile&action=change_password">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control"
                                       required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control"
                                       required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-warning text-white">
                                <i class="fa-solid fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once ROOT . '/includes/footer.php'; ?>
