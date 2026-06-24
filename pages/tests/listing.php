<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();

$exam_type  = trim($_GET['exam_type'] ?? '');
$subject    = trim($_GET['subject'] ?? '');
$difficulty = trim($_GET['difficulty'] ?? '');
$access     = trim($_GET['access'] ?? '');
$sort       = trim($_GET['sort'] ?? 'newest');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 15;

$allowed_types  = get_exam_categories();
$allowed_diff   = ['easy', 'medium', 'hard'];
$allowed_access = ['free', 'premium'];
$allowed_sorts  = ['newest', 'oldest', 'price_asc', 'price_desc'];

// exam_type (category) is free-form now; just trim it.
if ($difficulty && !in_array($difficulty, $allowed_diff, true))   $difficulty = '';
if ($access     && !in_array($access, $allowed_access, true))     $access     = '';
if (!in_array($sort, $allowed_sorts, true))                       $sort       = 'newest';

// Build WHERE
$conditions = ['is_active = 1'];
$params     = [];

if ($exam_type) {
    $conditions[] = 'category = ?';
    $params[]     = $exam_type;
}
if ($subject) {
    $conditions[] = '(title LIKE ? OR description LIKE ?)';
    $params[]     = "%$subject%";
    $params[]     = "%$subject%";
}
if ($access) {
    $conditions[] = 'access_type = ?';
    $params[]     = $access;
}
$where = implode(' AND ', $conditions);

$order = match($sort) {
    'oldest'     => 'created_at ASC',
    'price_asc'  => 'marks_per_question ASC',
    'price_desc' => 'marks_per_question DESC',
    default      => 'created_at DESC',
};

