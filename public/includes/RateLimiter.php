<?php
/**
 * ==============================================================================
 * GroCo Enterprise Rate Limiter
 * ==============================================================================
 * Multi-driver token-bucket rate limiter supporting Redis, CacheService, and
 * session fallbacks. Protects authentication, public API, search, checkout,
 * and review submission endpoints against abuse.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheService.php';

class RateLimiter
{
    public const PREFIX = 'groco:ratelimit:';

    /**
     * Endpoint configuration presets (max requests per decay window)
     */
    public const PRESETS = [
        'login'            => ['max' => 5,   'decay' => 60],   // 5 per minute
        'register'         => ['max' => 3,   'decay' => 300],  // 3 per 5 minutes
        'password_reset'   => ['max' => 3,   'decay' => 300],  // 3 per 5 minutes
        'otp_verify'       => ['max' => 5,   'decay' => 300],  // 5 per 5 minutes
        'api_read'         => ['max' => 120, 'decay' => 60],   // 120 per minute
        'api_write'        => ['max' => 30,  'decay' => 60],   // 30 per minute
        'search'           => ['max' => 60,  'decay' => 60],   // 60 per minute
        'review_submit'    => ['max' => 5,   'decay' => 300],  // 5 per 5 minutes
        'checkout_attempt' => ['max' => 10,  'decay' => 60],   // 10 per minute
    ];

    /**
     * Determine if a key has exceeded maximum attempts
     */
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) >= $maxAttempts;
    }

    /**
     * Increment the attempt counter for a given key
     */
    public static function hit(string $key, int $decaySeconds = 60): int
    {
        $cacheKey = self::PREFIX . $key;
        $state = CacheService::get($cacheKey);

        $now = time();
        if (!$state || !is_array($state) || $now > ($state['expires_at'] ?? 0)) {
            $state = [
                'attempts'   => 1,
                'expires_at' => $now + $decaySeconds,
                'reset_at'   => $now + $decaySeconds
            ];
        } else {
            $state['attempts']++;
        }

        $remainingTtl = max(1, $state['expires_at'] - $now);
        CacheService::set($cacheKey, $state, $remainingTtl, ['ratelimit']);

        return (int)$state['attempts'];
    }

    /**
     * Get the number of attempts for a given key
     */
    public static function attempts(string $key): int
    {
        $cacheKey = self::PREFIX . $key;
        $state = CacheService::get($cacheKey);
        if (!$state || !is_array($state)) {
            return 0;
        }
        if (time() > ($state['expires_at'] ?? 0)) {
            CacheService::delete($cacheKey);
            return 0;
        }
        return (int)($state['attempts'] ?? 0);
    }

    /**
     * Get the seconds until the rate limit is reset
     */
    public static function availableIn(string $key): int
    {
        $cacheKey = self::PREFIX . $key;
        $state = CacheService::get($cacheKey);
        if (!$state || !is_array($state)) {
            return 0;
        }
        return max(0, ($state['reset_at'] ?? time()) - time());
    }

    /**
     * Reset the attempts counter for a given key
     */
    public static function reset(string $key): void
    {
        CacheService::delete(self::PREFIX . $key);
    }

    /**
     * Reset rate limit for a specific action and client identifier
     */
    public static function resetAction(string $action, ?string $identifier = null): void
    {
        $ip = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $key = md5($action . ':' . $ip);
        self::reset($key);
    }

    /**
     * Convenient check method that increments and returns whether request is allowed
     *
     * @param string $action Action name or preset
     * @param string|null $identifier Client identifier (IP, user ID, or composite)
     * @param int|null $customMax Custom max attempts override
     * @param int|null $customDecay Custom decay seconds override
     * @return bool True if allowed, False if limit exceeded
     */
    public static function check(string $action, ?string $identifier = null, ?int $customMax = null, ?int $customDecay = null): bool
    {
        $ip = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $key = md5($action . ':' . $ip);

        $config = self::PRESETS[$action] ?? ['max' => 60, 'decay' => 60];
        $max = $customMax ?? $config['max'];
        $decay = $customDecay ?? $config['decay'];

        if (self::tooManyAttempts($key, $max)) {
            return false;
        }

        self::hit($key, $decay);
        return true;
    }
}
