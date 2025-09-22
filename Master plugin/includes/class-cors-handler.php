<?php
/**
 * CORS handler class referenced in main plugin but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_CORS_Handler {
    
    public function __construct() {
        add_action('init', [$this, 'handle_preflight']);
        add_action('rest_api_init', [$this, 'add_cors_headers']);
    }
    
    public function handle_preflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->send_cors_headers();
            exit;
        }
    }
    
    public function add_cors_headers() {
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers_to_response'], 10, 4);
    }
    
    public function add_cors_headers_to_response($served, $result, $request, $server) {
        // Only add CORS headers to our API endpoints
        if (strpos($request->get_route(), '/affcd/') === false) {
            return $served;
        }
        
        $this->send_cors_headers();
        return $served;
    }
    
    private function send_cors_headers() {
        $settings = get_option('affcd_api_settings', []);
        
        if (!($settings['enable_cors'] ?? true)) {
            return;
        }
        
        $origin = $this->get_request_origin();
        
        if ($origin && $this->is_origin_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
        
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    }
    
    private function get_request_origin() {
        return $_SERVER['HTTP_ORIGIN'] ?? '';
    }
    
    private function is_origin_allowed($origin) {
        $parsed = parse_url($origin);
        $host = $parsed['host'] ?? '';
        
        if (empty($host)) {
            return false;
        }
        
        // Check against authorised domains
        $domains = get_option('affcd_allowed_domains', []);
        
        foreach ($domains as $domain) {
            $domain_host = is_array($domain) ? 
                parse_url($domain['url'], PHP_URL_HOST) : 
                parse_url($domain, PHP_URL_HOST);
                
            if ($host === $domain_host) {
                return true;
            }
        }
        
        return false;
    }
}