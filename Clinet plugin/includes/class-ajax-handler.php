<?php
/**
 * AJAX Handler for Affiliate Client Integration
 * 
 * Path: /wp-content/plugins/affiliate-client-integration/includes/class-ajax-handler.php
 * Plugin: Affiliate Client Integration (Satellite)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_AJAX_Handler {

    /**
     * Plugin instance
     */
    private $plugin;

    /**
     * API client
     */
    private $api_client;

    /**
     * Session manager
     */
    private $session_manager;

    /**
     * Price calculator
     */
    private $price_calculator;

    /**
     * Constructor
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->api_client = $plugin->get_api_client();
        $this->session_manager = $plugin->get_session_manager();
        $this->price_calculator = new ACI_Price_Calculator($plugin->get_settings());
        
        $this->register_ajax_hooks();
    }

    /**
     * Register AJAX hooks
     */
    private function register_ajax_hooks() {
        // Public AJAX actions (both logged in and logged out users)
        $public_actions = [
            'validate_code',
            'calculate_price',
            'track_event',
            'test_connection',
            'get_code_details',
            'apply_discount',
            'remove_discount',
            'health_check',
            'clear_session'
        ];

        foreach ($public_actions as $action) {
            add_action("wp_ajax_aci_{$action}", [$this, "ajax_{$action}"]);
            add_action("wp_ajax_nopriv_aci_{$action}", [$this, "ajax_{$action}"]);
        }

        // Admin-only AJAX actions
        $admin_actions = [
            'save_settings',
            'reset_settings',
            'export_data',
            'import_data',
            'clear_cache',
            'run_diagnostics'
        ];

        foreach ($admin_actions as $action) {
            add_action("wp_ajax_aci_{$action}", [$this, "ajax_{$action}"]);
        }
    }

    /**
     * Validate affiliate code via AJAX
     */
    public function ajax_validate_code() {
        $this->verify_ajax_request('aci_nonce');

        $code = Sanitise_text_field($_POST['code'] ?? '');
        $real_time = filter_var($_POST['real_time'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($code)) {
            wp_send_json_error([
                'message' => __('Please enter a valid affiliate code.', 'affiliate-client-integration'),
                'code' => 'EMPTY_CODE'
            ]);
        }

        // Rate limiting for real-time validation
        if ($real_time && !$this->check_rate_limit('validate_realtime', 10, 60)) {
            wp_send_json_error([
                'message' => __('Too many validation attempts. Please wait a moment.', 'affiliate-client-integration'),
                'code' => 'RATE_LIMITED'
            ]);
        }

        // Prepare additional data
        $additional_data = [
            'page_url' => esc_url_raw($_POST['current_url'] ?? ''),
            'page_title' => Sanitise_text_field($_POST['page_title'] ?? ''),
            'user_agent' => Sanitise_text_field($_POST['user_agent'] ?? ''),
            'referer' => wp_get_referer(),
            'user_id' => get_current_user_id(),
            'session_id' => $this->session_manager->get_session_id(),
            'real_time' => $real_time
        ];

        // Validate with API
        $validation_result = $this->api_client->validate_code($code, $additional_data);

        if (is_wp_error($validation_result)) {
            aci_log_activity('ajax_validation_error', [
                'code' => $code,
                'error' => $validation_result->get_error_message(),
                'real_time' => $real_time
            ]);

            wp_send_json_error([
                'message' => $validation_result->get_error_message(),
                'code' => $validation_result->get_error_code()
            ]);
        }

        // Store in session if not real-time
        if (!$real_time) {
            $this->session_manager->set_affiliate_data($validation_result);
        }

        // Log successful validation
        aci_log_activity('ajax_validation_success', [
            'code' => $code,
            'discount_type' => $validation_result['discount_type'] ?? '',
            'discount_amount' => $validation_result['discount_amount'] ?? 0,
            'real_time' => $real_time
        ]);

        wp_send_json_success($validation_result);
    }

    /**
     * Calculate price with discount via AJAX
     */
    public function ajax_calculate_price() {
        $this->verify_ajax_request('aci_nonce');

        $original_price = floatval($_POST['original_price'] ?? 0);
        $code = Sanitise_text_field($_POST['code'] ?? '');
        $currency = Sanitise_text_field($_POST['currency'] ?? 'USD');
        $product_id = Sanitise_text_field($_POST['product_id'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);

        if ($original_price <= 0) {
            wp_send_json_error([
                'message' => __('Invalid price specified.', 'affiliate-client-integration'),
                'code' => 'INVALID_PRICE'
            ]);
        }

        // Prepare calculation options
        $options = [
            'currency' => $currency,
            'product_id' => $product_id,
            'quantity' => max(1, $quantity),
            'apply_tax' => filter_var($_POST['apply_tax'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'tax_country' => Sanitise_text_field($_POST['tax_country'] ?? ''),
            'tax_state' => Sanitise_text_field($_POST['tax_state'] ?? ''),
            'include_shipping' => filter_var($_POST['include_shipping'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'shipping_amount' => floatval($_POST['shipping_amount'] ?? 0)
        ];

        // Calculate discounted price
        $calculation_result = $this->price_calculator->calculate_discounted_price(
            $original_price,
            $code,
            $options
        );

        if (is_wp_error($calculation_result)) {
            wp_send_json_error([
                'message' => $calculation_result->get_error_message(),
                'code' => $calculation_result->get_error_code()
            ]);
        }

        // Update session with cart data
        if ($calculation_result['discount_applied']) {
            $this->session_manager->update_cart_with_discount(
                $original_price * $quantity,
                $calculation_result['final_price'],
                $calculation_result['discount_amount']
            );
        }

        // Log calculation
        aci_log_activity('ajax_price_calculated', [
            'original_price' => $original_price,
            'final_price' => $calculation_result['final_price'],
            'discount_amount' => $calculation_result['discount_amount'],
            'code' => $code,
            'currency' => $currency
        ]);

        wp_send_json_success($calculation_result);
    }

    /**
     * Track event via AJAX
     */
    public function ajax_track_event() {
        $this->verify_ajax_request('aci_nonce');

        $event_type = Sanitise_text_field($_POST['event_type'] ?? '');
        $event_data = $_POST['event_data'] ?? [];

        if (empty($event_type)) {
            wp_send_json_error([
                'message' => __('Event type is required.', 'affiliate-client-integration'),
                'code' => 'MISSING_EVENT_TYPE'
            ]);
        }

        // Sanitise event data
        $Sanitised_data = [];
        if (is_array($event_data)) {
            foreach ($event_data as $key => $value) {
                $Sanitised_data[Sanitise_key($key)] = Sanitise_text_field($value);
            }
        }

        // Add context data
        $Sanitised_data['timestamp'] = Sanitise_text_field($_POST['timestamp'] ?? '');
        $Sanitised_data['url'] = esc_url_raw($_POST['url'] ?? '');
        $Sanitised_data['user_agent'] = Sanitise_text_field($_POST['user_agent'] ?? '');

        // Log the event
        aci_log_activity($event_type, $Sanitised_data);

        // Track with API if needed
        $code = $this->session_manager->get_affiliate_data('code');
        if (!empty($code) && in_array($event_type, ['impression', 'click', 'view'])) {
            $this->api_client->track_impression($code, $Sanitised_data);
        }

        wp_send_json_success(['message' => __('Event tracked successfully.', 'affiliate-client-integration')]);
    }

    /**
     * Test connection to master domain via AJAX
     */
    public function ajax_test_connection() {
        $this->verify_ajax_request('aci_nonce');

        $connection_test = $this->api_client->test_connection();

        if (is_wp_error($connection_test)) {
            wp_send_json_error([
                'message' => $connection_test->get_error_message(),
                'details' => $connection_test->get_error_data()
            ]);
        }

        wp_send_json_success($connection_test);
    }

    /**
     * Get affiliate code details via AJAX
     */
    public function ajax_get_code_details() {
        $this->verify_ajax_request('aci_nonce');

        $code = Sanitise_text_field($_POST['code'] ?? '');

        if (empty($code)) {
            wp_send_json_error([
                'message' => __('Code is required.', 'affiliate-client-integration'),
                'code' => 'MISSING_CODE'
            ]);
        }

        $code_details = $this->api_client->get_code_details($code);

        if (is_wp_error($code_details)) {
            wp_send_json_error([
                'message' => $code_details->get_error_message(),
                'code' => $code_details->get_error_code()
            ]);
        }

        wp_send_json_success($code_details);
    }

    /**
     * Apply discount via AJAX
     */
    public function ajax_apply_discount() {
        $this->verify_ajax_request('aci_nonce');

        $code = Sanitise_text_field($_POST['code'] ?? '');

        if (empty($code)) {
            wp_send_json_error([
                'message' => __('Code is required.', 'affiliate-client-integration'),
                'code' => 'MISSING_CODE'
            ]);
        }

        // Validate code first
        $validation_result = $this->api_client->validate_code($code);

        if (is_wp_error($validation_result)) {
            wp_send_json_error([
                'message' => $validation_result->get_error_message(),
                'code' => $validation_result->get_error_code()
            ]);
        }

        // Store in session
        $this->session_manager->set_affiliate_data($validation_result);

        // Log application
        aci_log_activity('discount_applied_ajax', [
            'code' => $code,
            'discount_amount' => $validation_result['discount_amount'] ?? 0
        ]);

        wp_send_json_success([
            'message' => __('Discount applied successfully!', 'affiliate-client-integration'),
            'data' => $validation_result
        ]);
    }

    /**
     * Remove discount via AJAX
     */
    public function ajax_remove_discount() {
        $this->verify_ajax_request('aci_nonce');

        // Clear affiliate data from session
        $this->session_manager->clear_affiliate_data();

        // Log removal
        aci_log_activity('discount_removed_ajax', []);

        wp_send_json_success([
            'message' => __('Discount removed successfully.', 'affiliate-client-integration')
        ]);
    }

    /**
     * Health check via AJAX
     */
    public function ajax_health_check() {
        $health_status = $this->plugin->perform_health_check();

        if ($health_status['overall_status'] === 'healthy') {
            wp_send_json_success($health_status);
        } else {
            wp_send_json_error($health_status);
        }
    }

    /**
     * Clear session via AJAX
     */
    public function ajax_clear_session() {
        $this->verify_ajax_request('aci_nonce');

        $this->session_manager->clear_all();

        wp_send_json_success([
            'message' => __('Session cleared successfully.', 'affiliate-client-integration')
        ]);
    }

    /**
     * Save settings via AJAX (Admin only)
     */
    public function ajax_save_settings() {
        $this->verify_ajax_request('aci_admin_nonce');
        $this->verify_admin_capability();

        $settings = $_POST['settings'] ?? [];

        if (!is_array($settings)) {
            wp_send_json_error([
                'message' => __('Invalid settings data.', 'affiliate-client-integration'),
                'code' => 'INVALID_SETTINGS'
            ]);
        }

        // Sanitise settings
        $Sanitised_settings = [];
        $allowed_settings = [
            'master_domain', 'api_key', 'api_secret', 'popup_enabled', 
            'popup_style', 'auto_apply_discount', 'track_conversions',
            'security_level', 'cache_duration', 'timeout', 'retry_attempts', 'debug_mode'
        ];

        foreach ($allowed_settings as $key) {
            if (isset($settings[$key])) {
                switch ($key) {
                    case 'master_domain':
                        $Sanitised_settings[$key] = esc_url_raw($settings[$key]);
                        break;
                    case 'popup_enabled':
                    case 'auto_apply_discount':
                    case 'track_conversions':
                    case 'debug_mode':
                        $Sanitised_settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'cache_duration':
                    case 'timeout':
                    case 'retry_attempts':
                        $Sanitised_settings[$key] = intval($settings[$key]);
                        break;
                    default:
                        $Sanitised_settings[$key] = Sanitise_text_field($settings[$key]);
                        break;
                }
            }
        }

        // Update settings
        $this->plugin->update_settings($Sanitised_settings);

        // Log settings update
        aci_log_activity('settings_updated', [
            'updated_by' => get_current_user_id(),
            'updated_fields' => array_keys($Sanitised_settings)
        ]);

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'affiliate-client-integration')
        ]);
    }

    /**
     * Reset settings via AJAX (Admin only)
     */
    public function ajax_reset_settings() {
        $this->verify_ajax_request('aci_admin_nonce');
        $this->verify_admin_capability();

        // Reset to default settings
        delete_option('aci_settings');

        // Log settings reset
        aci_log_activity('settings_reset', [
            'reset_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => __('Settings have been reset to defaults.', 'affiliate-client-integration')
        ]);
    }

    /**
     * Export data via AJAX (Admin only)
     */
    public function ajax_export_data() {
        $this->verify_ajax_request('aci_admin_nonce');
        $this->verify_admin_capability();

        $export_type = Sanitise_text_field($_POST['export_type'] ?? 'all');

        $export_data = [];

        switch ($export_type) {
            case 'settings':
                $export_data['settings'] = $this->plugin->get_settings();
                break;
                
            case 'logs':
                $export_data['logs'] = $this->get_activity_logs(1000);
                break;
                
            case 'session_data':
                $export_data['session_data'] = $this->session_manager->export_session_data();
                break;
                
            case 'all':
            default:
                $export_data['settings'] = $this->plugin->get_settings();
                $export_data['logs'] = $this->get_activity_logs(1000);
                $export_data['session_data'] = $this->session_manager->export_session_data();
                break;
        }

        $export_data['exported_at'] = current_time('mysql');
        $export_data['exported_by'] = get_current_user_id();
        $export_data['plugin_version'] = ACI_VERSION;
        $export_data['wordpress_version'] = get_bloginfo('version');

        // Log export
        aci_log_activity('data_exported', [
            'export_type' => $export_type,
            'exported_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'data' => $export_data,
            'filename' => 'aci-export-' . date('Y-m-d-H-i-s') . '.json'
        ]);
    }

    /**
     * Import data via AJAX (Admin only)
     */
    public function ajax_import_data() {
        $this->verify_ajax_request('aci_admin_nonce');
        $this->verify_admin_capability();

        $import_data = $_POST['import_data'] ?? '';

        if (empty($import_data)) {
            wp_send_json_error([
                'message' => __('Import data is required.', 'affiliate-client-integration'),
                'code' => 'MISSING_IMPORT_DATA'
            ]);
        }

        $data = json_decode(stripslashes($import_data), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                'message' => __('Invalid JSON data.', 'affiliate-client-integration'),
                'code' => 'INVALID_JSON'
            ]);
        }

        $imported_items = [];

        // Import settings
        if (isset($data['settings']) && is_array($data['settings'])) {
            $this->plugin->update_settings($data['settings']);
            $imported_items[] = 'settings';
        }

        // Log import
        aci_log_activity('data_imported', [
            'imported_items' => $imported_items,
            'imported_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => sprintf(__('Successfully imported: %s', 'affiliate-client-integration'), 
                               implode(', ', $imported_items)),
            'imported_items' => $imported_items
        ]);
    }

    /**
     * Clear cache via AJAX (Admin only)
     */
    public function ajax_clear_cache() {
        $this->verify_ajax_request('aci_admin_nonce');
        $this->verify_admin_capability();

        // Clear API client cache
        $this->api_client->clear_cache();

        // Clear WordPress object cache
        wp_cache_flush();

        // Clear any custom caches
        do_action('aci_clear_cache');

        // Log cache clear
        aci_log_activity('cache_cleared', [
            'cleared_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => __('Cache cleared successfully.', 'affiliate-client-integration')
        ]);
    }

    /**
     * Run diagnostics via AJAX (Admin only)
     */
    public function ajax_run_diagnostics() {
        $this->verify_ajax_request('aci_admin_nonce');
        $this->verify_admin_capability();

        $diagnostics = [];

        // PHP version check
        $diagnostics['php_version'] = [
            'value' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warning',
            'message' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 
                        'PHP version is compatible' : 
                        'PHP version should be 7.4 or higher'
        ];

        // WordPress version check
        $wp_version = get_bloginfo('version');
        $diagnostics['wordpress_version'] = [
            'value' => $wp_version,
            'status' => version_compare($wp_version, '5.0', '>=') ? 'ok' : 'warning',
            'message' => version_compare($wp_version, '5.0', '>=') ? 
                        'WordPress version is compatible' : 
                        'WordPress version should be 5.0 or higher'
        ];

        // cURL check
        $diagnostics['curl_available'] = [
            'value' => function_exists('curl_init'),
            'status' => function_exists('curl_init') ? 'ok' : 'error',
            'message' => function_exists('curl_init') ? 
                        'cURL is available' : 
                        'cURL is required but not available'
        ];

        // JSON check
        $diagnostics['json_available'] = [
            'value' => function_exists('json_encode'),
            'status' => function_exists('json_encode') ? 'ok' : 'error',
            'message' => function_exists('json_encode') ? 
                        'JSON functions are available' : 
                        'JSON functions are required but not available'
        ];

        // Settings check
        $settings = $this->plugin->get_settings();
        $diagnostics['configuration'] = [
            'value' => !empty($settings['master_domain']) && !empty($settings['api_key']),
            'status' => (!empty($settings['master_domain']) && !empty($settings['api_key'])) ? 'ok' : 'warning',
            'message' => (!empty($settings['master_domain']) && !empty($settings['api_key'])) ? 
                        'Plugin is properly configured' : 
                        'Master domain and API key are required'
        ];

        // Database check
        global $wpdb;
        $required_tables = [
            $wpdb->prefix . 'aci_cache',
            $wpdb->prefix . 'aci_sessions',
            $wpdb->prefix . 'aci_activity_log'
        ];

        $missing_tables = [];
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                $missing_tables[] = $table;
            }
        }

        $diagnostics['database'] = [
            'value' => empty($missing_tables),
            'status' => empty($missing_tables) ? 'ok' : 'error',
            'message' => empty($missing_tables) ? 
                        'All required tables exist' : 
                        'Missing tables: ' . implode(', ', $missing_tables)
        ];

        // API connectivity check
        $api_test = $this->api_client->test_connection();
        $diagnostics['api_connectivity'] = [
            'value' => !is_wp_error($api_test),
            'status' => !is_wp_error($api_test) ? 'ok' : 'error',
            'message' => !is_wp_error($api_test) ? 
                        'API connection successful' : 
                        'API connection failed: ' . $api_test->get_error_message()
        ];

        // Log diagnostics run
        aci_log_activity('diagnostics_run', [
            'run_by' => get_current_user_id(),
            'overall_status' => $this->get_overall_diagnostic_status($diagnostics)
        ]);

        wp_send_json_success([
            'diagnostics' => $diagnostics,
            'overall_status' => $this->get_overall_diagnostic_status($diagnostics)
        ]);
    }

    /**
     * Verify AJAX request with nonce
     */
    private function verify_ajax_request($nonce_key) {
        if (!check_ajax_referer($nonce_key, 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'affiliate-client-integration'),
                'code' => 'INVALID_NONCE'
            ]);
        }
    }

    /**
     * Verify admin capability
     */
    private function verify_admin_capability() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'affiliate-client-integration'),
                'code' => 'INSUFFICIENT_PERMISSIONS'
            ]);
        }
    }

    /**
     * Check rate limit for specific action
     */
    private function check_rate_limit($action, $limit, $window_seconds) {
        $transient_key = 'aci_rate_limit_' . $action . '_' . $this->get_client_identifier();
        $current_count = get_transient($transient_key) ?: 0;

        if ($current_count >= $limit) {
            return false;
        }

        set_transient($transient_key, $current_count + 1, $window_seconds);
        return true;
    }

    /**
     * Get client identifier for rate limiting
     */
    private function get_client_identifier() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_id = get_current_user_id();
        
        return $user_id ? "user_{$user_id}" : "ip_{$ip}";
    }

    /**
     * Get activity logs
     */
    private function get_activity_logs($limit = 100) {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'aci_activity_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }

    /**
     * Get overall diagnostic status
     */
    private function get_overall_diagnostic_status($diagnostics) {
        $error_count = 0;
        $warning_count = 0;
        
        foreach ($diagnostics as $check) {
            if ($check['status'] === 'error') {
                $error_count++;
            } elseif ($check['status'] === 'warning') {
                $warning_count++;
            }
        }
        
        if ($error_count > 0) {
            return 'error';
        } elseif ($warning_count > 0) {
            return 'warning';
        } else {
            return 'ok';
        }
    }
}