# GroCo Grocery Store — Admin Back-Office Feature Inventory

This document provides a comprehensive operational and technical specification of every administrative module, role permission, CRUD operation, business logic rule, and database dependency across the GroCo Grocery Store back-office.

---

## 1. Executive Admin Dashboard

* **Module / File:** `admin/index.php` or `admin/dashboard.php`
* **Permission Required:** `dashboard.view`
* **Purpose:** Real-time business analytics cockpit providing sales metrics, revenue velocity, order fulfillment statuses, inventory health warnings, and live audit notifications.
* **Features & Widgets:**
  * **Summary Stat Cards:**
    * Total Revenue (Today, This Week, This Month, All Time) with percentage growth trends.
    * Total Orders Count (Pending, Processing, Delivered, Cancelled).
    * Total Active Customers and New Registrations.
    * Low Stock & Out of Stock Alerts Counter.
  * **Interactive Sales Chart:** Daily revenue breakdown and monthly comparative curve.
  * **Recent Orders Feed:** Top 10 latest orders with customer name, items count, amount, payment badge, status pill, and action link.
  * **Top Selling Products Leaderboard:** Top 5 grocery items by sales volume and revenue.
  * **Live Stock Alert Table:** Products with stock levels below their minimum threshold (`products.stock <= products.min_stock`).
  * **Recent Admin Audit Activity Stream:** Extracted from `admin_activity_logs`.
* **Database Tables Used:**
  * `orders`, `order_items`, `products`, `users`, `admins`, `admin_activity_logs`, `settings`.

---

## 2. Product Catalog Management

* **Module Directory:** `admin/products/`
* **Files:**
  * `index.php`: Product list table with filters (category, brand, stock level, status), search bar, pagination, and bulk status actions.
  * `create.php`: Form to add new product.
  * `edit.php`: Form to edit existing product.
  * `delete.php`: Soft/hard deletion handler.
  * `export.php`: CSV/Excel export of product catalog.
* **Permission Required:** `products.view`, `products.create`, `products.edit`, `products.delete`
* **Fields & Attributes Managed:**
  * `name`, `slug` (auto-generated from name), `category_id`, `brand_id`.
  * `sku` (unique inventory stock keeping unit), `barcode` (EAN-13 / UPC barcode string).
  * `cost_price` (wholesale procurement cost), `price` (regular retail selling price), `discount_price` (promotional price).
  * `unit` (e.g. `1 kg`, `500 gm`, `1 L`, `Pack`, `Piece`), `weight` (decimal weight for shipping).
  * `stock` (current physical inventory quantity), `min_stock` (re-order trigger threshold).
  * `thumbnail` (main primary product image upload).
  * `gallery_images` (multi-image upload stored in `product_images`).
  * `short_description` (1-2 sentence teaser), `description` (rich HTML specification).
  * Flags: `is_active` (1/0), `is_featured` (1/0), `is_trending` (1/0), `is_flash_sale` (1/0).
  * SEO Meta: `meta_title`, `meta_description`.
* **Business Logic & Validation:**
  * Image Upload: Validates MIME types (`image/jpeg`, `image/png`, `image/webp`), enforces 5MB max file size, uploads into `public/uploads/products/`.
  * Stock Movement Logging: Any manual edit to the `stock` field automatically inserts a record into `inventory_logs` (`type = 'manual_adjustment'`).

---

## 3. Categories & Brands Management

* **Categories Module (`admin/categories/`):**
  * `index.php`, `create.php`, `edit.php`, `delete.php`
  * **Fields:** `name`, `slug`, `parent_id` (nested subcategory support), `image`, `icon`, `description`, `is_active`.
  * **Business Rule:** Deleting a category with active products requires re-assigning products or cascades gracefully.
* **Brands Module (`admin/brands/`):**
  * `index.php`, `create.php`, `edit.php`, `delete.php`
  * **Fields:** `name`, `slug`, `logo`, `description`, `is_active`.

---

## 4. Order Processing & Invoicing

* **Module Directory:** `admin/orders/`
* **Files:**
  * `index.php`: Master order list with search by Order ID / Invoice / Customer Name / Phone, filter by Order Status (`pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`, `returned`) and Payment Status (`paid`, `unpaid`, `refunded`), date range picker.
  * `view.php`: Deep order inspector containing customer details, shipping address, scheduled delivery slot, itemized product table, applied coupon, payment transaction logs, and status update controls.
  * `invoice.php`: Printable thermal / A4 printable tax invoice format with barcode, company details, VAT calculation, and signature lines.
* **Status Transition Rules (`admin/orders/update-status.php`):**
  * Transitioning from `pending` &rarr; `cancelled` or `returned`: Automatically restores product inventory (`products.stock = products.stock + quantity`) and logs `inventory_logs (type = 'order_cancellation')`.
  * Every status change appends a chronological audit entry into `order_status_history`.
  * Delivery rider assignment creates a record in `delivery_assignments`.

---

## 5. Point of Sale (POS) Cash Register Module

* **Module Directory:** `admin/pos/`
* **Files:** `index.php`, `receipt.php`, `ajax/pos_actions.php`
* **Permission Required:** `pos.access`, `pos.sales`
* **Core Capabilities:**
  * Interactive barcode scanner input (auto-adds product to POS cart on scan).
  * Live searchable product catalog grid with category pill tabs.
  * POS Cart table with instant quantity, price override, line discount, and item removal.
  * Customer selector: Fast dropdown to pick existing customer or modal to register new walk-in customer.
  * Payment Modal: Cash, bKash, Nagad, Credit Card, or Split Payment.
  * Real-time Change Calculator: Given amount - Payable amount = Change due.
  * Hold / Resume Order: Allows cashier to put current transaction on hold to serve another customer and resume later (`pos_hold_orders`).
  * Instant Stock Deduction: Decrements `products.stock` on sale completion.
  * Thermal Receipt Printing: Generates 80mm / 58mm POS receipt.

