<?php
/**
 * Sitemap INDEX — lists all sub-sitemaps.
 * Reachable at /sitemap.xml (see .htaccess).
 */
define('ROOT', __DIR__);
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Most-recent content change, used as a reasonable lastmod for sub-sitemaps.
$lastmod = date('Y-m-d');
try {
    $row = $pdo->query("SELECT GREATEST(
        COALESCE((SELECT MAX(created_at) FROM notifications), '2000-01-01'),
        COALESCE((SELECT MAX(created_at) FROM blogs), '2000-01-01')
    ) AS m")->fetch();
    if (!empty($row['m'])) {
        $lastmod = date('Y-m-d', strtotime($row['m']));
    }
} catch (Throwable $ex) { /* ignore */ }

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
$maps = [
    'sitemap-pages.xml',
    'sitemap-exams.xml',
    'sitemap-blogs.xml',
    'sitemap-categories.xml',
    'sitemap-mock-tests.xml',
];
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($maps as $m): ?>
  <sitemap>
    <loc><?= $e($base . '/' . $m) ?></loc>
    <lastmod><?= $e($lastmod) ?></lastmod>
  </sitemap>
<?php endforeach; ?>
</sitemapindex>
