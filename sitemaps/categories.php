<?php
/**
 * Category landing pages sitemap.
 * Only categories with at least one notification are listed.
 * Categories with active exams get higher priority.
 */
require __DIR__ . '/_bootstrap.php';

try {
    $stmt = $pdo->query(
        "SELECT category,
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('active','upcoming','admitcard') THEN 1 ELSE 0 END) AS active_count,
                MAX(COALESCE(updated_at, created_at)) AS lastmod
           FROM notifications
          WHERE category IS NOT NULL AND category <> ''
          GROUP BY category
         HAVING total > 0
          ORDER BY active_count DESC, category ASC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Categories with active exams get higher priority
        $priority = ($row['active_count'] > 0) ? '0.8' : '0.6';
        $changefreq = ($row['active_count'] > 0) ? 'daily' : 'weekly';

        sitemap_url(
            $base . '/category/' . rawurlencode($row['category']) . '/',
            $row['lastmod'],
            $changefreq,
            $priority
        );
    }
} catch (Throwable $ex) {
    // skip
}

// Blog categories
try {
    $stmt = $pdo->query(
        "SELECT category,
                COUNT(*) AS total,
                MAX(COALESCE(updated_at, published_at, created_at)) AS lastmod
           FROM blogs
          WHERE is_published = 1 AND category IS NOT NULL AND category <> ''
          GROUP BY category
         HAVING total > 0
          ORDER BY category ASC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        sitemap_url(
            $base . '/blog/?category=' . rawurlencode($row['category']),
            $row['lastmod'],
            'weekly',
            '0.5'
        );
    }
} catch (Throwable $ex) {
    // skip
}

echo '</urlset>';
