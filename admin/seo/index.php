<?php
/**
 * Admin — SEO & Content-Quality Dashboard (Parts 2, 11 of brief)
 * Read-only health checks. Safe even before the SEO migration runs
 * (each metric is guarded; missing columns show "N/A").
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'SEO & Content Quality';

/** Run a COUNT query; return int, or null if it errors (e.g. column missing). */
function q_count($sql, $params = [])
{
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

/** Run a SELECT; return rows or [] on error. */
function q_rows($sql, $params = [])
{
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

$total_notifs = q_count("SELECT COUNT(*) FROM notifications");

$metrics = [
    'Expired (deadline passed)' => q_count(
        "SELECT COUNT(*) FROM notifications
          WHERE COALESCE(application_last_date, last_date_apply) IS NOT NULL
            AND COALESCE(application_last_date, last_date_apply) < CURDATE()"
    ),
    'Unverified (no Last Verified)' => q_count(
        "SELECT COUNT(*) FROM notifications WHERE last_verified_at IS NULL"
    ),
    'Missing official source link' => q_count(
        "SELECT COUNT(*) FROM notifications
          WHERE COALESCE(NULLIF(official_website_url,''), NULLIF(official_url,'')) IS NULL"
    ),
    'Not updated in 30+ days' => q_count(
        "SELECT COUNT(*) FROM notifications
          WHERE COALESCE(updated_at, created_at) < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    ),
    'Missing SEO title' => q_count(
        "SELECT COUNT(*) FROM notifications WHERE seo_title IS NULL OR seo_title = ''"
    ),
    'Missing meta description' => q_count(
        "SELECT COUNT(*) FROM notifications
          WHERE (meta_description IS NULL OR meta_description = '')
            AND (short_desc IS NULL OR short_desc = '')"
    ),
    'Missing featured image' => q_count(
        "SELECT COUNT(*) FROM notifications WHERE featured_image IS NULL OR featured_image = ''"
    ),
    'Missing category' => q_count(
        "SELECT COUNT(*) FROM notifications WHERE category IS NULL OR category = ''"
    ),
];

$blog_metrics = [
    'Blogs missing meta description' => q_count(
        "SELECT COUNT(*) FROM blogs
          WHERE is_published = 1 AND (meta_description IS NULL OR meta_description = '')
            AND (excerpt IS NULL OR excerpt = '')"
    ),
    'Blogs missing featured image' => q_count(
        "SELECT COUNT(*) FROM blogs WHERE is_published = 1 AND (featured_image IS NULL OR featured_image = '')"
    ),
    'Blogs missing category' => q_count(
        "SELECT COUNT(*) FROM blogs WHERE is_published = 1 AND (category IS NULL OR category = '')"
    ),
];

// Duplicate SEO titles / descriptions
$dup_titles = q_rows(
    "SELECT seo_title AS v, COUNT(*) c FROM notifications
      WHERE seo_title IS NOT NULL AND seo_title <> ''
      GROUP BY seo_title HAVING c > 1 ORDER BY c DESC LIMIT 20"
);
$dup_desc = q_rows(
    "SELECT meta_description AS v, COUNT(*) c FROM notifications
      WHERE meta_description IS NOT NULL AND meta_description <> ''
      GROUP BY meta_description HAVING c > 1 ORDER BY c DESC LIMIT 20"
);

// Attention list — notifications needing the most work.
$attention = q_rows(
    "SELECT id, title, category, last_verified_at,
            COALESCE(NULLIF(official_website_url,''), NULLIF(official_url,'')) AS src,
            seo_title, COALESCE(updated_at, created_at) AS lastmod
       FROM notifications
      WHERE last_verified_at IS NULL
         OR COALESCE(NULLIF(official_website_url,''), NULLIF(official_url,'')) IS NULL
         OR seo_title IS NULL OR seo_title = ''
         OR category IS NULL OR category = ''
      ORDER BY lastmod ASC
      LIMIT 25"
);

$open_corrections = q_count("SELECT COUNT(*) FROM corrections WHERE status = 'new'");

// File/system status
$robots_ok  = file_exists(ROOT . '/robots.txt');
$sitemap_ok = file_exists(ROOT . '/sitemap.xml.php');
$migration_done = ($metrics['Missing SEO title'] !== null);

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';

$badge = function ($v) {
    if ($v === null) return '<span class="badge bg-light text-dark">N/A</span>';
    $cls = $v > 0 ? 'bg-danger' : 'bg-success';
    return '<span class="badge ' . $cls . '">' . (int)$v . '</span>';
};
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-magnifying-glass-chart me-2"></i>SEO &amp; Content Quality</h2>
    <div class="d-flex gap-2">
      <a href="/admin/seo/sitemap.php" class="btn btn-sm btn-primary"><i class="fas fa-sitemap me-1"></i>Sitemap Manager</a>
      <a href="/sitemap.xml" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-sitemap me-1"></i>View Sitemap</a>
      <a href="/robots.txt" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-robot me-1"></i>robots.txt</a>
    </div>
  </div>

  <?php if (!$migration_done): ?>
    <div class="alert alert-warning">
      <i class="fas fa-triangle-exclamation me-2"></i>
      Some SEO columns are not present yet. Import
      <code>database/schema.sql</code> to enable all checks.
    </div>
  <?php endif; ?>

  <!-- System status -->
  <div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="text-<?= $robots_ok ? 'success' : 'danger' ?> mb-2"><i class="fas fa-robot fa-2x"></i></div>
        <div class="fw-bold">robots.txt</div><small class="text-muted"><?= $robots_ok ? 'Present' : 'Missing' ?></small>
      </div></div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="text-<?= $sitemap_ok ? 'success' : 'danger' ?> mb-2"><i class="fas fa-sitemap fa-2x"></i></div>
        <div class="fw-bold">XML Sitemap</div><small class="text-muted"><?= $sitemap_ok ? 'Active' : 'Missing' ?></small>
      </div></div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="text-primary mb-2"><i class="fas fa-bell fa-2x"></i></div>
        <div class="fw-bold"><?= (int)$total_notifs ?></div><small class="text-muted">Total Notifications</small>
      </div></div>
    </div>
    <div class="col-md-3 col-6">
      <a href="/admin/seo/corrections.php" class="text-decoration-none">
      <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="text-warning mb-2"><i class="fas fa-flag fa-2x"></i></div>
        <div class="fw-bold"><?= $open_corrections === null ? 'N/A' : (int)$open_corrections ?></div>
        <small class="text-muted">Open Corrections</small>
      </div></div></a>
    </div>
  </div>

  <div class="row g-3">
    <!-- Notification content quality -->
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-list-check me-2"></i>Notification Content Quality</div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <tbody>
              <?php foreach ($metrics as $label => $val): ?>
                <tr>
                  <td><?= htmlspecialchars($label) ?></td>
                  <td class="text-end"><?= $badge($val) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Blog quality + duplicates -->
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-newspaper me-2"></i>Blog Content Quality</div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <tbody>
              <?php foreach ($blog_metrics as $label => $val): ?>
                <tr><td><?= htmlspecialchars($label) ?></td><td class="text-end"><?= $badge($val) ?></td></tr>
              <?php endforeach; ?>
              <tr><td>Duplicate SEO titles</td><td class="text-end"><?= $badge(count($dup_titles)) ?></td></tr>
              <tr><td>Duplicate meta descriptions</td><td class="text-end"><?= $badge(count($dup_desc)) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Attention list -->
  <div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white fw-semibold"><i class="fas fa-triangle-exclamation me-2 text-warning"></i>Needs Attention</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>Title</th><th>Category</th><th>Verified</th><th>Official Link</th><th>SEO Title</th><th></th></tr>
          </thead>
          <tbody>
          <?php if (empty($attention)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">Nothing needs attention. 🎉</td></tr>
          <?php else: foreach ($attention as $a): ?>
            <tr>
              <td><?= htmlspecialchars(mb_substr($a['title'], 0, 55)) ?></td>
              <td><?= $a['category'] ? '<span class="badge bg-secondary">' . htmlspecialchars($a['category']) . '</span>' : '<span class="badge bg-danger">missing</span>' ?></td>
              <td><?= $a['last_verified_at'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-xmark text-danger"></i>' ?></td>
              <td><?= !empty($a['src']) ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-xmark text-danger"></i>' ?></td>
              <td><?= !empty($a['seo_title']) ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-xmark text-danger"></i>' ?></td>
              <td><a href="/admin/notifications/edit.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
</div>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
