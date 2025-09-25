<?php
/**
 * Discount Code Input Form
 *
 * Provides shortcode and Gutenberg block functionality for discount code
 * input forms where users can enter and apply their own discount codes.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFILIATE_CLIENT_Discount_Form {

    /**
     * Plugin configuration
     *
     * @var array
     */
    private $config;

    /**
     * Constructor
     *
     * @param array $config Plugin configuration
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Initialize discount form functionality
     */
    public function init() {
        // Register shortcodes
        add_shortcode('affiliate_discount_form', [$this, 'discount_form_shortcode']);
        add_shortcode('affiliate_coupon_form', [$this, 'discount_form_shortcode']); // Alias
        
        // Gutenberg block support
        add_action('init', [$this, 'register_form_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_form_block_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_affiliate_client_validate_discount', [$this, 'ajax_validate_discount']);
        add_action('wp_ajax_nopriv_affiliate_client_validate_discount', [$this, 'ajax_validate_discount']);
        add_action('wp_ajax_affiliate_client_apply_user_discount', [$this, 'ajax_apply_user_discount']);
        add_action('wp_ajax_nopriv_affiliate_client_apply_user_discount', [$this, 'ajax_apply_user_discount']);
        
        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_form_rest_endpoints']);
        
        // Frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_form_scripts']);
    }

    /**
     * Discount form shortcode
     *
     * Usage: [affiliate_discount_form placeholder="Enter discount code" button_text="Apply" style="inline"]
     */
    public function discount_form_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'placeholder' => 'Enter discount code',
            'button_text' => 'Apply',
            'style' => 'default', // default, inline, stacked, minimal
            'size' => 'medium', // small, medium, large
            'color' => '#4CAF50',
            'show_validation' => 'true',
            'auto_validate' => 'true',
            'track_usage' => 'true',
            'redirect_after' => '', // URL to redirect after successful application
            'success_message' => '',
            'error_message' => '',
            'class' => '',
            'id' => '',
        ], $atts, 'affiliate_discount_form');

        // Sanitize attributes
        $atts = array_map('sanitize_text_field', $atts);
        $atts['show_validation'] = filter_var($atts['show_validation'], FILTER_VALIDATE_BOOLEAN);
        $atts['auto_validate'] = filter_var($atts['auto_validate'], FILTER_VALIDATE_BOOLEAN);
        $atts['track_usage'] = filter_var($atts['track_usage'], FILTER_VALIDATE_BOOLEAN);

        // Generate unique ID for this form
        $form_id = !empty($atts['id']) ? $atts['id'] : 'affiliate-discount-form-' . uniqid();

        return $this->render_discount_form($form_id, $atts, $content);
    }

    /**
     * Render discount code input form
     */
    private function render_discount_form($form_id, $atts, $content = '') {
        $classes = ['affiliate-discount-form', 'style-' . $atts['style'], 'size-' . $atts['size']];
        if (!empty($atts['class'])) {
            $classes[] = $atts['class'];
        }

        // Default messages
        $success_message = !empty($atts['success_message']) ? $atts['success_message'] : __('Discount code applied successfully!', 'affiliate-client-full');
        $error_message = !empty($atts['error_message']) ? $atts['error_message'] : __('Invalid discount code. Please try again.', 'affiliate-client-full');

        ob_start();
        ?>
        <div id="<?php echo esc_attr($form_id); ?>" 
             class="<?php echo esc_attr(implode(' ', $classes)); ?>"
             data-auto-validate="<?php echo $atts['auto_validate'] ? 'true' : 'false'; ?>"
             data-track-usage="<?php echo $atts['track_usage'] ? 'true' : 'false'; ?>"
             data-redirect-after="<?php echo esc_attr($atts['redirect_after']); ?>"
             data-success-message="<?php echo esc_attr($success_message); ?>"
             data-error-message="<?php echo esc_attr($error_message); ?>"
             style="--primary-color: <?php echo esc_attr($atts['color']); ?>;">
            
            <?php if (!empty($content)): ?>
                <div class="affiliate-discount-form-header">
                    <?php echo do_shortcode($content); ?>
                </div>
            <?php endif; ?>

            <form class="discount-code-form" method="post">
                <div class="form-row">
                    <div class="input-group">
                        <input type="text" 
                               name="discount_code" 
                               class="discount-code-input" 
                               placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                               autocomplete="off"
                               spellcheck="false"
                               required>
                        
                        <?php if ($atts['show_validation']): ?>
                            <div class="validation-indicator">
                                <span class="validation-icon"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="discount-apply-button">
                        <span class="button-text"><?php echo esc_html($atts['button_text']); ?></span>
                        <span class="loading-spinner"></span>
                    </button>
                </div>

                <?php if ($atts['show_validation']): ?>
                    <div class="validation-message" style="display: none;"></div>
                <?php endif; ?>
            </form>

            <div class="form-feedback" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Register Gutenberg block for discount form
     */
    public function register_form_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('affiliate-client/discount-form', [
            'editor_script' => 'affiliate-client-discount-form-block',
            'editor_style' => 'affiliate-client-discount-form-block-editor',
            'style' => 'affiliate-client-discount-form-block',
            'render_callback' => [$this, 'render_form_block'],
            'attributes' => [
                'placeholder' => [
                    'type' => 'string',
                    'default' => 'Enter discount code',
                ],
                'buttonText' => [
                    'type' => 'string',
                    'default' => 'Apply',
                ],
                'style' => [
                    'type' => 'string',
                    'default' => 'default',
                ],
                'size' => [
                    'type' => 'string',
                    'default' => 'medium',
                ],
                'color' => [
                    'type' => 'string',
                    'default' => '#4CAF50',
                ],
                'showValidation' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'autoValidate' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'trackUsage' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'redirectAfter' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'successMessage' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'errorMessage' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'headerContent' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    /**
     * Render Gutenberg block
     */
    public function render_form_block($attributes) {
        // Convert camelCase to snake_case for shortcode
        $shortcode_atts = [
            'placeholder' => $attributes['placeholder'] ?? 'Enter discount code',
            'button_text' => $attributes['buttonText'] ?? 'Apply',
            'style' => $attributes['style'] ?? 'default',
            'size' => $attributes['size'] ?? 'medium',
            'color' => $attributes['color'] ?? '#4CAF50',
            'show_validation' => $attributes['showValidation'] ?? true,
            'auto_validate' => $attributes['autoValidate'] ?? true,
            'track_usage' => $attributes['trackUsage'] ?? true,
            'redirect_after' => $attributes['redirectAfter'] ?? '',
            'success_message' => $attributes['successMessage'] ?? '',
            'error_message' => $attributes['errorMessage'] ?? '',
        ];

        $form_id = 'affiliate-discount-form-block-' . uniqid();
        $content = $attributes['headerContent'] ?? '';
        
        return $this->render_discount_form($form_id, $shortcode_atts, $content);
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_form_block_assets() {
        wp_enqueue_script(
            'affiliate-client-discount-form-block',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/discount-form-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_enqueue_style(
            'affiliate-client-discount-form-block-editor',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/discount-form-block-editor.css',
            [],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_enqueue_style(
            'affiliate-client-discount-form-block',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/discount-form.css',
            [],
            AFFILIATE_CLIENT_FULL_VERSION
        );
    }

    /**
     * Enqueue frontend form scripts
     */
    public function enqueue_form_scripts() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        wp_enqueue_script(
            'affiliate-client-discount-form',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/discount-form.js',
            ['jquery'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_enqueue_style(
            'affiliate-client-discount-form',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/discount-form.css',
            [],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_localize_script('affiliate-client-discount-form', 'affiliateClientDiscountForm', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('affiliate-client/v1/'),
            'nonce' => wp_create_nonce('affiliate_client_nonce'),
            'strings' => [
                'validating' => __('Validating...', 'affiliate-client-full'),
                'applying' => __('Applying...', 'affiliate-client-full'),
                'valid' => __('Valid code', 'affiliate-client-full'),
                'invalid' => __('Invalid code', 'affiliate-client-full'),
                'applied' => __('Applied successfully', 'affiliate-client-full'),
                'failed' => __('Application failed', 'affiliate-client-full'),
            ],
        ]);
    }

    /**
     * Register REST API endpoints
     */
    public function register_form_rest_endpoints() {
        register_rest_route('affiliate-client/v1', '/discount/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_validate_discount'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route('affiliate-client/v1', '/discount/apply-user', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_apply_user_discount'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    /**
     * AJAX handler for validating discount code
     */
    public function ajax_validate_discount() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $code = strtoupper(sanitize_text_field($_POST['code']));
        $result = $this->validate_discount_code($code);
        
        wp_send_json($result);
    }

    /**
     * AJAX handler for applying user-entered discount
     */
    public function ajax_apply_user_discount() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $code = strtoupper(sanitize_text_field($_POST['code']));
        $result = $this->apply_user_discount($code);
        
        wp_send_json($result);
    }

    /**
     * REST API handler for validating discount
     */
    public function rest_validate_discount($request) {
        $code = strtoupper(sanitize_text_field($request->get_param('code')));
        $result = $this->validate_discount_code($code);
        
        return rest_ensure_response($result);
    }

    /**
     * REST API handler for applying user discount
     */
    public function rest_apply_user_discount($request) {
        $code = strtoupper(sanitize_text_field($request->get_param('code')));
        $result = $this->apply_user_discount($code);
        
        return rest_ensure_response($result);
    }

    /**
     * Validate discount code
     */
    private function validate_discount_code($code) {
        if (empty($code)) {
            return [
                'success' => false,
                'valid' => false,
                'message' => __('Please enter a discount code.', 'affiliate-client-full'),
            ];
        }

        // Track validation attempt
        $this->track_discount_action('validate', $code);

        // Check with e-commerce plugins
        $validation_result = $this->check_discount_with_ecommerce($code);
        
        if ($validation_result !== null) {
            return $validation_result;
        }

        // Generic validation - check if code looks valid
        $is_valid = $this->generic_code_validation($code);
        
        return [
            'success' => true,
            'valid' => $is_valid,
            'message' => $is_valid ? 
                __('Code appears valid', 'affiliate-client-full') : 
                __('Code format invalid', 'affiliate-client-full'),
            'code' => $code,
        ];
    }

    /**
     * Apply user-entered discount code
     */
    private function apply_user_discount($code) {
        if (empty($code)) {
            return [
                'success' => false,
                'message' => __('Please enter a discount code.', 'affiliate-client-full'),
            ];
        }

        // Track application attempt
        $this->track_discount_action('apply', $code);

        // WooCommerce integration
        if (class_exists('WooCommerce')) {
            return $this->apply_woocommerce_user_coupon($code);
        }
        
        // Easy Digital Downloads integration
        if (class_exists('Easy_Digital_Downloads')) {
            return $this->apply_edd_user_discount($code);
        }
        
        // Generic fallback - store for later use
        return $this->store_discount_for_checkout($code);
    }

    /**
     * Check discount code with e-commerce platforms
     */
    private function check_discount_with_ecommerce($code) {
        // WooCommerce validation
        if (class_exists('WooCommerce')) {
            $coupon = new WC_Coupon($code);
            if ($coupon->get_id()) {
                $valid = $coupon->is_valid();
                return [
                    'success' => true,
                    'valid' => $valid,
                    'message' => $valid ? 
                        sprintf(__('Coupon "%s" is valid', 'affiliate-client-full'), $code) :
                        __('Coupon is not currently valid', 'affiliate-client-full'),
                    'code' => $code,
                    'platform' => 'woocommerce',
                ];
            }
        }

        // Easy Digital Downloads validation
        if (function_exists('edd_get_discount_id_by_code')) {
            $discount_id = edd_get_discount_id_by_code($code);
            if ($discount_id) {
                $valid = !edd_is_discount_expired($discount_id) && edd_is_discount_active($discount_id);
                return [
                    'success' => true,
                    'valid' => $valid,
                    'message' => $valid ? 
                        sprintf(__('Discount "%s" is valid', 'affiliate-client-full'), $code) :
                        __('Discount is expired or inactive', 'affiliate-client-full'),
                    'code' => $code,
                    'platform' => 'edd',
                ];
            }
        }

        return null; // No e-commerce platform found
    }

    /**
     * Generic code validation
     */
    private function generic_code_validation($code) {
        // Basic format validation
        if (strlen($code) < 3 || strlen($code) > 50) {
            return false;
        }

        // Check for valid characters (alphanumeric and common symbols)
        if (!preg_match('/^[A-Z0-9\-_]+$/', $code)) {
            return false;
        }

        return true;
    }

    /**
     * Apply WooCommerce user coupon
     */
    private function apply_woocommerce_user_coupon($code) {
        if (!WC()->cart) {
            return [
                'success' => false,
                'message' => __('Cart not available. Please add items to cart first.', 'affiliate-client-full'),
                'action' => 'redirect_to_shop',
            ];
        }

        // Check if coupon exists
        $coupon = new WC_Coupon($code);
        if (!$coupon->get_id()) {
            return [
                'success' => false,
                'message' => __('Invalid coupon code.', 'affiliate-client-full'),
            ];
        }

        // Remove existing coupons if any
        WC()->cart->remove_coupons();

        // Apply new coupon
        try {
            $result = WC()->cart->apply_coupon($code);
            if ($result) {
                return [
                    'success' => true,
                    'message' => sprintf(__('Coupon "%s" applied successfully!', 'affiliate-client-full'), $code),
                    'action' => 'applied',
                    'savings' => WC()->cart->get_discount_total(),
                    'redirect_url' => wc_get_checkout_url(),
                ];
            } else {
                $notices = wc_get_notices('error');
                $error_message = !empty($notices) ? $notices[0]['notice'] : __('Failed to apply coupon.', 'affiliate-client-full');
                wc_clear_notices();
                
                return [
                    'success' => false,
                    'message' => $error_message,
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Apply EDD user discount
     */
    private function apply_edd_user_discount($code) {
        if (!function_exists('edd_set_cart_discount')) {
            return [
                'success' => false,
                'message' => __('Discount system not available.', 'affiliate-client-full'),
            ];
        }

        // Check if discount exists
        $discount_id = edd_get_discount_id_by_code($code);
        if (!$discount_id) {
            return [
                'success' => false,
                'message' => __('Invalid discount code.', 'affiliate-client-full'),
            ];
        }

        // Remove existing discounts
        edd_unset_all_cart_discounts();

        // Apply new discount
        $result = edd_set_cart_discount($code);
        if ($result) {
            return [
                'success' => true,
                'message' => sprintf(__('Discount "%s" applied successfully!', 'affiliate-client-full'), $code),
                'action' => 'applied',
                'savings' => edd_get_cart_discounted_amount(),
                'redirect_url' => edd_get_checkout_uri(),
            ];
        } else {
            return [
                'success' => false,
                'message' => __('Failed to apply discount.', 'affiliate-client-full'),
            ];
        }
    }

    /**
     * Store discount for checkout (generic fallback)
     */
    private function store_discount_for_checkout($code) {
        // Store in cookie for later use
        setcookie('affiliate_user_discount', $code, time() + (24 * 60 * 60), '/');
        
        // Store in session if available
        if (!session_id()) {
            session_start();
        }
        $_SESSION['affiliate_user_discount'] = $code;

        return [
            'success' => true,
            'message' => sprintf(__('Discount code "%s" saved. Apply it during checkout.', 'affiliate-client-full'), $code),
            'action' => 'stored',
            'code' => $code,
        ];
    }

    /**
     * Track discount form actions
     */
    private function track_discount_action($action, $code) {
        // Get main plugin instance to access tracking handler
        $main_plugin = affiliate_client_full();
        if ($main_plugin && $main_plugin->tracking_handler) {
            $main_plugin->tracking_handler->track_event('discount_form_action', [
                'action' => $action,
                'code' => $code,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
        }
    }

    /**
     * Get discount form usage statistics
     */
    public function get_form_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                JSON_EXTRACT(data, '$.action') as action_type,
                JSON_EXTRACT(data, '$.code') as discount_code,
                COUNT(*) as action_count,
                COUNT(DISTINCT affiliate_id) as unique_users
            FROM {$table_name}
            WHERE event_type = 'discount_form_action'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY JSON_EXTRACT(data, '$.action'), JSON_EXTRACT(data, '$.code')
            ORDER BY action_count DESC
        ", $days));
        
        $formatted_stats = [
            'total_validations' => 0,
            'total_applications' => 0,
            'unique_codes' => [],
            'success_rate' => 0,
        ];
        
        foreach ($stats as $stat) {
            $action = trim($stat->action_type, '"');
            $code = trim($stat->discount_code, '"');
            $count = intval($stat->action_count);
            
            if ($action === 'validate') {
                $formatted_stats['total_validations'] += $count;
            } elseif ($action === 'apply') {
                $formatted_stats['total_applications'] += $count;
            }
            
            if (!in_array($code, $formatted_stats['unique_codes'])) {
                $formatted_stats['unique_codes'][] = $code;
            }
        }
        
        // Calculate success rate
        if ($formatted_stats['total_validations'] > 0) {
            $formatted_stats['success_rate'] = round(
                ($formatted_stats['total_applications'] / $formatted_stats['total_validations']) * 100, 
                2
            );
        }
        
        $formatted_stats['unique_codes_count'] = count($formatted_stats['unique_codes']);
        
        return $formatted_stats;
    }
}