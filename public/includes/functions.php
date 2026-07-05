<?php
/**
 * ==========================================================================
 * includes/functions.php
 * ==========================================================================
 * General-purpose, presentation-agnostic helper functions used across the
 * whole site (both storefront and admin). No auth logic here — see auth.php.
 * Requires: dbconnect.php (for db()) to already be loaded.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * get_setting()
 *
 * Reads a single row from the `settings` table (key_name/value) with an
 * in-memory cache so we never hit the DB twice for the same key per request.
 */
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT `value` FROM settings WHERE key_name = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = ($value !== false) ? (string) $value : $default;
    } catch (PDOException $e) {
        error_log('[get_setting] ' . $e->getMessage());
        $cache[$key] = $default;
    }

    return $cache[$key];
}

/**
 * site_name() — pulled from settings table, falling back to a constant.
 */
function site_name(): string
{
    return get_setting('site_name', DEFAULT_SITE_NAME);
}

/**
 * currency_symbol() — the DB only stores a currency code (e.g. "BDT"),
 * so we map known codes to symbols for display and fall back to the code.
 */
function currency_symbol(): string
{
    $code = get_setting('currency', 'BDT');
    $map = [
        'BDT' => '৳',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
    ];

    return $map[$code] ?? $code . ' ';
}

/**
 * format_price()
 *
 * Consistent money formatting everywhere: ৳1,250.00
 */
function format_price(float|string $amount): string
{
    return currency_symbol() . number_format((float) $amount, 2);
}

/**
 * default_delivery_charge() — from settings table.
 */
function default_delivery_charge(): float
{
    return (float) get_setting('delivery_charge', '0');
}

/**
 * time_ago()
 *
 * Human-friendly relative time for notifications, reviews, order history.
 */
function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' minute' . ($m > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 2592000) {
        $d = (int) floor($diff / 86400);
        return $d . ' day' . ($d > 1 ? 's' : '') . ' ago';
    }

    return date('M j, Y', $timestamp);
}

/**
 * truncate() — safe text truncation for product cards / descriptions.
 */
function truncate(string $text, int $length = 100, string $suffix = '…'): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length)) . $suffix;
}

/**
 * generate_slug()
 *
 * Used by admin product/category forms. Guarantees a URL-safe, lowercase,
 * hyphenated slug. Uniqueness against a table/column is checked separately
 * (see make_unique_slug()) because that requires a DB round-trip.
 */
function generate_slug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

/**
 * make_unique_slug()
 *
 * Appends -2, -3, etc. until the slug is unique in the given table/column,
 * optionally ignoring a specific row id (useful when editing).
 */
function make_unique_slug(string $table, string $baseSlug, ?int $ignoreId = null): string
{
    $slug = $baseSlug;
    $suffix = 2;

    while (true) {
        $sql = "SELECT id FROM `{$table}` WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetchColumn() === false) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

/**
 * redirect() — sends a Location header and halts execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * flash() — one-shot session flash messages (success/error/info) rendered
 * by components/toast.php on the next page load.
 */
function flash(string $key, ?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = ['message' => $message, 'type' => $type];
        return null;
    }

    if (!empty($_SESSION['_flash'][$key])) {
        $data = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $data;
    }

    return null;
}

/**
 * has_flash() — checks if a flash message exists for a given key.
 */
function has_flash(string $key): bool
{
    return !empty($_SESSION['_flash'][$key]);
}

/**
 * display_flash_alerts()
 *
 * Renders HTML block for a given flash key if set.
 */
function display_flash_alerts(string $key): void
{
    $flash = flash($key);
    if ($flash !== null) {
        $typeClass = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
        $icon = $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
        echo '<div class="alert ' . $typeClass . '" role="alert" style="margin-bottom:var(--space-4); display:flex; align-items:center; gap:8px; padding:12px 16px; border-radius:8px; font-size:13px; font-weight:600; line-height:1.4; border:1px solid transparent; ';
        if ($flash['type'] === 'success') {
            echo 'background-color:#ebfbee; color:#2b8a3e; border-color:#d3f9d8;';
        } else {
            echo 'background-color:#fff5f5; color:#c92a2a; border-color:#ffe3e3;';
        }
        echo '">';
        echo '  <i class="fas ' . $icon . '"></i>';
        echo '  <span>' . htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</div>';
    }
}

/**
 * get_all_flashes() — pulls every pending flash message at once (used by
 * components/toast.php) and clears them so they never show twice.
 */
function get_all_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/**
 * old()
 *
 * Repopulates a form field with the previously submitted value after a
 * validation failure + redirect. Pair with includes/validation.php.
 */
function old(string $field, string $default = ''): string
{
    return htmlspecialchars((string) ($_SESSION['_old_input'][$field] ?? $default), ENT_QUOTES, 'UTF-8');
}

function set_old_input(array $input): void
{
    $_SESSION['_old_input'] = $input;
}

function clear_old_input(): void
{
    unset($_SESSION['_old_input']);
}

/**
 * current_lang() / t()
 *
 * Minimal i18n loader backed by lang/en.php and lang/bn.php. The active
 * language is stored in the session and can be switched via ?lang=en|bn
 * (handled once, in header.php, before any output).
 */
function current_lang(): string
{
    return $_SESSION['lang'] ?? 'en';
}

function t(string $key): string
{
    static $strings = [];
    $lang = current_lang();

    if (!isset($strings[$lang])) {
        $file = PUBLIC_PATH . '/lang/' . $lang . '.php';
        $strings[$lang] = is_file($file) ? require $file : [];
    }

    return $strings[$lang][$key] ?? $key;
}

/**
 * json_response()
 *
 * Standard envelope for every AJAX/API endpoint in ajax/ and api/.
 */
function json_response(bool $success, string $message = '', array $data = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
