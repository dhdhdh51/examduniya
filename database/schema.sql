-- =====================================================================
-- Exam Duniya — SINGLE UPGRADED DATABASE SCHEMA
-- =====================================================================
-- This is the ONE file to import. It works for BOTH:
--   * a fresh install  (creates every table with all columns), and
--   * an existing site  (idempotent guards add any missing columns/
--                         tables/settings without touching your data).
--
-- Safe to run multiple times. No DROP/destructive statements.
--
-- phpMyAdmin: select DB -> Import -> choose this file -> Go.
-- CLI:        mysql -u USER -p DBNAME < database/schema.sql
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    password_hash VARCHAR(255),
    google_id VARCHAR(100),
    role ENUM('admin','premium','free') DEFAULT 'free',
    plan ENUM('monthly','yearly','free') DEFAULT 'free',
    plan_expiry DATE,
    email_verified TINYINT DEFAULT 0,
    avatar VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account (only inserted if it does not already exist).
--   Email:    admin@govexam.local
--   Password: Admin@12345
-- IMPORTANT: change this email/password immediately after install.
INSERT IGNORE INTO users (name, email, password_hash, role, plan, email_verified)
VALUES (
    'Administrator',
    'admin@govexam.local',
    '$2y$10$UAB.T6cuW3Ses9gLupXNc.zVGnZ2yLKde2XKCvZN04cYR9BUnvn.O',
    'admin',
    'yearly',
    1
);

-- ---------------------------------------------------------------------
-- NOTIFICATIONS (exams / admit cards / results) — full upgraded columns
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    short_desc TEXT,
    full_content LONGTEXT,
    category VARCHAR(100),
    conducting_body VARCHAR(150),
    organization_name VARCHAR(150),
    post_type VARCHAR(40) NOT NULL DEFAULT 'notification',
    notification_date DATE,
    publish_date DATE,
    application_start_date DATE,
    last_date_apply DATE,
    application_last_date DATE,
    exam_date DATE,
    admit_card_date DATE,
    result_date DATE,
    last_verified_at DATETIME NULL,
    vacancies INT,
    age_limit VARCHAR(100),
    qualification TEXT,
    eligibility_summary TEXT,
    fee_general DECIMAL(8,2),
    fee_obc DECIMAL(8,2),
    fee_sc_st DECIMAL(8,2),
    official_url VARCHAR(500),
    official_website_url VARCHAR(500),
    official_notification_pdf_url VARCHAR(500),
    official_apply_url VARCHAR(500),
    pdf_path VARCHAR(255),
    featured_image VARCHAR(255),
    status ENUM('upcoming','active','result','admitcard') DEFAULT 'upcoming',
    computed_status VARCHAR(30) NULL,
    is_featured TINYINT NOT NULL DEFAULT 0,
    is_homepage_visible TINYINT NOT NULL DEFAULT 1,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    canonical_url VARCHAR(500) NULL,
    telegram_sent TINYINT DEFAULT 0,
    created_by INT,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notif_category (category),
    INDEX idx_notif_status (status),
    INDEX idx_notif_posttype (post_type),
    INDEX idx_notif_applast (application_last_date),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- MOCK TESTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mock_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    category VARCHAR(100),
    total_questions INT DEFAULT 0,
    duration_minutes INT DEFAULT 60,
    marks_per_question DECIMAL(4,2) DEFAULT 2.00,
    negative_marking DECIMAL(4,2) DEFAULT 0.50,
    access_type ENUM('free','premium') DEFAULT 'free',
    questions_json LONGTEXT,
    is_active TINYINT DEFAULT 1,
    attempts_count INT DEFAULT 0,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- USER ATTEMPTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    test_id INT NOT NULL,
    answers_json LONGTEXT,
    score DECIMAL(8,2),
    total_marks DECIMAL(8,2),
    correct_count INT DEFAULT 0,
    wrong_count INT DEFAULT 0,
    unanswered_count INT DEFAULT 0,
    time_taken_seconds INT,
    tab_switches INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES mock_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PAYMENTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    txn_id VARCHAR(100) UNIQUE,
    plan ENUM('monthly','yearly') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_gateway VARCHAR(50) DEFAULT 'payu',
    gateway_response TEXT,
    status ENUM('pending','success','failed','refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- BLOGS — full upgraded columns
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    content LONGTEXT,
    excerpt TEXT,
    featured_image VARCHAR(255),
    category VARCHAR(100),
    tags VARCHAR(255),
    author_id INT,
    views INT DEFAULT 0,
    is_published TINYINT DEFAULT 0,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(320) NULL,
    canonical_url VARCHAR(500) NULL,
    last_verified_at DATETIME NULL,
    read_time_minutes INT NULL,
    content_status VARCHAR(20) NOT NULL DEFAULT 'published',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- COMMENTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    content TEXT,
    is_approved TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PASSWORD RESETS / LOGIN ATTEMPTS / SETTINGS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45),
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- EXAM CATEGORIES (managed suggestion list)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) UNIQUE NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO exam_categories (name, sort_order) VALUES
    ('SSC', 1), ('UPSC', 2), ('Railway', 3), ('Banking', 4), ('State PSC', 5),
    ('Defence', 6), ('UP Police', 7), ('UPSSSC', 8), ('Bihar Police', 9),
    ('MP Police', 10), ('Rajasthan Police', 11), ('Teaching (CTET/TET)', 12),
    ('Nursing', 13), ('Other', 99)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- AI PROVIDERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_providers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    provider_key VARCHAR(50) UNIQUE NOT NULL,
    name         VARCHAR(100) NOT NULL,
    api_type     ENUM('gemini','openai','anthropic') NOT NULL DEFAULT 'openai',
    api_key      TEXT,
    model        VARCHAR(100),
    endpoint     VARCHAR(255),
    enabled      TINYINT(1) NOT NULL DEFAULT 0,
    is_default   TINYINT(1) NOT NULL DEFAULT 0,
    last_test    TEXT,
    sort_order   INT NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO ai_providers
    (provider_key, name, api_type, model, endpoint, enabled, is_default, sort_order)
