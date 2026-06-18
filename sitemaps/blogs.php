<?php
// Published blog posts -> /blog/{slug}/
require __DIR__ . '/_bootstrap.php';

try {
    $stmt = $pdo->query(
        "SELECT slug, created_at,
                COALESCE(updated_at, published_at, created_at) AS lastmod
           FROM blogs
          WHERE is_published = 1 AND slug IS NOT NULL AND slug <> ''
          ORDER BY lastmod DESC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        sitemap_url(
            $base . '/blog/' . rawurlencode($row['slug']) . '/',
            $row['lastmod'] ?: $row['created_at'],
            'monthly',
            '0.6'
        );
    }
} catch (Throwable $ex) {
    // skip
}

echo '</urlset>';
