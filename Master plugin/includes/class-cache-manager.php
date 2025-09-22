<?php
/**
 * Cache manager referenced in main plugin but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Cache_Manager {
    
    private $cache_group = 'affcd';
    private $default_ttl = 300; // 5 minutes
    
    public function __construct() {
        add_action('init', [$this, 'setup_cache']);
    }
    
    public function setup_cache() {
        $settings = get_option('affcd_cache_settings', []);
        $this->default_ttl = $settings['default_ttl'] ?? 300;
        
        // Hook into REST API to add caching
        add_filter('rest_pre_dispatch', [$this, 'maybe_serve_cached'], 10, 3);
        add_action('rest_post_dispatch', [$this, 'maybe_cache_response'], 10, 3);
    }
    
    public function maybe_serve_cached($result, $server, $request) {
        // Only cache GET requests to our API
        if ($request->get_method() !== 'GET' || strpos($request->get_route(), '/affcd/') === false) {
            return $result;
        }
        
        $cache_key = $this->generate_cache_key($request);
        $cached = $this->get($cache_key);
        
        if ($cached !== false) {
            return new WP_REST_Response($cached['data'], $cached['status']);
        }
        
        return $result;
    }
    
    public function maybe_cache_response($response, $server, $request) {
        // Only cache successful GET requests
        if ($request->get_method() !== 'GET' || 
            strpos($request->get_route(), '/affcd/') === false ||
            $response->get_status() !== 200) {
            return $response;
        }
        
        $cache_key = $this->generate_cache_key($request);
        $ttl = $this->get_ttl_for_endpoint($request->get_route());
        
        $this->set($cache_key, [
            'data' => $response->get_data(),
            'status' => $response->get_status()
        ], $ttl);
        
        return $response;
    }
    
    public function get($key) {
        if (function_exists('wp_cache_get')) {
            return wp_cache_get($key, $this->cache_group);
        }
        
        return get_transient($this->cache_group . '_' . $key);
    }
    
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?: $this->default_ttl;
        
        if (function_exists('wp_cache_set')) {
            return wp_cache_set($key, $data, $this->cache_group, $ttl);
        }
        
        return set_transient($this->cache_group . '_' . $key, $data, $ttl);
    }
    
    public function delete($key) {
        if (function_exists('wp_cache_delete')) {
            return wp_cache_delete($key, $this->cache_group);
        }
        
        return delete_transient($this->cache_group . '_' . $key);
    }
    
    public function flush() {
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group($this->cache_group);
        }
        
        // Fallback: delete known transients
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $this->cache_group . '_%'
        ));
        
        return true;
    }
    
    private function generate_cache_key($request) {
        $key_parts = [
            $request->get_route(),
            $request->get_query_params(),
            $request->get_header('X-API-Key') ? substr($request->get_header('X-API-Key'), 0, 10) : 'nokey'
        ];
        
        return md5(serialize($key_parts));
    }
    
    private function get_ttl_for_endpoint($route) {
        $ttl_map = [
            '/affcd/v1/codes' => 600,        // 10 minutes for code lists
            '/affcd/v1/validate-code' => 60, // 1 minute for validations
            '/affcd/v1/health' => 30,        // 30 seconds for health checks
        ];
        
        return $ttl_map[$route] ?? $this->default_ttl;
    }
}