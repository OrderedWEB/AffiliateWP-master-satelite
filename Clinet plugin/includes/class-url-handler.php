<?php
/**
 * URL Handler for Affiliate Client Integration
 * 
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-url-handler.php
 * 
 * Handles URL parameter detection, processing, and affiliate code management
 * with session persistence and comprehensive tracking capabilities.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_URL_Handler {

    private $api_handler;
    private $settings;
    private $processed_parameters = [];
    private $active_affiliate_data = [];
    
    // URL parameter names to watch for
    private $parameter_names = [
        'affiliate', 'aff', 'ref', 'referral', 'partner', 'promo', 'code', 'discount'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_handler = new ACI_API_Handler();
        $this->settings = get_option('aci_settings', []);
        
        // Load custom parameter names
        $custom_params = $this->settings['url_parameters'] ?? [];
        if (!empty($custom_params)) {
            $this->parameter_names = array_merge($this->parameter_names, $custom_params);
        }
        
        // Initialize hooks
        add_action('init', [$this, 'init'], 1);
        add_action('wp', [$this, 'process_url_parameters']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_aci_process_url_affiliate', [$this, 'ajax_process_affiliate']);
        add_action('wp_ajax_nopriv_aci_process_url_affiliate', [$this, 'ajax_process_affiliate']);
        
        // WooCommerce integration
        add_action('woocommerce_checkout_order_processed', [$this, 'track_woocommerce_conversion'], 10, 2);
        add_filter('woocommerce_cart_totals_before_order_total', [$this, 'display_affiliate_discount']);
        
        // Shortcode support
        add_shortcode('affiliate_url_parameter', [$this, 'url_parameter_shortcode']);
        add_shortcode('affiliate_discount_info', [$this, 'discount_info_shortcode']);
    }

    /**
     * Initialize URL handler
     */
    public function init() {
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }
        
        // Clean up old sessions
        $this->cleanup_old_sessions();
        
        // Load active affiliate data from session
        $this->load_session_data();
    }

    /**
     * Process URL parameters
     */
    public function process_url_parameters() {
        // Check if we have any affiliate parameters in the URL
        $detected_parameters = $this->detect_affiliate_parameters();
        
        if (empty($detected_parameters)) {
            return;
        }

        // Process each detected parameter
        foreach ($detected_parameters as $param_name => $param_value) {
            $this->process_single_parameter($param_name, $param_value);
        }

        // Set referrer information
        $this->set_referrer_data();
        
        // Track the URL parameter detection
        $this->track_url_parameter_event($detected_parameters);
    }

    /**
     * Detect affiliate parameters in URL
     */
    private function detect_affiliate_parameters() {
        $detected = [];
        
        foreach ($this->parameter_names as $param_name) {
            if (isset($_GET[$param_name]) && !empty($_GET[$param_name])) {
                $param_value = sanitize_text_field($_GET[$param_name]);
                
                // Basic validation
                if ($this->validate_parameter_value($param_value)) {
                    $detected[$param_name] = $param_value;
                }
            }
        }
        
        return $detected;
    }

    /**
     * Process single URL parameter
     */
    private function process_single_parameter($param_name, $param_value) {
        // Check if this parameter was already processed in this session
        if (isset($_SESSION['aci_processed_params'][$param_name][$param_value])) {
            return;
        }

        // Validate affiliate code with master domain
        $validation_result = $this->api_handler->validate_affiliate_code($param_value, [
            'source' => 'url_parameter',
            'parameter_name' => $param_name,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        if (is_wp_error($validation_result)) {
            $this->log_parameter_error($param_name, $param_value, $validation_result);
            return;
        }

        if (!$validation_result['valid']) {
            $this->log_invalid_parameter($param_name, $param_value, $validation_result);
            return;
        }

        // Store valid affiliate data
        $this->store_affiliate_data($validation_result, $param_name);
        
        // Mark parameter as processed
        $_SESSION['aci_processed_params'][$param_name][$param_value] = time();
        
        // Apply any automatic discounts
        $this->maybe_apply_discount($validation_result);
        
        // Trigger affiliate detected action
        do_action('aci_affiliate_detected', $validation_result, $param_name, $param_value);
    }

    /**
     * Validate parameter value
     */
    private function validate_parameter_value($value) {
        // Basic validation rules
        if (strlen($value) < 2 || strlen($value) > 100) {
            return false;
        }

        // Check for suspicious patterns
        $suspicious_patterns = [
            '/[<>"\']/',  // HTML/script tags
            '/\bjavascript\b/i',
            '/\bvbscript\b/i',
            '/\bon\w+\s*=/i'  // Event handlers
        ];

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Store affiliate data in session and cookies
     */
    private function store_affiliate_data($validation_result, $param_name) {
        $affiliate_data = [
            'affiliate_id' => $validation_result['affiliate_id'],
            'affiliate_code' => $validation_result['affiliate_code'],
            'vanity_code' => $validation_result['vanity_code'],
            'parameter_name' => $param_name,
            'detected_at' => time(),
            'page_url' => home_url($_SERVER['REQUEST_URI']),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_ip' => $this->get_client_ip(),
            'session_id' => session_id()
        ];

        // Store in session
        $_SESSION['aci_affiliate_data'] = $affiliate_data;
        $this->active_affiliate_data = $affiliate_data;

        // Store in persistent cookie (30 days)
        $cookie_data = base64_encode(json_encode([
            'affiliate_id' => $affiliate_data['affiliate_id'],
            'affiliate_code' => $affiliate_data['affiliate_code'],
            'detected_at' => $affiliate_data['detected_at']
        ]));

        setcookie(
            'aci_affiliate',
            $cookie_data,
            time() + (30 * DAY_IN_SECONDS),
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // HTTP only
        );

        // Store in database for analytics
        $this->store_affiliate_tracking($affiliate_data);
    }

    /**
     * Store affiliate tracking data
     */
    private function store_affiliate_tracking($affiliate_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_affiliate_tracking';
        
        // Create table if it doesn't exist
        $this->maybe_create_tracking_table();
        
        $wpdb->insert(
            $table_name,
            [
                'affiliate_id' => $affiliate_data['affiliate_id'],
                'affiliate_code' => $affiliate_data['affiliate_code'],
                'vanity_code' => $affiliate_data['vanity_code'],
                'parameter_name' => $affiliate_data['parameter_name'],
                'page_url' => $affiliate_data['page_url'],
                'referrer' => $affiliate_data['referrer'],
                'user_ip' => $affiliate_data['user_ip'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_id' => $affiliate_data['session_id'],
                'utm_source' => $_GET['utm_source'] ?? null,
                'utm_medium' => $_GET['utm_medium'] ?? null,
                'utm_campaign' => $_GET['utm_campaign'] ?? null,
                'utm_term' => $_GET['utm_term'] ?? null,
                'utm_content' => $_GET['utm_content'] ?? null,
                'device_type' => $this->detect_device_type(),
                'browser' => $this->detect_browser(),
                'operating_system' => $this->detect_operating_system(),
                'tracked_at' => current_time('mysql')
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );
    }

    /**
     * Maybe create tracking table
     */
    private function maybe_create_tracking_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_affiliate_tracking';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                affiliate_id bigint(20) unsigned NOT NULL,
                affiliate_code varchar(255) NOT NULL,
                vanity_code varchar(255),
                parameter_name varchar(50) NOT NULL,
                page_url varchar(500) NOT NULL,
                referrer varchar(500),
                user_ip varchar(45) NOT NULL,
                user_agent text,
                session_id varchar(100),
                utm_source varchar(100),
                utm_medium varchar(100),
                utm_campaign varchar(100),
                utm_term varchar(100),
                utm_content varchar(100),
                device_type varchar(20),
                browser varchar(100),
                operating_system varchar(100),
                converted tinyint(1) DEFAULT 0,
                conversion_value decimal(10,2) DEFAULT 0.00,
                tracked_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY affiliate_id (affiliate_id),
                KEY session_id (session_id),
                KEY tracked_at (tracked_at),
                KEY converted (converted),
                KEY user_ip (user_ip)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Maybe apply discount
     */
    private function maybe_apply_discount($validation_result) {
        $discount_settings = $this->settings['auto_discount'] ?? [];
        
        if (empty($discount_settings['enabled'])) {
            return;
        }

        // Apply WooCommerce discount
        if (class_exists('WooCommerce')) {
            $this->apply_woocommerce_discount($validation_result, $discount_settings);
        }

        // Apply custom discount logic
        do_action('aci_apply_affiliate_discount', $validation_result, $discount_settings);
    }

    /**
     * Apply WooCommerce discount
     */
    private function apply_woocommerce_discount($validation_result, $discount_settings) {
        if (!WC()->session) {
            return;
        }

        $discount_data = [
            'type' => $discount_settings['type'] ?? 'percentage',
            'amount' => $discount_settings['amount'] ?? 10,
            'affiliate_id' => $validation_result['affiliate_id'],
            'affiliate_code' => $validation_result['affiliate_code']
        ];

        WC()->session->set('aci_affiliate_discount', $discount_data);
        
        // Add notice to user
        $discount_text = $discount_data['type'] === 'percentage' ? 
            $discount_data['amount'] . '%' : 
            '$' . $discount_data['amount'];
            
        wc_add_notice(
            sprintf(
                __('Affiliate discount applied: %s off your order!', 'affiliate-client-integration'),
                $discount_text
            ),
            'success'
        );
    }

    /**
     * Set referrer data
     */
    private function set_referrer_data() {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $_SESSION['aci_referrer_data'] = [
                'referrer' => $_SERVER['HTTP_REFERER'],
                'referrer_domain' => parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST),
                'landing_page' => home_url($_SERVER['REQUEST_URI']),
                'timestamp' => time()
            ];
        }
    }

    /**
     * Track URL parameter event
     */
    private function track_url_parameter_event($detected_parameters) {
        $tracking_data = [
            'event_type' => 'url_parameter_detected',
            'parameters' => $detected_parameters,
            'page_url' => home_url($_SERVER['REQUEST_URI']),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        ];

        // Store locally
        $this->store_event_tracking($tracking_data);
        
        // Send to master domain
        $this->send_event_to_master($tracking_data);
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!$this->has_active_affiliate()) {
            return;
        }

        wp_enqueue_script(
            'aci-url-handler',
            ACI_PLUGIN_URL . 'assets/js/affiliate-url-processor.js',
            ['jquery'],
            ACI_VERSION,
            true
        );

        wp_localize_script('aci-url-handler', 'aciUrlHandler', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aci_url_nonce'),
            'affiliateData' => $this->get_active_affiliate_data(),
            'settings' => $this->get_frontend_settings()
        ]);
    }

    /**
     * Get active affiliate data
     */
    public function get_active_affiliate_data() {
        return $this->active_affiliate_data;
    }

    /**
     * Check if has active affiliate
     */
    public function has_active_affiliate() {
        return !empty($this->active_affiliate_data);
    }

    /**
     * Load session data
     */
    private function load_session_data() {
        // Load from session
        if (!empty($_SESSION['aci_affiliate_data'])) {
            $this->active_affiliate_data = $_SESSION['aci_affiliate_data'];
            return;
        }

        // Load from cookie as fallback
        if (!empty($_COOKIE['aci_affiliate'])) {
            $cookie_data = json_decode(base64_decode($_COOKIE['aci_affiliate']), true);
            
            if ($cookie_data && is_array($cookie_data)) {
                // Validate cookie data is not too old (30 days)
                if (isset($cookie_data['detected_at']) && 
                    (time() - $cookie_data['detected_at']) < (30 * DAY_IN_SECONDS)) {
                    
                    // Restore basic affiliate data
                    $this->active_affiliate_data = [
                        'affiliate_id' => $cookie_data['affiliate_id'],
                        'affiliate_code' => $cookie_data['affiliate_code'],
                        'detected_at' => $cookie_data['detected_at'],
                        'source' => 'cookie'
                    ];
                    
                    // Update session
                    $_SESSION['aci_affiliate_data'] = $this->active_affiliate_data;
                }
            }
        }
    }

    /**
     * Track WooCommerce conversion
     */
    public function track_woocommerce_conversion($order_id, $posted_data) {
        if (!$this->has_active_affiliate()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $conversion_data = [
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'order_currency' => $order->get_currency(),
            'customer_email' => $order->get_billing_email(),
            'customer_id' => $order->get_customer_id(),
            'affiliate_data' => $this->active_affiliate_data,
            'conversion_type' => 'woocommerce_order'
        ];

        // Track conversion locally
        $this->store_conversion_tracking($conversion_data);
        
        // Send conversion to master domain
        $this->api_handler->track_conversion($this->active_affiliate_data, $conversion_data);
        
        // Clear affiliate data after conversion
        $this->clear_affiliate_data();
    }

    /**
     * Store conversion tracking
     */
    private function store_conversion_tracking($conversion_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        
        // Create table if it doesn't exist
        $this->maybe_create_conversions_table();
        
        $wpdb->insert(
            $table_name,
            [
                'affiliate_id' => $this->active_affiliate_data['affiliate_id'],
                'affiliate_code' => $this->active_affiliate_data['affiliate_code'],
                'order_id' => $conversion_data['order_id'],
                'order_total' => $conversion_data['order_total'],
                'order_currency' => $conversion_data['order_currency'],
                'customer_email' => $conversion_data['customer_email'],
                'customer_id' => $conversion_data['customer_id'] ?: 0,
                'conversion_type' => $conversion_data['conversion_type'],
                'session_id' => session_id(),
                'user_ip' => $this->get_client_ip(),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        // Update tracking record as converted
        $wpdb->update(
            $wpdb->prefix . 'aci_affiliate_tracking',
            [
                'converted' => 1,
                'conversion_value' => $conversion_data['order_total']
            ],
            [
                'session_id' => session_id(),
                'affiliate_id' => $this->active_affiliate_data['affiliate_id']
            ],
            ['%d', '%f'],
            ['%s', '%d']
        );
    }

    /**
     * Maybe create conversions table
     */
    private function maybe_create_conversions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                affiliate_id bigint(20) unsigned NOT NULL,
                affiliate_code varchar(255) NOT NULL,
                order_id bigint(20) unsigned NOT NULL,
                order_total decimal(10,2) NOT NULL,
                order_currency varchar(3) DEFAULT 'USD',
                customer_email varchar(255),
                customer_id bigint(20) unsigned DEFAULT 0,
                conversion_type varchar(50) DEFAULT 'purchase',
                session_id varchar(100),
                user_ip varchar(45),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY affiliate_id (affiliate_id),
                KEY order_id (order_id),
                KEY customer_id (customer_id),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Display affiliate discount in WooCommerce cart
     */
    public function display_affiliate_discount() {
        if (!WC()->session || !$this->has_active_affiliate()) {
            return;
        }

        $discount_data = WC()->session->get('aci_affiliate_discount');
        if (!$discount_data) {
            return;
        }

        $discount_text = $discount_data['type'] === 'percentage' ? 
            $discount_data['amount'] . '%' : 
            wc_price($discount_data['amount']);

        ?>
        <tr class="aci-affiliate-discount">
            <th><?php _e('Affiliate Discount', 'affiliate-client-integration'); ?></th>
            <td><?php echo $discount_text; ?> 
                <small>(<?php echo esc_html($discount_data['affiliate_code']); ?>)</small>
            </td>
        </tr>
        <?php
    }

    /**
     * URL parameter shortcode
     */
    public function url_parameter_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'parameter' => 'affiliate',
            'show_if_empty' => 'false',
            'wrapper' => 'span',
            'class' => 'aci-url-parameter'
        ], $atts);

        $param_value = $_GET[$atts['parameter']] ?? '';
        
        if (empty($param_value) && $atts['show_if_empty'] === 'false') {
            return '';
        }

        $display_value = $param_value ?: $content;
        
        return sprintf(
            '<%s class="%s">%s</%s>',
            esc_attr($atts['wrapper']),
            esc_attr($atts['class']),
            esc_html($display_value),
            esc_attr($atts['wrapper'])
        );
    }

    /**
     * Discount info shortcode
     */
    public function discount_info_shortcode($atts, $content = '') {
        if (!$this->has_active_affiliate()) {
            return '';
        }

        $atts = shortcode_atts([
            'show' => 'code', // code, discount, both
            'format' => 'text', // text, badge, card
            'class' => 'aci-discount-info'
        ], $atts);

        $affiliate_data = $this->get_active_affiliate_data();
        $discount_data = $this->get_active_discount_data();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?> aci-format-<?php echo esc_attr($atts['format']); ?>">
            <?php if ($atts['show'] === 'code' || $atts['show'] === 'both'): ?>
                <span class="aci-affiliate-code"><?php echo esc_html($affiliate_data['affiliate_code']); ?></span>
            <?php endif; ?>
            
            <?php if ($discount_data && ($atts['show'] === 'discount' || $atts['show'] === 'both')): ?>
                <span class="aci-discount-amount">
                    <?php echo $discount_data['type'] === 'percentage' ? 
                        $discount_data['amount'] . '% off' : 
                        '$' . $discount_data['amount'] . ' off'; ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get active discount data
     */
    private function get_active_discount_data() {
        if (class_exists('WooCommerce') && WC()->session) {
            return WC()->session->get('aci_affiliate_discount');
        }
        return null;
    }

    /**
     * AJAX: Process affiliate code
     */
    public function ajax_process_affiliate() {
        check_ajax_referer('aci_url_nonce', 'nonce');
        
        $affiliate_code = sanitize_text_field($_POST['affiliate_code'] ?? '');
        
        if (empty($affiliate_code)) {
            wp_send_json_error(__('Affiliate code is required.', 'affiliate-client-integration'));
        }

        // Process as if it came from URL parameter
        $this->process_single_parameter('ajax_submission', $affiliate_code);
        
        if ($this->has_active_affiliate()) {
            wp_send_json_success([
                'message' => __('Affiliate code processed successfully.', 'affiliate-client-integration'),
                'affiliate_data' => $this->get_active_affiliate_data()
            ]);
        } else {
            wp_send_json_error(__('Invalid affiliate code.', 'affiliate-client-integration'));
        }
    }

    /**
     * Clear affiliate data
     */
    public function clear_affiliate_data() {
        unset($_SESSION['aci_affiliate_data']);
        $this->active_affiliate_data = [];
        
        // Clear cookie
        setcookie('aci_affiliate', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }

    /**
     * Cleanup old sessions
     */
    private function cleanup_old_sessions() {
        // Clean up processed parameters older than 1 hour
        if (isset($_SESSION['aci_processed_params'])) {
            $cutoff_time = time() - 3600;
            
            foreach ($_SESSION['aci_processed_params'] as $param_name => &$param_values) {
                foreach ($param_values as $value => $timestamp) {
                    if ($timestamp < $cutoff_time) {
                        unset($param_values[$value]);
                    }
                }
                
                if (empty($param_values)) {
                    unset($_SESSION['aci_processed_params'][$param_name]);
                }
            }
        }
    }

    /**
     * Get frontend settings
     */
    private function get_frontend_settings() {
        return [
            'track_interactions' => $this->settings['track_interactions'] ?? true,
            'auto_discount' => $this->settings['auto_discount']['enabled'] ?? false,
            'show_notifications' => $this->settings['show_notifications'] ?? true
        ];
    }

    /**
     * Utility functions
     */
    
    private function get_client_ip() {
        $ip_headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
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

    private function detect_device_type() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/tablet|ipad/i', $user_agent)) {
            return 'tablet';
        }
        
        if (preg_match('/mobile|android|iphone/i', $user_agent)) {
            return 'mobile';
        }
        
        return 'desktop';
    }

    private function detect_browser() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browsers = [
            'Chrome' => '/chrome/i',
            'Firefox' => '/firefox/i',
            'Safari' => '/safari/i',
            'Edge' => '/edge/i',
            'Opera' => '/opera/i',
            'Internet Explorer' => '/msie|trident/i'
        ];
        
        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return $browser;
            }
        }
        
        return 'Unknown';
    }

    private function detect_operating_system() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $os_list = [
            'Windows' => '/windows/i',
            'macOS' => '/macintosh|mac os x/i',
            'Linux' => '/linux/i',
            'Android' => '/android/i',
            'iOS' => '/iphone|ipad|ipod/i'
        ];