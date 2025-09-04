<?php
/**
 * Plugin Activator
 *
 * @package WCFDR\Core
 * @since 1.0.0
 */

namespace WCFDR\Core;

/**
 * Plugin Activator Class
 */
class Activator {
    
    /**
     * Activate plugin
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Backup table
        $table_backups = $wpdb->prefix . 'wcfdr_backups';
        $sql_backups = "CREATE TABLE $table_backups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            old_value longtext NOT NULL,
            new_value longtext NOT NULL,
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            batch_id varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY meta_key (meta_key),
            KEY batch_id (batch_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_backups);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = [
            'max_per_page' => 1500,
            'max_bulk_rows' => 5000,
            'regex_timeout' => 5000,
            'backup_retention' => 10,
            'enable_live_tester' => true,
            'enable_auto_suggest' => true,
            'enable_progress_bar' => true,
            'enable_keyboard_shortcuts' => true,
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option('wcfdr_' . $key) === false) {
                add_option('wcfdr_' . $key, $value);
            }
        }
    }
}
