-- GovExam Portal Database Schema
-- Run this SQL against your MySQL database to create all required tables

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

-- Default admin account
--   Email:    admin@govexam.local
--   Password: Admin@12345
-- IMPORTANT: Log in and change this email/password immediately after install
-- (Admin Panel > Users), or update the row below before importing.
INSERT INTO users (name, email, password_hash, role, plan, email_verified)
VALUES (
    'Administrator',
    'admin@govexam.local',
    '$2y$10$UAB.T6cuW3Ses9gLupXNc.zVGnZ2yLKde2XKCvZN04cYR9BUnvn.O',
    'admin',
    'yearly',
    1
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    short_desc TEXT,
    full_content LONGTEXT,
    -- Free-form category so ANY exam can be added (UP Police, UPSSSC, MP Police, etc.)
    category VARCHAR(100),
    conducting_body VARCHAR(150),
    notification_date DATE,
    last_date_apply DATE,
    exam_date DATE,
    vacancies INT,
    age_limit VARCHAR(100),
    qualification TEXT,
    fee_general DECIMAL(8,2),
    fee_obc DECIMAL(8,2),
    fee_sc_st DECIMAL(8,2),
    official_url VARCHAR(500),
    pdf_path VARCHAR(255),
    status ENUM('upcoming','active','result','admitcard') DEFAULT 'upcoming',
    telegram_sent TINYINT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mock_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    -- Free-form category so ANY exam can be added (UP Police, UPSSSC, etc.)
    category VARCHAR(100),
    total_questions INT DEFAULT 0,
    duration_minutes INT DEFAULT 60,
    marks_per_question DECIMAL(4,2) DEFAULT 2.00,
    negative_marking DECIMAL(4,2) DEFAULT 0.50,
    access_type ENUM('free','premium') DEFAULT 'free',
    questions_json LONGTEXT,
    is_active TINYINT DEFAULT 1,
    attempts_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Default settings: Gemini AI
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('gemini_api_key', '', 'gemini'),
('gemini_model', 'gemini-3.5-flash', 'gemini');

-- Default settings: Telegram
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('telegram_bot_token', '', 'telegram'),
('telegram_chat_ids', '', 'telegram'),
('telegram_enabled', '1', 'telegram');

-- Default settings: SMTP / Email
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('smtp_host', '', 'smtp'),
('smtp_port', '587', 'smtp'),
('smtp_username', '', 'smtp'),
('smtp_password', '', 'smtp'),
('smtp_from_email', '', 'smtp'),
('smtp_from_name', 'GovExam Portal', 'smtp'),
('smtp_encryption', 'tls', 'smtp'),
('smtp_enabled', '1', 'smtp');

-- Default settings: PayU
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('payu_merchant_key', '', 'payu'),
('payu_merchant_salt', '', 'payu'),
('payu_mode', 'test', 'payu'),
('payu_enabled', '1', 'payu'),
('plan_monthly_price', '99', 'payu'),
('plan_yearly_price', '799', 'payu');

-- Default settings: Site
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'GovExam Portal', 'site'),
('site_description', 'Government Exam Notifications, Mock Tests & Study Material', 'site'),
('site_url', 'https://example.com', 'site'),
('site_logo', '', 'site'),
('google_analytics_id', '', 'site'),
('maintenance_mode', '0', 'site'),
('per_page', '12', 'site');

-- Default settings: Google OAuth
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('google_client_id', '', 'google'),
('google_client_secret', '', 'google'),
('google_redirect_uri', '', 'google');

-- Managed list of exam categories (powers admin suggestions + public filters).
-- category columns above are free-form VARCHAR, so you can use ANY name.
CREATE TABLE IF NOT EXISTS exam_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) UNIQUE NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO exam_categories (name, sort_order) VALUES
    ('SSC', 1),
    ('UPSC', 2),
    ('Railway', 3),
    ('Banking', 4),
    ('State PSC', 5),
    ('Defence', 6),
    ('UP Police', 7),
    ('UPSSSC', 8),
    ('Bihar Police', 9),
    ('MP Police', 10),
    ('Rajasthan Police', 11),
    ('Teaching (CTET/TET)', 12),
    ('Nursing', 13),
    ('Other', 99);
