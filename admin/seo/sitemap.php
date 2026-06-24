<?php
/**
 * Admin — Sitemap Manager
 * View sitemap status, URL counts, and manually regenerate / ping search engines.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';
require_once ROOT . '/includes/sitemap-generator.php';

$admin_page_title = 'Sitemap Manager';
$flash = '';
$flash_type = 'success';

// Handle "Ping search engines" / "Regenerate" action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'ping') {
        sitemap_notify($pdo);
        $flash = 'Sitemap submitted to Google &amp; Bing successfully. Search engines will recrawl shortly.';
    }
}

$base = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');

/** Helper to count rows safely */
function sm_count($sql)
{
    global $pdo;
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

// URL counts per sub-sitemap
$counts = [
    'Pages (static)'    => 13, // fixed list in sitemaps/pages.php
    'Exams / Posts'     => sm_count("SELECT COUNT(*) FROM notifications WHERE slug IS NOT NULL AND slug <> ''"),
    'Blog Posts'        => sm_count("SELECT COUNT(*) FROM blogs WHERE is_published = 1 AND slug IS NOT NULL AND slug <> ''"),
    'Categories'        => sm_count("SELECT COUNT(DISTINCT category) FROM notifications WHERE category IS NOT NULL AND category <> ''"),
    'Mock Tests'        => sm_count("SELECT COUNT(*) FROM mock_tests WHERE is_active = 1 AND slug IS NOT NULL AND slug <> ''"),
];

$total_urls = 0;
foreach ($counts as $c) { $total_urls += (int)$c; }

$last_updated = get_setting('sitemap_last_updated');

$sub_sitemaps = [
    'sitemap-pages.xml'      => 'Static Pages',
    'sitemap-exams.xml'      => 'Exam Notifications',
    'sitemap-blogs.xml'      => 'Blog Posts',
    'sitemap-categories.xml' => 'Categories',
    'sitemap-mock-tests.xml' => 'Mock Tests',
];

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-sitemap me-2"></i>Sitemap Manager</h2>
    <div class="d-flex gap-2">
      <a href="/sitemap.xml" target="_blank" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-eye me-1"></i>View Sitemap
      </a>
      <a href="/robots.txt" target="_blank" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-robot me-1"></i>robots.txt
      </a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show">
      <i class="fas fa-circle-check me-2"></i><?= $flash ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="alert alert-info border-0">
    <i class="fas fa-circle-info me-2"></i>
    Your sitemap is generated <strong>automatically &amp; live</strong> from your database.
    Every time you add, edit, or delete a notification, blog post, or mock test,
    search engines are notified instantly. Use the button below to ping them manually anytime.
  </div>

  <!-- Summary cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="text-primary mb-2"><i class="fas fa-link fa-2x"></i></div>
        <div class="fw-bold fs-4"><?= (int)$total_urls ?></div>
        <small class="text-muted">Total URLs</small>
      </div></div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="text-success mb-2"><i class="fas fa-layer-group fa-2x"></i></div>
        <div class="fw-bold fs-4"><?= count($sub_sitemaps) ?></div>
        <small class="text-muted">Sub-Sitemaps</small>
      </div></div>
    </div>
    <div class="col-md-6 col-12">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Last submitted to search engines</div>
            <div class="fw-semibold">
              <?= $last_updated ? htmlspecialchars($last_updated) : '<span class="text-muted">Never — ping now</span>' ?>
            </div>
          </div>
          <form method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="ping">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-paper-plane me-1"></i>Generate &amp; Ping Now
            </button>
          </form>
        </div>
      </div></div>
    </div>
  </div>

  <!-- Sub-sitemap breakdown -->
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white fw-semibold">
          <i class="fas fa-list-ol me-2"></i>URLs per Section
        </div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <tbody>
              <?php foreach ($counts as $label => $val): ?>
                <tr>
                  <td><?= htmlspecialchars($label) ?></td>
                  <td class="text-end">
                    <?php if ($val === null): ?>
                      <span class="badge bg-light text-dark">N/A</span>
                    <?php else: ?>
                      <span class="badge bg-primary"><?= (int)$val ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white fw-semibold">
          <i class="fas fa-sitemap me-2"></i>Sitemap Files
        </div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <tbody>
              <tr>
                <td>
                  <i class="fas fa-star text-warning me-1"></i>
                  <strong>sitemap.xml</strong> <small class="text-muted">(index)</small>
                </td>
                <td class="text-end">
                  <a href="/sitemap.xml" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                </td>
              </tr>
              <?php foreach ($sub_sitemaps as $file => $label): ?>
                <tr>
                  <td><i class="fas fa-file-code text-muted me-1"></i><?= htmlspecialchars($file) ?>
                    <small class="text-muted d-block ms-4"><?= htmlspecialchars($label) ?></small>
                  </td>
                  <td class="text-end">
                    <a href="/<?= htmlspecialchars($file) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                      <i class="fas fa-external-link-alt"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Search engine submission help -->
  <div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white fw-semibold">
      <i class="fas fa-magnifying-glass-location me-2"></i>Submit to Search Consoles
    </div>
    <div class="card-body">
      <p class="text-muted small mb-3">
        For best results, also submit your sitemap URL once in the official webmaster tools:
      </p>
      <div class="d-flex flex-wrap gap-2">
        <a href="https://search.google.com/search-console" target="_blank" class="btn btn-outline-danger btn-sm">
          <i class="fab fa-google me-1"></i>Google Search Console
        </a>
        <a href="https://www.bing.com/webmasters" target="_blank" class="btn btn-outline-primary btn-sm">
          <i class="fab fa-microsoft me-1"></i>Bing Webmaster Tools
        </a>
      </div>
      <div class="mt-3">
        <label class="form-label small text-muted mb-1">Your sitemap URL (copy this):</label>
        <div class="input-group input-group-sm" style="max-width:500px;">
          <input type="text" class="form-control" id="sitemapUrl" value="<?= htmlspecialchars($base . '/sitemap.xml') ?>" readonly>
          <button class="btn btn-outline-secondary" type="button" onclick="copySitemapUrl()">
            <i class="fas fa-copy"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
function copySitemapUrl() {
  var input = document.getElementById('sitemapUrl');
  input.select();
  input.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(input.value);
}
</script>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
