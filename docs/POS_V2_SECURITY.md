# GroCo Enterprise POS V2 — Security, Auditing & Access Control

## 1. Authentication & Role-Based Access Control (RBAC)
POS V2 integrates directly with GroCo's enterprise RBAC engine:
- **`pos.access`**: Allows viewing and operating the POS counter terminal.
- **`pos.discount`**: Enables manual item or cart discounts up to the configured limit (`pos_max_discount_pct`).
- **`pos.price_override`**: Permits authorized managers to override product unit prices.
- **`pos.refund`**: Governs access to itemized return processing and customer cash refunds.
- **`pos.shift_manage`**: Governs opening, mid-shift cash-in/out adjustments, and closing register reconciliation.

---

## 2. Manager Override Verification
Any action exceeding baseline cashier permissions (e.g. applying a discount > 15%, manually voiding a finalized transaction, issuing refunds over threshold) requires a Manager Pin/Credential modal:
- Manager credentials are authenticated server-side with rate limiting.
- The override event, approving manager ID, cashier ID, reason, and original vs new values are recorded in `pos_audit_logs`.

---

## 3. Tamper-Proof Audit Logging
All high-consequence terminal operations generate structured forensic audit logs:
```sql
CREATE TABLE IF NOT EXISTS `pos_audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(64) NOT NULL,
    `store_id` INT UNSIGNED NULL,
    `shift_id` INT UNSIGNED NULL,
    `admin_id` INT UNSIGNED NOT NULL,
    `entity_type` VARCHAR(64) NULL,
    `entity_id` INT UNSIGNED NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pos_audit_action` (`action`),
    INDEX `idx_pos_audit_admin` (`admin_id`),
    INDEX `idx_pos_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Financial Reconciliation & Drawer Security
- **Blind Close Support**: Cashiers must perform a blind cash count at shift end without seeing expected sales figures first.
- **Z-Reading Generation**: Once confirmed, expected vs actual cash is locked, discrepancies (shortage or overage) are calculated, and the shift is permanently finalized.
