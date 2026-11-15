# KaamKhoj - Job Portal (PHP 8 + MySQL, cPanel-ready)

## Install (cPanel)
1. cPanel › MySQL Databases → create DB + user, grant ALL.
2. Upload & extract the zip into `public_html` (or a subdomain folder).
3. Open `https://yourdomain/install.php` → fill DB + admin details → Install.
4. **Delete `install.php`.**
5. cPanel › Cron Jobs (every 5 min): `/usr/local/bin/php /home/USER/public_html/cron.php >/dev/null 2>&1`
6. Admin › Settings: SMTP, Google/Microsoft login (redirect URIs shown there), Groq AI key, SEO (Search Console code, Analytics).
7. Turn on HTTPS redirect in `.htaccess` once SSL is active. Submit `/sitemap.xml` to Google Search Console.

Requirements: PHP 8.0+ (pdo_mysql, curl, mbstring, fileinfo, gd, openssl), MySQL 5.7+/MariaDB 10.3+, Apache mod_rewrite.

## Structure
- `index.php` router · `app/` core (db, auth, mail/SMTP queue, OAuth, AI, layout) · `pages/` public, seeker, employer · `admin/` admin panel
- `sql/schema.sql`, `sql/seed.sql` · `uploads/` (CVs are private, served only via permission check)
- Change brand name/color anytime in Admin › Settings › General.
