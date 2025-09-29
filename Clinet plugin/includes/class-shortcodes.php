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

        // Sanitize attributes
        $style = sanitize_text_field($atts['style']);
        $button_text = sanitize_text_field($atts['button_text']);
        $placeholder = sanitize_text_field($atts['placeholder']);
        $show_labels = filter_var($atts['show_labels'], FILTER_VALIDATE_BOOLEAN);
        $auto_apply = filter_var($atts['auto_apply'], FILTER_VALIDATE_BOOLEAN);
        $class = sanitize_html_class($atts['class']);
        $id = sanitize_html_class($atts['id']);
        $required = filter_var($atts['required'], FILTER_VALIDATE_BOOLEAN);
        $maxlength = intval($atts['maxlength']);
        $size = sanitize_text_field($atts['size']);

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

        $text = sanitize_text_field($atts['text']);
        $style = sanitize_text_field($atts['style']);
        $popup_type = sanitize_text_field($atts['popup_type']);
        $class = sanitize_html_class($atts['class']);
        $size = sanitize_text_field($atts['size']);
        $icon = sanitize_text_field($atts['icon']);

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

        $parameter = sanitize_text_field($atts['parameter']);
        $default = sanitize_text_field($atts['default']);
        $display_type = sanitize_text_field($atts['display_type']);
        $auto_apply = filter_var($atts['auto_apply'], FILTER_VALIDATE_BOOLEAN);
        $class = sanitize_html_class($atts['class']);

        // Get parameter value from URL
        $value = isset($_GET[$parameter]) ? sanitize_text_field($_GET[$parameter]) : $default;

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
        $currency = sanitize_text_field($atts['currency']);
        $format = sanitize_text_field($atts['format']);
        $class = sanitize_html_class($atts['class']);
        $product_id = sanitize_text_field($atts['product_id']);
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

        $code = sanitize_text_field($atts['code']);
        $title = sanitize_text_field($atts['title']);
        $description = sanitize_text_field($atts['description']);
        $style = sanitize_text_field($atts['style']);
        $size = sanitize_text_field($atts['size']);
        $background = sanitize_hex_color($atts['background']);
        $color = sanitize_hex_color($atts['color']);
        $show_copy = filter_var($atts['show_copy'], FILTER_VALIDATE_BOOLEAN);
        $show_apply = filter_var($atts['show_apply'], FILTER_VALIDATE_BOOLEAN);
        $class = sanitize_html_class($atts['class']);

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

        $expires = sanitize_text_field($atts['expires']);
        $code = sanitize_text_field($atts['code']);
        $title = sanitize_text_field($atts['title']);
        $style = sanitize_text_field($atts['style']);
        $size = sanitize_text_field($atts['size']);
        $show_labels = filter_var($atts['show_labels'], FILTER_VALIDATE_BOOLEAN);
        $class = sanitize_html_class($atts['class']);

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
            setInterval(updateCountdown, 1000);
        });
        </script>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Testimonial shortcode
     * [affiliate_testimonial author="John Doe" rating="5" image="url"]Content[/affiliate_testimonial]
     */
    public function shortcode_testimonial($atts, $content = '') {
        $atts = shortcode_atts([
            'author' => '',
            'company' => '',
            'image' => '',
            'rating' => '',
            'style' => 'default',
            'size' => 'medium',
            'class' => ''
        ], $atts, 'affiliate_testimonial');

        $author = sanitize_text_field($atts['author']);
        $company = sanitize_text_field($atts['company']);
        $image = esc_url($atts['image']);
        $rating = intval($atts['rating']);
        $style = sanitize_text_field($atts['style']);
        $size = sanitize_text_field($atts['size']);
        $class = sanitize_html_class($atts['class']);

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-testimonial-wrapper aci-style-<?php echo esc_attr($style); ?> aci-size-<?php echo esc_attr($size); ?> <?php echo esc_attr($class); ?>">
            <div class="aci-testimonial">
                <?php if ($rating > 0): ?>
                    <div class="aci-testimonial-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="aci-star <?php echo $i <= $rating ? 'filled' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                
                <div class="aci-testimonial-content">
                    <?php echo wp_kses_post($content); ?>
                </div>
                
                <div class="aci-testimonial-author">
                    <?php if (!empty($image)): ?>
                        <div class="aci-author-image">
                            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($author); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="aci-author-info">
                        <?php if (!empty($author)): ?>
                            <div class="aci-author-name"><?php echo esc_html($author); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($company)): ?>
                            <div class="aci-author-company"><?php echo esc_html($company); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Comparison table shortcode
     * [affiliate_comparison features="Feature 1,Feature 2" product1="Product A" product2="Product B"]
     */
    public function shortcode_comparison($atts, $content = '') {
        $atts = shortcode_atts([
            'features' => '',
            'product1' => '',
            'product2' => '',
            'product3' => '',
            'style' => 'default',
            'highlight' => '',
            'class' => ''
        ], $atts, 'affiliate_comparison');

        $features = array_filter(array_map('trim', explode(',', $atts['features'])));
        $products = array_filter([
            $atts['product1'],
            $atts['product2'],
            $atts['product3']
        ]);
        
        $style = sanitize_text_field($atts['style']);
        $highlight = sanitize_text_field($atts['highlight']);
        $class = sanitize_html_class($atts['class']);

        if (empty($features) || empty($products)) {
            return '<div class="aci-error">Features and products are required for comparison</div>';
        }

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-comparison-wrapper aci-style-<?php echo esc_attr($style); ?> <?php echo esc_attr($class); ?>">
            <div class="aci-comparison-table">
                <table>
                    <thead>
                        <tr>
                            <th class="aci-feature-header"><?php _e('Features', 'affiliate-client-integration'); ?></th>
                            <?php foreach ($products as $index => $product): ?>
                                <th class="aci-product-header <?php echo $highlight === (string)($index + 1) ? 'highlighted' : ''; ?>">
                                    <?php echo esc_html($product); ?>
                                    <?php if ($highlight === (string)($index + 1)): ?>
                                        <span class="aci-recommended"><?php _e('Recommended', 'affiliate-client-integration'); ?></span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($features as $feature): ?>
                            <tr>
                                <td class="aci-feature-name"><?php echo esc_html($feature); ?></td>
                                <?php foreach ($products as $index => $product): ?>
                                    <td class="aci-product-feature <?php echo $highlight === (string)($index + 1) ? 'highlighted' : ''; ?>">
                                        <span class="aci-check">✓</span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($content)): ?>
                <div class="aci-comparison-footer">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Stats display shortcode
     * [affiliate_stats clicks="1234" conversions="56" revenue="12345.67"]
     */
    public function shortcode_stats($atts, $content = '') {
        $atts = shortcode_atts([
            'clicks' => '0',
            'conversions' => '0',
            'revenue' => '0',
            'currency' => 'EUR',
            'style' => 'default',
            'layout' => 'grid',
            'class' => ''
        ], $atts, 'affiliate_stats');

        $clicks = sanitize_text_field($atts['clicks']);
        $conversions = sanitize_text_field($atts['conversions']);
        $revenue = sanitize_text_field($atts['revenue']);
        $currency = sanitize_text_field($atts['currency']);
        $style = sanitize_text_field($atts['style']);
        $layout = sanitize_text_field($atts['layout']);
        $class = sanitize_html_class($atts['class']);

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-stats-wrapper aci-style-<?php echo esc_attr($style); ?> aci-layout-<?php echo esc_attr($layout); ?> <?php echo esc_attr($class); ?>">
            <div class="aci-stats-grid">
                <div class="aci-stat-item">
                    <div class="aci-stat-icon">👁️</div>
                    <div class="aci-stat-value"><?php echo esc_html(number_format_i18n($clicks)); ?></div>
                    <div class="aci-stat-label"><?php _e('Total Clicks', 'affiliate-client-integration'); ?></div>
                </div>
                
                <div class="aci-stat-item">
                    <div class="aci-stat-icon">✅</div>
                    <div class="aci-stat-value"><?php echo esc_html(number_format_i18n($conversions)); ?></div>
                    <div class="aci-stat-label"><?php _e('Conversions', 'affiliate-client-integration'); ?></div>
                </div>
                
                <div class="aci-stat-item">
                    <div class="aci-stat-icon">💰</div>
                    <div class="aci-stat-value"><?php echo esc_html($this->format_price($revenue, $currency)); ?></div>
                    <div class="aci-stat-label"><?php _e('Revenue', 'affiliate-client-integration'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($content)): ?>
                <div class="aci-stats-description">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Progress bar shortcode
     * [affiliate_progress_bar value="75" max="100" label="Progress"]
     */
    public function shortcode_progress_bar($atts, $content = '') {
        $atts = shortcode_atts([
            'value' => '0',
            'max' => '100',
            'label' => '',
            'color' => '#4CAF50',
            'style' => 'default',
            'size' => 'medium',
            'show_percentage' => 'true',
            'class' => ''
        ], $atts, 'affiliate_progress_bar');

        $value = floatval($atts['value']);
        $max = floatval($atts['max']);
        $label = sanitize_text_field($atts['label']);
        $color = sanitize_hex_color($atts['color']);
        $style = sanitize_text_field($atts['style']);
        $size = sanitize_text_field($atts['size']);
        $show_percentage = filter_var($atts['show_percentage'], FILTER_VALIDATE_BOOLEAN);
        $class = sanitize_html_class($atts['class']);

        $percentage = $max > 0 ? min(100, ($value / $max) * 100) : 0;

        ob_start();
        ?>
        <div class="aci-shortcode-wrapper aci-progress-wrapper aci-style-<?php echo esc_attr($style); ?> aci-size-<?php echo esc_attr($size); ?> <?php echo esc_attr($class); ?>">
            <?php if (!empty($label)): ?>
                <div class="aci-progress-header">
                    <span class="aci-progress-label"><?php echo esc_html($label); ?></span>
                    <?php if ($show_percentage): ?>
                        <span class="aci-progress-percentage"><?php echo number_format($percentage, 1); ?>%</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="aci-progress-bar-container">
                <div class="aci-progress-bar" 
                     style="width: <?php echo esc_attr($percentage); ?>%; background-color: <?php echo esc_attr($color); ?>;"
                     role="progressbar" 
                     aria-valuenow="<?php echo esc_attr($value); ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="<?php echo esc_attr($max); ?>">
                </div>
            </div>
            
            <?php if (!empty($content)): ?>
                <div class="aci-progress-description">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Enqueue shortcode assets
     */
    public function enqueue_shortcode_assets() {
        if (!$this->shortcode_assets_needed()) {
            return;
        }

        wp_enqueue_style(
            'aci-shortcodes',
            ACI_PLUGIN_URL . 'assets/css/shortcodes.css',
            [],
            ACI_VERSION
        );

        wp_enqueue_script(
            'aci-shortcodes',
            ACI_PLUGIN_URL . 'assets/js/shortcodes.js',
            ['jquery'],
            ACI_VERSION,
            true
        );

        wp_localize_script('aci-shortcodes', 'aciShortcodes', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aci_shortcode_nonce'),
            'i18n' => [
                'validating' => __('Validating...', 'affiliate-client-integration'),
                'submitting' => __('Processing...', 'affiliate-client-integration'),
                'success' => __('Success!', 'affiliate-client-integration'),
                'error' => __('An error occurred. Please try again.', 'affiliate-client-integration'),
                'invalidCode' => __('Invalid affiliate code.', 'affiliate-client-integration'),
                'requiredField' => __('This field is required.', 'affiliate-client-integration'),
                'invalidEmail' => __('Please enter a valid email address.', 'affiliate-client-integration'),
                'copied' => __('Copied to clipboard!', 'affiliate-client-integration')
            ],
            'settings' => [
                'validateRealtime' => $this->settings['validate_realtime'] ?? true,
                'showNotifications' => $this->settings['show_notifications'] ?? true,
                'trackInteractions' => $this->settings['track_interactions'] ?? true
            ]
        ]);
    }

    /**
     * Mark that shortcode assets are needed
     */
    private function mark_shortcode_assets_needed() {
        if (!isset($GLOBALS['aci_shortcode_assets_needed'])) {
            $GLOBALS['aci_shortcode_assets_needed'] = true;
        }
    }

    /**
     * Check if shortcode assets are needed
     */
    private function shortcode_assets_needed() {
        return isset($GLOBALS['aci_shortcode_assets_needed']) && $GLOBALS['aci_shortcode_assets_needed'];
    }

    /**
     * Get active discount data
     */
    private function get_active_discount_data() {
        if (class_exists('WooCommerce') && WC()->session) {
            return WC()->session->get('aci_affiliate_discount');
        }
        
        return apply_filters('aci_get_active_discount_data', null);
    }

    /**
     * Format price for display
     */
    private function format_price($amount, $currency = 'EUR') {
        $currency_symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'JPY' => '¥',
        ];
        
        $symbol = $currency_symbols[$currency] ?? $currency . ' ';
        return $symbol . number_format($amount, 2);
    }

    /**
     * AJAX: Validate affiliate code
     */
    public function ajax_validate_code() {
        check_ajax_referer('aci_shortcode_nonce', 'nonce');
        
        $affiliate_code = sanitize_text_field($_POST['affiliate_code'] ?? '');
        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        
        if (empty($affiliate_code)) {
            wp_send_json_error([
                'message' => __('Affiliate code is required.', 'affiliate-client-integration')
            ]);
        }

        $api_handler = new ACI_API_Handler();
        $result = $api_handler->validate_affiliate_code($affiliate_code);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        if ($result['valid']) {
            do_action('aci_affiliate_code_validated', $affiliate_code, $result);
            
            wp_send_json_success([
                'message' => __('Affiliate code is valid.', 'affiliate-client-integration'),
                'data' => $result
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Invalid affiliate code.', 'affiliate-client-integration')
            ]);
        }
    }

    /**
     * AJAX: Submit affiliate form
     */
    public function ajax_submit_form() {
        check_ajax_referer('aci_shortcode_nonce', 'nonce');
        
        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        $affiliate_code = sanitize_text_field($_POST['affiliate_code'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $additional_data = $_POST['data'] ?? [];

        if (empty($affiliate_code)) {
            wp_send_json_error([
                'message' => __('Affiliate code is required.', 'affiliate-client-integration')
            ]);
        }

        $api_handler = new ACI_API_Handler();
        
        $submission_data = [
            'code' => $affiliate_code,
            'email' => $email,
            'form_id' => $form_id,
            'additional_data' => $additional_data,
            'ip_address' => $this->get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql')
        ];

        $result = $api_handler->submit_affiliate_form($submission_data);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        if ($result['success']) {
            do_action('aci_affiliate_form_submitted', $submission_data, $result);
            
            wp_send_json_success([
                'message' => __('Form submitted successfully!', 'affiliate-client-integration'),
                'data' => $result
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? __('Form submission failed.', 'affiliate-client-integration')
            ]);
        }
    }

    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}