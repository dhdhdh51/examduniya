<?php
// Static, indexable pages only.
require __DIR__ . '/_bootstrap.php';

sitemap_url($base . '/', null, 'daily', '1.0');
sitemap_url($base . '/exams/', null, 'daily', '0.9');
sitemap_url($base . '/blog/', null, 'daily', '0.8');
sitemap_url($base . '/mock-tests/', null, 'weekly', '0.8');
sitemap_url($base . '/about-exam-duniya/', null, 'monthly', '0.5');
sitemap_url($base . '/editorial-policy/', null, 'yearly', '0.3');
sitemap_url($base . '/fact-check-policy/', null, 'yearly', '0.3');
sitemap_url($base . '/correction-policy/', null, 'yearly', '0.3');
sitemap_url($base . '/contact/', null, 'yearly', '0.3');
sitemap_url($base . '/pages/privacy.php', null, 'yearly', '0.2');
sitemap_url($base . '/pages/terms.php', null, 'yearly', '0.2');

echo '</urlset>';
