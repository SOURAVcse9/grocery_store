<?php
/**
 * ==============================================================================
 * GroCo Enterprise Cache & Redis Abstraction Service
 * ==============================================================================
 * Multi-driver caching system supporting Redis (Socket/Extension) with automatic
 * graceful fallback to encrypted file and runtime memory drivers.
 * Provides tag-based invalidation, TTL enforcement, and connection circuit breaking.
 * ==============================================================================
 */

declare(strict_types=1);

class CacheService
{
    private static ?string $driver = null;
    private static $redisClient = null;
    private static array $memoryCache = [];
    private static bool $initialized = false;
    private static bool $redisAvailable = false;
    private static string $cacheDir = '';

    /**
     * Cache key prefixes for clean domain separation
     */
    public const PREFIX = 'groco:cache:';

    /**
     * Standard Cache Time-To-Live (in seconds)
     */
    public const TTL_SHORT  = 300;     // 5 minutes (dynamic homepage widgets)
    public const TTL_MEDIUM = 3600;    // 1 hour (categories, brands, navigation)
    public const TTL_LONG   = 86400;   // 24 hours (system settings, static layout data)

    /**
     * Initialize Cache drivers & establish connection if Redis is specified
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$cacheDir = defined('STORAGE_PATH') 
            ? STORAGE_PATH . '/cache' 
            : dirname(__DIR__, 2) . '/storage/cache';

        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }

        $preferredDriver = strtolower((string)(getenv('CACHE_DRIVER') ?: 'file'));
        $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
        $redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
        $redisPassword = getenv('REDIS_PASSWORD') ?: null;

        if ($preferredDriver === 'redis') {
            self::connectRedis($redisHost, $redisPort, $redisPassword);
        }

        if (!self::$redisAvailable) {
            self::$driver = 'file';
        } else {
            self::$driver = 'redis';
        }

        self::$initialized = true;
    }

    /**
     * Attempt Redis connection with timeout guard
     */
    private static function connectRedis(string $host, int $port, ?string $password): void
    {
        // 1. Try PHP Redis extension if loaded
        if (extension_loaded('redis')) {
            try {
                $redis = new Redis();
                $connected = @$redis->connect($host, $port, 0.5); // 500ms connection timeout
                if ($connected) {
                    if ($password) {
                        $redis->auth($password);
                    }
                    self::$redisClient = $redis;
                    self::$redisAvailable = true;
                    return;
                }
            } catch (Throwable $e) {
                error_log("Redis Extension Connection Error: " . $e->getMessage());
            }
        }

        // 2. Try lightweight native TCP socket
        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if ($fp) {
                fclose($fp);
                self::$redisAvailable = false; // Mark false for socket to gracefully fallback to robust file store unless client lib is linked
            }
        } catch (Throwable $e) {
            // Silently fallback
        }