---

## 6. ERP Inventory, Stock Adjustments & Damage Write-Offs

* **Inventory Overview (`admin/inventory/`):**
  * Displays live stock quantity, stock status pill (In Stock, Low Stock, Out of Stock), total inventory valuation at cost price vs retail price.
* **Stock Adjustments (`admin/stock-adjustment/`):**
  * Records physical audit discrepancies (e.g. found surplus or unrecorded sales) with adjustment quantity, type (`addition` / `subtraction`), and reason notes.
* **Damaged Products (`admin/damaged-products/`):**
  * Logs broken, spilled, or spoiled grocery products. Deducts quantity from `products.stock`, records loss financial value (`quantity * cost_price`).
* **Expiry Products (`admin/expiry-products/`):**
  * Perishable grocery batch monitoring: tracks expiration dates and flags items expiring within 30/15/7 days.

---

## 7. Procurement, Suppliers & Purchase Orders (PO)

* **Suppliers Registry (`admin/suppliers/`):**
  * Company name, contact person, phone, email, address, opening balance, current payable balance.
  * Supplier payment recording (`admin/suppliers/payments.php`).
* **Purchase Orders (`admin/purchases/`):**
  * Create Purchase Order with supplier selection, purchase items, unit cost price, quantity, total bill.
  * Upon marking PO as `received`: Automatically increments product stock (`products.stock = products.stock + po_quantity`), updates product `cost_price`, and logs `inventory_logs (type = 'purchase')`.

---

## 8. Customer Management

* **Module Directory:** `admin/customers/`
* **Files:** `index.php`, `view.php`, `edit.php`, `delete.php`
* **Features:**
  * Customer directory with lifetime purchase amount, total orders count, joined date.
  * Customer details view: Profile, registered addresses, complete order history, submitted product reviews.
  * Status toggle: Active / Suspended.

---

## 9. Delivery Fleet & Logistics Management

* **Module Directory:** `admin/delivery/`
* **Files:** `index.php`, `create.php`, `edit.php`, `assign.php`
* **Features:**
  * Delivery rider registration (Full Name, Phone Number, Vehicle Type, Status, National ID).
  * Assign pending orders to specific riders.
  * Rider Cash on Delivery (COD) collection tracking and reconciliation.

---

## 10. Finance, Expenses & Daily Cash Closing

* **Expenses Module (`admin/expenses/`):**
  * Log business operational expenses: Expense title, Category (`Rent`, `Electricity`, `Staff Salary`, `Packaging`, `Transportation`, `Office Supplies`), Amount in BDT, Date, Receipt attachment.
* **Daily Cash Closing (`admin/finance/daily-closing.php`):**
  * Cashier shift reconciliation: Opening cash balance + Total cash sales - Cash refunds - Cash expenses = Expected cash in drawer vs Actual counted cash (Over/Shortage calculation).

---

## 11. Reports & Business Analytics

* **Sales Report (`admin/reports/sales.php`):** Daily, weekly, monthly, annual sales volume, average order value, gross revenue.
* **Inventory Report (`admin/reports/inventory.php`):** Current stock valuation, fast-moving items, slow-moving dead stock.
* **Expense & Profit/Loss Report (`admin/reports/expenses.php` & `profit-loss.php`):** Revenue - Cost of Goods Sold (COGS) - Operational Expenses = Net Profit.
* **Export Engine:** Exports tabular reports to CSV and Excel formats.

---

## 12. Marketing: Coupons, Flash Sales & Banners

* **Coupons (`admin/coupons/`):** Promo code, discount type (`percentage` or `fixed_amount`), discount value, minimum order spend, valid from, valid until, usage limit per customer, status.
* **Flash Sales (`admin/flash-sales/`):** Campaign title, start time, end time, product selection, flash sale discount price.
* **Banners (`admin/banners/`):** Hero carousel slide images, promotional titles, subtitle, destination link URL, sort priority.

---

## 13. Customer Review Moderation

* **Module Directory:** `admin/reviews/`
* **Files:** `index.php`, `approve.php`, `delete.php`
* **Features:**
  * Review moderation queue: Product name, customer name, star rating (1-5), review title, comment, attached customer photos.
  * Actions: One-click "Approve" (publishes review live on storefront) or "Reject / Delete".
  * Recalculates product `avg_rating` and `review_count` in `products` table upon approval.

---

## 14. Administrative User Accounts & RBAC

* **Admin Accounts (`admin/admins/`):** Create admin users, assign roles, activate/suspend accounts. Super Admin OTP password reset generator.
* **Roles & Permissions (`admin/roles/`):** Create roles (e.g. `Super Admin`, `Store Manager`, `Cashier`, `Inventory Manager`), configure 42 granular checkbox permissions.

---

## 15. Store Settings, Licensing & Database Backups

* **Settings (`admin/settings/`):** Store name, logo, contact phone/email, physical address, currency (`BDT`), tax/VAT rate (`%`), default delivery fee, minimum order threshold, SMTP credentials, Google OAuth keys, payment gateway API keys.
* **Software Licensing (`admin/license/`):** Live RSA-2048 cryptographic license health, domain binding verification, installation deactivation.
* **System Backups (`admin/backup/`):** Generates on-demand `.sql` database backup dumps stored in `storage/backups/`.
