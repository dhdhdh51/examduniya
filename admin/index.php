<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Dashboard';
$active_menu = 'dashboard';

$stats = [
    'notifications'   => $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
    'tests'           => $pdo->query("SELECT COUNT(*) FROM mock_tests")->fetchColumn(),
    'users'           => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'revenue'         => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success'")->fetchColumn(),
    'pending_comments'=> $pdo->query("SELECT COUNT(*) FROM comments WHERE is_approved=0")->fetchColumn(),
    'active_tests'    => $pdo->query("SELECT COUNT(*) FROM mock_tests WHERE is_active=1")->fetchColumn(),
];

$recent_notifications = $pdo->query(
    "SELECT id, title, category, status, vacancies, last_date_apply, telegram_sent, created_at
     FROM notifications ORDER BY created_at DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

$recent_users = $pdo->query(
    "SELECT id, name, email, role, plan, created_at FROM users ORDER BY created_at DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

// Chart data: notifications by category
$cat_rows = $pdo->query(
    "SELECT category, COUNT(*) as cnt FROM notifications GROUP BY category"
)->fetchAll(PDO::FETCH_ASSOC);
$cat_labels = array_column($cat_rows, 'category');
$cat_counts = array_column($cat_rows, 'cnt');

// Chart data: revenue last 30 days
$rev_rows = $pdo->query(
    "SELECT DATE(created_at) as day, COALESCE(SUM(amount),0) as total
     FROM payments WHERE status='success' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(created_at) ORDER BY day ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$rev_labels = array_column($rev_rows, 'day');
$rev_totals = array_column($rev_rows, 'total');

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <!-- Page title + quick actions -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="mb-0"><i class="fas fa-gauge me-2"></i>Dashboard</h2>
    <div class="d-flex gap-2 flex-wrap">
      <a href="/admin/notifications/add.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i>Add Notification
      </a>
      <a href="/admin/tests/generate.php" class="btn btn-success btn-sm">
        <i class="fas fa-robot me-1"></i>Generate Test
      </a>
      <a href="/admin/blogs/add.php" class="btn btn-info btn-sm text-white">
        <i class="fas fa-plus me-1"></i>Add Blog
      </a>
    </div>
  </div>

  <!-- Stats cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="text-primary mb-2"><i class="fas fa-bell fa-2x"></i></div>
          <div class="h4 fw-bold mb-0"><?= (int)$stats['notifications'] ?></div>
          <small class="text-muted">Notifications</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="text-success mb-2"><i class="fas fa-file-pen fa-2x"></i></div>
          <div class="h4 fw-bold mb-0"><?= (int)$stats['active_tests'] ?></div>
          <small class="text-muted">Active Tests</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="text-warning mb-2"><i class="fas fa-users fa-2x"></i></div>
          <div class="h4 fw-bold mb-0"><?= (int)$stats['users'] ?></div>
          <small class="text-muted">Users</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="text-danger mb-2"><i class="fas fa-indian-rupee-sign fa-2x"></i></div>
          <div class="h4 fw-bold mb-0">&#8377;<?= number_format((float)$stats['revenue'], 0) ?></div>
          <small class="text-muted">Revenue</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="text-info mb-2"><i class="fas fa-comments fa-2x"></i></div>
          <div class="h4 fw-bold mb-0"><?= (int)$stats['pending_comments'] ?></div>
          <small class="text-muted">Pending Comments</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="text-secondary mb-2"><i class="fas fa-clipboard-list fa-2x"></i></div>
          <div class="h4 fw-bold mb-0"><?= (int)$stats['tests'] ?></div>
          <small class="text-muted">Total Tests</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts row -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Notifications by Category</div>
        <div class="card-body"><canvas id="catChart" height="180"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Revenue (Last 30 Days)</div>
        <div class="card-body"><canvas id="revChart" height="180"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Recent notifications table -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><i class="fas fa-bell me-2"></i>Recent Notifications</span>
      <a href="/admin/notifications/list.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Title</th><th>Category</th><th>Status</th>
              <th>Vacancies</th><th>Last Date</th><th>Telegram</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_notifications as $n): ?>
            <tr>
              <td><?= htmlspecialchars(mb_substr($n['title'], 0, 50)) ?><?= mb_strlen($n['title']) > 50 ? '...' : '' ?></td>
              <td><span class="badge bg-secondary"><?= htmlspecialchars($n['category']) ?></span></td>
              <td>
                <?php
                $sc = ['upcoming'=>'bg-info','active'=>'bg-success','result'=>'bg-warning','admitcard'=>'bg-primary'];
                $cls = $sc[$n['status']] ?? 'bg-secondary';
                ?>
                <span class="badge <?= $cls ?>"><?= htmlspecialchars($n['status']) ?></span>
              </td>
              <td><?= $n['vacancies'] ? number_format((int)$n['vacancies']) : '-' ?></td>
              <td><?= $n['last_date_apply'] ? format_date($n['last_date_apply']) : '-' ?></td>
              <td>
                <span class="badge <?= $n['telegram_sent'] ? 'bg-success' : 'bg-light text-dark' ?>">
                  <?= $n['telegram_sent'] ? 'Yes' : 'No' ?>
                </span>
              </td>
              <td>
                <a href="/admin/notifications/edit.php?id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="fas fa-edit"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recent_notifications)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No notifications yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent users table -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><i class="fas fa-users me-2"></i>Recent Registrations</span>
      <a href="/admin/users/list.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Plan</th><th>Joined</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recent_users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge <?= $u['role']==='admin'?'bg-danger':($u['role']==='premium'?'bg-warning text-dark':'bg-secondary') ?>">
                  <?= htmlspecialchars($u['role']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $u['plan']==='free'?'bg-light text-dark':'bg-success' ?>">
                  <?= htmlspecialchars($u['plan']) ?>
                </span>
              </td>
              <td><?= format_date($u['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recent_users)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No users yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /.container-fluid -->
</div><!-- /.admin-content -->

<?php
$extra_js = '<script>
(function(){
  var catCtx = document.getElementById("catChart");
  if(catCtx){
    new Chart(catCtx, {
      type: "bar",
      data: {
        labels: ' . json_encode($cat_labels ?: ['No data']) . ',
        datasets: [{
          label: "Notifications",
          data: ' . json_encode($cat_counts ?: [0]) . ',
          backgroundColor: "rgba(99,102,241,0.7)"
        }]
      },
      options: { responsive:true, plugins:{ legend:{display:false} } }
    });
  }
  var revCtx = document.getElementById("revChart");
  if(revCtx){
    new Chart(revCtx, {
      type: "line",
      data: {
        labels: ' . json_encode($rev_labels ?: ['No data']) . ',
        datasets: [{
          label: "Revenue (INR)",
          data: ' . json_encode($rev_totals ?: [0]) . ',
          borderColor: "rgba(34,197,94,1)",
          backgroundColor: "rgba(34,197,94,0.1)",
          fill: true,
          tension: 0.3
        }]
      },
      options: { responsive:true, plugins:{ legend:{display:false} } }
    });
  }
})();
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
