-- ============================================================
-- Migration: make exam categories flexible (any exam name)
-- Run this ONCE on an EXISTING database (created before this change).
-- New installs using the updated schema.sql do not need it.
--
-- Safe to run: it only widens the column type; existing data is kept.
-- ============================================================

-- 1) Convert the fixed ENUM category columns to free-form VARCHAR.
ALTER TABLE notifications MODIFY COLUMN category VARCHAR(100);
ALTER TABLE mock_tests   MODIFY COLUMN category VARCHAR(100);

-- 2) Optional: a managed list of exam categories used to power the
--    suggestion dropdowns in the admin forms and the public filters.
--    You can add/remove rows here or from the admin panel.
CREATE TABLE IF NOT EXISTS exam_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) UNIQUE NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed with the original set plus common state exams. Add your own anytime.
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
    ('Other', 99)
ON DUPLICATE KEY UPDATE name = VALUES(name);