VALUES
    ('gemini',     'Google Gemini',      'gemini',    'gemini-3.5-flash',          'https://generativelanguage.googleapis.com/v1beta', 1, 1, 1),
    ('openai',     'ChatGPT (OpenAI)',   'openai',    'gpt-4o-mini',               'https://api.openai.com/v1',                         0, 0, 2),
    ('claude',     'Claude (Anthropic)', 'anthropic', 'claude-3-5-sonnet-latest',  'https://api.anthropic.com/v1',                      0, 0, 3),
    ('deepseek',   'DeepSeek',           'openai',    'deepseek-chat',             'https://api.deepseek.com/v1',                       0, 0, 4),
    ('grok',       'Grok (xAI)',         'openai',    'grok-2-latest',             'https://api.x.ai/v1',                               0, 0, 5),
    ('openrouter', 'OpenRouter',         'openai',    'openai/gpt-4o-mini',        'https://openrouter.ai/api/v1',                      0, 0, 6)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- NEW SUPPORT TABLES: redirects, activity_logs, corrections
-- ---------------------------------------------------------------------
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
-- DEFAULT SETTINGS (INSERT IGNORE = safe on existing installs)
-- =====================================================================
INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
('gemini_api_key', '', 'gemini'),
('gemini_model', 'gemini-3.5-flash', 'gemini'),
('telegram_bot_token', '', 'telegram'),
('telegram_chat_ids', '', 'telegram'),
('telegram_enabled', '1', 'telegram'),
('smtp_host', '', 'smtp'),
('smtp_port', '587', 'smtp'),
('smtp_username', '', 'smtp'),
('smtp_password', '', 'smtp'),
('smtp_from_email', '', 'smtp'),
('smtp_from_name', 'Exam Duniya', 'smtp'),
('smtp_encryption', 'tls', 'smtp'),
('smtp_enabled', '1', 'smtp'),
('payu_merchant_key', '', 'payu'),
('payu_merchant_salt', '', 'payu'),
('payu_mode', 'test', 'payu'),
('payu_enabled', '1', 'payu'),
('plan_monthly_price', '99', 'payu'),
('plan_yearly_price', '799', 'payu'),
('site_name', 'Exam Duniya', 'site'),
('site_description', 'Get latest SSC, UPSC, Railway, Banking, Defence, UP Police and State Government job notifications, admit cards, results, syllabus, free mock tests and study material on Exam Duniya.', 'site'),
('site_url', 'https://examduniya.in', 'site'),
('site_logo', '', 'site'),
('google_analytics_id', '', 'site'),
('maintenance_mode', '0', 'site'),
('per_page', '12', 'site'),
('google_client_id', '', 'google'),
('google_client_secret', '', 'google'),
('google_redirect_uri', '', 'google'),
('canonical_domain', 'https://examduniya.in', 'seo'),
('footer_disclaimer', 'Exam Duniya is an independent education and exam information platform. We are not affiliated with any government recruitment board. Candidates must verify all important details from the official website before applying.', 'seo'),
('homepage_seo_title', 'Exam Duniya: Govt Exam Notifications, Free Mock Tests & Results', 'seo'),
('show_public_counters', '0', 'seo'),
('default_og_image', '', 'seo'),
('twitter_handle', '', 'seo'),
('organization_name', 'Exam Duniya', 'seo');

