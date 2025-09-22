<?php
/**
 * Discount Pricing Integration Class
 *
 * Handles complete discount pricing system including fetching discount values
 * from master site, dynamic price updates, and success tracking.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFILIATE_CLIENT_Discount_Pricing_Integration {

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
     * Obfuscated field names for security
     *
     * @var array
     */
    private $obfuscated_fields;

    /**
     * Constructor
     *
     * @param array $config Plugin configuration
     * @param AFFILIATE_CLIENT_API_Client $api_client API client instance
     */
    public function __construct($config, $api_client) {
        $this->config = $config;
        $this->api_client = $api_client;
        $this->init_obfuscated_fields();
    }

    /**
     * Initialse the integration
     */
    public function init() {
        // Shortcodes
        add_shortcode('affiliate_dynamic_price', [$this, 'dynamic_price_shortcode']);
        add_shortcode('affiliate_success_tracker', [$this, 'success_tracker_shortcode']);
        add_shortcode('affiliate_discount_display', [$this, 'discount_display_shortcode']);
        
        // Gutenberg blocks
        add_action('init', [$this, 'register_pricing_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_assets']);
        
        // Frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_pricing_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_affiliate_get_discount_data', [$this, 'ajax_get_discount_data']);
        add_action('wp_ajax_nopriv_affiliate_get_discount_data', [$this, 'ajax_get_discount_data']);
        add_action('wp_ajax_affiliate_track_success', [$this, 'ajax_track_success']);
        add_action('wp_ajax_nopriv_affiliate_track_success', [$this, 'ajax_track_success']);
        
        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_pricing_endpoints']);
        
        // Hook into Zoho integration to add discount fields
        add_filter('affiliate_client_zoho_tracking_params', [$this, 'add_discount_params'], 10, 2);
    }

    /**
     * Initialse obfuscated field names for security
     */
    private function init_obfuscated_fields() {
        $this->obfuscated_fields = [
            'discount_type' => 'x7k2m',    // percentage or fixed
            'discount_value' => 'h9p1n',   // the actual discount amount/percentage
            'original_price' => 'z3f8q',   // original price before discount
            'discounted_price' => 'w5j6r', // final price after discount
            'currency' => 'l4t9x',         // currency code
            'discount_code' => 'v2b7s',    // the discount code used
            'expires' => 'n8m3k',          // expiration timestamp
            'signature' => 'q1r5p'         // security signature
        ];
    }

    /**
     * Dynamic price shortcode
     * 
     * [affiliate_dynamic_price base_price="100" currency="EUR" service_id="premium"]
     */
    public function dynamic_price_shortcode($atts) {
        $atts = shortcode_atts([
            'base_price' => '0',
            'currency' => 'EUR',
            'service_id' => '',
            'display_original' => 'true',
            'show_savings' => 'true',
            'format' => 'standard', // standard, minimal, detailed
            'class' => '',
            'animate' => 'true',
        ], $atts, 'affiliate_dynamic_price');

        $base_price = floatval($atts['base_price']);
        $currency = strtoupper($atts['currency']);
        $widget_id = 'price-widget-' . uniqid();

        return $this->render_price_widget($widget_id, $base_price, $currency, $atts);
    }

    /**
     * Success tracker shortcode
     * 
     * [affiliate_success_tracker]
     */
    public function success_tracker_shortcode($atts) {
        $atts = shortcode_atts([
            'auto_track' => 'true',
            'show_message' => 'true',
            'redirect_delay' => '5',
            'class' => '',
        ], $atts, 'affiliate_success_tracker');

        $tracker_id = 'success-tracker-' . uniqid();
        return $this->render_success_tracker($tracker_id, $atts);
    }

    /**
     * Discount display shortcode
     * 
     * [affiliate_discount_display show_code="true" show_amount="true"]
     */
    public function discount_display_shortcode($atts) {
        $atts = shortcode_atts([
            'show_code' => 'true',
            'show_amount' => 'true',
            'show_type' => 'false',
            'show_expires' => 'true',
            'format' => 'badge', // badge, inline, card
            'class' => '',
        ], $atts, 'affiliate_discount_display');

        $display_id = 'discount-display-' . uniqid();
        return $this->render_discount_display($display_id, $atts);
    }

    /**
     * Render dynamic price widget
     */
    private function render_price_widget($widget_id, $base_price, $currency, $atts) {
        ob_start();
        ?>
        <div id="<?php echo esc_attr($widget_id); ?>" 
             class="affiliate-price-widget <?php echo esc_attr($atts['class']); ?>"
             data-base-price="<?php echo esc_attr($base_price); ?>"
             data-currency="<?php echo esc_attr($currency); ?>"
             data-service-id="<?php echo esc_attr($atts['service_id']); ?>"
             data-display-original="<?php echo esc_attr($atts['display_original']); ?>"
             data-show-savings="<?php echo esc_attr($atts['show_savings']); ?>"
             data-format="<?php echo esc_attr($atts['format']); ?>"
             data-animate="<?php echo esc_attr($atts['animate']); ?>">
            
            <div class="price-container">
                <?php if ($atts['display_original'] === 'true'): ?>
                <div class="original-price" style="display: none;">
                    <span class="price-label"><?php _e('Regular Price:', 'affiliate-client-full'); ?></span>
                    <span class="price-amount"><?php echo $this->format_price($base_price, $currency); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="current-price">
                    <span class="price-label"><?php _e('Your Price:', 'affiliate-client-full'); ?></span>
                    <span class="price-amount loading"><?php echo $this->format_price($base_price, $currency); ?></span>
                </div>
                
                <?php if ($atts['show_savings'] === 'true'): ?>
                <div class="savings-amount" style="display: none;">
                    <span class="savings-label"><?php _e('You Save:', 'affiliate-client-full'); ?></span>
                    <span class="savings-value"></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="price-loader" style="display: none;">
                <span class="loader-text"><?php _e('Calculating discount...', 'affiliate-client-full'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render success tracker
     */
    private function render_success_tracker($tracker_id, $atts) {
        ob_start();
        ?>
        <div id="<?php echo esc_attr($tracker_id); ?>" 
             class="affiliate-success-tracker <?php echo esc_attr($atts['class']); ?>"
             data-auto-track="<?php echo esc_attr($atts['auto_track']); ?>"
             data-show-message="<?php echo esc_attr($atts['show_message']); ?>"
             data-redirect-delay="<?php echo esc_attr($atts['redirect_delay']); ?>"
             style="display: none;">
            
            <?php if ($atts['show_message'] === 'true'): ?>
            <div class="success-message">
                <div class="success-icon">✅</div>
                <h3><?php _e('Order Confirmed!', 'affiliate-client-full'); ?></h3>
                <p><?php _e('Thank you for your purchase. Your order has been processed successfully.', 'affiliate-client-full'); ?></p>
                <div class="order-details"></div>
            </div>
            <?php endif; ?>
            
            <div class="tracking-status">
                <span class="status-text"><?php _e('Processing...', 'affiliate-client-full'); ?></span>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof AffiliateClientPricing !== 'undefined') {
                AffiliateClientPricing.initSuccessTracker('<?php echo esc_js($tracker_id); ?>');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render discount display
     */
    private function render_discount_display($display_id, $atts) {
        ob_start();
        ?>
        <div id="<?php echo esc_attr($display_id); ?>" 
             class="affiliate-discount-display format-<?php echo esc_attr($atts['format']); ?> <?php echo esc_attr($atts['class']); ?>"
             data-show-code="<?php echo esc_attr($atts['show_code']); ?>"
             data-show-amount="<?php echo esc_attr($atts['show_amount']); ?>"
             data-show-type="<?php echo esc_attr($atts['show_type']); ?>"
             data-show-expires="<?php echo esc_attr($atts['show_expires']); ?>">
            
            <div class="discount-content">
                <div class="discount-icon">🎯</div>
                <div class="discount-details">
                    <div class="discount-text"><?php _e('Discount Applied', 'affiliate-client-full'); ?></div>
                    <div class="discount-info"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Register Gutenberg blocks
     */
    public function register_pricing_blocks() {
        // Dynamic Price Block
        register_block_type('affiliate-client/dynamic-price', [
            'editor_script' => 'affiliate-client-pricing-blocks',
            'render_callback' => [$this, 'render_dynamic_price_block'],
            'attributes' => [
                'basePrice' => ['type' => 'number', 'default' => 0],
                'currency' => ['type' => 'string', 'default' => 'EUR'],
                'serviceId' => ['type' => 'string', 'default' => ''],
                'displayOriginal' => ['type' => 'boolean', 'default' => true],
                'showSavings' => ['type' => 'boolean', 'default' => true],
                'format' => ['type' => 'string', 'default' => 'standard'],
                'animate' => ['type' => 'boolean', 'default' => true],
            ],
        ]);

        // Success Tracker Block
        register_block_type('affiliate-client/success-tracker', [
            'editor_script' => 'affiliate-client-pricing-blocks',
            'render_callback' => [$this, 'render_success_tracker_block'],
            'attributes' => [
                'autoTrack' => ['type' => 'boolean', 'default' => true],
                'showMessage' => ['type' => 'boolean', 'default' => true],
                'redirectDelay' => ['type' => 'number', 'default' => 5],
            ],
        ]);

        // Discount Display Block
        register_block_type('affiliate-client/discount-display', [
            'editor_script' => 'affiliate-client-pricing-blocks',
            'render_callback' => [$this, 'render_discount_display_block'],
            'attributes' => [
                'showCode' => ['type' => 'boolean', 'default' => true],
                'showAmount' => ['type' => 'boolean', 'default' => true],
                'showType' => ['type' => 'boolean', 'default' => false],
                'showExpires' => ['type' => 'boolean', 'default' => true],
                'format' => ['type' => 'string', 'default' => 'badge'],
            ],
        ]);
    }

    /**
     * Render Gutenberg blocks
     */
    public function render_dynamic_price_block($attributes) {
        $widget_id = 'price-widget-block-' . uniqid();
        return $this->render_price_widget($widget_id, $attributes['basePrice'], $attributes['currency'], [
            'service_id' => $attributes['serviceId'],
            'display_original' => $attributes['displayOriginal'] ? 'true' : 'false',
            'show_savings' => $attributes['showSavings'] ? 'true' : 'false',
            'format' => $attributes['format'],
            'animate' => $attributes['animate'] ? 'true' : 'false',
            'class' => 'gutenberg-block',
        ]);
    }

    public function render_success_tracker_block($attributes) {
        $tracker_id = 'success-tracker-block-' . uniqid();
        return $this->render_success_tracker($tracker_id, [
            'auto_track' => $attributes['autoTrack'] ? 'true' : 'false',
            'show_message' => $attributes['showMessage'] ? 'true' : 'false',
            'redirect_delay' => strval($attributes['redirectDelay']),
            'class' => 'gutenberg-block',
        ]);
    }

    public function render_discount_display_block($attributes) {
        $display_id = 'discount-display-block-' . uniqid();
        return $this->render_discount_display($display_id, [
            'show_code' => $attributes['showCode'] ? 'true' : 'false',
            'show_amount' => $attributes['showAmount'] ? 'true' : 'false',
            'show_type' => $attributes['showType'] ? 'true' : 'false',
            'show_expires' => $attributes['showExpires'] ? 'true' : 'false',
            'format' => $attributes['format'],
            'class' => 'gutenberg-block',
        ]);
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_assets() {
        wp_enqueue_script(
            'affiliate-client-pricing-blocks',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/pricing-blocks.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );
    }

    /**
     * Enqueue pricing scripts
     */
    public function enqueue_pricing_scripts() {
        wp_enqueue_script(
            'affiliate-client-pricing',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/pricing-integration.js',
            ['jquery'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_enqueue_style(
            'affiliate-client-pricing',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/pricing-integration.css',
            [],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_localize_script('affiliate-client-pricing', 'affiliateClientPricing', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('affiliate-client/v1/'),
            'nonce' => wp_create_nonce('affiliate_client_nonce'),
            'obfuscatedFields' => $this->obfuscated_fields,
            'currency' => [
                'default' => 'EUR',
                'symbols' => [
                    'EUR' => '€',
                    'USD' => '$',
                    'GBP' => '£',
                    'JPY' => '¥',
                ],
            ],
            'strings' => [
                'loading' => __('Loading...', 'affiliate-client-full'),
                'error' => __('Error loading discount', 'affiliate-client-full'),
                'save' => __('Save', 'affiliate-client-full'),
                'saved' => __('Saved', 'affiliate-client-full'),
            ],
        ]);
    }

    /**
     * Register REST API endpoints
     */
    public function register_pricing_endpoints() {
        register_rest_route('affiliate-client/v1', '/discount/data', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_discount_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('affiliate-client/v1', '/success/track', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_track_success'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * AJAX handler to get discount data from master site
     */
    public function ajax_get_discount_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $discount_code = sanitize_text_field($_POST['discount_code'] ?? '');
        $affiliate_id = intval($_POST['affiliate_id'] ?? 0);
        $base_price = floatval($_POST['base_price'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'EUR');

        $result = $this->fetch_discount_data($discount_code, $affiliate_id, $base_price, $currency);
        wp_send_json($result);
    }

    /**
     * AJAX handler to track success
     */
    public function ajax_track_success() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $order_data = $_POST['order_data'] ?? [];
        $result = $this->track_purchase_success($order_data);
        wp_send_json($result);
    }

    /**
     * REST handlers
     */
    public function rest_get_discount_data($request) {
        $discount_code = sanitize_text_field($request->get_param('code'));
        $affiliate_id = intval($request->get_param('affiliate_id'));
        $base_price = floatval($request->get_param('base_price'));
        $currency = sanitize_text_field($request->get_param('currency'));

        $result = $this->fetch_discount_data($discount_code, $affiliate_id, $base_price, $currency);
        return rest_ensure_response($result);
    }

    public function rest_track_success($request) {
        $order_data = $request->get_json_params();
        $result = $this->track_purchase_success($order_data);
        return rest_ensure_response($result);
    }

    /**
     * Fetch discount data from master site
     */
    private function fetch_discount_data($discount_code, $affiliate_id, $base_price, $currency) {
        if (!$this->api_client || !$this->api_client->is_available()) {
            return [
                'success' => false,
                'message' => 'API not available',
            ];
        }

        // Request discount information from master site
        $response = $this->api_client->make_request('discount/calculate', [
            'code' => $discount_code,
            'affiliate_id' => $affiliate_id,
            'base_price' => $base_price,
            'currency' => $currency,
            'site_url' => home_url(),
        ], 'POST');

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to fetch discount data',
                'error' => $response['error'] ?? 'Unknown error',
            ];
        }

        $discount_data = $response['data'];
        
        // Calculate final prices
        $discount_amount = 0;
        $final_price = $base_price;

        if ($discount_data['type'] === 'percentage') {
            $discount_amount = $base_price * ($discount_data['value'] / 100);
            $final_price = $base_price - $discount_amount;
        } elseif ($discount_data['type'] === 'fixed') {
            $discount_amount = $discount_data['value'];
            $final_price = max(0, $base_price - $discount_amount);
        }

        // Create security signature
        $signature_data = [
            'code' => $discount_code,
            'type' => $discount_data['type'],
            'value' => $discount_data['value'],
            'original_price' => $base_price,
            'final_price' => $final_price,
            'timestamp' => time(),
        ];
        $signature = $this->create_signature($signature_data);

        // Prepare obfuscated data for Zoho form
        $obfuscated_data = [
            $this->obfuscated_fields['discount_type'] => $discount_data['type'],
            $this->obfuscated_fields['discount_value'] => $discount_data['value'],
            $this->obfuscated_fields['original_price'] => $base_price,
            $this->obfuscated_fields['discounted_price'] => $final_price,
            $this->obfuscated_fields['currency'] => $currency,
            $this->obfuscated_fields['discount_code'] => $discount_code,
            $this->obfuscated_fields['expires'] => time() + (24 * 60 * 60), // 24 hours
            $this->obfuscated_fields['signature'] => $signature,
        ];

        return [
            'success' => true,
            'discount' => [
                'code' => $discount_code,
                'type' => $discount_data['type'],
                'value' => $discount_data['value'],
                'amount' => $discount_amount,
                'original_price' => $base_price,
                'final_price' => $final_price,
                'currency' => $currency,
                'savings' => $discount_amount,
                'expires' => $discount_data['expires'] ?? null,
            ],
            'obfuscated_data' => $obfuscated_data,
            'formatted' => [
                'original_price' => $this->format_price($base_price, $currency),
                'final_price' => $this->format_price($final_price, $currency),
                'savings' => $this->format_price($discount_amount, $currency),
                'discount_display' => $discount_data['type'] === 'percentage' 
                    ? $discount_data['value'] . '%' 
                    : $this->format_price($discount_data['value'], $currency),
            ],
        ];
    }

    /**
     * Track purchase success
     */
    private function track_purchase_success($order_data) {
        // Extract order information from URL parameters or POST data
        $total_order = floatval($order_data['total_order'] ?? $_GET['totalorder'] ?? 0);
        $total_paid = floatval($order_data['total_paid'] ?? $_GET['totalpaid'] ?? 0);
        $order_id = sanitize_text_field($order_data['order_id'] ?? $_GET['orderid'] ?? '');
        $discount_code = sanitize_text_field($order_data['discount_code'] ?? $_GET['discount'] ?? '');

        // Get affiliate tracking data
        $affiliate_id = $this->get_current_affiliate_id();
        $visit_id = $this->get_current_visit_id();

        // Prepare success data
        $success_data = [
            'order_id' => $order_id,
            'total_order' => $total_order,
            'total_paid' => $total_paid,
            'discount_code' => $discount_code,
            'savings' => $total_order - $total_paid,
            'affiliate_id' => $affiliate_id,
            'visit_id' => $visit_id,
            'timestamp' => current_time('c'),
            'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        ];

        // Track locally
        $main_plugin = affiliate_client_full();
        if ($main_plugin && $main_plugin->tracking_handler) {
            $main_plugin->tracking_handler->track_event('purchase_success', $success_data);
        }

        // Send to master site
        if ($this->api_client && $this->api_client->is_available()) {
            $this->api_client->send_tracking_data([
                'type' => 'purchase_completion',
                'data' => $success_data,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Purchase tracked successfully',
            'order_data' => $success_data,
        ];
    }

    /**
     * Add discount parameters to Zoho tracking
     */
    public function add_discount_params($tracking_params, $form_atts) {
        // Get current discount data if available
        $discount_code = $_COOKIE['affiliate_discount_code'] ?? '';
        
        if ($discount_code) {
            $affiliate_id = $tracking_params['affiliate_id'] ?? 0;
            $base_price = $form_atts['base_price'] ?? 0;
            $currency = $form_atts['currency'] ?? 'EUR';

            $discount_data = $this->fetch_discount_data($discount_code, $affiliate_id, $base_price, $currency);
            
            if ($discount_data['success']) {
                // Add obfuscated discount data to tracking parameters
                $tracking_params = array_merge($tracking_params, $discount_data['obfuscated_data']);
            }
        }

        return $tracking_params;
    }

    /**
     * Helper methods
     */
    private function format_price($amount, $currency) {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'JPY' => '¥',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';
        return $symbol . number_format($amount, 2);
    }

    private function create_signature($data) {
        $string = implode('|', $data);
        return hash_hmac('sha256', $string, wp_salt());
    }

    private function get_current_affiliate_id() {
        if (isset($_COOKIE[$this->config['cookie_name']])) {
            return intval($_COOKIE[$this->config['cookie_name']]);
        }
        return null;
    }

    private function get_current_visit_id() {
        if (isset($_COOKIE['affiliate_client_visit_id'])) {
            return sanitize_text_field($_COOKIE['affiliate_client_visit_id']);
        }
        return null;
    }

    /**
     * Get discount statistics
     */
    public function get_discount_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                JSON_EXTRACT(data, '$.discount_code') as discount_code,
                JSON_EXTRACT(data, '$.total_order') as total_order,
                JSON_EXTRACT(data, '$.total_paid') as total_paid,
                JSON_EXTRACT(data, '$.savings') as savings,
                COUNT(*) as usage_count
            FROM {$table_name}
            WHERE event_type = 'purchase_success'
            AND JSON_EXTRACT(data, '$.discount_code') IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY JSON_EXTRACT(data, '$.discount_code')
            ORDER BY usage_count DESC
        ", $days));
    }
}