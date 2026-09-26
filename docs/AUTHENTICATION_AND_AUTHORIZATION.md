# GroCo Grocery Store — Authentication & Authorization Specification

This document provides a deep architectural and forensic analysis of the dual customer authentication system, Google OAuth integration, secure password recovery lifecycle, administrative role-based access control (RBAC), session versioning, and tier separation.

---

## 1. Authentication Architecture Overview

GroCo Grocery Store enforces strict segregation between the **Customer Storefront** and the **Administrative Back-Office**:

```text
┌───────────────────────────────────────────────┐     ┌───────────────────────────────────────────────┐
│              CUSTOMER STOREFRONT              │     │            ADMINISTRATIVE CONTROL             │
├───────────────────────────────────────────────┤     ├───────────────────────────────────────────────┤
│ Identity Table: `users`                       │     │ Identity Table: `admins`                      │
│ Session Key: `$_SESSION['customer_id']`       │     │ Session Key: `$_SESSION['admin_id']`          │
│ Methods: Email+Password, Google OAuth 2.0     │     │ Methods: Username/Email + Password (OTP Reset)│
│ Cart Binding: `carts.user_id`                 │     │ Permission Check: `admin_role_permissions`    │
│ Multi-Device Sync: `session_version`          │     │ Audit Log: `admin_activity_logs`              │
└───────────────────────────────────────────────┘     └───────────────────────────────────────────────┘
```

---

## 2. Customer Registration Flow

* **Entry Point:** `public/register.php` & `public/process_register.php`
* **Input Fields:** `full_name`, `email`, `phone`, `password`, `confirm_password`, `terms_agreement`.
* **Step-by-Step Processing:**
  1. **CSRF Validation:** Verify `$_POST['csrf_token']` against `$_SESSION['csrf_token']`.
  2. **Admin Email Barrier:** Execute `is_admin_email($email)` checking `admins` table. If the email belongs to an administrative account, registration is immediately halted with: *"This email is reserved for administrative access and cannot be registered as a customer."*
  3. **Duplicate Check:** Query `users` table for existing `email`. If found, halt with error.
  4. **Password Strength:** Verify password minimum length (8 characters).
  5. **Password Hashing:** Hash password using `password_hash($password, PASSWORD_DEFAULT)`.
  6. **Customer Record Creation:** Insert into `users`:
     * `full_name = :name`
     * `email = :email`
     * `phone = :phone`
     * `password = :hash`
     * `role = 'customer'`
     * `status = 'active'`
     * `session_version = 1`
     * `created_at = NOW()`
  7. **Auto-Login & Cart Migration:** Establish customer session (`$_SESSION['customer_id'] = $newId`), merge any guest cart items from `$_SESSION['guest_token']` into `carts` table for this `user_id`.
  8. **Redirect:** Redirect customer to `public/index.php` or `return_url`.

---

## 3. Customer Email & Password Login Flow

* **Entry Point:** `public/login.php` & `public/process_login.php`
* **Input Fields:** `email`, `password`, `remember_me` (checkbox).
* **Step-by-Step Processing:**
  1. **CSRF Check:** Validate CSRF token.
  2. **Brute-Force Rate Limiting:** Query `users` by email. If `failed_logins >= 5` and last attempt occurred within 15 minutes, reject login attempt with HTTP 429 / cooldown notice.
  3. **Credential Verification:**
     * Fetch user row: `SELECT * FROM users WHERE email = :email AND status = 'active' LIMIT 1`.
     * If user exists and `password_verify($password, $user['password'])` returns `true`:
       * Reset `failed_logins = 0` and update `last_login = NOW()`.
       * Set session:
         * `$_SESSION['customer_id'] = (int)$user['id']`
         * `$_SESSION['customer_name'] = $user['full_name']`
         * `$_SESSION['customer_email'] = $user['email']`
         * `$_SESSION['customer_session_version'] = (int)$user['session_version']`
       * **Persistent Cookie ("Remember Me"):** If checkbox is checked, generate a random 64-byte token, hash it using SHA-256, store in database / cookie `remember_customer` with 30-day expiry (`HttpOnly`, `SameSite=Lax`, `Secure`).
       * **Cart Merge:** Migrate guest cart items from `carts WHERE session_id = :guest_token` into `carts WHERE user_id = :customer_id`.
       * Redirect to requested destination.
     * If invalid password:
       * Increment `failed_logins = failed_logins + 1` in `users` table.
       * Return generic error: *"Invalid email or password."*

