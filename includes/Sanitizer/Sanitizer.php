<?php
/**
 * Sanitizer for WCF Data Replacer
 *
 * @package WCFDR\Sanitizer
 * @since 1.0.0
 */

namespace WCFDR\Sanitizer;

use WCFDR\Logger\Logger;

/**
 * Optimized Sanitizer Class with Singleton Pattern
 */
final class Sanitizer {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Sanitization methods cache
     */
    private $methods_cache = [];
    
    /**
     * Custom sanitizers
     */
    private $custom_sanitizers = [];
    
    /**
     * Private constructor
     */
    private function __construct() {
        // No dependencies loaded in constructor to avoid circular dependency
        $this->logger = null;
        $this->register_default_sanitizers();
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
     * Initialize sanitizer
     */
    public function init(): void {
        $this->log_info('Sanitizer initialized');
    }
    
    /**
     * Register default sanitizers
     */
    private function register_default_sanitizers(): void {
        // Text sanitizers
        $this->custom_sanitizers['text'] = [$this, 'sanitize_text'];
        $this->custom_sanitizers['html'] = [$this, 'sanitize_html'];
        $this->custom_sanitizers['email'] = [$this, 'sanitize_email'];
        $this->custom_sanitizers['url'] = [$this, 'sanitize_url'];
        $this->custom_sanitizers['filename'] = [$this, 'sanitize_filename'];
        $this->custom_sanitizers['sql'] = [$this, 'sanitize_sql'];
        $this->custom_sanitizers['json'] = [$this, 'sanitize_json'];
        $this->custom_sanitizers['regex'] = [$this, 'sanitize_regex'];
        
        // Numeric sanitizers
        $this->custom_sanitizers['int'] = [$this, 'sanitize_int'];
        $this->custom_sanitizers['float'] = [$this, 'sanitize_float'];
        $this->custom_sanitizers['hex'] = [$this, 'sanitize_hex'];
        
        // Array sanitizers
        $this->custom_sanitizers['array'] = [$this, 'sanitize_array'];
        $this->custom_sanitizers['assoc_array'] = [$this, 'sanitize_assoc_array'];
        
        // Special sanitizers
        $this->custom_sanitizers['meta_key'] = [$this, 'sanitize_meta_key'];
        $this->custom_sanitizers['post_type'] = [$this, 'sanitize_post_type'];
        $this->custom_sanitizers['taxonomy'] = [$this, 'sanitize_taxonomy'];
        $this->custom_sanitizers['user_role'] = [$this, 'sanitize_user_role'];
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
     * Sanitize text field
     */
    public function sanitize_text_field($value): string {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = stripslashes($value);
        $value = wp_strip_all_tags($value);
        
        return $value;
    }
    
    /**
     * Sanitize textarea
     */
    public function sanitize_textarea_field($value): string {
        if (is_array($value)) {
            $value = implode("\n", $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = stripslashes($value);
        
        // Allow some basic HTML tags
        $allowed_tags = [
            'br' => [],
            'p' => [],
            'strong' => [],
            'em' => [],
            'u' => [],
            'ol' => [],
            'ul' => [],
            'li' => []
        ];
        
        $value = wp_kses($value, $allowed_tags);
        
        return $value;
    }
    
    /**
     * Sanitize HTML content
     */
    public function sanitize_html($value, array $allowed_tags = []): string {
        if (is_array($value)) {
            $value = implode('', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        
        if (empty($allowed_tags)) {
            // Default allowed tags
            $allowed_tags = [
                'p' => ['class' => [], 'id' => []],
                'br' => [],
                'strong' => ['class' => []],
                'em' => ['class' => []],
                'u' => ['class' => []],
                'ol' => ['class' => [], 'id' => []],
                'ul' => ['class' => [], 'id' => []],
                'li' => ['class' => []],
                'a' => ['href' => [], 'title' => [], 'target' => [], 'class' => []],
                'img' => ['src' => [], 'alt' => [], 'title' => [], 'class' => []],
                'div' => ['class' => [], 'id' => []],
                'span' => ['class' => [], 'id' => []],
                'h1' => ['class' => [], 'id' => []],
                'h2' => ['class' => [], 'id' => []],
                'h3' => ['class' => [], 'id' => []],
                'h4' => ['class' => [], 'id' => []],
                'h5' => ['class' => [], 'id' => []],
                'h6' => ['class' => [], 'id' => []]
            ];
        }
        
        return wp_kses($value, $allowed_tags);
    }
    
    /**
     * Sanitize email
     */
    public function sanitize_email($value): string {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = strtolower($value);
        
        return sanitize_email($value);
    }
    
    /**
     * Sanitize URL
     */
    public function sanitize_url($value): string {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        
        return esc_url_raw($value);
    }
    
    /**
     * Sanitize filename
     */
    public function sanitize_filename($value): string {
        if (is_array($value)) {
            $value = implode('_', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = sanitize_file_name($value);
        
        return $value;
    }
    
    /**
     * Sanitize SQL query
     */
    public function sanitize_sql($value): string {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        
        // Remove potentially dangerous SQL keywords
        $dangerous_keywords = [
            'DROP', 'DELETE', 'UPDATE', 'INSERT', 'CREATE', 'ALTER', 'TRUNCATE',
            'EXEC', 'EXECUTE', 'UNION', 'SELECT', 'SCRIPT', 'JAVASCRIPT'
        ];
        
        foreach ($dangerous_keywords as $keyword) {
            $value = str_ireplace($keyword, '', $value);
        }
        
        return $value;
    }
    
    /**
     * Sanitize JSON
     */
    public function sanitize_json($value): string {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        
        $value = strval($value);
        $value = trim($value);
        
        // Validate JSON
        if (json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
        
        return $value;
    }
    
    /**
     * Sanitize regex pattern
     */
    public function sanitize_regex($value): string {
        if (is_array($value)) {
            $value = implode('|', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        
        // Basic regex safety
        $value = preg_replace('/[<>"\']/', '', $value);
        
        return $value;
    }
    
    /**
     * Sanitize integer
     */
    public function sanitize_int($value): int {
        if (is_array($value)) {
            $value = reset($value);
        }
        
        return intval($value);
    }
    
    /**
     * Sanitize float
     */
    public function sanitize_float($value): float {
        if (is_array($value)) {
            $value = reset($value);
        }
        
        return floatval($value);
    }
    
    /**
     * Sanitize hex color
     */
    public function sanitize_hex_color($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }
        
        $value = strval($value);
        $value = trim($value);
        
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $value)) {
            return $value;
        }
        
        return '';
    }
    
    /**
     * Sanitize array
     */
    public function sanitize_array($value, callable $item_sanitizer = null): array {
        if (!is_array($value)) {
            return [];
        }
        
        if ($item_sanitizer === null) {
            $item_sanitizer = [$this, 'sanitize_text_field'];
        }
        
        $sanitized = [];
        foreach ($value as $item) {
            $sanitized[] = $item_sanitizer($item);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize associative array
     */
    public function sanitize_assoc_array($value, array $field_sanitizers = []): array {
        if (!is_array($value)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($value as $key => $item) {
            if (isset($field_sanitizers[$key])) {
                $sanitized[$key] = $field_sanitizers[$key]($item);
            } else {
                $sanitized[$key] = $this->sanitize_text_field($item);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize meta key
     */
    public function sanitize_meta_key($value): string {
        if (is_array($value)) {
            $value = implode('_', $value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        $value = preg_replace('/_{2,}/', '_', $value);
        $value = trim($value, '_');
        
        return $value;
    }
    
    /**
     * Sanitize post type
     */
    public function sanitize_post_type($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        
        return $value;
    }
    
    /**
     * Sanitize taxonomy
     */
    public function sanitize_taxonomy($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        
        return $value;
    }
    
    /**
     * Sanitize user role
     */
    public function sanitize_user_role($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }
        
        $value = strval($value);
        $value = trim($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        
        return $value;
    }
    
    /**
     * Sanitize with custom method
     */
    public function sanitize_with($value, string $method): mixed {
        if (isset($this->custom_sanitizers[$method])) {
            return $this->custom_sanitizers[$method]($value);
        }
        
        if (method_exists($this, $method)) {
            return $this->$method($value);
        }
        
        $this->log_warning('Unknown sanitization method', ['method' => $method]);
        return $this->sanitize_text_field($value);
    }
    
    /**
     * Sanitize multiple values
     */
    public function sanitize_multiple(array $data, array $field_sanitizers = []): array {
        $sanitized = [];
        
        foreach ($data as $field => $value) {
            if (isset($field_sanitizers[$field])) {
                $sanitized[$field] = $this->sanitize_with($value, $field_sanitizers[$field]);
            } else {
                $sanitized[$field] = $this->sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Add custom sanitizer
     */
    public function add_sanitizer(string $name, callable $sanitizer): void {
        $this->custom_sanitizers[$name] = $sanitizer;
        $this->log_info('Custom sanitizer added', ['name' => $name]);
    }
    
    /**
     * Remove custom sanitizer
     */
    public function remove_sanitizer(string $name): bool {
        if (isset($this->custom_sanitizers[$name])) {
            unset($this->custom_sanitizers[$name]);
            $this->log_info('Custom sanitizer removed', ['name' => $name]);
            return true;
        }
        return false;
    }
    
    /**
     * Get custom sanitizers
     */
    public function get_custom_sanitizers(): array {
        return array_keys($this->custom_sanitizers);
    }
    
    /**
     * Get sanitization methods for common fields
     */
    public function get_common_sanitizers(): array {
        return [
            'post_id' => 'int',
            'meta_key' => 'meta_key',
            'post_type' => 'post_type',
            'value' => 'textarea',
            'find' => 'text',
            'replace' => 'textarea',
            'mode' => 'text',
            'case_sensitive' => 'int',
            'regex' => 'int',
            'page' => 'int',
            'per_page' => 'int',
            'limit' => 'int',
            'confirm' => 'int',
            'batch_id' => 'text'
        ];
    }
    
    /**
     * Reset instance (useful for testing)
     */
    public static function reset(): void {
        self::$instance = null;
    }
}
