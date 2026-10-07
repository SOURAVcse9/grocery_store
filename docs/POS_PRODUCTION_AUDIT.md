# GroCo Enterprise POS System — Comprehensive Production Audit & Architectural Assessment

**Project:** GroCo Grocery Store  
**Scope:** Physical Supermarket Retail Counter + Online E-Commerce Grocery Storefront  
**Date:** October 2026  
**Auditor:** Senior POS Architect, Retail Systems Engineer, Database Architect & Cybersecurity Lead  
**Target Environments:** Online, Offline, and Hybrid Multichannel Retail Environments  

---

## Executive Summary

The GroCo platform operates a dual-channel retail grocery architecture:
1. **Online E-Commerce Storefront / Next.js / PWA**: Customer-facing ordering with delivery options, cart, coupons, and wallet credits.
2. **Admin Counter POS (Point of Sale)**: Cashier desktop interface handling walk-in customers, barcode scanning, shift drawer accounting, split payments, and printed thermal receipts.

While the existing POS implementation handles single-store basic counter checkouts, it requires structural modernization into an enterprise supermarket POS engine capable of:
- Multi-store & multi-terminal isolation (`Store -> Register -> Terminal -> Cashier -> Shift`).
- Barcode-first, keyboard-first high-speed throughput (<100ms per scan).
- Decimal and weighted produce (e.g. 2.75 kg rice @ ৳85.00/kg).
- Deterministic pricing hierarchy with manager price overrides.
- Offline resilience (IndexedDB/local storage cache with robust bidirectional sync and conflict detection).
- Concurrency-safe atomic inventory reservation preventing online/offline overselling.
- Immutable financial audit trail and multi-payment allocations.

---

## 1. Existing POS Architecture

The current POS subsystem resides within `admin/pos/` with frontend assets in `admin/assets/js/pos.js`.

```
[Browser Client (Desktop/Touch)]
       │
       ├─► /admin/pos/index.php (UI Terminal)
       │       ├─► /admin/pos/ajax/search_products.php (Catalog search)
       │       ├─► /admin/pos/ajax/search_customer.php (Customer autocomplete)
       │       ├─► /admin/pos/ajax/create_customer.php (New customer modal)
       │       ├─► /admin/pos/ajax/shifts.php (Shift drawer lifecycle)
       │       ├─► /admin/pos/checkout.php (Sale transaction processor)
       │       └─► /admin/pos/hold-orders.php (Cart suspension)
       │
       ├─► /admin/pos/register.php (Register drawer & X/Z readings)
       ├─► /admin/pos/receipts.php & receipt.php (Thermal print templates)
       └─► /admin/pos/returns.php (Returns & restock)
```

**Underlying Core Services:**
- PDO Singleton Connection via `public/dbconnect.php`.
- RBAC Middleware via `admin/middleware/auth_middleware.php` & `admin/includes/auth_helpers.php`.
- Domain Services: `OrderService`, `InventoryService`, `ProductService`, `CustomerService`, `LoggerService`, `CacheService`.

---

## 2. Existing Database Structure

| Existing Table | Purpose in POS | Limitations Identified |
| :--- | :--- | :--- |
| `pos_shifts` | Cashier shift drawer lifecycle (`admin_id`, `opening_cash`, `closing_cash`, `actual_cash`, `status`) | Missing `store_id`, `register_id`, `terminal_id`, `cash_in`, `cash_out`, `discrepancy_reason`. |
| `pos_drawer_transactions` | Cash-in and cash-out petty cash movements | Missing `admin_id`, supervisor authorization keys, receipt refs. |
| `pos_hold_orders` | Suspended carts payload stored as JSON text | Lacks terminal-specific scoping, expiration timestamps, item snapshots. |
| `pos_returns` & `pos_return_items` | Counter refunds and restock log | Missing original payment method refund allocation, condition tracking. |
| `orders` & `order_items` | Master order records (`POS-YYYYMMDD-XXXX`) | `payment_method` enum limited to `('cod', 'card', 'mobile_banking')`; lacks structured split payment records. |
| `inventory_logs` | Audit movements (`stock_in`, `stock_out`) | Missing `store_id` (assumes single global warehouse stock). |
| `transactions` | Financial accounting ledger (`income`, `expense`) | Stores lumped summary without tender allocation lines. |

---

## 3. Existing POS Workflows

1. **Shift Initiation**:
   - Cashier enters opening cash in modal / `register.php`.
   - Record created in `pos_shifts` (`status = 'open'`).
