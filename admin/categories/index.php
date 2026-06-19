<?php
/**
 * Admin: Exam Categories manager.
 * Add / rename / delete the exam categories used across notifications,
 * mock tests, the homepage grid and the public filters.
 *
 * Categories live in the exam_categories table. The category columns on
 * notifications/mock_tests are free-form VARCHAR, so deleting a category
 * here never breaks existing records — it only removes the suggestion.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Exam Categories';
$active_menu = 'categories';

$msg = '';
$msg_type = 'success';

// Ensure the table exists (so the page works even before the migration is run).
$table_ready = true;
try {
    $pdo->query("SELECT 1 FROM exam_categories LIMIT 1");
} catch (Throwable $e) {
    $table_ready = false;
}

// Handle POST actions (add / edit / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_ready) {
    if (!csrf_verify()) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $order = (int)($_POST['sort_order'] ?? 0);
            if ($name === '') {
                $msg = 'Category name cannot be empty.'; $msg_type = 'danger';
            } else {
                $stmt = $pdo->prepare("INSERT INTO exam_categories (name, sort_order) VALUES (?, ?)");
                $stmt->execute([$name, $order]);
                $msg = 'Category "' . htmlspecialchars($name) . '" added.';
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $order = (int)($_POST['sort_order'] ?? 0);
            if ($id && $name !== '') {
                $stmt = $pdo->prepare("UPDATE exam_categories SET name = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$name, $order, $id]);
                $msg = 'Category updated.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare("DELETE FROM exam_categories WHERE id = ?")->execute([$id]);
                $msg = 'Category deleted (existing notifications/tests are unaffected).';
            }
        }
    } catch (PDOException $e) {
        // Most likely a duplicate name (UNIQUE constraint).
        $msg = (stripos($e->getMessage(), 'Duplicate') !== false)
            ? 'That category already exists.'
            : 'Database error: ' . $e->getMessage();
        $msg_type = 'danger';
    }
}

$categories = [];
if ($table_ready) {
    $categories = $pdo->query("SELECT * FROM exam_categories ORDER BY sort_order ASC, name ASC")
                      ->fetchAll(PDO::FETCH_ASSOC);
}

// Count how many notifications/tests use each category name (for info).
$usage = [];
try {
    foreach (['notifications', 'mock_tests'] as $tbl) {
        $rows = $pdo->query("SELECT category, COUNT(*) c FROM {$tbl} WHERE category IS NOT NULL AND category <> '' GROUP BY category")
                    ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $usage[$r['category']] = ($usage[$r['category']] ?? 0) + (int)$r['c'];
        }
    }
} catch (Throwable $e) { /* ignore */ }

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
$csrf = csrf_token();
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-tags me-2"></i>Exam Categories</h2>
    <span class="text-muted small">Used by notifications, mock tests, homepage &amp; filters</span>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
      <?= $msg ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!$table_ready): ?>
    <div class="alert alert-warning">
      <i class="fas fa-triangle-exclamation me-2"></i>
      The <code>exam_categories</code> table was not found. Import
      <code>database/schema.sql</code> into your database, then reload this page.
    </div>
  <?php else: ?>

  <div class="row g-4">
    <!-- Add new -->
    <div class="col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-plus me-2"></i>Add Category</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
              <label class="form-label">Category Name</label>
              <input type="text" name="name" class="form-control" required
                     placeholder="e.g. UP Police, UPSSSC, Bihar Police">
            </div>
            <div class="mb-3">
              <label class="form-label">Sort Order</label>
              <input type="number" name="sort_order" class="form-control" value="50">
              <div class="form-text">Lower numbers appear first.</div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Add Category</button>
          </form>
        </div>
      </div>
    </div>

    <!-- List -->
    <div class="col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><?= count($categories) ?> categories</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th style="width:70px">Order</th><th>Name</th><th style="width:90px">In Use</th><th style="width:150px">Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                  <form method="post" class="d-contents">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <td><input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>" class="form-control form-control-sm" style="width:64px"></td>
                    <td><input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>" class="form-control form-control-sm"></td>
                    <td>
                      <?php $u = $usage[$c['name']] ?? 0; ?>
                      <span class="badge <?= $u ? 'bg-success' : 'bg-light text-muted border' ?>"><?= $u ?></span>
                    </td>
                    <td>
                      <button type="submit" class="btn btn-sm btn-outline-primary" title="Save"><i class="fas fa-floppy-disk"></i></button>
                  </form>
                      <form method="post" class="d-inline" onsubmit="return confirm('Delete this category? Existing records keep their category text.')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                      </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No categories yet. Add one on the left.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <p class="text-muted small mt-2">
        <i class="fas fa-circle-info me-1"></i>
        Deleting a category only removes the suggestion — notifications/tests already using it keep working.
      </p>
    </div>
  </div>

  <?php endif; ?>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
