<?php
/**
 * Main sitemap — a COMPLETE sitemap listing every indexable URL directly
 * (static pages, exam notifications, blog posts, categories, mock tests).
 *
 * Reachable at /sitemap.xml (see .htaccess).
 *
 * NOTE: The per-section sub-sitemaps (sitemap-exams.xml, etc.) are still
 * available individually, but this file contains everything in one place.
 */
define('ROOT', __DIR__);
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

$base = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/** Emit one <url> entry with optional images. */
function sm_url($loc, $lastmod = null, $changefreq = 'weekly', $priority = '0.6', $images = [])
{
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    echo "  <url>\n";
    echo '    <loc>' . $e($loc) . "</loc>\n";
    if ($lastmod) {
        echo '    <lastmod>' . $e(date('Y-m-d', strtotime($lastmod))) . "</lastmod>\n";
    }
    echo '    <changefreq>' . $e($changefreq) . "</changefreq>\n";
    echo '    <priority>' . $e($priority) . "</priority>\n";
    foreach ($images as $img) {
        if (empty($img['loc'])) continue;
        echo "    <image:image>\n";
        echo '      <image:loc>' . $e($img['loc']) . "</image:loc>\n";
        if (!empty($img['title'])) echo '      <image:title>' . $e($img['title']) . "</image:title>\n";
        echo "    </image:image>\n";
    }
    echo "  </url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL;
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . PHP_EOL;

// ─── Static / trust pages ────────────────────────────────────────────
sm_url($base . '/', date('Y-m-d'), 'daily', '1.0');
sm_url($base . '/exams/', date('Y-m-d'), 'daily', '0.9');
sm_url($base . '/blog/', date('Y-m-d'), 'daily', '0.8');
sm_url($base . '/mock-tests/', date('Y-m-d'), 'weekly', '0.8');
sm_url($base . '/about-exam-duniya/', null, 'monthly', '0.6');
sm_url($base . '/editorial-policy/', null, 'yearly', '0.5');
sm_url($base . '/fact-check-policy/', null, 'yearly', '0.5');
sm_url($base . '/correction-policy/', null, 'yearly', '0.5');
sm_url($base . '/contact/', null, 'monthly', '0.4');
sm_url($base . '/pages/privacy.php', null, 'yearly', '0.3');
sm_url($base . '/pages/terms.php', null, 'yearly', '0.3');
sm_url($base . '/pages/refund.php', null, 'yearly', '0.3');
sm_url($base . '/pages/shipping.php', null, 'yearly', '0.3');

// ─── Exam notifications ──────────────────────────────────────────────
try {
    $stmt = $pdo->query(
        "SELECT slug, title, status, featured_image, created_at,
                COALESCE(updated_at, created_at) AS lastmod
           FROM notifications
          WHERE slug IS NOT NULL AND slug <> ''
          ORDER BY lastmod DESC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $priority = '0.7'; $changefreq = 'monthly';
        if (in_array($row['status'], ['active', 'upcoming'])) { $priority = '0.8'; $changefreq = 'weekly'; }
        elseif ($row['status'] === 'admitcard') { $priority = '0.8'; $changefreq = 'daily'; }

        $images = [];
        if (!empty($row['featured_image'])) {
            $img = str_starts_with($row['featured_image'], 'http')
                ? $row['featured_image']
                : $base . '/uploads/notifications/' . $row['featured_image'];
            $images[] = ['loc' => $img, 'title' => $row['title']];
        }
        sm_url($base . '/exams/' . rawurlencode($row['slug']) . '/', $row['lastmod'] ?: $row['created_at'], $changefreq, $priority, $images);
    }
} catch (Throwable $ex) { /* skip */ }

// ─── Exam category pages ─────────────────────────────────────────────
try {
    $stmt = $pdo->query(
        "SELECT category,
                SUM(CASE WHEN status IN ('active','upcoming','admitcard') THEN 1 ELSE 0 END) AS active_count,
                MAX(COALESCE(updated_at, created_at)) AS lastmod
           FROM notifications
          WHERE category IS NOT NULL AND category <> ''
          GROUP BY category
          ORDER BY active_count DESC, category ASC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $priority = ($row['active_count'] > 0) ? '0.8' : '0.6';
        $changefreq = ($row['active_count'] > 0) ? 'daily' : 'weekly';
        sm_url($base . '/category/' . rawurlencode($row['category']) . '/', $row['lastmod'], $changefreq, $priority);
    }
} catch (Throwable $ex) { /* skip */ }

// ─── Blog posts ──────────────────────────────────────────────────────
try {
    $stmt = $pdo->query(
        "SELECT slug, title, featured_image, created_at,
                COALESCE(updated_at, published_at, created_at) AS lastmod
           FROM blogs
          WHERE is_published = 1 AND slug IS NOT NULL AND slug <> ''
          ORDER BY lastmod DESC"
    );
    $now = time();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $age_days = ($now - strtotime($row['lastmod'])) / 86400;
        if ($age_days <= 7)       { $priority = '0.8'; $changefreq = 'daily'; }
        elseif ($age_days <= 30)  { $priority = '0.7'; $changefreq = 'weekly'; }
        else                      { $priority = '0.6'; $changefreq = 'monthly'; }

        $images = [];
        if (!empty($row['featured_image'])) {
            $img = str_starts_with($row['featured_image'], 'http')
                ? $row['featured_image']
                : $base . '/uploads/blogs/' . $row['featured_image'];
            $images[] = ['loc' => $img, 'title' => $row['title']];
        }
        sm_url($base . '/blog/' . rawurlencode($row['slug']) . '/', $row['lastmod'] ?: $row['created_at'], $changefreq, $priority, $images);
    }
} catch (Throwable $ex) { /* skip */ }

// ─── Mock tests (only indexable listing + category filters) ──────────
// Individual test pages are behind attempt/purchase (noindex/private),
// so we only list the public listing and category-filtered listings.
try {
    $stmt = $pdo->query(
        "SELECT DISTINCT category
           FROM mock_tests
          WHERE is_active = 1 AND category IS NOT NULL AND category <> ''
          ORDER BY category ASC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        sm_url($base . '/mock-tests/?category=' . rawurlencode($row['category']), null, 'weekly', '0.6');
    }
} catch (Throwable $ex) { /* skip */ }

echo '</urlset>';
