<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Edit Notification';
$active_menu = 'notifications';
$errors = [];
$warnings = [];
$is_edit = true;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/notifications/list.php'); exit; }

$notif = $pdo->prepare("SELECT * FROM notifications WHERE id = ? LIMIT 1");
$notif->execute([$id]);
$notif = $notif->fetch(PDO::FETCH_ASSOC);
if (!$notif) { header('Location: /admin/notifications/list.php'); exit; }
$slug_current = $notif['slug'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { die('CSRF token mismatch.'); }
    $existing_pdf      = $notif['pdf_path'] ?? null;
    $existing_image    = $notif['featured_image'] ?? null;
    $existing_verified = $notif['last_verified_at'] ?? null;
    require ROOT . '/admin/notifications/_capture.php';

    if (empty($errors) && !empty($_FILES['pdf']['tmp_name'])) {
        $u = upload_file($_FILES['pdf'], 'notifications', ['pdf']);
        if ($u === false) $errors[] = 'PDF upload failed (PDF only).'; else $f['pdf_path'] = $u;
    }
    if (empty($errors) && !empty($_FILES['featured_image']['tmp_name'])) {
        $u = upload_file($_FILES['featured_image'], 'notifications', ['jpg','jpeg','png','webp']);
        if ($u === false) $errors[] = 'Image upload failed (JPG/PNG/WebP only).'; else $f['featured_image'] = $u;
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE notifications SET
                 title=?, category=?, conducting_body=?, organization_name=?, post_type=?, short_desc=?, full_content=?,
                 notification_date=?, application_start_date=?, last_date_apply=?, application_last_date=?,
                 exam_date=?, admit_card_date=?, result_date=?, last_verified_at=?,
                 vacancies=?, age_limit=?, qualification=?, eligibility_summary=?, fee_general=?, fee_obc=?, fee_sc_st=?,
                 official_url=?, official_website_url=?, official_apply_url=?, official_notification_pdf_url=?,
                 pdf_path=?, featured_image=?, status=?, is_featured=?, is_homepage_visible=?,
                 seo_title=?, meta_description=?, updated_by=?
                 WHERE id=?"
            );
            $stmt->execute([
                $f['title'],$f['category'],$f['conducting_body'],$f['organization_name'],$f['post_type'],$f['short_desc'],$f['full_content'],
                $f['notification_date'],$f['application_start_date'],$f['application_last_date'],$f['application_last_date'],
                $f['exam_date'],$f['admit_card_date'],$f['result_date'],$f['last_verified_at'],
                $f['vacancies'],$f['age_limit'],$f['qualification'],$f['eligibility_summary'],$f['fee_general'],$f['fee_obc'],$f['fee_sc_st'],
                $f['official_website_url'],$f['official_website_url'],$f['official_apply_url'],$f['official_notification_pdf_url'],
                $f['pdf_path'],$f['featured_image'],$f['status'],$f['is_featured'],$f['is_homepage_visible'],
                $f['seo_title'],$f['meta_description'],(int)($_SESSION['user_id']??0)?:null,$id
            ]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare(
                "UPDATE notifications SET title=?, category=?, conducting_body=?, short_desc=?, full_content=?,
                 notification_date=?, last_date_apply=?, exam_date=?, vacancies=?, age_limit=?,
                 qualification=?, fee_general=?, fee_obc=?, fee_sc_st=?, official_url=?, pdf_path=?, status=? WHERE id=?"
            );
            $stmt->execute([
                $f['title'],$f['category'],$f['conducting_body'],$f['short_desc'],$f['full_content'],
                $f['notification_date'],$f['application_last_date'],$f['exam_date'],$f['vacancies'],$f['age_limit'],
                $f['qualification'],$f['fee_general'],$f['fee_obc'],$f['fee_sc_st'],$f['official_website_url'],$f['pdf_path'],$f['status'],$id
            ]);
        }
        if (function_exists('log_activity')) log_activity('notification_update','notifications',$id,$f['title']);

        if (!empty($f['send_telegram']) && get_setting('telegram_enabled') === '1') {
            $site_url = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: '', '/');
            $tg = "<b>Updated Notification!</b>\n<b>Exam:</b> ".htmlspecialchars($f['title'])."\n"
                . "<a href='{$site_url}/exams/{$notif['slug']}/'>View Details</a>";
            send_telegram($tg);
            $pdo->prepare("UPDATE notifications SET telegram_sent = 1 WHERE id = ?")->execute([$id]);
        }
        header('Location: /admin/notifications/list.php?success=1'); exit;
    }

    // Re-display with posted values merged over existing row.
    $notif = array_merge($notif, $f);
}

$n = $notif;
require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
$submit_label = 'Update Notification';
include ROOT . '/admin/notifications/_form.php';
require_once ROOT . '/includes/admin_footer.php';
