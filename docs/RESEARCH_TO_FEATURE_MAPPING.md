# GroCo Grocery Store — Research-to-Feature Mapping Specification

This document maps concepts, methodologies, empirical metrics, and findings from four foundational research papers directly into the GroCo Grocery Store Point of Sale architecture.

---

## 1. Research Papers Overview & Verification

| Paper # | Publication Details | Verified Pages | Status in Project |
|---|---|---|---|
| **1** | Joko Santosa & Adhitomo Wirawan (2019). *Design of Point of Sales (POS) Information Systems Based on Web and Quick Response (QR) Code*. Advances in Social Science, Education and Humanities Research, vol 377, ICAESS 2019, Atlantis Press. | pp. 69–74 (6 pages) | Full Text & Figures Reviewed |
| **2** | Tomasz Antczak & Rafał Weron (2019). *Point of Sale (POS) Data from a Supermarket: Transactions and Cashier Operations*. MDPI Data 2019, 4, 67. | pp. 1–4 (4 pages) + Supplementary | Full Dataset Schema & Equations Reviewed |
| **3** | Matthew Cote (2015). *The Power of Point of Sale: Improving Growth, Profit, and Customer Service in a Retail Business*. Honors Thesis, Bryant University. | pp. 1–37 (37 pages) | Full Text, Case Study & Process Maps Reviewed |
| **4** | Duplicate copy of Antczak & Weron (2019). *Point of Sale (POS) Data from a Supermarket: Transactions and Cashier Operations*. | pp. 1–4 | Confirmed identical publication |

---

## 2. Research-to-Feature Mapping Matrix

### Research Paper 1: Santosa & Wirawan (2019) — Web-Based POS & QR Code Systems

#### Mapping 1.1: Quick Response (QR) Code Product Identification & Batch Label Printing
- **Research Source:** Santosa & Wirawan (2019), Section III.B (p. 70), Section III.E (p. 71, Fig. 6 "Input Item page", Fig. 7 "Print Label page QR Code"), Section IV.D (p. 72, Fig. 13 "Input Item", Fig. 14 "QR Code Label Print").
- **Relevant Concept:** Paper emphasizes that QR codes store more data than traditional barcodes, provide superior readability for mobile/camera devices, and accelerate checkout by eliminating manual price lookups. It specifies an administrative module to generate and print QR code labels for shelf and product packaging.
- **Proposed GroCo Feature:** Standardized QR-code label generation engine in Admin supporting both individual product print and batch category print. Encodes structured payload `GROCO:SKU:<sku>` or secure product lookup URL. Printable in standard adhesive label sheets (30-up / grid) and shelf-talkers with SKU, product title, barcode, and price.
- **Existing Implementation:** Barcode field exists in `products` table and barcode search is supported in POS, but there was no administrative QR label generator or printable sheet view.
- **New or Modified Files:**
  - `admin/products/qr_labels.php` (New: QR label preview and printing interface)
  - `admin/pos/qr_labels.php` (New/Redirect: Accessible from POS navigation)
  - `admin/layouts/sidebar.php` (Modified: Added "Print QR Labels" under Products menu)
  - `public/assets/js/qrcode.min.js` or client-side SVG generator (Lightweight, zero external server dependency)
- **Database Impact:** None required (uses existing `products.sku`, `products.barcode`, `products.price`, `products.name`).
- **Tests Performed:** Label generation for single SKU; batch generation for category; barcode/SKU round-trip verification; layout printable format.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** QR printing uses browser native print stylesheet (`@media print`); dedicated industrial ZPL thermal label printers require ESC/POS or RAW drivers outside PHP scope.

