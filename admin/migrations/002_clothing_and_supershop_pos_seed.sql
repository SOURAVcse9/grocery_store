-- ==========================================================================
-- 002_clothing_and_supershop_pos_seed.sql
-- Seed Clothing & Apparel category and sample fashion items for retail POS
-- Also configure Supermarket/Retail BIN and store receipt settings
-- ==========================================================================

-- 1. Ensure Clothing & Apparel category exists
INSERT INTO categories (name, slug, description, icon, is_active, created_at, updated_at)
SELECT 'Clothing & Apparel', 'clothing-apparel', 'Men, women, and kids apparel, shirts, pants, panjabi and fashion items', 'fas fa-tshirt', 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE slug = 'clothing-apparel' OR name = 'Clothing & Apparel'
);

-- 2. Ensure Retail Store & NBR VAT BIN settings exist
INSERT INTO settings (key_name, value, updated_at)
SELECT 'pos_vat_bin', '004189214-0101', NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'pos_vat_bin');

INSERT INTO settings (key_name, value, updated_at)
SELECT 'pos_store_tagline', 'Fresh Supermarket • Apparel & Fashion • Daily Essentials', NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'pos_store_tagline');

INSERT INTO settings (key_name, value, updated_at)
SELECT 'pos_receipt_footer', 'Exchange within 7 days for Clothing & Dry goods with receipt and tag intact. Fresh produce, fish and dairy are non-exchangeable.', NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'pos_receipt_footer');
