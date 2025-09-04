<?php
/**
 * Validator for WCF Data Replacer
 *
 * @package WCFDR\Validator
 * @since 1.0.0
 */

namespace WCFDR\Validator;

use WCFDR\Logger\Logger;

/**
 * Optimized Validator Class with Singleton Pattern
 */
final class Validator {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Validation rules cache
     */
    private $rules_cache = [];
    
    /**
     * Custom validation methods
     */
    private $custom_validators = [];
    
    /**
     * Private constructor
     */
    private function __construct() {
        // No dependencies loaded in constructor to avoid circular dependency
        $this->logger = null;
        $this->register_default_validators();
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
     * Initialize validator
     */
    public function init(): void {
        $this->log_info('Validator initialized');
    }
    
    /**
     * Register default validators
     */
    private function register_default_validators(): void {
        // URL validation
        $this->custom_validators['url'] = function($value) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        };
        
        // Email validation
        $this->custom_validators['email'] = function($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        };
        
        // IP address validation
        $this->custom_validators['ip'] = function($value) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        };
        
        // Regex pattern validation
        $this->custom_validators['regex'] = function($value) {
            return @preg_match($value, '') !== false;
        };
        
        // WordPress post ID validation
        $this->custom_validators['post_id'] = function($value) {
            return get_post($value) !== null;
        };
        
        // WordPress user ID validation
        $this->custom_validators['user_id'] = function($value) {
            return get_user_by('ID', $value) !== false;
        };
        
        // Meta key validation
        $this->custom_validators['meta_key'] = function($value) {
            return is_string($value) && strlen($value) <= 255 && preg_match('/^[a-zA-Z0-9_-]+$/', $value);
        };
        
        // Post type validation
        $this->custom_validators['post_type'] = function($value) {
            return post_type_exists($value);
        };
        
        // Date format validation
        $this->custom_validators['date'] = function($value) {
            return strtotime($value) !== false;
        };
        
