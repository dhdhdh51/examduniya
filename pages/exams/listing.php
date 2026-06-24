<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();

$category = trim($_GET['category'] ?? $_GET['cat'] ?? '');
$status   = trim($_GET['status'] ?? '');
$search   = trim($_GET['q'] ?? '');
$sort     = trim($_GET['sort'] ?? 'newest');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

// Allowed categories: pull the managed list (any exam name supported)
$allowed_cats     = get_exam_categories();
$allowed_statuses = ['upcoming', 'active', 'result', 'admitcard'];

// Category is free-form; just trim it. (No longer restricted to a fixed set.)
if ($status   && !in_array($status,   $allowed_statuses, true)) $status = '';

// Build WHERE
$conditions = ['1=1'];
$params     = [];
if ($category) { $conditions[] = 'category = ?'; $params[] = $category; }
if ($status)   { $conditions[] = 'status = ?';   $params[] = $status; }
if ($search)   {
    $conditions[] = '(title LIKE ? OR short_desc LIKE ?)';
    $params[]     = "%$search%";
    $params[]     = "%$search%";
}
$where = implode(' AND ', $conditions);

$order = match($sort) {
    'oldest'   => 'created_at ASC',
    'vacancies'=> 'vacancies DESC',
    'deadline' => 'last_date_apply ASC',
    default    => 'created_at DESC',
};

try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

$pagination = paginate($total, $per_page, $page);

