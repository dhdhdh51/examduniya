-- =====================================================================
-- Exam Duniya — DATA BACKFILL (legacy columns -> new columns)
-- =====================================================================
-- Run this AFTER the columns exist (i.e. after importing schema.sql).
-- It only fills NEW columns that are still empty, using the old data.
-- Safe to run multiple times — it never overwrites existing values.
--
-- phpMyAdmin: select your DB -> SQL tab -> paste -> Go
-- CLI:        mysql -u USER -p DBNAME < database/backfill.sql
-- =====================================================================

-- 1) Official website URL  <-  legacy official_url
UPDATE notifications
   SET official_website_url = official_url
 WHERE (official_website_url IS NULL OR official_website_url = '')
   AND official_url IS NOT NULL
   AND official_url <> '';

-- 2) Application last date  <-  legacy last_date_apply
UPDATE notifications
   SET application_last_date = last_date_apply
 WHERE application_last_date IS NULL
   AND last_date_apply IS NOT NULL;

-- 3) Organization name  <-  legacy conducting_body
--    (public pages & Telegram now show organization_name)
UPDATE notifications
   SET organization_name = conducting_body
 WHERE (organization_name IS NULL OR organization_name = '')
   AND conducting_body IS NOT NULL
   AND conducting_body <> '';

-- 4) Publish date  <-  notification_date (if publish_date empty)
UPDATE notifications
   SET publish_date = notification_date
 WHERE publish_date IS NULL
   AND notification_date IS NOT NULL;

-- 5) SEO title  <-  title (only where missing; keeps manual SEO titles)
UPDATE notifications
   SET seo_title = title
 WHERE (seo_title IS NULL OR seo_title = '')
   AND title IS NOT NULL
   AND title <> '';

-- 6) Meta description  <-  short_desc (trimmed to 300 chars; only where missing)
UPDATE notifications
   SET meta_description = LEFT(short_desc, 300)
 WHERE (meta_description IS NULL OR meta_description = '')
   AND short_desc IS NOT NULL
   AND short_desc <> '';

-- 7) Make sure every notification is visible on homepage/Latest unless
--    explicitly hidden (older rows may have NULL).
UPDATE notifications
   SET is_homepage_visible = 1
 WHERE is_homepage_visible IS NULL;

-- ---------------------------------------------------------------------
-- BLOGS
-- ---------------------------------------------------------------------

-- 8) Blog SEO title  <-  title (where missing)
UPDATE blogs
   SET seo_title = title
 WHERE (seo_title IS NULL OR seo_title = '')
   AND title IS NOT NULL
   AND title <> '';

-- 9) Blog meta description  <-  excerpt (where missing)
UPDATE blogs
   SET meta_description = LEFT(excerpt, 300)
 WHERE (meta_description IS NULL OR meta_description = '')
   AND excerpt IS NOT NULL
   AND excerpt <> '';

-- 10) Blog published_at  <-  created_at (for already-published posts)
UPDATE blogs
   SET published_at = created_at
 WHERE published_at IS NULL
   AND is_published = 1
   AND created_at IS NOT NULL;

-- ---------------------------------------------------------------------
-- MOCK TESTS
-- ---------------------------------------------------------------------

-- 11) Mock test SEO title  <-  title (where missing)
UPDATE mock_tests
   SET seo_title = title
 WHERE (seo_title IS NULL OR seo_title = '')
   AND title IS NOT NULL
   AND title <> '';

-- Done. Re-run anytime; it only fills blanks.
