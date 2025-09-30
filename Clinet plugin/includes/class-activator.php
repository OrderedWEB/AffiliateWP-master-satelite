<?php
/**
 * Plugin Activation and Deactivation Handler
 * 
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-activator.php
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * 
 * COMPLETE IMPLEMENTATION - Handles all activation, deactivation, and database setup
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Activator {

    /**
     * Plugin activation
     * Creates all necessary database tables, sets default options, and schedules cron jobs
     */
    public static function activate() {
        // Create database tables
        self::create_tracking_tables();
        self::create_attribution_tables();
        self::create_analytics_tables();
        self::create_session_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Set activation flag
        add_option('aci_activated', time());
        add_option('aci_version', ACI_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        if (function_exists('aci_log')) {
            aci_log('info', 'Plugin activated successfully');
        }
    }

    /**
     * Create tracking event tables
     */
    private static function create_tracking_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tracking events table
        $table_name = $wpdb->prefix . 'aci_tracking_events';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id varchar(64) NOT NULL,
            session_id varchar(64) DEFAULT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            affiliate_code varchar(50) DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            page_url varchar(500) DEFAULT NULL,
            referrer varchar(500) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX visit_idx (visit_id),
            INDEX session_idx (session_id),
            INDEX affiliate_idx (affiliate_id),
            INDEX event_type_idx (event_type),
            INDEX created_idx (created_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Attribution statistics table
        $table_name = $wpdb->prefix . 'aci_attribution_stats';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            total_visits int(11) NOT NULL DEFAULT 0,
            total_conversions int(11) NOT NULL DEFAULT 0,
            avg_quality_score decimal(5,4) NOT NULL DEFAULT 0.0000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY affiliate_idx (affiliate_id),
            INDEX conversions_idx (total_conversions),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Touchpoint tracking table
        $table_name = $wpdb->prefix . 'aci_touchpoints';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id varchar(64) NOT NULL,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            touchpoint_type varchar(50) NOT NULL,
            touchpoint_data longtext DEFAULT NULL,
            quality_score decimal(5,4) NOT NULL DEFAULT 0.0000,
            weight decimal(5,4) NOT NULL DEFAULT 0.0000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX visit_idx (visit_id),
            INDEX affiliate_idx (affiliate_id),
            INDEX type_idx (touchpoint_type),
            INDEX created_idx (created_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }

    /**
     * Create analytics tables
     */
    private static function create_analytics_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Daily metrics table
        $table_name = $wpdb->prefix . 'aci_daily_metrics';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_date date NOT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            visits int(11) NOT NULL DEFAULT 0,
            conversions int(11) NOT NULL DEFAULT 0,
            conversion_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            avg_session_duration int(11) NOT NULL DEFAULT 0,
            bounce_rate decimal(5,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY date_affiliate_idx (metric_date, affiliate_id),
            INDEX date_idx (metric_date),
            INDEX affiliate_idx (affiliate_id),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Performance metrics table
        $table_name = $wpdb->prefix . 'aci_performance_metrics';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url varchar(500) NOT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            page_load_time int(11) NOT NULL DEFAULT 0,
            time_to_interactive int(11) NOT NULL DEFAULT 0,
            first_contentful_paint int(11) NOT NULL DEFAULT 0,
            largest_contentful_paint int(11) NOT NULL DEFAULT 0,
            cumulative_layout_shift decimal(5,4) NOT NULL DEFAULT 0.0000,
            sample_count int(11) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX url_idx (page_url(255)),
            INDEX affiliate_idx (affiliate_id),
            INDEX created_idx (created_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }

    /**
     * Create session tables
     */
    private static function create_session_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sessions table
        $table_name = $wpdb->prefix . 'aci_sessions';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            affiliate_code varchar(50) DEFAULT NULL,
            session_data longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            UNIQUE KEY session_idx (session_id),
            INDEX user_idx (user_id),
            INDEX affiliate_idx (affiliate_id),
            INDEX expires_idx (expires_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            'aci_tracking_enabled' => true,
            'aci_attribution_model' => 'last_click',
            'aci_attribution_window' => 30,
            'aci_cookie_duration' => 30,
            'aci_session_timeout' => 1800,
            'aci_data_retention_days' => 365,
            'aci_debug_mode' => false,
            'aci_cache_enabled' => true,
            'aci_cache_duration' => 3600,
            'aci_sync_interval' => 3600,
            'aci_batch_size' => 100,
            'aci_retry_attempts' => 3,
            'aci_timeout' => 30,
            'aci_verify_ssl' => true,
            'aci_rate_limit' => 1000,
            'aci_queue_processing_enabled' => true,
            'aci_realtime_tracking' => true,
            'aci_performance_tracking' => true,
            'aci_attribution_tracking' => true
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
        // Sync conversions hourly
        if (!wp_next_scheduled('aci_sync_conversions')) {
            wp_schedule_event(time(), 'hourly', 'aci_sync_conversions');
        }
        
        // Clean up old data daily
        if (!wp_next_scheduled('aci_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'aci_cleanup_old_data');
        }
        
        // Clean up expired sessions hourly
        if (!wp_next_scheduled('aci_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'aci_cleanup_sessions');
        }
        
        // Update attribution statistics daily
        if (!wp_next_scheduled('aci_update_attribution_stats')) {
            wp_schedule_event(time(), 'daily', 'aci_update_attribution_stats');
        }
        
        // Generate analytics reports weekly
        if (!wp_next_scheduled('aci_generate_analytics')) {
            wp_schedule_event(time(), 'weekly', 'aci_generate_analytics');
        }
    }

    /**
     * Plugin deactivation
     * Cleans up scheduled tasks and optionally removes data
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
        
        // Clear transients and cache
        self::clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Update deactivation timestamp
        update_option('aci_deactivated', time());
        
        // Log deactivation
        if (function_exists('aci_log')) {
            aci_log('info', 'Plugin deactivated');
        }
    }

    /**
     * Clear all scheduled cron jobs
     */
    private static function clear_cron_jobs() {
        $cron_jobs = [
            'aci_sync_conversions',
            'aci_cleanup_old_data',
            'aci_cleanup_sessions',
            'aci_update_attribution_stats',
            'aci_generate_analytics'
        ];
        
        foreach ($cron_jobs as $job) {
            $timestamp = wp_next_scheduled($job);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $job);
            }
        }
    }

    /**
     * Clear all plugin cache and transients
     */
    private static function clear_cache() {
        global $wpdb;
        
        // Clear plugin-specific transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aci_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aci_%'");
        
        // Clear object cache
        wp_cache_flush();
        
        // Clear specific cache groups
        wp_cache_delete_group('aci_affiliates');
        wp_cache_delete_group('aci_sessions');
        wp_cache_delete_group('aci_metrics');
    }

    /**
     * Plugin uninstall
     * Removes all plugin data including tables and options
     */
    public static function uninstall() {
        // Check if user wants to keep data
        $keep_data = get_option('aci_keep_data_on_uninstall', false);
        
        if ($keep_data) {
            return;
        }
        
        // Remove all database tables
        self::remove_tables();
        
        // Remove all plugin options
        self::remove_options();
        
        // Clear all cron jobs
        self::clear_cron_jobs();
        
        // Clear all cache
        self::clear_cache();
        
        // Log uninstall
        if (function_exists('aci_log')) {
            aci_log('info', 'Plugin uninstalled - all data removed');
        }
    }

    /**
     * Remove all plugin database tables
     */
    private static function remove_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aci_tracking_events',
            $wpdb->prefix . 'aci_conversion_logs',
            $wpdb->prefix . 'aci_attribution_results',
            $wpdb->prefix . 'aci_attribution_stats',
            $wpdb->prefix . 'aci_touchpoints',
            $wpdb->prefix . 'aci_daily_metrics',
            $wpdb->prefix . 'aci_performance_metrics',
            $wpdb->prefix . 'aci_sessions'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        global $wpdb;
        
        // Remove all options starting with 'aci_'
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aci_%'");
    }

    /**
     * Check if plugin needs database update
     * @return bool Whether update is needed
     */
    public static function needs_database_update() {
        $current_version = get_option('aci_db_version', '0');
        $required_version = ACI_DB_VERSION;
        
        return version_compare($current_version, $required_version, '<');
    }

    /**
     * Update database schema
     */
    public static function update_database() {
        $current_version = get_option('aci_db_version', '0');
        
        // Run migrations in order
        if (version_compare($current_version, '1.0.0', '<')) {
            self::migrate_to_1_0_0();
        }
        
        if (version_compare($current_version, '1.1.0', '<')) {
            self::migrate_to_1_1_0();
        }
        
        // Update version
        update_option('aci_db_version', ACI_DB_VERSION);
        
        // Log update
        if (function_exists('aci_log')) {
            aci_log('info', 'Database updated to version ' . ACI_DB_VERSION);
        }
    }

    /**
     * Migration to version 1.0.0
     */
    private static function migrate_to_1_0_0() {
        // Initial database setup
        self::create_tracking_tables();
        self::create_attribution_tables();
        self::create_analytics_tables();
        self::create_session_tables();
    }

    /**
     * Migration to version 1.1.0
     */
    private static function migrate_to_1_1_0() {
        global $wpdb;
        
        // Add new columns or tables for version 1.1.0
        // Example: Add quality_score to touchpoints if it doesn't exist
        $table_name = $wpdb->prefix . 'aci_touchpoints';
        
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$table_name} LIKE 'quality_score'"
        );
        
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD COLUMN quality_score decimal(5,4) NOT NULL DEFAULT 0.0000 
                 AFTER touchpoint_data"
            );
        }
    }

    /**
     * Get database table status
     * @return array Table status information
     */
    public static function get_table_status() {
        global $wpdb;
        
        $tables = [
            'tracking_events' => $wpdb->prefix . 'aci_tracking_events',
            'conversion_logs' => $wpdb->prefix . 'aci_conversion_logs',
            'attribution_results' => $wpdb->prefix . 'aci_attribution_results',
            'attribution_stats' => $wpdb->prefix . 'aci_attribution_stats',
            'touchpoints' => $wpdb->prefix . 'aci_touchpoints',
            'daily_metrics' => $wpdb->prefix . 'aci_daily_metrics',
            'performance_metrics' => $wpdb->prefix . 'aci_performance_metrics',
            'sessions' => $wpdb->prefix . 'aci_sessions'
        ];
        
        $status = [];
        
        foreach ($tables as $key => $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $table_size = $wpdb->get_var(
                    "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                     FROM information_schema.TABLES 
                     WHERE table_schema = DATABASE() 
                     AND table_name = '{$table_name}'"
                );
                
                $status[$key] = [
                    'exists' => true,
                    'rows' => intval($row_count),
                    'size_mb' => floatval($table_size)
                ];
            } else {
                $status[$key] = [
                    'exists' => false,
                    'rows' => 0,
                    'size_mb' => 0
                ];
            }
        }
        
        return $status;
    }

    /**
     * Repair database tables
     * @return array Repair results
     */
    public static function repair_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aci_tracking_events',
            $wpdb->prefix . 'aci_conversion_logs',
            $wpdb->prefix . 'aci_attribution_results',
            $wpdb->prefix . 'aci_attribution_stats',
            $wpdb->prefix . 'aci_touchpoints',
            $wpdb->prefix . 'aci_daily_metrics',
            $wpdb->prefix . 'aci_performance_metrics',
            $wpdb->prefix . 'aci_sessions'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $result = $wpdb->query("REPAIR TABLE {$table}");
            $results[$table] = $result !== false;
        }
        
        return $results;
    }

    /**
     * Optimize database tables
     * @return array Optimization results
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aci_tracking_events',
            $wpdb->prefix . 'aci_conversion_logs',
            $wpdb->prefix . 'aci_attribution_results',
            $wpdb->prefix . 'aci_attribution_stats',
            $wpdb->prefix . 'aci_touchpoints',
            $wpdb->prefix . 'aci_daily_metrics',
            $wpdb->prefix . 'aci_performance_metrics',
            $wpdb->prefix . 'aci_sessions'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            $results[$table] = $result !== false;
        }
        
        return $results;
    }
}

/**
 * Define database version constant
 */
