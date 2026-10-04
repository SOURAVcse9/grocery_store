-- ==============================================================================
-- GroCo Grocery Store — Production SEO & Cloudinary Image Schema Upgrade
-- ==============================================================================
-- Safe, idempotent SQL migration for production e-commerce SEO & Cloudinary metadata.
-- ==============================================================================

-- 1. Upgrade products table
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `image_public_id` VARCHAR(255) NULL AFTER `thumbnail`,
    ADD COLUMN IF NOT EXISTS `image_alt` VARCHAR(255) NULL AFTER `image_public_id`,
    ADD COLUMN IF NOT EXISTS `image_width` INT UNSIGNED NULL AFTER `image_alt`,
    ADD COLUMN IF NOT EXISTS `image_height` INT UNSIGNED NULL AFTER `image_width`,
    ADD COLUMN IF NOT EXISTS `seo_title` VARCHAR(255) NULL AFTER `meta_description`,
    ADD COLUMN IF NOT EXISTS `seo_description` TEXT NULL AFTER `seo_title`,
    ADD COLUMN IF NOT EXISTS `og_title` VARCHAR(255) NULL AFTER `seo_description`,
    ADD COLUMN IF NOT EXISTS `og_description` TEXT NULL AFTER `og_title`,
    ADD COLUMN IF NOT EXISTS `og_image` VARCHAR(255) NULL AFTER `og_description`,
    ADD COLUMN IF NOT EXISTS `meta_robots` VARCHAR(100) NULL DEFAULT 'index, follow' AFTER `og_image`;

-- 2. Upgrade product_images gallery table
ALTER TABLE `product_images`
    ADD COLUMN IF NOT EXISTS `image_public_id` VARCHAR(255) NULL AFTER `image_url`,
    ADD COLUMN IF NOT EXISTS `image_alt` VARCHAR(255) NULL AFTER `image_public_id`,
    ADD COLUMN IF NOT EXISTS `image_width` INT UNSIGNED NULL AFTER `image_alt`,
    ADD COLUMN IF NOT EXISTS `image_height` INT UNSIGNED NULL AFTER `image_width`;

-- 3. Upgrade categories table
ALTER TABLE `categories`
    ADD COLUMN IF NOT EXISTS `meta_title` VARCHAR(255) NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `meta_description` TEXT NULL AFTER `meta_title`,
    ADD COLUMN IF NOT EXISTS `image_public_id` VARCHAR(255) NULL AFTER `meta_description`,
    ADD COLUMN IF NOT EXISTS `image_alt` VARCHAR(255) NULL AFTER `image_public_id`,
    ADD COLUMN IF NOT EXISTS `og_title` VARCHAR(255) NULL AFTER `image_alt`,
    ADD COLUMN IF NOT EXISTS `og_description` TEXT NULL AFTER `og_title`;

-- 4. Upgrade brands table
ALTER TABLE `brands`
    ADD COLUMN IF NOT EXISTS `meta_title` VARCHAR(255) NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `meta_description` TEXT NULL AFTER `meta_title`,
    ADD COLUMN IF NOT EXISTS `image_public_id` VARCHAR(255) NULL AFTER `meta_description`,
    ADD COLUMN IF NOT EXISTS `image_alt` VARCHAR(255) NULL AFTER `image_public_id`,
    ADD COLUMN IF NOT EXISTS `og_title` VARCHAR(255) NULL AFTER `image_alt`,
    ADD COLUMN IF NOT EXISTS `og_description` TEXT NULL AFTER `og_title`;

-- 5. Performance composite indexes
-- Note: executed safely via migration runner to check index existence
