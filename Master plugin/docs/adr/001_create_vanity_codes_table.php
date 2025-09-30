<?php
/**
 * Database Migration: Create Vanity Codes Table
 * 
 * Plugin: Affiliate Cross Domain System (Master)
 * File: /wp-content/plugins/affiliate-cross-domain-system/database/migrations/001_create_vanity_codes_table.php
 * 
 * Creates the vanity codes table with proper indexes and constraints
 * for optimal performance and data integrity.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Migration_001_Create_Vanity_Codes_Table {

    private $table_name;
    private $usage_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'affcd_vanity_codes';
        $this->usage_table = $wpdb->prefix . 'affcd_usage_tracking';
    }

    /**
     * Execute migration
     */
    public function up() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create vanity codes table
        $sql_vanity_codes = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vanity_code varchar(100) NOT NULL,
            affiliate_id bigint(20) unsigned NOT NULL,
            affiliate_code varchar(255) NOT NULL,
            description text DEFAULT NULL,
            usage_count bigint(20) unsigned DEFAULT 0,
            conversion_count bigint(20) unsigned DEFAULT 0,
            revenue_generated decimal(15,4) DEFAULT 0.0000,
            click_count bigint(20) unsigned DEFAULT 0,
            unique_clicks bigint(20) unsigned DEFAULT 0,
            last_used_at datetime DEFAULT NULL,
            status enum('active','inactive','expired','suspended','draft') DEFAULT 'active',
            expires_at datetime DEFAULT NULL,
            usage_limit bigint(20) unsigned DEFAULT NULL,
            conversion_limit bigint(20) unsigned DEFAULT NULL,
            target_url text DEFAULT NULL,
            tracking_parameters longtext DEFAULT NULL,
            geographic_restrictions longtext DEFAULT NULL,
            device_restrictions longtext DEFAULT NULL,
            time_restrictions longtext DEFAULT NULL,
            custom_fields longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_vanity_code (vanity_code),
            KEY idx_affiliate_id (affiliate_id),
            KEY idx_affiliate_code (affiliate_code),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at),
            KEY idx_created_at (created_at),
            KEY idx_last_used (last_used_at),
            KEY idx_created_by (created_by),
            KEY idx_active_codes (status, expires_at),
            KEY idx_performance (usage_count, conversion_count, revenue_generated),
            FULLTEXT KEY ft_search (vanity_code, description)
        ) $charset_collate;";

        // Create usage tracking table
        $sql_usage_tracking = "CREATE TABLE {$this->usage_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vanity_code_id bigint(20) unsigned NOT NULL,
            session_id varchar(100) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            domain varchar(255) NOT NULL,
            source_url varchar(500) DEFAULT NULL,
            target_url varchar(500) DEFAULT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            referrer varchar(500) DEFAULT NULL,
            utm_source varchar(100) DEFAULT NULL,
            utm_medium varchar(100) DEFAULT NULL,
            utm_campaign varchar(100) DEFAULT NULL,
            utm_term varchar(100) DEFAULT NULL,
            utm_content varchar(100) DEFAULT NULL,
            device_type enum('desktop','mobile','tablet','unknown') DEFAULT 'unknown',
            browser varchar(100) DEFAULT NULL,
            operating_system varchar(100) DEFAULT NULL,
            country_code varchar(2) DEFAULT NULL,
            region varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            latitude decimal(10,8) DEFAULT NULL,
            longitude decimal(11,8) DEFAULT NULL,
            converted tinyint(1) DEFAULT 0,
            conversion_value decimal(15,4) DEFAULT 0.0000,
            conversion_currency varchar(3) DEFAULT 'USD',
            conversion_type varchar(50) DEFAULT NULL,
            conversion_details longtext DEFAULT NULL,
            time_to_conversion int unsigned DEFAULT NULL,
            is_unique_click tinyint(1) DEFAULT 1,
            is_bot tinyint(1) DEFAULT 0,
            fraud_score tinyint unsigned DEFAULT 0,
            custom_data longtext DEFAULT NULL,
            tracked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vanity_code_id (vanity_code_id),
            KEY idx_domain (domain),
            KEY idx_user_ip (user_ip),
            KEY idx_tracked_at (tracked_at),
            KEY idx_converted (converted),
            KEY idx_session_id (session_id),
            KEY idx_user_id (user_id),
            KEY idx_device_type (device_type),
            KEY idx_country_code (country_code),
            KEY idx_is_unique (is_unique_click),
            KEY idx_is_bot (is_bot),
            KEY idx_fraud_score (fraud_score),
            KEY idx_utm_source (utm_source),
            KEY idx_conversion_value (conversion_value),
            KEY idx_performance_analysis (vanity_code_id, tracked_at, converted, conversion_value),
            KEY idx_geographic_analysis (country_code, region, city),
            KEY idx_traffic_source (utm_source, utm_medium, utm_campaign),
            FOREIGN KEY fk_vanity_code (vanity_code_id) REFERENCES {$this->table_name}(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Execute table creation
        $result_vanity = dbDelta($sql_vanity_codes);
        $result_usage = dbDelta($sql_usage_tracking);
        
        // Create additional indexes for performance
        $this->create_additional_indexes();
        
        // Insert default data if needed
        $this->insert_default_data();
        
        // Log migration
        $this->log_migration('up', [
            'vanity_table_result' => $result_vanity,
            'usage_table_result' => $result_usage
        ]);
        
        return true;
    }

    /**
     * Rollback migration
     */
    public function down() {
        global $wpdb;
        
        // Drop foreign key constraints first
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Drop tables
        $wpdb->query("DROP TABLE IF EXISTS {$this->usage_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        
        // Re-enable foreign key checks
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
        
        // Log rollback
        $this->log_migration('down', [
            'tables_dropped' => [$this->table_name, $this->usage_table]
        ]);
        
        return true;
    }

    /**
     * Create additional indexes for performance optimization
     */
    private function create_additional_indexes() {
        global $wpdb;
        
        // Composite indexes for common queries
        $additional_indexes = [
            // Vanity codes table
            "CREATE INDEX idx_status_expires ON {$this->table_name} (status, expires_at, affiliate_id)",
            "CREATE INDEX idx_affiliate_performance ON {$this->table_name} (affiliate_id, status, usage_count DESC)",
            "CREATE INDEX idx_revenue_ranking ON {$this->table_name} (revenue_generated DESC, conversion_count DESC)",
            "CREATE INDEX idx_recent_activity ON {$this->table_name} (last_used_at DESC, status)",
            
            // Usage tracking table
            "CREATE INDEX idx_conversion_analysis ON {$this->usage_table} (converted, tracked_at, conversion_value)",
            "CREATE INDEX idx_traffic_analysis ON {$this->usage_table} (domain, tracked_at, device_type)",
            "CREATE INDEX idx_geographic_performance ON {$this->usage_table} (country_code, converted, conversion_value)",
            "CREATE INDEX idx_fraud_detection ON {$this->usage_table} (user_ip, is_bot, fraud_score, tracked_at)",
            "CREATE INDEX idx_session_tracking ON {$this->usage_table} (session_id, tracked_at, vanity_code_id)",
            "CREATE INDEX idx_referrer_analysis ON {$this->usage_table} (utm_source, utm_medium, converted)"
        ];
        
        foreach ($additional_indexes as $index_sql) {
            $wpdb->query($index_sql);
        }
    }

    /**
     * Insert default data
     */
    private function insert_default_data() {
        global $wpdb;
        
        // Check if we should insert demo data
        $insert_demo = get_option('affcd_insert_demo_data', false);
        
        if (!$insert_demo) {
            return;
        }
        
        // Insert demo vanity codes
        $demo_codes = [
            [
                'vanity_code' => 'WELCOME10',
                'affiliate_id' => 1,
                'affiliate_code' => 'affiliate_001',
                'description' => 'Welcome discount code - 10% off',
                'status' => 'active',
                'created_by' => 1,
                'target_url' => home_url('/special-offer'),
                'custom_fields' => json_encode(['discount_type' => 'percentage', 'discount_value' => 10])
            ],
            [
                'vanity_code' => 'SAVE25',
                'affiliate_id' => 1,
                'affiliate_code' => 'affiliate_001',
                'description' => 'Limited time offer - $25 off',
                'status' => 'active',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'usage_limit' => 100,
                'created_by' => 1,
                'custom_fields' => json_encode(['discount_type' => 'fixed', 'discount_value' => 25])
            ],
            [
                'vanity_code' => 'EARLYBIRD',
                'affiliate_id' => 2,
                'affiliate_code' => 'affiliate_002',
                'description' => 'Early bird special pricing',
                'status' => 'active',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
                'created_by' => 1,
                'time_restrictions' => json_encode([
                    'valid_hours' => ['start' => '09:00', 'end' => '17:00'],
                    'valid_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
                ])
            ]
        ];
        
        foreach ($demo_codes as $demo_code) {
            $wpdb->insert($this->table_name, $demo_code);
        }
        
        // Mark demo data as inserted
        update_option('affcd_demo_data_inserted', true);
    }

    /**
     * Check if migration is needed
     */
    public function needs_migration() {
        global $wpdb;
        
        // Check if tables exist
        $vanity_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        $usage_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->usage_table}'") === $this->usage_table;
        
        if (!$vanity_table_exists || !$usage_table_exists) {
            return true;
        }
        
        // Check if all required columns exist
        $required_columns = $this->get_required_columns();
        
        foreach ($required_columns as $table => $columns) {
            $table_name = $table === 'vanity' ? $this->table_name : $this->usage_table;
            
            foreach ($columns as $column) {
                $column_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                    DB_NAME, $table_name, $column
                ));
                
                if (!$column_exists) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get required columns for validation
     */
    private function get_required_columns() {
        return [
            'vanity' => [
                'id', 'vanity_code', 'affiliate_id', 'affiliate_code', 'description',
                'usage_count', 'conversion_count', 'revenue_generated', 'status',
                'expires_at', 'created_at', 'updated_at', 'created_by'
            ],
            'usage' => [
                'id', 'vanity_code_id', 'domain', 'user_ip', 'user_agent',
                'converted', 'conversion_value', 'tracked_at', 'device_type',
                'country_code', 'utm_source', 'utm_medium', 'utm_campaign'
            ]
        ];
    }

    /**
     * Validate table structure
     */
    public function validate_structure() {
        global $wpdb;
        
        $errors = [];
        
        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") !== $this->table_name) {
            $errors[] = "Table {$this->table_name} does not exist";
        }
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->usage_table}'") !== $this->usage_table) {
            $errors[] = "Table {$this->usage_table} does not exist";
        }
        
        if (!empty($errors)) {
            return $errors;
        }
        
        // Check primary keys
        $vanity_pk = $wpdb->get_var("SHOW KEYS FROM {$this->table_name} WHERE Key_name = 'PRIMARY'");
        if (!$vanity_pk) {
            $errors[] = "Primary key missing on {$this->table_name}";
        }
        
        // Check unique constraints
        $unique_vanity = $wpdb->get_var("SHOW KEYS FROM {$this->table_name} WHERE Key_name = 'unique_vanity_code'");
        if (!$unique_vanity) {
            $errors[] = "Unique constraint missing on vanity_code";
        }
        
        // Check foreign key constraints
        $foreign_key = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = %s",
            DB_NAME, $this->usage_table, $this->table_name
        ));
        
        if (!$foreign_key) {
            $errors[] = "Foreign key constraint missing between usage and vanity tables";
        }
        
        return $errors;
    }

    /**
     * Optimize tables
     */
    public function optimize_tables() {
        global $wpdb;
        
        // Analyze tables for query optimization
        $wpdb->query("ANALYZE TABLE {$this->table_name}");
        $wpdb->query("ANALYZE TABLE {$this->usage_table}");
        
        // Optimize tables
        $wpdb->query("OPTIMIZE TABLE {$this->table_name}");
        $wpdb->query("OPTIMIZE TABLE {$this->usage_table}");
        
        $this->log_migration('optimize', [
            'tables_optimized' => [$this->table_name, $this->usage_table]
        ]);
    }

    /**
     * Get table statistics
     */
    public function get_table_stats() {
        global $wpdb;
        
        $stats = [];
        
        // Vanity codes statistics
        $vanity_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_codes,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_codes,
                COUNT(CASE WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 1 END) as expired_codes,
                SUM(usage_count) as total_usage,
                SUM(conversion_count) as total_conversions,
                SUM(revenue_generated) as total_revenue,
                AVG(usage_count) as avg_usage_per_code,
                MAX(created_at) as latest_created
             FROM {$this->table_name}"
        ), ARRAY_A);
        
        $stats['vanity_codes'] = $vanity_stats;
        
        // Usage tracking statistics
        $usage_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_tracking_records,
                COUNT(CASE WHEN converted = 1 THEN 1 END) as total_conversions,
                COUNT(DISTINCT user_ip) as unique_visitors,
                COUNT(DISTINCT domain) as unique_domains,
                AVG(conversion_value) as avg_conversion_value,
                COUNT(CASE WHEN tracked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as recent_24h,
                COUNT(CASE WHEN is_bot = 1 THEN 1 END) as bot_traffic
             FROM {$this->usage_table}"
        ), ARRAY_A);
        
        $stats['usage_tracking'] = $usage_stats;
        
        // Table size information
        $table_sizes = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                TABLE_NAME,
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'size_mb',
                TABLE_ROWS as 'row_count'
             FROM INFORMATION_SCHEMA.TABLES 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME IN (%s, %s)",
            DB_NAME, $this->table_name, $this->usage_table
        ), ARRAY_A);
        
        $stats['table_sizes'] = $table_sizes;
        
        return $stats;
    }

    /**
     * Log migration activity
     */
    private function log_migration($action, $data = []) {
        $log_entry = [
            'migration' => '001_create_vanity_codes_table',
            'action' => $action,
            'timestamp' => current_time('mysql'),
            'data' => $data,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version()
        ];
        
        // Store in options table for migration history
        $migration_log = get_option('affcd_migration_log', []);
        $migration_log[] = $log_entry;
        update_option('affcd_migration_log', $migration_log);
        
        // Also log to error log for debugging
        error_log('AFFCD Migration: ' . json_encode($log_entry));
    }

    /**
     * Backup tables before migration
     */
    public function backup_tables() {
        global $wpdb;
        
        $backup_tables = [];
        
        // Check if tables exist before backup
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name) {
            $backup_table = $this->table_name . '_backup_' . date('Y_m_d_H_i_s');
            $wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$this->table_name}");
            $backup_tables[] = $backup_table;
        }
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->usage_table}'") === $this->usage_table) {
            $backup_table = $this->usage_table . '_backup_' . date('Y_m_d_H_i_s');
            $wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$this->usage_table}");
            $backup_tables[] = $backup_table;
        }
        
        return $backup_tables;
    }

    /**
     * Clean up old backup tables
     */
    public function cleanup_old_backups($days_to_keep = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y_m_d', strtotime("-{$days_to_keep} days"));
        
        // Find backup tables older than cutoff date
        $tables = $wpdb->get_col("SHOW TABLES LIKE '%_backup_%'");
        
        foreach ($tables as $table) {
            if (preg_match('/_backup_(\d{4}_\d{2}_\d{2})/', $table, $matches)) {
                if ($matches[1] < $cutoff_date) {
                    $wpdb->query("DROP TABLE IF EXISTS {$table}");
                }
            }
        }
    }

    /**
     * Get migration version
     */
    public function get_version() {
        return '001';
    }

    /**
     * Get migration description
     */
    public function get_description() {
        return 'Create vanity codes and usage tracking tables with comprehensive indexing for performance optimization';
    }

    /**
     * Check migration dependencies
     */
    public function check_dependencies() {
        $dependencies = [];
        
        // Check MySQL version
        global $wpdb;
        $mysql_version = $wpdb->db_version();
        if (version_compare($mysql_version, '5.7.0', '<')) {
            $dependencies[] = "MySQL version 5.7.0 or higher required (current: {$mysql_version})";
        }
        
        // Check if AffiliateWP is active
        if (!function_exists('affwp_get_affiliate')) {
            $dependencies[] = "AffiliateWP plugin must be active";
        }
        
        // Check database privileges
        $required_privileges = ['CREATE', 'ALTER', 'INDEX', 'REFERENCES'];
        foreach ($required_privileges as $privilege) {
            // This is a simplified check - in production you might want more sophisticated privilege checking
            if (!current_user_can('manage_options')) {
                $dependencies[] = "Insufficient database privileges for migration";
                break;
            }
        }
        
        return $dependencies;
    }
}