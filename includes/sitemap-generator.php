<?php
/**
 * Automatic Sitemap Generator
 * 
 * Generates a static sitemap.xml file at the project root.
 * Called automatically when content is created/updated/deleted in admin panel.
 * Also pings Google & Bing to reindex.
 *
 * Usage: generate_sitemap($pdo)
 */

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

/**
 * Generate sitemap.xml and write it to the project root.
 * Includes: static pages, notifications, blog posts, mock tests, category pages.
 *
 * @param PDO $pdo Database connection
 * @return bool True on success
 */
function generate_sitemap($pdo)
{
    require_once ROOT . '/includes/functions.php';

    $site_url = rtrim(get_setting('site_url') ?: 'https://example.com', '/');
    $urls = [];

    // ─── Static Pages ───────────────────────────────────────────────────
    $static_pages = [
        ['loc' => '/',                   'changefreq' => 'daily',   'priority' => '1.0'],
        ['loc' => '/pages/exams/',       'changefreq' => 'daily',   'priority' => '0.9'],
        ['loc' => '/pages/tests/',       'changefreq' => 'weekly',  'priority' => '0.8'],
        ['loc' => '/pages/blog/',        'changefreq' => 'daily',   'priority' => '0.8'],
        ['loc' => '/pages/about.php',    'changefreq' => 'monthly', 'priority' => '0.5'],
        ['loc' => '/pages/contact.php',  'changefreq' => 'monthly', 'priority' => '0.5'],
        ['loc' => '/pages/privacy.php',  'changefreq' => 'yearly',  'priority' => '0.3'],
        ['loc' => '/pages/terms.php',    'changefreq' => 'yearly',  'priority' => '0.3'],
        ['loc' => '/pages/refund.php',   'changefreq' => 'yearly',  'priority' => '0.3'],
        ['loc' => '/pages/shipping.php', 'changefreq' => 'yearly',  'priority' => '0.3'],
    ];

    foreach ($static_pages as $page) {
        $urls[] = [
            'loc'        => $site_url . $page['loc'],
            'changefreq' => $page['changefreq'],
            'priority'   => $page['priority'],
        ];
    }

    // ─── Category Pages ─────────────────────────────────────────────────
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM notifications WHERE category IS NOT NULL AND category != '' ORDER BY category");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc'        => $site_url . '/pages/exams/?cat=' . urlencode($row['category']),
                'changefreq' => 'weekly',
                'priority'   => '0.7',
            ];
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ─── Notifications (Exam Posts) ─────────────────────────────────────
    try {
        $stmt = $pdo->query("SELECT slug, created_at, status FROM notifications ORDER BY created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc'        => $site_url . '/notification/' . $row['slug'],
                'lastmod'    => date('Y-m-d', strtotime($row['created_at'])),
                'changefreq' => ($row['status'] === 'active') ? 'weekly' : 'monthly',
                'priority'   => '0.7',
            ];
        }
    } catch (Exception $e) { /* silently skip */ }

    // ─── Blog Posts ─────────────────────────────────────────────────────
    try {
        $stmt = $pdo->query("SELECT slug, created_at, updated_at FROM blogs WHERE is_published = 1 ORDER BY created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lastmod = !empty($row['updated_at']) ? $row['updated_at'] : $row['created_at'];
            $urls[] = [
                'loc'        => $site_url . '/blog/' . $row['slug'],
                'lastmod'    => date('Y-m-d', strtotime($lastmod)),
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ];
        }
    } catch (Exception $e) { /* silently skip */ }

    // ─── Mock Tests ─────────────────────────────────────────────────────
    try {
        $stmt = $pdo->query("SELECT slug, created_at FROM mock_tests WHERE is_active = 1 ORDER BY created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc'        => $site_url . '/pages/tests/detail.php?slug=' . urlencode($row['slug']),
                'lastmod'    => date('Y-m-d', strtotime($row['created_at'])),
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ];
        }
    } catch (Exception $e) { /* silently skip */ }

    // ─── Blog Category Pages ────────────────────────────────────────────
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM blogs WHERE is_published = 1 AND category IS NOT NULL AND category != '' ORDER BY category");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc'        => $site_url . '/pages/blog/?category=' . urlencode($row['category']),
                'changefreq' => 'weekly',
                'priority'   => '0.5',
            ];
        }
    } catch (Exception $e) { /* silently skip */ }

    // ─── Build XML ──────────────────────────────────────────────────────
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

    foreach ($urls as $url) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;
        if (!empty($url['lastmod'])) {
            $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod']) . '</lastmod>' . PHP_EOL;
        }
        $xml .= '    <changefreq>' . htmlspecialchars($url['changefreq']) . '</changefreq>' . PHP_EOL;
        $xml .= '    <priority>' . htmlspecialchars($url['priority']) . '</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }

    $xml .= '</urlset>' . PHP_EOL;

    // ─── Write sitemap.xml ──────────────────────────────────────────────
    $sitemap_path = ROOT . '/sitemap.xml';
    $result = @file_put_contents($sitemap_path, $xml);

    if ($result === false) {
        error_log('Sitemap Generator: Failed to write sitemap.xml');
        return false;
    }

    // ─── Ping Search Engines ────────────────────────────────────────────
    ping_search_engines($site_url . '/sitemap.xml');

    // Store last generation time
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['sitemap_last_generated', date('Y-m-d H:i:s')]);
    } catch (Exception $e) { /* non-critical */ }

    return true;
}

/**
 * Ping Google and Bing to notify them about sitemap update.
 *
 * @param string $sitemap_url Full URL to sitemap.xml
 * @return void
 */
function ping_search_engines($sitemap_url)
{
    $ping_urls = [
        'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url),
        'https://www.bing.com/indexnow?url=' . urlencode($sitemap_url),
    ];

    foreach ($ping_urls as $ping_url) {
        $ch = curl_init($ping_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'GovExam-Sitemap-Generator/1.0',
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * Regenerate sitemap if it's older than the specified minutes.
 * Useful for cron jobs or lazy generation on frontend requests.
 *
 * @param PDO $pdo Database connection
 * @param int $max_age_minutes Re-generate if older than this (default: 60 minutes)
 * @return bool Whether regeneration was performed
 */
function regenerate_sitemap_if_stale($pdo, $max_age_minutes = 60)
{
    $sitemap_path = ROOT . '/sitemap.xml';

    if (!file_exists($sitemap_path)) {
        return generate_sitemap($pdo);
    }

    $file_age_minutes = (time() - filemtime($sitemap_path)) / 60;

    if ($file_age_minutes > $max_age_minutes) {
        return generate_sitemap($pdo);
    }

    return false;
}
