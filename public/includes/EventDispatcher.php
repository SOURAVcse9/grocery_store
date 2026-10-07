<?php
/**
 * ==============================================================================
 * GroCo Core Event Dispatcher
 * ==============================================================================
 * Lightweight, synchronous/asynchronous event bus conforming to PSR-14 concepts.
 * Facilitates decoupling of domain events (e.g. order placed, product updated,
 * cache invalidated) from transport, notification, and indexing layers.
 * ==============================================================================
 */

declare(strict_types=1);

class EventDispatcher
{
    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private static array $listeners = [];

    /** @var array<int, array{event: string, payload: array, timestamp: float}> */
    private static array $eventHistory = [];

    /**
     * Register an event listener callback with priority ordering (higher = earlier)
     */
    public static function listen(string $eventName, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }

        self::$listeners[$eventName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort listeners by priority descending
        usort(self::$listeners[$eventName], function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }

    /**
     * Dispatch an event to all registered listeners
     */
    public static function dispatch(string $eventName, array $payload = []): array
    {
        $payload['event_name'] = $eventName;
        $payload['dispatched_at'] = microtime(true);

        self::$eventHistory[] = [
            'event'     => $eventName,
            'payload'   => $payload,
            'timestamp' => $payload['dispatched_at']
        ];

        $results = [];

        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listener) {
                try {
                    $result = call_user_func($listener['callback'], $payload);
                    $results[] = [
                        'status' => 'success',
                        'result' => $result
                    ];
                } catch (Throwable $e) {
                    error_log("EventDispatcher error on [{$eventName}]: " . $e->getMessage());
                    $results[] = [
                        'status' => 'error',
                        'error'  => $e->getMessage()
                    ];
                }
            }
        }

        // Also trigger wildcard listeners if registered
        if (isset(self::$listeners['*'])) {
            foreach (self::$listeners['*'] as $listener) {
                try {
                    call_user_func($listener['callback'], $eventName, $payload);
                } catch (Throwable $e) {
                    error_log("EventDispatcher wildcard error on [{$eventName}]: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Check if an event has registered listeners
     */
    public static function hasListeners(string $eventName): bool
    {
        return !empty(self::$listeners[$eventName]) || !empty(self::$listeners['*']);
    }

    /**
     * Get recorded events in current request context
     */
    public static function getHistory(): array
    {
        return self::$eventHistory;
    }

    /**
     * Reset listeners and event history (useful for unit tests)
     */
    public static function reset(): void
    {
        self::$listeners = [];
        self::$eventHistory = [];
    }

    /**
     * Register core default event subscriptions
     */
    public static function registerDefaultSubscribers(): void
    {
        // When product is updated or deleted: invalidate cache and trigger search sync
        self::listen('product.updated', function(array $payload) {
            if (class_exists('CacheService')) {
                CacheService::invalidateCatalog();
            }
        }, 100);

        self::listen('product.created', function(array $payload) {
            if (class_exists('CacheService')) {
                CacheService::invalidateCatalog();
            }
        }, 100);

        self::listen('order.created', function(array $payload) {
            if (class_exists('CacheService')) {
                CacheService::forget('admin_dashboard_metrics');
            }
        }, 50);
    }
}

// Automatically register default subscribers on load
EventDispatcher::registerDefaultSubscribers();
