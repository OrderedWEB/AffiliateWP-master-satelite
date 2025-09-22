<?php
/**
 * API Handler for Affiliate Client Integration
 * 
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-api-handler.php
 * 
 * Handles secure communication with the master affiliate domain system
 * including code validation, data synchronization, and error handling.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_API_Handler {

    private $master_domain;
    private $api_key;
    private $api_version = 'v1';
    private $timeout = 30;
    private $retry_attempts = 3;
    private $cache_prefix = 'aci_api_';
    private $cache_expiration = 300; // 5 minutes

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('aci_settings', []);
        $this->master_domain = rtrim($settings['master_domain'] ?? '', '/');
        $this->api_key = $settings['api_key'] ?? '';
        
        // Initialise hooks
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_aci_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_nopriv_aci_test_connection', [$this, 'ajax_test_connection']);
        add_action('aci_sync_with_master', [$this, 'sync_with_master']);
        
        // Schedule regular sync
        add_action('wp', [$this, 'schedule_sync']);
    }

    /**
     * Initialse API handler
     */
    public function init() {
        $this->validate_configuration();
        $this->setup_error_handling();
    }

    /**
     * Validate affiliate code with master domain
     */
    public function validate_affiliate_code($code, $additional_data = []) {
        if (empty($code)) {
            return new WP_Error('empty_code', __('Affiliate code is required.', 'affiliate-client-integration'));
        }

        // Check cache first
        $cache_key = $this->cache_prefix . 'validate_' . md5($code);
        $cached_result = wp_cache_get($cache_key, 'aci_api');
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Prepare request data
        $request_data = array_merge([
            'code' => sanitize_text_field($code),
            'domain' => home_url(),
            'timestamp' => time(),
            'client_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], $additional_data);

        // Make API request
        $response = $this->make_api_request('validate-code', $request_data, 'POST');

        if (is_wp_error($response)) {
            return $response;
        }

        // Cache valid results
        if (!empty($response['valid'])) {
            wp_cache_set($cache_key, $response, 'aci_api', $this->cache_expiration);
        }

        return $response;
    }

    /**
     * Send form data to master domain
     */
    public function submit_form_data($form_data, $affiliate_data) {
        $request_data = [
            'form_data' => $form_data,
            'affiliate_data' => $affiliate_data,
            'domain' => home_url(),
            'timestamp' => time(),
            'submission_id' => wp_generate_uuid4()
        ];

        return $this->make_api_request('submit-form', $request_data, 'POST');
    }

    /**
     * Track conversion
     */
    public function track_conversion($affiliate_data, $conversion_data) {
        $request_data = [
            'affiliate_id' => $affiliate_data['affiliate_id'],
            'affiliate_code' => $affiliate_data['affiliate_code'],
            'conversion_data' => $conversion_data,
            'domain' => home_url(),
            'timestamp' => time(),
            'conversion_id' => wp_generate_uuid4()
        ];

        return $this->make_api_request('track-conversion', $request_data, 'POST');
    }

    /**
     * Send analytics data
     */
    public function send_analytics($analytics_data) {
        $request_data = [
            'analytics' => $analytics_data,
            'domain' => home_url(),
            'timestamp' => time()
        ];

        return $this->make_api_request('analytics', $request_data, 'POST');
    }

    /**
     * Sync configuration with master
     */
    public function sync_configuration() {
        $response = $this->make_api_request('sync-config', [
            'domain' => home_url(),
            'version' => ACI_VERSION,
            'timestamp' => time()
        ], 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        // Update local configuration
        if (!empty($response['config'])) {
            $this->update_local_config($response['config']);
        }

        return $response;
    }

    /**
     * Test connection to master domain
     */
    public function test_connection() {
        $start_time = microtime(true);
        
        $response = $this->make_api_request('ping', [
            'domain' => home_url(),
            'timestamp' => time(),
            'test' => true
        ], 'GET');

        $response_time = round((microtime(true) - $start_time) * 1000, 2);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'response_time' => $response_time
            ];
        }

        return [
            'success' => true,
            'response_time' => $response_time,
            'server_info' => $response['server_info'] ?? [],
            'connection_status' => 'active'
        ];
    }

    /**
     * Make API request to master domain
     */
    private function make_api_request($endpoint, $data = [], $method = 'GET') {
        if (empty($this->master_domain) || empty($this->api_key)) {
            return new WP_Error(
                'configuration_error',
                __('API configuration is incomplete. Please check master domain and API key.', 'affiliate-client-integration')
            );
        }

        $url = $this->build_api_url($endpoint);
        $args = $this->build_request_args($data, $method);

        // Add retry logic
        $last_error = null;
        for ($attempt = 1; $attempt <= $this->retry_attempts; $attempt++) {
            $response = wp_remote_request($url, $args);

            if (!is_wp_error($response)) {
                return $this->process_response($response, $endpoint);
            }

            $last_error = $response;
            
            // Exponential backoff
            if ($attempt < $this->retry_attempts) {
                sleep(pow(2, $attempt - 1));
            }
        }

        // Log failed request
        $this->log_api_error($endpoint, $last_error, $data);

        return $last_error;
    }

    /**
     * Build API URL
     */
    private function build_api_url($endpoint) {
        return sprintf(
            '%s/wp-json/affcd/%s/%s',
            $this->master_domain,
            $this->api_version,
            ltrim($endpoint, '/')
        );
    }

    /**
     * Build request arguments
     */
    private function build_request_args($data, $method) {
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'User-Agent' => $this->get_user_agent(),
                'X-Client-Domain' => home_url(),
                'X-Client-Version' => ACI_VERSION
            ],
            'sslverify' => true
        ];

        if (!empty($data)) {
            if ($method === 'GET') {
                $args['body'] = $data;
            } else {
                $args['body'] = wp_json_encode($data);
            }
        }

        // Add security headers
        $args['headers']['X-Request-ID'] = wp_generate_uuid4();
        $args['headers']['X-Timestamp'] = time();
        $args['headers']['X-Signature'] = $this->generate_request_signature($data);

        return $args;
    }

    /**
     * Generate request signature
     */
    private function generate_request_signature($data) {
        $payload = wp_json_encode($data) . time() . $this->api_key;
        return hash_hmac('sha256', $payload, $this->api_key);
    }

    /**
     * Process API response
     */
    private function process_response($response, $endpoint) {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log response for debugging
        $this->log_api_response($endpoint, $status_code, $body);

        if ($status_code >= 400) {
            return $this->handle_error_response($status_code, $body);
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_response',
                __('Invalid JSON response from server.', 'affiliate-client-integration')
            );
        }

        return $decoded;
    }

    /**
     * Handle error response
     */
    private function handle_error_response($status_code, $body) {
        $error_data = json_decode($body, true);
        
        $error_messages = [
            400 => __('Bad request. Please check your data.', 'affiliate-client-integration'),
            401 => __('Unauthorised. Please check your API key.', 'affiliate-client-integration'),
            403 => __('Forbidden. Your domain may not be authorised.', 'affiliate-client-integration'),
            404 => __('API endpoint not found.', 'affiliate-client-integration'),
            429 => __('Rate limit exceeded. Please try again later.', 'affiliate-client-integration'),
            500 => __('Server error. Please try again later.', 'affiliate-client-integration'),
            503 => __('Service unavailable. Please try again later.', 'affiliate-client-integration')
        ];

        $error_message = $error_messages[$status_code] ?? 
            sprintf(__('API request failed with status %d.', 'affiliate-client-integration'), $status_code);

        // Use server-provided error message if available
        if (!empty($error_data['message'])) {
            $error_message = $error_data['message'];
        }

        return new WP_Error(
            'api_error_' . $status_code,
            $error_message,
            ['status_code' => $status_code, 'response_body' => $body]
        );
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip_headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent string
     */
    private function get_user_agent() {
        return sprintf(
            'AffiliateClientIntegration/%s WordPress/%s (Site: %s)',
            ACI_VERSION,
            get_bloginfo('version'),
            home_url()
        );
    }

    /**
     * Validate configuration
     */
    private function validate_configuration() {
        $errors = [];

        if (empty($this->master_domain)) {
            $errors[] = __('Master domain is not configured.', 'affiliate-client-integration');
        } elseif (!filter_var($this->master_domain, FILTER_VALIDATE_URL)) {
            $errors[] = __('Master domain is not a valid URL.', 'affiliate-client-integration');
        }

        if (empty($this->api_key)) {
            $errors[] = __('API key is not configured.', 'affiliate-client-integration');
        } elseif (strlen($this->api_key) < 20) {
            $errors[] = __('API key appears to be invalid.', 'affiliate-client-integration');
        }

        if (!empty($errors)) {
            add_action('admin_notices', function() use ($errors) {
                foreach ($errors as $error) {
                    echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
                }
            });
        }
    }

    /**
     * Setup error handling
     */
    private function setup_error_handling() {
        // Schedule cleanup of old logs
        if (!wp_next_scheduled('aci_cleanup_api_logs')) {
            wp_schedule_event(time(), 'daily', 'aci_cleanup_api_logs');
        }

        add_action('aci_cleanup_api_logs', [$this, 'cleanup_old_logs']);
    }

    /**
     * Update local configuration
     */
    private function update_local_config($config) {
        $current_settings = get_option('aci_settings', []);
        
        // Merge with received configuration
        $updated_settings = array_merge($current_settings, [
            'popup_settings' => $config['popup_settings'] ?? [],
            'form_settings' => $config['form_settings'] ?? [],
            'discount_settings' => $config['discount_settings'] ?? [],
            'tracking_settings' => $config['tracking_settings'] ?? [],
            'last_sync' => current_time('mysql')
        ]);

        update_option('aci_settings', $updated_settings);

        // Clear relevant caches
        wp_cache_flush_group('aci_api');
        wp_cache_flush_group('aci_config');

        // Log configuration update
        $this->log_api_event('config_updated', [
            'updated_keys' => array_keys($config),
            'sync_time' => current_time('mysql')
        ]);
    }

    /**
     * Log API error
     */
    private function log_api_error($endpoint, $error, $request_data = []) {
        $log_data = [
            'endpoint' => $endpoint,
            'error_message' => is_wp_error($error) ? $error->get_error_message() : $error,
            'error_code' => is_wp_error($error) ? $error->get_error_code() : 'unknown',
            'request_data' => $this->sanitize_log_data($request_data),
            'timestamp' => current_time('mysql'),
            'client_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        // Store in database
        $this->store_api_log('error', $log_data);

        // Send to master if critical
        if ($this->is_critical_error($error)) {
            $this->send_error_to_master($log_data);
        }
    }

    /**
     * Log API response
     */
    private function log_api_response($endpoint, $status_code, $body) {
        // Only log errors and important events to avoid spam
        if ($status_code >= 400 || $this->should_log_response($endpoint)) {
            $log_data = [
                'endpoint' => $endpoint,
                'status_code' => $status_code,
                'response_size' => strlen($body),
                'timestamp' => current_time('mysql'),
                'success' => $status_code < 400
            ];

            $this->store_api_log('response', $log_data);
        }
    }

    /**
     * Log API event
     */
    private function log_api_event($event_type, $data = []) {
        $log_data = array_merge($data, [
            'event_type' => $event_type,
            'timestamp' => current_time('mysql'),
            'domain' => home_url()
        ]);

        $this->store_api_log('event', $log_data);
    }

    /**
     * Store API log in database
     */
    private function store_api_log($log_type, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aci_api_logs';

        // Create table if it doesn't exist
        $this->maybe_create_logs_table();

        $wpdb->insert(
            $table_name,
            [
                'log_type' => $log_type,
                'log_data' => wp_json_encode($data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s']
        );
    }

    /**
     * Create logs table
     */
    private function maybe_create_logs_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aci_api_logs';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL,
            log_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_type (log_type),
            KEY created_at (created_at)
        ) {$wpdb->get_charset_collate()};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Sanitize log data
     */
    private function sanitize_log_data($data) {
        // Remove sensitive information
        $sensitive_keys = ['api_key', 'password', 'secret', 'token'];
        
        array_walk_recursive($data, function(&$value, $key) use ($sensitive_keys) {
            if (in_array(strtolower($key), $sensitive_keys)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }

    /**
     * Check if error is critical
     */
    private function is_critical_error($error) {
        if (!is_wp_error($error)) {
            return false;
        }

        $critical_codes = [
            'authorization_failed',
            'domain_blocked',
            'api_key_invalid',
            'rate_limit_exceeded'
        ];

        return in_array($error->get_error_code(), $critical_codes);
    }

    /**
     * Check if response should be logged
     */
    private function should_log_response($endpoint) {
        $important_endpoints = ['validate-code', 'submit-form', 'track-conversion'];
        return in_array($endpoint, $important_endpoints);
    }

    /**
     * Send error to master
     */
    private function send_error_to_master($error_data) {
        // Send error notification to master domain for monitoring
        wp_remote_post($this->master_domain . '/wp-json/affcd/v1/client-error', [
            'body' => wp_json_encode([
                'client_domain' => home_url(),
                'error_data' => $error_data,
                'timestamp' => time()
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'timeout' => 10
        ]);
    }

    /**
     * Get API statistics
     */
    public function get_api_statistics($period = '24h') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aci_api_logs';
        $time_intervals = [
            '1h' => 'INTERVAL 1 HOUR',
            '24h' => 'INTERVAL 24 HOUR',
            '7d' => 'INTERVAL 7 DAY',
            '30d' => 'INTERVAL 30 DAY'
        ];

        $interval = $time_intervals[$period] ?? 'INTERVAL 24 HOUR';

        // Total requests
        $total_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE log_type = 'response' AND created_at >= DATE_SUB(NOW(), {$interval})"
        ));

        // Error count
        $error_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE log_type = 'error' AND created_at >= DATE_SUB(NOW(), {$interval})"
        ));

        // Success rate
        $success_rate = $total_requests > 0 ? 
            round((($total_requests - $error_count) / $total_requests) * 100, 2) : 0;

        // Response times (from recent successful requests)
        $avg_response_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(JSON_EXTRACT(log_data, '$.response_time')) 
             FROM {$table_name} 
             WHERE log_type = 'response' 
               AND JSON_EXTRACT(log_data, '$.success') = true
               AND created_at >= DATE_SUB(NOW(), {$interval})"
        ));

        return [
            'total_requests' => (int) $total_requests,
            'error_count' => (int) $error_count,
            'success_rate' => $success_rate,
            'avg_response_time' => round($avg_response_time, 2),
            'period' => $period
        ];
    }

    /**
     * Schedule sync with master
     */
    public function schedule_sync() {
        if (!wp_next_scheduled('aci_sync_with_master')) {
            wp_schedule_event(time(), 'hourly', 'aci_sync_with_master');
        }
    }

    /**
     * Sync with master domain
     */
    public function sync_with_master() {
        // Sync configuration
        $config_result = $this->sync_configuration();
        
        if (is_wp_error($config_result)) {
            $this->log_api_error('sync_configuration', $config_result);
            return;
        }

        // Send analytics if enabled
        $settings = get_option('aci_settings', []);
        if (!empty($settings['send_analytics'])) {
            $analytics_data = $this->collect_analytics_data();
            $analytics_result = $this->send_analytics($analytics_data);
            
            if (is_wp_error($analytics_result)) {
                $this->log_api_error('send_analytics', $analytics_result);
            }
        }

        // Update last sync time
        update_option('aci_last_sync', current_time('mysql'));
    }

    /**
     * Collect analytics data
     */
    private function collect_analytics_data() {
        global $wpdb;

        // Get form submissions count
        $form_submissions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aci_form_submissions 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // Get popup interactions
        $popup_interactions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aci_popup_tracking 
             WHERE tracked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // Get conversion data
        $conversions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aci_conversions 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return [
            'domain' => home_url(),
            'period' => '24h',
            'metrics' => [
                'form_submissions' => (int) $form_submissions,
                'popup_interactions' => (int) $popup_interactions,
                'conversions' => (int) $conversions,
                'active_affiliates' => $this->get_active_affiliates_count()
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Get active affiliates count
     */
    private function get_active_affiliates_count() {
        global $wpdb;

        return $wpdb->get_var(
            "SELECT COUNT(DISTINCT JSON_EXTRACT(log_data, '$.affiliate_id')) 
             FROM {$wpdb->prefix}aci_api_logs 
             WHERE log_type = 'event' 
               AND JSON_EXTRACT(log_data, '$.event_type') = 'code_validation_success'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aci_api_logs';
        $retention_days = get_option('aci_log_retention_days', 30);

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        if ($deleted !== false) {
            $this->log_api_event('logs_cleaned', [
                'deleted_count' => $deleted,
                'retention_days' => $retention_days
            ]);
        }
    }

    /**
     * Get recent API logs
     */
    public function get_recent_logs($limit = 50, $log_type = '') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aci_api_logs';
        $where_clause = '';
        $params = [$limit];

        if (!empty($log_type)) {
            $where_clause = 'WHERE log_type = %s';
            array_unshift($params, $log_type);
        }

        $sql = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d";
        $logs = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // Decode JSON data
        foreach ($logs as &$log) {
            $log['log_data'] = json_decode($log['log_data'], true);
        }

        return $logs;
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('aci_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'affiliate-client-integration'));
        }

        $result = $this->test_connection();

        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Connection successful!', 'affiliate-client-integration'),
                'response_time' => $result['response_time'] . 'ms',
                'server_info' => $result['server_info']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Connection failed: %s', 'affiliate-client-integration'),
                    $result['error']
                ),
                'response_time' => $result['response_time'] . 'ms'
            ]);
        }
    }

    /**
     * Validate webhook signature
     */
    public function validate_webhook_signature($payload, $signature, $secret) {
        $calculated_signature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($calculated_signature, $signature);
    }

    /**
     * Process webhook from master
     */
    public function process_webhook($webhook_data) {
        $webhook_type = $webhook_data['type'] ?? '';

        switch ($webhook_type) {
            case 'config_update':
                return $this->handle_config_update_webhook($webhook_data);
            case 'affiliate_status_change':
                return $this->handle_affiliate_status_webhook($webhook_data);
            case 'domain_authorization_change':
                return $this->handle_domain_auth_webhook($webhook_data);
            default:
                return new WP_Error('unknown_webhook', __('Unknown webhook type.', 'affiliate-client-integration'));
        }
    }

    /**
     * Handle configuration update webhook
     */
    private function handle_config_update_webhook($webhook_data) {
        if (!empty($webhook_data['config'])) {
            $this->update_local_config($webhook_data['config']);
            
            $this->log_api_event('webhook_config_update', [
                'updated_config' => array_keys($webhook_data['config'])
            ]);
            
            return true;
        }
        
        return new WP_Error('invalid_config_webhook', __('Invalid configuration webhook data.', 'affiliate-client-integration'));
    }

    /**
     * Handle affiliate status change webhook
     */
    private function handle_affiliate_status_webhook($webhook_data) {
        $affiliate_id = $webhook_data['affiliate_id'] ?? '';
        $new_status = $webhook_data['status'] ?? '';
        
        if (empty($affiliate_id) || empty($new_status)) {
            return new WP_Error('invalid_status_webhook', __('Invalid affiliate status webhook data.', 'affiliate-client-integration'));
        }

        // Clear related caches
        $this->clear_affiliate_cache($affiliate_id);
        
        $this->log_api_event('webhook_affiliate_status', [
            'affiliate_id' => $affiliate_id,
            'new_status' => $new_status
        ]);
        
        return true;
    }

    /**
     * Handle domain authorization webhook
     */
    private function handle_domain_auth_webhook($webhook_data) {
        $domain = $webhook_data['domain'] ?? '';
        $authorization_status = $webhook_data['authorised'] ?? false;
        
        if (empty($domain)) {
            return new WP_Error('invalid_auth_webhook', __('Invalid domain authorization webhook data.', 'affiliate-client-integration'));
        }

        // If this is our domain and authorization changed
        if ($domain === home_url() || $domain === parse_url(home_url(), PHP_URL_HOST)) {
            if (!$authorization_status) {
                // Domain was unauthorised - log critical event
                $this->log_api_event('domain_unauthorised', [
                    'domain' => $domain,
                    'timestamp' => time()
                ]);
                
                // Disable API functionality
                update_option('aci_api_disabled', true);
            } else {
                // Domain was re-authorised
                delete_option('aci_api_disabled');
            }
        }
        
        return true;
    }

    /**
     * Clear affiliate cache
     */
    private function clear_affiliate_cache($affiliate_id) {
        // Clear validation caches for this affiliate
        wp_cache_flush_group('aci_api');
        
        // Clear any affiliate-specific caches
        $cache_keys = wp_cache_get('affiliate_cache_keys_' . $affiliate_id, 'aci_affiliates');
        if (is_array($cache_keys)) {
            foreach ($cache_keys as $key) {
                wp_cache_delete($key, 'aci_api');
            }
            wp_cache_delete('affiliate_cache_keys_' . $affiliate_id, 'aci_affiliates');
        }
    }

    /**
     * Check if API is operational
     */
    public function is_api_operational() {
        // Check if API is disabled
        if (get_option('aci_api_disabled', false)) {
            return false;
        }

        // Check configuration
        if (empty($this->master_domain) || empty($this->api_key)) {
            return false;
        }

        // Check recent error rate
        $recent_errors = $this->get_recent_error_rate();
        if ($recent_errors > 0.8) { // More than 80% error rate
            return false;
        }

        return true;
    }

    /**
     * Get recent error rate
     */
    private function get_recent_error_rate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aci_api_logs';
        
        $total_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE log_type = 'response' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        if ($total_requests == 0) {
            return 0;
        }

        $error_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE log_type = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        return $error_requests / $total_requests;
    }

    /**
     * Get API health status
     */
    public function get_health_status() {
        $is_operational = $this->is_api_operational();
        $statistics = $this->get_api_statistics('1h');
        $last_sync = get_option('aci_last_sync', '');
        
        return [
            'operational' => $is_operational,
            'last_sync' => $last_sync,
            'error_rate' => $this->get_recent_error_rate(),
            'recent_stats' => $statistics,
            'configuration' => [
                'master_domain' => !empty($this->master_domain),
                'api_key' => !empty($this->api_key),
                'ssl_verify' => true
            ]
        ];
    }
}