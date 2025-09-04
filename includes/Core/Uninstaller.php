<?php
/**
 * Plugin Uninstaller
 *
 * @package WCFDR\Core
 * @since 1.0.0
 */

namespace WCFDR\Core;

/**
 * Plugin Uninstaller Class
 */
class Uninstaller {
    
    /**
     * Uninstall plugin
     */
    public function uninstall() {
        // Only run if uninstalling the plugin
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Check if user has permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Remove database tables
        $this->remove_tables();
        
        // Remove options
        $this->remove_options();
    }
    
    /**
     * Remove database tables
     */
    private function remove_tables() {
        global $wpdb;
        
        $table_backups = $wpdb->prefix . 'wcfdr_backups';
        $wpdb->query("DROP TABLE IF EXISTS $table_backups");
    }
    
    /**
     * Remove options
     */
    private function remove_options() {
        $options = [
            'wcfdr_max_per_page',
            'wcfdr_max_bulk_rows',
            'wcfdr_regex_timeout',
            'wcfdr_backup_retention',
            'wcfdr_enable_live_tester',
            'wcfdr_enable_auto_suggest',
            'wcfdr_enable_progress_bar',
            'wcfdr_enable_keyboard_shortcuts',
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
}
