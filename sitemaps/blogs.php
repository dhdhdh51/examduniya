<?php
/**
 * Published blog posts sitemap.
 * Includes featured images for Google Image Search.
 * Recent posts (< 7 days) get higher priority.
 */
require __DIR__ . '/_bootstrap.php';

try {
    $stmt = $pdo->query(
        "SELECT slug, title, featured_image, category, created_at,
                COALESCE(updated_at, published_at, created_at) AS lastmod
           FROM blogs
          WHERE is_published = 1 AND slug IS NOT NULL AND slug <> ''
          ORDER BY lastmod DESC"
    );
    $now = time();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Recent posts get higher priority (fresher = more crawl priority)
        $age_days = ($now - strtotime($row['lastmod'])) / 86400;
        if ($age_days <= 7) {
            $priority = '0.8';
            $changefreq = 'daily';
        } elseif ($age_days <= 30) {
            $priority = '0.7';
            $changefreq = 'weekly';
        } else {
            $priority = '0.6';
            $changefreq = 'monthly';
        }

        // Image sitemap entry
        $images = [];
        if (!empty($row['featured_image'])) {
            $img_url = (str_starts_with($row['featured_image'], 'http'))
                ? $row['featured_image']
                : $base . '/uploads/blogs/' . $row['featured_image'];
            $images[] = ['loc' => $img_url, 'title' => $row['title']];
        }

        sitemap_url(
            $base . '/blog/' . rawurlencode($row['slug']) . '/',
            $row['lastmod'] ?: $row['created_at'],
            $changefreq,
            $priority,
            $images
        );
    }
} catch (Throwable $ex) {
    // skip
}

echo '</urlset>';
