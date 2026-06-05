<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Add Notification';
$active_menu = 'notifications';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        die('CSRF token mismatch.');
    }

    $title            = trim($_POST['title'] ?? '');
    $category         = trim($_POST['category'] ?? '');
    $conducting_body  = trim($_POST['conducting_body'] ?? '');
    $short_desc       = trim($_POST['short_desc'] ?? '');
    $full_content     = trim($_POST['full_content'] ?? '');
    $notification_date= trim($_POST['notification_date'] ?? '') ?: null;
    $last_date_apply  = trim($_POST['last_date_apply'] ?? '') ?: null;
    $exam_date        = trim($_POST['exam_date'] ?? '') ?: null;
    $vacancies        = (int)($_POST['vacancies'] ?? 0) ?: null;
    $age_limit        = trim($_POST['age_limit'] ?? '');
    $qualification    = trim($_POST['qualification'] ?? '');
    $fee_general      = is_numeric($_POST['fee_general'] ?? '') ? (float)$_POST['fee_general'] : null;
    $fee_obc          = is_numeric($_POST['fee_obc'] ?? '') ? (float)$_POST['fee_obc'] : null;
    $fee_sc_st        = is_numeric($_POST['fee_sc_st'] ?? '') ? (float)$_POST['fee_sc_st'] : null;
    $official_url     = trim($_POST['official_url'] ?? '');
    $status           = trim($_POST['status'] ?? 'upcoming');
    $send_telegram    = !empty($_POST['send_telegram']);
    $created_by       = (int)($_SESSION['user_id'] ?? 0);

    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($category)) $errors[] = 'Category is required.';

    if (empty($errors)) {
        $slug_base = slug($title);
        $slug = $slug_base;
        $slug_check = $pdo->prepare("SELECT id FROM notifications WHERE slug = ? LIMIT 1");
        $slug_check->execute([$slug]);
        if ($slug_check->fetchColumn()) {
            $slug = $slug_base . '-' . time();
        }

        // Handle PDF upload
        $pdf_path = null;
        if (!empty($_FILES['pdf']['tmp_name'])) {
            $uploaded = upload_file($_FILES['pdf'], 'notifications', ['pdf']);
            if ($uploaded === false) {
                $errors[] = 'PDF upload failed. Only PDF files are allowed.';
            } else {
                $pdf_path = $uploaded;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
             (title, slug, short_desc, full_content, category, conducting_body,
              notification_date, last_date_apply, exam_date, vacancies, age_limit,
              qualification, fee_general, fee_obc, fee_sc_st, official_url, pdf_path,
              status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $title, $slug, $short_desc, $full_content, $category, $conducting_body,
            $notification_date, $last_date_apply, $exam_date, $vacancies, $age_limit,
            $qualification, $fee_general, $fee_obc, $fee_sc_st, $official_url,
            $pdf_path, $status, $created_by ?: null
        ]);
        $new_id = (int)$pdo->lastInsertId();

        if ($send_telegram && get_setting('telegram_enabled') === '1') {
            $site_url = rtrim(get_setting('site_url') ?: '', '/');
            $tg_msg = "<b>New Exam Notification!</b>\n"
                    . "<b>Exam:</b> " . htmlspecialchars($title) . "\n"
                    . "<b>Board:</b> " . htmlspecialchars($conducting_body) . "\n"
                    . "<b>Last Date:</b> " . ($last_date_apply ?: 'N/A') . "\n"
                    . "<b>Vacancies:</b> " . ($vacancies ?: 'N/A') . "\n"
                    . "<a href='{$site_url}/notification/{$slug}'>View Details</a>";
            send_telegram($tg_msg);
            $pdo->prepare("UPDATE notifications SET telegram_sent = 1 WHERE id = ?")->execute([$new_id]);
        }

        header('Location: /admin/notifications/list.php?success=1');
        exit;
    }
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-plus me-2"></i>Add Notification</h2>
    <a href="/admin/notifications/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i>Back to List
    </a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="row g-3">
          <!-- Title + slug preview -->
          <div class="col-12">
            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="notif-title" class="form-control"
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
            <div class="form-text">Slug preview: <code id="slug-preview"></code></div>
          </div>

          <!-- Category -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
            <select name="category" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach (['SSC','UPSC','Railway','Banking','StatePSC','Defence','Other'] as $cat): ?>
              <option value="<?= $cat ?>" <?= ($_POST['category']??'')===$cat?'selected':'' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Conducting Body -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Conducting Body</label>
            <input type="text" name="conducting_body" class="form-control"
                   value="<?= htmlspecialchars($_POST['conducting_body'] ?? '') ?>" placeholder="e.g. SSC, UPSC, RRB">
          </div>

          <!-- Short Description -->
          <div class="col-12">
            <label class="form-label fw-semibold">Short Description</label>
            <textarea name="short_desc" class="form-control" rows="3" maxlength="300"
                      placeholder="Max 300 characters"><?= htmlspecialchars($_POST['short_desc'] ?? '') ?></textarea>
          </div>

          <!-- Full Content -->
          <div class="col-12">
            <label class="form-label fw-semibold">Full Content</label>
            <textarea name="full_content" class="form-control" rows="10"><?= htmlspecialchars($_POST['full_content'] ?? '') ?></textarea>
          </div>

          <!-- Dates -->
          <div class="col-md-4">
            <label class="form-label">Notification Date</label>
            <input type="date" name="notification_date" class="form-control"
                   value="<?= htmlspecialchars($_POST['notification_date'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Date to Apply</label>
            <input type="date" name="last_date_apply" class="form-control"
                   value="<?= htmlspecialchars($_POST['last_date_apply'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Exam Date</label>
            <input type="date" name="exam_date" class="form-control"
                   value="<?= htmlspecialchars($_POST['exam_date'] ?? '') ?>">
          </div>

          <!-- Vacancies + Age -->
          <div class="col-md-6">
            <label class="form-label">Vacancies</label>
            <input type="number" name="vacancies" class="form-control" min="0"
                   value="<?= htmlspecialchars($_POST['vacancies'] ?? '') ?>" placeholder="e.g. 1500">
          </div>
          <div class="col-md-6">
            <label class="form-label">Age Limit</label>
            <input type="text" name="age_limit" class="form-control"
                   value="<?= htmlspecialchars($_POST['age_limit'] ?? '') ?>" placeholder="e.g. 18-27 years">
          </div>

          <!-- Qualification -->
          <div class="col-12">
            <label class="form-label">Qualification</label>
            <textarea name="qualification" class="form-control" rows="3"><?= htmlspecialchars($_POST['qualification'] ?? '') ?></textarea>
          </div>

          <!-- Application Fee -->
          <div class="col-12">
            <label class="form-label fw-semibold">Application Fee (INR)</label>
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small">General</label>
                <input type="number" name="fee_general" class="form-control" step="0.01" min="0"
                       value="<?= htmlspecialchars($_POST['fee_general'] ?? '') ?>" placeholder="e.g. 100">
              </div>
              <div class="col-md-4">
                <label class="form-label small">OBC</label>
                <input type="number" name="fee_obc" class="form-control" step="0.01" min="0"
                       value="<?= htmlspecialchars($_POST['fee_obc'] ?? '') ?>" placeholder="e.g. 100">
              </div>
              <div class="col-md-4">
                <label class="form-label small">SC/ST</label>
                <input type="number" name="fee_sc_st" class="form-control" step="0.01" min="0"
                       value="<?= htmlspecialchars($_POST['fee_sc_st'] ?? '') ?>" placeholder="e.g. 0">
              </div>
            </div>
          </div>

          <!-- Official URL -->
          <div class="col-12">
            <label class="form-label">Official URL</label>
            <input type="url" name="official_url" class="form-control"
                   value="<?= htmlspecialchars($_POST['official_url'] ?? '') ?>" placeholder="https://...">
          </div>

          <!-- PDF Upload -->
          <div class="col-md-6">
            <label class="form-label">PDF Upload</label>
            <input type="file" name="pdf" class="form-control" accept=".pdf">
            <div class="form-text">Only PDF files accepted.</div>
          </div>

          <!-- Status -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['upcoming'=>'Upcoming','active'=>'Active','result'=>'Result Out','admitcard'=>'Admit Card'] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= ($_POST['status']??'upcoming')===$val?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Telegram -->
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="send_telegram" value="1" id="send-tg"
                     <?= !empty($_POST['send_telegram'])?'checked':'' ?>>
              <label class="form-check-label" for="send-tg">
                <i class="fab fa-telegram text-primary me-1"></i>Send Telegram Notification
                <small class="text-muted">(only if Telegram is enabled in settings)</small>
              </label>
            </div>
          </div>

          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Save Notification
            </button>
            <a href="/admin/notifications/list.php" class="btn btn-outline-secondary ms-2">Cancel</a>
          </div>
        </div><!-- /.row -->
      </form>
    </div>
  </div>

</div>
</div>
<?php
$extra_js = '<script>
document.getElementById("notif-title").addEventListener("input", function() {
    var slug = this.value.toLowerCase().trim()
        .replace(/[^a-z0-9\s\-]/g, "")
        .replace(/[\s\-]+/g, "-")
        .replace(/^-+|-+$/g, "");
    document.getElementById("slug-preview").textContent = slug;
});
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
