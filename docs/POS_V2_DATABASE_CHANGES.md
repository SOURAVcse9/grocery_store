# GroCo POS V2 — Database Architecture & Migration Log (2026)

## 1. Safety & Data Preservation Mandate

In strict accordance with enterprise data protection standards:
- **Zero Existing Tables Dropped**: All legacy tables (`orders`, `order_items`, `products`, `users`, `transactions`, `inventory_logs`, `pos_shifts`, `pos_drawer_transactions`, `pos_hold_orders`, `pos_returns`, `pos_return_items`) remain 100% active and intact.
- **Zero Data Deleted**: All historical sales records, customer balances, and transaction logs are preserved.
- **Non-Destructive Enhancements**: Added only targeted composite indexes and configuration keys.

---

## 2. Applied Migrations Summary

Migration file: [`database/migrations/pos_v2_upgrade_2026.sql`](file:///c:/xampp/htdocs/grocery-store/database/migrations/pos_v2_upgrade_2026.sql)

### 2.1 Settings & Operational Configuration Flags
| Key Name | Default Value | Purpose |
| :--- | :--- | :--- |
| `pos_v2_enabled` | `1` | Master feature flag for POS V2 terminal interface |
| `pos_max_discount_pct` | `15.00` | Maximum discount percentage permitted before supervisor override key |
| `pos_auto_print_receipt` | `1` | Automatically trigger print dialog upon sale completion |
| `pos_default_receipt_format` | `80mm` | Default thermal paper width (`80mm` / `58mm` / `a4`) |
| `pos_barcode_auto_add` | `1` | Instantly add exact barcode matches to active cart |

### 2.2 Performance Compound Indexes
| Target Table | Index Name | Indexed Columns | Objective |
| :--- | :--- | :--- | :--- |
| `products` | `idx_products_barcode_active` | `(barcode, is_active, stock)` | Sub-50ms hardware barcode scanner lookups |
| `products` | `idx_products_sku_active` | `(sku, is_active, stock)` | Fast SKU search and stock availability check |
| `pos_transactions` | `idx_pos_tx_store_date` | `(store_id, created_at)` | High-speed daily shift and store sales reporting queries |

---

## 3. Relational Schema ERD

```
+-------------------+        +--------------------+        +-------------------+
|    pos_stores     |1      *|   pos_registers    |1      *|   pos_terminals   |
+-------------------+<-------+--------------------+<-------+-------------------+
| id (PK)           |        | id (PK)            |        | id (PK)           |
| code              |        | store_id (FK)      |        | register_id (FK)  |
| name              |        | register_number    |        | terminal_code     |
+---------+---------+        +---------+----------+        +---------+---------+
          |                            |                             |
          |1                           |1                            |1
          |                            |                             |
          |*                           |*                            |*
+---------+----------------------------+-----------------------------+---------+
|                                  pos_shifts                                  |
+------------------------------------------------------------------------------+
| id (PK), admin_id (FK), store_id (FK), register_id (FK), terminal_id (FK)    |
| opening_cash, closing_cash, actual_cash, status ('open'/'closed')            |
+--------------------------------------+---------------------------------------+
                                       |1
                                       |*
+--------------------------------------+---------------------------------------+
|                               pos_transactions                               |
+------------------------------------------------------------------------------+
| id (PK), transaction_number (UNI), order_id (FK), client_uuid (UNI)          |
| store_id (FK), register_id, terminal_id, shift_id (FK), cashier_id (FK)      |
| customer_id (FK), status, subtotal, discount, tax, total, paid, change        |
+-------------------+----------------------------------+-----------------------+
                    |1                                 |1
                    |*                                 |*
+-------------------+------------------+   +-----------+-----------------------+
|       pos_transaction_items          |   |             pos_payments          |
+--------------------------------------+   +-----------------------------------+
| id (PK), transaction_id (FK)         |   | id (PK), transaction_id (FK)      |
| product_id (FK), product_name, sku   |   | payment_method, amount            |
| unit, is_weighted, regular_price     |   | card_type, card_last_four         |
| unit_price, quantity, discount, total|   | card_auth_code, card_bank         |
+--------------------------------------+   | mobile_provider, transaction_ref  |
                                           +-----------------------------------+
```
