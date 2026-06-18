<?php
// Mock-test landing page(s). Individual attempt/purchase pages are
// intentionally excluded (noindex / private). The public listing is the
// indexable entry point.
require __DIR__ . '/_bootstrap.php';

sitemap_url($base . '/mock-tests/', null, 'weekly', '0.8');

echo '</urlset>';
