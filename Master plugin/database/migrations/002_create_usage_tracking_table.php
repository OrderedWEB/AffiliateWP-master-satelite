<?php
/**
 * Usage Tracking Table Migration for Affiliate Cross Domain System
 * 
 * Path: /wp-content/plugins/affiliate-cross-domain-system/database/migrations/002_create_usage_tracking_table.php
 * Plugin: Affiliate Cross Domain System (Master)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Migration_002_Create_Usage_Tracking_Table {

    /**
     * Run the migration
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tracking_id varchar(64) NOT NULL,
            event_type enum('validation','conversion','click','impression','error') NOT NULL,
            affiliate_code varchar(255) NOT NULL,
            domain_from varchar(255) NOT NULL,
            domain_to varchar(255) NULL,
            user_id bigint(20) unsigned NULL,
            session_id varchar(128) NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text NULL,
            referer_url text NULL,
            landing_page text NULL,
            conversion_value decimal(10,2) NULL,
            currency varchar(3) NULL DEFAULT 'USD',
            metadata longtext NULL,
            status enum('pending','processed','failed','cancelled') NOT NULL DEFAULT 'pending',
            response_time_ms int(11) NULL,
            error_code varchar(50) NULL,
            error_message text NULL,
            processed_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_tracking_id (tracking_id),
            KEY idx_event_type (event_type),
            KEY idx_affiliate_code (affiliate_code),
            KEY idx_domain_from (domain_from),
            KEY idx_domain_to (domain_to),
            KEY idx_user_id (user_id),
            KEY idx_session_id (session_id),
            KEY idx_ip_address (ip_address),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_processed_at (processed_at),
            KEY idx_conversion_tracking (event_type, affiliate_code, created_at),
            KEY idx_domain_performance (domain_from, event_type, created_at),
            KEY idx_user_journey (session_id, created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Create partitions for better performance (if MySQL supports it)
        self::create_partitions($table_name);
        
        // Log migration
        error_log("AFFCD Migration 002: Usage tracking table created/updated");
        
        return $result;
    }

    /**
     * Rollback the migration
     */
    public static function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        
        $sql = "DROP TABLE IF EXISTS {$table_name}";
        $result = $wpdb->query($sql);
        
        // Log rollback
        error_log("AFFCD Migration 002: Usage tracking table dropped");
        
        return $result !== false;
    }

    /**
     * Create table partitions for better performance
     */
    private static function create_partitions($table_name) {
        global $wpdb;
        
        // Check if partitioning is supported
        $partition_support = $wpdb->get_var("SELECT 1 FROM INFORMATION_SCHEMA.PLUGINS WHERE PLUGIN_NAME = 'partition'");
        
        if (!$partition_support) {
            return false;
        }
        
        try {
            // Create monthly partitions for the next 12 months
            $current_date = new DateTime();
            $partitions = [];
            
            for ($i = 0; $i < 12; $i++) {
                $partition_date = clone $current_date;
                $partition_date->modify("+{$i} months");
                
                $partition_name = 'p' . $partition_date->format('Ym');
                $partition_value = $partition_date->modify('+1 month')->format('Y-m-01');
                
                $partitions[] = "PARTITION {$partition_name} VALUES LESS THAN (TO_DAYS('{$partition_value}'))";
            }
            
            // Add a future partition for data beyond 12 months
            $partitions[] = "PARTITION p_future VALUES LESS THAN MAXVALUE";
            
            $partition_sql = "ALTER TABLE {$table_name} 
                            PARTITION BY RANGE (TO_DAYS(created_at)) (" .
                            implode(', ', $partitions) . ")";
            
            $wpdb->query($partition_sql);
            
            error_log("AFFCD Migration 002: Table partitions created successfully");
            
        } catch (Exception $e) {
            error_log("AFFCD Migration 002: Failed to create partitions - " . $e->getMessage());
        }
    }

    /**
     * Seed the table with sample data (for development)
     */
    public static function seed() {
        global $wpdb;
        
        // Only seed in development environment
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        
        $sample_data = [
            [
                'tracking_id' => wp_generate_uuid4(),
                'event_type' => 'validation',
                'affiliate_code' => 'DEMO123',
                'domain_from' => 'client1.example.com',
                'domain_to' => 'master.example.com',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'referer_url' => 'https://google.com',
                'landing_page' => 'https://client1.example.com/discount',
                'status' => 'processed',
                'response_time_ms' => 45,
                'metadata' => wp_json_encode(['source' => 'popup', 'campaign' => 'summer2024']),
                'processed_at' => current_time('mysql'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            [
                'tracking_id' => wp_generate_uuid4(),
                'event_type' => 'conversion',
                'affiliate_code' => 'DEMO123',
                'domain_from' => 'client1.example.com',
                'conversion_value' => 150.00,
                'currency' => 'USD',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'status' => 'processed',
                'response_time_ms' => 32,
                'metadata' => wp_json_encode(['order_id' => 'ORD-12345', 'product' => 'Premium Plan']),
                'processed_at' => current_time('mysql'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
            ],
            [
                'tracking_id' => wp_generate_uuid4(),
                'event_type' => 'error',
                'affiliate_code' => 'INVALID456',
                'domain_from' => 'client2.example.com',
                'ip_address' => '192.168.1.101',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'status' => 'failed',
                'response_time_ms' => 15,
                'error_code' => 'INVALID_CODE',
                'error_message' => 'Affiliate code not found or expired',
                'metadata' => wp_json_encode(['retry_count' => 1]),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
            ]
        ];
        
        foreach ($sample_data as $data) {
            $wpdb->insert($table_name, $data);
        }
        
        error_log("AFFCD Migration 002: Sample data seeded successfully");
    }

    /**
     * Add indexes for better query performance
     */
    public static function add_performance_indexes() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        
        $indexes = [
            "CREATE INDEX idx_performance_analytics ON {$table_name} (domain_from, event_type, created_at, conversion_value)",
            "CREATE INDEX idx_affiliate_performance ON {$table_name} (affiliate_code, event_type, status, created_at)",
            "CREATE INDEX idx_user_behavior ON {$table_name} (session_id, event_type, created_at)",
            "CREATE INDEX idx_revenue_tracking ON {$table_name} (event_type, conversion_value, currency, created_at) WHERE event_type = 'conversion'",
            "CREATE INDEX idx_error_analysis ON {$table_name} (error_code, domain_from, created_at) WHERE status = 'failed'"
        ];
        
        foreach ($indexes as $index_sql) {
            try {
                $wpdb->query($index_sql);
            } catch (Exception $e) {
                error_log("AFFCD Migration 002: Failed to create index - " . $e->getMessage());
            }
        }
    }

    /**
     * Optimize table for better performance
     */
    public static function optimize_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        
        // Optimize table structure
        $wpdb->query("OPTIMIZE TABLE {$table_name}");
        
        // Analyze table for better query planning
        $wpdb->query("ANALYZE TABLE {$table_name}");
        
        error_log("AFFCD Migration 002: Table optimized successfully");
    }

    /**
     * Check if migration is needed
     */
    public static function is_migration_needed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        
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
            'version' => '002',
            'name' => 'Create Usage Tracking Table',
            'description' => 'Creates the main usage tracking table for storing affiliate validation events, conversions, and analytics data',
            'dependencies' => ['001'],
            'estimated_time' => '30 seconds',
            'affects_data' => false
        ];
    }

    /**
     * Validate table structure
     */
    public static function validate_table_structure() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        $required_columns = [
            'id', 'tracking_id', 'event_type', 'affiliate_code', 
            'domain_from', 'ip_address', 'status', 'created_at'
        ];
        
        $existing_columns = wp_list_pluck($columns, 'Field');
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (!empty($missing_columns)) {
            error_log("AFFCD Migration 002: Missing columns - " . implode(', ', $missing_columns));
            return false;
        }
        
        // Check indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
        $index_names = wp_list_pluck($indexes, 'Key_name');
        
        $required_indexes = ['PRIMARY', 'unique_tracking_id', 'idx_event_type', 'idx_affiliate_code'];
        $missing_indexes = array_diff($required_indexes, $index_names);
        
        if (!empty($missing_indexes)) {
            error_log("AFFCD Migration 002: Missing indexes - " . implode(', ', $missing_indexes));
            return false;
        }
        
        return true;
    }

    /**
     * Clean up old data (data retention policy)
     */
    public static function cleanup_old_data($retention_days = 365) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Keep conversion data longer, clean up other events
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE created_at < %s 
             AND event_type NOT IN ('conversion') 
             AND status = 'processed'",
            $cutoff_date
        ));
        
        // Clean up very old conversion data (keep for 2 years)
        $conversion_cutoff = date('Y-m-d H:i:s', strtotime('-730 days'));
        $deleted_conversions = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE created_at < %s 
             AND event_type = 'conversion'",
            $conversion_cutoff
        ));
        
        error_log("AFFCD Migration 002: Cleaned up {$deleted} old records and {$deleted_conversions} old conversions");
        
        return $deleted + $deleted_conversions;
    }

    /**
     * Archive old data to separate table
     */
    public static function archive_old_data($archive_days = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        $archive_table = $wpdb->prefix . 'affcd_usage_tracking_archive';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$archive_days} days"));
        
        // Create archive table if it doesn't exist
        $charset_collate = $wpdb->get_charset_collate();
        $archive_sql = "CREATE TABLE IF NOT EXISTS {$archive_table} LIKE {$table_name} {$charset_collate}";
        $wpdb->query($archive_sql);
        
        // Move old data to archive
        $archived = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$archive_table} 
             SELECT * FROM {$table_name} 
             WHERE created_at < %s 
             AND status = 'processed'",
            $cutoff_date
        ));
        
        // Delete archived data from main table
        if ($archived > 0) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_name} 
                 WHERE created_at < %s 
                 AND status = 'processed'",
                $cutoff_date
            ));
        }
        
        error_log("AFFCD Migration 002: Archived {$archived} old records");
        
        return $archived;
    }
}