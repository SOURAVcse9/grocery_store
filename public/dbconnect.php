<?php
/**
 * ==========================================================================
 * Grocery Store — Core Bootstrap & Database Connection
 * ==========================================================================
 * PDO only. No mysqli. This file is the single entry point every other
 * script requires first. It must never output anything itself.
 *
 * Responsibilities:
 *   - Environment / error-reporting configuration
 *   - Secure session bootstrap
 *   - Global constants (paths, URLs, currency)
 *   - PDO connection (Database singleton)
 * ==========================================================================
 */

declare(strict_types=1);

// --------------------------------------------------------------------------
// Environment
// --------------------------------------------------------------------------
// Set APP_ENV=production on the live server (e.g. via Apache SetEnv, or
// simply flip the literal below before deployment).
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV !== 'production');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

date_default_timezone_set('Asia/Dhaka');

// --------------------------------------------------------------------------
// Error logging (never show raw DB errors to visitors)
// --------------------------------------------------------------------------
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/app.log');

// --------------------------------------------------------------------------
// Path & URL constants
// --------------------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));                 // .../grocery-store
define('PUBLIC_PATH', __DIR__);                        // .../grocery-store/public
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Base URL is auto-detected so the same code runs on localhost or a domain.
if (!defined('BASE_URL')) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Directory the app is running from (e.g. /grocery-store/public)
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    // If the request is routed through api/ or ajax/ folders, strip them to keep BASE_URL unified
    $scriptDir = preg_replace('/\/(api|ajax)$/i', '', $scriptDir);
    define('BASE_URL', $scheme . '://' . $host . $scriptDir);
}

// --------------------------------------------------------------------------
// Site-wide defaults (presentational only — not stored in the DB schema).
// Anything that IS in the `settings` table is fetched via get_setting()
// in includes/functions.php instead of being hard-coded here.
// --------------------------------------------------------------------------
define('DEFAULT_SITE_NAME', 'Grocery Store');
define('DEFAULT_CURRENCY_SYMBOL', '৳');

// --------------------------------------------------------------------------
// Database configuration
// --------------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'grocery_store');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Database
 *
 * Thin PDO singleton. Always returns the same connection within a request.
 * Prepared statements are enforced everywhere by disabling emulation.
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$instance->exec("SET time_zone = '+06:00'");
            } catch (PDOException $e) {
                error_log('[DB CONNECTION ERROR] ' . $e->getMessage());

                if (APP_DEBUG) {
                    die('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
                }

                http_response_code(500);
                die('Something went wrong. Please try again later.');
            }
        }

        return self::$instance;
    }
}

/**
 * db()
 *
 * Convenience accessor used throughout the whole codebase instead of
 * passing $pdo around everywhere.
 */
function db(): PDO
{
    return Database::getConnection();
}

// --------------------------------------------------------------------------
// Secure session bootstrap (must happen before ANY output)
// --------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Basic session-hijacking mitigation: bind session to the browser's UA hash.
if (!isset($_SESSION['_ua_hash'])) {
    $_SESSION['_ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
} elseif ($_SESSION['_ua_hash'] !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown')) {
    // User agent changed mid-session — force a clean session instead of trusting it.
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['_ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
}

// --------------------------------------------------------------------------
// Core dependencies every page needs (order matters: no circular requires)
// --------------------------------------------------------------------------
require_once PUBLIC_PATH . '/includes/logger.php';
require_once PUBLIC_PATH . '/includes/error_handler.php';
require_once PUBLIC_PATH . '/includes/security.php';
require_once PUBLIC_PATH . '/includes/rate_limit.php';

require_once PUBLIC_PATH . '/includes/functions.php';
require_once PUBLIC_PATH . '/includes/helpers.php';
require_once PUBLIC_PATH . '/includes/validation.php';
require_once PUBLIC_PATH . '/csrf.php';
require_once PUBLIC_PATH . '/includes/auth.php';

// Trigger automatic cookie logins
check_remember_me_autologin();

// Load dynamic contact settings
try {
    $__setData = [];
    $__stmtSet = db()->query("SELECT key_name, value FROM settings WHERE key_name IN ('site_address', 'site_email', 'site_phone')");
    while ($__row = $__stmtSet->fetch()) {
        $__setData[$__row['key_name']] = $__row['value'];
    }
} catch (PDOException $e) {
    $__setData = [];
}
define('CONTACT_ADDRESS', !empty($__setData['site_address']) ? $__setData['site_address'] : 'Chiknikandi, Galachipa, Patuakhali, Bangladesh');
define('CONTACT_EMAIL', !empty($__setData['site_email']) ? $__setData['site_email'] : 'support@grocerystore.com');
define('CONTACT_PHONE', !empty($__setData['site_phone']) ? $__setData['site_phone'] : '+8801700000000');
