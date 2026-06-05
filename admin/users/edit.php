<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Edit User';
$active_menu = 'users';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/users/list.php');
    exit;
}

$user = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$user->execute([$id]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /admin/users/list.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        die('CSRF token mismatch.');
    }

    $role = $_POST['role'] ?? 'free';
    $plan = $_POST['plan'] ?? 'free';
    $plan_expiry = trim($_POST['plan_expiry'] ?? '') ?: null;

    $allowed_roles = ['admin','premium','free'];
    $allowed_plans = ['monthly','yearly','free'];

    if (!in_array($role, $allowed_roles)) $errors[] = 'Invalid role.';
    if (!in_array($plan, $allowed_plans)) $errors[] = 'Invalid plan.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET role=?, plan=?, plan_expiry=? WHERE id=?");
        $stmt->execute([$role, $plan, $plan_expiry, $id]);

        header('Location: /admin/users/list.php?success=1');
        exit;
    }
}

// Fetch recent test attempts
$attempts = $pdo->prepare(
    "SELECT ua.id, mt.title, ua.score, ua.total_marks, ua.correct_count, ua.completed_at
     FROM user_attempts ua
     JOIN mock_tests mt ON mt.id = ua.test_id
     WHERE ua.user_id = ?
     ORDER BY ua.completed_at DESC LIMIT 10"
);
$attempts->execute([$id]);
$attempts = $attempts->fetchAll(PDO::FETCH_ASSOC);

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit User</h2>
    <a href="/admin/users/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i>Back to List
    </a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-6">
      <!-- User info (read-only) -->
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-semibold">User Info</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-5">Name</dt>
            <dd class="col-7"><?= htmlspecialchars($user['name'] ?? '') ?></dd>
            <dt class="col-5">Email</dt>
            <dd class="col-7"><?= htmlspecialchars($user['email']) ?></dd>
            <dt class="col-5">Joined</dt>
            <dd class="col-7"><?= format_date($user['created_at']) ?></dd>
            <dt class="col-5">Email Verified</dt>
            <dd class="col-7">
              <span class="badge <?= $user['email_verified']?'bg-success':'bg-secondary' ?>">
                <?= $user['email_verified']?'Yes':'No' ?>
              </span>
            </dd>
          </dl>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <!-- Edit form -->
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Edit Role &amp; Plan</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Role</label>
              <select name="role" class="form-select">
                <option value="free" <?= $user['role']==='free'?'selected':'' ?>>Free</option>
                <option value="premium" <?= $user['role']==='premium'?'selected':'' ?>>Premium</option>
                <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Plan</label>
              <select name="plan" class="form-select">
                <option value="free" <?= $user['plan']==='free'?'selected':'' ?>>Free</option>
                <option value="monthly" <?= $user['plan']==='monthly'?'selected':'' ?>>Monthly</option>
                <option value="yearly" <?= $user['plan']==='yearly'?'selected':'' ?>>Yearly</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Plan Expiry</label>
              <input type="date" name="plan_expiry" class="form-control"
                     value="<?= htmlspecialchars($user['plan_expiry'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Save Changes
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Test attempts -->
  <div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white fw-semibold">Recent Test Attempts</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Test</th><th>Score</th><th>Total</th><th>Correct</th><th>Completed</th></tr>
          </thead>
          <tbody>
            <?php foreach ($attempts as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= number_format((float)$a['score'], 2) ?></td>
              <td><?= number_format((float)$a['total_marks'], 2) ?></td>
              <td><?= (int)$a['correct_count'] ?></td>
              <td><?= $a['completed_at'] ? format_date($a['completed_at'], 'd M Y H:i') : 'In progress' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($attempts)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No test attempts yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
