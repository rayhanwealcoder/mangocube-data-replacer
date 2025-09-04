<?php
/**
 * Database Manager for WCF Data Replacer
 *
 * @package WCFDR\Database
 * @since 1.0.0
 */

namespace WCFDR\Database;

use WCFDR\Logger\Logger;
use WCFDR\Cache\Cache_Manager;

/**
 * Optimized Database Manager Class with Singleton Pattern
 */
final class Database_Manager {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Cache manager
     */
    private $cache;
    
    /**
     * Database prefix
     */
    private $prefix;
    
    /**
     * Private constructor
     */
    private function __construct() {
        global $wpdb;
        
        $this->logger = \WCFDR\Core\Container::getInstance()->get('logger');
        $this->cache = \WCFDR\Core\Container::getInstance()->get('cache');
        $this->prefix = $wpdb->prefix;
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
     * Initialize database manager
     */
    public function init(): void {
        // Ensure tables exist
        $this->ensure_tables_exist();
        
        // Add cleanup hooks
        add_action('wp_scheduled_delete', [$this, 'cleanup_old_data']);
    }
    
    /**
     * Ensure required tables exist
     */
    private function ensure_tables_exist(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Backup table
        $backup_table = $this->prefix . 'wcfdr_meta_backups';
        $backup_sql = "CREATE TABLE IF NOT EXISTS {$backup_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            revision_id varchar(50) NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            old_value longtext NOT NULL,
            new_value longtext NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            actor_name varchar(100) NOT NULL,
            created_at datetime NOT NULL,
            batch_id varchar(100) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY revision_id (revision_id),
            KEY post_meta (post_id, meta_key),
            KEY batch_id (batch_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        // Logs table
        $logs_table = $this->prefix . 'wcfdr_operation_logs';
        $logs_sql = "CREATE TABLE IF NOT EXISTS {$logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            details longtext NOT NULL,
            created_at datetime NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            PRIMARY KEY (id),
            KEY operation_type (operation_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($backup_sql);
        dbDelta($logs_sql);
        
        // Update logger's database logging status after creating tables
        try {
            $logger = $this->get_logger();
            if (method_exists($logger, 'update_database_logging_status')) {
                $logger->update_database_logging_status();
            }
        } catch (\Exception $e) {
            // Logger might not be available yet, ignore
        }
        
        $this->logger->info('Database tables ensured', [
            'backup_table' => $backup_table,
            'logs_table' => $logs_table
        ]);
    }
    
    /**
     * Get post meta with optimized query
     */
    public function get_post_meta_batch(array $post_ids, string $meta_key): array {
        global $wpdb;
        
        if (empty($post_ids)) {
            return [];
        }
        
        $cache_key = 'wcfdr_meta_batch_' . md5(serialize($post_ids) . $meta_key);
        $cached_result = $this->cache->get($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT post_id, meta_value 
             FROM {$wpdb->postmeta} 
             WHERE post_id IN ({$placeholders}) 
             AND meta_key = %s",
            array_merge($post_ids, [$meta_key])
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($results === null) {
            $this->logger->error('Failed to get post meta batch', [
                'post_ids' => $post_ids,
                'meta_key' => $meta_key,
                'error' => $wpdb->last_error
            ]);
            return [];
        }
        
        $formatted_results = [];
        foreach ($results as $row) {
            $formatted_results[$row['post_id']] = $row['meta_value'];
        }
        
        // Cache for 2 minutes
        $this->cache->set($cache_key, $formatted_results, 120);
        
        return $formatted_results;
    }
    
    /**
     * Update post meta in batch
     */
    public function update_post_meta_batch(array $updates): array {
        global $wpdb;
        
        if (empty($updates)) {
            return ['updated' => 0, 'failed' => 0, 'errors' => []];
        }
        
        $updated = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($updates as $update) {
            try {
                $result = update_post_meta(
                    $update['post_id'],
                    $update['meta_key'],
                    $update['meta_value']
                );
                
                if ($result !== false) {
                    $updated++;
                    
                    // Clear cache for this post meta
                    $cache_key = 'wcfdr_meta_batch_' . md5($update['post_id'] . $update['meta_key']);
                    $this->cache->delete($cache_key);
                } else {
                    $failed++;
                    $errors[] = [
                        'post_id' => $update['post_id'],
                        'meta_key' => $update['meta_key'],
                        'error' => 'Database update failed'
                    ];
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'post_id' => $update['post_id'],
                    'meta_key' => $update['meta_key'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->logger->info('Batch post meta update completed', [
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($updates)
        ]);
        
        return [
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    /**
     * Get post meta statistics
     */
    public function get_meta_statistics(string $post_type = null): array {
        global $wpdb;
        
        $cache_key = 'wcfdr_meta_stats_' . ($post_type ?? 'all');
        $cached_result = $this->cache->get($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $where_clause = '';
        $params = [];
        
        if ($post_type) {
            $where_clause = "WHERE p.post_type = %s";
            $params[] = $post_type;
        }
        
        $query = "
            SELECT 
                pm.meta_key,
                COUNT(*) as count,
                AVG(LENGTH(pm.meta_value)) as avg_length,
                MAX(LENGTH(pm.meta_value)) as max_length,
                MIN(LENGTH(pm.meta_value)) as min_length
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            {$where_clause}
            GROUP BY pm.meta_key
            ORDER BY count DESC
            LIMIT 100
        ";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($results === null) {
            $this->logger->error('Failed to get meta statistics', [
                'post_type' => $post_type,
                'error' => $wpdb->last_error
            ]);
            return [];
        }
        
        // Cache for 10 minutes
        $this->cache->set($cache_key, $results, 600);
        
        return $results;
    }
    
    /**
     * Search post meta with advanced filtering
     */
    public function search_post_meta(array $filters): array {
        global $wpdb;
        
        $where_conditions = [];
        $params = [];
        
        // Post type filter
        if (!empty($filters['post_type'])) {
            $where_conditions[] = "p.post_type = %s";
            $params[] = $filters['post_type'];
        }
        
        // Meta key filter
        if (!empty($filters['meta_key'])) {
            $where_conditions[] = "pm.meta_key = %s";
            $params[] = $filters['meta_key'];
        }
        
        // Value filter
        if (!empty($filters['value'])) {
            if (!empty($filters['regex'])) {
                $where_conditions[] = "pm.meta_value REGEXP %s";
                $params[] = $filters['value'];
            } else {
                $operator = !empty($filters['case_sensitive']) ? 'LIKE' : 'LIKE';
                $value = !empty($filters['case_sensitive']) ? $filters['value'] : '%' . $filters['value'] . '%';
                $where_conditions[] = "pm.meta_value {$operator} %s";
                $params[] = $value;
            }
        }
        
        // Status filter
        if (!empty($filters['post_status'])) {
            $where_conditions[] = "p.post_status = %s";
            $params[] = $filters['post_status'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Count query
        $count_query = "
            SELECT COUNT(DISTINCT pm.post_id) as total
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            {$where_clause}
        ";
        
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        
        $total = $wpdb->get_var($count_query);
        
        // Main query with pagination
        $limit = min(200, max(1, intval($filters['per_page'] ?? 20)));
        $offset = (intval($filters['page'] ?? 1) - 1) * $limit;
        
        $main_query = "
            SELECT 
                p.ID as post_id,
                p.post_title,
                p.post_type,
                p.post_status,
                pm.meta_key,
                pm.meta_value,
                p.post_date
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            {$where_clause}
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $main_query = $wpdb->prepare($main_query, $params);
        $results = $wpdb->get_results($main_query, ARRAY_A);
        
        if ($results === null) {
            $this->logger->error('Failed to search post meta', [
                'filters' => $filters,
                'error' => $wpdb->last_error
            ]);
            return [
                'rows' => [],
                'total' => 0,
                'total_pages' => 0,
                'page' => intval($filters['page'] ?? 1),
                'per_page' => $limit
            ];
        }
        
        return [
            'rows' => $results,
            'total' => intval($total),
            'total_pages' => ceil(intval($total) / $limit),
            'page' => intval($filters['page'] ?? 1),
            'per_page' => $limit
        ];
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup_old_data(): void {
        global $wpdb;
        
        // Cleanup old logs (older than 90 days)
        $logs_table = $this->prefix . 'wcfdr_operation_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        
        $deleted_logs = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$logs_table} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        if ($deleted_logs > 0) {
            $this->logger->info('Cleaned up old operation logs', [
                'deleted_count' => $deleted_logs,
                'cutoff_date' => $cutoff_date
            ]);
        }
        
        // Clear expired cache
        $this->cache->cleanup();
    }
    
    /**
     * Get database size information
     */
    public function get_database_size(): array {
        global $wpdb;
        
        $cache_key = 'wcfdr_db_size';
        $cached_result = $this->cache->get($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $backup_table = $this->prefix . 'wcfdr_meta_backups';
        $logs_table = $this->prefix . 'wcfdr_operation_logs';
        
        $backup_size = $wpdb->get_var("SELECT SUM(LENGTH(old_value) + LENGTH(new_value)) FROM {$backup_table}");
        $logs_size = $wpdb->get_var("SELECT SUM(LENGTH(details)) FROM {$logs_table}");
        
        $backup_count = $wpdb->get_var("SELECT COUNT(*) FROM {$backup_table}");
        $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
        
        $result = [
            'backup_table' => [
                'size' => intval($backup_size ?? 0),
                'count' => intval($backup_count ?? 0)
            ],
            'logs_table' => [
                'size' => intval($logs_size ?? 0),
                'count' => intval($logs_count ?? 0)
            ],
            'total_size' => intval(($backup_size ?? 0) + ($logs_size ?? 0)),
            'total_records' => intval(($backup_count ?? 0) + ($logs_count ?? 0))
        ];
        
        // Cache for 1 hour
        $this->cache->set($cache_key, $result, 3600);
        
        return $result;
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
     * Get cache manager (lazy loaded)
     */
    private function get_cache() {
        if ($this->cache === null) {
            try {
                $this->cache = \WCFDR\Core\Container::getInstance()->get('cache');
            } catch (\Exception $e) {
                // Fallback to null if container fails
                $this->cache = null;
            }
        }
        return $this->cache;
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
     * Log error message (with fallback)
     */
    private function log_error(string $message, array $context = []): void {
        $logger = $this->get_logger();
        if ($logger) {
            $logger->error($message, $context);
        }
    }
}