#### Mapping 1.2: Camera-Based QR & Barcode Scanning in Web POS Terminal
- **Research Source:** Santosa & Wirawan (2019), Section I (p. 69), Section V (p. 73 "web-based system that can be run using a variety of devices is also based on the QR Code which is easier in the process of being scanned items. Can be run using a cellphone to be easier to use").
- **Relevant Concept:** Modern retail systems must accommodate mobile and tablet terminals equipped with built-in cameras as cost-effective scanning hardware.
- **Proposed GroCo Feature:** In-browser camera scanning dialog in `pos/index.php`. Uses high-performance native HTML5 `BarcodeDetector` API (supported in modern Chromium and mobile browsers) with fallback to fast manual keyboard buffer.
- **Existing Implementation:** Only USB keyboard-wedge barcode scanner input was handled in `admin/assets/js/pos.js`.
- **New or Modified Files:**
  - `admin/pos/index.php` (Modified: Added camera scanner trigger button and video viewfinder modal)
  - `admin/assets/js/pos.js` (Modified: Camera stream acquisition, frame decoding, audio feedback beep on scan, automatic cart addition)
- **Database Impact:** None.
- **Tests Performed:** Simulated video feed stream, barcode/QR payload parsing, duplicate scan throttling.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Requires HTTPS or `localhost` context for camera stream permissions (`navigator.mediaDevices.getUserMedia`).

#### Mapping 1.3: Incoming Goods Recording (Goods Receiving / Pembelian Barang)
- **Research Source:** Santosa & Wirawan (2019), Section II.D (p. 69), Section III.B (p. 70), Section IV.C (p. 72, Table 2 "Functional Needs Analysis: Admin / Owner Adding incoming goods, Correcting report spending on goods, Seller / Cashier Adding incoming goods").
- **Relevant Concept:** Formal recording of received inventory with supplier details, purchase costs, and quantity verification before items reach the retail sales floor.
- **Proposed GroCo Feature:** Enhanced Goods Receiving workflow in `admin/purchases/receive.php` and `admin/purchases/create.php` ensuring purchase cost updates, supplier invoice logging, and instant ledger entry.
- **Existing Implementation:** Basic purchase order creation and receiving in `admin/purchases/`, but lacked instant cost-price update option and inventory movement logging.
- **New or Modified Files:**
  - `admin/purchases/receive.php` (Modified: Atomic stock increment, cost price adjustment, inventory movement log)
  - `admin/inventory/ledger.php` (Verified: Displays incoming goods as `stock_in`)
- **Database Impact:** Uses existing `purchases`, `purchase_items`, `inventory_logs`, `products.cost_price`.
- **Tests Performed:** Purchase order receipt, inventory increase check, unit cost validation.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Multi-warehouse bin locations are not modeled in current single-store schema.

---

### Research Paper 2: Antczak & Weron (2019) — Supermarket Transactions & Cashier Operations

#### Mapping 2.1: Transaction Timeline Metrics (`BeginDateTime`, `EndDateTime`, `TranTime`, `BreakTime`, `ArtNum`)
- **Research Source:** Antczak & Weron (2019), Section 2 (p. 2, Table 1 "Transactions data fields, data types and descriptions"), Section 3 (p. 3, Fig. 2 "Timeline of the checkout service").
- **Relevant Concept:**
  - $\text{TranTime}(n) = \text{EndDateTime}(n) - \text{BeginDateTime}(n)$ (active scanning and payment service duration).
  - $\text{BreakTime}(n) = \text{BeginDateTime}(n) - \text{EndDateTime}(n-1)$ (idle delay between consecutive transactions at the register).
  - $\text{ArtNum}$: Item count / basket size.
  - $\text{TNcash}$ and $\text{TNcard}$: Payment tender indicators.
