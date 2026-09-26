# New Project Implementation Checklist (Sections A–Z)

This modular A–Z engineering checklist guides the modern reconstruction of the GroCo Grocery Store platform in any target modern technology stack (e.g., Next.js / Laravel / NestJS / FastAPI / Remix / SvelteKit / Node.js).

---

### Section A: Project Initialization & Infrastructure
- [ ] Initialize repository with structured directories (`/apps/web`, `/apps/admin`, `/packages/database`).
- [ ] Configure environment variables (`.env`) for DB, SMTP, OAuth, and API secrets.
- [ ] Setup Docker Compose for local development (App, PostgreSQL/MariaDB, Redis, Mailpit).
- [ ] Configure CI/CD pipeline with linting, automated unit tests, and security scanning.

### Section B: Database Schema & Migrations
- [ ] Create core tables: `users`, `admins`, `roles`, `permissions`, `role_permissions`.
- [ ] Create catalog tables: `categories`, `brands`, `products`, `product_images`, `product_variants`.
- [ ] Create ordering & cart tables: `cart_items`, `orders`, `order_items`, `addresses`, `coupons`, `coupon_usage`.
- [ ] Create inventory & procurement tables: `inventory_logs`, `damaged_products`, `suppliers`, `purchases`, `purchase_items`.
- [ ] Create finance & POS tables: `pos_shifts`, `pos_held_orders`, `expenses`, `expense_categories`, `finance_transactions`.
- [ ] Create engagement & system tables: `reviews`, `wishlist`, `banners`, `flash_sales`, `notifications`, `settings`.
- [ ] Set proper foreign key constraints, cascading rules, and composite indexes (`idx_sku`, `idx_status_created`, `idx_user_read`).

### Section C: Authentication & Session Management
- [ ] Implement password hashing with Argon2id or BCrypt ($cost \ge 12$).
- [ ] Build Customer Registration & Login with rate-limiting and email verification.
- [ ] Implement Google OAuth 2.0 (Dual auth linking on verified email).
- [ ] Implement SHA-256 password reset tokens with 1-hour expiration and single-use invalidation.
- [ ] Build multi-device session revocation using integer `session_version` counter.
- [ ] Build separate Admin Authentication with IP-binding and strict brute-force cooldown.
- [ ] Implement CLI / Root OTP password rotation for Super Admin (blocking public web password reset).

### Section D: Authorization & Role-Based Access Control (RBAC)
- [ ] Define all 42 granular permission identifiers (`dashboard.view`, `pos.access`, `products.create`, `orders.update`, etc.).
- [ ] Create Role Management CRUD in Admin panel with permission checkbox matrix.
- [ ] Implement route-level and API-level RBAC middleware.

### Section E: Storefront Layout & Navigation
- [ ] Implement responsive sticky header with category dropdown, search bar, and cart drawer.
- [ ] Implement mobile-first bottom navigation bar for screens $\le 768\text{px}$.
- [ ] Create responsive footer with store address, customer service phone, legal links, and payment badges.
- [ ] Implement Light / Dark mode theme toggle persisted in `localStorage`.

### Section F: Product Catalog & Search
- [ ] Build hierarchical category browsing with nested subcategory breadcrumbs.
- [ ] Implement real-time live search with debounced autocomplete (300ms) matching SKU and name.
- [ ] Implement faceted product filtering by category, brand, price slider, in-stock toggle, and rating.
- [ ] Implement sorting dropdown (`price_asc`, `price_desc`, `newest`, `popular`, `rating`).
- [ ] Build Product Details Page with multi-image gallery zoom, variant selectors, stock badge, and SKU display.
- [ ] Implement Product Quick View modal with AJAX hydration.

### Section G: Shopping Cart & Mini-Cart Drawer
- [ ] Build hybrid cart supporting both guest session cart and logged-in user database persistence.
- [ ] Implement automatic cart merging when a guest user logs in.
- [ ] Implement dynamic line-item stepper with live AJAX subtotal recalculation and stock ceiling validation.
- [ ] Build slide-in mini-cart drawer with empty-state call-to-action.

### Section H: Checkout & Payment Engine
- [ ] Build single-page / multi-step checkout form with phone number validation (`^01[3-9]\d{8}$`).
- [ ] Implement shipping tier calculations:
  - Dhaka City: ৳ 60.00
  - Outside Dhaka: ৳ 120.00
  - Free Shipping on orders $\ge$ ৳ 1,500.00.
- [ ] Implement minimum order enforcement (Subtotal $\ge$ ৳ 100.00).
- [ ] Implement Coupon validation engine (fixed vs percentage, minimum spend, max discount, per-user usage limits).
- [ ] Implement Cash on Delivery (COD) workflow.
- [ ] Integrate Bangladeshi digital payment gateways (bKash, Nagad, SSLCommerz, Shurjopay).
- [ ] Wrap checkout order placement inside atomic database transactions (`BEGIN ... COMMIT`).

### Section I: Order Processing & State Machine
- [ ] Implement 7-state order lifecycle: `pending` $\rightarrow$ `confirmed` $\rightarrow$ `processing` $\rightarrow$ `shipped` $\rightarrow$ `delivered` $\rightarrow$ `cancelled` / `returned`.
- [ ] Implement automatic inventory deduction upon order creation.
- [ ] Implement automatic inventory restoration when an order is marked `cancelled` or `returned`.
- [ ] Build customer-facing live order tracking timeline.
- [ ] Build PDF/HTML downloadable order invoices with QR code verification.

