<?php
/**
 * Mock tests sitemap.
 * Individual test pages live behind attempt/purchase (noindex/private),
 * so only the public listing and category-filtered listings are indexable.
 */
require __DIR__ . '/_bootstrap.php';

// Main mock tests listing page
sitemap_url($base . '/mock-tests/', date('Y-m-d'), 'weekly', '0.8');

// Category-filtered listing pages (indexable, useful for SEO)
try {
    $stmt = $pdo->query(
        "SELECT DISTINCT category
           FROM mock_tests
          WHERE is_active = 1 AND category IS NOT NULL AND category <> ''
          ORDER BY category ASC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        sitemap_url(
            $base . '/mock-tests/?category=' . rawurlencode($row['category']),
            null,
            'weekly',
            '0.6'
        );
    }
} catch (Throwable $ex) {
    // table missing — skip
}

echo '</urlset>';
