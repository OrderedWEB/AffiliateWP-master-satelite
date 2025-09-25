<?php
/**
 * API Endpoints Class
 *
 * Handles all REST API endpoints for cross-domain affiliate code validation,
 * domain management, analytics, and webhook processing.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_API_Endpoints {

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'affcd/v1';

    /**
     * Security validator instance
     *
     * @var AFFCD_Security_Validator
     */
    private $security_validator;

    /**
     * Rate limiter instance
     *
     * @var AFFCD_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Vanity code manager instance
     *
     * @var AFFCD_Vanity_Code_Manager
     */
    private $vanity_code_manager;

    /**
     * Constructor
     */
    public function __construct($security_validator, $rate_limiter, $vanity_code_manager) {
        $this->security_validator   = $security_validator;
        $this->rate_limiter         = $rate_limiter;
        $this->vanity_code_manager  = $vanity_code_manager;
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Code validation
        register_rest_route($this->namespace, '/validate-code', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'validate_code'],
            'permission_callback' => [$this, 'validate_api_request'],
            'args'                => $this->get_validate_code_args()
        ]);

        // Authorized domains (list/create/update/delete/verify)
        register_rest_route($this->namespace, '/domains', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_domains'],
            'permission_callback' => [$this, 'validate_admin_request']
        ]);

        register_rest_route($this->namespace, '/domains', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'add_domain'],
            'permission_callback' => [$this, 'validate_admin_request'],
            'args'                => $this->get_domain_args()
        ]);

        register_rest_route($this->namespace, '/domains/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_domain'],
            'permission_callback' => [$this, 'validate_admin_request'],
            'args'                => array_merge(['id' => ['required' => true, 'type' => 'integer']], $this->get_domain_args())
        ]);

        register_rest_route($this->namespace, '/domains/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_domain'],
            'permission_callback' => [$this, 'validate_admin_request'],
            'args'                => ['id' => ['required' => true, 'type' => 'integer']]
        ]);

        register_rest_route($this->namespace, '/domains/(?P<id>\d+)/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verify_domain'],
            'permission_callback' => [$this, 'validate_admin_request'],
            'args'                => ['id' => ['required' => true, 'type' => 'integer']]
        ]);

        // Analytics
        register_rest_route($this->namespace, '/analytics/summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_analytics_summary'],
            'permission_callback' => [$this, 'validate_admin_request']
        ]);

        register_rest_route($this->namespace, '/analytics/codes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_code_analytics'],
            'permission_callback' => [$this, 'validate_admin_request'],
            'args'                => $this->get_analytics_args()
        ]);

        // Health
        register_rest_route($this->namespace, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'health_check'],
            'permission_callback' => '__return_true'
        ]);

        // Client config
        register_rest_route($this->namespace, '/config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_client_config'],
            'permission_callback' => [$this, 'validate_api_request']
        ]);

        // Webhook test
        register_rest_route($this->namespace, '/webhook/test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'test_webhook'],
            'permission_callback' => [$this, 'validate_admin_request']
        ]);
    }

    /**
     * Validate affiliate code
     */
    public function validate_code($request) {
        $code      = sanitize_text_field($request->get_param('code'));
        $domain    = sanitize_text_field($request->get_param('domain'));
        $user_data = $request->get_param('user_data') ?: [];

        if (empty($code)) {
            return new WP_Error('missing_code', __('Affiliate code is required', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        if (!$this->rate_limiter->check_rate_limit($domain, 'validate_code')) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded. Please try again later.', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 429]);
        }

        $validation_result = $this->vanity_code_manager->validate_code($code, $domain, $user_data);

        $this->log_validation_attempt($code, $domain, $validation_result, $request);

        $response_data = [
            'valid'     => $validation_result['valid'],
            'message'   => $validation_result['message'],
            'timestamp' => current_time('mysql')
        ];

        if ($validation_result['valid'] && !empty($validation_result['discount'])) {
            $response_data['discount'] = [
                'type'        => $validation_result['discount']['type'],
                'amount'      => $validation_result['discount']['amount'],
                'description' => $validation_result['discount']['description'] ?? ''
            ];
        }

        if ($validation_result['valid'] && !empty($validation_result['affiliate'])) {
            $response_data['affiliate'] = [
                'id'   => $validation_result['affiliate']['id'],
                'name' => $validation_result['affiliate']['name']
            ];
        }

        return rest_ensure_response($response_data);
    }

    /**
     * List authorized domains (ordered by domain_url)
     */
    public function list_domains($request) {
        global $wpdb;

        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        $page     = max(1, intval($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
        $offset   = ($page - 1) * $per_page;

        $domains = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$domains_table} ORDER BY domain_url ASC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$domains_table}");

        return rest_ensure_response([
            'domains'     => $domains,
            'pagination'  => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
                'pages'    => (int) ceil($total / $per_page)
            ]
        ]);
    }

    /**
     * Add new authorized domain
     */
    public function add_domain($request) {
        global $wpdb;

        // Back-compat: accept "domain" or "domain_url"
        $domain_url  = sanitize_text_field($request->get_param('domain_url') ?: $request->get_param('domain'));
        $domain_name = sanitize_text_field($request->get_param('domain_name'));
        $api_key     = sanitize_text_field($request->get_param('api_key'));
        $api_secret  = sanitize_text_field($request->get_param('api_secret'));
        $notes       = sanitize_text_field($request->get_param('description')); // map old "description" -> notes
        $status      = sanitize_text_field($request->get_param('status') ?: 'active');

        // Normalise and validate URL
        if (empty($domain_url)) {
            return new WP_Error('invalid_domain', __('Domain URL is required', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }
        $domain_url = $this->normalize_domain_url($domain_url);
        if (!filter_var($domain_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_domain', __('Invalid domain URL', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        // Exists?
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$domains_table} WHERE domain_url = %s",
            $domain_url
        ));
        if ($existing > 0) {
            return new WP_Error('domain_exists', __('Domain already exists', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 409]);
        }

        // Keys
        if (empty($api_key)) {
            $api_key = wp_generate_password(40, false);
        }
        if (empty($api_secret)) {
            $api_secret = wp_generate_password(64, true, true);
        }

        $result = $wpdb->insert(
            $domains_table,
            [
                'domain_url'            => $domain_url,
                'domain_name'           => $domain_name ?: parse_url($domain_url, PHP_URL_HOST),
                'api_key'               => $api_key,
                'api_secret'            => $api_secret,
                'status'                => in_array($status, ['active','inactive','suspended','pending'], true) ? $status : 'active',
                'verification_status'   => 'unverified',
                'verification_method'   => 'file',
                'notes'                 => $notes,
                'created_by'            => get_current_user_id(),
                'created_at'            => current_time('mysql'),
                'updated_at'            => current_time('mysql'),
                'last_verified_at'      => null,
                'verification_failures' => 0
            ],
            ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%d']
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to add domain', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 500]);
        }

        $domain_id = (int) $wpdb->insert_id;

        if (function_exists('affcd_log_activity')) {
            affcd_log_activity('domain_added', [
                'domain_id' => $domain_id,
                'domain'    => $domain_url,
                'user_id'   => get_current_user_id()
            ]);
        }

        return rest_ensure_response([
            'id'                  => $domain_id,
            'domain_url'          => $domain_url,
            'domain_name'         => $domain_name ?: parse_url($domain_url, PHP_URL_HOST),
            'api_key'             => $api_key,
            'api_secret'          => $api_secret,
            'status'              => $status,
            'verification_status' => 'unverified',
            'message'             => __('Domain added successfully', 'affiliatewp-cross-domain-plugin-suite')
        ]);
    }

    /**
     * Update authorized domain
     */
    public function update_domain($request) {
        global $wpdb;

        $domain_id   = (int) $request->get_param('id');
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$domains_table} WHERE id = %d", $domain_id));
        if (!$existing) {
            return new WP_Error('domain_not_found', __('Domain not found', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 404]);
        }

        // Accept both old and new field names
        $domain_url   = sanitize_text_field($request->get_param('domain_url') ?: $request->get_param('domain'));
        $domain_name  = sanitize_text_field($request->get_param('domain_name'));
        $status       = sanitize_text_field($request->get_param('status'));
        $notes        = sanitize_text_field($request->get_param('description') ?: $request->get_param('notes'));
        $verification_status = sanitize_text_field($request->get_param('verification_status'));

        $update_data    = [];
        $update_formats = [];

        if (!empty($domain_url) && $domain_url !== $existing->domain_url) {
            $domain_url = $this->normalize_domain_url($domain_url);
            if (!filter_var($domain_url, FILTER_VALIDATE_URL)) {
                return new WP_Error('invalid_domain', __('Invalid domain URL', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
            }
            $update_data['domain_url'] = $domain_url;
            $update_formats[] = '%s';
        }

        if (!empty($domain_name)) {
            $update_data['domain_name'] = $domain_name;
            $update_formats[] = '%s';
        }

        if (!empty($notes)) {
            $update_data['notes'] = $notes;
            $update_formats[] = '%s';
        }

        if (!empty($status) && in_array($status, ['active','inactive','suspended','pending'], true)) {
            $update_data['status'] = $status;
            $update_formats[] = '%s';
        }

        if (!empty($verification_status) && in_array($verification_status, ['verified','unverified','failed'], true)) {
            $update_data['verification_status'] = $verification_status;
            $update_formats[] = '%s';
        }

        if (empty($update_data)) {
            return new WP_Error('no_changes', __('No changes provided', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        $update_data['updated_at'] = current_time('mysql');
        $update_formats[] = '%s';

        $result = $wpdb->update(
            $domains_table,
            $update_data,
            ['id' => $domain_id],
            $update_formats,
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to update domain', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 500]);
        }

        if (function_exists('affcd_log_activity')) {
            affcd_log_activity('domain_updated', [
                'domain_id' => $domain_id,
                'changes'   => $update_data,
                'user_id'   => get_current_user_id()
            ]);
        }

        return rest_ensure_response(['message' => __('Domain updated successfully', 'affiliatewp-cross-domain-plugin-suite')]);
    }

    /**
     * Delete authorized domain
     */
    public function delete_domain($request) {
        global $wpdb;

        $domain_id = (int) $request->get_param('id');
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$domains_table} WHERE id = %d", $domain_id));
        if (!$existing) {
            return new WP_Error('domain_not_found', __('Domain not found', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 404]);
        }

        $result = $wpdb->delete($domains_table, ['id' => $domain_id], ['%d']);

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to delete domain', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 500]);
        }

        if (function_exists('affcd_log_activity')) {
            affcd_log_activity('domain_deleted', [
                'domain_id' => $domain_id,
                'domain'    => $existing->domain_url,
                'user_id'   => get_current_user_id()
            ]);
        }

        return rest_ensure_response(['message' => __('Domain deleted successfully', 'affiliatewp-cross-domain-plugin-suite')]);
    }

    /**
     * Verify domain connection
     */
    public function verify_domain($request) {
        global $wpdb;

        $domain_id = (int) $request->get_param('id');
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';

        $domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$domains_table} WHERE id = %d", $domain_id));
        if (!$domain) {
            return new WP_Error('domain_not_found', __('Domain not found', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 404]);
        }

        // Build test URL
        $base = rtrim($this->normalize_domain_url($domain->domain_url), '/');
        $test_url = $base . '/wp-json/wp/v2/';

        $response = wp_remote_get($test_url, [
            'timeout' => 10,
            'headers' => ['User-Agent' => 'AffiliateWP-Cross-Domain/' . (defined('AFFCD_VERSION') ? AFFCD_VERSION : '1.0')]
        ]);

        $verification_success = false;
        $verification_message = '';

        if (is_wp_error($response)) {
            $verification_message = $response->get_error_message();
        } else {
            $status_code = (int) wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                $verification_success = true;
                $verification_message = __('Connection verified successfully', 'affiliatewp-cross-domain-plugin-suite');
            } else {
                $verification_message = sprintf(__('HTTP %d response received', 'affiliatewp-cross-domain-plugin-suite'), $status_code);
            }
        }

        $update_data = [
            'last_verified_at'      => current_time('mysql'),
            'updated_at'            => current_time('mysql')
        ];

        if ($verification_success) {
            $update_data['verification_failures'] = 0;
            $update_data['verification_status']   = 'verified';
            $update_data['status']                = 'active';
        } else {
            $update_data['verification_failures'] = (int) $domain->verification_failures + 1;
            $update_data['verification_status']   = 'failed';
            if ($update_data['verification_failures'] >= 3) {
                $update_data['status'] = 'suspended';
            }
        }

        $wpdb->update(
            $domains_table,
            $update_data,
            ['id' => $domain_id],
            ['%s','%s','%d','%s','%s'],
            ['%d']
        );

        return rest_ensure_response([
            'success'  => $verification_success,
            'message'  => $verification_message,
            'failures' => (int) $update_data['verification_failures']
        ]);
    }

    /**
     * Get analytics summary
     */
    public function get_analytics_summary($request) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        $codes_table     = $wpdb->prefix . 'affcd_vanity_codes';
        $domains_table   = $wpdb->prefix . 'affcd_authorized_domains';

        $date_from = sanitize_text_field($request->get_param('date_from')) ?: date('Y-m-d', strtotime('-30 days'));
        $date_to   = sanitize_text_field($request->get_param('date_to'))   ?: date('Y-m-d');

        $total_validations = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$analytics_table}
             WHERE event_type = 'code_validation'
             AND DATE(created_at) BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        $successful_validations = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$analytics_table}
             WHERE event_type = 'code_validation'
               AND event_data LIKE '%\"valid\":true%'
               AND DATE(created_at) BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        $total_active_codes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$codes_table} WHERE status = 'active'");

        $total_domains = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$domains_table} WHERE status = 'active'");

        $top_codes = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.code')) as code,
                COUNT(*) as validations,
                SUM(CASE WHEN event_data LIKE '%\"valid\":true%' THEN 1 ELSE 0 END) as successful
             FROM {$analytics_table}
             WHERE event_type = 'code_validation'
               AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.code'))
             ORDER BY validations DESC
             LIMIT 10",
            $date_from, $date_to
        ));

        $daily_validations = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN event_data LIKE '%\"valid\":true%' THEN 1 ELSE 0 END) as successful
             FROM {$analytics_table}
             WHERE event_type = 'code_validation'
               AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $date_from, $date_to
        ));

        return rest_ensure_response([
            'summary' => [
                'total_validations'      => $total_validations,
                'successful_validations' => $successful_validations,
                'success_rate'           => $total_validations > 0 ? round(($successful_validations / $total_validations) * 100, 2) : 0,
                'total_active_codes'     => $total_active_codes,
                'total_domains'          => $total_domains
            ],
            'top_codes'         => $top_codes,
            'daily_validations' => $daily_validations,
            'date_range'        => ['from' => $date_from, 'to' => $date_to]
        ]);
    }

    /**
     * Get code analytics
     */
    public function get_code_analytics($request) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        $code = sanitize_text_field($request->get_param('code'));
        if (empty($code)) {
            return new WP_Error('missing_code', __('Code parameter is required', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        $date_from = sanitize_text_field($request->get_param('date_from')) ?: date('Y-m-d', strtotime('-30 days'));
        $date_to   = sanitize_text_field($request->get_param('date_to'))   ?: date('Y-m-d');

        $validation_history = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                created_at,
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.domain')) as domain,
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.valid')) as valid,
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.message')) as message
             FROM {$analytics_table}
             WHERE event_type = 'code_validation'
               AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.code')) = %s
               AND DATE(created_at) BETWEEN %s AND %s
             ORDER BY created_at DESC
             LIMIT 100",
            $code, $date_from, $date_to
        ));

        $domain_breakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.domain')) as domain,
                COUNT(*) as total_validations,
                SUM(CASE WHEN event_data LIKE '%\"valid\":true%' THEN 1 ELSE 0 END) as successful_validations
             FROM {$analytics_table}
             WHERE event_type = 'code_validation'
               AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.code')) = %s
               AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.domain'))
             ORDER BY total_validations DESC",
            $code, $date_from, $date_to
        ));

        return rest_ensure_response([
            'code'               => $code,
            'validation_history' => $validation_history,
            'domain_breakdown'   => $domain_breakdown,
            'date_range'         => ['from' => $date_from, 'to' => $date_to]
        ]);
    }

    /**
     * Health check
     */
    public function health_check($request) {
        global $wpdb;

        $health_status = 'healthy';
        $checks        = [];

        // DB
        try {
            $wpdb->get_var("SELECT 1");
            $checks['database'] = ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
            $health_status = 'unhealthy';
        }

        // Required tables
        $required_tables = [
            $wpdb->prefix . 'affcd_vanity_codes',
            $wpdb->prefix . 'affcd_authorized_domains',
            $wpdb->prefix . 'affcd_analytics'
        ];

        $missing_tables = [];
        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }

        if (empty($missing_tables)) {
            $checks['tables'] = ['status' => 'ok', 'message' => 'All required tables exist'];
        } else {
            $checks['tables'] = ['status' => 'error', 'message' => 'Missing tables: ' . implode(', ', $missing_tables)];
            $health_status = 'unhealthy';
        }

        // AffiliateWP
        if (function_exists('affiliate_wp')) {
            $checks['affiliatewp'] = ['status' => 'ok', 'message' => 'AffiliateWP is active'];
        } else {
            $checks['affiliatewp'] = ['status' => 'warning', 'message' => 'AffiliateWP not found'];
        }

        // WP version
        global $wp_version;
        $checks['wordpress'] = version_compare($wp_version, '5.0', '>=') ?
            ['status' => 'ok', 'message' => 'WordPress version compatible'] :
            ['status' => 'warning', 'message' => 'WordPress version may be incompatible'];

        // PHP version
        if (version_compare(PHP_VERSION, '7.4', '>=')) {
            $checks['php'] = ['status' => 'ok', 'message' => 'PHP version compatible'];
        } else {
            $checks['php'] = ['status' => 'error', 'message' => 'PHP version incompatible'];
            $health_status = 'unhealthy';
        }

        return rest_ensure_response([
            'status'    => $health_status,
            'version'   => defined('AFFCD_VERSION') ? AFFCD_VERSION : '1.0',
            'timestamp' => current_time('mysql'),
            'checks'    => $checks
        ]);
    }

    /**
     * Get client configuration
     */
    public function get_client_config($request) {
        $domain = $this->get_request_domain($request);

        $settings = get_option('affcd_settings', []);

        $config = [
            'api_version' => 'v1',
            'endpoints'   => [
                'validate_code' => rest_url($this->namespace . '/validate-code'),
                'health'        => rest_url($this->namespace . '/health')
            ],
            'rate_limits' => [
                'requests_per_hour' => $settings['rate_limit_requests_per_hour'] ?? 1000
            ],
            'cache'       => [
                'enabled'  => $settings['cache_enabled'] ?? true,
                'duration' => $settings['cache_duration'] ?? 900
            ],
            'security'    => [
                'require_https' => $settings['require_https'] ?? true
            ],
            'domain'      => $domain
        ];

        return rest_ensure_response($config);
    }

    /**
     * Test webhook
     */
    public function test_webhook($request) {
        $webhook_url = esc_url_raw($request->get_param('url'));

        if (empty($webhook_url)) {
            return new WP_Error('missing_url', __('Webhook URL is required', 'affiliatewp-cross-domain-plugin-suite'), ['status' => 400]);
        }

        $test_data = [
            'event'     => 'test',
            'timestamp' => current_time('mysql'),
            'data'      => ['message' => 'This is a test webhook from AffiliateWP Cross Domain Plugin Suite']
        ];

        $response = wp_remote_post($webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'AffiliateWP-Cross-Domain/' . (defined('AFFCD_VERSION') ? AFFCD_VERSION : '1.0')
            ],
            'body'    => wp_json_encode($test_data),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('webhook_failed', $response->get_error_message(), ['status' => 500]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $success     = $status_code >= 200 && $status_code < 300;

        return rest_ensure_response([
            'success'     => $success,
            'status_code' => $status_code,
            'message'     => $success
                ? __('Webhook test successful', 'affiliatewp-cross-domain-plugin-suite')
                : sprintf(__('Webhook test failed with status %d', 'affiliatewp-cross-domain-plugin-suite'), $status_code)
        ]);
    }

    /**
     * Permission checks
     */
    public function validate_api_request($request) {
        return $this->security_validator->validate_request($request);
    }
    public function validate_admin_request($request) {
        return current_user_can('manage_affiliates');
    }

    /**
     * Args
     */
    private function get_validate_code_args() {
        return [
            'code' => [
                'required'           => true,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field',
                'validate_callback'  => function($param){ return !empty($param) && strlen($param) <= 100; }
            ],
            'domain' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field'
            ],
            'user_data' => [
                'required' => false,
                'type'     => 'object',
                'default'  => []
            ]
        ];
    }

    private function get_domain_args() {
        return [
            // accept both "domain_url" and legacy "domain"
            'domain_url' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field',
                'validate_callback'  => function($param){ return !empty($param); }
            ],
            'domain' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field'
            ],
            'domain_name' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field'
            ],
            'api_key' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field'
            ],
            'api_secret' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field'
            ],
            'description' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field'
            ],
            'notes' => [
                'required'           => false,
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field'
            ],
            'status' => [
                'required' => false,
                'type'     => 'string',
                'enum'     => ['active','inactive','suspended','pending'],
                'default'  => 'active'
            ],
            'verification_status' => [
                'required' => false,
                'type'     => 'string',
                'enum'     => ['verified','unverified','failed']
            ]
        ];
    }

    private function get_analytics_args() {
        return [
            'date_from' => [
                'required' => false,
                'type'     => 'string',
                'format'   => 'date',
                'default'  => date('Y-m-d', strtotime('-30 days'))
            ],
            'date_to' => [
                'required' => false,
                'type'     => 'string',
                'format'   => 'date',
                'default'  => date('Y-m-d')
            ],
            'code' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Logging helpers
     */
    private function log_validation_attempt($code, $domain, $result, $request) {
        $event_data = [
            'code'       => $code,
            'domain'     => $domain,
            'valid'      => $result['valid'],
            'message'    => $result['message'],
            'ip_address' => $this->get_client_ip($request),
            'user_agent' => $request->get_header('user_agent')
        ];
        if (function_exists('affcd_log_activity')) {
            affcd_log_activity('code_validation', $event_data);
        }
    }

    private function get_client_ip($request) {
        $ip_headers = ['HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','HTTP_CLIENT_IP','REMOTE_ADDR'];
        foreach ($ip_headers as $header) {
            $ip = $request->get_header($header);
            if (!empty($ip)) {
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function get_request_domain($request) {
        $domain = $request->get_param('domain') ?: $request->get_header('x_client_domain');
        if (empty($domain)) {
            $referer = $request->get_header('referer');
            if ($referer) {
                $parsed = parse_url($referer);
                $domain = $parsed['host'] ?? '';
            }
        }
        return sanitize_text_field($domain);
    }

    private function normalize_domain_url($url) {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }
}
