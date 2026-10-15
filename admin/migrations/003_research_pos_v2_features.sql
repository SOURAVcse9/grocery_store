-- ==============================================================================
-- GroCo Grocery Store — Migration 003: Research-Based POS V2 Features
-- ==============================================================================
-- Implements database structures required for:
--  1. High-frequency Supermarket Cashier Operations Event Logging (Antczak & Weron 2019 Table 2)
--  2. High-resolution Transaction Timeline Metrics (Antczak & Weron 2019 Table 1)
--  3. Demand Forecasting & High-Performance Analytical Indexing (Matthew Cote 2015)
-- Safe to rerun (idempotent DDL with IF NOT EXISTS).
-- ==============================================================================

-- 1. Cashier Operations Event Log (Antczak & Weron 2019 Table 2)
-- Stores OperatorSignOn, OperatorSignOff, OperatorLock, OperatorUnLock events
CREATE TABLE IF NOT EXISTS `pos_operator_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `operator_id` INT(11) NOT NULL,
  `workstation_id` INT(11) NULL,
  `tran_id` VARCHAR(100) NULL,
  `event_type` VARCHAR(50) NOT NULL COMMENT 'OperatorSignOn, OperatorSignOff, OperatorLock, OperatorUnLock',
  `event_time` DATETIME NOT NULL,
  `metadata` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pos_op_logs_op_time` (`operator_id`, `event_time`),
  INDEX `idx_pos_op_logs_event_type` (`event_type`),
  INDEX `idx_pos_op_logs_time` (`event_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Transaction Timeline Metrics on pos_transactions (Antczak & Weron 2019 Table 1)
ALTER TABLE `pos_transactions` 
  ADD COLUMN IF NOT EXISTS `begin_time` DATETIME NULL COMMENT 'Timestamp when cashier begins scanning first article',
  ADD COLUMN IF NOT EXISTS `end_time` DATETIME NULL COMMENT 'Timestamp when payment completes and receipt emits',
  ADD COLUMN IF NOT EXISTS `tran_time` INT(11) NULL DEFAULT 0 COMMENT 'Active transaction service duration in seconds (EndDateTime - BeginDateTime)',
  ADD COLUMN IF NOT EXISTS `break_time` INT(11) NULL DEFAULT 0 COMMENT 'Idle/break seconds elapsed since previous transaction on register',
  ADD COLUMN IF NOT EXISTS `art_num` INT(11) NULL DEFAULT 0 COMMENT 'Total article/item count (basket size)',
  ADD COLUMN IF NOT EXISTS `is_cash` TINYINT(1) NULL DEFAULT 0 COMMENT '1 if paid in cash',
  ADD COLUMN IF NOT EXISTS `is_card` TINYINT(1) NULL DEFAULT 0 COMMENT '1 if paid by card or electronic';

-- 3. Optimization Indexes for Analytical Queries & Reporting
-- Safely add indexes if not existing
ALTER TABLE `pos_transactions` 
  ADD INDEX IF NOT EXISTS `idx_pos_tx_created_at` (`created_at`),
  ADD INDEX IF NOT EXISTS `idx_pos_tx_cashier_time` (`cashier_id`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_pos_tx_tran_time` (`tran_time`);

ALTER TABLE `orders` 
  ADD INDEX IF NOT EXISTS `idx_orders_created_status` (`created_at`, `status`);

ALTER TABLE `products` 
  ADD INDEX IF NOT EXISTS `idx_products_barcode` (`barcode`),
  ADD INDEX IF NOT EXISTS `idx_products_sku` (`sku`),
  ADD INDEX IF NOT EXISTS `idx_products_stock_min` (`stock`, `min_stock`);