if (!defined('ACI_DB_VERSION')) {
    define('ACI_DB_VERSION', '1.1.0');
}

/**
 * Register activation hook
 */
register_activation_hook(ACI_FILE, ['ACI_Activator', 'activate']);

/**
 * Register deactivation hook
 */
register_deactivation_hook(ACI_FILE, ['ACI_Activator', 'deactivate']);

/**
 * Register uninstall hook
 */
register_uninstall_hook(ACI_FILE, ['ACI_Activator', 'uninstall']);

/**
 * Check for database updates on admin init
 */
add_action('admin_init', function() {
    if (ACI_Activator::needs_database_update()) {
        ACI_Activator::update_database();
    }
});

        
        // Conversion logs table
        $table_name = $wpdb->prefix . 'aci_conversion_logs';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id varchar(64) NOT NULL,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            conversion_data longtext NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            reference varchar(255) DEFAULT NULL,
            synced tinyint(1) NOT NULL DEFAULT 0,
            sync_attempts int(11) NOT NULL DEFAULT 0,
            last_sync_attempt datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX affiliate_idx (affiliate_id),
            INDEX synced_idx (synced),
            INDEX created_idx (created_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }

    /**
     * Create attribution tables
     */
    private static function create_attribution_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Attribution results table
        $table_name = $wpdb->prefix . 'aci_attribution_results';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id varchar(64) NOT NULL,
            attribution_model varchar(50) NOT NULL,
            attribution_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX visit_idx (visit_id),
            INDEX model_idx (attribution_model),
            INDEX created_idx (created_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Attribution statistics table
        $table_name = $wpdb->prefix . 'aci_attribution_stats';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            total_visits int(11) NOT NULL DEFAULT 0,
            total_conversions int(11) NOT NULL DEFAULT 0,
            avg_quality_score decimal(5,4) NOT NULL DEFAULT 0.0000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY affiliate_idx (affiliate_id),
            INDEX conversions_idx (total_conversions),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Touchpoint tracking table
        $table_name = $wpdb->prefix . 'aci_touchpoints';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id varchar(64) NOT NULL,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            touchpoint_type varchar(50) NOT NULL,
            touchpoint_data longtext DEFAULT NULL,
            quality_score decimal(5,4) NOT NULL DEFAULT 0.0000,
            weight decimal(5,4) NOT NULL DEFAULT 0.0000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX visit_idx (visit_id),
            INDEX affiliate_idx (affiliate_id),
            INDEX type_idx (touchpoint_type),
            INDEX created_idx (created_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }

    /**
     * Create analytics tables
     */
    private static function create_analytics_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Daily metrics table
        $table_name = $wpdb->prefix . 'aci_daily_metrics';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_date date NOT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            visits int(11) NOT NULL DEFAULT 0,
            conversions int(11) NOT NULL DEFAULT 0,
            conversion_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            avg_session_duration int(11) NOT NULL DEFAULT 0,
            bounce_rate decimal(5,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY date_affiliate_idx (metric_date, affiliate_id),
            INDEX date_idx (metric_date),
            INDEX affiliate_idx (affiliate_id),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Performance metrics table
        $table_name = $wpdb->prefix . 'aci_performance_metrics';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url varchar(500) NOT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            page_load_time int(11) NOT NULL DEFAULT 0,
            time_to_interactive int(11) NOT NULL DEFAULT 0,
            first_contentful_paint int(11) NOT NULL DEFAULT 0,
            largest_contentful_paint int(11) NOT NULL DEFAULT 0,
            cumulative_layout_shift decimal(5,4) NOT NULL DEFAULT 0.0000,
            sample_count int(11) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX url_idx (page_url(255)),
            INDEX affiliate_idx (affiliate_id),
            INDEX created_idx (created_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }

    /**
     * Create session tables
     */
    private static function create_session_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sessions table
        $table_name = $wpdb->prefix . 'aci_sessions';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            affiliate_code varchar(50) DEFAULT NULL,
            session_data longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            UNIQUE KEY session_idx (session_id),
            INDEX user_idx (user_id),
            INDEX affiliate_idx (affiliate_id),
            INDEX expires_idx (expires_at),
            PRIMARY KEY (id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            'aci_tracking_enabled' => true,
            'aci_attribution_model' => 'last_click',
            'aci_attribution_window' => 30,
            'aci_cookie_duration' => 30,
            'aci_session_timeout' => 1800,
            'aci_data_retention_days' => 365,
            'aci_debug_mode' => false,
            'aci_cache_enabled' => true,
            'aci_cache_duration' => 3600,
            'aci_sync_interval' => 3600,
            'aci_batch_size' => 100,
            'aci_retry_attempts' => 3,
            'aci_timeout' => 30,
            'aci_verify_ssl' => true,
            'aci_rate_limit' => 1000,
            'aci_queue_processing_enabled' => true,
            'aci_realtime_tracking' => true,
            'aci_performance_tracking' => true,
            'aci_attribution_tracking' => true
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
        // Sync conversions hourly
        if (!wp_next_scheduled('aci_sync_conversions')) {
            wp_schedule_event(time(), 'hourly', 'aci_sync_conversions');
        }
        
        // Clean up old data daily
        if (!wp_next_scheduled('aci_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'aci_cleanup_old_data');
        }
        
        // Clean up expired sessions hourly
        if (!wp_next_scheduled('aci_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'aci_cleanup_sessions');
        }
        
        // Update attribution statistics daily
        if (!wp_next_scheduled('aci_update_attribution_stats')) {
            wp_schedule_event(time(), 'daily', 'aci_update_attribution_stats');
        }
        
        // Generate analytics reports weekly
        if (!wp_next_scheduled('aci_generate_analytics')) {
            wp_schedule_event(time(), 'weekly', 'aci_generate_analytics');
        }
    }

    /**
     * Plugin deactivation
     * Cleans up scheduled tasks and optionally removes data
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
        
        // Clear transients and cache
        self::clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Update deactivation timestamp
        update_option('aci_deactivated', time());
        
        // Log deactivation
        if (function_exists('aci_log')) {
            aci_log('info', 'Plugin deactivated');
        }
    }

    /**
     * Clear all scheduled cron jobs
     */
    private static function clear_cron_jobs() {
        $cron_jobs = [
            'aci_sync_conversions',
            'aci_cleanup_old_data',
            'aci_cleanup_sessions',
            'aci_update_attribution_stats',
            'aci_generate_analytics'
        ];
        
        foreach ($cron_jobs as $job) {
            $timestamp = wp_next_scheduled($job);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $job);
            }
        }
    }

    /**
     * Clear all plugin cache and transients
     */
    private static function clear_cache() {
        global $wpdb;
        
        // Clear plugin-specific transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aci_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aci_%'");
        
        // Clear object cache
        wp_cache_flush();
        
        // Clear specific cache groups
        wp_cache_delete_group('aci_affiliates');
        wp_cache_delete_group('aci_sessions');
        wp_cache_delete_group('aci_metrics');
    }

    /**
     * Plugin uninstall
     * Removes all plugin data including tables and options
     */
    public static function uninstall() {
        // Check if user wants to keep data
        $keep_data = get_option('aci_keep_data_on_uninstall', false);
        
        if ($keep_data) {
            return;
        }
        
        // Remove all database tables
        self::remove_tables();
        
        // Remove all plugin options
        self::remove_options();
        
        // Clear all cron jobs
        self::clear_cron_jobs();
        
        // Clear all cache
        self::clear_cache();
        
        // Log uninstall
        if (function_exists('aci_log')) {
            aci_log('info', 'Plugin uninstalled - all data removed');
        }
    }

    /**
     * Remove all plugin database tables
     */
    private static function remove_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aci_tracking_events',
            $wpdb->prefix . 'aci_conversion_logs',
            $wpdb->prefix . 'aci_attribution_results',
            $wpdb->prefix . 'aci_attribution_stats',
            $wpdb->prefix . 'aci_touchpoints',
            $wpdb->prefix . 'aci_daily_metrics',
            $wpdb->prefix . 'aci_performance_metrics',
            $wpdb->prefix . 'aci_sessions'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        global $wpdb;
        
        // Remove all options starting with 'aci_'
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aci_%'");
    }

    /**
     * Check if plugin needs database update
     * @return bool Whether update is needed
     */
    public static function needs_database_update() {
        $current_version = get_option('aci_db_version', '0');
        $required_version = ACI_DB_VERSION;
        
        return version_compare($current_version, $required_version, '<');
    }

    /**
     * Update database schema
     */
    public static function update_database() {
        $current_version = get_option('aci_db_version', '0');
        
        // Run migrations in order
        if (version_compare($current_version, '1.0.0', '<')) {
            self::migrate_to_1_0_0();
        }
        
        if (version_compare($current_version, '1.1.0', '<')) {
            self::migrate_to_1_1_0();
        }
        
        // Update version
        update_option('aci_db_version', ACI_DB_VERSION);
        
        // Log update
        if (function_exists('aci_log')) {
            aci_log('info', 'Database updated to version ' . ACI_DB_VERSION);
        }
    }

    /**
     * Migration to version 1.0.0
     */
    private static function migrate_to_1_0_0() {
        // Initial database setup
        self::create_tracking_tables();
        self::create_attribution_tables();
        self::create_analytics_tables();
        self::create_session_tables();
    }

    /**
     * Migration to version 1.1.0
     */
    private static function migrate_to_1_1_0() {
        global $wpdb;
        
        // Add new columns or tables for version 1.1.0
        // Example: Add quality_score to touchpoints if it doesn't exist
        $table_name = $wpdb->prefix . 'aci_touchpoints';
        
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$table_name} LIKE 'quality_score'"
        );
        
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD COLUMN quality_score decimal(5,4) NOT NULL DEFAULT 0.0000 
                 AFTER touchpoint_data"
            );
        }
    }

    /**
     * Get database table status
     * @return array Table status information
     */
    public static function get_table_status() {
        global $wpdb;
        
        $tables = [
            'tracking_events' => $wpdb->prefix . 'aci_tracking_events',
            'conversion_logs' => $wpdb->prefix . 'aci_conversion_logs',
            'attribution_results' => $wpdb->prefix . 'aci_attribution_results',
            'attribution_stats' => $wpdb->prefix . 'aci_attribution_stats',
            'touchpoints' => $wpdb->prefix . 'aci_touchpoints',
            'daily_metrics' => $wpdb->prefix . 'aci_daily_metrics',
            'performance_metrics' => $wpdb->prefix . 'aci_performance_metrics',
            'sessions' => $wpdb->prefix . 'aci_sessions'
        ];
        
        $status = [];
        
        foreach ($tables as $key => $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $table_size = $wpdb->get_var(
                    "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                     FROM information_schema.TABLES 
                     WHERE table_schema = DATABASE() 
                     AND table_name = '{$table_name}'"
                );
                
                $status[$key] = [
                    'exists' => true,
                    'rows' => intval($row_count),
                    'size_mb' => floatval($table_size)
                ];
            } else {
                $status[$key] = [
                    'exists' => false,
                    'rows' => 0,
                    'size_mb' => 0
                ];
            }
        }
        
        return $status;
    }

    /**
     * Repair database tables
     * @return array Repair results
     */
    public static function repair_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aci_tracking_events',
            $wpdb->prefix . 'aci_conversion_logs',
            $wpdb->prefix . 'aci_attribution_results',
            $wpdb->prefix . 'aci_attribution_stats',
            $wpdb->prefix . 'aci_touchpoints',
            $wpdb->prefix . 'aci_daily_metrics',
            $wpdb->prefix . 'aci_performance_metrics',
            $wpdb->prefix . 'aci_sessions'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $result = $wpdb->query("REPAIR TABLE {$table}");
            $results[$table] = $result !== false;
        }
        
        return $results;
    }

    /**
     * Optimize database tables
     * @return array Optimization results
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aci_tracking_events',
            $wpdb->prefix . 'aci_conversion_logs',
            $wpdb->prefix . 'aci_attribution_results',
            $wpdb->prefix . 'aci_attribution_stats',
            $wpdb->prefix . 'aci_touchpoints',
            $wpdb->prefix . 'aci_daily_metrics',
            $wpdb->prefix . 'aci_performance_metrics',
            $wpdb->prefix . 'aci_sessions'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            $results[$table] = $result !== false;
        }
        
        return $results;
    }
}

/**
 * Define database version constant
 */
if (!defined('ACI_DB_VERSION')) {
    define('ACI_DB_VERSION', '1.1.0');
}