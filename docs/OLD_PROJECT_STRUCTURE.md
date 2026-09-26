# GroCo Grocery Store — Complete Project Directory & Architecture Blueprint

This document provides a comprehensive technical inventory of the directory hierarchy, core architectural layout, and file-level relationships for the GroCo Grocery Store PHP / MySQL project.

---

## 1. Top-Level Directory Overview

```text
c:\xampp\htdocs\grocery-store\
├── .env.example                     # Environment configuration template (DB, SMTP, OAuth, App)
├── .gitignore                       # Git exclusion rules protecting secrets, logs, uploads, keys
├── .htaccess                        # Root Apache rewrite rules, security headers, file access locks
├── index.php                        # Root router forwarding entry traffic to public/index.php
├── README.md                        # Developer onboarding & test suite execution guide
├── admin/                           # Administrative Back-Office, POS, ERP, and Store Management
├── database/                        # Database schemas, migrations, seeders, and backup definitions
├── docs/                            # Technical specifications and operational blueprints
├── licensing_server/                # Standalone RSA-2048 Licensing Authority & Server Subsystem
├── public/                          # Public Customer-Facing Storefront & Core Application Engine
├── storage/                         # Runtime logs, uploaded media, backups, and cache
└── tests/                           # Automated test suites covering Auth, Security, Licensing, SEO
```

---

## 2. Core Architectural Subsystems

