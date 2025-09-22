<?php
/**
 * Rate Limiting Table Migration for Affiliate Cross Domain System
 * 
 * Path: /wp-content/plugins/affiliate-cross-domain-system/database/migrations/004_create_rate_limiting_table.php
 * Plugin: Affiliate Cross Domain System (Master)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Migration_004_Create_Rate_Limiting_Table {

    /**
     * Run the migration
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL COMMENT 'IP address, API key, or user ID',
            identifier_type enum('ip','api_key','user_id','domain') NOT NULL,
            endpoint varchar(255) NOT NULL COMMENT 'API endpoint or action',
            time_window enum('minute','hour','day','month') NOT NULL,
            window_start datetime NOT NULL COMMENT 'Start of current time window',
            request_count int(11) NOT NULL DEFAULT 0,
            limit_amount int(11) NOT NULL COMMENT 'Maximum requests allowed in window',
            blocked_count int(11) NOT NULL DEFAULT 0 COMMENT 'Number of blocked requests',
            first_request_at datetime NOT NULL,
            last_request_at datetime NOT NULL,
            last_blocked_at datetime NULL,
            reset_at datetime NOT NULL COMMENT 'When the window resets',
            status enum('active','exceeded','blocked','suspended') NOT NULL DEFAULT 'active',
            violation_level int(11) NOT NULL DEFAULT 0 COMMENT 'Escalation level for repeated violations',
            metadata longtext NULL COMMENT 'Additional context data',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_rate_limit (identifier, identifier_type, endpoint, time_window),
            KEY idx_identifier (identifier),
            KEY idx_identifier_type (identifier_type),
            KEY idx_endpoint (endpoint),
            KEY idx_time_window (time_window),
            KEY idx_window_start (window_start),
            KEY idx_reset_at (reset_at),
            KEY idx_status (status),
            KEY idx_exceeded_limits (status, violation_level, last_blocked_at),
            KEY idx_cleanup (reset_at, status),
            KEY idx_monitoring (identifier_type, endpoint, status, request_count)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Create rate limiting events table
        self::create_rate_limit_events_table();
        
        // Create performance indexes
        self::create_performance_indexes($table_name);
        
        // Log migration
        error_log("AFFCD Migration 004: Rate limiting table created/updated");
        
        return $result;
    }

    /**
     * Create rate limiting events table for detailed logging
     */
    private static function create_rate_limit_events_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limit_events';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rate_limit_id bigint(20) unsigned NOT NULL,
            event_type enum('request','block','reset','violation','escalation') NOT NULL,
            identifier varchar(255) NOT NULL,
            endpoint varchar(255) NOT NULL,
            request_details longtext NULL,
            violation_details longtext NULL,
            action_taken varchar(255) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_rate_limit_id (rate_limit_id),
            KEY idx_event_type (event_type),
            KEY idx_identifier (identifier),
            KEY idx_endpoint (endpoint),
            KEY idx_created_at (created_at),
            KEY idx_violations (event_type, identifier, created_at),
            CONSTRAINT fk_rate_limit_events_rate_limit 
                FOREIGN KEY (rate_limit_id) 
                REFERENCES {$wpdb->prefix}affcd_rate_limiting(id) 
                ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create additional performance indexes
     */
    private static function create_performance_indexes($table_name) {
        global $wpdb;
        
        $indexes = [
            "CREATE INDEX idx_active_limits ON {$table_name} (status, reset_at, request_count) WHERE status = 'active'",
            "CREATE INDEX idx_exceeded_recent ON {$table_name} (identifier_type, violation_level, last_blocked_at) WHERE status = 'exceeded'",
            "CREATE INDEX idx_endpoint_performance ON {$table_name} (endpoint, time_window, request_count, limit_amount)",
            "CREATE INDEX idx_suspicious_activity ON {$table_name} (violation_level, blocked_count, last_blocked_at) WHERE violation_level > 0"
        ];
        
        foreach ($indexes as $index_sql) {
            try {
                $wpdb->query($index_sql);
            } catch (Exception $e) {
                // Some MySQL versions don't support WHERE clause in indexes
                error_log("AFFCD Migration 004: Index creation note - " . $e->getMessage());
            }
        }
    }

    /**
     * Rollback the migration
     */
    public static function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $events_table = $wpdb->prefix . 'affcd_rate_limit_events';
        
        // Drop events table first (foreign key constraint)
        $wpdb->query("DROP TABLE IF EXISTS {$events_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        
        // Log rollback
        error_log("AFFCD Migration 004: Rate limiting tables dropped");
        
        return true;
    }

    /**
     * Seed the table with default rate limits
     */
    public static function seed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        
        // Default rate limits for different scenarios
        $default_limits = [
            // API endpoint limits
            [
                'identifier' => 'global',
                'identifier_type' => 'api_key',
                'endpoint' => '/api/v1/validate',
                'time_window' => 'minute',
                'limit_amount' => 100,
                'window_start' => current_time('mysql'),
                'reset_at' => date('Y-m-d H:i:00', strtotime('+1 minute')),
                'first_request_at' => current_time('mysql'),
                'last_request_at' => current_time('mysql')
            ],
            [
                'identifier' => 'global',
                'identifier_type' => 'api_key',
                'endpoint' => '/api/v1/validate',
                'time_window' => 'hour',
                'limit_amount' => 1000,
                'window_start' => current_time('mysql'),
                'reset_at' => date('Y-m-d H:00:00', strtotime('+1 hour')),
                'first_request_at' => current_time('mysql'),
                'last_request_at' => current_time('mysql')
            ],
            [
                'identifier' => 'global',
                'identifier_type' => 'api_key',
                'endpoint' => '/api/v1/validate',
                'time_window' => 'day',
                'limit_amount' => 10000,
                'window_start' => current_time('mysql'),
                'reset_at' => date('Y-m-d 00:00:00', strtotime('+1 day')),
                'first_request_at' => current_time('mysql'),
                'last_request_at' => current_time('mysql')
            ],
            // IP-based limits
            [
                'identifier' => 'global',
                'identifier_type' => 'ip',
                'endpoint' => '*',
                'time_window' => 'minute',
                'limit_amount' => 60,
                'window_start' => current_time('mysql'),
                'reset_at' => date('Y-m-d H:i:00', strtotime('+1 minute')),
                'first_request_at' => current_time('mysql'),
                'last_request_at' => current_time('mysql')
            ],
            [
                'identifier' => 'global',
                'identifier_type' => 'ip',
                'endpoint' => '*',
                'time_window' => 'hour',
                'limit_amount' => 1000,
                'window_start' => current_time('mysql'),
                'reset_at' => date('Y-m-d H:00:00', strtotime('+1 hour')),
                'first_request_at' => current_time('mysql'),
                'last_request_at' => current_time('mysql')
            ]
        ];
        
        foreach ($default_limits as $limit) {
            $wpdb->insert($table_name, $limit);
        }
        
        error_log("AFFCD Migration 004: Default rate limits seeded successfully");
    }

    /**
     * Check if migration is needed
     */
    public static function is_migration_needed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));
        
        return $table_exists == 0;
    }

    /**
     * Get migration info
     */
    public static function get_migration_info() {
        return [
            'version' => '004',
            'name' => 'Create Rate Limiting Table',
            'description' => 'Creates rate limiting tables for API throttling and abuse prevention',
            'dependencies' => ['001', '002', '003'],
            'estimated_time' => '30 seconds',
            'affects_data' => false
        ];
    }

    /**
     * Validate table structure
     */
    public static function validate_table_structure() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $events_table = $wpdb->prefix . 'affcd_rate_limit_events';
        
        // Check main table
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        $required_columns = [
            'id', 'identifier', 'identifier_type', 'endpoint', 'time_window',
            'window_start', 'request_count', 'limit_amount', 'reset_at', 'status'
        ];
        
        $existing_columns = wp_list_pluck($columns, 'Field');
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (!empty($missing_columns)) {
            error_log("AFFCD Migration 004: Missing columns in main table - " . implode(', ', $missing_columns));
            return false;
        }
        
        // Check events table
        $events_columns = $wpdb->get_results("DESCRIBE {$events_table}");
        $required_events_columns = ['id', 'rate_limit_id', 'event_type', 'identifier', 'endpoint'];
        
        $existing_events_columns = wp_list_pluck($events_columns, 'Field');
        $missing_events_columns = array_diff($required_events_columns, $existing_events_columns);
        
        if (!empty($missing_events_columns)) {
            error_log("AFFCD Migration 004: Missing columns in events table - " . implode(', ', $missing_events_columns));
            return false;
        }
        
        return true;
    }

    /**
     * Clean up expired rate limit windows
     */
    public static function cleanup_expired_windows() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $events_table = $wpdb->prefix . 'affcd_rate_limit_events';
        $current_time = current_time('mysql');
        
        // Delete expired rate limit windows
        $deleted_limits = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE reset_at < %s AND status != 'blocked'",
            $current_time
        ));
        
        // Clean up old events (keep last 30 days)
        $event_cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        $deleted_events = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$events_table} WHERE created_at < %s",
            $event_cutoff
        ));
        
        error_log("AFFCD Migration 004: Cleaned up {$deleted_limits} expired limits and {$deleted_events} old events");
        
        return ['limits' => $deleted_limits, 'events' => $deleted_events];
    }

    /**
     * Reset rate limit windows that have expired
     */
    public static function reset_expired_windows() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $current_time = current_time('mysql');
        
        // Find windows that need to be reset
        $expired_windows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE reset_at <= %s AND status = 'active'",
            $current_time
        ));
        
        $reset_count = 0;
        
        foreach ($expired_windows as $window) {
            $new_window_start = current_time('mysql');
            $new_reset_time = self::calculate_reset_time($window->time_window, $new_window_start);
            
            $updated = $wpdb->update(
                $table_name,
                [
                    'window_start' => $new_window_start,
                    'reset_at' => $new_reset_time,
                    'request_count' => 0,
                    'status' => 'active'
                ],
                ['id' => $window->id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                $reset_count++;
                
                // Log reset event
                self::log_rate_limit_event($window->id, 'reset', $window->identifier, $window->endpoint, [
                    'old_window_start' => $window->window_start,
                    'new_window_start' => $new_window_start,
                    'old_request_count' => $window->request_count
                ]);
            }
        }
        
        error_log("AFFCD Migration 004: Reset {$reset_count} expired rate limit windows");
        
        return $reset_count;
    }

    /**
     * Calculate next reset time based on window type
     */
    private static function calculate_reset_time($time_window, $start_time) {
        switch ($time_window) {
            case 'minute':
                return date('Y-m-d H:i:00', strtotime($start_time . ' +1 minute'));
            case 'hour':
                return date('Y-m-d H:00:00', strtotime($start_time . ' +1 hour'));
            case 'day':
                return date('Y-m-d 00:00:00', strtotime($start_time . ' +1 day'));
            case 'month':
                return date('Y-m-01 00:00:00', strtotime($start_time . ' +1 month'));
            default:
                return date('Y-m-d H:i:00', strtotime($start_time . ' +1 minute'));
        }
    }

    /**
     * Log rate limiting event
     */
    public static function log_rate_limit_event($rate_limit_id, $event_type, $identifier, $endpoint, $details = []) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'affcd_rate_limit_events';
        
        $event_data = [
            'rate_limit_id' => $rate_limit_id,
            'event_type' => $event_type,
            'identifier' => $identifier,
            'endpoint' => $endpoint,
            'request_details' => wp_json_encode($details),
            'created_at' => current_time('mysql')
        ];
        
        return $wpdb->insert($events_table, $event_data);
    }

    /**
     * Get rate limit status for identifier
     */
    public static function get_rate_limit_status($identifier, $identifier_type, $endpoint = '*') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $current_time = current_time('mysql');
        
        $rate_limits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE identifier = %s 
             AND identifier_type = %s 
             AND (endpoint = %s OR endpoint = '*')
             AND reset_at > %s
             ORDER BY time_window ASC",
            $identifier,
            $identifier_type,
            $endpoint,
            $