<?php
/**
 * Enhanced Security Manager for Affiliate Cross Domain System
 *
 * Plugin: Affiliate Cross Domain System (Master)
 *
 * Handles advanced security features including rate limiting, fraud detection,
 * domain authorisation, and comprehensive security logging.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Security_Manager {

    private $rate_limit_table;
    private $security_logs_table;
    private $fraud_detection_table;
    private $authorized_domains_table;
    private $cache_prefix = 'affcd_security_';

    // Security thresholds
    private $rate_limits = [
        'api_request'       => ['limit' => 100, 'window' => 3600], // 100 per hour
        'validate_code'     => ['limit' => 50,  'window' => 3600], // 50 per hour
        'create_vanity'     => ['limit' => 10,  'window' => 3600], // 10 per hour
        'failed_validation' => ['limit' => 5,   'window' => 300],  // 5 per 5 minutes
        'form_submission'   => ['limit' => 20,  'window' => 3600], // 20 per hour
    ];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->rate_limit_table         = $wpdb->prefix . 'affcd_rate_limiting';
        $this->security_logs_table      = $wpdb->prefix . 'affcd_security_logs';
        $this->fraud_detection_table    = $wpdb->prefix . 'affcd_fraud_detection';
        $this->authorized_domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        // Initialize hooks
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_affcd_security_test', [$this, 'ajax_security_test']);
        add_action('affcd_cleanup_security_logs', [$this, 'cleanup_old_logs']);
        add_action('affcd_analyze_fraud_patterns', [$this, 'analyze_fraud_patterns']);

        // Security headers
        add_action('send_headers', [$this, 'add_security_headers']);

        // API request filtering
        add_filter('affcd_api_request_allowed', [$this, 'filter_api_requests'], 10, 2);
    }

    /**
     * Initialize security manager
     */
    public function init() {
        $this->maybe_create_tables();
        $this->schedule_cleanup_tasks();
        $this->load_rate_limits();
        $this->setup_fraud_detection();
    }

    /**
     * Create all required security tables
     */
    private function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_rate_limit = "CREATE TABLE IF NOT EXISTS {$this->rate_limit_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            action_type varchar(100) NOT NULL,
            request_count int unsigned DEFAULT 1,
            window_start datetime NOT NULL,
            last_request datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_blocked tinyint(1) DEFAULT 0,
            block_until datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_action (identifier, action_type, window_start),
            KEY action_type (action_type),
            KEY window_start (window_start),
            KEY is_blocked (is_blocked)
        ) $charset_collate;";

        $sql_security_logs = "CREATE TABLE IF NOT EXISTS {$this->security_logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            severity enum('low','medium','high','critical') DEFAULT 'medium',
            source_ip varchar(45) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            domain varchar(255),
            user_agent text,
            event_data longtext,
            context_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY severity (severity),
            KEY source_ip (source_ip),
            KEY user_id (user_id),
            KEY domain (domain),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_fraud_detection = "CREATE TABLE IF NOT EXISTS {$this->fraud_detection_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            fraud_type varchar(100) NOT NULL,
            risk_score int unsigned DEFAULT 0,
            pattern_data longtext,
            detection_count int unsigned DEFAULT 1,
            first_detected datetime DEFAULT CURRENT_TIMESTAMP,
            last_detected datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            actions_taken longtext,
            PRIMARY KEY (id),
            KEY identifier (identifier),
            KEY fraud_type (fraud_type),
            KEY risk_score (risk_score),
            KEY first_detected (first_detected),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_authorized_domains = "CREATE TABLE IF NOT EXISTS {$this->authorized_domains_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            domain_hash varchar(64) NOT NULL,
            status enum('active','inactive','suspended','pending') DEFAULT 'pending',
            api_key varchar(100),
            rate_limit_override longtext,
            security_settings longtext,
            last_verified datetime NULL,
            verification_failures int unsigned DEFAULT 0,
            total_requests bigint(20) unsigned DEFAULT 0,
            blocked_requests bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned,
            PRIMARY KEY (id),
            UNIQUE KEY domain (domain),
            UNIQUE KEY domain_hash (domain_hash),
            KEY status (status),
            KEY api_key (api_key),
            KEY last_verified (last_verified),
            KEY created_by (created_by)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_rate_limit);
        dbDelta($sql_security_logs);
        dbDelta($sql_fraud_detection);
        dbDelta($sql_authorized_domains);
    }

    /* 
     * Rate limiting
     */

    public function check_rate_limit($action_type, $identifier = null) {
        global $wpdb;

        // Use client IP consistently as the identifier
        if (!$identifier) {
            $identifier = $this->get_client_ip();
        }

        $rate_config  = $this->rate_limits[$action_type] ?? ['limit' => 60, 'window' => 3600];
        $bucket       = (int) floor(time() / $rate_config['window']) * $rate_config['window'];
        $window_start = date('Y-m-d H:i:s', $bucket);

        $current_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(request_count),0) FROM {$this->rate_limit_table}
             WHERE identifier = %s AND action_type = %s
               AND window_start = %s AND is_blocked = 0",
            $identifier, $action_type, $window_start
        ));

        if ($current_count >= $rate_config['limit']) {
            $this->handle_rate_limit_exceeded($identifier, $action_type, $current_count);
            return false;
        }

        $this->update_rate_limit_record($identifier, $action_type, $window_start);
        return true;
    }

    private function update_rate_limit_record($identifier, $action_type, $window_start) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->rate_limit_table}
             (identifier, action_type, request_count, window_start)
             VALUES (%s, %s, 1, %s)
             ON DUPLICATE KEY UPDATE
                request_count = request_count + 1,
                last_request = CURRENT_TIMESTAMP",
            $identifier, $action_type, $window_start
        ));
    }

    private function handle_rate_limit_exceeded($identifier, $action_type, $count) {
        global $wpdb;
        $block_duration = $this->calculate_block_duration($action_type, $count);
        $block_until = date('Y-m-d H:i:s', time() + $block_duration);

        $wpdb->update(
            $this->rate_limit_table,
            ['is_blocked' => 1, 'block_until' => $block_until],
            ['identifier' => $identifier, 'action_type' => $action_type],
            ['%d', '%s'],
            ['%s', '%s']
        );

        $this->log_security_event('rate_limit_exceeded', 'medium', [
            'identifier'     => $identifier,
            'action_type'    => $action_type,
            'request_count'  => $count,
            'block_duration' => $block_duration,
        ]);

        $this->check_fraud_patterns($identifier, 'rate_limit_abuse', [
            'action_type' => $action_type,
            'count'       => $count,
        ]);
    }

    private function calculate_block_duration($action_type, $count) {
        $base_duration = 300; // 5 min
        $multiplier = min(floor($count / 10), 10);
        $action_multipliers = [
            'failed_validation' => 2,
            'api_request'       => 1,
            'create_vanity'     => 3,
            'form_submission'   => 1.5,
        ];
        $action_multiplier = $action_multipliers[$action_type] ?? 1;

        return (int) ($base_duration * (1 + $multiplier) * $action_multiplier);
    }

    /* 
     * Domain authorisation
     **/

    public function is_domain_authorized($domain) {
        global $wpdb;

        $clean_domain = $this->clean_domain($domain);
        if (!$clean_domain) {
            return false;
        }

        $cache_key = $this->cache_prefix . 'domain_' . md5($clean_domain);
        $cached = wp_cache_get($cache_key, 'affcd_security');
        if ($cached !== false) {
            return $cached === 'authorized';
        }

        $domain_hash = hash('sha256', $clean_domain);
        $domain_record = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$this->authorized_domains_table}
             WHERE domain_hash = %s",
            $domain_hash
        ));

        $is_authorized = $domain_record && $domain_record->status === 'active';

        wp_cache_set($cache_key, $is_authorized ? 'authorized' : 'unauthorized', 'affcd_security', 300);

        if ($is_authorized) {
            $this->update_domain_stats($domain_hash, 'request');
        } else {
            $this->log_security_event('unauthorized_domain', 'medium', [
                'domain' => $clean_domain,
                'status' => $domain_record->status ?? 'not_found',
            ]);
        }

        return $is_authorized;
    }

    /**
     * Authorize domain
     */
    public function authorize_domain($domain, $settings = []) {
        global $wpdb;
        
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $clean_domain = $this->clean_domain($domain);
        if (!$clean_domain) {
            return new WP_Error('invalid_domain', __('Invalid domain format.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_hash = hash('sha256', $clean_domain);
        $api_key = $this->generate_api_key();
        
        $result = $wpdb->insert(
            $this->authorized_domains_table,
            [
                'domain' => $clean_domain,
                'domain_hash' => $domain_hash,
                'status' => 'active',
                'api_key' => $api_key,
                'security_settings' => wp_json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_by' => get_current_user_id()
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to authorize domain.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Clear cache
        $cache_key = $this->cache_prefix . 'domain_' . md5($clean_domain);
        wp_cache_delete($cache_key, 'affcd_security');
        
        // Log authorisation
        $this->log_security_event('domain_authorized', 'low', [
            'domain' => $clean_domain,
            'api_key' => substr($api_key, 0, 8) . '...'
        ]);
        
        return [
            'domain_id' => $wpdb->insert_id,
            'api_key' => $api_key,
            'domain' => $clean_domain
        ];
    }

    /**
     * Validate API key
     */
    public function validate_api_key($api_key, $domain = '') {
        global $wpdb;
        
        if (empty($api_key)) {
            return false;
        }
        
        // Cache key must consider domain filter too
        $cache_key = $this->cache_prefix . 'api_key_' . md5($api_key . '|' . strtolower((string) $domain));
        $cached = wp_cache_get($cache_key, 'affcd_security');
        if ($cached !== false) {
            return $cached;
        }
        
        $sql = "SELECT d.domain, d.status, d.security_settings 
                FROM {$this->authorized_domains_table} d 
                WHERE d.api_key = %s AND d.status = 'active'";
        $params = [$api_key];
        
        if (!empty($domain)) {
            $sql .= " AND d.domain = %s";
            $params[] = $this->clean_domain($domain);
        }
        
        $result = $wpdb->get_row($wpdb->prepare($sql, $params));
        
        // Cache result
        wp_cache_set($cache_key, $result ?: false, 'affcd_security', 300);
        
        if (!$result) {
            $this->log_security_event('invalid_api_key', 'medium', [
                'api_key' => substr($api_key, 0, 8) . '...',
                'domain' => $domain
            ]);
        }
        
        return $result;
    }

    /**
     * Detect fraud patterns
     */
    public function check_fraud_patterns($identifier, $pattern_type, $data = []) {
        global $wpdb;
        
        $risk_score = $this->calculate_risk_score($pattern_type, $data);
        
        if ($risk_score < 30) { // Low risk threshold
            return false;
        }
        
        // Update or insert fraud detection record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, detection_count, risk_score FROM {$this->fraud_detection_table} 
             WHERE identifier = %s AND fraud_type = %s AND is_active = 1",
            $identifier, $pattern_type
        ));
        
        if ($existing) {
            // Update existing record
            $new_count = $existing->detection_count + 1;
            $new_risk_score = min(100, $existing->risk_score + $risk_score);
            
            $wpdb->update(
                $this->fraud_detection_table,
                [
                    'detection_count' => $new_count,
                    'risk_score' => $new_risk_score,
                    'pattern_data' => wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ],
                ['id' => $existing->id],
                ['%d', '%d', '%s'],
                ['%d']
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $this->fraud_detection_table,
                [
                    'identifier' => $identifier,
                    'fraud_type' => $pattern_type,
                    'risk_score' => $risk_score,
                    'pattern_data' => wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ],
                ['%s', '%s', '%d', '%s']
            );
        }
        
        // Check if action needed
        $final_risk_score = isset($new_risk_score) ? $new_risk_score : $risk_score;
        if ($final_risk_score >= 70) {
            $this->handle_high_risk_fraud($identifier, $pattern_type, $final_risk_score, $data);
        }
        
        // Log fraud detection
        $this->log_security_event('fraud_pattern_detected', 'high', [
            'identifier' => $identifier,
            'pattern_type' => $pattern_type,
            'risk_score' => $final_risk_score,
            'data' => $data
        ]);
        
        return true;
    }

    /**
     * Calculate risk score
     */
    private function calculate_risk_score($pattern_type, $data) {
        $base_scores = [
            'rate_limit_abuse' => 40,
            'invalid_codes'    => 30,
            'suspicious_ip'    => 50,
            'bot_behavior'     => 60,
            'multiple_domains' => 35,
            'unusual_patterns' => 25
        ];
        
        $base_score = $base_scores[$pattern_type] ?? 20;
        
        // Adjust based on data
        $multiplier = 1.0;
        
        if (isset($data['count']) && (int)$data['count'] > 10) {
            $multiplier += 0.5;
        }
        
        if (isset($data['velocity']) && (float)$data['velocity'] > 100) {
            $multiplier += 0.3;
        }
        
        return min(100, (int) round($base_score * $multiplier));
    }

    /**
     * Handle high risk fraud
     */
    private function handle_high_risk_fraud($identifier, $pattern_type, $risk_score, $data) {
        global $wpdb;
        
        $actions_taken = [];
        
        // Block temporarily
        $block_duration = $this->calculate_fraud_block_duration($risk_score);
        $this->block_identifier($identifier, $block_duration);
        $actions_taken[] = "blocked_for_{$block_duration}_seconds";
        
        // Notify administrators
        if ($risk_score >= 90) {
            $this->notify_administrators($identifier, $pattern_type, $risk_score, $data);
            $actions_taken[] = 'admin_notified';
        }
        
        // Update fraud record with actions
        $wpdb->update(
            $this->fraud_detection_table,
            ['actions_taken' => wp_json_encode($actions_taken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
            [
                'identifier' => $identifier,
                'fraud_type' => $pattern_type,
                'is_active' => 1
            ],
            ['%s'],
            ['%s', '%s', '%d']
        );
        
        // Log critical event
        $this->log_security_event('high_risk_fraud_handled', 'critical', [
            'identifier' => $identifier,
            'pattern_type' => $pattern_type,
            'risk_score' => $risk_score,
            'actions_taken' => $actions_taken
        ]);
    }

    /**
     * Block identifier
     */
    private function block_identifier($identifier, $duration) {
        global $wpdb;
        
        $block_until = date('Y-m-d H:i:s', time() + (int)$duration);
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->rate_limit_table} 
             SET is_blocked = 1, block_until = %s 
             WHERE identifier = %s",
            $block_until, $identifier
        ));
    }

    /**
     * Log security event
     */
    public function log_security_event($event_type, $severity, $event_data = [], $context = []) {
        global $wpdb;
        
        $source_ip = $this->get_client_ip();
        $user_id   = get_current_user_id() ?: null;
        $domain    = $this->get_current_domain();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $wpdb->insert(
            $this->security_logs_table,
            [
                'event_type'   => $event_type,
                'severity'     => $severity,
                'source_ip'    => $source_ip,
                'user_id'      => $user_id,
                'domain'       => $domain,
                'user_agent'   => $user_agent,
                'event_data'   => wp_json_encode($event_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'context_data' => wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
        
        // Real-time alerting for critical events
        if ($severity === 'critical') {
            $this->send_critical_alert($event_type, $event_data);
        }
    }

    /**
     * Log activity (wrapper for common events)
     */
    public function log_activity($activity_type, $data = []) {
        $severity_map = [
            'vanity_code_created'   => 'low',
            'vanity_code_updated'   => 'low',
            'vanity_code_deleted'   => 'medium',
            'code_validation_success' => 'low',
            'code_validation_failed' => 'medium',
            'api_request'           => 'low',
            'unauthorized_access'   => 'high'
        ];
        
        $severity = $severity_map[$activity_type] ?? 'medium';
        $this->log_security_event($activity_type, $severity, $data);
    }

    /**
     * Get security dashboard data
     */
    public function get_security_dashboard_data($period = '24h') {
        global $wpdb;
        
        $time_clause = $this->get_time_clause($period);
        
        // Get event counts by severity
        $severity_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT severity, COUNT(*) as count 
             FROM {$this->security_logs_table} 
             WHERE created_at >= %s 
             GROUP BY severity",
            $time_clause
        ), ARRAY_A);
        
        // Get top event types
        $top_events = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->security_logs_table} 
             WHERE created_at >= %s 
             GROUP BY event_type 
             ORDER BY count DESC 
             LIMIT 10",
            $time_clause
        ), ARRAY_A);
        
        // Get blocked IPs (no prepare needed: no placeholders)
        $blocked_ips = $wpdb->get_results(
            "SELECT identifier, action_type, block_until 
             FROM {$this->rate_limit_table} 
             WHERE is_blocked = 1 AND block_until > NOW()",
            ARRAY_A
        );
        
        // Get fraud detections
        $fraud_detections = $wpdb->get_results($wpdb->prepare(
            "SELECT fraud_type, COUNT(*) as count, AVG(risk_score) as avg_risk 
             FROM {$this->fraud_detection_table} 
             WHERE first_detected >= %s 
             GROUP BY fraud_type",
            $time_clause
        ), ARRAY_A);
        
        return [
            'severity_counts' => $severity_counts,
            'top_events' => $top_events,
            'blocked_ips' => $blocked_ips,
            'fraud_detections' => $fraud_detections,
            'period' => $period
        ];
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add headers for API requests
        if (!$this->is_api_request()) {
            return;
        }
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block'); // legacy; harmless
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
  

        // Add rate limit headers
        $remaining = $this->get_rate_limit_remaining();
        if ($remaining !== null) {
            header("X-RateLimit-Remaining: {$remaining}");
        }
    }

    /**
     * Filter API requests
     */
    public function filter_api_requests($allowed, $request) {
        if (!$allowed) {
            return false;
        }
        
        $client_ip = $this->get_client_ip();
        
        // Check if IP is blocked
        if ($this->is_ip_blocked($client_ip)) {
            $this->log_security_event('blocked_ip_attempt', 'medium', [
                'ip' => $client_ip,
                'endpoint' => $request->get_route()
            ]);
            return false;
        }
        
        // Check rate limits (use IP as identifier consistently)
        if (!$this->check_rate_limit('api_request', $client_ip)) {
            return false;
        }
        
        // Additional security checks
        if ($this->detect_bot_behavior($request)) {
            $this->check_fraud_patterns($client_ip, 'bot_behavior', [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'endpoint' => $request->get_route()
            ]);
        }
        
        return true;
    }

    /**
     * Get client identifier (IP + User Agent hash)
     * (Kept for potential use in other contexts)
     */
    public function get_client_identifier() {
        $ip = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $ip . '|' . $user_agent);
    }

    /**
     * Get client IP
     */
    public function get_client_ip() {
        $ip_headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Clean domain
     */
    private function clean_domain($domain) {
        // Remove protocol
        $domain = preg_replace('#^https?://#i', '', $domain);
        
        // Remove www
        $domain = preg_replace('#^www\.#i', '', $domain);
        
        // Remove trailing slash and path
        $domain = strtok($domain, '/');
        
        // Validate domain format
        if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return strtolower($domain);
    }

    /**
     * Generate API key
     */
    private function generate_api_key() {
        return 'affcd_' . wp_generate_password(32, false);
    }

    /**
     * Update domain stats
     */
    private function update_domain_stats($domain_hash, $stat_type) {
        global $wpdb;
        
        $field = $stat_type === 'block' ? 'blocked_requests' : 'total_requests';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->authorized_domains_table} 
             SET {$field} = {$field} + 1 
             WHERE domain_hash = %s",
            $domain_hash
        ));
    }

    /**
     * Check if IP is blocked
     */
    private function is_ip_blocked($ip) {
        global $wpdb;
        
        $blocked = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->rate_limit_table} 
             WHERE identifier = %s AND is_blocked = 1 AND block_until > NOW()",
            $ip
        ));
        
        return $blocked > 0;
    }

    /**
     * Detect bot behavior
     */
    private function detect_bot_behavior($request) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Common bot patterns
        $bot_patterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i'
        ];
        
        foreach ($bot_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        // Check for unusual request patterns
        $suspicious_headers = [
            'HTTP_X_FORWARDED_FOR' => 10, // Too many forwarded IPs
            'HTTP_VIA' => 5 // Proxy chains
        ];
        
        foreach ($suspicious_headers as $header => $max_count) {
            if (!empty($_SERVER[$header]) && substr_count($_SERVER[$header], ',') > $max_count) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get rate limit remaining
     */
    private function get_rate_limit_remaining() {
        global $wpdb;
        
        $identifier = $this->get_client_ip(); // consistent with rate limiting
        $limit  = (int) ($this->rate_limits['api_request']['limit'] ?? 100);
        $window = (int) ($this->rate_limits['api_request']['window'] ?? 3600);

        $bucket       = (int) floor(time() / $window) * $window;
        $window_start = date('Y-m-d H:i:s', $bucket);
        
        $current_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(request_count), 0) FROM {$this->rate_limit_table} 
             WHERE identifier = %s AND action_type = %s AND window_start = %s",
            $identifier, 'api_request', $window_start
        ));
        
        return max(0, $limit - $current_count);
    }

    /**
     * Check if current request is API request
     */
    private function is_api_request() {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/affcd/') !== false;
    }

    /**
     * Get current domain
     */
    private function get_current_domain() {
        return $_SERVER['HTTP_HOST'] ?? '';
    }

    /**
     * Get time clause for queries
     */
    private function get_time_clause($period) {
        $now = time();
        $seconds = match ($period) {
            '1h'  => 3600,
            '24h' => 24 * 3600,
            '7d'  => 7 * 24 * 3600,
            '30d' => 30 * 24 * 3600,
            default => 24 * 3600,
        };
        return date('Y-m-d H:i:s', $now - $seconds);
    }

    /**
     * Calculate fraud block duration
     */
    private function calculate_fraud_block_duration($risk_score) {
        $base_duration = 900; // 15 minutes
        
        if ($risk_score >= 90) {
            return $base_duration * 8; // 2 hours
        } elseif ($risk_score >= 80) {
            return $base_duration * 4; // 1 hour
        } elseif ($risk_score >= 70) {
            return $base_duration * 2; // 30 minutes
        }
        
        return $base_duration;
    }

    /**
     * Notify administrators
     */
    private function notify_administrators($identifier, $pattern_type, $risk_score, $data) {
        $admin_emails = (array) get_option('affcd_security_admin_emails', []);
        if (!$admin_emails) {
            $fallback = get_option('admin_email');
            if (!empty($fallback)) {
                $admin_emails = [(string) $fallback];
            }
        }
        
        if (!$admin_emails) {
            return;
        }

        $subject = sprintf(
            __('[SECURITY ALERT] High Risk Fraud Detection - %s', 'affiliatewp-cross-domain-plugin-suite'),
            $pattern_type
        );
        
        $message = sprintf(
            __("High risk fraud pattern detected:\n\nIdentifier: %s\nPattern: %s\nRisk Score: %d\nData: %s\n\nPlease review immediately.", 'affiliatewp-cross-domain-plugin-suite'),
            $identifier,
            $pattern_type,
            (int) $risk_score,
            wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        
        foreach ($admin_emails as $email) {
            wp_mail($email, $subject, $message);
        }
    }

    /**
     * Send critical alert
     */
    private function send_critical_alert($event_type, $event_data) {
        // Implementation for real-time alerting (Slack, SMS, etc.)
        do_action('affcd_critical_security_alert', $event_type, $event_data);
    }

    /**
     * Load rate limits from settings
     */
    private function load_rate_limits() {
        $custom_limits = get_option('affcd_rate_limits', []);
        $this->rate_limits = array_merge($this->rate_limits, $custom_limits);
    }

    /**
     * Setup fraud detection
     */
    private function setup_fraud_detection() {
        // Schedule fraud pattern analysis
        if (!wp_next_scheduled('affcd_analyze_fraud_patterns')) {
            wp_schedule_event(time(), 'hourly', 'affcd_analyze_fraud_patterns');
        }
    }

    /**
     * Schedule cleanup tasks
     */
    private function schedule_cleanup_tasks() {
        if (!wp_next_scheduled('affcd_cleanup_security_logs')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_security_logs');
        }
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = (int) get_option('affcd_log_retention_days', 90);
        $cutoff_date = date('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));
        
        // Clean security logs
        $deleted_logs = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->security_logs_table} WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Clean old rate limit records
        $deleted_rate_limits = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->rate_limit_table} 
             WHERE window_start < %s AND is_blocked = 0",
            $cutoff_date
        ));
        
        // Clean resolved fraud detections
        $deleted_fraud = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->fraud_detection_table} 
             WHERE first_detected < %s AND is_active = 0",
            $cutoff_date
        ));
        
        // Log cleanup results
        $this->log_security_event('security_cleanup', 'low', [
            'deleted_logs' => $deleted_logs,
            'deleted_rate_limits' => $deleted_rate_limits,
            'deleted_fraud_records' => $deleted_fraud,
            'retention_days' => $retention_days
        ]);
    }

    /**
     * Analyze fraud patterns
     */
    public function analyze_fraud_patterns() {
        global $wpdb;
        
        // Analyze IP patterns
        $suspicious_ips = $wpdb->get_results(
            "SELECT source_ip, COUNT(*) as event_count, 
                    COUNT(DISTINCT event_type) as event_types
             FROM {$this->security_logs_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND severity IN ('medium', 'high', 'critical')
             GROUP BY source_ip 
             HAVING event_count > 10 OR event_types > 3
             ORDER BY event_count DESC",
            ARRAY_A
        );
        
        foreach ($suspicious_ips as $ip_data) {
            $this->check_fraud_patterns($ip_data['source_ip'], 'suspicious_ip', [
                'event_count' => $ip_data['event_count'],
                'event_types' => $ip_data['event_types'],
                'velocity'    => $ip_data['event_count'] / 24 // events per hour
            ]);
        }
        
        // Analyze domain patterns
        $suspicious_domains = $wpdb->get_results(
            "SELECT domain, COUNT(*) as request_count
             FROM {$this->security_logs_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND event_type = 'unauthorized_domain'
             GROUP BY domain 
             HAVING request_count > 50
             ORDER BY request_count DESC",
            ARRAY_A
        );
        
        foreach ($suspicious_domains as $domain_data) {
            $this->check_fraud_patterns($domain_data['domain'], 'multiple_domains', [
                'request_count' => $domain_data['request_count']
            ]);
        }
        
        // Log analysis completion
        $this->log_security_event('fraud_analysis_completed', 'low', [
            'suspicious_ips'     => count($suspicious_ips),
            'suspicious_domains' => count($suspicious_domains)
        ]);
    }

    /**
     * AJAX: Security test endpoint
     */
    public function ajax_security_test() {
        check_ajax_referer('affcd_security_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die(__('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $test_type   = sanitize_text_field($_POST['test_type'] ?? '');
        $test_target = sanitize_text_field($_POST['test_target'] ?? '');
        
        $results = [];
        
        switch ($test_type) {
            case 'domain_verification':
                $results = $this->test_domain_verification($test_target);
                break;
            case 'api_key_validation':
                $results = $this->test_api_key_validation($test_target);
                break;
            case 'rate_limit_status':
                $results = $this->test_rate_limit_status($test_target);
                break;
            case 'fraud_detection':
                $results = $this->test_fraud_detection($test_target);
                break;
            default:
                $results = ['error' => __('Invalid test type.', 'affiliatewp-cross-domain-plugin-suite')];
        }
        
        wp_send_json_success($results);
    }

    /**
     * Test domain verification
     */
    private function test_domain_verification($domain) {
        $clean_domain = $this->clean_domain($domain);
        
        if (!$clean_domain) {
            return [
                'status'  => 'error',
                'message' => __('Invalid domain format.', 'affiliatewp-cross-domain-plugin-suite')
            ];
        }
        
        $is_authorized = $this->is_domain_authorized($clean_domain);
        
        return [
            'status'     => $is_authorized ? 'success' : 'failed',
            'message'    => $is_authorized ? 
                __('Domain is authorized.', 'affiliatewp-cross-domain-plugin-suite') : 
                __('Domain is not authorized.', 'affiliatewp-cross-domain-plugin-suite'),
            'domain'     => $clean_domain,
            'authorized' => $is_authorized
        ];
    }

    /**
     * Test API key validation
     */
    private function test_api_key_validation($api_key) {
        $validation_result = $this->validate_api_key($api_key);
        
        return [
            'status'  => $validation_result ? 'success' : 'failed',
            'message' => $validation_result ? 
                __('API key is valid.', 'affiliatewp-cross-domain-plugin-suite') : 
                __('API key is invalid.', 'affiliatewp-cross-domain-plugin-suite'),
            'api_key' => substr($api_key, 0, 8) . '...',
            'valid'   => (bool) $validation_result,
            'domain'  => $validation_result->domain ?? null
        ];
    }

    /**
     * Test rate limit status
     */
    private function test_rate_limit_status($identifier) {
        global $wpdb;
        
        $current_limits = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, request_count, window_start, is_blocked, block_until
             FROM {$this->rate_limit_table} 
             WHERE identifier = %s
             ORDER BY window_start DESC
             LIMIT 10",
            $identifier
        ), ARRAY_A);
        
        return [
            'status'         => 'success',
            'message'        => sprintf(__('Found %d rate limit records.', 'affiliatewp-cross-domain-plugin-suite'), count($current_limits)),
            'identifier'     => $identifier,
            'current_limits' => $current_limits,
            'is_blocked'     => $this->is_ip_blocked($identifier)
        ];
    }

    /**
     * Test fraud detection
     */
    private function test_fraud_detection($identifier) {
        global $wpdb;
        
        $fraud_records = $wpdb->get_results($wpdb->prepare(
            "SELECT fraud_type, risk_score, detection_count, first_detected, is_active
             FROM {$this->fraud_detection_table} 
             WHERE identifier = %s
             ORDER BY first_detected DESC",
            $identifier
        ), ARRAY_A);
        
        return [
            'status'            => 'success',
            'message'           => sprintf(__('Found %d fraud detection records.', 'affiliatewp-cross-domain-plugin-suite'), count($fraud_records)),
            'identifier'        => $identifier,
            'fraud_records'     => $fraud_records,
            'total_risk_score'  => array_sum(array_map('intval', wp_list_pluck($fraud_records, 'risk_score')))
        ];
    }

    /**
     * Get security metrics
     */
    public function get_security_metrics($period = '24h') {
        global $wpdb;
        
        $time_clause = $this->get_time_clause($period);
        
        // Total events
        $total_events = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->security_logs_table} WHERE created_at >= %s",
            $time_clause
        ));
        
        // Blocked requests
        $blocked_requests = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->rate_limit_table} 
             WHERE window_start >= %s AND is_blocked = 1",
            $time_clause
        ));
        
        // Fraud detections
        $fraud_detections = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->fraud_detection_table} 
             WHERE first_detected >= %s",
            $time_clause
        ));
        
        // Average risk score
        $avg_risk_score = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(risk_score) FROM {$this->fraud_detection_table} 
             WHERE first_detected >= %s AND is_active = 1",
            $time_clause
        ));
        
        // Top attacked domains
        $top_domains = $wpdb->get_results($wpdb->prepare(
            "SELECT domain, COUNT(*) as attack_count 
             FROM {$this->security_logs_table} 
             WHERE created_at >= %s AND severity IN ('high', 'critical')
             GROUP BY domain 
             ORDER BY attack_count DESC 
             LIMIT 5",
            $time_clause
        ), ARRAY_A);
        
        return [
            'total_events'        => (int) $total_events,
            'blocked_requests'    => (int) $blocked_requests,
            'fraud_detections'    => (int) $fraud_detections,
            'avg_risk_score'      => round($avg_risk_score, 2),
            'top_attacked_domains'=> $top_domains,
            'period'              => $period
        ];
    }

    /**
     * Export security logs
     */
    public function export_security_logs($filters = []) {
        global $wpdb;
        
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Apply filters
        if (!empty($filters['start_date'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['end_date'];
        }
        
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'severity = %s';
            $where_values[] = $filters['severity'];
        }
        
        if (!empty($filters['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = $filters['event_type'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $limit = min(absint($filters['limit'] ?? 1000), 10000); // Max 10k records
        
        $sql = "SELECT * FROM {$this->security_logs_table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT {$limit}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $logs = $wpdb->get_results($sql, ARRAY_A);
        
        // Generate CSV
        $csv_data = [];
        $csv_data[] = [
            'ID', 'Event Type', 'Severity', 'Source IP', 'User ID', 
            'Domain', 'User Agent', 'Event Data', 'Context Data', 'Created At'
        ];
        
        foreach ($logs as $log) {
            $csv_data[] = [
                $log['id'],
                $log['event_type'],
                $log['severity'],
                $log['source_ip'],
                $log['user_id'],
                $log['domain'],
                $log['user_agent'],
                $log['event_data'],
                $log['context_data'],
                $log['created_at']
            ];
        }
        
        return [
            'csv_data' => $csv_data,
            'total_records' => count($logs),
            'filename' => 'security_logs_' . date('Y-m-d_H-i-s') . '.csv'
        ];
    }

    /**
     * Get blocked IPs list
     */
    public function get_blocked_ips($limit = 100) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT r.identifier, r.action_type, r.block_until,
                    COUNT(DISTINCT r.action_type) as blocked_actions,
                    MAX(r.request_count) as max_requests,
                    GROUP_CONCAT(DISTINCT r.action_type) as actions
             FROM {$this->rate_limit_table} r
             WHERE r.is_blocked = 1 AND r.block_until > NOW()
             GROUP BY r.identifier
             ORDER BY r.block_until DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Unblock IP
     */
    public function unblock_ip($identifier) {
        global $wpdb;
        
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $result = $wpdb->update(
            $this->rate_limit_table,
            [
                'is_blocked'  => 0,
                'block_until' => null
            ],
            ['identifier' => $identifier],
            ['%d', '%s'],
            ['%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to unblock IP.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Log the unblock action
        $this->log_security_event('ip_unblocked', 'medium', [
            'identifier'  => $identifier,
            'unblocked_by'=> get_current_user_id()
        ]);
        
        return true;
    }

    /**
     * Get fraud summary
     */
    public function get_fraud_summary($period = '7d') {
        global $wpdb;
        
        $time_clause = $this->get_time_clause($period);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT fraud_type, 
                    COUNT(*) as total_detections,
                    AVG(risk_score) as avg_risk_score,
                    MAX(risk_score) as max_risk_score,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_cases
             FROM {$this->fraud_detection_table}
             WHERE first_detected >= %s
             GROUP BY fraud_type
             ORDER BY total_detections DESC",
            $time_clause
        ), ARRAY_A);
    }

    /**
     * Whitelist IP
     */
    public function whitelist_ip($ip, $reason = '') {
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $whitelisted_ips = get_option('affcd_whitelisted_ips', []);
        $whitelisted_ips[$ip] = [
            'reason'    => sanitize_text_field($reason),
            'added_by'  => get_current_user_id(),
            'added_at'  => current_time('mysql')
        ];
        
        update_option('affcd_whitelisted_ips', $whitelisted_ips);
        
        // Unblock if currently blocked
        $this->unblock_ip($ip);
        
        $this->log_security_event('ip_whitelisted', 'low', [
            'ip'       => $ip,
            'reason'   => $reason,
            'added_by' => get_current_user_id()
        ]);
        
        return true;
    }

    /**
     * Check if IP is whitelisted
     */
    public function is_ip_whitelisted($ip) {
        $whitelisted_ips = get_option('affcd_whitelisted_ips', []);
        return isset($whitelisted_ips[$ip]);
    }
}
