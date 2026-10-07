# GroCo Grocery Store — Production Readiness Checklist

This document provides the definitive verification and go-live checklist for deploying the GroCo PHP + MySQL Grocery E-Commerce platform to production.

---

## 1. Environment & Configuration Hardening

- [ ] **Create Production `.env`**: Copy `.env.example` to `.env` on production server.
- [ ] **Configure `APP_ENV`**: Set `APP_ENV=production`.
- [ ] **Disable Debugging**: Set `APP_DEBUG=false` (verifies zero PHP warnings, stack traces, or SQL errors shown to visitors).
- [ ] **Configure Canonical `APP_URL`**: Set `APP_URL=https://your-domain.com` (no trailing slash).
- [ ] **Database Credentials**: Configure production `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and strong random `DB_PASS`.
- [ ] **CSRF Secret Key**: Generate a 64-character random cryptographic hex string for `CSRF_SECRET_KEY`.
- [ ] **Verify `.env` Permissions**: Set file mode `chmod 600 .env` (readable only by web server user).
- [ ] **Ensure Git Exclusions**: Verify `.env` is listed in `.gitignore` and NEVER committed to version control.

---

## 2. Web Server & Apache Security (.htaccess)

- [ ] **Enforce HTTPS Redirect**: Ensure non-SSL `http://` requests redirect 301 to `https://`.
- [ ] **Canonical Domain**: Ensure either `www` or non-`www` is enforced consistently with 301 redirect.
- [ ] **Block Sensitive Files**: Verify that `.env`, `.git`, `.sql`, `.log`, `.bak`, `.key`, `composer.json` return `403 Forbidden`.
- [ ] **Block Internal Directories**: Verify that `/storage/`, `/database/`, `/tools/`, `/scratch/`, and `/tests/` return `403 Forbidden` if accessed via browser.
- [ ] **Disable Directory Listing**: Verify `Options -Indexes` is active across all directories.
- [ ] **Disable Server Signature**: Verify `ServerSignature Off` is active.
- [ ] **Verify HTTP Security Headers**:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()`
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains` (on HTTPS)
  - `Content-Security-Policy`: Compatible with Cloudinary CDN, Google Fonts, and Google Analytics.
- [ ] **Enable Gzip / Deflate Compression**: Test that text, CSS, JS, JSON, and SVG assets are served compressed.
- [ ] **Verify Browser Caching**: Test `Expires` / `Cache-Control` headers for images (1 month), fonts (1 year), and CSS/JS (1 week).

---

## 3. Session & Authentication Security

- [ ] **Secure Cookie Flag**: `SESSION_SECURE=true` in `.env` (cookies only transmitted over HTTPS).
- [ ] **HttpOnly Flag**: `SESSION_HTTPONLY=true` (cookies cannot be read via JavaScript `document.cookie`).
- [ ] **SameSite Attribute**: `SESSION_SAMESITE=Lax` (protects against Cross-Site Request Forgery).
- [ ] **Session Inactivity Timeout**: Set `SESSION_TIMEOUT=7200` (expires idle sessions after 2 hours).
- [ ] **Session Fixation Prevention**: Verify `session_regenerate_id(true)` fires upon login.
- [ ] **Clean Session Destruction**: Verify `logout_user()` removes session data and expires session cookie.
- [ ] **Brute-Force & Rate Limiting**: Verify login attempts increment `failed_logins` and enforce rate limits.
- [ ] **Admin Role Verification**: Ensure administrative access is verified server-side via session and database role checks, never trust hidden form fields.

---

## 4. CSRF & Input / Output Hardening

- [ ] **CSRF Tokens in Forms**: Verify every POST form contains `<input type="hidden" name="csrf_token" value="...">`.
- [ ] **CSRF Tokens in AJAX POSTs**: Verify AJAX headers or payloads include `X-CSRF-Token` or `csrf_token`.
- [ ] **Output Escaping**: Verify dynamic customer data, product names, reviews, and search terms use `htmlspecialchars()` / `e()`.
- [ ] **Database Queries**: Verify 100% of dynamic queries use PDO prepared statements with bound parameters (`PDO::ATTR_EMULATE_PREPARES => false`).

---

