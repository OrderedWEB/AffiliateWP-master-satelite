<?php
/**
 * API Client Class
 *
 * Handles all communication with the remote AffiliateWP site
 * including authentication, data transmission, and error handling.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFILIATE_CLIENT_API_Client {

    /**
     * Plugin configuration
     *
     * @var array
     */
    private $config;

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url;

    /**
     * Request headers
     *
     * @var array
     */
    private $default_headers;

    /**
     * Rate limiting data
     *
     * @var array
     */
    private $rate_limit_data;

    /**
     * Constructor
     *
     * @param array $config Plugin configuration
     */
    public function __construct($config) {
        $this->config = $config;
        $this->setup_api_client();
    }

    /**
     * Setup API client
     */
    private function setup_api_client() {
        $this->api_base_url = rtrim($this->config['remote_url'], '/') . '/wp-json/affcd/v1/';
        
        $this->default_headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateClientFull/' . $this->config['version'],
            'X-API-Key' => $this->config['api_key'],
            'X-Site-URL' => home_url(),
            'X-Client-Version' => $this->config['version'],
        ];

        $this->rate_limit_data = get_option('affiliate_client_rate_limit', [
            'requests' => 0,
            'reset_time' => time() + HOUR_IN_SECONDS,
        ]);
    }

    /**
     * Check if API is available
     *
     * @return bool True if API is available
     */
    public function is_available() {
        return !empty($this->config['remote_url']) && 
               !empty($this->config['api_key']) && 
               $this->config['tracking_enabled'];
    }

    /**
     * Test connection to remote API
     *
     * @return array Test result
     */
    public function test_connection() {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'message' => __('API configuration incomplete', 'affiliate-client-full'),
                'details' => [
                    'remote_url' => !empty($this->config['remote_url']),
                    'api_key' => !empty($this->config['api_key']),
                    'tracking_enabled' => $this->config['tracking_enabled'],
                ],
            ];
        }

        $response = $this->make_request('test', [], 'GET');

        if ($response['success']) {
            return [
                'success' => true,
                'message' => __('Connection successful', 'affiliate-client-full'),
                'details' => $response['data'],
            ];
        } else {
            return [
                'success' => false,
                'message' => __('Connection failed', 'affiliate-client-full'),
                'error' => $response['error'],
                'details' => $response,
            ];
        }
    }

    /**
     * Validate affiliate
     *
     * @param string $affiliate_param Affiliate parameter (ID, username, or slug)
     * @return array Validation result
     */
    public function validate_affiliate($affiliate_param) {
        $cache_key = 'affiliate_client_validate_' . md5($affiliate_param);
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        $response = $this->make_request('validate-affiliate', [
            'affiliate' => $affiliate_param,
        ], 'POST');

        $result = [
            'valid' => false,
            'affiliate_id' => null,
            'affiliate_data' => null,
        ];

        if ($response['success'] && isset($response['data']['valid'])) {
            $result = [
                'valid' => $response['data']['valid'],
                'affiliate_id' => $response['data']['affiliate_id'] ?? null,
                'affiliate_data' => $response['data']['affiliate'] ?? null,
            ];
        }

        // Cache result for 15 minutes
        set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);

        return $result;
    }

    /**
     * Send tracking data to remote site
     *
     * @param array $tracking_data Tracking data
     * @return array Send result
     */
    public function send_tracking_data($tracking_data) {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'error' => 'API not available',
            ];
        }

        // Check rate limiting
        if (!$this->check_rate_limit()) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded',
            ];
        }

        $response = $this->make_request('track', $tracking_data, 'POST');

        // Update rate limiting
        $this->update_rate_limit();

        return $response;
    }

    /**
     * Send conversion data
     *
     * @param array $conversion_data Conversion data
     * @return array Send result
     */
    public function send_conversion_data($conversion_data) {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'error' => 'API not available',
            ];
        }

        $response = $this->make_request('convert', $conversion_data, 'POST');

        return $response;
    }

    /**
     * Get affiliate data
     *
     * @param int $affiliate_id Affiliate ID
     * @return array Affiliate data
     */
    public function get_affiliate_data($affiliate_id) {
        $cache_key = 'affiliate_client_data_' . $affiliate_id;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        $response = $this->make_request('affiliate/' . $affiliate_id, [], 'GET');

        $result = [
            'success' => false,
            'data' => null,
        ];

        if ($response['success']) {
            $result = [
                'success' => true,
                'data' => $response['data'],
            ];

            // Cache for 1 hour
            set_transient($cache_key, $result, HOUR_IN_SECONDS);
        }

        return $result;
    }

    /**
     * Sync batch data
     *
     * @param array $batch_data Array of tracking events
     * @return array Sync result
     */
    public function sync_batch_data($batch_data) {
        if (empty($batch_data)) {
            return [
                'success' => true,
                'message' => 'No data to sync',
                'synced_count' => 0,
            ];
        }

        $response = $this->make_request('sync-batch', [
            'events' => $batch_data,
            'site_url' => home_url(),
            'timestamp' => current_time('c'),
        ], 'POST');

        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'Batch synced successfully',
                'synced_count' => count($batch_data),
                'response_data' => $response['data'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Batch sync failed',
            'error' => $response['error'],
            'synced_count' => 0,
        ];
    }

    /**
     * Get remote configuration
     *
     * @return array Remote configuration
     */
    public function get_remote_config() {
        $cache_key = 'affiliate_client_remote_config';
        $cached_config = get_transient($cache_key);

        if ($cached_config !== false) {
            return $cached_config;
        }

        $response = $this->make_request('config', [], 'GET');

        $result = [
            'success' => false,
            'config' => [],
        ];

        if ($response['success']) {
            $result = [
                'success' => true,
                'config' => $response['data'],
            ];

            // Cache for 30 minutes
            set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    /**
     * Make HTTP request to API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array Response data
     */
    private function make_request($endpoint, $data = [], $method = 'POST') {
        $url = $this->api_base_url . ltrim($endpoint, '/');
        
        $args = [
            'method' => strtoupper($method),
            'headers' => $this->default_headers,
            'timeout' => $this->config['api_timeout'],
            'sslverify' => !$this->config['debug_mode'],
            'user-agent' => $this->default_headers['User-Agent'],
        ];

        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }

        // Add retry logic
        $max_retries = $this->config['api_retry_attempts'];
        $retry_delay = $this->config['api_retry_delay'];
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                break;
            }
            
            if ($attempt < $max_retries) {
                sleep($retry_delay);
            }
        }

        return $this->process_response($response, $endpoint, $data);
    }

    /**
     * Process API response
     *
     * @param WP_HTTP_Response|WP_Error $response HTTP response
     * @param string $endpoint Endpoint called
     * @param array $data Request data
     * @return array Processed response
     */
    private function process_response($response, $endpoint, $data) {
        // Log request if debug mode is enabled
        if ($this->config['debug_mode']) {
            $this->log_request($endpoint, $data, $response);
        }

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Handle rate limiting headers
        if (isset($response_headers['X-RateLimit-Remaining'])) {
            $this->update_rate_limit_from_headers($response_headers);
        }

        if ($response_code < 200 || $response_code >= 300) {
            return [
                'success' => false,
                'error' => "HTTP {$response_code}: " . $response_body,
                'response_code' => $response_code,
                'response_body' => $response_body,
            ];
        }

        $decoded_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'response_body' => $response_body,
            ];
        }

        return [
            'success' => true,
            'data' => $decoded_response,
            'response_code' => $response_code,
            'headers' => $response_headers,
        ];
    }

    /**
     * Check rate limiting
     *
     * @return bool True if within rate limits
     */
    private function check_rate_limit() {
        $current_time = time();
        
        // Reset counter if time window has passed
        if ($current_time >= $this->rate_limit_data['reset_time']) {
            $this->rate_limit_data = [
                'requests' => 0,
                'reset_time' => $current_time + HOUR_IN_SECONDS,
            ];
        }

        return $this->rate_limit_data['requests'] < $this->config['api_rate_limit'];
    }

    /**
     * Update rate limiting counter
     */
    private function update_rate_limit() {
        $this->rate_limit_data['requests']++;
        update_option('affiliate_client_rate_limit', $this->rate_limit_data);
    }

    /**
     * Update rate limiting from response headers
     *
     * @param array $headers Response headers
     */
    private function update_rate_limit_from_headers($headers) {
        if (isset($headers['X-RateLimit-Remaining'])) {
            $remaining = intval($headers['X-RateLimit-Remaining']);
            $limit = intval($headers['X-RateLimit-Limit'] ?? $this->config['api_rate_limit']);
            
            $this->rate_limit_data['requests'] = $limit - $remaining;
            
            if (isset($headers['X-RateLimit-Reset'])) {
                $this->rate_limit_data['reset_time'] = intval($headers['X-RateLimit-Reset']);
            }
            
            update_option('affiliate_client_rate_limit', $this->rate_limit_data);
        }
    }

    /**
     * Log API request for debugging
     *
     * @param string $endpoint Endpoint
     * @param array $data Request data
     * @param mixed $response Response
     */
    private function log_request($endpoint, $data, $response) {
        $log_entry = [
            'timestamp' => current_time('c'),
            'endpoint' => $endpoint,
            'data' => $data,
            'response_code' => is_wp_error($response) ? 'ERROR' : wp_remote_retrieve_response_code($response),
            'response_message' => is_wp_error($response) ? $response->get_error_message() : 'Success',
        ];

        $log_file = WP_CONTENT_DIR . '/affiliate-client-api.log';
        
        // Don't log sensitive data in production
        if (!$this->config['debug_mode']) {
            unset($log_entry['data']);
        }

        error_log(
            '[' . $log_entry['timestamp'] . '] ' . 
            $endpoint . ' - ' . 
            $log_entry['response_code'] . ' - ' . 
            $log_entry['response_message'] . "\n",
            3,
            $log_file
        );
    }

    /**
     * Get rate limit status
     *
     * @return array Rate limit information
     */
    public function get_rate_limit_status() {
        $current_time = time();
        
        if ($current_time >= $this->rate_limit_data['reset_time']) {
            $remaining = $this->config['api_rate_limit'];
            $reset_time = $current_time + HOUR_IN_SECONDS;
        } else {
            $remaining = max(0, $this->config['api_rate_limit'] - $this->rate_limit_data['requests']);
            $reset_time = $this->rate_limit_data['reset_time'];
        }

        return [
            'limit' => $this->config['api_rate_limit'],
            'remaining' => $remaining,
            'reset_time' => $reset_time,
            'reset_in_seconds' => max(0, $reset_time - $current_time),
        ];
    }

    /**
     * Clear API cache
     */
    public function clear_cache() {
        global $wpdb;
        
        // Delete all transients starting with our prefix
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_affiliate_client_%' 
            OR option_name LIKE '_transient_timeout_affiliate_client_%'
        ");
    }

    /**
     * Get API statistics
     *
     * @return array API usage statistics
     */
    public function get_api_stats() {
        $log_file = WP_CONTENT_DIR . '/affiliate-client-api.log';
        
        if (!file_exists($log_file)) {
            return [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'error_rate' => 0,
                'last_request' => null,
            ];
        }

        $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($log_lines);
        $successful = 0;
        $failed = 0;
        $last_request = null;

        foreach ($log_lines as $line) {
            if (strpos($line, ' - 200 - ') !== false || strpos($line, ' - Success') !== false) {
                $successful++;
            } else {
                $failed++;
            }
            
            // Extract timestamp from last line for last request time
            if (preg_match('/\[(.*?)\]/', $line, $matches)) {
                $last_request = $matches[1];
            }
        }

        return [
            'total_requests' => $total,
            'successful_requests' => $successful,
            'failed_requests' => $failed,
            'error_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'last_request' => $last_request,
        ];
    }

    /**
     * Test specific endpoint
     *
     * @param string $endpoint Endpoint to test
     * @param array $data Test data
     * @param string $method HTTP method
     * @return array Test result
     */
    public function test_endpoint($endpoint, $data = [], $method = 'POST') {
        $start_time = microtime(true);
        $response = $this->make_request($endpoint, $data, $method);
        $end_time = microtime(true);
        
        $response['response_time'] = round(($end_time - $start_time) * 1000, 2); // milliseconds
        
        return $response;
    }

    /**
     * Validate API key
     *
     * @return array Validation result
     */
    public function validate_api_key() {
        $response = $this->make_request('validate-key', [], 'GET');
        
        if ($response['success'] && isset($response['data']['valid'])) {
            return [
                'valid' => $response['data']['valid'],
                'permissions' => $response['data']['permissions'] ?? [],
                'site_info' => $response['data']['site_info'] ?? [],
            ];
        }
        
        return [
            'valid' => false,
            'error' => $response['error'] ?? 'Unknown error',
        ];
    }

    /**
     * Get remote site information
     *
     * @return array Site information
     */
    public function get_remote_site_info() {
        $cache_key = 'affiliate_client_site_info';
        $cached_info = get_transient($cache_key);

        if ($cached_info !== false) {
            return $cached_info;
        }

        $response = $this->make_request('site-info', [], 'GET');

        $result = [
            'success' => false,
            'info' => [],
        ];

        if ($response['success']) {
            $result = [
                'success' => true,
                'info' => $response['data'],
            ];

            // Cache for 1 hour
            set_transient($cache_key, $result, HOUR_IN_SECONDS);
        }

        return $result;
    }

    /**
     * Send heartbeat to maintain connection
     *
     * @return array Heartbeat result
     */
    public function send_heartbeat() {
        $heartbeat_data = [
            'site_url' => home_url(),
            'timestamp' => current_time('c'),
            'version' => $this->config['version'],
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'active_plugins' => $this->get_active_plugins_list(),
        ];

        return $this->make_request('heartbeat', $heartbeat_data, 'POST');
    }

    /**
     * Get list of active plugins (for diagnostics)
     *
     * @return array Active plugins list
     */
    private function get_active_plugins_list() {
        $active_plugins = get_option('active_plugins', []);
        $plugin_names = [];

        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
            if (!empty($plugin_data['Name'])) {
                $plugin_names[] = $plugin_data['Name'] . ' (' . $plugin_data['Version'] . ')';
            }
        }

        return $plugin_names;
    }

    /**
     * Handle API errors and provide user-friendly messages
     *
     * @param array $response API response
     * @return string User-friendly error message
     */
    public function get_error_message($response) {
        if (!isset($response['error'])) {
            return __('Unknown error occurred', 'affiliate-client-full');
        }

        $error = $response['error'];
        $error_code = $response['error_code'] ?? '';

        // Map common errors to user-friendly messages
        $error_messages = [
            'timeout' => __('Connection timeout. Please check your internet connection and try again.', 'affiliate-client-full'),
            'ssl_verification_failed' => __('SSL verification failed. Please check your SSL configuration.', 'affiliate-client-full'),
            'invalid_api_key' => __('Invalid API key. Please check your API key in settings.', 'affiliate-client-full'),
            'rate_limit_exceeded' => __('API rate limit exceeded. Please wait before making more requests.', 'affiliate-client-full'),
            'unAuthorized' => __('UnAuthorized access. Please check your API key and permissions.', 'affiliate-client-full'),
            'not_found' => __('API endpoint not found. Please check your remote site configuration.', 'affiliate-client-full'),
            'server_error' => __('Server error on remote site. Please contact support.', 'affiliate-client-full'),
        ];

        // Check for specific error patterns
        if (strpos($error, 'timeout') !== false || $error_code === 'http_request_timeout') {
            return $error_messages['timeout'];
        }

        if (strpos($error, 'SSL') !== false || strpos($error, 'certificate') !== false) {
            return $error_messages['ssl_verification_failed'];
        }

        if (isset($response['response_code'])) {
            switch ($response['response_code']) {
                case 401:
                    return $error_messages['unAuthorized'];
                case 404:
                    return $error_messages['not_found'];
                case 429:
                    return $error_messages['rate_limit_exceeded'];
                case 500:
                case 502:
                case 503:
                    return $error_messages['server_error'];
            }
        }

        // Return original error if no mapping found
        return $error;
    }

    /**
     * Check API health
     *
     * @return array Health check result
     */
    public function health_check() {
        $start_time = microtime(true);
        
        $checks = [
            'connection' => $this->test_connection(),
            'rate_limit' => $this->get_rate_limit_status(),
        ];
        
        $end_time = microtime(true);
        $total_time = round(($end_time - $start_time) * 1000, 2);
        
        $overall_status = $checks['connection']['success'] ? 'healthy' : 'unhealthy';
        
        return [
            'status' => $overall_status,
            'response_time' => $total_time,
            'checks' => $checks,
            'timestamp' => current_time('c'),
        ];
    }

    /**
     * Get configuration for this client
     *
     * @return array Client configuration
     */
    public function get_client_config() {
        return [
            'api_base_url' => $this->api_base_url,
            'version' => $this->config['version'],
            'rate_limit' => $this->config['api_rate_limit'],
            'timeout' => $this->config['api_timeout'],
            'retry_attempts' => $this->config['api_retry_attempts'],
            'debug_mode' => $this->config['debug_mode'],
        ];
    }
}