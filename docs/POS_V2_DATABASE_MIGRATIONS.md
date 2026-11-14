# GroCo Grocery Store — POS V2 Database Migrations Record

This document records the database schema design, executed migrations, verification procedures, and rollback instructions for GroCo Grocery Store Point of Sale V2 and Research Integration.

---

## 1. Migration Overview

| Migration | File | Purpose | Engine / Dialect | Status |
|---|---|---|---|---|
| **001** | `admin/migrations/001_pos_optional_upgrades.sql` | Decimal stock/quantities, refund approval limit setting, base indexes | MariaDB 10.4+ / MySQL 8.0+ | `EXECUTED & ACTIVE` |
| **002** | `admin/migrations/002_clothing_and_supershop_pos_seed.sql` | Departmental demo catalog seed for supershop / fashion | MariaDB 10.4+ / MySQL 8.0+ | `EXECUTED & ACTIVE` |
| **003** | `admin/migrations/003_research_pos_v2_features.sql` | Cashier operator event logging, transaction duration timeline metrics, analytics indexes | MariaDB 10.4+ / MySQL 8.0+ | `EXECUTED & ACTIVE` |

---

## 2. Detailed Migration Specifications

### Migration 001: Core POS V2 Numeric Precision & Settings
- **Features Enabled:**
  - Block A: Decimal precision on product inventory and order lines:
    - `products.stock` -> `DECIMAL(10,3)` (allows selling 0.750 kg produce or meat)
    - `products.min_stock` -> `DECIMAL(10,3)`
    - `order_items.quantity` -> `DECIMAL(10,3)`
    - `inventory_logs.quantity` -> `DECIMAL(10,3)`
    - `pos_return_items.quantity` -> `DECIMAL(10,3)`
  - Block B: Configuration key `pos_refund_approval_limit` (manager authorization threshold).
  - Block C: Performance indexes on `orders`, `pos_shifts`, and `inventory_logs`.
- **Safety & Rollback:**
  - Existing integer data automatically casts to decimal without truncation.
  - Rollback modifies decimal columns back to `INT(11)` (only safe if no fractional quantities were sold).

### Migration 002: Retail Supershop & Departmental Categorization
- **Features Enabled:**
  - Departmental categories (Produce, Dairy & Bakery, Meat & Fish, Grocery, Clothing & Fabrics, Household & Cleaning, Snacks & Beverages).
  - Sample products with barcodes and unit weights for test verification.

### Migration 003: Research-Based Supermarket Cashier & Transaction Timeline Architecture
- **Features Enabled:**
  - **Table `pos_operator_logs`:**
    Implements Table 2 from Tomasz Antczak & Rafał Weron (2019):
    ```sql
    CREATE TABLE IF NOT EXISTS `pos_operator_logs` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `operator_id` INT(11) NOT NULL,
      `workstation_id` INT(11) NULL,
      `tran_id` VARCHAR(100) NULL,
      `event_type` VARCHAR(50) NOT NULL COMMENT 'OperatorSignOn, OperatorSignOff, OperatorLock, OperatorUnLock',
      `event_time` DATETIME NOT NULL,
      `metadata` TEXT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX `idx_pos_op_logs_op_time` (`operator_id`, `event_time`),
      INDEX `idx_pos_op_logs_event_type` (`event_type`),
      INDEX `idx_pos_op_logs_time` (`event_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ```
  - **Columns on `pos_transactions`:**
    Implements Table 1 from Tomasz Antczak & Rafał Weron (2019):
    - `begin_time DATETIME`: Timestamp when the cashier scans or registers the first item in the cart.
    - `end_time DATETIME`: Timestamp when payment is confirmed and receipt is emitted.
    - `tran_time INT(11)`: Active transaction duration in seconds ($\text{EndDateTime} - \text{BeginDateTime}$).
    - `break_time INT(11)`: Idle delay in seconds between consecutive transactions ($\text{BeginDateTime}(n) - \text{EndDateTime}(n-1)$).
    - `art_num INT(11)`: Total units / basket size.
    - `is_cash TINYINT(1)`: Flag indicating cash tender.
    - `is_card TINYINT(1)`: Flag indicating card or electronic payment tender.
  - **Analytical Indexes:**
    - `idx_pos_tx_created_at` on `pos_transactions(created_at)`
    - `idx_pos_tx_cashier_time` on `pos_transactions(cashier_id, created_at)`
    - `idx_pos_tx_tran_time` on `pos_transactions(tran_time)`
    - `idx_orders_created_status` on `orders(created_at, status)`
    - `idx_products_barcode` on `products(barcode)`
    - `idx_products_sku` on `products(sku)`
    - `idx_products_stock_min` on `products(stock, min_stock)`

---

## 3. Rollback Instructions

In the event of an emergency schema reversion, execute the following SQL in sequence:

```sql
-- Rollback Migration 003
DROP TABLE IF EXISTS `pos_operator_logs`;

ALTER TABLE `pos_transactions`
  DROP COLUMN IF EXISTS `begin_time`,
  DROP COLUMN IF EXISTS `end_time`,
  DROP COLUMN IF EXISTS `tran_time`,
  DROP COLUMN IF EXISTS `break_time`,
  DROP COLUMN IF EXISTS `art_num`,
  DROP COLUMN IF EXISTS `is_cash`,
  DROP COLUMN IF EXISTS `is_card`;

ALTER TABLE `pos_transactions` DROP INDEX IF EXISTS `idx_pos_tx_tran_time`;
ALTER TABLE `orders` DROP INDEX IF EXISTS `idx_orders_created_status`;
ALTER TABLE `products` DROP INDEX IF EXISTS `idx_products_barcode`;
ALTER TABLE `products` DROP INDEX IF EXISTS `idx_products_sku`;
ALTER TABLE `products` DROP INDEX IF EXISTS `idx_products_stock_min`;
```

---

## 4. Verification Check

To verify all schema objects are valid and accessible in CLI:
```bash
php -r "define('GROCO_CLI_TEST_MODE', true); require 'public/dbconnect.php'; \$c = db()->query('SELECT COUNT(*) FROM pos_operator_logs')->fetchColumn(); echo 'pos_operator_logs ready: ' . \$c . PHP_EOL;"
```
Expected output:
`pos_operator_logs ready: 0` (or count of logged events).