## 5. Cloudinary & Media CDN Integration

- [ ] **Obtain Cloudinary Credentials**: Sign up at [Cloudinary](https://cloudinary.com) and retrieve Cloud Name, API Key, and API Secret.
- [ ] **Populate `.env`**: Set `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, and `CLOUDINARY_API_SECRET`.
- [ ] **Run Image Migration Tool**:
  ```bash
  php tools/migrate_images_to_cloudinary.php
  ```
- [ ] **Verify Media Delivery**: Inspect storefront product images to confirm `res.cloudinary.com` URLs with `f_auto,q_auto,dpr_auto`.
- [ ] **Test Image Upload**: Upload a product image in Admin panel and verify successful Cloudinary upload and local fallback resiliency.
- [ ] **Verify LCP Optimization**: Verify first hero product image on details page uses `loading="eager"` and `fetchpriority="high"`.

---

## 6. SEO, Sitemaps & Search Console

- [ ] **Verify Clean SEO URLs**:
  - `/product/{slug}`
  - `/category/{slug}`
  - `/brand/{slug}`
  - `/search?q={query}`
- [ ] **Verify 301 Redirects**: Old `product.php?id=X` redirects to `/product/{slug}`.
- [ ] **Inspect Dynamic XML Sitemap**: Test `https://your-domain.com/sitemap.xml` for valid XML syntax and canonical URLs.
- [ ] **Verify `robots.txt`**: Test `https://your-domain.com/robots.txt` disallows `/admin/`, `/storage/`, etc., and links sitemap.
- [ ] **Google Search Console**: Add verification token in `.env` (`GSC_VERIFICATION_TOKEN=...`).
- [ ] **Submit Sitemap to Google Search Console**: Submit `sitemap.xml`.
- [ ] **Validate JSON-LD Structured Data**: Run [Google Rich Results Test](https://search.google.com/test/rich-results) on homepage and product pages.

---

## 7. Analytics & Tracking

- [ ] **Configure Google Analytics 4**: Set `GA_MEASUREMENT_ID=G-XXXXXXXXXX` in `.env` or in Admin Settings (`google_analytics`).
- [ ] **Verify Real-Time Events**: In GA4 Realtime view, verify:
  - Page views trigger properly.
  - Product page loads fire `view_item` ecommerce event.
  - Adding to cart triggers `add_to_cart`.
  - Checkout initiation triggers `begin_checkout`.
  - Completed orders trigger `purchase`.

---

## 8. Database Backups & Scheduled Maintenance

- [ ] **Test Backup Utility**:
  ```bash
  php tools/backup_database.php --compress
  ```
- [ ] **Verify Backup Location**: Confirm `.sql.gz` file exists in `storage/backups/`.
- [ ] **Verify Security**: Confirm `storage/backups/` is not accessible via web browser (returns 403).
- [ ] **Configure Automated Cron Job**:
  ```text
  0 2 * * * /usr/bin/php /var/www/grocery-store/tools/backup_database.php --compress --keep=7 --quiet
  ```
- [ ] **Verify Pruning**: Ensure archives older than 7 days are automatically removed.

---

## 9. Error Handling & Monitoring

- [ ] **Verify 404 Page**: Visit an invalid URL (`https://your-domain.com/non-existent-page`) and verify friendly 404 page.
- [ ] **Verify 403 Page**: Attempt to access a forbidden path and verify friendly 403 page.
- [ ] **Verify 500 Error Page**: Confirm custom error template displays without exposing file paths or database queries.
- [ ] **Verify Private Error Logging**: Confirm errors are logged exclusively to `storage/logs/app.log`.

---

## 10. Post-Launch Smoke Tests

- [ ] **Registration & Login**: Create test customer account, verify email/login.
- [ ] **Product Browsing**: Filter by category, brand, and search term.
- [ ] **Cart Operations**: Add items to cart, modify quantities, remove items.
- [ ] **Checkout Flow**: Complete Cash on Delivery or digital payment test order.
- [ ] **Admin Operations**: Login to admin dashboard, view order, update status.
- [ ] **POS Checkout**: Perform quick POS sale if POS module is enabled.
- [ ] **Mobile Responsiveness**: Test UI on iOS Safari and Android Chrome.
