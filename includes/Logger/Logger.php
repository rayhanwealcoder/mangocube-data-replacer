<?php
/**
 * Logger for WCF Data Replacer
 *
 * @package WCFDR\Logger
 * @since 1.0.0
 */

namespace WCFDR\Logger;

use WCFDR\Database\Database_Manager;

/**
 * Optimized Logger Class with Singleton Pattern
 */
final class Logger {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Database manager
     */
    private $database;
    
    /**
     * Log levels
     */
    private const LOG_LEVELS = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    ];
    
    /**
     * Current log level
     */
    private $log_level;
    
    /**
     * Enable database logging
     */
    private $enable_db_logging;
    
    /**
     * Enable file logging
     */
    private $enable_file_logging;
    
    /**
     * Log file path
     */
    private $log_file;
    
    /**
     * Private constructor
     */
    private function __construct() {
        // No dependencies loaded in constructor to avoid circular dependency
        $this->database = null;
        $this->log_level = defined('WP_DEBUG') && WP_DEBUG ? 'debug' : 'info';
        $this->enable_db_logging = true;
        $this->enable_file_logging = defined('WP_DEBUG') && WP_DEBUG;
        
        // Handle WP_CONTENT_DIR constant safely
        if (defined('WP_CONTENT_DIR')) {
            $this->log_file = WP_CONTENT_DIR . '/logs/wcfdr-plugin.log';
        } else {
            $this->log_file = __DIR__ . '/../../logs/wcfdr-plugin.log';
        }
        
        // Defer log directory creation
        add_action('init', [$this, 'ensure_log_directory'], 5);
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
     * Initialize logger
     */
    public function init(): void {
        // Add shutdown hook to flush any pending logs
        add_action('shutdown', [$this, 'flush_logs']);
        
        // Log plugin initialization
        $this->info('Logger initialized', [
            'log_level' => $this->log_level,
            'db_logging' => $this->enable_db_logging,
            'file_logging' => $this->enable_file_logging
        ]);
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory(): void {
        $log_dir = dirname($this->log_file);
        
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Create .htaccess to protect logs
        $htaccess_file = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n");
        }
    }
    
    /**
     * Set log level
     */
    public function setLogLevel(string $level): void {
        if (isset(self::LOG_LEVELS[$level])) {
            $this->log_level = $level;
        }
    }
    
    /**
     * Check if level should be logged
     */
    private function shouldLog(string $level): bool {
        return self::LOG_LEVELS[$level] <= self::LOG_LEVELS[$this->log_level];
    }
    
    /**
     * Log emergency message
     */
    public function emergency(string $message, array $context = []): void {
        $this->log('emergency', $message, $context);
    }
    
    /**
     * Log alert message
     */
    public function alert(string $message, array $context = []): void {
        $this->log('alert', $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log notice message
     */
    public function notice(string $message, array $context = []): void {
        $this->log('notice', $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Main logging method
     */
    private function log(string $level, string $message, array $context = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $log_entry = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        $logging_successful = false;
        
        // Database logging
        if ($this->enable_db_logging) {
            try {
                $this->log_to_database($log_entry);
                $logging_successful = true;
            } catch (\Exception $e) {
                // Database logging failed, continue with file logging
            }
        }
        
        // File logging
        if ($this->enable_file_logging) {
            try {
                $this->log_to_file($log_entry);
                $logging_successful = true;
            } catch (\Exception $e) {
                // File logging failed
            }
        }
        
        // If both logging methods failed, disable logging to prevent repeated errors
        if (!$logging_successful && ($this->enable_db_logging || $this->enable_file_logging)) {
            $this->disable_all_logging();
        }
        
        // WordPress error log (for critical errors)
        if (in_array($level, ['emergency', 'alert', 'critical', 'error'])) {
            error_log("WCFDR [{$level}]: {$message}");
        }
    }
    
    /**
     * Log to database
     */
    private function log_to_database(array $log_entry): void {
        try {
            global $wpdb;
            
            $logs_table = $wpdb->prefix . 'wcfdr_operation_logs';
            
            // Check if table exists before trying to insert
            $table_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $logs_table
                )
            );
            
            if (!$table_exists) {
                // Table doesn't exist, fall back to file logging only
                if ($this->enable_file_logging) {
                    $this->log_to_file($log_entry);
                }
                return;
            }
            
            $result = $wpdb->insert(
                $logs_table,
                [
                    'operation_type' => $log_entry['level'],
                    'user_id' => $log_entry['user_id'],
                    'details' => json_encode($log_entry),
                    'created_at' => $log_entry['timestamp'],
                    'ip_address' => $log_entry['ip_address'],
                    'user_agent' => $log_entry['user_agent']
                ],
                [
                    '%s', // operation_type
                    '%d', // user_id
                    '%s', // details
                    '%s', // created_at
                    '%s', // ip_address
                    '%s'  // user_agent
                ]
            );
            
            if ($result === false) {
                error_log("WCFDR Logger: Failed to insert log entry to database: " . $wpdb->last_error);
            }
            
        } catch (\Exception $e) {
            error_log("WCFDR Logger: Database logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log to file
     */
    private function log_to_file(array $log_entry): void {
        // Check if file logging is enabled
        if (!$this->enable_file_logging) {
            return;
        }
        
        try {
            // Ensure log directory exists before writing
            $this->ensure_log_directory();
            
            // Check if we can write to the file
            if (!is_writable(dirname($this->log_file)) && !is_writable($this->log_file)) {
                // Disable file logging if we can't write
                $this->enable_file_logging = false;
                error_log("WCFDR Logger: Cannot write to log file: " . $this->log_file);
                return;
            }
            
            $formatted_entry = sprintf(
                "[%s] %s: %s %s\n",
                $log_entry['timestamp'],
                strtoupper($log_entry['level']),
                $log_entry['message'],
                !empty($log_entry['context']) ? json_encode($log_entry['context']) : ''
            );
            
            $result = file_put_contents($this->log_file, $formatted_entry, FILE_APPEND | LOCK_EX);
            
            if ($result === false) {
                // Disable file logging if write fails
                $this->enable_file_logging = false;
                error_log("WCFDR Logger: Failed to write to log file: " . $this->log_file);
            }
            
        } catch (\Exception $e) {
            // Disable file logging on error
            $this->enable_file_logging = false;
            error_log("WCFDR Logger: File logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get logs from database
     */
    public function get_logs(array $filters = []): array {
        try {
            global $wpdb;
            
            $logs_table = $wpdb->prefix . 'wcfdr_operation_logs';
            
            $where_conditions = [];
            $params = [];
            
            // Level filter
            if (!empty($filters['level'])) {
                $where_conditions[] = "operation_type = %s";
                $params[] = $filters['level'];
            }
            
            // User filter
            if (!empty($filters['user_id'])) {
                $where_conditions[] = "user_id = %d";
                $params[] = $filters['user_id'];
            }
            
            // Date range filter
            if (!empty($filters['start_date'])) {
                $where_conditions[] = "created_at >= %s";
                $params[] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $where_conditions[] = "created_at <= %s";
                $params[] = $filters['end_date'];
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Count query
            $count_query = "SELECT COUNT(*) FROM {$logs_table} {$where_clause}";
            if (!empty($params)) {
                $count_query = $wpdb->prepare($count_query, $params);
            }
            
            $total = $wpdb->get_var($count_query);
            
            // Main query with pagination
            $limit = min(100, max(1, intval($filters['per_page'] ?? 20)));
            $offset = (intval($filters['page'] ?? 1) - 1) * $limit;
            
            $main_query = "
                SELECT * FROM {$logs_table} 
                {$where_clause}
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $main_query = $wpdb->prepare($main_query, $params);
            $results = $wpdb->get_results($main_query, ARRAY_A);
            
            if ($results === null) {
                return [
                    'logs' => [],
                    'total' => 0,
                    'total_pages' => 0,
                    'page' => intval($filters['page'] ?? 1),
                    'per_page' => $limit
                ];
            }
            
            // Parse details JSON
            foreach ($results as &$log) {
                if (!empty($log['details'])) {
                    $log['details_parsed'] = json_decode($log['details'], true);
                }
            }
            
            return [
                'logs' => $results,
                'total' => intval($total),
                'total_pages' => ceil(intval($total) / $limit),
                'page' => intval($filters['page'] ?? 1),
                'per_page' => $limit
            ];
            
        } catch (\Exception $e) {
            error_log("WCFDR Logger: Failed to get logs: " . $e->getMessage());
            return [
                'logs' => [],
                'total' => 0,
                'total_pages' => 0,
                'page' => 1,
                'per_page' => 20
            ];
        }
    }
    
    /**
     * Clean old logs
     */
    public function clean_old_logs(int $days = 90): int {
        try {
            global $wpdb;
            
            $logs_table = $wpdb->prefix . 'wcfdr_operation_logs';
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$logs_table} WHERE created_at < %s",
                    $cutoff_date
                )
            );
            
            if ($deleted > 0) {
                $this->info("Cleaned up old logs", [
                    'deleted_count' => $deleted,
                    'cutoff_date' => $cutoff_date
                ]);
            }
            
            return intval($deleted);
            
        } catch (\Exception $e) {
            $this->error("Failed to clean old logs", [
                'error' => $e->getMessage(),
                'days' => $days
            ]);
            return 0;
        }
    }
    
    /**
     * Get log statistics
     */
    public function get_log_statistics(): array {
        try {
            global $wpdb;
            
            $logs_table = $wpdb->prefix . 'wcfdr_operation_logs';
            
            $stats = [
                'total_logs' => 0,
                'logs_today' => 0,
                'logs_this_week' => 0,
                'logs_this_month' => 0,
                'by_level' => [],
                'by_user' => []
            ];
            
            // Total logs
            $stats['total_logs'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}"));
            
            // Logs today
            $stats['logs_today'] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$logs_table} WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )));
            
            // Logs this week
            $stats['logs_this_week'] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$logs_table} WHERE YEARWEEK(created_at) = %s",
                current_time('YW')
            )));
            
            // Logs this month
            $stats['logs_this_month'] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$logs_table} WHERE YEAR(created_at) = %s AND MONTH(created_at) = %s",
                current_time('Y'),
                current_time('n')
            )));
            
            // By level
            $level_stats = $wpdb->get_results("
                SELECT operation_type, COUNT(*) as count 
                FROM {$logs_table} 
                GROUP BY operation_type
            ", ARRAY_A);
            
            foreach ($level_stats as $level_stat) {
                $stats['by_level'][$level_stat['operation_type']] = intval($level_stat['count']);
            }
            
            // By user (top 10)
            $user_stats = $wpdb->get_results("
                SELECT user_id, COUNT(*) as count 
                FROM {$logs_table} 
                GROUP BY user_id 
                ORDER BY count DESC 
                LIMIT 10
            ", ARRAY_A);
            
            foreach ($user_stats as $user_stat) {
                $stats['by_user'][$user_stat['user_id']] = intval($user_stat['count']);
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            $this->error("Failed to get log statistics", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Flush any pending logs
     */
    public function flush_logs(): void {
        // This method can be used to flush any buffered logs
        // Currently no buffering, but can be extended
    }
    
    /**
     * Update database logging availability
     */
    public function update_database_logging_status(): void {
        $this->enable_db_logging = $this->is_database_logging_available();
    }

    /**
     * Check if database logging is available
     */
    private function is_database_logging_available(): bool {
        try {
            global $wpdb;
            $logs_table = $wpdb->prefix . 'wcfdr_operation_logs';
            $table_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $logs_table
                )
            );
            return $table_exists !== null;
        } catch (\Exception $e) {
            error_log("WCFDR Logger: Failed to check database logging availability: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable all logging
     */
    private function disable_all_logging(): void {
        $this->enable_db_logging = false;
        $this->enable_file_logging = false;
        error_log("WCFDR Logger: Both database and file logging are disabled due to repeated failures.");
    }
    
    /**
     * Reset instance (useful for testing)
     */
    public static function reset(): void {
        self::$instance = null;
    }
}
