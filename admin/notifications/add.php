<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Add Notification';
$active_menu = 'notifications';
$errors = [];
$warnings = [];
$is_edit = false;
$slug_current = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { die('CSRF token mismatch.'); }
    require ROOT . '/admin/notifications/_capture.php'; // fills $f, $errors, $warnings

    if (empty($errors)) {
        $slug_base = slug($f['title']); $slug = $slug_base;
        $chk = $pdo->prepare("SELECT id FROM notifications WHERE slug = ? LIMIT 1");
        $chk->execute([$slug]);
        if ($chk->fetchColumn()) { $slug = $slug_base . '-' . time(); }
        $slug_current = $slug;

        // uploads
        if (!empty($_FILES['pdf']['tmp_name'])) {
            $u = upload_file($_FILES['pdf'], 'notifications', ['pdf']);
            if ($u === false) $errors[] = 'PDF upload failed (PDF only).'; else $f['pdf_path'] = $u;
        }
        if (empty($errors) && !empty($_FILES['featured_image']['tmp_name'])) {
            $u = upload_file($_FILES['featured_image'], 'notifications', ['jpg','jpeg','png','webp']);
            if ($u === false) $errors[] = 'Image upload failed (JPG/PNG/WebP only).'; else $f['featured_image'] = $u;
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO notifications
                 (title, slug, short_desc, full_content, category, conducting_body, organization_name, post_type,
                  notification_date, publish_date, application_start_date, last_date_apply, application_last_date,
                  exam_date, admit_card_date, result_date, last_verified_at,
                  vacancies, age_limit, qualification, eligibility_summary, fee_general, fee_obc, fee_sc_st,
                  official_url, official_website_url, official_apply_url, official_notification_pdf_url,
                  pdf_path, featured_image, status, is_featured, is_homepage_visible,
                  seo_title, meta_description, created_by, updated_by)
                 VALUES (?,?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?)"
            );
            $stmt->execute([
                $f['title'],$slug,$f['short_desc'],$f['full_content'],$f['category'],$f['conducting_body'],$f['organization_name'],$f['post_type'],
                $f['notification_date'],$f['notification_date'],$f['application_start_date'],$f['application_last_date'],$f['application_last_date'],
                $f['exam_date'],$f['admit_card_date'],$f['result_date'],$f['last_verified_at'],
                $f['vacancies'],$f['age_limit'],$f['qualification'],$f['eligibility_summary'],$f['fee_general'],$f['fee_obc'],$f['fee_sc_st'],
                $f['official_website_url'],$f['official_website_url'],$f['official_apply_url'],$f['official_notification_pdf_url'],
                $f['pdf_path'],$f['featured_image'],$f['status'],$f['is_featured'],$f['is_homepage_visible'],
                $f['seo_title'],$f['meta_description'],(int)($_SESSION['user_id']??0)?:null,(int)($_SESSION['user_id']??0)?:null
            ]);
        } catch (PDOException $e) {
            // Pre-migration fallback: legacy columns only.
            $stmt = $pdo->prepare(
                "INSERT INTO notifications
                 (title, slug, short_desc, full_content, category, conducting_body,
                  notification_date, last_date_apply, exam_date, vacancies, age_limit,
                  qualification, fee_general, fee_obc, fee_sc_st, official_url, pdf_path, status, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $f['title'],$slug,$f['short_desc'],$f['full_content'],$f['category'],$f['conducting_body'],
                $f['notification_date'],$f['application_last_date'],$f['exam_date'],$f['vacancies'],$f['age_limit'],
                $f['qualification'],$f['fee_general'],$f['fee_obc'],$f['fee_sc_st'],$f['official_website_url'],$f['pdf_path'],$f['status'],(int)($_SESSION['user_id']??0)?:null
            ]);
        }
        $new_id = (int)$pdo->lastInsertId();
        if (function_exists('log_activity')) log_activity('notification_create','notifications',$new_id,$f['title']);

        if (!empty($f['send_telegram']) && get_setting('telegram_enabled') === '1') {
            $site_url = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: '', '/');
            $tg = "<b>New Exam Notification!</b>\n<b>Exam:</b> ".htmlspecialchars($f['title'])."\n"
                . "<b>Board:</b> ".htmlspecialchars($f['organization_name'] ?: $f['conducting_body'])."\n"
                . "<b>Last Date:</b> ".($f['application_last_date'] ?: 'N/A')."\n"
                . "<a href='{$site_url}/exams/{$slug}/'>View Details</a>";
            send_telegram($tg);
            $pdo->prepare("UPDATE notifications SET telegram_sent = 1 WHERE id = ?")->execute([$new_id]);
        }
        header('Location: /admin/notifications/list.php?success=1'); exit;
    }

    $n = $f; // repopulate
    $n['application_last_date'] = $f['application_last_date'];
} else {
    $n = [];
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
$submit_label = 'Save Notification';
include ROOT . '/admin/notifications/_form.php';
require_once ROOT . '/includes/admin_footer.php';
