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

// Homepage category shortcuts — pull from managed list (dynamic), cap at 8
$cat_icons  = [
    'SSC'      => 'fa-briefcase',
    'UPSC'     => 'fa-landmark',
    'Railway'  => 'fa-train',
    'Banking'  => 'fa-building-columns',
    'Defence'  => 'fa-shield-halved',
    'StatePSC' => 'fa-flag',
    'State PSC'=> 'fa-flag',
    'UP Police'        => 'fa-user-shield',
    'Bihar Police'     => 'fa-user-shield',
    'MP Police'        => 'fa-user-shield',
    'Rajasthan Police' => 'fa-user-shield',
    'UPSSSC'   => 'fa-file-signature',
    'Teaching (CTET/TET)' => 'fa-chalkboard-user',
    'Nursing'  => 'fa-user-nurse',
    'Other'    => 'fa-ellipsis',
];
$all_cats   = get_exam_categories();
// Don't show a generic "Other" tile on the homepage grid.
$categories = array_values(array_filter($all_cats, function ($c) {
    return strcasecmp($c, 'Other') !== 0;
}));
$categories = array_slice($categories, 0, 8);
$status_labels = [
    'upcoming'  => 'Upcoming',
    'active'    => 'Active',
    'result'    => 'Result Out',
    'admitcard' => 'Admit Card',
];
?>

<!-- Hero Section -->
<section class="hero text-white">
    <!-- Floating gradient blobs -->
    <span class="hero-blob hero-blob-1"></span>
    <span class="hero-blob hero-blob-2"></span>
    <span class="hero-blob hero-blob-3"></span>

    <div class="container hero-content">
        <div class="row justify-content-center text-center">
            <div class="col-lg-9">
                <span class="hero-eyebrow mb-3">
                    <i class="fa-solid fa-bolt"></i> India's all-in-one exam companion
                </span>
                <h1 class="hero-title mb-3">Your Gateway to <span class="hero-gradient-text">Government Jobs</span></h1>
                <p class="hero-subtitle mb-4">Latest SSC, UPSC, Railway &amp; Banking notifications, plus free &amp; premium mock tests — all in one modern platform.</p>

                <!-- Category Pills -->
                <div class="category-pills mb-4">
                    <?php foreach ($categories as $cat): ?>
                        <a href="/pages/exams/listing.php?category=<?= urlencode($cat) ?>"
                           class="hero-pill">
                            <i class="fa-solid <?= htmlspecialchars($cat_icons[$cat] ?? 'fa-star') ?>"></i>
                            <?= htmlspecialchars($cat === 'StatePSC' ? 'State PSC' : $cat) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- CTA Buttons -->
                <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                    <a href="/pages/exams/" class="btn btn-light btn-lg fw-semibold rounded-pill px-4 hero-cta">
                        <i class="fa-solid fa-bell me-2 text-primary"></i>View Notifications
                    </a>
                    <a href="/pages/tests/" class="btn btn-lg fw-semibold rounded-pill px-4 btn-gradient-accent">
                        <i class="fa-solid fa-file-pen me-2"></i>Start Mock Test
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Bar -->
<section class="stats-bar py-4 py-md-5">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-icon stat-grad-blue"><i class="fa-solid fa-bell"></i></div>
                    <div>
                        <div class="stat-card-value"><?= number_format((int)$stats['total_notifications']) ?>+</div>
                        <div class="stat-card-label">Notifications</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-icon stat-grad-green"><i class="fa-solid fa-file-pen"></i></div>
                    <div>
                        <div class="stat-card-value"><?= number_format((int)$stats['total_tests']) ?>+</div>
                        <div class="stat-card-label">Mock Tests</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-icon stat-grad-purple"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="stat-card-value"><?= number_format((int)$stats['total_users']) ?>+</div>
                        <div class="stat-card-label">Registered Users</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-icon stat-grad-orange"><i class="fa-solid fa-trophy"></i></div>
                    <div>
                        <div class="stat-card-value"><?= number_format((int)$stats['total_purchases']) ?>+</div>
                        <div class="stat-card-label">Test Purchases</div>
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
