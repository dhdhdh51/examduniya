<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Edit Notification';
$active_menu = 'notifications';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/notifications/list.php');
    exit;
}

$notif = $pdo->prepare("SELECT * FROM notifications WHERE id = ? LIMIT 1");
$notif->execute([$id]);
$notif = $notif->fetch(PDO::FETCH_ASSOC);

if (!$notif) {
    header('Location: /admin/notifications/list.php');
    exit;
}

$errors = [];

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

    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($category)) $errors[] = 'Category is required.';

    $pdf_path = $notif['pdf_path'];
    if (empty($errors) && !empty($_FILES['pdf']['tmp_name'])) {
        $uploaded = upload_file($_FILES['pdf'], 'notifications', ['pdf']);
        if ($uploaded === false) {
            $errors[] = 'PDF upload failed. Only PDF files are allowed.';
        } else {
            $pdf_path = $uploaded;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "UPDATE notifications SET
             title=?, category=?, conducting_body=?, short_desc=?, full_content=?,
             notification_date=?, last_date_apply=?, exam_date=?, vacancies=?, age_limit=?,
             qualification=?, fee_general=?, fee_obc=?, fee_sc_st=?, official_url=?,
             pdf_path=?, status=?
             WHERE id=?"
        );
        $stmt->execute([
            $title, $category, $conducting_body, $short_desc, $full_content,
            $notification_date, $last_date_apply, $exam_date, $vacancies, $age_limit,
            $qualification, $fee_general, $fee_obc, $fee_sc_st, $official_url,
            $pdf_path, $status, $id
        ]);

        if ($send_telegram && get_setting('telegram_enabled') === '1') {
            $site_url = rtrim(get_setting('site_url') ?: '', '/');
            $tg_msg = "<b>Updated Exam Notification!</b>\n"
                    . "<b>Exam:</b> " . htmlspecialchars($title) . "\n"
                    . "<b>Board:</b> " . htmlspecialchars($conducting_body) . "\n"
                    . "<b>Last Date:</b> " . ($last_date_apply ?: 'N/A') . "\n"
                    . "<a href='{$site_url}/notification/{$notif['slug']}'>View Details</a>";
            send_telegram($tg_msg);
            $pdo->prepare("UPDATE notifications SET telegram_sent = 1 WHERE id = ?")->execute([$id]);
        }

        header('Location: /admin/notifications/list.php?success=1');
        exit;
    }

    // Update $notif with posted data for re-display
    $notif = array_merge($notif, [
        'title'=>$title,'category'=>$category,'conducting_body'=>$conducting_body,
        'short_desc'=>$short_desc,'full_content'=>$full_content,
        'notification_date'=>$notification_date,'last_date_apply'=>$last_date_apply,
        'exam_date'=>$exam_date,'vacancies'=>$vacancies,'age_limit'=>$age_limit,
        'qualification'=>$qualification,'fee_general'=>$fee_general,'fee_obc'=>$fee_obc,
        'fee_sc_st'=>$fee_sc_st,'official_url'=>$official_url,'status'=>$status,
    ]);
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Notification</h2>
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
          <div class="col-12">
            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="<?= htmlspecialchars($notif['title']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
            <input type="text" name="category" class="form-control" required list="category-list"
                   value="<?= htmlspecialchars($notif['category'] ?? '') ?>"
                   placeholder="Type or pick — e.g. UP Police, UPSSSC, SSC">
            <datalist id="category-list">
              <?php foreach (get_exam_categories() as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"></option>
              <?php endforeach; ?>
            </datalist>
            <div class="form-text">Type any exam name — it's not limited to the suggestions.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Conducting Body</label>
            <input type="text" name="conducting_body" class="form-control"
                   value="<?= htmlspecialchars($notif['conducting_body'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Short Description</label>
            <textarea name="short_desc" class="form-control" rows="3" maxlength="300"><?= htmlspecialchars($notif['short_desc'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Full Content</label>
            <textarea name="full_content" class="form-control" rows="10"><?= htmlspecialchars($notif['full_content'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Notification Date</label>
            <input type="date" name="notification_date" class="form-control" value="<?= htmlspecialchars($notif['notification_date'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Date to Apply</label>
            <input type="date" name="last_date_apply" class="form-control" value="<?= htmlspecialchars($notif['last_date_apply'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Exam Date</label>
            <input type="date" name="exam_date" class="form-control" value="<?= htmlspecialchars($notif['exam_date'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Vacancies</label>
            <input type="number" name="vacancies" class="form-control" min="0" value="<?= htmlspecialchars($notif['vacancies'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Age Limit</label>
            <input type="text" name="age_limit" class="form-control" value="<?= htmlspecialchars($notif['age_limit'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Qualification</label>
            <textarea name="qualification" class="form-control" rows="3"><?= htmlspecialchars($notif['qualification'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Application Fee (INR)</label>
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small">General</label>
                <input type="number" name="fee_general" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($notif['fee_general'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small">OBC</label>
                <input type="number" name="fee_obc" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($notif['fee_obc'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small">SC/ST</label>
                <input type="number" name="fee_sc_st" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($notif['fee_sc_st'] ?? '') ?>">
              </div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Official URL</label>
            <input type="url" name="official_url" class="form-control" value="<?= htmlspecialchars($notif['official_url'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">PDF Upload</label>
            <input type="file" name="pdf" class="form-control" accept=".pdf">
            <?php if ($notif['pdf_path']): ?>
            <div class="form-text">Current: <a href="/uploads/notifications/<?= htmlspecialchars($notif['pdf_path']) ?>" target="_blank"><?= htmlspecialchars($notif['pdf_path']) ?></a></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['upcoming'=>'Upcoming','active'=>'Active','result'=>'Result Out','admitcard'=>'Admit Card'] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= $notif['status']===$val?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="send_telegram" value="1" id="send-tg">
              <label class="form-check-label" for="send-tg">
                <i class="fab fa-telegram text-primary me-1"></i>Send Telegram Notification (re-send update)
              </label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>Update Notification
            </button>
            <a href="/admin/notifications/list.php" class="btn btn-outline-secondary ms-2">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
