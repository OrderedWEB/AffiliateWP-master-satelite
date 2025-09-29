<?php
/**
 * Client Admin Interface for Affiliate Client Integration
 * 
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/admin/class-client-admin.php
 * 
 * Provides the admin interface for client sites to configure and monitor
 * their affiliate integration with comprehensive settings and analytics.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Client_Admin {

    private $api_handler;
    private $popup_manager;
    private $url_handler;
    private $shortcode_manager;
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_handler = new ACI_API_Handler();
        $this->popup_manager = new ACI_Popup_Manager();
        $this->url_handler = new ACI_URL_Handler();
        $this->shortcode_manager = new ACI_Shortcode_Manager();
        $this->settings = get_option('aci_settings', []);
        
        // Initialize hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        
        // AJAX handlers
        add_action('wp_ajax_aci_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_aci_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_aci_get_analytics', [$this, 'ajax_get_analytics']);
        add_action('wp_ajax_aci_export_data', [$this, 'ajax_export_data']);
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Affiliate Integration', 'affiliate-client-integration'),
            __('Affiliate Integration', 'affiliate-client-integration'),
            'manage_options',
            'aci-settings',
            [$this, 'render_settings_page'],
            'dashicons-networking',
            30
        );

        add_submenu_page(
            'aci-settings',
            __('Settings', 'affiliate-client-integration'),
            __('Settings', 'affiliate-client-integration'),
            'manage_options',
            'aci-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'aci-settings',
            __('Analytics', 'affiliate-client-integration'),
            __('Analytics', 'affiliate-client-integration'),
            'manage_options',
            'aci-analytics',
            [$this, 'render_analytics_page']
        );

        add_submenu_page(
            'aci-settings',
            __('Documentation', 'affiliate-client-integration'),
            __('Documentation', 'affiliate-client-integration'),
            'manage_options',
            'aci-documentation',
            [$this, 'render_documentation_page']
        );

        add_submenu_page(
            'aci-settings',
            __('Support', 'affiliate-client-integration'),
            __('Support', 'affiliate-client-integration'),
            'manage_options',
            'aci-support',
            [$this, 'render_support_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('aci_settings', 'aci_settings', [$this, 'validate_settings']);
        
        // Connection Settings
        add_settings_section(
            'aci_connection',
            __('Connection Settings', 'affiliate-client-integration'),
            [$this, 'connection_section_callback'],
            'aci_settings'
        );

        add_settings_field(
            'master_domain',
            __('Master Domain URL', 'affiliate-client-integration'),
            [$this, 'master_domain_callback'],
            'aci_settings',
            'aci_connection'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'affiliate-client-integration'),
            [$this, 'api_key_callback'],
            'aci_settings',
            'aci_connection'
        );

        // Popup Settings
        add_settings_section(
            'aci_popup',
            __('Popup Settings', 'affiliate-client-integration'),
            [$this, 'popup_section_callback'],
            'aci_settings'
        );

        add_settings_field(
            'popup_enabled',
            __('Enable Popups', 'affiliate-client-integration'),
            [$this, 'popup_enabled_callback'],
            'aci_settings',
            'aci_popup'
        );

        // URL Parameter Settings
        add_settings_section(
            'aci_url_params',
            __('URL Parameter Settings', 'affiliate-client-integration'),
            [$this, 'url_params_section_callback'],
            'aci_settings'
        );

        // Discount Settings
        add_settings_section(
            'aci_discounts',
            __('Discount Settings', 'affiliate-client-integration'),
            [$this, 'discounts_section_callback'],
            'aci_settings'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'aci-') === false && $hook !== 'toplevel_page_aci-settings') {
            return;
        }

        wp_enqueue_script(
            'aci-admin',
            ACI_PLUGIN_URL . 'admin/js/admin-settings.js',
            ['jquery', 'wp-util'],
            ACI_VERSION,
            true
        );

        wp_enqueue_style(
            'aci-admin',
            ACI_PLUGIN_URL . 'admin/css/admin-settings.css',
            [],
            ACI_VERSION
        );

        // Chart.js for analytics
        if ($hook === 'affiliate-integration_page_aci-analytics') {
            wp_enqueue_script(
                'chartjs',
                'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
                [],
                '3.9.1',
                true
            );
        }

        wp_localize_script('aci-admin', 'aciAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aci_admin_nonce'),
            'i18n' => [
                'testing' => __('Testing connection...', 'affiliate-client-integration'),
                'saving' => __('Saving settings...', 'affiliate-client-integration'),
                'success' => __('Success!', 'affiliate-client-integration'),
                'error' => __('Error occurred', 'affiliate-client-integration'),
                'confirm' => __('Are you sure?', 'affiliate-client-integration')
            ]
        ]);
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'aci-') === false) {
            return;
        }

        // Check if configuration is complete
        if (empty($this->settings['master_domain']) || empty($this->settings['api_key'])) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Affiliate Integration Setup Required', 'affiliate-client-integration'); ?></strong>
                    <br>
                    <?php _e('Please configure your master domain and API key to enable affiliate functionality.', 'affiliate-client-integration'); ?>
                    <a href="<?php echo admin_url('admin.php?page=aci-settings'); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php _e('Configure Now', 'affiliate-client-integration'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        // Check API connection health
        if (!empty($this->settings['master_domain']) && !empty($this->settings['api_key'])) {
            $health_status = $this->api_handler->get_health_status();
            
            if (!$health_status['operational']) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php _e('API Connection Issue', 'affiliate-client-integration'); ?></strong>
                        <br>
                        <?php _e('Unable to connect to the master affiliate domain. Please check your settings.', 'affiliate-client-integration'); ?>
                        <button type="button" id="test-connection" class="button" style="margin-left: 10px;">
                            <?php _e('Test Connection', 'affiliate-client-integration'); ?>
                        </button>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = $_GET['tab'] ?? 'connection';
        ?>
        <div class="wrap aci-admin-wrap">
            <h1><?php _e('Affiliate Integration Settings', 'affiliate-client-integration'); ?></h1>

            <!-- Connection Status -->
            <div class="aci-status-card">
                <div class="aci-status-indicator" id="connection-status">
                    <div class="status-light status-unknown"></div>
                    <span class="status-text"><?php _e('Connection Status: Unknown', 'affiliate-client-integration'); ?></span>
                </div>
                <button type="button" id="test-connection-btn" class="button">
                    <?php _e('Test Connection', 'affiliate-client-integration'); ?>
                </button>
            </div>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="?page=aci-settings&tab=connection" 
                   class="nav-tab <?php echo $active_tab === 'connection' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Connection', 'affiliate-client-integration'); ?>
                </a>
                <a href="?page=aci-settings&tab=popups" 
                   class="nav-tab <?php echo $active_tab === 'popups' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Popups', 'affiliate-client-integration'); ?>
                </a>
                <a href="?page=aci-settings&tab=url-parameters" 
                   class="nav-tab <?php echo $active_tab === 'url-parameters' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('URL Parameters', 'affiliate-client-integration'); ?>
                </a>
                <a href="?page=aci-settings&tab=discounts" 
                   class="nav-tab <?php echo $active_tab === 'discounts' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Discounts', 'affiliate-client-integration'); ?>
                </a>
                <a href="?page=aci-settings&tab=advanced" 
                   class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Advanced', 'affiliate-client-integration'); ?>
                </a>
            </nav>

            <!-- Tab Content -->
            <form id="aci-settings-form" method="post" action="options.php">
                <?php settings_fields('aci_settings'); ?>
                
                <div class="tab-content">
                    <?php
                    switch ($active_tab) {
                        case 'connection':
                            $this->render_connection_tab();
                            break;
                        case 'popups':
                            $this->render_popups_tab();
                            break;
                        case 'url-parameters':
                            $this->render_url_parameters_tab();
                            break;
                        case 'discounts':
                            $this->render_discounts_tab();
                            break;
                        case 'advanced':
                            $this->render_advanced_tab();
                            break;
                    }
                    ?>
                </div>

                <?php submit_button(__('Save Settings', 'affiliate-client-integration'), 'primary', 'submit', true, ['id' => 'save-settings-btn']); ?>
            </form>
        </div>

        <style>
        .aci-admin-wrap {
            margin: 20px 20px 0 2px;
        }
        .aci-status-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .aci-status-indicator {
            display: flex;
            align-items: center;
        }
        .status-light {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-unknown { background: #ccc; }
        .status-connected { background: #46b450; }
        .status-error { background: #dc3232; }
        .status-warning { background: #ffb900; }
        .tab-content {
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            padding: 20px;
        }
        .aci-setting-group {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .aci-setting-group:last-child {
            border-bottom: none;
        }
        .aci-setting-group h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        .aci-form-table {
            width: 100%;
        }
        .aci-form-table th {
            width: 200px;
            text-align: left;
            padding: 15px 10px 15px 0;
            vertical-align: top;
        }
        .aci-form-table td {
            padding: 15px 0;
        }
        .aci-form-table input[type="text"],
        .aci-form-table input[type="url"],
        .aci-form-table input[type="number"],
        .aci-form-table select,
        .aci-form-table textarea {
            width: 400px;
            max-width: 100%;
        }
        .aci-form-table textarea {
            height: 100px;
        }
        .aci-description {
            font-style: italic;
            color: #666;
            margin-top: 5px;
        }
        .aci-preview-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        .aci-shortcode-examples {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            margin-top: 10px;
        }
        .aci-shortcode-examples code {
            display: block;
            margin: 5px 0;
            padding: 5px;
            background: #fff;
            border-radius: 3px;
        }
        </style>
        <?php
    }

    /**
     * Render connection tab
     */
    private function render_connection_tab() {
        ?>
        <div class="aci-setting-group">
            <h3><?php _e('Master Domain Configuration', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="master_domain"><?php _e('Master Domain URL', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               id="master_domain" 
                               name="aci_settings[master_domain]" 
                               value="<?php echo esc_url($this->settings['master_domain'] ?? ''); ?>" 
                               placeholder="https://affiliate.example.com"
                               required>
                        <p class="aci-description">
                            <?php _e('The URL of your main affiliate domain where the master plugin is installed.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="api_key"><?php _e('API Key', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="api_key" 
                               name="aci_settings[api_key]" 
                               value="<?php echo esc_attr($this->settings['api_key'] ?? ''); ?>" 
                               placeholder="affcd_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                               required>
                        <button type="button" id="toggle-api-key" class="button"><?php _e('Show/Hide', 'affiliate-client-integration'); ?></button>
                        <p class="aci-description">
                            <?php _e('Your unique API key provided by the master domain administrator.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-setting-group">
            <h3><?php _e('Connection Options', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="timeout"><?php _e('Request Timeout', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="timeout" 
                               name="aci_settings[timeout]" 
                               value="<?php echo esc_attr($this->settings['timeout'] ?? 30); ?>" 
                               min="5" 
                               max="120">
                        <span><?php _e('seconds', 'affiliate-client-integration'); ?></span>
                        <p class="aci-description">
                            <?php _e('Maximum time to wait for API responses.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="retry_attempts"><?php _e('Retry Attempts', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="retry_attempts" 
                               name="aci_settings[retry_attempts]" 
                               value="<?php echo esc_attr($this->settings['retry_attempts'] ?? 3); ?>" 
                               min="1" 
                               max="10">
                        <p class="aci-description">
                            <?php _e('Number of times to retry failed requests.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="send_analytics"><?php _e('Send Analytics', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="send_analytics" 
                                   name="aci_settings[send_analytics]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['send_analytics'])); ?>>
                            <?php _e('Send usage analytics to master domain', 'affiliate-client-integration'); ?>
                        </label>
                        <p class="aci-description">
                            <?php _e('Helps improve the service by sharing anonymous usage data.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render popups tab
     */
    private function render_popups_tab() {
        ?>
        <div class="aci-setting-group">
            <h3><?php _e('Popup Configuration', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="popup_enabled"><?php _e('Enable Popups', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="popup_enabled" 
                                   name="aci_settings[popup][enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['popup']['enabled'])); ?>>
                            <?php _e('Enable affiliate popups on this site', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="popup_template"><?php _e('Default Template', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <select id="popup_template" name="aci_settings[popup][template]">
                            <option value="default" <?php selected($this->settings['popup']['template'] ?? 'default', 'default'); ?>>
                                <?php _e('Default', 'affiliate-client-integration'); ?>
                            </option>
                            <option value="compact" <?php selected($this->settings['popup']['template'] ?? 'default', 'compact'); ?>>
                                <?php _e('Compact', 'affiliate-client-integration'); ?>
                            </option>
                            <option value="fullscreen" <?php selected($this->settings['popup']['template'] ?? 'default', 'fullscreen'); ?>>
                                <?php _e('Fullscreen', 'affiliate-client-integration'); ?>
                            </option>
                            <option value="slide_in" <?php selected($this->settings['popup']['template'] ?? 'default', 'slide_in'); ?>>
                                <?php _e('Slide-in', 'affiliate-client-integration'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="popup_title"><?php _e('Default Title', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="popup_title" 
                               name="aci_settings[popup][title]" 
                               value="<?php echo esc_attr($this->settings['popup']['title'] ?? __('Enter Affiliate Code', 'affiliate-client-integration')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="popup_description"><?php _e('Default Description', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <textarea id="popup_description" 
                                  name="aci_settings[popup][description]"><?php echo esc_textarea($this->settings['popup']['description'] ?? __('Enter your affiliate code to unlock exclusive discounts.', 'affiliate-client-integration')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="popup_collect_email"><?php _e('Collect Email', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="popup_collect_email" 
                                   name="aci_settings[popup][collect_email]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['popup']['collect_email'])); ?>>
                            <?php _e('Show email field in popups', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="popup_show_frequency"><?php _e('Show Frequency', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <select id="popup_show_frequency" name="aci_settings[popup][show_frequency]">
                            <option value="once_per_session" <?php selected($this->settings['popup']['show_frequency'] ?? 'once_per_session', 'once_per_session'); ?>>
                                <?php _e('Once per session', 'affiliate-client-integration'); ?>
                            </option>
                            <option value="once_per_day" <?php selected($this->settings['popup']['show_frequency'] ?? 'once_per_session', 'once_per_day'); ?>>
                                <?php _e('Once per day', 'affiliate-client-integration'); ?>
                            </option>
                            <option value="always" <?php selected($this->settings['popup']['show_frequency'] ?? 'once_per_session', 'always'); ?>>
                                <?php _e('Always show', 'affiliate-client-integration'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-setting-group">
            <h3><?php _e('Popup Triggers', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row"><?php _e('URL Parameter', 'affiliate-client-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="aci_settings[popup_triggers][url_parameter][enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['popup_triggers']['url_parameter']['enabled'])); ?>>
                            <?php _e('Show popup when affiliate parameter detected in URL', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Time Delay', 'affiliate-client-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="aci_settings[popup_triggers][time_delay][enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['popup_triggers']['time_delay']['enabled'])); ?>>
                            <?php _e('Show popup after', 'affiliate-client-integration'); ?>
                        </label>
                        <input type="number" 
                               name="aci_settings[popup_triggers][time_delay][delay]" 
                               value="<?php echo esc_attr($this->settings['popup_triggers']['time_delay']['delay'] ?? 5); ?>" 
                               min="1" 
                               max="300"
                               style="width: 80px; margin: 0 5px;">
                        <span><?php _e('seconds', 'affiliate-client-integration'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Scroll Percentage', 'affiliate-client-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="aci_settings[popup_triggers][scroll_percentage][enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['popup_triggers']['scroll_percentage']['enabled'])); ?>>
                            <?php _e('Show popup when user scrolls', 'affiliate-client-integration'); ?>
                        </label>
                        <input type="number" 
                               name="aci_settings[popup_triggers][scroll_percentage][percentage]" 
                               value="<?php echo esc_attr($this->settings['popup_triggers']['scroll_percentage']['percentage'] ?? 50); ?>" 
                               min="10" 
                               max="100"
                               style="width: 80px; margin: 0 5px;">
                        <span><?php _e('% of page', 'affiliate-client-integration'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Exit Intent', 'affiliate-client-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="aci_settings[popup_triggers][exit_intent][enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['popup_triggers']['exit_intent']['enabled'])); ?>>
                            <?php _e('Show popup when user is about to leave', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-preview-box">
            <h4><?php _e('Popup Shortcode Examples', 'affiliate-client-integration'); ?></h4>
            <div class="aci-shortcode-examples">
                <code>[affiliate_popup template="default" title="Special Offer"]</code>
                <code>[affiliate_popup_trigger text="Get Discount"]Click here for discount[/affiliate_popup_trigger]</code>
                <code>[affiliate_form style="compact" collect_email="true"]</code>
            </div>
        </div>
        <?php
    }

    /**
     * Render URL parameters tab
     */
    private function render_url_parameters_tab() {
        $url_parameters = $this->settings['url_parameters'] ?? ['affiliate', 'aff', 'ref'];
        ?>
        <div class="aci-setting-group">
            <h3><?php _e('URL Parameter Detection', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="primary_parameter"><?php _e('Primary Parameter', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="primary_parameter" 
                               name="aci_settings[primary_parameter]" 
                               value="<?php echo esc_attr($this->settings['primary_parameter'] ?? 'affiliate'); ?>">
                     <p class="aci-description">
                            <?php _e('The main URL parameter name to look for affiliate codes.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="url_parameters"><?php _e('Additional Parameters', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <textarea id="url_parameters" 
                                  name="aci_settings[url_parameters]" 
                                  placeholder="aff&#10;ref&#10;referral&#10;partner"><?php echo esc_textarea(implode("\n", $url_parameters)); ?></textarea>
                        <p class="aci-description">
                            <?php _e('One parameter name per line. These will be checked in addition to the primary parameter.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="track_utm"><?php _e('Track UTM Parameters', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="track_utm" 
                                   name="aci_settings[track_utm]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['track_utm'])); ?>>
                            <?php _e('Track UTM parameters for analytics', 'affiliate-client-integration'); ?>
                        </label>
                        <p class="aci-description">
                            <?php _e('Captures utm_source, utm_medium, utm_campaign for detailed tracking.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="session_duration"><?php _e('Session Duration', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="session_duration" 
                               name="aci_settings[session_duration]" 
                               value="<?php echo esc_attr($this->settings['session_duration'] ?? 30); ?>" 
                               min="1" 
                               max="365">
                        <span><?php _e('days', 'affiliate-client-integration'); ?></span>
                        <p class="aci-description">
                            <?php _e('How long to remember the affiliate code in cookies.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-setting-group">
            <h3><?php _e('URL Processing Options', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="validate_realtime"><?php _e('Real-time Validation', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="validate_realtime" 
                                   name="aci_settings[validate_realtime]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['validate_realtime'])); ?>>
                            <?php _e('Validate affiliate codes immediately when detected', 'affiliate-client-integration'); ?>
                        </label>
                        <p class="aci-description">
                            <?php _e('Validates codes with master domain as soon as they are detected in URLs.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="redirect_after_detection"><?php _e('Clean URL After Detection', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="redirect_after_detection" 
                                   name="aci_settings[redirect_after_detection]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['redirect_after_detection'])); ?>>
                            <?php _e('Remove affiliate parameters from URL after processing', 'affiliate-client-integration'); ?>
                        </label>
                        <p class="aci-description">
                            <?php _e('Redirects to clean URL while preserving affiliate data in session.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-preview-box">
            <h4><?php _e('URL Parameter Shortcode Examples', 'affiliate-client-integration'); ?></h4>
            <div class="aci-shortcode-examples">
                <code>[affiliate_url_param parameter="affiliate" default="No code detected"]</code>
                <code>[affiliate_if_active]Content shown only when affiliate is active[/affiliate_if_active]</code>
                <code>[affiliate_referrer_info show="domain"]</code>
                <code>[affiliate_code_info show="all" format="table"]</code>
            </div>
        </div>
        <?php
    }

    /**
     * Render discounts tab
     */
    private function render_discounts_tab() {
        ?>
        <div class="aci-setting-group">
            <h3><?php _e('Automatic Discounts', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_discount_enabled"><?php _e('Enable Auto Discounts', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="auto_discount_enabled" 
                                   name="aci_settings[auto_discount][enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['auto_discount']['enabled'])); ?>>
                            <?php _e('Automatically apply discounts when affiliate codes are validated', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="discount_type"><?php _e('Discount Type', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <select id="discount_type" name="aci_settings[auto_discount][type]">
                            <option value="percentage" <?php selected($this->settings['auto_discount']['type'] ?? 'percentage', 'percentage'); ?>>
                                <?php _e('Percentage', 'affiliate-client-integration'); ?>
                            </option>
                            <option value="fixed" <?php selected($this->settings['auto_discount']['type'] ?? 'percentage', 'fixed'); ?>>
                                <?php _e('Fixed Amount', 'affiliate-client-integration'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="discount_amount"><?php _e('Discount Amount', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="discount_amount" 
                               name="aci_settings[auto_discount][amount]" 
                               value="<?php echo esc_attr($this->settings['auto_discount']['amount'] ?? 10); ?>" 
                               min="0" 
                               step="0.01">
                        <span id="discount-unit">%</span>
                        <p class="aci-description">
                            <?php _e('The discount amount to apply automatically.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="show_notifications"><?php _e('Show Notifications', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_notifications" 
                                   name="aci_settings[show_notifications]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['show_notifications'])); ?>>
                            <?php _e('Show discount notifications to users', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php if (class_exists('WooCommerce')): ?>
        <div class="aci-setting-group">
            <h3><?php _e('WooCommerce Integration', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_integration"><?php _e('WooCommerce Integration', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="woo_integration" 
                                   name="aci_settings[woocommerce][enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['woocommerce']['enabled'])); ?>>
                            <?php _e('Enable WooCommerce integration', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_track_orders"><?php _e('Track Orders', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="woo_track_orders" 
                                   name="aci_settings[woocommerce][track_orders]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['woocommerce']['track_orders'])); ?>>
                            <?php _e('Track order conversions and send to master domain', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_coupon_prefix"><?php _e('Coupon Prefix', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="woo_coupon_prefix" 
                               name="aci_settings[woocommerce][coupon_prefix]" 
                               value="<?php echo esc_attr($this->settings['woocommerce']['coupon_prefix'] ?? 'AFF'); ?>"
                               style="width: 100px;">
                        <p class="aci-description">
                            <?php _e('Prefix for automatically generated WooCommerce coupons.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <div class="aci-preview-box">
            <h4><?php _e('Discount Shortcode Examples', 'affiliate-client-integration'); ?></h4>
            <div class="aci-shortcode-examples">
                <code>[affiliate_discount_display format="badge" show_code="true"]</code>
                <code>[affiliate_success_message show_discount="true"]</code>
                <code>[affiliate_woocommerce_notice type="success"]</code>
                <code>[affiliate_discount_form discount_amount="15" discount_type="percentage"]</code>
            </div>
        </div>
        <?php
    }

    /**
     * Render advanced tab
     */
    private function render_advanced_tab() {
        ?>
        <div class="aci-setting-group">
            <h3><?php _e('Performance & Caching', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="cache_duration"><?php _e('Cache Duration', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="cache_duration" 
                               name="aci_settings[cache_duration]" 
                               value="<?php echo esc_attr($this->settings['cache_duration'] ?? 300); ?>" 
                               min="0" 
                               max="3600">
                        <span><?php _e('seconds', 'affiliate-client-integration'); ?></span>
                        <p class="aci-description">
                            <?php _e('How long to cache API responses. Set to 0 to disable caching.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lazy_load"><?php _e('Lazy Load Assets', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="lazy_load" 
                                   name="aci_settings[lazy_load]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['lazy_load'])); ?>>
                            <?php _e('Only load scripts and styles when needed', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-setting-group">
            <h3><?php _e('Privacy & Compliance', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="gdpr_compliance"><?php _e('GDPR Compliance', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="gdpr_compliance" 
                                   name="aci_settings[gdpr_compliance]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['gdpr_compliance'])); ?>>
                            <?php _e('Enable GDPR compliance features', 'affiliate-client-integration'); ?>
                        </label>
                        <p class="aci-description">
                            <?php _e('Requires consent before setting cookies and collecting data.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="anonymize_ip"><?php _e('Anonymize IPs', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="anonymize_ip" 
                                   name="aci_settings[anonymize_ip]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['anonymize_ip'])); ?>>
                            <?php _e('Anonymize IP addresses in tracking data', 'affiliate-client-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="data_retention"><?php _e('Data Retention', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="data_retention" 
                               name="aci_settings[data_retention]" 
                               value="<?php echo esc_attr($this->settings['data_retention'] ?? 90); ?>" 
                               min="1" 
                               max="365">
                        <span><?php _e('days', 'affiliate-client-integration'); ?></span>
                        <p class="aci-description">
                            <?php _e('How long to keep tracking data before automatic deletion.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-setting-group">
            <h3><?php _e('Debugging & Logging', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php _e('Debug Mode', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="debug_mode" 
                                   name="aci_settings[debug_mode]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['debug_mode'])); ?>>
                            <?php _e('Enable debug logging', 'affiliate-client-integration'); ?>
                        </label>
                        <p class="aci-description">
                            <?php _e('Logs detailed information for troubleshooting. Disable in production.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="log_retention"><?php _e('Log Retention', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="log_retention" 
                               name="aci_settings[log_retention]" 
                               value="<?php echo esc_attr($this->settings['log_retention'] ?? 30); ?>" 
                               min="1" 
                               max="90">
                        <span><?php _e('days', 'affiliate-client-integration'); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="aci-setting-group">
            <h3><?php _e('Integration Hooks', 'affiliate-client-integration'); ?></h3>
            <table class="aci-form-table">
                <tr>
                    <th scope="row">
                        <label for="custom_hooks"><?php _e('Custom Hooks', 'affiliate-client-integration'); ?></label>
                    </th>
                    <td>
                        <textarea id="custom_hooks" 
                                  name="aci_settings[custom_hooks]" 
                                  placeholder="// Custom PHP code to execute on affiliate detection&#10;// Example:&#10;// do_action('my_custom_affiliate_action', $affiliate_data);"><?php echo esc_textarea($this->settings['custom_hooks'] ?? ''); ?></textarea>
                        <p class="aci-description">
                            <?php _e('Custom PHP code to execute when affiliate codes are detected. Use with caution.', 'affiliate-client-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap aci-admin-wrap">
            <h1><?php _e('Affiliate Analytics', 'affiliate-client-integration'); ?></h1>

            <!-- Analytics Dashboard -->
            <div class="aci-analytics-dashboard">
                <!-- Key Metrics -->
                <div class="aci-metrics-grid" id="aci-metrics">
                    <div class="aci-metric-card">
                        <div class="metric-value" id="total-clicks">-</div>
                        <div class="metric-label"><?php _e('Total Clicks', 'affiliate-client-integration'); ?></div>
                    </div>
                    <div class="aci-metric-card">
                        <div class="metric-value" id="total-conversions">-</div>
                        <div class="metric-label"><?php _e('Conversions', 'affiliate-client-integration'); ?></div>
                    </div>
                    <div class="aci-metric-card">
                        <div class="metric-value" id="conversion-rate">-</div>
                        <div class="metric-label"><?php _e('Conversion Rate', 'affiliate-client-integration'); ?></div>
                    </div>
                    <div class="aci-metric-card">
                        <div class="metric-value" id="total-revenue">-</div>
                        <div class="metric-label"><?php _e('Revenue', 'affiliate-client-integration'); ?></div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="aci-charts-grid">
                    <div class="aci-chart-container">
                        <h3><?php _e('Traffic Over Time', 'affiliate-client-integration'); ?></h3>
                        <canvas id="traffic-chart"></canvas>
                    </div>
                    <div class="aci-chart-container">
                        <h3><?php _e('Top Affiliate Codes', 'affiliate-client-integration'); ?></h3>
                        <canvas id="codes-chart"></canvas>
                    </div>
                </div>

                <!-- Data Tables -->
                <div class="aci-table-container">
                    <h3><?php _e('Recent Activity', 'affiliate-client-integration'); ?></h3>
                    <table class="wp-list-table widefat fixed striped" id="recent-activity-table">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Affiliate Code', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Source', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Status', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Value', 'affiliate-client-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="activity-tbody">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">
                                    <?php _e('Loading analytics data...', 'affiliate-client-integration'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Export Options -->
                <div class="aci-export-section">
                    <h3><?php _e('Export Data', 'affiliate-client-integration'); ?></h3>
                    <div class="aci-export-controls">
                        <select id="export-period">
                            <option value="7d"><?php _e('Last 7 Days', 'affiliate-client-integration'); ?></option>
                            <option value="30d"><?php _e('Last 30 Days', 'affiliate-client-integration'); ?></option>
                            <option value="90d"><?php _e('Last 90 Days', 'affiliate-client-integration'); ?></option>
                        </select>
                        <select id="export-format">
                            <option value="csv"><?php _e('CSV', 'affiliate-client-integration'); ?></option>
                            <option value="json"><?php _e('JSON', 'affiliate-client-integration'); ?></option>
                        </select>
                        <button type="button" id="export-data-btn" class="button button-primary">
                            <?php _e('Export Data', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .aci-analytics-dashboard {
            margin: 20px 0;
        }
        .aci-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .aci-metric-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        .metric-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 10px;
        }
        .metric-label {
            color: #666;
            font-size: 0.9em;
        }
        .aci-charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .aci-chart-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
        }
        .aci-table-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .aci-export-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
        }
        .aci-export-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Load analytics data
            ACI_Analytics.loadDashboard();
            
            // Export functionality
            $('#export-data-btn').on('click', function() {
                var period = $('#export-period').val();
                var format = $('#export-format').val();
                ACI_Analytics.exportData(period, format);
            });
        });
        </script>
        <?php
    }

    /**
     * Render documentation page
     */
    public function render_documentation_page() {
        ?>
        <div class="wrap aci-admin-wrap">
            <h1><?php _e('Documentation', 'affiliate-client-integration'); ?></h1>
            
            <div class="aci-documentation">
                <!-- Quick Start Guide -->
                <div class="aci-doc-section">
                    <h2><?php _e('Quick Start Guide', 'affiliate-client-integration'); ?></h2>
                    <ol>
                        <li><?php _e('Configure your master domain URL and API key in the Connection tab', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Test the connection to ensure everything is working', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Configure your preferred URL parameters and popup settings', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Add shortcodes to your pages where you want affiliate functionality', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Monitor performance in the Analytics section', 'affiliate-client-integration'); ?></li>
                    </ol>
                </div>

             <div class="aci-doc-section">
                    <h2><?php _e('Available Shortcodes', 'affiliate-client-integration'); ?></h2>
                    
                    <h3><?php _e('Form Shortcodes', 'affiliate-client-integration'); ?></h3>
                    <table class="aci-doc-table">
                        <thead>
                            <tr>
                                <th><?php _e('Shortcode', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Description', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Example', 'affiliate-client-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[affiliate_form]</code></td>
                                <td><?php _e('Display a full affiliate code form', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_form template="default"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[affiliate_form_inline]</code></td>
                                <td><?php _e('Display an inline affiliate code input', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_form_inline placeholder="Enter code"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[affiliate_discount_form]</code></td>
                                <td><?php _e('Display a discount application form', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_discount_form show_validation="true"]</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3><?php _e('Display Shortcodes', 'affiliate-client-integration'); ?></h3>
                    <table class="aci-doc-table">
                        <thead>
                            <tr>
                                <th><?php _e('Shortcode', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Description', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Example', 'affiliate-client-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[affiliate_discount_display]</code></td>
                                <td><?php _e('Show active discount information', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_discount_display format="badge"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[affiliate_success_message]</code></td>
                                <td><?php _e('Success message when code is applied', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_success_message show_discount="true"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[affiliate_code_info]</code></td>
                                <td><?php _e('Display current affiliate code information', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_code_info format="table"]</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3><?php _e('Popup Shortcodes', 'affiliate-client-integration'); ?></h3>
                    <table class="aci-doc-table">
                        <thead>
                            <tr>
                                <th><?php _e('Shortcode', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Description', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Example', 'affiliate-client-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[affiliate_popup_trigger]</code></td>
                                <td><?php _e('Create a button to trigger the affiliate popup', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_popup_trigger text="Enter Code"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[affiliate_popup_form]</code></td>
                                <td><?php _e('Display a popup form on page', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_popup_form trigger="exit_intent"]</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3><?php _e('URL Parameter Shortcodes', 'affiliate-client-integration'); ?></h3>
                    <table class="aci-doc-table">
                        <thead>
                            <tr>
                                <th><?php _e('Shortcode', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Description', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Example', 'affiliate-client-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[affiliate_url_param]</code></td>
                                <td><?php _e('Display value from URL parameter', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_url_param parameter="aff"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[affiliate_referrer_info]</code></td>
                                <td><?php _e('Display referrer information', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_referrer_info show="domain"]</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3><?php _e('Conditional Shortcodes', 'affiliate-client-integration'); ?></h3>
                    <table class="aci-doc-table">
                        <thead>
                            <tr>
                                <th><?php _e('Shortcode', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Description', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Example', 'affiliate-client-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[affiliate_if_active]...[/affiliate_if_active]</code></td>
                                <td><?php _e('Show content only if affiliate is active', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_if_active]Thank you![/affiliate_if_active]</code></td>
                            </tr>
                            <tr>
                                <td><code>[affiliate_if_code]...[/affiliate_if_code]</code></td>
                                <td><?php _e('Show content if specific code matches', 'affiliate-client-integration'); ?></td>
                                <td><code>[affiliate_if_code code="SPECIAL"]Content[/affiliate_if_code]</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Shortcode Parameters -->
                <div class="aci-doc-section">
                    <h2><?php _e('Common Shortcode Parameters', 'affiliate-client-integration'); ?></h2>
                    <table class="aci-doc-table">
                        <thead>
                            <tr>
                                <th><?php _e('Parameter', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Description', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Default', 'affiliate-client-integration'); ?></th>
                                <th><?php _e('Example', 'affiliate-client-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>template</code></td>
                                <td><?php _e('Template style to use', 'affiliate-client-integration'); ?></td>
                                <td><code>default</code></td>
                                <td><code>template="minimal"</code></td>
                            </tr>
                            <tr>
                                <td><code>class</code></td>
                                <td><?php _e('Additional CSS classes', 'affiliate-client-integration'); ?></td>
                                <td><code>""</code></td>
                                <td><code>class="my-custom-class"</code></td>
                            </tr>
                            <tr>
                                <td><code>show_validation</code></td>
                                <td><?php _e('Show real-time validation', 'affiliate-client-integration'); ?></td>
                                <td><code>true</code></td>
                                <td><code>show_validation="false"</code></td>
                            </tr>
                            <tr>
                                <td><code>redirect_url</code></td>
                                <td><?php _e('URL to redirect after success', 'affiliate-client-integration'); ?></td>
                                <td><code>""</code></td>
                                <td><code>redirect_url="/thank-you"</code></td>
                            </tr>
                            <tr>
                                <td><code>format</code></td>
                                <td><?php _e('Display format', 'affiliate-client-integration'); ?></td>
                                <td><code>default</code></td>
                                <td><code>format="badge"</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- URL Parameters -->
                <div class="aci-doc-section">
                    <h2><?php _e('URL Parameter Configuration', 'affiliate-client-integration'); ?></h2>
                    <p><?php _e('The plugin automatically detects affiliate codes from URL parameters. Configure your preferred parameter name in Settings.', 'affiliate-client-integration'); ?></p>
                    
                    <h3><?php _e('Default URL Structure', 'affiliate-client-integration'); ?></h3>
                    <pre><code>https://yoursite.com/?aff=AFFILIATE_CODE</code></pre>
                    
                    <h3><?php _e('Custom Parameter Names', 'affiliate-client-integration'); ?></h3>
                    <pre><code>https://yoursite.com/?affiliate=CODE
https://yoursite.com/?ref=CODE
https://yoursite.com/?referral=CODE</code></pre>
                    
                    <p><?php _e('Configure your preferred parameter name in Settings > URL Parameters.', 'affiliate-client-integration'); ?></p>
                </div>

                <!-- Popup Configuration -->
                <div class="aci-doc-section">
                    <h2><?php _e('Popup Configuration', 'affiliate-client-integration'); ?></h2>
                    <p><?php _e('Configure popups to capture affiliate codes from visitors.', 'affiliate-client-integration'); ?></p>
                    
                    <h3><?php _e('Trigger Types', 'affiliate-client-integration'); ?></h3>
                    <ul>
                        <li><strong><?php _e('Exit Intent:', 'affiliate-client-integration'); ?></strong> <?php _e('Triggers when user moves cursor to leave page', 'affiliate-client-integration'); ?></li>
                        <li><strong><?php _e('Time Delay:', 'affiliate-client-integration'); ?></strong> <?php _e('Triggers after specified seconds on page', 'affiliate-client-integration'); ?></li>
                        <li><strong><?php _e('Scroll Percentage:', 'affiliate-client-integration'); ?></strong> <?php _e('Triggers when user scrolls to percentage', 'affiliate-client-integration'); ?></li>
                        <li><strong><?php _e('Manual:', 'affiliate-client-integration'); ?></strong> <?php _e('Triggered by button click', 'affiliate-client-integration'); ?></li>
                    </ul>
                </div>

                <!-- API Integration -->
                <div class="aci-doc-section">
                    <h2><?php _e('API Integration', 'affiliate-client-integration'); ?></h2>
                    <p><?php _e('The plugin communicates with your master affiliate domain via REST API.', 'affiliate-client-integration'); ?></p>
                    
                    <h3><?php _e('Required Configuration', 'affiliate-client-integration'); ?></h3>
                    <ol>
                        <li><?php _e('Master Domain URL', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('API Key', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('API Secret (optional for enhanced security)', 'affiliate-client-integration'); ?></li>
                    </ol>
                    
                    <h3><?php _e('API Endpoints Used', 'affiliate-client-integration'); ?></h3>
                    <ul>
                        <li><code>/wp-json/affcd/v1/validate</code> - <?php _e('Validate affiliate codes', 'affiliate-client-integration'); ?></li>
                        <li><code>/wp-json/affcd/v1/track</code> - <?php _e('Track events and conversions', 'affiliate-client-integration'); ?></li>
                        <li><code>/wp-json/affcd/v1/config</code> - <?php _e('Sync configuration', 'affiliate-client-integration'); ?></li>
                        <li><code>/wp-json/affcd/v1/health</code> - <?php _e('Health check', 'affiliate-client-integration'); ?></li>
                    </ul>
                </div>

                <!-- Troubleshooting -->
                <div class="aci-doc-section">
                    <h2><?php _e('Troubleshooting', 'affiliate-client-integration'); ?></h2>
                    
                    <h3><?php _e('Connection Issues', 'affiliate-client-integration'); ?></h3>
                    <ul>
                        <li><?php _e('Verify master domain URL is correct (include https://)', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Check API key is valid and active', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Ensure SSL certificate is valid if using HTTPS', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Check firewall settings allow API communication', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Use the Connection Test tool in Settings', 'affiliate-client-integration'); ?></li>
                    </ul>
                    
                    <h3><?php _e('Code Validation Issues', 'affiliate-client-integration'); ?></h3>
                    <ul>
                        <li><?php _e('Verify code exists and is active on master domain', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Check code expiry date has not passed', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Verify usage limit has not been reached', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Clear cache if code was recently created', 'affiliate-client-integration'); ?></li>
                    </ul>
                    
                    <h3><?php _e('Performance Issues', 'affiliate-client-integration'); ?></h3>
                    <ul>
                        <li><?php _e('Enable caching in Advanced Settings', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Adjust cache duration for optimal performance', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Use object caching if available', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Review error logs for specific issues', 'affiliate-client-integration'); ?></li>
                    </ul>
                </div>

                <!-- Support -->
                <div class="aci-doc-section">
                    <h2><?php _e('Support', 'affiliate-client-integration'); ?></h2>
                    <p>
                        <?php _e('For additional support:', 'affiliate-client-integration'); ?>
                    </p>
                    <ul>
                        <li><?php _e('Visit the Support page for system diagnostics', 'affiliate-client-integration'); ?></li>
                        <li><?php _e('Review error logs in Advanced Settings', 'affiliate-client-integration'); ?></li>
                        <li><?php printf(__('Contact: %s', 'affiliate-client-integration'), '<a href="mailto:r.king@starneconsulting.com">r.king@starneconsulting.com</a>'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <style>
        .aci-documentation {
            max-width: 1200px;
        }
        .aci-doc-section {
            background: #fff;
            border: 1px solid #ddd;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .aci-doc-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
            color: #0073aa;
        }
        .aci-doc-section h3 {
            color: #333;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .aci-doc-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .aci-doc-table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        .aci-doc-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .aci-doc-table code {
            background: #f9f9f9;
            padding: 2px 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
        }
        .aci-doc-section pre {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .aci-doc-section pre code {
            background: transparent;
            border: none;
            padding: 0;
        }
        .aci-doc-section ul {
            padding-left: 20px;
        }
        .aci-doc-section ul li {
            margin: 8px 0;
        }
        </style>
        <?php
    }




          /**
     * Render support page
     */
    public function render_support_page() {
        ?>
        <div class="wrap aci-admin-wrap">
            <h1><?php _e('Support', 'affiliate-client-integration'); ?></h1>
            
            <div class="aci-support-container">
                <!-- System Information -->
                <div class="aci-support-section">
                    <h2><?php _e('System Information', 'affiliate-client-integration'); ?></h2>
                    <table class="aci-info-table">
                        <tr>
                            <th><?php _e('Plugin Version:', 'affiliate-client-integration'); ?></th>
                            <td><?php echo defined('ACI_VERSION') ? ACI_VERSION : '1.0.0'; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('WordPress Version:', 'affiliate-client-integration'); ?></th>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('PHP Version:', 'affiliate-client-integration'); ?></th>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Site URL:', 'affiliate-client-integration'); ?></th>
                            <td><?php echo home_url(); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('API Status:', 'affiliate-client-integration'); ?></th>
                            <td>
                                <span class="status-indicator" id="api-status">
                                    <?php _e('Checking...', 'affiliate-client-integration'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Cache Status:', 'affiliate-client-integration'); ?></th>
                            <td>
                                <?php echo wp_using_ext_object_cache() ? 
                                    __('Active (Object Cache)', 'affiliate-client-integration') : 
                                    __('File-based', 'affiliate-client-integration'); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Help Resources -->
                <div class="aci-support-section">
                    <h2><?php _e('Help Resources', 'affiliate-client-integration'); ?></h2>
                    <ul class="aci-resource-list">
                        <li>
                            <strong><?php _e('Documentation:', 'affiliate-client-integration'); ?></strong>
                            <a href="<?php echo admin_url('admin.php?page=aci-documentation'); ?>">
                                <?php _e('View Documentation', 'affiliate-client-integration'); ?>
                            </a>
                        </li>
                        <li>
                            <strong><?php _e('Shortcode Reference:', 'affiliate-client-integration'); ?></strong>
                            <a href="<?php echo admin_url('admin.php?page=aci-documentation#shortcodes'); ?>">
                                <?php _e('View Shortcodes', 'affiliate-client-integration'); ?>
                            </a>
                        </li>
                        <li>
                            <strong><?php _e('API Documentation:', 'affiliate-client-integration'); ?></strong>
                            <a href="<?php echo admin_url('admin.php?page=aci-documentation#api'); ?>">
                                <?php _e('View API Docs', 'affiliate-client-integration'); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Contact Information -->
                <div class="aci-support-section">
                    <h2><?php _e('Contact Information', 'affiliate-client-integration'); ?></h2>
                    <p>
                        <strong><?php _e('Author:', 'affiliate-client-integration'); ?></strong> Richard King<br>
                        <strong><?php _e('Email:', 'affiliate-client-integration'); ?></strong> 
                        <a href="mailto:r.king@starneconsulting.com">r.king@starneconsulting.com</a><br>
                        <strong><?php _e('Website:', 'affiliate-client-integration'); ?></strong> 
                        <a href="https://starneconsulting.com" target="_blank">starneconsulting.com</a>
                    </p>
                </div>

                <!-- Diagnostic Tools -->
                <div class="aci-support-section">
                    <h2><?php _e('Diagnostic Tools', 'affiliate-client-integration'); ?></h2>
                    <div class="aci-diagnostic-tools">
                        <button type="button" id="run-diagnostics" class="button button-secondary">
                            <?php _e('Run System Diagnostics', 'affiliate-client-integration'); ?>
                        </button>
                        <button type="button" id="test-api-connection" class="button button-secondary">
                            <?php _e('Test API Connection', 'affiliate-client-integration'); ?>
                        </button>
                        <button type="button" id="view-error-logs" class="button button-secondary">
                            <?php _e('View Error Logs', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                    <div id="diagnostic-results" class="aci-diagnostic-results"></div>
                </div>
            </div>
        </div>

        <style>
        .aci-support-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        .aci-support-section {
            background: #fff;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 4px;
        }
        .aci-support-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .aci-info-table {
            width: 100%;
            margin-top: 15px;
        }
        .aci-info-table th {
            text-align: left;
            padding: 8px 10px;
            width: 180px;
            font-weight: 600;
        }
        .aci-info-table td {
            padding: 8px 10px;
        }
        .aci-resource-list {
            list-style: none;
            padding-left: 0;
        }
        .aci-resource-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .aci-resource-list li:last-child {
            border-bottom: none;
        }
        .aci-diagnostic-tools {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .aci-diagnostic-results {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: none;
        }
        .aci-diagnostic-results.active {
            display: block;
        }
        .status-indicator {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-indicator.ok {
            background: #46b450;
            color: #fff;
        }
        .status-indicator.error {
            background: #dc3232;
            color: #fff;
        }
        .status-indicator.warning {
            background: #ffb900;
            color: #000;
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Test API connection on page load
            ACI_Admin.testConnection(function(result) {
                var $status = $('#api-status');
                $status.removeClass('ok error warning');
                
                if (result.success) {
                    $status.addClass('ok').text('<?php _e('Connected', 'affiliate-client-integration'); ?>');
                } else {
                    $status.addClass('error').text('<?php _e('Connection Failed', 'affiliate-client-integration'); ?>');
                }
            });

            // Diagnostic tools
            $('#run-diagnostics').on('click', function() {
                ACI_Admin.runDiagnostics();
            });

            $('#test-api-connection').on('click', function() {
                ACI_Admin.testConnection();
            });

            $('#view-error-logs').on('click', function() {
                ACI_Admin.viewErrorLogs();
            });
        });
        </script>
        <?php
    }

    /**
     * Validate settings before saving
     */
    public function validate_settings($settings) {
        $validated = [];

        // Validate master domain
        if (!empty($settings['master_domain'])) {
            $validated['master_domain'] = esc_url_raw($settings['master_domain']);
            
            if (empty($validated['master_domain'])) {
                add_settings_error(
                    'aci_settings',
                    'invalid_master_domain',
                    __('Invalid master domain URL.', 'affiliate-client-integration'),
                    'error'
                );
            }
        }

        // Validate API credentials
        if (!empty($settings['api_key'])) {
            $validated['api_key'] = sanitize_text_field($settings['api_key']);
        }

        if (!empty($settings['api_secret'])) {
            $validated['api_secret'] = sanitize_text_field($settings['api_secret']);
        }

        // Validate timeout
        if (isset($settings['timeout'])) {
            $timeout = absint($settings['timeout']);
            $validated['timeout'] = max(5, min(60, $timeout));
        }

        // Validate cache duration
        if (isset($settings['cache_duration'])) {
            $cache_duration = absint($settings['cache_duration']);
            $validated['cache_duration'] = max(300, min(86400, $cache_duration));
        }

        // Validate retry attempts
        if (isset($settings['retry_attempts'])) {
            $retry = absint($settings['retry_attempts']);
            $validated['retry_attempts'] = max(1, min(10, $retry));
        }

        // Boolean settings
        $boolean_settings = [
            'enable_logging',
            'enable_ssl_verify',
            'send_analytics'
        ];

        foreach ($boolean_settings as $key) {
            $validated[$key] = !empty($settings[$key]);
        }

        // Validate popup settings
        if (isset($settings['popup']) && is_array($settings['popup'])) {
            $validated['popup'] = $this->validate_popup_settings($settings['popup']);
        }

        // Validate URL parameters
        if (isset($settings['url_params']) && is_array($settings['url_params'])) {
            $validated['url_params'] = $this->validate_url_params($settings['url_params']);
        }

        // Validate shortcode settings
        if (isset($settings['shortcodes']) && is_array($settings['shortcodes'])) {
            $validated['shortcodes'] = $this->validate_shortcode_settings($settings['shortcodes']);
        }

        // Custom code validation
        if (!empty($settings['custom_code'])) {
            $validated['custom_code'] = $settings['custom_code'];
        }

        return $validated;
    }

    /**
     * Validate popup settings
     */
    private function validate_popup_settings($popup_settings) {
        $validated = [];

        $validated['enabled'] = !empty($popup_settings['enabled']);
        
        $validated['template'] = sanitize_text_field($popup_settings['template'] ?? 'default');
        
        $validated['trigger'] = in_array($popup_settings['trigger'] ?? 'exit_intent', 
            ['exit_intent', 'time_delay', 'scroll_percentage', 'manual']) ? 
            $popup_settings['trigger'] : 'exit_intent';
        
        $validated['delay'] = absint($popup_settings['delay'] ?? 5);
        $validated['delay'] = max(1, min(60, $validated['delay']));
        
        $validated['scroll_percentage'] = absint($popup_settings['scroll_percentage'] ?? 50);
        $validated['scroll_percentage'] = max(0, min(100, $validated['scroll_percentage']));
        
        $validated['show_once'] = !empty($popup_settings['show_once']);
        $validated['cookie_duration'] = absint($popup_settings['cookie_duration'] ?? 30);
        
        $validated['popup_title'] = sanitize_text_field($popup_settings['popup_title'] ?? '');
        $validated['popup_description'] = wp_kses_post($popup_settings['popup_description'] ?? '');

        return $validated;
    }

    /**
     * Validate URL parameters
     */
    private function validate_url_params($url_params) {
        $validated = [];

        $validated['enabled'] = !empty($url_params['enabled']);
        
        $validated['param_name'] = sanitize_text_field($url_params['param_name'] ?? 'aff');
        
        if (empty($validated['param_name'])) {
            $validated['param_name'] = 'aff';
        }
        
        $validated['auto_apply'] = !empty($url_params['auto_apply']);
        $validated['override_existing'] = !empty($url_params['override_existing']);

        return $validated;
    }

    /**
     * Validate shortcode settings
     */
    private function validate_shortcode_settings($shortcode_settings) {
        $validated = [];

        $validated['enabled'] = !empty($shortcode_settings['enabled']);
        $validated['default_template'] = sanitize_text_field($shortcode_settings['default_template'] ?? 'default');
        $validated['enable_form'] = !empty($shortcode_settings['enable_form']);
        $validated['enable_display'] = !empty($shortcode_settings['enable_display']);

        return $validated;
    }

    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('aci_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'affiliate-client-integration')
            ]);
        }

        $result = $this->api_handler->test_connection();

        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Connection successful!', 'affiliate-client-integration'),
                'data' => $result['data']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? __('Connection failed.', 'affiliate-client-integration'),
                'error' => $result['error'] ?? null
            ]);
        }
    }

    /**
     * AJAX save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('aci_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'affiliate-client-integration')
            ]);
        }

        $settings = $_POST['settings'] ?? [];
        $validated_settings = $this->validate_settings($settings);
        
        update_option('aci_settings', $validated_settings);

        do_action('aci_settings_saved', $validated_settings);

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'affiliate-client-integration')
        ]);
    }

    /**
     * AJAX get analytics
     */
    public function ajax_get_analytics() {
        check_ajax_referer('aci_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'affiliate-client-integration')
            ]);
        }

        $period = sanitize_text_field($_POST['period'] ?? '7d');
        $analytics = $this->api_handler->get_analytics($period);

        if ($analytics) {
            wp_send_json_success([
                'analytics' => $analytics
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Unable to retrieve analytics data.', 'affiliate-client-integration')
            ]);
        }
    }

    /**
     * AJAX export data
     */
    public function ajax_export_data() {
        check_ajax_referer('aci_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'affiliate-client-integration')
            ]);
        }

        $period = sanitize_text_field($_POST['period'] ?? '30d');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        $export_data = $this->api_handler->export_analytics($period, $format);

        if ($export_data) {
            wp_send_json_success([
                'export_url' => $export_data['url'],
                'filename' => $export_data['filename']
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Unable to export data.', 'affiliate-client-integration')
            ]);
        }
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'aci_dashboard_widget',
            __('Affiliate Integration Stats', 'affiliate-client-integration'),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $stats = $this->api_handler->get_quick_stats();
        ?>
        <div class="aci-dashboard-widget">
            <div class="aci-widget-stats">
                <div class="aci-widget-stat">
                    <span class="label"><?php _e('Total Clicks:', 'affiliate-client-integration'); ?></span>
                    <span class="value"><?php echo number_format($stats['total_clicks'] ?? 0); ?></span>
                </div>
                <div class="aci-widget-stat">
                    <span class="label"><?php _e('Conversions:', 'affiliate-client-integration'); ?></span>
                    <span class="value"><?php echo number_format($stats['conversions'] ?? 0); ?></span>
                </div>
                <div class="aci-widget-stat">
                    <span class="label"><?php _e('Conversion Rate:', 'affiliate-client-integration'); ?></span>
                    <span class="value"><?php echo number_format($stats['conversion_rate'] ?? 0, 2); ?>%</span>
                </div>
            </div>
            <p class="aci-widget-link">
                <a href="<?php echo admin_url('admin.php?page=aci-analytics'); ?>">
                    <?php _e('View Full Analytics', 'affiliate-client-integration'); ?> &rarr;
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Section callbacks
     */
    public function connection_section_callback() {
        echo '<p>' . __('Configure the connection to your master affiliate domain.', 'affiliate-client-integration') . '</p>';
    }

    public function popup_section_callback() {
        echo '<p>' . __('Configure popup behaviour and appearance for affiliate code collection.', 'affiliate-client-integration') . '</p>';
    }

    public function url_section_callback() {
        echo '<p>' . __('Configure URL parameter handling for affiliate codes.', 'affiliate-client-integration') . '</p>';
    }

    public function shortcode_section_callback() {
        echo '<p>' . __('Configure shortcode functionality and default settings.', 'affiliate-client-integration') . '</p>';
    }

    public function advanced_section_callback() {
        echo '<p>' . __('Advanced settings for developers and troubleshooting.', 'affiliate-client-integration') . '</p>';
    }
}