2. **Item Scanning / Search**:
   - Barcode reader or keyboard input triggers query against `products` (`barcode`, `sku`, `name`).
   - Matching product added to JS client state (`window.touchCart`).
3. **Cart Assembly**:
   - Client adjusts quantities; calculates subtotal and discount overrides.
4. **Checkout**:
   - Payment modal opens; splits entered across Cash, Card, Mobile, Wallet.
   - Payload submitted to `checkout.php` via AJAX POST.
5. **Fulfillment & Receipt**:
   - Atomic transaction decrements `products.stock`, writes `orders`, `order_items`, `inventory_logs`, `transactions`.
   - Browser opens thermal print window `receipts.php?id={order_id}`.

---

## 4. Existing Payment Workflow

- **Supported Types in UI**: Cash, Card, Mobile Banking (bKash, Nagad, Rocket), Wallet Credit, Bank Transfer.
- **Backend Mapping**: Backend collapses all payment methods into `orders.payment_method` enum (`cod`, `card`, `mobile_banking`) based on highest amount paid, and adds transaction string into `orders.note`.
- **Limitation**: No dedicated `pos_payments` relational table exists. If a customer pays ৳1,000 via Cash ৳400 + bKash ৳600, accounting reconciliation cannot easily group cash drawer sales separately from digital merchant gateway settlements.

---

## 5. Existing Inventory Workflow

- Products use a single global column `products.stock` (DECIMAL / INT).
- Deductions execute with `UPDATE products SET stock = stock - ? WHERE id = ?`.
- Changes log to `inventory_logs` with `type = 'stock_out'` and `admin_id`.
- **Limitation**: No multi-store warehouse separation. Online checkout and POS counter compete for the same stock without store location partitioning.

---

## 6. Existing Order Workflow

- POS orders are created as completed sales: `orders.status = 'delivered'`, `orders.payment_status = 'paid'`, `orders.order_number = 'POS-...'`.
- `user_id` falls back to default Walk-in customer record (`phone = '00000000000'`).
- Integrated with customer reward points: 1 point per ৳100 spent for registered customers.

---

## 7. Existing Customer Workflow

- Supports Walk-in customer and registered customer search (`phone`, `name`, `id`).
- Supports inline modal creation of new customers (`admin/pos/ajax/create_customer.php`) with phone uniqueness validation and optional loyalty enrollment.
- Displays customer wallet credit and reward points in loyalty widget.

---

## 8. Existing Discount & Coupon Workflow

- Supports line items and cart discounts.
- `pos_max_discount_pct` setting limits maximum cashier override without supervisor key.
- Manual price overrides supported via `pos.override` permission.
- **Limitation**: No automated Buy X Get Y, bundle deals, or coupon barcode scanning integration.

---

## 9. Existing Receipt Workflow

- Formats: Thermal 80mm/58mm printable receipt.
- Features: Store header, date, order number, cashier name, line items, price, subtotal, discount, grand total, barcode.
- **Limitation**: Hardcoded store address in header; no A4 invoice format toggle; reprint events are not logged in security audit table.

---

## 10. Existing Authentication & Authorization

- Leverages admin session and RBAC permission checks:
  - `pos.access`: Terminal UI access.
  - `pos.sale`: Process sale transactions.
  - `pos.cash`: Shift open/close and drawer cash operations.
  - `pos.override`: Price overrides.
  - `pos.discount`: Discount overrides.
  - `pos.return`: Returns and exchanges.
  - `pos.void`: Void completed sales.
  - `pos.report`: Analytics access.
- Fully compatible with `require_admin_auth()` and dual auth guards.

---

## 11. Existing POS Limitations

1. **Single Store Assumption**: All shifts, inventories, and transactions assume one central physical store.
2. **Missing Terminal Identification**: Inability to isolate multiple lanes/checkout counters within the same branch.
3. **No Offline Mode**: If internet or LAN connection drops, cashier terminal halts completely.
4. **Lack of Relational Payment Allocations**: Split payments stored as text notes rather than itemized ledger rows.
5. **No Weighted Produce Scales Interface**: Assumes integer quantities or manual keypad entry without PLU / weight scale barcode parsing (e.g. EAN-13 in-store price/weight encoded barcodes `20xxxxxyyyyyc`).

---

## 12. Existing Security Risks

1. **Client-Side Pricing Tampering Risk**: Client sending overridden prices without server-side permission validation (mitigated in recent phase, but requires continuous architectural enforcement in domain services).
2. **Double Submission on Rapid Scanning**: Multiple quick checkout button clicks could create duplicate charges without idempotent transaction keys.
3. **Offline Sync Replay**: Offline queued transactions could be replayed if not protected by cryptographically unique UUIDs and server-side duplicate detection.

