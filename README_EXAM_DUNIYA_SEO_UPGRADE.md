# Exam Duniya — SEO / Trust / Content-Freshness Upgrade

This document explains everything added in the SEO upgrade and how to deploy it
**safely on shared hosting (PHP 8+ / MySQL / Apache)** without breaking existing
functionality or data.

> The stack is unchanged: plain PHP + MySQL + Apache. Nothing was migrated to
> Node, Laravel, React, or any framework. All database changes are **additive**
> (new columns/tables only — no data is dropped or rewritten).

---

## 1. What changed (file map)

### New files
| File | Purpose |
|------|---------|
| `database/migration_seo_upgrade.sql` | Idempotent, additive DB migration (new columns + `redirects`, `activity_logs`, `corrections` tables + branding settings). |
| `includes/seo.php` | Central SEO engine — title, description, canonical, robots, Open Graph, Twitter Card. |
| `includes/schema.php` | JSON-LD schema helpers (Organization, WebSite, Breadcrumb, Article/BlogPosting, CollectionPage, FAQ). |
| `robots.txt` | Crawl rules + sitemap references. |
| `sitemaps/_bootstrap.php` + `sitemaps/*.php` | Dynamic sub-sitemaps (pages, exams, blogs, categories, mock-tests). |
| `cron/update_exam_statuses.php` | Daily lifecycle-status recompute (Asia/Kolkata). |
| `pages/about-exam-duniya.php`, `editorial-policy.php`, `fact-check-policy.php`, `correction-policy.php`, `author.php` | Trust / E-E-A-T pages. |
| `api/report-correction.php` | "Report Correction" endpoint -> `corrections` table. |
| `admin/seo/index.php`, `admin/seo/corrections.php` | SEO & Content-Quality dashboard + corrections inbox. |

### Modified files (kept backward compatible)
- `sitemap.xml.php` -> now a **sitemap index** pointing at the sub-sitemaps.
- `.htaccess` -> HTTPS + www->non-www, clean URLs, 301s for old URLs, gzip/caching, security headers.
- `index.php` -> homepage SEO title/H1/description, hides low-trust counters, excludes closed posts, homepage schema, clean internal links.
- `includes/header.php` -> renders meta via `seo_render_head()` (legacy `$page_title` / `$meta_desc` still work).
- `includes/footer.php` -> trust disclaimer + trust-page links.
- `includes/functions.php` -> clean-URL helpers (`exam_url`, `blog_url`, `category_url`, `author_url`), lifecycle helpers, `log_activity()`.
- `pages/exams/detail.php` -> full SEO meta, Article + Breadcrumb schema, "Last Verified" block, official-link buttons, "Report Correction".
- `pages/exams/listing.php` -> category H1/intro, canonical, `noindex` on filtered views, schema.
- `pages/blog/detail.php`, `pages/blog/listing.php` -> BlogPosting schema, author links, read-time, canonical, clean URLs.
- Auth / dashboard / purchase / attempt / result pages -> `noindex,follow`.
- Branding: every `GovExam Portal` fallback -> `Exam Duniya`.

---

## 2. How to upload files

1. **Back up** your current site and database first.
2. Upload the changed/new files to your web root (e.g. `public_html/`), keeping
   the same folder structure. Overwrite the modified files.
3. Ensure `robots.txt`, `.htaccess`, `sitemap.xml.php` and the `sitemaps/`
   folder are in the **web root**.

No file permissions beyond the usual (644 files / 755 dirs) are required.

---

## 3. How to run the SQL migration

Run **once** (it is safe to re-run — every change is guarded):

**phpMyAdmin:** select your database -> *Import* -> choose
`database/migration_seo_upgrade.sql` -> *Go*.

**CLI:**
```bash
mysql -u DB_USER -p DB_NAME < database/migration_seo_upgrade.sql
```

This adds the new notification fields (`application_last_date`, `result_date`,
`last_verified_at`, `official_website_url`, `seo_title`, `meta_description`,
`computed_status`, ...), the `redirects` / `activity_logs` / `corrections`
tables, sets the brand to **Exam Duniya**, and adds SEO settings. It backfills
`application_last_date` from the legacy `last_date_apply` automatically.

> If you skip the migration the site still runs — the admin SEO dashboard and
> new fields simply show "N/A" until the columns exist.

---

## 4. How to configure the cron job

