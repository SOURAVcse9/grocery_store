# GroCo POS V2 — Deep Production Audit & Baseline Assessment

## 1. Executive Summary

This document captures the comprehensive audit of the GroCo Point-of-Sale (POS) system within `c:\xampp\htdocs\grocery-store`. The primary objective is to evaluate every existing POS screen, script, service, database table, workflow, and security boundary to ensure that **POS V2** is an authentic, non-destructive, compatibility-first modernization of the existing production codebase.

---

## 2. Existing POS Screens & File Structure

The POS subsystem is located in `admin/pos/` with domain services in `public/includes/pos/` and client assets in `admin/assets/js/pos.js`.

### 2.1 Admin POS Pages (`admin/pos/`)
| File | Current Role | V2 Evolution Path |
| :--- | :--- | :--- |
| `index.php` | Main Supermarket Counter Terminal | Keep as unified entry point; enhance with 3-column layout (Catalog \| Cart \| Totals & Quick Tenders) |
| `checkout.php` | AJAX Sale Processor Endpoint | Uses `PosTransactionService`; keep as atomic handler with feature flag validation |
| `register.php` | Shift Register & Drawer Manager | Dual support for X-Reading (mid-shift) and Z-Reading (closing reconciliation) |
| `hold-orders.php` | Suspended Cart Handler | Multi-cart parking with cashier/terminal tagging |
| `receipts.php` | Thermal Receipt View & Invoices Directory | Multi-format print engine (80mm / 58mm / A4) with reprint audit tracking |
| `receipt.php` | Legacy receipt redirect / direct render | Maintained for backward compatibility |
| `returns.php` | Dedicated Returns & Exchange Counter | Itemized return processor with restock and ledger postings |
| `reports.php` | POS Sales & Shift Performance Analytics | Extended with average basket size, hourly trends, tender breakdowns |
| `history.php` | Transaction History & Audit Logs | Real-time audit log inspector for supervisors |
| `cashier.php` | Cashier Quick Switch / Terminal lock | Fast pin/password session re-authentication |
| `customers.php` | Customer Directory & Loyalty Manager | Integrated phone lookup, wallet ledger, points redemption |
| `discounts.php` | POS Discount & Coupon Rule Center | Supervisor threshold overrides and active promo rules |
| `shift.php` | Shift management alias / quick action | Backward compatibility alias |

### 2.2 AJAX Handlers (`admin/pos/ajax/`)
| File | Action | Description |
| :--- | :--- | :--- |
| `search_products.php` | Search Catalog | Fast indexed search by name, SKU, barcode, brand, category |
| `barcode.php` | Barcode Lookup | Sub-50ms scanner query returning product, stock, price, unit |
| `search_customer.php` | Customer Lookup | Instant phone and name autocomplete with loyalty balances |
| `create_customer.php` | Quick Registration | Enrolls new walk-in customer directly from POS terminal |
| `process_sale.php` | Sale Processor | Alternative AJAX checkout route using `PosTransactionService` |
| `shifts.php` | Shift State Lookup | Returns active shift status, drawer totals, X-Reading metrics |

### 2.3 Domain Services (`public/includes/pos/`)
| Class Name | Responsibility |
| :--- | :--- |
| `PosService` | Master facade exposing all underlying POS domain components |
| `PosCartService` | Decimal quantities, produce scale weights, line & cart discounts, tax |
| `PosInventoryService` | Row-level locking (`SELECT ... FOR UPDATE`), atomic inventory reduction |
| `PosPaymentService` | Multi-tender validation, MFS, card reference validation, wallet debit |
| `PosTransactionService` | Idempotent transaction processor, sequence generator (`GR-YYYYMMDD-XXXXXX`) |
| `PosShiftService` | Shift lifecycle, float tracking, petty cash in/out, discrepancy calc |
| `PosReturnService` | Itemized returns, restock controller, refund ledger poster |
| `PosCustomerService` | Customer resolver, default walk-in fallback, loyalty points |
| `PosDiscountService` | Supervisor discount limits (`pos_max_discount_pct`), coupon engine |
| `PosSyncService` | Offline outbox batch synchronizer with conflict detection |
| `PosReceiptService` | Dynamic thermal printer data formatter and reprint logger |
| `PosAuditService` | Immutable structured operational event logger |

