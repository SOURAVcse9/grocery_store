# GROCO POS V2 — SECURITY AND DATABASE AUDIT

**Date:** October 16, 2026  
**Audited System:** GroCo Grocery Store POS V2 Engine  
**Database:** MariaDB 10.4.32 / MySQL Compatible (`grocery_store`)  
**Security Framework:** CSRF Protection, Role-Based Access Control (RBAC), Server-Side Price/Stock Authority, Idempotent Transaction Keys

---

## 1. Security Architecture & RBAC Audit

### Administrative Authentication & Permission Enforcement
All POS controllers and background endpoints require active session authentication via `require_admin_auth()` and granular permissions:

| Endpoint | Required Permission | Defense Layer |
| :--- | :--- | :--- |
| `admin/pos/index.php` | `pos.access` or `pos.sale` or `pos.manage` | Redirect to login if unauthenticated; HTTP 403 if missing RBAC permission |
| `admin/pos/checkout.php` | `pos.sale` | Server-authoritative inventory checks, transaction row locks, monetary total recalculated server-side |
| `admin/pos/register.php` | `pos.cash` | RBAC gate on opening shifts, closing shifts, safe drops, and cash audit readings |
| `admin/pos/ajax/customer_history.php` | `pos.sale` / `pos.access` / `pos.manage` / `pos.cash` | HTTP 401 on missing session; HTTP 403 on missing permission; SQL parameterization |
| `admin/pos/ajax/operator_events.php` | `pos.access` / `pos.sale` | CSRF token validation; cashier lock/unlock timeline tracking |
| `admin/inventory/reorder_recommendations.php` | `products.view` | RBAC permission check; parameter binding for window and category filters |
| `admin/pos/qr_labels.php` | `products.view` or `pos.access` or `pos.sale` | Granular permission check; SQL parameterized query |

---

## 2. Server-Authoritative Monetary Safety & Drawer Mathematics

### The Physical Cash Drawer Principle
The GroCo retail POS engine enforces strict separation between physical cash in the drawer and electronic non-cash payments:
1. **Physical Drawer Expected Cash:**  
   $$\text{Expected Cash} = \text{Opening Cash} + \text{Cash Sales} + \text{Cash In} - \text{Cash Out} - \text{Cash Refunds}$$
2. **Electronic Tenders (Card, bKash, Nagad, Rocket, Bank Transfer, Wallet Credits):**  
   These payments settle into bank and digital merchant accounts directly. They are recorded in `pos_payments` and orders markers for turnover accounting, but are **never** added to the physical cash drawer reconciliation count.
3. **Discrepancy / Variance Integrity:**  
   When a shift closes, the counted cash entered by the cashier is recorded directly as `actual_cash` without automated adjustments or silent write-offs. Any surplus or shortage is permanently retained in `pos_shifts` for supervisory auditing.

---

## 3. Concurrency, Row-Level Locking & Oversell Defense

### Negative Inventory Prevention
1. In `admin/pos/checkout.php`, each cart line product is queried using `SELECT ... FOR UPDATE` within an active database transaction (`$pdo->beginTransaction()`).
2. If `requested_qty > available_stock`, the transaction triggers a `PosException` and executes `$pdo->rollBack()`.
3. Simultaneous checkout requests cannot race or oversell stock into negative values.

### Replay Defense & Idempotency
1. Checkout submissions generate a client UUID (`idempotency_key`) stored in session storage per sale attempt.
2. If network packets drop and the cashier clicks "Confirm Sale" a second time, the server queries `pos_audit_logs` / `orders` for the idempotency key.
3. The server returns the original order confirmation without double-deducting stock or recording duplicate payments.

---

## 4. CSRF & HTTP Security Headers

### Permissions-Policy Header Configuration
- Root `.htaccess` and `public/.htaccess` define:
  ```apache
  Header always set Permissions-Policy "camera=(self), microphone=(), geolocation=()"
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set X-XSS-Protection "1; mode=block"
  ```
- **Security Assessment:**
  Restricting `camera` to `(self)` allows the POS application to operate camera QR decoding on its own origin while preventing unauthorized cross-origin iframe hijacking. Unused sensors (`microphone`, `geolocation`) remain strictly blocked `()`.

### CSRF Protection on Mutating Actions
- State-changing actions (`open_shift`, `close_shift`, `drawer_tx`, `checkout`) enforce `verify_csrf_or_fail()` before executing SQL statements.
- Session tokens are generated using cryptographically secure random bytes (`random_bytes(32)`) and hashed comparisons.

---

## 5. Database Schema Integrity Audit

An automated inspection verified that all production tables, primary keys, foreign constraints, and column structures remain healthy:

| Table | Status | Key Columns Verified |
| :--- | :---: | :--- |
| `products` | Clean | `id`, `name`, `sku`, `barcode`, `price`, `cost_price`, `stock`, `min_stock`, `is_active`, `deleted_at` |
| `categories` | Clean | `id`, `name`, `slug`, `is_active` (`TINYINT(1)` confirmed; no invalid `status` references) |
| `orders` | Clean | `id`, `order_number`, `user_id`, `total_amount`, `payment_method`, `payment_status`, `status`, `note` |
| `order_items` | Clean | `id`, `order_id`, `product_id`, `product_name`, `product_sku`, `price`, `quantity`, `line_total` |
| `pos_shifts` | Clean | `id`, `admin_id`, `opening_cash`, `closing_cash`, `actual_cash`, `status`, `start_time`, `end_time` |
| `pos_drawer_transactions`| Clean | `id`, `shift_id`, `admin_id`, `type`, `amount`, `notes`, `created_at` |
| `pos_transactions` | Clean | `id`, `shift_id`, `order_id`, `art_num`, `tran_time`, `begin_time`, `end_time`, `is_cash`, `is_card` |
| `pos_operator_logs` | Clean | `id`, `shift_id`, `admin_id`, `event_type`, `created_at` (timeline logging intact) |
| `pos_returns` | Clean | `id`, `order_id`, `admin_id`, `refund_amount`, `refund_method`, `created_at` |
| `system_license` | Active | Development license `GRCO-••••-••••-••••-2CE4` verified active for `localhost` |

---

## 6. Audit Conclusion

The GroCo POS V2 subsystem is robust, secure, and production-ready. No vulnerabilities, permission bypasses, schema corruptions, or financial calculation anomalies were detected.
