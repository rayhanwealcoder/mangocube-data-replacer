<?php
/**
 * Plugin Deactivator
 *
 * @package WCFDR\Core
 * @since 1.0.0
 */

namespace WCFDR\Core;

/**
 * Plugin Deactivator Class
 */
class Deactivator {
    
    /**
     * Deactivate plugin
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any scheduled events
        wp_clear_scheduled_hook('wcfdr_cleanup_backups');
    }
}
