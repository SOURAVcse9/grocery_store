<?php
/**
 * ==========================================================================
 * public/includes/cache.php — Caching & Browser Headers Manager
 * ==========================================================================
 * Sets browser caching controls, prevents caching on secure APIs, and sends
 * HTTP 304 Not Modified codes to save bandwidth.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * set_browser_cache_headers()
 *
 * Sets public Cache-Control headers for static or semi-static content pages.
 */
function set_browser_cache_headers(int $seconds = 3600): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: public, max-age=' . $seconds . ', must-revalidate');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
}

/**
 * set_no_cache_headers()
 *
 * Instructs browser not to cache dynamic pages, checkout steps, or APIs.
 */
function set_no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * check_last_modified()
 *
 * Emits Last-Modified header and returns HTTP 304 Not Modified if the browser's
 * cache is up to date, halting execution to save processing and network resources.
 */
function check_last_modified(int $lastModifiedTime): void
{
    if (headers_sent()) {
        return;
    }

    $lastModifiedString = gmdate('D, d M Y H:i:s', $lastModifiedTime) . ' GMT';
    header('Last-Modified: ' . $lastModifiedString);

    // Check If-Modified-Since request headers
    $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;

    if ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModifiedTime) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
}
