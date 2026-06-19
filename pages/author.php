<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/seo.php';
require_once ROOT . '/includes/schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /');
    exit;
}

// Find an author (a user who has authored at least one published blog) whose
// slugified name matches. Falls back to a site-editor profile for "shivam".
$author = null;
try {
    $rows = $pdo->query(
        "SELECT DISTINCT u.id, u.name, u.avatar
           FROM users u
           JOIN blogs b ON b.author_id = u.id AND b.is_published = 1"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (slug($r['name']) === $slug) { $author = $r; break; }
    }
} catch (Throwable $e) { /* ignore */ }

// Site editor fallback profile.
$known_profiles = [
    'shivam' => [
        'name' => 'Shivam',
        'bio'  => 'Shivam is the editor at Exam Duniya. He tracks government recruitment notifications, admit cards and results, and verifies key details against official sources before they are published.',
    ],
];

if (!$author && !isset($known_profiles[$slug])) {
    http_response_code(404);
    include ROOT . '/404.php';
    exit;
}

$display_name = $author['name'] ?? $known_profiles[$slug]['name'];
$bio          = $known_profiles[$slug]['bio']
    ?? ($display_name . ' contributes exam notifications, study guides and preparation articles on Exam Duniya.');
$avatar       = $author['avatar'] ?? '';

// Published posts by this author.
$posts = [];
if ($author) {
    try {
        $stmt = $pdo->prepare(
            "SELECT title, slug, category, featured_image, COALESCE(published_at, created_at) AS dt
               FROM blogs WHERE author_id = ? AND is_published = 1
               ORDER BY dt DESC LIMIT 24"
        );
        $stmt->execute([$author['id']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* ignore */ }
}

seo_set([
    'title'       => $display_name . ' — Author at Exam Duniya',
    'description' => seo_clamp_description($bio),
    'canonical'   => author_url($slug),
    'og_type'     => 'profile',
]);

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

schema_breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Authors', 'url' => '/'],
    ['name' => $display_name, 'url' => author_url($slug)],
]);
schema_emit([
    '@type' => 'Person',
    'name'  => $display_name,
    'url'   => seo_abs_url(author_url($slug)),
    'description' => $bio,
    'worksFor' => schema_publisher(),
]);
?>
<main class="container my-5">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($display_name) ?></li>
    </ol>
  </nav>

  <div class="d-flex align-items-center gap-3 mb-4">
    <?php if ($avatar): ?>
      <img src="<?= htmlspecialchars(get_avatar_url($avatar)) ?>" class="rounded-circle" width="72" height="72" alt="<?= htmlspecialchars($display_name) ?>"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:72px;height:72px;display:none;">
        <i class="fa-solid fa-user text-white fa-2x"></i>
      </div>
    <?php else: ?>
      <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
        <i class="fa-solid fa-user text-white fa-2x"></i>
      </div>
    <?php endif; ?>
    <div>
      <h1 class="fw-bold mb-1"><?= htmlspecialchars($display_name) ?></h1>
      <p class="text-muted mb-0">Author at Exam Duniya</p>
    </div>
  </div>

  <p class="lead text-muted"><?= htmlspecialchars($bio) ?></p>

  <?php if (!empty($posts)): ?>
    <h2 class="h4 fw-bold mt-4 mb-3">Articles by <?= htmlspecialchars($display_name) ?></h2>
    <div class="row g-3">
      <?php foreach ($posts as $p): ?>
        <div class="col-md-6 col-lg-4">
          <div class="blog-card h-100">
            <div class="p-3 d-flex flex-column h-100">
              <?php if ($p['category']): ?><span class="blog-category d-inline-block mb-2"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
              <h6 class="blog-title"><?= htmlspecialchars($p['title']) ?></h6>
              <p class="text-muted small mt-auto mb-2"><i class="fa-solid fa-calendar me-1"></i><?= htmlspecialchars(format_date($p['dt'])) ?></p>
              <a href="<?= htmlspecialchars(blog_url($p['slug'])) ?>" class="btn btn-outline-primary btn-sm">Read More <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-muted mt-4">No published articles yet.</p>
  <?php endif; ?>
</main>
<?php require_once ROOT . '/includes/footer.php'; ?>
