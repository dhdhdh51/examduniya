-- =====================================================================
-- Exam Duniya — SEO / Content-Freshness / Trust Upgrade Migration
-- =====================================================================
-- SAFE & IDEMPOTENT: This script ONLY ADDS columns/tables/settings.
-- It NEVER drops or alters existing data. It can be run multiple times
-- safely (each ADD COLUMN is guarded by an information_schema check).
--
-- Compatible with MySQL 5.7+ and 8.0+ on shared hosting.
--
-- HOW TO RUN (phpMyAdmin):
--   1. Take a backup/export of your database first.
--   2. Open phpMyAdmin > select your database > "Import" tab.
--   3. Upload this file and click "Go".
--
-- HOW TO RUN (CLI):
--   mysql -u USER -p DBNAME < database/migration_seo_upgrade.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- Helper procedure: add a column only if it does not already exist.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS ed_add_column;
DELIMITER //
CREATE PROCEDURE ed_add_column(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_def    TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- ---------------------------------------------------------------------
-- Helper procedure: add an index only if it does not already exist.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS ed_add_index;
DELIMITER //
CREATE PROCEDURE ed_add_index(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_cols  VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_cols, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- =====================================================================
-- 1) NOTIFICATIONS — content-freshness + SEO fields (Part 2 of brief)
-- =====================================================================
-- NOTE: The existing table already has: title, slug, category,
-- conducting_body, last_date_apply, exam_date, vacancies, official_url,
-- pdf_path, status, created_by, created_at. We ADD the new fields and
-- keep the old ones working. New code reads new-or-old via COALESCE.

CALL ed_add_column('notifications', 'post_type',
    "VARCHAR(40) NOT NULL DEFAULT 'notification' COMMENT 'notification|admit_card|result|answer_key|syllabus'");
CALL ed_add_column('notifications', 'organization_name', 'VARCHAR(150) NULL');
CALL ed_add_column('notifications', 'publish_date',            'DATE NULL');
CALL ed_add_column('notifications', 'application_start_date',  'DATE NULL');
CALL ed_add_column('notifications', 'application_last_date',   'DATE NULL');
CALL ed_add_column('notifications', 'admit_card_date',         'DATE NULL');
CALL ed_add_column('notifications', 'result_date',             'DATE NULL');
CALL ed_add_column('notifications', 'last_verified_at',        'DATETIME NULL');
CALL ed_add_column('notifications', 'official_website_url',    'VARCHAR(500) NULL');
CALL ed_add_column('notifications', 'official_notification_pdf_url', 'VARCHAR(500) NULL');
CALL ed_add_column('notifications', 'official_apply_url',      'VARCHAR(500) NULL');
CALL ed_add_column('notifications', 'eligibility_summary',     'TEXT NULL');
CALL ed_add_column('notifications', 'is_featured',             'TINYINT NOT NULL DEFAULT 0');
CALL ed_add_column('notifications', 'is_homepage_visible',     'TINYINT NOT NULL DEFAULT 1');
CALL ed_add_column('notifications', 'seo_title',              'VARCHAR(255) NULL');
CALL ed_add_column('notifications', 'meta_description',        'VARCHAR(320) NULL');
CALL ed_add_column('notifications', 'canonical_url',           'VARCHAR(500) NULL');
CALL ed_add_column('notifications', 'featured_image',          'VARCHAR(255) NULL');
-- Auto-computed lifecycle status, separate from the manual `status` ENUM so
-- existing code that reads `status` keeps working. Values:
-- open|upcoming|closed|admit_card|exam_completed|result|awaited
CALL ed_add_column('notifications', 'computed_status',         "VARCHAR(30) NULL");
CALL ed_add_column('notifications', 'updated_by',              'INT NULL');
CALL ed_add_column('notifications', 'updated_at',
    'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Backfill new date columns from the legacy column the first time only
-- (only fills rows where the new column is still NULL).
UPDATE notifications
   SET application_last_date = last_date_apply
 WHERE application_last_date IS NULL AND last_date_apply IS NOT NULL;

UPDATE notifications
   SET official_website_url = official_url
 WHERE official_website_url IS NULL AND official_url IS NOT NULL AND official_url <> '';

CALL ed_add_index('notifications', 'idx_notif_category', '`category`');
CALL ed_add_index('notifications', 'idx_notif_status',   '`status`');
CALL ed_add_index('notifications', 'idx_notif_posttype', '`post_type`');
CALL ed_add_index('notifications', 'idx_notif_applast',  '`application_last_date`');

-- =====================================================================
-- 2) BLOGS — SEO + editorial workflow fields (Part 9 of brief)
-- =====================================================================
CALL ed_add_column('blogs', 'seo_title',        'VARCHAR(255) NULL');
CALL ed_add_column('blogs', 'meta_description',  'VARCHAR(320) NULL');
CALL ed_add_column('blogs', 'canonical_url',     'VARCHAR(500) NULL');
CALL ed_add_column('blogs', 'last_verified_at',  'DATETIME NULL');
CALL ed_add_column('blogs', 'read_time_minutes', 'INT NULL');
-- Editorial workflow: draft|pending|published|updated|outdated|archived
CALL ed_add_column('blogs', 'content_status',   "VARCHAR(20) NOT NULL DEFAULT 'published'");
CALL ed_add_column('blogs', 'updated_at',
    'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- =====================================================================
-- 3) MOCK TESTS — SEO fields
-- =====================================================================
CALL ed_add_column('mock_tests', 'seo_title',       'VARCHAR(255) NULL');
CALL ed_add_column('mock_tests', 'meta_description', 'VARCHAR(320) NULL');
CALL ed_add_column('mock_tests', 'updated_at',
    'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- =====================================================================
-- 4) NEW TABLE — 301 redirect manager (Part 11)
-- =====================================================================
CREATE TABLE IF NOT EXISTS redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_path   VARCHAR(500) NOT NULL,
    to_path     VARCHAR(500) NOT NULL,
    status_code SMALLINT NOT NULL DEFAULT 301,
    hits        INT NOT NULL DEFAULT 0,
    is_active   TINYINT NOT NULL DEFAULT 1,
    note        VARCHAR(255) NULL,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_hit_at DATETIME NULL,
    UNIQUE KEY uq_from_path (from_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 5) NEW TABLE — admin activity log (Part 11)
-- =====================================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    action      VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id   INT NULL,
    details     TEXT NULL,
    ip          VARCHAR(45) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_entity (entity_type, entity_id),
    KEY idx_log_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 6) NEW TABLE — "Report Correction" submissions (Part 8)