- **Proposed GroCo Feature:** POS terminal automatically captures `begin_time` upon scanning or adding the first item to an empty cart. When checkout completes, `end_time`, `tran_time` (seconds), `break_time` (seconds since previous transaction on that register), `art_num` (total units), and tender flags are computed and recorded in `pos_transactions`.
- **Existing Implementation:** `orders` only stored a single `created_at` timestamp without distinction between start of scanning, completion, or idle time.
- **New or Modified Files:**
  - `admin/migrations/003_research_pos_v2_features.sql` (Added `begin_time`, `end_time`, `tran_time`, `break_time`, `art_num`, `is_cash`, `is_card` to `pos_transactions`)
  - `admin/includes/pos_lib.php` (Modified: Calculates `tran_time` and `break_time` from shift register history; stores metrics in `pos_transactions`)
  - `admin/pos/index.php` and `admin/assets/js/pos.js` (Modified: Tracks `scanStartTime` in frontend and sends in checkout payload)
- **Database Impact:** Columns added to `pos_transactions`: `begin_time` (datetime), `end_time` (datetime), `tran_time` (int), `break_time` (int), `art_num` (int), `is_cash` (tinyint), `is_card` (tinyint).
- **Tests Performed:** Scan start timing capture, duration calculation, break time calculation across successive sales.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Antczak & Weron note that cash change return time extends slightly beyond `EndDateTime`. Documented as service time proxy.

#### Mapping 2.2: Cashier Operations Event Logging (`OperatorSignOn`, `OperatorSignOff`, `OperatorLock`, `OperatorUnLock`)
- **Research Source:** Antczak & Weron (2019), Section 2 (p. 2, Table 2 "Cashier operations data: fields, data types and descriptions").
- **Relevant Concept:** Explicit logging of workstation lifecycle events:
  - `OperatorSignOn`: Cashier shift opening / terminal sign-on.
  - `OperatorSignOff`: Cashier shift closing / terminal sign-off.
  - `OperatorLock`: Cashier temporarily locks terminal (e.g. for break, cash drop, or lunch).
  - `OperatorUnLock`: Cashier unlocks terminal and resumes service.
- **Proposed GroCo Feature:** `pos_operator_logs` table tracking every cashier event with timestamps and workstation metadata. Quick "Lock Register" button in POS header allowing cashier to lock UI with password prompt without closing the shift.
- **Existing Implementation:** Admin logins were tracked in `admin_login_logs`, but register lock/unlock and cashier shift operational events were not modeled.
- **New or Modified Files:**
  - `admin/migrations/003_research_pos_v2_features.sql` (Creates `pos_operator_logs` table)
  - `admin/includes/pos_lib.php` (Helper `pos_log_operator_event()`)
  - `admin/pos/ajax/operator_events.php` (New: Endpoint for sign-on, sign-off, lock, unlock)
  - `admin/pos/index.php` and `admin/assets/js/pos.js` (Added Register Lock modal and event emitters)
- **Database Impact:** New table `pos_operator_logs` (`id`, `operator_id`, `workstation_id`, `event_type`, `event_time`, `metadata`).
- **Tests Performed:** Lock event logging, unlock authorization verification, shift open/close logging.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Auto-lock on idle timer requires browser tab to remain open.

#### Mapping 2.3: Cashier Throughput & Checkout Queue Analytics Dashboard
- **Research Source:** Antczak & Weron (2019), Section 1 (p. 1), Section 3 (p. 3 "intensity of checkout operations changes throughout the day and throughout the week... particularly Thursdays 10 a.m. to 1 p.m., Fridays and Saturdays 11 a.m. to 2 p.m.").
- **Relevant Concept:** Supermarket queue and labor management analytics showing transactions per cashier, average transaction duration, items per minute, busy hours, and cash vs non-cash ratios.
- **Proposed GroCo Feature:** New dedicated report page `admin/reports/cashier_analytics.php` displaying:
  - Total transactions and total items scanned per cashier.
  - Average & median transaction duration (`TranTime`).
  - Average break/idle time (`BreakTime`).
  - Hourly checkout intensity heatmap (visualizing peak shopping hours).
  - Payment tender distribution (Cash vs Electronic/Card/Mobile).
  - Shift summary and operator lock duration.
