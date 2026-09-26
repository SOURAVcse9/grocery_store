# System Settings Key-Value Dictionary

## 1. Overview

The GroCo Grocery Store configuration engine utilizes a dynamic key-value storage model in the `settings` database table. These settings control application metadata, financial parameters, payment gateways, email servers, inventory thresholds, and SEO options without modifying PHP source code.

### Database Table Schema:
```sql
CREATE TABLE `settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL DEFAULT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if exposed to public storefront JS/templates',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_key` (`setting_key`),
  INDEX `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Complete Inventory of System Settings

### 2.1 General Store Settings (`setting_group = 'general'`)

| Setting Key | Default Value | Data Type | Description |
| :--- | :--- | :--- | :--- |
| `site_name` | `GroCo Grocery Store` | string | Brand name displayed on title, header, and emails. |
| `site_tagline` | `Fresh Grocery Delivery in Bangladesh` | string | Marketing slogan. |
| `site_logo` | `assets/images/logo.png` | string | Path to main header logo image. |
| `site_favicon` | `assets/images/favicon.ico` | string | Path to browser favicon. |
| `site_phone` | `+8801700000000` | string | Customer service telephone number. |
| `site_email` | `support@groco.site.je` | string | Primary public support email. |
| `site_address` | `House 12, Road 4, Dhanmondi, Dhaka 1205` | string | Physical headquarters / return warehouse address. |
| `default_language` | `en` | enum (`en`,`bn`) | Default locale for first-time visitors. |
| `site_timezone` | `Asia/Dhaka` | string | System timezone for timestamps and shift closing. |

---

### 2.2 Financial & Tax Settings (`setting_group = 'finance'`)

| Setting Key | Default Value | Data Type | Description |
| :--- | :--- | :--- | :--- |
| `site_currency` | `BDT` | string | ISO currency code. |
| `site_currency_symbol` | `৳` | string | Display currency symbol. |
| `site_tax` | `0.00` | decimal(5,2) | Default sitewide VAT/tax percentage (e.g. 5.00 for 5%). |
| `tax_inclusive` | `0` | boolean (`0`,`1`) | Whether product prices are inclusive of VAT. |
| `min_order_amount` | `100.00` | decimal(10,2) | Minimum cart subtotal required to proceed to checkout. |
| `free_shipping_threshold` | `1500.00` | decimal(10,2) | Subtotal qualifying for free delivery. |
| `shipping_fee_inside_dhaka` | `60.00` | decimal(10,2) | Flat shipping rate within Dhaka metropolitan area. |
| `shipping_fee_outside_dhaka`| `120.00` | decimal(10,2) | Flat shipping rate for districts outside Dhaka. |

---

### 2.3 Inventory & POS Settings (`setting_group = 'inventory'` & `'pos'`)

| Setting Key | Default Value | Data Type | Description |
| :--- | :--- | :--- | :--- |
| `low_stock_threshold` | `5` | integer | Default threshold triggering low stock warnings. |
| `allow_negative_stock` | `0` | boolean (`0`,`1`) | Whether POS or Online orders can be placed when stock is 0. |
| `auto_deduct_stock_on_order`| `1` | boolean (`0`,`1`) | Immediate stock deduction on order confirmation. |
| `pos_default_customer_id` | `1` | integer | Customer ID assigned to walk-in anonymous sales. |
| `pos_receipt_printer_size` | `80mm` | enum (`80mm`,`58mm`)| Default thermal invoice paper width. |
| `pos_show_tax_breakdown` | `1` | boolean (`0`,`1`) | Include tax breakdown on printed thermal slips. |

---

### 2.4 Email & SMTP Settings (`setting_group = 'smtp'`)

| Setting Key | Default Value | Description |
| :--- | :--- | :--- |
| `smtp_host` | `smtp.gmail.com` | Outgoing SMTP server hostname. |
| `smtp_port` | `587` | SMTP port (`587` / `465` / `25`). |
| `smtp_user` | `admin@groco.site.je` | SMTP username. |
| `smtp_pass` | `********` | Encrypted/app password. |
| `smtp_encryption` | `tls` | Transport layer encryption (`tls` / `ssl`). |
| `smtp_from_email` | `noreply@groco.site.je` | Display sender email address. |
| `smtp_from_name` | `GroCo Grocery Store` | Display sender name. |

---

### 2.5 Security & Maintenance Settings (`setting_group = 'system'`)

| Setting Key | Default Value | Description |
| :--- | :--- | :--- |
| `maintenance_mode` | `0` | If `1`, storefront redirects to maintenance message for non-admins. |
| `max_login_attempts` | `5` | Maximum failed passwords before temporary IP lockout. |
| `session_lifetime` | `86400` | Session expiration in seconds (24 hours). |
| `reviews_moderation_enabled`| `1` | Require admin review approval before public display. |
| `google_oauth_enabled` | `1` | Enable/disable Google 1-tap and OAuth login. |
| `google_client_id` | `***.apps.googleusercontent.com` | Google Cloud OAuth Client ID. |
| `google_client_secret` | `***` | Google Cloud OAuth Client Secret. |

---

## 3. Programmatic Helper Function (`get_setting()`)

```php
function get_setting(string $key, $default = null) {
    global $pdo;
    static $settings_cache = null;

    if ($settings_cache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    return $settings_cache[$key] ?? $default;
}
```
