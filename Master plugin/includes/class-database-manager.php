<?php
/**
 * Database Manager Class
 *
 * Handles all database operations including table creation, updates, migrations,
 * data management, performance optimisation, and maintenance for the affiliate 
 * cross-domain system.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 * @author Richard King, starneconsulting.com
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
     * Migration log table
     *
     * @var string
     */
    private $migration_log_table;

    /**
     * Database character set and collation
     *
     * @var string
     */
    private $charset_collate;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->charset_collate = $wpdb->get_charset_collate();
        
        $this->tables = [
            'vanity_codes'       => $wpdb->prefix . 'affcd_vanity_codes',
            'vanity_usage'       => $wpdb->prefix . 'affcd_vanity_usage',
            'authorized_domains' => $wpdb->prefix . 'affcd_authorized_domains', // fixed key (lowercase)
            'analytics'          => $wpdb->prefix . 'affcd_analytics',
            'rate_limiting'      => $wpdb->prefix . 'affcd_rate_limiting',
            'security_logs'      => $wpdb->prefix . 'affcd_security_logs',
            'fraud_detection'    => $wpdb->prefix . 'affcd_fraud_detection',
            'usage_tracking'     => $wpdb->prefix . 'affcd_usage_tracking',
            'api_audit_logs'     => $wpdb->prefix . 'affcd_api_audit_logs',
            'performance_metrics'=> $wpdb->prefix . 'affcd_performance_metrics'
        ];

        $this->migration_log_table = $wpdb->prefix . 'affcd_migrations';
    }

    /**
     * Initialize database manager
     *
     * @return bool Success status
     */
    public function init() {
        $this->create_migration_log_table();
        return $this->create_tables();
    }

    /**
     * Create migration log table
     */
    private function create_migration_log_table() {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS {$this->migration_log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            migration_version varchar(20) NOT NULL,
            migration_name varchar(255) NOT NULL,
            direction enum('up','down') NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            status enum('running','completed','failed') DEFAULT 'running',
            error_message text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_migration (migration_version, direction),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create all required tables
     *
     * @return bool Success status
     */
    public function create_tables() {
        $success = true;

        try {
            $this->create_vanity_codes_table();
            $this->create_vanity_usage_table();
            $this->create_authorized_domains_table(); // fixed method name
            $this->create_analytics_table();
            $this->create_rate_limiting_table();
            $this->create_security_logs_table();
            $this->create_fraud_detection_table();
            $this->create_usage_tracking_table();
            $this->create_api_audit_logs_table();
            $this->create_performance_metrics_table();
            $this->add_indexes();
            $this->add_foreign_keys();

            update_option('affcd_db_version', $this->db_version);
            
        } catch (Exception $e) {
            error_log('AFFCD Database Manager: Table creation failed - ' . $e->getMessage());
            $success = false;
        }

        return $success;
    }

    /**
     * Create vanity codes table
     */
    private function create_vanity_codes_table() {
        global $wpdb;
        
        $table_name = $this->tables['vanity_codes'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            affiliate_id bigint(20) unsigned NOT NULL,
            status enum('active','inactive','expired','suspended') DEFAULT 'active',
            usage_count bigint(20) unsigned DEFAULT 0,
            conversion_count bigint(20) unsigned DEFAULT 0,
            revenue_generated decimal(15,4) DEFAULT 0.0000,
            currency varchar(3) DEFAULT 'GBP',
            commission_rate decimal(5,4) DEFAULT 0.0000,
            commission_type enum('percentage','fixed') DEFAULT 'percentage',
            starts_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            last_used_at datetime DEFAULT NULL,
            last_conversion_at datetime DEFAULT NULL,
            description text DEFAULT NULL,
            target_url varchar(2000) DEFAULT NULL,
            redirect_count bigint(20) unsigned DEFAULT 0,
            metadata longtext DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_code (code),
            KEY idx_affiliate_id (affiliate_id),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at),
            KEY idx_performance (status, usage_count DESC, revenue_generated DESC),
            KEY idx_activity (last_used_at, status),
            KEY idx_conversion_analysis (conversion_count DESC, revenue_generated DESC),
            CONSTRAINT fk_vanity_affiliate FOREIGN KEY (affiliate_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
            CONSTRAINT fk_vanity_creator FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create vanity usage tracking table
     */
    private function create_vanity_usage_table() {
        global $wpdb;
        
        $table_name = $this->tables['vanity_usage'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vanity_code_id bigint(20) unsigned NOT NULL,
            domain varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            referrer varchar(2000) DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            validation_result enum('valid','invalid','expired','suspended','rate_limited') NOT NULL,
            conversion_occurred tinyint(1) DEFAULT 0,
            conversion_value decimal(15,4) DEFAULT NULL,
            conversion_currency varchar(3) DEFAULT 'GBP',
            commission_earned decimal(15,4) DEFAULT NULL,
            tracking_data longtext DEFAULT NULL,
            utm_source varchar(255) DEFAULT NULL,
            utm_medium varchar(255) DEFAULT NULL,
            utm_campaign varchar(255) DEFAULT NULL,
            utm_term varchar(255) DEFAULT NULL,
            utm_content varchar(255) DEFAULT NULL,
            device_type varchar(50) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            operating_system varchar(100) DEFAULT NULL,
            country_code varchar(2) DEFAULT NULL,
            region varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vanity_code_id (vanity_code_id),
            KEY idx_domain (domain),
            KEY idx_ip_address (ip_address),
            KEY idx_validation_result (validation_result),
            KEY idx_conversion (conversion_occurred, conversion_value),
            KEY idx_session (session_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_analytics (vanity_code_id, validation_result, created_at),
            KEY idx_utm_tracking (utm_source, utm_medium, utm_campaign),
            KEY idx_geographic (country_code, region, city),
            KEY idx_device_analytics (device_type, browser, operating_system),
            CONSTRAINT fk_usage_vanity_code FOREIGN KEY (vanity_code_id) REFERENCES {$this->tables['vanity_codes']}(id) ON DELETE CASCADE,
            CONSTRAINT fk_usage_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create authorized domains table (fixed method name)
     */
    private function create_authorized_domains_table() {
        global $wpdb;
        
        $table_name = $this->tables['authorized_domains'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain_url varchar(500) NOT NULL,
            domain_name varchar(255) DEFAULT NULL,
            api_key varchar(100) NOT NULL,
            api_secret varchar(128) NOT NULL,
            status enum('active','inactive','suspended','pending') DEFAULT 'pending',
            verification_status enum('verified','unverified','failed') DEFAULT 'unverified',
            verification_token varchar(100) DEFAULT NULL,
            verification_method enum('file','dns','meta_tag','api') DEFAULT 'file',
            max_daily_requests int unsigned DEFAULT 10000,
            current_daily_requests int unsigned DEFAULT 0,
            daily_reset_at datetime DEFAULT NULL,
            rate_limit_per_minute int unsigned DEFAULT 100,
            rate_limit_per_hour int unsigned DEFAULT 1000,
            allowed_endpoints text DEFAULT NULL,
            blocked_endpoints text DEFAULT NULL,
            allowed_ips text DEFAULT NULL,
            blocked_ips text DEFAULT NULL,
            security_level enum('low','medium','high','strict') DEFAULT 'medium',
            require_https tinyint(1) DEFAULT 1,
            webhook_url varchar(500) DEFAULT NULL,
            webhook_secret varchar(128) DEFAULT NULL,
            webhook_events text DEFAULT NULL,
            webhook_last_sent datetime DEFAULT NULL,
            webhook_failures int unsigned DEFAULT 0,
            total_requests bigint(20) unsigned DEFAULT 0,
            blocked_requests bigint(20) unsigned DEFAULT 0,
            error_requests bigint(20) unsigned DEFAULT 0,
            last_request_at datetime DEFAULT NULL,
            last_verified_at datetime DEFAULT NULL,
            verification_failures int unsigned DEFAULT 0,
            statistics longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            owner_user_id bigint(20) unsigned DEFAULT NULL,
            owner_email varchar(255) DEFAULT NULL,
            owner_name varchar(255) DEFAULT NULL,
            contact_email varchar(255) DEFAULT NULL,
            contact_phone varchar(50) DEFAULT NULL,
            timezone varchar(50) DEFAULT 'UTC',
            language varchar(10) DEFAULT 'en_GB',
            notes text DEFAULT NULL,
            tags text DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            suspended_at datetime DEFAULT NULL,
            suspended_reason text DEFAULT NULL,
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
            CONSTRAINT fk_domain_owner FOREIGN KEY (owner_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_domain_creator FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_domain_suspender FOREIGN KEY (suspended_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create analytics table
     */
    private function create_analytics_table() {
        global $wpdb;
        
        $table_name = $this->tables['analytics'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned DEFAULT NULL,
            domain varchar(255) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            referrer varchar(2000) DEFAULT NULL,
            event_data longtext DEFAULT NULL,
            revenue_impact decimal(15,4) DEFAULT NULL,
            conversion_funnel_stage varchar(100) DEFAULT NULL,
            ab_test_variant varchar(50) DEFAULT NULL,
            geographic_data json DEFAULT NULL,
            device_fingerprint varchar(255) DEFAULT NULL,
            performance_metrics json DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_domain (domain),
            KEY idx_user_id (user_id),
            KEY idx_session_id (session_id),
            KEY idx_ip_address (ip_address),
            KEY idx_created_at (created_at),
            KEY idx_analytics_overview (event_type, entity_type, created_at),
            KEY idx_domain_analytics (domain, event_type, created_at),
            KEY idx_revenue_analytics (revenue_impact DESC, created_at),
            KEY idx_funnel_analytics (conversion_funnel_stage, event_type, created_at),
            CONSTRAINT fk_analytics_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create rate limiting table
     */
    private function create_rate_limiting_table() {
        global $wpdb;
        
        $table_name = $this->tables['rate_limiting'];

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
            warning_sent tinyint(1) DEFAULT 0,
            escalation_level tinyint unsigned DEFAULT 0,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_rate_limit (identifier, action_type, window_start),
            KEY idx_identifier (identifier),
            KEY idx_action_type (action_type),
            KEY idx_window_period (window_start, window_end),
            KEY idx_blocked_status (is_blocked, block_until),
            KEY idx_rate_check (identifier, action_type, window_start, is_blocked),
            KEY idx_escalation (escalation_level, is_blocked, created_at)
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create security logs table
     */
    private function create_security_logs_table() {
        global $wpdb;
        
        $table_name = $this->tables['security_logs'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            severity enum('low','medium','high','critical') DEFAULT 'medium',
            identifier varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            domain varchar(255) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            endpoint varchar(255) DEFAULT NULL,
            request_method varchar(10) DEFAULT NULL,
            response_code int unsigned DEFAULT NULL,
            threat_score tinyint unsigned DEFAULT 0,
            attack_vectors text DEFAULT NULL,
            blocked_action varchar(255) DEFAULT NULL,
            event_data longtext DEFAULT NULL,
            response_action varchar(255) DEFAULT NULL,
            investigation_status enum('pending','investigating','resolved','false_positive') DEFAULT 'pending',
            assigned_to bigint(20) unsigned DEFAULT NULL,
            resolved_by bigint(20) unsigned DEFAULT NULL,
            resolution_notes text DEFAULT NULL,
            follow_up_required tinyint(1) DEFAULT 0,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_severity (severity),
            KEY idx_identifier (identifier),
            KEY idx_ip_address (ip_address),
            KEY idx_domain (domain),
            KEY idx_user_id (user_id),
            KEY idx_threat_score (threat_score),
            KEY idx_created_at (created_at),
            KEY idx_investigation (investigation_status, severity, created_at),
            KEY idx_security_overview (event_type, severity, created_at),
            KEY idx_threat_analysis (threat_score DESC, severity, created_at),
            CONSTRAINT fk_security_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_security_assigned FOREIGN KEY (assigned_to) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_security_resolver FOREIGN KEY (resolved_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create fraud detection table
     */
    private function create_fraud_detection_table() {
        global $wpdb;
        
        $table_name = $this->tables['fraud_detection'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            fraud_type varchar(100) NOT NULL,
            risk_score int unsigned DEFAULT 0,
            confidence_level decimal(3,2) DEFAULT 0.00,
            detection_count int unsigned DEFAULT 1,
            detection_algorithm varchar(100) DEFAULT NULL,
            related_events json DEFAULT NULL,
            financial_impact decimal(15,4) DEFAULT NULL,
            affected_affiliates text DEFAULT NULL,
            mitigation_actions text DEFAULT NULL,
            first_detected datetime DEFAULT CURRENT_TIMESTAMP,
            last_detected datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            auto_blocked tinyint(1) DEFAULT 0,
            manual_review_required tinyint(1) DEFAULT 0,
            actions_taken longtext DEFAULT NULL,
            investigation_notes text DEFAULT NULL,
            false_positive tinyint(1) DEFAULT 0,
            resolved_at datetime DEFAULT NULL,
            resolved_by bigint(20) unsigned DEFAULT NULL,
            resolution_method varchar(100) DEFAULT NULL,
            prevention_measures text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_fraud_detection (identifier, fraud_type),
            KEY idx_fraud_type (fraud_type),
            KEY idx_risk_score (risk_score),
            KEY idx_confidence_level (confidence_level),
            KEY idx_first_detected (first_detected),
            KEY idx_last_detected (last_detected),
            KEY idx_is_active (is_active),
            KEY idx_manual_review (manual_review_required),
            KEY idx_fraud_analysis (fraud_type, risk_score, is_active),
            KEY idx_financial_impact (financial_impact DESC, created_at),
            CONSTRAINT fk_fraud_resolver FOREIGN KEY (resolved_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create usage tracking table
     */
    private function create_usage_tracking_table() {
        global $wpdb;
        
        $table_name = $this->tables['usage_tracking'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain_from varchar(255) NOT NULL,
            domain_to varchar(255) DEFAULT NULL,
            affiliate_code varchar(100) DEFAULT NULL,
            event_type varchar(100) NOT NULL,
            status enum('success','failed','pending','timeout') DEFAULT 'success',
            session_id varchar(100) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            referrer varchar(2000) DEFAULT NULL,
            request_data longtext DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            processing_time_ms int unsigned DEFAULT NULL,
            conversion_value decimal(15,4) DEFAULT NULL,
            currency varchar(3) DEFAULT 'GBP',
            commission_rate decimal(5,4) DEFAULT NULL,
            commission_amount decimal(15,4) DEFAULT NULL,
            utm_parameters json DEFAULT NULL,
            device_info json DEFAULT NULL,
            geographic_info json DEFAULT NULL,
            error_code varchar(50) DEFAULT NULL,
            error_message text DEFAULT NULL,
            retry_count tinyint unsigned DEFAULT 0,
            webhook_sent tinyint(1) DEFAULT 0,
            webhook_response_code int unsigned DEFAULT NULL,
            api_version varchar(10) DEFAULT '1.0',
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_domain_from (domain_from),
            KEY idx_affiliate_code (affiliate_code),
            KEY idx_event_type (event_type),
            KEY idx_status (status),
            KEY idx_session_id (session_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_performance_analytics (domain_from, event_type, created_at, conversion_value),
            KEY idx_affiliate_performance (affiliate_code, event_type, status, created_at),
            KEY idx_user_behavior (session_id, event_type, created_at),
            KEY idx_revenue_tracking (event_type, conversion_value, currency, created_at),
            KEY idx_error_analysis (error_code, domain_from, created_at),
            CONSTRAINT fk_tracking_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create API audit logs table
     */
    private function create_api_audit_logs_table() {
        global $wpdb;
        
        $table_name = $this->tables['api_audit_logs'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_id varchar(100) NOT NULL,
            api_key varchar(100) DEFAULT NULL,
            domain varchar(255) DEFAULT NULL,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            request_headers longtext DEFAULT NULL,
            request_body longtext DEFAULT NULL,
            response_code int unsigned NOT NULL,
            response_headers longtext DEFAULT NULL,
            response_body longtext DEFAULT NULL,
            processing_time_ms int unsigned DEFAULT NULL,
            memory_usage_mb decimal(8,2) DEFAULT NULL,
            rate_limit_remaining int unsigned DEFAULT NULL,
            authentication_method varchar(50) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            error_details text DEFAULT NULL,
            business_context varchar(255) DEFAULT NULL,
            compliance_flags text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_request_id (request_id),
            KEY idx_api_key (api_key),
            KEY idx_domain (domain),
            KEY idx_endpoint (endpoint),
            KEY idx_method (method),
            KEY idx_response_code (response_code),
            KEY idx_ip_address (ip_address),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_performance_audit (endpoint, processing_time_ms, created_at),
            KEY idx_error_audit (response_code, created_at)
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create performance metrics table
     */
    private function create_performance_metrics_table() {
        global $wpdb;
        
        $table_name = $this->tables['performance_metrics'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_type varchar(100) NOT NULL,
            metric_name varchar(255) NOT NULL,
            metric_value decimal(15,4) NOT NULL,
            metric_unit varchar(50) DEFAULT NULL,
            aggregation_period enum('minute','hour','day','week','month') NOT NULL,
            period_start datetime NOT NULL,
            period_end datetime NOT NULL,
            domain varchar(255) DEFAULT NULL,
            entity_type varchar(50) DEFAULT NULL,
            entity_id bigint(20) unsigned DEFAULT NULL,
            baseline_value decimal(15,4) DEFAULT NULL,
            variance_percentage decimal(5,2) DEFAULT NULL,
            threshold_breached tinyint(1) DEFAULT 0,
            alert_sent tinyint(1) DEFAULT 0,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_metric_period (metric_type, metric_name, aggregation_period, period_start, domain),
            KEY idx_metric_type (metric_type),
            KEY idx_metric_name (metric_name),
            KEY idx_aggregation_period (aggregation_period),
            KEY idx_period_range (period_start, period_end),
            KEY idx_domain (domain),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_threshold_breached (threshold_breached),
            KEY idx_performance_overview (metric_type, aggregation_period, period_start),
            KEY idx_domain_performance (domain, metric_type, period_start)
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add performance-optimized indexes
     */
    private function add_indexes() {
        global $wpdb;
        
        $indexes = [
            // Vanity codes performance indexes
            "CREATE INDEX IF NOT EXISTS idx_vanity_performance ON {$this->tables['vanity_codes']} (status, usage_count DESC, revenue_generated DESC)",
            "CREATE INDEX IF NOT EXISTS idx_vanity_expiry ON {$this->tables['vanity_codes']} (expires_at, status)",
            "CREATE INDEX IF NOT EXISTS idx_vanity_commission ON {$this->tables['vanity_codes']} (commission_type, commission_rate, affiliate_id)",
            
            // Usage tracking indexes
            "CREATE INDEX IF NOT EXISTS idx_usage_analytics ON {$this->tables['vanity_usage']} (vanity_code_id, validation_result, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_conversion_tracking ON {$this->tables['vanity_usage']} (conversion_occurred, conversion_value, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_geographic_analysis ON {$this->tables['vanity_usage']} (country_code, region, city, created_at)",
            
            // Domain management indexes (fixed table key)
            "CREATE INDEX IF NOT EXISTS idx_domain_activity ON {$this->tables['authorized_domains']} (last_activity_at DESC, status)",
            "CREATE INDEX IF NOT EXISTS idx_domain_verification ON {$this->tables['authorized_domains']} (verification_status, last_verified_at)",
            "CREATE INDEX IF NOT EXISTS idx_domain_security ON {$this->tables['authorized_domains']} (security_level, status, verification_status)",
            
            // Analytics reporting indexes
            "CREATE INDEX IF NOT EXISTS idx_analytics_reporting ON {$this->tables['analytics']} (event_type, domain, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_analytics_entity ON {$this->tables['analytics']} (entity_type, entity_id, event_type, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_analytics_revenue ON {$this->tables['analytics']} (revenue_impact DESC, event_type, created_at)",
            
            // Security monitoring indexes
            "CREATE INDEX IF NOT EXISTS idx_security_monitoring ON {$this->tables['security_logs']} (severity, investigation_status, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_security_threats ON {$this->tables['security_logs']} (threat_score DESC, event_type, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_fraud_monitoring ON {$this->tables['fraud_detection']} (is_active, risk_score DESC, last_detected DESC)",
            "CREATE INDEX IF NOT EXISTS idx_fraud_financial ON {$this->tables['fraud_detection']} (financial_impact DESC, fraud_type, created_at)",
            
            // Performance optimization indexes
            "CREATE INDEX IF NOT EXISTS idx_api_performance ON {$this->tables['api_audit_logs']} (endpoint, processing_time_ms, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_metrics_trending ON {$this->tables['performance_metrics']} (metric_type, metric_name, period_start DESC)"
        ];

        foreach ($indexes as $index_sql) {
            $wpdb->query($index_sql);
        }
    }

    /**
     * Add foreign key constraints
     */
    private function add_foreign_keys() {
        // Foreign keys are already defined in table creation
        // This method is for future constraint additions
    }

    /**
     * Update database tables if needed
     *
     * @param string $from_version Previous version
     * @param string $to_version Target version
     */
    public function update_tables($from_version, $to_version) {
        $this->log_migration($from_version, $to_version, 'up', 'start');

        try {
            if (version_compare($from_version, '1.0.0', '<')) {
                $this->create_tables();
            }

            $this->run_migrations($from_version, $to_version);
            
            update_option('affcd_db_version', $to_version);
            $this->log_migration($from_version, $to_version, 'up', 'completed');
            
        } catch (Exception $e) {
            $this->log_migration($from_version, $to_version, 'up', 'failed', $e->getMessage());
            throw $e;
        }
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
            '1.1.0' => 'migration_1_1_0',
            '1.2.0' => 'migration_1_2_0'
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
     * Migration for version 1.0.1
     */
    private function migration_1_0_1() {
        global $wpdb;
        
        // Add new columns for enhanced tracking
        $wpdb->query("ALTER TABLE {$this->tables['vanity_usage']} 
                     ADD COLUMN IF NOT EXISTS device_type varchar(50) DEFAULT NULL AFTER utm_content");
        
        $wpdb->query("ALTER TABLE {$this->tables['vanity_usage']} 
                     ADD COLUMN IF NOT EXISTS browser varchar(100) DEFAULT NULL AFTER device_type");
    }

    /**
     * Migration for version 1.1.0
     */
    private function migration_1_1_0() {
        global $wpdb;
        
        // Add performance tracking columns
        $wpdb->query("ALTER TABLE {$this->tables['api_audit_logs']} 
                     ADD COLUMN IF NOT EXISTS memory_usage_mb decimal(8,2) DEFAULT NULL AFTER processing_time_ms");
        
        // Add compliance tracking
        $wpdb->query("ALTER TABLE {$this->tables['api_audit_logs']} 
                     ADD COLUMN IF NOT EXISTS compliance_flags text DEFAULT NULL AFTER business_context");
    }

    /**
     * Migration for version 1.2.0
     */
    private function migration_1_2_0() {
        global $wpdb;
        
        // Add enhanced fraud detection
        $wpdb->query("ALTER TABLE {$this->tables['fraud_detection']} 
                     ADD COLUMN IF NOT EXISTS detection_algorithm varchar(100) DEFAULT NULL AFTER detection_count");
        
        $wpdb->query("ALTER TABLE {$this->tables['fraud_detection']} 
                     ADD COLUMN IF NOT EXISTS confidence_level decimal(3,2) DEFAULT 0.00 AFTER risk_score");
    }

    /**
     * Log migration activity
     *
     * @param string $from_version
     * @param string $to_version
     * @param string $direction
     * @param string $status
     * @param string $error_message
     */
    private function log_migration($from_version, $to_version, $direction, $status, $error_message = null) {
        global $wpdb;

        $data = [
            'migration_version' => $to_version,
            'migration_name' => "Database migration from {$from_version} to {$to_version}",
            'direction' => $direction,
            'status' => $status,
            'error_message' => $error_message,
            'metadata' => json_encode([
                'from_version' => $from_version,
                'to_version' => $to_version,
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo('version'),
                'mysql_version' => $wpdb->db_version()
            ])
        ];

        if ($status === 'start') {
            $data['start_time'] = current_time('mysql');
        } elseif (in_array($status, ['completed', 'failed'], true)) {
            $data['end_time'] = current_time('mysql');
        }

        $wpdb->insert($this->migration_log_table, $data);
    }

    /**
     * Drop all tables
     */
    public function drop_tables() {
        global $wpdb;
        
        // Disable foreign key checks
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Drop in reverse order to handle dependencies
        $tables_to_drop = array_reverse($this->tables);
        
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // Drop migration log table
        $wpdb->query("DROP TABLE IF EXISTS {$this->migration_log_table}");
        
        // Re-enable foreign key checks
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
        
        delete_option('affcd_db_version');
    }

    /**
     * Get table name
     *
     * @param string $table Table identifier
     * @return string|null Table name or null if not found
     */
    public function get_table_name($table) {
        return $this->tables[$table] ?? null;
    }

    /**
     * Get all table names
     *
     * @return array All table names
     */
    public function get_all_table_names() {
        return $this->tables;
    }

    /**
     * Check if table exists
     *
     * @param string $table Table identifier
     * @return bool True if table exists
     */
    public function table_exists($table) {
        global $wpdb;
        
        $table_name = $this->get_table_name($table);
        if (!$table_name) {
            return false;
        }
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));
        
        return (int) $result > 0;
    }

    /**
     * Get database version
     *
     * @return string Current database version
     */
    public function get_version() {
        return get_option('affcd_db_version', '0.0.0');
    }

    /**
     * Check if database needs upgrade
     *
     * @return bool True if upgrade needed
     */
    public function needs_upgrade() {
        return version_compare($this->get_version(), $this->db_version, '<');
    }

    /**
     * optimize all tables
     */
    public function optimize_tables() {
        global $wpdb;
        
        foreach ($this->tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
            $wpdb->query("ANALYZE TABLE {$table}");
        }
    }

    /**
     * Get table statistics
     *
     * @return array Table statistics
     */
    public function get_table_statistics() {
        global $wpdb;
        
        $stats = [];
        
        foreach ($this->tables as $key => $table) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as row_count,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
                    ROUND((data_free / 1024 / 1024), 2) as free_mb
                FROM information_schema.tables 
                WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table
            ));
            
            $stats[$key] = [
                'table_name' => $table,
                'row_count'  => intval($result->row_count ?? 0),
                'size_mb'    => floatval($result->size_mb ?? 0),
                'free_mb'    => floatval($result->free_mb ?? 0)
            ];
        }
        
        return $stats;
    }

    /**
     * Clean up old data based on retention policies
     *
     * @param array $retention_days Retention days per table
     */
    public function cleanup_old_data($retention_days = []) {
        global $wpdb;
        
        $default_retention = [
            'analytics'          => 365,
            'security_logs'      => 90,
            'api_audit_logs'     => 30,
            'performance_metrics'=> 90,
            'vanity_usage'       => 730
        ];
        
        $retention_days = array_merge($default_retention, $retention_days);
        
        foreach ($retention_days as $table_key => $days) {
            if (isset($this->tables[$table_key])) {
                $table = $this->tables[$table_key];
                $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                
                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table} WHERE created_at < %s",
                    $cutoff_date
                ));
                
                if ($deleted !== false) {
                    error_log("AFFCD Database Manager: Cleaned {$deleted} old records from {$table_key}");
                }
            }
        }
    }

    /**
     * Backup critical tables
     *
     * @param string $backup_dir Backup directory
     * @return bool Success status
     */
    public function backup_tables($backup_dir = null) {
        if (!$backup_dir) {
            $upload_dir = wp_upload_dir();
            $backup_dir = rtrim($upload_dir['basedir'] ?? WP_CONTENT_DIR, '/').'/affcd-backups';
        }
        
        if (!wp_mkdir_p($backup_dir)) {
            return false;
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $backup_dir . "/affcd_backup_{$timestamp}.sql";
        
        // Placeholder for real backup logic.
        return (bool) $backup_file;
    }

    /**
     * Validate table integrity
     *
     * @return array Validation results
     */
    public function validate_table_integrity() {
        global $wpdb;
        
        $results = [];
        
        foreach ($this->tables as $key => $table) {
            $check_result = $wpdb->get_row("CHECK TABLE {$table}");
            $msg = $check_result->Msg_text ?? 'Unknown';
            $results[$key] = [
                'table' => $table,
                'status' => $msg,
                'valid' => ($msg === 'OK')
            ];
        }
        
        return $results;
    }

    /**
     * Get migration history
     *
     * @param int $limit Number of records to retrieve
     * @return array Migration history
     */
    public function get_migration_history($limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->migration_log_table} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Reset database to initial state
     *
     * @return bool Success status
     */
    public function reset_database() {
        $this->drop_tables();
        return $this->create_tables();
    }
}