---

## 13. Existing Concurrency Risks

- When an online e-commerce buyer adds the last unit of stock to cart while an in-store cashier scans the same unit, race conditions during un-locked `SELECT` could lead to negative stock.
- **Resolution**: Enforce `SELECT ... FOR UPDATE` row locks inside atomic PDO transactions across both `OrderService` (storefront) and `PosTransactionService` (POS).

---

## 14. Existing Offline Limitations

- Catalog search calls `ajax/search_products.php` over HTTP. Without network, search fails.
- No local IndexedDB / ServiceWorker transaction outbox exists.

---

## 15. Existing Online / Offline Integration

- Currently unified via single MySQL database: any POS sale immediately updates `products.stock`, which the storefront API/PWA reflects.
- Needs formal multi-store inventory routing so that online orders reserve stock from central fulfillment or designated local branch.

---

## 16. Required Database Changes (Non-Destructive Migrations)

We will introduce new dedicated tables and nullable columns with backward-compatible defaults:

1. `pos_stores`: Store physical branches (Code, Name, Address, Phone, Tax/VAT Number, Currency).
2. `pos_registers`: Physical cash registers / counter stations.
3. `pos_terminals`: Device terminals / browsers tied to registers.
4. `pos_transactions`: Dedicated retail transaction table linking to `orders`, `store_id`, `register_id`, `terminal_id`, `shift_id`, `cashier_id`, unique sequence numbers (`GR-YYYYMMDD-XXXXXX`).
5. `pos_transaction_items`: Itemized transaction lines with regular price, sold price, discount amount, weighted unit flag.
6. `pos_payments`: Relational payment records (`transaction_id`, `method`, `amount`, `reference`, `card_type`, `mobile_provider`, `auth_code`).
7. `pos_cash_movements`: Expanded petty cash movements and shift drawer top-ups.
8. `pos_sync_queue`: Offline transaction sync outbox with status (`pending`, `syncing`, `synced`, `failed`, `conflict`).
9. `pos_audit_logs`: Dedicated immutable POS event audit logger.

---

## 17. Required API Changes

Introduce `/api/v1/pos/` endpoint suite:
- `GET /api/v1/pos/products`: Fast cached catalog search with barcode & PLU indexing.
- `GET /api/v1/pos/products/barcode/{barcode}`: Direct scanner lookup endpoint (<50ms).
- `POST /api/v1/pos/transactions`: Idempotent transaction processor with atomic inventory reservation.
- `POST /api/v1/pos/payments`: Split payment recorder.
- `POST /api/v1/pos/sync`: Offline batch queue synchronizer with conflict detection.
- `POST /api/v1/pos/shifts/open` & `POST /api/v1/pos/shifts/close`: Structured shift drawer management.
- `POST /api/v1/pos/returns` & `POST /api/v1/pos/refunds`: Multi-tender return/refund processor.

---

## 18. Required UI Changes

- **Supermarket Layout**:
  - Header: Live Store, Register, Terminal ID, Active Cashier, Shift Status, Online/Offline Network Status Indicator.
  - Left: Instant Barcode input (auto-focused), Category quick-tiles, Product grid with image & stock indicator.
  - Center: Live High-density Cart Table with quantity steppers, unit selector (pcs, kg, gm, liter), line discount, unit pricing.
  - Right: Grand totals, VAT/Tax summary, tender split quick-buttons (Cash, Card, bKash, Nagad), customer loyalty badge.
  - Footer: Keyboard shortcuts guide (`F1 Search`, `F2 Customer`, `F3 Hold`, `F4 Resume`, `F5 Discount`, `F6 Payment`, `F7 Return`, `F8 Reprint`, `F9 Cash In/Out`, `F10 Complete`).
- **Responsive Scales**: Optimized for 1366x768 and 1920x1080 touch screens.

---

## 19. Required Testing Plan

1. **Unit & Domain Tests**: `PosCartService`, `PosTransactionService`, `PosPaymentService`, `PosInventoryService`, `PosShiftService`.
2. **Concurrency Tests**: 100 concurrent sales against identical SKU verifying zero stock drift and zero race overselling.
3. **Offline Sync & Idempotency Tests**: Offline queue submission, replay attack prevention, duplicate key rejection.
4. **Weighted Produce Tests**: Decimal quantity calculations (e.g. 1.345 kg @ ৳120/kg = ৳161.40).
5. **Return/Refund Tests**: Partial item return restock verification and payment method ledger reimbursement.
6. **Backward Compatibility**: Full regression run against all 106 existing application assertions.
