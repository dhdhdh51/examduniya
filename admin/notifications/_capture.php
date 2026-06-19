<?php
/** Captures notification POST data into $f and runs validation
 *  (appends to $errors and $warnings). Included by add.php and edit.php. */
if (!defined('ROOT')) { die('No direct access'); }

$f = [
  'title'            => trim($_POST['title'] ?? ''),
  'category'         => trim($_POST['category'] ?? ''),
  'post_type'        => trim($_POST['post_type'] ?? 'notification') ?: 'notification',
  'organization_name'=> trim($_POST['organization_name'] ?? ''),
  'conducting_body'  => trim($_POST['conducting_body'] ?? ''),
  'short_desc'       => trim($_POST['short_desc'] ?? ''),
  'full_content'     => trim($_POST['full_content'] ?? ''),
  'notification_date'=> trim($_POST['notification_date'] ?? '') ?: null,
  'application_start_date' => trim($_POST['application_start_date'] ?? '') ?: null,
  'application_last_date'  => trim($_POST['application_last_date'] ?? '') ?: null,
  'exam_date'        => trim($_POST['exam_date'] ?? '') ?: null,
  'admit_card_date'  => trim($_POST['admit_card_date'] ?? '') ?: null,
  'result_date'      => trim($_POST['result_date'] ?? '') ?: null,
  'vacancies'        => ($_POST['vacancies'] ?? '') !== '' ? (int)$_POST['vacancies'] : null,
  'age_limit'        => trim($_POST['age_limit'] ?? ''),
  'qualification'    => trim($_POST['qualification'] ?? ''),
  'eligibility_summary' => trim($_POST['eligibility_summary'] ?? ''),
  'fee_general'      => is_numeric($_POST['fee_general'] ?? '') ? (float)$_POST['fee_general'] : null,
  'fee_obc'          => is_numeric($_POST['fee_obc'] ?? '') ? (float)$_POST['fee_obc'] : null,
  'fee_sc_st'        => is_numeric($_POST['fee_sc_st'] ?? '') ? (float)$_POST['fee_sc_st'] : null,
  'official_website_url' => trim($_POST['official_website_url'] ?? ''),
  'official_apply_url'   => trim($_POST['official_apply_url'] ?? ''),
  'official_notification_pdf_url' => trim($_POST['official_notification_pdf_url'] ?? ''),
  'status'           => trim($_POST['status'] ?? 'upcoming'),
  'is_featured'      => !empty($_POST['is_featured']) ? 1 : 0,
  'is_homepage_visible' => !empty($_POST['is_homepage_visible']) ? 1 : 0,
  'seo_title'        => trim($_POST['seo_title'] ?? '') ?: null,
  'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
  'pdf_path'         => $existing_pdf ?? null,
  'featured_image'   => $existing_image ?? null,
  'send_telegram'    => !empty($_POST['send_telegram']),
];

// Last Verified
if (!empty($_POST['verify_now'])) {
    $f['last_verified_at'] = date('Y-m-d H:i:s');
} elseif (!empty($_POST['last_verified_date'])) {
    $f['last_verified_at'] = trim($_POST['last_verified_date']) . ' 12:00:00';
} else {
    $f['last_verified_at'] = $existing_verified ?? null;
}

// Validate URLs lightly
foreach (['official_website_url','official_apply_url','official_notification_pdf_url'] as $uk) {
    if ($f[$uk] !== '' && !preg_match('#^https?://#i', $f[$uk])) {
        $errors[] = 'Links must start with http:// or https:// (' . $uk . ').';
    }
}

// Required fields
if ($f['title'] === '')    $errors[] = 'Title is required.';
if ($f['category'] === '') $errors[] = 'Category is required.';
if ($f['organization_name'] === '' && $f['conducting_body'] === '')
    $errors[] = 'Organization name (or conducting body) is required.';

// Official source required before publishing to homepage / Latest.
if ($f['is_homepage_visible']
    && $f['official_website_url'] === ''
    && $f['official_notification_pdf_url'] === ''
    && empty($_FILES['pdf']['tmp_name'])
    && empty($f['pdf_path'])) {
    $errors[] = 'An official source URL (or PDF) is required before publishing to the homepage / Latest. '
              . 'Add a source, or uncheck "Show on homepage / Latest".';
}

// Expired deadline cannot be a Latest/homepage post.
if ($f['is_homepage_visible'] && $f['application_last_date']) {
    $tz = new DateTimeZone('Asia/Kolkata');
    $today = (new DateTime('now', $tz))->setTime(0,0,0);
    try {
        $last = (new DateTime($f['application_last_date'], $tz))->setTime(0,0,0);
        if ($last < $today) {
            $errors[] = 'The application deadline is in the past, so this cannot be shown as a "Latest" notification. '
                      . 'Uncheck "Show on homepage / Latest" to save it as an archived/expired post.';
        }
    } catch (Throwable $e) {}
}

// Non-blocking: likely category/title mismatch.
$tu = strtoupper($f['title']);
$cu = strtoupper($f['category']);
foreach (['UPSSSC','UPPSC','UPSC','SSC','RRB','IBPS'] as $tok) {
    if (strpos($tu, $tok) !== false && strpos($cu, $tok) === false) {
        $warnings[] = "Title mentions \"{$tok}\" but the category is \"{$f['category']}\". Please confirm there is no category mismatch.";
        break;
    }
}
// Non-blocking: trust nudge.
if (empty($f['last_verified_at'])) {
    $warnings[] = 'No "Last Verified" date set — adding one improves trust on the public page.';
}
