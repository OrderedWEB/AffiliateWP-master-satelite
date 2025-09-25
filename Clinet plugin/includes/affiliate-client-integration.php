<?php
/**
 * API client for communicating with affiliate domain
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCI_API_Client {
    
    private $api_endpoint;
    private $api_key;
    private $timeout = 10;
    
    public function __construct() {
        $this->api_endpoint = trailingslashit(affci_get_option('affiliate_domain')) . 'wp-json/affcd/v1/';
        $this->api_key = affci_get_option('api_key');
        
        add_action('affci_sync_codes', [$this, 'sync_codes']);
        add_action('wp_ajax_affci_validate_code', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_nopriv_affci_validate_code', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_affci_track_conversion', [$this, 'ajax_track_conversion']);
        add_action('wp_ajax_nopriv_affci_track_conversion', [$this, 'ajax_track_conversion']);
    }
    
    public function validate_code($code, $session_id = null) {
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            return $this->error_response('not_configured', 'API not configured');
        }
        
        $endpoint = $this->api_endpoint . 'validate-code';
        
        $body = [
            'code' => sanitize_text_field($code),
            'domain' => home_url(),
            'session_id' => $session_id
        ];
        
        $response = $this->make_request('POST', $endpoint, $body);
        
        if (is_wp_error($response)) {
            affci_log('API validation error: ' . $response->get_error_message(), 'error');
            return $this->error_response('api_error', $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code === 200 && $body_data && $body_data['success']) {
            // Cache valid code temporarily
            $this->cache_code_result($code, $body_data['data']);
            return $body_data['data'];
        }
        
        if ($body_data && isset($body_data['error'])) {
            return $this->error_response($body_data['error']['code'], $body_data['error']['message']);
        }
        
        return $this->error_response('unknown_error', 'Unknown API error');
    }
    
    public function track_usage($code, $conversion = false, $conversion_value = null, $metadata = []) {
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            return false;
        }
        
        $endpoint = $this->api_endpoint . 'track-usage';
        
        $body = [
            'code' => sanitize_text_field($code),
            'domain' => home_url(),
            'conversion' => (bool) $conversion,
            'conversion_value' => $conversion_value ? (float) $conversion_value : null,
            'session_id' => $this->get_session_id(),
            'metadata' => $metadata
        ];
        
        $response = $this->make_request('POST', $endpoint, $body);
        
        if (is_wp_error($response)) {
            affci_log('API tracking error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        return $status_code === 200 && $body_data && $body_data['success'];
    }
    
    public function get_available_codes() {
        // Check cache first
        $cached_codes = get_transient('affci_cached_codes');
        if ($cached_codes !== false) {
            return $cached_codes;
        }
        
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            return [];
        }
        
        $endpoint = $this->api_endpoint . 'codes';
        
        $response = $this->make_request('GET', $endpoint, [
            'domain' => home_url(),
            'active_only' => true
        ]);
        
        if (is_wp_error($response)) {
            affci_log('API codes fetch error: ' . $response->get_error_message(), 'error');
            return [];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code === 200 && $body_data && $body_data['success']) {
            $codes = $body_data['data']['codes'] ?? [];
            
            // Cache for 5 minutes
            set_transient('affci_cached_codes', $codes, 300);
            update_option('affci_last_sync', current_time('mysql'));
            
            return $codes;
        }
        
        return [];
    }
    
    public function test_connection() {
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            return $this->error_response('not_configured', 'API not configured');
        }
        
        $endpoint = $this->api_endpoint . 'health';
        
        $response = $this->make_request('GET', $endpoint);
        
        if (is_wp_error($response)) {
            update_option('affci_connection_status', [
                'status' => 'error',
                'message' => $response->get_error_message(),
                'last_check' => current_time('mysql')
            ]);
            return $this->error_response('connection_error', $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code === 200 && $body_data && $body_data['success']) {
            update_option('affci_connection_status', [
                'status' => 'connected',
                'message' => 'Connection successful',
                'last_check' => current_time('mysql'),
                'api_version' => $body_data['data']['version'] ?? 'unknown'
            ]);
            return $body_data['data'];
        }
        
        update_option('affci_connection_status', [
            'status' => 'error',
            'message' => 'Invalid API response',
            'last_check' => current_time('mysql')
        ]);
        
        return $this->error_response('invalid_response', 'Invalid API response');
    }
    
    public function sync_codes() {
        $codes = $this->get_available_codes();
        affci_log('Synced ' . count($codes) . ' codes from affiliate domain');
    }
    
    public function ajax_validate_code() {
        check_ajax_referer('affci_nonce', 'nonce');
        
        $code = sanitize_text_field($_POST['code'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($code)) {
            wp_send_json_error(['message' => __('Code is required', 'affiliate-client-integration')]);
        }
        
        $result = $this->validate_code($code, $session_id);
        
        if (isset($result['error'])) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_track_conversion() {
        check_ajax_referer('affci_nonce', 'nonce');
        
        $code = sanitize_text_field($_POST['code'] ?? '');
        $conversion_value = floatval($_POST['conversion_value'] ?? 0);
        $metadata = $_POST['metadata'] ?? [];
        
        if (empty($code)) {
            wp_send_json_error(['message' => __('Code is required', 'affiliate-client-integration')]);
        }
        
        // Sanitize metadata
        if (is_array($metadata)) {
            $metadata = array_map('sanitize_text_field', $metadata);
        }
        
        $result = $this->track_usage($code, true, $conversion_value, $metadata);
        
        if ($result) {
            wp_send_json_success(['message' => __('Conversion tracked', 'affiliate-client-integration')]);
        } else {
            wp_send_json_error(['message' => __('Failed to track conversion', 'affiliate-client-integration')]);
        }
    }
    
    private function make_request($method, $endpoint, $data = []) {
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key,
                'User-Agent' => 'AFFCI-Client/1.0'
            ]
        ];
        
        if ($method === 'POST') {
            $args['body'] = json_encode($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $endpoint = add_query_arg($data, $endpoint);
        }
        
        return wp_remote_request($endpoint, $args);
    }
    
    private function cache_code_result($code, $data) {
        $cache_key = 'affci_code_' . md5($code);
        set_transient($cache_key, $data, 300); // 5 minutes
    }
    
    private function get_cached_code_result($code) {
        $cache_key = 'affci_code_' . md5($code);
        return get_transient($cache_key);
    }
    
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }
    
    private function error_response($code, $message) {
        return [
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }
    
    public function get_connection_status() {
        return get_option('affci_connection_status', [
            'status' => 'unknown',
            'message' => 'Not tested yet',
            'last_check' => null
        ]);
    }
}