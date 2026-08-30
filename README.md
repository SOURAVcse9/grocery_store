# 🛒 GroCo — Modern Grocery E-Commerce & Retail ERP Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-00618A?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Frontend-Vanilla%20JS%20%2F%20CSS3%20Tokens-F7DF1E?style=flat&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![License Security](https://img.shields.io/badge/Licensing-RSA--2048%20Signed-1a9d55?style=flat&logo=shield&logoColor=white)](docs/LICENSING_SYSTEM.md)
[![PWA Ready](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=flat&logo=pwa&logoColor=white)]()
[![Repository Model](https://img.shields.io/badge/GitHub-Public%20Repository-blue.svg?style=flat)]()

**GroCo** is a full-featured, enterprise-grade Grocery E-Commerce Storefront seamlessly integrated with a back-office Retail ERP, Point-of-Sale (POS) terminal, multi-warehouse inventory manager, double-entry financial ledger, and customer relationship system.

Engineered with clean PHP, vanilla JavaScript, modern CSS Design Tokens, and pure MySQL PDO transactions, GroCo is built for extreme performance, rock-solid security, zero framework bloat, and 100% responsiveness across all mobile, tablet, and desktop viewports.

---

## 📑 Table of Contents

- [System Architecture](#-system-architecture)
- [Key Features](#-key-features)
  - [1. Customer Storefront](#1-customer-storefront)
  - [2. Admin ERP & Back-Office](#2-admin-erp--back-office)
  - [3. Point-of-Sale (POS) Terminal](#3-point-of-sale-pos-terminal)
  - [4. Core Platform Engines](#4-core-platform-engines)
- [Software Licensing & Installation Protection](#-software-licensing--installation-protection)
  - [Public GitHub Repository Security Model](#public-github-repository-security-model)
  - [License Tiers: Development vs. Production](#license-tiers-development-vs-production)
  - [Immediate Remote Verification & RSA-2048 Cryptography](#immediate-remote-verification--rsa-2048-cryptography)
  - [Commercial 1-Year Subscriptions & Seamless Renewal](#commercial-1-year-subscriptions--seamless-renewal)
- [Project Directory Structure](#-project-directory-structure)
- [Security & Compliance](#-security--compliance)
- [Prerequisites](#-prerequisites)
- [Installation & Local Setup](#-installation--local-setup)
- [Database Setup & Migrations](#-database-setup--migrations)
- [License Administration via CLI](#-license-administration-via-cli)
- [Automated Verification & Test Suites](#-automated-verification--test-suites)
- [Production Deployment Guidelines](#-production-deployment-guidelines)
- [Contributing & Code Standards](#-contributing--code-standards)
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
│ • Responsive Product Catalog & Filters │                      │ • Real-Time KPI Dashboards
│ • Verified Delivered Customer Reviews  │                      │ • Catalog & Tree Manager│
│ • Cross-Sells (FBT / Related Products) │                      │ • Touch POS Terminal    │
│ • Cart, Dynamic Coupons & Zero-VAT     │                      │ • Inventory & Expiries  │
│ • Multi-Address Instant Checkout       │                      │ • Order Fulfillment     │
│ • Customer Orders & In-App Alerts      │                      │ • Double-Entry Ledger   │
│ • Dual Theme Engine (Dark / Light)     │                      │ • RBAC Role Permissions │
│ • PWA (Offline Fallback & Service Wkr) │                      │ • Software License Panel│
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
│ • Immediate Remote Verification on Incoming Web Requests with 7-Day Outage Grace Period │
└─────────────────────────────────────────────────────────────────────────────────────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              CORE BOOTSTRAP & SERVICES                                  │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│ • PDO Singleton Database Connection Layer (`Database::getConnection()`)                 │
│ • CSRF Protection Tokens (`csrf.php`) & Comprehensive HTTP Security Headers             │
│ • Session Hijacking Fingerprint Guard & IP Rate Limiting (`rate_limit.php`)              │
│ • Custom Error & Exception Interceptor (`error_handler.php`)                            │
│ • Image MIME & Storage Normalizer (`image.php`)                                         │
└─────────────────────────────────────────────────────────────────────────────────────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                        PERSISTENCE & STORAGE INFRASTRUCTURE                             │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│ • MySQL / MariaDB (InnoDB, UTF8MB4, Strict Foreign Keys, ACID Transactions)             │
│ • `system_license` & `system_license_logs` (Cryptographic Activation Persistence)       │
│ • `storage/uploads/` (User Avatars, Review Photos, Product Catalogs, Banners)           │
│ • `storage/logs/` (Application Exception Logs & Security Audit Trail)                    │
│ • `storage/backups/` (Automated SQL Dump Snapshots)                                     │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## ✨ Key Features

### 1. Customer Storefront

- **Dynamic Homepage**: Hero banner carousel, category showcase grid, daily flash deals with countdown timers, top-rated products, and newsletter subscription forms.
- **Advanced Product Catalog**: Instant faceted filtering by category, brand, price range slider, in-stock availability, and customer star ratings. Sort by newest, price (low/high), and popularity.
- **Product Detail Experience**:
  - High-resolution gallery with thumbnail selector.
  - Live stock badges with low-inventory warnings.
  - **Cross-Selling & Recommendations**:
    - *Frequently Bought Together (FBT)*: Single-click bundle purchases with dynamic package discounts.
    - *Related Products*: Category-linked algorithm suggestions.
    - *Recently Viewed*: Client-side tracked browsing history.
- **Verified Customer Review System**:
  - **Delivered Order Guard**: Only authenticated customers with confirmed **Delivered** order status can review a product.
  - Multi-image photo upload support with MIME/dimension validation.
  - **Custom Lightbox Modal**: High-performance inline modal with keyboard navigation (`Esc`/`Arrows`), zoom preview, and zero browser tab redirects.
  - Review moderation pipeline (Pending $\rightarrow$ Approved/Rejected) with automatic rating recalculation.
- **Shopping Cart & Checkout**:
  - Real-time slide-out mini-cart drawer and dedicated cart page.
  - Dynamic coupon validation engine supporting flat-rate and percentage discounts.
  - **Zero-VAT Compliance**: Strictly enforces 0.00% VAT across cart calculations, order subtotals, invoices, and payment summaries.
  - Saved multi-address selector (Home, Office, Other) with inline creation modal.
- **Customer Account Portal**:
  - Comprehensive dashboard displaying order counts, wishlist items, and loyalty points.
  - Multi-address book management with default shipping/billing assignment.
  - Interactive order timeline tracking (Pending $\rightarrow$ Processing $\rightarrow$ Shipped $\rightarrow$ Delivered).
  - In-app notification center with read/unread indicators.
  - Product comparison matrix and wishlist.

### 2. Admin ERP & Back-Office

- **Executive Dashboard**: Visual sales analytics charts powered by Chart.js, revenue metrics, order velocity KPIs, top-selling items, and low-inventory alerts.
- **Catalog Management**: Complete CRUD for Products (with multi-image galleries, SKU, barcodes, tags), hierarchical Categories (parent-child trees), and Brands.
- **Warehouse & Inventory Control**:
  - Manual stock adjustments with reason tracking.
  - Inter-branch and warehouse stock transfers.
  - Damaged product logging.
  - Perishable product expiry-date monitoring.
- **Order Fulfillment & Logistics**:
  - Lifecycle management: Update statuses, generate printable thermal/A4 invoices, process cancellations, and automatic stock restoration.
  - Delivery dispatch: Driver assignment, route management, and status tracking.
- **Financial Accounting**: Double-entry ledger tracking transactions across Cash, Card, and Mobile Banking (bKash/Nagad), operational expense logs, and profit/loss reports.
- **Security & RBAC**: Role-Based Access Control matrix (Superadmin, Store Manager, Cashier, Delivery Staff) with granular permission checks on every route.
- **Software License Panel**: In-dashboard license overview, license key masking, domain binding status, manual re-verification, and node deactivation.
- **Data Export & Backups**: One-click SQL database snapshot generation, restore terminal, and CSV report exports (sales, ledger, inventory, customers).

### 3. Point-of-Sale (POS) Terminal

- **Touchscreen Optimized**: Fast product catalog grid with barcode / SKU scanner input.
- **Split Payments**: Flexible checkout supporting combinations of Cash, Credit/Debit Card, and Mobile Banking in a single transaction.
- **Register & Shift Management**: Cashier shift open/close tracking, initial float recording, and Cash-In / Cash-Out drawer reconciliations.
- **Thermal Receipts**: Automated printable 80mm and 58mm POS receipt generation.
- **Walk-in & Member Customers**: Quick customer creation, wallet balance deductions, and loyalty point rewards directly at the register.

### 4. Core Platform Engines

- **Dual Theme System**: Flawless Dark and Light mode powered by CSS variables (`--color-surface`, `--color-text`, `--color-primary`, etc.) with `color-scheme: dark` form controls, custom native select dropdown styling, and persistent local storage.
- **Accessible Custom Selects**: Progressive-enhancement select component with full WAI-ARIA combobox semantics and complete keyboard accessibility (`ArrowUp`, `ArrowDown`, `Enter`, `Escape`, `Tab`).
- **Responsive Layout Matrix**: Validated across 13 distinct responsive breakpoints ($320\text{px} \rightarrow 1440\text{px}+$); zero horizontal overflow and fluid auto-fit grids.
- **Progressive Web App (PWA)**: Includes Service Worker (`sw.js`) for background caching, offline fallback screens, and mobile Add-to-Home-Screen integration.

---

## 🛡️ Software Licensing & Installation Protection

GroCo includes an enterprise-grade cryptographic licensing subsystem documented in detail in [`docs/LICENSING_SYSTEM.md`](docs/LICENSING_SYSTEM.md).

### Public GitHub Repository Security Model

The GroCo repository is designed to remain **Public on GitHub**. Anyone may clone the repository, but **nobody can run or use the application without authorization from your licensing authority**.

- **No Free Localhost Bypass**: Development environments (`localhost`, `127.0.0.1`, `*.test`) do NOT bypass licensing. Every installation requires an active, signed license record.
- **No Client-Side Bypass Flags**: There are no hidden parameters (`?dev=true`) or environment variables (`LICENSE_DISABLED`, `SKIP_LICENSE`) to disable licensing.
- **Non-Destructive Enforcement**: Inactive installations are cleanly paused and redirected to an activation or status screen. Customer orders, database tables, and user files are **never deleted or modified**.

### License Tiers: Development vs. Production

The licensing engine enforces two strict license tiers:

| Attribute | Development License | Production License |
| :--- | :--- | :--- |
| **`license_type`** | `development` | `production` |
| **Authorized Domains** | `localhost`, `127.0.0.1`, `::1`, `*.test`, `*.local` | Designated production hostnames (e.g. `shop.example.com`) |
| **Attempt on Localhost** | Allowed | **Blocked** (`PROD_LICENSE_ON_LOCALHOST`) |
| **Attempt on Public Web** | **Blocked** (`DEV_LICENSE_ON_PRODUCTION`) | Allowed on bound domain |
| **Intended User** | Internal core developers, evaluators | Commercial clients, live deployments |

### Immediate Remote Verification & RSA-2048 Cryptography

1. **Authoritative Signing Authority**: The licensing server holds a 2048-bit RSA **private signing key** (`licensing_server/data/license_private.pem`). This key is strictly excluded by `.gitignore` and **never committed to GitHub**.
2. **Public Key Verification**: The GroCo application embeds only the matching RSA **public verification key**.
3. **Immediate Remote Verification**: Every normal incoming web request reaching `enforce_license()` performs live cryptographic verification against the central authority. When an administrator revokes a license, the **very next request** to the application is blocked.
4. **Tamper Containment**: Any attempt to modify the stored license status (e.g., manually changing `status` to `active` or altering `expires_at` in MySQL) invalidates the signature and immediately pauses application execution.
5. **Outage Resilience**: If your remote licensing server experiences temporary network downtime, active installations continue operating without interruption under a **7-day grace period**.

### Commercial 1-Year Subscriptions & Seamless Renewal

- When a 1-year subscription expires, the remote authority transitions the license status to `EXPIRED`.
- The merchant renews their subscription with the software provider.
- The administrator updates the license expiry via the CLI tool:
  ```bash
  php licensing_server/cli_license_tool.php renew GRCO-XXXX-XXXX-XXXX-XXXX --days=365
  ```
- On the next web request, the store automatically fetches the updated signed payload and restores full operation with **zero downtime, zero code changes, and no reinstallation**.

---

## 📂 Project Directory Structure

```text
grocery-store/
├── admin/                           # Admin ERP, POS & Store Management Portal (44+ modules)
│   ├── index.php                    # Admin dashboard overview & analytics
│   ├── login.php / logout.php       # Admin authentication & 2FA guards
│   ├── products/                    # Product catalog management (CRUD, images, stock)
│   ├── categories/                  # Category tree management (parent/child hierarchy)
│   ├── brands/                      # Brand directory
│   ├── orders/                      # Order fulfillment & status updates
│   ├── pos/                         # Point-of-Sale terminal, shift logs, receipts
│   ├── inventory/                   # Stock adjustments, transfers, damaged goods, expiries
│   ├── finance/ & expenses/         # Financial ledger & operational expenses
│   ├── delivery/                    # Delivery boy assignments & dispatch
│   ├── reviews/                     # Review moderation & image lightbox
│   ├── coupons/ & flash-sales/      # Promotion & discount engines
│   ├── customers/ & admins/         # User directory & RBAC staff accounts
│   ├── license/                     # Admin software license management dashboard
│   ├── backup/                      # Database backup & restore terminal
│   ├── layouts/                     # Admin shell (topbar, sidebar, header, footer)
│   └── assets/                      # Admin stylesheets (`admin.css`) and POS scripts
│
├── public/                          # Customer-Facing Storefront & Application Core
│   ├── index.php                    # Storefront homepage
│   ├── header.php / footer.php      # Global storefront chrome, navigation & scripts
│   ├── dbconnect.php                # Core PDO bootstrap, session init, license gatekeeper
│   ├── csrf.php                     # CSRF token generator & middleware
│   ├── activate.php                 # Software license activation screen
│   ├── license_status.php           # License status & diagnostic screen
│   │
│   ├── catalog & checkout/
│   │   ├── products.php             # Filterable product catalog
│   │   ├── product.php              # Product detail, reviews, lightbox, FBT cross-sells
│   │   ├── cart.php / checkout.php  # Shopping cart & multi-address checkout
│   │   └── process_checkout.php     # Checkout database transaction processor
│   │
│   ├── customer account/
│   │   ├── account.php              # Customer dashboard
│   │   ├── orders.php / order-details.php # Order tracking & invoices
│   │   ├── addresses.php            # Multi-address management
│   │   └── reviews.php              # Customer review history
│   │
│   ├── components/                  # Reusable UI partials (cards, modal, hero, toasts)
│   ├── includes/                    # Business logic (auth, license engine, security)
│   │   └── license.php              # Client licensing engine & RSA signature verifier
│   ├── lang/                        # Internationalization (English `en.php`, Bengali `bn.php`)
│   ├── ajax/ & api/                 # Asynchronous endpoints (cart, search, wishlist)
│   ├── sw.js / manifest.json        # PWA Service Worker & manifest
│   └── assets/                      # Storefront CSS tokens, icons, and JavaScript
│
├── licensing_server/                # Authoritative Licensing Authority (Excluded Secrets)
│   ├── license_server.php           # RSA-2048 signing engine & SQLite license authority
│   ├── api.php                      # Authoritative REST verification API endpoint
│   ├── cli_license_tool.php         # Administrative CLI tool for issuing & revoking keys
│   └── data/                        # [GIT-IGNORED] Master private key & authority SQLite DB
│
├── database/                        # Database Schemas & Migrations
│   ├── pos_erp_migrations.sql       # DDL migrations for ERP, POS, and financial ledger
│   ├── license_migrations.sql       # DDL migrations for system_license & audit logs
│   └── .htaccess                    # Access protection blocking direct SQL downloads
│
├── storage/                         # Runtime Storage (Excluded from Git)
│   ├── uploads/                     # User uploads (products, reviews, avatars)
│   ├── logs/                        # Error logs (`app.log`)
│   ├── cache/                       # Cached fragments
│   ├── backups/                     # Generated SQL backup dumps
│   └── .htaccess                    # Script execution prevention in upload directories
│
├── docs/                            # Architecture & Technical Documentation
│   └── LICENSING_SYSTEM.md          # Comprehensive Licensing & Security Architecture Guide
│
├── tests/                           # Automated Test Suites
│   ├── licensing_system_test.php    # 28-Scenario Mandatory Licensing Hardening Audit
│   └── licensing_security_hardening_test.php # 32-Scenario Attack Matrix & Security Audit
│
├── admin_full_audit_test.php        # Admin portal automated regression test runner
├── admin_db_integrity_scan.php      # Database integrity & foreign-key scan runner
├── .env.example                     # Safe environment configuration template
├── .gitignore                       # Production-safe Git exclusion rules
└── .htaccess                        # Root Apache rewrite rules & security headers
```

---

## 🔒 Security & Compliance

GroCo incorporates multi-layered defense-in-depth security mechanisms:

1. **SQL Injection Prevention**: 100% prepared statements via PDO with emulation disabled (`PDO::ATTR_EMULATE_PREPARES => false`).
2. **Cross-Site Request Forgery (CSRF)**: Cryptographically secure 64-character tokens bound to user sessions, validated on all state-changing `POST`/`PUT`/`DELETE` requests (`csrf.php`).
3. **Cross-Site Scripting (XSS)**: Strict HTML entity escaping via `e()` helper function on all user-supplied inputs and outputs.
4. **Session Security**:
   - Cookie flags: `HttpOnly`, `SameSite=Lax`, and `Secure` (when HTTPS is detected).
   - User-Agent cryptographic fingerprint binding to invalidate hijacked sessions.
5. **Upload Protection**:
   - File extension whitelisting, MIME type verification with `finfo_file()`, and filename obfuscation.
   - Dedicated `.htaccess` files inside `storage/` and `public/uploads/` with `php_flag engine off` and `RemoveHandler` to block script execution.
6. **Rate Limiting**: IP-based rate limiting on sensitive routes (customer login, admin login, registration, contact inquiry forms) to prevent brute-force attacks.
7. **Production Error Handling**: Custom interceptor suppresses verbose stack traces from clients and writes structured events to `storage/logs/app.log`.
8. **Cryptographic Software Licensing**: Asymmetric RSA-2048 digital signatures verify installation authenticity on every request.

---

## 📦 Prerequisites

Before deploying or running GroCo locally, ensure your environment meets the following specifications:

- **Web Server**: Apache 2.4+ (with `mod_rewrite` and `mod_headers` enabled)
- **PHP**: PHP 8.1 or PHP 8.2+
  - Required Extensions: `pdo_mysql`, `pdo_sqlite` (for authority), `openssl`, `mbstring`, `fileinfo`, `gd` (or `imagick`), `json`, `curl`, `session`
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

Ensure Apache has `AllowOverride All` enabled for the project directory so `.htaccess` rules take effect.

### Step 3: Configure Environment Variables
Copy the example environment file:
```bash
cp .env.example .env
```
Open `.env` and configure your database and environment settings:
```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080/grocery-store

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=grocery_store
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

### Step 4: Verify Directory Permissions (Linux / macOS)
Ensure the web server user (`www-data` or `apache`) has read/write permissions to the `storage/` and `public/uploads/` folders:
```bash
chmod -R 775 storage public/uploads
chown -R www-data:www-data storage public/uploads
```

---

## 🗄️ Database Setup & Migrations

1. Start your MySQL/MariaDB service.
2. Create the database:
   ```sql
   CREATE DATABASE grocery_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the core ERP/POS schema and licensing schema:
   ```bash
   mysql -u root -p grocery_store < database/pos_erp_migrations.sql
   mysql -u root -p grocery_store < database/license_migrations.sql
   ```

---

## 🔑 License Administration via CLI

Because GroCo uses a mandatory licensing architecture, every installation requires an active license before it can be used.

### 1. Generating a Development License (For Local Development)
Run the administrative CLI tool to generate a Development license:
```bash
php licensing_server/cli_license_tool.php create --customer="Dev Team" --email="dev@groco.com" --type=development --limit=3
```
*Output:*
```text
=======================================================
 🎉 NEW LICENSE GENERATED SUCCESSFULLY
=======================================================
 License Key:        GRCO-XXXX-XXXX-XXXX-XXXX
 License Type:       DEVELOPMENT
 Customer Name:      Dev Team
 Customer Email:     dev@groco.com
 Allowed Domains:    localhost, 127.0.0.1, ::1, *.test, *.local
 Activation Limit:   3
 Expiration Date:    Perpetual (No expiry)
 Status:             ACTIVE
=======================================================
```

### 2. Activating the License
Open `http://localhost/grocery-store/public/` in your browser. You will be automatically presented with the **License Activation Screen** (`public/activate.php`). Enter:
- **License Key**: The generated `GRCO-XXXX-XXXX-XXXX-XXXX`
- **Email**: `dev@groco.com`
- Click **Activate GroCo License**.

The application cryptographically validates the token, binds the installation to your local loopback, and unlocks the complete storefront and admin portal.

### 3. Generating a Production License (For Commercial Deployments)
```bash
php licensing_server/cli_license_tool.php create --customer="Retail Client Ltd" --email="billing@retail.com" --domains="shop.retail.com" --type=production --limit=1 --expires="2027-08-30"
```

### 4. Renewing a Commercial Subscription
```bash
php licensing_server/cli_license_tool.php renew GRCO-XXXX-XXXX-XXXX-XXXX --days=365
```

### 5. Other License Management Commands
```bash
# List all registered licenses and activation counts
php licensing_server/cli_license_tool.php list

# Inspect a specific license
php licensing_server/cli_license_tool.php inspect GRCO-XXXX-XXXX-XXXX-XXXX

# Suspend a license
php licensing_server/cli_license_tool.php suspend GRCO-XXXX-XXXX-XXXX-XXXX --reason="Account under review"

# Revoke a license
php licensing_server/cli_license_tool.php revoke GRCO-XXXX-XXXX-XXXX-XXXX --reason="Payment dispute"

# Reactivate a suspended license
php licensing_server/cli_license_tool.php reactivate GRCO-XXXX-XXXX-XXXX-XXXX
```

---

## 🧪 Automated Verification & Test Suites

GroCo includes an exhaustive suite of automated regression and audit tests that validate all critical financial, security, responsive, administrative, and licensing workflows:

| Test Script | Location | Assertions | Purpose |
| :--- | :--- | :--- | :--- |
| **Licensing Security Hardening Audit** | `tests/licensing_security_hardening_test.php` | **32 Tests / 32 Scenarios** | 32-scenario zero-trust attack matrix testing clone blocking, tampering, expiry, outage, and renewal flows. |
| **Mandatory Licensing Hardening** | `tests/licensing_system_test.php` | **33 Tests / 28 Scenarios** | Validates public repo clone blocking, dev vs. prod license tiers, RSA signatures, domain binding, and outage tolerance. |
| **Dark Theme & Form Controls** | `public/dark_theme_form_controls_test.php` | **38 Tests** | Validates CSS tokens, native select `<option>` dark styling, zero inline white backgrounds, and custom select ARIA semantics. |
| **About & Contact Responsiveness** | `public/about_contact_responsive_audit_test.php` | **40 Tests** | Simulates a 13-breakpoint matrix ($320\text{px} \rightarrow 1440\text{px}$), verifying container constraints and zero horizontal overflow. |
| **Customer Review Image & Lightbox** | `public/customer_review_image_e2e_test.php` | **23 Tests** | Verifies secure image uploads, storage resolution, thumbnail buttons, and modal lightbox viewer logic. |
| **Product Page & Cross-Sells** | `public/responsive_product_audit_test.php` | **25 Tests** | Validates fluid card grids, FBT summary responsiveness, and mobile layouts. |
| **Customer Reviews Lifecycle** | `public/customer_review_audit_test.php` | **36 Tests** | Enforces delivered-order review permissions, rating recalculations, and moderation workflows. |
| **Zero-VAT System Reconciliation** | `public/zero_vat_reconciliation_test.php` | **14 Tests** | Confirms 0.00% VAT calculations across cart, orders, coupons, and POS sales. |
| **Admin Portal Full Audit** | `admin_full_audit_test.php` | **Comprehensive** | Tests admin authentication, RBAC authorization, product CRUD, and inventory mutation. |
| **Database Integrity Scan** | `admin_db_integrity_scan.php` | **13 Invariants** | Scans for orphaned records, negative prices, ledger mismatches, and structural anomalies. |

### Running the Test Suites via CLI:
```bash
# Run 32-Scenario Licensing Security Hardening Audit
php tests/licensing_security_hardening_test.php

# Run 28-Scenario Mandatory Licensing Audit
php tests/licensing_system_test.php

# Run Dark Theme Form Controls Test
php public/dark_theme_form_controls_test.php

# Run Zero-VAT Reconciliation Audit
php public/zero_vat_reconciliation_test.php

# Run Responsive Product & Layout Matrix Test
php public/about_contact_responsive_audit_test.php

# Run Full Admin & Inventory Audit
php admin_full_audit_test.php

# Run Database Consistency & Integrity Scanner
php admin_db_integrity_scan.php
```

---

## 🚢 Production Deployment Guidelines

When deploying GroCo to a live production server:

1. **Set Environment to Production**:
   In `.env`:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   LICENSE_SERVER_URL=https://license.yourdomain.com/api.php
   LICENSE_GRACE_PERIOD_DAYS=7
   ```
2. **Enforce HTTPS**:
   Configure SSL certificates (e.g., Let's Encrypt) and uncomment the HTTPS rewrite rules in `.htaccess`.
3. **Verify Git Exclusions**:
   - Run `git status` on the production server to ensure `.env`, private keys (`*.pem`, `*.key`), authority databases (`*.sqlite`), and user uploads are NOT tracked.
4. **Activate Production License**:
   Generate a Production license with your domain (`--domains="shop.yourdomain.com"`) and activate it on first visit via `public/activate.php`.
5. **Configure Automated Cron Backups**:
   Schedule database backups via cron using the built-in backup CLI or MySQL dump tools.
6. **Optimize PHP Configuration (`php.ini`)**:
   ```ini
   display_errors = Off
   log_errors = On
   upload_max_filesize = 10M
   post_max_size = 12M
   session.cookie_httponly = 1
   session.cookie_secure = 1
   session.use_strict_mode = 1
   ```

---

## 👥 Contributing & Code Standards

- **Strict Typing**: Declare `declare(strict_types=1);` at the top of all PHP files.
- **Prepared Statements**: Never concatenate SQL queries; always bind parameters using PDO.
- **Design Tokens**: Use existing CSS variables (`var(--color-surface)`, `var(--color-primary)`, `var(--radius-sm)`, etc.) to preserve dual-theme compatibility.
- **No License Bypasses**: Never commit bypasses, short-circuits, or dev hardcodes to version control.
- **Mobile First & Responsive**: Verify layout changes across small mobile viewports ($320\text{px} - 375\text{px}$) before submitting pull requests.

---

## 📄 License

This software is distributed under commercial terms with cryptographic installation authorization. All rights reserved.
For licensing inquiries, contact your GroCo software provider or visit [docs/LICENSING_SYSTEM.md](docs/LICENSING_SYSTEM.md).
