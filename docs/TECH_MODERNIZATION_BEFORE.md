# GroCo Grocery Store — Technology Modernization Audit (Pre-Modernization Baseline)

**Target System:** GroCo Grocery Store Platform (`C:\xampp\htdocs\grocery-store`)  
**Audit Date:** 2026-09-26  
**Evaluation Branch:** `modernization/future-tech-2026` (Tag: `pre-modernization-20260926`)  
**Security Baseline:** 100% Passed (13/13 Security Hardening, 22/22 Dual Auth, 29/29 RBAC Auth)

---

## 1. System Architecture & Environment

- **Runtime Stack:** PHP 8.2.12 (ZTS x64) on Apache 2.4 (XAMPP / Linux production compatible).
- **Database Layer:** MySQL / MariaDB (InnoDB, UTF-8 Unicode, strict foreign key constraints, ACID transaction isolation).
- **Frontend Architecture:** Vanilla JavaScript (ES6+), custom CSS Design Tokens (`--color-surface`, `--color-primary`, `--space-*`), FontAwesome 6, Chart.js, HTML5.
- **Architectural Pattern:** Modular Model-View-Controller & Service Pattern in standard PHP without heavy monolithic framework bloat.
- **Licensing Gatekeeper:** RSA-2048 Asymmetric Signature verification on every request (`public/includes/license.php` + `licensing_server/`).

---

## 2. Component Inventory & Existing Capabilities

### 2.1 Public Modules (`public/`)
- **Storefront & Catalog:** `index.php` (Hero banners, categories, flash sales, featured items), `products.php` (faceted filters, sorting, pagination), `product.php` (high-res gallery, cross-sells, verified customer reviews with lightbox).
- **Cart & Checkout:** `cart.php`, `checkout.php`, `process_checkout.php` (multi-address selection, coupons, zero-VAT calculation, stock verification).
- **Customer Account Portal:** `account.php`, `orders.php`, `order-details.php`, `addresses.php`, `reviews.php`, `wishlist.php`.
- **SEO & Discoverability:** `robots.txt`, `sitemap.xml`, `sitemap.php`, JSON-LD schema markup (`Organization`, `WebSite`, `Product`, `BreadcrumbList`).
- **PWA Capabilities:** `manifest.json`, `sw.js` (caching static assets and offline fallback screen).

### 2.2 Admin ERP & Back-Office Modules (`admin/`)
- **Executive Operations:** `index.php` (KPI charts, velocity metrics, low-stock warnings).
- **Catalog & Inventory:** `products/`, `categories/`, `brands/`, `stock-adjustment/`, `stock-transfer/`, `damaged-products/`, `expiry-products/`.
- **Fulfillment & Sales:** `orders/`, `delivery/`, `coupons/`, `flash-sales/`.
- **Financial Ledger & Accounting:** `finance/`, `expenses/` (cash, card, mobile banking double-entry transactions).
- **User & Access Management:** `admins/`, `customers/`, `roles/` (RBAC with granular permissions), `security/`, `backup/`.

### 2.3 Point-of-Sale (POS) System (`admin/pos/`)
- Touchscreen-optimized interface with product search, barcode scanner integration, split payments (Cash, Card, bKash/Nagad), cashier shift tracking (`shift.php`), register float management, customer quick-add, and printable thermal receipts.

### 2.4 Authentication & Identity Management
- Dual-tier authentication: Customer accounts (`users` table) and Staff accounts (`admins` table) strictly separated in session namespaces.
- Google OAuth with CSRF state token verification and safe account linking to permanent `users.id`.
- Secure password hashing (`password_hash` with `PASSWORD_DEFAULT`).
- Multi-device session invalidation via `session_version` bumping.

### 2.5 Email & Communication
- SMTP / PHPMailer integration in `public/includes/mailer.php` for password resets, order confirmations, and notifications.

### 2.6 Storage & Media Pipeline
- Local disk storage under `public/uploads/` (images for products, categories, banners, reviews) with Apache `.htaccess` script execution prevention.

---

## 3. Bottlenecks & Modernization Opportunities

| Dimension | Current State | Modernization Opportunity |
| :--- | :--- | :--- |
| **Media Storage** | Local disk only; unoptimized image transformations. | Cloudinary integration for CDN delivery, auto WebP/AVIF format conversion, responsive `srcset`, and local storage fallback. |
| **Caching Layer** | Direct database queries on every request for settings/categories. | Multi-backend cache service (Redis with in-memory/file fallback) for categories, brands, settings, and navigation. |
| **API Layer** | Ad-hoc AJAX endpoints across admin/public folders. | Versioned RESTful JSON API (`/api/v1/`) with standardized responses, request IDs, rate limiting, and OpenAPI specs. |
| **Frontend UI/UX** | CSS variables with bespoke component scripting. | Standardized modern UI component system (accessible modals, skeleton loaders, toast notifications, responsive forms). |
| **PWA & Offline** | Basic service worker with generic cache. | Upgraded Service Worker with cache versioning, network-first strategy for dynamic data, and safe offline store browsing. |
| **POS Offline Support** | Requires continuous server connectivity. | Client-side indexed/local cart queuing with idempotency keys (`client_tx_id`) and conflict-free server synchronization. |
| **Search Engine** | Standard SQL `LIKE` queries. | Fast faceted search service with MySQL FULLTEXT optimization, typo tolerance, autocomplete, and relevance ranking. |
| **Background Jobs** | Synchronous execution during HTTP requests. | Resilient async queue service (`QueueService`) for async emails, image processing, and report generation with retry handling. |
| **Observability** | Plain text error logs. | Structured JSON logging with request IDs, correlation tracking, performance timers, and sensitive data masking. |
| **Database Performance** | Basic indexing. | Index optimization on high-frequency query paths (orders, products, reviews), query profiling, and N+1 prevention. |

---

## 4. Preservation Invariants

The modernization roadmap strictly preserves:
1. **Zero Data Loss & Zero Schema Corruption**: MySQL remains authoritative for all transactional state.
2. **Security Hardening**: All `.htaccess` rules, CSRF tokens, XSS entity escaping, IDOR ownership checks, and SQL parameterization remain active.
3. **RSA-2048 Licensing Gatekeeper**: The cryptographic installation verification remains untouched and authoritative.
4. **Business Logic Continuity**: Pricing, discounts, zero-VAT calculations, stock deductions, and POS cashier shifts remain identical.
