<?php
/**
 * Replace Engine for Post Meta Values
 *
 * @package WCFDR\Replace
 * @since 1.0.0
 */

namespace WCFDR\Replace;

use WCFDR\Database\Database_Manager;
use WCFDR\Validator\Validator;
use WCFDR\Sanitizer\Sanitizer;
use WCFDR\Logger\Logger;
use WCFDR\Backup\Backup_Manager;
use WCFDR\Utils\String_Helper;
use WCFDR\Utils\URL_Helper;
use WCFDR\Cache\Cache_Manager;

/**
 * Optimized Replace Engine Class with Singleton Pattern
 */
final class Replace_Engine {
    
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
     * Backup manager
     */
    private $backup;
    
    /**
     * String helper
     */
    private $string_helper;
    
    /**
     * URL helper
     */
    private $url_helper;
    
    /**
     * Cache manager
     */
    private $cache;
    
    /**
     * Regex timeout in milliseconds
     */
    private const REGEX_TIMEOUT = 5000;
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Defer dependency loading to reduce memory usage
        $this->database = null;
        $this->validator = null;
        $this->sanitizer = null;
        $this->logger = null;
        $this->backup = null;
        $this->string_helper = null;
        $this->url_helper = null;
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
     * Initialize replace engine
     */
    public function init(): void {
        // AJAX handlers are now registered in the main plugin file
        // to avoid conflicts and centralize all AJAX handling
    }
    
