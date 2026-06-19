<?php
/**
 * Sitemap INDEX — lists all sub-sitemaps with accurate lastmod.
 * Reachable at /sitemap.xml (see .htaccess).
 */
define('ROOT', __DIR__);
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

$base = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Get per-section lastmod dates for accurate sitemap index
$lastmods = [];
try {
    $row = $pdo->query("SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM notifications")->fetch();
    $lastmods['exams'] = !empty($row['m']) ? date('Y-m-d', strtotime($row['m'])) : date('Y-m-d');
} catch (Throwable $ex) { $lastmods['exams'] = date('Y-m-d'); }

try {
    $row = $pdo->query("SELECT MAX(COALESCE(updated_at, published_at, created_at)) AS m FROM blogs WHERE is_published = 1")->fetch();
    $lastmods['blogs'] = !empty($row['m']) ? date('Y-m-d', strtotime($row['m'])) : date('Y-m-d');
} catch (Throwable $ex) { $lastmods['blogs'] = date('Y-m-d'); }

try {
    $row = $pdo->query("SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM mock_tests WHERE is_active = 1")->fetch();
    $lastmods['tests'] = !empty($row['m']) ? date('Y-m-d', strtotime($row['m'])) : date('Y-m-d');
} catch (Throwable $ex) { $lastmods['tests'] = date('Y-m-d'); }

$lastmods['pages'] = date('Y-m-d');
$lastmods['categories'] = $lastmods['exams']; // categories update when exams update

$maps = [
    'sitemap-pages.xml'       => $lastmods['pages'],
    'sitemap-exams.xml'       => $lastmods['exams'],
    'sitemap-blogs.xml'       => $lastmods['blogs'],
    'sitemap-categories.xml'  => $lastmods['categories'],
    'sitemap-mock-tests.xml'  => $lastmods['tests'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($maps as $m => $mod): ?>
  <sitemap>
    <loc><?= $e($base . '/' . $m) ?></loc>
    <lastmod><?= $e($mod) ?></lastmod>
  </sitemap>
<?php endforeach; ?>
</sitemapindex>
