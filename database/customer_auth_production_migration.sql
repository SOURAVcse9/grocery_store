-- ==============================================================================
-- database/customer_auth_production_migration.sql
-- ==============================================================================
-- Safe, idempotent migration for GroCo Production Customer Authentication:
-- 1. Adds session_version, failed_logins, last_password_change to users table.
-- 2. Ensures password_resets table exists with proper indexes and columns.
-- ==============================================================================

-- 1. Extend users table with session_version for multi-device session revocation
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'session_version');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `session_version` INT NOT NULL DEFAULT 1 AFTER `is_active`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure failed_logins exists on users
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'failed_logins');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `failed_logins` INT NOT NULL DEFAULT 0 AFTER `session_version`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure last_password_change exists on users
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_password_change');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `last_password_change` DATETIME NULL AFTER `failed_logins`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure password can be NULL (for Google-only customer accounts)
ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NULL DEFAULT NULL;

-- 2. Ensure password_resets table exists
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pwreset_token` (`token`),
    INDEX `idx_pwreset_user` (`user_id`),
    INDEX `idx_pwreset_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