    /**
     * Preview replacements without executing them
     */
    public function preview(array $params): array {
        try {
            // Validate and sanitize parameters
            $validated = $this->validate_replace_params($params);
            
            // Check cache first
            $cache_key = $this->generate_preview_cache_key($validated);
            $cached_result = $this->get_cache()->get($cache_key);
            
            if ($cached_result !== false) {
                return $cached_result;
            }
            
            // Get search results for preview
            $search_engine = WCFDR\Search\Search_Engine::getInstance();
            $search_params = [
                'post_type' => $validated['post_type'],
                'meta_key' => $validated['meta_key'],
                'value' => $validated['value_filter'] ?? '',
                'case_sensitive' => $validated['case_sensitive'],
                'regex' => $validated['regex'],
                'per_page' => $validated['limit'] ?? 100,
                'page' => 1
            ];
            
            $search_results = $search_engine->search($search_params);
            
            if (!$search_results['success']) {
                throw new \Exception($search_results['error'] ?? 'Search failed');
            }
            
            // Process preview for each row
            $preview_rows = [];
            foreach ($search_results['rows'] as $row) {
                $preview = $this->preview_single_replacement(
                    $row['meta_value'],
                    $validated['find'],
                    $validated['replace'],
                    $validated['mode']
                );
                
                if ($preview['will_change']) {
                    $preview_rows[] = [
                        'post_id' => $row['post_id'],
                        'post_title' => $row['post_title'],
                        'meta_before' => $row['meta_value'],
                        'meta_after' => $preview['new_value'],
                        'match_count' => $preview['match_count'],
                        'changes' => $preview['changes']
                    ];
                }
            }
            
            $result = [
                'success' => true,
                'rows' => $preview_rows,
                'total' => count($preview_rows),
                'total_pages' => 1,
                'preview_mode' => true
            ];
            
            // Cache preview results for 2 minutes
            $this->get_cache()->set($cache_key, $result, 120);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->get_logger()->error('Preview failed: ' . $e->getMessage(), [
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
     * Execute replacements
     */
    public function execute(array $params): array {
        try {
            // Validate and sanitize parameters
            $validated = $this->validate_replace_params($params);
            
            if (empty($validated['confirm'])) {
                throw new \Exception('Confirmation required for execution');
            }
            
            // Get search results for replacement
            $search_engine = WCFDR\Search\Search_Engine::getInstance();
            $search_params = [
                'post_type' => $validated['post_type'],
                'meta_key' => $validated['meta_key'],
                'value' => $validated['value_filter'] ?? '',
                'case_sensitive' => $validated['case_sensitive'],
                'regex' => $validated['regex'],
                'per_page' => $validated['limit'] ?? 1000,
                'page' => 1
            ];
            
            $search_results = $search_engine->search($search_params);
            
            if (!$search_results['success']) {
                throw new \Exception($search_results['error'] ?? 'Search failed');
            }
            
            // Execute replacements
            $results = $this->execute_replacements($search_results['rows'], $validated);
            
            // Log the operation
            $this->get_logger()->info('Replace operation completed', [
                'user_id' => get_current_user_id(),
                'params' => $validated,
                'results' => $results
            ]);
            
            return [
                'success' => true,
                'updated' => $results['updated'],
                'failed' => $results['failed'],
                'items' => $results['items'],
                'backup_batch_id' => $results['backup_batch_id']
            ];
            
        } catch (\Exception $e) {
            $this->get_logger()->error('Replace execution failed: ' . $e->getMessage(), [
                'params' => $params,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'updated' => 0,
                'failed' => 0,
                'items' => []
            ];
        }
    }
    
    /**
     * Preview a single replacement
     */
    private function preview_single_replacement(string $old_value, string $find, string $replace, string $mode): array {
        $changes = [];
        $match_count = 0;
        $new_value = $old_value;
        
        try {
            switch ($mode) {
                case 'plain':
                    $new_value = $this->replace_plain_text($old_value, $find, $replace, false);
                    break;
                    
                case 'plain_cs':
                    $new_value = $this->replace_plain_text($old_value, $find, $replace, true);
                    break;
                    
                case 'regex':
                    $new_value = $this->replace_regex($old_value, $find, $replace);
                    break;
                    
                case 'url':
                    $new_value = $this->replace_url($old_value, $find, $replace);
                    break;
                    
                case 'url_segment':
                    $new_value = $this->replace_url_segment($old_value, $find, $replace);
                    break;
                    
                case 'prefix_swap':
                    $new_value = $this->replace_prefix_swap($old_value, $find, $replace);
                    break;
                    
                case 'full_text':
                    $new_value = $replace;
                    break;
                    
                default:
                    throw new \InvalidArgumentException("Unknown replace mode: {$mode}");
            }
            
            $will_change = ($new_value !== $old_value);
            
            if ($will_change) {
                $changes[] = [
                    'type' => 'replacement',
                    'description' => "Changed from '{$old_value}' to '{$new_value}'"
                ];
            }
            
        } catch (\Exception $e) {
            $changes[] = [
                'type' => 'error',
                'description' => $e->getMessage()
            ];
            $new_value = $old_value;
        }
        
        return [
            'will_change' => ($new_value !== $old_value),
            'new_value' => $new_value,
            'match_count' => $match_count,
            'changes' => $changes
        ];
    }
    
    /**
     * Execute replacements on multiple rows
     */
    private function execute_replacements(array $rows, array $params): array {
        global $wpdb;
        
        $updated = 0;
        $failed = 0;
        $items = [];
        $batch_id = uniqid('wcfdr_', true);
        
        foreach ($rows as $row) {
            try {
                // Create backup before replacement
                $backup_result = $this->get_backup()->create_backup([
                    'post_id' => $row['post_id'],
                    'meta_key' => $row['meta_key'],
                    'old_value' => $row['meta_value'],
                    'batch_id' => $batch_id
                ]);
                
                if (!$backup_result['success']) {
                    throw new \Exception('Failed to create backup: ' . $backup_result['error']);
                }
                
                // Perform replacement
                $new_value = $this->perform_replacement(
                    $row['meta_value'],
                    $params['find'],
                    $params['replace'],
                    $params['mode']
                );
                
                // Update the meta value
                $update_result = update_post_meta($row['post_id'], $row['meta_key'], $new_value);
                
                if ($update_result === false) {
                    throw new \Exception('Failed to update post meta');
                }
                
                $updated++;
                $items[] = [
                    'post_id' => $row['post_id'],
                    'status' => 'success',
                    'meta_key' => $row['meta_key']
                ];
                
            } catch (\Exception $e) {
                $failed++;
                $items[] = [
                    'post_id' => $row['post_id'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'meta_key' => $row['meta_key']
                ];
            }
        }
        
        return [
            'updated' => $updated,
            'failed' => $failed,
            'items' => $items,
            'backup_batch_id' => $batch_id
        ];
    }
    
    /**
     * Perform a single replacement
     */
    private function perform_replacement(string $old_value, string $find, string $replace, string $mode): string {
        switch ($mode) {
            case 'plain':
                return $this->replace_plain_text($old_value, $find, $replace, false);
                
            case 'plain_cs':
                return $this->replace_plain_text($old_value, $find, $replace, true);
                
            case 'regex':
                return $this->replace_regex($old_value, $find, $replace);
                
            case 'url':
                return $this->replace_url($old_value, $find, $replace);
                
            case 'url_segment':
                return $this->replace_url_segment($old_value, $find, $replace);
                
            case 'prefix_swap':
                return $this->replace_prefix_swap($old_value, $find, $replace);
                
            case 'full_text':
                return $replace;
                
            default:
                throw new \InvalidArgumentException("Unknown replace mode: {$mode}");
        }
    }
    
    /**
     * Replace plain text
     */
    private function replace_plain_text(string $text, string $find, string $replace, bool $case_sensitive): string {
        if ($case_sensitive) {
            return str_replace($find, $replace, $text);
        } else {
            return str_ireplace($find, $replace, $text);
        }
    }
    
    /**
     * Replace using regex
     */
    private function replace_regex(string $text, string $pattern, string $replace): string {
        // Set regex timeout
        set_time_limit(self::REGEX_TIMEOUT / 1000);
        
        // Validate regex pattern
        if (@preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException('Invalid regex pattern: ' . preg_last_error_msg());
        }
        
        $result = preg_replace($pattern, $replace, $text);
        
        if ($result === null) {
            throw new \Exception('Regex replacement failed');
        }
        
        return $result;
    }
    
    /**
     * Replace URL
     */
    private function replace_url(string $url, string $find, string $replace): string {
        return $this->get_url_helper()->replace_url($url, $find, $replace);
    }
    
    /**
     * Replace URL segment
     */
    private function replace_url_segment(string $url, string $find, string $replace): string {
        return $this->get_url_helper()->replace_url_segment($url, $find, $replace);
    }
    
    /**
     * Replace prefix swap
     */
    private function replace_prefix_swap(string $text, string $from_prefix, string $to_prefix): string {
        if (strpos($text, $from_prefix) === 0) {
            return $to_prefix . substr($text, strlen($from_prefix));
        }
        return $text;
    }
    
    /**
     * Validate replace parameters
     */
    private function validate_replace_params(array $params): array {
        $validated = [];
        
        // Required fields
        $validated['find'] = $this->get_sanitizer()->sanitize_text_field($params['find'] ?? '');
        $validated['replace'] = $this->get_sanitizer()->sanitize_text_field($params['replace'] ?? '');
        $validated['mode'] = $this->get_sanitizer()->sanitize_text_field($params['mode'] ?? 'plain');
        $validated['meta_key'] = $this->get_sanitizer()->sanitize_text_field($params['meta_key'] ?? '');
        $validated['post_type'] = $this->get_sanitizer()->sanitize_text_field($params['post_type'] ?? '');
        
        if (empty($validated['find']) || empty($validated['meta_key']) || empty($validated['post_type'])) {
            throw new \InvalidArgumentException('Find, meta_key, and post_type are required');
        }
        
        // Optional fields with defaults
        $validated['value_filter'] = $this->get_sanitizer()->sanitize_text_field($params['value_filter'] ?? '');
        $validated['case_sensitive'] = (bool) ($params['case_sensitive'] ?? false);
        $validated['limit'] = min(5000, max(1, intval($params['limit'] ?? 1000)));
        $validated['confirm'] = (bool) ($params['confirm'] ?? false);
        
        // Validate mode
        $valid_modes = ['plain', 'plain_cs', 'regex', 'url', 'url_segment', 'prefix_swap', 'full_text'];
        if (!in_array($validated['mode'], $valid_modes)) {
            throw new \InvalidArgumentException('Invalid replace mode');
        }
        
        return $validated;
    }
    
    /**
     * Generate cache key for preview
     */
    private function generate_preview_cache_key(array $params): string {
        return 'wcfdr_preview_' . md5(serialize($params));
    }
    
    /**
     * Handle AJAX preview request
     */
    public function handle_ajax_preview(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $results = $this->preview($_POST);
        wp_send_json($results);
    }
    
    /**
     * Handle AJAX replace request
     */
    public function handle_ajax_replace(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $results = $this->execute($_POST);
        wp_send_json($results);
    }
    
    /**
     * Handle AJAX update row request
     */
    public function handle_ajax_update_row(): void {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $meta_key = sanitize_text_field($_POST['meta_key'] ?? '');
        $new_value = sanitize_text_field($_POST['new_value'] ?? '');
        
        if (!$post_id || empty($meta_key)) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }
        
        try {
            // Create backup
            $old_value = get_post_meta($post_id, $meta_key, true);
            $backup_result = $this->get_backup()->create_backup([
                'post_id' => $post_id,
                'meta_key' => $meta_key,
                'old_value' => $old_value,
                'batch_id' => uniqid('wcfdr_row_', true)
            ]);
            
            if (!$backup_result['success']) {
                throw new \Exception('Failed to create backup');
            }
            
            // Update meta
            $result = update_post_meta($post_id, $meta_key, $new_value);
            
            if ($result === false) {
                throw new \Exception('Failed to update post meta');
            }
            
            wp_send_json_success([
                'post_id' => $post_id,
                'meta_key' => $meta_key,
                'message' => 'Row updated successfully'
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
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
     * Get backup manager (lazy loaded)
     */
    private function get_backup() {
        if ($this->backup === null) {
            $this->backup = \WCFDR\Core\Container::getInstance()->get('backup');
        }
        return $this->backup;
    }
    
    /**
     * Get string helper (lazy loaded)
     */
    private function get_string_helper() {
        if ($this->string_helper === null) {
            $this->string_helper = \WCFDR\Core\Container::getInstance()->get('string_helper');
        }
        return $this->string_helper;
    }
    
    /**
     * Get URL helper (lazy loaded)
     */
    private function get_url_helper() {
        if ($this->url_helper === null) {
            $this->url_helper = \WCFDR\Core\Container::getInstance()->get('url_helper');
        }
        return $this->url_helper;
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
