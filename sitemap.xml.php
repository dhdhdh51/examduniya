<?php
/**
 * Sitemap endpoint — serves the pre-generated sitemap.xml file.
 * If the static file doesn't exist or is stale (>60 min), regenerates it.
 * The .htaccess rewrites /sitemap.xml to this file.
 */
define('ROOT', __DIR__);
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/sitemap-generator.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$sitemap_path = ROOT . '/sitemap.xml';

// Serve static file if fresh, otherwise regenerate
regenerate_sitemap_if_stale($pdo, 60);

if (file_exists($sitemap_path)) {
    readfile($sitemap_path);
} else {
    // Fallback: generate and output directly
    generate_sitemap($pdo);
    if (file_exists($sitemap_path)) {
        readfile($sitemap_path);
    }
}
