# GroCo Grocery Store — Production Security Architecture & Hardening Guide

This document describes the security policies, defense-in-depth protections, and implementation details for the **GroCo Grocery Store** e-commerce platform.

---

## 1. Threat Model & Defense-in-Depth Overview

The application follows OWASP Top 10 security standards:

```
[ Internet User / Client ]
           │
           ▼
[ Apache / Nginx Web Server ]  ──► Security Headers, Directory Blocks, Sensitive File Filter (.htaccess)
           │
           ▼
[ PHP Bootstrap (dbconnect.php) ]  ──► Secure Session Bootstrap, Inactivity Timeout, UA Hash Binding
           │
           ▼
[ Application Layer ]  ──► CSRF Verification, RBAC Middleware, Input Sanitization
           │
           ▼
[ Database Layer (PDO) ]  ──► 100% Prepared Statements (No Emulation), SQL Injection Immunity
```

---

## 2. Session Security & Fixation Prevention

### Cookie Security Attributes
Session cookies are strictly configured in `public/dbconnect.php` via `.env`:
- **`Secure`**: Enforced on HTTPS connections (`SESSION_SECURE=true`). Prevents eavesdropping over plaintext HTTP.
- **`HttpOnly`**: Set to `true` (`SESSION_HTTPONLY=true`). Prevents JavaScript from reading `document.cookie`, mitigating XSS session theft.
- **`SameSite`**: Set to `Lax` (`SESSION_SAMESITE=Lax`). Guards against cross-site request forgery via external links.

### Session Fixation & Hijacking Countermeasures
1. **Login Session Regeneration**: `session_regenerate_id(true)` is immediately invoked upon authentication in `login_user()`.
2. **User-Agent Fingerprinting**: A SHA-256 hash of `HTTP_USER_AGENT` is bound to `$_SESSION['_ua_hash']`. If a mid-session UA mutation is detected, the session is invalidated immediately.
3. **Inactivity Timeout**: Idle sessions are automatically purged if `time() - $_SESSION['_last_activity'] > SESSION_TIMEOUT` (default: 7200 seconds / 2 hours).
4. **Complete Destruction on Logout**: `logout_user()` empties `$_SESSION`, clears the session cookie with past expiration, and calls `session_destroy()`.

---

## 3. Cross-Site Request Forgery (CSRF) Protection

All state-changing HTTP requests (POST, PUT, DELETE) require a valid CSRF token:

### HTML Form Integration
```php
<form action="process.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    ...
</form>
```

### AJAX / JavaScript Integration
The CSRF token is exposed in meta tags and global JavaScript:
```html
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
```
```javascript
fetch('/api/cart/add', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.GROCO.csrfToken
    },
    body: JSON.stringify({ product_id: 42, quantity: 1 })
});
```

---

## 4. SQL Injection Immunity (PDO Prepared Statements)

The application strictly utilizes PDO prepared statements with native driver preparation:
```php
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Enforces true server-side prepared statements
];
```
Direct string concatenation in queries is strictly disallowed. All user-supplied inputs (`$_GET`, `$_POST`, JSON payloads) are bound as parameters.

---

## 5. Cross-Site Scripting (XSS) Prevention

All user-controlled data rendered into HTML documents is filtered using `e()` / `htmlspecialchars()`:

```php
// Helper in public/includes/helpers.php
function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
```

Applicable to:
- Customer names, addresses, and phone numbers
- Product names, descriptions, and categories
- Search terms (`$_GET['q']`)
- Reviews and testimonials
- Error and flash messages

---

## 6. HTTP Security Headers

The following response headers are set via `.htaccess`:

| Header | Production Value | Purpose |
| :--- | :--- | :--- |
| `X-Content-Type-Options` | `nosniff` | Prevents MIME-type sniffing exploits |
| `X-Frame-Options` | `SAMEORIGIN` | Protects against UI redressing / clickjacking |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Protects referrer leakage on third-party links |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Disables invasive browser APIs |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Forces HTTPS communication for 1 year |
| `Content-Security-Policy` | Strict whitelist (self, Cloudinary, Google Fonts, GA) | Prevents unauthorized third-party script injection |

---

## 7. Sensitive File & Directory Protection

Public web requests to sensitive directories and configuration files are blocked at the web-server layer:
- **Files**: `.env*`, `.git*`, `composer.json`, `*.sql`, `*.log`, `*.bak`, `*.sh`, `*.key`, `*.pem`, `*.conf`, `*.ini`.
- **Directories**: `/storage/`, `/database/`, `/tools/`, `/scratch/`, `/tests/`.
- All return `403 Forbidden` if accessed directly via browser.
