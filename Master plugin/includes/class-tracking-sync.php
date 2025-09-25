<?php
/**
 * Tracking Sync Class
 *
 * Handles synchronisation of tracking data between master plugin and client domains.
 * Manages data aggregation, real-time sync, batch processing, and conflict resolution
 * for affiliate tracking across multiple domains.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 * @author Richard King
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Tracking_Sync {

    /**
     * Database manager instance
     * @var AFFCD_Database_Manager
     */
    private $db_manager;

    /**
     * Sync configuration
     * @var array
     */
    private $sync_config = [];

    /**
     * Active sync processes
     * @var array
     */
    private $active_syncs = [];

    /**
     * Sync batch size
     * @var int
     */
    private $batch_size = 100;

    /**
     * Maximum retry attempts
     * @var int
     */
    private $max_retries = 3;

    /**
     * Sync timeout (seconds)
     * @var int
     */
    private $sync_timeout = 30;

    /**
     * Data retention period (days)
     * @var int
     */
    private $retention_days = 365;

    /**
     * Constructor
     *
     * @param AFFCD_Database_Manager $db_manager Database manager instance
     */
    public function __construct($db_manager) {
        $this->db_manager = $db_manager;
        $this->init_sync_config();
        $this->init_hooks();
    }

    /**
     * Initialize sync configuration
     */
    private function init_sync_config() {
        $this->sync_config = [
            'real_time_sync'      => true,
            'batch_sync_interval' => 300,  // 5 minutes
            'full_sync_interval'  => 3600, // 1 hour
            'conflict_resolution' => 'latest_wins',
            'data_validation'     => true,
            'compression_enabled' => true,
            'encryption_enabled'  => true,
            'sync_priorities'     => [
                'conversions'   => 1,
                'vanity_usage'  => 2,
                'analytics'     => 3,
                'security_logs' => 4
            ],
            'sync_methods'        => [
                'webhook'        => true,
                'api_pull'       => true,
                'scheduled_push' => true
            ]
        ];

        // Allow configuration filtering
        $this->sync_config = apply_filters('affcd_tracking_sync_config', $this->sync_config);
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Scheduled sync events
        add_action('affcd_batch_sync',        [$this, 'run_batch_sync']);
        add_action('affcd_full_sync',         [$this, 'run_full_sync']);
        add_action('affcd_cleanup_sync_data', [$this, 'cleanup_sync_data']);

        // Real-time sync triggers
        add_action('affcd_track_conversion', [$this, 'sync_conversion_real_time'], 10, 2);
        add_action('affcd_track_usage',      [$this, 'sync_usage_real_time'], 10, 2);

        // Admin hooks
        add_action('wp_ajax_affcd_force_sync',  [$this, 'ajax_force_sync']);
        add_action('wp_ajax_affcd_sync_status', [$this, 'ajax_sync_status']);

        // Webhook handling
        add_action('rest_api_init', [$this, 'register_webhook_endpoints']);

        // Schedule sync events if not already scheduled
        if (!wp_next_scheduled('affcd_batch_sync')) {
            wp_schedule_event(time(), 'affcd_5min', 'affcd_batch_sync');
        }

        if (!wp_next_scheduled('affcd_full_sync')) {
            wp_schedule_event(time(), 'hourly', 'affcd_full_sync');
        }

        if (!wp_next_scheduled('affcd_cleanup_sync_data')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_sync_data');
        }
    }

    /**
     * Register webhook endpoints for sync
     */
    public function register_webhook_endpoints() {
        register_rest_route('affcd/v1', '/sync/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_sync_webhook'],
            'permission_callback' => [$this, 'validate_webhook_request']
        ]);

        register_rest_route('affcd/v1', '/sync/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_sync_status'],
            'permission_callback' => [$this, 'check_sync_permissions']
        ]);
    }

    /**
     * Run batch synchronisation
     */
    public function run_batch_sync() {
        if (!$this->can_run_sync('batch')) {
            return false;
        }

        $sync_id = $this->generate_sync_id();
        $this->log_sync_start('batch', $sync_id);

        try {
            $domains = $this->get_active_sync_domains();
            $results = [];

            foreach ($domains as $domain) {
                $domain_result = $this->sync_domain_data($domain, 'batch');
                $results[$domain->domain_url] = $domain_result;

                // Small delay between domains
                usleep(100000); // 0.1s
            }

            $this->log_sync_completion('batch', $sync_id, $results);
            return true;
        } catch (Exception $e) {
            $this->log_sync_error('batch', $sync_id, $e->getMessage());
            return false;
        }
    }

    /**
     * Run full synchronisation
     */
    public function run_full_sync() {
        if (!$this->can_run_sync('full')) {
            return false;
        }

        $sync_id = $this->generate_sync_id();
        $this->log_sync_start('full', $sync_id);

        try {
            $domains = $this->get_active_sync_domains();
            $results = [];

            foreach ($domains as $domain) {
                // Full sync includes validation and conflict resolution
                $domain_result = $this->sync_domain_data($domain, 'full');
                $this->validate_sync_data($domain, $domain_result);
                $this->resolve_sync_conflicts($domain);

                $results[$domain->domain_url] = $domain_result;

                // Slight delay for full syncs
                sleep(1);
            }

            // Data integrity checks
            $this->run_data_integrity_checks();

            $this->log_sync_completion('full', $sync_id, $results);
            return true;
        } catch (Exception $e) {
            $this->log_sync_error('full', $sync_id, $e->getMessage());
            return false;
        }
    }

    /**
     * Sync conversion in real-time
     */
    public function sync_conversion_real_time($conversion_data, $domain_url) {
        if (!$this->sync_config['real_time_sync']) {
            return;
        }

        $sync_data = [
            'type'      => 'conversion',
            'data'      => $conversion_data,
            'domain'    => $domain_url,
            'timestamp' => current_time('c'),
            'priority'  => $this->sync_config['sync_priorities']['conversions']
        ];

        $this->queue_real_time_sync($sync_data);
    }

    /**
     * Sync usage data in real-time
     */
    public function sync_usage_real_time($usage_data, $domain_url) {
        if (!$this->sync_config['real_time_sync']) {
            return;
        }

        $sync_data = [
            'type'      => 'usage',
            'data'      => $usage_data,
            'domain'    => $domain_url,
            'timestamp' => current_time('c'),
            'priority'  => $this->sync_config['sync_priorities']['vanity_usage']
        ];

        $this->queue_real_time_sync($sync_data);
    }

    /**
     * Queue real-time sync data
     */
    private function queue_real_time_sync($sync_data) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('usage_tracking');

        $wpdb->insert($table_name, [
            'domain_from'  => $sync_data['domain'],
            'event_type'   => 'sync_queue',
            'status'       => 'pending',
            'request_data' => json_encode($sync_data),
            'created_at'   => current_time('mysql')
        ]);

        // Process immediately if enabled
        $this->process_real_time_sync_queue();
    }

    /**
     * Process real-time sync queue
     */
    private function process_real_time_sync_queue() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('usage_tracking');

        $pending_syncs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE event_type = 'sync_queue'
               AND status = 'pending'
             ORDER BY created_at ASC
             LIMIT %d",
            10
        ));

        foreach ($pending_syncs as $sync_item) {
            $sync_data = json_decode($sync_item->request_data, true);

            // Mark processing
            $wpdb->update(
                $table_name,
                ['status' => 'processing'],
                ['id' => $sync_item->id],
                ['%s'],
                ['%d']
            );

            // Execute
            $success = $this->execute_real_time_sync($sync_data);

            // Update status
            $wpdb->update(
                $table_name,
                [
                    'status'     => $success ? 'completed' : 'failed',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $sync_item->id],
                ['%s', '%s'],
                ['%d']
            );
        }
    }

    /**
     * Execute real-time sync
     */
    private function execute_real_time_sync($sync_data) {
        $target_domains = $this->get_sync_target_domains($sync_data['domain']);
        $success = true;

        foreach ($target_domains as $domain) {
            $webhook_url = $this->get_domain_webhook_url($domain->domain_url);
            if ($webhook_url) {
                $result = $this->send_webhook_sync($webhook_url, $sync_data, $domain);
                if (!$result['success']) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Get active sync domains
     */
    private function get_active_sync_domains() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        // Note: verification_status column assumed present per earlier schema
        return $wpdb->get_results(
            "SELECT * FROM {$table_name}
             WHERE status = 'active'
               AND verification_status = 'verified'
               AND webhook_url IS NOT NULL
             ORDER BY last_activity_at DESC"
        );
    }

    /**
     * Get sync target domains (excluding source)
     */
    private function get_sync_target_domains($source_domain) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE status = 'active'
               AND verification_status = 'verified'
               AND domain_url != %s
               AND webhook_url IS NOT NULL",
            $source_domain
        ));
    }

    /**
     * Sync domain data
     */
    private function sync_domain_data($domain, $sync_type = 'batch') {
        $results = [
            'domain'         => $domain->domain_url,
            'sync_type'      => $sync_type,
            'started_at'     => current_time('c'),
            'data_types'     => [],
            'total_records'  => 0,
            'success_count'  => 0,
            'error_count'    => 0,
            'errors'         => []
        ];

        try {
            $data_types = $this->get_sync_data_types($sync_type);

            foreach ($data_types as $data_type) {
                $type_result = $this->sync_data_type($domain, $data_type, $sync_type);
                $results['data_types'][$data_type] = $type_result;
                $results['total_records'] += $type_result['record_count'];
                $results['success_count'] += $type_result['success_count'];
                $results['error_count']   += $type_result['error_count'];

                if (!empty($type_result['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $type_result['errors']);
                }
            }
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $results['error_count']++;
        }

        $results['completed_at'] = current_time('c');
        $results['success'] = $results['error_count'] === 0;

        return $results;
    }

    /**
     * Get data types to sync
     */
    private function get_sync_data_types($sync_type) {
        $data_types = [];

        switch ($sync_type) {
            case 'batch':
                $data_types = ['conversions', 'recent_usage'];
                break;
            case 'full':
                $data_types = ['conversions', 'usage', 'analytics', 'vanity_codes'];
                break;
            case 'real_time':
                $data_types = ['conversions'];
                break;
        }

        return apply_filters('affcd_sync_data_types', $data_types, $sync_type);
    }

    /**
     * Sync specific data type
     */
    private function sync_data_type($domain, $data_type, $sync_type) {
        $result = [
            'data_type'    => $data_type,
            'record_count' => 0,
            'success_count'=> 0,
            'error_count'  => 0,
            'errors'       => []
        ];

        try {
            $data = $this->get_sync_data($domain, $data_type, $sync_type);
            $result['record_count'] = count($data);

            if (!empty($data)) {
                $send_result = $this->send_sync_data($domain, $data_type, $data);

                if ($send_result['success']) {
                    $result['success_count'] = $result['record_count'];
                } else {
                    $result['error_count'] = $result['record_count'];
                    $result['errors'][] = $send_result['error'];
                }
            }
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['error_count']++;
        }

        return $result;
    }

    /**
     * Get sync data for specific type
     */
    private function get_sync_data($domain, $data_type, $sync_type) {
        global $wpdb;
        $data = [];

        switch ($data_type) {
            case 'conversions':
                $table_name = $this->db_manager->get_table_name('usage_tracking');
                $time_limit = $sync_type === 'batch' ? '1 HOUR' : '24 HOUR';

                $data = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name}
                     WHERE domain_from = %s
                       AND event_type = 'conversion'
                       AND created_at >= DATE_SUB(NOW(), INTERVAL {$time_limit})
                     ORDER BY created_at DESC
                     LIMIT %d",
                    $domain->domain_url,
                    $this->batch_size
                ), ARRAY_A);
                break;

            case 'recent_usage':
                $table_name = $this->db_manager->get_table_name('vanity_usage');

                $data = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name}
                     WHERE domain = %s
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                     ORDER BY created_at DESC
                     LIMIT %d",
                    $domain->domain_url,
                    $this->batch_size
                ), ARRAY_A);
                break;

            case 'usage':
                $table_name = $this->db_manager->get_table_name('vanity_usage');

                $data = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name}
                     WHERE domain = %s
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     ORDER BY created_at DESC
                     LIMIT %d",
                    $domain->domain_url,
                    $this->batch_size
                ), ARRAY_A);
                break;

            case 'analytics':
                $table_name = $this->db_manager->get_table_name('analytics');

                $data = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name}
                     WHERE domain = %s
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     ORDER BY created_at DESC
                     LIMIT %d",
                    $domain->domain_url,
                    $this->batch_size
                ), ARRAY_A);
                break;

            case 'vanity_codes':
                $table_name = $this->db_manager->get_table_name('vanity_codes');

                $data = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name}
                     WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     ORDER BY updated_at DESC
                     LIMIT %d",
                    $this->batch_size
                ), ARRAY_A);
                break;
        }

        return $data;
    }

    /**
     * Send sync data to domain
     */
    private function send_sync_data($domain, $data_type, $data) {
        $webhook_url = $domain->webhook_url;

        if (!$webhook_url) {
            return [
                'success' => false,
                'error'   => 'No webhook URL configured for domain'
            ];
        }

        // Prepare sync payload
        $payload = [
            'sync_type'     => 'data_sync',
            'data_type'     => $data_type,
            'data'          => $data,
            'timestamp'     => current_time('c'),
            'source_domain' => get_site_url(),
            'checksum'      => $this->calculate_data_checksum($data)
        ];

        // Encrypt first, then compress (receiver should decompress then decrypt)
        if ($this->sync_config['encryption_enabled']) {
            $payload = $this->encrypt_sync_payload($payload, $domain->webhook_secret);
        }

        if ($this->sync_config['compression_enabled']) {
            $payload = $this->compress_sync_payload($payload);
        }

        return $this->send_webhook_sync($webhook_url, $payload, $domain);
    }

    /**
     * Send webhook sync
     */
    private function send_webhook_sync($webhook_url, $payload, $domain) {
        $headers = [
            'Content-Type'       => 'application/json',
            'User-Agent'         => 'AFFCD-Sync/1.0',
            'X-AFFCD-Signature'  => $this->generate_webhook_signature($payload, $domain->webhook_secret),
            'X-AFFCD-Timestamp'  => time()
        ];

        $response = wp_remote_post($webhook_url, [
            'headers'  => $headers,
            'body'     => json_encode($payload),
            'timeout'  => $this->sync_timeout,
            'blocking' => true,
            'sslverify'=> true
        ]);

        if (is_wp_error($response)) {
            $this->update_domain_webhook_status($domain, false);
            return [
                'success' => false,
                'error'   => $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $this->update_domain_webhook_status($domain, true);
            return [
                'success'  => true,
                'response' => $response_body
            ];
        }

        $this->update_domain_webhook_status($domain, false);
        return [
            'success' => false,
            'error'   => "HTTP {$response_code}: {$response_body}"
        ];
    }

    /**
     * Update domain webhook status
     */
    private function update_domain_webhook_status($domain, $success) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        if ($success) {
            $wpdb->update(
                $table_name,
                [
                    'webhook_last_sent' => current_time('mysql'),
                    'webhook_failures'  => 0
                ],
                ['id' => $domain->id],
                ['%s', '%d'],
                ['%d']
            );
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name}
                 SET webhook_failures = webhook_failures + 1
                 WHERE id = %d",
                $domain->id
            ));
        }
    }

    /**
     * Generate webhook signature
     */
    private function generate_webhook_signature($payload, $secret) {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Calculate data checksum
     */
    private function calculate_data_checksum($data) {
        return md5(serialize($data));
    }

    /**
     * Encrypt sync payload (AES-256-CBC)
     * Uses webhook_secret (hashed) as key, with IV derived from key hash.
     */
    private function encrypt_sync_payload($payload, $key) {
        $key_hash = hash('sha256', $key, true);
        $iv = substr($key_hash, 0, 16);

        $encrypted = openssl_encrypt(
            json_encode($payload),
            'AES-256-CBC',
            $key_hash,
            OPENSSL_RAW_DATA,
            $iv
        );

        return [
            'encrypted' => true,
            'data'      => base64_encode($encrypted)
        ];
    }

    /**
     * Decrypt sync payload (AES-256-CBC)
     */
    private function decrypt_sync_payload($payload, $key) {
        $key_hash = hash('sha256', $key, true);
        $iv = substr($key_hash, 0, 16);

        $decrypted = openssl_decrypt(
            base64_decode($payload['data']),
            'AES-256-CBC',
            $key_hash,
            OPENSSL_RAW_DATA,
            $iv
        );

        return json_decode($decrypted, true);
    }

    /**
     * Compress sync payload (gzip)
     */
    private function compress_sync_payload($payload) {
        $compressed_data = gzcompress(json_encode($payload), 6);

        return [
            'compressed' => true,
            'data'       => base64_encode($compressed_data)
        ];
    }

    /**
     * Decompress sync payload (gzip)
     */
    private function decompress_sync_payload($payload) {
        $compressed_data = base64_decode($payload['data']);
        $decompressed = gzuncompress($compressed_data);

        return json_decode($decompressed, true);
    }

    /**
     * Validate sync data (optional)
     */
    private function validate_sync_data($domain, $sync_result) {
        if (!$this->sync_config['data_validation']) {
            return true;
        }

        foreach ($sync_result['data_types'] as $data_type => $type_result) {
            if ($type_result['error_count'] > 0) {
                $this->log_validation_error($domain, $data_type, $type_result['errors']);
            }
        }

        return $sync_result['success'];
    }

    /**
     * Resolve sync conflicts
     */
    private function resolve_sync_conflicts($domain) {
        switch ($this->sync_config['conflict_resolution']) {
            case 'latest_wins':
                $this->resolve_conflicts_latest_wins($domain);
                break;
            case 'merge':
                $this->resolve_conflicts_merge($domain);
                break;
            case 'manual':
                $this->flag_conflicts_for_manual_review($domain);
                break;
        }
    }

    /**
     * Resolve conflicts using latest-wins strategy
     */
    private function resolve_conflicts_latest_wins($domain) {
        global $wpdb;

        $usage_table = $this->db_manager->get_table_name('vanity_usage');

        $wpdb->query($wpdb->prepare(
            "DELETE u1 FROM {$usage_table} u1
             INNER JOIN {$usage_table} u2
               ON u1.vanity_code_id = u2.vanity_code_id
              AND u1.session_id = u2.session_id
              AND u1.created_at < u2.created_at
             WHERE u1.domain = %s",
            $domain->domain_url
        ));
    }

    /**
     * Run data integrity checks
     */
    private function run_data_integrity_checks() {
        global $wpdb;

        $issues = [];

        // Orphaned usage records
        $usage_table = $this->db_manager->get_table_name('vanity_usage');
        $codes_table = $this->db_manager->get_table_name('vanity_codes');

        $orphaned_count = $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$usage_table} u
             LEFT JOIN {$codes_table} c ON u.vanity_code_id = c.id
             WHERE c.id IS NULL"
        );

        if ($orphaned_count > 0) {
            $issues[] = "Found {$orphaned_count} orphaned usage records";
        }

        // Duplicate conversions (rough heuristic)
        $tracking_table = $this->db_manager->get_table_name('usage_tracking');

        $duplicate_count = $wpdb->get_var(
            "SELECT COUNT(*) - COUNT(DISTINCT CONCAT_WS('|', domain_from, affiliate_code, conversion_value, DATE(created_at)))
             FROM {$tracking_table}
             WHERE event_type = 'conversion'"
        );

        if ($duplicate_count > 0) {
            $issues[] = "Found {$duplicate_count} potential duplicate conversions";
        }

        if (!empty($issues)) {
            $this->log_integrity_issues($issues);
        }
    }

    /**
     * Get domain webhook URL
     */
    private function get_domain_webhook_url($domain_url) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT webhook_url FROM {$table_name}
             WHERE domain_url = %s
               AND status = 'active'",
            $domain_url
        ));
    }

    /**
     * Check if sync can run
     */
    private function can_run_sync($sync_type) {
        if (isset($this->active_syncs[$sync_type])) {
            return false;
        }

        // Simple memory guard
        if (function_exists('memory_get_usage') && memory_get_usage(true) > (1024 * 1024 * 500)) { // 500MB
            return false;
        }

        return true;
    }

    /**
     * Generate unique sync ID
     */
    private function generate_sync_id() {
        return 'sync_' . time() . '_' . wp_generate_uuid4();
    }

    /**
     * Cleanup old sync data
     */
    public function cleanup_sync_data() {
        global $wpdb;

        $tracking_table = $this->db_manager->get_table_name('usage_tracking');

        // Clean up old sync queue items
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tracking_table}
             WHERE event_type = 'sync_queue'
               AND status IN ('completed', 'failed')
               AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            7
        ));

        // Archive / delete old tracking data (retention)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tracking_table}
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $this->retention_days
        ));
    }

    /**
     * Handle sync webhook
     */
    public function handle_sync_webhook($request) {
        $payload = $request->get_json_params();

        // Validate signature first
        if (!$this->validate_webhook_signature($request, $payload)) {
            return new WP_Error('invalid_signature', 'Invalid webhook signature', ['status' => 401]);
        }

        // Process sync data: if compressed, then decrypt (reverse of sending order)
        $result = $this->process_incoming_sync_data($payload);

        return rest_ensure_response([
            'success'   => $result,
            'timestamp' => current_time('c')
        ]);
    }

    /**
     * Get sync status
     */
    public function get_sync_status($request) {
        global $wpdb;
        $tracking_table = $this->db_manager->get_table_name('usage_tracking');

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                MAX(created_at) as last_sync
             FROM {$tracking_table}
             WHERE event_type = 'sync_queue'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ARRAY_A
        );

        return rest_ensure_response([
            'sync_enabled' => $this->sync_config['real_time_sync'],
            'statistics'   => $stats,
            'active_syncs' => count($this->active_syncs),
            'last_check'   => current_time('c')
        ]);
    }

    /**
     * Validate webhook request (basic presence of signature header)
     */
    public function validate_webhook_request($request) {
        $signature = $request->get_header('X-AFFCD-Signature');
        return !empty($signature);
    }

    /**
     * Validate webhook signature
     */
    private function validate_webhook_signature($request, $payload) {
        $signature = $request->get_header('X-AFFCD-Signature');
        $timestamp = $request->get_header('X-AFFCD-Timestamp');

        // 5-minute replay window
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $origin = $request->get_header('origin') ?: ($_SERVER['HTTP_ORIGIN'] ?? '');

        $domain = $this->get_domain_by_url($origin);
        if (!$domain || empty($domain->webhook_secret)) {
            return false;
        }

        $expected_signature = $this->generate_webhook_signature($payload, $domain->webhook_secret);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Get domain by URL
     */
    private function get_domain_by_url($url) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        // Accept both bare domain and full URL (normalize)
        $normalized = $this->normalize_domain_url($url);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE domain_url = %s",
            $normalized
        ));
    }

    /**
     * Normalize domain URL to match stored domain_url (strip scheme, path)
     */
    private function normalize_domain_url($url) {
        if (empty($url)) {
            return '';
        }
        // If full URL, parse host; else treat as already domain
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            return $host;
        }
        // Fallback: strip protocol if present
        $url = preg_replace('#^https?://#i', '', $url);
        // Remove path/port
        $url = preg_replace('#[:/].*$#', '', $url);
        return strtolower($url);
    }

    /**
     * Check sync permissions (admin-only)
     */
    public function check_sync_permissions($request) {
        return current_user_can('manage_options');
    }

    /**
     * Process incoming sync data
     */
    private function process_incoming_sync_data($payload) {
        // Decompress first (we compress after encrypt on sender)
        if (isset($payload['compressed']) && $payload['compressed']) {
            $payload = $this->decompress_sync_payload($payload);
        }

        // Then decrypt if needed
        if (isset($payload['encrypted']) && $payload['encrypted']) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $domain = $this->get_domain_by_url($origin);
            if (!$domain || empty($domain->webhook_secret)) {
                return false;
            }
            $payload = $this->decrypt_sync_payload($payload, $domain->webhook_secret);
        }

        if (!is_array($payload) || empty($payload['sync_type'])) {
            return false;
        }

        switch ($payload['sync_type']) {
            case 'data_sync':
                return $this->process_data_sync($payload);
            case 'configuration_sync':
                return $this->process_configuration_sync($payload);
            case 'test':
                return true;
            default:
                return false;
        }
    }

    /**
     * AJAX: force sync
     */
    public function ajax_force_sync() {
        check_ajax_referer('affcd_force_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $sync_type = sanitize_text_field($_POST['sync_type'] ?? 'batch');

        $result = ($sync_type === 'full')
            ? $this->run_full_sync()
            : $this->run_batch_sync();

        wp_send_json([
            'success'   => $result,
            'message'   => $result ? 'Sync completed successfully' : 'Sync failed',
            'timestamp' => current_time('c')
        ]);
    }

    /**
     * AJAX: sync status
     */
    public function ajax_sync_status() {
        check_ajax_referer('affcd_sync_status', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $status = $this->get_sync_status(new WP_REST_Request());
        wp_send_json($status->get_data());
    }

    /**
     * Log sync start
     */
    private function log_sync_start($sync_type, $sync_id) {
        $this->active_syncs[$sync_type] = [
            'started_at' => microtime(true), // use microtime for accurate duration
            'pid'        => function_exists('getmypid') ? getmypid() : null,
            'sync_id'    => $sync_id
        ];

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('analytics');

        $wpdb->insert($table_name, [
            'event_type' => 'sync_started',
            'entity_type'=> 'system',
            'domain'     => get_site_url(),
            'ip_address' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'event_data' => json_encode([
                'sync_type'     => $sync_type,
                'sync_id'       => $sync_id,
                'configuration' => $this->sync_config
            ]),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Log sync completion
     * (Compute duration BEFORE unsetting active state)
     */
    private function log_sync_completion($sync_type, $sync_id, $results) {
        $duration = $this->calculate_sync_duration($sync_type);
        unset($this->active_syncs[$sync_type]);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('analytics');

        $wpdb->insert($table_name, [
            'event_type' => 'sync_completed',
            'entity_type'=> 'system',
            'domain'     => get_site_url(),
            'ip_address' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'event_data' => json_encode([
                'sync_type' => $sync_type,
                'sync_id'   => $sync_id,
                'results'   => $results,
                'duration'  => $duration
            ]),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Log sync error
     */
    private function log_sync_error($sync_type, $sync_id, $error_message) {
        $duration = $this->calculate_sync_duration($sync_type);
        unset($this->active_syncs[$sync_type]);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('security_logs');

        $wpdb->insert($table_name, [
            'event_type'           => 'sync_error',
            'severity'             => 'high',
            'identifier'           => $sync_id,
            'ip_address'           => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'event_data'           => json_encode([
                'sync_type'     => $sync_type,
                'error_message' => $error_message,
                'configuration' => $this->sync_config,
                'duration'      => $duration
            ]),
            'investigation_status' => 'pending',
            'created_at'           => current_time('mysql')
        ]);
    }

    /**
     * Log validation error
     */
    private function log_validation_error($domain, $data_type, $errors) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('security_logs');

        $wpdb->insert($table_name, [
            'event_type'           => 'sync_validation_error',
            'severity'             => 'medium',
            'identifier'           => $domain->domain_url,
            'ip_address'           => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'domain'               => $domain->domain_url,
            'event_data'           => json_encode([
                'data_type' => $data_type,
                'errors'    => $errors
            ]),
            'investigation_status' => 'pending',
            'created_at'           => current_time('mysql')
        ]);
    }

    /**
     * Log integrity issues
     */
    private function log_integrity_issues($issues) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('security_logs');

        $wpdb->insert($table_name, [
            'event_type'           => 'data_integrity_issue',
            'severity'             => 'high',
            'identifier'           => 'data_integrity_check',
            'ip_address'           => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'event_data'           => json_encode([
                'issues'          => $issues,
                'check_timestamp' => current_time('c')
            ]),
            'investigation_status' => 'pending',
            'created_at'           => current_time('mysql')
        ]);
    }

    /**
     * Calculate sync duration (seconds)
     */
    private function calculate_sync_duration($sync_type) {
        if (empty($this->active_syncs[$sync_type]['started_at'])) {
            return 0;
        }
        return round(microtime(true) - $this->active_syncs[$sync_type]['started_at'], 4);
    }

    /**
     * Resolve conflicts using merge strategy (placeholder for business rules)
     */
    private function resolve_conflicts_merge($domain) {
        // Implement merge conflict resolution if needed.
    }

    /**
     * Flag conflicts for manual review
     */
    private function flag_conflicts_for_manual_review($domain) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('security_logs');

        $wpdb->insert($table_name, [
            'event_type'           => 'sync_conflict_detected',
            'severity'             => 'medium',
            'identifier'           => $domain->domain_url,
            'ip_address'           => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'domain'               => $domain->domain_url,
            'event_data'           => json_encode([
                'requires_manual_review' => true,
                'detected_at'            => current_time('c')
            ]),
            'investigation_status' => 'pending',
            'created_at'           => current_time('mysql')
        ]);
    }

    /**
     * Process data sync
     */
    private function process_data_sync($payload) {
        $data_type = $payload['data'] ?? null;
        $type = $payload['data_type'] ?? '';

        try {
            switch ($type) {
                case 'conversions':
                    return $this->import_conversion_data($payload['data']);
                case 'usage':
                case 'recent_usage':
                    return $this->import_usage_data($payload['data']);
                case 'analytics':
                    return $this->import_analytics_data($payload['data']);
                case 'vanity_codes':
                    // If needed, add import logic for vanity codes
                    return true;
                default:
                    return false;
            }
        } catch (Exception $e) {
            error_log('AFFCD Tracking Sync: Data sync error - ' . $e->get_message());
            return false;
        }
    }

    /**
     * Process configuration sync
     */
    private function process_configuration_sync($payload) {
        $config_updates = $payload['configuration'] ?? [];

        foreach ($config_updates as $key => $value) {
            if (array_key_exists($key, $this->sync_config)) {
                $this->sync_config[$key] = $value;
            }
        }

        return true;
    }

    /**
     * Import conversion data
     */
    private function import_conversion_data($conversions) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('usage_tracking');

        $success_count = 0;

        foreach ($conversions as $conversion) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name}
                 WHERE domain_from = %s
                   AND conversion_value = %s
                   AND created_at = %s",
                $conversion['domain_from'],
                $conversion['conversion_value'],
                $conversion['created_at']
            ));

            if (!$existing) {
                $result = $wpdb->insert($table_name, $conversion);
                if ($result) {
                    $success_count++;
                }
            }
        }

        return $success_count > 0;
    }

    /**
     * Import usage data
     */
    private function import_usage_data($usage_records) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('vanity_usage');

        $success_count = 0;

        foreach ($usage_records as $usage) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name}
                 WHERE session_id = %s
                   AND vanity_code_id = %s
                   AND created_at = %s",
                $usage['session_id'],
                $usage['vanity_code_id'],
                $usage['created_at']
            ));

            if (!$existing) {
                $result = $wpdb->insert($table_name, $usage);
                if ($result) {
                    $success_count++;
                }
            }
        }

        return $success_count > 0;
    }

    /**
     * Import analytics data
     */
    private function import_analytics_data($analytics_records) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('analytics');

        $success_count = 0;

        foreach ($analytics_records as $record) {
            $result = $wpdb->insert($table_name, $record);
            if ($result) {
                $success_count++;
            }
        }

        return $success_count > 0;
    }

    /**
     * Get sync configuration
     */
    public function get_sync_configuration() {
        return $this->sync_config;
    }

    /**
     * Update sync configuration
     */
    public function update_sync_configuration($config) {
        $this->sync_config = array_merge($this->sync_config, (array)$config);
        return update_option('affcd_sync_config', $this->sync_config);
    }

    /**
     * Get sync statistics
     */
    public function get_sync_statistics($days = 7) {
        global $wpdb;
        $analytics_table = $this->db_manager->get_table_name('analytics');

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(CASE WHEN event_type = 'sync_started'   THEN 1 END) as total_syncs,
                COUNT(CASE WHEN event_type = 'sync_completed' THEN 1 END) as completed_syncs,
                COUNT(CASE WHEN event_type = 'sync_error'     THEN 1 END) as failed_syncs,
                AVG(CASE WHEN event_type = 'sync_completed'
                         THEN JSON_EXTRACT(event_data, '$.duration')
                    END) as avg_duration
             FROM {$analytics_table}
             WHERE event_type IN ('sync_started', 'sync_completed', 'sync_error')
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A);

        $total_syncs     = intval($stats['total_syncs'] ?? 0);
        $completed_syncs = intval($stats['completed_syncs'] ?? 0);
        $success_rate    = $total_syncs > 0 ? ($completed_syncs / $total_syncs) * 100 : 0;

        return [
            'total_syncs'      => $total_syncs,
            'completed_syncs'  => $completed_syncs,
            'failed_syncs'     => intval($stats['failed_syncs'] ?? 0),
            'success_rate'     => round($success_rate, 2),
            'average_duration' => round(floatval($stats['avg_duration'] ?? 0), 2),
            'period_days'      => $days
        ];
    }

    /**
     * Test sync connectivity to domain
     */
    public function test_sync_connectivity($domain_url) {
        $domain = $this->get_domain_by_url($domain_url);

        if (!$domain) {
            return [
                'success' => false,
                'error'   => 'Domain not found'
            ];
        }

        if (!$domain->webhook_url) {
            return [
                'success' => false,
                'error'   => 'No webhook URL configured'
            ];
        }

        $test_payload = [
            'sync_type'  => 'test',
            'timestamp'  => current_time('c'),
            'test_data'  => 'connectivity_check'
        ];

        $result = $this->send_webhook_sync($domain->webhook_url, $test_payload, $domain);

        return [
            'success'       => $result['success'],
            'response_time' => $this->measure_response_time($domain->webhook_url),
            'error'         => $result['error'] ?? null
        ];
    }

    /**
     * Measure response time to webhook URL
     */
    private function measure_response_time($webhook_url) {
        $start = microtime(true);

        wp_remote_get($webhook_url, [
            'timeout'  => 5,
            'blocking' => true
        ]);

        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Force sync for specific domain
     */
    public function force_domain_sync($domain_url, $sync_type = 'batch') {
        $domain = $this->get_domain_by_url($domain_url);

        if (!$domain) {
            return [
                'success' => false,
                'error'   => 'Domain not found'
            ];
        }

        $sync_result = $this->sync_domain_data($domain, $sync_type);

        return [
            'success'   => $sync_result['success'],
            'results'   => $sync_result,
            'timestamp' => current_time('c')
        ];
    }
}
