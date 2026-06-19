<?php
/**
 * Exam/notification detail pages sitemap.
 * Includes featured images for Google Image Search.
 * Active notifications get higher priority & weekly changefreq.
 * Queries fall back to base columns if SEO columns are not present yet.
 */
require __DIR__ . '/_bootstrap.php';

$stmt = sm_query([
    "SELECT slug, title, status, featured_image, created_at,
            COALESCE(updated_at, created_at) AS lastmod
       FROM notifications
      WHERE slug IS NOT NULL AND slug <> ''
      ORDER BY created_at DESC",
    "SELECT slug, title, status, created_at, created_at AS lastmod
       FROM notifications
      WHERE slug IS NOT NULL AND slug <> ''
      ORDER BY created_at DESC",
    "SELECT slug, created_at, created_at AS lastmod
       FROM notifications
      WHERE slug IS NOT NULL AND slug <> ''
      ORDER BY created_at DESC",
]);

if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['status'] ?? '';
        $priority = '0.7'; $changefreq = 'monthly';
        if (in_array($status, ['active', 'upcoming'])) { $priority = '0.8'; $changefreq = 'weekly'; }
        elseif ($status === 'admitcard') { $priority = '0.8'; $changefreq = 'daily'; }

        $images = [];
        if (!empty($row['featured_image'])) {
            $img_url = str_starts_with($row['featured_image'], 'http')
                ? $row['featured_image']
                : $base . '/uploads/notifications/' . $row['featured_image'];
            $images[] = ['loc' => $img_url, 'title' => $row['title'] ?? ''];
        }

        sitemap_url(
            $base . '/exams/' . rawurlencode($row['slug']) . '/',
            $row['lastmod'] ?: $row['created_at'],
            $changefreq,
            $priority,
            $images
        );
    }
}

echo '</urlset>';
