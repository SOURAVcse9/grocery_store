<?php
/**
 * ==========================================================================
 * csrf.php — CSRF Protection
 * ==========================================================================
 * Refactored from the original project (the logic was already solid —
 * token generation + hash_equals comparison — just modernized and
 * documented). Session is started by dbconnect.php before this file is
 * ever required, but the guard below keeps this file safe to include
 * standalone too (e.g. in isolated tests).
 * ==========================================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * csrf_token()
 *
 * Returns the current CSRF token, generating one on first call.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * csrf_field()
 *
 * Ready-to-echo hidden input for HTML forms:
 *   <form method="post"><?= csrf_field() ?> ... </form>
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * verify_csrf_token()
 *
 * Timing-safe comparison. Returns false (never throws) so callers can
 * decide how to respond (redirect vs JSON 403).
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * verify_csrf_or_fail()
 *
 * Convenience guard for the top of every state-changing script
 * (process_login.php, ajax/add_to_cart.php, admin forms, etc.).
 * Responds appropriately whether the caller expected JSON or HTML.
 */
function verify_csrf_or_fail(bool $isJson = false): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        if ($isJson) {
            json_response(false, 'Invalid or expired security token. Please refresh and try again.', [], 419);
        }

        http_response_code(419);
        die('Invalid or expired security token. Please go back, refresh the page, and try again.');
    }
}

/**
 * verify_csrf()
 *
 * Safe boolean evaluation of the POST token.
 */
function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return verify_csrf_token($token);
}

/**
 * regenerate_csrf_token()
 *
 * Called after login/logout so a stale token from a previous session
 * state can never be replayed.
 */
function regenerate_csrf_token(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