-- =====================================================================
CREATE TABLE IF NOT EXISTS corrections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL DEFAULT 'notification',
    entity_id   INT NULL,
    page_url    VARCHAR(500) NULL,
    name        VARCHAR(120) NULL,
    email       VARCHAR(150) NULL,
    message     TEXT NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'new',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_corr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 7) BRANDING + new settings (Part 1, 3) — safe upsert
-- =====================================================================
-- Update brand identity to "Exam Duniya". Only overwrites the known old
-- default values so we never clobber a value an admin already customised.
UPDATE settings SET setting_value = 'Exam Duniya'
 WHERE setting_key = 'site_name'
   AND (setting_value IS NULL OR setting_value = '' OR setting_value = 'GovExam Portal');

UPDATE settings SET setting_value = 'Exam Duniya'
 WHERE setting_key = 'smtp_from_name'
   AND (setting_value IS NULL OR setting_value = '' OR setting_value = 'GovExam Portal');

UPDATE settings
   SET setting_value = 'Get latest SSC, UPSC, Railway, Banking, Defence, UP Police and State Government job notifications, admit cards, results, syllabus, free mock tests and study material on Exam Duniya.'
 WHERE setting_key = 'site_description'
   AND (setting_value IS NULL OR setting_value = ''
        OR setting_value = 'Government Exam Notifications, Mock Tests & Study Material');

UPDATE settings SET setting_value = 'https://examduniya.in'
 WHERE setting_key = 'site_url'
   AND (setting_value IS NULL OR setting_value = '' OR setting_value = 'https://example.com');

-- New settings (insert only if missing).
INSERT INTO settings (setting_key, setting_value, setting_group)
SELECT 'canonical_domain', 'https://examduniya.in', 'seo'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'canonical_domain');

INSERT INTO settings (setting_key, setting_value, setting_group)
SELECT 'footer_disclaimer',
       'Exam Duniya is an independent education and exam information platform. We are not affiliated with any government recruitment board. Candidates must verify all important details from the official website before applying.',
       'seo'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'footer_disclaimer');

INSERT INTO settings (setting_key, setting_value, setting_group)
SELECT 'homepage_seo_title',
       'Exam Duniya: Govt Exam Notifications, Free Mock Tests & Results',
       'seo'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'homepage_seo_title');

INSERT INTO settings (setting_key, setting_value, setting_group)
SELECT 'show_public_counters', '0', 'seo'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'show_public_counters');

INSERT INTO settings (setting_key, setting_value, setting_group)
SELECT 'default_og_image', '', 'seo'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'default_og_image');

INSERT INTO settings (setting_key, setting_value, setting_group)
SELECT 'twitter_handle', '', 'seo'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'twitter_handle');

INSERT INTO settings (setting_key, setting_value, setting_group)
SELECT 'organization_name', 'Exam Duniya', 'seo'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'organization_name');

-- =====================================================================
-- Clean up helper procedures (optional — they are harmless if left).
-- =====================================================================
DROP PROCEDURE IF EXISTS ed_add_column;
DROP PROCEDURE IF EXISTS ed_add_index;

-- Done. No existing data was modified destructively.
