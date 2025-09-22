<?php
/**
 * Discount Code Shortcode and Gutenberg Block
 *
 * Provides shortcode and Gutenberg block functionality for displaying
 * and applying discount codes with affiliate tracking integration.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFILIATE_CLIENT_Discount_Shortcode {

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
     * Initialse shortcode and block functionality
     */
    public function init() {
        // Register shortcodes
        add_shortcode('affiliate_discount', [$this, 'discount_shortcode']);
        add_shortcode('affiliate_coupon', [$this, 'discount_shortcode']); // Alias
        
        // Gutenberg block support
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_affiliate_client_apply_discount', [$this, 'ajax_apply_discount']);
        add_action('wp_ajax_nopriv_affiliate_client_apply_discount', [$this, 'ajax_apply_discount']);
        
        // REST API endpoint
        add_action('rest_api_init', [$this, 'register_rest_endpoints']);
    }

    /**
     * Discount code shortcode
     *
     * Usage: [affiliate_discount code="SAVE20" type="coupon" affiliate_id="123" style="modern"]
     */
    public function discount_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'code' => '',
            'type' => 'coupon', // coupon, discount, promo
            'affiliate_id' => '',
            'title' => '',
            'description' => '',
            'expiry' => '',
            'style' => 'default', // default, modern, minimal, card
            'color' => '#4CAF50',
            'text_color' => '#ffffff',
            'size' => 'medium', // small, medium, large
            'show_copy' => 'true',
            'show_apply' => 'true',
            'auto_apply' => 'false',
            'track_clicks' => 'true',
            'class' => '',
            'button_text' => '',
            'copy_text' => '',
            'success_text' => '',
        ], $atts, 'affiliate_discount');

        // Sanitize attributes
        $atts = array_map('sanitize_text_field', $atts);
        $atts['show_copy'] = filter_var($atts['show_copy'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_apply'] = filter_var($atts['show_apply'], FILTER_VALIDATE_BOOLEAN);
        $atts['auto_apply'] = filter_var($atts['auto_apply'], FILTER_VALIDATE_BOOLEAN);
        $atts['track_clicks'] = filter_var($atts['track_clicks'], FILTER_VALIDATE_BOOLEAN);

        if (empty($atts['code'])) {
            return '<p class="affiliate-discount-error">' . __('Discount code is required.', 'affiliate-client-full') . '</p>';
        }

        // Generate unique ID for this instance
        $instance_id = 'affiliate-discount-' . uniqid();

        // Get current affiliate ID if not specified
        if (empty($atts['affiliate_id'])) {
            $atts['affiliate_id'] = $this->get_current_affiliate_id();
        }

        // Build the discount code widget
        return $this->render_discount_widget($instance_id, $atts, $content);
    }

    /**
     * Render discount code widget
     */
    private function render_discount_widget($instance_id, $atts, $content = '') {
        $classes = ['affiliate-discount-widget', 'style-' . $atts['style'], 'size-' . $atts['size']];
        if (!empty($atts['class'])) {
            $classes[] = $atts['class'];
        }

        // Default text values
        $button_text = !empty($atts['button_text']) ? $atts['button_text'] : __('Apply Code', 'affiliate-client-full');
        $copy_text = !empty($atts['copy_text']) ? $atts['copy_text'] : __('Copy Code', 'affiliate-client-full');
        $success_text = !empty($atts['success_text']) ? $atts['success_text'] : __('Code copied!', 'affiliate-client-full');

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" 
             class="<?php echo esc_attr(implode(' ', $classes)); ?>"
             data-code="<?php echo esc_attr($atts['code']); ?>"
             data-type="<?php echo esc_attr($atts['type']); ?>"
             data-affiliate-id="<?php echo esc_attr($atts['affiliate_id']); ?>"
             data-track-clicks="<?php echo $atts['track_clicks'] ? 'true' : 'false'; ?>"
             data-auto-apply="<?php echo $atts['auto_apply'] ? 'true' : 'false'; ?>"
             style="--primary-color: <?php echo esc_attr($atts['color']); ?>; --text-color: <?php echo esc_attr($atts['text_color']); ?>;">
            
            <?php if (!empty($atts['title'])): ?>
                <div class="affiliate-discount-title">
                    <?php echo esc_html($atts['title']); ?>
                </div>
            <?php endif; ?>

            <div class="affiliate-discount-content">
                <div class="affiliate-discount-code-section">
                    <span class="affiliate-discount-label"><?php echo esc_html(strtoupper($atts['type'])); ?> <?php _e('CODE:', 'affiliate-client-full'); ?></span>
                    <div class="affiliate-discount-code-container">
                        <span class="affiliate-discount-code"><?php echo esc_html($atts['code']); ?></span>
                        
                        <?php if ($atts['show_copy']): ?>
                            <button type="button" 
                                    class="affiliate-discount-copy-btn" 
                                    data-copy-text="<?php echo esc_attr($copy_text); ?>"
                                    data-success-text="<?php echo esc_attr($success_text); ?>"
                                    title="<?php echo esc_attr($copy_text); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                                </svg>
                                <span class="copy-text"><?php echo esc_html($copy_text); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($atts['description']) || !empty($content)): ?>
                    <div class="affiliate-discount-description">
                        <?php echo !empty($content) ? do_shortcode($content) : esc_html($atts['description']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($atts['expiry'])): ?>
                    <div class="affiliate-discount-expiry">
                        <span class="expiry-label"><?php _e('Expires:', 'affiliate-client-full'); ?></span>
                        <span class="expiry-date"><?php echo esc_html($atts['expiry']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($atts['show_apply']): ?>
                    <div class="affiliate-discount-actions">
                        <button type="button" class="affiliate-discount-apply-btn">
                            <?php echo esc_html($button_text); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="affiliate-discount-feedback" style="display: none;"></div>
        </div>

        <?php if ($atts['auto_apply']): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof AffiliateClientDiscount !== 'undefined') {
                        AffiliateClientDiscount.autoApply('<?php echo esc_js($instance_id); ?>');
                    }
                });
            </script>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('affiliate-client/discount-code', [
            'editor_script' => 'affiliate-client-discount-block',
            'editor_style' => 'affiliate-client-discount-block-editor',
            'style' => 'affiliate-client-discount-block',
            'render_callback' => [$this, 'render_block'],
            'attributes' => [
                'code' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'type' => [
                    'type' => 'string',
                    'default' => 'coupon',
                ],
                'affiliateId' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'title' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'description' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'expiry' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'style' => [
                    'type' => 'string',
                    'default' => 'default',
                ],
                'color' => [
                    'type' => 'string',
                    'default' => '#4CAF50',
                ],
                'textColor' => [
                    'type' => 'string',
                    'default' => '#ffffff',
                ],
                'size' => [
                    'type' => 'string',
                    'default' => 'medium',
                ],
                'showCopy' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showApply' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'autoApply' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'trackClicks' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'buttonText' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'copyText' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'successText' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    /**
     * Render Gutenberg block
     */
    public function render_block($attributes) {
        // Convert camelCase to snake_case for shortcode
        $shortcode_atts = [
            'code' => $attributes['code'] ?? '',
            'type' => $attributes['type'] ?? 'coupon',
            'affiliate_id' => $attributes['affiliateId'] ?? '',
            'title' => $attributes['title'] ?? '',
            'description' => $attributes['description'] ?? '',
            'expiry' => $attributes['expiry'] ?? '',
            'style' => $attributes['style'] ?? 'default',
            'color' => $attributes['color'] ?? '#4CAF50',
            'text_color' => $attributes['textColor'] ?? '#ffffff',
            'size' => $attributes['size'] ?? 'medium',
            'show_copy' => $attributes['showCopy'] ?? true,
            'show_apply' => $attributes['showApply'] ?? true,
            'auto_apply' => $attributes['autoApply'] ?? false,
            'track_clicks' => $attributes['trackClicks'] ?? true,
            'button_text' => $attributes['buttonText'] ?? '',
            'copy_text' => $attributes['copyText'] ?? '',
            'success_text' => $attributes['successText'] ?? '',
        ];

        $instance_id = 'affiliate-discount-block-' . uniqid();
        return $this->render_discount_widget($instance_id, $shortcode_atts);
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'affiliate-client-discount-block',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/discount-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_enqueue_style(
            'affiliate-client-discount-block-editor',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/discount-block-editor.css',
            [],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_enqueue_style(
            'affiliate-client-discount-block',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/discount-block.css',
            [],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_localize_script('affiliate-client-discount-block', 'affiliateClientDiscountBlock', [
            'apiUrl' => rest_url('affiliate-client/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentAffiliateId' => $this->get_current_affiliate_id(),
        ]);
    }

    /**
     * Register REST API endpoints
     */
    public function register_rest_endpoints() {
        register_rest_route('affiliate-client/v1', '/discount/apply', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_apply_discount'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'coupon',
                ],
                'affiliate_id' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    /**
     * AJAX handler for applying discount
     */
    public function ajax_apply_discount() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $code = sanitize_text_field($_POST['code']);
        $type = sanitize_text_field($_POST['type'] ?? 'coupon');
        $affiliate_id = sanitize_text_field($_POST['affiliate_id'] ?? '');

        $result = $this->apply_discount_code($code, $type, $affiliate_id);
        wp_send_json($result);
    }

    /**
     * REST API handler for applying discount
     */
    public function rest_apply_discount($request) {
        $code = sanitize_text_field($request->get_param('code'));
        $type = sanitize_text_field($request->get_param('type'));
        $affiliate_id = sanitize_text_field($request->get_param('affiliate_id'));

        $result = $this->apply_discount_code($code, $type, $affiliate_id);
        return rest_ensure_response($result);
    }

    /**
     * Apply discount code logic
     */
    private function apply_discount_code($code, $type, $affiliate_id) {
        // Track the discount code usage
        $this->track_discount_usage($code, $type, $affiliate_id);

        // Integration with supported e-commerce plugins
        $result = ['success' => false, 'message' => ''];

        // WooCommerce integration
        if (class_exists('WooCommerce')) {
            $result = $this->apply_woocommerce_coupon($code);
        }
        // Easy Digital Downloads integration
        elseif (class_exists('Easy_Digital_Downloads')) {
            $result = $this->apply_edd_discount($code);
        }
        // Generic fallback
        else {
            $result = [
                'success' => true,
                'message' => sprintf(__('Discount code %s has been noted. Apply it during checkout.', 'affiliate-client-full'), $code),
                'action' => 'manual',
            ];
        }

        // Store the discount code in session/cookie for later use
        if ($result['success']) {
            setcookie('affiliate_discount_code', $code, time() + (24 * 60 * 60), '/');
        }

        return $result;
    }

    /**
     * Apply WooCommerce coupon
     */
    private function apply_woocommerce_coupon($code) {
        if (!WC()->cart) {
            return [
                'success' => false,
                'message' => __('Cart not available. Please add items to cart first.', 'affiliate-client-full'),
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

        // Apply coupon to cart
        try {
            $result = WC()->cart->apply_coupon($code);
            if ($result) {
                return [
                    'success' => true,
                    'message' => sprintf(__('Coupon "%s" applied successfully!', 'affiliate-client-full'), $code),
                    'action' => 'applied',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Failed to apply coupon. Please try again.', 'affiliate-client-full'),
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
     * Apply Easy Digital Downloads discount
     */
    private function apply_edd_discount($code) {
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

        // Apply discount
        $result = edd_set_cart_discount($code);
        if ($result) {
            return [
                'success' => true,
                'message' => sprintf(__('Discount "%s" applied successfully!', 'affiliate-client-full'), $code),
                'action' => 'applied',
            ];
        } else {
            return [
                'success' => false,
                'message' => __('Failed to apply discount. Please try again.', 'affiliate-client-full'),
            ];
        }
    }

    /**
     * Track discount code usage
     */
    private function track_discount_usage($code, $type, $affiliate_id) {
        // Get tracking handler from main plugin
        $main_plugin = affiliate_client_full();
        if ($main_plugin && $main_plugin->tracking_handler) {
            $main_plugin->tracking_handler->track_event('discount_code_used', [
                'code' => $code,
                'type' => $type,
                'affiliate_id' => $affiliate_id,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            ]);
        }
    }

    /**
     * Get current affiliate ID
     */
    private function get_current_affiliate_id() {
        // Get from cookie
        if (isset($_COOKIE[$this->config['cookie_name']])) {
            return intval($_COOKIE[$this->config['cookie_name']]);
        }

        // Get from tracking handler
        $main_plugin = affiliate_client_full();
        if ($main_plugin && $main_plugin->tracking_handler) {
            return $main_plugin->tracking_handler->get_current_affiliate_id();
        }

        return null;
    }

    /**
     * Get discount statistics
     */
    public function get_discount_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                JSON_EXTRACT(data, '$.code') as discount_code,
                JSON_EXTRACT(data, '$.type') as discount_type,
                COUNT(*) as usage_count,
                COUNT(DISTINCT affiliate_id) as unique_affiliates
            FROM {$table_name}
            WHERE event_type = 'discount_code_used'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY JSON_EXTRACT(data, '$.code'), JSON_EXTRACT(data, '$.type')
            ORDER BY usage_count DESC
        ", $days));
        
        $formatted_stats = [];
        foreach ($stats as $stat) {
            $code = trim($stat->discount_code, '"');
            $type = trim($stat->discount_type, '"');
            
            $formatted_stats[] = [
                'code' => $code,
                'type' => $type,
                'usage_count' => intval($stat->usage_count),
                'unique_affiliates' => intval($stat->unique_affiliates),
            ];
        }
        
        return $formatted_stats;
    }
}