### Section J: Customer Account Portal
- [ ] Profile management (Name, Email, Phone, Avatar upload with MIME security).
- [ ] Saved Addresses Book (Default Shipping and Billing selectors).
- [ ] Order history with live status filters and one-click reorder.
- [ ] Customer Wishlist with 1-click "Move to Cart".
- [ ] Product Reviews manager (Verified buyer validation).

### Section K: Point of Sale (POS) Cashier Register
- [ ] Build full-screen POS cashier interface.
- [ ] Implement high-speed barcode gun scanner buffer listener.
- [ ] Quick keyboard shortcuts (`F2` Search, `F4` Customer, `F7` Hold, `F8` Recall, `F9` Pay, `Esc`).
- [ ] Split payment support (Cash + bKash + Card).
- [ ] Cart suspension (Hold / Resume orders).
- [ ] ESC/POS Thermal Receipt printing (80mm & 58mm).
- [ ] Cashier Shift Management with opening float, cash drops, and variance calculation.

### Section L: Inventory ERP & Warehousing
- [ ] Implement `inventory_logs` audit trail recording all 7 movement types (`purchase`, `sale`, `pos_sale`, `adjustment`, `damage`, `return_customer`, `return_supplier`).
- [ ] Manual stock adjustments with reason notes.
- [ ] Damaged products write-off logging at cost price.
- [ ] Low-stock and Out-of-Stock automated alert badges.

### Section M: Supplier Procurement & Purchase Orders
- [ ] Supplier directory CRUD (Contact, phone, address, trade license).
- [ ] Purchase Order generation with cost per unit calculation.
- [ ] Goods Received Note (GRN) workflow that automatically adds received quantities to warehouse stock.

### Section N: Delivery & Fleet Management
- [ ] Delivery personnel directory with phone and vehicle info.
- [ ] Order assignment to in-house delivery rider or external courier (Steadfast / Pathao / RedX).
- [ ] Delivery status tracking and cash collection reconciliation.

### Section O: Marketing & Promotions
- [ ] Dynamic Coupon Code management engine.
- [ ] Flash Sales manager with start/end datetimes and automated countdown timer banners.
- [ ] Hero Banners and Promotional Slider manager.
- [ ] Frequently Bought Together (FBT) bundled product recommendations.

### Section P: Financial Accounting & Expenses
- [ ] Categorized Expense Logger (Rent, Utilities, Salaries, Packaging, Marketing).
- [ ] File attachment upload for expense receipts.
- [ ] Daily Drawer Closing financial ledger.
- [ ] Automated Gross Revenue, COGS, Gross Profit, and Net Profit reports.

### Section Q: Analytics & Executive Dashboard
- [ ] Real-time sales charts (Day, Week, Month, Year).
- [ ] Order status breakdown pie/donut charts.
- [ ] Top-selling products table by units and gross volume.
- [ ] Export reports to CSV / Excel format.

### Section R: Notification & Email System
- [ ] Asynchronous SMTP email dispatcher with fallback queue.
- [ ] Responsive HTML email templates (Welcome, Order Confirmation, Status Change, Password Reset, Low Stock).
- [ ] In-app notification bell with unread count and real-time polling/WebSockets.
- [ ] SMS Gateway driver interface.

### Section S: Search Engine Optimization (SEO)
- [ ] Dynamic metadata generator for titles, descriptions, and OpenGraph tags.
- [ ] Canonical URL generator.
- [ ] Structured Schema.org JSON-LD (`Organization`, `WebSite`, `Product`, `BreadcrumbList`).
- [ ] Automated XML Sitemap generator (`sitemap.xml`) with image tags.
- [ ] `robots.txt` configuration restricting admin and private endpoints.

### Section T: Progressive Web App (PWA)
- [ ] Complete `manifest.json` with icons and standalone display mode.
- [ ] Service Worker (`sw.js`) with cache-first for static assets and offline fallback page.
- [ ] Install prompt banner for mobile browsers.

### Section U: Localization & Multi-Language (i18n)
- [ ] Bilingual dictionary support for English (`en`) and Bengali (`bn`).
- [ ] Dynamic translation helper `t(key, params)`.
- [ ] Bangla numeral formatter (`০-৯`).

### Section V: System Settings Engine
- [ ] Key-value dynamic configuration store in `settings` table.
- [ ] Admin Settings UI tabs (General, Finance & Shipping, POS, Mail/SMTP, OAuth, System).

### Section W: Cryptographic Licensing System
- [ ] RSA-2048 public key verification engine.
- [ ] Domain and machine hardware binding validation.
- [ ] 14-day offline grace period calculation.
- [ ] Admin license upload and activation screen with automated lockouts.

### Section X: Security Hardening
- [ ] CSRF tokens on all state-mutating requests.
- [ ] Strict output encoding (`htmlspecialchars`) to eliminate XSS.
- [ ] 100% Parameterized queries for SQL injection prevention.
- [ ] Insecure Direct Object Reference (IDOR) validation on all user records.
- [ ] File upload MIME validation, random UUID renaming, and `.htaccess` execution locks.
- [ ] Security headers (CSP, HSTS, X-Frame-Options, X-Content-Type-Options).

### Section Y: Performance & Optimization
- [ ] Image optimization pipeline (Auto WebP conversion and responsive `srcset`).
- [ ] Database query indexing on all foreign keys and search columns.
- [ ] In-memory caching (Redis / Memcached) for settings, categories, and top products.
- [ ] Asset minification (CSS / JS) and Brotli/Gzip compression.

### Section Z: Quality Assurance & Deployment
- [ ] End-to-end (E2E) testing of complete checkout flow.
- [ ] POS barcode scanning load testing.
- [ ] Disaster recovery: Automated daily database backup routine.
- [ ] Production deployment checklist on Linux / Docker / Cloud.
