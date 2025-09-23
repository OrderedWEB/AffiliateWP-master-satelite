<?php
/**
 * Shortcodes Handler for Affiliate Client Integration
 * 
 * Path: /wp-content/plugins/affiliate-client-integration/includes/class-shortcodes.php
 * Plugin: Affiliate Client Integration (Satellite)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Shortcodes {

    /**
     * Plugin instance
     */
    private $plugin;

    /**
     * Session manager
     */
    private $session_manager;

    /**
     * Price calculator
     */
    private $price_calculator;

    /**
     * Registered shortcodes
     */
    private $shortcodes = [
        'affiliate_discount_form',
        'affiliate_popup_trigger', 
        'affiliate_url_parameter',
        'affiliate_price_display',
        'affiliate_banner',
        'affiliate_countdown',
        'affiliate_testimonial',
        'affiliate_comparison',
        'affiliate_stats',
        'affiliate_progress_bar'
    ];

    /**
     * Constructor
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->session_manager = new ACI_Session_Manager();
        $this->price_calculator = new ACI_Price_Calculator($plugin->get_settings());
        
        $this->register_shortcodes();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_shortcode_assets']);
    }

    /**
     * Register all shortcodes
     */
    private function register_shortcodes() {
        foreach ($this->shortcodes as $shortcode) {
            $method_name = 'shortcode_' . str_replace('affiliate_', '', $shortcode);
            if (method_exists($this, $method_name)) {
                add_shortcode($shortcode, [$this, $method_name]);
            }
        }
    }

    /**
     * Enqueue shortcode-specific assets
     */
    public function enqueue_shortcode_assets() {
        // Only enqueue if shortcodes are used on the page
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'affiliate_')) {
            return;
        }

        wp_enqueue_script('aci-shortcodes');
        wp_enqueue_style('aci-shortcodes');
    }

    /**
     * Discount form shortcode
     * [affiliate_discount_form style="inline" button_text="Apply" placeholder="Enter code"]
     */
    public function shortcode_discount_form($atts, $content = '') {
        $atts = shortcode_atts([
            'style' => 'inline',
            'button_text' => __('Apply Discount', 'affiliate-client-integration'),
            'placeholder' => __('Enter affiliate code', 'affiliate-client-integration'),
            'show_labels' => 'true',
            'auto_apply' => 'false',
            'class' => '',
            'id' => '',
            'required' => 'false',
            'maxlength' => '50',
            'size' => 'medium'
        ], $atts, 'affiliate_discount_form');

        // Sanitise attributes
        $style = Sanitise_text_field($atts['style']);
        $button_text = Sanitise_text_field($atts['button_text']);
        $placeholder = Sanitise_text_field($atts['placeholder']);
        $show_labels = filter_var($atts['show_labels'], FILTER_VALIDATE_BOOLEAN);
        $auto_apply = filter_var($atts['auto_apply'], FILTER_VALIDATE_BOOLEAN);
        $class = Sanitise_html_class($atts['class']);
        $id = Sanitise_html_class($atts['id']);
        $required = filter_var($atts['required'], FILTER_VALIDATE_BOOLEAN);
        $maxlength = intval($atts['maxlength']);
        $size = Sanitise_text_field($atts['size']);

        // Generate unique ID if not provided
        if (empty($id)) {
            $id = 'aci-form-' . wp_generate_uuid4();
        }

        // Get current affiliate data from session
        $current_code = $this->session_manager->get_affiliate_data('code');
        $discount_applied = $this->session_manager->get_affiliate_data('discount_applied');

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-discount-form-wrapper aci-style-<?php echo esc_attr($style); ?> aci-size-<?php echo esc_attr($size); ?> <?php echo esc_attr($class); ?>">
            
            <?php if ($discount_applied): ?>
                <!-- Show applied discount -->
                <div class="aci-discount-applied">
                    <div class="aci-discount-message">
                        <span class="aci-success-icon">✓</span>
                        <span class="aci-discount-text">
                            <?php printf(__('Discount applied: %s', 'affiliate-client-integration'), esc_html($current_code)); ?>
                        </span>
                        <button type="button" class="aci-remove-discount aci-btn-link">
                            <?php _e('Remove', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Show discount form -->
                <form class="aci-affiliate-form aci-shortcode-form" id="<?php echo esc_attr($id); ?>" method="post" novalidate>
                    <?php wp_nonce_field('aci_validate_code', 'aci_nonce'); ?>
                    
                    <?php if ($style === 'stacked'): ?>
                        <div class="aci-form-group aci-stacked">
                            <?php if ($show_labels): ?>
                                <label for="<?php echo esc_attr($id); ?>_input" class="aci-form-label">
                                    <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                                    <?php if ($required): ?>
                                        <span class="aci-required">*</span>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>
                            
                            <div class="aci-input-group">
                                <input 
                                    type="text" 
                                    id="<?php echo esc_attr($id); ?>_input"
                                    name="affiliate_code"
                                    class="aci-code-input aci-form-control" 
                                    placeholder="<?php echo esc_attr($placeholder); ?>"
                                    value="<?php echo esc_attr($current_code); ?>"
                                    maxlength="<?php echo esc_attr($maxlength); ?>"
                                    <?php echo $required ? 'required' : ''; ?>
                                    <?php echo $auto_apply ? 'data-auto-apply="true"' : ''; ?>
                                />
                                <div class="aci-input-feedback">
                                    <span class="aci-loading-spinner" style="display: none;"></span>
                                    <span class="aci-success-icon" style="display: none;">✓</span>
                                    <span class="aci-error-icon" style="display: none;">✗</span>
                                </div>
                            </div>
                            
                            <button type="submit" class="aci-apply-btn aci-btn aci-btn-primary">
                                <span class="aci-btn-text"><?php echo esc_html($button_text); ?></span>
                                <span class="aci-btn-loading" style="display: none;">
                                    <span class="aci-spinner"></span>
                                    <?php _e('Applying...', 'affiliate-client-integration'); ?>
                                </span>
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Inline style -->
                        <div class="aci-form-group aci-inline">
                            <?php if ($show_labels): ?>
                                <label for="<?php echo esc_attr($id); ?>_input" class="aci-form-label aci-sr-only">
                                    <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                                </label>
                            <?php endif; ?>
                            
                            <div class="aci-input-group aci-input-group-connected">
                                <input 
                                    type="text" 
                                    id="<?php echo esc_attr($id); ?>_input"
                                    name="affiliate_code"
                                    class="aci-code-input aci-form-control" 
                                    placeholder="<?php echo esc_attr($placeholder); ?>"
                                    value="<?php echo esc_attr($current_code); ?>"
                                    maxlength="<?php echo esc_attr($maxlength); ?>"
                                    <?php echo $required ? 'required' : ''; ?>
                                    <?php echo $auto_apply ? 'data-auto-apply="true"' : ''; ?>
                                />
                                <button type="submit" class="aci-apply-btn aci-btn aci-btn-primary aci-btn-connected">
                                    <span class="aci-btn-text"><?php echo esc_html($button_text); ?></span>
                                    <span class="aci-btn-loading" style="display: none;">
                                        <span class="aci-spinner"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="aci-message-container" role="alert" aria-live="polite"></div>
                    
                    <?php if (!empty($content)): ?>
                        <div class="aci-form-description">
                            <?php echo wp_kses_post($content); ?>
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Popup trigger shortcode
     * [affiliate_popup_trigger text="Have a code?" style="button" popup_type="default"]
     */
    public function shortcode_popup_trigger($atts, $content = '') {
        $atts = shortcode_atts([
            'text' => __('Have an affiliate code?', 'affiliate-client-integration'),
            'style' => 'link',
            'popup_type' => 'default',
            'class' => '',
            'size' => 'medium',
            'icon' => 'tag'
        ], $atts, 'affiliate_popup_trigger');

        $text = Sanitise_text_field($atts['text']);
        $style = Sanitise_text_field($atts['style']);
        $popup_type = Sanitise_text_field($atts['popup_type']);
        $class = Sanitise_html_class($atts['class']);
        $size = Sanitise_text_field($atts['size']);
        $icon = Sanitise_text_field($atts['icon']);

        $icon_html = '';
        if (!empty($icon)) {
            $icons = [
                'tag' => '🏷️',
                'discount' => '💰',
                'percent' => '%',
                'gift' => '🎁',
                'star' => '⭐'
            ];
            $icon_html = isset($icons[$icon]) ? '<span class="aci-trigger-icon">' . $icons[$icon] . '</span>' : '';
        }

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-popup-trigger-wrapper">
            <?php if ($style === 'button'): ?>
                <button type="button" class="aci-popup-trigger aci-btn aci-btn-secondary aci-size-<?php echo esc_attr($size); ?> <?php echo esc_attr($class); ?>" 
                        data-popup-type="<?php echo esc_attr($popup_type); ?>">
                    <?php echo $icon_html; ?>
                    <span class="aci-trigger-text"><?php echo esc_html($text); ?></span>
                </button>
            <?php else: ?>
                <a href="#" class="aci-popup-trigger aci-trigger-link <?php echo esc_attr($class); ?>" 
                   data-popup-type="<?php echo esc_attr($popup_type); ?>">
                    <?php echo $icon_html; ?>
                    <span class="aci-trigger-text"><?php echo esc_html($text); ?></span>
                </a>
            <?php endif; ?>
            
            <?php if (!empty($content)): ?>
                <div class="aci-trigger-description">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * URL parameter shortcode
     * [affiliate_url_parameter parameter="ref" default="" display_type="hidden"]
     */
    public function shortcode_url_parameter($atts, $content = '') {
        $atts = shortcode_atts([
            'parameter' => 'affiliate_code',
            'default' => '',
            'display_type' => 'hidden',
            'auto_apply' => 'true',
            'class' => ''
        ], $atts, 'affiliate_url_parameter');

        $parameter = Sanitise_text_field($atts['parameter']);
        $default = Sanitise_text_field($atts['default']);
        $display_type = Sanitise_text_field($atts['display_type']);
        $auto_apply = filter_var($atts['auto_apply'], FILTER_VALIDATE_BOOLEAN);
        $class = Sanitise_html_class($atts['class']);

        // Get parameter value from URL
        $value = isset($_GET[$parameter]) ? Sanitise_text_field($_GET[$parameter]) : $default;

        if (empty($value)) {
            return '';
        }

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-url-parameter-wrapper <?php echo esc_attr($class); ?>">
            <?php if ($display_type === 'visible'): ?>
                <div class="aci-url-parameter-display">
                    <span class="aci-parameter-label">
                        <?php printf(__('Code: %s', 'affiliate-client-integration'), esc_html($value)); ?>
                    </span>
                    <?php if ($auto_apply): ?>
                        <button type="button" class="aci-apply-url-code aci-btn aci-btn-small" data-code="<?php echo esc_attr($value); ?>">
                            <?php _e('Apply', 'affiliate-client-integration'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <input type="hidden" class="aci-url-parameter" 
                       data-parameter="<?php echo esc_attr($parameter); ?>" 
                       data-code="<?php echo esc_attr($value); ?>"
                       <?php echo $auto_apply ? 'data-auto-apply="true"' : ''; ?> />
            <?php endif; ?>
        </div>
        
        <?php if ($auto_apply): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof ACI !== 'undefined' && ACI.api) {
                    ACI.api.validateCode('<?php echo esc_js($value); ?>');
                }
            });
            </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Price display shortcode
     * [affiliate_price_display original_price="100" show_discount="true" currency="USD"]
     */
    public function shortcode_price_display($atts, $content = '') {
        $atts = shortcode_atts([
            'original_price' => '0',
            'show_discount' => 'true',
            'show_savings' => 'true',
            'currency' => 'USD',
            'format' => 'standard',
            'class' => '',
            'product_id' => '',
            'auto_calculate' => 'true'
        ], $atts, 'affiliate_price_display');

        $original_price = floatval($atts['original_price']);
        $show_discount = filter_var($atts['show_discount'], FILTER_VALIDATE_BOOLEAN);
        $show_savings = filter_var($atts['show_savings'], FILTER_VALIDATE_BOOLEAN);
        $currency = Sanitise_text_field($atts['currency']);
        $format = Sanitise_text_field($atts['format']);
        $class = Sanitise_html_class($atts['class']);
        $product_id = Sanitise_text_field($atts['product_id']);
        $auto_calculate = filter_var($atts['auto_calculate'], FILTER_VALIDATE_BOOLEAN);

        if ($original_price <= 0) {
            return '<div class="aci-error">Invalid price specified</div>';
        }

        // Get current affiliate code
        $affiliate_code = $this->session_manager->get_affiliate_data('code');
        $discount_applied = false;
        $discounted_price = $original_price;
        $savings = 0;

        // Calculate discount if code exists and auto_calculate is enabled
        if ($auto_calculate && !empty($affiliate_code)) {
            $calculation = $this->price_calculator->calculate_discounted_price(
                $original_price, 
                $affiliate_code,
                ['product_id' => $product_id, 'currency' => $currency]
            );

            if (!is_wp_error($calculation) && $calculation['discount_applied']) {
                $discount_applied = true;
                $discounted_price = $calculation['final_price'];
                $savings = $calculation['savings'];
            }
        }

        $unique_id = 'aci-price-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-price-display-wrapper aci-format-<?php echo esc_attr($format); ?> <?php echo esc_attr($class); ?>" 
             id="<?php echo esc_attr($unique_id); ?>"
             data-original-price="<?php echo esc_attr($original_price); ?>"
             data-currency="<?php echo esc_attr($currency); ?>"
             data-product-id="<?php echo esc_attr($product_id); ?>">
            
            <div class="aci-price-container">
                <?php if ($discount_applied && $show_discount): ?>
                    <span class="aci-original-price aci-strikethrough">
                        <?php echo $this->format_currency($original_price, $currency); ?>
                    </span>
                    <span class="aci-discounted-price aci-primary-price">
                        <?php echo $this->format_currency($discounted_price, $currency); ?>
                    </span>
                    <?php if ($show_savings && $savings > 0): ?>
                        <span class="aci-savings aci-savings-amount">
                            <?php printf(__('Save %s', 'affiliate-client-integration'), 
                                       $this->format_currency($savings, $currency)); ?>
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="aci-current-price aci-primary-price">
                        <?php echo $this->format_currency($original_price, $currency); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($content)): ?>
                <div class="aci-price-description">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Affiliate banner shortcode
     * [affiliate_banner code="SAVE20" title="Special Offer" description="Get 20% off"]
     */
    public function shortcode_banner($atts, $content = '') {
        $atts = shortcode_atts([
            'code' => '',
            'title' => __('Special Offer', 'affiliate-client-integration'),
            'description' => '',
            'style' => 'default',
            'size' => 'medium',
            'background' => '#f0f8ff',
            'color' => '#333',
            'show_copy' => 'true',
            'show_apply' => 'true',
            'class' => ''
        ], $atts, 'affiliate_banner');

        $code = Sanitise_text_field($atts['code']);
        $title = Sanitise_text_field($atts['title']);
        $description = Sanitise_text_field($atts['description']);
        $style = Sanitise_text_field($atts['style']);
        $size = Sanitise_text_field($atts['size']);
        $background = Sanitise_hex_color($atts['background']);
        $color = Sanitise_hex_color($atts['color']);
        $show_copy = filter_var($atts['show_copy'], FILTER_VALIDATE_BOOLEAN);
        $show_apply = filter_var($atts['show_apply'], FILTER_VALIDATE_BOOLEAN);
        $class = Sanitise_html_class($atts['class']);

        if (empty($code)) {
            return '<div class="aci-error">Affiliate code is required for banner</div>';
        }

        $banner_id = 'aci-banner-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-banner-wrapper aci-style-<?php echo esc_attr($style); ?> aci-size-<?php echo esc_attr($size); ?> <?php echo esc_attr($class); ?>" 
             id="<?php echo esc_attr($banner_id); ?>"
             style="background-color: <?php echo esc_attr($background); ?>; color: <?php echo esc_attr($color); ?>;">
            
            <div class="aci-banner-content">
                <div class="aci-banner-text">
                    <h3 class="aci-banner-title"><?php echo esc_html($title); ?></h3>
                    <?php if (!empty($description)): ?>
                        <p class="aci-banner-description"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($content)): ?>
                        <div class="aci-banner-custom">
                            <?php echo wp_kses_post($content); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="aci-banner-code">
                    <div class="aci-code-display">
                        <span class="aci-code-label"><?php _e('Code:', 'affiliate-client-integration'); ?></span>
                        <span class="aci-code-value" data-code="<?php echo esc_attr($code); ?>"><?php echo esc_html($code); ?></span>
                    </div>
                    
                    <div class="aci-banner-actions">
                        <?php if ($show_copy): ?>
                            <button type="button" class="aci-copy-code aci-btn aci-btn-outline" data-code="<?php echo esc_attr($code); ?>">
                                <span class="aci-copy-icon">📋</span>
                                <span class="aci-copy-text"><?php _e('Copy', 'affiliate-client-integration'); ?></span>
                                <span class="aci-copied-text" style="display: none;"><?php _e('Copied!', 'affiliate-client-integration'); ?></span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($show_apply): ?>
                            <button type="button" class="aci-apply-code aci-btn aci-btn-primary" data-code="<?php echo esc_attr($code); ?>">
                                <?php _e('Apply Now', 'affiliate-client-integration'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format currency for display
     */
    private function format_currency($amount, $currency = 'USD') {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'JPY' => '¥'
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';
        return $symbol . number_format($amount, 2);
    }

    /**
     * Countdown timer shortcode
     * [affiliate_countdown expires="2024-12-31 23:59:59" code="URGENT50"]
     */
    public function shortcode_countdown($atts, $content = '') {
        $atts = shortcode_atts([
            'expires' => '',
            'code' => '',
            'title' => __('Limited Time Offer', 'affiliate-client-integration'),
            'style' => 'default',
            'size' => 'medium',
            'show_labels' => 'true',
            'class' => ''
        ], $atts, 'affiliate_countdown');

        $expires = Sanitise_text_field($atts['expires']);
        $code = Sanitise_text_field($atts['code']);
        $title = Sanitise_text_field($atts['title']);
        $style = Sanitise_text_field($atts['style']);
        $size = Sanitise_text_field($atts['size']);
        $show_labels = filter_var($atts['show_labels'], FILTER_VALIDATE_BOOLEAN);
        $class = Sanitise_html_class($atts['class']);

        if (empty($expires)) {
            return '<div class="aci-error">Expiry date is required for countdown</div>';
        }

        $expiry_timestamp = strtotime($expires);
        if ($expiry_timestamp === false || $expiry_timestamp <= time()) {
            return '<div class="aci-expired">This offer has expired</div>';
        }

        $countdown_id = 'aci-countdown-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-countdown-wrapper aci-style-<?php echo esc_attr($style); ?> aci-size-<?php echo esc_attr($size); ?> <?php echo esc_attr($class); ?>" 
             id="<?php echo esc_attr($countdown_id); ?>"
             data-expires="<?php echo esc_attr($expiry_timestamp); ?>"
             data-code="<?php echo esc_attr($code); ?>">
            
            <div class="aci-countdown-header">
                <h3 class="aci-countdown-title"><?php echo esc_html($title); ?></h3>
                <?php if (!empty($code)): ?>
                    <div class="aci-countdown-code">
                        <?php printf(__('Use code: %s', 'affiliate-client-integration'), '<strong>' . esc_html($code) . '</strong>'); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="aci-countdown-timer">
                <div class="aci-time-unit">
                    <span class="aci-time-value" data-unit="days">00</span>
                    <?php if ($show_labels): ?>
                        <span class="aci-time-label"><?php _e('Days', 'affiliate-client-integration'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="aci-time-separator">:</div>
                <div class="aci-time-unit">
                    <span class="aci-time-value" data-unit="hours">00</span>
                    <?php if ($show_labels): ?>
                        <span class="aci-time-label"><?php _e('Hours', 'affiliate-client-integration'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="aci-time-separator">:</div>
                <div class="aci-time-unit">
                    <span class="aci-time-value" data-unit="minutes">00</span>
                    <?php if ($show_labels): ?>
                        <span class="aci-time-label"><?php _e('Min', 'affiliate-client-integration'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="aci-time-separator">:</div>
                <div class="aci-time-unit">
                    <span class="aci-time-value" data-unit="seconds">00</span>
                    <?php if ($show_labels): ?>
                        <span class="aci-time-label"><?php _e('Sec', 'affiliate-client-integration'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($content)): ?>
                <div class="aci-countdown-description">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var countdown = document.getElementById('<?php echo esc_js($countdown_id); ?>');
            var expires = <?php echo intval($expiry_timestamp); ?> * 1000;
            
            function updateCountdown() {
                var now = new Date().getTime();
                var distance = expires - now;
                
                if (distance < 0) {
                    countdown.innerHTML = '<div class="aci-expired"><?php _e('This offer has expired', 'affiliate-client-integration'); ?></div>';
                    return;
                }
                
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                countdown.querySelector('[data-unit="days"]').textContent = days.toString().padStart(2, '0');
                countdown.querySelector('[data-unit="hours"]').textContent = hours.toString().padStart(2, '0');
                countdown.querySelector('[data-unit="minutes"]').textContent = minutes.toString().padStart(2, '0');
                countdown.querySelector('[data-unit="seconds"]').textContent = seconds.toString().padStart(2, '0');
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1