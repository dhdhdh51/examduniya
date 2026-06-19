<?php
/**
 * Shared bootstrap for sitemap generators.
 * Sets ROOT (parent dir), loads db + functions, emits the XML header,
 * and exposes $base (canonical origin), helpers for URL & image entries.
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

$base = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/**
 * Emit one <url> entry with optional image support.
 *
 * @param string      $loc        Full URL
 * @param string|null $lastmod    Date/datetime string or null
 * @param string      $changefreq daily|weekly|monthly|yearly
 * @param string      $priority   0.0 to 1.0
 * @param array       $images     Array of ['loc'=>url, 'title'=>string] for image sitemap
 */
function sitemap_url($loc, $lastmod = null, $changefreq = 'weekly', $priority = '0.6', $images = [])
{
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    echo "  <url>\n";
    echo '    <loc>' . $e($loc) . "</loc>\n";
    if ($lastmod) {
        echo '    <lastmod>' . $e(date('Y-m-d', strtotime($lastmod))) . "</lastmod>\n";
    }
    echo '    <changefreq>' . $e($changefreq) . "</changefreq>\n";
    echo '    <priority>' . $e($priority) . "</priority>\n";

    // Image sitemap entries (Google Image Search optimization)
    foreach ($images as $img) {
        if (empty($img['loc'])) continue;
        echo "    <image:image>\n";
        echo '      <image:loc>' . $e($img['loc']) . "</image:loc>\n";
        if (!empty($img['title'])) {
            echo '      <image:title>' . $e($img['title']) . "</image:title>\n";
        }
        if (!empty($img['caption'])) {
            echo '      <image:caption>' . $e($img['caption']) . "</image:caption>\n";
        }
        echo "    </image:image>\n";
    }

    echo "  </url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL;
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . PHP_EOL;
