-- ==============================================================================
-- GroCo Grocery Store — SSLCOMMERZ Payment System Migration
-- ==============================================================================
-- Defines payments, payment_refunds, payment_audit_logs, payment_webhook_events,
-- and enhances orders table for enterprise online payment processing.
-- ==============================================================================

-- 1. Enhance orders table payment fields
ALTER TABLE `orders` 
    MODIFY COLUMN `payment_method` ENUM('cod','card','mobile_banking','sslcommerz') DEFAULT 'cod',
    MODIFY COLUMN `payment_status` ENUM('unpaid','paid','refunded','partially_refunded','failed','cancelled') DEFAULT 'unpaid';

-- Ensure inventory_deducted column exists to prevent double deduction / double restoration
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'inventory_deducted');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `orders` ADD COLUMN `inventory_deducted` TINYINT(1) NOT NULL DEFAULT 1 AFTER `payment_status`', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Core Payments Ledger Table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `payment_attempt_id` VARCHAR(50) NOT NULL,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'sslcommerz',
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'sslcommerz',
    `tran_id` VARCHAR(30) NOT NULL UNIQUE,
    `sessionkey` VARCHAR(255) NULL,
    `gateway_transaction_id` VARCHAR(100) NULL,
    `bank_tran_id` VARCHAR(100) NULL,
    `val_id` VARCHAR(100) NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `store_amount` DECIMAL(12,2) NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'BDT',
    `card_type` VARCHAR(100) NULL,
    `card_no` VARCHAR(50) NULL,
    `bank_status` VARCHAR(50) NULL,
    `status` ENUM('INITIATED','PENDING','PROCESSING','PAID','FAILED','CANCELLED','EXPIRED','REFUND_PENDING','PARTIALLY_REFUNDED','REFUNDED','REVIEW_REQUIRED') NOT NULL DEFAULT 'INITIATED',
    `risk_level` VARCHAR(20) DEFAULT '0',
    `risk_title` VARCHAR(255) NULL,
    `gateway_status` VARCHAR(50) NULL,
    `verification_status` VARCHAR(50) NOT NULL DEFAULT 'UNVERIFIED',
    `initiated_at` DATETIME NULL,
    `processing_at` DATETIME NULL,
    `paid_at` DATETIME NULL,
    `failed_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `expired_at` DATETIME NULL,
    `refund_status` VARCHAR(50) NULL,
    `refunded_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `failure_reason` TEXT NULL,
    `metadata` LONGTEXT NULL,
    `ipn_received` TINYINT(1) NOT NULL DEFAULT 0,
    `ipn_received_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_payments_order_id` (`order_id`),
    INDEX `idx_payments_user_id` (`user_id`),
    INDEX `idx_payments_status` (`status`),
    INDEX `idx_payments_bank_tran` (`bank_tran_id`),
    INDEX `idx_payments_val_id` (`val_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Dedicated Payment Refunds Table
CREATE TABLE IF NOT EXISTS `payment_refunds` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_id` BIGINT UNSIGNED NOT NULL,
    `order_id` INT NOT NULL,
    `refund_trans_id` VARCHAR(100) NOT NULL UNIQUE,
    `bank_tran_id` VARCHAR(100) NOT NULL,
    `refund_amount` DECIMAL(12,2) NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('PENDING','PROCESSING','COMPLETED','FAILED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `refund_ref_id` VARCHAR(100) NULL,
    `error_reason` TEXT NULL,
    `gateway_response` LONGTEXT NULL,
    `requested_by` INT NULL,
    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_refunds_payment_id` (`payment_id`),
    INDEX `idx_refunds_order_id` (`order_id`),
    INDEX `idx_refunds_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Audit & Event Trail Table
CREATE TABLE IF NOT EXISTS `payment_audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_id` BIGINT UNSIGNED NULL,
    `order_id` INT NULL,
    `tran_id` VARCHAR(50) NULL,
    `event` VARCHAR(100) NOT NULL,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'sslcommerz',
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NULL,
    `amount` DECIMAL(12,2) NULL,
    `currency` VARCHAR(10) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NULL,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_payment_id` (`payment_id`),
    INDEX `idx_audit_order_id` (`order_id`),
    INDEX `idx_audit_tran_id` (`tran_id`),
    INDEX `idx_audit_event` (`event`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Webhook Events Table (IPN & Callback Replay Protection)
CREATE TABLE IF NOT EXISTS `payment_webhook_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(50) NOT NULL,
    `tran_id` VARCHAR(50) NULL,
    `val_id` VARCHAR(100) NULL,
    `payload_hash` VARCHAR(64) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `headers` LONGTEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `processed` TINYINT(1) NOT NULL DEFAULT 0,
    `process_result` VARCHAR(50) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_webhook_tran_id` (`tran_id`),
    INDEX `idx_webhook_val_id` (`val_id`),
    INDEX `idx_webhook_hash` (`payload_hash`),
    INDEX `idx_webhook_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
