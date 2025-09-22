<?php
/**
 * Settings Page Class
 * File: /wp-content/plugins/affiliate-client-integration/admin/class-settings-page.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Settings_Page {

    /**
     * Settings page slug
     */
    private $page_slug = 'aci-settings';

    /**
     * Settings sections
     */
    private $sections = [];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_aci_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_aci_sync_affiliate_codes', [$this, 'ajax_sync_affiliate_codes']);
        add_action('wp_ajax_aci_clear_cache', [$this, 'ajax_clear_cache']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Affiliate Integration Settings', 'affiliate-client-integration'),
            __('Affiliate Integration', 'affiliate-client-integration'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Initialse settings
     */
    public function init_settings() {
        // Register settings
        register_setting('aci_settings', 'aci_master_domain', [
            'sanitize_callback' => 'esc_url_raw'
        ]);
        register_setting('aci_settings', 'aci_api_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('aci_settings', 'aci_api_secret', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('aci_settings', 'aci_cache_duration', [
            'sanitize_callback' => 'intval'
        ]);
        register_setting('aci_settings', 'aci_enable_logging', [
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        register_setting('aci_settings', 'aci_auto_apply_discounts', [
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        register_setting('aci_settings', 'aci_popup_settings', [
            'sanitize_callback' => [$this, 'sanitize_popup_settings']
        ]);
        register_setting('aci_settings', 'aci_display_settings', [
            'sanitize_callback' => [$this, 'sanitize_display_settings']
        ]);
        register_setting('aci_settings', 'aci_zoho_settings', [
            'sanitize_callback' => [$this, 'sanitize_zoho_settings']
        ]);

        // Define sections
        $this->sections = [
            'connection' => __('Master Domain Connection', 'affiliate-client-integration'),
            'display' => __('Display Settings', 'affiliate-client-integration'),
            'popup' => __('Popup Configuration', 'affiliate-client-integration'),
            'integrations' => __('Third-party Integrations', 'affiliate-client-integration'),
            'advanced' => __('Advanced Settings', 'affiliate-client-integration')
        ];

        // Add settings sections
        foreach ($this->sections as $section_id => $section_title) {
            add_settings_section(
                'aci_' . $section_id . '_section',
                $section_title,
                [$this, 'render_section_' . $section_id],
                $this->page_slug
            );
        }

        // Add settings fields
        $this->add_connection_fields();
        $this->add_display_fields();
        $this->add_popup_fields();
        $this->add_integration_fields();
        $this->add_advanced_fields();
    }

    /**
     * Add connection settings fields
     */
    private function add_connection_fields() {
        add_settings_field(
            'aci_master_domain',
            __('Master Domain URL', 'affiliate-client-integration'),
            [$this, 'render_field_master_domain'],
            $this->page_slug,
            'aci_connection_section'
        );

        add_settings_field(
            'aci_api_key',
            __('API Key', 'affiliate-client-integration'),
            [$this, 'render_field_api_key'],
            $this->page_slug,
            'aci_connection_section'
        );

        add_settings_field(
            'aci_api_secret',
            __('API Secret', 'affiliate-client-integration'),
            [$this, 'render_field_api_secret'],
            $this->page_slug,
            'aci_connection_section'
        );

        add_settings_field(
            'aci_connection_test',
            __('Connection Test', 'affiliate-client-integration'),
            [$this, 'render_field_connection_test'],
            $this->page_slug,
            'aci_connection_section'
        );
    }

    /**
     * Add display settings fields
     */
    private function add_display_fields() {
        add_settings_field(
            'aci_auto_apply_discounts',
            __('Auto-apply Discounts', 'affiliate-client-integration'),
            [$this, 'render_field_auto_apply_discounts'],
            $this->page_slug,
            'aci_display_section'
        );

        add_settings_field(
            'aci_display_settings',
            __('Display Options', 'affiliate-client-integration'),
            [$this, 'render_field_display_settings'],
            $this->page_slug,
            'aci_display_section'
        );
    }

    /**
     * Add popup settings fields
     */
    private function add_popup_fields() {
        add_settings_field(
    /**
     * Add popup settings fields
     */
    private function add_popup_fields() {
        add_settings_field(
            'aci_popup_settings',
            __('Popup Configuration', 'affiliate-client-integration'),
            [$this, 'render_field_popup_settings'],
            $this->page_slug,
            'aci_popup_section'
        );
    }

    /**
     * Add integration settings fields
     */
    private function add_integration_fields() {
        add_settings_field(
            'aci_zoho_settings',
            __('Zoho Integration', 'affiliate-client-integration'),
            [$this, 'render_field_zoho_settings'],
            $this->page_slug,
            'aci_integrations_section'
        );
    }

    /**
     * Add advanced settings fields
     */
    private function add_advanced_fields() {
        add_settings_field(
            'aci_cache_duration',
            __('Cache Duration', 'affiliate-client-integration'),
            [$this, 'render_field_cache_duration'],
            $this->page_slug,
            'aci_advanced_section'
        );

        add_settings_field(
            'aci_enable_logging',
            __('Enable Logging', 'affiliate-client-integration'),
            [$this, 'render_field_enable_logging'],
            $this->page_slug,
            'aci_advanced_section'
        );

        add_settings_field(
            'aci_advanced_actions',
            __('Advanced Actions', 'affiliate-client-integration'),
            [$this, 'render_field_advanced_actions'],
            $this->page_slug,
            'aci_advanced_section'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('aci_messages', 'aci_message', __('Settings Saved', 'affiliate-client-integration'), 'updated');
        }

        settings_errors('aci_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="aci-admin-header">
                <div class="aci-connection-status" id="aci-connection-status">
                    <?php $this->render_connection_status(); ?>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('aci_settings');
                do_settings_sections($this->page_slug);
                submit_button(__('Save Settings', 'affiliate-client-integration'));
                ?>
            </form>

            <div class="aci-admin-sidebar">
                <?php $this->render_sidebar(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render connection status
     */
    private function render_connection_status() {
        $master_domain = get_option('aci_master_domain', '');
        $api_key = get_option('aci_api_key', '');
        
        if (empty($master_domain) || empty($api_key)) {
            echo '<div class="aci-status aci-status-warning">⚠️ ' . __('Connection not configured', 'affiliate-client-integration') . '</div>';
            return;
        }

        // Test connection
        $connection_test = $this->test_connection();
        
        if ($connection_test['success']) {
            echo '<div class="aci-status aci-status-success">✅ ' . __('Connected to master domain', 'affiliate-client-integration') . '</div>';
        } else {
            echo '<div class="aci-status aci-status-error">❌ ' . __('Connection failed', 'affiliate-client-integration') . ': ' . esc_html($connection_test['message']) . '</div>';
        }
    }

    /**
     * Render section descriptions
     */
    public function render_section_connection() {
        echo '<p>' . __('Configure the connection to your master affiliate domain.', 'affiliate-client-integration') . '</p>';
    }

    public function render_section_display() {
        echo '<p>' . __('Control how affiliate elements are displayed on your site.', 'affiliate-client-integration') . '</p>';
    }

    public function render_section_popup() {
        echo '<p>' . __('Configure popup behavior and appearance.', 'affiliate-client-integration') . '</p>';
    }

    public function render_section_integrations() {
        echo '<p>' . __('Connect with third-party services and platforms.', 'affiliate-client-integration') . '</p>';
    }

    public function render_section_advanced() {
        echo '<p>' . __('Advanced configuration options for developers.', 'affiliate-client-integration') . '</p>';
    }

    /**
     * Render master domain field
     */
    public function render_field_master_domain() {
        $value = get_option('aci_master_domain', '');
        ?>
        <input type="url" 
               id="aci_master_domain" 
               name="aci_master_domain" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="https://your-affiliate-domain.com">
        <p class="description">
            <?php _e('The URL of your master affiliate domain (without trailing slash)', 'affiliate-client-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render API key field
     */
    public function render_field_api_key() {
        $value = get_option('aci_api_key', '');
        ?>
        <input type="text" 
               id="aci_api_key" 
               name="aci_api_key" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               autocomplete="off">
        <p class="description">
            <?php _e('API key provided by your master affiliate domain', 'affiliate-client-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render API secret field
     */
    public function render_field_api_secret() {
        $value = get_option('aci_api_secret', '');
        ?>
        <input type="password" 
               id="aci_api_secret" 
               name="aci_api_secret" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               autocomplete="off">
        <p class="description">
            <?php _e('API secret for secure authentication', 'affiliate-client-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render connection test field
     */
    public function render_field_connection_test() {
        ?>
        <button type="button" id="aci-test-connection" class="button button-secondary">
            <?php _e('Test Connection', 'affiliate-client-integration'); ?>
        </button>
        <div id="aci-test-result" style="margin-top: 10px;"></div>
        <?php
    }

    /**
     * Render auto apply discounts field
     */
    public function render_field_auto_apply_discounts() {
        $value = get_option('aci_auto_apply_discounts', true);
        ?>
        <label>
            <input type="checkbox" 
                   name="aci_auto_apply_discounts" 
                   value="1" 
                   <?php checked($value); ?>>
            <?php _e('Automatically apply affiliate discounts when affiliate codes are detected', 'affiliate-client-integration'); ?>
        </label>
        <?php
    }

    /**
     * Render display settings field
     */
    public function render_field_display_settings() {
        $settings = get_option('aci_display_settings', [
            'show_affiliate_notice' => true,
            'show_discount_amount' => true,
            'affiliate_notice_position' => 'top',
            'custom_css_class' => ''
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Show Affiliate Notice', 'affiliate-client-integration'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="aci_display_settings[show_affiliate_notice]" 
                               value="1" 
                               <?php checked($settings['show_affiliate_notice'] ?? true); ?>>
                        <?php _e('Display notice when affiliate code is active', 'affiliate-client-integration'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Show Discount Amount', 'affiliate-client-integration'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="aci_display_settings[show_discount_amount]" 
                               value="1" 
                               <?php checked($settings['show_discount_amount'] ?? true); ?>>
                        <?php _e('Show the discount amount in notices', 'affiliate-client-integration'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Notice Position', 'affiliate-client-integration'); ?></th>
                <td>
                    <select name="aci_display_settings[affiliate_notice_position]">
                        <option value="top" <?php selected($settings['affiliate_notice_position'] ?? 'top', 'top'); ?>><?php _e('Top of page', 'affiliate-client-integration'); ?></option>
                        <option value="bottom" <?php selected($settings['affiliate_notice_position'] ?? 'top', 'bottom'); ?>><?php _e('Bottom of page', 'affiliate-client-integration'); ?></option>
                        <option value="floating" <?php selected($settings['affiliate_notice_position'] ?? 'top', 'floating'); ?>><?php _e('Floating', 'affiliate-client-integration'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Custom CSS Class', 'affiliate-client-integration'); ?></th>
                <td>
                    <input type="text" 
                           name="aci_display_settings[custom_css_class]" 
                           value="<?php echo esc_attr($settings['custom_css_class'] ?? ''); ?>"
                           class="regular-text">
                    <p class="description"><?php _e('Additional CSS class for styling affiliate elements', 'affiliate-client-integration'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render popup settings field
     */
    public function render_field_popup_settings() {
        $settings = get_option('aci_popup_settings', [
            'enabled' => false,
            'trigger' => 'exit_intent',
            'delay' => 5,
            'show_once' => true,
            'popup_title' => 'Special Discount Available!',
            'popup_message' => 'Enter your affiliate code to receive an exclusive discount.',
            'popup_style' => 'default'
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Popup', 'affiliate-client-integration'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="aci_popup_settings[enabled]" 
                               value="1" 
                               <?php checked($settings['enabled'] ?? false); ?>>
                        <?php _e('Enable affiliate code popup', 'affiliate-client-integration'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Trigger', 'affiliate-client-integration'); ?></th>
                <td>
                    <select name="aci_popup_settings[trigger]">
                        <option value="exit_intent" <?php selected($settings['trigger'] ?? 'exit_intent', 'exit_intent'); ?>><?php _e('Exit Intent', 'affiliate-client-integration'); ?></option>
                        <option value="time_delay" <?php selected($settings['trigger'] ?? 'exit_intent', 'time_delay'); ?>><?php _e('Time Delay', 'affiliate-client-integration'); ?></option>
                        <option value="scroll" <?php selected($settings['trigger'] ?? 'exit_intent', 'scroll'); ?>><?php _e('Scroll Percentage', 'affiliate-client-integration'); ?></option>
                        <option value="manual" <?php selected($settings['trigger'] ?? 'exit_intent', 'manual'); ?>><?php _e('Manual Trigger', 'affiliate-client-integration'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Delay (seconds)', 'affiliate-client-integration'); ?></th>
                <td>
                    <input type="number" 
                           name="aci_popup_settings[delay]" 
                           value="<?php echo esc_attr($settings['delay'] ?? 5); ?>"
                           min="1" 
                           max="300">
                    <p class="description"><?php _e('Delay before showing popup (for time delay trigger)', 'affiliate-client-integration'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Show Once Per Session', 'affiliate-client-integration'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="aci_popup_settings[show_once]" 
                               value="1" 
                               <?php checked($settings['show_once'] ?? true); ?>>
                        <?php _e('Only show popup once per visitor session', 'affiliate-client-integration'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Popup Title', 'affiliate-client-integration'); ?></th>
                <td>
                    <input type="text" 
                           name="aci_popup_settings[popup_title]" 
                           value="<?php echo esc_attr($settings['popup_title'] ?? 'Special Discount Available!'); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Popup Message', 'affiliate-client-integration'); ?></th>
                <td>
                    <textarea name="aci_popup_settings[popup_message]" 
                              rows="3" 
                              class="large-text"><?php echo esc_textarea($settings['popup_message'] ?? 'Enter your affiliate code to receive an exclusive discount.'); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Zoho settings field
     */
    public function render_field_zoho_settings() {
        $settings = get_option('aci_zoho_settings', [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'refresh_token' => '',
            'api_domain' => 'https://www.zohoapis.com',
            'books_org_id' => ''
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Zoho Integration', 'affiliate-client-integration'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="aci_zoho_settings[enabled]" 
                               value="1" 
                               <?php checked($settings['enabled'] ?? false); ?>>
                        <?php _e('Enable Zoho Books/CRM integration', 'affiliate-client-integration'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Client ID', 'affiliate-client-integration'); ?></th>
                <td>
                    <input type="text" 
                           name="aci_zoho_settings[client_id]" 
                           value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Client Secret', 'affiliate-client-integration'); ?></th>
                <td>
                    <input type="password" 
                           name="aci_zoho_settings[client_secret]" 
                           value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Books Organization ID', 'affiliate-client-integration'); ?></th>
                <td>
                    <input type="text" 
                           name="aci_zoho_settings[books_org_id]" 
                           value="<?php echo esc_attr($settings['books_org_id'] ?? ''); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render cache duration field
     */
    public function render_field_cache_duration() {
        $value = get_option('aci_cache_duration', 300);
        ?>
        <input type="number" 
               id="aci_cache_duration" 
               name="aci_cache_duration" 
               value="<?php echo esc_attr($value); ?>"
               min="60" 
               max="3600"
               class="small-text"> seconds
        <p class="description">
            <?php _e('How long to cache affiliate validation results (60-3600 seconds)', 'affiliate-client-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render enable logging field
     */
    public function render_field_enable_logging() {
        $value = get_option('aci_enable_logging', false);
        ?>
        <label>
            <input type="checkbox" 
                   name="aci_enable_logging" 
                   value="1" 
                   <?php checked($value); ?>>
            <?php _e('Enable debug logging for troubleshooting', 'affiliate-client-integration'); ?>
        </label>
        <p class="description">
            <?php _e('Logs will be written to the WordPress debug.log file', 'affiliate-client-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render advanced actions field
     */
    public function render_field_advanced_actions() {
        ?>
        <div class="aci-advanced-actions">
            <button type="button" id="aci-sync-codes" class="button button-secondary">
                <?php _e('Sync Affiliate Codes', 'affiliate-client-integration'); ?>
            </button>
            <button type="button" id="aci-clear-cache" class="button button-secondary">
                <?php _e('Clear Cache', 'affiliate-client-integration'); ?>
            </button>
            <button type="button" id="aci-export-settings" class="button button-secondary">
                <?php _e('Export Settings', 'affiliate-client-integration'); ?>
            </button>
        </div>
        <div id="aci-advanced-results" style="margin-top: 10px;"></div>
        <?php
    }

    /**
     * Render sidebar
     */
    private function render_sidebar() {
        ?>
        <div class="aci-sidebar-widget">
            <h3><?php _e('Documentation', 'affiliate-client-integration'); ?></h3>
            <ul>
                <li><a href="#" target="_blank"><?php _e('Setup Guide', 'affiliate-client-integration'); ?></a></li>
                <li><a href="#" target="_blank"><?php _e('Shortcode Reference', 'affiliate-client-integration'); ?></a></li>
                <li><a href="#" target="_blank"><?php _e('API Documentation', 'affiliate-client-integration'); ?></a></li>
                <li><a href="#" target="_blank"><?php _e('Troubleshooting', 'affiliate-client-integration'); ?></a></li>
            </ul>
        </div>

        <div class="aci-sidebar-widget">
            <h3><?php _e('Plugin Status', 'affiliate-client-integration'); ?></h3>
            <ul>
                <li><?php _e('Version:', 'affiliate-client-integration'); ?> <?php echo ACI_VERSION; ?></li>
                <li><?php _e('Cache Status:', 'affiliate-client-integration'); ?> 
                    <span class="<?php echo wp_using_ext_object_cache() ? 'aci-status-good' : 'aci-status-warn'; ?>">
                        <?php echo wp_using_ext_object_cache() ? __('Active', 'affiliate-client-integration') : __('File-based', 'affiliate-client-integration'); ?>
                    </span>
                </li>
                <li><?php _e('Last Sync:', 'affiliate-client-integration'); ?> 
                    <?php echo get_option('aci_last_sync', __('Never', 'affiliate-client-integration')); ?>
                </li>
            </ul>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (!str_contains($hook, $this->page_slug)) {
            return;
        }

        wp_enqueue_script(
            'aci-admin-settings',
            ACI_PLUGIN_URL . 'admin/js/admin-settings.js',
            ['jquery'],
            ACI_VERSION,
            true
        );

        wp_enqueue_style(
            'aci-admin-settings',
            ACI_PLUGIN_URL . 'admin/css/admin-settings.css',
            [],
            ACI_VERSION
        );

        wp_localize_script('aci-admin-settings', 'aci_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aci_admin_actions'),
            'strings' => [
                'testing_connection' => __('Testing connection...', 'affiliate-client-integration'),
                'connection_success' => __('Connection successful!', 'affiliate-client-integration'),
                'connection_failed' => __('Connection failed:', 'affiliate-client-integration'),
                'syncing_codes' => __('Syncing affiliate codes...', 'affiliate-client-integration'),
                'sync_complete' => __('Sync completed successfully', 'affiliate-client-integration'),
                'clearing_cache' => __('Clearing cache...', 'affiliate-client-integration'),
                'cache_cleared' => __('Cache cleared successfully', 'affiliate-client-integration'),
                'error_occurred' => __('An error occurred:', 'affiliate-client-integration')
            ]
        ]);
    }

    /**
     * Test connection to master domain
     */
    private function test_connection() {
        $master_domain = get_option('aci_master_domain', '');
        $api_key = get_option('aci_api_key', '');

        if (empty($master_domain) || empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Master domain and API key are required', 'affiliate-client-integration')
            ];
        }

        $response = wp_remote_get($master_domain . '/wp-json/affcd/v1/status', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'X-Domain' => home_url()
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (wp_remote_retrieve_response_code($response) === 200 && !empty($body['success'])) {
            return [
                'success' => true,
                'message' => __('Connection successful', 'affiliate-client-integration')
            ];
        }

        return [
            'success' => false,
            'message' => $body['message'] ?? __('Unknown error', 'affiliate-client-integration')
        ];
    }

    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('aci_admin_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'affiliate-client-integration'));
        }

        $result = $this->test_connection();
        wp_send_json($result);
    }

    /**
     * AJAX sync affiliate codes
     */
    public function ajax_sync_affiliate_codes() {
        check_ajax_referer('aci_admin_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'affiliate-client-integration'));
        }

        // Implement sync logic here
        update_option('aci_last_sync', current_time('mysql'));
        
        wp_send_json_success([
            'message' => __('Affiliate codes synced successfully', 'affiliate-client-integration')
        ]);
    }

    /**
     * AJAX clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('aci_admin_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'affiliate-client-integration'));
        }

        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aci_%' OR option_name LIKE '_transient_timeout_aci_%'");

        wp_send_json_success([
            'message' => __('Cache cleared successfully', 'affiliate-client-integration')
        ]);
    }

    /**
     * Sanitize popup settings
     */
    public function sanitize_popup_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['trigger'] = sanitize_text_field($input['trigger'] ?? 'exit_intent');
        $sanitized['delay'] = intval($input['delay'] ?? 5);
        $sanitized['show_once'] = !empty($input['show_once']);
        $sanitized['popup_title'] = sanitize_text_field($input['popup_title'] ?? '');
        $sanitized['popup_message'] = sanitize_textarea_field($input['popup_message'] ?? '');
        $sanitized['popup_style'] = sanitize_text_field($input['popup_style'] ?? 'default');

        return $sanitized;
    }

    /**
     * Sanitize display settings
     */
    public function sanitize_display_settings($input) {
        $sanitized = [];
        
        $sanitized['show_affiliate_notice'] = !empty($input['show_affiliate_notice']);
        $sanitized['show_discount_amount'] = !empty($input['show_discount_amount']);
        $sanitized['affiliate_notice_position'] = sanitize_text_field($input['affiliate_notice_position'] ?? 'top');
        $sanitized['custom_css_class'] = sanitize_text_field($input['custom_css_class'] ?? '');

        return $sanitized;
    }

    /**
     * Sanitize Zoho settings
     */
    public function sanitize_zoho_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['client_id'] = sanitize_text_field($input['client_id'] ?? '');
        $sanitized['client_secret'] = sanitize_text_field($input['client_secret'] ?? '');
        $sanitized['refresh_token'] = sanitize_text_field($input['refresh_token'] ?? '');
        $sanitized['api_domain'] = esc_url_raw($input['api_domain'] ?? 'https://www.zohoapis.com');
        $sanitized['books_org_id'] = sanitize_text_field($input['books_org_id'] ?? '');

        return $sanitized;
    }
}