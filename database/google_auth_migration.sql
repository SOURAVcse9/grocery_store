-- ==============================================================================
-- database/google_auth_migration.sql
-- ==============================================================================
-- Safe, idempotent migration for GroCo Two-Tier Authentication Architecture:
-- 1. Adds google_id, first_name, last_name to users table and permits NULL passwords.
-- 2. Adds must_change_password, password_changed_at, password_created_at to admins.
-- ==============================================================================

-- 1. Extend users table for Google OAuth 2.0 / OpenID Connect
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_id');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(100) NULL UNIQUE AFTER `role_id`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'first_name');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `first_name` VARCHAR(50) NULL AFTER `full_name`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'last_name');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `last_name` VARCHAR(50) NULL AFTER `first_name`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Allow nullable password for Google-authenticated customer accounts
ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NULL DEFAULT NULL;

-- 2. Extend admins table for One-Time Passwords & Force First-Login Password Change
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'must_change_password');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `admins` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'password_changed_at');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `admins` ADD COLUMN `password_changed_at` DATETIME NULL AFTER `must_change_password`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'password_created_at');
SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `admins` ADD COLUMN `password_created_at` DATETIME NULL AFTER `password_changed_at`', 'SELECT 1');
PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;
