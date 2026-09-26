# Security Hardening & Audit Changelog

All security patches and hardening measures implemented during the production security audit for **GroCo Grocery Store**.

---

### [Hardening Release] — 2026-09-26

#### 1. Directory Protection & Web Server Rules
- **`public/uploads/.htaccess`**: Created security policy disabling directory indexes (`Options -Indexes -ExecCGI`), blocking execution of `.php`, `.phtml`, `.phar`, `.pl`, `.cgi`, `.sh`, `.exe`, etc., and disabling the PHP engine module.
- **`storage/.htaccess`**: Added `Require all denied` to protect session files, cache, and logs from direct HTTP requests.
- **`database/.htaccess`**: Added `Require all denied` to protect raw SQL schemas, migrations, and seed files.
- **`licensing_server/.htaccess`**: Added access control rules denying direct web requests for sensitive private keys (`*.key`, `*.pem`), sqlite databases (`*.sqlite`, `*.db`), and SQL dumps.

#### 2. Cross-Site Scripting (XSS) Remediation
- **Admin Views (48 files)**:
  - Replaced unescaped `<?= $error ?>` with `<?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>` across all admin template views (`admin/admins/`, `admin/backup/`, `admin/banners/`, `admin/brands/`, `admin/categories/`, `admin/cms/`, `admin/contact/`, `admin/coupons/`, `admin/customers/`, `admin/damaged-products/`, `admin/delivery/`, `admin/expenses/`, `admin/expiry-products/`, `admin/faq/`, `admin/finance/`, `admin/flash-sales/`, `admin/notifications/`, `admin/orders/`, `admin/pos/`, `admin/products/`, `admin/profile.php`, `admin/purchases/`, `admin/roles/`, `admin/security/`, `admin/settings/`, `admin/stock-adjustment/`, `admin/stock-transfer/`, `admin/suppliers/`, `admin/testimonials/`).

#### 3. Information Disclosure & Exception Handling
- **Public Controllers (11 files)**:
  - Replaced raw `die($e->getMessage())` with user-friendly error banners and secure server-side logging in:
    - `public/account.php`
    - `public/cart.php`
    - `public/checkout.php`
    - `public/index.php`
    - `public/order-details.php`
    - `public/orders.php`
    - `public/product.php`
    - `public/products.php`
    - `public/reviews.php`
    - `public/search.php`
    - `public/dbconnect.php`

#### 4. Path Traversal Elimination
- **`admin/banners/delete.php`**: Applied `basename()` sanitization to file deletion targets.
- **`admin/products/delete.php`**: Applied `basename()` sanitization to product image deletion targets.

#### 5. Cross-Site Request Forgery (CSRF)
- **`public/license_status.php`**: Added CSRF token verification (`verify_csrf()`) and hidden form token field (`csrf_field()`) to license re-verification action.

#### 6. Cross-Tier Identity Guard
- **`public/process_register.php`**, **`public/register.php`**, **`public/includes/google_auth.php`**, **`public/update_profile.php`**, **`public/process_checkout.php`**:
  - Enforced `is_admin_email()` check to block any registration, social login, profile update, or guest checkout attempting to claim an administrator email address.

#### 7. Test Suites & Verification
- **`tests/security_hardening_penetration_test.php`**: Created comprehensive automated penetration and hardening verification test suite.
- **`tests/production_dual_auth_test.php`**: Parameterized test query and verified dual authentication suite.
- **`docs/SECURITY_AUDIT_BEFORE.md`**: Pre-hardening security audit vulnerability catalog.
- **`docs/SECURITY_AUDIT_FINAL.md`**: Final security audit report and production readiness checklist.
