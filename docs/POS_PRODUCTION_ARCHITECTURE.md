# GroCo Supermarket POS — Production Architecture Specification (2026)

## 1. Architectural Overview

The GroCo Point-of-Sale (POS) system is designed as an enterprise-grade retail checkout platform tailored for physical supermarket stores and unified with the GroCo online grocery e-commerce catalog.

```
+-------------------------------------------------------------------------------+
|                             Physical Retail Stores                            |
|  [Store: STORE-MAIN] ---> [Register: REG-01] ---> [Terminal: TERM-MAIN-01]   |
+-------------------------------------------------------------------------------+
                                      |
                                      v
+-------------------------------------------------------------------------------+
|                              GroCo POS Layer                                  |
|   - Barcode-First Scanner Engine (< 50ms lookup)                              |
|   - Keyboard Shortcuts Router (F1 - F10, ESC)                                 |
|   - Decimal & Weighted Produce Stepper (Scale Support)                        |
|   - Multi-Tender Split Payment Modal with Quick Cash Presets                  |
|   - Offline Resilient Outbox Queue (LocalStorage + Auto-Sync)                 |
|   - Thermal Receipt Generator (80mm / 58mm / A4 with Barcode)                 |
+-------------------------------------------------------------------------------+
                                      |
                                      v
+-------------------------------------------------------------------------------+
|                       GroCo POS Domain Subsystems                             |
|  - PosCartService        : Decimal rounding, pricing rules, tax & discounts  |
|  - PosInventoryService   : Row-level locking (FOR UPDATE), stock deduction    |
|  - PosPaymentService     : Multi-tender allocation, card/mobile validation    |
|  - PosTransactionService : Atomic sale execution, idempotency, sequence gen   |
|  - PosShiftService       : Cash register drawers, X/Z reports, discrepancies |
|  - PosReturnService      : Itemized returns, restocking, refund accounting    |
|  - PosCustomerService    : Walk-in vs registered, wallet debit, loyalty pts   |
|  - PosSyncService        : Offline queue batch sync, idempotency, audit trail |
|  - PosReceiptService     : Thermal invoice layouts, store metadata, reprints  |
|  - PosAuditService       : Immutable event audit logging                      |
+-------------------------------------------------------------------------------+
                                      |
                                      v
+-------------------------------------------------------------------------------+
|                       Shared MySQL / MariaDB Database                         |
|  pos_stores, pos_registers, pos_terminals, pos_shifts, pos_transactions,      |
|  pos_transaction_items, pos_payments, pos_cash_movements, pos_sync_queue,    |
|  pos_suspended_carts, pos_audit_logs, products, orders, order_items, users   |
+-------------------------------------------------------------------------------+
```

## 2. Store Hierarchy & Terminal Isolation

```
Store (pos_stores)
  └── Cash Registers (pos_registers)
        └── POS Terminals (pos_terminals)
              └── Cashier Shifts (pos_shifts)
                    └── Transactions (pos_transactions)
                          ├── Line Items (pos_transaction_items)
                          └── Tender Allocations (pos_payments)
```

1. **Store Isolation**: Every store has unique physical metadata, VAT Registration Number (BIN), currency, and default operational settings.
2. **Station & Terminal**: Each physical terminal is assigned a unique hardware station identifier (`TERM-MAIN-01`).
3. **Cashier Shifts**: Operating a terminal requires an active open shift drawer. Cashiers cannot process sales without an initialized shift with recorded opening float.

## 3. Concurrency & Overselling Prevention

To eliminate race conditions between counter checkout cashiers and online storefront shoppers:
1. Every sale transaction acquires exclusive row-level database locks using `SELECT ... FOR UPDATE` on all catalog products in the cart.
2. Stocks are verified atomically inside the transaction. If `stock < requested_quantity`, the transaction is rolled back with an immediate descriptive stock exception.
3. Successful checkouts decrement `products.stock` atomically and insert audit log entries in `inventory_logs` and `pos_audit_logs`.

## 4. Offline Resilience & Sync Architecture

1. **Disconnection Detection**: The frontend monitors `navigator.onLine` and `window.addEventListener('offline')`.
2. **Local Queuing**: When disconnected or in poor network conditions, completed transactions are stored in `localStorage` (`groco_pos_offline_queue`) with a client-generated UUID (`term-TIMESTAMP-RANDOM`).
3. **Background Sync Worker**: Automatically triggers synchronization via `POST /api/v1/pos/sync` upon network recovery or every 15 seconds.
4. **Server Idempotency**: The server checks `client_uuid` against `pos_transactions.client_uuid`. If already present, it returns the existing transaction without double-charging or duplicate inventory deduction.
