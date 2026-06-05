<?php
define('ROOT', __DIR__);
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();
$page_title = 'Home';
$meta_desc  = get_setting('site_description') ?: 'Government Exam Notifications, Mock Tests & Study Material';

// Stats
try {
    $stats = $pdo->query("SELECT
        (SELECT COUNT(*) FROM notifications) as total_notifications,
        (SELECT COUNT(*) FROM mock_tests WHERE is_active=1) as total_tests,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM payments WHERE status='success') as total_purchases
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total_notifications' => 0, 'total_tests' => 0, 'total_users' => 0, 'total_purchases' => 0];
}

// Latest Notifications
try {
    $stmt = $pdo->prepare("SELECT id, title, slug, category, status, conducting_body, last_date_apply, vacancies FROM notifications WHERE status != 'upcoming' OR status = 'upcoming' ORDER BY created_at DESC LIMIT 6");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notifications = [];
}

// Featured Mock Tests
try {
    $stmt = $pdo->prepare("SELECT id, title, slug, category, total_questions, duration_minutes, access_type, description FROM mock_tests WHERE is_active=1 ORDER BY attempts_count DESC LIMIT 3");
    $stmt->execute();
    $mock_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mock_tests = [];
}

// Recent Blog Posts
try {
    $stmt = $pdo->prepare("SELECT id, title, slug, featured_image, category, created_at FROM blogs WHERE is_published=1 ORDER BY created_at DESC LIMIT 3");
    $stmt->execute();
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blogs = [];
}

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

$categories = ['SSC', 'UPSC', 'Railway', 'Banking', 'Defence', 'StatePSC'];
$cat_icons  = [
    'SSC'     => 'fa-briefcase',
    'UPSC'    => 'fa-landmark',
    'Railway' => 'fa-train',
    'Banking' => 'fa-building-columns',
    'Defence' => 'fa-shield-halved',
    'StatePSC'=> 'fa-flag',
];
$status_labels = [
    'upcoming'  => 'Upcoming',
    'active'    => 'Active',
    'result'    => 'Result Out',
    'admitcard' => 'Admit Card',
];
?>

