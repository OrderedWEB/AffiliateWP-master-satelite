<?php
/**
 * Security Validator Class
 *
 * Handles API request validation, domain authorisation, JWT token validation,
 * and security checks for the affiliate cross-domain system.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 * @author Richard King, Starne Consulting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Security_Validator {

    /**
     * JWT secret key
     *
     * @var string
     */
    private $jwt_secret;

    /**
     * Security manager instance
     *
     * @var AFFCD_Security_Manager
     */
    private $security_manager;

    /**
     * Cache prefix
     *
     * @var string
     */
    private $cache_prefix = 'affcd_sec_val_';

    /**
     * Cache expiration time (15 minutes)
     *
     * @var int
     */
    private $cache_expiration = 900;

    /**
     * Constructor
     */
    public function __construct() {
        $this->jwt_secret = $this->get_jwt_secret();
        $this->security_manager = new AFFCD_Security_Manager();
        
        // Initialise hooks
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'add_cors_headers']);
        add_filter('rest_pre_serve_request', [$this, 'add_security_headers'], 10, 4);
    }

    /**
     * Initialse security validator
     */
    public function init() {
        // Setup CORS handling
        if ($this->is_cors_request()) {
            $this->handle_cors_request();
        }
    }

    /**
     * Validate API request
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_api_request($request) {
        // Get request data
        $domain = $this->get_request_domain($request);
        $api_key = $this->get_api_key($request);
        $signature = $this->get_request_signature($request);
        $timestamp = $this->get_request_timestamp($request);

        // Validate domain authorisation
        $domain_validation = $this->validate_domain_authorisation($domain);
        if (is_wp_error($domain_validation)) {
            return $domain_validation;
        }

        // Validate API key
        $api_key_validation = $this->validate_api_key($api_key, $domain);
        if (is_wp_error($api_key_validation)) {
            return $api_key_validation;
        }

        // Validate request signature
        $signature_validation = $this->validate_request_signature($request, $signature);
        if (is_wp_error($signature_validation)) {
            return $signature_validation;
        }

        // Validate timestamp (prevent replay attacks)
        $timestamp_validation = $this->validate_request_timestamp($timestamp);
        if (is_wp_error($timestamp_validation)) {
            return $timestamp_validation;
        }

        // Check rate limiting
        if (!$this->security_manager->check_rate_limit('api_request', $domain)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded for this domain.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 429]
            );
        }

        // Log successful validation
        $this->security_manager->log_activity('api_request_validated', [
            'domain' => $domain,
            'endpoint' => $request->get_route()
        ]);

        return true;
    }

    /**
     * Validate domain authorisation
     *
     * @param string $domain Domain to validate
     * @return bool|WP_Error True if authorised, WP_Error if not
     */
    public function validate_domain_authorisation($domain) {
        if (empty($domain)) {
            return new WP_Error(
                'missing_domain',
                __('Domain parameter is required.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 400]
            );
        }

        // Sanitise domain
        $clean_domain = affcd_Sanitise_domain($domain);
        if (empty($clean_domain)) {
            return new WP_Error(
                'invalid_domain',
                __('Invalid domain format.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 400]
            );
        }

        // Check cache first
        $cache_key = $this->cache_prefix . 'domain_auth_' . md5($clean_domain);
        $cached_result = wp_cache_get($cache_key, 'affcd');
        if ($cached_result !== false) {
            return $cached_result === 'authorised';
        }

        // Check database
        $is_authorised = $this->is_domain_authorised($clean_domain);
        if (!$is_authorised) {
            // Cache negative result for shorter time
            wp_cache_set($cache_key, 'unauthorised', 'affcd', 300);
            
            $this->security_manager->log_activity('unauthorised_domain_access', [
                'domain' => $clean_domain,
                'ip_address' => $this->get_client_ip()
            ]);

            return new WP_Error(
                'unauthorised_domain',
                __('Domain not authorised for API access.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 403]
            );
        }

        // Cache positive result
        wp_cache_set($cache_key, 'authorised', 'affcd', $this->cache_expiration);

        return true;
    }

    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @param string $domain Associated domain
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public function validate_api_key($api_key, $domain) {
        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                __('API key is required.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        // Check cache first
        $cache_key = $this->cache_prefix . 'api_key_' . md5($api_key . $domain);
        $cached_result = wp_cache_get($cache_key, 'affcd');
        if ($cached_result !== false) {
            return $cached_result === 'valid';
        }

        global $wpdb;
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        $domain_record = $wpdb->get_row($wpdb->prepare(
            "SELECT api_key, status FROM {$domains_table} 
             WHERE domain_url = %s AND status = 'active'",
            $domain
        ));

        if (!$domain_record || !hash_equals($domain_record->api_key, $api_key)) {
            // Cache negative result
            wp_cache_set($cache_key, 'invalid', 'affcd', 300);
            
            $this->security_manager->log_activity('invalid_api_key_attempt', [
                'domain' => $domain,
                'api_key_prefix' => substr($api_key, 0, 8) . '...',
                'ip_address' => $this->get_client_ip()
            ]);

            return new WP_Error(
                'invalid_api_key',
                __('Invalid API key for this domain.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        // Cache positive result
        wp_cache_set($cache_key, 'valid', 'affcd', $this->cache_expiration);

        return true;
    }

    /**
     * Validate request signature
     *
     * @param WP_REST_Request $request Request object
     * @param string $signature Provided signature
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public function validate_request_signature($request, $signature) {
        if (empty($signature)) {
            return new WP_Error(
                'missing_signature',
                __('Request signature is required.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        // Generate expected signature
        $expected_signature = $this->generate_request_signature($request);
        
        if (!hash_equals($expected_signature, $signature)) {
            $this->security_manager->log_activity('invalid_signature_attempt', [
                'domain' => $this->get_request_domain($request),
                'endpoint' => $request->get_route(),
                'ip_address' => $this->get_client_ip()
            ]);

            return new WP_Error(
                'invalid_signature',
                __('Invalid request signature.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Validate request timestamp
     *
     * @param int $timestamp Request timestamp
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public function validate_request_timestamp($timestamp) {
        if (empty($timestamp)) {
            return new WP_Error(
                'missing_timestamp',
                __('Request timestamp is required.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 400]
            );
        }

        $current_time = time();
        $time_difference = abs($current_time - $timestamp);
        
        // Allow 5 minute window to account for clock skew
        $max_time_difference = apply_filters('affcd_max_timestamp_difference', 300);
        
        if ($time_difference > $max_time_difference) {
            $this->security_manager->log_activity('timestamp_validation_failed', [
                'provided_timestamp' => $timestamp,
                'current_timestamp' => $current_time,
                'difference' => $time_difference
            ]);

            return new WP_Error(
                'invalid_timestamp',
                __('Request timestamp is outside acceptable range.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Generate JWT token
     *
     * @param array $payload Token payload
     * @param int $expiration Expiration time (default 1 hour)
     * @return string JWT token
     */
    public function generate_jwt_token($payload, $expiration = 3600) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $payload['exp'] = time() + $expiration;
        $payload['iat'] = time();

        $header_encoded = $this->base64url_encode(wp_json_encode($header));
        $payload_encoded = $this->base64url_encode(wp_json_encode($payload));
        
        $signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $this->jwt_secret, true);
        $signature_encoded = $this->base64url_encode($signature);

        return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    }

    /**
     * Validate JWT token
     *
     * @param string $token JWT token
     * @return array|WP_Error Payload if valid, WP_Error if not
     */
    public function validate_jwt_token($token) {
        if (empty($token)) {
            return new WP_Error(
                'missing_token',
                __('JWT token is required.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return new WP_Error(
                'invalid_token_format',
                __('Invalid JWT token format.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;

        // Validate signature
        $expected_signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $this->jwt_secret, true);
        $expected_signature_encoded = $this->base64url_encode($expected_signature);

        if (!hash_equals($expected_signature_encoded, $signature_encoded)) {
            return new WP_Error(
                'invalid_token_signature',
                __('Invalid JWT token signature.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        // Decode payload
        $payload = json_decode($this->base64url_decode($payload_encoded), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_token_payload',
                __('Invalid JWT token payload.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return new WP_Error(
                'token_expired',
                __('JWT token has expired.', 'affiliatewp-cross-domain-plugin-suite'),
                ['status' => 401]
            );
        }

        return $payload;
    }

    /**
     * Add CORS headers
     */
    public function add_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [$this, 'send_cors_headers'], 15, 4);
    }

    /**
     * Send CORS headers
     *
     * @param bool $served Whether the request has already been served
     * @param WP_HTTP_Response $result Response data
     * @param WP_REST_Request $request Request object
     * @param WP_REST_Server $server Server instance
     * @return bool Whether the request has been served
     */
    public function send_cors_headers($served, $result, $request, $server) {
        $origin = get_http_origin();
        
        if ($origin && $this->is_origin_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link');
            
            if ('OPTIONS' === $request->get_method()) {
                header('Access-Control-Allow-Headers: authorisation, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type, X-API-Key, X-Signature, X-Client-Domain, X-Client-Version');
                header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');
                header('Access-Control-Max-Age: 86400');
                exit;
            }
        }

        return $served;
    }

    /**
     * Add security headers
     *
     * @param bool $served Whether the request has already been served
     * @param WP_HTTP_Response $result Response data
     * @param WP_REST_Request $request Request object
     * @param WP_REST_Server $server Server instance
     * @return bool Whether the request has been served
     */
    public function add_security_headers($served, $result, $request, $server) {
        if (strpos($request->get_route(), '/affcd/') === 0) {
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
     * Check if domain is authorised
     *
     * @param string $domain Domain to check
     * @return bool Whether domain is authorised
     */
    private function is_domain_authorised($domain) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$domains_table} 
             WHERE domain_url = %s AND status = 'active'",
            $domain
        ));

        return $count > 0;
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin Origin to check
     * @return bool Whether origin is allowed
     */
    private function is_origin_allowed($origin) {
        $allowed_origins = $this->get_allowed_origins();
        return in_array($origin, $allowed_origins, true);
    }

    /**
     * Get allowed origins for CORS
     *
     * @return array Allowed origins
     */
    private function get_allowed_origins() {
        global $wpdb;
        
        $cache_key = $this->cache_prefix . 'allowed_origins';
        $cached_origins = wp_cache_get($cache_key, 'affcd');
        
        if ($cached_origins !== false) {
            return $cached_origins;
        }

        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        $domains = $wpdb->get_col(
            "SELECT domain_url FROM {$domains_table} WHERE status = 'active'"
        );

        $origins = [];
        foreach ($domains as $domain) {
            $origins[] = 'https://' . $domain;
            $origins[] = 'http://' . $domain; // Allow HTTP for development
        }

        wp_cache_set($cache_key, $origins, 'affcd', $this->cache_expiration);
        
        return $origins;
    }

    /**
     * Generate request signature
     *
     * @param WP_REST_Request $request Request object
     * @return string Generated signature
     */
    private function generate_request_signature($request) {
        $domain = $this->get_request_domain($request);
        $timestamp = $this->get_request_timestamp($request);
        $method = $request->get_method();
        $route = $request->get_route();
        $body = wp_json_encode($request->get_json_params());

        $string_to_sign = $method . '|' . $route . '|' . $domain . '|' . $timestamp . '|' . $body;
        
        return hash_hmac('sha256', $string_to_sign, $this->jwt_secret);
    }

    /**
     * Get request domain
     *
     * @param WP_REST_Request $request Request object
     * @return string Request domain
     */
    private function get_request_domain($request) {
        return $request->get_header('X-Client-Domain') ?: $request->get_param('domain');
    }

    /**
     * Get API key from request
     *
     * @param WP_REST_Request $request Request object
     * @return string API key
     */
    private function get_api_key($request) {
        return $request->get_header('X-API-Key') ?: $request->get_param('api_key');
    }

    /**
     * Get request signature
     *
     * @param WP_REST_Request $request Request object
     * @return string Request signature
     */
    private function get_request_signature($request) {
        return $request->get_header('X-Signature') ?: $request->get_param('signature');
    }

    /**
     * Get request timestamp
     *
     * @param WP_REST_Request $request Request object
     * @return int Request timestamp
     */
    private function get_request_timestamp($request) {
        return $request->get_header('X-Timestamp') ?: $request->get_param('timestamp');
    }

    /**
     * Check if this is a CORS request
     *
     * @return bool Whether this is a CORS request
     */
    private function is_cors_request() {
        return !empty($_SERVER['HTTP_ORIGIN']);
    }

    /**
     * Handle CORS preflight request
     */
    private function handle_cors_request() {
        $origin = get_http_origin();
        
        if ($origin && $this->is_origin_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            
            if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
                header('Access-Control-Allow-Headers: authorisation, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type, X-API-Key, X-Signature, X-Client-Domain, X-Client-Version');
                header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');
                header('Access-Control-Max-Age: 86400');
                status_header(200);
                exit;
            }
        }
    }

    /**
     * Get JWT secret key
     *
     * @return string JWT secret key
     */
    private function get_jwt_secret() {
        $secret = get_option('affcd_jwt_secret');
        
        if (empty($secret)) {
            $secret = wp_generate_password(64, false);
            update_option('affcd_jwt_secret', $secret);
        }
        
        return $secret;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
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

    /**
     * Base64URL encode
     *
     * @param string $data Data to encode
     * @return string Encoded data
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode
     *
     * @param string $data Data to decode
     * @return string Decoded data
     */
    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Sanitise request data
     *
     * @param array $data Request data
     * @return array Sanitised data
     */
    public function Sanitise_request_data($data) {
        if (!is_array($data)) {
            return [];
        }

        $Sanitised = [];
        
        foreach ($data as $key => $value) {
            $key = Sanitise_key($key);
            
            if (is_array($value)) {
                $Sanitised[$key] = $this->Sanitise_request_data($value);
            } elseif (is_string($value)) {
                $Sanitised[$key] = Sanitise_text_field($value);
            } elseif (is_numeric($value)) {
                $Sanitised[$key] = is_float($value) ? floatval($value) : intval($value);
            } elseif (is_bool($value)) {
                $Sanitised[$key] = (bool) $value;
            } else {
                $Sanitised[$key] = Sanitise_text_field((string) $value);
            }
        }

        return $Sanitised;
    }

    /**
     * Log security event
     *
     * @param string $event_type Event type
     * @param string $message Event message
     * @param array $data Event data
     */
    public function log_security_event($event_type, $message, $data = []) {
        $this->security_manager->log_activity($event_type, array_merge($data, [
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]));
    }

    /**
     * Get security status
     *
     * @return array Security status information
     */
    public function get_security_status() {
        return [
            'jwt_enabled' => !empty($this->jwt_secret),
            'cors_enabled' => true,
            'rate_limiting_enabled' => true,
            'signature_validation_enabled' => true,
            'timestamp_validation_enabled' => true,
            'authorised_domains_count' => $this->get_authorised_domains_count(),
            'security_events_today' => $this->get_security_events_count('today')
        ];
    }

    /**
     * Get authorised domains count
     *
     * @return int authorised domains count
     */
    private function get_authorised_domains_count() {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$domains_table} WHERE status = 'active'"
        );
    }

    /**
     * Get security events count
     *
     * @param string $period Time period (today, week, month)
     * @return int Events count
     */
    private function get_security_events_count($period = 'today') {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'affcd_security_logs';
        
        $date_clause = '';
        switch ($period) {
            case 'today':
                $date_clause = 'DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $date_clause = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $date_clause = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
            default:
                $date_clause = '1=1';
        }
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE {$date_clause}"
        );
    }
}