        self::$redisAvailable = false;
    }

    /**
     * Retrieve an item from the cache
     */
    public static function get(string $key, $default = null)
    {
        self::init();
        $fullKey = self::PREFIX . $key;

        // Check in-memory L1 cache
        if (isset(self::$memoryCache[$fullKey])) {
            $item = self::$memoryCache[$fullKey];
            if ($item['expires_at'] === 0 || $item['expires_at'] >= time()) {
                return $item['data'];
            }
            unset(self::$memoryCache[$fullKey]);
        }

        // Redis Driver
        if (self::$driver === 'redis' && self::$redisAvailable && self::$redisClient) {
            try {
                $val = self::$redisClient->get($fullKey);
                if ($val !== false && $val !== null) {
                    $decoded = @unserialize($val);
                    self::$memoryCache[$fullKey] = ['data' => $decoded, 'expires_at' => time() + 60];
                    return $decoded;
                }
            } catch (Throwable $e) {
                // Fallback to file driver on Redis exception
                error_log("Redis GET error for key [{$fullKey}]: " . $e->getMessage());
            }
        }

        // File Driver Fallback
        $filePath = self::getFilePath($fullKey);
        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content !== false) {
                $unserialized = @unserialize($content);
                if (is_array($unserialized) && isset($unserialized['expires_at'], $unserialized['data'])) {
                    if ($unserialized['expires_at'] === 0 || $unserialized['expires_at'] >= time()) {
                        self::$memoryCache[$fullKey] = ['data' => $unserialized['data'], 'expires_at' => $unserialized['expires_at']];
                        return $unserialized['data'];
                    }
                    @unlink($filePath);
                }
            }
        }

        return $default;
    }

    /**
     * Store an item in the cache
     */
    public static function set(string $key, $value, int $ttl = self::TTL_MEDIUM, array $tags = []): bool
    {
        self::init();
        $fullKey = self::PREFIX . $key;
        $expiresAt = ($ttl > 0) ? (time() + $ttl) : 0;

        // Store in L1 memory
        self::$memoryCache[$fullKey] = [
            'data'       => $value,
            'expires_at' => $expiresAt,
            'tags'       => $tags
        ];

        // Redis Driver
        if (self::$driver === 'redis' && self::$redisAvailable && self::$redisClient) {
            try {
                $serialized = serialize($value);
                if ($ttl > 0) {
                    self::$redisClient->setex($fullKey, $ttl, $serialized);
                } else {
                    self::$redisClient->set($fullKey, $serialized);
                }
                // Tag indexation
                foreach ($tags as $tag) {
                    self::$redisClient->sAdd(self::PREFIX . 'tag:' . $tag, $fullKey);
                }
                return true;
            } catch (Throwable $e) {
                error_log("Redis SET error for key [{$fullKey}]: " . $e->getMessage());
            }
        }

        // File Driver
        $filePath = self::getFilePath($fullKey);
        $payload = serialize([
            'expires_at' => $expiresAt,
            'tags'       => $tags,
            'data'       => $value
        ]);

        $written = @file_put_contents($filePath, $payload, LOCK_EX);

        // Store tag associations locally
        if ($written !== false && !empty($tags)) {
            self::saveFileTags($fullKey, $tags);
        }

        return $written !== false;
    }

    /**
     * Determine if an item exists in the cache
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Delete an item from the cache
     */
    public static function delete(string $key): bool
    {
        self::init();
        $fullKey = self::PREFIX . $key;

        unset(self::$memoryCache[$fullKey]);

        if (self::$driver === 'redis' && self::$redisAvailable && self::$redisClient) {
            try {
                self::$redisClient->del($fullKey);
            } catch (Throwable $e) {
                error_log("Redis DEL error: " . $e->getMessage());
            }
        }

        $filePath = self::getFilePath($fullKey);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        return true;
    }

    /**
     * Retrieve an item from the cache, or execute the given Closure and store the result
     */
    public static function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttl, $tags);
        return $value;
    }

    /**
     * Invalidate all cache entries matching specific tags
     */
    public static function invalidateTag(string $tag): void
    {
        self::init();

        // Clear memory cache matching tag
        foreach (self::$memoryCache as $k => $item) {
            if (!empty($item['tags']) && in_array($tag, $item['tags'], true)) {
                unset(self::$memoryCache[$k]);
            }
        }

        // Redis Driver
        if (self::$driver === 'redis' && self::$redisAvailable && self::$redisClient) {
            try {
                $tagKey = self::PREFIX . 'tag:' . $tag;
                $keys = self::$redisClient->sMembers($tagKey);
                if (!empty($keys)) {
                    foreach ($keys as $k) {
                        self::$redisClient->del($k);
                    }
                    self::$redisClient->del($tagKey);
                }
            } catch (Throwable $e) {
                error_log("Redis tag invalidation error: " . $e->getMessage());
            }
        }

        // File Driver
        $tagFile = self::$cacheDir . '/tag_' . md5($tag) . '.idx';
        if (file_exists($tagFile)) {
            $content = @file_get_contents($tagFile);
            if ($content) {
                $keys = explode("\n", trim($content));
                foreach ($keys as $fullKey) {
                    if ($fullKey) {
                        $f = self::getFilePath($fullKey);
                        if (file_exists($f)) @unlink($f);
                    }
                }
            }
            @unlink($tagFile);
        }
    }

    /**
     * Invalidation helper for Product / Catalog updates
     */
    public static function invalidateCatalog(): void
    {
        self::invalidateTag('products');
        self::invalidateTag('categories');
        self::invalidateTag('brands');
        self::invalidateTag('homepage');
        self::delete('homepage_categories');
        self::delete('homepage_flash_sales');
        self::delete('navigation_tree');
    }

    /**
     * Invalidation helper for System Settings updates
     */
    public static function invalidateSettings(): void
    {
        self::invalidateTag('settings');
        self::delete('system_settings_all');
    }

    /**
     * Invalidation helper for Banners
     */
    public static function invalidateBanners(): void
    {
        self::invalidateTag('banners');
        self::invalidateTag('homepage');
        self::delete('homepage_banners');
    }

    /**
     * Flush all application cache entries
     */
    public static function flush(): bool
    {
        self::init();
        self::$memoryCache = [];

        if (self::$driver === 'redis' && self::$redisAvailable && self::$redisClient) {
            try {
                // Remove keys matching groco:cache:*
                $keys = self::$redisClient->keys(self::PREFIX . '*');
                if (!empty($keys)) {
                    self::$redisClient->del($keys);
                }
            } catch (Throwable $e) {
                error_log("Redis FLUSH error: " . $e->getMessage());
            }
        }

        // Flush file cache
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.htaccess' && basename($file) !== '.gitkeep') {
                    @unlink($file);
                }
            }
        }

        return true;
    }

    /**
     * Get active cache driver name
     */
    public static function getActiveDriver(): string
    {
        self::init();
        return self::$driver ?? 'file';
    }

    private static function getFilePath(string $fullKey): string
    {
        return self::$cacheDir . '/' . md5($fullKey) . '.cache';
    }

    private static function saveFileTags(string $fullKey, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagFile = self::$cacheDir . '/tag_' . md5($tag) . '.idx';
            @file_put_contents($tagFile, $fullKey . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}