try {
    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM mock_tests WHERE $where");
    $cnt_stmt->execute($params);
    $total = (int)$cnt_stmt->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

$pagination = paginate($total, $per_page, $page);

try {
    $stmt = $pdo->prepare("SELECT id, title, slug, category, description, total_questions, duration_minutes, marks_per_question, negative_marking, access_type, attempts_count FROM mock_tests WHERE $where ORDER BY $order LIMIT ? OFFSET ?");
    $stmt->execute([...$params, $per_page, $pagination['offset']]);
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tests = [];
}

function build_test_filter_url($overrides = [])
{
    $params = [
        'exam_type'  => $_GET['exam_type'] ?? '',
        'subject'    => $_GET['subject'] ?? '',
        'difficulty' => $_GET['difficulty'] ?? '',
        'access'     => $_GET['access'] ?? '',
        'sort'       => $_GET['sort'] ?? 'newest',
        'page'       => $_GET['page'] ?? 1,
    ];
    foreach ($overrides as $k => $v) $params[$k] = $v;
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '/pages/tests/listing.php?' . http_build_query($params);
}

$page_title = 'Mock Tests';
require_once ROOT . '/includes/seo.php';
seo_set([
    'title'       => 'Free Government Exam Mock Tests | Exam Duniya',
    'description' => 'Practice free and premium online mock tests for SSC, UPSC, Railway, Banking, Defence and State exams on Exam Duniya, with instant scores and detailed solutions.',
    'canonical'   => '/mock-tests/',
    'robots'      => 'index,follow',
]);
require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item active">Mock Tests</li>
        </ol>
    </nav>

    <div class="row g-4">

        <!-- Sidebar Filters -->
        <div class="col-lg-3">

            <!-- Exam Type Filter -->
            <div class="card no-lift mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-filter me-2 text-primary"></i>Exam Type</h6>
                </div>
                <div class="card-body py-2">
                    <?php foreach (array_merge([''], $allowed_types) as $et):
                        $label = $et === '' ? 'All Types' : ($et === 'StatePSC' ? 'State PSC' : htmlspecialchars($et));
                        $active = ($exam_type === $et) ? ' active' : '';
                    ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exam_type_radio" id="et_<?= htmlspecialchars($et ?: 'all') ?>"
                                <?= $active ? 'checked' : '' ?>
                                onchange="window.location='<?= htmlspecialchars(build_test_filter_url(['exam_type' => $et, 'page' => 1])) ?>'">
                            <label class="form-check-label" for="et_<?= htmlspecialchars($et ?: 'all') ?>">
                                <?= $label ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Access Filter -->
            <div class="card no-lift mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-lock-open me-2 text-primary"></i>Access</h6>
                </div>
                <div class="card-body py-2">
                    <?php foreach (['' => 'All Tests', 'free' => 'FREE Only', 'premium' => 'PAID Only'] as $val => $lbl): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="access_radio" id="acc_<?= htmlspecialchars($val ?: 'all') ?>"
                                <?= ($access === $val) ? 'checked' : '' ?>
                                onchange="window.location='<?= htmlspecialchars(build_test_filter_url(['access' => $val, 'page' => 1])) ?>'">
                            <label class="form-check-label" for="acc_<?= htmlspecialchars($val ?: 'all') ?>">
                                <?= htmlspecialchars($lbl) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Subject Filter -->
            <div class="card no-lift mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-book me-2 text-primary"></i>Subject / Keyword</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="/pages/tests/listing.php">
                        <?php if ($exam_type): ?><input type="hidden" name="exam_type" value="<?= htmlspecialchars($exam_type) ?>"><?php endif; ?>
                        <?php if ($access): ?><input type="hidden" name="access" value="<?= htmlspecialchars($access) ?>"><?php endif; ?>
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        <div class="input-group input-group-sm">
                            <input type="search" name="subject" class="form-control"
                                   placeholder="e.g. Maths, GK..."
                                   value="<?= htmlspecialchars($subject) ?>">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i></button>
                        </div>
                    </form>
                    <?php if ($subject): ?>
                        <a href="<?= htmlspecialchars(build_test_filter_url(['subject' => '', 'page' => 1])) ?>"
                           class="btn btn-sm btn-outline-secondary mt-2 w-100">
                            <i class="fa-solid fa-xmark me-1"></i>Clear Keyword
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sort -->
            <div class="card no-lift mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-sort me-2 text-primary"></i>Sort By</h6>
                </div>
                <div class="card-body py-2">
                    <?php foreach (['newest' => 'Newest First', 'oldest' => 'Oldest First'] as $val => $lbl): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sort_radio" id="sort_<?= htmlspecialchars($val) ?>"
                                <?= ($sort === $val) ? 'checked' : '' ?>
                                onchange="window.location='<?= htmlspecialchars(build_test_filter_url(['sort' => $val, 'page' => 1])) ?>'">
                            <label class="form-check-label" for="sort_<?= htmlspecialchars($val) ?>">
                                <?= htmlspecialchars($lbl) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($exam_type || $subject || $access): ?>
                <a href="/pages/tests/listing.php" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fa-solid fa-xmark me-1"></i>Clear All Filters
                </a>
            <?php endif; ?>

        </div>

        <!-- Main Content -->
        <div class="col-lg-9">

            <!-- Results info -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-muted mb-0 small">
                    Showing
                    <strong><?= number_format(min($pagination['offset'] + 1, max($total, 1))) ?>&ndash;<?= number_format(min($pagination['offset'] + $per_page, $total)) ?></strong>
                    of <strong><?= number_format($total) ?></strong> tests
                    <?php if ($subject): ?> for "<em><?= htmlspecialchars($subject) ?></em>"<?php endif; ?>
                </p>
            </div>

            <!-- Test Cards -->
            <?php if (empty($tests)): ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-file-circle-question fa-4x text-muted opacity-50 mb-3"></i>
                    <h5 class="text-muted">No tests found</h5>
                    <p class="text-muted small">Try adjusting your filters.</p>
                    <a href="/pages/tests/listing.php" class="btn btn-outline-primary mt-2">Clear All Filters</a>
                </div>
            <?php else: ?>
                <?php foreach ($tests as $test):
                    $is_free = ($test['access_type'] === 'free');
                    $cat_label = $test['category'] === 'StatePSC' ? 'State PSC' : htmlspecialchars($test['category'] ?? '');
                ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="badge bg-primary"><?= $cat_label ?></span>
                                <?php if ($is_free): ?>
                                    <span class="badge bg-success">FREE</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">PREMIUM</span>
                                <?php endif; ?>
                            </div>
                            <h5 class="card-title mt-2"><?= htmlspecialchars($test['title']) ?></h5>
                            <?php if ($test['description']): ?>
                                <p class="text-muted small mb-2"><?= htmlspecialchars(excerpt($test['description'], 100)) ?></p>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-3 text-muted small mb-3">
                                <span><i class="fas fa-question-circle"></i> <?= (int)$test['total_questions'] ?> Qs</span>
                                <span><i class="fas fa-clock"></i> <?= (int)$test['duration_minutes'] ?> min</span>
                                <span><i class="fas fa-star"></i> <?= number_format((float)$test['marks_per_question'], 2) ?> marks/q</span>
                                <?php if ($test['negative_marking'] > 0): ?>
                                    <span class="text-danger"><i class="fas fa-minus-circle"></i> -<?= number_format((float)$test['negative_marking'], 2) ?> neg</span>
                                <?php endif; ?>
                                <span><i class="fas fa-users"></i> <?= number_format((int)$test['attempts_count']) ?> attempts</span>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($is_free): ?>
                                    <a href="/pages/tests/attempt.php?test_id=<?= (int)$test['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="fa-solid fa-play me-1"></i>Start Test
                                    </a>
                                <?php else: ?>
                                    <a href="/pages/tests/purchase.php?test_id=<?= (int)$test['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-lock-open me-1"></i>Get Access
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Tests pagination" class="mt-4">
                        <ul class="pagination justify-content-center flex-wrap">
                            <li class="page-item<?= !$pagination['has_prev'] ? ' disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars(build_test_filter_url(['page' => $page - 1])) ?>">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php
                            $range_start = max(1, $page - 2);
                            $range_end   = min($pagination['total_pages'], $page + 2);
                            if ($range_start > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= htmlspecialchars(build_test_filter_url(['page' => 1])) ?>">1</a>
                                </li>
                                <?php if ($range_start > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $range_start; $i <= $range_end; $i++): ?>
                                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(build_test_filter_url(['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($range_end < $pagination['total_pages']): ?>
                                <?php if ($range_end < $pagination['total_pages'] - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= htmlspecialchars(build_test_filter_url(['page' => $pagination['total_pages']])) ?>"><?= $pagination['total_pages'] ?></a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item<?= !$pagination['has_next'] ? ' disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars(build_test_filter_url(['page' => $page + 1])) ?>">
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

<div class="main-content" style="display:none;"></div>

<?php require_once ROOT . '/includes/footer.php'; ?>