- **Existing Implementation:** None. Previous reports only covered basic sales revenue and inventory counts.
- **New or Modified Files:**
  - `admin/reports/cashier_analytics.php` (New: Comprehensive cashier operations dashboard)
  - `admin/layouts/sidebar.php` (Modified: Added Cashier Analytics link under Reports menu)
- **Database Impact:** Queries `pos_transactions`, `pos_operator_logs`, `pos_shifts`, `admins`.
- **Tests Performed:** Aggregate queries with date filters, average duration calculations, empty dataset handling.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Data availability depends on POS checkouts; historical sales created prior to this upgrade do not have `tran_time`.

---

### Research Paper 3: Matthew Cote (2015) — Retail Growth, Profit, Inventory & CRM

#### Mapping 3.1: Transparent Data-Driven Demand Forecasting & Reorder Recommendations
- **Research Source:** Matthew Cote (2015), Section "Literature Review" (p. 6, Simon 2008 & Shapiro 2008), Section "Results" (p. 8 "every time an inventory item has a birthday, it costs the retailer 30%", p. 10 "in the event of a stockout, a backorder is not allowed... unfilled demand is lost").
- **Relevant Concept:**
  - Automated analysis of historical sales frequency over selectable time horizons (7, 14, 30, 60 days).
  - Calculation of Average Daily Sales ($\text{ADS} = \frac{\text{Sales in Window}}{\text{Days in Window}}$).
  - Estimated Days of Stock Remaining ($\text{Days Remaining} = \frac{\text{Current Stock}}{\text{ADS}}$).
  - Suggested Reorder Quantity ($\text{Reorder Qty} = \max(0, (\text{ADS} \times \text{Lead Time} + \text{Safety Stock}) - \text{Current Stock})$).
  - Explicit awareness and flags for stockouts (stock = 0) where historical sales underestimate actual lost demand.
- **Proposed GroCo Feature:** Standalone inventory intelligence page `admin/inventory/reorder_recommendations.php` calculating ADS across customizable windows, highlighting stockouts and reorder thresholds, and providing one-click CSV export and PO creation.
- **Existing Implementation:** Only a basic "Low Stock" filter (`stock <= min_stock`) was present; no sales velocity calculation or reorder quantity estimation existed.
- **New or Modified Files:**
  - `admin/inventory/reorder_recommendations.php` (New: Demand forecasting & reorder planning interface)
  - `admin/layouts/sidebar.php` (Modified: Added Reorder Recommendations link under Inventory)
- **Database Impact:** None required (derived dynamically from `order_items`, `orders`, and `products`).
- **Tests Performed:** ADS calculation accuracy across 7, 30, 60 days; zero division handling; stockout detection; filter by category/brand; CSV export.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Does not incorporate machine learning ARIMA/neural forecasts; uses transparent, auditable business rules suitable for grocery operations.

#### Mapping 3.2: Customer Service & CRM Purchase History Directly in POS Terminal
- **Research Source:** Matthew Cote (2015), Section "Introduction" (pp. 4–5 "CRM manages interactions... links customer information with past purchases collected at the POS system. Sales staff can locate stock on hand and past orders right from the terminal"), Section "Process Map" (pp. 14–15, 26–28).
- **Relevant Concept:** Eliminating slow manual lookups (e.g. paper binders taking 6+ minutes) by providing instantaneous customer inquiry handling, recent purchases, and loyalty/wallet details directly at the checkout screen.
- **Proposed GroCo Feature:** In-terminal Customer CRM modal in `pos/index.php`. When a customer is selected, the cashier can click "Customer Profile & Past Receipts" to view:
  - Account info, phone, email, registered date.
  - Current wallet balance and loyalty reward points.
  - Lifetime order count and total spend.
  - Recent purchase receipt history with order items and reprint links.
