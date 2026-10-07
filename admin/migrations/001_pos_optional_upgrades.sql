-- =============================================================================
-- GroCo Admin — POS upgrade: OPTIONAL database migration
-- =============================================================================
-- The upgraded admin/ folder works WITHOUT running this file (whole-number
-- quantities, existing tables). Run it only to unlock the features noted below.
--
-- BACK UP YOUR DATABASE FIRST.  This file contains no DROP / TRUNCATE / DELETE.
-- Review each block against your real schema before running — the admin/ folder
-- was delivered without the main project's schema, so column definitions below
-- are written from how the code uses them.
--
-- Verify current types first:
--   SELECT table_name, column_name, column_type FROM information_schema.columns
--   WHERE table_schema = DATABASE()
--     AND (table_name, column_name) IN (('products','stock'),('products','min_stock'),
--          ('order_items','quantity'),('inventory_logs','quantity'),
--          ('inventory_logs','remaining_stock'),('pos_return_items','quantity'));
-- =============================================================================

-- -----------------------------------------------------------------------------
-- A) FRACTIONAL QUANTITIES  (unlocks 2.75 kg × ৳85 = ৳233.75 at the POS)
--    Until ALL of products.stock, order_items.quantity, inventory_logs.quantity
--    are DECIMAL, the POS rejects fractional quantities (it never rounds silently).
--    The storefront (outside admin/) should be reviewed to make sure it tolerates
--    decimal stock values before you run this on a shared database.
-- -----------------------------------------------------------------------------
ALTER TABLE products          MODIFY stock           DECIMAL(12,3) NOT NULL DEFAULT 0;
ALTER TABLE products          MODIFY min_stock       DECIMAL(12,3) NOT NULL DEFAULT 0;   -- skip if the column does not exist
ALTER TABLE order_items       MODIFY quantity        DECIMAL(12,3) NOT NULL;
ALTER TABLE inventory_logs    MODIFY quantity        DECIMAL(12,3) NOT NULL;
ALTER TABLE inventory_logs    MODIFY remaining_stock DECIMAL(12,3) NOT NULL;
ALTER TABLE pos_return_items  MODIFY quantity        DECIMAL(12,3) NOT NULL;

-- -----------------------------------------------------------------------------
-- B) SETTINGS used by the new POS rules (all optional; safe defaults are built in)
--    pos_max_discount_pct       existing key — max % discount a cashier may give (default 15)
--    pos_refund_approval_limit  NEW — refunds above this amount need pos.override (0 = no limit)
-- -----------------------------------------------------------------------------
INSERT INTO settings (key_name, value, updated_at)
SELECT 'pos_refund_approval_limit', '0', NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'pos_refund_approval_limit');

-- -----------------------------------------------------------------------------
-- C) PERFORMANCE INDEXES (barcode scan + POS reporting). MariaDB syntax;
--    on MySQL 8 remove "IF NOT EXISTS" and skip any index that already exists.
-- -----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_products_barcode   ON products (barcode);
CREATE INDEX IF NOT EXISTS idx_products_sku       ON products (sku);
CREATE INDEX IF NOT EXISTS idx_orders_created     ON orders (created_at);
CREATE INDEX IF NOT EXISTS idx_pos_returns_order  ON pos_returns (order_id);
CREATE INDEX IF NOT EXISTS idx_pos_shifts_admin   ON pos_shifts (admin_id, status);
CREATE INDEX IF NOT EXISTS idx_invlogs_product    ON inventory_logs (product_id, created_at);
