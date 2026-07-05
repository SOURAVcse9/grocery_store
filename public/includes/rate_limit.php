<?php
/**
 * ==========================================================================
 * public/includes/rate_limit.php — Action Rate Limiter
 * ==========================================================================
 * Implements session-based client request rate limiting to block brute force
 * submissions on forms and API endpoints.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * check_rate_limit()
 *
 * Verifies if the client has exceeded request count thresholds for a given
 * action within a specified time span, returning HTTP 429 on violations.
 */
function check_rate_limit(string $action, int $maxRequests = 5, int $seconds = 60, bool $respondJson = true): bool
{
    // Start session if not already initialized
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $now = time();

    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }

    if (!isset($_SESSION['rate_limits'][$action])) {
        // Initial setup for this action
        $_SESSION['rate_limits'][$action] = [
            'start_time' => $now,
            'count'      => 1
        ];
        return true;
    }

    $limit = &$_SESSION['rate_limits'][$action];

    // If time span has elapsed, reset count
    if (($now - $limit['start_time']) >= $seconds) {
        $limit['start_time'] = $now;
        $limit['count'] = 1;
        return true;
    }

    // Increment count
    $limit['count']++;

    // Check if count exceeds limit
    if ($limit['count'] > $maxRequests) {
        // Log brute-force attempts
        require_once __DIR__ . '/logger.php';
        log_action('RATE_LIMIT_TRIGGERED', "Action '{$action}' exceeded limit. Count: " . $limit['count']);

        if ($respondJson) {
            if (!headers_sent()) {
                header('HTTP/1.1 429 Too Many Requests');
                header('Retry-After: ' . $seconds);
            }
            json_response(false, 'Too many requests. Please wait a moment before trying again.', [
                'retry_after_seconds' => $seconds
            ], 429);
        } else {
            if (!headers_sent()) {
                header('HTTP/1.1 429 Too Many Requests');
                header('Retry-After: ' . $seconds);
            }
            echo "<h1>429 Too Many Requests</h1><p>You have made too many requests. Please try again later.</p>";
            exit;
        }
    }

    return true;
}