        // JSON validation
        $this->custom_validators['json'] = function($value) {
            if (!is_string($value)) {
                return false;
            }
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE;
        };
    }
    
    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules): array {
        $errors = [];
        $validated = [];
        
        foreach ($rules as $field => $field_rules) {
            $field_rules = $this->parse_rules($field_rules);
            $value = $data[$field] ?? null;
            
            $field_errors = $this->validate_field($field, $value, $field_rules);
            
            if (!empty($field_errors)) {
                $errors[$field] = $field_errors;
            } else {
                $validated[$field] = $this->transform_value($value, $field_rules);
            }
        }
        
        if (!empty($errors)) {
            $this->log_warning('Validation failed', [
                'field' => array_keys($errors),
                'errors' => $errors
            ]);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'validated' => $validated
        ];
    }
    
    /**
     * Parse validation rules string
     */
    private function parse_rules(string $rules_string): array {
        $rules = [];
        $rule_parts = explode('|', $rules_string);
        
        foreach ($rule_parts as $rule) {
            $rule = trim($rule);
            
            if (strpos($rule, ':') !== false) {
                [$rule_name, $rule_value] = explode(':', $rule, 2);
                $rules[$rule_name] = $rule_value;
            } else {
                $rules[$rule] = true;
            }
        }
        
        return $rules;
    }
    
    /**
     * Validate single field
     */
    private function validate_field(string $field, $value, array $rules): array {
        $errors = [];
        
        // Required check
        if (isset($rules['required']) && $rules['required'] && ($value === null || $value === '')) {
            $errors[] = "The {$field} field is required.";
            return $errors;
        }
        
        // Skip other validations if field is empty and not required
        if (($value === null || $value === '') && !isset($rules['required'])) {
            return $errors;
        }
        
        // Type validations
        if (isset($rules['string'])) {
            if (!is_string($value)) {
                $errors[] = "The {$field} field must be a string.";
            }
        }
        
        if (isset($rules['integer'])) {
            if (!is_numeric($value) || (string)(int)$value !== (string)$value) {
                $errors[] = "The {$field} field must be an integer.";
            }
        }
        
        if (isset($rules['numeric'])) {
            if (!is_numeric($value)) {
                $errors[] = "The {$field} field must be numeric.";
            }
        }
        
        if (isset($rules['boolean'])) {
            if (!in_array($value, [true, false, 1, 0, '1', '0'], true)) {
                $errors[] = "The {$field} field must be a boolean.";
            }
        }
        
        if (isset($rules['array'])) {
            if (!is_array($value)) {
                $errors[] = "The {$field} field must be an array.";
            }
        }
        
        // Size validations
        if (isset($rules['min'])) {
            $min = intval($rules['min']);
            if (is_string($value) && strlen($value) < $min) {
                $errors[] = "The {$field} field must be at least {$min} characters.";
            } elseif (is_numeric($value) && $value < $min) {
                $errors[] = "The {$field} field must be at least {$min}.";
            } elseif (is_array($value) && count($value) < $min) {
                $errors[] = "The {$field} field must have at least {$min} items.";
            }
        }
        
        if (isset($rules['max'])) {
            $max = intval($rules['max']);
            if (is_string($value) && strlen($value) > $max) {
                $errors[] = "The {$field} field must not exceed {$max} characters.";
            } elseif (is_numeric($value) && $value > $max) {
                $errors[] = "The {$field} field must not exceed {$max}.";
            } elseif (is_array($value) && count($value) > $max) {
                $errors[] = "The {$field} field must not have more than {$max} items.";
            }
        }
        
        // Length validations
        if (isset($rules['length'])) {
            $length = intval($rules['length']);
            if (is_string($value) && strlen($value) !== $length) {
                $errors[] = "The {$field} field must be exactly {$length} characters.";
            }
        }
        
        // Range validations
        if (isset($rules['between'])) {
            [$min, $max] = explode(',', $rules['between']);
            $min = intval($min);
            $max = intval($max);
            
            if (is_numeric($value) && ($value < $min || $value > $max)) {
                $errors[] = "The {$field} field must be between {$min} and {$max}.";
            }
        }
        
        // Pattern validation
        if (isset($rules['pattern'])) {
            if (!preg_match($rules['pattern'], $value)) {
                $errors[] = "The {$field} field format is invalid.";
            }
        }
        
        // In validation
        if (isset($rules['in'])) {
            $allowed_values = explode(',', $rules['in']);
            if (!in_array($value, $allowed_values)) {
                $errors[] = "The {$field} field must be one of: " . implode(', ', $allowed_values) . ".";
            }
        }
        
        // Not in validation
        if (isset($rules['not_in'])) {
            $forbidden_values = explode(',', $rules['not_in']);
            if (in_array($value, $forbidden_values)) {
                $errors[] = "The {$field} field must not be one of: " . implode(', ', $forbidden_values) . ".";
            }
        }
        
        // Custom validators
        foreach ($rules as $rule_name => $rule_value) {
            if (isset($this->custom_validators[$rule_name])) {
                $validator = $this->custom_validators[$rule_name];
                if (!$validator($value)) {
                    $errors[] = "The {$field} field failed {$rule_name} validation.";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Transform value based on rules
     */
    private function transform_value($value, array $rules): mixed {
        // Type casting
        if (isset($rules['integer'])) {
            return intval($value);
        }
        
        if (isset($rules['numeric'])) {
            return is_float($value) ? floatval($value) : intval($value);
        }
        
        if (isset($rules['boolean'])) {
            return in_array($value, [true, 1, '1'], true);
        }
        
        if (isset($rules['string'])) {
            return strval($value);
        }
        
        if (isset($rules['array'])) {
            return is_array($value) ? $value : [$value];
        }
        
        // Trim strings
        if (is_string($value) && isset($rules['trim'])) {
            $value = trim($value);
        }
        
        // Lowercase
        if (is_string($value) && isset($rules['lowercase'])) {
            $value = strtolower($value);
        }
        
        // Uppercase
        if (is_string($value) && isset($rules['uppercase'])) {
            $value = strtoupper($value);
        }
        
        // Capitalize
        if (is_string($value) && isset($rules['capitalize'])) {
            $value = ucfirst(strtolower($value));
        }
        
        // Title case
        if (is_string($value) && isset($rules['title'])) {
            $value = ucwords(strtolower($value));
        }
        
        return $value;
    }
    
    /**
     * Add custom validator
     */
    public function add_validator(string $name, callable $validator): void {
        $this->custom_validators[$name] = $validator;
        $this->log_info('Custom validator added', ['name' => $name]);
    }
    
    /**
     * Remove custom validator
     */
    public function remove_validator(string $name): bool {
        if (isset($this->custom_validators[$name])) {
            unset($this->custom_validators[$name]);
            $this->log_info('Custom validator removed', ['name' => $name]);
            return true;
        }
        return false;
    }
    
    /**
     * Get custom validators
     */
    public function get_custom_validators(): array {
        return array_keys($this->custom_validators);
    }
    
    /**
     * Validate single value
     */
    public function validate_value($value, string $rules): array {
        $parsed_rules = $this->parse_rules($rules);
        $errors = $this->validate_field('value', $value, $parsed_rules);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'value' => empty($errors) ? $this->transform_value($value, $parsed_rules) : $value
        ];
    }
    
    /**
     * Validate URL
     */
    public function validate_url(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate email
     */
    public function validate_email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate IP address
     */
    public function validate_ip(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Validate regex pattern
     */
    public function validate_regex(string $pattern): bool {
        return @preg_match($pattern, '') !== false;
    }
    
    /**
     * Validate WordPress post ID
     */
    public function validate_post_id($post_id): bool {
        return get_post($post_id) !== null;
    }
    
    /**
     * Validate WordPress user ID
     */
    public function validate_user_id($user_id): bool {
        return get_user_by('ID', $user_id) !== false;
    }
    
    /**
     * Validate meta key
     */
    public function validate_meta_key(string $meta_key): bool {
        return strlen($meta_key) <= 255 && preg_match('/^[a-zA-Z0-9_-]+$/', $meta_key);
    }
    
    /**
     * Validate post type
     */
    public function validate_post_type(string $post_type): bool {
        return post_type_exists($post_type);
    }
    
    /**
     * Validate date format
     */
    public function validate_date(string $date): bool {
        return strtotime($date) !== false;
    }
    
    /**
     * Validate JSON
     */
    public function validate_json($value): bool {
        if (!is_string($value)) {
            return false;
        }
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Get validation rules for common fields
     */
    public function get_common_rules(): array {
        return [
            'post_id' => 'required|integer|post_id',
            'meta_key' => 'required|string|max:255|meta_key',
            'post_type' => 'required|string|max:50|post_type',
            'value' => 'string|max:10000',
            'find' => 'required|string|max:1000',
            'replace' => 'required|string|max:1000',
            'mode' => 'required|string|in:plain,plain_cs,regex,url,url_segment,prefix_swap,full_text',
            'case_sensitive' => 'boolean',
            'regex' => 'boolean',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:1500',
            'limit' => 'integer|min:1|max:5000',
            'confirm' => 'boolean',
            'batch_id' => 'string|max:100'
        ];
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
}
