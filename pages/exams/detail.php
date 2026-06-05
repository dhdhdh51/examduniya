<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('Location: /pages/exams/listing.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$notif = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notif) {
    http_response_code(404);
    include ROOT . '/404.php';
    exit;
}

$page_title = $notif['title'];
$meta_desc  = excerpt($notif['short_desc'] ?? '', 160);
$site_url   = rtrim(get_setting('site_url') ?: '', '/');

// Related notifications (same category, exclude current)
$stmt2 = $pdo->prepare("SELECT id, title, slug, category, status, last_date_apply FROM notifications WHERE category = ? AND id != ? ORDER BY created_at DESC LIMIT 4");
$stmt2->execute([$notif['category'], $notif['id']]);
$related = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$status_labels = [
    'upcoming'  => 'Upcoming',
    'active'    => 'Active',
    'result'    => 'Result Out',
    'admitcard' => 'Admit Card',
];

$wa_text = urlencode($notif['title'] . ' - ' . $site_url . '/notification/' . $notif['slug']);
$wa_url  = 'https://wa.me/?text=' . $wa_text;
$page_url = $site_url . '/pages/exams/detail.php?slug=' . urlencode($notif['slug']);

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/exams/listing.php">Exams</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars(mb_substr($notif['title'], 0, 40)) ?><?= mb_strlen($notif['title']) > 40 ? '...' : '' ?></li>
        </ol>
    </nav>

    <!-- Hero area -->
    <div class="card no-lift mb-4" style="background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white;border:none;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-white text-primary fw-semibold"><?= htmlspecialchars($notif['category']) ?></span>
                <span class="badge-status badge-<?= htmlspecialchars($notif['status']) ?>">
                    <?= htmlspecialchars($status_labels[$notif['status']] ?? ucfirst($notif['status'])) ?>
                </span>
            </div>
            <h1 class="h2 fw-bold text-white mb-2"><?= htmlspecialchars($notif['title']) ?></h1>
            <?php if ($notif['conducting_body']): ?>
                <p class="mb-0 opacity-90">
                    <i class="fa-solid fa-building me-2"></i>
                    <?= htmlspecialchars($notif['conducting_body']) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">

        <!-- Main Content -->
        <div class="col-lg-8">

            <!-- Short Description -->
            <?php if ($notif['short_desc']): ?>
                <div class="alert alert-info mb-4">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    <?= htmlspecialchars($notif['short_desc']) ?>
                </div>
            <?php endif; ?>

            <!-- Important Dates -->
            <div class="card no-lift mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Important Dates</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tbody>
                            <?php if ($notif['notification_date']): ?>
                                <tr>
                                    <td class="fw-semibold" style="width:50%;">Notification Date</td>
                                    <td><?= htmlspecialchars(format_date($notif['notification_date'])) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($notif['last_date_apply']): ?>
                                <tr>
                                    <td class="fw-semibold">Last Date to Apply</td>
                                    <td class="<?= strtotime($notif['last_date_apply']) < time() ? 'text-danger fw-bold' : '' ?>">
                                        <?= htmlspecialchars(format_date($notif['last_date_apply'])) ?>
                                        <?php if (strtotime($notif['last_date_apply']) < time()): ?>
                                            <span class="badge bg-danger ms-1" style="font-size:0.65rem;">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($notif['exam_date']): ?>
                                <tr>
                                    <td class="fw-semibold">Exam Date</td>
                                    <td><?= htmlspecialchars(format_date($notif['exam_date'])) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Vacancy & Age Limit -->
            <div class="card no-lift mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-users text-success me-2"></i>Vacancy Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if ($notif['vacancies']): ?>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-3 p-3 bg-success-light rounded-3">
                                    <i class="fa-solid fa-users text-success fa-2x"></i>
                                    <div>
                                        <div class="fw-bold fs-4 text-success"><?= number_format((int)$notif['vacancies']) ?></div>
                                        <div class="small text-muted">Total Vacancies</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($notif['age_limit']): ?>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-3 p-3 bg-primary-light rounded-3">
                                    <i class="fa-solid fa-id-card text-primary fa-2x"></i>
                                    <div>
                                        <div class="fw-bold text-primary"><?= htmlspecialchars($notif['age_limit']) ?></div>
                                        <div class="small text-muted">Age Limit</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Application Fee -->
            <?php if ($notif['fee_general'] || $notif['fee_obc'] || $notif['fee_sc_st']): ?>
                <div class="card no-lift mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fa-solid fa-indian-rupee-sign text-warning me-2"></i>Application Fee</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th>Fee Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>General / OBC (others)</td>
                                    <td><?= $notif['fee_general'] ? '&#8377; ' . number_format((float)$notif['fee_general'], 2) : 'NIL' ?></td>
                                </tr>
                                <tr>
                                    <td>OBC / EWS</td>
                                    <td><?= $notif['fee_obc'] ? '&#8377; ' . number_format((float)$notif['fee_obc'], 2) : 'NIL' ?></td>
                                </tr>
                                <tr>
                                    <td>SC / ST / PwD</td>
                                    <td><?= $notif['fee_sc_st'] ? '&#8377; ' . number_format((float)$notif['fee_sc_st'], 2) : 'NIL' ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Qualification -->
            <?php if ($notif['qualification']): ?>
                <div class="card no-lift mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fa-solid fa-graduation-cap text-purple me-2" style="color:#7c3aed;"></i>Eligibility / Qualification</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0" style="white-space:pre-line;"><?= htmlspecialchars($notif['qualification']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Full HTML Content (admin-entered trusted HTML) -->
            <?php if ($notif['full_content']): ?>
                <div class="card no-lift mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fa-solid fa-file-lines text-info me-2"></i>Detailed Information</h5>
                    </div>
                    <div class="card-body prose">
                        <?= $notif['full_content'] ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Official Links -->
            <?php if ($notif['official_url'] || $notif['pdf_path']): ?>
                <div class="card no-lift mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fa-solid fa-link text-primary me-2"></i>Official Links</h5>
                    </div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        <?php if ($notif['official_url']): ?>
                            <a href="<?= htmlspecialchars($notif['official_url']) ?>"
                               class="btn btn-primary"
                               target="_blank" rel="noopener noreferrer">
                                <i class="fa-solid fa-external-link me-2"></i>Official Website
                            </a>
                        <?php endif; ?>
                        <?php if ($notif['pdf_path']): ?>
                            <a href="/uploads/notifications/<?= htmlspecialchars($notif['pdf_path']) ?>"
                               class="btn btn-outline-danger"
                               target="_blank" rel="noopener noreferrer">
                                <i class="fa-solid fa-file-pdf me-2"></i>Download PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">

            <!-- Apply Now Card -->
            <div class="card no-lift mb-3 border-primary">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="badge-status badge-<?= htmlspecialchars($notif['status']) ?> mb-2">
                            <?= htmlspecialchars($status_labels[$notif['status']] ?? ucfirst($notif['status'])) ?>
                        </span>
                    </div>
                    <?php if ($notif['last_date_apply']): ?>
                        <p class="text-muted small mb-1">Last Date to Apply</p>
                        <p class="fw-bold fs-5 mb-3 <?= strtotime($notif['last_date_apply']) < time() ? 'text-danger' : 'text-primary' ?>">
                            <?= htmlspecialchars(format_date($notif['last_date_apply'])) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($notif['official_url'] && $notif['status'] === 'active'): ?>
                        <a href="<?= htmlspecialchars($notif['official_url']) ?>"
                           class="btn btn-success btn-lg w-100 mb-2"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fa-solid fa-pen-to-square me-2"></i>Apply Online
                        </a>
                    <?php elseif ($notif['official_url']): ?>
                        <a href="<?= htmlspecialchars($notif['official_url']) ?>"
                           class="btn btn-outline-primary w-100 mb-2"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fa-solid fa-external-link me-2"></i>Visit Official Site
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Share Card -->
            <div class="card no-lift mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-share-nodes me-2 text-primary"></i>Share</h6>
                </div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <a href="<?= htmlspecialchars($wa_url) ?>"
                       class="btn btn-success btn-sm flex-fill"
                       target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp me-1"></i> WhatsApp
                    </a>
                    <button class="btn btn-outline-secondary btn-sm flex-fill"
                            onclick="copyLink('<?= htmlspecialchars(addslashes($page_url)) ?>')">
                        <i class="fa-solid fa-copy me-1"></i> Copy Link
                    </button>
                </div>
            </div>

            <!-- Related Notifications -->
            <?php if (!empty($related)): ?>
                <div class="card no-lift">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fa-solid fa-layer-group me-2 text-primary"></i>Related Notifications</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($related as $rel):
                                $is_past_rel = !empty($rel['last_date_apply']) && strtotime($rel['last_date_apply']) < time();
                            ?>
                                <a href="/pages/exams/detail.php?slug=<?= urlencode($rel['slug']) ?>"
                                   class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="fw-semibold small" style="line-height:1.4;">
                                            <?= htmlspecialchars(mb_substr($rel['title'], 0, 60)) ?><?= mb_strlen($rel['title']) > 60 ? '...' : '' ?>
                                        </div>
                                        <span class="badge-status badge-<?= htmlspecialchars($rel['status']) ?>" style="font-size:0.6rem;white-space:nowrap;flex-shrink:0;">
                                            <?= htmlspecialchars($status_labels[$rel['status']] ?? ucfirst($rel['status'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($rel['last_date_apply']): ?>
                                        <div class="small mt-1 <?= $is_past_rel ? 'text-danger' : 'text-muted' ?>">
                                            <i class="fa-solid fa-calendar-xmark me-1"></i>
                                            <?= htmlspecialchars(format_date($rel['last_date_apply'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function copyLink(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            showToast('Link copied to clipboard!', 'success');
        }).catch(function() {
            fallbackCopy(url);
        });
    } else {
        fallbackCopy(url);
    }
}
function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); showToast('Link copied!', 'success'); }
    catch(e) { showToast('Could not copy link', 'danger'); }
    document.body.removeChild(ta);
}
</script>

<?php require_once ROOT . '/includes/footer.php'; ?>
