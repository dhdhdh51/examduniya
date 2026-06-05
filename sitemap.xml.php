<?php
define('ROOT', __DIR__);
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$site_url = rtrim(get_setting('site_url') ?: 'https://example.com', '/');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($site_url) ?>/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($site_url) ?>/pages/exams/listing.php</loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($site_url) ?>/pages/tests/listing.php</loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($site_url) ?>/pages/blog/listing.php</loc>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
<?php
try {
    $stmt = $pdo->query("SELECT slug, created_at FROM notifications ORDER BY created_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
?>
  <url>
    <loc><?= htmlspecialchars($site_url . '/notification/' . $row['slug']) ?></loc>
    <lastmod><?= htmlspecialchars(date('Y-m-d', strtotime($row['created_at']))) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
<?php
    endwhile;
} catch (Exception $e) {
    // silently skip if table doesn't exist yet
}
?>
<?php
try {
    $stmt = $pdo->query("SELECT slug, created_at FROM blogs WHERE is_published = 1 ORDER BY created_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
?>
  <url>
    <loc><?= htmlspecialchars($site_url . '/blog/' . $row['slug']) ?></loc>
    <lastmod><?= htmlspecialchars(date('Y-m-d', strtotime($row['created_at']))) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    endwhile;
} catch (Exception $e) {
    // silently skip
}
?>
</urlset>
