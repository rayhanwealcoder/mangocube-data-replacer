<?php
/**
 * Dependency Injection Container for WCF Data Replacer
 *
 * @package WCFDR\Core
 * @since 1.0.0
 */

namespace WCFDR\Core;

/**
 * Memory-Optimized Container Class with Singleton Pattern
 */
final class Container {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Service instances (lazy loaded)
     */
    private $instances = [];
    
    /**
     * Private constructor
     */
    private function __construct() {
        // No heavy operations in constructor
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
     * Get a service instance (lazy loaded)
     */
    public function get(string $service) {
        // Return existing instance if available
        if (isset($this->instances[$service])) {
            return $this->instances[$service];
        }
        
        // Create and cache instance
        $instance = $this->create_service($service);
        $this->instances[$service] = $instance;
        
        return $instance;
    }
    
    /**
     * Create service instance (simplified)
     */
    private function create_service(string $service) {
        switch ($service) {
            case 'database':
                return \WCFDR\Database\Database_Manager::getInstance();
                
            case 'logger':
                return \WCFDR\Logger\Logger::getInstance();
                
            case 'cache':
                return \WCFDR\Cache\Cache_Manager::getInstance();
                
            case 'validator':
                return \WCFDR\Validator\Validator::getInstance();
                
            case 'sanitizer':
                return \WCFDR\Sanitizer\Sanitizer::getInstance();
                
            case 'search':
                return \WCFDR\Search\Search_Engine::getInstance();
                
            case 'replace':
                return \WCFDR\Replace\Replace_Engine::getInstance();
                
            case 'backup':
                return \WCFDR\Backup\Backup_Manager::getInstance();
                
            case 'admin':
                return new \WCFDR\Admin\Admin_Controller();
                
            case 'rest':
                return new \WCFDR\REST\REST_Controller();
                
            case 'string_helper':
                return new \WCFDR\Utils\String_Helper();
                
            case 'url_helper':
                return new \WCFDR\Utils\URL_Helper();
                
            default:
                throw new \Exception("Unknown service: {$service}");
        }
    }
    
    /**
     * Check if service exists
     */
    public function has(string $service): bool {
        $available_services = [
            'database', 'logger', 'cache', 'validator', 'sanitizer',
            'search', 'replace', 'backup', 'admin', 'rest',
            'string_helper', 'url_helper'
        ];
        
        return in_array($service, $available_services);
    }
    
    /**
     * Clear all instances (useful for testing)
     */
    public function clear(): void {
        $this->instances = [];
    }
    
    /**
     * Reset instance (useful for testing)
     */
    public static function reset(): void {
        self::$instance = null;
    }
}
