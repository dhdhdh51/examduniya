<?php
/**
 * Exam Duniya — JSON-LD schema helper (Part 7 of brief)
 * ------------------------------------------------------------------
 * Produces valid schema.org JSON-LD. Only emit schema that matches the
 * VISIBLE content of the page. Never emit fake Review/Rating/JobPosting/
 * Event/FAQ markup.
 *
 * Depends on includes/seo.php (seo_base_url / seo_abs_url).
 */

if (!defined('ROOT')) {
    die('Direct access not allowed');
}

if (!function_exists('seo_base_url')) {
    require_once ROOT . '/includes/seo.php';
}

/**
 * Encode + echo a JSON-LD <script> block.
 *
 * @param array $data Associative array (will get @context added).
 */
function schema_emit(array $data)
{
    if (empty($data)) {
        return;
    }
    if (!isset($data['@context'])) {
        $data = array_merge(['@context' => 'https://schema.org'], $data);
    }
    echo '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . "</script>\n";
}

/** Publisher Organization sub-object (reused by Article schema). */
function schema_publisher()
{
    $name = function_exists('get_setting')
        ? (get_setting('organization_name') ?: (get_setting('site_name') ?: 'Exam Duniya'))
        : 'Exam Duniya';
    $logo = function_exists('get_setting') ? get_setting('site_logo') : '';
    $org = [
        '@type' => 'Organization',
        'name'  => $name,
        'url'   => seo_base_url() . '/',
    ];
    if ($logo) {
        $org['logo'] = [
            '@type' => 'ImageObject',
            'url'   => seo_abs_url('/uploads/site/' . $logo),
        ];
    }
    return $org;
}

/** Homepage: Organization. */
function schema_organization()
{
    $org = schema_publisher();
    $org['description'] = function_exists('get_setting') ? (get_setting('site_description') ?: '') : '';
    $tw = function_exists('get_setting') ? get_setting('twitter_handle') : '';
    if ($tw) {
        $org['sameAs'] = ['https://twitter.com/' . ltrim($tw, '@')];
    }
    schema_emit($org);
}

/** Homepage: WebSite + SearchAction (internal search exists at /api/search.php). */
function schema_website($searchExists = true)
{
    $name = function_exists('get_setting') ? (get_setting('site_name') ?: 'Exam Duniya') : 'Exam Duniya';
    $data = [
        '@type' => 'WebSite',
        'name'  => $name,
        'url'   => seo_base_url() . '/',
    ];
    if ($searchExists) {
        $data['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => seo_base_url() . '/pages/exams/listing.php?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ];
    }
    schema_emit($data);
}

/**
 * BreadcrumbList from an ordered array of ['name'=>, 'url'=>] items.
 */
function schema_breadcrumbs(array $items)
{
    if (empty($items)) {
        return;
    }
    $list = [];
    $pos = 1;
    foreach ($items as $it) {
        $entry = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => $it['name'] ?? '',
        ];
        if (!empty($it['url'])) {
            $entry['item'] = seo_abs_url($it['url']);
        }
        $list[] = $entry;
    }
    schema_emit(['@type' => 'BreadcrumbList', 'itemListElement' => $list]);
}

/**
 * Article / BlogPosting schema.
 *
 * @param array $a Keys: type(Article|BlogPosting), headline, url, image,
 *                 datePublished, dateModified, authorName, authorUrl, description
 */
function schema_article(array $a)
{
    $data = [
        '@type'            => $a['type'] ?? 'Article',
        'headline'         => mb_substr((string)($a['headline'] ?? ''), 0, 110),
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id'   => seo_abs_url($a['url'] ?? '/'),
        ],
        'publisher'        => schema_publisher(),
    ];
    if (!empty($a['description'])) {
        $data['description'] = $a['description'];
    }
    if (!empty($a['image'])) {
        $data['image'] = seo_abs_url($a['image']);
    }
    if (!empty($a['datePublished'])) {
        $data['datePublished'] = $a['datePublished'];
    }
    if (!empty($a['dateModified'])) {
        $data['dateModified'] = $a['dateModified'];
    }
    if (!empty($a['authorName'])) {
        $author = ['@type' => 'Person', 'name' => $a['authorName']];
        if (!empty($a['authorUrl'])) {
            $author['url'] = seo_abs_url($a['authorUrl']);
        }
        $data['author'] = $author;
    }
    schema_emit($data);
}

/** CollectionPage schema for category/listing pages. */
function schema_collection_page($name, $url, $description = '')
{
    $data = [
        '@type' => 'CollectionPage',
        'name'  => $name,
        'url'   => seo_abs_url($url),
    ];
    if ($description) {
        $data['description'] = $description;
    }
    schema_emit($data);
}

/**
 * FAQPage schema — ONLY call this when the same Q&As are visible on the page.
 *
 * @param array $faqs Array of ['q'=>question, 'a'=>answer(plain/HTML)]
 */
function schema_faq(array $faqs)
{
    $faqs = array_values(array_filter($faqs, fn($f) => !empty($f['q']) && !empty($f['a'])));
    if (empty($faqs)) {
        return;
    }
    $entities = [];
    foreach ($faqs as $f) {
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => trim(strip_tags((string)$f['a'])),
            ],
        ];
    }
    schema_emit(['@type' => 'FAQPage', 'mainEntity' => $entities]);
}
