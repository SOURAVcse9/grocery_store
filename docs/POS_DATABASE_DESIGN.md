# GroCo Supermarket POS — Database Schema & Design (2026)

## 1. Overview of Database Tables

The GroCo POS system utilizes 11 dedicated relational tables designed with 3NF normalization, foreign key constraints, and compound indexing for high-throughput retail operations:

| Table Name | Purpose | Primary Key | Key Indexes |
| :--- | :--- | :--- | :--- |
| `pos_stores` | Physical supermarket store locations | `id` (INT) | `code`, `is_active` |
| `pos_registers` | Physical checkout register lanes | `id` (INT) | `store_id`, `register_number` |
| `pos_terminals` | Terminal hardware devices / stations | `id` (INT) | `register_id`, `terminal_code` |
| `pos_shifts` | Cashier drawer shifts & reconciliations | `id` (INT) | `admin_id`, `store_id`, `status` |
| `pos_transactions` | Master POS retail sales records | `id` (INT) | `transaction_number`, `client_uuid`, `store_id`, `shift_id` |
| `pos_transaction_items` | Itemized line items with weighted & discount details | `id` (INT) | `transaction_id`, `product_id` |
| `pos_payments` | Multi-tender split payment records | `id` (INT) | `transaction_id`, `payment_method` |
| `pos_cash_movements` | Petty cash drawer movements (Paid-In, Paid-Out) | `id` (INT) | `shift_id`, `admin_id`, `type` |
| `pos_suspended_carts` | Held / parked shopping carts | `id` (INT) | `hold_reference`, `store_id`, `status` |
| `pos_sync_queue` | Offline transaction synchronization outbox | `id` (INT) | `client_uuid`, `store_id`, `status` |
| `pos_audit_logs` | Immutable audit trail for all POS operational events | `id` (INT) | `event_type`, `admin_id`, `transaction_id`, `created_at` |

## 2. Table Schemas & Constraints

### 2.1 `pos_stores`
```sql
CREATE TABLE pos_stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(100) NULL,
    vat_reg_no VARCHAR(50) NULL,
    currency VARCHAR(10) DEFAULT 'BDT',
    is_active TINYINT(1) DEFAULT 1,
    is_main TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);
```

### 2.2 `pos_transactions`
```sql
CREATE TABLE pos_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(100) UNIQUE NOT NULL,
    order_id INT NULL,
    client_uuid VARCHAR(100) UNIQUE NULL,
    store_id INT NOT NULL,
    register_id INT NULL,
    terminal_id INT NULL,
    shift_id INT NULL,
    cashier_id INT NOT NULL,
    customer_id INT NULL,
    status ENUM('completed', 'voided', 'returned', 'partial_return', 'suspended') DEFAULT 'completed',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    change_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    rounding_amount DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    is_offline TINYINT(1) DEFAULT 0,
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES pos_stores(id) ON DELETE RESTRICT,
    FOREIGN KEY (cashier_id) REFERENCES admins(id) ON DELETE RESTRICT
);
```

### 2.3 `pos_transaction_items`
```sql
CREATE TABLE pos_transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100) NULL,
    product_barcode VARCHAR(100) NULL,
    unit VARCHAR(20) DEFAULT 'pcs',
    is_weighted TINYINT(1) DEFAULT 0,
    regular_price DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    price_overridden TINYINT(1) DEFAULT 0,
    override_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES pos_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);
```

### 2.4 `pos_payments`
```sql
CREATE TABLE pos_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    payment_method ENUM('cash', 'card', 'bkash', 'nagad', 'rocket', 'wallet', 'bank_transfer', 'other') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    card_type VARCHAR(50) NULL,
    card_last_four VARCHAR(4) NULL,
    card_auth_code VARCHAR(100) NULL,
    card_bank VARCHAR(100) NULL,
    mobile_provider VARCHAR(50) NULL,
    transaction_reference VARCHAR(100) NULL,
    status ENUM('success', 'failed', 'refunded', 'pending') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES pos_transactions(id) ON DELETE CASCADE
);
```