-- =====================================================================
-- IDEMPOTENT UPGRADE BLOCK
-- For pre-existing databases that were created before these columns
-- existed. Each ADD is guarded so this whole file is safe to re-run and
-- never errors on a fresh DB (where the columns already exist above).
-- =====================================================================
DROP PROCEDURE IF EXISTS ed_add_column;
DELIMITER //
CREATE PROCEDURE ed_add_column(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Widen legacy ENUM category columns to free-form VARCHAR (safe if already VARCHAR).
ALTER TABLE notifications MODIFY COLUMN category VARCHAR(100);
ALTER TABLE mock_tests    MODIFY COLUMN category VARCHAR(100);

-- notifications
CALL ed_add_column('notifications','organization_name','VARCHAR(150) NULL');
CALL ed_add_column('notifications','post_type',"VARCHAR(40) NOT NULL DEFAULT 'notification'");
CALL ed_add_column('notifications','publish_date','DATE NULL');
CALL ed_add_column('notifications','application_start_date','DATE NULL');
CALL ed_add_column('notifications','application_last_date','DATE NULL');
CALL ed_add_column('notifications','admit_card_date','DATE NULL');
CALL ed_add_column('notifications','result_date','DATE NULL');
CALL ed_add_column('notifications','last_verified_at','DATETIME NULL');
CALL ed_add_column('notifications','eligibility_summary','TEXT NULL');
CALL ed_add_column('notifications','official_website_url','VARCHAR(500) NULL');
CALL ed_add_column('notifications','official_notification_pdf_url','VARCHAR(500) NULL');
CALL ed_add_column('notifications','official_apply_url','VARCHAR(500) NULL');
CALL ed_add_column('notifications','featured_image','VARCHAR(255) NULL');
CALL ed_add_column('notifications','computed_status','VARCHAR(30) NULL');
CALL ed_add_column('notifications','is_featured','TINYINT NOT NULL DEFAULT 0');
CALL ed_add_column('notifications','is_homepage_visible','TINYINT NOT NULL DEFAULT 1');
CALL ed_add_column('notifications','seo_title','VARCHAR(255) NULL');
CALL ed_add_column('notifications','meta_description','VARCHAR(320) NULL');
CALL ed_add_column('notifications','canonical_url','VARCHAR(500) NULL');
CALL ed_add_column('notifications','updated_by','INT NULL');
CALL ed_add_column('notifications','updated_at','TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- blogs
CALL ed_add_column('blogs','seo_title','VARCHAR(255) NULL');
CALL ed_add_column('blogs','meta_description','VARCHAR(320) NULL');
CALL ed_add_column('blogs','canonical_url','VARCHAR(500) NULL');
CALL ed_add_column('blogs','last_verified_at','DATETIME NULL');
CALL ed_add_column('blogs','read_time_minutes','INT NULL');
CALL ed_add_column('blogs','content_status',"VARCHAR(20) NOT NULL DEFAULT 'published'");
CALL ed_add_column('blogs','updated_at','TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- mock_tests
CALL ed_add_column('mock_tests','seo_title','VARCHAR(255) NULL');
CALL ed_add_column('mock_tests','meta_description','VARCHAR(320) NULL');
CALL ed_add_column('mock_tests','updated_at','TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

DROP PROCEDURE IF EXISTS ed_add_column;

-- Backfill new date/link columns from legacy ones (first run only).
UPDATE notifications SET application_last_date = last_date_apply
 WHERE application_last_date IS NULL AND last_date_apply IS NOT NULL;
UPDATE notifications SET official_website_url = official_url
 WHERE (official_website_url IS NULL OR official_website_url = '')
   AND official_url IS NOT NULL AND official_url <> '';

-- Re-assert brand on rows still holding the old default.
UPDATE settings SET setting_value = 'Exam Duniya'
 WHERE setting_key IN ('site_name','smtp_from_name','organization_name')
   AND (setting_value IS NULL OR setting_value = '' OR setting_value = 'GovExam Portal');

-- Done.
