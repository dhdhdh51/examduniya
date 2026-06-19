<?php
/**
 * Automatic Sitemap Ping & Cache Invalidation
 * 
 * Called automatically when content is created/updated/deleted in admin panel.
 * Pings Google & Bing to notify about sitemap changes.
 * Optionally generates a static sitemap.xml cache file.
 *
 * Usage: sitemap_notify($pdo)
 */

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

/**
 * Notify search engines that the sitemap has been updated.
 * Call this after any content CRUD operation (add/edit/delete).
 *
 * @param PDO $pdo Database connection
 * @return void
 */
function sitemap_notify($pdo)
{
    require_once ROOT . '/includes/functions.php';

    $site_url = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');
    $sitemap_url = $site_url . '/sitemap.xml';

    // Ping search engines
    ping_search_engines($sitemap_url);

    // Store last update time (useful for admin dashboard)
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['sitemap_last_updated', date('Y-m-d H:i:s')]);
    } catch (Exception $e) { /* non-critical */ }
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
            CURLOPT_USERAGENT      => 'ExamDuniya-Sitemap-Notifier/1.0',
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
