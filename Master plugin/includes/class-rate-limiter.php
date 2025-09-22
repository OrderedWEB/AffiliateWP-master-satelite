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

    /**
     * Record request and increment counters
     *
     * @param string $identifier Rate limit identifier  
     * @param string $type Limit type
     */
    private function record_request($identifier, $type) {
        // Increment minute counter
        $minute_key = "affcd_rate_minute:{$identifier}";
        $minute_count = get_transient($minute_key) ?: 0;
        set_transient($minute_key, $minute_count + 1, 60);
        
        // Increment hour counter  
        $hour_key = "affcd_rate_hour:{$identifier}";
        $hour_count = get_transient($hour_key) ?: 0;
        set_transient($hour_key, $hour_count + 1, 3600);
        
        // Update database record
        $this->update_database_record($identifier, $type);
    }

    /**
     * Update database rate limit record
     *
     * @param string $identifier Rate limit identifier
     * @param string $type Limit type
     */
    private function update_database_record($identifier, $type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $window_start = date('Y-m-d H:i:s', time() - 3600); // 1 hour window
        $window_end = date('Y-m-d H:i:s', time());
        
        // Try to update existing record first
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET request_count = request_count + 1, last_request = NOW() 
             WHERE identifier = %s AND action_type = %s AND window_start >= %s",
            $identifier, $type, $window_start
        ));
        
        // Insert new record if update didn't affect any rows
        if ($updated === 0) {
            $wpdb->insert(
                $table_name,
                [
                    'identifier' => $identifier,
                    'action_type' => $type,
                    'request_count' => 1,
                    'window_start' => $window_start,
                    'window_end' => $window_end
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
        }
    }

    /**
     * Get client IP address
     *
     * @return string Client IP
     */
    private function get_client_ip() {
        return affcd_get_client_ip();
    }

    /**
     * Check if IP is blocked
     *
     * @param string $ip IP address
     * @return bool Is blocked
     */
    public function is_ip_blocked($ip) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $current_time = current_time('mysql');
        
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE identifier = %s 
             AND is_blocked = 1 
             AND (block_until IS NULL OR block_until > %s)",
            'ip:' . $ip,
            $current_time
        ));
        
        return $blocked > 0;
    }

    /**
     * Block IP address
     *
     * @param string $ip IP address
     * @param int $duration Block duration in seconds
     * @param string $reason Block reason
     * @return bool Success
     */
    public function block_ip($ip, $duration = 3600, $reason = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $block_until = date('Y-m-d H:i:s', time() + $duration);
        
        $result = $wpdb->insert(
            $table_name,
            [
                'identifier' => 'ip:' . $ip,
                'action_type' => 'blocked',
                'request_count' => 0,
                'window_start' => current_time('mysql'),
                'window_end' => $block_until,
                'is_blocked' => 1,
                'block_until' => $block_until,
                'block_reason' => $reason
            ],
            ['%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s']
        );
        
        return $result !== false;
    }

    /**
     * Unblock IP address
     *
     * @param string $ip IP address
     * @return bool Success
     */
    public function unblock_ip($ip) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        
        $result = $wpdb->update(
            $table_name,
            [
                'is_blocked' => 0,
                'block_until' => null
            ],
            [
                'identifier' => 'ip:' . $ip,
                'is_blocked' => 1
            ],
            ['%d', '%s'],
            ['%s', '%d']
        );
        
        return $result !== false;
    }

    /**
     * Get rate limit status for identifier
     *
     * @param string $identifier Rate limit identifier
     * @return array Status information
     */
    public function get_rate_limit_status($identifier) {
        $minute_key = "affcd_rate_minute:{$identifier}";
        $hour_key = "affcd_rate_hour:{$identifier}";
        
        $minute_count = get_transient($minute_key) ?: 0;
        $hour_count = get_transient($hour_key) ?: 0;
        
        $settings = get_option('affcd_api_settings', []);
        $minute_limit = $settings['rate_limit_per_minute'] ?? 60;
        $hour_limit = $settings['rate_limit_per_hour'] ?? 1000;
        
        return [
            'identifier' => $identifier,
            'minute_count' => $minute_count,
            'minute_limit' => $minute_limit,
            'minute_remaining' => max(0, $minute_limit - $minute_count),
            'hour_count' => $hour_count,
            'hour_limit' => $hour_limit,
            'hour_remaining' => max(0, $hour_limit - $hour_count),
            'is_blocked' => $this->is_ip_blocked($identifier)
        ];
        
        // Get today's request count
        $today = date('Y-m-d');
        $stats['total_requests_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(request_count) FROM {$table_name} WHERE DATE(window_start) = %s",
            $today
        )) ?: 0;
        
        // Get blocked IPs count
        $stats['blocked_ips'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT identifier) FROM {$table_name} 
             WHERE is_blocked = 1 AND (block_until IS NULL OR block_until > NOW())"
        ) ?: 0;
        
        // Get top consumers
        $stats['top_consumers'] = $wpdb->get_results(
            "SELECT identifier, SUM(request_count) as total_requests 
             FROM {$table_name} 
             WHERE DATE(window_start) = CURDATE() 
             GROUP BY identifier 
             ORDER BY total_requests DESC 
             LIMIT 10"
        );
        
        // Get most blocked actions
        $stats['most_blocked_actions'] = $wpdb->get_results(
            "SELECT action_type, COUNT(*) as block_count 
             FROM {$table_name} 
             WHERE is_blocked = 1 AND DATE(created_at) = CURDATE()
             GROUP BY action_type 
             ORDER BY block_count DESC 
             LIMIT 10"
        );
        
        return $stats;
    }

    /**
     * Reset rate limits for identifier
     *
     * @param string $identifier Rate limit identifier
     * @return bool Success
     */
    public function reset_rate_limits($identifier) {
        $minute_key = "affcd_rate_minute:{$identifier}";
        $hour_key = "affcd_rate_hour:{$identifier}";
        
        delete_transient($minute_key);
        delete_transient($hour_key);
        
        // Also unblock if blocked
        $ip = str_replace('ip:', '', $identifier);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->unblock_ip($ip);
        }
        
        return true;
    }

    /**
     * Check if action should be rate limited
     *
     * @param string $action Action type
     * @return bool Should be rate limited
     */
    private function should_rate_limit($action) {
        $rate_limited_actions = [
            'validate_code',
            'api_request', 
            'form_submission',
            'failed_validation',
            'create_vanity',
            'webhook_request'
        ];
        
        return in_array($action, $rate_limited_actions);
    }

    /**
     * Get rate limit settings for action
     *
     * @param string $action Action type
     * @return array Rate limit settings
     */
    private function get_action_rate_limits($action) {
        $default_limits = [
            'validate_code' => ['per_minute' => 30, 'per_hour' => 500],
            'api_request' => ['per_minute' => 60, 'per_hour' => 1000],
            'form_submission' => ['per_minute' => 10, 'per_hour' => 100],
            'failed_validation' => ['per_minute' => 5, 'per_hour' => 50],
            'create_vanity' => ['per_minute' => 2, 'per_hour' => 20],
            'webhook_request' => ['per_minute' => 20, 'per_hour' => 200]
        ];
        
        $settings = get_option('affcd_api_settings', []);
        $custom_limits = $settings['custom_rate_limits'][$action] ?? [];
        
        return array_merge($default_limits[$action] ?? ['per_minute' => 60, 'per_hour' => 1000], $custom_limits);
    }

    /**
     * Add rate limit headers to response
     *
     * @param string $identifier Rate limit identifier
     * @param string $action Action type
     */
    public function add_rate_limit_headers($identifier, $action) {
        $status = $this->get_rate_limit_status($identifier);
        $limits = $this->get_action_rate_limits($action);
        
        header('X-RateLimit-Limit-Minute: ' . $limits['per_minute']);
        header('X-RateLimit-Remaining-Minute: ' . max(0, $limits['per_minute'] - $status['minute_count']));
        header('X-RateLimit-Reset-Minute: ' . (60 - (time() % 60)));
        
        header('X-RateLimit-Limit-Hour: ' . $limits['per_hour']);
        header('X-RateLimit-Remaining-Hour: ' . max(0, $limits['per_hour'] - $status['hour_count']));
        header('X-RateLimit-Reset-Hour: ' . (3600 - (time() % 3600)));
        
        if ($status['is_blocked']) {
            header('X-RateLimit-Blocked: true');
        }
    }

    /**
     * Log rate limit violation
     *
     * @param string $identifier Rate limit identifier
     * @param string $action Action type
     * @param array $context Additional context
     */
    private function log_rate_limit_violation($identifier, $action, $context = []) {
        affcd_log_activity('rate_limit_exceeded', array_merge([
            'identifier' => $identifier,
            'action' => $action,
            'ip_address' => affcd_get_client_ip(),
            'user_agent' => affcd_get_user_agent(),
            'timestamp' => current_time('mysql')
        ], $context));
        
        // Trigger security alert for severe violations
        $violation_count = $this->get_violation_count($identifier);
        if ($violation_count > 10) {
            do_action('affcd_security_alert', 'repeated_rate_limit_violations', '', [
                'severity' => 'high',
                'identifier' => $identifier,
                'violation_count' => $violation_count,
                'ip_address' => affcd_get_client_ip()
            ]);
        }
    }

    /**
     * Get violation count for identifier
     *
     * @param string $identifier Rate limit identifier
     * @return int Violation count
     */
    private function get_violation_count($identifier) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$analytics_table} 
             WHERE event_type = 'rate_limit_exceeded' 
             AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.identifier')) = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $identifier
        )) ?: 0;
    }

    /**
     * Check advanced rate limiting with burst allowance
     *
     * @param string $identifier Rate limit identifier
     * @param string $action Action type
     * @return bool Within limits
     */
    public function check_advanced_rate_limit($identifier, $action) {
        $limits = $this->get_action_rate_limits($action);
        $burst_allowance = $limits['burst_allowance'] ?? 0;
        
        // Check regular limits first
        if (!$this->is_within_limits($identifier, 'advanced')) {
            // Check if burst allowance is available
            $burst_key = "affcd_burst:{$identifier}:{$action}";
            $burst_used = get_transient($burst_key) ?: 0;
            
            if ($burst_used >= $burst_allowance) {
                return false;
            }
            
            // Use burst allowance
            set_transient($burst_key, $burst_used + 1, 300); // 5 minutes
        }
        
        return true;
    }

    /**
     * Whitelist IP address
     *
     * @param string $ip IP address
     * @param string $reason Whitelist reason
     * @return bool Success
     */
    public function whitelist_ip($ip, $reason = '') {
        $whitelist = get_option('affcd_rate_limit_whitelist', []);
        $whitelist[$ip] = [
            'added_at' => current_time('mysql'),
            'reason' => $reason,
            'added_by' => get_current_user_id()
        ];
        
        return update_option('affcd_rate_limit_whitelist', $whitelist);
    }

    /**
     * Remove IP from whitelist
     *
     * @param string $ip IP address
     * @return bool Success
     */
    public function remove_from_whitelist($ip) {
        $whitelist = get_option('affcd_rate_limit_whitelist', []);
        
        if (isset($whitelist[$ip])) {
            unset($whitelist[$ip]);
            return update_option('affcd_rate_limit_whitelist', $whitelist);
        }
        
        return false;
    }

    /**
     * Check if IP is whitelisted
     *
     * @param string $ip IP address
     * @return bool Is whitelisted
     */
    public function is_ip_whitelisted($ip) {
        $whitelist = get_option('affcd_rate_limit_whitelist', []);
        return isset($whitelist[$ip]);
    }

    /**
     * Get whitelisted IPs
     *
     * @return array Whitelisted IPs
     */
    public function get_whitelisted_ips() {
        return get_option('affcd_rate_limit_whitelist', []);
    }

    /**
     * Update rate limit settings
     *
     * @param array $settings New settings
     * @return bool Success
     */
    public function update_settings($settings) {
        $current_settings = get_option('affcd_api_settings', []);
        
        $rate_limit_settings = [
            'rate_limit_per_minute' => absint($settings['rate_limit_per_minute'] ?? 60),
            'rate_limit_per_hour' => absint($settings['rate_limit_per_hour'] ?? 1000),
            'rate_limit_enabled' => !empty($settings['rate_limit_enabled']),
            'custom_rate_limits' => $settings['custom_rate_limits'] ?? []
        ];
        
        $updated_settings = array_merge($current_settings, $rate_limit_settings);
        
        return update_option('affcd_api_settings', $updated_settings);
    }

} // End of class additions

/**
 * Helper function to get rate limiter instance
 *
 * @return AFFCD_Rate_Limiter
 */
function affcd_get_rate_limiter() {
    static $instance = null;
    
    if (null === $instance) {
        $instance = new AFFCD_Rate_Limiter();
    }
    
    return $instance;
}
    }

    /**
     * Clean old rate limit records
     *
     * @param int $days_old Days to keep records
     * @return int Records deleted
     */
    public function cleanup_old_records($days_old = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE window_end < %s AND is_blocked = 0",
            $cutoff_date
        ));
    }

    /**
     * Get rate limit statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_rate_limiting';
        
        $stats = [
            'total_requests_today' => 0,
            'blocked_ips' => 0,
            'top_consumers' => [],
            'most_blocked_actions' => []
}