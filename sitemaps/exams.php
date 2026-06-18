<?php
// Exam / notification detail pages -> /exams/{slug}/
require __DIR__ . '/_bootstrap.php';

try {
    $stmt = $pdo->query(
        "SELECT slug, created_at,
                COALESCE(updated_at, created_at) AS lastmod
           FROM notifications
          WHERE slug IS NOT NULL AND slug <> ''
          ORDER BY lastmod DESC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        sitemap_url(
            $base . '/exams/' . rawurlencode($row['slug']) . '/',
            $row['lastmod'] ?: $row['created_at'],
            'weekly',
            '0.7'
        );
    }
} catch (Throwable $ex) {
    // table missing — skip
}

echo '</urlset>';