---

## 3. Existing Database Schema & Relational Structure

The POS database architecture uses normalized tables sharing master data with the online store:

1. **Physical Store Hierarchy**: `pos_stores` (locations), `pos_registers` (checkout lanes), `pos_terminals` (stations).
2. **Shift & Cash Tracking**: `pos_shifts` (drawer sessions), `pos_cash_movements` (petty cash in/out), `pos_drawer_transactions` (legacy logs).
3. **Transactions & Tenders**: `pos_transactions` (master counter sales), `pos_transaction_items` (line items with weights), `pos_payments` (split tender records).
4. **Resilience & Storage**: `pos_suspended_carts` (held baskets), `pos_sync_queue` (offline outbox), `pos_audit_logs` (event logs).
5. **Shared Master Data**: `products` (stock/price source of truth), `orders` (legacy order mirror), `order_items`, `users` (customers), `transactions` (general finance ledger), `coupons`.

---

## 4. Existing Workflows & Strengths

1. **Shared Stock Source of Truth**: Both POS sales and Next.js / PHP storefront orders lock rows and decrement `products.stock`. Zero duplicate inventories.
2. **Unified Customer Accounts**: POS customers are stored in `users` with roles, wallet balances, and reward points shared across online and physical retail channels.
3. **Atomic Financial Posting**: Sales record income in `transactions`, drawer payouts record expense, and returns record refunds without altering historic completed records.
4. **Idempotency Guarantee**: Client UUID deduplication prevents duplicate charges even during network retries.

---

## 5. Weaknesses & Target Opportunities for POS V2

| Area | Current Baseline | POS V2 Target Enhancement |
| :--- | :--- | :--- |
| **UI Layout** | 2-column layout with modal overlays | 3-column layout (Catalog \| Cart \| Fast Tender Panel) with bottom dock shortcuts |
| **Scanner Resilience** | Keystroke listener active | Continuous focus maintainer with hardware scanner wedge detection |
| **Product Scale Handling** | Basic decimal stepper | Native scale weight presets (`+0.1`, `+0.25`, `+0.5`, `+1.0`, `+2.0 kg/liter`) |
| **Quick Tenders** | Number input with exact/presets | 1-touch smart tender calculator ([Exact], [৳50], [৳100], [৳500], [৳1000], [Round up]) |
| **Feature Flagging** | Always active | Configurable `POS_V2_ENABLED` flag with instant rollback capability |
| **Offline Cache** | LocalStorage outbox only | Full client-side catalog indexed cache + LocalStorage outbox for offline catalog lookup |
| **Void & Correction** | Direct return flow | Dedicated Supervisor Void with supervisor PIN/permission requirement |
| **Reporting Analytics** | Basic directory list | Visual performance dashboard with hourly sales velocity, basket metrics, tender pie charts |

---

## 6. Audit Conclusion & Non-Destructive Modernization Plan

The existing POS architecture is solid, strictly typed, and unified with the broader GroCo e-commerce ecosystem. **POS V2 will not rebuild or replace this foundation.** Instead, POS V2 will:
1. Extend existing domain services with additional convenience methods and security guards.
2. Upgrade the frontend UI layout into an ergonomic 3-column desktop supermarket terminal.
3. Add a feature flag (`POS_V2_ENABLED`) allowing operators to toggle between V1 and V2 interfaces.
4. Implement client-side product catalog caching for offline lookup.
5. Provide a full regression test suite (`tests/pos_v2_regression_test.php` and `tests/pos_v2/`) confirming 100% backward compatibility.
