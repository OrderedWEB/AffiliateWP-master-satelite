<?php
/**
 * Rate Limiter Class
 *
 * Handles API rate limiting, request throttling, IP blocking, and advanced
 * traffic management for the affiliate cross-domain validation system.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 * @author Richard King, starneconsulting.com
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Rate_Limiter {

    /**
     * Database table name for rate limiting
     *
     * @var string
     */
    private $rate_limit_table;

    /**
     * Cache group for rate limiting data
     *
     * @var string
     */
    private $cache_group = 'affcd_rate_limits';

    /**
     * Default rate limits per action type
     *
     * @var array
     */
    private $default_limits = [
        'validate_code' => ['per_minute' => 30, 'per_hour' => 500],
        'api_request' => ['per_minute' => 60, 'per_hour' => 1000],
        'form_submission' => ['per_minute' => 10, 'per_hour' => 100],
        'failed_validation' => ['per_minute' => 5, 'per_hour' => 50],
        'create_vanity' => ['per_minute' => 2, 'per_hour' => 20],
        'webhook_request' => ['per_minute' => 20, 'per_hour' => 200],
        'domain_verification' => ['per_minute' => 1, 'per_hour' => 10],
        'analytics_query' => ['per_minute' => 100, 'per_hour' => 2000]
    ];

    /**
     * Rate limited actions that require checking
     *
     * @var array
     */
    private $rate_limited_actions = [
        'validate_code',
        'api_request',
        'form_submission',
        'failed_validation',
        'create_vanity',
        'webhook_request',
        'domain_verification',
        'analytics_query'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->rate_limit_table = $wpdb->prefix . 'affcd_rate_limiting';
        
        add_action('rest_api_init', [$this, 'init']);
        add_action('wp_ajax_affcd_reset_rate_limits', [$this, 'ajax_reset_rate_limits']);
        add_action('wp_ajax_affcd_get_rate_stats', [$this, 'ajax_get_rate_statistics']);
        add_action('affcd_cleanup_rate_limits', [$this, 'cleanup_old_records']);
    }

    /**
     * Initialize rate limiter hooks
     */
    public function init() {
        add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
        add_filter('affcd_should_block_request', [$this, 'filter_should_block_request'], 10, 3);
    }

    /**
     * Check rate limit for REST API requests
     *
     * @param mixed $result Response data
     * @param WP_REST_Server $server REST server instance
     * @param WP_REST_Request $request Request object
     * @return mixed Original result or WP_Error
     */
    public function check_rate_limit($result, $server, $request) {
        // Only check for our API endpoints
        if (strpos($request->get_route(), '/affcd/') === false) {
            return $result;
        }

        $identifier = $this->get_rate_limit_identifier($request);
        $action_type = $this->determine_action_type($request);

        // Check if this action should be rate limited
        if (!$this->should_rate_limit($action_type)) {
            return $result;
        }

        // Check if identifier is blocked
        if ($this->is_identifier_blocked($identifier)) {
            return new WP_Error(
                'rate_limit_blocked',
                __('Your access has been temporarily blocked due to rate limit violations.', 'affiliate-cross-domain-full'),
                ['status' => 429, 'blocked_until' => $this->get_block_expiry($identifier)]
            );
        }

        // Check rate limits
        if (!$this->is_within_limits($identifier, $action_type)) {
            $this->handle_rate_limit_exceeded($identifier, $action_type, $request);
            
            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'affiliate-cross-domain-full'),
                [
                    'status' => 429,
                    'retry_after' => $this->get_retry_after($identifier, $action_type),
                    'rate_limit_info' => $this->get_rate_limit_headers($identifier, $action_type)
                ]
            );
        }

        // Record successful request
        $this->record_request($identifier, $action_type, $request);
        
        return $result;
    }

    /**
     * Get rate limit identifier from request
     *
     * @param WP_REST_Request $request Request object
     * @return string Rate limit identifier
     */
    private function get_rate_limit_identifier($request) {
        // Try API key first for more generous limits
        $api_key = $request->get_header('X-API-Key') ?: $request->get_param('api_key');
        if ($api_key && $this->validate_api_key($api_key)) {
            return 'api_key:' . substr(hash('sha256', $api_key), 0, 16);
        }

        // Use authenticated user ID if available
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return 'user:' . $user_id;
        }

        // Fall back to IP address
        return 'ip:' . $this->get_client_ip();
    }

    /**
     * Determine action type from request
     *
     * @param WP_REST_Request $request Request object
     * @return string Action type
     */
    private function determine_action_type($request) {
        $route = $request->get_route();
        $method = $request->get_method();

        // Map routes to action types
        $route_mappings = [
            '/affcd/v1/validate-code' => 'validate_code',
            '/affcd/v1/domains' => $method === 'POST' ? 'create_domain' : 'api_request',
            '/affcd/v1/vanity-codes' => $method === 'POST' ? 'create_vanity' : 'api_request',
            '/affcd/v1/webhook' => 'webhook_request',
            '/affcd/v1/analytics' => 'analytics_query'
        ];

        foreach ($route_mappings as $pattern => $action) {
            if (strpos($route, $pattern) !== false) {
                return $action;
            }
        }

        return 'api_request';
    }

    /**
     * Check if identifier is within rate limits
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action being performed
     * @return bool Within limits
     */
    private function is_within_limits($identifier, $action_type) {
        $limits = $this->get_action_rate_limits($action_type);
        
        // Check minute limit
        if (!$this->check_minute_limit($identifier, $action_type, $limits['per_minute'])) {
            return false;
        }

        // Check hour limit
        if (!$this->check_hour_limit($identifier, $action_type, $limits['per_hour'])) {
            return false;
        }

        return true;
    }

    /**
     * Check minute-based rate limit
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param int $limit Requests per minute limit
     * @return bool Within limit
     */
    private function check_minute_limit($identifier, $action_type, $limit) {
        $cache_key = "minute:{$identifier}:{$action_type}";
        $current_count = wp_cache_get($cache_key, $this->cache_group);
        
        if ($current_count === false) {
            // Check database for current minute
            $current_count = $this->get_minute_count_from_db($identifier, $action_type);
            wp_cache_set($cache_key, $current_count, $this->cache_group, 60);
        }

        return $current_count < $limit;
    }

    /**
     * Check hour-based rate limit
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param int $limit Requests per hour limit
     * @return bool Within limit
     */
    private function check_hour_limit($identifier, $action_type, $limit) {
        $cache_key = "hour:{$identifier}:{$action_type}";
        $current_count = wp_cache_get($cache_key, $this->cache_group);
        
        if ($current_count === false) {
            // Check database for current hour
            $current_count = $this->get_hour_count_from_db($identifier, $action_type);
            wp_cache_set($cache_key, $current_count, $this->cache_group, 3600);
        }

        return $current_count < $limit;
    }

    /**
     * Get current minute count from database
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @return int Request count
     */
    private function get_minute_count_from_db($identifier, $action_type) {
        global $wpdb;
        
        $minute_start = date('Y-m-d H:i:00');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(request_count) FROM {$this->rate_limit_table} 
             WHERE identifier = %s 
             AND action_type = %s 
             AND window_start >= %s
             AND is_blocked = 0",
            $identifier,
            $action_type,
            $minute_start
        ));

        return intval($count);
    }

    /**
     * Get current hour count from database
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @return int Request count
     */
    private function get_hour_count_from_db($identifier, $action_type) {
        global $wpdb;
        
        $hour_start = date('Y-m-d H:00:00');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(request_count) FROM {$this->rate_limit_table} 
             WHERE identifier = %s 
             AND action_type = %s 
             AND window_start >= %s
             AND is_blocked = 0",
            $identifier,
            $action_type,
            $hour_start
        ));

        return intval($count);
    }

    /**
     * Record a request in rate limiting system
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param WP_REST_Request $request Request object
     */
    private function record_request($identifier, $action_type, $request) {
        // Update cache counters
        $this->increment_cache_counters($identifier, $action_type);
        
        // Update database record
        $this->update_database_record($identifier, $action_type, $request);
        
        // Log analytics event
        $this->log_rate_limit_event($identifier, 'request', $action_type, [
            'endpoint' => $request->get_route(),
            'method' => $request->get_method(),
            'user_agent' => $request->get_header('User-Agent'),
            'referer' => $request->get_header('Referer')
        ]);
    }

    /**
     * Increment cache counters for rate limiting
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     */
    private function increment_cache_counters($identifier, $action_type) {
        // Increment minute counter
        $minute_key = "minute:{$identifier}:{$action_type}";
        $minute_count = wp_cache_get($minute_key, $this->cache_group) ?: 0;
        wp_cache_set($minute_key, $minute_count + 1, $this->cache_group, 60);
        
        // Increment hour counter
        $hour_key = "hour:{$identifier}:{$action_type}";
        $hour_count = wp_cache_get($hour_key, $this->cache_group) ?: 0;
        wp_cache_set($hour_key, $hour_count + 1, $this->cache_group, 3600);
    }

    /**
     * Update database rate limit record
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param WP_REST_Request $request Request object
     */
    private function update_database_record($identifier, $action_type, $request) {
        global $wpdb;
        
        $current_minute = date('Y-m-d H:i:00');
        $current_hour = date('Y-m-d H:00:00');
        
        // Update or insert minute window record
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->rate_limit_table} 
             (identifier, action_type, request_count, window_start, window_end, time_window, endpoint) 
             VALUES (%s, %s, 1, %s, %s, 'minute', %s)
             ON DUPLICATE KEY UPDATE 
             request_count = request_count + 1, 
             last_request = CURRENT_TIMESTAMP",
            $identifier,
            $action_type,
            $current_minute,
            date('Y-m-d H:i:59'),
            $request->get_route()
        ));

        // Update or insert hour window record
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->rate_limit_table} 
             (identifier, action_type, request_count, window_start, window_end, time_window, endpoint) 
             VALUES (%s, %s, 1, %s, %s, 'hour', %s)
             ON DUPLICATE KEY UPDATE 
             request_count = request_count + 1, 
             last_request = CURRENT_TIMESTAMP",
            $identifier,
            $action_type,
            $current_hour,
            date('Y-m-d H:59:59'),
            $request->get_route()
        ));
    }

    /**
     * Handle rate limit exceeded situation
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param WP_REST_Request $request Request object
     */
    private function handle_rate_limit_exceeded($identifier, $action_type, $request) {
        // Log violation
        $this->log_rate_limit_event($identifier, 'violation', $action_type, [
            'endpoint' => $request->get_route(),
            'method' => $request->get_method(),
            'user_agent' => $request->get_header('User-Agent'),
            'ip_address' => $this->get_client_ip()
        ]);

        // Check for repeated violations
        $violation_count = $this->get_recent_violation_count($identifier);
        
        // Progressive blocking based on violation severity
        if ($violation_count >= $this->get_block_threshold($action_type)) {
            $this->block_identifier($identifier, $action_type, $violation_count);
        }

        // Send security notification if needed
        $this->maybe_send_security_notification($identifier, $action_type, $violation_count);
    }

    /**
     * Get recent violation count for identifier
     *
     * @param string $identifier Rate limit identifier
     * @return int Violation count
     */
    private function get_recent_violation_count($identifier) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$analytics_table} 
             WHERE event_type = 'rate_limit_violation' 
             AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.identifier')) = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $identifier
        )));
    }

    /**
     * Block identifier for rate limit violations
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param int $violation_count Number of violations
     */
    private function block_identifier($identifier, $action_type, $violation_count) {
        global $wpdb;
        
        $block_duration = $this->calculate_block_duration($violation_count, $action_type);
        $block_until = date('Y-m-d H:i:s', time() + $block_duration);
        
        $result = $wpdb->insert(
            $this->rate_limit_table,
            [
                'identifier' => $identifier,
                'action_type' => 'blocked',
                'request_count' => 0,
                'window_start' => current_time('mysql'),
                'window_end' => $block_until,
                'is_blocked' => 1,
                'block_until' => $block_until,
                'block_reason' => sprintf('Rate limit violations: %d', $violation_count),
                'violation_level' => min($violation_count, 10),
                'time_window' => 'block'
            ],
            ['%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s']
        );

        if ($result !== false) {
            // Clear cache for this identifier
            wp_cache_delete("minute:{$identifier}:*", $this->cache_group);
            wp_cache_delete("hour:{$identifier}:*", $this->cache_group);

            // Log block event
            $this->log_rate_limit_event($identifier, 'block', $action_type, [
                'block_duration' => $block_duration,
                'violation_count' => $violation_count,
                'block_until' => $block_until
            ]);
        }
    }

    /**
     * Calculate block duration based on violation count
     *
     * @param int $violation_count Number of violations
     * @param string $action_type Action type
     * @return int Block duration in seconds
     */
    private function calculate_block_duration($violation_count, $action_type) {
        $base_duration = 300; // 5 minutes
        
        // Progressive blocking - exponential backoff
        $multiplier = min(pow(2, $violation_count - 3), 128); // Max 128x multiplier
        
        // Critical actions get longer blocks
        $critical_actions = ['failed_validation', 'create_vanity'];
        if (in_array($action_type, $critical_actions)) {
            $multiplier *= 2;
        }
        
        return $base_duration * $multiplier;
    }

    /**
     * Check if identifier is blocked
     *
     * @param string $identifier Rate limit identifier
     * @return bool Is blocked
     */
    public function is_identifier_blocked($identifier) {
        global $wpdb;
        
        // Check cache first
        $cache_key = "blocked:{$identifier}";
        $cached = wp_cache_get($cache_key, $this->cache_group);
        if ($cached !== false) {
            return $cached === 'yes';
        }
        
        $current_time = current_time('mysql');
        
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->rate_limit_table} 
             WHERE identifier = %s 
             AND is_blocked = 1 
             AND (block_until IS NULL OR block_until > %s)",
            $identifier,
            $current_time
        ));
        
        $is_blocked = $blocked > 0;
        
        // Cache result for 1 minute
        wp_cache_set($cache_key, $is_blocked ? 'yes' : 'no', $this->cache_group, 60);
        
        return $is_blocked;
    }

    /**
     * Get block expiry time for identifier
     *
     * @param string $identifier Rate limit identifier
     * @return string|null Block expiry time
     */
    private function get_block_expiry($identifier) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT block_until FROM {$this->rate_limit_table} 
             WHERE identifier = %s 
             AND is_blocked = 1 
             AND block_until > NOW()
             ORDER BY block_until DESC
             LIMIT 1",
            $identifier
        ));
    }

    /**
     * Get retry after time in seconds
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @return int Seconds to wait
     */
    private function get_retry_after($identifier, $action_type) {
        // For minute limits, suggest retry after current minute ends
        $minute_count = wp_cache_get("minute:{$identifier}:{$action_type}", $this->cache_group);
        if ($minute_count !== false) {
            return 60 - (time() % 60);
        }
        
        // Default retry after 1 minute
        return 60;
    }

    /**
     * Get rate limit headers for response
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @return array Rate limit headers
     */
    private function get_rate_limit_headers($identifier, $action_type) {
        $limits = $this->get_action_rate_limits($action_type);
        $minute_count = wp_cache_get("minute:{$identifier}:{$action_type}", $this->cache_group) ?: 0;
        $hour_count = wp_cache_get("hour:{$identifier}:{$action_type}", $this->cache_group) ?: 0;
        
        return [
            'X-RateLimit-Limit-Minute' => $limits['per_minute'],
            'X-RateLimit-Remaining-Minute' => max(0, $limits['per_minute'] - $minute_count),
            'X-RateLimit-Limit-Hour' => $limits['per_hour'],
            'X-RateLimit-Remaining-Hour' => max(0, $limits['per_hour'] - $hour_count),
            'X-RateLimit-Reset' => time() + (60 - (time() % 60))
        ];
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $headers_to_check = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers_to_check as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @return bool Is valid
     */
    private function validate_api_key($api_key) {
        if (empty($api_key) || strlen($api_key) < 32) {
            return false;
        }
        
        global $wpdb;
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        
        $valid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$domains_table} 
             WHERE api_key = %s AND status = 'active'",
            $api_key
        ));
        
        return $valid > 0;
    }

    /**
     * Check if action should be rate limited
     *
     * @param string $action_type Action type
     * @return bool Should be rate limited
     */
    private function should_rate_limit($action_type) {
        return in_array($action_type, $this->rate_limited_actions);
    }

    /**
     * Get rate limit settings for action
     *
     * @param string $action_type Action type
     * @return array Rate limit settings
     */
    private function get_action_rate_limits($action_type) {
        $settings = get_option('affcd_api_settings', []);
        $custom_limits = $settings['custom_rate_limits'][$action_type] ?? [];
        
        $defaults = $this->default_limits[$action_type] ?? $this->default_limits['api_request'];
        
        return array_merge($defaults, $custom_limits);
    }

    /**
     * Get block threshold for action type
     *
     * @param string $action_type Action type
     * @return int Violations before blocking
     */
    private function get_block_threshold($action_type) {
        $thresholds = [
            'failed_validation' => 3,
            'create_vanity' => 5,
            'webhook_request' => 10,
            'validate_code' => 15,
            'api_request' => 20
        ];
        
        return $thresholds[$action_type] ?? 10;
    }

    /**
     * Log rate limit event
     *
     * @param string $identifier Rate limit identifier
     * @param string $event_type Event type
     * @param string $action_type Action type
     * @param array $details Event details
     */
    private function log_rate_limit_event($identifier, $event_type, $action_type, $details = []) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        $event_data = [
            'identifier' => $identifier,
            'action_type' => $action_type,
            'details' => $details,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        ];
        
        $wpdb->insert(
            $analytics_table,
            [
                'event_type' => 'rate_limit_' . $event_type,
                'event_data' => wp_json_encode($event_data),
                'status' => 'completed',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Send security notification for severe violations
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param int $violation_count Violation count
     */
    private function maybe_send_security_notification($identifier, $action_type, $violation_count) {
        // Only send for severe violations
        if ($violation_count < 10) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Rate Limit Security Alert', $site_name);
        
        $message = sprintf(
            "Security Alert: High rate limit violations detected\n\n" .
            "Identifier: %s\n" .
            "Action: %s\n" .
            "Violations: %d\n" .
            "Time: %s\n" .
            "IP: %s\n\n" .
            "Please review and take appropriate action if necessary.",
            $identifier,
            $action_type,
            $violation_count,
            current_time('mysql'),
            $this->get_client_ip()
        );
        
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Unblock identifier
     *
     * @param string $identifier Rate limit identifier
     * @return bool Success
     */
    public function unblock_identifier($identifier) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->rate_limit_table,
            [
                'is_blocked' => 0,
                'block_until' => null
            ],
            ['identifier' => $identifier, 'is_blocked' => 1],
            ['%d', '%s'],
            ['%s', '%d']
        );
        
        if ($result !== false) {
            // Clear cache
            wp_cache_delete("blocked:{$identifier}", $this->cache_group);
            
            // Log unblock event
            $this->log_rate_limit_event($identifier, 'unblock', 'manual', [
                'unblocked_by' => get_current_user_id(),
                'unblocked_at' => current_time('mysql')
            ]);
        }
        
        return $result !== false;
    }

    /**
     * Reset rate limits for identifier
     *
     * @param string $identifier Rate limit identifier
     * @return bool Success
     */
    public function reset_rate_limits($identifier) {
        // Clear cache entries
        wp_cache_flush_group($this->cache_group);
        
        // Unblock if blocked
        $this->unblock_identifier($identifier);
        
        // Log reset event
        $this->log_rate_limit_event($identifier, 'reset', 'manual', [
            'reset_by' => get_current_user_id(),
            'reset_at' => current_time('mysql')
        ]);
        
        return true;
    }

    /**
     * Get rate limit status for identifier
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @return array Status information
     */
    public function get_rate_limit_status($identifier, $action_type = 'api_request') {
        $limits = $this->get_action_rate_limits($action_type);
        
        $minute_count = wp_cache_get("minute:{$identifier}:{$action_type}", $this->cache_group);
        if ($minute_count === false) {
            $minute_count = $this->get_minute_count_from_db($identifier, $action_type);
        }
        
        $hour_count = wp_cache_get("hour:{$identifier}:{$action_type}", $this->cache_group);
        if ($hour_count === false) {
            $hour_count = $this->get_hour_count_from_db($identifier, $action_type);
        }
        
        return [
            'identifier' => $identifier,
            'action_type' => $action_type,
            'minute_count' => intval($minute_count),
            'minute_limit' => $limits['per_minute'],
            'minute_remaining' => max(0, $limits['per_minute'] - intval($minute_count)),
            'hour_count' => intval($hour_count),
            'hour_limit' => $limits['per_hour'],
            'hour_remaining' => max(0, $limits['per_hour'] - intval($hour_count)),
            'is_blocked' => $this->is_identifier_blocked($identifier),
            'block_until' => $this->get_block_expiry($identifier),
            'next_reset' => date('Y-m-d H:i:00', strtotime('+1 minute'))
        ];
    }

    /**
     * Get comprehensive rate limit statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = [
            'total_requests_today' => 0,
            'blocked_identifiers' => 0,
            'top_consumers' => [],
            'most_blocked_actions' => [],
            'hourly_breakdown' => [],
            'violation_trends' => []
        ];
        
        $today = date('Y-m-d');
        $current_time = current_time('mysql');
        
        // Get total requests today
        $stats['total_requests_today'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(request_count) FROM {$this->rate_limit_table} 
             WHERE DATE(window_start) = %s AND is_blocked = 0",
            $today
        )));
        
        // Get blocked identifiers count
        $stats['blocked_identifiers'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT identifier) FROM {$this->rate_limit_table} 
             WHERE is_blocked = 1 AND (block_until IS NULL OR block_until > %s)",
            $current_time
        )));
        
        // Get top consumers
        $stats['top_consumers'] = $wpdb->get_results($wpdb->prepare(
            "SELECT identifier, SUM(request_count) as total_requests,
                    COUNT(DISTINCT action_type) as action_types
             FROM {$this->rate_limit_table} 
             WHERE DATE(window_start) = %s AND is_blocked = 0
             GROUP BY identifier 
             ORDER BY total_requests DESC 
             LIMIT 10",
            $today
        ), ARRAY_A);
        
        // Get most blocked actions
        $stats['most_blocked_actions'] = $wpdb->get_results(
            "SELECT action_type, COUNT(*) as block_count,
                    AVG(violation_level) as avg_severity
             FROM {$this->rate_limit_table} 
             WHERE is_blocked = 1 AND DATE(window_start) = CURDATE()
             GROUP BY action_type 
             ORDER BY block_count DESC 
             LIMIT 10",
            ARRAY_A
        );
        
        // Get hourly breakdown for today
        $stats['hourly_breakdown'] = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(window_start) as hour, 
                    SUM(request_count) as requests,
                    COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocks
             FROM {$this->rate_limit_table} 
             WHERE DATE(window_start) = %s
             GROUP BY HOUR(window_start) 
             ORDER BY hour",
            $today
        ), ARRAY_A);
        
        // Get violation trends (last 7 days)
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        $stats['violation_trends'] = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as violations
             FROM {$analytics_table} 
             WHERE event_type = 'rate_limit_violation' 
             AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at) 
             ORDER BY date",
            ARRAY_A
        );
        
        return $stats;
    }

    /**
     * Clean up old rate limit records
     *
     * @param int $days_old Days to keep records
     * @return int Records deleted
     */
    public function cleanup_old_records($days_old = 7) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        // Delete old non-blocked records
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->rate_limit_table} 
             WHERE window_end < %s AND is_blocked = 0",
            $cutoff_date
        ));
        
        // Delete expired blocks
        $deleted += $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->rate_limit_table} 
             WHERE is_blocked = 1 AND block_until < %s",
            current_time('mysql')
        ));
        
        return intval($deleted);
    }

    /**
     * Check advanced rate limiting with burst allowance
     *
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @param int $burst_allowance Additional requests allowed
     * @return bool Within limits including burst
     */
    public function check_advanced_rate_limit($identifier, $action_type, $burst_allowance = 0) {
        $limits = $this->get_action_rate_limits($action_type);
        
        // Check regular limits first
        if ($this->is_within_limits($identifier, $action_type)) {
            return true;
        }
        
        // Check if burst allowance is available
        if ($burst_allowance > 0) {
            $burst_key = "burst:{$identifier}:{$action_type}";
            $burst_used = wp_cache_get($burst_key, $this->cache_group) ?: 0;
            
            if ($burst_used < $burst_allowance) {
                // Allow request and consume burst allowance
                wp_cache_set($burst_key, $burst_used + 1, $this->cache_group, 3600);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Update rate limit settings
     *
     * @param array $settings Rate limit settings
     * @return bool Success
     */
    public function update_rate_limit_settings($settings) {
        $current_settings = get_option('affcd_api_settings', []);
        
        $rate_limit_settings = [
            'rate_limit_per_minute' => absint($settings['rate_limit_per_minute'] ?? 60),
            'rate_limit_per_hour' => absint($settings['rate_limit_per_hour'] ?? 1000),
            'rate_limit_enabled' => !empty($settings['rate_limit_enabled']),
            'custom_rate_limits' => $settings['custom_rate_limits'] ?? []
        ];
        
        $updated_settings = array_merge($current_settings, $rate_limit_settings);
        
        // Clear cache when settings change
        wp_cache_flush_group($this->cache_group);
        
        return update_option('affcd_api_settings', $updated_settings);
    }

    /**
     * Filter to determine if request should be blocked
     *
     * @param bool $should_block Current block status
     * @param string $identifier Rate limit identifier
     * @param string $action_type Action type
     * @return bool Should block request
     */
    public function filter_should_block_request($should_block, $identifier, $action_type) {
        if ($should_block) {
            return true;
        }
        
        // Check if identifier is blocked by rate limiter
        return $this->is_identifier_blocked($identifier);
    }

    /**
     * AJAX handler for resetting rate limits
     */
    public function ajax_reset_rate_limits() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !check_ajax_referer('affcd_admin_nonce', 'nonce', false)) {
            wp_die('Unauthorised access');
        }
        
        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        
        if (empty($identifier)) {
            wp_send_json_error('Invalid identifier');
        }
        
        $success = $this->reset_rate_limits($identifier);
        
        if ($success) {
            wp_send_json_success([
                'message' => 'Rate limits reset successfully',
                'status' => $this->get_rate_limit_status($identifier)
            ]);
        } else {
            wp_send_json_error('Failed to reset rate limits');
        }
    }

    /**
     * AJAX handler for getting rate limit statistics
     */
    public function ajax_get_rate_statistics() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !check_ajax_referer('affcd_admin_nonce', 'nonce', false)) {
            wp_die('Unauthorised access');
        }
        
        $stats = $this->get_statistics();
        wp_send_json_success($stats);
    }

    /**
     * Get whitelist status for identifier
     *
     * @param string $identifier Rate limit identifier
     * @return bool Is whitelisted
     */
    public function is_whitelisted($identifier) {
        $whitelist = get_option('affcd_rate_limit_whitelist', []);
        
        // Check exact match
        if (in_array($identifier, $whitelist)) {
            return true;
        }
        
        // Check IP ranges for IP identifiers
        if (strpos($identifier, 'ip:') === 0) {
            $ip = substr($identifier, 3);
            foreach ($whitelist as $whitelisted) {
                if (strpos($whitelisted, '/') !== false && $this->ip_in_range($ip, $whitelisted)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if IP is in CIDR range
     *
     * @param string $ip IP address
     * @param string $range CIDR range
     * @return bool Is in range
     */
    private function ip_in_range($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4_in_range($ip, $subnet, $mask);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6_in_range($ip, $subnet, $mask);
        }
        
        return false;
    }

    /**
     * Check if IPv4 is in range
     *
     * @param string $ip IPv4 address
     * @param string $subnet Subnet address
     * @param int $mask Subnet mask
     * @return bool Is in range
     */
    private function ipv4_in_range($ip, $subnet, $mask) {
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - $mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }

    /**
     * Check if IPv6 is in range
     *
     * @param string $ip IPv6 address
     * @param string $subnet Subnet address
     * @param int $mask Subnet mask
     * @return bool Is in range
     */
    private function ipv6_in_range($ip, $subnet, $mask) {
        $ip_bin = inet_pton($ip);
        $subnet_bin = inet_pton($subnet);
        
        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }
        
        $mask_bytes = intval($mask / 8);
        $mask_bits = $mask % 8;
        
        // Compare full bytes
        if ($mask_bytes > 0 && substr($ip_bin, 0, $mask_bytes) !== substr($subnet_bin, 0, $mask_bytes)) {
            return false;
        }
        
        // Compare partial byte
        if ($mask_bits > 0 && $mask_bytes < 16) {
            $ip_byte = ord($ip_bin[$mask_bytes]);
            $subnet_byte = ord($subnet_bin[$mask_bytes]);
            $byte_mask = 0xFF << (8 - $mask_bits);
            
            return ($ip_byte & $byte_mask) === ($subnet_byte & $byte_mask);
        }
        
        return true;
    }

    /**
     * Export rate limit data for analysis
     *
     * @param string $format Export format (csv, json)
     * @param int $days Number of days to export
     * @return string|array Export data
     */
    public function export_rate_limit_data($format = 'csv', $days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT identifier, action_type, request_count, window_start, window_end,
                    time_window, is_blocked, block_until, violation_level, endpoint,
                    last_request, created_at
             FROM {$this->rate_limit_table} 
             WHERE created_at >= %s
             ORDER BY created_at DESC",
            $cutoff_date
        ), ARRAY_A);
        
        if ($format === 'json') {
            return wp_json_encode($data);
        }
        
        // CSV format
        if (empty($data)) {
            return '';
        }
        
        $csv = fopen('php://temp', 'r+');
        
        // Add header row
        fputcsv($csv, array_keys($data[0]));
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($csv, $row);
        }
        
        rewind($csv);
        $csv_string = stream_get_contents($csv);
        fclose($csv);
        
        return $csv_string;
    }
}

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

/**
 * Helper function to check rate limits
 *
 * @param string $action_type Action type
 * @param string $identifier Optional identifier
 * @return bool Within limits
 */
function affcd_check_rate_limit($action_type, $identifier = null) {
    $rate_limiter = affcd_get_rate_limiter();
    
    if (!$identifier) {
        // Create mock request to determine identifier
        $request = new stdClass();
        $request->headers = getallheaders();
        $identifier = $rate_limiter->get_rate_limit_identifier($request);
    }
    
    return !$rate_limiter->is_identifier_blocked($identifier) && 
           $rate_limiter->is_within_limits($identifier, $action_type);
}

/**
 * Helper function to get client IP safely
 *
 * @return string Client IP address
 */
function affcd_get_client_ip() {
    $headers_to_check = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers_to_check as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}