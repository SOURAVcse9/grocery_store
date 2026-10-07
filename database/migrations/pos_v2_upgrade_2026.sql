-- ==============================================================================
-- GroCo POS V2 Database Migration & Modernization Script (2026)
-- ==============================================================================
-- Preserves 100% of existing tables, data, orders, and transactions.
-- Adds POS V2 operational settings, optimized scanning indexes, and configuration.
-- ==============================================================================

-- 1. Insert POS V2 Configuration Flags into settings table
INSERT INTO settings (`key_name`, `value`)
VALUES 
    ('pos_v2_enabled', '1'),
    ('pos_max_discount_pct', '15.00'),
    ('pos_auto_print_receipt', '1'),
    ('pos_default_receipt_format', '80mm'),
    ('pos_barcode_auto_add', '1')
ON DUPLICATE KEY UPDATE 
    `value` = VALUES(`value`),
    `updated_at` = CURRENT_TIMESTAMP;

-- 2. Optimize Product Scanning Compound Indexes
SET @dbname = DATABASE();
SET @tablename = "products";

-- Index on barcode + is_active
SET @indexname = "idx_products_barcode_active";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_products_barcode_active ON products(barcode, is_active, stock)"
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- Index on sku + is_active
SET @indexname = "idx_products_sku_active";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_products_sku_active ON products(sku, is_active, stock)"
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- 3. Optimize POS Transactions Reporting Indexes
SET @tablename = "pos_transactions";

SET @indexname = "idx_pos_tx_store_date";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_pos_tx_store_date ON pos_transactions(store_id, created_at)"
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;
