# GROCO ENTERPRISE — DATABASE SCHEMA VERIFICATION & ROLLBACK PROCEDURES

**Document ID:** `docs/DATABASE_MIGRATION_AND_ROLLBACK.md`  
**Date:** October 15, 2026  
**Target Database:** `grocery_store` (MariaDB 10.4.32)  
**Status:** **AUTHORITATIVE SCHEMA PRESERVED (NO SCHEMA MIGRATIONS NEEDED)**

---

## 1. Schema State Assessment

During the production audit and POS V2 stabilization, a complete inspection of all 32 database tables was conducted.

### Key Finding:
**Zero database migrations or schema alterations were required.**
The reported 500 error on `admin/pos/qr_labels.php` was caused by defective application SQL querying `WHERE status = 'active'` against the `categories` table. The authoritative MariaDB schema already contained `is_active TINYINT(1) DEFAULT 1`. The fix aligned the application code to the authoritative database schema without altering production tables or column definitions.

---

## 2. Authoritative Table Reference (Key Schemas)

### `categories`
```sql
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `pos_shifts`
```sql
CREATE TABLE `pos_shifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `register_number` varchar(50) NOT NULL DEFAULT 'Register 01',
  `opening_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `closing_cash` decimal(10,2) DEFAULT NULL,
  `expected_cash` decimal(10,2) DEFAULT NULL,
  `difference` decimal(10,2) DEFAULT NULL,
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_status` (`admin_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `pos_transactions` (Antczak & Weron 2019 Dual-Write Ledger)
```sql
CREATE TABLE `pos_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `shift_id` int(10) unsigned DEFAULT NULL,
  `cashier_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('completed','held','refunded','partial_return','voided') NOT NULL DEFAULT 'completed',
  `begin_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `tran_time` int(10) unsigned DEFAULT 0,
  `break_time` int(10) unsigned DEFAULT 0,
  `art_num` decimal(10,3) DEFAULT 0.000,
  `is_cash` tinyint(1) DEFAULT 0,
  `is_card` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tran_number` (`transaction_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 3. Database Backups Available on Disk

The system maintains recoverable, point-in-time SQL backups in `storage/backups/`:

1. **Pre-POS V2 Upgrade Full Backup:**
   `storage/backups/grocery_store_backup_pre_research_pos_20261015.sql`
   - Complete database structure and data snapshot prior to POS V2 upgrade.
2. **Automated Gzip Archive:**
   `storage/backups/backup_grocery_store_2026-10-15_*.sql.gz`
   - Created and verified by `tools/backup_database.php`.

---

## 4. Emergency Database Rollback Procedure

If emergency database restoration is ever required:

```powershell
# 1. Stop web server traffic (optional for maintenance window)
C:\xampp\apache\bin\httpd.exe -k stop

# 2. Restore database from pre-merge snapshot
C:\xampp\mysql\bin\mysql.exe -u root -p grocery_store < C:\xampp\htdocs\grocery-store\storage\backups\grocery_store_backup_pre_research_pos_20261015.sql

# 3. Restart Apache
C:\xampp\apache\bin\httpd.exe -k start

# 4. Verify system health via automated regression suite
C:\xampp\php\php.exe tests/pos_v2_regression_test.php
```

All customer orders, wallet balances, user accounts, and product inventories will be restored safely without data loss.
