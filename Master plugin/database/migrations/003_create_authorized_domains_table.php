<?php
/**
 * authorised Domains Table Migration for Affiliate Cross Domain System
 * 
 * Path: /wp-content/plugins/affiliate-cross-domain-system/database/migrations/003_create_authorised_domains_table.php
 * Plugin: Affiliate Cross Domain System (Master)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Migration_003_Create_authorised_Domains_Table {

    /**
     * Run the migration
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain_url varchar(255) NOT NULL,
            domain_name varchar(255) NULL,
            api_key varchar(128) NOT NULL,
            api_secret varchar(255) NOT NULL,
            status enum('active','inactive','suspended','pending') NOT NULL DEFAULT 'pending',
            verification_status enum('verified','pending','failed') NOT NULL DEFAULT 'pending',
            verification_token varchar(128) NULL,
            verification_method enum('dns','file','meta','api') NOT NULL DEFAULT 'api',
            last_verification_attempt datetime NULL,
            last_successful_verification datetime NULL,
            verification_attempts int(11) NOT NULL DEFAULT 0,
            max_daily_requests int(11) NOT NULL DEFAULT 10000,
            current_daily_requests int(11) NOT NULL DEFAULT 0,
            rate_limit_per_minute int(11) NOT NULL DEFAULT 100,
            rate_limit_per_hour int(11) NOT NULL DEFAULT 1000,
            allowed_endpoints text NULL,
            blocked_endpoints text NULL,
            security_level enum('low','medium','high','strict') NOT NULL DEFAULT 'medium',
            require_https tinyint(1) NOT NULL DEFAULT 1,
            allowed_ips text NULL,
            blocked_ips text NULL,
            webhook_url varchar(500) NULL,
            webhook_secret varchar(128) NULL,
            webhook_events text NULL,
            webhook_last_sent datetime NULL,
            webhook_failures int(11) NOT NULL DEFAULT 0,
            statistics longtext NULL,
            metadata longtext NULL,
            owner_user_id bigint(20) unsigned NULL,
            owner_email varchar(255) NULL,
            owner_name varchar(255) NULL,
            contact_email varchar(255) NULL,
            contact_phone varchar(50) NULL,
            timezone varchar(50) NULL DEFAULT 'UTC',
            language varchar(10) NULL DEFAULT 'en',
            notes text NULL,
            tags text NULL,
            expires_at datetime NULL,
            suspended_at datetime NULL,
            suspended_reason text NULL,
            suspended_by bigint(20) unsigned NULL,
            last_activity_at datetime NULL,
            last_api_call_at datetime NULL,
            last_error_at datetime NULL,
            last_error_message text NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
            KEY idx_domain_performance (status, verification_status, last_activity_at),
            KEY idx_rate_limiting (domain_url, current_daily_requests, max_daily_requests),
            CONSTRAINT fk_owner_user FOREIGN KEY (owner_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_created_by FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
            CONSTRAINT fk_suspended_by FOREIGN KEY (suspended_by) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Create additional indexes for performance
        self::create_performance_indexes($table_name);
        
        // Log migration
        error_log("AFFCD Migration 003: Suspended {$suspended} expired domains");
        
        return $suspended;
    }

    /**
     * Update domain statistics
     */
    public static function update_domain_statistics($domain_id, $stats_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $current_stats = $wpdb->get_var($wpdb->prepare(
            "SELECT statistics FROM {$table_name} WHERE id = %d",
            $domain_id
        ));
        
        $stats = $current_stats ? json_decode($current_stats, true) : [];
        $stats = array_merge($stats, $stats_data);
        $stats['last_updated'] = current_time('mysql');
        
        $updated = $wpdb->update(
            $table_name,
            ['statistics' => wp_json_encode($stats)],
            ['id' => $domain_id],
            ['%s'],
            ['%d']
        );
        
        return $updated !== false;
    }

    /**
     * Get domain by API key
     */
    public static function get_domain_by_api_key($api_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE api_key = %s AND status = 'active'",
            $api_key
        ));
    }

    /**
     * Check rate limits for domain
     */
    public static function check_rate_limits($domain_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT current_daily_requests, max_daily_requests, rate_limit_per_minute, rate_limit_per_hour 
             FROM {$table_name} WHERE id = %d",
            $domain_id
        ));
        
        if (!$domain) {
            return false;
        }
        
        // Check daily limit
        if ($domain->current_daily_requests >= $domain->max_daily_requests) {
            return ['status' => 'exceeded', 'type' => 'daily', 'limit' => $domain->max_daily_requests];
        }
        
        // Check hourly limit
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $hourly_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affcd_usage_tracking 
             WHERE domain_from = (SELECT domain_url FROM {$table_name} WHERE id = %d) 
             AND created_at > %s",
            $domain_id,
            $hour_ago
        ));
        
        if ($hourly_requests >= $domain->rate_limit_per_hour) {
            return ['status' => 'exceeded', 'type' => 'hourly', 'limit' => $domain->rate_limit_per_hour];
        }
        
        // Check minute limit
        $minute_ago = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $minute_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affcd_usage_tracking 
             WHERE domain_from = (SELECT domain_url FROM {$table_name} WHERE id = %d) 
             AND created_at > %s",
            $domain_id,
            $minute_ago
        ));
        
        if ($minute_requests >= $domain->rate_limit_per_minute) {
            return ['status' => 'exceeded', 'type' => 'minute', 'limit' => $domain->rate_limit_per_minute];
        }
        
        return ['status' => 'ok'];
    }

    /**
     * Increment request counter
     */
    public static function increment_request_counter($domain_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET current_daily_requests = current_daily_requests + 1,
                 last_api_call_at = %s 
             WHERE id = %d",
            current_time('mysql'),
            $domain_id
        ));
        
        return $updated !== false;
    }

    /**
     * Update last activity timestamp
     */
    public static function update_last_activity($domain_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $updated = $wpdb->update(
            $table_name,
            ['last_activity_at' => current_time('mysql')],
            ['id' => $domain_id],
            ['%s'],
            ['%d']
        );
        
        return $updated !== false;
    }

    /**
     * Log verification attempt
     */
    public static function log_verification_attempt($domain_id, $success = false, $error_message = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $update_data = [
            'last_verification_attempt' => current_time('mysql'),
            'verification_attempts' => new stdClass() // Will be handled as SQL expression
        ];
        
        $update_format = ['%s', '%d'];
        
        if ($success) {
            $update_data['verification_status'] = 'verified';
            $update_data['last_successful_verification'] = current_time('mysql');
            $update_data['verification_attempts'] = 0;
            $update_data['last_error_message'] = null;
            $update_format = array_merge($update_format, ['%s', '%s', '%d', '%s']);
        } else {
            $update_data['verification_status'] = 'failed';
            if ($error_message) {
                $update_data['last_error_at'] = current_time('mysql');
                $update_data['last_error_message'] = $error_message;
                $update_format = array_merge($update_format, ['%s', '%s', '%s']);
            }
        }
        
        // Handle verification attempts increment manually
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET verification_attempts = verification_attempts + 1,
                 last_verification_attempt = %s
             WHERE id = %d",
            current_time('mysql'),
            $domain_id
        ));
        
        // Update other fields if successful
        if ($success) {
            $wpdb->update(
                $table_name,
                [
                    'verification_status' => 'verified',
                    'last_successful_verification' => current_time('mysql'),
                    'verification_attempts' => 0,
                    'last_error_message' => null
                ],
                ['id' => $domain_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
        } elseif ($error_message) {
            $wpdb->update(
                $table_name,
                [
                    'verification_status' => 'failed',
                    'last_error_at' => current_time('mysql'),
                    'last_error_message' => $error_message
                ],
                ['id' => $domain_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
        }
        
        return true;
    }

    /**
     * Generate new API credentials
     */
    public static function regenerate_api_credentials($domain_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $new_api_key = self::generate_api_key();
        $new_api_secret = self::generate_api_secret();
        
        $updated = $wpdb->update(
            $table_name,
            [
                'api_key' => $new_api_key,
                'api_secret' => $new_api_secret,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $domain_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($updated !== false) {
            // Log the credential regeneration
            error_log("AFFCD: API credentials regenerated for domain ID {$domain_id}");
            
            return [
                'api_key' => $new_api_key,
                'api_secret' => $new_api_secret
            ];
        }
        
        return false;
    }

    /**
     * Bulk update domain settings
     */
    public static function bulk_update_domains($domain_ids, $settings) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $updated_count = 0;
        
        if (empty($domain_ids) || empty($settings)) {
            return 0;
        }
        
        // Sanitize domain IDs
        $domain_ids = array_map('intval', $domain_ids);
        $placeholders = implode(',', array_fill(0, count($domain_ids), '%d'));
        
        // Build SET clause
        $set_clauses = [];
        $values = [];
        
        $allowed_fields = [
            'status', 'security_level', 'max_daily_requests', 'rate_limit_per_minute', 
            'rate_limit_per_hour', 'require_https', 'tags', 'notes'
        ];
        
        foreach ($settings as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_clauses[] = "{$field} = %s";
                $values[] = $value;
            }
        }
        
        if (empty($set_clauses)) {
            return 0;
        }
        
        $set_clause = implode(', ', $set_clauses);
        $values = array_merge($values, $domain_ids);
        
        $sql = "UPDATE {$table_name} 
                SET {$set_clause}, updated_at = NOW() 
                WHERE id IN ({$placeholders})";
        
        $updated_count = $wpdb->query($wpdb->prepare($sql, $values));
        
        error_log("AFFCD Migration 003: Bulk updated {$updated_count} domains");
        
        return $updated_count;
    }

    /**
     * Archive inactive domains
     */
    public static function archive_inactive_domains($inactive_days = 180) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $archive_table = $wpdb->prefix . 'affcd_authorised_domains_archive';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$inactive_days} days"));
        
        // Create archive table if it doesn't exist
        $charset_collate = $wpdb->get_charset_collate();
        $archive_sql = "CREATE TABLE IF NOT EXISTS {$archive_table} LIKE {$table_name} {$charset_collate}";
        $wpdb->query($archive_sql);
        
        // Find inactive domains
        $inactive_domains = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE (last_activity_at < %s OR last_activity_at IS NULL) 
             AND status IN ('inactive', 'suspended') 
             AND created_at < %s",
            $cutoff_date,
            $cutoff_date
        ));
        
        $archived_count = 0;
        
        foreach ($inactive_domains as $domain) {
            // Insert into archive
            $archive_data = (array) $domain;
            unset($archive_data['id']); // Let archive table generate new ID
            $archive_data['archived_at'] = current_time('mysql');
            $archive_data['archived_reason'] = 'Inactive for more than ' . $inactive_days . ' days';
            
            $archived = $wpdb->insert($archive_table, $archive_data);
            
            if ($archived !== false) {
                // Delete from main table
                $wpdb->delete($table_name, ['id' => $domain->id], ['%d']);
                $archived_count++;
            }
        }
        
        error_log("AFFCD Migration 003: Archived {$archived_count} inactive domains");
        
        return $archived_count;
    }
} authorised domains table created/updated");
        
        return $result;
    }

    /**
     * Rollback the migration
     */
    public static function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $sql = "DROP TABLE IF EXISTS {$table_name}";
        $result = $wpdb->query($sql);
        
        // Log rollback
        error_log("AFFCD Migration 003: authorised domains table dropped");
        
        return $result !== false;
    }

    /**
     * Create additional performance indexes
     */
    private static function create_performance_indexes($table_name) {
        global $wpdb;
        
        $indexes = [
            "CREATE INDEX idx_api_lookup ON {$table_name} (api_key, status, verification_status)",
            "CREATE INDEX idx_domain_health ON {$table_name} (verification_status, last_successful_verification, status)",
            "CREATE INDEX idx_rate_limit_check ON {$table_name} (domain_url, current_daily_requests, rate_limit_per_minute)",
            "CREATE INDEX idx_expiring_domains ON {$table_name} (expires_at, status) WHERE expires_at IS NOT NULL",
            "CREATE INDEX idx_webhook_queue ON {$table_name} (webhook_url, webhook_last_sent) WHERE webhook_url IS NOT NULL"
        ];
        
        foreach ($indexes as $index_sql) {
            try {
                $wpdb->query($index_sql);
            } catch (Exception $e) {
                // Index might already exist or MySQL version doesn't support WHERE clause
                error_log("AFFCD Migration 003: Index creation note - " . $e->getMessage());
            }
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
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $current_user_id = get_current_user_id() ?: 1;
        
        $sample_data = [
            [
                'domain_url' => 'https://demo-client1.example.com',
                'domain_name' => 'Demo Client 1',
                'api_key' => self::generate_api_key(),
                'api_secret' => self::generate_api_secret(),
                'status' => 'active',
                'verification_status' => 'verified',
                'verification_method' => 'api',
                'last_successful_verification' => current_time('mysql'),
                'max_daily_requests' => 50000,
                'current_daily_requests' => 1250,
                'rate_limit_per_minute' => 200,
                'rate_limit_per_hour' => 5000,
                'allowed_endpoints' => wp_json_encode(['validate', 'convert', 'track']),
                'security_level' => 'high',
                'require_https' => 1,
                'webhook_url' => 'https://demo-client1.example.com/webhook/affiliate',
                'webhook_secret' => wp_generate_password(32, false),
                'webhook_events' => wp_json_encode(['validation', 'conversion', 'error']),
                'statistics' => wp_json_encode([
                    'total_requests' => 25000,
                    'successful_requests' => 24500,
                    'failed_requests' => 500,
                    'avg_response_time' => 45.5,
                    'last_30_days' => [
                        'requests' => 8500,
                        'conversions' => 125,
                        'revenue' => 12500.00
                    ]
                ]),
                'owner_user_id' => $current_user_id,
                'owner_email' => 'admin@demo-client1.example.com',
                'owner_name' => 'Demo Client 1 Admin',
                'contact_email' => 'support@demo-client1.example.com',
                'timezone' => 'America/New_York',
                'language' => 'en',
                'tags' => 'demo,client,active,premium',
                'last_activity_at' => current_time('mysql'),
                'last_api_call_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'created_by' => $current_user_id
            ],
            [
                'domain_url' => 'https://demo-client2.example.com',
                'domain_name' => 'Demo Client 2',
                'api_key' => self::generate_api_key(),
                'api_secret' => self::generate_api_secret(),
                'status' => 'active',
                'verification_status' => 'verified',
                'verification_method' => 'dns',
                'last_successful_verification' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'max_daily_requests' => 25000,
                'current_daily_requests' => 890,
                'rate_limit_per_minute' => 150,
                'rate_limit_per_hour' => 2500,
                'allowed_endpoints' => wp_json_encode(['validate', 'track']),
                'security_level' => 'medium',
                'require_https' => 1,
                'statistics' => wp_json_encode([
                    'total_requests' => 15000,
                    'successful_requests' => 14200,
                    'failed_requests' => 800,
                    'avg_response_time' => 62.3,
                    'last_30_days' => [
                        'requests' => 5500,
                        'conversions' => 85,
                        'revenue' => 8500.00
                    ]
                ]),
                'owner_user_id' => $current_user_id,
                'owner_email' => 'admin@demo-client2.example.com',
                'owner_name' => 'Demo Client 2 Admin',
                'contact_email' => 'support@demo-client2.example.com',
                'timezone' => 'Europe/London',
                'language' => 'en',
                'tags' => 'demo,client,active,standard',
                'last_activity_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'last_api_call_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                'created_by' => $current_user_id
            ],
            [
                'domain_url' => 'https://pending-client.example.com',
                'domain_name' => 'Pending Client',
                'api_key' => self::generate_api_key(),
                'api_secret' => self::generate_api_secret(),
                'status' => 'pending',
                'verification_status' => 'pending',
                'verification_token' => wp_generate_password(32, false),
                'verification_method' => 'file',
                'verification_attempts' => 2,
                'max_daily_requests' => 5000,
                'current_daily_requests' => 0,
                'rate_limit_per_minute' => 50,
                'rate_limit_per_hour' => 500,
                'security_level' => 'medium',
                'require_https' => 1,
                'owner_email' => 'admin@pending-client.example.com',
                'owner_name' => 'Pending Client Admin',
                'contact_email' => 'support@pending-client.example.com',
                'timezone' => 'America/Los_Angeles',
                'language' => 'en',
                'tags' => 'demo,pending,new',
                'notes' => 'Awaiting domain verification via file upload method',
                'created_by' => $current_user_id
            ]
        ];
        
        foreach ($sample_data as $data) {
            $wpdb->insert($table_name, $data);
        }
        
        error_log("AFFCD Migration 003: Sample authorised domains seeded successfully");
    }

    /**
     * Generate secure API key
     */
    private static function generate_api_key() {
        return 'affcd_' . wp_generate_password(32, false, false);
    }

    /**
     * Generate secure API secret
     */
    private static function generate_api_secret() {
        return hash('sha256', wp_generate_password(64, true, true) . time() . wp_rand());
    }

    /**
     * Migrate from old domain storage format
     */
    public static function migrate_from_options() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $old_domains = get_option('affcd_allowed_domains', []);
        
        if (empty($old_domains)) {
            return 0;
        }
        
        $migrated = 0;
        $current_user_id = get_current_user_id() ?: 1;
        
        foreach ($old_domains as $index => $domain_url) {
            // Check if domain already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE domain_url = %s",
                $domain_url
            ));
            
            if ($exists > 0) {
                continue;
            }
            
            // Parse domain info
            $parsed_url = parse_url($domain_url);
            $domain_name = $parsed_url['host'] ?? 'Unknown Domain';
            
            // Get old settings if they exist
            $old_api_key = get_option("affcd_domain_api_key_{$index}", '');
            $old_status = get_option("affcd_domain_status_{$index}", 'pending');
            $old_last_check = get_option("affcd_domain_last_check_{$index}", null);
            
            $domain_data = [
                'domain_url' => esc_url_raw($domain_url),
                'domain_name' => sanitize_text_field($domain_name),
                'api_key' => $old_api_key ?: self::generate_api_key(),
                'api_secret' => self::generate_api_secret(),
                'status' => $old_status === 'verified' ? 'active' : 'pending',
                'verification_status' => $old_status === 'verified' ? 'verified' : 'pending',
                'verification_method' => 'api',
                'last_successful_verification' => $old_last_check,
                'max_daily_requests' => 10000,
                'rate_limit_per_minute' => 100,
                'rate_limit_per_hour' => 1000,
                'security_level' => 'medium',
                'require_https' => 1,
                'created_by' => $current_user_id
            ];
            
            $result = $wpdb->insert($table_name, $domain_data);
            
            if ($result !== false) {
                $migrated++;
                
                // Clean up old option
                delete_option("affcd_domain_api_key_{$index}");
                delete_option("affcd_domain_status_{$index}");
                delete_option("affcd_domain_last_check_{$index}");
            }
        }
        
        // Remove old domains option if migration was successful
        if ($migrated > 0) {
            delete_option('affcd_allowed_domains');
        }
        
        error_log("AFFCD Migration 003: Migrated {$migrated} domains from old storage format");
        
        return $migrated;
    }

    /**
     * Check if migration is needed
     */
    public static function is_migration_needed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
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
            'version' => '003',
            'name' => 'Create authorised Domains Table',
            'description' => 'Creates the authorised domains table for managing client domains, API keys, and security settings',
            'dependencies' => ['001', '002'],
            'estimated_time' => '45 seconds',
            'affects_data' => true,
            'backup_recommended' => true
        ];
    }

    /**
     * Validate table structure
     */
    public static function validate_table_structure() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        $required_columns = [
            'id', 'domain_url', 'api_key', 'api_secret', 'status', 
            'verification_status', 'max_daily_requests', 'security_level', 'created_at'
        ];
        
        $existing_columns = wp_list_pluck($columns, 'Field');
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (!empty($missing_columns)) {
            error_log("AFFCD Migration 003: Missing columns - " . implode(', ', $missing_columns));
            return false;
        }
        
        // Check unique constraints
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
        $unique_indexes = array_filter($indexes, function($index) {
            return $index->Non_unique == 0;
        });
        
        $unique_keys = wp_list_pluck($unique_indexes, 'Key_name');
        $required_unique = ['PRIMARY', 'unique_domain_url', 'unique_api_key'];
        $missing_unique = array_diff($required_unique, $unique_keys);
        
        if (!empty($missing_unique)) {
            error_log("AFFCD Migration 003: Missing unique constraints - " . implode(', ', $missing_unique));
            return false;
        }
        
        return true;
    }

    /**
     * Update rate limit counters (daily reset)
     */
    public static function reset_daily_counters() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $updated = $wpdb->query(
            "UPDATE {$table_name} SET current_daily_requests = 0 WHERE status = 'active'"
        );
        
        error_log("AFFCD Migration 003: Reset daily counters for {$updated} domains");
        
        return $updated;
    }

  /**
     * Clean up expired domains
     */
    public static function cleanup_expired_domains() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $current_time = current_time('mysql');
        
        // Suspend expired domains
        $suspended = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET status = 'suspended', 
                 suspended_at = %s, 
                 suspended_reason = 'Domain expired' 
             WHERE expires_at < %s 
             AND status = 'active'",
            $current_time,
            $current_time
        ));
        
        error_log("AFFCD Migration 003: Suspended {$suspended} expired domains");
        
        return $suspended;
    }

    /**
     * Purge old verification tokens
     */
    public static function purge_old_verification_tokens() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $cutoff_time = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        $purged = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET verification_token = NULL,
                 verification_attempts = 0
             WHERE verification_token IS NOT NULL 
             AND last_verification_attempt < %s
             AND verification_status != 'verified'",
            $cutoff_time
        ));
        
        error_log("AFFCD Migration 003: Purged {$purged} old verification tokens");
        
        return $purged;
    }

    /**
     * Update webhook failure counts
     */
    public static function update_webhook_failure($domain_id, $error_message = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET webhook_failures = webhook_failures + 1,
                 last_error_at = %s,
                 last_error_message = %s
             WHERE id = %d",
            current_time('mysql'),
            $error_message,
            $domain_id
        ));
        
        // Check if webhook should be disabled
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT webhook_failures, webhook_url FROM {$table_name} WHERE id = %d",
            $domain_id
        ));
        
        if ($domain && $domain->webhook_failures >= 5) {
            // Disable webhook after 5 consecutive failures
            $wpdb->update(
                $table_name,
                [
                    'webhook_url' => null,
                    'webhook_events' => null,
                    'notes' => trim(($wpdb->get_var($wpdb->prepare(
                        "SELECT notes FROM {$table_name} WHERE id = %d", 
                        $domain_id
                    )) ?? '') . "\nWebhook disabled due to repeated failures.")
                ],
                ['id' => $domain_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            error_log("AFFCD Migration 003: Disabled webhook for domain ID {$domain_id} due to failures");
        }
        
        return true;
    }

    /**
     * Reset webhook success
     */
    public static function reset_webhook_failure($domain_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $updated = $wpdb->update(
            $table_name,
            [
                'webhook_failures' => 0,
                'webhook_last_sent' => current_time('mysql'),
                'last_error_at' => null,
                'last_error_message' => null
            ],
            ['id' => $domain_id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );
        
        return $updated !== false;
    }

    /**
     * Get domains requiring verification
     */
    public static function get_domains_for_verification($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, domain_url, verification_method, verification_token, verification_attempts 
             FROM {$table_name} 
             WHERE verification_status IN ('pending', 'failed')
             AND status != 'suspended'
             AND verification_attempts < 5
             AND (last_verification_attempt IS NULL OR last_verification_attempt < %s)
             ORDER BY created_at ASC
             LIMIT %d",
            date('Y-m-d H:i:s', strtotime('-1 hour')),
            $limit
        ));
    }

    /**
     * Get domain performance metrics
     */
    public static function get_domain_performance_metrics($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        $date_range = date('Y-m-d', strtotime("-{$days} days"));
        
        $metrics = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                d.id,
                d.domain_url,
                d.domain_name,
                d.status,
                d.max_daily_requests,
                d.current_daily_requests,
                COALESCE(u.total_requests, 0) as period_requests,
                COALESCE(u.successful_requests, 0) as period_successful,
                COALESCE(u.failed_requests, 0) as period_failed,
                COALESCE(u.avg_response_time, 0) as avg_response_time,
                d.last_activity_at,
                CASE 
                    WHEN d.last_activity_at IS NULL THEN 'Never Active'
                    WHEN d.last_activity_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Inactive'
                    WHEN d.last_activity_at < DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'Low Activity'
                    ELSE 'Active'
                END as activity_status
             FROM {$table_name} d
             LEFT JOIN (
                SELECT 
                    domain_from,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_requests,
                    AVG(response_time) as avg_response_time
                FROM {$usage_table} 
                WHERE created_at >= %s
                GROUP BY domain_from
             ) u ON d.domain_url = u.domain_from
             WHERE d.status = 'active'
             ORDER BY period_requests DESC",
            $date_range
        ));
        
        return $metrics;
    }

    /**
     * Get security violations for domains
     */
    public static function get_security_violations($days = 7, $limit = 100) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $security_table = $wpdb->prefix . 'affcd_security_log';
        $date_range = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                d.id,
                d.domain_url,
                d.domain_name,
                d.security_level,
                s.violation_type,
                s.severity,
                s.details,
                s.ip_address,
                s.user_agent,
                s.created_at as violation_time
             FROM {$table_name} d
             INNER JOIN {$security_table} s ON d.domain_url = s.domain_url
             WHERE s.created_at >= %s
             AND s.severity IN ('medium', 'high', 'critical')
             ORDER BY s.created_at DESC, s.severity DESC
             LIMIT %d",
            $date_range,
            $limit
        ));
    }

    /**
     * Suspend domain for security violations
     */
    public static function suspend_domain_for_security($domain_id, $reason, $suspended_by = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $suspended_by = $suspended_by ?: get_current_user_id();
        
        $updated = $wpdb->update(
            $table_name,
            [
                'status' => 'suspended',
                'suspended_at' => current_time('mysql'),
                'suspended_reason' => sanitize_text_field($reason),
                'suspended_by' => $suspended_by,
                'security_level' => 'strict'
            ],
            ['id' => $domain_id],
            ['%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );
        
        if ($updated !== false) {
            error_log("AFFCD Migration 003: Suspended domain ID {$domain_id} for security: {$reason}");
            
            // Send notification webhook if configured
            self::send_security_notification($domain_id, 'suspended', $reason);
        }
        
        return $updated !== false;
    }

    /**
     * Send security notification
     */
    private static function send_security_notification($domain_id, $event_type, $details) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT domain_url, domain_name, webhook_url, webhook_secret, owner_email 
             FROM {$table_name} WHERE id = %d",
            $domain_id
        ));
        
        if (!$domain) {
            return false;
        }
        
        $notification_data = [
            'event' => 'security.' . $event_type,
            'domain' => $domain->domain_url,
            'domain_name' => $domain->domain_name,
            'details' => $details,
            'timestamp' => current_time('mysql'),
            'severity' => 'high'
        ];
        
        // Send webhook if configured
        if ($domain->webhook_url) {
            $webhook_body = wp_json_encode($notification_data);
            $signature = hash_hmac('sha256', $webhook_body, $domain->webhook_secret);
            
            wp_remote_post($domain->webhook_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-AFFCD-Signature' => 'sha256=' . $signature,
                    'X-AFFCD-Event' => $notification_data['event']
                ],
                'body' => $webhook_body,
                'timeout' => 15
            ]);
        }
        
        // Send email notification
        if ($domain->owner_email) {
            $subject = sprintf('[AFFCD] Security Alert: %s', $domain->domain_name ?: $domain->domain_url);
            $message = sprintf(
                "A security event has occurred for your domain: %s\n\nEvent: %s\nDetails: %s\nTime: %s\n\nPlease review your domain configuration and contact support if needed.",
                $domain->domain_url,
                $event_type,
                $details,
                current_time('mysql')
            );
            
            wp_mail($domain->owner_email, $subject, $message);
        }
        
        return true;
    }

    /**
     * Get domain quotas and usage
     */
    public static function get_domain_quotas($domain_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        $usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                domain_url,
                max_daily_requests,
                current_daily_requests,
                rate_limit_per_minute,
                rate_limit_per_hour,
                last_activity_at
             FROM {$table_name} WHERE id = %d",
            $domain_id
        ));
        
        if (!$domain) {
            return false;
        }
        
        // Get hourly usage
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $hourly_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$usage_table} 
             WHERE domain_from = %s AND created_at > %s",
            $domain->domain_url,
            $hour_ago
        ));
        
        // Get minute usage
        $minute_ago = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $minute_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$usage_table} 
             WHERE domain_from = %s AND created_at > %s",
            $domain->domain_url,
            $minute_ago
        ));
        
        return [
            'daily' => [
                'limit' => $domain->max_daily_requests,
                'used' => $domain->current_daily_requests,
                'percentage' => round(($domain->current_daily_requests / $domain->max_daily_requests) * 100, 2)
            ],
            'hourly' => [
                'limit' => $domain->rate_limit_per_hour,
                'used' => $hourly_requests,
                'percentage' => round(($hourly_requests / $domain->rate_limit_per_hour) * 100, 2)
            ],
            'minute' => [
                'limit' => $domain->rate_limit_per_minute,
                'used' => $minute_requests,
                'percentage' => round(($minute_requests / $domain->rate_limit_per_minute) * 100, 2)
            ]
        ];
    }

    /**
     * Export domain configuration
     */
    public static function export_domain_config($domain_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $domain_id
        ), ARRAY_A);
        
        if (!$domain) {
            return false;
        }
        
        // Remove sensitive data
        unset($domain['api_secret']);
        unset($domain['webhook_secret']);
        unset($domain['verification_token']);
        
        // Parse JSON fields
        $json_fields = ['allowed_endpoints', 'blocked_endpoints', 'webhook_events', 'statistics', 'metadata'];
        foreach ($json_fields as $field) {
            if (!empty($domain[$field])) {
                $domain[$field] = json_decode($domain[$field], true);
            }
        }
        
        $domain['exported_at'] = current_time('mysql');
        $domain['export_version'] = '1.0';
        
        return $domain;
    }

    /**
     * Import domain configuration
     */
    public static function import_domain_config($config_data, $overwrite = false) {
        global $wpdb;
        
        if (empty($config_data['domain_url'])) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        // Check if domain already exists
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE domain_url = %s",
            $config_data['domain_url']
        ));
        
        if ($existing_id && !$overwrite) {
            return false; // Domain exists and overwrite not allowed
        }
        
        // Prepare data for insertion/update
        $domain_data = [
            'domain_url' => esc_url_raw($config_data['domain_url']),
            'domain_name' => sanitize_text_field($config_data['domain_name'] ?? ''),
            'status' => in_array($config_data['status'] ?? '', ['active', 'inactive', 'suspended', 'pending']) 
                       ? $config_data['status'] : 'pending',
            'security_level' => in_array($config_data['security_level'] ?? '', ['low', 'medium', 'high', 'strict']) 
                              ? $config_data['security_level'] : 'medium',
            'max_daily_requests' => intval($config_data['max_daily_requests'] ?? 10000),
            'rate_limit_per_minute' => intval($config_data['rate_limit_per_minute'] ?? 100),
            'rate_limit_per_hour' => intval($config_data['rate_limit_per_hour'] ?? 1000),
            'require_https' => !empty($config_data['require_https']),
            'owner_email' => sanitize_email($config_data['owner_email'] ?? ''),
            'owner_name' => sanitize_text_field($config_data['owner_name'] ?? ''),
            'contact_email' => sanitize_email($config_data['contact_email'] ?? ''),
            'timezone' => sanitize_text_field($config_data['timezone'] ?? 'UTC'),
            'language' => sanitize_text_field($config_data['language'] ?? 'en'),
            'tags' => sanitize_text_field($config_data['tags'] ?? ''),
            'notes' => sanitize_textarea_field($config_data['notes'] ?? ''),
        ];
        
        // Handle JSON fields
        $json_fields = ['allowed_endpoints', 'blocked_endpoints', 'webhook_events', 'metadata'];
        foreach ($json_fields as $field) {
            if (isset($config_data[$field]) && is_array($config_data[$field])) {
                $domain_data[$field] = wp_json_encode($config_data[$field]);
            }
        }
        
        // Generate new credentials if not importing existing ones
        if (empty($config_data['api_key'])) {
            $domain_data['api_key'] = self::generate_api_key();
        }
        $domain_data['api_secret'] = self::generate_api_secret();
        
        if ($existing_id) {
            // Update existing domain
            $result = $wpdb->update($table_name, $domain_data, ['id' => $existing_id]);
            return $result !== false ? $existing_id : false;
        } else {
            // Insert new domain
            $domain_data['created_by'] = get_current_user_id();
            $result = $wpdb->insert($table_name, $domain_data);
            return $result !== false ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get table statistics
     */
    public static function get_table_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_domains,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_domains,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_domains,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_domains,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified_domains,
                SUM(current_daily_requests) as total_daily_requests,
                AVG(current_daily_requests) as avg_daily_requests,
                MAX(last_activity_at) as latest_activity,
                MIN(created_at) as first_domain_created
             FROM {$table_name}",
            ARRAY_A
        );
        
        return $stats;
    }
}
?>