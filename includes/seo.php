<?php
/**
 * Exam Duniya — SEO helper
 * ------------------------------------------------------------------
 * Central engine for <title>, meta description, canonical, robots,
 * Open Graph and Twitter Card tags.
 *
 * BACKWARD COMPATIBLE: pages that only set $page_title / $meta_desc keep
 * working. New pages can call seo_set([...]) before including the header
 * to control every tag precisely.
 *
 * Usage (new style):
 *   seo_set([
 *     'title'       => 'SSC CGL 2026: Apply Online ... | Exam Duniya',
 *     'description' => '...',
 *     'canonical'   => 'https://examduniya.in/exams/ssc-cgl-2026/',
 *     'robots'      => 'index,follow',
 *     'og_image'    => 'https://examduniya.in/uploads/...jpg',
 *     'og_type'     => 'article',
 *     'published_time' => '2026-06-01T10:00:00+05:30',
 *     'modified_time'  => '2026-06-18T09:00:00+05:30',
 *   ]);
 *   require_once ROOT . '/includes/header.php';
 */

if (!defined('ROOT')) {
    die('Direct access not allowed');
}

/**
 * Internal store for the current page's SEO data.
 */
function &seo_store()
{
    static $seo = [];
    return $seo;
}

/**
 * The canonical base origin, e.g. "https://examduniya.in" (no trailing slash).
 */
function seo_base_url()
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $base = rtrim(
        (function_exists('get_setting')
            ? (get_setting('canonical_domain') ?: get_setting('site_url'))
            : '') ?: 'https://examduniya.in',
        '/'
    );
    return $base;
}

/**
 * Build an absolute URL on the canonical domain from a path or absolute URL.
 */
function seo_abs_url($path)
{
    if ($path === '' || $path === null) {
        return seo_base_url() . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return seo_base_url() . '/' . ltrim($path, '/');
}

/**
 * The clean, query-stripped path of the current request (for default canonical).
 * Sensitive query strings are intentionally dropped so we never build
 * canonical URLs from unsafe raw parameters.
 */
function seo_current_path()
{
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    return $path;
}

/**
 * Set SEO values for the current page. Merge-friendly; call once per page.
 *
 * @param array $opts
 */
function seo_set(array $opts)
{
    $seo = &seo_store();
    $seo = array_merge($seo, $opts);
}

/**
 * Resolve the final SEO values (filling sensible defaults). Reads legacy
 * $page_title / $meta_desc globals so old pages keep working unchanged.
 *
 * @return array
 */
function seo_resolve()
{
    $seo = seo_store();

    $site_name = function_exists('get_setting') ? (get_setting('site_name') ?: 'Exam Duniya') : 'Exam Duniya';
    $site_desc = function_exists('get_setting')
        ? (get_setting('site_description') ?: 'Government Exam Notifications, Free Mock Tests & Study Material')
        : 'Government Exam Notifications, Free Mock Tests & Study Material';

    // Legacy fallbacks from page-level globals.
    $legacy_title = $GLOBALS['page_title'] ?? null;
    $legacy_desc  = $GLOBALS['meta_desc'] ?? null;

    // Title: explicit > legacy "{page} — {site}" > site name.
    if (!empty($seo['title'])) {
        $title = $seo['title'];
    } elseif ($legacy_title) {
        $title = $legacy_title . ' — ' . $site_name;
    } else {
        $title = $site_name;
    }

    $description = $seo['description'] ?? $legacy_desc ?? $site_desc;

    // Canonical: explicit > current clean path on canonical domain.
    $canonical = !empty($seo['canonical'])
        ? seo_abs_url($seo['canonical'])
        : seo_base_url() . seo_current_path();

    $robots = $seo['robots'] ?? 'index,follow';

    $og_image = $seo['og_image']
        ?? (function_exists('get_setting') ? get_setting('default_og_image') : '')
        ?: '';
    if ($og_image) {
        $og_image = seo_abs_url($og_image);
    }

    return [
        'site_name'      => $site_name,
        'title'          => $title,
        'description'    => mb_substr(trim((string)$description), 0, 320),
        'canonical'      => $canonical,
        'robots'         => $robots,
        'og_type'        => $seo['og_type'] ?? 'website',
        'og_image'       => $og_image,
        'published_time' => $seo['published_time'] ?? null,
        'modified_time'  => $seo['modified_time'] ?? null,
        'twitter_handle' => function_exists('get_setting') ? (get_setting('twitter_handle') ?: '') : '',
    ];
}

/**
 * Echo all <head> SEO tags. Called from header.php.
 */
function seo_render_head()
{
    $s = seo_resolve();
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<title>' . $e($s['title']) . "</title>\n";
    echo '<meta name="description" content="' . $e($s['description']) . "\">\n";
    echo '<meta name="robots" content="' . $e($s['robots']) . "\">\n";
    echo '<link rel="canonical" href="' . $e($s['canonical']) . "\">\n";

    // Open Graph
    echo '<meta property="og:site_name" content="' . $e($s['site_name']) . "\">\n";
    echo '<meta property="og:title" content="' . $e($s['title']) . "\">\n";
    echo '<meta property="og:description" content="' . $e($s['description']) . "\">\n";
    echo '<meta property="og:type" content="' . $e($s['og_type']) . "\">\n";
    echo '<meta property="og:url" content="' . $e($s['canonical']) . "\">\n";
    if ($s['og_image']) {
        echo '<meta property="og:image" content="' . $e($s['og_image']) . "\">\n";
    }
    if ($s['og_type'] === 'article') {
        if ($s['published_time']) {
            echo '<meta property="article:published_time" content="' . $e($s['published_time']) . "\">\n";
        }
        if ($s['modified_time']) {
            echo '<meta property="article:modified_time" content="' . $e($s['modified_time']) . "\">\n";
        }
    }

    // Twitter Card
    echo '<meta name="twitter:card" content="' . ($s['og_image'] ? 'summary_large_image' : 'summary') . "\">\n";
    echo '<meta name="twitter:title" content="' . $e($s['title']) . "\">\n";
    echo '<meta name="twitter:description" content="' . $e($s['description']) . "\">\n";
    if ($s['og_image']) {
        echo '<meta name="twitter:image" content="' . $e($s['og_image']) . "\">\n";
    }
    if ($s['twitter_handle']) {
        echo '<meta name="twitter:site" content="' . $e($s['twitter_handle']) . "\">\n";
    }
}

/* =====================================================================
 * Title-pattern helpers (Part 6 of brief)
 * =================================================================== */

/** Exam detail title pattern. */
function seo_exam_title($examTitle, $year = null)
{
    $year = $year ?: date('Y');
    return trim($examTitle) . ' ' . $year
        . ': Apply Online, Eligibility, Dates, Vacancy, Admit Card | Exam Duniya';
}

/** Blog title pattern. */
function seo_blog_title($blogTitle)
{
    return trim($blogTitle) . ' | Exam Duniya';
}

/** Category title pattern. */
function seo_category_title($categoryName)
{
    return 'Latest ' . trim($categoryName)
        . ' Jobs, Admit Cards, Results & Updates | Exam Duniya';
}

/**
 * Clamp a meta description to a clean ~155 char sentence boundary.
 */
function seo_clamp_description($text, $max = 158)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $lastSpace = mb_strrpos($cut, ' ');
    if ($lastSpace !== false && $lastSpace > $max * 0.6) {
        $cut = mb_substr($cut, 0, $lastSpace);
    }
    return rtrim($cut, " ,.;:-") . '…';
}
