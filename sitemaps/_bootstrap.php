<?php
/**
 * Shared bootstrap for sitemap generators.
 * Sets ROOT (parent dir), loads db + functions, emits the XML header,
 * and exposes $base (canonical origin) and the $e() escaper + url helpers.
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/**
 * Emit one <url> entry.
 */
function sitemap_url($loc, $lastmod = null, $changefreq = 'weekly', $priority = '0.6')
{
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    echo "  <url>\n";
    echo '    <loc>' . $e($loc) . "</loc>\n";
    if ($lastmod) {
        echo '    <lastmod>' . $e(date('Y-m-d', strtotime($lastmod))) . "</lastmod>\n";
    }
    echo '    <changefreq>' . $e($changefreq) . "</changefreq>\n";
    echo '    <priority>' . $e($priority) . "</priority>\n";
    echo "  </url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
