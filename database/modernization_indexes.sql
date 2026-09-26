-- ==============================================================================
-- GroCo Database Performance & Index Modernization Migration
-- ==============================================================================
-- Adds non-destructive compound indexes to eliminate full-table scans
-- on high-frequency storefront, catalog, and checkout queries.
-- ==============================================================================

-- 1. Index products by status, category, brand, and stock
ALTER TABLE products 
  ADD INDEX IF NOT EXISTS idx_products_catalog_perf (is_active, category_id, brand_id, stock);

-- 2. Index orders by user_id and creation date for instant customer portal lookups
ALTER TABLE orders 
  ADD INDEX IF NOT EXISTS idx_orders_user_created (user_id, created_at DESC);

-- 3. Index order items by order_id
ALTER TABLE order_items 
  ADD INDEX IF NOT EXISTS idx_order_items_order (order_id, product_id);

-- 4. Index product reviews by product, approval status, and rating
ALTER TABLE product_reviews 
  ADD INDEX IF NOT EXISTS idx_reviews_product_status (product_id, is_approved, rating);

-- 5. Index customer addresses by user and default status
ALTER TABLE addresses 
  ADD INDEX IF NOT EXISTS idx_addresses_user (user_id, is_default);
