<?php
/**
 * Popup Manager Class
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-popup-manager.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Popup_Manager {

    /**
     * Popup configurations
     */
    private static $popup_configs = [];

    /**
     * Default popup settings
     */
    private static $default_settings = [];

    /**
     * Current popup data
     */
    private static $current_popup = null;

    /**
     * Initialize popup manager
     */
    public static function init() {
        self::load_default_settings();
        self::register_default_popups();
        
        // Hooks
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_footer', [__CLASS__, 'render_popup_html']);
        add_action('wp_ajax_aci_popup_interaction', [__CLASS__, 'ajax_popup_interaction']);
        add_action('wp_ajax_nopriv_aci_popup_interaction', [__CLASS__, 'ajax_popup_interaction']);
        
        // Shortcode
        add_shortcode('aci_popup_trigger', [__CLASS__, 'popup_trigger_shortcode']);
    }

    /**
     * Load default popup settings
     */
    private static function load_default_settings() {
        self::$default_settings = [
            'enabled' => get_option('aci_popup_enabled', false),
            'trigger' => get_option('aci_popup_trigger', 'exit_intent'),
            'delay' => get_option('aci_popup_delay', 5),
            'show_once' => get_option('aci_popup_show_once', true),
            'popup_title' => get_option('aci_popup_title', __('Special Discount Available!', 'affiliate-client-integration')),
            'popup_message' => get_option('aci_popup_message', __('Enter your affiliate code to receive an exclusive discount.', 'affiliate-client-integration')),
            'popup_style' => get_option('aci_popup_style', 'default'),
            'button_text' => get_option('aci_popup_button_text', __('Apply Code', 'affiliate-client-integration')),
            'show_benefits' => get_option('aci_popup_show_benefits', true),
            'close_button' => get_option('aci_popup_close_button', true),
            'overlay_close' => get_option('aci_popup_overlay_close', true)
        ];
    }

    /**
     * Register default popup configurations
     */
    private static function register_default_popups() {
        // Default popup
        self::register_popup('default', [
            'template' => 'default',
            'title' => self::$default_settings['popup_title'],
            'message' => self::$default_settings['popup_message'],
            'style' => 'default',
            'size' => 'medium',
            'animation' => 'fade-in'
        ]);

        // Compact popup
        self::register_popup('compact', [
            'template' => 'compact',
            'title' => __('Got a discount code?', 'affiliate-client-integration'),
            'message' => __('Enter it here for instant savings', 'affiliate-client-integration'),
            'style' => 'compact',
            'size' => 'small',
            'animation' => 'slide-in'
        ]);

        // Fullscreen popup
        self::register_popup('fullscreen', [
            'template' => 'fullscreen',
            'title' => __('Exclusive Discount Inside!', 'affiliate-client-integration'),
            'message' => __('Enter your affiliate code to unlock special pricing on your entire order.', 'affiliate-client-integration'),
            'style' => 'fullscreen',
            'size' => 'large',
            'animation' => 'zoom-in'
        ]);

        // Slide-in popup
        self::register_popup('slide', [
            'template' => 'slide',
            'title' => __('Discount Available', 'affiliate-client-integration'),
            'message' => __('Have an affiliate code?', 'affiliate-client-integration'),
            'style' => 'slide',
            'size' => 'small',
            'animation' => 'slide-right',
            'position' => 'bottom-right'
        ]);

        // Exit intent popup
        self::register_popup('exit_intent', [
            'template' => 'default',
            'title' => __('Wait! Don\'t Leave Empty Handed', 'affiliate-client-integration'),
            'message' => __('Enter your affiliate code before you go and save on your purchase.', 'affiliate-client-integration'),
            'style' => 'urgent',
            'size' => 'medium',
            'animation' => 'bounce-in',
            'trigger' => 'exit_intent'
        ]);
    }

    /**
     * Register a popup configuration
     */
    public static function register_popup($name, $config) {
        $default_config = [
            'template' => 'default',
            'title' => '',
            'message' => '',
            'style' => 'default',
            'size' => 'medium',
            'animation' => 'fade-in',
            'position' => 'center',
            'trigger' => 'manual',
            'delay' => 0,
            'show_form' => true,
            'show_benefits' => true,
            'show_close' => true,
            'auto_close' => false,
            'auto_close_delay' => 5000,
            'backdrop_close' => true
        ];

        self::$popup_configs[$name] = array_merge($default_config, $config);
    }

    /**
     * Get popup configuration
     */
    public static function get_popup_config($name) {
        return self::$popup_configs[$name] ?? null;
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts() {
        if (!self::should_show_popup()) {
            return;
        }

        wp_enqueue_script(
            'aci-popup-manager',
            ACI_PLUGIN_URL . 'assets/js/popup-manager.js',
            ['jquery'],
            ACI_VERSION,
            true
        );

        wp_localize_script('aci-popup-manager', 'aci_popup_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aci_popup_interaction'),
            'settings' => self::$default_settings,
            'popups' => self::$popup_configs,
            'strings' => [
                'loading' => __('Loading...', 'affiliate-client-integration'),
                'error' => __('An error occurred', 'affiliate-client-integration'),
                'success' => __('Success!', 'affiliate-client-integration'),
                'invalid_code' => __('Invalid affiliate code', 'affiliate-client-integration'),
                'code_applied' => __('Affiliate code applied successfully!', 'affiliate-client-integration')
            ]
        ]);
    }

    /**
     * Check if popup should be shown
     */
    private static function should_show_popup() {
        // Check if popups are enabled globally
        if (!self::$default_settings['enabled']) {
            return false;
        }

        // Check if user already has an affiliate code
        if (self::user_has_affiliate_code()) {
            return false;
        }

        // Check if user has already seen popup (if show_once is enabled)
        if (self::$default_settings['show_once'] && self::user_has_seen_popup()) {
            return false;
        }

        // Check page restrictions
        if (!self::is_popup_allowed_on_page()) {
            return false;
        }

        // Check device restrictions
        if (!self::is_popup_allowed_on_device()) {
            return false;
        }

        return true;
    }

    /**
     * Check if user already has affiliate code
     */
    private static function user_has_affiliate_code() {
        // Check session
        if (class_exists('ACI_Session_Manager')) {
            $session_manager = new ACI_Session_Manager();
            return $session_manager->has_affiliate();
        }

        // Check URL parameters
        $affiliate_params = ['aff', 'affiliate', 'ref', 'referrer', 'partner'];
        foreach ($affiliate_params as $param) {
            if (!empty($_GET[$param])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has already seen popup
     */
    private static function user_has_seen_popup() {
        // Check cookie
        return !empty($_COOKIE['aci_popup_shown']);
    }

    /**
     * Check if popup is allowed on current page
     */
    private static function is_popup_allowed_on_page() {
        $excluded_pages = get_option('aci_popup_excluded_pages', []);
        $current_page_id = get_queried_object_id();

        if (in_array($current_page_id, $excluded_pages)) {
            return false;
        }

        // Don't show on admin pages
        if (is_admin()) {
            return false;
        }

        // Don't show on login/register pages
        if (is_page(['login', 'register', 'checkout'])) {
            return false;
        }

        return true;
    }

    /**
     * Render popup HTML in footer
     */
    public static function render_popup_html() {
        if (!self::should_show_popup()) {
            return;
        }

        $popup_type = self::determine_popup_type();
        $config = self::get_popup_config($popup_type);
        
        if (!$config) {
            return;
        }

        self::$current_popup = $config;
        
        echo '<div id="aci-popup-container" style="display: none;">';
        self::render_popup_template($popup_type, $config);
        echo '</div>';
    }

    /**
     * Determine which popup type to show
     */
    private static function determine_popup_type() {
        $trigger = self::$default_settings['trigger'];
        
        switch ($trigger) {
            case 'exit_intent':
                return 'exit_intent';
            case 'time_delay':
                return 'default';
            case 'scroll':
                return 'compact';
            case 'slide_in':
                return 'slide';
            default:
                return 'default';
        }
    }

    /**
     * Render popup template
     */
    private static function render_popup_template($type, $config) {
        $template_file = ACI_PLUGIN_PATH . "templates/popups/{$config['template']}-popup.php";
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            self::render_default_popup_template($config);
        }
    }

    /**
     * Render default popup template
     */
    private static function render_default_popup_template($config) {
        $popup_id = 'aci-popup-' . uniqid();
        $size_class = 'aci-popup-' . $config['size'];
        $style_class = 'aci-popup-style-' . $config['style'];
        $animation_class = 'aci-popup-animation-' . $config['animation'];
        ?>
        
        <div class="aci-popup-overlay <?php echo esc_attr($animation_class); ?>" id="<?php echo esc_attr($popup_id); ?>">
            <div class="aci-popup <?php echo esc_attr($size_class . ' ' . $style_class); ?>" role="dialog" aria-labelledby="popup-title" aria-describedby="popup-description">
                
                <?php if ($config['show_close']): ?>
                <button type="button" class="aci-popup-close" aria-label="<?php _e('Close popup', 'affiliate-client-integration'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <?php endif; ?>

                <div class="aci-popup-header">
                    <h2 id="popup-title" class="aci-popup-title"><?php echo esc_html($config['title']); ?></h2>
                    <?php if (!empty($config['message'])): ?>
                        <p id="popup-description" class="aci-popup-subtitle"><?php echo esc_html($config['message']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="aci-popup-body">
                    <?php if ($config['show_form']): ?>
                        <form class="aci-popup-form" method="post">
                            <?php wp_nonce_field('aci_popup_submit', 'aci_popup_nonce'); ?>
                            
                            <div class="aci-popup-field">
                                <label for="popup-affiliate-code" class="aci-popup-label">
                                    <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                                </label>
                                <input type="text" 
                                       id="popup-affiliate-code" 
                                       name="affiliate_code" 
                                       class="aci-popup-input" 
                                       placeholder="<?php _e('Enter your code', 'affiliate-client-integration'); ?>"
                                       autocomplete="off"
                                       required>
                                <div class="aci-input-feedback"></div>
                            </div>

                            <button type="submit" class="aci-popup-button">
                                <span class="aci-popup-button-text">
                                    <?php echo esc_html($config['button_text'] ?? __('Apply Code', 'affiliate-client-integration')); ?>
                                </span>
                                <span class="aci-popup-loader" style="display: none;">
                                    <span class="aci-popup-spinner"></span>
                                </span>
                            </button>

                            <div class="aci-popup-message" style="display: none;"></div>
                        </form>
                    <?php endif; ?>

                    <?php if ($config['show_benefits']): ?>
                        <div class="aci-popup-benefits">
                            <h3 class="aci-benefits-title"><?php _e('Why enter your code?', 'affiliate-client-integration'); ?></h3>
                            <ul class="aci-benefits-list">
                                <li class="aci-benefit-item">
                                    <span class="aci-benefit-icon">💰</span>
                                    <span><?php _e('Instant discount applied', 'affiliate-client-integration'); ?></span>
                                </li>
                                <li class="aci-benefit-item">
                                    <span class="aci-benefit-icon">🔒</span>
                                    <span><?php _e('Secure and trusted', 'affiliate-client-integration'); ?></span>
                                </li>
                                <li class="aci-benefit-item">
                                    <span class="aci-benefit-icon">⚡</span>
                                    <span><?php _e('No signup required', 'affiliate-client-integration'); ?></span>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($config['footer_text'])): ?>
                    <div class="aci-popup-footer">
                        <p class="aci-popup-footer-text"><?php echo esc_html($config['footer_text']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
    }

    /**
     * AJAX handler for popup interactions
     */
    public static function ajax_popup_interaction() {
        check_ajax_referer('aci_popup_interaction', 'nonce');

        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $popup_type = sanitize_text_field($_POST['popup_type'] ?? '');
        $affiliate_code = sanitize_text_field($_POST['affiliate_code'] ?? '');

        switch ($action_type) {
            case 'submit_code':
                self::handle_popup_code_submission($affiliate_code, $popup_type);
                break;
                
            case 'close':
                self::handle_popup_close($popup_type);
                break;
                
            case 'view':
                self::handle_popup_view($popup_type);
                break;
                
            default:
                wp_send_json_error(__('Invalid action', 'affiliate-client-integration'));
        }
    }

    /**
     * Handle popup code submission
     */
    private static function handle_popup_code_submission($affiliate_code, $popup_type) {
        if (empty($affiliate_code)) {
            wp_send_json_error(__('Please enter an affiliate code', 'affiliate-client-integration'));
        }

        // Validate affiliate code
        if (class_exists('ACI_Validation_Helpers')) {
            $validation_result = ACI_Validation_Helpers::validate_affiliate_code_remote($affiliate_code, [
                'source' => 'popup',
                'popup_type' => $popup_type,
                'url' => $_POST['current_url'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            if ($validation_result['valid']) {
                // Set affiliate in session
                if (class_exists('ACI_Session_Manager')) {
                    $session_manager = new ACI_Session_Manager();
                    $session_manager->set_affiliate_data($affiliate_code, [
                        'source' => 'popup',
                        'popup_type' => $popup_type,
                        'validation_data' => $validation_result['data']
                    ]);
                }

                // Track successful conversion
                self::track_popup_conversion($popup_type, $affiliate_code, true);

                // Set cookie to prevent showing popup again
                setcookie('aci_popup_shown', '1', time() + (30 * 24 * 60 * 60), '/'); // 30 days

                wp_send_json_success([
                    'message' => __('Affiliate code applied successfully!', 'affiliate-client-integration'),
                    'discount' => $validation_result['discount'] ?? null,
                    'redirect' => $_POST['redirect_url'] ?? ''
                ]);
            } else {
                self::track_popup_conversion($popup_type, $affiliate_code, false);
                wp_send_json_error($validation_result['error'] ?? __('Invalid affiliate code', 'affiliate-client-integration'));
            }
        } else {
            wp_send_json_error(__('Validation system not available', 'affiliate-client-integration'));
        }
    }

    /**
     * Handle popup close
     */
    private static function handle_popup_close($popup_type) {
        self::track_popup_interaction($popup_type, 'close');
        
        // Set cookie to prevent showing popup again (shorter duration for close)
        setcookie('aci_popup_closed', '1', time() + (24 * 60 * 60), '/'); // 24 hours
        
        wp_send_json_success(['message' => 'Popup closed']);
    }

    /**
     * Handle popup view
     */
    private static function handle_popup_view($popup_type) {
        self::track_popup_interaction($popup_type, 'view');
        wp_send_json_success(['message' => 'View tracked']);
    }

    /**
     * Track popup interactions
     */
    private static function track_popup_interaction($popup_type, $action) {
        $tracking_data = [
            'popup_type' => $popup_type,
            'action' => $action,
            'timestamp' => current_time('mysql'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'referrer' => $_POST['referrer'] ?? ''
        ];

        // Store in database or send to analytics
        $interactions = get_option('aci_popup_interactions', []);
        $interactions[] = $tracking_data;

        // Keep only last 1000 interactions
        if (count($interactions) > 1000) {
            $interactions = array_slice($interactions, -1000);
        }

        update_option('aci_popup_interactions', $interactions);

        // Trigger action for external tracking
        do_action('aci_popup_interaction_tracked', $tracking_data);
    }

    /**
     * Track popup conversions
     */
    private static function track_popup_conversion($popup_type, $affiliate_code, $success) {
        $conversion_data = [
            'popup_type' => $popup_type,
            'affiliate_code' => $affiliate_code,
            'success' => $success,
            'timestamp' => current_time('mysql'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        $conversions = get_option('aci_popup_conversions', []);
        $conversions[] = $conversion_data;

        // Keep only last 500 conversions
        if (count($conversions) > 500) {
            $conversions = array_slice($conversions, -500);
        }

        update_option('aci_popup_conversions', $conversions);

        // Trigger action for external tracking
        do_action('aci_popup_conversion_tracked', $conversion_data);
    }

    /**
     * Popup trigger shortcode
     */
    public static function popup_trigger_shortcode($atts) {
        $atts = shortcode_atts([
            'type' => 'default',
            'text' => __('Show Discount Form', 'affiliate-client-integration'),
            'class' => '',
            'id' => ''
        ], $atts, 'aci_popup_trigger');

        $button_id = !empty($atts['id']) ? $atts['id'] : 'aci-popup-trigger-' . uniqid();
        $button_class = 'aci-popup-trigger ' . $atts['class'];

        return sprintf(
            '<button type="button" id="%s" class="%s" data-popup-type="%s">%s</button>',
            esc_attr($button_id),
            esc_attr($button_class),
            esc_attr($atts['type']),
            esc_html($atts['text'])
        );
    }

    /**
     * Get popup statistics
     */
    public static function get_popup_statistics($days = 30) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $interactions = get_option('aci_popup_interactions', []);
        $conversions = get_option('aci_popup_conversions', []);

        $filtered_interactions = array_filter($interactions, function($interaction) use ($cutoff_date) {
            return $interaction['timestamp'] >= $cutoff_date;
        });

        $filtered_conversions = array_filter($conversions, function($conversion) use ($cutoff_date) {
            return $conversion['timestamp'] >= $cutoff_date;
        });

        $views = count(array_filter($filtered_interactions, function($interaction) {
            return $interaction['action'] === 'view';
        }));

        $closes = count(array_filter($filtered_interactions, function($interaction) {
            return $interaction['action'] === 'close';
        }));

        $successful_conversions = count(array_filter($filtered_conversions, function($conversion) {
            return $conversion['success'] === true;
        }));

        $total_conversions = count($filtered_conversions);

        return [
            'views' => $views,
            'closes' => $closes,
            'conversion_attempts' => $total_conversions,
            'successful_conversions' => $successful_conversions,
            'conversion_rate' => $views > 0 ? round(($successful_conversions / $views) * 100, 2) : 0,
            'close_rate' => $views > 0 ? round(($closes / $views) * 100, 2) : 0
        ];
    }

    /**
     * Show popup programmatically
     */
    public static function show_popup($type = 'default', $config_override = []) {
        $config = self::get_popup_config($type);
        
        if (!$config) {
            return false;
        }

        // Override config if provided
        if (!empty($config_override)) {
            $config = array_merge($config, $config_override);
        }

        // Set JavaScript flag to show popup
        add_action('wp_footer', function() use ($type, $config) {
            echo '<script>
                if (typeof ACI !== "undefined" && ACI.PopupManager) {
                    ACI.PopupManager.showPopup("' . esc_js($type) . '", ' . json_encode($config) . ');
                }
            </script>';
        }, 999);

        return true;
    }

    /**
     * Hide all popups
     */
    public static function hide_all_popups() {
        add_action('wp_footer', function() {
            echo '<script>
                if (typeof ACI !== "undefined" && ACI.PopupManager) {
                    ACI.PopupManager.hideAll();
                }
            </script>';
        }, 999);
    }

    /**
     * Check if popup should be shown based on conditions
     */
    public static function should_show_popup_for_user($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Don't show to admin users unless testing
        if ($user_id && user_can($user_id, 'manage_options') && !defined('ACI_POPUP_TESTING')) {
            return false;
        }

        // Check user meta for popup preferences
        if ($user_id) {
            $user_disabled = get_user_meta($user_id, 'aci_popup_disabled', true);
            if ($user_disabled) {
                return false;
            }
        }

        return self::should_show_popup();
    }

    /**
     * Disable popup for specific user
     */
    public static function disable_popup_for_user($user_id) {
        update_user_meta($user_id, 'aci_popup_disabled', true);
    }

    /**
     * Enable popup for specific user
     */
    public static function enable_popup_for_user($user_id) {
        delete_user_meta($user_id, 'aci_popup_disabled');
    }

    /**
     * Get all registered popup types
     */
    public static function get_popup_types() {
        return array_keys(self::$popup_configs);
    }

    /**
     * Remove popup configuration
     */
    public static function unregister_popup($name) {
        unset(self::$popup_configs[$name]);
    }

    /**
     * Clear popup statistics
     */
    public static function clear_statistics() {
        delete_option('aci_popup_interactions');
        delete_option('aci_popup_conversions');
    }
}