try {
    $stmt = $pdo->prepare("SELECT id, title, slug, category, status, conducting_body, last_date_apply, vacancies, short_desc FROM notifications WHERE $where ORDER BY $order LIMIT ? OFFSET ?");
    $stmt->execute([...$params, $per_page, $pagination['offset']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notifications = [];
}

$page_title = 'Exam Notifications' . ($category ? " — $category" : '');
$status_labels = [
    'upcoming'  => 'Upcoming',
    'active'    => 'Active',
    'result'    => 'Result Out',
    'admitcard' => 'Admit Card',
];

// --- SEO: only the clean category / base listing is indexable. Any search,
// status, sort or pagination view is noindex,follow to avoid duplicate /
// thin indexable pages.
require_once ROOT . '/includes/seo.php';
require_once ROOT . '/includes/schema.php';
$is_filtered = ($search !== '' || $status !== '' || $sort !== 'newest' || $page > 1);
if ($category && !$is_filtered) {
    seo_set([
        'title'       => seo_category_title($category === 'StatePSC' ? 'State PSC' : $category),
        'description' => seo_clamp_description('Latest ' . ($category === 'StatePSC' ? 'State PSC' : $category)
            . ' government job notifications, admit cards, results and exam updates on Exam Duniya. Find dates, vacancies, eligibility and official links.'),
        'canonical'   => category_url($category),
        'robots'      => 'index,follow',
    ]);
} elseif (!$category && !$is_filtered) {
    seo_set([
        'title'       => 'Latest Government Exam Notifications | Exam Duniya',
        'description' => seo_clamp_description('Browse the latest SSC, UPSC, Railway, Banking, Defence and State government exam notifications, admit cards and results on Exam Duniya.'),
        'canonical'   => '/exams/',
        'robots'      => 'index,follow',
    ]);
} else {
    seo_set([
        'canonical' => $category ? category_url($category) : '/exams/',
        'robots'    => 'noindex,follow',
    ]);
}

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

// Structured data for the listing / category page.
schema_breadcrumbs(array_values(array_filter([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Exams', 'url' => '/exams/'],
    $category ? ['name' => ($category === 'StatePSC' ? 'State PSC' : $category), 'url' => category_url($category)] : null,
])));
if ($category && !$is_filtered) {
    schema_collection_page('Latest ' . ($category === 'StatePSC' ? 'State PSC' : $category) . ' Jobs', category_url($category));
}

// Build current filter URL (without page/sort)
function build_filter_url($overrides = [])
{
    $params = [
        'category' => $_GET['category'] ?? $_GET['cat'] ?? '',
        'status'   => $_GET['status'] ?? '',
        'q'        => $_GET['q'] ?? '',
        'sort'     => $_GET['sort'] ?? 'newest',
        'page'     => $_GET['page'] ?? 1,
    ];
    foreach ($overrides as $k => $v) $params[$k] = $v;
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '/pages/exams/listing.php?' . http_build_query($params);
}
?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item"><a href="/exams/">Exams</a></li>
            <?php if ($category): ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($category === 'StatePSC' ? 'State PSC' : $category) ?></li>
            <?php else: ?>
                <li class="breadcrumb-item active">Notifications</li>
            <?php endif; ?>
        </ol>
    </nav>

    <?php $cat_label = $category === 'StatePSC' ? 'State PSC' : $category; ?>
    <header class="mb-3">
        <h1 class="h3 fw-bold mb-1">
            <?= $category
                ? 'Latest ' . htmlspecialchars($cat_label) . ' Jobs, Admit Cards &amp; Results'
                : 'Government Exam Notifications' ?>
        </h1>
        <?php if ($category && !$is_filtered): ?>
            <p class="text-muted">
                Find the latest <?= htmlspecialchars($cat_label) ?> recruitment notifications on Exam Duniya, with
                application dates, vacancy details, eligibility and official links. Closed posts are kept for
                reference and clearly marked, while active opportunities appear first. Always verify details on the
                official website before applying.
            </p>
        <?php elseif (!$category && !$is_filtered): ?>
            <p class="text-muted">
                Browse the latest SSC, UPSC, Railway, Banking, Defence, Police and State government exam
                notifications, admit cards and results — updated regularly and verified against official sources.
            </p>
        <?php endif; ?>
    </header>

    <div class="row g-4">

        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="card no-lift mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-filter me-2 text-primary"></i>Filter By Category</h6>
                </div>
                <div class="card-body py-2">
                    <div class="list-group list-group-flush">
                        <a href="<?= htmlspecialchars(build_filter_url(['category' => '', 'page' => 1])) ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between<?= !$category ? ' active' : '' ?>">
                            All Categories
                        </a>
                        <?php foreach ($allowed_cats as $cat): ?>
                            <a href="<?= htmlspecialchars(build_filter_url(['category' => $cat, 'page' => 1])) ?>"
                               class="list-group-item list-group-item-action<?= ($category === $cat) ? ' active' : '' ?>">
                                <?= htmlspecialchars($cat === 'StatePSC' ? 'State PSC' : $cat) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card no-lift mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-circle-dot me-2 text-primary"></i>Filter By Status</h6>
                </div>
                <div class="card-body py-2">
                    <div class="list-group list-group-flush">
                        <a href="<?= htmlspecialchars(build_filter_url(['status' => '', 'page' => 1])) ?>"
                           class="list-group-item list-group-item-action<?= !$status ? ' active' : '' ?>">
                            All Status
                        </a>
                        <?php foreach ($status_labels as $val => $lbl): ?>
                            <a href="<?= htmlspecialchars(build_filter_url(['status' => $val, 'page' => 1])) ?>"
                               class="list-group-item list-group-item-action<?= ($status === $val) ? ' active' : '' ?>">
                                <span class="badge-status badge-<?= htmlspecialchars($val) ?> me-1" style="font-size:0.65rem;">&nbsp;</span>
                                <?= htmlspecialchars($lbl) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">

            <!-- Search + Sort Bar -->
            <div class="card no-lift mb-3">
                <div class="card-body py-2">
                    <form method="GET" action="/pages/exams/listing.php" class="row g-2 align-items-center">
                        <?php if ($category): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                        <?php endif; ?>
                        <?php if ($status): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <?php endif; ?>
                        <div class="col-sm-8">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="search" name="q" class="form-control"
                                       placeholder="Search notifications..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="newest"    <?= $sort === 'newest'    ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest"    <?= $sort === 'oldest'    ? 'selected' : '' ?>>Oldest First</option>
                                <option value="vacancies" <?= $sort === 'vacancies' ? 'selected' : '' ?>>Most Vacancies</option>
                                <option value="deadline"  <?= $sort === 'deadline'  ? 'selected' : '' ?>>Nearest Deadline</option>
                            </select>
                        </div>
                        <div class="col-sm-1">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Count -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-muted mb-0 small">
                    Showing
                    <strong><?= number_format(min($pagination['offset'] + 1, $total)) ?>&ndash;<?= number_format(min($pagination['offset'] + $per_page, $total)) ?></strong>
                    of <strong><?= number_format($total) ?></strong> notifications
                    <?php if ($search): ?>
                        for "<em><?= htmlspecialchars($search) ?></em>"
                    <?php endif; ?>
                </p>
                <?php if ($search || $category || $status): ?>
                    <a href="/pages/exams/listing.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-xmark me-1"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>

            <!-- Notification Cards -->
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-search fa-4x text-muted opacity-50 mb-3"></i>
                    <h5 class="text-muted">No notifications found</h5>
                    <p class="text-muted small">Try adjusting your filters or search query.</p>
                    <a href="/pages/exams/listing.php" class="btn btn-outline-primary mt-2">Clear All Filters</a>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($notifications as $notif):
                        $is_deadline_near = !empty($notif['last_date_apply'])
                            && strtotime($notif['last_date_apply']) > time()
                            && (strtotime($notif['last_date_apply']) - time()) < 7 * 86400;
                        $is_past = !empty($notif['last_date_apply']) && strtotime($notif['last_date_apply']) < time();
                    ?>
                        <div class="col-md-6">
                            <div class="exam-card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="category-badge"><?= htmlspecialchars($notif['category']) ?></span>
                                        <span class="badge-status badge-<?= htmlspecialchars($notif['status']) ?>">
                                            <?= htmlspecialchars($status_labels[$notif['status']] ?? ucfirst($notif['status'])) ?>
                                        </span>
                                    </div>
                                    <h6 class="exam-title"><?= htmlspecialchars($notif['title']) ?></h6>
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
                                                    <span class="badge bg-warning text-dark" style="font-size:0.6rem;">Closing Soon</span>
                                                <?php elseif ($is_past): ?>
                                                    <span class="badge bg-danger" style="font-size:0.6rem;">Closed</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-auto">
                                        <a href="<?= htmlspecialchars(exam_url($notif['slug'])) ?>"
                                           class="btn btn-primary btn-sm w-100">
                                            View Details <i class="fa-solid fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Notifications pagination">
                        <ul class="pagination justify-content-center flex-wrap">
                            <li class="page-item<?= !$pagination['has_prev'] ? ' disabled' : '' ?>">
                                <a class="page-link"
                                   href="<?= htmlspecialchars(build_filter_url(['page' => $page - 1])) ?>">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php
                            $range_start = max(1, $page - 2);
                            $range_end   = min($pagination['total_pages'], $page + 2);
                            if ($range_start > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= htmlspecialchars(build_filter_url(['page' => 1])) ?>">1</a>
                                </li>
                                <?php if ($range_start > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $range_start; $i <= $range_end; $i++): ?>
                                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                                    <a class="page-link"
                                       href="<?= htmlspecialchars(build_filter_url(['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($range_end < $pagination['total_pages']): ?>
                                <?php if ($range_end < $pagination['total_pages'] - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link"
                                       href="<?= htmlspecialchars(build_filter_url(['page' => $pagination['total_pages']])) ?>">
                                        <?= $pagination['total_pages'] ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item<?= !$pagination['has_next'] ? ' disabled' : '' ?>">
                                <a class="page-link"
                                   href="<?= htmlspecialchars(build_filter_url(['page' => $page + 1])) ?>">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once ROOT . '/includes/footer.php'; ?>
