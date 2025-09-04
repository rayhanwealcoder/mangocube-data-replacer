<?php
/**
 * Simple autoloader for WCFDR classes
 * This replaces Composer autoloader when OpenSSL is not available
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

spl_autoload_register(function ($class) {
    // Only handle our namespace
    if (strpos($class, 'WCFDR\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $class = str_replace('WCFDR\\', '', $class);
    $class = str_replace('\\', '/', $class);
    
    // Build file path
    $file = WCFDR_PLUGIN_DIR . 'includes/' . $class . '.php';
    
    // Load the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
});
