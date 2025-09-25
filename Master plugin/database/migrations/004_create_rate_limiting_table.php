<?php
/**
 * Migration 004: Create Rate Limiting Table
 *
 * Creates the rate limiting table for tracking API request limits,
 * user action throttling, and security-based rate limiting across
 * all domains in the affiliate cross-domain system.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @subpackage Database\Migrations
 * @version 1.0.0
 * @author Richard King, starneconsulting.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Migration_004_Create_Rate_Limiting_Table {

    /**
     * Migration version
     *
     * @var string
     */
    private $version = '004';

    /**
     * Migration name
     *
     * @var string
     */
    private $name = 'Create Rate Limiting Table';

    /**
     * Table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'affcd_rate_limiting';
    }

    /**
     * Execute migration
     *
     * @return bool Migration success status
     */
    public function up() {
        global $wpdb;

        $this->log_migration('up', 'start');

        try {
            // Create rate limiting table
            $this->create_rate_limiting_table();
            
            // Create additional indexes for performance
            $this->create_additional_indexes();
            
            // Insert default rate limiting rules
            $this->insert_default_rate_limits();
            
            // Create monitoring triggers
            $this->create_monitoring_triggers();
            
            // Log successful migration
            $this->log_migration('up', 'completed', [
                'table_created' => $this->table_name,
                'indexes_created' => $this->get_created_indexes(),
                'default_rules_inserted' => $this->get_default_rules_count()
            ]);

            return true;

        } catch (Exception $e) {
            $this->log_migration('up', 'failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Attempt rollback on failure
            $this->down();
            
            return false;
        }
    }

    /**
     * Rollback migration
     *
     * @return bool Rollback success status
     */
    public function down() {
        global $wpdb;

        $this->log_migration('down', 'start');

        try {
            // Remove monitoring triggers
            $this->remove_monitoring_triggers();
            
            // Drop additional indexes
            $this->drop_additional_indexes();
            
            // Drop the main table
            $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
            
            // Clean up any related options
            $this->cleanup_migration_options();
            
            $this->log_migration('down', 'completed', [
                'table_dropped' => $this->table_name
            ]);

            return true;

        } catch (Exception $e) {
            $this->log_migration('down', 'failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Create rate limiting table
     */
    private function create_rate_limiting_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL COMMENT 'IP address, user ID, API key, or other identifier',
            identifier_type enum('ip','user','api_key','domain','session') NOT NULL DEFAULT 'ip' COMMENT 'Type of identifier being rate limited',
            action_type varchar(100) NOT NULL COMMENT 'Type of action being rate limited (api_request, login_attempt, etc.)',
            request_count int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Number of requests in current window',
            window_start datetime NOT NULL COMMENT 'Start time of current rate limiting window',
            window_end datetime NOT NULL COMMENT 'End time of current rate limiting window',
            window_duration int(10) unsigned NOT NULL DEFAULT 3600 COMMENT 'Window duration in seconds',
            rate_limit int(10) unsigned NOT NULL DEFAULT 100 COMMENT 'Maximum requests allowed in window',
            last_request_at datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of most recent request',
            is_blocked tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether identifier is currently blocked',
            block_until datetime DEFAULT NULL COMMENT 'Timestamp when block expires',
            block_reason varchar(255) DEFAULT NULL COMMENT 'Reason for blocking',
            block_duration int(10) unsigned DEFAULT NULL COMMENT 'Block duration in seconds',
            escalation_level tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Current escalation level (0-5)',
            escalation_multiplier decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT 'Rate limit multiplier based on escalation',
            warning_sent tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether warning has been sent',
            warning_sent_at datetime DEFAULT NULL COMMENT 'When warning was sent',
            total_requests bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Total lifetime requests for this identifier',
            total_blocks bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Total number of times blocked',
            first_request_at datetime DEFAULT NULL COMMENT 'Timestamp of first recorded request',
            last_reset_at datetime DEFAULT NULL COMMENT 'When counters were last reset',
            user_agent text DEFAULT NULL COMMENT 'User agent string for context',
            referrer varchar(500) DEFAULT NULL COMMENT 'HTTP referrer for context',
            geographic_region varchar(10) DEFAULT NULL COMMENT 'Geographic region code',
            priority_level enum('low','normal','high','critical') NOT NULL DEFAULT 'normal' COMMENT 'Priority level for processing',
            bypass_token varchar(100) DEFAULT NULL COMMENT 'Token for bypassing rate limits',
            bypass_expires_at datetime DEFAULT NULL COMMENT 'When bypass token expires',
            custom_limits longtext DEFAULT NULL COMMENT 'JSON encoded custom rate limiting rules',
            metadata longtext DEFAULT NULL COMMENT 'Additional metadata as JSON',
            notes text DEFAULT NULL COMMENT 'Administrative notes',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_rate_limit (identifier, action_type, window_start),
            KEY idx_identifier (identifier),
            KEY idx_identifier_type (identifier_type),
            KEY idx_action_type (action_type),
            KEY idx_window_period (window_start, window_end),
            KEY idx_blocked_status (is_blocked, block_until),
            KEY idx_escalation_level (escalation_level),
            KEY idx_last_request (last_request_at),
            KEY idx_geographic (geographic_region),
            KEY idx_priority (priority_level),
            KEY idx_rate_check (identifier, action_type, window_start, is_blocked),
            KEY idx_cleanup (window_end, created_at),
            KEY idx_monitoring (identifier_type, action_type, is_blocked, escalation_level),
            KEY idx_performance (identifier, action_type, is_blocked, last_request_at),
            KEY idx_analytics (action_type, escalation_level, created_at),
            KEY idx_bypass (bypass_token, bypass_expires_at)
        ) {$charset_collate} COMMENT='Rate limiting and throttling for API requests and user actions';";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify table was created successfully
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        if ($table_exists !== $this->table_name) {
            throw new Exception("Failed to create rate limiting table: {$this->table_name}");
        }
    }

    /**
     * Create additional performance indexes
     */
    private function create_additional_indexes() {
        global $wpdb;

        $additional_indexes = [
            // Composite indexes for common query patterns
            "CREATE INDEX idx_active_blocks ON {$this->table_name} (is_blocked, block_until, identifier_type) WHERE is_blocked = 1",
            "CREATE INDEX idx_recent_activity ON {$this->table_name} (last_request_at DESC, action_type, identifier_type)",
            "CREATE INDEX idx_escalated_users ON {$this->table_name} (escalation_level DESC, identifier, action_type) WHERE escalation_level > 0",
            "CREATE INDEX idx_warning_candidates ON {$this->table_name} (request_count, rate_limit, warning_sent) WHERE warning_sent = 0 AND request_count > rate_limit * 0.8",
            
            // Partitioning-ready indexes
            "CREATE INDEX idx_time_partitioned ON {$this->table_name} (DATE(created_at), action_type, identifier_type)",
            "CREATE INDEX idx_cleanup_candidates ON {$this->table_name} (window_end, is_blocked, escalation_level) WHERE window_end < NOW() - INTERVAL 24 HOUR",
            
            // Security monitoring indexes
            "CREATE INDEX idx_suspicious_activity ON {$this->table_name} (escalation_level, total_blocks, identifier_type) WHERE escalation_level >= 3",
            "CREATE INDEX idx_geographic_patterns ON {$this->table_name} (geographic_region, action_type, escalation_level, created_at)",
            
            // Performance monitoring indexes  
            "CREATE INDEX idx_high_volume_identifiers ON {$this->table_name} (total_requests DESC, identifier, identifier_type)",
            "CREATE INDEX idx_frequent_blockers ON {$this->table_name} (total_blocks DESC, identifier, last_reset_at)"
        ];

        foreach ($additional_indexes as $index_sql) {
            try {
                $wpdb->query($index_sql);
            } catch (Exception $e) {
                // Log index creation failures but don't fail the migration
                error_log("AFFCD Migration 004: Failed to create additional index - " . $e->getMessage());
            }
        }
    }

    /**
     * Insert default rate limiting rules
     */
    private function insert_default_rate_limits() {
        global $wpdb;

        $default_limits = [
            // API rate limits
            [
                'identifier' => 'api_default',
                'identifier_type' => 'api_key',
                'action_type' => 'api_request_default',
                'rate_limit' => 1000,
                'window_duration' => 3600, // 1 hour
                'window_start' => current_time('mysql'),
                'window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'priority_level' => 'normal',
                'notes' => 'Default API rate limit for all authenticated requests'
            ],
            [
                'identifier' => 'api_conversion',
                'identifier_type' => 'api_key', 
                'action_type' => 'conversion_tracking',
                'rate_limit' => 500,
                'window_duration' => 3600,
                'window_start' => current_time('mysql'),
                'window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'priority_level' => 'high',
                'notes' => 'Conversion tracking API rate limit'
            ],
            
            // Authentication rate limits
            [
                'identifier' => 'login_default',
                'identifier_type' => 'ip',
                'action_type' => 'login_attempt',
                'rate_limit' => 5,
                'window_duration' => 900, // 15 minutes
                'window_start' => current_time('mysql'),
                'window_end' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                'priority_level' => 'critical',
                'notes' => 'Login attempt rate limit per IP address'
            ],
            [
                'identifier' => 'password_reset',
                'identifier_type' => 'ip',
                'action_type' => 'password_reset',
                'rate_limit' => 3,
                'window_duration' => 3600,
                'window_start' => current_time('mysql'),
                'window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'priority_level' => 'high',
                'notes' => 'Password reset request rate limit'
            ],
            
            // Domain verification limits
            [
                'identifier' => 'domain_verification',
                'identifier_type' => 'domain',
                'action_type' => 'verification_request',
                'rate_limit' => 10,
                'window_duration' => 3600,
                'window_start' => current_time('mysql'),
                'window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'priority_level' => 'normal',
                'notes' => 'Domain verification request rate limit'
            ],
            
            // Webhook delivery limits
            [
                'identifier' => 'webhook_delivery',
                'identifier_type' => 'domain',
                'action_type' => 'webhook_delivery',
                'rate_limit' => 200,
                'window_duration' => 3600,
                'window_start' => current_time('mysql'),
                'window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'priority_level' => 'normal',
                'notes' => 'Webhook delivery rate limit per domain'
            ],
            
            // Data export limits
            [
                'identifier' => 'data_export',
                'identifier_type' => 'user',
                'action_type' => 'export_request',
                'rate_limit' => 5,
                'window_duration' => 3600,
                'window_start' => current_time('mysql'),
                'window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'priority_level' => 'low',
                'notes' => 'Data export request rate limit per user'
            ]
        ];

        foreach ($default_limits as $limit) {
            $wpdb->insert($this->table_name, $limit);
        }
    }

    /**
     * Create monitoring triggers
     */
    private function create_monitoring_triggers() {
        global $wpdb;

        // Create trigger for automatic escalation
        $escalation_trigger = "
        CREATE TRIGGER rate_limit_auto_escalate 
        BEFORE UPDATE ON {$this->table_name}
        FOR EACH ROW
        BEGIN
            -- Auto-escalate based on request count exceeding limits
            IF NEW.request_count > NEW.rate_limit * 1.5 AND OLD.escalation_level < 3 THEN
                SET NEW.escalation_level = OLD.escalation_level + 1;
                SET NEW.escalation_multiplier = 1.0 + (NEW.escalation_level * 0.25);
            END IF;
            
            -- Set warning flag when approaching limit
            IF NEW.request_count > NEW.rate_limit * 0.8 AND OLD.warning_sent = 0 THEN
                SET NEW.warning_sent = 1;
                SET NEW.warning_sent_at = NOW();
            END IF;
            
            -- Update total counters
            SET NEW.total_requests = OLD.total_requests + (NEW.request_count - OLD.request_count);
            
            -- Auto-block when limit exceeded significantly
            IF NEW.request_count > NEW.rate_limit * 2 AND OLD.is_blocked = 0 THEN
                SET NEW.is_blocked = 1;
                SET NEW.block_until = DATE_ADD(NOW(), INTERVAL (NEW.escalation_level * 300) SECOND);
                SET NEW.block_reason = 'Automatic block - rate limit exceeded';
                SET NEW.total_blocks = OLD.total_blocks + 1;
            END IF;
        END";

        try {
            $wpdb->query($escalation_trigger);
        } catch (Exception $e) {
            // MySQL triggers might not be supported in all environments
            error_log("AFFCD Migration 004: Could not create escalation trigger - " . $e->getMessage());
        }

        // Create cleanup event
        $cleanup_event = "
        CREATE EVENT IF NOT EXISTS rate_limit_cleanup
        ON SCHEDULE EVERY 1 HOUR
        STARTS NOW()
        DO
        BEGIN
            -- Clean up expired rate limit windows
            DELETE FROM {$this->table_name} 
            WHERE window_end < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            AND is_blocked = 0 
            AND escalation_level = 0;
            
            -- Reset blocks that have expired
            UPDATE {$this->table_name} 
            SET is_blocked = 0, block_until = NULL, block_reason = NULL
            WHERE is_blocked = 1 AND block_until < NOW();
            
            -- Reset escalation levels after extended good behaviour
            UPDATE {$this->table_name}
            SET escalation_level = GREATEST(0, escalation_level - 1),
                escalation_multiplier = 1.0 + (GREATEST(0, escalation_level - 1) * 0.25)
            WHERE escalation_level > 0 
            AND last_request_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND total_blocks = 0;
        END";

        try {
            $wpdb->query($cleanup_event);
        } catch (Exception $e) {
            error_log("AFFCD Migration 004: Could not create cleanup event - " . $e->getMessage());
        }
    }

    /**
     * Remove monitoring triggers
     */
    private function remove_monitoring_triggers() {
        global $wpdb;

        $wpdb->query("DROP TRIGGER IF EXISTS rate_limit_auto_escalate");
        $wpdb->query("DROP EVENT IF EXISTS rate_limit_cleanup");
    }

    /**
     * Drop additional indexes
     */
    private function drop_additional_indexes() {
        global $wpdb;

        $indexes_to_drop = [
            'idx_active_blocks',
            'idx_recent_activity', 
            'idx_escalated_users',
            'idx_warning_candidates',
            'idx_time_partitioned',
            'idx_cleanup_candidates',
            'idx_suspicious_activity',
            'idx_geographic_patterns',
            'idx_high_volume_identifiers',
            'idx_frequent_blockers'
        ];

        foreach ($indexes_to_drop as $index_name) {
            try {
                $wpdb->query("DROP INDEX {$index_name} ON {$this->table_name}");
            } catch (Exception $e) {
                // Ignore errors when dropping indexes
            }
        }
    }

    /**
     * Clean up migration-related options
     */
    private function cleanup_migration_options() {
        delete_option('affcd_rate_limiting_version');
        delete_option('affcd_rate_limiting_defaults_inserted');
        delete_transient('affcd_rate_limiting_setup');
    }

    /**
     * Get created indexes list
     *
     * @return array List of created indexes
     */
    private function get_created_indexes() {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_name}");
        return array_column($indexes, 'Key_name');
    }

    /**
     * Get default rules count
     *
     * @return int Number of default rules inserted
     */
    private function get_default_rules_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * Check if migration is needed
     *
     * @return bool True if migration is needed
     */
    public static function is_migration_needed() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        return $table_exists !== $table_name;
    }

    /**
     * Validate table structure after migration
     *
     * @return array Validation results
     */
    public static function validate_table_structure() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';

        $results = [
            'table_exists' => false,
            'required_columns' => [],
            'required_indexes' => [],
            'issues' => []
        ];

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        $results['table_exists'] = ($table_exists === $table_name);

        if (!$results['table_exists']) {
            $results['issues'][] = 'Rate limiting table does not exist';
            return $results;
        }

        // Check required columns
        $required_columns = [
            'id', 'identifier', 'identifier_type', 'action_type', 
            'request_count', 'window_start', 'window_end', 'rate_limit',
            'is_blocked', 'escalation_level', 'created_at', 'updated_at'
        ];

        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}");
        
        foreach ($required_columns as $column) {
            $results['required_columns'][$column] = in_array($column, $existing_columns);
            if (!$results['required_columns'][$column]) {
                $results['issues'][] = "Missing required column: {$column}";
            }
        }

        // Check required indexes
        $required_indexes = [
            'PRIMARY', 'unique_rate_limit', 'idx_identifier', 
            'idx_action_type', 'idx_rate_check'
        ];

        $existing_indexes = $wpdb->get_col("SHOW INDEX FROM {$table_name}");
        $index_names = array_unique($existing_indexes);

        foreach ($required_indexes as $index) {
            $results['required_indexes'][$index] = in_array($index, $index_names);
            if (!$results['required_indexes'][$index]) {
                $results['issues'][] = "Missing required index: {$index}";
            }
        }

        return $results;
    }

    /**
     * Get migration info
     *
     * @return array Migration information
     */
    public static function get_migration_info() {
        return [
            'version' => '004',
            'name' => 'Create Rate Limiting Table',
            'description' => 'Creates comprehensive rate limiting table for API throttling, security controls, and performance management across all domains',
            'dependencies' => ['001', '002', '003'],
            'estimated_time' => '45 seconds',
            'affects_data' => false,
            'reversible' => true,
            'critical' => true
        ];
    }

    /**
     * Log migration activity
     *
     * @param string $direction Migration direction (up/down)
     * @param string $status Migration status
     * @param array $data Additional data to log
     */
    private function log_migration($direction, $status, $data = []) {
        global $wpdb;

        $migration_data = array_merge([
            'migration_version' => $this->version,
            'migration_name' => $this->name,
            'direction' => $direction,
            'status' => $status,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'mysql_version' => $wpdb->db_version(),
            'table_name' => $this->table_name
        ], $data);

        error_log(sprintf(
            'AFFCD Migration %s (%s): %s - %s - %s',
            $this->version,
            $direction,
            $status,
            $this->name,
            json_encode($migration_data)
        ));

        // Store in database if migrations table exists
        $migrations_table = $wpdb->prefix . 'affcd_migrations';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$migrations_table}'") === $migrations_table) {
            $wpdb->insert($migrations_table, [
                'migration_version' => $this->version,
                'migration_name' => $this->name,
                'direction' => $direction,
                'status' => $status,
                'metadata' => json_encode($migration_data),
                'created_at' => current_time('mysql')
            ]);
        }
    }

    /**
     * Get table statistics
     *
     * @return array Table statistics
     */
    public static function get_table_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return ['error' => 'Table does not exist'];
        }

        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_records,
                COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocked_identifiers,
                COUNT(DISTINCT identifier) as unique_identifiers,
                COUNT(DISTINCT action_type) as unique_action_types,
                AVG(request_count) as avg_request_count,
                MAX(escalation_level) as max_escalation_level,
                SUM(total_requests) as lifetime_requests,
                SUM(total_blocks) as lifetime_blocks
            FROM {$table_name}
        ", ARRAY_A);

        $table_size = $wpdb->get_row("
            SELECT 
                ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
                table_rows as estimated_rows
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() AND table_name = '{$table_name}'
        ", ARRAY_A);

        return array_merge($stats ?: [], $table_size ?: []);
    }
}