### A. Root Configuration & Bootstrap Layer
* **`.htaccess`**
  * **Purpose:** Apache web server configuration.
  * **What it does:** Enforces `Options -Indexes`, blocks direct HTTP requests to `.env`, `.git`, `*.sql`, `*.log`, `*.pem`, `*.key`. Sets HTTP security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`). Rewrites clean SEO routes (`/product/{slug}`, `/category/{slug}`, `/license`, `/activate`, `/sitemap.xml`, `/robots.txt`).
* **`index.php`**
  * **Purpose:** Top-level forwarder.
  * **What it does:** Forwards any root domain hit to `public/index.php`.

---

### B. Public Storefront Application (`public/`)

The `public/` directory contains the customer-facing storefront, layout components, AJAX endpoints, and foundational framework libraries.

#### 1. Core Framework & Engine (`public/includes/`)
* **`public/dbconnect.php`**
  * **Purpose:** Central bootstrap and PDO database connector.
  * **Depends on:** `.env`, `public/includes/logger.php`, `security.php`, `rate_limit.php`, `functions.php`, `helpers.php`, `validation.php`, `csrf.php`, `mailer.php`, `auth.php`, `license.php`.
  * **What it does:** Loads environment variables, configures error handling/timezone (`Asia/Dhaka`), initializes secure session parameters (`HttpOnly`, `SameSite=Lax`, `Secure`), provides singleton `Database::getConnection()` and `db()` helper, binds session to User-Agent hash to prevent session hijacking, triggers `enforce_license()`, loads dynamic contact settings from `settings` table.
* **`public/includes/auth.php`**
  * **Purpose:** Authentication, session versioning, user credentials management.
  * **What it does:** Handles customer registration, password hashing (`password_hash` with `PASSWORD_DEFAULT`), login attempt rate-limiting (`failed_logins` counter in `users`), multi-device session invalidation (`session_version`), customer-admin separation (`is_admin()`, `is_admin_email()`), cart merge on authentication.
* **`public/includes/google_auth.php`**
  * **Purpose:** Google OAuth 2.0 customer onboarding and account linking.
  * **What it does:** Communicates with Google token & userinfo endpoints, checks if email belongs to an administrator (strictly blocks admin emails from storefront registration), performs atomic account linking into `users` table without creating duplicate rows, binds Google customer accounts to existing order history.
* **`public/includes/mailer.php`**
  * **Purpose:** Low-level socket-based SMTP delivery engine.
  * **What it does:** Connects to SMTP host/port (TLS/STARTTLS/SSL) via stream sockets, negotiates `AUTH LOGIN`, generates responsive HTML transactional emails with plain text fallbacks for password resets, Google password setup, and order confirmations.
* **`public/includes/security.php`**
  * **Purpose:** Application security guards and sanitization.
  * **What it does:** XSS input sanitization, security headers, IP resolution, brute-force mitigation helpers.
* **`public/includes/csrf.php` & `public/csrf.php`**
  * **Purpose:** Cross-Site Request Forgery token generation and validation.
  * **What it does:** Generates cryptographically secure CSRF tokens stored in session (`csrf_token()`), provides HTML hidden input field (`csrf_field()`), verifies tokens on POST requests (`verify_csrf()`).
* **`public/includes/seo.php`**
  * **Purpose:** Dynamic SEO metadata, canonical URL normalization, and Schema.org JSON-LD generator.
  * **What it does:** Normalizes HTTPS canonical URLs, strips tracking query parameters, determines `robots` meta tags (`index, follow` vs `noindex`), compiles Schema.org `Product`, `Offer`, `BreadcrumbList`, `Organization`, `GroceryStore`, and `WebSite` structured data.
* **`public/includes/license.php`**
  * **Purpose:** Software license client middleware and verification engine.
  * **What it does:** Cryptographically verifies RSA-2048 signed activation payloads, binds installations to authorized domains, manages offline 7-day grace periods during network outages, logs audit events into `system_license_logs`, halts execution if license is revoked/tampered.
* **`public/includes/functions.php` & `public/includes/helpers.php`**
  * **Purpose:** Business logic calculation and template formatting helpers.
  * **What it does:** Formats currency (`format_price()`), generates SVG customer initials avatar (`generate_initials_svg_data_uri()`), resolves media paths (`image_url()`), handles translations (`t()`), builds asset URLs (`asset()`), builds page URLs (`url_for()`).

#### 2. Customer Pages (`public/*.php`)
* **`public/index.php`:** Homepage with hero banner slider, category grid, flash sale countdown, featured products, today's deals, best sellers, and newsletter subscription.
* **`public/products.php`:** Main catalog listing with hybrid server-rendered + AJAX filtering (by category, brand, price range, stock availability, star rating, on-sale status) and pagination.
* **`public/product.php`:** Comprehensive product details view with multi-image gallery zoom, star rating distribution, customer review submission, verified purchase badges, related products, frequently bought together bundle, and recently viewed tracking.
* **`public/categories.php`:** Full directory of parent and subcategories with active product counts.
* **`public/cart.php`:** Customer shopping cart view with item quantity controls, unit price updates, subtotal, coupon discount calculation, and free shipping threshold progress bar.
* **`public/checkout.php`:** Step-by-step checkout with saved address selection, new address creation, delivery date/time slot picker, payment method selector (COD, bKash, Nagad, SSLCommerz), order review, and discount computation.
* **`public/login.php` & `public/register.php`:** Customer authentication pages with password visibility toggles, client-side validation, official Google OAuth buttons, and admin email registration barriers.
* **`public/forgot-password.php` & `public/reset-password.php`:** Secure customer password recovery flow utilizing SHA-256 token hashing, 1-hour expiration timestamps, single-use invalidation, and transactional SMTP dispatch.
* **`public/profile.php`:** Customer account dashboard showing order history, profile details editor, security/auth methods manager (allowing Google-linked accounts to add/change local passwords), multi-device signout trigger, and saved addresses.
* **`public/orders.php` & `public/order-details.php`:** Customer order tracking, status timeline, itemized invoice preview, and delivery progress.
* **`public/wishlist.php`:** Customer saved products collection with one-click move-to-cart.
* **`public/compare.php`:** Side-by-side product comparison table (specifications, price, ratings, stock).
* **`public/about.php`, `public/contact.php`, `public/faq.php`, `public/privacy.php`, `public/terms.php`:** Static and CMS-driven public information pages.
* **`public/sitemap.php` & `public/sitemap.xml`:** XML sitemap feeds with Google Image search extensions.
* **`public/robots.txt`:** Web crawler indexing directives.

#### 3. Public AJAX & API Endpoints (`public/ajax/` & `public/api/`)
* **`public/ajax/cart.php`:** Add to cart, update quantity, remove item, clear cart, fetch cart count and drawer HTML.
* **`public/ajax/wishlist.php`:** Toggle product in wishlist, fetch wishlist items.
* **`public/ajax/compare.php`:** Add/remove items from product comparison session.
* **`public/ajax/quickview.php`:** Returns modal HTML for fast product preview.
* **`public/ajax/search.php` & `public/ajax/live-search.php`:** Autocomplete instant search query returning matching products with thumbnails and prices.
* **`public/ajax/reviews.php`:** Submits customer product reviews, rating, and image attachments.
* **`public/ajax/coupon.php`:** Validates coupon codes against minimum spend and expiry, returning discount amount.
* **`public/ajax/newsletter.php`:** Collects newsletter email subscriptions.

#### 4. Reusable UI Components (`public/components/`)
* **`meta-tags.php`:** Dynamic `<head>` metadata, Open Graph tags, Twitter cards, Schema.org JSON-LD.
* **`product-card.php`:** Product grid card with discount badges, star ratings, quick action overlay, and buy buttons.
* **`category-card.php`:** Category grid item with image and product count.
* **`breadcrumb.php`:** Visible breadcrumb navigation hierarchy.
* **`filter-sidebar.php`:** Catalog filter panel with category checklists, brand filters, price sliders.
* **`rating-stars.php`:** Reusable 5-star visual rating component with half-star support.
* **`install-app.php`:** Smart PWA install prompt banner with emerald gradient badge and dismissal logic.

---

### C. Administrative Back-Office, POS & ERP (`admin/`)

The `admin/` directory houses the management control panel, Point of Sale (POS) cash register, ERP inventory controls, finance, reporting, role-based access control (RBAC), and store configuration.

#### 1. Authentication & Security Middleware (`admin/middleware/` & `admin/includes/`)
* **`admin/middleware/auth_middleware.php`:**
  * Enforces active admin session (`$_SESSION['admin_id']`), checks account active status (`status = 'active'`), logs admin activity, terminates unauthenticated requests with redirect to `admin/login.php`.
* **`admin/includes/auth_helpers.php`:**
  * Defines RBAC helpers: `has_permission(string $perm)`, `require_admin_permission(string $perm)`, `is_super_admin()`, `require_super_admin()`, `admin_activity_log()`.
* **`admin/login.php`:** Admin authentication page using `admins` table, password hashing, and brute-force tracking.
* **`admin/forgot-password.php` & `admin/reset-password.php`:** Disabled (returns HTTP 403) to eliminate self-service admin reset vulnerabilities.
* **`admin/admins/reset-password.php`:** Super Admin-controlled temporary OTP password reset generator.

#### 2. Admin Modules Overview
| Module Directory | Key Files | Operational Purpose |
| :--- | :--- | :--- |
| **`admin/pos/`** | `index.php`, `receipt.php`, `ajax/pos_actions.php` | Retail Point of Sale cash register with barcode scanning, product lookup, customer assignment, split payments (Cash/bKash/Card), discount/tax calculations, thermal receipt printing, hold/resume orders. |
| **`admin/products/`** | `index.php`, `create.php`, `edit.php`, `delete.php`, `export.php` | Catalog inventory manager: SKU, barcode, cost price, sale price, stock levels, low stock alert threshold, gallery image uploads, SEO fields, status toggles. |
| **`admin/categories/`** | `index.php`, `create.php`, `edit.php`, `delete.php` | Category tree management: parent-child hierarchies, slugs, icons, thumbnails, descriptions. |
| **`admin/brands/`** | `index.php`, `create.php`, `edit.php`, `delete.php` | Brand registry: brand names, logos, descriptions, status. |
| **`admin/orders/`** | `index.php`, `view.php`, `invoice.php`, `update-status.php` | Order processing: status workflow (Pending &rarr; Processing &rarr; Shipped &rarr; Delivered &rarr; Cancelled), payment verification, itemized invoice printing, delivery assign. |
| **`admin/inventory/`** | `index.php`, `adjust.php`, `logs.php` | ERP stock tracking: inventory valuation, stock movement history, audit trails, low-stock warnings. |
| **`admin/stock-adjustment/`** | `index.php`, `create.php` | Manual stock adjustments: surplus additions, physical audit corrections, reason logging. |
| **`admin/damaged-products/`** | `index.php`, `create.php` | Damaged/spoiled goods write-offs: records lost stock, cost impact, disposal reasons. |
| **`admin/expiry-products/`** | `index.php`, `create.php` | Perishable grocery batch expiry tracking and alerts. |
| **`admin/suppliers/`** | `index.php`, `create.php`, `edit.php`, `payments.php` | Vendor registry: supplier company info, balance tracking, purchase history, vendor payments. |
| **`admin/purchases/`** | `index.php`, `create.php`, `view.php` | Purchase Orders (PO): procurement of goods, cost tracking, stock auto-increment upon PO receipt. |
| **`admin/customers/`** | `index.php`, `view.php`, `edit.php`, `delete.php` | Customer directory: registered users, lifetime spend, order count, address records, account status. |
| **`admin/delivery/`** | `index.php`, `create.php`, `edit.php`, `assign.php` | Delivery rider fleet management: rider profiles, active delivery assignments, COD cash collection. |
| **`admin/finance/` & `expenses/`**| `index.php`, `expenses/index.php`, `daily-closing.php` | Retail accounting: expense logging by category, daily cash closing, revenue vs expense reconciliation. |
| **`admin/reports/`** | `sales.php`, `inventory.php`, `expenses.php`, `customers.php` | Analytics engine: sales summaries, top-selling products, profit margins, customer acquisition charts, CSV exports. |
| **`admin/reviews/`** | `index.php`, `approve.php`, `delete.php` | Customer review moderation: approve/reject review comments, ratings, and uploaded review images. |
| **`admin/coupons/`** | `index.php`, `create.php`, `edit.php` | Promotional coupons: percentage/fixed discounts, minimum purchase limits, expiration dates. |
| **`admin/flash-sales/`** | `index.php`, `create.php`, `edit.php` | Flash sale campaign manager: timed countdown discounts on selected products. |
| **`admin/banners/`** | `index.php`, `create.php`, `edit.php` | Storefront banner sliders: hero slides, promo cards, links, display priorities. |
| **`admin/admins/` & `roles/`** | `index.php`, `create.php`, `edit.php`, `roles/index.php` | Administrative RBAC: create admin staff, define custom roles, map granular permissions. |
| **`admin/settings/`** | `index.php`, `general.php`, `smtp.php`, `payment.php` | Store configuration: store profile, currency, VAT rate, delivery fee, SMTP credentials, payment keys. |
| **`admin/license/`** | `index.php` | Software license monitoring: live cryptographic validity, domain binding, node activation quota, manual re-verification. |
| **`admin/backup/`** | `index.php`, `create.php`, `download.php` | Database backup manager: on-demand SQL dump generation and download. |

---

### D. Licensing Authority Server (`licensing_server/`)
* **`licensing_server/license_server.php`:** Authoritative licensing engine implementing RSA-2048 cryptographic signing, domain/machine ID binding, license issuance, verification, and revocation.
* **`licensing_server/api.php`:** JSON REST API endpoint receiving client verification handshakes.
* **`licensing_server/cli_license_tool.php`:** Platform owner CLI tool for generating, inspecting, and revoking license keys.
* **`licensing_server/data/`:** Protected storage for private signing keys and SQLite license authority database.

---

### E. Storage, Logs & Uploads (`storage/` & `public/uploads/`)
* **`storage/logs/app.log`:** Runtime application error and security log.
* **`storage/backups/`:** Generated SQL database backups.
* **`public/uploads/products/`:** Uploaded product thumbnails and gallery images.
* **`public/uploads/categories/`:** Category icon and banner images.
* **`public/uploads/brands/`:** Brand logo images.
* **`public/uploads/users/`:** Customer avatar uploads.
* **`public/uploads/reviews/`:** Customer review image attachments.
* **`public/uploads/banners/`:** Homepage promotional banners.
