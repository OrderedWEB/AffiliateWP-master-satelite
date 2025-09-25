<?php
/**
 * Security Validator Class
 *
 * Handles API request validation, domain authorization, JWT token validation,
 * and security checks for the affiliate cross-domain system.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 * @author Richard King
 */

if (!defined('ABSPATH')) {
    exit;
}
if ( ! class_exists('AFFCD_Security_Manager') ) {
    // Normal path (this file is in /includes/)
    $candidate = dirname(__DIR__) . '/includes/class-security-manager.php';
    if ( is_readable($candidate) ) {
        require_once $candidate;
    }

    // Legacy/alternate class names → alias to the expected one
    if ( ! class_exists('AFFCD_Security_Manager') ) {
        if ( class_exists('AFFCD_Security') ) {
            class_alias('AFFCD_Security', 'AFFCD_Security_Manager');
        } elseif ( class_exists('AffiliateWP_Cross_Domain_Security_Manager') ) {
            class_alias('AffiliateWP_Cross_Domain_Security_Manager', 'AFFCD_Security_Manager');
        }
    }
}

// Last-resort soft-fail to avoid a hard fatal during activation
if ( ! class_exists('AFFCD_Security_Manager') ) {
    error_log('[AFFCD] Security Manager class missing; Security Validator will run in disabled mode.');
}


class AFFCD_Security_Validator {

    /** @var string JWT secret key */
    private $jwt_secret;

    /** @var AFFCD_Security_Manager */
    private $security_manager;

    /** @var string Cache prefix */
    private $cache_prefix = 'affcd_sec_val_';

    /** @var int Cache expiration time (15 minutes) */
    private $cache_expiration = 900;

    public function __construct() {
        $this->jwt_secret       = $this->get_jwt_secret();
        $this->security_manager = new AFFCD_Security_Manager();

        add_action('init',               [$this, 'init']);
        add_action('rest_api_init',      [$this, 'add_cors_headers']);
        add_filter('rest_pre_serve_request', [$this, 'add_security_headers'], 10, 4);
    }

    public function init() {
        if ($this->is_cors_request()) {
            $this->handle_cors_request();
        }
    }

