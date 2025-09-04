<?php
/**
 * Plugin Name: Mangocube Data Replacer
 * Plugin URI: https://github.com/rayhanwealcoder/mangocube-data-replacer
 * Description: Professional WordPress admin tool for searching, previewing, and replacing post meta values with advanced features, backups, and live testing.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Rayhan Uddin
 * Author URI: https://github.com/rayhanwealcoder
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mangocube-data-replacer
 * Domain Path: /languages
 * Network: true
 * 
 * @package Mangocube_Data_Replacer
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Define plugin constants
define('MCDR_VERSION', '1.0.0');
define('MCDR_PLUGIN_FILE', __FILE__);
define('MCDR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCDR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MCDR_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MCDR_MIN_WP_VERSION', '6.2');
define('MCDR_MIN_PHP_VERSION', '7.4');

// Autoloader
if (file_exists(MCDR_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once MCDR_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback to manual autoloader if Composer is not available
    require_once MCDR_PLUGIN_DIR . 'autoload.php';
}

/**
 * Main plugin class
 */
final class Mangocube_Data_Replacer {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin container
     */
    private $container;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init'], 0);
        add_action('init', [$this, 'load_textdomain']);
    }
    
    /**
     * Initialize the plugin
     */
    public function init(): void {
        // Initialize container only
        $this->init_container();
        
        // Defer heavy service initialization
        add_action('admin_init', [$this, 'init_admin_services']);
        add_action('rest_api_init', [$this, 'init_rest_services']);
        
        // Add activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add AJAX handlers
        $this->add_ajax_handlers();
    }
    
    /**
     * Initialize dependency container
     */
    private function init_container() {
        $this->container = WCFDR\Core\Container::getInstance();
    }
    
    /**
     * Initialize admin services (deferred)
     */
    public function init_admin_services(): void {
        if (!is_admin()) {
            return;
        }
        
        try {
            // Initialize core services only when needed
            $this->container->get('search')->init();
            $this->container->get('replace')->init();
            $this->container->get('backup')->init();
            
            // Initialize admin
            $this->container->get('admin')->init();
            
        } catch (\Exception $e) {
            error_log('WCFDR: Failed to initialize admin services: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize REST services (deferred)
     */
    public function init_rest_services(): void {
        try {
            $this->container->get('rest')->init();
        } catch (\Exception $e) {
            error_log('WCFDR: Failed to initialize REST services: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize AJAX services (deferred)
     */
    public function init_ajax_services(): void {
        try {
            // Initialize database manager first to ensure tables exist
            $this->container->get('database')->init();
            
            // Initialize only the services needed for AJAX
            $this->container->get('search')->init();
            $this->container->get('replace')->init();
            $this->container->get('backup')->init();
        } catch (\Exception $e) {
            error_log('WCFDR: Failed to initialize AJAX services: ' . $e->getMessage());
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        add_menu_page(
            'WCF Data Replacer',
            'Data Replacer',
            'edit_posts',
            'wcf-data-replacer',
            [$this, 'admin_page'],
            'dashicons-search',
            30
        );
    }
    
    /**
     * Render admin page
     */
    public function admin_page(): void {
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Include admin page template
        include_once MCDR_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wcf-data-replacer'));
        }
        
        include MCDR_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook): void {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_wcf-data-replacer') {
            return;
        }
        
        // Enqueue React app
        wp_enqueue_script(
            'wcfdr-admin-app',
            MCDR_PLUGIN_URL . 'assets/js/admin.js',
            ['wp-element', 'wp-components', 'wp-api-fetch'],
            MCDR_VERSION,
            true
        );
        
        // Enqueue styles
        wp_enqueue_style(
            'wcfdr-admin-styles',
            MCDR_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MCDR_VERSION
        );
       
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('wcfdr-admin-app', 'wcfdr_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcfdr_nonce'),
            'rest_url' => rest_url('wcfdr/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest')
        ]);
    }
    
    /**
     * Get localized strings
     */
    private function get_localized_strings() {
        return [
            'search' => __('Search', 'wcf-data-replacer'),
            'replace' => __('Replace', 'wcf-data-replacer'),
            'preview' => __('Preview', 'wcf-data-replacer'),
            'confirm' => __('Confirm', 'wcf-data-replacer'),
            'cancel' => __('Cancel', 'wcf-data-replacer'),
            'loading' => __('Loading...', 'wcf-data-replacer'),
            'error' => __('Error', 'wcf-data-replacer'),
            'success' => __('Success', 'wcf-data-replacer'),
            'warning' => __('Warning', 'wcf-data-replacer'),
            'info' => __('Information', 'wcf-data-replacer'),
            'noResults' => __('No results found', 'wcf-data-replacer'),
            'searching' => __('Searching...', 'wcf-data-replacer'),
            'processing' => __('Processing...', 'wcf-data-replacer'),
            'completed' => __('Completed', 'wcf-data-replacer'),
            'failed' => __('Failed', 'wcf-data-replacer'),
            'backupCreated' => __('Backup created successfully', 'wcf-data-replacer'),
            'restoreCompleted' => __('Restore completed successfully', 'wcf-data-replacer'),
            'invalidRegex' => __('Invalid regex pattern', 'wcf-data-replacer'),
            'regexTimeout' => __('Regex operation timed out', 'wcf-data-replacer'),
            'permissionDenied' => __('Permission denied', 'wcf-data-replacer'),
            'networkError' => __('Network error occurred', 'wcf-data-replacer'),
        ];
    }
    
    /**
     * Get plugin settings
     */
    private function get_plugin_settings() {
        return [
            'maxPerPage' => 200,
            'maxBulkRows' => 5000,
            'regexTimeout' => 5000,
            'backupRetention' => 10,
            'enableLiveTester' => true,
            'enableAutoSuggest' => true,
            'enableProgressBar' => true,
            'enableKeyboardShortcuts' => true,
        ];
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_search() {
        try {
            // Initialize AJAX services if needed
            $this->init_ajax_services();
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
                wp_send_json_error('Invalid nonce');
            }
            
            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Permission denied');
            }
            
            // Get search engine from container
            try {
                $search_engine = $this->container->get('search');
            } catch (\Exception $e) {
                error_log('WCFDR: Failed to get search engine: ' . $e->getMessage());
                wp_send_json_error('Failed to initialize search engine: ' . $e->getMessage());
            }
            
            // Perform search
            try {
                $results = $search_engine->search($_POST);
                wp_send_json_success($results);
            } catch (\Exception $e) {
                error_log('WCFDR: Search failed: ' . $e->getMessage());
                wp_send_json_error('Search failed: ' . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            error_log('WCFDR: AJAX search error: ' . $e->getMessage());
            wp_send_json_error('Internal server error: ' . $e->getMessage());
        }
    }
    
    public function ajax_preview() {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $replace_engine = $this->container->get('replace');
        $preview = $replace_engine->preview($_POST);
        
        wp_send_json_success($preview);
    }
    
    public function ajax_replace() {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $replace_engine = $this->container->get('replace');
        $result = $replace_engine->execute($_POST);
        
        wp_send_json_success($result);
    }
    
    /**
     * Update row AJAX handler
     */
    public function ajax_update_row(): void {
        // Initialize AJAX services if needed
        $this->init_ajax_services();
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $replace_engine = $this->container->get('replace');
            $replace_engine->handle_ajax_update_row();
        } catch (\Exception $e) {
            wp_send_json_error('Update failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_get_meta_keys() {
        try {
            // Initialize AJAX services if needed
            $this->init_ajax_services();
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
                wp_send_json_error('Invalid nonce');
            }
            
            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Permission denied');
            }
            
            // Post type is now optional - we fetch all meta keys
            $post_type = sanitize_text_field($_POST['post_type'] ?? '');
            
            // Get search engine from container
            try {
                $search_engine = $this->container->get('search');
            } catch (\Exception $e) {
                error_log('WCFDR: Failed to get search engine: ' . $e->getMessage());
                wp_send_json_error('Failed to initialize search engine: ' . $e->getMessage());
            }
            
            // Get meta keys (post_type parameter is now optional)
            try {
                $meta_keys = $search_engine->get_meta_keys($post_type);
                
                // Log for debugging
                error_log('WCFDR: AJAX get_meta_keys - Raw result: ' . print_r($meta_keys, true));
                
                // Ensure we return an array of strings for the frontend
                if (is_array($meta_keys)) {
                    // Extract just the meta_key values if the result has frequency data
                    $keys = array_map(function($item) {
                        return is_array($item) ? $item['meta_key'] : $item;
                    }, $meta_keys);
                    
                    error_log('WCFDR: AJAX get_meta_keys - Processed keys: ' . print_r($keys, true));
                    wp_send_json_success($keys);
                } else {
                    wp_send_json_error('Invalid meta keys format returned');
                }
                
            } catch (\Exception $e) {
                error_log('WCFDR: Failed to get meta keys: ' . $e->getMessage());
                wp_send_json_error('Failed to get meta keys: ' . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            error_log('WCFDR: AJAX get_meta_keys error: ' . $e->getMessage());
            wp_send_json_error('Internal server error: ' . $e->getMessage());
        }
    }
    
    public function ajax_get_post_types() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
                wp_send_json_error('Invalid nonce');
            }
            
            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Permission denied');
            }
            
            try {
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
                
                wp_send_json_success($formatted);
                
            } catch (\Exception $e) {
                error_log('WCFDR: Failed to get post types: ' . $e->getMessage());
                wp_send_json_error('Failed to get post types: ' . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            error_log('WCFDR: AJAX get_post_types error: ' . $e->getMessage());
            wp_send_json_error('Internal server error: ' . $e->getMessage());
        }
    }
    
    public function ajax_backup() {
        check_ajax_referer('wcfdr_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied', 'wcf-data-replacer'));
        }
        
        $backup_manager = $this->container->get('backup');
        $result = $backup_manager->create_backup($_POST);
        
        wp_send_json_success($result);
    }
    
    /**
     * Backups AJAX handler
     */
    public function ajax_backups(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $backup_manager = $this->container->get('backup');
            $results = $backup_manager->get_backups($_POST['post_id'], $_POST['meta_key']);
            wp_send_json_success($results);
        } catch (\Exception $e) {
            wp_send_json_error('Backup retrieval failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Restore AJAX handler
     */
    public function ajax_restore(): void {
        // Initialize AJAX services if needed
        $this->init_ajax_services();
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $post_id = intval($_POST['post_id'] ?? 0);
            $meta_key = sanitize_text_field($_POST['meta_key'] ?? '');
            
            if (!$post_id || empty($meta_key)) {
                wp_send_json_error('Invalid parameters');
            }
            
            $backup_manager = $this->container->get('backup');
            $result = $backup_manager->restore_latest($post_id, $meta_key);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error('Restore failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Restore all AJAX handler
     */
    public function ajax_restore_all(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $backup_manager = $this->container->get('backup');
            $results = $backup_manager->restore_batch($_POST['batch_id']);
            wp_send_json_success($results);
        } catch (\Exception $e) {
            wp_send_json_error('Restore all failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test connection AJAX handler
     */
    public function ajax_test_connection(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcfdr_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Test basic functionality
            $container = $this->container;
            $services = ['database', 'logger', 'cache', 'validator', 'sanitizer'];
            $test_results = [];
            
            foreach ($services as $service) {
                try {
                    $instance = $container->get($service);
                    $test_results[$service] = '✅ Available';
                } catch (\Exception $e) {
                    $test_results[$service] = '❌ Error: ' . $e->getMessage();
                }
            }
            
            wp_send_json_success([
                'message' => 'Connection test completed',
                'services' => $test_results,
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error('Test failed: ' . $e->getMessage());
        }
    }

    public function ajax_save_settings() {
        check_ajax_referer('wcfdr_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        try {
            $settings = json_decode(stripslashes($_POST['settings']), true);
            if (!is_array($settings)) {
                throw new \Exception('Invalid settings data');
            }

            // Save settings to WordPress options
            $sanitized_settings = [
                'maxResultsPerPage' => intval($settings['maxResultsPerPage'] ?? 1500),
                'maxBulkOperations' => intval($settings['maxBulkOperations'] ?? 5000),
                'backupRetention' => intval($settings['backupRetention'] ?? 10),
                'autoCleanup' => intval($settings['autoCleanup'] ?? 30)
            ];

            update_option('wcfdr_settings', $sanitized_settings);

            wp_send_json_success([
                'message' => 'Settings saved successfully',
                'settings' => $sanitized_settings
            ]);
        } catch (\Exception $e) {
            error_log('WCFDR: Failed to save settings: ' . $e->getMessage());
            wp_send_json_error('Failed to save settings: ' . $e->getMessage());
        }
    }

    public function ajax_get_settings() {
        check_ajax_referer('wcfdr_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        try {
            // Get settings from WordPress options
            $settings = get_option('wcfdr_settings', [
                'maxResultsPerPage' => 1500,
                'maxBulkOperations' => 5000,
                'backupRetention' => 10,
                'autoCleanup' => 30
            ]);

            wp_send_json_success($settings);
        } catch (\Exception $e) {
            error_log('WCFDR: Failed to get settings: ' . $e->getMessage());
            wp_send_json_error('Failed to get settings: ' . $e->getMessage());
        }
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wcf-data-replacer',
            false,
            dirname(MCDR_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Activate plugin
     */
    public function activate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        $activator = new WCFDR\Core\Activator();
        $activator->activate();
    }
    
    /**
     * Deactivate plugin
     */
    public function deactivate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        $deactivator = new WCFDR\Core\Deactivator();
        $deactivator->deactivate();
    }
    
    /**
     * Uninstall plugin
     */
    public static function uninstall() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        $uninstaller = new WCFDR\Core\Uninstaller();
        $uninstaller->uninstall();
    }
    
    /**
     * Add AJAX handlers
     */
    private function add_ajax_handlers(): void {
        // Register AJAX handlers
        add_action('wp_ajax_wcfdr_search', [$this, 'ajax_search']);
        add_action('wp_ajax_wcfdr_preview', [$this, 'ajax_preview']);
        add_action('wp_ajax_wcfdr_replace', [$this, 'ajax_replace']);
        add_action('wp_ajax_wcfdr_update_row', [$this, 'ajax_update_row']);
        add_action('wp_ajax_wcfdr_get_meta_keys', [$this, 'ajax_get_meta_keys']);
        add_action('wp_ajax_wcfdr_get_post_types', [$this, 'ajax_get_post_types']);
        add_action('wp_ajax_wcfdr_backup', [$this, 'ajax_backup']);
        add_action('wp_ajax_wcfdr_backups', [$this, 'ajax_backups']);
        add_action('wp_ajax_wcfdr_restore', [$this, 'ajax_restore']);
        add_action('wp_ajax_wcfdr_restore_all', [$this, 'ajax_restore_all']);
        add_action('wp_ajax_wcfdr_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_wcfdr_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_wcfdr_get_settings', [$this, 'ajax_get_settings']);
    }
    
    /**
     * Get container
     */
    public function get_container() {
        return $this->container;
    }

    /**
     * Remove admin notices on our plugin page
     */
    public function remove_admin_notices() {
        global $pagenow;
        
        // Check if we're on our plugin page
        if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'wcf-data-replacer') {
            // Remove all admin notices by filtering them out
            add_filter('admin_notices', '__return_empty_array', 999);
            
            // Also remove update nag and other WordPress notices
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            
            // Remove update nag
            add_filter('update_nag', '__return_empty_string');
            
            // Remove admin bar update notifications
            add_filter('admin_bar_menu', function($wp_admin_bar) {
                $wp_admin_bar->remove_node('updates');
            }, 999);
        }
    }
}

// Register uninstall hook (must be outside the class)
register_uninstall_hook(__FILE__, ['Mangocube_Data_Replacer', 'uninstall']);

// Initialize plugin
function mcdr() {
    return Mangocube_Data_Replacer::get_instance();
}

// Start the plugin
mcdr();
