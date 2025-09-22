<?php
/**
 * Webhook Handler Class
 *
 * Manages webhook notifications to client domains including event handling,
 * delivery management, retry logic, and failure handling.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Webhook_Handler {

    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private $max_retries = 3;

    /**
     * Retry delay in seconds
     *
     * @var array
     */
    private $retry_delays = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Supported webhook events
     *
     * @var array
     */
    private $supported_events = [
        'code_validated',
        'code_created',
        'code_updated',
        'code_deleted',
        'domain_added',
        'domain_updated',
        'domain_suspended',
        'conversion_tracked',
        'security_alert',
        'rate_limit_exceeded'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_affcd_test_webhook', [$this, 'ajax_test_webhook']);
        add_action('wp_ajax_affcd_retry_webhook', [$this, 'ajax_retry_webhook']);
        
        // Schedule webhook processing
        add_action('affcd_process_webhook_queue', [$this, 'process_webhook_queue']);
        add_action('affcd_retry_failed_webhooks', [$this, 'retry_failed_webhooks']);
        
        // Hook into plugin events
        add_action('affcd_code_validated', [$this, 'handle_code_validated'], 10, 3);
        add_action('affcd_code_created', [$this, 'handle_code_created'], 10, 2);
        add_action('affcd_domain_added', [$this, 'handle_domain_added'], 10, 2);
        add_action('affcd_security_alert', [$this, 'handle_security_alert'], 10, 3);
    }

    /**
     * Initialse webhook handler
     */
    public function init() {
        $this->schedule_webhook_tasks();
        $this->maybe_create_webhook_table();
    }

    /**
     * Schedule webhook processing tasks
     */
    private function schedule_webhook_tasks() {
        if (!wp_next_scheduled('affcd_process_webhook_queue')) {
            wp_schedule_event(time(), 'every_minute', 'affcd_process_webhook_queue');
        }
        
        if (!wp_next_scheduled('affcd_retry_failed_webhooks')) {
            wp_schedule_event(time(), 'hourly', 'affcd_retry_failed_webhooks');
        }
    }

    /**
     * Create webhook queue table if needed
     */
    private function maybe_create_webhook_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                domain varchar(255) NOT NULL,
                event varchar(100) NOT NULL,
                payload longtext NOT NULL,
                webhook_url varchar(500) NOT NULL,
                secret varchar(128) DEFAULT NULL,
                attempts int unsigned DEFAULT 0,
                max_attempts int unsigned DEFAULT 3,
                status enum('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
                last_attempt datetime DEFAULT NULL,
                next_attempt datetime DEFAULT NULL,
                response_code int unsigned DEFAULT NULL,
                response_body text,
                error_message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                sent_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_status (status),
                KEY idx_domain (domain),
                KEY idx_event (event),
                KEY idx_next_attempt (next_attempt),
                KEY idx_created_at (created_at),
                KEY idx_webhook_processing (status, next_attempt)
            ) {$charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Send webhook notification
     *
     * @param string $domain Target domain
     * @param string $event Event type
     * @param array $data Event data
     * @param bool $async Send asynchronously
     * @return bool|array Success status or response data
     */
    public function send_webhook($domain, $event, $data = [], $async = true) {
        if (!$this->is_event_supported($event)) {
            return false;
        }

        $domain_record = affcd_is_domain_authorised($domain);
        if (!$domain_record || empty($domain_record->webhook_url)) {
            return false;
        }

        // Check if event is enabled for this domain
        if (!$this->is_event_enabled($domain_record, $event)) {
            return false;
        }

        $payload = $this->prepare_payload($domain, $event, $data);
        
        if ($async) {
            return $this->queue_webhook($domain_record, $event, $payload);
        } else {
            return $this->send_webhook_immediately($domain_record, $event, $payload);
        }
    }

    /**
     * Queue webhook for async processing
     *
     * @param object $domain_record Domain record
     * @param string $event Event type
     * @param array $payload Webhook payload
     * @return bool Success status
     */
    private function queue_webhook($domain_record, $event, $payload) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'domain' => $domain_record->domain_url,
                'event' => $event,
                'payload' => wp_json_encode($payload),
                'webhook_url' => $domain_record->webhook_url,
                'secret' => $domain_record->webhook_secret,
                'max_attempts' => $this->max_retries,
                'next_attempt' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Send webhook immediately
     *
     * @param object $domain_record Domain record
     * @param string $event Event type
     * @param array $payload Webhook payload
     * @return array Response data
     */
    private function send_webhook_immediately($domain_record, $event, $payload) {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION,
            'X-AFFCD-Event' => $event,
            'X-AFFCD-Domain' => $domain_record->domain_url,
            'X-AFFCD-Timestamp' => time()
        ];

        // Add signature if secret is configured
        if (!empty($domain_record->webhook_secret)) {
            $signature = $this->generate_signature($payload, $domain_record->webhook_secret);
            $headers['X-AFFCD-Signature'] = $signature;
        }

        $response = wp_remote_post($domain_record->webhook_url, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 30,
            'blocking' => true
        ]);

        $response_data = [
            'success' => !is_wp_error($response),
            'response_code' => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
            'response_body' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)
        ];

        $this->update_domain_webhook_stats($domain_record, $response_data['success']);

        return $response_data;
    }

    /**
     * Process webhook queue
     */
    public function process_webhook_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $current_time = current_time('mysql');
        
        // Get pending webhooks ready to be sent
        $webhooks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status IN ('pending', 'failed') 
             AND (next_attempt IS NULL OR next_attempt <= %s)
             AND attempts < max_attempts
             ORDER BY created_at ASC
             LIMIT 50",
            $current_time
        ));

        foreach ($webhooks as $webhook) {
            $this->process_single_webhook($webhook);
        }
    }

    /**
     * Process single webhook
     *
     * @param object $webhook Webhook record
     */
    private function process_single_webhook($webhook) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        // Mark as processing
        $wpdb->update(
            $table_name,
            [
                'status' => 'processing',
                'last_attempt' => current_time('mysql')
            ],
            ['id' => $webhook->id],
            ['%s', '%s'],
            ['%d']
        );

        $payload = json_decode($webhook->payload, true);
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION,
            'X-AFFCD-Event' => $webhook->event,
            'X-AFFCD-Domain' => $webhook->domain,
            'X-AFFCD-Timestamp' => time()
        ];

        // Add signature if secret is configured
        if (!empty($webhook->secret)) {
            $signature = $this->generate_signature($payload, $webhook->secret);
            $headers['X-AFFCD-Signature'] = $signature;
        }

        $response = wp_remote_post($webhook->webhook_url, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 30,
            'blocking' => true
        ]);

        $response_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        $response_body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
        $success = !is_wp_error($response) && $response_code >= 200 && $response_code < 300;

        $update_data = [
            'attempts' => $webhook->attempts + 1,
            'response_code' => $response_code,
            'response_body' => $response_body
        ];

        if ($success) {
            $update_data['status'] = 'sent';
            $update_data['sent_at'] = current_time('mysql');
        } else {
            $update_data['error_message'] = $response_body;
            
            if ($webhook->attempts + 1 >= $webhook->max_attempts) {
                $update_data['status'] = 'failed';
            } else {
                $update_data['status'] = 'pending';
                $update_data['next_attempt'] = $this->calculate_next_attempt($webhook->attempts + 1);
            }
        }

        $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $webhook->id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );

        // Log webhook attempt
        affcd_log_activity('webhook_attempt', [
            'webhook_id' => $webhook->id,
            'domain' => $webhook->domain,
            'event' => $webhook->event,
            'success' => $success,
            'response_code' => $response_code,
            'attempts' => $webhook->attempts + 1
        ]);
    }

    /**
     * Calculate next attempt time
     *
     * @param int $attempt_number Attempt number
     * @return string Next attempt datetime
     */
    private function calculate_next_attempt($attempt_number) {
        $delay = $this->retry_delays[$attempt_number - 1] ?? 3600; // Default 1 hour
        return date('Y-m-d H:i:s', time() + $delay);
    }

    /**
     * Retry failed webhooks
     */
    public function retry_failed_webhooks() {
        global $wpdb;
        
        <?php
/**
 * Webhook Handler Class
 *
 * Manages webhook notifications to client domains including event handling,
 * delivery management, retry logic, and failure handling.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Webhook_Handler {

    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private $max_retries = 3;

    /**
     * Retry delay in seconds
     *
     * @var array
     */
    private $retry_delays = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Supported webhook events
     *
     * @var array
     */
    private $supported_events = [
        'code_validated',
        'code_created',
        'code_updated',
        'code_deleted',
        'domain_added',
        'domain_updated',
        'domain_suspended',
        'conversion_tracked',
        'security_alert',
        'rate_limit_exceeded'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_affcd_test_webhook', [$this, 'ajax_test_webhook']);
        add_action('wp_ajax_affcd_retry_webhook', [$this, 'ajax_retry_webhook']);
        
        // Schedule webhook processing
        add_action('affcd_process_webhook_queue', [$this, 'process_webhook_queue']);
        add_action('affcd_retry_failed_webhooks', [$this, 'retry_failed_webhooks']);
        
        // Hook into plugin events
        add_action('affcd_code_validated', [$this, 'handle_code_validated'], 10, 3);
        add_action('affcd_code_created', [$this, 'handle_code_created'], 10, 2);
        add_action('affcd_domain_added', [$this, 'handle_domain_added'], 10, 2);
        add_action('affcd_security_alert', [$this, 'handle_security_alert'], 10, 3);
    }

    /**
     * Initialse webhook handler
     */
    public function init() {
        $this->schedule_webhook_tasks();
        $this->maybe_create_webhook_table();
    }

    /**
     * Schedule webhook processing tasks
     */
    private function schedule_webhook_tasks() {
        if (!wp_next_scheduled('affcd_process_webhook_queue')) {
            wp_schedule_event(time(), 'every_minute', 'affcd_process_webhook_queue');
        }
        
        if (!wp_next_scheduled('affcd_retry_failed_webhooks')) {
            wp_schedule_event(time(), 'hourly', 'affcd_retry_failed_webhooks');
        }
    }

    /**
     * Create webhook queue table if needed
     */
    private function maybe_create_webhook_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $retry_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        // Reset failed webhooks that haven't been retried recently
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET status = 'pending', next_attempt = NOW() 
             WHERE status = 'failed' 
             AND attempts < max_attempts 
             AND last_attempt < %s",
            $retry_time
        ));
    }

    /**
     * Prepare webhook payload
     *
     * @param string $domain Domain
     * @param string $event Event type
     * @param array $data Event data
     * @return array Prepared payload
     */
    private function prepare_payload($domain, $event, $data) {
        $payload = [
            'id' => wp_generate_uuid4(),
            'event' => $event,
            'domain' => $domain,
            'timestamp' => current_time('mysql'),
            'api_version' => '1.0',
            'data' => $data
        ];

        // Add metadata
        $payload['meta'] = [
            'source' => home_url(),
            'plugin_version' => AFFCD_VERSION,
            'wordpress_version' => get_bloginfo('version')
        ];

        return $payload;
    }

    /**
     * Generate webhook signature
     *
     * @param array $payload Payload data
     * @param string $secret Webhook secret
     * @return string Generated signature
     */
    private function generate_signature($payload, $secret) {
        $payload_string = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return 'sha256=' . hash_hmac('sha256', $payload_string, $secret);
    }

    /**
     * Check if event is supported
     *
     * @param string $event Event type
     * @return bool Is supported
     */
    private function is_event_supported($event) {
        return in_array($event, $this->supported_events);
    }

    /**
     * Check if event is enabled for domain
     *
     * @param object $domain_record Domain record
     * @param string $event Event type
     * @return bool Is enabled
     */
    private function is_event_enabled($domain_record, $event) {
        if (empty($domain_record->webhook_events)) {
            return true; // All events enabled by default
        }

        $enabled_events = json_decode($domain_record->webhook_events, true);
        return is_array($enabled_events) && in_array($event, $enabled_events);
    }

    /**
     * Update domain webhook statistics
     *
     * @param object $domain_record Domain record
     * @param bool $success Success status
     */
    private function update_domain_webhook_stats($domain_record, $success) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$domains_table} SET 
             webhook_last_sent = NOW(),
             webhook_failures = CASE WHEN %d THEN 0 ELSE webhook_failures + 1 END
             WHERE id = %d",
            $success ? 1 : 0,
            $domain_record->id
        ));
    }

    /**
     * Handle code validated event
     *
     * @param string $code Validated code
     * @param string $domain Source domain
     * @param array $result Validation result
     */
    public function handle_code_validated($code, $domain, $result) {
        if (!$result['valid']) {
            return;
        }

        $this->send_webhook($domain, 'code_validated', [
            'code' => $code,
            'affiliate_id' => $result['affiliate_id'] ?? null,
            'discount' => $result['discount'] ?? null,
            'validation_time' => current_time('mysql')
        ]);
    }

    /**
     * Handle code created event
     *
     * @param int $code_id Code ID
     * @param array $code_data Code data
     */
    public function handle_code_created($code_id, $code_data) {
        // Send to all active domains
        $this->broadcast_to_all_domains('code_created', [
            'code_id' => $code_id,
            'vanity_code' => $code_data['vanity_code'],
            'affiliate_id' => $code_data['affiliate_id'],
            'status' => $code_data['status'],
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Handle domain added event
     *
     * @param int $domain_id Domain ID
     * @param array $domain_data Domain data
     */
    public function handle_domain_added($domain_id, $domain_data) {
        // Send welcome webhook to the new domain
        $this->send_webhook($domain_data['domain_url'], 'domain_added', [
            'domain_id' => $domain_id,
            'domain_name' => $domain_data['domain_name'] ?? '',
            'status' => $domain_data['status'],
            'welcome_message' => __('Welcome to the affiliate network!', 'affiliatewp-cross-domain-plugin-suite')
        ]);
    }

    /**
     * Handle security alert event
     *
     * @param string $alert_type Alert type
     * @param string $domain Affected domain
     * @param array $alert_data Alert data
     */
    public function handle_security_alert($alert_type, $domain, $alert_data) {
        $this->send_webhook($domain, 'security_alert', [
            'alert_type' => $alert_type,
            'severity' => $alert_data['severity'] ?? 'medium',
            'message' => $alert_data['message'] ?? '',
            'ip_address' => $alert_data['ip_address'] ?? '',
            'action_taken' => $alert_data['action_taken'] ?? '',
            'alert_time' => current_time('mysql')
        ]);
    }

    /**
     * Broadcast event to all active domains
     *
     * @param string $event Event type
     * @param array $data Event data
     * @return int Number of webhooks queued
     */
    public function broadcast_to_all_domains($event, $data) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        $domains = $wpdb->get_results(
            "SELECT domain_url, webhook_url, webhook_secret, webhook_events 
             FROM {$domains_table} 
             WHERE status = 'active' 
             AND webhook_url IS NOT NULL 
             AND webhook_url != ''"
        );

        $queued_count = 0;
        foreach ($domains as $domain_record) {
            if ($this->is_event_enabled($domain_record, $event)) {
                if ($this->queue_webhook($domain_record, $event, $this->prepare_payload($domain_record->domain_url, $event, $data))) {
                    $queued_count++;
                }
            }
        }

        return $queued_count;
    }

    /**
     * Test webhook endpoint
     *
     * @param string $webhook_url Webhook URL
     * @param string $secret Optional secret
     * @return array Test result
     */
    public function test_webhook($webhook_url, $secret = '') {
        $test_payload = [
            'id' => wp_generate_uuid4(),
            'event' => 'webhook_test',
            'domain' => home_url(),
            'timestamp' => current_time('mysql'),
            'api_version' => '1.0',
            'data' => [
                'message' => __('This is a test webhook from AffiliateWP Cross Domain Plugin Suite', 'affiliatewp-cross-domain-plugin-suite'),
                'test_time' => current_time('mysql')
            ],
            'meta' => [
                'source' => home_url(),
                'plugin_version' => AFFCD_VERSION,
                'wordpress_version' => get_bloginfo('version')
            ]
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION,
            'X-AFFCD-Event' => 'webhook_test',
            'X-AFFCD-Domain' => home_url(),
            'X-AFFCD-Timestamp' => time()
        ];

        if (!empty($secret)) {
            $signature = $this->generate_signature($test_payload, $secret);
            $headers['X-AFFCD-Signature'] = $signature;
        }

        $start_time = microtime(true);
        $response = wp_remote_post($webhook_url, [
            'headers' => $headers,
            'body' => wp_json_encode($test_payload),
            'timeout' => 30,
            'blocking' => true
        ]);
        $response_time = round((microtime(true) - $start_time) * 1000, 2);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'response_time' => $response_time
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        return [
            'success' => $response_code >= 200 && $response_code < 300,
            'response_code' => $response_code,
            'response_body' => $response_body,
            'response_time' => $response_time,
            'headers_sent' => $headers
        ];
    }

    /**
     * Get webhook queue status
     *
     * @return array Queue status
     */
    public function get_queue_status() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$table_name} 
             GROUP BY status",
            OBJECT_K
        );

        $recent_activity = $wpdb->get_results(
            "SELECT domain, event, status, attempts, created_at, sent_at 
             FROM {$table_name} 
             ORDER BY created_at DESC 
             LIMIT 20"
        );

        return [
            'pending' => $status_counts['pending']->count ?? 0,
            'processing' => $status_counts['processing']->count ?? 0,
            'sent' => $status_counts['sent']->count ?? 0,
            'failed' => $status_counts['failed']->count ?? 0,
            'cancelled' => $status_counts['cancelled']->count ?? 0,
            'recent_activity' => $recent_activity
        ];
    }

    /**
     * Clean webhook queue
     *
     * @param int $days_old Days to keep data
     * @return int Rows deleted
     */
    public function clean_webhook_queue($days_old = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE status IN ('sent', 'failed', 'cancelled') 
             AND created_at < %s",
            $cutoff_date
        ));
    }

    /**
     * Cancel webhook
     *
     * @param int $webhook_id Webhook ID
     * @return bool Success status
     */
    public function cancel_webhook($webhook_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        $result = $wpdb->update(
            $table_name,
            ['status' => 'cancelled'],
            ['id' => $webhook_id, 'status' => 'pending'],
            ['%s'],
            ['%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Retry webhook
     *
     * @param int $webhook_id Webhook ID
     * @return bool Success status
     */
    public function retry_webhook($webhook_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'pending',
                'attempts' => 0,
                'next_attempt' => current_time('mysql'),
                'error_message' => null
            ],
            ['id' => $webhook_id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * AJAX test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $webhook_url = esc_url_raw($_POST['webhook_url'] ?? '');
        $secret = sanitize_text_field($_POST['secret'] ?? '');

        if (empty($webhook_url)) {
            wp_send_json_error(__('Webhook URL is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $this->test_webhook($webhook_url, $secret);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX retry webhook
     */
    public function ajax_retry_webhook() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $webhook_id = absint($_POST['webhook_id'] ?? 0);
        
        if (empty($webhook_id)) {
            wp_send_json_error(__('Webhook ID is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $success = $this->retry_webhook($webhook_id);
        
        if ($success) {
            wp_send_json_success(__('Webhook queued for retry', 'affiliatewp-cross-domain-plugin-suite'));
        } else {
            wp_send_json_error(__('Failed to queue webhook for retry', 'affiliatewp-cross-domain-plugin-suite'));
        }
    }

    /**
     * Get supported events
     *
     * @return array Supported events with descriptions
     */
    public function get_supported_events() {
        return [
            'code_validated' => __('Fired when an affiliate code is successfully validated', 'affiliatewp-cross-domain-plugin-suite'),
            'code_created' => __('Fired when a new vanity code is created', 'affiliatewp-cross-domain-plugin-suite'),
            'code_updated' => __('Fired when a vanity code is updated', 'affiliatewp-cross-domain-plugin-suite'),
            'code_deleted' => __('Fired when a vanity code is deleted', 'affiliatewp-cross-domain-plugin-suite'),
            'domain_added' => __('Fired when a new domain is authorised', 'affiliatewp-cross-domain-plugin-suite'),
            'domain_updated' => __('Fired when domain settings are updated', 'affiliatewp-cross-domain-plugin-suite'),
            'domain_suspended' => __('Fired when a domain is suspended', 'affiliatewp-cross-domain-plugin-suite'),
            'conversion_tracked' => __('Fired when a conversion is tracked', 'affiliatewp-cross-domain-plugin-suite'),
            'security_alert' => __('Fired when a security issue is detected', 'affiliatewp-cross-domain-plugin-suite'),
            'rate_limit_exceeded' => __('Fired when rate limits are exceeded', 'affiliatewp-cross-domain-plugin-suite')
        ];
    }
}d_webhook_queue';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                domain varchar(255) NOT NULL,
                event varchar(100) NOT NULL,
                payload longtext NOT NULL,
                webhook_url varchar(500) NOT NULL,
                secret varchar(128) DEFAULT NULL,
                attempts int unsigned DEFAULT 0,
                max_attempts int unsigned DEFAULT 3,
                status enum('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
                last_attempt datetime DEFAULT NULL,
                next_attempt datetime DEFAULT NULL,
                response_code int unsigned DEFAULT NULL,
                response_body text,
                error_message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                sent_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_status (status),
                KEY idx_domain (domain),
                KEY idx_event (event),
                KEY idx_next_attempt (next_attempt),
                KEY idx_created_at (created_at),
                KEY idx_webhook_processing (status, next_attempt)
            ) {$charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Send webhook notification
     *
     * @param string $domain Target domain
     * @param string $event Event type
     * @param array $data Event data
     * @param bool $async Send asynchronously
     * @return bool|array Success status or response data
     */
    public function send_webhook($domain, $event, $data = [], $async = true) {
        if (!$this->is_event_supported($event)) {
            return false;
        }

        $domain_record = affcd_is_domain_authorised($domain);
        if (!$domain_record || empty($domain_record->webhook_url)) {
            return false;
        }

        // Check if event is enabled for this domain
        if (!$this->is_event_enabled($domain_record, $event)) {
            return false;
        }

        $payload = $this->prepare_payload($domain, $event, $data);
        
        if ($async) {
            return $this->queue_webhook($domain_record, $event, $payload);
        } else {
            return $this->send_webhook_immediately($domain_record, $event, $payload);
        }
    }

    /**
     * Queue webhook for async processing
     *
     * @param object $domain_record Domain record
     * @param string $event Event type
     * @param array $payload Webhook payload
     * @return bool Success status
     */
    private function queue_webhook($domain_record, $event, $payload) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'domain' => $domain_record->domain_url,
                'event' => $event,
                'payload' => wp_json_encode($payload),
                'webhook_url' => $domain_record->webhook_url,
                'secret' => $domain_record->webhook_secret,
                'max_attempts' => $this->max_retries,
                'next_attempt' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Send webhook immediately
     *
     * @param object $domain_record Domain record
     * @param string $event Event type
     * @param array $payload Webhook payload
     * @return array Response data
     */
    private function send_webhook_immediately($domain_record, $event, $payload) {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION,
            'X-AFFCD-Event' => $event,
            'X-AFFCD-Domain' => $domain_record->domain_url,
            'X-AFFCD-Timestamp' => time()
        ];

        // Add signature if secret is configured
        if (!empty($domain_record->webhook_secret)) {
            $signature = $this->generate_signature($payload, $domain_record->webhook_secret);
            $headers['X-AFFCD-Signature'] = $signature;
        }

        $response = wp_remote_post($domain_record->webhook_url, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 30,
            'blocking' => true
        ]);

        $response_data = [
            'success' => !is_wp_error($response),
            'response_code' => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
            'response_body' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)
        ];

        $this->update_domain_webhook_stats($domain_record, $response_data['success']);

        return $response_data;
    }

    /**
     * Process webhook queue
     */
    public function process_webhook_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $current_time = current_time('mysql');
        
        // Get pending webhooks ready to be sent
        $webhooks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status IN ('pending', 'failed') 
             AND (next_attempt IS NULL OR next_attempt <= %s)
             AND attempts < max_attempts
             ORDER BY created_at ASC
             LIMIT 50",
            $current_time
        ));

        foreach ($webhooks as $webhook) {
            $this->process_single_webhook($webhook);
        }
    }

    /**
     * Process single webhook
     *
     * @param object $webhook Webhook record
     */
    private function process_single_webhook($webhook) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        // Mark as processing
        $wpdb->update(
            $table_name,
            [
                'status' => 'processing',
                'last_attempt' => current_time('mysql')
            ],
            ['id' => $webhook->id],
            ['%s', '%s'],
            ['%d']
        );

        $payload = json_decode($webhook->payload, true);
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION,
            'X-AFFCD-Event' => $webhook->event,
            'X-AFFCD-Domain' => $webhook->domain,
            'X-AFFCD-Timestamp' => time()
        ];

        // Add signature if secret is configured
        if (!empty($webhook->secret)) {
            $signature = $this->generate_signature($payload, $webhook->secret);
            $headers['X-AFFCD-Signature'] = $signature;
        }

        $response = wp_remote_post($webhook->webhook_url, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 30,
            'blocking' => true
        ]);

        $response_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        $response_body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
        $success = !is_wp_error($response) && $response_code >= 200 && $response_code < 300;

        $update_data = [
            'attempts' => $webhook->attempts + 1,
            'response_code' => $response_code,
            'response_body' => $response_body
        ];

        if ($success) {
            $update_data['status'] = 'sent';
            $update_data['sent_at'] = current_time('mysql');
        } else {
            $update_data['error_message'] = $response_body;
            
            if ($webhook->attempts + 1 >= $webhook->max_attempts) {
                $update_data['status'] = 'failed';
            } else {
                $update_data['status'] = 'pending';
                $update_data['next_attempt'] = $this->calculate_next_attempt($webhook->attempts + 1);
            }
        }

        $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $webhook->id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );

        // Log webhook attempt
        affcd_log_activity('webhook_attempt', [
            'webhook_id' => $webhook->id,
            'domain' => $webhook->domain,
            'event' => $webhook->event,
            'success' => $success,
            'response_code' => $response_code,
            'attempts' => $webhook->attempts + 1
        ]);
    }

    /**
     * Calculate next attempt time
     *
     * @param int $attempt_number Attempt number
     * @return string Next attempt datetime
     */
    private function calculate_next_attempt($attempt_number) {
        $delay = $this->retry_delays[$attempt_number - 1] ?? 3600; // Default 1 hour
        return date('Y-m-d H:i:s', time() + $delay);
    }

       /**
     * Retry failed webhooks
     */
    public function retry_failed_webhooks() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $retry_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        // Reset failed webhooks that haven't been retried recently
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET status = 'pending', next_attempt = NOW() 
             WHERE status = 'failed' 
             AND attempts < max_attempts 
             AND last_attempt < %s",
            $retry_time
        ));
        
        // Log retry operation
        affcd_log_activity('webhook_retry_batch', [
            'retry_time_cutoff' => $retry_time,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Prepare webhook payload
     *
     * @param string $domain Domain
     * @param string $event Event type
     * @param array $data Event data
     * @return array Prepared payload
     */
    private function prepare_payload($domain, $event, $data) {
        $payload = [
            'id' => wp_generate_uuid4(),
            'event' => $event,
            'domain' => $domain,
            'timestamp' => current_time('mysql'),
            'api_version' => '1.0',
            'data' => $data
        ];

        // Add metadata
        $payload['meta'] = [
            'source' => home_url(),
            'plugin_version' => AFFCD_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'user_agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION
        ];

        return $payload;
    }

    /**
     * Generate webhook signature
     *
     * @param array $payload Payload data
     * @param string $secret Webhook secret
     * @return string Generated signature
     */
    private function generate_signature($payload, $secret) {
        $payload_string = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return 'sha256=' . hash_hmac('sha256', $payload_string, $secret);
    }

    /**
     * Check if event is supported
     *
     * @param string $event Event type
     * @return bool Is supported
     */
    private function is_event_supported($event) {
        return in_array($event, $this->supported_events);
    }

    /**
     * Check if event is enabled for domain
     *
     * @param object $domain_record Domain record
     * @param string $event Event type
     * @return bool Is enabled
     */
    private function is_event_enabled($domain_record, $event) {
        if (empty($domain_record->webhook_events)) {
            return true; // All events enabled by default
        }

        $enabled_events = json_decode($domain_record->webhook_events, true);
        return is_array($enabled_events) && in_array($event, $enabled_events);
    }

    /**
     * Update domain webhook statistics
     *
     * @param object $domain_record Domain record
     * @param bool $success Success status
     */
    private function update_domain_webhook_stats($domain_record, $success) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$domains_table} SET 
             webhook_last_sent = NOW(),
             webhook_failures = CASE WHEN %d THEN 0 ELSE webhook_failures + 1 END
             WHERE id = %d",
            $success ? 1 : 0,
            $domain_record->id
        ));
    }

    /**
     * Handle code validated event
     *
     * @param string $code Validated code
     * @param string $domain Source domain
     * @param array $result Validation result
     */
    public function handle_code_validated($code, $domain, $result) {
        if (!$result['valid']) {
            return;
        }

        $this->send_webhook($domain, 'code_validated', [
            'code' => $code,
            'affiliate_id' => $result['affiliate_id'] ?? null,
            'discount' => $result['discount'] ?? null,
            'validation_time' => current_time('mysql'),
            'client_ip' => affcd_get_client_ip(),
            'user_agent' => affcd_get_user_agent()
        ]);
    }

    /**
     * Handle code created event
     *
     * @param int $code_id Code ID
     * @param array $code_data Code data
     */
    public function handle_code_created($code_id, $code_data) {
        // Send to all active domains
        $this->broadcast_to_all_domains('code_created', [
            'code_id' => $code_id,
            'vanity_code' => $code_data['vanity_code'],
            'affiliate_id' => $code_data['affiliate_id'],
            'status' => $code_data['status'],
            'discount_type' => $code_data['discount_type'] ?? 'percentage',
            'discount_value' => $code_data['discount_value'] ?? 0,
            'expires_at' => $code_data['expires_at'] ?? null,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Handle domain added event
     *
     * @param int $domain_id Domain ID
     * @param array $domain_data Domain data
     */
    public function handle_domain_added($domain_id, $domain_data) {
        // Send welcome webhook to the new domain
        $this->send_webhook($domain_data['domain_url'], 'domain_added', [
            'domain_id' => $domain_id,
            'domain_name' => $domain_data['domain_name'] ?? '',
            'status' => $domain_data['status'],
            'api_key' => substr($domain_data['api_key'] ?? '', 0, 8) . '...',
            'welcome_message' => __('Welcome to the affiliate network!', 'affiliatewp-cross-domain-plugin-suite'),
            'setup_instructions' => [
                'install_plugin' => __('Install the client plugin on your site', 'affiliatewp-cross-domain-plugin-suite'),
                'configure_api' => __('Configure API settings with provided credentials', 'affiliatewp-cross-domain-plugin-suite'),
                'test_connection' => __('Test the connection to ensure everything works', 'affiliatewp-cross-domain-plugin-suite')
            ]
        ]);
    }

    /**
     * Handle security alert event
     *
     * @param string $alert_type Alert type
     * @param string $domain Affected domain
     * @param array $alert_data Alert data
     */
    public function handle_security_alert($alert_type, $domain, $alert_data) {
        $this->send_webhook($domain, 'security_alert', [
            'alert_type' => $alert_type,
            'severity' => $alert_data['severity'] ?? 'medium',
            'message' => $alert_data['message'] ?? '',
            'ip_address' => $alert_data['ip_address'] ?? '',
            'action_taken' => $alert_data['action_taken'] ?? '',
            'alert_time' => current_time('mysql'),
            'recommended_actions' => $this->get_security_recommendations($alert_type)
        ]);
    }

    /**
     * Get security recommendations for alert type
     *
     * @param string $alert_type Alert type
     * @return array Recommendations
     */
    private function get_security_recommendations($alert_type) {
        $recommendations = [
            'rate_limit_exceeded' => [
                __('Review rate limiting settings', 'affiliatewp-cross-domain-plugin-suite'),
                __('Consider blocking the IP if abuse continues', 'affiliatewp-cross-domain-plugin-suite'),
                __('Monitor for patterns in failed requests', 'affiliatewp-cross-domain-plugin-suite')
            ],
            'repeated_failures' => [
                __('Investigate the source of repeated failures', 'affiliatewp-cross-domain-plugin-suite'),
                __('Check API credentials configuration', 'affiliatewp-cross-domain-plugin-suite'),
                __('Consider temporarily blocking the IP', 'affiliatewp-cross-domain-plugin-suite')
            ],
            'suspicious_activity' => [
                __('Review access logs for anomalies', 'affiliatewp-cross-domain-plugin-suite'),
                __('Verify all API credentials are secure', 'affiliatewp-cross-domain-plugin-suite'),
                __('Consider additional authentication measures', 'affiliatewp-cross-domain-plugin-suite')
            ]
        ];

        return $recommendations[$alert_type] ?? [
            __('Review security logs', 'affiliatewp-cross-domain-plugin-suite'),
            __('Monitor for continued suspicious activity', 'affiliatewp-cross-domain-plugin-suite')
        ];
    }

    /**
     * Broadcast event to all active domains
     *
     * @param string $event Event type
     * @param array $data Event data
     * @return int Number of webhooks queued
     */
    public function broadcast_to_all_domains($event, $data) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        $domains = $wpdb->get_results(
            "SELECT domain_url, webhook_url, webhook_secret, webhook_events 
             FROM {$domains_table} 
             WHERE status = 'active' 
             AND webhook_url IS NOT NULL 
             AND webhook_url != ''"
        );

        $queued_count = 0;
        foreach ($domains as $domain_record) {
            if ($this->is_event_enabled($domain_record, $event)) {
                $payload = $this->prepare_payload($domain_record->domain_url, $event, $data);
                if ($this->queue_webhook($domain_record, $event, $payload)) {
                    $queued_count++;
                }
            }
        }

        // Log broadcast
        affcd_log_activity('webhook_broadcast', [
            'event' => $event,
            'domains_targeted' => count($domains),
            'webhooks_queued' => $queued_count
        ]);

        return $queued_count;
    }

    /**
     * Test webhook endpoint
     *
     * @param string $webhook_url Webhook URL
     * @param string $secret Optional secret
     * @return array Test result
     */
    public function test_webhook($webhook_url, $secret = '') {
        $test_payload = [
            'id' => wp_generate_uuid4(),
            'event' => 'webhook_test',
            'domain' => home_url(),
            'timestamp' => current_time('mysql'),
            'api_version' => '1.0',
            'data' => [
                'message' => __('This is a test webhook from AffiliateWP Cross Domain Plugin Suite', 'affiliatewp-cross-domain-plugin-suite'),
                'test_time' => current_time('mysql'),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'wordpress_version' => get_bloginfo('version'),
                    'plugin_version' => AFFCD_VERSION
                ]
            ],
            'meta' => [
                'source' => home_url(),
                'plugin_version' => AFFCD_VERSION,
                'wordpress_version' => get_bloginfo('version')
            ]
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION,
            'X-AFFCD-Event' => 'webhook_test',
            'X-AFFCD-Domain' => home_url(),
            'X-AFFCD-Timestamp' => time(),
            'X-AFFCD-Test' => 'true'
        ];

        if (!empty($secret)) {
            $signature = $this->generate_signature($test_payload, $secret);
            $headers['X-AFFCD-Signature'] = $signature;
        }

        $start_time = microtime(true);
        $response = wp_remote_post($webhook_url, [
            'headers' => $headers,
            'body' => wp_json_encode($test_payload),
            'timeout' => 30,
            'blocking' => true,
            'sslverify' => true
        ]);
        $response_time = round((microtime(true) - $start_time) * 1000, 2);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'response_time' => $response_time,
                'test_payload' => $test_payload
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        $success = $response_code >= 200 && $response_code < 300;

        return [
            'success' => $success,
            'response_code' => $response_code,
            'response_body' => $response_body,
            'response_headers' => $response_headers,
            'response_time' => $response_time,
            'headers_sent' => $headers,
            'test_payload' => $test_payload
        ];
    }

    /**
     * Get webhook queue status
     *
     * @return array Queue status
     */
    public function get_queue_status() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        // Get status counts
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$table_name} 
             GROUP BY status",
            OBJECT_K
        );

        // Get recent activity
        $recent_activity = $wpdb->get_results(
            "SELECT domain, event, status, attempts, created_at, sent_at, error_message
             FROM {$table_name} 
             ORDER BY created_at DESC 
             LIMIT 20"
        );

        // Get failed webhooks needing retry
        $retry_needed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE status = 'failed' 
             AND attempts < max_attempts"
        );

        return [
            'pending' => intval($status_counts['pending']->count ?? 0),
            'processing' => intval($status_counts['processing']->count ?? 0),
            'sent' => intval($status_counts['sent']->count ?? 0),
            'failed' => intval($status_counts['failed']->count ?? 0),
            'cancelled' => intval($status_counts['cancelled']->count ?? 0),
            'retry_needed' => intval($retry_needed),
            'recent_activity' => $recent_activity
        ];
    }

    /**
     * Clean webhook queue
     *
     * @param int $days_old Days to keep data
     * @return int Rows deleted
     */
    public function clean_webhook_queue($days_old = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE status IN ('sent', 'failed', 'cancelled') 
             AND created_at < %s",
            $cutoff_date
        ));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE status IN ('sent', 'failed', 'cancelled') 
             AND created_at < %s",
            $cutoff_date
        ));

        // Log cleanup
        affcd_log_activity('webhook_queue_cleanup', [
            'cutoff_date' => $cutoff_date,
            'rows_deleted' => $deleted
        ]);

        return intval($deleted);
    }

    /**
     * Cancel webhook
     *
     * @param int $webhook_id Webhook ID
     * @return bool Success status
     */
    public function cancel_webhook($webhook_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ],
            [
                'id' => $webhook_id, 
                'status' => 'pending'
            ],
            ['%s', '%s'],
            ['%d', '%s']
        );

        if ($result !== false) {
            affcd_log_activity('webhook_cancelled', [
                'webhook_id' => $webhook_id
            ]);
        }

        return $result !== false;
    }

    /**
     * Retry webhook
     *
     * @param int $webhook_id Webhook ID
     * @return bool Success status
     */
    public function retry_webhook($webhook_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'pending',
                'attempts' => 0,
                'next_attempt' => current_time('mysql'),
                'error_message' => null,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $webhook_id],
            ['%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            affcd_log_activity('webhook_retry_manual', [
                'webhook_id' => $webhook_id
            ]);
        }

        return $result !== false;
    }

    /**
     * Get webhook details
     *
     * @param int $webhook_id Webhook ID
     * @return object|null Webhook details
     */
    public function get_webhook($webhook_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $webhook_id
        ));
    }

    /**
     * Bulk cancel webhooks
     *
     * @param array $webhook_ids Array of webhook IDs
     * @return int Number of webhooks cancelled
     */
    public function bulk_cancel_webhooks($webhook_ids) {
        if (empty($webhook_ids) || !is_array($webhook_ids)) {
            return 0;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $placeholders = implode(',', array_fill(0, count($webhook_ids), '%d'));
        
        $cancelled = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET status = 'cancelled', updated_at = NOW() 
             WHERE id IN ({$placeholders}) 
             AND status = 'pending'",
            $webhook_ids
        ));

        if ($cancelled > 0) {
            affcd_log_activity('webhook_bulk_cancelled', [
                'webhook_ids' => $webhook_ids,
                'cancelled_count' => $cancelled
            ]);
        }

        return intval($cancelled);
    }

    /**
     * Get webhook statistics
     *
     * @param int $days Number of days to analyze
     * @return array Statistics
     */
    public function get_webhook_statistics($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_webhook_queue';
        $date_from = date('Y-m-d', strtotime("-{$days} days"));

        $stats = [
            'total_webhooks' => 0,
            'successful_webhooks' => 0,
            'failed_webhooks' => 0,
            'average_response_time' => 0,
            'success_rate' => 0,
            'top_events' => [],
            'top_domains' => [],
            'daily_stats' => []
        ];

        // Total webhooks
        $stats['total_webhooks'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) >= %s",
            $date_from
        )));

        // Successful webhooks
        $stats['successful_webhooks'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE status = 'sent' AND DATE(created_at) >= %s",
            $date_from
        )));

        // Failed webhooks
        $stats['failed_webhooks'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE status = 'failed' AND DATE(created_at) >= %s",
            $date_from
        )));

        // Success rate
        if ($stats['total_webhooks'] > 0) {
            $stats['success_rate'] = round(($stats['successful_webhooks'] / $stats['total_webhooks']) * 100, 2);
        }

        // Top events
        $stats['top_events'] = $wpdb->get_results($wpdb->prepare(
            "SELECT event, COUNT(*) as count 
             FROM {$table_name} 
             WHERE DATE(created_at) >= %s 
             GROUP BY event 
             ORDER BY count DESC 
             LIMIT 10",
            $date_from
        ));

        // Top domains
        $stats['top_domains'] = $wpdb->get_results($wpdb->prepare(
            "SELECT domain, COUNT(*) as count,
             SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful
             FROM {$table_name} 
             WHERE DATE(created_at) >= %s 
             GROUP BY domain 
             ORDER BY count DESC 
             LIMIT 10",
            $date_from
        ));

        // Daily stats
        $stats['daily_stats'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$table_name} 
             WHERE DATE(created_at) >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            $date_from
        ));

        return $stats;
    }

    /**
     * AJAX test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $webhook_url = esc_url_raw($_POST['webhook_url'] ?? '');
        $secret = sanitize_text_field($_POST['secret'] ?? '');

        if (empty($webhook_url)) {
            wp_send_json_error(__('Webhook URL is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(__('Invalid webhook URL format', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $this->test_webhook($webhook_url, $secret);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Webhook test successful', 'affiliatewp-cross-domain-plugin-suite'),
                'response_time' => $result['response_time'],
                'response_code' => $result['response_code']
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Webhook test failed', 'affiliatewp-cross-domain-plugin-suite'),
                'error' => $result['error'] ?? __('Unknown error', 'affiliatewp-cross-domain-plugin-suite'),
                'response_code' => $result['response_code'] ?? 0
            ]);
        }
    }

    /**
     * AJAX retry webhook
     */
    public function ajax_retry_webhook() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $webhook_id = absint($_POST['webhook_id'] ?? 0);
        
        if (empty($webhook_id)) {
            wp_send_json_error(__('Webhook ID is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $success = $this->retry_webhook($webhook_id);
        
        if ($success) {
            wp_send_json_success([
                'message' => __('Webhook queued for retry', 'affiliatewp-cross-domain-plugin-suite')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to queue webhook for retry', 'affiliatewp-cross-domain-plugin-suite')
            ]);
        }
    }

    /**
     * AJAX get queue status
     */
    public function ajax_get_queue_status() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $status = $this->get_queue_status();
        wp_send_json_success($status);
    }

    /**
     * AJAX clean webhook queue
     */
    public function ajax_clean_queue() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $days = absint($_POST['days'] ?? 30);
        $deleted = $this->clean_webhook_queue($days);
        
        wp_send_json_success([
            'message' => sprintf(
                __('Cleaned %d webhook records older than %d days', 'affiliatewp-cross-domain-plugin-suite'),
                $deleted,
                $days
            ),
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Get supported events with descriptions
     *
     * @return array Supported events
     */
    public function get_supported_events() {
        return [
            'code_validated' => __('Fired when an affiliate code is successfully validated', 'affiliatewp-cross-domain-plugin-suite'),
            'code_created' => __('Fired when a new vanity code is created', 'affiliatewp-cross-domain-plugin-suite'),
            'code_updated' => __('Fired when a vanity code is updated', 'affiliatewp-cross-domain-plugin-suite'),
            'code_deleted' => __('Fired when a vanity code is deleted', 'affiliatewp-cross-domain-plugin-suite'),
            'domain_added' => __('Fired when a new domain is authorised', 'affiliatewp-cross-domain-plugin-suite'),
            'domain_updated' => __('Fired when domain settings are updated', 'affiliatewp-cross-domain-plugin-suite'),
            'domain_suspended' => __('Fired when a domain is suspended', 'affiliatewp-cross-domain-plugin-suite'),
            'conversion_tracked' => __('Fired when a conversion is tracked', 'affiliatewp-cross-domain-plugin-suite'),
            'security_alert' => __('Fired when a security issue is detected', 'affiliatewp-cross-domain-plugin-suite'),
            'rate_limit_exceeded' => __('Fired when rate limits are exceeded', 'affiliatewp-cross-domain-plugin-suite')
        ];
    }

    /**
     * Register additional AJAX handlers
     */
    private function register_additional_ajax_handlers() {
        add_action('wp_ajax_affcd_get_queue_status', [$this, 'ajax_get_queue_status']);
        add_action('wp_ajax_affcd_clean_webhook_queue', [$this, 'ajax_clean_queue']);
        add_action('wp_ajax_affcd_bulk_cancel_webhooks', [$this, 'ajax_bulk_cancel_webhooks']);
        add_action('wp_ajax_affcd_get_webhook_stats', [$this, 'ajax_get_webhook_stats']);
    }

    /**
     * AJAX bulk cancel webhooks
     */
    public function ajax_bulk_cancel_webhooks() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $webhook_ids = array_map('absint', $_POST['webhook_ids'] ?? []);
        
        if (empty($webhook_ids)) {
            wp_send_json_error(__('No webhook IDs provided', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $cancelled = $this->bulk_cancel_webhooks($webhook_ids);
        
        wp_send_json_success([
            'message' => sprintf(
                __('Cancelled %d webhooks', 'affiliatewp-cross-domain-plugin-suite'),
                $cancelled
            ),
            'cancelled_count' => $cancelled
        ]);
    }

    /**
     * AJAX get webhook statistics
     */
    public function ajax_get_webhook_stats() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $days = absint($_POST['days'] ?? 30);
        $stats = $this->get_webhook_statistics($days);
        
        wp_send_json_success($stats);
    }

    /**
     * Destructor - ensure cleanup tasks are registered
     */
    public function __destruct() {
        // Register additional AJAX handlers if not already done
        if (!has_action('wp_ajax_affcd_get_queue_status')) {
            $this->register_additional_ajax_handlers();
        }
    }

} 