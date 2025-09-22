<?php
/**
 * Addon Client Class
 *
 * Handles integration with various WordPress plugins and addons
 * for automatic conversion tracking and enhanced functionality.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFILIATE_CLIENT_Addon_Client {

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
     * Enabled addons
     *
     * @var array
     */
    private $enabled_addons = [];

    /**
     * Constructor
     *
     * @param array $config Plugin configuration
     * @param AFFILIATE_CLIENT_API_Client $api_client API client instance
     */
    public function __construct($config, $api_client) {
        $this->config = $config;
        $this->api_client = $api_client;
    }

    /**
     * Initialse addon integrations
     */
    public function init() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        $this->detect_enabled_addons();
        $this->setup_addon_hooks();
    }

    /**
     * Detect enabled addons
     */
    private function detect_enabled_addons() {
        foreach ($this->config['supported_addons'] as $addon_slug => $addon_config) {
            if ($addon_config['enabled']) {
                $this->enabled_addons[$addon_slug] = $addon_config;
            }
        }
    }

    /**
     * Setup hooks for enabled addons
     */
    private function setup_addon_hooks() {
        foreach ($this->enabled_addons as $addon_slug => $addon_config) {
            foreach ($addon_config['hooks'] as $hook => $callback) {
                if (method_exists($this, $callback)) {
                    add_action($hook, [$this, $callback], 10, 10);
                }
            }
        }

        // Generic hooks that work across addons
        add_action('wp_footer', [$this, 'output_addon_tracking_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_addon_scripts']);
    }

    /**
     * Track WooCommerce purchase
     *
     * @param int $order_id Order ID
     */
    public function track_purchase($order_id) {
        if (!$this->is_addon_enabled('woocommerce') || !class_exists('WC_Order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $conversion_data = [
            'event_type' => 'purchase',
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'products' => $this->get_woocommerce_products($order),
            'customer_email' => $order->get_billing_email(),
            'customer_id' => $order->get_customer_id(),
            'payment_method' => $order->get_payment_method(),
            'order_status' => $order->get_status(),
            'coupon_codes' => $order->get_coupon_codes(),
            'order_date' => $order->get_date_created()->format('c'),
        ];

        $this->track_conversion($conversion_data);
    }

    /**
     * Track WooCommerce add to cart
     *
     * @param string $cart_item_key Cart item key
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param int $variation_id Variation ID
     * @param array $variation Variation data
     * @param array $cart_item_data Cart item data
     */
    public function track_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        if (!$this->is_addon_enabled('woocommerce')) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $event_data = [
            'event_type' => 'add_to_cart',
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'product_price' => $product->get_price(),
            'quantity' => $quantity,
            'variation_id' => $variation_id,
            'variation_data' => $variation,
            'cart_total' => WC()->cart->get_cart_contents_total(),
        ];

        $this->track_event($event_data);
    }

    /**
     * Track EDD purchase
     *
     * @param int $payment_id Payment ID
     */
    public function track_edd_purchase($payment_id) {
        if (!$this->is_addon_enabled('easy_digital_downloads') || !function_exists('edd_get_payment')) {
            return;
        }

        $payment = edd_get_payment($payment_id);
        if (!$payment) {
            return;
        }

        $conversion_data = [
            'event_type' => 'purchase',
            'payment_id' => $payment_id,
            'total' => $payment->total,
            'currency' => $payment->currency,
            'products' => $this->get_edd_products($payment),
            'customer_email' => $payment->email,
            'customer_id' => $payment->customer_id,
            'payment_method' => $payment->gateway,
            'payment_status' => $payment->status,
            'discount_codes' => $this->get_edd_discounts($payment),
            'payment_date' => $payment->date,
        ];

        $this->track_conversion($conversion_data);
    }

    /**
     * Track EDD add to cart
     *
     * @param int $download_id Download ID
     * @param array $options Download options
     */
    public function track_edd_add_to_cart($download_id, $options) {
        if (!$this->is_addon_enabled('easy_digital_downloads') || !function_exists('edd_get_download')) {
            return;
        }

        $download = edd_get_download($download_id);
        if (!$download) {
            return;
        }

        $event_data = [
            'event_type' => 'add_to_cart',
            'download_id' => $download_id,
            'download_name' => $download->post_title,
            'download_price' => edd_get_download_price($download_id),
            'options' => $options,
            'cart_total' => edd_get_cart_total(),
        ];

        $this->track_event($event_data);
    }

    /**
     * Track MemberPress membership purchase
     *
     * @param object $event MemberPress event
     */
    public function track_membership_purchase($event) {
        if (!$this->is_addon_enabled('memberpress') || !isset($event->data)) {
            return;
        }

        $transaction = $event->data;

        $conversion_data = [
            'event_type' => 'membership_purchase',
            'transaction_id' => $transaction->id,
            'membership_id' => $transaction->product_id,
            'total' => $transaction->total,
            'user_id' => $transaction->user_id,
            'subscription_id' => $transaction->subscription_id,
            'gateway' => $transaction->gateway,
            'status' => $transaction->status,
        ];

        $this->track_conversion($conversion_data);
    }

    /**
     * Track MemberPress member signup
     *
     * @param object $event MemberPress event
     */
    public function track_member_signup($event) {
        if (!$this->is_addon_enabled('memberpress') || !isset($event->data)) {
            return;
        }

        $user = $event->data;

        $event_data = [
            'event_type' => 'member_signup',
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'signup_date' => current_time('c'),
        ];

        $this->track_event($event_data);
    }

    /**
     * Track LifterLMS course enrollment
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     */
    public function track_course_enrollment($user_id, $course_id) {
        if (!$this->is_addon_enabled('lifter_lms')) {
            return;
        }

        $event_data = [
            'event_type' => 'course_enrollment',
            'user_id' => $user_id,
            'course_id' => $course_id,
            'course_title' => get_the_title($course_id),
            'enrollment_date' => current_time('c'),
        ];

        $this->track_event($event_data);
    }

    /**
     * Track LifterLMS course purchase
     *
     * @param object $order LifterLMS order
     */
    public function track_course_purchase($order) {
        if (!$this->is_addon_enabled('lifter_lms') || !is_object($order)) {
            return;
        }

        $conversion_data = [
            'event_type' => 'course_purchase',
            'order_id' => $order->get('id'),
            'total' => $order->get('total'),
            'currency' => $order->get('currency'),
            'user_id' => $order->get('user_id'),
            'products' => $this->get_llms_products($order),
            'payment_gateway' => $order->get('payment_gateway'),
            'order_status' => $order->get('status'),
        ];

        $this->track_conversion($conversion_data);
    }

    /**
     * Track Gravity Forms submission
     *
     * @param array $entry Form entry
     * @param array $form Form configuration
     */
    public function track_form_submission($entry, $form) {
        if (!$this->is_addon_enabled('gravity_forms')) {
            return;
        }

        $event_data = [
            'event_type' => 'form_submission',
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'entry_id' => $entry['id'],
            'user_id' => $entry['created_by'],
            'submission_date' => $entry['date_created'],
            'source_url' => $entry['source_url'],
            'user_ip' => $entry['ip'],
        ];

        // Add specific field values if configured
        $tracked_fields = $this->get_tracked_form_fields($form['id']);
        foreach ($tracked_fields as $field_id) {
            if (isset($entry[$field_id])) {
                $event_data['field_' . $field_id] = $entry[$field_id];
            }
        }

        $this->track_event($event_data);
    }

    /**
     * Track Contact Form 7 submission
     *
     * @param object $contact_form CF7 form object
     */
    public function track_cf7_submission($contact_form) {
        if (!$this->is_addon_enabled('contact_form_7')) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }

        $event_data = [
            'event_type' => 'form_submission',
            'form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'submission_date' => current_time('c'),
            'posted_data' => $submission->get_posted_data(),
        ];

        $this->track_event($event_data);
    }

    /**
     * Track conversion event
     *
     * @param array $conversion_data Conversion data
     */
    private function track_conversion($conversion_data) {
        // Get main plugin instance to access conversion tracker
        $main_plugin = affiliate_client_full();
        if ($main_plugin && $main_plugin->conversion_tracker) {
            $main_plugin->conversion_tracker->track_conversion(
                $conversion_data['total'] ?? 0,
                $conversion_data['order_id'] ?? null,
                $conversion_data
            );
        }
    }

    /**
     * Track generic event
     *
     * @param array $event_data Event data
     */
    private function track_event($event_data) {
        // Get main plugin instance to access tracking handler
        $main_plugin = affiliate_client_full();
        if ($main_plugin && $main_plugin->tracking_handler) {
            $main_plugin->tracking_handler->track_event(
                $event_data['event_type'],
                $event_data
            );
        }
    }

    /**
     * Get WooCommerce products from order
     *
     * @param WC_Order $order Order object
     * @return array Products data
     */
    private function get_woocommerce_products($order) {
        $products = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $products[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    'sku' => $product->get_sku(),
                    'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']),
                ];
            }
        }
        
        return $products;
    }

    /**
     * Get EDD products from payment
     *
     * @param object $payment EDD payment object
     * @return array Products data
     */
    private function get_edd_products($payment) {
        $products = [];
        $downloads = $payment->downloads;
        
        foreach ($downloads as $download) {
            $products[] = [
                'id' => $download['id'],
                'name' => get_the_title($download['id']),
                'price' => $download['price'],
                'quantity' => $download['quantity'],
                'options' => $download['options'] ?? [],
            ];
        }
        
        return $products;
    }

    /**
     * Get EDD discount codes from payment
     *
     * @param object $payment EDD payment object
     * @return array Discount codes
     */
    private function get_edd_discounts($payment) {
        $discounts = [];
        
        if (isset($payment->discounts) && !empty($payment->discounts)) {
            $discounts = explode(',', $payment->discounts);
        }
        
        return $discounts;
    }

    /**
     * Get LifterLMS products from order
     *
     * @param object $order LLMS order object
     * @return array Products data
     */
    private function get_llms_products($order) {
        $products = [];
        $product_id = $order->get('product_id');
        
        if ($product_id) {
            $products[] = [
                'id' => $product_id,
                'name' => get_the_title($product_id),
                'type' => get_post_type($product_id),
                'price' => $order->get('total'),
            ];
        }
        
        return $products;
    }

    /**
     * Get tracked form fields for Gravity Forms
     *
     * @param int $form_id Form ID
     * @return array Field IDs to track
     */
    private function get_tracked_form_fields($form_id) {
        // Get from options or return default fields
        $tracked_fields = get_option('affiliate_client_gf_tracked_fields_' . $form_id, []);
        
        if (empty($tracked_fields)) {
            // Default fields to track
            $tracked_fields = ['1', '2', '3']; // Usually name, email, message
        }
        
        return apply_filters('affiliate_client_gf_tracked_fields', $tracked_fields, $form_id);
    }

    /**
     * Check if specific addon is enabled
     *
     * @param string $addon_slug Addon slug
     * @return bool True if enabled
     */
    private function is_addon_enabled($addon_slug) {
        return isset($this->enabled_addons[$addon_slug]);
    }

    /**
     * Enqueue addon-specific scripts
     */
    public function enqueue_addon_scripts() {
        $addon_data = [];
        
        foreach ($this->enabled_addons as $addon_slug => $addon_config) {
            $addon_data[$addon_slug] = [
                'enabled' => true,
                'settings' => $this->get_addon_settings($addon_slug),
            ];
        }

        if (!empty($addon_data)) {
            wp_localize_script('affiliate-client-addons', 'affiliateClientAddons', $addon_data);
        }
    }

    /**
     * Output addon tracking scripts
     */
    public function output_addon_tracking_scripts() {
        if (empty($this->enabled_addons)) {
            return;
        }

        ?>
        <script type="text/javascript">
        (function() {
            // Enhanced tracking for supported addons
            <?php foreach ($this->enabled_addons as $addon_slug => $addon_config): ?>
                <?php $this->output_addon_specific_script($addon_slug); ?>
            <?php endforeach; ?>
        })();
        </script>
        <?php
    }

    /**
     * Output addon-specific tracking script
     *
     * @param string $addon_slug Addon slug
     */
    private function output_addon_specific_script($addon_slug) {
        switch ($addon_slug) {
            case 'woocommerce':
                $this->output_woocommerce_script();
                break;
            case 'easy_digital_downloads':
                $this->output_edd_script();
                break;
            case 'gravity_forms':
                $this->output_gravity_forms_script();
                break;
        }
    }

    /**
     * Output WooCommerce tracking script
     */
    private function output_woocommerce_script() {
        ?>
        // WooCommerce enhanced tracking
        if (typeof wc_add_to_cart_params !== 'undefined') {
            jQuery(document).on('added_to_cart', function(event, fragments, cart_hash, button) {
                if (typeof AffiliateClientTracker !== 'undefined') {
                    var productId = button.data('product_id');
                    var quantity = button.data('quantity') || 1;
                    
                    AffiliateClientTracker.trackEvent('wc_add_to_cart', {
                        product_id: productId,
                        quantity: quantity,
                        timestamp: Date.now()
                    });
                }
            });
        }
        <?php
    }

    /**
     * Output EDD tracking script
     */
    private function output_edd_script() {
        ?>
        // EDD enhanced tracking
        jQuery(document).on('edd_cart_item_added', function(event, response) {
            if (typeof AffiliateClientTracker !== 'undefined') {
                AffiliateClientTracker.trackEvent('edd_add_to_cart', {
                    download_id: response.download_id,
                    cart_total: response.cart_total,
                    timestamp: Date.now()
                });
            }
        });
        <?php
    }

    /**
     * Output Gravity Forms tracking script
     */
    private function output_gravity_forms_script() {
        ?>
        // Gravity Forms enhanced tracking
        jQuery(document).on('gform_confirmation_loaded', function(event, formId) {
            if (typeof AffiliateClientTracker !== 'undefined') {
                AffiliateClientTracker.trackEvent('gf_form_submitted', {
                    form_id: formId,
                    timestamp: Date.now()
                });
            }
        });
        <?php
    }

    /**
     * Get addon-specific settings
     *
     * @param string $addon_slug Addon slug
     * @return array Addon settings
     */
    private function get_addon_settings($addon_slug) {
        $default_settings = [
            'track_add_to_cart' => true,
            'track_purchases' => true,
            'track_form_submissions' => true,
        ];
        
        $saved_settings = get_option('affiliate_client_addon_settings_' . $addon_slug, []);
        
        return array_merge($default_settings, $saved_settings);
    }

    /**
     * Get enabled addons list
     *
     * @return array Enabled addons
     */
    public function get_enabled_addons() {
        return $this->enabled_addons;
    }

    /**
     * Get addon statistics
     *
     * @param string $addon_slug Addon slug
     * @param int $days Number of days to look back
     * @return array Addon statistics
     */
    public function get_addon_stats($addon_slug, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as purchases,
                SUM(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as add_to_carts,
                SUM(CASE WHEN event_type = 'form_submission' THEN 1 ELSE 0 END) as form_submissions
            FROM {$table_name}
            WHERE JSON_EXTRACT(data, '$.addon') = %s
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $addon_slug, $days));
        
        return [
            'total_events' => intval($stats->total_events ?? 0),
            'purchases' => intval($stats->purchases ?? 0),
            'add_to_carts' => intval($stats->add_to_carts ?? 0),
            'form_submissions' => intval($stats->form_submissions ?? 0),
        ];
    }

    /**
     * Update addon settings
     *
     * @param string $addon_slug Addon slug
     * @param array $settings New settings
     * @return bool Success status
     */
    public function update_addon_settings($addon_slug, $settings) {
        return update_option('affiliate_client_addon_settings_' . $addon_slug, $settings);
    }

    /**
     * Reset addon settings to defaults
     *
     * @param string $addon_slug Addon slug
     * @return bool Success status
     */
    public function reset_addon_settings($addon_slug) {
        return delete_option('affiliate_client_addon_settings_' . $addon_slug);
    }

    /**
     * Check addon compatibility
     *
     * @param string $addon_slug Addon slug
     * @return array Compatibility info
     */
    public function check_addon_compatibility($addon_slug) {
        $compatibility = [
            'compatible' => false,
            'version' => '',
            'min_version' => '',
            'issues' => [],
        ];

        switch ($addon_slug) {
            case 'woocommerce':
                if (class_exists('WooCommerce')) {
                    $wc_version = WC()->version;
                    $min_version = '3.0.0';
                    
                    $compatibility['version'] = $wc_version;
                    $compatibility['min_version'] = $min_version;
                    $compatibility['compatible'] = version_compare($wc_version, $min_version, '>=');
                    
                    if (!$compatibility['compatible']) {
                        $compatibility['issues'][] = sprintf(
                            __('WooCommerce version %s is required, but %s is installed.', 'affiliate-client-full'),
                            $min_version,
                            $wc_version
                        );
                    }
                }
                break;

            case 'easy_digital_downloads':
                if (class_exists('Easy_Digital_Downloads')) {
                    $edd_version = EDD_VERSION;
                    $min_version = '2.8.0';
                    
                    $compatibility['version'] = $edd_version;
                    $compatibility['min_version'] = $min_version;
                    $compatibility['compatible'] = version_compare($edd_version, $min_version, '>=');
                    
                    if (!$compatibility['compatible']) {
                        $compatibility['issues'][] = sprintf(
                            __('Easy Digital Downloads version %s is required, but %s is installed.', 'affiliate-client-full'),
                            $min_version,
                            $edd_version
                        );
                    }
                }
                break;

            case 'memberpress':
                if (defined('MEPR_VERSION')) {
                    $mp_version = MEPR_VERSION;
                    $min_version = '1.9.0';
                    
                    $compatibility['version'] = $mp_version;
                    $compatibility['min_version'] = $min_version;
                    $compatibility['compatible'] = version_compare($mp_version, $min_version, '>=');
                    
                    if (!$compatibility['compatible']) {
                        $compatibility['issues'][] = sprintf(
                            __('MemberPress version %s is required, but %s is installed.', 'affiliate-client-full'),
                            $min_version,
                            $mp_version
                        );
                    }
                }
                break;

            case 'lifter_lms':
                if (class_exists('LifterLMS')) {
                    $llms_version = LLMS()->version;
                    $min_version = '4.0.0';
                    
                    $compatibility['version'] = $llms_version;
                    $compatibility['min_version'] = $min_version;
                    $compatibility['compatible'] = version_compare($llms_version, $min_version, '>=');
                    
                    if (!$compatibility['compatible']) {
                        $compatibility['issues'][] = sprintf(
                            __('LifterLMS version %s is required, but %s is installed.', 'affiliate-client-full'),
                            $min_version,
                            $llms_version
                        );
                    }
                }
                break;

            case 'gravity_forms':
                if (class_exists('GFForms')) {
                    $gf_version = GFForms::$version;
                    $min_version = '2.4.0';
                    
                    $compatibility['version'] = $gf_version;
                    $compatibility['min_version'] = $min_version;
                    $compatibility['compatible'] = version_compare($gf_version, $min_version, '>=');
                    
                    if (!$compatibility['compatible']) {
                        $compatibility['issues'][] = sprintf(
                            __('Gravity Forms version %s is required, but %s is installed.', 'affiliate-client-full'),
                            $min_version,
                            $gf_version
                        );
                    }
                }
                break;

            case 'contact_form_7':
                if (defined('WPCF7_VERSION')) {
                    $cf7_version = WPCF7_VERSION;
                    $min_version = '5.0.0';
                    
                    $compatibility['version'] = $cf7_version;
                    $compatibility['min_version'] = $min_version;
                    $compatibility['compatible'] = version_compare($cf7_version, $min_version, '>=');
                    
                    if (!$compatibility['compatible']) {
                        $compatibility['issues'][] = sprintf(
                            __('Contact Form 7 version %s is required, but %s is installed.', 'affiliate-client-full'),
                            $min_version,
                            $cf7_version
                        );
                    }
                }
                break;
        }

        return $compatibility;
    }

    /**
     * Get all addon compatibility info
     *
     * @return array All addons compatibility
     */
    public function get_all_addon_compatibility() {
        $compatibility_info = [];
        
        foreach ($this->config['supported_addons'] as $addon_slug => $addon_config) {
            $compatibility_info[$addon_slug] = [
                'name' => $this->get_addon_name($addon_slug),
                'enabled' => $addon_config['enabled'],
                'compatibility' => $this->check_addon_compatibility($addon_slug),
            ];
        }
        
        return $compatibility_info;
    }

    /**
     * Get addon display name
     *
     * @param string $addon_slug Addon slug
     * @return string Addon name
     */
    private function get_addon_name($addon_slug) {
        $names = [
            'woocommerce' => 'WooCommerce',
            'easy_digital_downloads' => 'Easy Digital Downloads',
            'memberpress' => 'MemberPress',
            'lifter_lms' => 'LifterLMS',
            'gravity_forms' => 'Gravity Forms',
            'contact_form_7' => 'Contact Form 7',
        ];
        
        return $names[$addon_slug] ?? ucwords(str_replace('_', ' ', $addon_slug));
    }

    /**
     * Register custom post conversion for addon
     *
     * @param string $addon_slug Addon slug
     * @param string $post_type Post type to track
     * @param array $settings Tracking settings
     */
    public function register_custom_conversion($addon_slug, $post_type, $settings = []) {
        $custom_conversions = get_option('affiliate_client_custom_conversions', []);
        
        $custom_conversions[$addon_slug . '_' . $post_type] = [
            'addon' => $addon_slug,
            'post_type' => $post_type,
            'settings' => array_merge([
                'track_creation' => true,
                'track_updates' => false,
                'value_field' => '',
                'reference_field' => 'ID',
            ], $settings),
        ];
        
        update_option('affiliate_client_custom_conversions', $custom_conversions);
        
        // Add hooks for custom post type
        add_action('save_post_' . $post_type, [$this, 'track_custom_post_conversion'], 10, 3);
    }

    /**
     * Track custom post conversion
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function track_custom_post_conversion($post_id, $post, $update) {
        $custom_conversions = get_option('affiliate_client_custom_conversions', []);
        $conversion_key = null;
        
        // Find matching conversion configuration
        foreach ($custom_conversions as $key => $config) {
            if ($config['post_type'] === $post->post_type) {
                $conversion_key = $key;
                break;
            }
        }
        
        if (!$conversion_key) {
            return;
        }
        
        $config = $custom_conversions[$conversion_key];
        $settings = $config['settings'];
        
        // Check if we should track this event
        if ($update && !$settings['track_updates']) {
            return;
        }
        
        if (!$update && !$settings['track_creation']) {
            return;
        }
        
        // Get conversion value
        $value = 0;
        if (!empty($settings['value_field'])) {
            $value = get_post_meta($post_id, $settings['value_field'], true);
            $value = floatval($value);
        }
        
        // Get reference
        $reference = $post_id;
        if (!empty($settings['reference_field']) && $settings['reference_field'] !== 'ID') {
            $reference = get_post_meta($post_id, $settings['reference_field'], true);
            $reference = $reference ?: $post_id;
        }
        
        $conversion_data = [
            'event_type' => 'custom_conversion',
            'addon' => $config['addon'],
            'post_type' => $post->post_type,
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'total' => $value,
            'reference' => $reference,
            'is_update' => $update,
        ];
        
        $this->track_conversion($conversion_data);
    }

    /**
     * Disable addon integration
     *
     * @param string $addon_slug Addon slug
     */
    public function disable_addon($addon_slug) {
        if (isset($this->enabled_addons[$addon_slug])) {
            unset($this->enabled_addons[$addon_slug]);
            
            // Remove hooks for this addon
            $addon_config = $this->config['supported_addons'][$addon_slug] ?? [];
            foreach ($addon_config['hooks'] ?? [] as $hook => $callback) {
                if (method_exists($this, $callback)) {
                    remove_action($hook, [$this, $callback]);
                }
            }
        }
    }

    /**
     * Enable addon integration
     *
     * @param string $addon_slug Addon slug
     */
    public function enable_addon($addon_slug) {
        if (isset($this->config['supported_addons'][$addon_slug])) {
            $addon_config = $this->config['supported_addons'][$addon_slug];
            
            if ($addon_config['enabled']) {
                $this->enabled_addons[$addon_slug] = $addon_config;
                
                // Add hooks for this addon
                foreach ($addon_config['hooks'] as $hook => $callback) {
                    if (method_exists($this, $callback)) {
                        add_action($hook, [$this, $callback], 10, 10);
                    }
                }
            }
        }
    }

    /**
     * Get addon integration status
     *
     * @return array Integration status for all addons
     */
    public function get_integration_status() {
        $status = [];
        
        foreach ($this->config['supported_addons'] as $addon_slug => $addon_config) {
            $compatibility = $this->check_addon_compatibility($addon_slug);
            
            $status[$addon_slug] = [
                'name' => $this->get_addon_name($addon_slug),
                'plugin_active' => $addon_config['enabled'],
                'compatible' => $compatibility['compatible'],
                'integration_active' => isset($this->enabled_addons[$addon_slug]),
                'version' => $compatibility['version'],
                'issues' => $compatibility['issues'],
                'stats' => $this->get_addon_stats($addon_slug, 7), // Last 7 days
            ];
        }
        
        return $status;
    }
}