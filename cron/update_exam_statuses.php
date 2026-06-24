<?php
/**
 * Exam Duniya — daily content-freshness cron (Part 2 of brief)
 * ------------------------------------------------------------------
 * Recomputes notifications.computed_status from the date fields using
 * the Asia/Kolkata timezone, so listings can hide closed posts and move
 * items into Admit Card / Result / Result-Awaited buckets automatically.
 *
 * It NEVER deletes posts (expired pages keep their SEO value) and only
 * writes a row when its status actually changes (so updated_at — and thus
 * sitemap lastmod — is not churned needlessly).
 *
 * SCHEDULE (cPanel cron, run once daily, e.g. 00:15 IST):
 *   /usr/bin/php /home/USER/public_html/cron/update_exam_statuses.php
 *
 * Or via HTTP with a secret (the /cron/ dir is blocked in .htaccess, so
 * prefer CLI). To allow a manual web run, set CRON_SECRET below and call:
 *   https://examduniya.in/cron/update_exam_statuses.php?key=YOUR_SECRET
 */

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';

// ---- Access control -------------------------------------------------
$is_cli = (PHP_SAPI === 'cli');
$secret = getenv('CRON_SECRET') ?: ''; // optionally set in server env
if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    if ($secret === '' || ($_GET['key'] ?? '') !== $secret) {
        http_response_code(403);
        echo "Forbidden\n";
        exit;
    }
}

date_default_timezone_set('Asia/Kolkata');
$tz    = new DateTimeZone('Asia/Kolkata');
$today = (new DateTime('now', $tz))->setTime(0, 0, 0);

$toDate = function ($v) use ($tz) {
    if (empty($v) || $v === '0000-00-00') return null;
    try { return (new DateTime($v, $tz))->setTime(0, 0, 0); }
    catch (Throwable $e) { return null; }
};

/**
 * Derive lifecycle status purely from dates (mirrors
 * notification_lifecycle_status but ignores any stored computed_status,
 * since this cron is what populates it).
 */
$derive = function (array $n) use ($today, $toDate) {
    $lastApply = $toDate($n['application_last_date'] ?? $n['last_date_apply'] ?? null);
    $examDate  = $toDate($n['exam_date'] ?? null);
    $admitDate = $toDate($n['admit_card_date'] ?? null);
    $resultDt  = $toDate($n['result_date'] ?? null);
    $startDate = $toDate($n['application_start_date'] ?? null);

    if ($resultDt && $resultDt <= $today)            return 'result';
    if ($examDate && $examDate < $today)             return $resultDt ? 'awaited' : 'exam_completed';
    if ($admitDate && $admitDate <= $today && (!$examDate || $examDate >= $today)) return 'admit_card';
    if ($startDate && $startDate > $today)           return 'upcoming';
    if ($lastApply && $lastApply < $today)           return 'closed';
    return 'open';
};

$updated = 0;
$scanned = 0;

try {
    $rows = $pdo->query(
        "SELECT id, status, computed_status, last_date_apply, application_last_date,
                application_start_date, exam_date, admit_card_date, result_date
           FROM notifications"
    )->fetchAll(PDO::FETCH_ASSOC);

    $upd = $pdo->prepare("UPDATE notifications SET computed_status = ? WHERE id = ?");

    foreach ($rows as $n) {
        $scanned++;
        $new = $derive($n);
        if (($n['computed_status'] ?? null) !== $new) {
            $upd->execute([$new, $n['id']]);
            $updated++;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Cron error: ' . $e->getMessage() . "\n");
    if (!$is_cli) { echo 'Error: ' . $e->getMessage() . "\n"; }
    exit(1);
}

$msg = sprintf("[%s IST] Exam statuses recomputed: %d updated of %d scanned.\n",
    $today->format('Y-m-d H:i'), $updated, $scanned);
echo $msg;
if (function_exists('log_activity')) {
    log_activity('cron_status_update', 'notifications', null, trim($msg));
}
exit(0);
