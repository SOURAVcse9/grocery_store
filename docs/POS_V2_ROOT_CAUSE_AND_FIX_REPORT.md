# GROCO POS V2 — ROOT CAUSE AND FIX REPORT

**Date:** October 16, 2026  
**Environment:** PHP 8.2.12 / Apache 2.4 (XAMPP on Windows) / MariaDB 10.4.32  
**Branch:** `modernization/pos-v2-upgrade-2026`  
**Application Root:** `C:\xampp\htdocs\grocery-store`

---

## Executive Summary

Following the integration of POS V2 and research-backed supermarket retail features, an exhaustive audit was performed to resolve reported UI/layout defects, server 500 errors, camera scanner restrictions, customer profile modal stalls, and print layout wrapping. All underlying defects were reproduced, isolated, and permanently corrected with 100% test suite pass rates (111/111 passing tests) across both the core e-commerce engine and the POS terminal.

---

## Detailed Root Cause Analysis & Fix Matrix

### Bug A: Register X-Report Layout & Narrow 280px Column Wrapping

- **Affected File / URL:** `admin/pos/register.php?action=x_report`
- **Observed Behavior:**
  The report rendered in an extremely cramped ~280px width column. Numbers collided with labels, text wrapped onto multiple broken lines, and triggering `window.print()` printed the entire admin sidebar, topbar, and dashboard navigation.
- **Root Cause:**
  `admin/pos/register.php` unconditionally executed `require_once __DIR__ . '/../layouts/dashboard_layout.php';` on line 11 before verifying the requested `action` query parameter. When `action=x_report` was requested, the controller injected a second nested `<!DOCTYPE html><head>` document inside the admin dashboard's `<main>` container. An inline CSS declaration (`body { width: 280px; font-family: monospace; }`) was applied globally to the browser DOM, collapsing the entire admin wrapper into 280px. Furthermore, `@media print` rules were absent, forcing the full dashboard navigation to be output by physical printers.
- **Resolution Implemented:**
  1. Relocated administrative authentication (`require_admin_auth()`), permissions enforcement (`require_admin_permission('pos.cash')`), and POS library requirements to the top of the file before layout loading.
  2. Intercepted `$reportAction` (`x_report`, `z_report`, `report`) before `dashboard_layout.php` is reached.
  3. Engineered a dedicated, standalone, professional audit report template equipped with:
     - Top Sticky Action Toolbar (`.no-print`) containing "Back to Register", View Mode Switcher ("A4 Audit Sheet" vs "80mm Thermal Receipt"), and a "Print Report" action button.
     - Cash Drawer Physical Reconciliation Table strictly adhering to the supermarket drawer formula:  
       $$\text{Opening Float} + \text{Cash Sales} + \text{Cash In} - \text{Cash Out} - \text{Cash Refunds} = \text{Expected Physical Drawer Cash}$$
     - Separate Non-Cash Electronic Tenders section (Card, bKash/MFS, Bank Transfer, Customer Wallet Credit) clearly showing funds deposited outside the physical cash drawer.
     - Operational Turnover & Basket Analytics section (Total Gross Turnover, Paid Transaction Count, Average Transaction Value (ATV), and Voided Transactions).
     - Itemized Cash Drawer Movements Log detailing all petty cash additions and safe drops.
     - Dual-mode CSS allowing on-the-fly toggling between an 820px A4 corporate audit sheet and a 320px monospace thermal receipt.
     - Print-specific media styles (`@media print`) that suppress toolbars and page margins.
  4. Added `shift_id` parameter support, enabling cashiers and managers to inspect and print historical reports for any previous shift directly from the Shift History table.

---

### Bug B: POS Product Images Display, Aspect Ratio & Missing File Handling

- **Affected File / URL:** `admin/pos/index.php`
- **Observed Behavior:**
  Certain product cards in the POS terminal displayed broken image icons or stretched aspect ratios when catalog images were missing from disk.
- **Root Cause:**
  Database seed records referenced product image filenames that did not exist in `public/uploads/products/`. The front-end lacked an inline fallback error listener, causing the browser to render a broken image box. Additionally, the image container lacked strict dimensions and aspect ratio containment rules.