---

## 4. Google OAuth 2.0 & Safe Account Linking

* **Entry Points:**
  * Login Button Click: `public/auth/google-login.php`
  * OAuth Provider Callback: `public/auth/google-callback.php`
  * Core Handler: `public/includes/google_auth.php` (`sync_google_customer()`)
* **Step-by-Step Processing:**
  1. **State CSRF Token:** User clicks "Continue with Google". System generates random state token `$_SESSION['google_oauth_state']` and redirects to `https://accounts.google.com/o/oauth2/v2/auth`.
  2. **Callback Validation:** Google redirects back to `public/auth/google-callback.php?code=...&state=...`. System verifies state parameter matches `$_SESSION['google_oauth_state']`.
  3. **Token Exchange:** Backend performs HTTP POST to `https://oauth2.googleapis.com/token` with `client_id`, `client_secret`, `code`, `redirect_uri` to obtain `access_token` and `id_token`.
  4. **User Profile Fetch:** Backend performs HTTP GET to `https://www.googleapis.com/oauth2/v3/userinfo` to receive `sub` (Google Unique ID), `email`, `name`, `picture`, `email_verified`.
  5. **Admin Email Protection:** Verify `is_admin_email($googleEmail)`. If email belongs to an administrator, block Google onboarding with clear notice.
  6. **Atomic Customer Sync / Account Linking:**
     * **Case A (Existing Customer with same email):**
       * Update user row: `UPDATE users SET google_id = :google_id, avatar = COALESCE(avatar, :picture) WHERE email = :email`.
       * Retains existing customer ID, order history, addresses, and any previously set local password.
     * **Case B (New Customer):**
       * Insert into `users`: `full_name = :name`, `email = :email`, `google_id = :google_id`, `avatar = :picture`, `password = NULL`, `status = 'active'`, `role = 'customer'`, `session_version = 1`.
  7. **Establish Session:** Set customer session variables and merge guest cart.
  8. **Redirect:** Direct customer to account or previous page.

---

## 5. Google Customer Local Password Setup Flow

* **Problem Solved:** Customers who sign up via Google can seamlessly add a local Email + Password login method to their account without creating a duplicate record or losing order history.
* **Entry Point:** `public/profile.php` (Security & Auth Methods Tab) & `public/update_password.php`.
* **Step-by-Step Processing:**
  1. If `users.password IS NULL` and `users.google_id IS NOT NULL`: Profile UI presents a specialized **"Create Local Password"** card (instead of requiring an "Old Password").
  2. Customer enters: `new_password` and `confirm_password`.
  3. Backend validates password length (&ge; 8 chars) and complexity.
  4. Backend hashes password with `password_hash()` and executes:
     `UPDATE users SET password = :hash, session_version = session_version + 1 WHERE id = :user_id`.
  5. Status updates to **"Dual Authentication Enabled"** (Customer can now log in using either Google or Email+Password).

---

## 6. Customer Password Reset Lifecycle (SHA-256 + SMTP)