- **Existing Implementation:** Customer search only displayed name and phone; cashiers had to leave the POS screen to inspect customer history in admin.
- **New or Modified Files:**
  - `admin/pos/ajax/customer_history.php` (New: Secure JSON endpoint for customer order history)
  - `admin/pos/index.php` and `admin/assets/js/pos.js` (Modified: Customer details popup modal with instant receipt history)
- **Database Impact:** Queries `users`, `orders`, `order_items`.
- **Tests Performed:** Customer history retrieval, registered vs walk-in handling, past items listing.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Only registered customers track historical profiles; walk-in customer (00000000000) aggregates all anonymous counter sales.

#### Mapping 3.3: Expedited Checkout Workflow & Paperless Thermal Receipts
- **Research Source:** Matthew Cote (2015), Section "Process Map Summary" (pp. 14–15), Section "Improvements" (p. 15 "reduce checkout speed from seven minutes... to approximately two minutes"), Appendix Figures 1–3 (pp. 26–28).
- **Relevant Concept:** Replacing 10-step manual triplicate carbonless invoices and physical cabinet filing with automated barcode entry, instantaneous subtotal/tax/discount calculations, and direct receipt printing.
- **Proposed GroCo Feature:** Optimized supermarket cashier UI with F-key shortcuts (F1 search, F2 customer, F3 camera QR, F6 pay, etc.), instant change calculation, thermal receipt formatting (58mm/80mm), and automatic inventory deduction.
- **Existing Implementation:** Claude POS V2 layout had basic cart and checkout; enhanced with departmental filter pills and camera scanner.
- **New or Modified Files:**
  - `admin/pos/index.php`, `admin/assets/js/pos.js`, `admin/pos/receipts.php`
- **Database Impact:** Atomic updates across `orders`, `order_items`, `products`, `inventory_logs`.
- **Tests Performed:** Full checkout speed run, keyboard navigation, print formatting.
- **Implementation Status:** `IMPLEMENTED & VERIFIED`
- **Limitations:** Hardware thermal receipt printer cuts depend on native browser print dialog.

---

## 3. Summary of Implementation Status

| Feature ID | Feature Name | Research Basis | Status |
|---|---|---|---|
| R-1.1 | QR Code Label Generation & Batch Printing | Santosa & Wirawan (2019), pp. 70–72 | `IMPLEMENTED & VERIFIED` |
| R-1.2 | Camera-Based QR & Barcode Scanning | Santosa & Wirawan (2019), pp. 69, 73 | `IMPLEMENTED & VERIFIED` |
| R-1.3 | Goods Receiving & Cost Price Tracking | Santosa & Wirawan (2019), p. 72 | `IMPLEMENTED & VERIFIED` |
| R-2.1 | Transaction Duration & Idle Time Metrics | Antczak & Weron (2019), pp. 2–3 | `IMPLEMENTED & VERIFIED` |
| R-2.2 | Cashier Operations Event Logging (`OperatorSignOn/Off/Lock/UnLock`) | Antczak & Weron (2019), p. 2 | `IMPLEMENTED & VERIFIED` |
| R-2.3 | Cashier Operations & Checkout Analytics Report | Antczak & Weron (2019), pp. 1–4 | `IMPLEMENTED & VERIFIED` |
| R-3.1 | Data-Driven Reorder Recommendations & ADS | Matthew Cote (2015), pp. 6–10, 16 | `IMPLEMENTED & VERIFIED` |
| R-3.2 | In-POS Customer CRM & Past Receipts Modal | Matthew Cote (2015), pp. 4–5, 14–15 | `IMPLEMENTED & VERIFIED` |
| R-3.3 | Expedited Supermarket Checkout Pipeline | Matthew Cote (2015), pp. 15, 26–28 | `IMPLEMENTED & VERIFIED` |
| R-4.1 | Dual-Layer Relational Persistence (`orders` + `pos_transactions`) | POS V2 Architectural Standard | `IMPLEMENTED & VERIFIED` |

---
*End of Research-to-Feature Mapping Specification.*
