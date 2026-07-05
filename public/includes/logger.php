<?php
/**
 * ==========================================================================
 * public/includes/logger.php — Audit Logging Helper
 * ==========================================================================
 * Logs critical security actions, errors, and logins to a log file.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * log_action()
 *
 * Appends audit entries (timestamps, user IDs, action names, client IP,
 * and user agents) to a secure local file.
 */
function log_action(string $action, string $message, ?int $userId = null): bool
{
    // Define audit log path inside app configurations directory
    $logDir = PUBLIC_PATH . '/logs';
    $logFile = $logDir . '/security_audit.log';

    try {
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN_AGENT';
        
        $resolvedUid = 'GUEST';
        if ($userId !== null) {
            $resolvedUid = $userId;
        } elseif (function_exists('current_user_id')) {
            $resolvedUid = current_user_id() ?? 'GUEST';
        }
        $uid = $resolvedUid;
        $timestamp = date('Y-m-d H:i:s');

        // Sanitize log content to prevent injection inside log readers
        $sanMsg = str_replace(["\r", "\n", "\t"], ' ', $message);

        $line = sprintf(
            "[%s] [IP:%s] [UID:%s] [ACT:%s] MSG: %s | UA: %s\n",
            $timestamp,
            $ip,
            $uid,
            strtoupper($action),
            $sanMsg,
            $agent
        );

        return file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) !== false;

    } catch (Exception $e) {
        // Fail silently during audit logs failure to keep page loading intact
        error_log('[log_action] Writing failed: ' . $e->getMessage());
        return false;
    }
}
