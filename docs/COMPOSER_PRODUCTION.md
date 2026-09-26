# GroCo Grocery Store — Composer Production Integration Runbook

**Document Version:** 1.0.0  
**Project:** GroCo Grocery Store Modernization  
**Phase:** Phase 1 — Production Dependency Management  

---

## 1. Production Autoloading Architecture

GroCo uses an adaptive dual-mode classloader:
1. When Composer dependencies are installed (`vendor/autoload.php`), Composer's optimized classmap and PSR-4 resolver are utilized.
2. If deployed to an environment without Composer CLI (e.g. shared hosting or offline retail POS machines), the native SPL autoloader registered in `public/dbconnect.php` resolves namespace mappings to `src/` and `public/includes/` with zero external dependencies.

```php
// Dual-Mode Autoloader in public/dbconnect.php
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'Groco\\Includes\\';
        $baseDir = __DIR__ . '/includes/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            $simpleFile = $baseDir . str_replace('\\', '/', $class) . '.php';
            if (file_exists($simpleFile)) {
                require_once $simpleFile;
            }
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}
```

---

## 2. Production Deployment Commands

For environments with Composer installed:
```bash
# Production install with authoritative classmap and no development overhead
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

---

## 3. Package Audit & Security Policy

- Every suggested package (`resend/resend-php`, `phpmailer/phpmailer`, `cloudinary/cloudinary_php`) is encapsulated behind an internal adapter class (`EmailService`, `MediaService`).
- If an external package is absent or fails, the adapter immediately invokes internal native PHP cURL/mail/GD fallback routines with zero fatal errors.
- `/vendor/` directory access is blocked at the HTTP web server level via `.htaccess`.
