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
        if ($current_code === $found_code) {
            // Code already in session, skip reprocessing
            return;
        }

        // Validate code with API
        $validation_result = $this->api_client->validate_affiliate_code($found_code, [
            'source' => 'url_parameter',
            'parameter' => $parameter_used,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => wp_get_referer()
        ]);

        if (is_wp_error($validation_result)) {
            aci_log_activity('url_parameter_validation_failed', [
                'code' => $found_code,
                'error' => $validation_result->get_error_message()
            ]);
            return;
        }

        if (!$validation_result['valid']) {
            aci_log_activity('url_parameter_invalid_code', [
                'code' => $found_code,
                'message' => $validation_result['message'] ?? 'Invalid code'
            ]);
            return;
        }

        // Store affiliate data in session
        $this->session_manager->set_affiliate_data([
            'code' => $found_code,
            'affiliate_id' => $validation_result['affiliate_id'] ?? null,
            'parameter' => $parameter_used,
            'detected_at' => current_time('mysql'),
            'validation_data' => $validation_result
        ]);

        // Log successful detection
        aci_log_activity('affiliate_code_applied', [
            'code' => $found_code,
            'affiliate_id' => $validation_result['affiliate_id'] ?? null,
            'source' => 'url_parameter'
        ]);

        // Trigger action for other plugins
        do_action('aci_affiliate_code_detected', $found_code, $validation_result);

        // Auto-apply discount if enabled
        if ($this->should_auto_apply_discount()) {
            $this->auto_apply_discount($validation_result);
        }

        // Redirect to clean URL if configured
        if ($this->should_clean_url()) {
            $this->redirect_to_clean_url();
        }
    }

    /**
     * Track UTM parameters
     */
    private function track_utm_parameters() {
        $utm_params = [];
        
        foreach ($this->utm_parameters as $param) {
            if (isset($_GET[$param])) {
                $utm_params[$param] = sanitize_text_field($_GET[$param]);
            }
        }

        if (!empty($utm_params)) {
            $this->session_manager->set_utm_data($utm_params);
            
            aci_log_activity('utm_parameters_detected', [
                'utm_params' => $utm_params,
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
        }
    }

    /**
     * Track landing page
     */
    private function track_landing_page() {
        $landing_page = $this->session_manager->get_data('landing_page');
        
        // Only set if this is the first page view
        if (!$landing_page) {
            $this->session_manager->set_data('landing_page', [
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'title' => get_the_title(),
                'timestamp' => current_time('mysql'),
                'referrer' => wp_get_referer()
            ]);
        }
    }

    /**
     * Handle redirects if needed
     */
    private function handle_redirects() {
        // This method can be extended for custom redirect logic
        do_action('aci_url_processor_redirects');
    }

    /**
     * Should auto-apply discount
     */
    private function should_auto_apply_discount() {
        $settings = $this->plugin->get_settings();
        return !empty($settings['auto_apply_discount']);
    }

    /**
     * Auto-apply discount
     */
    private function auto_apply_discount($validation_result) {
        if (empty($validation_result['discount'])) {
            return;
        }

        // WooCommerce integration
        if (class_exists('WooCommerce')) {
            $this->apply_woocommerce_discount($validation_result['discount']);
        }

        // EDD integration
        if (class_exists('Easy_Digital_Downloads')) {
            $this->apply_edd_discount($validation_result['discount']);
        }

        // Custom discount application
        do_action('aci_auto_apply_discount', $validation_result);
    }

    /**
     * Apply WooCommerce discount
     */
    private function apply_woocommerce_discount($discount_data) {
        if (!WC()->session) {
            return;
        }

        WC()->session->set('aci_auto_discount', [
            'code' => $discount_data['code'] ?? '',
            'type' => $discount_data['type'] ?? 'percentage',
            'amount' => $discount_data['amount'] ?? 0,
            'applied_at' => current_time('mysql')
        ]);

        // Add notice
        wc_add_notice(
            sprintf(
                __('Discount code %s has been applied!', 'affiliate-client-integration'),
                '<strong>' . esc_html($discount_data['code']) . '</strong>'
            ),
            'success'
        );
    }

    /**
     * Apply EDD discount
     */
    private function apply_edd_discount($discount_data) {
        if (!function_exists('edd_set_cart_discount')) {
            return;
        }

        $discount_code = $discount_data['code'] ?? '';
        if ($discount_code) {
            edd_set_cart_discount($discount_code);
        }
    }

    /**
     * Should clean URL
     */
    private function should_clean_url() {
        $settings = $this->plugin->get_settings();
        return !empty($settings['clean_url_after_detection']);
    }

    /**
     * Redirect to clean URL
     */
    private function redirect_to_clean_url() {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $parsed_url = wp_parse_url($current_url);
        
        if (empty($parsed_url['query'])) {
            return;
        }

        parse_str($parsed_url['query'], $query_params);
        
        // Remove affiliate parameters
        foreach ($this->supported_parameters as $param) {
            unset($query_params[$param]);
        }

        // Build clean URL
        $clean_path = $parsed_url['path'] ?? '/';
        
        if (!empty($query_params)) {
            $clean_url = $clean_path . '?' . http_build_query($query_params);
        } else {
            $clean_url = $clean_path;
        }

        // Add fragment if exists
        if (!empty($parsed_url['fragment'])) {
            $clean_url .= '#' . $parsed_url['fragment'];
        }

        // Redirect
        wp_safe_redirect($clean_url, 301);
        exit;
    }

    /**
     * Get current affiliate code
     */
    public function get_current_affiliate_code() {
        return $this->session_manager->get_affiliate_data('code');
    }

    /**
     * Get current affiliate ID
     */
    public function get_current_affiliate_id() {
        return $this->session_manager->get_affiliate_data('affiliate_id');
    }

    /**
     * Get current affiliate data
     */
    public function get_current_affiliate_data() {
        return $this->session_manager->get_affiliate_data();
    }

    /**
     * Has active affiliate
     */
    public function has_active_affiliate() {
        return !empty($this->get_current_affiliate_code());
    }

    /**
     * Clear affiliate data
     */
    public function clear_affiliate_data() {
        $this->session_manager->clear_affiliate_data();
        
        aci_log_activity('affiliate_data_cleared', [
            'timestamp' => current_time('mysql')
        ]);

        do_action('aci_affiliate_data_cleared');
    }

    /**
     * Get UTM data
     */
    public function get_utm_data() {
        return $this->session_manager->get_utm_data();
    }

    /**
     * Get landing page data
     */
    public function get_landing_page_data() {
        return $this->session_manager->get_data('landing_page');
    }

    /**
     * Track conversion
     */
    public function track_conversion($order_id, $order_total = 0) {
        if (!$this->has_active_affiliate()) {
            return false;
        }

        $affiliate_data = $this->get_current_affiliate_data();
        
        $conversion_data = [
            'order_id' => $order_id,
            'order_total' => floatval($order_total),
            'affiliate_code' => $affiliate_data['code'] ?? '',
            'affiliate_id' => $affiliate_data['affiliate_id'] ?? null,
            'utm_data' => $this->get_utm_data(),
            'landing_page' => $this->get_landing_page_data(),
            'timestamp' => current_time('mysql')
        ];

        // Send to API
        $result = $this->api_client->track_conversion($conversion_data);

        if (!is_wp_error($result)) {
            aci_log_activity('conversion_tracked', [
                'order_id' => $order_id,
                'affiliate_code' => $affiliate_data['code'] ?? '',
                'order_total' => $order_total
            ]);

            do_action('aci_conversion_tracked', $conversion_data, $result);

            return true;
        }

        return false;
    }

    /**
     * Register AJAX handlers
     */
    public function register_ajax_handlers() {
        add_action('wp_ajax_aci_validate_url_code', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_nopriv_aci_validate_url_code', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_aci_clear_affiliate', [$this, 'ajax_clear_affiliate']);
        add_action('wp_ajax_nopriv_aci_clear_affiliate', [$this, 'ajax_clear_affiliate']);
    }

    /**
     * AJAX: Validate code
     */
    public function ajax_validate_code() {
        check_ajax_referer('aci_url_processor', 'nonce');

        $code = sanitize_text_field($_POST['code'] ?? '');

        if (empty($code)) {
            wp_send_json_error([
                'message' => __('Code is required.', 'affiliate-client-integration')
            ]);
        }

        $validation_result = $this->api_client->validate_affiliate_code($code);

        if (is_wp_error($validation_result)) {
            wp_send_json_error([
                'message' => $validation_result->get_error_message()
            ]);
        }

        if ($validation_result['valid']) {
            wp_send_json_success([
                'message' => __('Code is valid.', 'affiliate-client-integration'),
                'data' => $validation_result
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Invalid code.', 'affiliate-client-integration')
            ]);
        }
    }

    /**
     * AJAX: Clear affiliate
     */
    public function ajax_clear_affiliate() {
        check_ajax_referer('aci_url_processor', 'nonce');

        $this->clear_affiliate_data();

        wp_send_json_success([
            'message' => __('Affiliate data cleared.', 'affiliate-client-integration')
        ]);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'aci-url-processor',
            ACI_PLUGIN_URL . 'assets/js/url-processor.js',
            ['jquery'],
            ACI_VERSION,
            true
        );

        wp_localize_script('aci-url-processor', 'aciUrlProcessor', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aci_url_processor'),
            'hasAffiliate' => $this->has_active_affiliate(),
            'affiliateData' => $this->get_current_affiliate_data(),
            'supportedParams' => $this->supported_parameters,
            'strings' => [
                'codeApplied' => __('Affiliate code applied!', 'affiliate-client-integration'),
                'codeInvalid' => __('Invalid affiliate code.', 'affiliate-client-integration'),
                'error' => __('An error occurred.', 'affiliate-client-integration')
            ]
        ]);
    }

    /**
     * Get statistics
     */
    public function get_statistics($period = '30d') {
        global $wpdb;

        $date_filter = '';
        switch ($period) {
            case '24h':
                $date_filter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                break;
            case '7d':
                $date_filter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30d':
                $date_filter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_detections,
                COUNT(DISTINCT affiliate_code) as unique_codes,
                COUNT(DISTINCT session_id) as unique_sessions
             FROM {$wpdb->prefix}aci_activity_log
             WHERE activity_type = 'url_parameter_detected'
             {$date_filter}"
        );

        return $stats;
    }

    /**
     * Export detection data
     */
    public function export_detections($start_date = null, $end_date = null) {
        global $wpdb;

        $where_clauses = ["activity_type = 'url_parameter_detected'"];
        $where_values = [];

        if ($start_date) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $start_date;
        }

        if ($end_date) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $end_date;
        }

        $where_sql = implode(' AND ', $where_clauses);

        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aci_activity_log 
                 WHERE {$where_sql} 
                 ORDER BY created_at DESC",
                $where_values
            );
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}aci_activity_log 
                      WHERE {$where_sql} 
                      ORDER BY created_at DESC";
        }

        return $wpdb->get_results($query, ARRAY_A);
    }
}