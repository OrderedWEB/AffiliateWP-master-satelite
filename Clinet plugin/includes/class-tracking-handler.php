<?php
/**
 * Tracking Handler Class
 *
 * Handles all affiliate tracking functionality including
 * visit tracking, referral detection, and event logging.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFILIATE_CLIENT_Tracking_Handler {

    /**
     * Plugin configuration
     *
     * @var array
     */
    private $config;

    /**
     * API client instance
     *
     * @var AFFILIATE_CLIENT_API_Client
     */
    private $api_client;

    /**
     * Current affiliate ID
     *
     * @var int|null
     */
    private $current_affiliate_id;

    /**
     * Current visit ID
     *
     * @var string|null
     */
    private $current_visit_id;

    /**
     * Constructor
     *
     * @param array $config Plugin configuration
     * @param AFFILIATE_CLIENT_API_Client $api_client API client instance
     */
    public function __construct($config, $api_client) {
        $this->config = $config;
        $this->api_client = $api_client;
    }

    /**
     * Initialize tracking handler
     */
    public function init() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        // Handle affiliate referrals
        add_action('init', [$this, 'handle_referral'], 1);
        add_action('wp', [$this, 'track_visit']);
        
        // JavaScript tracking events
        add_action('wp_ajax_affiliate_client_track_event', [$this, 'ajax_track_event']);
        add_action('wp_ajax_nopriv_affiliate_client_track_event', [$this, 'ajax_track_event']);
        
        // Privacy hooks
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_data_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_data_eraser']);
    }

    /**
     * Handle affiliate referral
     */
    public function handle_referral() {
        // Check for affiliate parameter in URL
        $affiliate_param = $this->get_affiliate_parameter();
        
        if ($affiliate_param) {
            $this->set_affiliate_referral($affiliate_param);
        }
        
        // Load current affiliate from cookie
        $this->current_affiliate_id = $this->get_affiliate_from_cookie();
        $this->current_visit_id = $this->get_or_create_visit_id();
    }

    /**
     * Track page visit
     */
    public function track_visit() {
        if (!$this->should_track_visit()) {
            return;
        }

        $visit_data = [
            'url' => $this->get_current_url(),
            'title' => get_the_title(),
            'referrer' => $this->get_referrer(),
            'user_agent' => $this->get_user_agent(),
            'ip_address' => $this->get_ip_address(),
            'affiliate_id' => $this->current_affiliate_id,
            'visit_id' => $this->current_visit_id,
            'timestamp' => current_time('c'),
        ];

        $this->track_event('page_view', $visit_data);
    }

    /**
     * Track custom event
     *
     * @param string $event_type Event type
     * @param array $data Event data
     * @return bool Success status
     */
    public function track_event($event_type, $data = []) {
        if (!$this->config['tracking_enabled']) {
            return false;
        }

        // Validate event type
        if (!$this->is_valid_event_type($event_type)) {
            return false;
        }

        // Privacy check
        if (!$this->should_track_user()) {
            return false;
        }

        // Prepare event data
        $event_data = $this->prepare_event_data($event_type, $data);
        
        // Log locally first
        $log_id = $this->log_event($event_type, $event_data);
        
        if (!$log_id) {
            return false;
        }

        // Send to remote site if possible
        if ($this->api_client->is_available()) {
            $result = $this->api_client->send_tracking_data([
                'event_type' => $event_type,
                'data' => $event_data,
                'log_id' => $log_id,
            ]);

            if ($result['success']) {
                $this->mark_event_synced($log_id);
            }
        }

        // Fire action hook for other plugins
        do_action('affiliate_client_event_tracked', $event_type, $event_data, $log_id);

        return true;
    }

    /**
     * AJAX handler for tracking events
     */
    public function ajax_track_event() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_die('Invalid nonce');
        }

        $event_type = sanitize_text_field($_POST['event_type']);
        $event_data = isset($_POST['data']) ? $_POST['data'] : [];

        // Sanitize event data
        $event_data = $this->sanitize_event_data($event_data);

        $result = $this->track_event($event_type, $event_data);

        wp_send_json([
            'success' => $result,
            'message' => $result ? 'Event tracked successfully' : 'Failed to track event',
        ]);
    }

    /**
     * Get affiliate parameter from URL
     *
     * @return string|null Affiliate parameter value
     */
    private function get_affiliate_parameter() {
        $param_names = ['ref', 'affiliate', 'aff', 'affiliate_id'];
        
        foreach ($param_names as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                return sanitize_text_field($_GET[$param]);
            }
        }
        
        return null;
    }

    /**
     * Set affiliate referral in cookie
     *
     * @param string $affiliate_param Affiliate parameter
     */
    private function set_affiliate_referral($affiliate_param) {
        // Validate affiliate exists on remote site
        $affiliate_data = $this->api_client->validate_affiliate($affiliate_param);
        
        if (!$affiliate_data['valid']) {
            return;
        }

        $affiliate_id = $affiliate_data['affiliate_id'];
        
        // Set cookie
        setcookie(
            $this->config['cookie_name'],
            $affiliate_id,
            time() + $this->config['cookie_expiry'],
            $this->config['cookie_path'],
            $this->config['cookie_domain'],
            $this->config['cookie_secure'],
            $this->config['cookie_httponly']
        );

        $this->current_affiliate_id = $affiliate_id;

        // Track referral event
        $this->track_event('referral', [
            'affiliate_id' => $affiliate_id,
            'referral_url' => $this->get_current_url(),
            'original_referrer' => $this->get_referrer(),
        ]);
    }

    /**
     * Get affiliate ID from cookie
     *
     * @return int|null Affiliate ID
     */
    private function get_affiliate_from_cookie() {
        if (isset($_COOKIE[$this->config['cookie_name']])) {
            return intval($_COOKIE[$this->config['cookie_name']]);
        }
        
        return null;
    }

    /**
     * Get or create visit ID
     *
     * @return string Visit ID
     */
    private function get_or_create_visit_id() {
        $session_cookie = 'affiliate_client_visit_id';
        
        if (isset($_COOKIE[$session_cookie])) {
            return sanitize_text_field($_COOKIE[$session_cookie]);
        }
        
        $visit_id = $this->generate_visit_id();
        
        // Set session cookie (expires when browser closes)
        setcookie(
            $session_cookie,
            $visit_id,
            0, // Session cookie
            $this->config['cookie_path'],
            $this->config['cookie_domain'],
            $this->config['cookie_secure'],
            true // HTTP only for security
        );
        
        return $visit_id;
    }

    /**
     * Generate unique visit ID
     *
     * @return string Visit ID
     */
    private function generate_visit_id() {
        return uniqid('visit_', true);
    }

    /**
     * Check if visit should be tracked
     *
     * @return bool True if should track
     */
    private function should_track_visit() {
        // Don't track admin pages
        if (is_admin()) {
            return false;
        }
        
        // Don't track login/register pages
        if (is_page(['login', 'register', 'wp-login'])) {
            return false;
        }
        
        // Don't track bots (basic check)
        if ($this->is_bot()) {
            return false;
        }
        
        // Don't track if user opted out
        if (!$this->should_track_user()) {
            return false;
        }
        
        return apply_filters('affiliate_client_should_track_visit', true);
    }

    /**
     * Check if user should be tracked (privacy compliance)
     *
     * @return bool True if should track
     */
    private function should_track_user() {
        // Respect Do Not Track header
        if ($this->config['privacy']['respect_dnt'] && isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == '1') {
            return false;
        }
        
        // Check for opt-out cookie
        if (isset($_COOKIE['affiliate_client_opt_out'])) {
            return false;
        }
        
        // GDPR compliance - check for consent if required
        if ($this->config['privacy']['cookie_consent_required']) {
            return $this->has_cookie_consent();
        }
        
        return true;
    }

    /**
     * Check if request is from a bot
     *
     * @return bool True if bot
     */
    private function is_bot() {
        $user_agent = $this->get_user_agent();
        
        $bot_patterns = [
            'bot', 'crawl', 'spider', 'scrape', 'fetch',
            'google', 'bing', 'yahoo', 'facebook', 'twitter',
        ];
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for cookie consent
     *
     * @return bool True if consent given
     */
    private function has_cookie_consent() {
        // Check common consent cookies
        $consent_cookies = [
            'cookie_consent',
            'gdpr_consent',
            'cookieConsent',
            'cookie-agreed',
        ];
        
        foreach ($consent_cookies as $cookie) {
            if (isset($_COOKIE[$cookie]) && $_COOKIE[$cookie] == '1') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate event type
     *
     * @param string $event_type Event type
     * @return bool True if valid
     */
    private function is_valid_event_type($event_type) {
        $valid_events = array_keys($this->config['default_events']);
        return in_array($event_type, $valid_events);
    }

    /**
     * Prepare event data
     *
     * @param string $event_type Event type
     * @param array $data Raw event data
     * @return array Prepared event data
     */
    private function prepare_event_data($event_type, $data) {
        $event_config = $this->config['default_events'][$event_type] ?? [];
        $allowed_fields = $event_config['data'] ?? [];
        
        $prepared_data = [
            'event_type' => $event_type,
            'timestamp' => current_time('c'),
            'url' => $this->get_current_url(),
            'user_ip' => $this->get_ip_address(),
            'user_agent' => $this->get_user_agent(),
            'affiliate_id' => $this->current_affiliate_id,
            'visit_id' => $this->current_visit_id,
        ];
        
        // Add allowed event-specific data
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $prepared_data[$field] = $data[$field];
            }
        }
        
        // Add any additional data passed
        foreach ($data as $key => $value) {
            if (!isset($prepared_data[$key])) {
                $prepared_data[$key] = $value;
            }
        }
        
        // Anonymize IP if required
        if ($this->config['privacy']['anonymize_ips']) {
            $prepared_data['user_ip'] = $this->anonymize_ip($prepared_data['user_ip']);
        }
        
        return apply_filters('affiliate_client_prepare_event_data', $prepared_data, $event_type, $data);
    }

    /**
     * Sanitize event data from AJAX request
     *
     * @param array $data Raw event data
     * @return array Sanitized data
     */
    private function sanitize_event_data($data) {
        $Sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $Sanitized[$key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $Sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
            } elseif (is_array($value)) {
                $Sanitized[$key] = $this->sanitize_event_data($value);
            } else {
                $Sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $Sanitized;
    }

    /**
     * Log event to local database
     *
     * @param string $event_type Event type
     * @param array $data Event data
     * @return int|false Log ID or false on failure
     */
    private function log_event($event_type, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'event_type' => $event_type,
                'affiliate_id' => $this->current_affiliate_id,
                'visit_id' => $this->current_visit_id,
                'data' => json_encode($data),
                'synced' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%d', '%s']
        );
        
        if ($result === false) {
            $this->log_error('Failed to log event: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Mark event as synced
     *
     * @param int $log_id Log ID
     * @return bool Success status
     */
    private function mark_event_synced($log_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        return $wpdb->update(
            $table_name,
            ['synced' => 1],
            ['id' => $log_id],
            ['%d'],
            ['%d']
        ) !== false;
    }

    /**
     * Get current URL
     *
     * @return string Current URL
     */
    private function get_current_url() {
        if (isset($_SERVER['REQUEST_URI'])) {
            return home_url($_SERVER['REQUEST_URI']);
        }
        
        return home_url();
    }

    /**
     * Get referrer URL
     *
     * @return string Referrer URL
     */
    private function get_referrer() {
        return isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
    }

    /**
     * Get user agent
     *
     * @return string User agent
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }

    /**
     * Get user IP address
     *
     * @return string IP address
     */
    private function get_ip_address() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Anonymize IP address for privacy compliance
     *
     * @param string $ip IP address
     * @return string Anonymized IP
     */
    private function anonymize_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Remove last octet
            return preg_replace('/\.\d+$/', '.0', $ip);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Remove last 64 bits
            return preg_replace('/:[0-9a-f]*:[0-9a-f]*:[0-9a-f]*:[0-9a-f]*$/i', '::', $ip);
        }
        
        return $ip;
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        if ($this->config['debug_mode']) {
            error_log('[Affiliate Client Full] ' . $message);
        }
    }

    /**
     * Get current affiliate ID
     *
     * @return int|null Current affiliate ID
     */
    public function get_current_affiliate_id() {
        return $this->current_affiliate_id;
    }

    /**
     * Get current visit ID
     *
     * @return string|null Current visit ID
     */
    public function get_current_visit_id() {
        return $this->current_visit_id;
    }

    /**
     * Set affiliate ID manually
     *
     * @param int $affiliate_id Affiliate ID
     */
    public function set_affiliate_id($affiliate_id) {
        $this->current_affiliate_id = intval($affiliate_id);
        
        // Update cookie
        setcookie(
            $this->config['cookie_name'],
            $this->current_affiliate_id,
            time() + $this->config['cookie_expiry'],
            $this->config['cookie_path'],
            $this->config['cookie_domain'],
            $this->config['cookie_secure'],
            $this->config['cookie_httponly']
        );
    }

    /**
     * Clear affiliate tracking
     */
    public function clear_affiliate() {
        $this->current_affiliate_id = null;
        
        // Clear cookie
        setcookie(
            $this->config['cookie_name'],
            '',
            time() - 3600,
            $this->config['cookie_path'],
            $this->config['cookie_domain'],
            $this->config['cookie_secure'],
            $this->config['cookie_httponly']
        );
    }

    /**
     * Get tracking statistics
     *
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public function get_tracking_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT visit_id) as unique_visits,
                COUNT(DISTINCT affiliate_id) as unique_affiliates,
                SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as page_views,
                SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions,
                SUM(CASE WHEN synced = 1 THEN 1 ELSE 0 END) as synced_events
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
        
        return [
            'total_events' => intval($stats->total_events),
            'unique_visits' => intval($stats->unique_visits),
            'unique_affiliates' => intval($stats->unique_affiliates),
            'page_views' => intval($stats->page_views),
            'conversions' => intval($stats->conversions),
            'synced_events' => intval($stats->synced_events),
            'sync_rate' => $stats->total_events > 0 ? round(($stats->synced_events / $stats->total_events) * 100, 2) : 0,
        ];
    }

    /**
     * Register privacy data exporter
     *
     * @param array $exporters Existing exporters
     * @return array Updated exporters
     */
    public function register_data_exporter($exporters) {
        $exporters['affiliate-client-full'] = [
            'exporter_friendly_name' => __('Affiliate Client Full', 'affiliate-client-full'),
            'callback' => [$this, 'export_user_data'],
        ];
        
        return $exporters;
    }

    /**
     * Register privacy data eraser
     *
     * @param array $erasers Existing erasers
     * @return array Updated erasers
     */
    public function register_data_eraser($erasers) {
        $erasers['affiliate-client-full'] = [
            'eraser_friendly_name' => __('Affiliate Client Full', 'affiliate-client-full'),
            'callback' => [$this, 'erase_user_data'],
        ];
        
        return $erasers;
    }

    /**
     * Export user data for privacy requests
     *
     * @param string $email_address User email
     * @param int $page Page number
     * @return array Export data
     */
    public function export_user_data($email_address, $page = 1) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        $user_ip = $this->get_ip_address();
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE JSON_EXTRACT(data, '$.user_ip') = %s
            ORDER BY created_at DESC
            LIMIT 500 OFFSET %d
        ", $user_ip, ($page - 1) * 500));
        
        $export_items = [];
        
        foreach ($logs as $log) {
            $data = json_decode($log->data, true);
            
            $export_items[] = [
                'group_id' => 'affiliate_tracking',
                'group_label' => __('Affiliate Tracking Data', 'affiliate-client-full'),
                'item_id' => "log-{$log->id}",
                'data' => [
                    [
                        'name' => __('Event Type', 'affiliate-client-full'),
                        'value' => $log->event_type,
                    ],
                    [
                        'name' => __('Date', 'affiliate-client-full'),
                        'value' => $log->created_at,
                    ],
                    [
                        'name' => __('URL', 'affiliate-client-full'),
                        'value' => $data['url'] ?? '',
                    ],
                    [
                        'name' => __('Affiliate ID', 'affiliate-client-full'),
                        'value' => $log->affiliate_id ?? 'None',
                    ],
                ],
            ];
        }
        
        return [
            'data' => $export_items,
            'done' => count($logs) < 500,
        ];
    }

    /**
     * Erase user data for privacy requests
     *
     * @param string $email_address User email
     * @param int $page Page number
     * @return array Erasure results
     */
    public function erase_user_data($email_address, $page = 1) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        $user_ip = $this->get_ip_address();
        
        $deleted = $wpdb->delete(
            $table_name,
            ['JSON_EXTRACT(data, "$.user_ip")' => $user_ip],
            ['%s']
        );
        
        return [
            'items_removed' => $deleted !== false ? $deleted : 0,
            'items_retained' => false,
            'messages' => [],
            'done' => true,
        ];
    }

    /**
     * Get unsynced events count
     *
     * @return int Number of unsynced events
     */
    public function get_unsynced_count() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        return intval($wpdb->get_var("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE synced = 0
        "));
    }

    /**
     * Clean up old tracking data
     *
     * @param int $days Number of days to retain
     * @return int Number of records deleted
     */
    public function cleanup_old_data($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$table_name}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND synced = 1
        ", $days));
        
        return $deleted !== false ? $deleted : 0;
    }
}