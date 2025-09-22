<?php
/**
 * Database Manager Class
 *
 * Handles all database operations including table creation, updates, migrations,
 * and data management for the affiliate cross-domain system.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Database_Manager {

    /**
     * Current database version
     *
     * @var string
     */
    private $db_version = '1.0.0';

    /**
     * Database tables
     *
     * @var array
     */
    private $tables = [];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->tables = [
            'vanity_codes' => $wpdb->prefix . 'affcd_vanity_codes',
            'vanity_usage' => $wpdb->prefix . 'affcd_vanity_usage',
            'authorised_domains' => $wpdb->prefix . 'affcd_authorised_domains',
            'analytics' => $wpdb->prefix . 'affcd_analytics',
            'rate_limiting' => $wpdb->prefix . 'affcd_rate_limiting',
            'security_logs' => $wpdb->prefix . 'affcd_security_logs',
            'fraud_detection' => $wpdb->prefix . 'affcd_fraud_detection'
        ];

        add_action('init', [$this, 'maybe_update_database']);
    }

    /**
     * Create all required tables
     */
    public function create_tables() {
        $this->create_vanity_codes_table();
        $this->create_vanity_usage_table();
        $this->create_authorised_domains_table();
        $this->create_analytics_table();
        $this->create_rate_limiting_table();
        $this->create_security_logs_table();
        $this->create_fraud_detection_table();
        $this->add_indexes();
        $this->update_database_version();
    }

    /**
     * Create vanity codes table
     */
    private function create_vanity_codes_table() {
        global $wpdb;
        
        $table_name = $this->tables['vanity_codes'];
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vanity_code varchar(100) NOT NULL,
            affiliate_id bigint(20) unsigned NOT NULL,
            affiliate_code varchar(100) NOT NULL,
            description text,
            discount_type enum('percentage','fixed','custom') DEFAULT 'percentage',
            discount_value decimal(10,2) DEFAULT 0.00,
            status enum('active','inactive','expired','suspended') DEFAULT 'active',
            usage_limit int unsigned DEFAULT NULL,
            usage_count int unsigned DEFAULT 0,
            conversion_count int unsigned DEFAULT 0,
            revenue_generated decimal(15,2) DEFAULT 0.00,
            expires_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_vanity_code (vanity_code),
            KEY idx_affiliate_id (affiliate_id),
            KEY idx_affiliate_code (affiliate_code),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at),
            KEY idx_created_at (created_at),
            KEY idx_usage_stats (usage_count, conversion_count),
            KEY idx_performance (status, usage_count, revenue_generated)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create vanity usage tracking table
     */
    private function create_vanity_usage_table() {
        global $wpdb;
        
        $table_name = $this->tables['vanity_usage'];
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vanity_code_id bigint(20) unsigned NOT NULL,
            domain varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            referrer varchar(500),
            session_id varchar(100),
            validation_result enum('valid','invalid','expired','suspended') NOT NULL,
            conversion_occurred tinyint(1) DEFAULT 0,
            conversion_value decimal(10,2) DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vanity_code_id (vanity_code_id),
            KEY idx_domain (domain),
            KEY idx_ip_address (ip_address),
            KEY idx_validation_result (validation_result),
            KEY idx_conversion (conversion_occurred, conversion_value),
            KEY idx_created_at (created_at),
            KEY idx_analytics (vanity_code_id, validation_result, created_at),
            CONSTRAINT fk_usage_vanity_code FOREIGN KEY (vanity_code_id) REFERENCES {$this->tables['vanity_codes']}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create authorised domains table
     */
    private function create_authorised_domains_table() {
        global $wpdb;
        
        $table_name = $this->tables['authorised_domains'];
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain_url varchar(500) NOT NULL,
            domain_name varchar(255) DEFAULT NULL,
            api_key varchar(100) NOT NULL,
            api_secret varchar(128) NOT NULL,
            status enum('active','inactive','suspended','pending') DEFAULT 'pending',
            verification_status enum('verified','unverified','failed') DEFAULT 'unverified',
            verification_token varchar(100) DEFAULT NULL,
            max_daily_requests int unsigned DEFAULT 10000,
            current_daily_requests int unsigned DEFAULT 0,
            daily_reset_at datetime DEFAULT NULL,
            rate_limit_per_minute int unsigned DEFAULT 100,
            rate_limit_per_hour int unsigned DEFAULT 1000,
            allowed_endpoints text,
            blocked_endpoints text,
            security_level enum('low','medium','high','strict') DEFAULT 'medium',
            require_https tinyint(1) DEFAULT 1,
            allowed_ips text,
            blocked_ips text,
            webhook_url varchar(500) DEFAULT NULL,
            webhook_secret varchar(128) DEFAULT NULL,
            webhook_events text,
            webhook_last_sent datetime DEFAULT NULL,
            webhook_failures int unsigned DEFAULT 0,
            total_requests bigint(20) unsigned DEFAULT 0,
            blocked_requests bigint(20) unsigned DEFAULT 0,
            last_request_at datetime DEFAULT NULL,
            last_verified_at datetime DEFAULT NULL,
            verification_failures int unsigned DEFAULT 0,
            statistics longtext,
            metadata longtext,
            owner_user_id bigint(20) unsigned DEFAULT NULL,
            owner_email varchar(255) DEFAULT NULL,
            owner_name varchar(255) DEFAULT NULL,
            contact_email varchar(255) DEFAULT NULL,
            contact_phone varchar(50) DEFAULT NULL,
            timezone varchar(50) DEFAULT 'UTC',
            language varchar(10) DEFAULT 'en',
            notes text,
            tags text,
            expires_at datetime DEFAULT NULL,
            suspended_at datetime DEFAULT NULL,
            suspended_reason text,
            suspended_by bigint(20) unsigned DEFAULT NULL,
            last_activity_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_domain_url (domain_url),
            UNIQUE KEY unique_api_key (api_key),
            KEY idx_status (status),
            KEY idx_verification_status (verification_status),
            KEY idx_owner_user_id (owner_user_id),
            KEY idx_expires_at (expires_at),
            KEY idx_last_activity (last_activity_at),
            KEY idx_created_at (created_at),
            KEY idx_security_level (security_level),
            KEY idx_performance (status, verification_status, last_activity_at),
            KEY idx_rate_limiting (domain_url, current_daily_requests, max_daily_requests),
            CONSTRAINT fk_owner_user FOREIGN KEY (owner_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_created_by FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_suspended_by FOREIGN KEY (suspended_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create analytics table
     */
    private function create_analytics_table() {
        global $wpdb;
        
        $table_name = $this->tables['analytics'];
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned DEFAULT NULL,
            domain varchar(255) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            referrer varchar(500),
            event_data longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_domain (domain),
            KEY idx_user_id (user_id),
            KEY idx_ip_address (ip_address),
            KEY idx_created_at (created_at),
            KEY idx_analytics_overview (event_type, entity_type, created_at),
            KEY idx_domain_analytics (domain, event_type, created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create rate limiting table
     */
    private function create_rate_limiting_table() {
        global $wpdb;
        
        $table_name = $this->tables['rate_limiting'];
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            action_type varchar(100) NOT NULL,
            request_count int unsigned DEFAULT 1,
            window_start datetime NOT NULL,
            window_end datetime NOT NULL,
            last_request datetime DEFAULT CURRENT_TIMESTAMP,
            is_blocked tinyint(1) DEFAULT 0,
            block_until datetime DEFAULT NULL,
            block_reason varchar(255) DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_rate_limit (identifier, action_type, window_start),
            KEY idx_identifier (identifier),
            KEY idx_action_type (action_type),
            KEY idx_window_period (window_start, window_end),
            KEY idx_blocked_status (is_blocked, block_until),
            KEY idx_rate_check (identifier, action_type, window_start, is_blocked)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create security logs table
     */
    private function create_security_logs_table() {
        global $wpdb;
        
        $table_name = $this->tables['security_logs'];
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            severity enum('low','medium','high','critical') DEFAULT 'medium',
            identifier varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            domain varchar(255) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            event_data longtext,
            response_action varchar(255) DEFAULT NULL,
            investigation_status enum('pending','investigating','resolved','false_positive') DEFAULT 'pending',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_severity (severity),
            KEY idx_identifier (identifier),
            KEY idx_ip_address (ip_address),
            KEY idx_domain (domain),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_investigation (investigation_status, severity, created_at),
            KEY idx_security_overview (event_type, severity, created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create fraud detection table
     */
    private function create_fraud_detection_table() {
        global $wpdb;
        
        $table_name = $this->tables['fraud_detection'];
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            fraud_type varchar(100) NOT NULL,
            risk_score int unsigned DEFAULT 0,
            detection_count int unsigned DEFAULT 1,
            first_detected datetime DEFAULT CURRENT_TIMESTAMP,
            last_detected datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            actions_taken longtext,
            investigation_notes text,
            resolved_at datetime DEFAULT NULL,
            resolved_by bigint(20) unsigned DEFAULT NULL,
            metadata longtext,
            PRIMARY KEY (id),
            UNIQUE KEY unique_fraud_detection (identifier, fraud_type),
            KEY idx_fraud_type (fraud_type),
            KEY idx_risk_score (risk_score),
            KEY idx_first_detected (first_detected),
            KEY idx_last_detected (last_detected),
            KEY idx_is_active (is_active),
            KEY idx_fraud_analysis (fraud_type, risk_score, is_active),
            CONSTRAINT fk_resolved_by FOREIGN KEY (resolved_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add additional indexes for performance optimization
     */
    private function add_indexes() {
        global $wpdb;
        
        // Additional performance indexes
        $indexes = [
            // Vanity codes performance indexes
            "CREATE INDEX IF NOT EXISTS idx_vanity_performance ON {$this->tables['vanity_codes']} (status, usage_count DESC, revenue_generated DESC)",
            "CREATE INDEX IF NOT EXISTS idx_vanity_expiry ON {$this->tables['vanity_codes']} (expires_at, status)",
            
            // Usage tracking indexes
            "CREATE INDEX IF NOT EXISTS idx_usage_analytics ON {$this->tables['vanity_usage']} (vanity_code_id, validation_result, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_conversion_tracking ON {$this->tables['vanity_usage']} (conversion_occurred, conversion_value, created_at)",
            
            // Domain management indexes
            "CREATE INDEX IF NOT EXISTS idx_domain_activity ON {$this->tables['authorised_domains']} (last_activity_at DESC, status)",
            "CREATE INDEX IF NOT EXISTS idx_domain_verification ON {$this->tables['authorised_domains']} (verification_status, last_verified_at)",
            
            // Analytics reporting indexes
            "CREATE INDEX IF NOT EXISTS idx_analytics_reporting ON {$this->tables['analytics']} (event_type, domain, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_analytics_entity ON {$this->tables['analytics']} (entity_type, entity_id, event_type, created_at)",
            
            // Security monitoring indexes
            "CREATE INDEX IF NOT EXISTS idx_security_monitoring ON {$this->tables['security_logs']} (severity, investigation_status, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_fraud_monitoring ON {$this->tables['fraud_detection']} (is_active, risk_score DESC, last_detected DESC)"
        ];

        foreach ($indexes as $index_sql) {
            $wpdb->query($index_sql);
        }
    }

    /**
     * Update database tables if needed
     *
     * @param string $from_version Previous version
     * @param string $to_version Target version
     */
    public function update_tables($from_version, $to_version) {
        // Handle version-specific updates
        if (version_compare($from_version, '1.0.0', '<')) {
            $this->create_tables();
        }

        // Add future migration logic here
        $this->run_migrations($from_version, $to_version);
    }

    /**
     * Run database migrations
     *
     * @param string $from_version Previous version
     * @param string $to_version Target version
     */
    private function run_migrations($from_version, $to_version) {
        $migrations = [
            '1.0.1' => 'migration_1_0_1',
            '1.1.0' => 'migration_1_1_0'
        ];

        foreach ($migrations as $version => $method) {
            if (version_compare($from_version, $version, '<') && version_compare($to_version, $version, '>=')) {
                if (method_exists($this, $method)) {
                    $this->$method();
                }
            }
        }
    }

    /**
     * Drop all tables
     */
    public function drop_tables() {
        global $wpdb;
        
        // Drop in reverse order to handle foreign key constraints
        $tables_to_drop = array_reverse($this->tables);
        
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * Get table name
     *
     * @param string $table Table identifier
     * @return string Table name
     */
    public function get_table_name($table) {
        return $this->tables[$table] ?? null;
    }

    /**
     * Check if tables exist
     *
     * @return array Missing tables
     */
    public function check_tables() {
        global $wpdb;
        
        $missing_tables = [];
        
        foreach ($this->tables as $key => $table_name) {
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));
            
            if (!$table_exists) {
                $missing_tables[] = $key;
            }
        }
        
        return $missing_tables;
    }

    /**
     * Get database statistics
     *
     * @return array Database statistics
     */
    public function get_database_statistics() {
        global $wpdb;
        
        $stats = [];
        
        foreach ($this->tables as $key => $table_name) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $stats[$key] = [
                'table' => $table_name,
                'count' => intval($count)
            ];
        }
        
        return $stats;
    }

    /**
     * Optimize database tables
     */
    public function optimize_tables() {
        global $wpdb;
        
        foreach ($this->tables as $table_name) {
            $wpdb->query("OPTIMIZE TABLE {$table_name}");
        }
    }

    /**
     * Update database version
     */
    private function update_database_version() {
        update_option('affcd_db_version', $this->db_version);
    }

    /**
     * Get current database version
     *
     * @return string Database version
     */
    public function get_database_version() {
        return get_option('affcd_db_version', '0.0.0');
    }

    /**
     * Maybe update database
     */
    public function maybe_update_database() {
        $current_version = $this->get_database_version();
        
        if (version_compare($current_version, $this->db_version, '<')) {
            $this->update_tables($current_version, $this->db_version);
            $this->update_database_version();
        }
    }

    /**
     * Clean old data
     *
     * @param int $days_old Days to keep data
     */
    public function cleanup_old_data($days_old = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        // Clean old analytics data
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['analytics']} WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Clean old security logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['security_logs']} WHERE created_at < %s AND investigation_status = 'resolved'",
            $cutoff_date
        ));
        
        // Clean old rate limiting data
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['rate_limiting']} WHERE window_end < %s",
            $cutoff_date
        ));
    }

    /**
     * Example migration method for version 1.0.1
     */
    private function migration_1_0_1() {
        global $wpdb;
        
        // Add new columns or modify existing ones
        // Example: Add new column to vanity codes table
        $wpdb->query("ALTER TABLE {$this->tables['vanity_codes']} ADD COLUMN IF NOT EXISTS last_used_at datetime DEFAULT NULL AFTER updated_at");
        
        // Add index for new column
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_last_used ON {$this->tables['vanity_codes']} (last_used_at)");
    }

    /**
     * Example migration method for version 1.1.0
     */
    private function migration_1_1_0() {
        global $wpdb;
        
        // Example: Add new table for advanced features
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_advanced_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_type varchar(100) NOT NULL,
            report_data longtext,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_report_type (report_type),
            KEY idx_generated_at (generated_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}