<?php
/**
 * Analytics tracker referenced in API endpoints but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Analytics_Tracker {
    
    public function track_usage($domain, $code, $conversion = false, $metadata = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        
        $data = [
            'domain' => sanitize_url($domain),
            'code' => sanitize_text_field($code),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referrer' => sanitize_url($_SERVER['HTTP_REFERER'] ?? ''),
            'conversion' => $conversion ? 1 : 0,
            'conversion_value' => $metadata['conversion_value'] ?? null,
            'session_id' => sanitize_text_field($metadata['session_id'] ?? ''),
            'metadata' => !empty($metadata) ? json_encode($metadata) : null,
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public function get_domain_stats($domain, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(DISTINCT session_id) as unique_sessions,
                SUM(CASE WHEN conversion = 1 THEN 1 ELSE 0 END) as conversions,
                SUM(CASE WHEN conversion = 1 THEN conversion_value ELSE 0 END) as total_value
             FROM {$table_name} 
             WHERE domain = %s AND created_at >= %s",
            $domain, $since
        ));
    }
    
    public function get_code_stats($code, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_uses,
                COUNT(DISTINCT domain) as unique_domains,
                SUM(CASE WHEN conversion = 1 THEN 1 ELSE 0 END) as conversions,
                AVG(CASE WHEN conversion = 1 THEN conversion_value ELSE 0 END) as avg_value
             FROM {$table_name} 
             WHERE code = %s AND created_at >= %s",
            $code, $since
        ));
    }
    
    private function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
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