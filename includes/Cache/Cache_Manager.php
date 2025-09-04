<?php
/**
 * Cache Manager for WCF Data Replacer
 *
 * @package WCFDR\Cache
 * @since 1.0.0
 */

namespace WCFDR\Cache;

use WCFDR\Logger\Logger;

/**
 * Optimized Cache Manager Class with Singleton Pattern
 */
final class Cache_Manager {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Cache backend
     */
    private $backend;
    
    /**
     * Default TTL in seconds
     */
    private const DEFAULT_TTL = 3600;
    
    /**
     * Maximum TTL in seconds
     */
    private const MAX_TTL = 86400;
    
    /**
     * Cache prefix
     */
    private const CACHE_PREFIX = 'wcfdr_';
    
    /**
     * Private constructor
     */
    private function __construct() {
        // No dependencies loaded in constructor to avoid circular dependency
        $this->logger = null;
        $this->backend = $this->determine_backend();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
    
    /**
     * Get single instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize cache manager
     */
    public function init(): void {
        // Schedule cleanup
        if (!wp_next_scheduled('wcfdr_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'wcfdr_cache_cleanup');
        }
        add_action('wcfdr_cache_cleanup', [$this, 'cleanup']);
        
        $this->log_info('Cache manager hooks initialized');
    }
    
    /**
     * Determine best cache backend
     */
    private function determine_backend() {
        // Try Redis first
        if (class_exists('Redis') && defined('WP_REDIS_HOST')) {
            try {
                $redis = new \Redis();
                $redis->connect(WP_REDIS_HOST, WP_REDIS_PORT ?? 6379);
                if ($redis->ping() === '+PONG') {
                    return new RedisBackend($redis);
                }
            } catch (\Exception $e) {
                $this->log_warning('Redis connection failed, falling back to object cache', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Try Memcached
        if (class_exists('Memcached') && defined('WP_MEMCACHED_HOST')) {
            try {
                $memcached = new \Memcached();
                $memcached->addServer(WP_MEMCACHED_HOST, WP_MEMCACHED_PORT ?? 11211);
                if ($memcached->getStats()) {
                    return new MemcachedBackend($memcached);
                }
            } catch (\Exception $e) {
                $this->log_warning('Memcached connection failed, falling back to object cache', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Fallback to WordPress object cache
        return new WordPressBackend();
    }
    
    /**
     * Set cache value
     */
    public function set(string $key, $value, int $ttl = null): bool {
        try {
            $ttl = $this->normalize_ttl($ttl);
            $full_key = $this->get_full_key($key);
            
            $result = $this->backend->set($full_key, $value, $ttl);
            
            if ($result) {
                $this->log_debug('Cache set successfully', [
                    'key' => $key,
                    'ttl' => $ttl
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->log_error('Cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get cache value
     */
    public function get(string $key) {
        try {
            $full_key = $this->get_full_key($key);
            $value = $this->backend->get($full_key);
            
            if ($value !== false) {
                $this->log_debug('Cache hit', ['key' => $key]);
            } else {
                $this->log_debug('Cache miss', ['key' => $key]);
            }
            
            return $value;
            
        } catch (\Exception $e) {
            $this->log_error('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete cache value
     */
    public function delete(string $key): bool {
        try {
            $full_key = $this->get_full_key($key);
            $result = $this->backend->delete($full_key);
            
            if ($result) {
                $this->log_debug('Cache deleted', ['key' => $key]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->log_error('Cache delete failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Check if key exists
     */
    public function exists(string $key): bool {
        try {
            $full_key = $this->get_full_key($key);
            return $this->backend->exists($full_key);
            
        } catch (\Exception $e) {
            $this->log_error('Cache exists check failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Increment cache value
     */
    public function increment(string $key, int $value = 1): int|false {
        try {
            $full_key = $this->get_full_key($key);
            return $this->backend->increment($full_key, $value);
            
        } catch (\Exception $e) {
            $this->log_error('Cache increment failed', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Decrement cache value
     */
    public function decrement(string $key, int $value = 1): int|false {
        try {
            $full_key = $this->get_full_key($key);
            return $this->backend->decrement($full_key, $value);
            
        } catch (\Exception $e) {
            $this->log_error('Cache decrement failed', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear(): bool {
        try {
            $result = $this->backend->clear();
            
            if ($result) {
                $this->log_info('Cache cleared successfully');
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->log_error('Cache clear failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function get_stats(): array {
        try {
            return $this->backend->get_stats();
            
        } catch (\Exception $e) {
            $this->log_error('Failed to get cache stats', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Cleanup expired cache
     */
    public function cleanup(): void {
        try {
            $cleaned = $this->backend->cleanup();
            
            if ($cleaned > 0) {
                $this->log_info('Cache cleanup completed', [
                    'cleaned_items' => $cleaned
                ]);
            }
            
        } catch (\Exception $e) {
            $this->log_error('Cache cleanup failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get full cache key with prefix
     */
    private function get_full_key(string $key): string {
        return self::CACHE_PREFIX . $key;
    }
    
    /**
     * Normalize TTL value
     */
    private function normalize_ttl(?int $ttl): int {
        if ($ttl === null) {
            return self::DEFAULT_TTL;
        }
        
        return min(max(1, $ttl), self::MAX_TTL);
    }
    
    /**
     * Reset instance (useful for testing)
     */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * Get logger (lazy loaded)
     */
    private function get_logger() {
        if ($this->logger === null) {
            try {
                $this->logger = \WCFDR\Core\Container::getInstance()->get('logger');
            } catch (\Exception $e) {
                // Fallback to error_log if container fails
                $this->logger = null;
            }
        }
        return $this->logger;
    }
    
    /**
     * Log info message (with fallback)
     */
    private function log_info(string $message, array $context = []): void {
        $logger = $this->get_logger();
        if ($logger) {
            $logger->info($message, $context);
        }
    }
    
    /**
     * Log warning message (with fallback)
     */
    private function log_warning(string $message, array $context = []): void {
        $logger = $this->get_logger();
        if ($logger) {
            $logger->warning($message, $context);
        }
    }
    
    /**
     * Log error message (with fallback)
     */
    private function log_error(string $message, array $context = []): void {
        $logger = $this->get_logger();
        if ($logger) {
            $logger->error($message, $context);
        }
    }
    
    /**
     * Log debug message (with fallback)
     */
    private function log_debug(string $message, array $context = []): void {
        $logger = $this->get_logger();
        if ($logger) {
            $logger->debug($message, $context);
        }
    }
}

/**
 * Cache Backend Interface
 */
interface CacheBackend {
    public function set(string $key, $value, int $ttl): bool;
    public function get(string $key);
    public function delete(string $key): bool;
    public function exists(string $key): bool;
    public function increment(string $key, int $value): int|false;
    public function decrement(string $key, int $value): int|false;
    public function clear(): bool;
    public function get_stats(): array;
    public function cleanup(): int;
}

/**
 * WordPress Object Cache Backend
 */
class WordPressBackend implements CacheBackend {
    
    public function set(string $key, $value, int $ttl): bool {
        return wp_cache_set($key, $value, 'wcfdr', $ttl);
    }
    
    public function get(string $key) {
        return wp_cache_get($key, 'wcfdr');
    }
    
    public function delete(string $key): bool {
        return wp_cache_delete($key, 'wcfdr');
    }
    
    public function exists(string $key): bool {
        return wp_cache_get($key, 'wcfdr') !== false;
    }
    
    public function increment(string $key, int $value): int|false {
        return wp_cache_increment($key, $value, 'wcfdr');
    }
    
    public function decrement(string $key, int $value): int|false {
        return wp_cache_decrement($key, $value, 'wcfdr');
    }
    
    public function clear(): bool {
        return wp_cache_flush_group('wcfdr');
    }
    
    public function get_stats(): array {
        return [
            'backend' => 'WordPress Object Cache',
            'available' => true
        ];
    }
    
    public function cleanup(): int {
        // WordPress handles cleanup automatically
        return 0;
    }
}

/**
 * Redis Backend
 */
class RedisBackend implements CacheBackend {
    
    private $redis;
    
    public function __construct(\Redis $redis) {
        $this->redis = $redis;
    }
    
    public function set(string $key, $value, int $ttl): bool {
        $serialized = serialize($value);
        return $this->redis->setex($key, $ttl, $serialized);
    }
    
    public function get(string $key) {
        $value = $this->redis->get($key);
        if ($value === false) {
            return false;
        }
        return unserialize($value);
    }
    
    public function delete(string $key): bool {
        return $this->redis->del($key) > 0;
    }
    
    public function exists(string $key): bool {
        return $this->redis->exists($key);
    }
    
    public function increment(string $key, int $value): int|false {
        return $this->redis->incrBy($key, $value);
    }
    
    public function decrement(string $key, int $value): int|false {
        return $this->redis->decrBy($key, $value);
    }
    
    public function clear(): bool {
        $keys = $this->redis->keys('wcfdr_*');
        if (empty($keys)) {
            return true;
        }
        return $this->redis->del($keys) > 0;
    }
    
    public function get_stats(): array {
        $info = $this->redis->info();
        return [
            'backend' => 'Redis',
            'version' => $info['redis_version'] ?? 'unknown',
            'used_memory' => $info['used_memory_human'] ?? 'unknown',
            'connected_clients' => $info['connected_clients'] ?? 'unknown'
        ];
    }
    
    public function cleanup(): int {
        // Redis handles TTL automatically
        return 0;
    }
}

/**
 * Memcached Backend
 */
class MemcachedBackend implements CacheBackend {
    
    private $memcached;
    
    public function __construct(\Memcached $memcached) {
        $this->memcached = $memcached;
    }
    
    public function set(string $key, $value, int $ttl): bool {
        $serialized = serialize($value);
        return $this->memcached->set($key, $serialized, $ttl);
    }
    
    public function get(string $key) {
        $value = $this->memcached->get($key);
        if ($value === false) {
            return false;
        }
        return unserialize($value);
    }
    
    public function delete(string $key): bool {
        return $this->memcached->delete($key);
    }
    
    public function exists(string $key): bool {
        return $this->memcached->get($key) !== false;
    }
    
    public function increment(string $key, int $value): int|false {
        return $this->memcached->increment($key, $value);
    }
    
    public function decrement(string $key, int $value): int|false {
        return $this->memcached->decrement($key, $value);
    }
    
    public function clear(): bool {
        return $this->memcached->flush();
    }
    
    public function get_stats(): array {
        $stats = $this->memcached->getStats();
        $server_stats = reset($stats);
        
        return [
            'backend' => 'Memcached',
            'version' => $server_stats['version'] ?? 'unknown',
            'uptime' => $server_stats['uptime'] ?? 'unknown',
            'bytes_read' => $server_stats['bytes_read'] ?? 'unknown',
            'bytes_written' => $server_stats['bytes_written'] ?? 'unknown'
        ];
    }
    
    public function cleanup(): int {
        // Memcached handles TTL automatically
        return 0;
    }
}
