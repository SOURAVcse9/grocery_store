# GroCo Grocery Store — Database Backup & Disaster Recovery Guide

This document describes the automated database backup mechanism, retention policies, and disaster recovery procedures for **GroCo Grocery Store**.

---

## 1. Backup Tool Overview

The backup script is located at `tools/backup_database.php`. It executes via the PHP Command Line Interface (CLI) to generate full atomic SQL dumps of all database tables, constraints, and data records.

### Key Capabilities:
- **Zero-Dependency Native Dumper**: Dumps tables via PDO without requiring external `mysqldump` binaries in the system path.
- **Foreign Key Safety**: Wraps statements in `SET FOREIGN_KEY_CHECKS = 0;` and transactions for clean multi-table restoration.
- **Gzip Compression**: Compresses `.sql` archives to `.sql.gz` reducing file sizes by up to 85–90%.
- **Automatic Pruning**: Deletes backup files exceeding the configured retention threshold (default: 7 days).
- **Web-Protected Storage**: Saves backups exclusively into `storage/backups/`, which is blocked from web access via Apache `.htaccess` (`Require all denied`).

---

## 2. Command-Line Usage

```bash
# Basic uncompressed backup (retains 7 days)
php tools/backup_database.php

# High-efficiency Gzip-compressed backup
php tools/backup_database.php --compress

# Custom retention period (e.g., retain 14 days)
php tools/backup_database.php --compress --keep=14

# Quiet mode (suppresses terminal output, ideal for cron jobs)
php tools/backup_database.php --compress --keep=7 --quiet
```

### Command Options:
| Flag | Description | Default |
| :--- | :--- | :--- |
| `--keep=N` | Maximum age of backup archives to keep (in days). Older files are pruned automatically. | `7` |
| `--compress` | Enables Gzip stream compression (`.sql.gz`). | Disabled |
| `--quiet` | Suppresses console stdout; errors are still sent to `stderr` and logged to `storage/logs/app.log`. | Disabled |
| `--help` | Displays usage instructions and available parameters. | — |

---

## 3. Automation & Scheduling

### Linux / Unix (Crontab)
To execute an automated backup every night at 2:00 AM:

```bash
sudo crontab -e -u www-data
```

Add the following entry:
```text
0 2 * * * /usr/bin/php /var/www/grocery-store/tools/backup_database.php --compress --keep=7 --quiet >> /var/www/grocery-store/storage/logs/cron_backup.log 2>&1
```

### Windows (Task Scheduler)
To schedule on Windows Server:
1. Open **Task Scheduler** -> **Create Basic Task**.
2. Name: `GroCo Daily Database Backup`.
3. Trigger: **Daily** at `02:00:00`.
4. Action: **Start a program**:
   - **Program/script**: `C:\xampp\php\php.exe`
   - **Add arguments**: `tools\backup_database.php --compress --keep=7 --quiet`
   - **Start in**: `C:\xampp\htdocs\grocery-store`

---

## 4. Restoration & Disaster Recovery Procedure

### Restoring from Compressed Archive (`.sql.gz`):
```bash
gunzip -c storage/backups/grocery_store_backup_YYYY-MM-DD_HHMMSS.sql.gz | mysql -u groco_user -p grocery_store
```

### Restoring from Plain SQL Archive (`.sql`):
```bash
mysql -u groco_user -p grocery_store < storage/backups/grocery_store_backup_YYYY-MM-DD_HHMMSS.sql
```

### Verification Post-Restore:
1. Run table verification query:
   ```sql
   SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema = 'grocery_store';
   ```
2. Verify customer login and admin dashboard metrics.
3. Test a mock order or inventory decrement.
