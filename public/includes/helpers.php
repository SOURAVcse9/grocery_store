<?php
/**
 * ==========================================================================
 * includes/helpers.php
 * ==========================================================================
 * Request/output/asset helpers. Kept separate from functions.php so that
 * "business formatting" (prices, slugs, settings) and "plumbing" (URLs,
 * escaping, guest identity) live in different files, per the project's
 * modular-file convention.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * e()
 *
 * Shorthand output-escaping helper. ALWAYS wrap untrusted/DB-sourced text
 * in this before printing it into HTML. Input validation happens on the
 * way IN (validation.php); this happens on the way OUT.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * asset()
 *
 * Builds a versioned/absolute URL for a file under public/assets/.
 * Example: asset('css/header.css') -> /grocery-store/public/assets/css/header.css?v=...
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $fullPath = PUBLIC_PATH . '/assets/' . $path;
    $version = is_file($fullPath) ? filemtime($fullPath) : time();

    return BASE_URL . '/assets/' . $path . '?v=' . $version;
}

/**
 * image_url()
 *
 * Resolves a product/category/brand/user image path stored in the DB to a
 * public URL, falling back to a placeholder when the column is NULL or the
 * file is missing on disk — critical because thumbnails/images are optional
 * in several tables (products.thumbnail, categories.image, brands.logo...).
 */
function image_url(?string $path, string $placeholderCategory = 'ui'): string
{
    if (!empty($path)) {
        // Already absolute (http/https) — return as-is.
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // 1. If it starts with uploads/
        if (str_starts_with(ltrim($path, '/'), 'uploads/')) {
            $cleaned = ltrim($path, '/');
            $filePath = PUBLIC_PATH . '/' . $cleaned;
            if (file_exists($filePath)) {
                $mtime = filemtime($filePath);
                return BASE_URL . '/' . $cleaned . '?v=' . $mtime;
            }
        }

        // 2. If it is a filename and exists in uploads/<category>/
        $folder = $placeholderCategory;
        if ($folder === 'users') {
            $folder = 'users';
        }
        $filePath = PUBLIC_PATH . '/uploads/' . $folder . '/' . ltrim($path, '/');
        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            return BASE_URL . '/uploads/' . $folder . '/' . ltrim($path, '/') . '?v=' . $mtime;
        }

        // 3. If it starts with storage/
        if (str_starts_with($path, 'storage/')) {
            $filePath = PUBLIC_PATH . '/../' . ltrim($path, '/');
            if (file_exists($filePath)) {
                $mtime = filemtime($filePath);
                return rtrim(dirname(BASE_URL), '/') . '/' . ltrim($path, '/') . '?v=' . $mtime;
            }
        }

        // 4. For standard assets inside assets/images/
        $filePath = PUBLIC_PATH . '/assets/images/' . ltrim($path, '/');
        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            return BASE_URL . '/assets/images/' . ltrim($path, '/') . '?v=' . $mtime;
        }
    }

    // Default Fallback
    $fallbackPath = 'assets/images/' . $placeholderCategory . '/placeholder.png';
    $filePath = PUBLIC_PATH . '/' . $fallbackPath;
    if (!file_exists($filePath)) {
        $fallbackPath = 'assets/images/ui/placeholder.png';
        $filePath = PUBLIC_PATH . '/' . $fallbackPath;
    }
    $mtime = file_exists($filePath) ? filemtime($filePath) : time();
    return BASE_URL . '/' . $fallbackPath . '?v=' . $mtime;
}

/**
 * url_for()
 *
 * Builds an absolute URL to another page inside public/, so links keep
 * working regardless of the folder the app is deployed into.
 */
function url_for(string $page): string
{
    return BASE_URL . '/' . ltrim($page, '/');
}

/**
 * current_url() — including query string, used for "redirect back after login".
 */
function current_url(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return BASE_URL . $uri;
}

/**
 * is_active_page()
 *
 * Helper for header.php nav to add an "active" class to the current link.
 */
function is_active_page(string $page): bool
{
    $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    return $current === $page;
}

/**
 * input()
 *
 * Reads a value from GET/POST safely, trimmed, with a default. This does
 * NOT escape for HTML output (use e() at render time) and does NOT
 * validate — see includes/validation.php for that.
 */
function input(string $key, string $default = '', string $method = 'post'): string
{
    $source = $method === 'get' ? $_GET : $_POST;
    $value = $source[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/**
 * get_or_create_guest_token()
 *
 * carts, compare_items, and (in practice) a guest wishlist flow all need a
 * stable identifier for anonymous visitors, matching the `session_id`
 * columns in the schema. We generate one opaque random token (NOT PHP's
 * raw session id, so it survives session id regeneration on login) and
 * persist it in the session.
 */
function get_or_create_guest_token(): string
{
    if (empty($_SESSION['guest_token'])) {
        $_SESSION['guest_token'] = bin2hex(random_bytes(20));
    }

    return $_SESSION['guest_token'];
}

/**
 * str_limit_words() — used for meta descriptions / short_description fallback.
 */
function str_limit_words(string $text, int $words = 25): string
{
    $parts = preg_split('/\s+/', trim($text)) ?: [];
    if (count($parts) <= $words) {
        return trim($text);
    }

    return implode(' ', array_slice($parts, 0, $words)) . '…';
}

/**
 * method_is() — small readability helper for AJAX endpoints.
 */
function method_is(string $method): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === strtoupper($method);
}

/**
 * require_method()
 *
 * Guards ajax/api endpoints against wrong HTTP verbs.
 */
function require_method(string $method): void
{
    if (!method_is($method)) {
        json_response(false, 'Method not allowed.', [], 405);
    }
}

/**
 * site_url() — builds an absolute URL using BASE_URL.
 */
function site_url(string $page = ''): string
{
    return BASE_URL . '/' . ltrim($page, '/');
}