<!-- Hero Section -->
<section class="hero text-white">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h1 class="fw-bold mb-3">Your Gateway to Government Jobs</h1>
                <p class="lead mb-4">Latest SSC, UPSC, Railway, Banking notifications &amp; free mock tests — all in one place.</p>

                <!-- Category Pills -->
                <div class="category-pills mb-3">
                    <?php foreach ($categories as $cat): ?>
                        <a href="/pages/exams/listing.php?category=<?= urlencode($cat) ?>"
                           class="btn btn-sm btn-light fw-semibold rounded-pill px-3 shadow-sm">
                            <i class="fa-solid <?= htmlspecialchars($cat_icons[$cat] ?? 'fa-star') ?> me-1 text-primary"></i>
                            <?= htmlspecialchars($cat === 'StatePSC' ? 'State PSC' : $cat) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- CTA Buttons -->
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                    <a href="/pages/exams/" class="btn btn-light btn-lg fw-semibold shadow-sm">
                        <i class="fa-solid fa-bell me-2 text-primary"></i>View Notifications
                    </a>
                    <a href="/pages/tests/" class="btn btn-warning btn-lg fw-semibold shadow-sm">
                        <i class="fa-solid fa-file-pen me-2"></i>Start Mock Test
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Bar -->
<section class="stats-bar bg-white border-bottom py-4">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="stat-icon bg-primary-light rounded-3">
                        <i class="fa-solid fa-bell text-primary"></i>
                    </div>
                    <div class="text-start">
                        <div class="fw-bold fs-4 text-primary"><?= number_format((int)$stats['total_notifications']) ?></div>
                        <div class="small text-muted">Notifications</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="stat-icon bg-success-light rounded-3">
                        <i class="fa-solid fa-file-pen text-success"></i>
                    </div>
                    <div class="text-start">
                        <div class="fw-bold fs-4 text-success"><?= number_format((int)$stats['total_tests']) ?></div>
                        <div class="small text-muted">Mock Tests</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="stat-icon rounded-3" style="background:#ede9fe;">
                        <i class="fa-solid fa-users" style="color:#7c3aed;"></i>
                    </div>
                    <div class="text-start">
                        <div class="fw-bold fs-4" style="color:#7c3aed;"><?= number_format((int)$stats['total_users']) ?></div>
                        <div class="small text-muted">Registered Users</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="stat-icon bg-danger-light rounded-3">
                        <i class="fa-solid fa-trophy text-danger"></i>
                    </div>
                    <div class="text-start">
                        <div class="fw-bold fs-4 text-danger"><?= number_format((int)$stats['total_purchases']) ?></div>
                        <div class="small text-muted">Test Purchases</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<main class="container my-5">

    <!-- Latest Notifications -->
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 class="section-title mb-0">Latest Notifications</h2>
                <div class="section-divider"></div>
            </div>
            <a href="/pages/exams/listing.php" class="btn btn-outline-primary btn-sm">
                View All <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-bell-slash fa-3x mb-3 opacity-50"></i>
                <p>No notifications yet. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($notifications as $notif):
                    $is_deadline_near = !empty($notif['last_date_apply'])
                        && strtotime($notif['last_date_apply']) > time()
                        && (strtotime($notif['last_date_apply']) - time()) < 7 * 86400;
                    $is_past = !empty($notif['last_date_apply']) && strtotime($notif['last_date_apply']) < time();
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="exam-card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="category-badge"><?= htmlspecialchars($notif['category']) ?></span>
                                    <span class="badge-status badge-<?= htmlspecialchars($notif['status']) ?>">
                                        <?= htmlspecialchars($status_labels[$notif['status']] ?? ucfirst($notif['status'])) ?>
                                    </span>
                                </div>
                                <h6 class="exam-title">
                                    <?= htmlspecialchars(mb_strlen($notif['title']) > 60 ? mb_substr($notif['title'], 0, 60) . '...' : $notif['title']) ?>
                                </h6>
                                <div class="exam-meta mb-3">
                                    <?php if ($notif['conducting_body']): ?>
                                        <div class="exam-meta-item">
                                            <i class="fa-solid fa-building"></i>
                                            <?= htmlspecialchars($notif['conducting_body']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($notif['vacancies']): ?>
                                        <div class="exam-meta-item">
                                            <i class="fa-solid fa-users"></i>
                                            <?= number_format((int)$notif['vacancies']) ?> vacancies
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($notif['last_date_apply']): ?>
                                        <div class="exam-meta-item <?= $is_deadline_near ? 'text-warning fw-semibold' : ($is_past ? 'text-danger' : '') ?>">
                                            <i class="fa-solid fa-calendar-xmark"></i>
                                            Last Date: <?= htmlspecialchars(format_date($notif['last_date_apply'])) ?>
                                            <?php if ($is_deadline_near): ?>
                                                <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">Closing Soon</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-auto">
                                    <a href="/pages/exams/detail.php?slug=<?= urlencode($notif['slug']) ?>"
                                       class="btn btn-primary btn-sm w-100">
                                        View Details <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Featured Mock Tests -->
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 class="section-title mb-0">Featured Mock Tests</h2>
                <div class="section-divider"></div>
            </div>
            <a href="/pages/tests/" class="btn btn-outline-primary btn-sm">
                View All <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (empty($mock_tests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-file-pen fa-3x mb-3 opacity-50"></i>
                <p>No mock tests available yet.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($mock_tests as $test):
                    $is_free = ($test['access_type'] === 'free');
                ?>
                    <div class="col-md-4">
                        <div class="pricing-card h-100">
                            <?php if (!$is_free): ?>
                                <span class="popular-badge">Premium</span>
                            <?php endif; ?>
                            <div class="mb-3">
                                <?php if ($is_free): ?>
                                    <span class="badge-status badge-free fs-6 px-3 py-2">FREE</span>
                                <?php else: ?>
                                    <span class="badge-status badge-premium fs-6 px-3 py-2">PREMIUM</span>
                                <?php endif; ?>
                            </div>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($test['title']) ?></h5>
                            <p class="text-muted small mb-3"><?= htmlspecialchars($test['category'] ?? '') ?></p>
                            <ul class="list-unstyled mb-4 text-start">
                                <li class="mb-2">
                                    <i class="fa-solid fa-circle-check text-success me-2"></i>
                                    <?= (int)$test['total_questions'] ?> Questions
                                </li>
                                <li class="mb-2">
                                    <i class="fa-solid fa-clock text-primary me-2"></i>
                                    <?= (int)$test['duration_minutes'] ?> Minutes
                                </li>
                                <?php if ($test['description']): ?>
                                    <li class="mb-2">
                                        <i class="fa-solid fa-info-circle text-info me-2"></i>
                                        <?= htmlspecialchars(excerpt($test['description'], 60)) ?>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <a href="<?= $is_free
                                    ? '/pages/tests/attempt.php?test_id=' . (int)$test['id']
                                    : '/pages/tests/purchase.php?test_id=' . (int)$test['id'] ?>"
                               class="btn <?= $is_free ? 'btn-success' : 'btn-primary' ?> w-100">
                                <?= $is_free ? 'Start Free Test' : 'View Test' ?>
                                <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Recent Blog Posts -->
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 class="section-title mb-0">Recent Blog Posts</h2>
                <div class="section-divider"></div>
            </div>
            <a href="/pages/blog/listing.php" class="btn btn-outline-primary btn-sm">
                View All <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (empty($blogs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-newspaper fa-3x mb-3 opacity-50"></i>
                <p>No blog posts yet.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($blogs as $blog): ?>
                    <div class="col-md-4">
                        <div class="blog-card h-100">
                            <?php if ($blog['featured_image']): ?>
                                <img src="/uploads/blogs/<?= htmlspecialchars($blog['featured_image']) ?>"
                                     alt="<?= htmlspecialchars($blog['title']) ?>"
                                     class="blog-image">
                            <?php else: ?>
                                <div class="blog-image d-flex align-items-center justify-content-center bg-primary-light">
                                    <i class="fa-solid fa-newspaper fa-3x text-primary opacity-50"></i>
                                </div>
                            <?php endif; ?>
                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <?php if ($blog['category']): ?>
                                    <span class="blog-category d-inline-block mb-2">
                                        <?= htmlspecialchars($blog['category']) ?>
                                    </span>
                                <?php endif; ?>
                                <h6 class="blog-title"><?= htmlspecialchars($blog['title']) ?></h6>
                                <p class="text-muted small mt-auto mb-2">
                                    <i class="fa-solid fa-calendar me-1"></i>
                                    <?= htmlspecialchars(format_date($blog['created_at'])) ?>
                                </p>
                                <a href="/pages/blog/detail.php?slug=<?= urlencode($blog['slug']) ?>"
                                   class="btn btn-outline-primary btn-sm">
                                    Read More <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php require_once ROOT . '/includes/footer.php'; ?>
