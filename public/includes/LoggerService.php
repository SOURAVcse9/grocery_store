<?php
/**
 * ==============================================================================
 * GroCo Modern Structured Observability & Audit Logging Service
 * ==============================================================================
 * High-performance JSON logging with request tracing, duration profiling,
 * automatic secret redaction, and security incident classification.
 * ==============================================================================
 */

declare(strict_types=1);

class LoggerService
{
    private static string $logDir = '';
    private static ?string $requestId = null;
    private static float $startTime = 0.0;

    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'secret', 'api_key',
        'token', 'csrf_token', 'card_number', 'cvv', 'auth', 'cookie'
    ];

    public static function init(): void
    {
        if (self::$startTime === 0.0) {
            self::$startTime = microtime(true);
        }

        self::$logDir = defined('STORAGE_PATH') 
            ? STORAGE_PATH . '/logs' 
            : dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }

        if (self::$requestId === null) {
            self::$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? ('req_' . bin2hex(random_bytes(10)));
        }
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();

        $durationMs = round((microtime(true) - self::$startTime) * 1000, 2);
        $userId = $_SESSION['user_id'] ?? ($_SESSION['customer_id'] ?? null);
        $adminId = $_SESSION['admin_id'] ?? null;
        $route = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $sanitizedContext = self::redactSensitiveData($context);

        $record = [
            'timestamp'   => date('c'),
            'level'       => strtoupper($level),
            'request_id'  => self::$requestId,
            'duration_ms' => $durationMs,
            'route'       => $route,
            'ip'          => $ip,
            'user_id'     => $userId,
            'admin_id'    => $adminId,
            'message'     => $message,
            'context'     => $sanitizedContext
        ];

        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $targetFile = self::$logDir . '/app_' . date('Y-m-d') . '.log';

        @file_put_contents($targetFile, $json, FILE_APPEND | LOCK_EX);

        // Also append critical security incidents to dedicated security.log
        if (in_array(strtoupper($level), ['SECURITY', 'CRITICAL', 'EMERGENCY'], true)) {
            @file_put_contents(self::$logDir . '/security.log', $json, FILE_APPEND | LOCK_EX);
        }
    }

    public static function info(string $msg, array $ctx = []): void { self::log('INFO', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::log('WARNING', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::log('ERROR', $msg, $ctx); }
    public static function security(string $msg, array $ctx = []): void { self::log('SECURITY', $msg, $ctx); }

    /**
     * Recursively mask sensitive fields
     */
    private static function redactSensitiveData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $k => $v) {
            $lowerKey = strtolower((string)$k);
            if (in_array($lowerKey, self::REDACTED_KEYS, true)) {
                $sanitized[$k] = '******** [REDACTED]';
            } elseif (is_array($v)) {
                $sanitized[$k] = self::redactSensitiveData($v);
            } else {
                $sanitized[$k] = $v;
            }
        }
        return $sanitized;
    }
}
