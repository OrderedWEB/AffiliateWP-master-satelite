<?php
/**
 * URL Processor for Affiliate Client Integration
 * 
 * Path: /wp-content/plugins/affiliate-client-integration/includes/class-url-processor.php
 * Plugin: Affiliate Client Integration (Satellite)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_URL_Processor {

    /**
     * Plugin instance
     */
    private $plugin;

    /**
     * Session manager
     */
    private $session_manager;

    /**
     * API client
     */
    private $api_client;

    /**
     * Supported URL parameters
     */
    private $supported_parameters = [
        'affiliate_code',
        'ref',
        'code',
        'discount',
        'promo',
        'coupon',
        'aff',
        'affiliate',
        'partner'
    ];

    /**
     * UTM parameters to track
     */
    private $utm_parameters = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content'
    ];

    /**
     * Constructor
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->session_manager = $plugin->get_session_manager();
        $this->api_client = $plugin->get_api_client();
        
        // Allow custom parameters via filter
        $this->supported_parameters = apply_filters('aci_supported_url_parameters', $this->supported_parameters);
    }

    /**
     * Process current request
     */
    public function process_current_request() {
        // Only process on front-end
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // Process URL parameters
        $this->process_url_parameters();
        
        // Track UTM parameters
        $this->track_utm_parameters();
        
        // Track landing page
        $this->track_landing_page();
        
        // Handle redirects if needed
        $this->handle_redirects();
    }

    /**
     * Process affiliate URL parameters
     */
    public function process_url_parameters() {
        $found_code = null;
        $parameter_used = null;

        // Check each supported parameter
        foreach ($this->supported_parameters as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $found_code = sanitize_text_field($_GET[$param]);
                $parameter_used = $param;
                break;
            }
        }

        if (!$found_code) {
            return;
        }

        // Log parameter detection
        aci_log_activity('url_parameter_detected', [
            'parameter' => $parameter_used,
            'code' => $found_code,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'referer' => wp_get_referer()
        ]);

        // Check if we already have this code in session
        $current_code = $this->session_manager->get_affiliate_data('code');
        if ($current_code === $foun