    /**
     * Validate API request
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function validate_api_request($request) {
        $domain    = $this->get_request_domain($request);
        $api_key   = $this->get_api_key($request);
        $signature = $this->get_request_signature($request);
        $timestamp = $this->get_request_timestamp($request);

        // Domain
        $domain_validation = $this->validate_domain_authorisation($domain);
        if (is_wp_error($domain_validation)) {
            return $domain_validation;
        }

        // API Key
        $api_key_validation = $this->validate_api_key($api_key, $domain);
        if (is_wp_error($api_key_validation)) {
            return $api_key_validation;
        }

        // Signature
        $signature_validation = $this->validate_request_signature($request, $signature);
        if (is_wp_error($signature_validation)) {
            return $signature_validation;
        }

        // Timestamp
        $timestamp_validation = $this->validate_request_timestamp($timestamp);
        if (is_wp_error($timestamp_validation)) {
            return $timestamp_validation;
        }

        // Rate limiting
        if (!$this->security_manager->check_rate_limit('api_request', $domain)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded for this domain.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 429]
            );
        }

        // Success log
        $this->security_manager->log_activity('api_request_validated', [
            'domain'   => $domain,
            'endpoint' => $request->get_route(),
        ]);

        return true;
    }

    /**
     * Validate domain authorisation
     *
     * @param string $domain
     * @return true|WP_Error
     */
    public function validate_domain_authorisation($domain) {
        if (empty($domain)) {
            return new WP_Error('missing_domain', __('Domain parameter is required.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        $clean_domain = function_exists('affcd_sanitize_domain')
            ? affcd_sanitize_domain($domain)
            : $this->sanitize_domain_fallback($domain);

        if (empty($clean_domain)) {
            return new WP_Error('invalid_domain', __('Invalid domain format.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        $cache_key     = $this->cache_prefix . 'domain_auth_' . md5($clean_domain);
        $cached_result = wp_cache_get($cache_key, 'affcd');
        if ($cached_result !== false) {
            return $cached_result === 'authorized';
        }

        $is_authorized = $this->is_domain_authorized($clean_domain);
        if (!$is_authorized) {
            wp_cache_set($cache_key, 'unauthorized', 'affcd', 300);
            $this->security_manager->log_activity('unauthorized_domain_access', [
                'domain'     => $clean_domain,
                'ip_address' => $this->get_client_ip(),
            ]);

            return new WP_Error('unauthorized_domain', __('Domain not authorized for API access.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 403]);
        }

        wp_cache_set($cache_key, 'authorized', 'affcd', $this->cache_expiration);
        return true;
    }

    /**
     * Validate API key
     *
     * @param string $api_key
     * @param string $domain
     * @return true|WP_Error
     */
    public function validate_api_key($api_key, $domain) {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is required.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 401]);
        }

        $cache_key     = $this->cache_prefix . 'api_key_' . md5($api_key . $domain);
        $cached_result = wp_cache_get($cache_key, 'affcd');
        if ($cached_result !== false) {
            return $cached_result === 'valid';
        }

        global $wpdb;
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        // Use domain_url consistently
        $domain_record = $wpdb->get_row($wpdb->prepare(
            "SELECT api_key, status FROM {$domains_table}
             WHERE domain_url = %s AND status = 'active'",
            $domain
        ));

        if (!$domain_record || !hash_equals((string)$domain_record->api_key, (string)$api_key)) {
            wp_cache_set($cache_key, 'invalid', 'affcd', 300);
            $this->security_manager->log_activity('invalid_api_key_attempt', [
                'domain'       => $domain,
                'api_key_pref' => substr($api_key, 0, 8) . '...',
                'ip_address'   => $this->get_client_ip(),
            ]);

            return new WP_Error('invalid_api_key', __('Invalid API key for this domain.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 401]);
        }

        wp_cache_set($cache_key, 'valid', 'affcd', $this->cache_expiration);
        return true;
    }

    /**
     * Validate request signature (HMAC over method|route|domain|timestamp|body)
     *
     * @param WP_REST_Request $request
     * @param string $signature
     * @return true|WP_Error
     */
    public function validate_request_signature($request, $signature) {
        if (empty($signature)) {
            return new WP_Error('missing_signature', __('Request signature is required.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 401]);
        }

        $expected_signature = $this->generate_request_signature($request);
        if (!hash_equals($expected_signature, $signature)) {
            $this->security_manager->log_activity('invalid_signature_attempt', [
                'domain'   => $this->get_request_domain($request),
                'endpoint' => $request->get_route(),
                'ip'       => $this->get_client_ip(),
            ]);

            return new WP_Error('invalid_signature', __('Invalid request signature.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 401]);
        }

        return true;
    }

    /**
     * Validate request timestamp (default +/- 5 minutes)
     *
     * @param int|string $timestamp
     * @return true|WP_Error
     */
    public function validate_request_timestamp($timestamp) {
        if (empty($timestamp) || !is_numeric($timestamp)) {
            return new WP_Error('missing_timestamp', __('Request timestamp is required.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        $current_time        = time();
        $time_difference     = abs($current_time - (int)$timestamp);
        $max_time_difference = (int) apply_filters('affcd_max_timestamp_difference', 300);

        if ($time_difference > $max_time_difference) {
            $this->security_manager->log_activity('timestamp_validation_failed', [
                'provided_timestamp' => (int)$timestamp,
                'current_timestamp'  => $current_time,
                'difference'         => $time_difference,
            ]);

            return new WP_Error('invalid_timestamp', __('Request timestamp is outside acceptable range.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Add CORS headers: replace WP's default and send ours
     */
    public function add_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [$this, 'send_cors_headers'], 15, 4);
    }

    /**
     * Actually send CORS headers
     */
    public function send_cors_headers($served, $result, $request, $server) {
        $origin = get_http_origin();

        if ($origin && $this->is_origin_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link');

            if ('OPTIONS' === $request->get_method()) {
                header('Access-Control-Allow-Headers: Authorization, authorisation, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type, X-API-Key, X-Signature, X-Client-Domain, X-Client-Version, X-Timestamp');
                header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');
                header('Access-Control-Max-Age: 86400');
                exit;
            }
        }

        return $served;
    }

    /**
     * Add general security headers for our namespace
     */
    public function add_security_headers($served, $result, $request, $server) {
        $route = $request instanceof WP_REST_Request ? (string) $request->get_route() : '';
        if (strpos($route, '/affcd/') === 0) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        return $served;
    }

    /**
     * Check if domain is authorized
     *
     * @param string $domain
     * @return bool
     */
    private function is_domain_authorized($domain) {
        global $wpdb;

        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$domains_table}
             WHERE domain_url = %s AND status = 'active'",
            $domain
        ));

        return $count > 0;
    }

    /**
     * Check if origin is allowed for CORS
     */
    private function is_origin_allowed($origin) {
        $allowed = $this->get_allowed_origins();
        return in_array($origin, $allowed, true);
    }

    /**
     * Get allowed origins for CORS from active authorized domains
     *
     * @return string[]
     */
    private function get_allowed_origins() {
        global $wpdb;

        $cache_key      = $this->cache_prefix . 'allowed_origins';
        $cached_origins = wp_cache_get($cache_key, 'affcd');

        if ($cached_origins !== false) {
            return $cached_origins;
        }

        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        $domains = (array) $wpdb->get_col(
            "SELECT domain_url FROM {$domains_table} WHERE status = 'active'"
        );

        $origins = [];
        foreach ($domains as $domain) {
            $origins[] = 'https://' . $domain;
            $origins[] = 'http://'  . $domain; // allow HTTP for dev if needed
        }

        wp_cache_set($cache_key, $origins, 'affcd', $this->cache_expiration);

        return $origins;
    }

    /**
     * Generate request signature
     */
    private function generate_request_signature($request) {
        $domain    = $this->get_request_domain($request);
        $timestamp = $this->get_request_timestamp($request);
        $method    = $request->get_method();
        $route     = $request->get_route();
        $body      = wp_json_encode($request->get_json_params());

        $string_to_sign = $method . '|' . $route . '|' . $domain . '|' . $timestamp . '|' . $body;

        return hash_hmac('sha256', $string_to_sign, $this->jwt_secret);
    }

    private function get_request_domain($request) {
        return $request->get_header('X-Client-Domain') ?: $request->get_param('domain');
    }

    private function get_api_key($request) {
        return $request->get_header('X-API-Key') ?: $request->get_param('api_key');
    }

    private function get_request_signature($request) {
        return $request->get_header('X-Signature') ?: $request->get_param('signature');
    }

    private function get_request_timestamp($request) {
        return $request->get_header('X-Timestamp') ?: $request->get_param('timestamp');
    }

    private function is_cors_request() {
        return !empty($_SERVER['HTTP_ORIGIN']);
    }

    private function handle_cors_request() {
        $origin = get_http_origin();

        if ($origin && $this->is_origin_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');

            if ('OPTIONS' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
                header('Access-Control-Allow-Headers: Authorization, authorisation, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type, X-API-Key, X-Signature, X-Client-Domain, X-Client-Version, X-Timestamp');
                header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');
                header('Access-Control-Max-Age: 86400');
                status_header(200);
                exit;
            }
        }
    }

    /**  JWT helpers  */

    private function get_jwt_secret() {
        $secret = get_option('affcd_jwt_secret');

        if (empty($secret)) {
            $secret = wp_generate_password(64, false);
            update_option('affcd_jwt_secret', $secret);
        }

        return $secret;
    }

    /**  Utility helpers  */

    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data) {
        $data = strtr($data, '-_', '+/');
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode($data);
    }

    private function sanitize_domain_fallback($domain) {
        // strip scheme / path / port, lower-case
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#[:/].*$#', '', $domain);
        $domain = strtolower($domain);
        return filter_var('http://' . $domain, FILTER_VALIDATE_URL) ? $domain : '';
    }

    /**
     * Sanitize request array
     *
     * @param array $data
     * @return array
     */
    public function sanitize_request_data($data) {
        if (!is_array($data)) {
            return [];
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            $key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_request_data($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = (false !== strpos((string)$value, '.')) ? (float)$value : (int)$value;
            } elseif (is_bool($value)) {
                $sanitized[$key] = (bool) $value;
            } else {
                $sanitized[$key] = sanitize_text_field((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Log security event (wrapper)
     */
    public function log_security_event($event_type, $message, $data = []) {
        $this->security_manager->log_activity($event_type, array_merge($data, [
            'message'    => $message,
            'timestamp'  => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]));
    }

    /**
     * Security status snapshot
     *
     * @return array
     */
    public function get_security_status() {
        return [
            'jwt_enabled'                    => !empty($this->jwt_secret),
            'cors_enabled'                   => true,
            'rate_limiting_enabled'          => true,
            'signature_validation_enabled'   => true,
            'timestamp_validation_enabled'   => true,
            'authorized_domains_count'       => $this->get_authorized_domains_count(),
            'security_events_today'          => $this->get_security_events_count('today'),
        ];
    }

    private function get_authorized_domains_count() {
        global $wpdb;

        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$domains_table} WHERE status = 'active'"
        );
    }

    private function get_security_events_count($period = 'today') {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'affcd_security_logs';

        switch ($period) {
            case 'today':
                $date_clause = 'DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $date_clause = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_clause = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                $date_clause = '1=1';
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE {$date_clause}"
        );
    }
}
