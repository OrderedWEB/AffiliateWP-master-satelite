<?php
/**
 * Rate limiter class referenced in domain management but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Rate_Limiter {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'init']);
    }
    
    public function init() {
        // Hook into REST API requests
        add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
    }
    
    public function check_rate_limit($result, $server, $request) {
        // Only check for our API endpoints
        if (strpos($request->get_route(), '/affcd/') === false) {
            return $result;
        }
        
        $identifier = $this->get_rate_limit_identifier($request);
        $limit_type = $this->get_limit_type($request);
        
        if (!$this->is_within_limits($identifier, $limit_type)) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }
        
        $this->record_request($identifier, $limit_type);
        return $result;
    }
    
    private function get_rate_limit_identifier($request) {
        // Try API key first, then IP
        $api_key = $request->get_header('X-API-Key');
        if ($api_key) {
            return 'api_key:' . substr($api_key, 0, 10);
        }
        
        return 'ip:' . $this->get_client_ip();
    }
    
    private function get_limit_type($request) {
        $api_key = $request->get_header('X-API-Key');
        return $api_key ? 'api_key' : 'ip';
    }
    
    private function is_within_limits($identifier, $type) {
        $settings = get_option('affcd_api_settings', []);
        $minute_limit = $settings['rate_limit_per_minute'] ?? 60;
        $hour_limit = $settings['rate_limit_per_hour'] ?? 1000;
        
        // Check minute limit
        $minute_key = "affcd_rate_minute:{$identifier}";
        $minute_count = get_transient($minute_key) ?: 0;
        
        if ($minute_count >= $minute_limit) {
            return false;
        }
        
        // Check hour limit
        $hour_key = "affcd_rate_hour:{$identifier}";
        $hour_count = get_transient($hour_key) ?: 0;
        
        return $hour_count < $hour_limit;
    }
    
    private function record_request($identifier, $type) {
        // Increment minute counter
        $minute_key = "affcd_rate_minute:{$identifier}";
        $minute_count = get_transient($minute_key) ?: 0;
        set_transient($minute_key, $minute_count + 1, 60);
        
        // Increment hour counter
        $hour_key = "affcd_rate_hour:{$identifier}";
        $hour_count = get_transient($hour_key) ?: 0;
        set_transient($hour_key, $hour_count + 1, 3600);
    }
    
    private function get_client_ip() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}