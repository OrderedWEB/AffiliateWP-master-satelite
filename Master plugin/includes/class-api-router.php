<?php
/**
 * Class AFFCD_API_Router
 * Unifies external REST endpoints under affcd/v1 and delegates to existing handlers.
 *
 * Place in: master plugin -> includes/class-api-router.php
 */

if (!defined('ABSPATH')) { exit; }

class AFFCD_API_Router {
    /** @var string */
    private $namespace = 'affcd/v1';

    /** @var callable */
    private $integration_permission_cb;

    /** @var callable */
    private $admin_permission_cb;

    /** @var array */
    private $allowed_origins = [];

    public function __construct($args = []) {
        $this->integration_permission_cb = $args['integration_permission_cb'] ?? [ $this, 'validate_api_request' ];
        $this->admin_permission_cb       = $args['admin_permission_cb'] ?? [ $this, 'validate_admin_request' ];
        $this->allowed_origins           = $args['allowed_origins'] ?? [];
        add_action('rest_api_init', [ $this, 'register_routes' ]);
       add_filter('rest_pre_serve_request', [ $this, 'send_cors_headers' ], 5, 4);
    }

    /** Register unified routes */
    public function register_routes() {
        // Basic health
        register_rest_route($this->namespace, '/health', [
            'methods'  => 'GET',
            'callback' => [ $this, 'health' ],
            'permission_callback' => '__return_true'
        ]);

        // Config for satellites
        register_rest_route($this->namespace, '/config', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_client_config' ],
            'permission_callback' => $this->integration_permission_cb
        ]);

        // Track single events
        register_rest_route($this->namespace, '/track', [
            'methods'  => 'POST',
            'callback' => [ $this, 'ingest_track' ],
            'permission_callback' => $this->integration_permission_cb,
            'args' => []
        ]);

        // Convert (purchase)
        register_rest_route($this->namespace, '/convert', [
            'methods'  => 'POST',
            'callback' => [ $this, 'ingest_convert' ],
            'permission_callback' => $this->integration_permission_cb
        ]);

        // Batch ingest
        register_rest_route($this->namespace, '/batch', [
            'methods'  => 'POST',
            'callback' => [ $this, 'ingest_batch' ],
            'permission_callback' => $this->integration_permission_cb
        ]);

        // Code validation (delegates to existing endpoints if present)
        register_rest_route($this->namespace, '/validate-code', [
            'methods'  => 'POST',
            'callback' => [ $this, 'validate_code' ],
            'permission_callback' => $this->integration_permission_cb
        ]);

        // Webhook receiver example (master -> satellite or third parties)
        register_rest_route($this->namespace, '/webhook/referral-update', [
            'methods'  => 'POST',
            'callback' => [ $this, 'receive_referral_update' ],
            'permission_callback' => $this->integration_permission_cb
        ]);
    }

    /** ===== Delegate Callbacks ===== */

    public function health($request) {
        return new WP_REST_Response([ 'ok' => true, 'time' => time(), 'version' => (defined('AFFCD_VERSION')? AFFCD_VERSION : 'unknown') ], 200);
    }

    public function get_client_config($request) {
        if (class_exists('AFFCD_API_Endpoints') && method_exists('AFFCD_API_Endpoints', 'get_client_config')) {
            $api = new AFFCD_API_Endpoints();
            return $api->get_client_config($request);
        }
        return new WP_Error('not_implemented', 'Config endpoint not available', [ 'status' => 501 ]);
    }

    public function validate_code($request) {
        if (class_exists('AFFCD_API_Endpoints') && method_exists('AFFCD_API_Endpoints', 'validate_code')) {
            $api = new AFFCD_API_Endpoints();
            return $api->validate_code($request);
        }
        return new WP_Error('not_implemented', 'Validate code not available', [ 'status' => 501 ]);
    }

    public function ingest_track($request) {
        if (class_exists('SatelliteDataBackflowManager') && method_exists('SatelliteDataBackflowManager', 'receive_track_event')) {
            $mgr = new SatelliteDataBackflowManager();
            return $mgr->receive_track_event($request);
        }
        if (class_exists('AFFCD_Tracking_Sync') && method_exists('AFFCD_Tracking_Sync', 'receive_track_event')) {
            $sync = new AFFCD_Tracking_Sync(null);
            return $sync->receive_track_event($request);
        }
        return new WP_Error('not_implemented', 'Track ingest not available', [ 'status' => 501 ]);
    }

    public function ingest_convert($request) {
        if (class_exists('SatelliteDataBackflowManager') && method_exists('SatelliteDataBackflowManager', 'receive_conversion_event')) {
            $mgr = new SatelliteDataBackflowManager();
            return $mgr->receive_conversion_event($request);
        }
        if (class_exists('AFFCD_Tracking_Sync') && method_exists('AFFCD_Tracking_Sync', 'receive_conversion_event')) {
            $sync = new AFFCD_Tracking_Sync(null);
            return $sync->receive_conversion_event($request);
        }
        return new WP_Error('not_implemented', 'Convert ingest not available', [ 'status' => 501 ]);
    }

    public function ingest_batch($request) {
        if (class_exists('SatelliteDataBackflowManager') && method_exists('SatelliteDataBackflowManager', 'receive_batch')) {
            $mgr = new SatelliteDataBackflowManager();
            return $mgr->receive_batch($request);
        }
        if (class_exists('AFFCD_Tracking_Sync') && method_exists('AFFCD_Tracking_Sync', 'receive_batch')) {
            $sync = new AFFCD_Tracking_Sync(null);
            return $sync->receive_batch($request);
        }
        return new WP_Error('not_implemented', 'Batch ingest not available', [ 'status' => 501 ]);
    }

    public function receive_referral_update($request) {
        if (class_exists('AFFCD_Tracking_Sync') && method_exists('AFFCD_Tracking_Sync', 'receive_referral_update')) {
            $sync = new AFFCD_Tracking_Sync(null);
            return $sync->receive_referral_update($request);
        }
        return new WP_Error('not_implemented', 'Referral webhook not available', [ 'status' => 501 ]);
    }

    /** ===== Permissions & CORS ===== */

    public function validate_api_request($request) {
        // Example HMAC validation: body + shared secret per domain/site
        $signature = $request->get_header('X-AFFCD-Signature');
        $timestamp = intval($request->get_header('X-AFFCD-Timestamp'));
        if (!$signature || !$timestamp) { return false; }
        if (abs(time() - $timestamp) > 300) { return false; } // 5 min skew

        // Domain/site secret resolution (pseudo)
        $site_id = is_array($request->get_json_params()) ? ($request->get_json_params()['site_id'] ?? '') : '';
        $secret  = apply_filters('affcd_secret_for_site', '', $site_id);
        if (empty($secret)) { return false; }

        $body    = $request->get_body();
        $calc    = hash_hmac('sha256', $body, $secret);
        return hash_equals($calc, $signature);
    }

    public function validate_admin_request($request) {
        return current_user_can('manage_options');
    }

    public function send_cors_headers($served, $result, $request, $server) {
        if (empty($this->allowed_origins)) { return $served; }
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (in_array($origin, $this->allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-AFFCD-Signature, X-AFFCD-Timestamp');
        }
        return $served;
    }
}
