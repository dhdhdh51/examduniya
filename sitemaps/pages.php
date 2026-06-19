<?php
/**
 * Static pages sitemap — all indexable non-dynamic pages.
 * Includes lastmod from file modification time where possible.
 */
require __DIR__ . '/_bootstrap.php';

// Homepage — highest priority, changes daily
sitemap_url($base . '/', date('Y-m-d'), 'daily', '1.0');

// Main listing pages
sitemap_url($base . '/exams/', date('Y-m-d'), 'daily', '0.9');
sitemap_url($base . '/blog/', date('Y-m-d'), 'daily', '0.8');
sitemap_url($base . '/mock-tests/', date('Y-m-d'), 'weekly', '0.8');

// Trust & authority pages (E-E-A-T signals for Google)
$trust_pages = [
    '/about-exam-duniya/'   => ['changefreq' => 'monthly', 'priority' => '0.6'],
    '/editorial-policy/'    => ['changefreq' => 'yearly',  'priority' => '0.5'],
    '/fact-check-policy/'   => ['changefreq' => 'yearly',  'priority' => '0.5'],
    '/correction-policy/'   => ['changefreq' => 'yearly',  'priority' => '0.5'],
    '/contact/'             => ['changefreq' => 'monthly', 'priority' => '0.4'],
    '/pages/privacy.php'    => ['changefreq' => 'yearly',  'priority' => '0.3'],
    '/pages/terms.php'      => ['changefreq' => 'yearly',  'priority' => '0.3'],
    '/pages/refund.php'     => ['changefreq' => 'yearly',  'priority' => '0.3'],
    '/pages/shipping.php'   => ['changefreq' => 'yearly',  'priority' => '0.3'],
];

foreach ($trust_pages as $path => $meta) {
    // Try to get file lastmod
    $file_path = ROOT . $path;
    if (str_ends_with($path, '/')) {
        $file_path = ROOT . rtrim($path, '/') . '.php';
    }
    $lastmod = file_exists($file_path) ? date('Y-m-d', filemtime($file_path)) : null;
    sitemap_url($base . $path, $lastmod, $meta['changefreq'], $meta['priority']);
}

// Auth pages (login/signup — useful for user acquisition via search)
sitemap_url($base . '/auth/login.php', null, 'monthly', '0.3');
sitemap_url($base . '/auth/signup.php', null, 'monthly', '0.3');

echo '</urlset>';
