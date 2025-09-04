<?php
/**
 * Search Engine for Post Meta
 *
 * @package WCFDR\Search
 * @since 1.0.0
 */

namespace WCFDR\Search;

use WCFDR\Database\Database_Manager;
use WCFDR\Validator\Validator;
use WCFDR\Sanitizer\Sanitizer;
use WCFDR\Logger\Logger;
use WCFDR\Cache\Cache_Manager;

/**
 * Optimized Search Engine Class with Singleton Pattern
 */
final class Search_Engine {
    
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
     * Cache TTL in seconds
     */
    private const CACHE_TTL = 300; // 5 minutes
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Defer dependency loading to reduce memory usage
        $this->database = null;
        $this->validator = null;
        $this->sanitizer = null;
        $this->logger = null;
        $this->cache = null;
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
     * Initialize search engine
     */
    public function init(): void {
        // AJAX handlers are now registered in the main plugin file
        // to avoid conflicts and centralize all AJAX handling
    }
    
    /**
     * Search post meta with optimized queries
     */
    public function search(array $params): array {
        try {
            // Validate and sanitize parameters
            $validated = $this->validate_search_params($params);
            
            // Check cache first
            $cache_key = $this->generate_cache_key($validated);
            $cached_result = $this->get_cache()->get($cache_key);
            
            if ($cached_result !== false) {
                return $cached_result;
            }
            
            // Build and execute search query
            $results = $this->execute_search($validated);
            
            // Cache results
            $this->get_cache()->set($cache_key, $results, self::CACHE_TTL);
            
            return $results;
            
        } catch (\Exception $e) {
            $this->get_logger()->error('Search failed: ' . $e->getMessage(), [
                'params' => $params,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rows' => [],
                'total' => 0,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Validate search parameters
     */
    private function validate_search_params(array $params): array {
        $validated = [];
        
        // Post type is now optional - we can search across all post types
        $validated['post_type'] = $this->get_sanitizer()->sanitize_text_field($params['post_type'] ?? '');
        
        // Meta key is optional if value is provided
        $validated['meta_key'] = $this->get_sanitizer()->sanitize_text_field($params['meta_key'] ?? '');
        $validated['value'] = $this->get_sanitizer()->sanitize_text_field($params['value'] ?? '');
        
        // Validate and sanitize per_page
        $max_per_page = get_option('wcfdr_settings', ['maxResultsPerPage' => 1500])['maxResultsPerPage'] ?? 1500;
        $validated['per_page'] = min($max_per_page, max(1, intval($params['per_page'] ?? 20)));
        
        // Require either meta_key or value to be present
        if (empty($validated['meta_key']) && empty($validated['value'])) {
            throw new \InvalidArgumentException('Either meta key or value must be provided');
        }
        
        // Optional fields with defaults
        $validated['page'] = max(1, intval($params['page'] ?? 1));
        $validated['case_sensitive'] = (bool) ($params['case_sensitive'] ?? false);
        $validated['regex'] = (bool) ($params['regex'] ?? false);
        $validated['dry_run'] = (bool) ($params['dry_run'] ?? false);
        
        return $validated;
    }
    
    /**
     * Execute the search query
     */
    private function execute_search(array $params): array {
        global $wpdb;
        
        $offset = ($params['page'] - 1) * $params['per_page'];
        
        // Build WHERE clause
        $where_clauses = [];
        $where_values = [];
        
        // Always exclude ACF fields from search results
        $where_clauses[] = "pm.meta_key NOT LIKE '_field%'";
        $where_clauses[] = "pm.meta_key NOT REGEXP '^field_[a-f0-9]{13}$'";
        $where_clauses[] = "pm.meta_key NOT LIKE '_required_plugins%'";
        $where_clauses[] = "pm.meta_key NOT LIKE '_acf%'";
        $where_clauses[] = "pm.meta_key NOT LIKE '_acf_%'";
        
        // Post type is optional - only add if provided
        if (!empty($params['post_type'])) {
            $where_clauses[] = "p.post_type = %s";
            $where_values[] = $params['post_type'];
        }
        
        // Meta key is optional - only add if provided
        if (!empty($params['meta_key'])) {
            $where_clauses[] = "pm.meta_key = %s";
            $where_values[] = $params['meta_key'];
        }
        
        // Add value filter if provided
        if (!empty($params['value'])) {
            if ($params['regex']) {
                $where_clauses[] = "pm.meta_value REGEXP %s";
                $where_values[] = $params['value'];
            } else {
                $operator = $params['case_sensitive'] ? 'LIKE BINARY' : 'LIKE';
                $where_clauses[] = "pm.meta_value {$operator} %s";
                $where_values[] = '%' . $wpdb->esc_like($params['value']) . '%';
            }
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Count total results
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.meta_id) as total 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE {$where_sql}",
            $where_values
        );
        
        $total = $wpdb->get_var($count_sql);
        
        if ($total === null) {
            throw new \Exception('Failed to count search results');
        }
        
        // Get paginated results
        $results_sql = $wpdb->prepare(
            "SELECT 
                p.ID as post_id,
                p.post_title,
                p.post_type,
                pm.meta_key,
                pm.meta_value,
                p.post_status,
                p.post_date
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE {$where_sql}
             ORDER BY p.post_date DESC
             LIMIT %d OFFSET %d",
            array_merge($where_values, [$params['per_page'], $offset])
        );
        
        $rows = $wpdb->get_results($results_sql, ARRAY_A);
        
        if ($rows === null) {
            throw new \Exception('Failed to fetch search results');
        }
        
        // Add backup information
        $rows = $this->add_backup_info($rows);
        
        // Decode HTML entities in meta values for display
        $rows = $this->decode_meta_values($rows);
        
        return [
            'success' => true,
            'rows' => $rows,
            'total' => (int) $total,
            'total_pages' => ceil($total / $params['per_page']),
            'page' => $params['page'],
            'per_page' => $params['per_page']
        ];
    }
    
    /**
     * Add backup information to search results
     */
    private function add_backup_info(array $rows): array {
        if (empty($rows)) {
            return $rows;
        }
        
        global $wpdb;
        
        // Check if backup table exists before querying
        $backup_table = $wpdb->prefix . 'wcfdr_meta_backups';
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $backup_table
            )
        );
        
        if (!$table_exists) {
            // Table doesn't exist yet, add default backup info
            foreach ($rows as &$row) {
                $row['has_backup'] = false;
                $row['backup_count'] = 0;
            }
            return $rows;
        }
        
        $post_ids = array_column($rows, 'post_id');
        $meta_keys = array_column($rows, 'meta_key');
        
        // Get backup counts for these posts/meta keys
        $backup_sql = $wpdb->prepare(
            "SELECT post_id, meta_key, COUNT(*) as backup_count 
             FROM {$backup_table} 
             WHERE post_id IN (" . implode(',', array_fill(0, count($post_ids), '%d')) . ")
             AND meta_key IN (" . implode(',', array_fill(0, count($meta_keys), '%s')) . ")
             GROUP BY post_id, meta_key",
            array_merge($post_ids, $meta_keys)
        );
        
        $backup_counts = $wpdb->get_results($backup_sql, OBJECT_K);
        
        // Add backup info to each row
        foreach ($rows as &$row) {
            $key = $row['post_id'] . '_' . $row['meta_key'];
            $row['has_backup'] = isset($backup_counts[$key]) && $backup_counts[$key]->backup_count > 0;
            $row['backup_count'] = $backup_counts[$key]->backup_count ?? 0;
        }
        
        return $rows;
    }
    
    /**
     * Decode HTML entities in meta values for display
     */
    private function decode_meta_values(array $rows): array {
        foreach ($rows as &$row) {
            if (isset($row['meta_value']) && !empty($row['meta_value'])) {
                // Check if the meta value contains URL patterns or HTML entities
                if ($this->contains_url_patterns($row['meta_value']) || strpos($row['meta_value'], '&amp;') !== false) {
                    $row['meta_value'] = $this->decode_html_entities($row['meta_value']);
                }
            }
        }
        return $rows;
    }
    
    /**
     * Check if content contains URL patterns
     */
    private function contains_url_patterns(string $content): bool {
        // Check if content contains any URL-like patterns
        $url_patterns = [
            // Contains http:// or https://
            '/https?:\/\//i',
            // Contains domain-like patterns
            '/[a-zA-Z0-9-]+\.[a-zA-Z]{2,}/',
            // Contains query parameters
            '/\?[a-zA-Z0-9&=]+/',
            // Contains HTML entities that might be in URLs
            '/&[a-zA-Z]+;/',
            // Contains URL fragments
            '/#[a-zA-Z0-9_-]+/'
        ];
        
        foreach ($url_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Decode HTML entities in text, specifically handling &amp; in URLs
     */
    private function decode_html_entities(string $text): string {
        // Decode common HTML entities, especially &amp; which should become &
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Additional specific handling for URL-related entities
        $decoded = str_replace('&amp;', '&', $decoded);
        $decoded = str_replace('&lt;', '<', $decoded);
        $decoded = str_replace('&gt;', '>', $decoded);
        $decoded = str_replace('&quot;', '"', $decoded);
        $decoded = str_replace('&#039;', "'", $decoded);
        
        return $decoded;
    }
    
    /**
     * Get meta keys filtered by post type (dynamic from postmeta table)
     * Excludes ACF fields starting with _field and field_ format
     */
    public function get_meta_keys(string $post_type = ''): array {
        $cache_key = "meta_keys_v2_" . ($post_type ?: 'all');
        $cached = $this->get_cache()->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        if (!empty($post_type)) {
            // Get meta keys for specific post type, excluding ACF fields
            $sql = "
                SELECT 
                    pm.meta_key,
                    COUNT(*) as frequency
                 FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE p.post_type = %s 
                 AND pm.meta_key != '' 
                 AND pm.meta_key NOT LIKE '_field%'
                 AND pm.meta_key NOT REGEXP '^field_[a-f0-9]{13}$'
                 AND pm.meta_key NOT LIKE '_required_plugins%'
                 AND pm.meta_key NOT LIKE '_acf%'
                 AND pm.meta_key NOT LIKE '_acf_%'
                 GROUP BY pm.meta_key 
                 ORDER BY frequency DESC, pm.meta_key ASC
                 LIMIT 200
            ";
            $results = $wpdb->get_results($wpdb->prepare($sql, $post_type), ARRAY_A);
        } else {
            // Get all meta keys across all post types, excluding ACF fields
            $sql = "
                SELECT 
                    pm.meta_key,
                    COUNT(*) as frequency
                 FROM {$wpdb->postmeta} pm 
                 WHERE pm.meta_key != '' 
                 AND pm.meta_key NOT LIKE '_field%'
                 AND pm.meta_key NOT REGEXP '^field_[a-f0-9]{13}$'
                 AND pm.meta_key NOT LIKE '_required_plugins%'
                 AND pm.meta_key NOT LIKE '_acf%'
                 AND pm.meta_key NOT LIKE '_acf_%'
                 GROUP BY pm.meta_key 
                 ORDER BY frequency DESC, pm.meta_key ASC
                 LIMIT 200
            ";
            $results = $wpdb->get_results($sql, ARRAY_A);
        }
        
        if ($results === null) {
            return [];
        }
        
        // Log the results for debugging
        error_log('WCFDR: Meta keys query returned ' . count($results) . ' results');
        if (count($results) > 0) {
            error_log('WCFDR: First few meta keys: ' . implode(', ', array_slice(array_column($results, 'meta_key'), 0, 5)));
        }
        
        // Force cache invalidation for ACF filtering update
        // Cache key includes version to ensure new filtering takes effect
        
        // Cache for 15 minutes (longer cache since this is filtered data)
        $this->get_cache()->set($cache_key, $results, 900);
        
        return $results;
    }
    
    /**
     * Get post types with counts
     */
    public function get_post_types(): array {
        $cache_key = 'post_types_with_counts';
        $cached = $this->get_cache()->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $post_types = get_post_types(['public' => true], 'objects');
        $formatted = [];
        
        foreach ($post_types as $type => $object) {
            $count = wp_count_posts($type);
            $formatted[] = [
                'value' => $type,
                'label' => $object->labels->singular_name,
                'count' => $count->publish
            ];
        }
        
        // Cache for 5 minutes
        $this->get_cache()->set($cache_key, $formatted, 300);
        
        return $formatted;
    }
    
    /**
     * Generate cache key for search parameters
     */
    private function generate_cache_key(array $params): string {
        return 'wcfdr_search_' . md5(serialize($params));
    }
    
    /**
     * Handle AJAX search request
     */
    public function handle_ajax_search(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $results = $this->search($_POST);
        wp_send_json($results);
    }
    
    /**
     * Handle AJAX get meta keys request
     */
    public function handle_get_meta_keys(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $meta_keys = $this->get_meta_keys($post_type);
        
        wp_send_json_success($meta_keys);
    }
    
    /**
     * Handle AJAX get post types request
     */
    public function handle_get_post_types(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $post_types = $this->get_post_types();
        wp_send_json_success($post_types);
    }
    
    /**
     * Reset instance (useful for testing)
     */
    public static function reset(): void {
        self::$instance = null;
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
     * Get sanitizer (lazy loaded)
     */
    private function get_sanitizer() {
        if ($this->sanitizer === null) {
            $this->sanitizer = \WCFDR\Core\Container::getInstance()->get('sanitizer');
        }
        return $this->sanitizer;
    }
}
