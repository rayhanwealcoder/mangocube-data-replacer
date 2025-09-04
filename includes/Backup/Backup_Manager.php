<?php
/**
 * Backup Manager for Post Meta Values
 *
 * @package WCFDR\Backup
 * @since 1.0.0
 */

namespace WCFDR\Backup;

use WCFDR\Database\Database_Manager;
use WCFDR\Validator\Validator;
use WCFDR\Sanitizer\Sanitizer;
use WCFDR\Logger\Logger;
use WCFDR\Cache\Cache_Manager;

/**
 * Optimized Backup Manager Class with Singleton Pattern
 */
final class Backup_Manager {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Database manager
     */
    private $database;
    
    /**
     * Validator
     */
    private $validator;
    
    /**
     * Sanitizer
     */
    private $sanitizer;
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Cache manager
     */
    private $cache;
    
    /**
     * Maximum revisions to keep per meta key
     */
    private const MAX_REVISIONS = 10;
    
    /**
     * Backup table name
     */
    private $backup_table;
    
    /**
     * Private constructor
     */
    private function __construct() {
        global $wpdb;
        
        $this->database = \WCFDR\Core\Container::getInstance()->get('database');
        $this->validator = \WCFDR\Core\Container::getInstance()->get('validator');
        $this->sanitizer = \WCFDR\Core\Container::getInstance()->get('sanitizer');
        $this->logger = \WCFDR\Core\Container::getInstance()->get('logger');
        $this->cache = \WCFDR\Core\Container::getInstance()->get('cache');
        
        $this->backup_table = $wpdb->prefix . 'wcfdr_meta_backups';
        
        $this->ensure_backup_table_exists();
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
     * Initialize backup manager
     */
    public function init(): void {
        // AJAX handlers are now registered in the main plugin file
        // to avoid conflicts and centralize all AJAX handling
        
        // Ensure backup table exists
        add_action('init', [$this, 'ensure_backup_table_exists']);
    }
    
    /**
     * Ensure backup table exists
     */
    private function ensure_backup_table_exists(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->backup_table} (
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create a backup before making changes
     */
    public function create_backup(array $params): array {
        try {
            // Validate and sanitize parameters
            $validated = $this->validate_backup_params($params);
            
            // Generate unique revision ID
            $revision_id = $this->generate_revision_id();
            
            // Get current user info
            $current_user = wp_get_current_user();
            
            // Create backup record
            $backup_data = [
                'revision_id' => $revision_id,
                'post_id' => $validated['post_id'],
                'meta_key' => $validated['meta_key'],
                'old_value' => $validated['old_value'],
                'new_value' => $validated['new_value'] ?? '',
                'actor_id' => $current_user->ID,
                'actor_name' => $current_user->display_name,
                'created_at' => current_time('mysql'),
                'batch_id' => $validated['batch_id'] ?? null
            ];
            
            $result = $this->insert_backup_record($backup_data);
            
            if (!$result) {
                throw new \Exception('Failed to insert backup record');
            }
            
            // Cleanup old revisions if needed
            $this->cleanup_old_revisions($validated['post_id'], $validated['meta_key']);
            
            // Clear cache
            $this->cache->delete("wcfdr_backups_{$validated['post_id']}_{$validated['meta_key']}");
            
            $this->logger->info('Backup created successfully', [
                'revision_id' => $revision_id,
                'post_id' => $validated['post_id'],
                'meta_key' => $validated['meta_key'],
                'actor_id' => $current_user->ID
            ]);
            
            return [
                'success' => true,
                'revision_id' => $revision_id,
                'message' => 'Backup created successfully'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Backup creation failed: ' . $e->getMessage(), [
                'params' => $params,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get backups for a specific post meta
     */
    public function get_backups(int $post_id, string $meta_key): array {
        try {
            // Check cache first
            $cache_key = "wcfdr_backups_{$post_id}_{$meta_key}";
            $cached_result = $this->cache->get($cache_key);
            
            if ($cached_result !== false) {
                return $cached_result;
            }
            
            global $wpdb;
            
            $query = $wpdb->prepare(
                "SELECT revision_id, old_value, new_value, actor_name, created_at, batch_id 
                 FROM {$this->backup_table} 
                 WHERE post_id = %d AND meta_key = %s 
                 ORDER BY created_at DESC",
                $post_id,
                $meta_key
            );
            
            $results = $wpdb->get_results($query, ARRAY_A);
            
            if ($results === null) {
                throw new \Exception('Database query failed');
            }
            
            $backups = [];
            foreach ($results as $row) {
                $backups[] = [
                    'revision_id' => $row['revision_id'],
                    'old_value' => $row['old_value'],
                    'new_value' => $row['new_value'],
                    'actor_name' => $row['actor_name'],
                    'created_at' => $row['created_at'],
                    'batch_id' => $row['batch_id'],
                    'value_excerpt' => $this->create_value_excerpt($row['old_value'])
                ];
            }
            
            $result = [
                'success' => true,
                'revisions' => $backups,
                'total' => count($backups)
            ];
            
            // Cache results for 5 minutes
            $this->cache->set($cache_key, $result, 300);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get backups: ' . $e->getMessage(), [
                'post_id' => $post_id,
                'meta_key' => $meta_key,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'revisions' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * Restore a specific revision
     */
    public function restore_revision(string $revision_id): array {
        try {
            global $wpdb;
            
            // Get backup record
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->backup_table} WHERE revision_id = %s",
                $revision_id
            );
            
            $backup = $wpdb->get_row($query, ARRAY_A);
            
            if (!$backup) {
                throw new \Exception('Backup not found');
            }
            
            // Create backup of current value before restore
            $current_value = get_post_meta($backup['post_id'], $backup['meta_key'], true);
            
            $pre_restore_backup = $this->create_backup([
                'post_id' => $backup['post_id'],
                'meta_key' => $backup['meta_key'],
                'old_value' => $current_value,
                'new_value' => $backup['old_value'],
                'batch_id' => 'restore_' . $revision_id
            ]);
            
            if (!$pre_restore_backup['success']) {
                throw new \Exception('Failed to create pre-restore backup');
            }
            
            // Restore the old value
            $result = update_post_meta($backup['post_id'], $backup['meta_key'], $backup['old_value']);
            
            if ($result === false) {
                throw new \Exception('Failed to restore post meta');
            }
            
            // Clear cache
            $this->cache->delete("wcfdr_backups_{$backup['post_id']}_{$backup['meta_key']}");
            
            $this->logger->info('Revision restored successfully', [
                'revision_id' => $revision_id,
                'post_id' => $backup['post_id'],
                'meta_key' => $backup['meta_key'],
                'actor_id' => get_current_user_id()
            ]);
            
            return [
                'success' => true,
                'restored_revision_id' => $revision_id,
                'message' => 'Revision restored successfully'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Revision restore failed: ' . $e->getMessage(), [
                'revision_id' => $revision_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore latest backup for a post meta
     */
    public function restore_latest(int $post_id, string $meta_key): array {
        try {
            $backups = $this->get_backups($post_id, $meta_key);
            
            if (!$backups['success'] || empty($backups['revisions'])) {
                throw new \Exception('No backups found for this post meta');
            }
            
            $latest_backup = $backups['revisions'][0];
            
            return $this->restore_revision($latest_backup['revision_id']);
            
        } catch (\Exception $e) {
            $this->logger->error('Latest restore failed: ' . $e->getMessage(), [
                'post_id' => $post_id,
                'meta_key' => $meta_key,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore all backups in a batch
     */
    public function restore_batch(string $batch_id): array {
        try {
            global $wpdb;
            
            // Get all backups in the batch
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->backup_table} WHERE batch_id = %s ORDER BY created_at ASC",
                $batch_id
            );
            
            $backups = $wpdb->get_results($query, ARRAY_A);
            
            if (empty($backups)) {
                throw new \Exception('No backups found for this batch');
            }
            
            $restored = 0;
            $failed = 0;
            $errors = [];
            
            foreach ($backups as $backup) {
                $result = $this->restore_revision($backup['revision_id']);
                
                if ($result['success']) {
                    $restored++;
                } else {
                    $failed++;
                    $errors[] = [
                        'post_id' => $backup['post_id'],
                        'meta_key' => $backup['meta_key'],
                        'error' => $result['error']
                    ];
                }
            }
            
            return [
                'success' => true,
                'restored' => $restored,
                'failed' => $failed,
                'errors' => $errors,
                'message' => "Restored {$restored} items, {$failed} failed"
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Batch restore failed: ' . $e->getMessage(), [
                'batch_id' => $batch_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cleanup old revisions for a specific post meta
     */
    private function cleanup_old_revisions(int $post_id, string $meta_key): void {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT id FROM {$this->backup_table} 
             WHERE post_id = %d AND meta_key = %s 
             ORDER BY created_at DESC 
             LIMIT 99999 OFFSET %d",
            $post_id,
            $meta_key,
            self::MAX_REVISIONS
        );
        
        $old_revisions = $wpdb->get_col($query);
        
        if (!empty($old_revisions)) {
            $ids = implode(',', array_map('intval', $old_revisions));
            $wpdb->query("DELETE FROM {$this->backup_table} WHERE id IN ({$ids})");
            
            $this->logger->info('Cleaned up old revisions', [
                'post_id' => $post_id,
                'meta_key' => $meta_key,
                'deleted_count' => count($old_revisions)
            ]);
        }
    }
    
    /**
     * Cleanup old backups (scheduled task)
     */
    public function cleanup_old_backups(): void {
        global $wpdb;
        
        // Delete backups older than 30 days
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->backup_table} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        if ($deleted > 0) {
            $this->logger->info('Cleaned up old backups', [
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoff_date
            ]);
        }
    }
    
    /**
     * Insert backup record into database
     */
    private function insert_backup_record(array $data): bool {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->backup_table,
            $data,
            [
                '%s', // revision_id
                '%d', // post_id
                '%s', // meta_key
                '%s', // old_value
                '%s', // new_value
                '%d', // actor_id
                '%s', // actor_name
                '%s', // created_at
                '%s'  // batch_id
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Generate unique revision ID
     */
    private function generate_revision_id(): string {
        return 'wcfdr_' . uniqid() . '_' . time();
    }
    
    /**
     * Create value excerpt for display
     */
    private function create_value_excerpt(string $value): string {
        $max_length = 100;
        
        if (strlen($value) <= $max_length) {
            return $value;
        }
        
        return substr($value, 0, $max_length) . '...';
    }
    
    /**
     * Validate backup parameters
     */
    private function validate_backup_params(array $params): array {
        $validated = [];
        
        // Required fields
        $validated['post_id'] = intval($params['post_id'] ?? 0);
        $validated['meta_key'] = $this->sanitizer->sanitize_text_field($params['meta_key'] ?? '');
        $validated['old_value'] = $params['old_value'] ?? '';
        
        if (!$validated['post_id'] || empty($validated['meta_key'])) {
            throw new \InvalidArgumentException('Post ID and meta key are required');
        }
        
        // Optional fields
        $validated['new_value'] = $params['new_value'] ?? '';
        $validated['batch_id'] = $this->sanitizer->sanitize_text_field($params['batch_id'] ?? null);
        
        return $validated;
    }
    
    /**
     * Handle AJAX backups request
     */
    public function handle_ajax_backups(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $post_id = intval($_GET['post_id'] ?? 0);
        $meta_key = sanitize_text_field($_GET['meta_key'] ?? '');
        
        if (!$post_id || empty($meta_key)) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }
        
        $results = $this->get_backups($post_id, $meta_key);
        wp_send_json($results);
    }
    
    /**
     * Handle AJAX restore request
     */
    public function handle_ajax_restore(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $revision_id = sanitize_text_field($_POST['revision_id'] ?? '');
        
        if (empty($revision_id)) {
            wp_send_json_error(['message' => 'Revision ID is required']);
        }
        
        $results = $this->restore_revision($revision_id);
        wp_send_json($results);
    }
    
    /**
     * Handle AJAX restore all request
     */
    public function handle_ajax_restore_all(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');
        
        if (empty($batch_id)) {
            wp_send_json_error(['message' => 'Batch ID is required']);
        }
        
        $results = $this->restore_batch($batch_id);
        wp_send_json($results);
    }
    
    /**
     * Reset instance (useful for testing)
     */
    public static function reset(): void {
        self::$instance = null;
    }
    
    /**
     * Get database manager (lazy loaded)
     */
    private function get_database() {
        if ($this->database === null) {
            $this->database = \WCFDR\Core\Container::getInstance()->get('database');
        }
        return $this->database;
    }
    
    /**
     * Get validator (lazy loaded)
     */
    private function get_validator() {
        if ($this->validator === null) {
            $this->validator = \WCFDR\Core\Container::getInstance()->get('validator');
        }
        return $this->validator;
    }
    
    /**
     * Get sanitizer (lazy loaded)
     */
    private function get_sanitizer() {
        if ($this->sanitizer === null) {
            $this->sanitizer = \WCFDR\Core\Container::getInstance()->get('sanitizer');
        }
        return $this->sanitizer;
    }
    
    /**
     * Get logger (lazy loaded)
     */
    private function get_logger() {
        if ($this->logger === null) {
            $this->logger = \WCFDR\Core\Container::getInstance()->get('logger');
        }
        return $this->logger;
    }
    
    /**
     * Get cache manager (lazy loaded)
     */
    private function get_cache() {
        if ($this->cache === null) {
            $this->cache = \WCFDR\Core\Container::getInstance()->get('cache');
        }
        return $this->cache;
    }
}
