<?php
// Category landing pages -> /category/{name}/
// Only categories that actually have at least one notification are listed
// (avoids thin/empty indexable pages).
require __DIR__ . '/_bootstrap.php';

try {
    $stmt = $pdo->query(
        "SELECT category, COUNT(*) AS c, MAX(COALESCE(updated_at, created_at)) AS lastmod
           FROM notifications
          WHERE category IS NOT NULL AND category <> ''
          GROUP BY category
         HAVING c > 0
          ORDER BY category ASC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        sitemap_url(
            $base . '/category/' . rawurlencode($row['category']) . '/',
            $row['lastmod'],
            'daily',
            '0.7'
        );
    }
} catch (Throwable $ex) {
    // skip
}

echo '</urlset>';
