# 🛒 GroCo — Modern Grocery E-Commerce & Retail ERP Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-00618A?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Frontend-Vanilla%20JS%20%2F%20CSS3%20Tokens-F7DF1E?style=flat&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg?style=flat)]()
[![PWA Ready](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=flat&logo=pwa&logoColor=white)]()

**GroCo** is a full-featured, enterprise-grade Grocery E-Commerce Storefront seamlessly integrated with a back-office Retail ERP, Point-of-Sale (POS) terminal, inventory warehouse manager, and customer relationship system.

Engineered with clean PHP, vanilla JavaScript, modern CSS Design Tokens, and pure MySQL PDO transactions, GroCo is designed for extreme speed, rock-solid security, zero framework bloat, and 100% responsiveness across all mobile, tablet, and desktop viewports.

---

## 📑 Table of Contents

- [System Architecture](#-system-architecture)
- [Key Features](#-key-features)
  - [Customer Storefront](#1-customer-storefront)
  - [Admin ERP & Back-Office](#2-admin-erp--back-office)
  - [Point-of-Sale (POS) Terminal](#3-point-of-sale-pos-terminal)
  - [Core Platform Engines](#4-core-platform-engines)
- [Project Directory Structure](#-project-directory-structure)
- [Security & Compliance](#-security--compliance)
- [Prerequisites](#-prerequisites)
- [Installation & Local Setup](#-installation--local-setup)
- [Database Setup & Migrations](#-database-setup--migrations)
- [Automated Verification & Test Suites](#-automated-verification--test-suites)
- [Production Deployment Guidelines](#-production-deployment-guidelines)
- [Contributing & Code Standards](#-contributing--code-standards)

---

## 🏗️ System Architecture

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                            GROCO PLATFORM ECOSYSTEM                         │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
         ┌─────────────────────────────┴─────────────────────────────┐
         ▼                                                           ▼
┌────────────────────────────────┐                         ┌────────────────────────────────┐
│      PUBLIC STOREFRONT         │                         │      ADMIN PORTAL & ERP        │
│   (Customer-Facing E-Commerce) │                         │    (Back-Office Operations)    │
├────────────────────────────────┤                         ├────────────────────────────────┤
│ • Product Catalog & Filters    │                         │ • Real-Time KPI Analytics      │
│ • Verified Customer Reviews    │                         │ • Product & Category Manager   │
│ • Cross-Sells (FBT / Related)  │                         │ • Touch POS Terminal           │
│ • Cart, Coupons & Zero-VAT     │                         │ • Stock & Warehouse Inventory  │
│ • Multi-Address Checkout       │                         │ • Order Fulfillment & Delivery │
│ • Customer Account & Orders    │                         │ • Review Moderation Lightbox   │
│ • Dual Theme (Dark / Light)    │                         │ • Double-Entry Financial Ledger│
│ • PWA (Offline & Installable)  │                         │ • RBAC Role Permissions Matrix │
└────────────────────────────────┘                         └────────────────────────────────┘
         │                                                           │
         └─────────────────────────────┬─────────────────────────────┘
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                       CORE BOOTSTRAP & SERVICES                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ • PDO Singleton Database Layer (`Database::getConnection()`)                │
│ • CSRF Protection Tokens (`csrf.php`) & Security Headers (`security.php`)   │
│ • Session Hijacking Guard & IP Rate Limiting (`rate_limit.php`)             │
│ • Custom Error & Exception Interceptor (`error_handler.php`)               │
│ • Image Upload Handler & Storage Normalizer (`image.php`)                   │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    PERSISTENCE & STORAGE INFRASTRUCTURE                     │
├─────────────────────────────────────────────────────────────────────────────┤
│ • MySQL / MariaDB (InnoDB, Foreign Keys, UTF8MB4, ACID Transactions)        │
│ • `storage/uploads/` (Products, Categories, Brands, Reviews, User Avatars)  │
│ • `storage/logs/` (Application Exception Logs & Security Audit Trail)       │
│ • `storage/backups/` (Automated SQL Dump Snapshots)                         │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## ✨ Key Features

### 1. Customer Storefront

- **Dynamic Homepage**: Hero banner carousel, category grid, daily flash sales with countdown timers, top-rated products, and newsletter subscriptions.
- **Advanced Product Catalog**: Instant faceted filtering by category, brand, price slider, in-stock status, and customer ratings. Sort by latest, price (low/high), and popularity.
- **Product Detail Experience**:
  - High-resolution image gallery with thumbnails.
  - Stock availability badges with low-stock warnings.
  - **Cross-Selling & Recommendations**:
    - *Frequently Bought Together (FBT)*: Single-click bundle purchases with dynamic discount calculations.
    - *Related Products*: Category-linked suggestions.
    - *Recently Viewed*: Client-side tracked product history.
- **Verified Customer Review System**:
  - **Anti-Fraud Guard**: Only authenticated customers with confirmed **Delivered** orders can review a product.
  - Multi-image photo upload support with MIME/dimension validation.
  - **Custom Lightbox Modal**: High-performance inline modal with keyboard navigation (Esc/Arrows), zoom preview, and zero browser tab redirects.
  - Review moderation pipeline (Pending $\rightarrow$ Approved/Rejected) with real-time rating updates.
- **Shopping Cart & Checkout**:
  - Real-time slide-out mini-cart drawer and full cart page.
  - Dynamic coupon validation engine (flat discounts and percentage caps).
  - **Zero-VAT Compliance**: Strictly enforces 0.00% VAT across cart calculations, order subtotals, invoices, and payment summaries.
  - Saved multi-address selector (Home, Office, Other) with inline creation.
- **Customer Portal**:
  - Comprehensive dashboard displaying order counts, wishlist items, and wallet/loyalty points.
  - Multi-address book management with default shipping/billing assignment.
  - Interactive order timeline tracking (Pending $\rightarrow$ Processing $\rightarrow$ Shipped $\rightarrow$ Delivered).
  - In-app notification center with read/unread statuses.
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
- **Data Export & Backups**: One-click SQL database snapshot generation, restore terminal, and CSV report exports (sales, ledger, inventory, customers).

### 3. Point-of-Sale (POS) Terminal

- **Touch-Friendly Interface**: Fast touchscreen product catalog grid with barcode / SKU scanner input.
- **Split Payments**: Flexible checkout supporting combinations of Cash, Credit/Debit Card, and Mobile Banking in a single order.
- **Register & Shift Management**: Cashier shift open/close tracking, initial float recording, and Cash-In / Cash-Out drawer reconciliations.
- **Thermal Receipts**: Automated printable 80mm and 58mm POS receipt generation.
- **Walk-in & Member Customers**: Quick customer creation, wallet balance deductions, and loyalty point rewards directly at the register.

### 4. Core Platform Engines

- **Dual Theme System**: Flawless Dark and Light mode powered by CSS variables (`--color-surface`, `--color-text`, `--color-primary`, etc.) with `color-scheme: dark` form controls, custom native select dropdown styling, and persistent local storage.
- **Accessible Custom Selects**: Progressive-enhancement select component with full WAI-ARIA combobox semantics and complete keyboard accessibility (`ArrowUp`, `ArrowDown`, `Enter`, `Escape`, `Tab`).
- **Responsive Layout Matrix**: Validated across 13 distinct responsive breakpoints ($320\text{px} \rightarrow 1440\text{px}+$); zero horizontal overflow and fluid auto-fit grids.
- **Progressive Web App (PWA)**: Includes Service Worker (`sw.js`) for background caching, offline fallback screens, and mobile Add-to-Home-Screen integration.

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
│   ├── backup/                      # Database backup & restore terminal
│   ├── layouts/                     # Admin shell (topbar, sidebar, header, footer)
│   └── assets/                      # Admin stylesheets (`admin.css`) and POS scripts
│
├── public/                          # Customer-Facing Storefront & Application Core
│   ├── index.php                    # Storefront homepage
│   ├── header.php / footer.php      # Global storefront chrome, navigation & scripts
│   ├── dbconnect.php                # Core PDO bootstrap, session init, dynamic settings
│   ├── csrf.php                     # CSRF token generator & middleware
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
│   ├── includes/                    # Business logic (auth, helpers, security, rate limits)
│   ├── lang/                        # Internationalization (English `en.php`, Bengali `bn.php`)
│   ├── ajax/ & api/                 # Asynchronous endpoints (cart, search, wishlist)
│   ├── sw.js / manifest.json        # PWA Service Worker & manifest
│   └── assets/                      # Storefront CSS tokens, icons, and JavaScript
│
├── database/                        # Database Schemas & Migrations
│   ├── pos_erp_migrations.sql       # Clean DDL migrations for ERP, POS, and ledger
│   └── .htaccess                    # Access protection blocking direct SQL downloads
│
├── storage/                         # Runtime Storage (Excluded from Git)
│   ├── uploads/                     # User uploads (products, reviews, avatars)
│   ├── logs/                        # Error logs (`app.log`)
│   ├── cache/                       # Cached fragments
│   ├── backups/                     # Generated SQL backup dumps
│   └── .htaccess                    # Script execution prevention in upload directories
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

---

## 📦 Prerequisites

Before deploying or running GroCo locally, ensure your environment meets the following specifications:

- **Web Server**: Apache 2.4+ (with `mod_rewrite` and `mod_headers` enabled)
- **PHP**: PHP 8.1 or PHP 8.2+
  - Required Extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (or `imagick`), `json`, `curl`, `session`
- **Database**: MySQL 5.7+ or MariaDB 10.4+
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
3. Import the database schema:
   ```bash
   mysql -u root -p grocery_store < database/pos_erp_migrations.sql
   ```
4. Verify database connectivity:
   Open `http://localhost/grocery-store/public/index.php` in your browser.

---

## 🧪 Automated Verification & Test Suites

GroCo includes an exhaustive suite of automated regression tests that validate all critical financial, security, responsive, and administrative workflows without altering production data:

| Test Script | Location | Assertions | Purpose |
| :--- | :--- | :--- | :--- |
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
   In `.env` (or via Apache environment variables):
   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```
2. **Enforce HTTPS**:
   Configure SSL certificates (e.g., Let's Encrypt) and uncomment the HTTPS rewrite rules in `.htaccess`.
3. **Protect Database Dumps & Uploads**:
   - Confirm `.gitignore` is active so user uploads, logs, and database snapshots are not tracked.
   - Run `git status` on deployment to verify no sensitive `.env` or log files are exposed.
4. **Configure Automated Cron Backups**:
   Schedule database backups via cron using the built-in backup CLI or MySQL dump tools.
5. **Optimize PHP Configuration (`php.ini`)**:
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

- **Strict Typing**: Declare `declare(strict_types=1);` at the top of all new PHP files.
- **Prepared Statements**: Never concatenate SQL queries; always bind parameters using PDO.
- **Design Tokens**: When writing CSS, use existing CSS variables (`var(--color-surface)`, `var(--color-primary)`, `var(--radius-sm)`, etc.) to preserve dual-theme compatibility.
- **Mobile First & Responsive**: Verify layout changes across small mobile viewports ($320\text{px} - 375\text{px}$) before submitting changes.

---

## 📄 License

This project is proprietary and maintained for GroCo Grocery Store operations. All rights reserved.
