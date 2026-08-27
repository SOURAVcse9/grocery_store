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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $now = time();
    
    $hash = md5($ip . '_' . $action);
    $dir = 'C:/xampp/tmp/rate_limit_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    
    $filePath = $dir . '/rl_' . $hash . '.json';
    $limit = null;
    $useSession = false;
    
    if (is_dir($dir) && is_writable($dir)) {
        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content !== false) {
                $limit = json_decode($content, true);
            }
        }
    } else {
        $useSession = true;
    }
    
    if ($useSession) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        if (isset($_SESSION['rate_limits'][$action])) {
            $limit = $_SESSION['rate_limits'][$action];
        }
    }
    
    if (!$limit) {
        $limit = [
            'start_time' => $now,
            'count'      => 1
        ];
    } else {
        if (($now - $limit['start_time']) >= $seconds) {
            $limit['start_time'] = $now;
            $limit['count'] = 1;
        } else {
            $limit['count']++;
        }
    }
    
    if ($useSession) {
        $_SESSION['rate_limits'][$action] = $limit;
    } else {
        @file_put_contents($filePath, json_encode($limit));
        if (rand(1, 100) === 1) {
            $files = glob($dir . '/rl_*.json');
            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file) && (time() - filemtime($file) > 3600)) {
                        @unlink($file);
                    }
                }
            }
        }
    }
    
    if ($limit['count'] > $maxRequests) {
        require_once __DIR__ . '/logger.php';
        log_action('RATE_LIMIT_TRIGGERED', "Action '{$action}' exceeded limit. IP: {$ip}, Count: " . $limit['count']);

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
