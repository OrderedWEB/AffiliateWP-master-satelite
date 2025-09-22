<?php
/**
 * Shortcode Manager for Affiliate Client Integration
 * 
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-shortcode-manager.php
 * 
 * Manages all shortcodes for affiliate functionality including forms,
 * popups, discount displays, and URL parameter handling.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Shortcode_Manager {

    private $api_handler;
    private $popup_manager;
    private $url_handler;
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_handler = new ACI_API_Handler();
        $this->popup_manager = new ACI_Popup_Manager();
        $this->url_handler = new ACI_URL_Handler();
        $this->settings = get_option('aci_settings', []);
        
        // Register shortcodes
        add_action('init', [$this, 'register_shortcodes']);
        
        // Enqueue shortcode assets
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_shortcode_assets']);
        
        // AJAX handlers for shortcode functionality
        add_action('wp_ajax_aci_shortcode_validate', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_nopriv_aci_shortcode_validate', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_aci_shortcode_submit', [$this, 'ajax_submit_form']);
        add_action('wp_ajax_nopriv_aci_shortcode_submit', [$this, 'ajax_submit_form']);
    }

    /**
     * Register all shortcodes
     */
    public function register_shortcodes() {
        // Form shortcodes
        add_shortcode('affiliate_form', [$this, 'affiliate_form_shortcode']);
        add_shortcode('affiliate_form_inline', [$this, 'affiliate_form_inline_shortcode']);
        add_shortcode('affiliate_discount_form', [$this, 'discount_form_shortcode']);
        
        // Display shortcodes
        add_shortcode('affiliate_discount_display', [$this, 'discount_display_shortcode']);
        add_shortcode('affiliate_success_message', [$this, 'success_message_shortcode']);
        add_shortcode('affiliate_code_info', [$this, 'code_info_shortcode']);
        
        // Popup shortcodes (extend existing)
        add_shortcode('affiliate_popup_trigger', [$this, 'popup_trigger_shortcode']);
        add_shortcode('affiliate_popup_form', [$this, 'popup_form_shortcode']);
        
        // URL parameter shortcodes (extend existing)
        add_shortcode('affiliate_url_param', [$this, 'url_parameter_shortcode']);
        add_shortcode('affiliate_referrer_info', [$this, 'referrer_info_shortcode']);
        
        // Conditional display shortcodes
        add_shortcode('affiliate_if_active', [$this, 'conditional_active_shortcode']);
        add_shortcode('affiliate_if_code', [$this, 'conditional_code_shortcode']);
        
        // Integration shortcodes
        add_shortcode('affiliate_woocommerce_notice', [$this, 'woocommerce_notice_shortcode']);
        add_shortcode('affiliate_tracking_pixel', [$this, 'tracking_pixel_shortcode']);
        
        // Analytics shortcodes
        add_shortcode('affiliate_stats', [$this, 'stats_shortcode']);
        add_shortcode('affiliate_conversion_tracker', [$this, 'conversion_tracker_shortcode']);
    }

    /**
     * Affiliate form shortcode
     */
    public function affiliate_form_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'id' => 'aci-form-' . uniqid(),
            'style' => 'default', // default, compact, minimal, custom
            'title' => __('Enter Affiliate Code', 'affiliate-client-integration'),
            'description' => __('Enter your affiliate code to unlock exclusive discounts.', 'affiliate-client-integration'),
            'submit_text' => __('Apply Code', 'affiliate-client-integration'),
            'placeholder' => __('Enter code here...', 'affiliate-client-integration'),
            'show_description' => 'true',
            'collect_email' => 'false',
            'require_email' => 'false',
            'redirect_url' => '',
            'success_message' => __('Affiliate code applied successfully!', 'affiliate-client-integration'),
            'class' => 'aci-affiliate-form',
            'ajax' => 'true',
            'validate_realtime' => 'true'
        ], $atts);

        // Generate unique form ID
        $form_id = sanitize_html_class($atts['id']);
        
        // Mark that we need shortcode assets
        $this->mark_shortcode_assets_needed();

        ob_start();
        ?>
        <div class="aci-form-wrapper aci-style-<?php echo esc_attr($atts['style']); ?>">
            <form id="<?php echo esc_attr($form_id); ?>" 
                  class="<?php echo esc_attr($atts['class']); ?>" 
                  data-ajax="<?php echo esc_attr($atts['ajax']); ?>"
                  data-validate-realtime="<?php echo esc_attr($atts['validate_realtime']); ?>"
                  data-redirect="<?php echo esc_url($atts['redirect_url']); ?>">
                
                <?php wp_nonce_field('aci_shortcode_nonce', 'aci_nonce'); ?>
                
                <?php if (!empty($atts['title'])): ?>
                    <h3 class="aci-form-title"><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>
                
                <?php if ($atts['show_description'] === 'true' && !empty($atts['description'])): ?>
                    <p class="aci-form-description"><?php echo esc_html($atts['description']); ?></p>
                <?php endif; ?>
                
                <div class="aci-form-fields">
                    <div class="aci-field-group">
                        <label for="<?php echo esc_attr($form_id); ?>_code" class="aci-field-label">
                            <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                        </label>
                        <input type="text" 
                               id="<?php echo esc_attr($form_id); ?>_code"
                               name="affiliate_code" 
                               class="aci-affiliate-code-input" 
                               placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                               required>
                        <div class="aci-field-feedback"></div>
                    </div>
                    
                    <?php if ($atts['collect_email'] === 'true'): ?>
                        <div class="aci-field-group">
                            <label for="<?php echo esc_attr($form_id); ?>_email" class="aci-field-label">
                                <?php _e('Email Address', 'affiliate-client-integration'); ?>
                                <?php if ($atts['require_email'] === 'true'): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="email" 
                                   id="<?php echo esc_attr($form_id); ?>_email"
                                   name="user_email" 
                                   class="aci-user-email-input" 
                                   placeholder="<?php esc_attr_e('your@email.com', 'affiliate-client-integration'); ?>"
                                   <?php echo $atts['require_email'] === 'true' ? 'required' : ''; ?>>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="aci-form-actions">
                    <button type="submit" class="aci-submit-button">
                        <?php echo esc_html($atts['submit_text']); ?>
                    </button>
                </div>
                
                <div class="aci-form-messages" style="display: none;"></div>
                
                <?php if (!empty($content)): ?>
                    <div class="aci-form-content">
                        <?php echo do_shortcode($content); ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            ACI_ShortcodeForms.initForm('<?php echo esc_js($form_id); ?>', <?php echo json_encode([
                'successMessage' => $atts['success_message'],
                'redirectUrl' => $atts['redirect_url'],
                'validateRealtime' => $atts['validate_realtime'] === 'true',
                'collectEmail' => $atts['collect_email'] === 'true'
            ]); ?>);
        });
        </script>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Inline affiliate form shortcode
     */
    public function affiliate_form_inline_shortcode($atts, $content = '') {
        $default_atts = [
            'style' => 'inline',
            'show_description' => 'false',
            'class' => 'aci-affiliate-form aci-inline-form'
        ];
        
        $atts = array_merge($default_atts, $atts);
        return $this->affiliate_form_shortcode($atts, $content);
    }

    /**
     * Discount form shortcode
     */
    public function discount_form_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'discount_type' => 'percentage', // percentage, fixed
            'discount_amount' => '10',
            'show_discount_preview' => 'true',
            'title' => __('Get Your Discount', 'affiliate-client-integration'),
            'description' => __('Enter your affiliate code to see your discount.', 'affiliate-client-integration')
        ], $atts);

        $form_atts = array_merge($atts, [
            'style' => 'discount',
            'class' => 'aci-affiliate-form aci-discount-form'
        ]);

        ob_start();
        echo $this->affiliate_form_shortcode($form_atts, $content);
        
        if ($atts['show_discount_preview'] === 'true') {
            ?>
            <div class="aci-discount-preview" style="display: none;">
                <div class="aci-discount-badge">
                    <span class="aci-discount-amount">
                        <?php echo $atts['discount_type'] === 'percentage' ? 
                            esc_html($atts['discount_amount']) . '%' : 
                            '$' . esc_html($atts['discount_amount']); ?>
                    </span>
                    <span class="aci-discount-label"><?php _e('OFF', 'affiliate-client-integration'); ?></span>
                </div>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }

    /**
     * Discount display shortcode
     */
    public function discount_display_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'format' => 'badge', // badge, text, card
            'show_code' => 'true',
            'show_amount' => 'true',
            'prefix' => '',
            'suffix' => '',
            'class' => 'aci-discount-display'
        ], $atts);

        // Check if there's an active affiliate
        $affiliate_data = $this->url_handler->get_active_affiliate_data();
        
        if (empty($affiliate_data)) {
            return '';
        }

        // Get discount data
        $discount_data = $this->get_active_discount_data();
        
        if (!$discount_data) {
            return '';
        }

        $this->mark_shortcode_assets_needed();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?> aci-format-<?php echo esc_attr($atts['format']); ?>">
            <?php echo esc_html($atts['prefix']); ?>
            
            <?php if ($atts['show_code'] === 'true'): ?>
                <span class="aci-affiliate-code"><?php echo esc_html($affiliate_data['affiliate_code']); ?></span>
            <?php endif; ?>
            
            <?php if ($atts['show_amount'] === 'true' && $discount_data): ?>
                <span class="aci-discount-amount">
                    <?php echo $discount_data['type'] === 'percentage' ? 
                        esc_html($discount_data['amount']) . '% OFF' : 
                        '$' . esc_html($discount_data['amount']) . ' OFF'; ?>
                </span>
            <?php endif; ?>
            
            <?php echo esc_html($atts['suffix']); ?>
            
            <?php if (!empty($content)): ?>
                <div class="aci-discount-content">
                    <?php echo do_shortcode($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Success message shortcode
     */
    public function success_message_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'message' => __('Affiliate code applied successfully!', 'affiliate-client-integration'),
            'show_code' => 'true',
            'show_discount' => 'true',
            'auto_hide' => 'false',
            'hide_delay' => '5000',
            'class' => 'aci-success-message'
        ], $atts);

        // Only show if there's an active affiliate
        $affiliate_data = $this->url_handler->get_active_affiliate_data();
        
        if (empty($affiliate_data)) {
            return '';
        }

        $this->mark_shortcode_assets_needed();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>"
             <?php if ($atts['auto_hide'] === 'true'): ?>
                data-auto-hide="<?php echo esc_attr($atts['hide_delay']); ?>"
             <?php endif; ?>>
            
            <div class="aci-success-icon">✓</div>
            
            <div class="aci-success-content">
                <p class="aci-success-text"><?php echo esc_html($atts['message']); ?></p>
                
                <?php if ($atts['show_code'] === 'true'): ?>
                    <p class="aci-success-code">
                        <?php printf(__('Code: %s', 'affiliate-client-integration'), 
                                   '<strong>' . esc_html($affiliate_data['affiliate_code']) . '</strong>'); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($atts['show_discount'] === 'true'): ?>
                    <?php $discount_data = $this->get_active_discount_data(); ?>
                    <?php if ($discount_data): ?>
                        <p class="aci-success-discount">
                            <?php printf(__('Discount: %s', 'affiliate-client-integration'), 
                                       '<strong>' . ($discount_data['type'] === 'percentage' ? 
                                           $discount_data['amount'] . '%' : 
                                           '$' . $discount_data['amount']) . '</strong>'); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($content)): ?>
                    <div class="aci-success-extra">
                        <?php echo do_shortcode($content); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Code info shortcode
     */
    public function code_info_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'show' => 'all', // code, affiliate, all
            'format' => 'list', // list, table, inline
            'class' => 'aci-code-info'
        ], $atts);

        $affiliate_data = $this->url_handler->get_active_affiliate_data();
        
        if (empty($affiliate_data)) {
            return '';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?> aci-format-<?php echo esc_attr($atts['format']); ?>">
            <?php if ($atts['format'] === 'table'): ?>
                <table class="aci-info-table">
                    <?php if ($atts['show'] === 'code' || $atts['show'] === 'all'): ?>
                        <tr>
                            <th><?php _e('Affiliate Code:', 'affiliate-client-integration'); ?></th>
                            <td><?php echo esc_html($affiliate_data['affiliate_code']); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($atts['show'] === 'affiliate' || $atts['show'] === 'all'): ?>
                        <tr>
                            <th><?php _e('Affiliate ID:', 'affiliate-client-integration'); ?></th>
                            <td><?php echo esc_html($affiliate_data['affiliate_id']); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($affiliate_data['detected_at'])): ?>
                        <tr>
                            <th><?php _e('Applied:', 'affiliate-client-integration'); ?></th>
                            <td><?php echo human_time_diff($affiliate_data['detected_at']) . ' ' . __('ago', 'affiliate-client-integration'); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            <?php else: ?>
                <ul class="aci-info-list">
                    <?php if ($atts['show'] === 'code' || $atts['show'] === 'all'): ?>
                        <li class="aci-info-code">
                            <strong><?php _e('Code:', 'affiliate-client-integration'); ?></strong> 
                            <?php echo esc_html($affiliate_data['affiliate_code']); ?>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($atts['show'] === 'affiliate' || $atts['show'] === 'all'): ?>
                        <li class="aci-info-affiliate">
                            <strong><?php _e('Affiliate ID:', 'affiliate-client-integration'); ?></strong> 
                            <?php echo esc_html($affiliate_data['affiliate_id']); ?>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (!empty($content)): ?>
                <div class="aci-info-content">
                    <?php echo do_shortcode($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Popup trigger shortcode
     */
    public function popup_trigger_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'popup_id' => '',
            'text' => __('Enter Affiliate Code', 'affiliate-client-integration'),
            'class' => 'aci-popup-trigger',
            'button_style' => 'button', // button, link, custom
            'size' => 'medium' // small, medium, large
        ], $atts);

        if (empty($atts['popup_id'])) {
            return '';
        }

        $this->mark_shortcode_assets_needed();

        $button_text = !empty($content) ? do_shortcode($content) : esc_html($atts['text']);
        $css_classes = $atts['class'] . ' aci-button-' . $atts['button_style'] . ' aci-size-' . $atts['size'];

        return sprintf(
            '<button type="button" class="%s" data-popup="%s">%s</button>',
            esc_attr($css_classes),
            esc_attr($atts['popup_id']),
            $button_text
        );
    }

    /**
     * URL parameter shortcode
     */
    public function url_parameter_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'parameter' => 'affiliate',
            'default' => '',
            'format' => 'text', // text, hidden_field, display_only
            'prefix' => '',
            'suffix' => '',
            'class' => 'aci-url-parameter'
        ], $atts);

        $param_value = $_GET[$atts['parameter']] ?? $atts['default'];
        
        if (empty($param_value) && empty($content)) {
            return '';
        }

        $display_value = $param_value ?: do_shortcode($content);

        switch ($atts['format']) {
            case 'hidden_field':
                return sprintf(
                    '<input type="hidden" name="%s" value="%s" class="%s">',
                    esc_attr($atts['parameter']),
                    esc_attr($display_value),
                    esc_attr($atts['class'])
                );
                
            case 'display_only':
                return sprintf(
                    '<span class="%s" data-parameter="%s">%s%s%s</span>',
                    esc_attr($atts['class']),
                    esc_attr($atts['parameter']),
                    esc_html($atts['prefix']),
                    esc_html($display_value),
                    esc_html($atts['suffix'])
                );
                
            default:
                return sprintf(
                    '<span class="%s">%s%s%s</span>',
                    esc_attr($atts['class']),
                    esc_html($atts['prefix']),
                    esc_html($display_value),
                    esc_html($atts['suffix'])
                );
        }
    }

    /**
     * Referrer info shortcode
     */
    public function referrer_info_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'show' => 'domain', // domain, full_url, utm_source, utm_campaign
            'default' => __('Direct', 'affiliate-client-integration'),
            'class' => 'aci-referrer-info'
        ], $atts);

        $referrer_data = $_SESSION['aci_referrer_data'] ?? [];
        
        if (empty($referrer_data)) {
            return sprintf('<span class="%s">%s</span>', esc_attr($atts['class']), esc_html($atts['default']));
        }

        $display_value = '';
        
        switch ($atts['show']) {
            case 'domain':
                $display_value = $referrer_data['referrer_domain'] ?? $atts['default'];
                break;
            case 'full_url':
                $display_value = $referrer_data['referrer'] ?? $atts['default'];
                break;
            case 'utm_source':
                $display_value = $_GET['utm_source'] ?? $atts['default'];
                break;
            case 'utm_campaign':
                $display_value = $_GET['utm_campaign'] ?? $atts['default'];
                break;
            default:
                $display_value = $atts['default'];
        }

        return sprintf('<span class="%s">%s</span>', esc_attr($atts['class']), esc_html($display_value));
    }

    /**
     * Conditional active affiliate shortcode
     */
    public function conditional_active_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'show_if' => 'active', // active, inactive
            'affiliate_id' => '', // specific affiliate ID
            'code' => '' // specific affiliate code
        ], $atts);

        $affiliate_data = $this->url_handler->get_active_affiliate_data();
        $has_active = !empty($affiliate_data);
        
        // Check specific conditions
        if (!empty($atts['affiliate_id']) && $has_active) {
            $has_active = ($affiliate_data['affiliate_id'] == $atts['affiliate_id']);
        }
        
        if (!empty($atts['code']) && $has_active) {
            $has_active = ($affiliate_data['affiliate_code'] === $atts['code']);
        }

        // Show content based on condition
        if (($atts['show_if'] === 'active' && $has_active) || 
            ($atts['show_if'] === 'inactive' && !$has_active)) {
            return do_shortcode($content);
        }

        return '';
    }

    /**
     * Conditional code shortcode
     */
    public function conditional_code_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'code' => '',
            'parameter' => 'affiliate',
            'show_if' => 'matches' // matches, not_matches
        ], $atts);

        if (empty($atts['code'])) {
            return '';
        }

        $current_code = $_GET[$atts['parameter']] ?? '';
        $affiliate_data = $this->url_handler->get_active_affiliate_data();
        
        if (!$current_code && !empty($affiliate_data['affiliate_code'])) {
            $current_code = $affiliate_data['affiliate_code'];
        }

        $matches = ($current_code === $atts['code']);

        if (($atts['show_if'] === 'matches' && $matches) || 
            ($atts['show_if'] === 'not_matches' && !$matches)) {
            return do_shortcode($content);
        }

        return '';
    }

    /**
     * WooCommerce notice shortcode
     */
    public function woocommerce_notice_shortcode($atts, $content = '') {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $atts = shortcode_atts([
            'type' => 'success', // success, info, error
            'dismissible' => 'true',
            'show_discount' => 'true',
            'message' => __('Affiliate discount applied to your cart!', 'affiliate-client-integration')
        ], $atts);

        $affiliate_data = $this->url_handler->get_active_affiliate_data();
        $discount_data = $this->get_active_discount_data();
        
        if (empty($affiliate_data) || !$discount_data) {
            return '';
        }

        $this->mark_shortcode_assets_needed();

        ob_start();
        ?>
        <div class="woocommerce-message woocommerce-message--<?php echo esc_attr($atts['type']); ?> aci-woo-notice"
             <?php if ($atts['dismissible'] === 'true'): ?>data-dismissible="true"<?php endif; ?>>
            
            <?php echo esc_html($atts['message']); ?>
            
            <?php if ($atts['show_discount'] === 'true'): ?>
                <strong>
                    <?php echo $discount_data['type'] === 'percentage' ? 
                        $discount_data['amount'] . '% OFF' : 
                        '$' . $discount_data['amount'] . ' OFF'; ?>
                </strong>
            <?php endif; ?>
            
            <?php if ($atts['dismissible'] === 'true'): ?>
                <button type="button" class="aci-dismiss-notice">&times;</button>
            <?php endif; ?>
            
            <?php if (!empty($content)): ?>
                <div class="aci-notice-content">
                    <?php echo do_shortcode($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Tracking pixel shortcode
     */
    public function tracking_pixel_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'event' => 'page_view',
            'affiliate_required' => 'true',
            'send_to_master' => 'true'
        ], $atts);

        if ($atts['affiliate_required'] === 'true') {
            $affiliate_data = $this->url_handler->get_active_affiliate_data();
            if (empty($affiliate_data)) {
                return '';
            }
        }

        $tracking_id = 'aci-pixel-' . uniqid();
        
        ob_start();
        ?>
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" 
             id="<?php echo esc_attr($tracking_id); ?>" 
             class="aci-tracking-pixel" 
             style="width:1px;height:1px;position:absolute;left:-9999px;" 
             data-event="<?php echo esc_attr($atts['event']); ?>"
             data-send-master="<?php echo esc_attr($atts['send_to_master']); ?>">
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            ACI_TrackingPixel.track('<?php echo esc_js($atts['event']); ?>', {
                pixelId: '<?php echo esc_js($tracking_id); ?>',
                sendToMaster: <?php echo $atts['send_to_master'] === 'true' ? 'true' : 'false'; ?>
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Stats shortcode
     */
    public function stats_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'show' => 'summary', // summary, detailed, custom
            'period' => '30d',
            'affiliate_code' => '',
            'format' => 'list', // list, table, cards
            'class' => 'aci-stats-display',
            'cache' => 'true'
        ], $atts);

        // Get current affiliate code if not specified
        if (empty($atts['affiliate_code'])) {
            $affiliate_data = $this->url_handler->get_active_affiliate_data();
            $atts['affiliate_code'] = $affiliate_data['affiliate_code'] ?? '';
        }

        if (empty($atts['affiliate_code'])) {
            return '';
        }

        // Get stats from URL handler
        $stats = $this->url_handler->get_link_stats($atts['affiliate_code'], $atts['period']);
        
        if (!$stats) {
            return '<p class="aci-no-stats">' . __('No statistics available.', 'affiliate-client-integration') . '</p>';
        }

        $this->mark_shortcode_assets_needed();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?> aci-format-<?php echo esc_attr($atts['format']); ?>">
            <?php if ($atts['format'] === 'cards'): ?>
                <div class="aci-stats-cards">
                    <div class="aci-stat-card">
                        <div class="aci-stat-value"><?php echo number_format($stats['total_clicks']); ?></div>
                        <div class="aci-stat-label"><?php _e('Total Clicks', 'affiliate-client-integration'); ?></div>
                    </div>
                    <div class="aci-stat-card">
                        <div class="aci-stat-value"><?php echo number_format($stats['conversions']); ?></div>
                        <div class="aci-stat-label"><?php _e('Conversions', 'affiliate-client-integration'); ?></div>
                    </div>
                    <div class="aci-stat-card">
                        <div class="aci-stat-value">$<?php echo number_format($stats['revenue'], 2); ?></div>
                        <div class="aci-stat-label"><?php _e('Revenue', 'affiliate-client-integration'); ?></div>
                    </div>
                    <div class="aci-stat-card">
                        <div class="aci-stat-value"><?php echo $stats['conversion_rate']; ?>%</div>
                        <div class="aci-stat-label"><?php _e('Conversion Rate', 'affiliate-client-integration'); ?></div>
                    </div>
                </div>
            <?php elseif ($atts['format'] === 'table'): ?>
                <table class="aci-stats-table">
                    <tr>
                        <th><?php _e('Total Clicks', 'affiliate-client-integration'); ?></th>
                        <td><?php echo number_format($stats['total_clicks']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Unique Visitors', 'affiliate-client-integration'); ?></th>
                        <td><?php echo number_format($stats['unique_visitors']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Conversions', 'affiliate-client-integration'); ?></th>
                        <td><?php echo number_format($stats['conversions']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Revenue', 'affiliate-client-integration'); ?></th>
                        <td>$<?php echo number_format($stats['revenue'], 2); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Conversion Rate', 'affiliate-client-integration'); ?></th>
                        <td><?php echo $stats['conversion_rate']; ?>%</td>
                    </tr>
                    <tr>
                        <th><?php _e('Avg Order Value', 'affiliate-client-integration'); ?></th>
                        <td>$<?php echo number_format($stats['avg_order_value'], 2); ?></td>
                    </tr>
                </table>
            <?php else: ?>
                <ul class="aci-stats-list">
                    <li><strong><?php _e('Total Clicks:', 'affiliate-client-integration'); ?></strong> <?php echo number_format($stats['total_clicks']); ?></li>
                    <li><strong><?php _e('Conversions:', 'affiliate-client-integration'); ?></strong> <?php echo number_format($stats['conversions']); ?></li>
                    <li><strong><?php _e('Revenue:', 'affiliate-client-integration'); ?></strong> $<?php echo number_format($stats['revenue'], 2); ?></li>
                    <li><strong><?php _e('Conversion Rate:', 'affiliate-client-integration'); ?></strong> <?php echo $stats['conversion_rate']; ?>%</li>
                </ul>
            <?php endif; ?>
            
            <?php if (!empty($content)): ?>
                <div class="aci-stats-content">
                    <?php echo do_shortcode($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Conversion tracker shortcode
     */
    public function conversion_tracker_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'event' => 'purchase',
            'value' => '',
            'currency' => 'USD',
            'order_id' => '',
            'affiliate_required' => 'true',
            'send_to_master' => 'true'
        ], $atts);

        if ($atts['affiliate_required'] === 'true') {
            $affiliate_data = $this->url_handler->get_active_affiliate_data();
            if (empty($affiliate_data)) {
                return '';
            }
        }

        $tracker_id = 'aci-tracker-' . uniqid();
        
        ob_start();
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            ACI_ConversionTracker.track({
                event: '<?php echo esc_js($atts['event']); ?>',
                value: <?php echo floatval($atts['value'] ?: 0); ?>,
                currency: '<?php echo esc_js($atts['currency']); ?>',
                orderId: '<?php echo esc_js($atts['order_id']); ?>',
                trackerId: '<?php echo esc_js($tracker_id); ?>',
                sendToMaster: <?php echo $atts['send_to_master'] === 'true' ? 'true' : 'false'; ?>
            });
        });
        </script>
        
        <noscript>
            <img src="<?php echo admin_url('admin-ajax.php'); ?>?action=aci_track_conversion&event=<?php echo urlencode($atts['event']); ?>&value=<?php echo urlencode($atts['value']); ?>&noscript=1" 
                 style="width:1px;height:1px;position:absolute;left:-9999px;" alt="">
        </noscript>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Maybe enqueue shortcode assets
     */
    public function maybe_enqueue_shortcode_assets() {
        if (!$this->shortcode_assets_needed()) {
            return;
        }

        // Enqueue shortcode CSS
        wp_enqueue_style(
            'aci-shortcodes',
            ACI_PLUGIN_URL . 'assets/css/shortcodes.css',
            [],
            ACI_VERSION
        );

        // Enqueue shortcode JavaScript
        wp_enqueue_script(
            'aci-shortcodes',
            ACI_PLUGIN_URL . 'assets/js/shortcodes.js',
            ['jquery'],
            ACI_VERSION,
            true
        );

        // Localize script
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
                'invalidEmail' => __('Please enter a valid email address.', 'affiliate-client-integration')
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
        
        // Check for other discount systems
        return apply_filters('aci_get_active_discount_data', null);
    }

    /**
     * AJAX: Validate affiliate code
     */
    public function ajax_validate_code() {
        check_ajax_referer('aci_shortcode_nonce', 'aci_nonce');
        
        $affiliate_code = sanitize_text_field($_POST['affiliate_code'] ?? '');
        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        
        if (empty($affiliate_code)) {
            wp_send_json_error([
                'message' => __('Affiliate code is required.', 'affiliate-client-integration')
            ]);
        }

        // Validate code through API
        $validation_result = $this->api_handler->validate_affiliate_code($affiliate_code, [
            'source' => 'shortcode',
            'form_id' => $form_id
        ]);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error([
                'message' => $validation_result->get_error_message()
            ]);
        }

        if (!$validation_result['valid']) {
            wp_send_json_error([
                'message' => $validation_result['message'] ?? __('Invalid affiliate code.', 'affiliate-client-integration')
            ]);
        }

        wp_send_json_success([
            'message' => __('Valid affiliate code!', 'affiliate-client-integration'),
            'affiliate_data' => $validation_result,
            'discount_info' => $this->get_discount_info_for_code($validation_result)
        ]);
    }

    /**
     * AJAX: Submit shortcode form
     */
    public function ajax_submit_form() {
        check_ajax_referer('aci_shortcode_nonce', 'aci_nonce');
        
        $form_data = [
            'affiliate_code' => sanitize_text_field($_POST['affiliate_code'] ?? ''),
            'user_email' => sanitize_email($_POST['user_email'] ?? ''),
            'form_id' => sanitize_text_field($_POST['form_id'] ?? ''),
            'source' => 'shortcode_form'
        ];

        // Validate affiliate code first
        $validation_result = $this->api_handler->validate_affiliate_code($form_data['affiliate_code'], [
            'source' => 'shortcode_form',
            'form_id' => $form_data['form_id']
        ]);
        
        if (is_wp_error($validation_result) || !$validation_result['valid']) {
            wp_send_json_error([
                'message' => __('Invalid affiliate code.', 'affiliate-client-integration')
            ]);
        }

        // Process the form submission
        $submission_result = $this->process_shortcode_form_submission($form_data, $validation_result);
        
        if (is_wp_error($submission_result)) {
            wp_send_json_error([
                'message' => $submission_result->get_error_message()
            ]);
        }

        // Track the successful submission
        $this->track_shortcode_conversion($form_data, $validation_result);

        wp_send_json_success([
            'message' => __('Affiliate code applied successfully!', 'affiliate-client-integration'),
            'affiliate_data' => $validation_result,
            'discount_applied' => $submission_result['discount_applied'] ?? false,
            'redirect_url' => $submission_result['redirect_url'] ?? ''
        ]);
    }

    /**
     * Process shortcode form submission
     */
    private function process_shortcode_form_submission($form_data, $validation_result) {
        $result = [
            'discount_applied' => false,
            'redirect_url' => ''
        ];

        // Store affiliate data in URL handler
        $this->url_handler->store_affiliate_data($validation_result, 'shortcode_form');
        
        // Apply discount if configured
        $discount_settings = $this->settings['auto_discount'] ?? [];
        if (!empty($discount_settings['enabled'])) {
            $discount_result = $this->apply_shortcode_discount($validation_result, $discount_settings);
            if (!is_wp_error($discount_result)) {
                $result['discount_applied'] = true;
            }
        }

        // Handle email collection
        if (!empty($form_data['user_email'])) {
            $this->handle_shortcode_email_collection($form_data['user_email'], $validation_result);
        }

        // Send form data to master domain
        $this->api_handler->submit_form_data($form_data, $validation_result);

        return $result;
    }

    /**
     * Apply discount from shortcode
     */
    private function apply_shortcode_discount($validation_result, $discount_settings) {
        if (class_exists('WooCommerce')) {
            return $this->apply_woocommerce_shortcode_discount($validation_result, $discount_settings);
        }
        
        return apply_filters('aci_apply_shortcode_discount', false, $validation_result, $discount_settings);
    }

    /**
     * Apply WooCommerce discount from shortcode
     */
    private function apply_woocommerce_shortcode_discount($validation_result, $discount_settings) {
        if (!WC()->session) {
            return new WP_Error('no_session', __('No WooCommerce session available.', 'affiliate-client-integration'));
        }

        $discount_data = [
            'type' => $discount_settings['type'] ?? 'percentage',
            'amount' => $discount_settings['amount'] ?? 10,
            'affiliate_id' => $validation_result['affiliate_id'],
            'affiliate_code' => $validation_result['affiliate_code'],
            'source' => 'shortcode'
        ];

        WC()->session->set('aci_affiliate_discount', $discount_data);
        
        return $discount_data;
    }

    /**
     * Handle email collection from shortcode
     */
    private function handle_shortcode_email_collection($email, $validation_result) {
        $email_data = [
            'email' => $email,
            'affiliate_id' => $validation_result['affiliate_id'],
            'collected_at' => current_time('mysql'),
            'source' => 'shortcode',
            'ip_address' => $this->get_client_ip()
        ];

        // Store locally
        $this->store_collected_email($email_data);
        
        // Send to mailing list if configured
        $mailing_settings = $this->settings['mailing_list'] ?? [];
        if (!empty($mailing_settings['enabled'])) {
            $this->add_to_mailing_list($email, $validation_result);
        }
    }

    /**
     * Track shortcode conversion
     */
    private function track_shortcode_conversion($form_data, $validation_result) {
        $tracking_data = [
            'event_type' => 'shortcode_conversion',
            'form_id' => $form_data['form_id'],
            'affiliate_id' => $validation_result['affiliate_id'],
            'affiliate_code' => $validation_result['affiliate_code'],
            'has_email' => !empty($form_data['user_email']),
            'page_url' => home_url($_SERVER['REQUEST_URI']),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        ];

        // Store locally
        $this->store_conversion_tracking($tracking_data);
        
        // Send to master domain
        $this->send_tracking_to_master($tracking_data);
    }

    /**
     * Get discount info for code
     */
    private function get_discount_info_for_code($validation_result) {
        $discount_settings = $this->settings['auto_discount'] ?? [];
        
        if (empty($discount_settings['enabled'])) {
            return null;
        }

        return [
            'type' => $discount_settings['type'] ?? 'percentage',
            'amount' => $discount_settings['amount'] ?? 10,
            'formatted' => $this->format_discount_amount($discount_settings)
        ];
    }

    /**
     * Format discount amount
     */
    private function format_discount_amount($discount_settings) {
        if ($discount_settings['type'] === 'percentage') {
            return $discount_settings['amount'] . '%';
        } else {
            $currency = get_option('woocommerce_currency', 'USD');
            return $currency . ' ' . number_format($discount_settings['amount'], 2);
        }
    }

    /**
     * Store collected email
     */
    private function store_collected_email($email_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_collected_emails';
        
        // Create table if it doesn't exist
        $this->maybe_create_emails_table();
        
        $wpdb->insert(
            $table_name,
            $email_data,
            ['%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Maybe create emails table
     */
    private function maybe_create_emails_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_collected_emails';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                affiliate_id bigint(20) unsigned NOT NULL,
                collected_at datetime DEFAULT CURRENT_TIMESTAMP,
                source varchar(50) DEFAULT 'unknown',
                ip_address varchar(45),
                PRIMARY KEY (id),
                KEY email (email),
                KEY affiliate_id (affiliate_id),
                KEY collected_at (collected_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Store conversion tracking
     */
    private function store_conversion_tracking($tracking_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_shortcode_conversions';
        
        // Create table if it doesn't exist
        $this->maybe_create_shortcode_conversions_table();
        
        $wpdb->insert(
            $table_name,
            [
                'event_type' => $tracking_data['event_type'],
                'form_id' => $tracking_data['form_id'],
                'affiliate_id' => $tracking_data['affiliate_id'],
                'affiliate_code' => $tracking_data['affiliate_code'],
                'has_email' => $tracking_data['has_email'] ? 1 : 0,
                'page_url' => $tracking_data['page_url'],
                'user_agent' => $tracking_data['user_agent'],
                'user_ip' => $this->get_client_ip(),
                'session_id' => session_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Maybe create shortcode conversions table
     */
    private function maybe_create_shortcode_conversions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_shortcode_conversions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                form_id varchar(100),
                affiliate_id bigint(20) unsigned NOT NULL,
                affiliate_code varchar(255) NOT NULL,
                has_email tinyint(1) DEFAULT 0,
                page_url varchar(500),
                user_agent text,
                user_ip varchar(45),
                session_id varchar(100),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY affiliate_id (affiliate_id),
                KEY session_id (session_id),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Send tracking to master
     */
    private function send_tracking_to_master($tracking_data) {
        $master_url = $this->settings['master_domain'] ?? '';
        
        if (empty($master_url)) {
            return;
        }

        wp_remote_post($master_url . '/wp-json/affcd/v1/track-shortcode', [
            'body' => json_encode($tracking_data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . ($this->settings['api_key'] ?? '')
            ],
            'timeout' => 5
        ]);
    }

    /**
     * Add to mailing list
     */
    private function add_to_mailing_list($email, $validation_result) {
        // Integration with popular mailing services
        do_action('aci_add_to_mailing_list', $email, $validation_result, 'shortcode');
    }

    /**
     * Get client IP
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

    /**
     * Get shortcode usage statistics
     */
    public function get_shortcode_statistics($period = '30d') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_shortcode_conversions';
        
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$period}"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_type,
                COUNT(*) as total_events,
                COUNT(DISTINCT form_id) as unique_forms,
                COUNT(CASE WHEN has_email = 1 THEN 1 END) as with_email,
                COUNT(DISTINCT affiliate_id) as unique_affiliates
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY event_type
             ORDER BY total_events DESC",
            $start_date, $end_date
        ), ARRAY_A);
    }

    /**
     * Get popular shortcodes
     */
    public function get_popular_shortcodes($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_shortcode_conversions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                form_id,
                COUNT(*) as usage_count,
                COUNT(DISTINCT affiliate_id) as unique_affiliates,
                MAX(created_at) as last_used
             FROM {$table_name}
             WHERE form_id IS NOT NULL
             GROUP BY form_id
             ORDER BY usage_count DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }
}