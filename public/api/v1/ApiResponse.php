<?php
/**
 * ==============================================================================
 * GroCo RESTful API v1 Standard Response & Security Handler
 * ==============================================================================
 */

declare(strict_types=1);

class ApiResponse
{
    private static ?string $requestId = null;

    public static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = 'req_' . bin2hex(random_bytes(12));
        }
        return self::$requestId;
    }

    public static function send(int $httpCode, string $status, $data = null, ?array $meta = null, ?array $error = null): void
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Request-Id: ' . self::getRequestId());
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Idempotency-Key');
        }

        $payload = [
            'status'     => $status,
            'code'       => $httpCode,
            'request_id' => self::getRequestId(),
            'timestamp'  => date('c'),
            'data'       => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        if ($error !== null) {
            $payload['error'] = $error;
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    public static function success($data = null, ?array $meta = null, int $httpCode = 200): void
    {
        self::send($httpCode, 'success', $data, $meta, null);
    }

    public static function error(string $message, int $httpCode = 400, ?string $errorCode = 'BAD_REQUEST', ?array $details = null): void
    {
        $errorObj = [
            'code'    => $errorCode,
            'message' => $message,
        ];
        if ($details !== null) {
            $errorObj['details'] = $details;
        }
        self::send($httpCode, 'error', null, null, $errorObj);
    }

    public static function unauthorized(string $message = 'Authentication required'): void
    {
        self::error($message, 401, 'UNAUTHORIZED');
    }

    public static function forbidden(string $message = 'Insufficient permissions'): void
    {
        self::error($message, 403, 'FORBIDDEN');
    }

    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404, 'NOT_FOUND');
    }

    public static function rateLimited(string $message = 'Too many requests. Please slow down.'): void
    {
        self::error($message, 429, 'TOO_MANY_REQUESTS');
    }
}