* **Entry Points:** `public/forgot-password.php` & `public/reset-password.php`
* **Step-by-Step Processing:**
  1. **Request Reset:** Customer enters registered email address at `public/forgot-password.php`.
  2. **Anti-Enumeration Response:** Regardless of whether the email exists in `users`, the UI renders a generic confirmation: *"If an account exists with that email, a password reset link has been sent."*
  3. **Token Generation (Backend):**
     * Query `users WHERE email = :email AND status = 'active'`.
     * If user exists:
       * Generate cryptographically secure 32-byte binary token: `$plainToken = bin2hex(random_bytes(32))`.
       * Hash token using SHA-256: `$tokenHash = hash('sha256', $plainToken)`.
       * Set expiry timestamp: `$expiresAt = date('Y-m-d H:i:s', time() + 3600)` (1 hour).
       * Invalidate any prior unused tokens for this user: `UPDATE password_resets SET used = 1 WHERE email = :email`.
       * Insert into `password_resets`:
         `INSERT INTO password_resets (email, token, created_at, expires_at, used) VALUES (:email, :tokenHash, NOW(), :expiresAt, 0)`.
  4. **SMTP Email Dispatch (`public/includes/mailer.php`):**
     * Builds action link: `https://groco.site.je/reset-password.php?token=` + `$plainToken`.
     * Connects to configured SMTP server via socket, sends HTML email with branding and security notice.
  5. **Token Verification & Password Update (`public/reset-password.php`):**
     * Customer clicks link with `?token=...`.
     * Backend hashes incoming token with SHA-256 and queries:
       `SELECT * FROM password_resets WHERE token = :hash AND used = 0 AND expires_at > NOW() LIMIT 1`.
     * If token is invalid or expired: Display *"Invalid or expired reset link."*
     * If valid: Customer enters new password and confirms.
     * Backend hashes new password, updates `users`:
       `UPDATE users SET password = :hash, session_version = session_version + 1 WHERE email = :email`.
     * Invalidate token immediately:
       `UPDATE password_resets SET used = 1 WHERE id = :reset_id`.
     * Invalidate all active sessions across devices (`session_version` incremented).
     * Redirect to `public/login.php` with success message.

---

## 7. Multi-Device Session Invalidation (`session_version`)

* **Purpose:** Allows a customer to instantly sign out of all active web sessions across phones, tablets, and desktop computers if they suspect unauthorized access or change their password.
* **Mechanism:**
  * The `users` table contains a `session_version INT DEFAULT 1` column.
  * When a customer logs in, their current session stores `$_SESSION['customer_session_version'] = $user['session_version']`.
  * On every request, `public/includes/auth.php` verifies that `$_SESSION['customer_session_version'] === $dbUser['session_version']`.
  * When customer clicks **"Sign Out of All Devices"** or resets their password:
    `UPDATE users SET session_version = session_version + 1 WHERE id = :user_id`.
  * Any other open browser session with the previous version number is immediately destroyed and redirected to login.

---

## 8. Administrative Authentication & Role-Based Access Control (RBAC)

* **Identity Table:** `admins`
* **Admin Login Route:** `admin/login.php`
* **Admin Middleware:** `admin/middleware/auth_middleware.php`
* **Super Admin Privilege:** Verified via `is_super_admin()` (`admins.role_id = 1` or `admin_roles.slug = 'super-admin'`).

### Admin Password Reset Lockdown:
* **Self-Service Routes Disabled:** `admin/forgot-password.php` and `admin/reset-password.php` return HTTP 403 Forbidden with redirect to `admin/login.php`.
* **Super Admin OTP Reset Tool (`admin/admins/reset-password.php`):**
  * Only accessible by authenticated Super Admins (`require_super_admin()`).
  * Generates an administrative single-use 8-character numeric OTP stored in database.
  * The target admin uses the OTP to set a new password upon their next login.

### Granular RBAC Permissions Table (`admin_permissions`):
* `dashboard.view`
* `products.view`, `products.create`, `products.edit`, `products.delete`
* `categories.manage`
* `brands.manage`
* `orders.view`, `orders.edit`, `orders.status_update`, `orders.delete`
* `customers.view`, `customers.edit`, `customers.delete`
* `pos.access`, `pos.sales`, `pos.discount`, `pos.refund`
* `inventory.view`, `inventory.adjust`, `inventory.valuation`
* `suppliers.manage`
* `purchases.manage`
* `finance.view`, `expenses.manage`, `reports.view`
* `reviews.moderate`
* `coupons.manage`, `flash_sales.manage`, `banners.manage`
* `admins.manage`, `roles.manage`
* `settings.manage`, `license.manage`, `backup.manage`
