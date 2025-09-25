<?php
/**
 * Addon API Class
 *
 * Provides REST API endpoints for addon communication and management.
 * Handles addon registration, configuration synchronisation, and data exchange
 * between master plugin and client plugins.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 * @author Richard King, starneconsulting.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Addon_API {

    /**
     * API version
     *
     * @var string
     */
    private $api_version = '1.0';

    /**
     * Database manager instance
     *
     * @var AFFCD_Database_Manager
     */
    private $db_manager;

    /**
     * Registered endpoints
     *
     * @var array
     */
    private $endpoints = [];

    /**
     * Rate limiting configuration
     *
     * @var array
     */
    private $rate_limits = [
        'default' => 1000,
        'registration' => 100,
        'configuration' => 500,
        'tracking' => 10000
    ];

    /**
     * Constructor
     *
     * @param AFFCD_Database_Manager $db_manager Database manager instance
     */
    public function __construct($db_manager) {
        $this->db_manager = $db_manager;
        $this->init_hooks();
        $this->define_endpoints();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_pre_dispatch', [$this, 'validate_api_request'], 10, 3);
        add_action('rest_api_init', [$this, 'add_cors_headers']);
    }

    /**
     * Define API endpoints
     */
    private function define_endpoints() {
        $this->endpoints = [
            // Addon management
            '/addons/register' => [
                'methods' => 'POST',
                'callback' => 'register_addon',
                'permission' => 'manage_addons',
                'rate_limit' => 'registration'
            ],
            '/addons/unregister' => [
                'methods' => 'POST',
                'callback' => 'unregister_addon',
                'permission' => 'manage_addons',
                'rate_limit' => 'registration'
            ],
            '/addons/status' => [
                'methods' => 'GET',
                'callback' => 'get_addon_status',
                'permission' => 'view_addons',
                'rate_limit' => 'default'
            ],
            '/addons/list' => [
                'methods' => 'GET',
                'callback' => 'list_addons',
                'permission' => 'view_addons',
                'rate_limit' => 'default'
            ],

            // Configuration management
            '/config/sync' => [
                'methods' => 'POST',
                'callback' => 'sync_configuration',
                'permission' => 'manage_configuration',
                'rate_limit' => 'configuration'
            ],
            '/config/get' => [
                'methods' => 'GET',
                'callback' => 'get_configuration',
                'permission' => 'view_configuration',
                'rate_limit' => 'configuration'
            ],
            '/config/update' => [
                'methods' => 'PUT',
                'callback' => 'update_configuration',
                'permission' => 'manage_configuration',
                'rate_limit' => 'configuration'
            ],

            // Tracking and analytics
            '/tracking/event' => [
                'methods' => 'POST',
                'callback' => 'track_event',
                'permission' => 'track_events',
                'rate_limit' => 'tracking'
            ],
            '/tracking/conversion' => [
                'methods' => 'POST',
                'callback' => 'track_conversion',
                'permission' => 'track_events',
                'rate_limit' => 'tracking'
            ],
            '/tracking/batch' => [
                'methods' => 'POST',
                'callback' => 'track_batch',
                'permission' => 'track_events',
                'rate_limit' => 'tracking'
            ],

            // Health and diagnostics
            '/health' => [
                'methods' => 'GET',
                'callback' => 'health_check',
                'permission' => 'public',
                'rate_limit' => 'default'
            ],
            '/version' => [
                'methods' => 'GET',
                'callback' => 'get_version',
                'permission' => 'public',
                'rate_limit' => 'default'
            ],
            '/diagnostics' => [
                'methods' => 'GET',
                'callback' => 'run_diagnostics',
                'permission' => 'manage_system',
                'rate_limit' => 'default'
            ]
        ];
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        foreach ($this->endpoints as $route => $config) {
            register_rest_route('affcd/v' . $this->api_version, $route, [
                'methods' => $config['methods'],
                'callback' => [$this, $config['callback']],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => $this->get_endpoint_args($route)
            ]);
        }
    }

    /**
     * Add CORS headers for cross-domain requests
     */
    public function add_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [$this, 'send_cors_headers']);
    }

    /**
     * Send CORS headers
     *
     * @param bool $served Whether request has been served
     * @return bool
     */
    public function send_cors_headers($served) {
        $origin = get_http_origin();
        
        if ($this->is_allowed_origin($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
        
        return $served;
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin Request origin
     * @return bool True if allowed
     */
    private function is_allowed_origin($origin) {
        if (!$origin) {
            return false;
        }

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');
        
        $allowed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE domain_url = %s 
             AND status = 'active' 
             AND verification_status = 'verified'",
            $origin
        ));
        
        return $allowed > 0;
    }

    /**
     * Validate API request
     *
     * @param mixed $result
     * @param WP_REST_Server $server
     * @param WP_REST_Request $request
     * @return mixed
     */
    public function validate_api_request($result, $server, $request) {
        $route = $request->get_route();
        
        if (!$this->is_affcd_route($route)) {
            return $result;
        }

        // Rate limiting
        if (!$this->check_rate_limit($request)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', ['status' => 429]);
        }

        // API key validation
        if (!$this->validate_api_key($request)) {
            return new WP_Error('invalid_api_key', 'Invalid API key', ['status' => 401]);
        }

        // Log API request
        $this->log_api_request($request);

        return $result;
    }

    /**
     * Check if route belongs to AFFCD
     *
     * @param string $route Route path
     * @return bool True if AFFCD route
     */
    private function is_affcd_route($route) {
        return strpos($route, '/affcd/v' . $this->api_version) === 0;
    }

    /**
     * Check rate limiting
     *
     * @param WP_REST_Request $request
     * @return bool True if within limits
     */
    private function check_rate_limit($request) {
        $api_key = $this->get_api_key($request);
        $endpoint = $this->get_endpoint_from_route($request->get_route());
        $rate_limit_type = $this->endpoints[$endpoint]['rate_limit'] ?? 'default';
        $limit = $this->rate_limits[$rate_limit_type];

        return $this->is_within_rate_limit($api_key, $limit);
    }

    /**
     * Check if request is within rate limit
     *
     * @param string $api_key API key
     * @param int $limit Rate limit
     * @return bool True if within limit
     */
    private function is_within_rate_limit($api_key, $limit) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('rate_limiting');
        
        $identifier = $api_key ?: $this->get_client_ip();
        $window_start = date('Y-m-d H:00:00');
        $window_end = date('Y-m-d H:59:59');

        // Get current count
        $current_count = $wpdb->get_var($wpdb->prepare(
            "SELECT request_count FROM {$table_name} 
             WHERE identifier = %s 
             AND action_type = 'api_request' 
             AND window_start = %s 
             AND is_blocked = 0",
            $identifier,
            $window_start
        ));

        if ($current_count >= $limit) {
            return false;
        }

        // Update or insert rate limit record
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name} 
             (identifier, action_type, request_count, window_start, window_end, last_request) 
             VALUES (%s, 'api_request', 1, %s, %s, NOW()) 
             ON DUPLICATE KEY UPDATE 
             request_count = request_count + 1, 
             last_request = NOW()",
            $identifier,
            $window_start,
            $window_end
        ));

        return true;
    }

    /**
     * Validate API key
     *
     * @param WP_REST_Request $request
     * @return bool True if valid
     */
    private function validate_api_key($request) {
        $api_key = $this->get_api_key($request);
        
        if (!$api_key) {
            return false;
        }

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');
        
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE api_key = %s 
             AND status = 'active' 
             AND verification_status = 'verified'",
            $api_key
        ));

        if (!$domain) {
            return false;
        }

        // Update last activity
        $wpdb->update(
            $table_name,
            ['last_activity_at' => current_time('mysql')],
            ['id' => $domain->id],
            ['%s'],
            ['%d']
        );

        return true;
    }

    /**
     * Get API key from request
     *
     * @param WP_REST_Request $request
     * @return string|null API key
     */
    private function get_api_key($request) {
        $auth_header = $request->get_header('authorization');
        
        if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }
        
        return $request->get_param('api_key');
    }

    /**
     * Get endpoint from route
     *
     * @param string $route Full route
     * @return string Endpoint
     */
    private function get_endpoint_from_route($route) {
        $prefix = '/affcd/v' . $this->api_version;
        return str_replace($prefix, '', $route);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim($_SERVER[$header]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Log API request
     *
     * @param WP_REST_Request $request
     */
    private function log_api_request($request) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('api_audit_logs');
        
        $request_id = wp_generate_uuid4();
        $api_key = $this->get_api_key($request);
        
        $wpdb->insert($table_name, [
            'request_id' => $request_id,
            'api_key' => $api_key,
            'endpoint' => $request->get_route(),
            'method' => $request->get_method(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_headers' => json_encode($request->get_headers()),
            'request_body' => json_encode($request->get_body_params()),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Check permissions for API endpoints
     *
     * @param WP_REST_Request $request
     * @return bool True if authorized
     */
    public function check_permissions($request) {
        $route = $request->get_route();
        $endpoint = $this->get_endpoint_from_route($route);
        $permission = $this->endpoints[$endpoint]['permission'] ?? 'manage_options';

        if ($permission === 'public') {
            return true;
        }

        $api_key = $this->get_api_key($request);
        if (!$api_key) {
            return false;
        }

        return $this->check_api_permission($api_key, $permission);
    }

    /**
     * Check API key permissions
     *
     * @param string $api_key API key
     * @param string $permission Required permission
     * @return bool True if authorized
     */
    private function check_api_permission($api_key, $permission) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');
        
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed_endpoints FROM {$table_name} 
             WHERE api_key = %s 
             AND status = 'active' 
             AND verification_status = 'verified'",
            $api_key
        ));

        if (!$domain) {
            return false;
        }

        $allowed_endpoints = json_decode($domain->allowed_endpoints, true) ?: [];
        return empty($allowed_endpoints) || in_array($permission, $allowed_endpoints, true);
    }

    /**
     * Get endpoint arguments
     *
     * @param string $endpoint Endpoint path
     * @return array Endpoint arguments
     */
    private function get_endpoint_args($endpoint) {
        $args = [];

        switch ($endpoint) {
            case '/addons/register':
                $args = [
                    'addon_slug' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key'
                    ],
                    'addon_name' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'version' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'capabilities' => [
                        'required' => false,
                        'type' => 'array',
                        'default' => []
                    ]
                ];
                break;

            case '/tracking/event':
                $args = [
                    'event_type' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key'
                    ],
                    'data' => [
                        'required' => false,
                        'type' => 'object',
                        'default' => []
                    ]
                ];
                break;

            case '/tracking/conversion':
                $args = [
                    'amount' => [
                        'required' => true,
                        'type' => 'number',
                        'minimum' => 0
                    ],
                    'currency' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'GBP',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'reference' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ];
                break;
        }

        return $args;
    }

    // API Endpoint Methods

    /**
     * Register addon
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function register_addon($request) {
        $addon_slug = $request->get_param('addon_slug');
        $addon_name = $request->get_param('addon_name');
        $version = $request->get_param('version');
        $capabilities = $request->get_param('capabilities');
        $api_key = $this->get_api_key($request);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        // Get domain information
        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE api_key = %s",
            $api_key
        ));

        if (!$domain) {
            return new WP_Error('invalid_domain', 'Domain not found', ['status' => 404]);
        }

        // Update domain with addon information
        $current_addons = json_decode($domain->metadata, true) ?: [];
        $current_addons['registered_addons'][$addon_slug] = [
            'name' => $addon_name,
            'version' => $version,
            'capabilities' => $capabilities,
            'registered_at' => current_time('c'),
            'last_seen' => current_time('c')
        ];

        $wpdb->update(
            $table_name,
            [
                'metadata' => json_encode($current_addons),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $domain->id],
            ['%s', '%s'],
            ['%d']
        );

        return rest_ensure_response([
            'success' => true,
            'addon_slug' => $addon_slug,
            'registered_at' => current_time('c'),
            'message' => 'Addon registered successfully'
        ]);
    }

    /**
     * Unregister addon
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function unregister_addon($request) {
        $addon_slug = $request->get_param('addon_slug');
        $api_key = $this->get_api_key($request);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE api_key = %s",
            $api_key
        ));

        if (!$domain) {
            return new WP_Error('invalid_domain', 'Domain not found', ['status' => 404]);
        }

        $current_addons = json_decode($domain->metadata, true) ?: [];
        if (isset($current_addons['registered_addons'][$addon_slug])) {
            unset($current_addons['registered_addons'][$addon_slug]);

            $wpdb->update(
                $table_name,
                [
                    'metadata' => json_encode($current_addons),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $domain->id],
                ['%s', '%s'],
                ['%d']
            );
        }

        return rest_ensure_response([
            'success' => true,
            'addon_slug' => $addon_slug,
            'message' => 'Addon unregistered successfully'
        ]);
    }

    /**
     * Get addon status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_addon_status($request) {
        $addon_slug = $request->get_param('addon_slug');
        $api_key = $this->get_api_key($request);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT metadata FROM {$table_name} WHERE api_key = %s",
            $api_key
        ));

        if (!$domain) {
            return new WP_Error('invalid_domain', 'Domain not found', ['status' => 404]);
        }

        $addons = json_decode($domain->metadata, true) ?: [];
        $registered_addons = $addons['registered_addons'] ?? [];

        if ($addon_slug && !isset($registered_addons[$addon_slug])) {
            return new WP_Error('addon_not_found', 'Addon not registered', ['status' => 404]);
        }

        if ($addon_slug) {
            $addon_info = $registered_addons[$addon_slug];
            $addon_info['status'] = 'active';
            return rest_ensure_response($addon_info);
        }

        return rest_ensure_response([
            'total_addons' => count($registered_addons),
            'registered_addons' => $registered_addons
        ]);
    }

    /**
     * List all addons
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_addons($request) {
        $api_key = $this->get_api_key($request);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT metadata, domain_name FROM {$table_name} WHERE api_key = %s",
            $api_key
        ));

        $addons = [];
        if ($domain) {
            $metadata = json_decode($domain->metadata, true) ?: [];
            $addons = $metadata['registered_addons'] ?? [];
        }

        return rest_ensure_response([
            'domain' => $domain->domain_name ?? 'Unknown',
            'addons' => $addons,
            'total' => count($addons)
        ]);
    }

    /**
     * Sync configuration
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function sync_configuration($request) {
        $config_data = $request->get_param('config');
        $api_key = $this->get_api_key($request);

        // Get domain configuration and merge with provided config
        $domain_config = $this->get_domain_configuration($api_key);
        $merged_config = array_merge($domain_config, $config_data ?: []);

        return rest_ensure_response([
            'success' => true,
            'configuration' => $merged_config,
            'synced_at' => current_time('c')
        ]);
    }

    /**
     * Get configuration
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_configuration($request) {
        $api_key = $this->get_api_key($request);
        $config = $this->get_domain_configuration($api_key);

        return rest_ensure_response([
            'configuration' => $config,
            'retrieved_at' => current_time('c')
        ]);
    }

    /**
     * Update configuration
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_configuration($request) {
        $config_updates = $request->get_param('config');
        $api_key = $this->get_api_key($request);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT metadata FROM {$table_name} WHERE api_key = %s",
            $api_key
        ));

        if (!$domain) {
            return new WP_Error('invalid_domain', 'Domain not found', ['status' => 404]);
        }

        $metadata = json_decode($domain->metadata, true) ?: [];
        $metadata['configuration'] = array_merge(
            $metadata['configuration'] ?? [],
            $config_updates
        );

        $wpdb->update(
            $table_name,
            ['metadata' => json_encode($metadata)],
            ['api_key' => $api_key],
            ['%s'],
            ['%s']
        );

        return rest_ensure_response([
            'success' => true,
            'updated_at' => current_time('c')
        ]);
    }

    /**
     * Track event
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function track_event($request) {
        $event_type = $request->get_param('event_type');
        $data = $request->get_param('data');
        $api_key = $this->get_api_key($request);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('usage_tracking');

        $tracking_data = [
            'domain_from' => $this->get_domain_from_api_key($api_key),
            'event_type' => $event_type,
            'status' => 'success',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_data' => json_encode($data),
            'api_version' => $this->api_version,
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($table_name, $tracking_data);

        return rest_ensure_response([
            'success' => true,
            'event_id' => $wpdb->insert_id,
            'tracked_at' => current_time('c')
        ]);
    }

    /**
     * Track conversion
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function track_conversion($request) {
        $amount = $request->get_param('amount');
        $currency = $request->get_param('currency');
        $reference = $request->get_param('reference');
        $api_key = $this->get_api_key($request);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('usage_tracking');

        $conversion_data = [
            'domain_from' => $this->get_domain_from_api_key($api_key),
            'event_type' => 'conversion',
            'status' => 'success',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'conversion_value' => $amount,
            'currency' => $currency,
            'request_data' => json_encode(['reference' => $reference]),
            'api_version' => $this->api_version,
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($table_name, $conversion_data);

        return rest_ensure_response([
            'success' => true,
            'conversion_id' => $wpdb->insert_id,
            'amount' => $amount,
            'currency' => $currency,
            'tracked_at' => current_time('c')
        ]);
    }

    /**
     * Track batch events
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function track_batch($request) {
        $events = $request->get_param('events');
        $api_key = $this->get_api_key($request);

        if (!is_array($events)) {
            return new WP_Error('invalid_batch', 'Events must be an array', ['status' => 400]);
        }

        $results = [];
        $domain = $this->get_domain_from_api_key($api_key);

        global $wpdb;
        $table_name = $this->db_manager->get_table_name('usage_tracking');

        foreach ($events as $event) {
            $tracking_data = [
                'domain_from' => $domain,
                'event_type' => $event['event_type'] ?? 'unknown',
                'status' => 'success',
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_data' => json_encode($event['data'] ?? []),
                'conversion_value' => $event['conversion_value'] ?? null,
                'currency' => $event['currency'] ?? 'GBP',
                'api_version' => $this->api_version,
                'created_at' => current_time('mysql')
            ];

            $insert_result = $wpdb->insert($table_name, $tracking_data);
            $results[] = [
                'success' => $insert_result !== false,
                'event_id' => $insert_result ? $wpdb->insert_id : null
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'processed' => count($events),
            'results' => $results,
            'tracked_at' => current_time('c')
        ]);
    }

    /**
     * Health check endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function health_check($request) {
        $checks = [
            'database' => $this->check_database_health(),
            'tables' => $this->check_tables_health(),
            'api' => true,
            'version' => $this->api_version
        ];

        $overall_health = !in_array(false, $checks, true);

        return rest_ensure_response([
            'healthy' => $overall_health,
            'checks' => $checks,
            'timestamp' => current_time('c'),
            'version' => AFFCD_VERSION ?? '1.0.0'
        ]);
    }

    /**
     * Get API version
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_version($request) {
        return rest_ensure_response([
            'api_version' => $this->api_version,
            'plugin_version' => AFFCD_VERSION ?? '1.0.0',
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        ]);
    }

    /**
     * Run diagnostics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function run_diagnostics($request) {
        $diagnostics = [
            'database_tables' => $this->db_manager->get_table_statistics(),
            'api_usage' => $this->get_api_usage_stats(),
            'security_status' => $this->get_security_status(),
            'performance_metrics' => $this->get_performance_metrics()
        ];

        return rest_ensure_response([
            'diagnostics' => $diagnostics,
            'generated_at' => current_time('c')
        ]);
    }

    // Helper Methods

    /**
     * Get domain from API key
     *
     * @param string $api_key API key
     * @return string Domain name
     */
    private function get_domain_from_api_key($api_key) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT domain_url FROM {$table_name} WHERE api_key = %s",
            $api_key
        )) ?: 'unknown';
    }

    /**
     * Get domain configuration
     *
     * @param string $api_key API key
     * @return array Configuration data
     */
    private function get_domain_configuration($api_key) {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');

        $domain = $wpdb->get_row($wpdb->prepare(
            "SELECT metadata, security_level, rate_limit_per_minute, rate_limit_per_hour FROM {$table_name} WHERE api_key = %s",
            $api_key
        ));

        if (!$domain) {
            return [];
        }

        $metadata = json_decode($domain->metadata, true) ?: [];
        
        return array_merge([
            'security_level' => $domain->security_level,
            'rate_limits' => [
                'per_minute' => $domain->rate_limit_per_minute,
                'per_hour' => $domain->rate_limit_per_hour
            ]
        ], $metadata['configuration'] ?? []);
    }

    /**
     * Check database health
     *
     * @return bool True if healthy
     */
    private function check_database_health() {
        global $wpdb;
        
        try {
            $wpdb->get_var("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check tables health
     *
     * @return bool True if all tables exist
     */
    private function check_tables_health() {
        foreach ($this->db_manager->get_all_table_names() as $table) {
            if (!$this->db_manager->table_exists($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get API usage statistics
     *
     * @return array Usage statistics
     */
    private function get_api_usage_stats() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('api_audit_logs');

        return $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(DISTINCT api_key) as unique_clients,
                AVG(processing_time_ms) as avg_response_time,
                COUNT(CASE WHEN response_code >= 400 THEN 1 END) as error_count
             FROM {$table_name} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get security status
     *
     * @return array Security metrics
     */
    private function get_security_status() {
        global $wpdb;
        $security_table = $this->db_manager->get_table_name('security_logs');
        $fraud_table = $this->db_manager->get_table_name('fraud_detection');

        $security_events = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$security_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $active_threats = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$fraud_table} WHERE is_active = 1"
        );

        return [
            'security_events_24h' => intval($security_events),
            'active_threats' => intval($active_threats),
            'status' => ($active_threats > 0) ? 'warning' : 'good'
        ];
    }

    /**
     * Get performance metrics
     *
     * @return array Performance data
     */
    private function get_performance_metrics() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('performance_metrics');

        return $wpdb->get_results(
            "SELECT metric_type, metric_name, AVG(metric_value) as avg_value 
             FROM {$table_name} 
             WHERE period_start >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
             GROUP BY metric_type, metric_name 
             ORDER BY metric_type, metric_name",
            ARRAY_A
        ) ?: [];
    }
}
