<?php
/**
 * Mock tests sitemap — includes individual test detail pages.
 * Only active (public) tests are listed.
 * Free tests get slightly higher priority (more accessible to users).
 */
require __DIR__ . '/_bootstrap.php';

// Main mock tests listing page
sitemap_url($base . '/mock-tests/', date('Y-m-d'), 'weekly', '0.8');

// Individual test detail pages
try {
    $stmt = $pdo->query(
        "SELECT slug, title, category, access_type, created_at,
                COALESCE(updated_at, created_at) AS lastmod
           FROM mock_tests
          WHERE is_active = 1 AND slug IS NOT NULL AND slug <> ''
          ORDER BY created_at DESC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Free tests = higher crawl priority (more users can access)
        $priority = ($row['access_type'] === 'free') ? '0.7' : '0.5';

        sitemap_url(
            $base . '/test/' . rawurlencode($row['slug']) . '/',
            $row['lastmod'] ?: $row['created_at'],
            'monthly',
            $priority
        );
    }
} catch (Throwable $ex) {
    // table missing — skip
}

// Test category pages
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
    // skip
}

echo '</urlset>';
