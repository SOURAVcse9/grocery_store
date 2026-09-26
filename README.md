# 🛒 GroCo — Modern Grocery E-Commerce, Retail ERP & POS Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-00618A?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Frontend-Vanilla%20JS%20%2F%20CSS3%20Tokens-F7DF1E?style=flat&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![Security Hardened](https://img.shields.io/badge/Security-Production%20Hardened-1a9d55?style=flat&logo=shield&logoColor=white)](docs/SECURITY_AUDIT_FINAL.md)
[![License Security](https://img.shields.io/badge/Licensing-RSA--2048%20Signed-1a9d55?style=flat&logo=lock&logoColor=white)](docs/LICENSING_SYSTEM.md)
[![SEO Ready](https://img.shields.io/badge/SEO-JSON--LD%20%2B%20Sitemap-orange?style=flat&logo=google)](docs/SEO_SETUP.md)
[![PWA Ready](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=flat&logo=pwa&logoColor=white)]()

**GroCo** is a production-hardened, enterprise-grade Grocery E-Commerce Storefront seamlessly integrated with a back-office Retail ERP, Point-of-Sale (POS) terminal, multi-warehouse inventory manager, double-entry financial ledger, dual-tier authentication system, and cryptographic licensing subsystem.

Engineered with clean PHP, vanilla JavaScript, modern CSS Design Tokens, and pure MySQL PDO transactions, GroCo is built for extreme performance, zero framework overhead, penetration resistance, and complete responsiveness across mobile, tablet, and desktop screens.

---

## 📑 Table of Contents

- [System Architecture](#-system-architecture)
- [Key Modules & Capabilities](#-key-modules--capabilities)
  - [1. Customer Storefront & Catalog](#1-customer-storefront--catalog)
  - [2. Dual Authentication & Identity Isolation](#2-dual-authentication--identity-isolation)
  - [3. SEO Infrastructure & Rich Snippets](#3-seo-infrastructure--rich-snippets)
  - [4. Admin ERP & Back-Office](#4-admin-erp--back-office)
  - [5. Point-of-Sale (POS) Terminal](#5-point-of-sale-pos-terminal)
- [Security Hardening & Penetration Defense](#-security-hardening--penetration-defense)
- [Software Licensing & Protection](#-software-licensing--protection)
- [Project Directory Structure](#-project-directory-structure)
- [Prerequisites](#-prerequisites)
- [Installation & Local Setup](#-installation--local-setup)
- [Database Setup & Migrations](#-database-setup--migrations)
- [License Administration via CLI](#-license-administration-via-cli)
- [Automated Verification & Test Suites](#-automated-verification--test-suites)
- [Production Deployment Guidelines](#-production-deployment-guidelines)
- [Documentation & Audit Reports](#-documentation--audit-reports)
- [License](#-license)

---

## 🏗️ System Architecture

```text
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                               GROCO PLATFORM ECOSYSTEM                                  │
└─────────────────────────────────────────────────────────────────────────────────────────┘
                                             │
             ┌───────────────────────────────┴───────────────────────────────┐
             ▼                                                               ▼
┌────────────────────────────────────────┐                      ┌─────────────────────────┐
│          PUBLIC STOREFRONT             │                      │   ADMIN PORTAL & ERP    │
│     (Customer-Facing E-Commerce)       │                      │ (Back-Office Operations)│
├────────────────────────────────────────┤                      ├─────────────────────────┤
│ • Responsive Product Catalog & Filters │                      │ • Real-Time KPI Visuals │
│ • Verified Delivered Customer Reviews  │                      │ • Catalog & Tree Manager│
│ • Cross-Sells (FBT / Related Products) │                      │ • Touch POS Terminal    │
│ • Cart, Dynamic Coupons & Zero-VAT     │                      │ • Inventory & Expiries  │
│ • Multi-Address Instant Checkout       │                      │ • Order Fulfillment     │
│ • Dual-Tier Customer & Google OAuth    │                      │ • Double-Entry Ledger   │
│ • JSON-LD Schema & Dynamic Sitemap     │                      │ • Granular RBAC Matrix  │
│ • PWA (Offline Fallback & Service Wkr) │                      │ • Software License Hub  │
└────────────────────────────────────────┘                      └─────────────────────────┘
             │                                                               │
             └───────────────────────────────┬───────────────────────────────┘
                                             ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              CENTRAL LICENSING GATEKEEPER                               │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│ • `enforce_license()`: Blocks unactivated installations across all environments         │
│ • RSA-2048 Asymmetric Signature Verification against official public key               │
│ • Distinct Domain Constraints: Local Development vs. Authorized Production Host        │
│ • Immediate Remote Verification on Incoming Requests with 7-Day Outage Grace Period     │
└─────────────────────────────────────────────────────────────────────────────────────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                        SECURITY MIDDLEWARE & DEFENSE LAYER                              │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│ • Upload Script Execution Blocking (`Options -Indexes -ExecCGI` & Engine Off)           │
│ • Zero Information Disclosure / Masked Exception Interceptor                            │
│ • 100% Parameterized PDO Statements (Zero SQL Injection)                                │
│ • Comprehensive `htmlspecialchars()` HTML Output Encoding                               │
│ • Strict IDOR Multi-Tenant Checks (`WHERE id = ? AND user_id = ?`)                      │
│ • CSRF Protection Tokens (`csrf.php`) & HTTP Security Headers                           │
│ • Session Hijacking Fingerprint Guard & IP Rate Limiting                                │
└─────────────────────────────────────────────────────────────────────────────────────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                        PERSISTENCE & STORAGE INFRASTRUCTURE                             │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│ • MySQL / MariaDB (InnoDB, UTF8MB4, Strict Foreign Keys, ACID Transactions)             │
│ • Protected Directory Access (`storage/.htaccess`, `database/.htaccess`)                │
│ • `storage/uploads/` (User Avatars, Review Photos, Product Catalogs, Banners)           │
│ • `storage/logs/` (Application Exception Logs & Security Audit Trail)                    │
│ • `storage/backups/` (Automated SQL Dump Snapshots)                                     │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## ✨ Key Modules & Capabilities

### 1. Customer Storefront & Catalog
- **Dynamic Homepage**: Hero banner slider, category showcase grid, flash sales with countdown timers, trending products, and newsletter subscriptions.
- **Advanced Catalog & Filters**: Instant faceted filtering by category, brand, price range, in-stock status, and customer ratings. Sort by newest, price (low/high), and popularity.
- **Product Detail Experience**:
  - High-resolution gallery with interactive thumbnail selector.
  - Live stock badges with low-inventory warnings.
  - **Cross-Selling**: *Frequently Bought Together (FBT)* bundle purchases with dynamic package discounts and *Related Products* algorithm.
- **Verified Customer Reviews**:
  - **Delivered Order Guard**: Only authenticated customers with confirmed **Delivered** order status can review a product.
  - Multi-image photo upload support with MIME/dimension validation.
  - **Custom Lightbox Modal**: High-performance inline modal with keyboard navigation (`Esc`/`Arrows`), zoom preview, and zero browser redirects.
- **Shopping Cart & Checkout**:
  - Slide-out mini-cart drawer and dedicated cart page.
  - Dynamic coupon engine supporting flat-rate and percentage discounts.
  - **Zero-VAT Compliance**: Strictly enforces 0.00% VAT across cart calculations, subtotals, invoices, and payment summaries.
  - Multi-address book (Home, Office, Other) with inline creation modal.
- **Customer Account Portal**:
  - Interactive order timeline tracking (Pending $\rightarrow$ Processing $\rightarrow$ Shipped $\rightarrow$ Delivered).
  - In-app notification center with read/unread indicators.
  - Product comparison matrix and wishlist.

### 2. Dual Authentication & Identity Isolation
- **Unified Identity Model**: Email+Password and Google OAuth seamlessly bind to a single permanent `users.id` with zero duplicate customer accounts.
- **Safe Account Linking**: Existing password accounts safely link Google profiles upon verified email match.
- **Google-First Password Creation**: Google-registered users can set a local password anytime without breaking OAuth.
- **Multi-Device Session Invalidation**: Single-click "Sign out all devices" increments `session_version` in MySQL to invalidate stale sessions across all other active browsers.
- **Admin Email Isolation**: Customer registration, Google OAuth, and profile updates strictly reject claiming administrator email addresses.

### 3. SEO Infrastructure & Rich Snippets
- **Semantic Meta Tags**: Dynamic canonical URLs, Open Graph (OG), and Twitter Card meta tags across all pages.
- **JSON-LD Structured Data**:
  - `Organization` & `WebSite` with `SearchAction` sitelinks schema on homepage.
  - Full schema.org `Product` markup with offers, price currency, SKU, brand, and aggregate ratings.
  - `BreadcrumbList` on catalog and detail pages.
- **Automated Sitemaps & Robots**:
  - Auto-generated XML sitemap ([`public/sitemap.xml`](public/sitemap.xml)) indexing all active products, categories, and static pages.
  - Search engine crawler policy in [`public/robots.txt`](public/robots.txt) allowing storefront indexing while blocking admin/API paths.

### 4. Admin ERP & Back-Office
- **Executive Dashboard**: Visual sales analytics charts powered by Chart.js, revenue metrics, order velocity KPIs, top-selling items, and low-inventory alerts.
- **Catalog Management**: Full CRUD for Products (with multi-image galleries, SKU, barcodes, tags), hierarchical Categories, and Brands.
- **Warehouse & Inventory Control**: Manual stock adjustments with reason tracking, inter-branch stock transfers, damaged product logging, and perishable product expiry-date monitoring.
- **Order Fulfillment & Logistics**: Driver assignment, thermal/A4 printable invoice generation, and status dispatch.
- **Financial Accounting**: Double-entry ledger tracking transactions across Cash, Card, and Mobile Banking (bKash/Nagad), operational expense logs, and profit/loss reports.
- **Granular RBAC**: Role-Based Access Control matrix (Superadmin, Store Manager, Cashier, Delivery Staff) with strict permission middleware on every route.

### 5. Point-of-Sale (POS) Terminal
- **Touchscreen Optimized**: Fast product catalog grid with barcode / SKU scanner input.
- **Split Payments**: Flexible checkout supporting combinations of Cash, Credit/Debit Card, and Mobile Banking in a single transaction.
- **Register & Shift Management**: Cashier shift open/close tracking, initial float recording, and Cash-In / Cash-Out drawer reconciliations.
- **Thermal Receipts**: Automated printable 80mm and 58mm POS receipt generation.

---

## 🔒 Security Hardening & Penetration Defense

GroCo implements a defense-in-depth security architecture verified against automated penetration tests:

1. **Upload Execution Prevention**:
   - `public/uploads/.htaccess` enforces `Options -Indexes -ExecCGI`, `php_flag engine off`, and blocks script execution (`.php`, `.phtml`, `.phar`, `.cgi`, `.sh`, `.exe`, etc.).
2. **Directory Access Restrictions**:
   - `storage/`, `database/`, and `licensing_server/data/` deny direct web requests via Apache `Require all denied`.
3. **Cross-Site Scripting (XSS) Remediation**:
   - 100% of admin template output streams and dynamic error variables are sanitized with `htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8')`.
4. **SQL Injection (SQLi) Elimination**:
   - All database operations use PDO prepared statements with parameter binding; raw query concatenation is eliminated.
5. **Path Traversal Guards**:
   - Disk file deletion and file upload routines enforce `basename()` sanitization to block directory traversal attacks (`../../`).
6. **Information Disclosure Prevention**:
   - Raw database exceptions (`die($e->getMessage())`) have been removed from all public controllers in favor of graceful user error views and server-side logging.
7. **Cross-Site Request Forgery (CSRF)**:
   - 64-hex CSRF tokens are bound to active user sessions and verified on all state-changing `POST`/`PUT`/`DELETE` requests.
8. **Strict IDOR Defenses**:
   - All order, address, cart, and review endpoints enforce strict customer ownership constraints (`WHERE id = ? AND user_id = ?`).

---

## 🛡️ Software Licensing & Protection

GroCo includes an enterprise-grade cryptographic licensing subsystem documented in [`docs/LICENSING_SYSTEM.md`](docs/LICENSING_SYSTEM.md).

- **Public Repository Security Model**: Anyone can clone the repository, but **nobody can run the application without an active signed license**.
- **No Localhost Bypass**: Development environments require an active `development` tier license.
- **RSA-2048 Digital Signatures**: Every incoming web request verifies the digital signature of the license against the embedded public key.
- **7-Day Outage Grace Period**: In the event of network connectivity loss with the remote licensing authority, active installations operate smoothly without disruption for 7 days.

---

## 📂 Project Directory Structure

```text
grocery-store/
├── admin/                           # Admin ERP, POS & Store Management Portal (44+ modules)
│   ├── index.php                    # Admin dashboard overview & analytics
│   ├── login.php / logout.php       # Admin authentication & RBAC guards
│   ├── products/                    # Product catalog management (CRUD, images, stock)
│   ├── categories/                  # Category tree management (parent/child hierarchy)
│   ├── brands/                      # Brand directory
│   ├── orders/                      # Order fulfillment & status updates
│   ├── pos/                         # Point-of-Sale terminal, shift logs, receipts
│   ├── inventory/                   # Stock adjustments, transfers, damaged goods, expiries
│   ├── finance/ & expenses/         # Financial ledger & operational expenses
│   ├── delivery/                    # Delivery staff assignments & dispatch
│   ├── reviews/                     # Review moderation & image lightbox
│   ├── coupons/ & flash-sales/      # Promotion & discount engines
│   ├── customers/ & admins/         # User directory & RBAC staff accounts
│   ├── backup/                      # Database backup & restore terminal
│   └── layouts/                     # Admin shell (topbar, sidebar, header, footer)
│
├── public/                          # Customer-Facing Storefront & Application Core
│   ├── index.php                    # Storefront homepage
│   ├── header.php / footer.php      # Storefront navigation, theme toggler & footer
│   ├── dbconnect.php                # Core PDO bootstrap, session init, license gatekeeper
│   ├── csrf.php                     # CSRF token generator & middleware
│   ├── activate.php                 # Software license activation screen
│   ├── license_status.php           # License status & diagnostic screen
│   ├── products.php / product.php   # Catalog, product details, reviews, FBT cross-sells
│   ├── cart.php / checkout.php      # Shopping cart & multi-address checkout
│   ├── account.php / orders.php     # Customer portal, order timeline, invoices
│   ├── includes/                    # Core libraries (auth, google_auth, mailer, license, seo)
│   ├── uploads/                     # Public user media (protected by .htaccess)
│   ├── sitemap.xml / robots.txt     # Search engine indexation & crawler policies
│   └── sw.js / manifest.json        # PWA Service Worker & manifest
│
├── licensing_server/                # Authoritative Licensing Authority (Excluded Secrets)
│   ├── license_server.php           # RSA-2048 signing engine & SQLite license authority
│   ├── api.php                      # Authoritative REST verification API endpoint
│   ├── cli_license_tool.php         # Administrative CLI tool for issuing & revoking keys
│   └── data/                        # [GIT-IGNORED] Master private key & authority SQLite DB
│
├── database/                        # Database Schemas & Migrations (Protected by .htaccess)
│   ├── pos_erp_migrations.sql       # DDL migrations for ERP, POS, and financial ledger
│   ├── license_migrations.sql       # DDL migrations for system_license & audit logs
│   └── .htaccess                    # Access protection blocking direct SQL downloads
│
├── storage/                         # Runtime Storage (Protected by .htaccess)
│   ├── logs/                        # Application logs (`app.log`)
│   ├── cache/                       # Fragment caching
│   └── backups/                     # Generated SQL backup dumps
│
├── docs/                            # Architecture & Technical Documentation
│   ├── SECURITY_AUDIT_BEFORE.md     # Pre-hardening vulnerability audit catalog
│   ├── SECURITY_AUDIT_FINAL.md      # Final production security hardening report
│   ├── LICENSING_SYSTEM.md          # Cryptographic licensing architecture guide
│   └── SEO_SETUP.md                 # Search engine optimization setup guide
│
├── tests/                           # Automated Test Suites
│   ├── security_hardening_penetration_test.php # Complete Security & Penetration Verification
│   ├── production_dual_auth_test.php          # 22-Scenario Dual Auth & Account Linking Suite
│   ├── authentication_security_test.php       # 29-Scenario RBAC & Auth Security Audit
│   ├── production_smtp_password_reset_test.php # 22-Scenario Password Reset & SMTP Suite
│   ├── admin_email_customer_registration_block_test.php # 6-Scenario Admin Email Guard Suite
│   ├── licensing_security_hardening_test.php  # 32-Scenario Attack Matrix & Security Audit
│   └── public_seo_audit_test.php              # Automated SEO & Schema Verification
│
├── CHANGELOG_SECURITY.md            # Comprehensive Security Audit Changelog
├── .env.example                     # Safe environment configuration template
├── .gitignore                       # Production-safe Git exclusion rules
└── .htaccess                        # Root Apache rewrite rules & security headers
```

---

## 📦 Prerequisites

- **Web Server**: Apache 2.4+ (with `mod_rewrite` and `mod_headers` enabled)
- **PHP**: PHP 8.1 or PHP 8.2+
  - Required Extensions: `pdo_mysql`, `pdo_sqlite` (for licensing authority), `openssl`, `mbstring`, `fileinfo`, `gd` (or `imagick`), `json`, `curl`, `session`
- **Database**: MySQL 5.7+ or MariaDB 10.4+
- **OpenSSL**: OpenSSL CLI or PHP OpenSSL extension configured
- **Browser Compatibility**: Chrome, Edge, Firefox, Safari, Opera (Desktop & Mobile)

---

## 🚀 Installation & Local Setup

### Step 1: Clone the Repository
```bash
git clone https://github.com/SOURAVcse9/grocery_store.git
cd grocery_store
```

### Step 2: Configure Web Server
Place the project inside your web server document root:
- **XAMPP (Windows)**: `C:\xampp\htdocs\grocery-store`
- **Linux (Apache)**: `/var/www/html/grocery-store`

Ensure Apache has `AllowOverride All` enabled for the project directory.

### Step 3: Configure Environment Variables
Copy the example environment file:
```bash
cp .env.example .env
```
Configure your database settings inside `.env`:
```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/grocery-store

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=grocery_store
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

---

## 🗄️ Database Setup & Migrations

1. Create the MySQL database:
   ```sql
   CREATE DATABASE grocery_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the ERP/POS and licensing schemas:
   ```bash
   mysql -u root -p grocery_store < database/pos_erp_migrations.sql
   mysql -u root -p grocery_store < database/license_migrations.sql
   ```

---

## 🔑 License Administration via CLI

### 1. Generating a Development License
```bash
php licensing_server/cli_license_tool.php create --customer="Dev Team" --email="dev@groco.com" --type=development --limit=3
```

### 2. Activating the License
Open `http://localhost/grocery-store/public/` in your browser. Enter the generated license key on the **License Activation Screen** (`public/activate.php`).

### 3. Generating a Production License
```bash
php licensing_server/cli_license_tool.php create --customer="Commercial Client" --email="billing@client.com" --domains="shop.groco.com" --type=production --limit=1 --expires="2027-12-31"
```

---

## 🧪 Automated Verification & Test Suites

GroCo includes an exhaustive automated testing suite:

| Test Script | Location | Assertions | Purpose |
| :--- | :--- | :--- | :--- |
| **Security Hardening & Penetration** | `tests/security_hardening_penetration_test.php` | **13 Tests** | Verifies script blocking in uploads, .htaccess locks, XSS escaping, path traversal, CSRF, and admin email guard. |
| **Production Dual Auth & Linking** | `tests/production_dual_auth_test.php` | **22 Tests** | Verifies Email+Password + Google OAuth account mapping to single permanent `users.id`, cart merge, and IDOR protection. |
| **Authentication & RBAC Security** | `tests/authentication_security_test.php` | **29 Tests** | Audits Super Admin OTP generation, forced password update, Google OAuth CSRF tokens, and Admin/Customer separation. |
| **Production SMTP & Password Reset** | `tests/production_smtp_password_reset_test.php` | **22 Tests** | Validates SHA-256 password resets, 1-hour expiration, and Google account password creation. |
| **Admin Email Customer Signup Block** | `tests/admin_email_customer_registration_block_test.php` | **6 Tests** | Enforces policy that administrator emails cannot register or create customer accounts. |
| **Licensing Security Hardening Audit** | `tests/licensing_security_hardening_test.php` | **32 Tests** | 32-scenario attack matrix testing clone blocking, tampering, expiry, outage, and renewal flows. |
| **Public Storefront SEO Audit** | `tests/public_seo_audit_test.php` | **Comprehensive** | Validates meta tags, OpenGraph, JSON-LD schemas, sitemaps, and robots.txt. |

### Running the Test Suites:
```bash
# Run Security Hardening & Penetration Verification
php tests/security_hardening_penetration_test.php

# Run Production Dual Auth & Account Linking Suite
php tests/production_dual_auth_test.php

# Run Authentication & RBAC Security Suite
php tests/authentication_security_test.php

# Run Public SEO & Rich Snippets Audit
php tests/public_seo_audit_test.php
```

---

## 🚢 Production Deployment Guidelines

1. **Set Environment to Production**:
   In `.env`:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   LICENSE_SERVER_URL=https://license.yourdomain.com/api.php
   LICENSE_GRACE_PERIOD_DAYS=7
   ```
2. **Enforce HTTPS**:
   Configure SSL/TLS certificates and uncomment the HTTPS rewrite rules in `.htaccess`.
3. **Verify Git Exclusions**:
   Confirm `.env`, private keys (`*.pem`, `*.key`), and user uploads are NOT tracked in git.
4. **Activate Production License**:
   Issue a production license for your domain and activate it on `public/activate.php`.
5. **Optimize PHP Configuration (`php.ini`)**:
   ```ini
   display_errors = Off
   log_errors = On
   session.cookie_httponly = 1
   session.cookie_secure = 1
   session.use_strict_mode = 1
   ```

---

## 📑 Documentation & Audit Reports

- 📄 **[Final Security Hardening Report](docs/SECURITY_AUDIT_FINAL.md)**: Full remediation matrix, defense-in-depth architecture, and verification results.
- 📄 **[Pre-Hardening Security Audit](docs/SECURITY_AUDIT_BEFORE.md)**: Initial vulnerability catalog and risk classifications.
- 📄 **[Security Changelog](CHANGELOG_SECURITY.md)**: Comprehensive log of all security patches and file modifications.
- 📄 **[Licensing System Guide](docs/LICENSING_SYSTEM.md)**: Detailed architectural guide for the RSA-2048 licensing engine.
- 📄 **[SEO Setup Guide](docs/SEO_SETUP.md)**: Public storefront search engine optimization guide.

---

## 📄 License

This software is distributed under commercial terms with cryptographic installation authorization. All rights reserved.
For licensing inquiries, contact your GroCo software provider or visit [docs/LICENSING_SYSTEM.md](docs/LICENSING_SYSTEM.md).
