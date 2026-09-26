# GroCo Grocery Store — Security Audit (Initial State Report)

**Audit Target:** GroCo Grocery Store Web Application (`C:\xampp\htdocs\grocery-store`)  
**Assessment Period:** 2026-09-26  
**Audit Scope:** Full Application Codebase (Public Storefront, Customer Accounts, Admin Panel, POS, APIs, Database layer, File Uploads, Licensing Server, Session & Authentication infrastructure)

---

## 1. Executive Summary (Pre-Hardening)

A comprehensive static analysis and penetration review was conducted across 447 source files comprising PHP, SQL, HTML, CSS, JavaScript, and Apache server configuration rules.

While the core e-commerce workflow and dual-tier authentication architecture exhibited modern patterns (e.g. prepared PDO statements across data models, password hashing, and session separation), the audit identified several critical and medium-risk vulnerabilities that could expose the system to unauthorized file execution, Cross-Site Scripting (XSS), path traversal, cross-site request forgery, and administrative info disclosure.

---

## 2. Vulnerability Findings & Risk Classifications

### 2.1 File Upload & Remote Code Execution (RCE)
- **Severity:** High / Critical
- **Location:** `public/uploads/`
- **Finding:** The public uploads folder lacked an Apache `.htaccess` policy to disable script execution and index listing. If an attacker managed to upload a `.php`, `.phtml`, or `.phar` file via a bypass or server misconfiguration, direct HTTP access would execute arbitrary server code.
- **Risk:** Remote Code Execution (RCE), complete server compromise.

### 2.2 Sensitive Directory Exposure
- **Severity:** High
- **Location:** `storage/`, `database/`, `licensing_server/`
- **Finding:** 
  - `storage/` and `database/` folders did not have directory-level access denial `.htaccess` rules to prevent HTTP web spiders from indexing sqlite files, database migration schemas (`schema.sql`), and temporary file locks.
  - `licensing_server/` lacked explicit block rules for private keys (`*.key`, `*.pem`), sqlite databases (`*.sqlite`, `*.db`), and SQL dumps.
- **Risk:** Database dump exposure, private key theft, infrastructure reconnaissance.

### 2.3 Reflected & Stored Cross-Site Scripting (XSS) in Admin Views
- **Severity:** Medium / High
- **Location:** 48 Admin Panel view templates (e.g. `admin/banners/*.php`, `admin/products/*.php`, `admin/coupons/*.php`, `admin/delivery/*.php`, `admin/finance/*.php`, etc.)
- **Finding:** Dynamic error feedback variables (`<?= $error ?>`) were printed directly to HTML without `htmlspecialchars()` escaping. If an unhandled validation error or database error string contained malicious characters, script injection in the administrator browser context was possible.
- **Risk:** Admin session hijacking, DOM manipulation, unauthorized administrative actions.

### 2.4 Arbitrary File Deletion / Path Traversal
- **Severity:** Medium
- **Location:** `admin/banners/delete.php`, `admin/products/delete.php`
- **Finding:** Disk file deletion handlers unlinked image files using unvalidated path variables without enforcing strict `basename()` sanitization on filenames before constructing absolute file paths.
- **Risk:** Arbitrary local file deletion (`unlink()`) via path traversal sequences (`../../`).

### 2.5 Information Disclosure via Database Exceptions
- **Severity:** Medium
- **Location:** `public/account.php`, `public/cart.php`, `public/checkout.php`, `public/index.php`, `public/order-details.php`, `public/orders.php`, `public/product.php`, `public/products.php`, `public/reviews.php`, `public/search.php`, `public/dbconnect.php`
- **Finding:** Public controller catch blocks executed `die($e->getMessage())`, dumping raw PDO error messages, SQL syntax details, database names, table names, and server paths directly into public HTTP responses upon database connection hiccups.
- **Risk:** Database architecture discovery, credential leakage, SQL structure disclosure.

### 2.6 CSRF on Diagnostic & State Endpoints
- **Severity:** Low / Medium
- **Location:** `public/license_status.php`
- **Finding:** The re-verification trigger endpoint in the license diagnostic page lacked CSRF token checks, allowing automated cross-site requests to initiate license verification round-trips.
- **Risk:** Unintended server roundtrips, rate-limit exhaustion.

### 2.7 Cross-Role Identity Isolation Edge Cases
- **Severity:** Medium
- **Location:** Customer registration & profile update endpoints
- **Finding:** Incomplete cross-table email checking allowed a user registering as a customer to claim an email address already registered as an administrative staff account.
- **Risk:** Confusion during administrative communication and recovery flows.

---

## 3. Pre-Hardening Summary Matrix

| Vulnerability Category | Pre-Hardening Status | Severity | Target Area |
| :--- | :--- | :--- | :--- |
| **Arbitrary Script Execution in Uploads** | Vulnerable | High | `public/uploads/` |
| **Direct Web Access to Sensitive Files** | Vulnerable | High | `storage/`, `database/`, `licensing_server/` |
| **XSS via Admin Error Strings** | Vulnerable | Medium/High | 48 Admin View files |
| **Path Traversal on Image Deletions** | Vulnerable | Medium | Admin Products & Banners deletion |
| **Raw DB Exception Leaks** | Vulnerable | Medium | 11 Public Controllers |
| **CSRF in License Diagnostics** | Vulnerable | Low/Medium | `public/license_status.php` |
| **Customer/Admin Email Namespace Separation** | Partially Protected | Medium | Customer registration / OAuth flows |
