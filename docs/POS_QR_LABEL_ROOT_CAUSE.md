# GROCO ENTERPRISE — POS & PRODUCT QR LABELS ROOT-CAUSE AUDIT & RESOLUTION

**Document ID:** `docs/POS_QR_LABEL_ROOT_CAUSE.md`  
**Date:** October 15, 2026  
**Environment:** Apache/2.4.58 (Win64), PHP 8.2.12, MariaDB 10.4.32, Windows XAMPP  
**Target File:** `admin/pos/qr_labels.php` (including `admin/products/qr_labels.php`)  
**Status:** **RESOLVED & VERIFIED (HTTP 200 OK)**

---

## 1. Incident Description

Following the integration of Admin POS V2 and research-based supermarket retail modules (Santosa & Wirawan 2019, Antczak & Weron 2019), navigating to the QR Label printing interface:

```
http://localhost:8080/grocery-store/admin/pos/qr_labels.php
```

consistently generated an **Internal Server Error (HTTP 500)**. No stack trace or diagnostic information was displayed in the browser due to production error suppression, blocking store managers and cashiers from printing product shelf barcodes and QR stickers.

---

## 2. Root Cause Analysis

A methodical inspection of `apache/logs/error.log`, `php_error_log`, application database schema, and source files revealed three compound root causes:

### Root Cause A: Database Schema Disparity (`categories.status` vs `categories.is_active`)

In `admin/products/qr_labels.php` (which is required by `admin/pos/qr_labels.php`), the category dropdown was populated via:

```php
// Defective query:
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
```

**Schema Truth:**
The actual MariaDB database schema for `categories` table uses:
- `is_active TINYINT(1) DEFAULT 1`
- `status` column does NOT exist in `categories` (unlike `orders` or `pos_shifts`).

**Exception Raised:**
```
PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'status' in 'where clause'
in C:\xampp\htdocs\grocery-store\admin\products\qr_labels.php on line 42
```
Because the PDO connection is configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, the unhandled exception terminated script execution immediately with an HTTP 500 response.

---

### Root Cause B: Strict Typing Violation in Helper Function (`strict_types=1`)

In `admin/products/qr_labels.php`, the file begins with:
```php
declare(strict_types=1);
```

Later in the script, request parameters were read using the global `input()` helper:
```php
$selectedCategory = (int) input('category_id', 0, 'get');
$selectedProduct  = (int) input('product_id', 0, 'get');
$copies           = max(1, min(100, (int) input('copies', 1, 'get')));
```

Notice that the second parameter (`$default`) was passed as integer `0` and `1`.

**Signature Conflict:**
In `public/includes/helpers.php`:
```php
function input(string $key, string $default = '', string $method = 'post'): string
```
In PHP 8.2 with `declare(strict_types=1)`, passing an integer to a strictly typed `string` parameter produces a fatal `TypeError`:

```
Fatal error: Uncaught TypeError: input(): Argument #2 ($default) must be of type string, int given,
called in C:\xampp\htdocs\grocery-store\admin\products\qr_labels.php on line 29
```

---

### Root Cause C: Inappropriate RBAC Permission Gating for POS Cashiers

`admin/products/qr_labels.php` was gated exclusively by:
```php
require_admin_permission('products.view');
```
When POS cashiers or register operators (who hold `pos.access` and `pos.sale`, but not broad administrative `products.view`) clicked the "QR Labels" button directly inside the POS terminal header, access was rejected with a 403 Forbidden exception or redirection loop.

---

### Root Cause D: Unchecked Null Admin in Topbar Avatar Rendering

In `admin/layouts/topbar.php`, the template accessed `$__admin['full_name']` and `$__admin['avatar']` directly without verifying whether `$__admin` was an array or `null`. During early error interception or guest handling, accessing array offsets on `null` triggered fatal `TypeError` in PHP 8.2.

---

## 3. Implemented Fixes

### 1. `admin/products/qr_labels.php` (Schema & RBAC Fixes)
- Fixed category SQL query to select active categories using MariaDB `is_active = 1`:
  ```php
  $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
  ```
- Updated all `input()` invocations to pass string literals:
  ```php
  $selectedCategory = (int) input('category_id', '0', 'get');
  $selectedProduct  = (int) input('product_id', '0', 'get');
  $copies           = max(1, min(100, (int) input('copies', '1', 'get')));
  ```
- Broadened permissions check:
  ```php
  if (!has_admin_permission('products.view') && !has_admin_permission('pos.access') && !has_admin_permission('pos.sale') && !has_admin_permission('pos.manage')) {
      require_admin_permission('products.view');
  }
  ```
- Added quick navigation buttons linking back to the POS Terminal and Product Catalog.

### 2. `public/includes/helpers.php` (Strict Type Union Support)
- Updated `input()` helper to accept union types `string|int|float|null` for `$default`:
  ```php
  function input(string $key, string|int|float|null $default = '', string $method = 'post'): string
  {
      $defaultStr = (string)($default ?? '');
      ...
  ```
  This eliminates fatal TypeErrors throughout the application whenever numeric defaults are supplied under `strict_types=1`.

### 3. `admin/layouts/topbar.php` (Defensive Guard)
- Guarded `$__admin` with `is_array($__admin)` checks and fallback placeholder values so topbar rendering never crashes on missing session data.

### 4. Secondary Files Audited & Hardened
- `admin/inventory/reorder_recommendations.php`: Replaced integer defaults in `input()` calls.
- `admin/reports/cashier_analytics.php`: Replaced integer defaults in `input()` calls.

---

## 4. Verification & Testing

1. **Direct Authenticated CLI Execution:**
   - Script: `scratch/test_qr.php`
   - Result: HTTP 200, 33 categories fetched successfully, zero errors or warnings, complete HTML output with embedded offline QR generation script (`qrcode.min.js`).

2. **Automated Regression Suite:**
   - Script: `tests/pos_v2_regression_test.php`
   - Test #8: `QR labels categories query uses is_active column successfully (33 categories)` — **PASS**.
   - Test #9: `input() helper safely handles int and string defaults under strict_types=1` — **PASS**.
   - Test #12: `Offline QR Code JS library exists locally (no CDN dependency)` — **PASS**.

3. **Research Feature Suite:**
   - Script: `tests/pos_v2_research_features_test.php`
   - Section 1 (Santosa & Wirawan 2019): All 6 assertions PASSED.
