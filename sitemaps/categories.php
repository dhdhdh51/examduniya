<?php
/**
 * Category landing pages sitemap.
 * Only categories with at least one notification are listed.
 * Categories with active exams get higher priority.
 * Queries fall back to base columns if SEO columns are not present yet.
 */
require __DIR__ . '/_bootstrap.php';

// Exam categories
$stmt = sm_query([
    "SELECT category,
            SUM(CASE WHEN status IN ('active','upcoming','admitcard') THEN 1 ELSE 0 END) AS active_count,
            MAX(COALESCE(updated_at, created_at)) AS lastmod
       FROM notifications
      WHERE category IS NOT NULL AND category <> ''
      GROUP BY category
      ORDER BY category ASC",
    "SELECT category, 0 AS active_count, MAX(created_at) AS lastmod
       FROM notifications
      WHERE category IS NOT NULL AND category <> ''
      GROUP BY category
      ORDER BY category ASC",
]);
if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $active = (int)($row['active_count'] ?? 0);
        $priority = $active > 0 ? '0.8' : '0.6';
        $changefreq = $active > 0 ? 'daily' : 'weekly';
        sitemap_url(
            $base . '/category/' . rawurlencode($row['category']) . '/',
            $row['lastmod'] ?? null,
            $changefreq,
            $priority
        );
    }
}

// Blog categories
$stmt = sm_query([
    "SELECT category, MAX(COALESCE(updated_at, published_at, created_at)) AS lastmod
       FROM blogs
      WHERE is_published = 1 AND category IS NOT NULL AND category <> ''
      GROUP BY category
      ORDER BY category ASC",
    "SELECT category, MAX(created_at) AS lastmod
       FROM blogs
      WHERE is_published = 1 AND category IS NOT NULL AND category <> ''
      GROUP BY category
      ORDER BY category ASC",
]);
if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        sitemap_url(
            $base . '/blog/?category=' . rawurlencode($row['category']),
            $row['lastmod'] ?? null,
            'weekly',
            '0.5'
        );
    }
}

echo '</urlset>';
