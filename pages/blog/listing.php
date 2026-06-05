<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();

$category = trim($_GET['category'] ?? '');
$search   = trim($_GET['q'] ?? '');
$sort     = trim($_GET['sort'] ?? 'newest');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

// Build WHERE
$conditions = ["is_published = 1"];
$params     = [];
if ($category) {
    $conditions[] = 'category = ?';
    $params[]     = $category;
}
if ($search) {
    $conditions[] = '(title LIKE ? OR content LIKE ?)';
    $params[]     = "%$search%";
    $params[]     = "%$search%";
}
$where = implode(' AND ', $conditions);

$order = match($sort) {
    'popular' => 'views DESC',
    'oldest'  => 'created_at ASC',
    default   => 'created_at DESC',
};

try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM blogs WHERE $where");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

$pagination = paginate($total, $per_page, $page);

try {
    $stmt = $pdo->prepare("SELECT id, title, slug, featured_image, category, tags, created_at, views, excerpt, content FROM blogs WHERE $where ORDER BY $order LIMIT ? OFFSET ?");
    $stmt->execute([...$params, $per_page, $pagination['offset']]);
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blogs = [];
}

// Get distinct categories for filter pills
try {
    $cats_stmt = $pdo->query("SELECT DISTINCT category FROM blogs WHERE is_published=1 AND category != '' AND category IS NOT NULL ORDER BY category");
    $all_categories = $cats_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $all_categories = [];
}

$page_title = 'Blog' . ($category ? " — $category" : '');

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

function build_blog_url($overrides = [])
{
    $params = [
        'category' => $_GET['category'] ?? '',
        'q'        => $_GET['q'] ?? '',
        'sort'     => $_GET['sort'] ?? 'newest',
        'page'     => $_GET['page'] ?? 1,
    ];
    foreach ($overrides as $k => $v) $params[$k] = $v;
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '/pages/blog/listing.php?' . http_build_query($params);
}
?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item active">Blog</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="section-title mb-0">Blog &amp; Articles</h1>
            <div class="section-divider"></div>
        </div>
    </div>

    <!-- Search + Sort Bar -->
    <div class="card no-lift mb-4">
        <div class="card-body py-2">
            <form method="GET" action="/pages/blog/listing.php" class="row g-2 align-items-center">
                <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <?php endif; ?>
                <div class="col-sm-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="search" name="q" class="form-control"
                               placeholder="Search articles..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-sm-3">
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="newest"  <?= $sort === 'newest'  ? 'selected' : '' ?>>Newest First</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="oldest"  <?= $sort === 'oldest'  ? 'selected' : '' ?>>Oldest First</option>
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

    <!-- Category Filter Pills -->
    <?php if (!empty($all_categories)): ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="/pages/blog/listing.php<?= $search ? '?q=' . urlencode($search) : '' ?>"
               class="btn btn-sm rounded-pill <?= !$category ? 'btn-primary' : 'btn-outline-secondary' ?>">
                All
            </a>
            <?php foreach ($all_categories as $cat): ?>
                <a href="<?= htmlspecialchars(build_blog_url(['category' => $cat, 'page' => 1])) ?>"
                   class="btn btn-sm rounded-pill <?= $category === $cat ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Results Count -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0 small">
            Showing <strong><?= number_format(min($pagination['offset'] + 1, max($total, 1))) ?>&ndash;<?= number_format(min($pagination['offset'] + $per_page, $total)) ?></strong>
            of <strong><?= number_format($total) ?></strong> articles
        </p>
        <?php if ($search || $category): ?>
            <a href="/pages/blog/listing.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-xmark me-1"></i>Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- Blog Cards Grid -->
    <?php if (empty($blogs)): ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-newspaper fa-4x text-muted opacity-50 mb-3"></i>
            <h5 class="text-muted">No articles found</h5>
            <p class="text-muted small">Try adjusting your search or filters.</p>
            <a href="/pages/blog/listing.php" class="btn btn-outline-primary mt-2">Clear Filters</a>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($blogs as $blog):
                $excerpt_text = $blog['excerpt'] ?: excerpt($blog['content'] ?? '', 150);
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="blog-card h-100 d-flex flex-column">
                        <?php if ($blog['featured_image']): ?>
                            <img src="/uploads/blogs/<?= htmlspecialchars($blog['featured_image']) ?>"
                                 alt="<?= htmlspecialchars($blog['title']) ?>"
                                 class="blog-image">
                        <?php else: ?>
                            <div class="blog-image d-flex align-items-center justify-content-center"
                                 style="background:var(--gray-100);">
                                <i class="fa-solid fa-newspaper fa-3x text-muted opacity-40"></i>
                            </div>
                        <?php endif; ?>
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <?php if ($blog['category']): ?>
                                <span class="blog-category d-inline-block mb-2"><?= htmlspecialchars($blog['category']) ?></span>
                            <?php endif; ?>
                            <h6 class="blog-title"><?= htmlspecialchars($blog['title']) ?></h6>
                            <?php if ($excerpt_text): ?>
                                <p class="text-muted small mb-3"><?= htmlspecialchars(mb_substr($excerpt_text, 0, 150)) ?><?= mb_strlen($excerpt_text) > 150 ? '...' : '' ?></p>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <div class="text-muted small">
                                    <i class="fa-solid fa-calendar me-1"></i><?= htmlspecialchars(format_date($blog['created_at'])) ?>
                                    <span class="ms-2"><i class="fa-solid fa-eye me-1"></i><?= number_format((int)$blog['views']) ?></span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="/pages/blog/detail.php?slug=<?= urlencode($blog['slug']) ?>"
                                   class="btn btn-outline-primary btn-sm w-100">
                                    Read More <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <nav aria-label="Blog pagination">
                <ul class="pagination justify-content-center flex-wrap">
                    <li class="page-item<?= !$pagination['has_prev'] ? ' disabled' : '' ?>">
                        <a class="page-link"
                           href="<?= htmlspecialchars(build_blog_url(['page' => $page - 1])) ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $rs = max(1, $page - 2);
                    $re = min($pagination['total_pages'], $page + 2);
                    if ($rs > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= htmlspecialchars(build_blog_url(['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($rs > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $rs; $i <= $re; $i++): ?>
                        <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars(build_blog_url(['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($re < $pagination['total_pages']): ?>
                        <?php if ($re < $pagination['total_pages'] - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= htmlspecialchars(build_blog_url(['page' => $pagination['total_pages']])) ?>">
                                <?= $pagination['total_pages'] ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="page-item<?= !$pagination['has_next'] ? ' disabled' : '' ?>">
                        <a class="page-link"
                           href="<?= htmlspecialchars(build_blog_url(['page' => $page + 1])) ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php require_once ROOT . '/includes/footer.php'; ?>
