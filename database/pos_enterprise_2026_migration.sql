-- ==============================================================================
-- database/pos_enterprise_2026_migration.sql
-- ==============================================================================
-- GroCo Enterprise Supermarket POS Subsystem Database Migration
-- Fully non-destructive, multi-store ready, atomic concurrency & audit logging.
-- ==============================================================================

-- 1. POS Physical Stores & Outlets
CREATE TABLE IF NOT EXISTS pos_stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(100) NULL,
    vat_reg_no VARCHAR(100) NULL,
    currency VARCHAR(10) DEFAULT 'BDT',
    is_active TINYINT(1) DEFAULT 1,
    is_main TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store_code (code),
    INDEX idx_store_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Physical Cash Registers / Lanes
CREATE TABLE IF NOT EXISTS pos_registers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    register_number VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NULL,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_store_register (store_id, register_number),
    FOREIGN KEY (store_id) REFERENCES pos_stores(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Device Terminals (Workstations / Browsers)
CREATE TABLE IF NOT EXISTS pos_terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_id INT NOT NULL,
    terminal_code VARCHAR(100) UNIQUE NOT NULL,
    device_fingerprint VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_sync_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (register_id) REFERENCES pos_registers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Alter pos_shifts to attach store, register, terminal tracking safely if columns do not exist
SET @dbname = DATABASE();
SET @tablename = "pos_shifts";

-- Check store_id
SET @columnname = "store_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE pos_shifts ADD COLUMN store_id INT NULL AFTER admin_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check register_id
SET @columnname = "register_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE pos_shifts ADD COLUMN register_id INT NULL AFTER store_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check terminal_id
SET @columnname = "terminal_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE pos_shifts ADD COLUMN terminal_id INT NULL AFTER register_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 5. Enterprise POS Transactions
CREATE TABLE IF NOT EXISTS pos_transactions (
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
    INDEX idx_pos_tx_num (transaction_number),
    INDEX idx_pos_tx_store (store_id),
    INDEX idx_pos_tx_shift (shift_id),
    INDEX idx_pos_tx_cashier (cashier_id),
    INDEX idx_pos_tx_date (created_at),
    INDEX idx_pos_tx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. POS Transaction Line Items
CREATE TABLE IF NOT EXISTS pos_transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100) NULL,
    product_barcode VARCHAR(100) NULL,
    unit VARCHAR(50) DEFAULT 'pcs',
    is_weighted TINYINT(1) DEFAULT 0,
    regular_price DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    price_overridden TINYINT(1) DEFAULT 0,
    override_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_item_tx (transaction_id),
    INDEX idx_pos_item_product (product_id),
    FOREIGN KEY (transaction_id) REFERENCES pos_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. POS Relational Multi-Tender Payments
CREATE TABLE IF NOT EXISTS pos_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    payment_method ENUM('cash', 'card', 'bkash', 'nagad', 'rocket', 'bank_transfer', 'wallet', 'gift_card', 'other') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    tendered_amount DECIMAL(12,2) NOT NULL,
    change_amount DECIMAL(12,2) DEFAULT 0.00,
    reference_no VARCHAR(100) NULL,
    card_type VARCHAR(50) NULL,
    card_last_four VARCHAR(4) NULL,
    mobile_provider VARCHAR(50) NULL,
    bank_name VARCHAR(100) NULL,
    auth_code VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_pay_tx (transaction_id),
    INDEX idx_pos_pay_method (payment_method),
    FOREIGN KEY (transaction_id) REFERENCES pos_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Enhanced POS Cash Movements
CREATE TABLE IF NOT EXISTS pos_cash_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    admin_id INT NOT NULL,
    type ENUM('cash_in', 'cash_out', 'safe_drop', 'petty_cash') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    authorized_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_cash_shift (shift_id),
    FOREIGN KEY (shift_id) REFERENCES pos_shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. POS Suspended Carts
CREATE TABLE IF NOT EXISTS pos_suspended_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hold_reference VARCHAR(100) UNIQUE NOT NULL,
    store_id INT NOT NULL,
    register_id INT NULL,
    terminal_id INT NULL,
    shift_id INT NULL,
    cashier_id INT NOT NULL,
    customer_id INT NULL,
    cart_payload JSON NOT NULL,
    hold_notes VARCHAR(255) NULL,
    status ENUM('active', 'resumed', 'expired', 'cancelled') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hold_ref (hold_reference),
    INDEX idx_hold_store (store_id),
    INDEX idx_hold_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. POS Offline Sync Queue
CREATE TABLE IF NOT EXISTS pos_sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_uuid VARCHAR(100) UNIQUE NOT NULL,
    store_id INT NOT NULL,
    terminal_id INT NULL,
    action_type ENUM('sale', 'return', 'shift_open', 'shift_close', 'cash_movement') NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'syncing', 'synced', 'failed', 'conflict') DEFAULT 'pending',
    retry_count INT DEFAULT 0,
    error_message TEXT NULL,
    server_entity_id INT NULL,
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sync_uuid (client_uuid),
    INDEX idx_sync_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. POS Audit Logs
CREATE TABLE IF NOT EXISTS pos_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    admin_id INT NULL,
    store_id INT NULL,
    register_id INT NULL,
    terminal_id INT NULL,
    transaction_id INT NULL,
    ip_address VARCHAR(45) NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_event (event_type),
    INDEX idx_audit_admin (admin_id),
    INDEX idx_audit_tx (transaction_id),
    INDEX idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Seed Default Physical Store, Register, and Terminal
INSERT IGNORE INTO pos_stores (id, code, name, address, phone, email, vat_reg_no, currency, is_active, is_main)
VALUES (1, 'STORE-MAIN', 'GroCo Main Superstore', 'Road 4, Mid Badda, Dhaka-1212, Bangladesh', '+8801700000000', 'superstore@groco.site', 'BIN-192837465', 'BDT', 1, 1);

INSERT IGNORE INTO pos_registers (id, store_id, register_number, name, status)
VALUES (1, 1, 'REG-01', 'Main Express Counter 1', 'active');

INSERT IGNORE INTO pos_terminals (id, register_id, terminal_code, is_active)
VALUES (1, 1, 'TERM-MAIN-01', 1);