- **Resolution Implemented:**
  1. Enclosed product thumbnail images in `.pos-product-img-box` (fixed 76px height, overflow hidden, white background, flex centering).
  2. Applied `max-width: 100%`, `max-height: 100%`, and `object-fit: contain` to preserve true product proportions without stretching or cropping.
  3. Added an inline `onerror` attribute to all POS catalog images:  
     `onerror="this.onerror=null;this.src='<?= e(image_url('', 'products')) ?>';"`  
     ensuring missing files fall back seamlessly to the application's clean SVG placeholder.

---

### Bug C: Customer Profile & CRM Indefinite Spinner

- **Affected File / URL:** `admin/pos/index.php` & `admin/pos/ajax/customer_history.php`
- **Observed Behavior:**
  Clicking the "CRM" button in the POS terminal opened `#customerCrmModal` with a spinner that remained stuck indefinitely on "Loading customer profile and order history...".
- **Root Cause:**
  Three compounding flaws caused this defect:
  1. *Walk-in Default*: On initial terminal load, the walk-in customer (ID #7) was selected. Clicking "CRM" triggered `ajax/customer_history.php?customer_id=7`. Walk-in accounts are anonymous and lack individual member CRM profiles.
  2. *Payload Property Discrepancy*: `customer_history.php` returned `stats.total_spent` and `stats.total_orders`. However, `pos.js` accessed `c.lifetime_spent` and `c.total_orders` directly on the `customer` object. In `order_items`, `customer_history.php` returned `product_name`, but `pos.js` accessed `it.name`. In `orders`, `customer_history.php` returned `status`, but `pos.js` accessed `o.order_status`. These mismatches caused JavaScript errors inside the `.then()` chain.
  3. *Static Initial HTML*: `#customerCrmModal` had hardcoded spinner HTML. When unhandled errors occurred in the fetch promise chain, the spinner remained indefinitely without displaying an error state.
- **Resolution Implemented:**
  1. Updated `openCustomerCRMModal()` in `admin/assets/js/pos.js` to detect walk-in customer selections. If walk-in is selected, it immediately displays an informative notice explaining that walk-in sales do not accrue loyalty credits, with a one-click button to register a new member (F2).
  2. Enriched `customer_history.php` JSON output to include `lifetime_spent`, `total_orders`, `full_name`, `name`, `status AS order_status`, and `product_name AS name`.
  3. Added comprehensive error handling and status code verification in `pos.js` with friendly error alerts and a "Retry" button.
  4. Expanded permission checks in `customer_history.php` to include `pos.cash` in addition to `pos.sale`, `pos.access`, and `pos.manage`.

---

### Bug D: Camera Scanner Permissions-Policy & Permission Denial

- **Affected File / URL:** `admin/pos/index.php` & `admin/assets/js/pos.js`
- **Observed Behavior:**
  Clicking the camera scan button failed with `NotAllowedError: Permission denied` and browser console warnings regarding Permissions-Policy violations.
- **Root Cause:**
  Both root `.htaccess` (line 26) and `public/.htaccess` (line 77) contained:
  ```apache
  Header set Permissions-Policy "camera=(), microphone=(), geolocation=()"
  ```
  The declaration `camera=()` strictly prohibits all camera access across the entire origin, causing the browser to reject `navigator.mediaDevices.getUserMedia` before even prompting the user.
- **Resolution Implemented:**
  1. Updated Permissions-Policy headers in `.htaccess` and `public/.htaccess` to:
     ```apache
     Header always set Permissions-Policy "camera=(self), microphone=(), geolocation=()"
     ```
     granting first-party permission for the POS application to access webcam and device camera hardware.
  2. Overhauled `openCameraScanner()` in `pos.js` with structured try/catch handling that distinguishes `NotAllowedError`, `NotFoundError`, `NotReadableError`, and `SecurityError`, rendering actionable instructions and a "Retry Camera" button.
  3. Guaranteed complete cleanup in `closeCameraScanner()` by stopping all active media stream tracks (`track.stop()`) and unsetting video `srcObject`.

---

### Bug E: Payment & Tender Terminal Responsive Alignment & Split Validation

- **Affected File / URL:** `admin/pos/index.php` (`#checkoutPaymentModal`)
- **Observed Behavior:**
  On narrow viewports and mobile devices, the payment tender rows compressed labels, misaligned numerical inputs, and overflowed modal boundaries.
- **Root Cause:**
  Tender rows used hardcoded two-column grids (`grid-template-columns: 1.5fr 2fr`) with inline styles that did not adapt to screens under 480px.
- **Resolution Implemented:**
  1. Extracted all payment modal rows into a reusable responsive CSS class `.pos-pay-row`.
  2. Added responsive media queries (`@media (max-width: 480px)`) that transition tender rows from a 2-column layout to a stacked 1-column layout, expanding input fields to 100% width.
  3. Reduced modal padding and button pill sizes on mobile to guarantee zero horizontal clipping.
  4. Retained rigorous client-side split validation where non-cash tenders + cash tendered must equal or exceed payable total before enabling "Confirm Sale".

---

### Bug F: Modal Accessibility, Focus Collisions & `aria-hidden` Warnings

- **Affected File / URL:** `admin/pos/index.php` & `admin/assets/js/pos.js`
- **Observed Behavior:**
  Browser console warned: `Blocked aria-hidden on an element because the element that currently has focus is within the subtree`.
- **Root Cause:**
  When Bootstrap 5 modals open, Bootstrap applies `aria-hidden="true"` to background elements. If keyboard focus remains on an element within the hidden DOM tree (or when transitioning between modals), modern browsers log accessibility violations.
- **Resolution Implemented:**
  1. Added dedicated `shown.bs.modal` event listeners across all POS modals (`createCustomerModal`, `cameraScanModal`, `customerCrmModal`, `checkoutPaymentModal`) to transfer keyboard focus to the first active input element immediately upon display.
  2. Added `hidden.bs.modal` listeners that safely return focus to the primary search input (`#posFilterSearch`) or customer search input (`#posCustomerSearch`), preventing orphaned focus traps.
  3. Attached an automated teardown listener on `cameraScanModal` to invoke `closeCameraScanner()` upon closure.

---

### Bug G: Product & POS QR Label Generation

- **Affected File / URL:** `admin/pos/qr_labels.php` & `admin/products/qr_labels.php`
- **Observed Behavior:**
  Visiting `admin/pos/qr_labels.php` previously resulted in an Internal Server Error.
- **Root Cause:**
  The categories dropdown query previously queried `WHERE status = 'active'`. In the database, `categories` uses `is_active` (`TINYINT(1)`). Furthermore, `input()` helper was called with integer defaults under `declare(strict_types=1);` before its signature was updated.
- **Resolution Implemented:**
  Verified that `admin/pos/qr_labels.php` cleanly routes to `admin/products/qr_labels.php`. Verified that offline `qrcode.min.js` (19.9 KB) is bundled locally without CDN dependencies, and verified that all 24 catalog products render print-ready QR cards with selectable formats (Standard, Compact, Shelf-Talker).

---

### Bug H: Inventory Reorder Recommendations 500 Error

- **Affected File / URL:** `admin/inventory/reorder_recommendations.php`
- **Observed Behavior:**
  Navigating to `admin/inventory/reorder_recommendations.php` caused an HTTP 500 Internal Server Error: `Column not found: 1054 Unknown column 'status' in 'where clause' on line 166`.
- **Root Cause:**
  Line 166 contained the obsolete query:
  ```php
  $categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
  ```
  Since `categories` uses `is_active` instead of `status`, MariaDB threw an uncaught PDOException.
- **Resolution Implemented:**
  Updated line 166 to:
  ```php
  $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
  ```
  Verified via CLI test execution that the page renders all 24 inventory recommendations, ADS velocities, Days of Supply indicators, safety stock thresholds, and CSV export functionality without errors.

---

## Automated Verification Results

All test suites were executed against the live application database:

| Test Suite File | Focus Area | Tests Run | Result |
| :--- | :--- | :---: | :---: |
| `tests/pos/pos_master_test_runner.php` | Cart, Pricing, Split Tenders, Concurrency, Restock, Reconciliation, API | 44 | **44 PASSED (100%)** |
| `tests/pos_v2_full_verification_test.php` | Shift Lifecycle, Idempotency, Oversell Defense, Cash Mathematics | 31 | **31 PASSED (100%)** |
| `tests/pos_v2_research_features_test.php` | QR Identification, Operator Timeline Logs, ADS Velocity, CRM History | 24 | **24 PASSED (100%)** |
| `tests/pos_v2_regression_test.php` | Schema Integrity, Categories, Brands, Helpers, Placeholders, POS Layout | 12 | **12 PASSED (100%)** |
| **Total** | | **111** | **111 PASSED (0 FAILURES)** |