In cPanel -> *Cron Jobs*, add a daily job (e.g. 00:15 IST):
```
/usr/bin/php /home/USERNAME/public_html/cron/update_exam_statuses.php
```
It recomputes each notification's lifecycle status (Open / Upcoming / Closed /
Admit Card / Exam Completed / Result Awaited / Result) using the **Asia/Kolkata**
timezone, and only writes rows that actually change. It never deletes posts.

(Optional web trigger: set a `CRON_SECRET` env var and call
`/cron/update_exam_statuses.php?key=YOUR_SECRET`. The `/cron/` folder is blocked
in `.htaccess` for normal browsing.)

---

## 5. How to test redirects

After deploying, check (browser dev-tools -> Network, or `curl -I`):

| Request | Expected |
|---------|----------|
| `http://examduniya.in/` | 301 -> `https://examduniya.in/` |
| `https://www.examduniya.in/exams/` | 301 -> `https://examduniya.in/exams/` |
| `/pages/exams/detail.php?slug=SOME-SLUG` | 301 -> `/exams/SOME-SLUG/` |
| `/pages/blog/detail.php?slug=SOME-SLUG` | 301 -> `/blog/SOME-SLUG/` |
| `/notification/SOME-SLUG` | 301 -> `/exams/SOME-SLUG/` |
| `/exams/SOME-SLUG/` | 200 (serves detail page) |

```bash
curl -sI https://www.examduniya.in/exams/ | grep -i location
```
There should be **no redirect loops** — each old URL resolves in a single hop.

---

## 6. How to submit the sitemap in Google Search Console

1. Verify `https://examduniya.in` in Search Console.
2. *Sitemaps* -> submit: `sitemap.xml`
   (the index automatically references `sitemap-pages.xml`, `sitemap-exams.xml`,
   `sitemap-blogs.xml`, `sitemap-categories.xml`, `sitemap-mock-tests.xml`).
3. The sitemaps regenerate on every request, so new content appears
   automatically — no manual rebuild needed.

---

## 7. How to verify canonical tags

View source of any public page and confirm a single:
```html
<link rel="canonical" href="https://examduniya.in/...">
```
- Homepage -> `https://examduniya.in/`
- Category -> `https://examduniya.in/category/{name}/`
- Exam -> `https://examduniya.in/exams/{slug}/`
- Blog -> `https://examduniya.in/blog/{slug}/`

Filtered/search/paginated views point their canonical back to the clean base URL
and are marked `noindex,follow`.

---

## 8. How to verify noindex pages

`curl -s URL | grep -i 'name="robots"'` should show `noindex` for:
login, signup, forgot/reset password, verify-email, dashboard, profile,
mock-test purchase, attempt, result, and any `?sort=`/`?status=`/`?q=`/`?page=`
listing view. Public content pages show `index,follow`.

---

## 9. How to test schema

Use Google's Rich Results Test or the Schema Markup Validator:
- Homepage -> Organization + WebSite (SearchAction)
- Category -> CollectionPage + BreadcrumbList
- Exam -> Article + BreadcrumbList (+ publisher)
- Blog -> BlogPosting + BreadcrumbList + author
- FAQ schema is emitted **only** where visible FAQs exist.

No fake Review/Rating/JobPosting/Event schema is used.

---

## 10. Admin tools

- **Admin -> SEO & Quality -> SEO Dashboard**: expired/unverified posts, missing
  source links / titles / descriptions / images / categories, stale (30+ day)
  posts, duplicate titles/descriptions, robots & sitemap status.
- **Admin -> SEO & Quality -> Reported Corrections**: inbox of user-submitted
  corrections; mark resolved/reopen.

---

## 11. How to clear cache if needed

There is no server-side page cache. If a browser/CDN shows stale content:
- Hard refresh (Ctrl/Cmd + Shift + R).
- If using Cloudflare: *Caching -> Purge Everything*, and keep "Always Use HTTPS"
  on (it complements the `.htaccess` HTTPS rule without conflicting).

---

## 12. Safety notes / rollback

- The migration is additive and idempotent; to roll back code, restore the
  previous files from your backup. Data added to new tables/columns is harmless
  if the old code is restored.
- Existing logins, payments, mock-test flow, admin data and old URLs continue to
  work. Old URLs 301-redirect to the new clean URLs.
- Set **Site Name** and **Canonical Domain** under *Admin -> Settings* if you ever
  need to change them — branding is read from settings first, with `Exam Duniya`
  only as a fallback.
