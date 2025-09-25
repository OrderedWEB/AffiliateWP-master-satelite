<?php
/**
 * Addon Settings Admin Page
 *
 * Provides comprehensive administration interface for managing addon integrations,
 * configuration synchronisation, and monitoring addon performance across
 * all authorised domains in the affiliate cross-domain system.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @subpackage Admin
 * @version 1.0.0
 * @author Richard King, starneconsulting.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Addon_Settings_Admin {

    /**
     * Database manager instance
     *
     * @var AFFCD_Database_Manager
     */
    private $db_manager;

    /**
     * Addon detector instance
     *
     * @var AFFCD_Addon_Detector
     */
    private $addon_detector;

    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'affcd-addon-settings';

    /**
     * Settings option name
     *
     * @var string
     */
    private $option_name = 'affcd_addon_settings';

    /**
     * Current tab
     *
     * @var string
     */
    private $current_tab = 'overview';

    /**
     * Available tabs
     *
     * @var array
     */
    private $tabs = [];

    /**
     * Constructor
     *
     * @param AFFCD_Database_Manager $db_manager Database manager instance
     * @param AFFCD_Addon_Detector $addon_detector Addon detector instance
     */
    public function __construct($db_manager, $addon_detector) {
        $this->db_manager = $db_manager;
        $this->addon_detector = $addon_detector;
        $this->init_tabs();
        $this->init_hooks();
    }

    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_affcd_refresh_addons', [$this, 'ajax_refresh_addons']);
        add_action('wp_ajax_affcd_toggle_addon', [$this, 'ajax_toggle_addon']);
        add_action('wp_ajax_affcd_sync_addon_config', [$this, 'ajax_sync_addon_config']);
        add_action('wp_ajax_affcd_test_addon_integration', [$this, 'ajax_test_addon_integration']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    /**
     * Initialize available tabs
     */
    private function init_tabs() {
        $this->tabs = [
            'overview' => [
                'title' => __('Overview', 'affiliatewp-cross-domain-full'),
                'description' => __('Overview of detected addons and their status', 'affiliatewp-cross-domain-full'),
                'capability' => 'manage_affcd'
            ],
            'configuration' => [
                'title' => __('Configuration', 'affiliatewp-cross-domain-full'),
                'description' => __('Configure addon integration settings', 'affiliatewp-cross-domain-full'),
                'capability' => 'manage_affcd'
            ],
            'synchronisation' => [
                'title' => __('Synchronisation', 'affiliatewp-cross-domain-full'),
                'description' => __('Manage addon configuration synchronisation across domains', 'affiliatewp-cross-domain-full'),
                'capability' => 'manage_affcd'
            ],
            'performance' => [
                'title' => __('Performance', 'affiliatewp-cross-domain-full'),
                'description' => __('Monitor addon performance and analytics', 'affiliatewp-cross-domain-full'),
                'capability' => 'view_affcd_reports'
            ],
            'troubleshooting' => [
                'title' => __('Troubleshooting', 'affiliatewp-cross-domain-full'),
                'description' => __('Diagnostic tools and troubleshooting options', 'affiliatewp-cross-domain-full'),
                'capability' => 'manage_affcd'
            ]
        ];

        $this->current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        
        if (!array_key_exists($this->current_tab, $this->tabs)) {
            $this->current_tab = 'overview';
        }
    }

    /**
     * Add admin page to WordPress menu
     */
    public function add_admin_page() {
        add_submenu_page(
            'affcd-dashboard',
            __('Addon Settings', 'affiliatewp-cross-domain-full'),
            __('Addon Settings', 'affiliatewp-cross-domain-full'),
            'manage_affcd',
            $this->page_slug,
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'affcd_addon_settings_group',
            $this->option_name,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings()
            ]
        );

        // Register settings sections for each tab
        foreach ($this->tabs as $tab_key => $tab_data) {
            if (current_user_can($tab_data['capability'])) {
                $this->register_tab_settings($tab_key);
            }
        }
    }

    /**
     * Register settings for specific tab
     *
     * @param string $tab_key Tab key
     */
    private function register_tab_settings($tab_key) {
        switch ($tab_key) {
            case 'configuration':
                $this->register_configuration_settings();
                break;
            case 'synchronisation':
                $this->register_synchronisation_settings();
                break;
            case 'performance':
                $this->register_performance_settings();
                break;
            case 'troubleshooting':
                $this->register_troubleshooting_settings();
                break;
        }
    }

    /**
     * Register configuration settings
     */
    private function register_configuration_settings() {
        add_settings_section(
            'affcd_addon_config_section',
            __('Addon Configuration', 'affiliatewp-cross-domain-full'),
            [$this, 'render_configuration_section_description'],
            'affcd_addon_config'
        );

        $detected_addons = $this->addon_detector->get_detected_addons();
        
        foreach ($detected_addons as $addon_slug => $addon_data) {
            add_settings_field(
                'addon_' . $addon_slug,
                $addon_data['name'] ?? ucfirst(str_replace('_', ' ', $addon_slug)),
                [$this, 'render_addon_configuration_field'],
                'affcd_addon_config',
                'affcd_addon_config_section',
                ['addon_slug' => $addon_slug, 'addon_data' => $addon_data]
            );
        }
    }

    /**
     * Register synchronisation settings
     */
    private function register_synchronisation_settings() {
        add_settings_section(
            'affcd_addon_sync_section',
            __('Synchronisation Settings', 'affiliatewp-cross-domain-full'),
            [$this, 'render_synchronisation_section_description'],
            'affcd_addon_sync'
        );

        add_settings_field(
            'auto_sync_enabled',
            __('Automatic Synchronisation', 'affiliatewp-cross-domain-full'),
            [$this, 'render_auto_sync_field'],
            'affcd_addon_sync',
            'affcd_addon_sync_section'
        );

        add_settings_field(
            'sync_interval',
            __('Synchronisation Interval', 'affiliatewp-cross-domain-full'),
            [$this, 'render_sync_interval_field'],
            'affcd_addon_sync',
            'affcd_addon_sync_section'
        );

        add_settings_field(
            'conflict_resolution',
            __('Conflict Resolution Strategy', 'affiliatewp-cross-domain-full'),
            [$this, 'render_conflict_resolution_field'],
            'affcd_addon_sync',
            'affcd_addon_sync_section'
        );
    }

    /**
     * Register performance settings
     */
    private function register_performance_settings() {
        add_settings_section(
            'affcd_addon_performance_section',
            __('Performance Monitoring', 'affiliatewp-cross-domain-full'),
            [$this, 'render_performance_section_description'],
            'affcd_addon_performance'
        );

        add_settings_field(
            'performance_monitoring_enabled',
            __('Enable Performance Monitoring', 'affiliatewp-cross-domain-full'),
            [$this, 'render_performance_monitoring_field'],
            'affcd_addon_performance',
            'affcd_addon_performance_section'
        );

        add_settings_field(
            'performance_alerts',
            __('Performance Alerts', 'affiliatewp-cross-domain-full'),
            [$this, 'render_performance_alerts_field'],
            'affcd_addon_performance',
            'affcd_addon_performance_section'
        );
    }

    /**
     * Register troubleshooting settings
     */
    private function register_troubleshooting_settings() {
        add_settings_section(
            'affcd_addon_debug_section',
            __('Debug and Troubleshooting', 'affiliatewp-cross-domain-full'),
            [$this, 'render_debug_section_description'],
            'affcd_addon_debug'
        );

        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'affiliatewp-cross-domain-full'),
            [$this, 'render_debug_mode_field'],
            'affcd_addon_debug',
            'affcd_addon_debug_section'
        );

        add_settings_field(
            'logging_level',
            __('Logging Level', 'affiliatewp-cross-domain-full'),
            [$this, 'render_logging_level_field'],
            'affcd_addon_debug',
            'affcd_addon_debug_section'
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Page hook
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }

        wp_enqueue_script(
            'affcd-addon-settings',
            AFFCD_PLUGIN_URL . 'admin/js/addon-settings.js',
            ['jquery', 'wp-util'],
            AFFCD_VERSION,
            true
        );

        wp_enqueue_style(
            'affcd-addon-settings',
            AFFCD_PLUGIN_URL . 'admin/css/addon-settings.css',
            [],
            AFFCD_VERSION
        );

        wp_localize_script('affcd-addon-settings', 'affcdAddonSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_addon_settings'),
            'strings' => [
                'refreshing' => __('Refreshing addon detection...', 'affiliatewp-cross-domain-full'),
                'testing' => __('Testing integration...', 'affiliatewp-cross-domain-full'),
                'syncing' => __('Synchronising configuration...', 'affiliatewp-cross-domain-full'),
                'success' => __('Operation completed successfully', 'affiliatewp-cross-domain-full'),
                'error' => __('An error occurred', 'affiliatewp-cross-domain-full'),
                'confirm' => __('Are you sure?', 'affiliatewp-cross-domain-full')
            ]
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_affcd')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'affiliatewp-cross-domain-full'));
        }

        ?>
        <div class="wrap affcd-addon-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->render_page_navigation(); ?>
            
            <div class="affcd-addon-content">
                <?php
                switch ($this->current_tab) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'configuration':
                        $this->render_configuration_tab();
                        break;
                    case 'synchronisation':
                        $this->render_synchronisation_tab();
                        break;
                    case 'performance':
                        $this->render_performance_tab();
                        break;
                    case 'troubleshooting':
                        $this->render_troubleshooting_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render page navigation tabs
     */
    private function render_page_navigation() {
        ?>
        <nav class="nav-tab-wrapper wp-clearfix">
            <?php foreach ($this->tabs as $tab_key => $tab_data): ?>
                <?php if (current_user_can($tab_data['capability'])): ?>
                    <a href="<?php echo esc_url(add_query_arg(['tab' => $tab_key], admin_url('admin.php?page=' . $this->page_slug))); ?>" 
                       class="nav-tab <?php echo $this->current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_data['title']); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Render overview tab
     */
    private function render_overview_tab() {
        $detected_addons = $this->addon_detector->get_detected_addons();
        $addon_stats = $this->addon_detector->get_addon_statistics();
        ?>
        <div class="affcd-overview-tab">
            <div class="affcd-stats-grid">
                <div class="affcd-stat-card">
                    <h3><?php esc_html_e('Total Supported Addons', 'affiliatewp-cross-domain-full'); ?></h3>
                    <div class="stat-number"><?php echo intval($addon_stats['total_supported']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php esc_html_e('Detected Addons', 'affiliatewp-cross-domain-full'); ?></h3>
                    <div class="stat-number"><?php echo intval($addon_stats['total_detected']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php esc_html_e('Compatible Addons', 'affiliatewp-cross-domain-full'); ?></h3>
                    <div class="stat-number"><?php echo intval($addon_stats['total_compatible']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php esc_html_e('Active Addons', 'affiliatewp-cross-domain-full'); ?></h3>
                    <div class="stat-number"><?php echo intval($addon_stats['total_active']); ?></div>
                </div>
            </div>

            <div class="affcd-addon-actions">
                <button type="button" class="button button-secondary" id="refresh-addons">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Refresh Detection', 'affiliatewp-cross-domain-full'); ?>
                </button>
                <button type="button" class="button button-primary" id="sync-all-configs">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Sync All Configurations', 'affiliatewp-cross-domain-full'); ?>
                </button>
            </div>

            <div class="affcd-addon-list">
                <h2><?php esc_html_e('Detected Addons', 'affiliatewp-cross-domain-full'); ?></h2>
                
                <?php if (empty($detected_addons)): ?>
                    <div class="notice notice-info">
                        <p><?php esc_html_e('No compatible addons detected. Ensure supported plugins are installed and activated.', 'affiliatewp-cross-domain-full'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Addon', 'affiliatewp-cross-domain-full'); ?></th>
                                <th scope="col"><?php esc_html_e('Version', 'affiliatewp-cross-domain-full'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'affiliatewp-cross-domain-full'); ?></th>
                                <th scope="col"><?php esc_html_e('Compatibility', 'affiliatewp-cross-domain-full'); ?></th>
                                <th scope="col"><?php esc_html_e('Actions', 'affiliatewp-cross-domain-full'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detected_addons as $addon_slug => $addon_data): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($addon_data['name'] ?? ucfirst(str_replace('_', ' ', $addon_slug))); ?></strong>
                                        <?php if (!empty($addon_data['capabilities'])): ?>
                                            <br><small><?php echo esc_html(implode(', ', $addon_data['capabilities'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($addon_data['version'] ?? __('Unknown', 'affiliatewp-cross-domain-full')); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $addon_data['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $addon_data['active'] ? esc_html__('Active', 'affiliatewp-cross-domain-full') : esc_html__('Inactive', 'affiliatewp-cross-domain-full'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="compatibility-badge compatibility-<?php echo $addon_data['compatible'] ? 'yes' : 'no'; ?>">
                                            <?php echo $addon_data['compatible'] ? esc_html__('Compatible', 'affiliatewp-cross-domain-full') : esc_html__('Issues', 'affiliatewp-cross-domain-full'); ?>
                                        </span>
                                        <?php if (!empty($addon_data['issues'])): ?>
                                            <div class="compatibility-issues">
                                                <?php foreach ($addon_data['issues'] as $issue): ?>
                                                    <small class="issue"><?php echo esc_html($issue); ?></small>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small test-integration" 
                                                data-addon="<?php echo esc_attr($addon_slug); ?>">
                                            <?php esc_html_e('Test', 'affiliatewp-cross-domain-full'); ?>
                                        </button>
                                        <button type="button" class="button button-small button-primary sync-config" 
                                                data-addon="<?php echo esc_attr($addon_slug); ?>">
                                            <?php esc_html_e('Sync', 'affiliatewp-cross-domain-full'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render configuration tab
     */
    private function render_configuration_tab() {
        ?>
        <div class="affcd-configuration-tab">
            <form method="post" action="options.php">
                <?php
                settings_fields('affcd_addon_settings_group');
                do_settings_sections('affcd_addon_config');
                submit_button(__('Save Configuration', 'affiliatewp-cross-domain-full'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render synchronisation tab
     */
    private function render_synchronisation_tab() {
        $sync_status = $this->get_synchronisation_status();
        ?>
        <div class="affcd-synchronisation-tab">
            <div class="sync-status-overview">
                <h2><?php esc_html_e('Synchronisation Status', 'affiliatewp-cross-domain-full'); ?></h2>
                <div class="sync-stats-grid">
                    <div class="sync-stat-card">
                        <h3><?php esc_html_e('Connected Domains', 'affiliatewp-cross-domain-full'); ?></h3>
                        <div class="stat-number"><?php echo intval($sync_status['connected_domains']); ?></div>
                    </div>
                    <div class="sync-stat-card">
                        <h3><?php esc_html_e('Last Sync', 'affiliatewp-cross-domain-full'); ?></h3>
                        <div class="stat-text"><?php echo esc_html($sync_status['last_sync'] ?? __('Never', 'affiliatewp-cross-domain-full')); ?></div>
                    </div>
                    <div class="sync-stat-card">
                        <h3><?php esc_html_e('Sync Success Rate', 'affiliatewp-cross-domain-full'); ?></h3>
                        <div class="stat-number"><?php echo floatval($sync_status['success_rate'] ?? 0); ?>%</div>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('affcd_addon_settings_group');
                do_settings_sections('affcd_addon_sync');
                submit_button(__('Save Synchronisation Settings', 'affiliatewp-cross-domain-full'));
                ?>
            </form>

            <div class="sync-domain-list">
                <h2><?php esc_html_e('Domain Synchronisation Status', 'affiliatewp-cross-domain-full'); ?></h2>
                <?php $this->render_domain_sync_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render performance tab
     */
    private function render_performance_tab() {
        $performance_data = $this->get_performance_data();
        ?>
        <div class="affcd-performance-tab">
            <div class="performance-overview">
                <h2><?php esc_html_e('Performance Overview', 'affiliatewp-cross-domain-full'); ?></h2>
                <div class="performance-charts">
                    <div class="chart-container">
                        <canvas id="addon-performance-chart"></canvas>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('affcd_addon_settings_group');
                do_settings_sections('affcd_addon_performance');
                submit_button(__('Save Performance Settings', 'affiliatewp-cross-domain-full'));
                ?>
            </form>

            <div class="performance-metrics">
                <h2><?php esc_html_e('Performance Metrics', 'affiliatewp-cross-domain-full'); ?></h2>
                <?php $this->render_performance_metrics_table($performance_data); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render troubleshooting tab
     */
    private function render_troubleshooting_tab() {
        ?>
        <div class="affcd-troubleshooting-tab">
            <div class="troubleshooting-tools">
                <h2><?php esc_html_e('Diagnostic Tools', 'affiliatewp-cross-domain-full'); ?></h2>
                <div class="diagnostic-actions">
                    <button type="button" class="button button-secondary" id="run-diagnostics">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php esc_html_e('Run Full Diagnostics', 'affiliatewp-cross-domain-full'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="clear-cache">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear Addon Cache', 'affiliatewp-cross-domain-full'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="export-debug-info">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export Debug Information', 'affiliatewp-cross-domain-full'); ?>
                    </button>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('affcd_addon_settings_group');
                do_settings_sections('affcd_addon_debug');
                submit_button(__('Save Debug Settings', 'affiliatewp-cross-domain-full'));
                ?>
            </form>

            <div class="system-info">
                <h2><?php esc_html_e('System Information', 'affiliatewp-cross-domain-full'); ?></h2>
                <?php $this->render_system_info_table(); ?>
            </div>

            <div class="recent-logs">
                <h2><?php esc_html_e('Recent Log Entries', 'affiliatewp-cross-domain-full'); ?></h2>
                <?php $this->render_recent_logs_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render addon configuration field
     *
     * @param array $args Field arguments
     */
    public function render_addon_configuration_field($args) {
        $addon_slug = $args['addon_slug'];
        $addon_data = $args['addon_data'];
        $settings = get_option($this->option_name, $this->get_default_settings());
        $addon_settings = $settings['addons'][$addon_slug] ?? [];

        ?>
        <div class="addon-config-field" data-addon="<?php echo esc_attr($addon_slug); ?>">
            <div class="addon-header">
                <label class="addon-toggle">
                    <input type="checkbox" 
                           name="<?php echo esc_attr($this->option_name); ?>[addons][<?php echo esc_attr($addon_slug); ?>][enabled]" 
                           value="1" 
                           <?php checked(!empty($addon_settings['enabled'])); ?>
                           <?php disabled(!$addon_data['compatible']); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span class="addon-name"><?php echo esc_html($addon_data['name'] ?? ucfirst(str_replace('_', ' ', $addon_slug))); ?></span>
                <span class="addon-version"><?php echo esc_html($addon_data['version'] ?? ''); ?></span>
            </div>

            <?php if ($addon_data['compatible']): ?>
                <div class="addon-details" style="<?php echo empty($addon_settings['enabled']) ? 'display: none;' : ''; ?>">
                    <h4><?php esc_html_e('Configuration Options', 'affiliatewp-cross-domain-full'); ?></h4>
                    
                    <?php foreach ($addon_data['configuration'] as $config_key => $config_value): ?>
                        <div class="config-option">
                            <label>
                                <?php if (is_bool($config_value)): ?>
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr($this->option_name); ?>[addons][<?php echo esc_attr($addon_slug); ?>][config][<?php echo esc_attr($config_key); ?>]" 
                                           value="1" 
                                           <?php checked(!empty($addon_settings['config'][$config_key])); ?>>
                                <?php else: ?>
                                    <input type="text" 
                                           name="<?php echo esc_attr($this->option_name); ?>[addons][<?php echo esc_attr($addon_slug); ?>][config][<?php echo esc_attr($config_key); ?>]" 
                                           value="<?php echo esc_attr($addon_settings['config'][$config_key] ?? $config_value); ?>" 
                                           class="regular-text">
                                <?php endif; ?>
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $config_key))); ?>
                            </label>
                            <p class="description"><?php echo esc_html($this->get_config_description($addon_slug, $config_key)); ?></p>
                        </div>
                    <?php endforeach; ?>

                    <div class="addon-actions">
                        <button type="button" class="button button-small test-integration" 
                                data-addon="<?php echo esc_attr($addon_slug); ?>">
                            <?php esc_html_e('Test Integration', 'affiliatewp-cross-domain-full'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="addon-issues">
                <h4><?php esc_html_e('Compatibility Issues', 'affiliatewp-cross-domain-full'); ?></h4>
                    <ul class="issue-list">
                        <?php foreach ($addon_data['issues'] as $issue): ?>
                            <li class="issue-item"><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render auto sync field
     */
    public function render_auto_sync_field() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        ?>
        <label class="sync-toggle">
            <input type="checkbox" 
                   name="<?php echo esc_attr($this->option_name); ?>[sync][auto_enabled]" 
                   value="1" 
                   <?php checked(!empty($settings['sync']['auto_enabled'])); ?>>
            <span class="toggle-slider"></span>
            <span class="toggle-label"><?php esc_html_e('Enable automatic synchronisation of addon configurations across domains', 'affiliatewp-cross-domain-full'); ?></span>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, addon configuration changes will be automatically synchronised to all connected domains.', 'affiliatewp-cross-domain-full'); ?>
        </p>
        <?php
    }

    /**
     * Render sync interval field
     */
    public function render_sync_interval_field() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $current_interval = $settings['sync']['interval'] ?? 300;
        
        $intervals = [
            60 => __('1 minute', 'affiliatewp-cross-domain-full'),
            300 => __('5 minutes', 'affiliatewp-cross-domain-full'),
            900 => __('15 minutes', 'affiliatewp-cross-domain-full'),
            1800 => __('30 minutes', 'affiliatewp-cross-domain-full'),
            3600 => __('1 hour', 'affiliatewp-cross-domain-full')
        ];
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[sync][interval]" class="regular-text">
            <?php foreach ($intervals as $seconds => $label): ?>
                <option value="<?php echo esc_attr($seconds); ?>" <?php selected($current_interval, $seconds); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('How frequently to check for and synchronise addon configuration changes.', 'affiliatewp-cross-domain-full'); ?>
        </p>
        <?php
    }

    /**
     * Render conflict resolution field
     */
    public function render_conflict_resolution_field() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $current_strategy = $settings['sync']['conflict_resolution'] ?? 'latest_wins';
        
        $strategies = [
            'latest_wins' => __('Latest Wins', 'affiliatewp-cross-domain-full'),
            'manual_review' => __('Manual Review Required', 'affiliatewp-cross-domain-full'),
            'preserve_local' => __('Preserve Local Changes', 'affiliatewp-cross-domain-full')
        ];
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[sync][conflict_resolution]" class="regular-text">
            <?php foreach ($strategies as $strategy => $label): ?>
                <option value="<?php echo esc_attr($strategy); ?>" <?php selected($current_strategy, $strategy); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('How to handle conflicts when the same addon configuration is modified on multiple domains.', 'affiliatewp-cross-domain-full'); ?>
        </p>
        <?php
    }

    /**
     * Render performance monitoring field
     */
    public function render_performance_monitoring_field() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        ?>
        <label class="performance-toggle">
            <input type="checkbox" 
                   name="<?php echo esc_attr($this->option_name); ?>[performance][monitoring_enabled]" 
                   value="1" 
                   <?php checked(!empty($settings['performance']['monitoring_enabled'])); ?>>
            <span class="toggle-slider"></span>
            <span class="toggle-label"><?php esc_html_e('Enable performance monitoring for addon integrations', 'affiliatewp-cross-domain-full'); ?></span>
        </label>
        <p class="description">
            <?php esc_html_e('Track performance metrics such as response times, error rates, and resource usage for addon integrations.', 'affiliatewp-cross-domain-full'); ?>
        </p>
        <?php
    }

    /**
     * Render performance alerts field
     */
    public function render_performance_alerts_field() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        ?>
        <div class="performance-alerts-config">
            <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr($this->option_name); ?>[performance][alerts_enabled]" 
                       value="1" 
                       <?php checked(!empty($settings['performance']['alerts_enabled'])); ?>>
                <?php esc_html_e('Enable performance alerts', 'affiliatewp-cross-domain-full'); ?>
            </label>
            <br><br>
            
            <div class="alert-thresholds">
                <h4><?php esc_html_e('Alert Thresholds', 'affiliatewp-cross-domain-full'); ?></h4>
                
                <label>
                    <?php esc_html_e('Response Time Threshold (ms)', 'affiliatewp-cross-domain-full'); ?>
                    <input type="number" 
                           name="<?php echo esc_attr($this->option_name); ?>[performance][response_time_threshold]" 
                           value="<?php echo esc_attr($settings['performance']['response_time_threshold'] ?? 5000); ?>" 
                           min="100" 
                           max="60000" 
                           class="small-text">
                </label>
                
                <label>
                    <?php esc_html_e('Error Rate Threshold (%)', 'affiliatewp-cross-domain-full'); ?>
                    <input type="number" 
                           name="<?php echo esc_attr($this->option_name); ?>[performance][error_rate_threshold]" 
                           value="<?php echo esc_attr($settings['performance']['error_rate_threshold'] ?? 5); ?>" 
                           min="1" 
                           max="100" 
                           class="small-text">
                </label>
            </div>
        </div>
        <?php
    }

    /**
     * Render debug mode field
     */
    public function render_debug_mode_field() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        ?>
        <label class="debug-toggle">
            <input type="checkbox" 
                   name="<?php echo esc_attr($this->option_name); ?>[debug][enabled]" 
                   value="1" 
                   <?php checked(!empty($settings['debug']['enabled'])); ?>>
            <span class="toggle-slider"></span>
            <span class="toggle-label"><?php esc_html_e('Enable debug mode for addon integrations', 'affiliatewp-cross-domain-full'); ?></span>
        </label>
        <p class="description">
            <?php esc_html_e('Debug mode provides detailed logging and diagnostic information. Only enable when troubleshooting issues.', 'affiliatewp-cross-domain-full'); ?>
        </p>
        <?php
    }

    /**
     * Render logging level field
     */
    public function render_logging_level_field() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $current_level = $settings['debug']['logging_level'] ?? 'error';
        
        $levels = [
            'error' => __('Error Only', 'affiliatewp-cross-domain-full'),
            'warning' => __('Warning and Above', 'affiliatewp-cross-domain-full'),
            'info' => __('Info and Above', 'affiliatewp-cross-domain-full'),
            'debug' => __('All Messages', 'affiliatewp-cross-domain-full')
        ];
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[debug][logging_level]" class="regular-text">
            <?php foreach ($levels as $level => $label): ?>
                <option value="<?php echo esc_attr($level); ?>" <?php selected($current_level, $level); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Set the minimum logging level for addon-related messages.', 'affiliatewp-cross-domain-full'); ?>
        </p>
        <?php
    }

    /**
     * Render domain sync table
     */
    private function render_domain_sync_table() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('authorized_domains');
        
        $domains = $wpdb->get_results(
            "SELECT domain_url, domain_name, status, verification_status, last_activity_at 
             FROM {$table_name} 
             WHERE status = 'active' 
             ORDER BY last_activity_at DESC"
        );

        if (empty($domains)) {
            echo '<p>' . esc_html__('No active domains found.', 'affiliatewp-cross-domain-full') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Domain', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Last Sync', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Actions', 'affiliatewp-cross-domain-full'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($domains as $domain): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($domain->domain_name ?: $domain->domain_url); ?></strong>
                            <br><small><?php echo esc_html($domain->domain_url); ?></small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($domain->verification_status); ?>">
                                <?php echo esc_html(ucfirst($domain->verification_status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            if ($domain->last_activity_at) {
                                echo esc_html(human_time_diff(strtotime($domain->last_activity_at), current_time('timestamp')) . ' ago');
                            } else {
                                esc_html_e('Never', 'affiliatewp-cross-domain-full');
                            }
                            ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small sync-domain" 
                                    data-domain="<?php echo esc_attr($domain->domain_url); ?>">
                                <?php esc_html_e('Sync Now', 'affiliatewp-cross-domain-full'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render performance metrics table
     *
     * @param array $performance_data Performance data
     */
    private function render_performance_metrics_table($performance_data) {
        if (empty($performance_data)) {
            echo '<p>' . esc_html__('No performance data available.', 'affiliatewp-cross-domain-full') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Addon', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Response Time', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Error Rate', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Requests/Hour', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'affiliatewp-cross-domain-full'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($performance_data as $addon_slug => $metrics): ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $addon_slug))); ?></td>
                        <td><?php echo esc_html($metrics['avg_response_time'] ?? 'N/A'); ?>ms</td>
                        <td><?php echo esc_html($metrics['error_rate'] ?? 'N/A'); ?>%</td>
                        <td><?php echo esc_html($metrics['requests_per_hour'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="performance-status performance-<?php echo esc_attr($metrics['status'] ?? 'unknown'); ?>">
                                <?php echo esc_html(ucfirst($metrics['status'] ?? 'Unknown')); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render system info table
     */
    private function render_system_info_table() {
        $system_info = $this->get_system_info();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Setting', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Value', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'affiliatewp-cross-domain-full'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($system_info as $setting => $data): ?>
                    <tr>
                        <td><?php echo esc_html($data['label']); ?></td>
                        <td><code><?php echo esc_html($data['value']); ?></code></td>
                        <td>
                            <span class="system-status system-<?php echo esc_attr($data['status']); ?>">
                                <?php echo esc_html(ucfirst($data['status'])); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render recent logs table
     */
    private function render_recent_logs_table() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name('analytics');
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, domain, event_data, created_at 
             FROM {$table_name} 
             WHERE event_type LIKE 'addon_%' 
             ORDER BY created_at DESC 
             LIMIT %d",
            20
        ));

        if (empty($logs)) {
            echo '<p>' . esc_html__('No recent addon logs found.', 'affiliatewp-cross-domain-full') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Time', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Event', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Domain', 'affiliatewp-cross-domain-full'); ?></th>
                    <th scope="col"><?php esc_html_e('Details', 'affiliatewp-cross-domain-full'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ago'); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $log->event_type))); ?></td>
                        <td><?php echo esc_html($log->domain ?: 'N/A'); ?></td>
                        <td>
                            <?php 
                            $event_data = json_decode($log->event_data, true);
                            if ($event_data) {
                                echo '<details><summary>' . esc_html__('View Details', 'affiliatewp-cross-domain-full') . '</summary>';
                                echo '<pre>' . esc_html(json_encode($event_data, JSON_PRETTY_PRINT)) . '</pre>';
                                echo '</details>';
                            } else {
                                esc_html_e('No details available', 'affiliatewp-cross-domain-full');
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render section descriptions
     */
    public function render_configuration_section_description() {
        echo '<p>' . esc_html__('Configure individual addon integrations and their tracking behaviour.', 'affiliatewp-cross-domain-full') . '</p>';
    }

    public function render_synchronisation_section_description() {
        echo '<p>' . esc_html__('Manage how addon configurations are synchronised across your domains.', 'affiliatewp-cross-domain-full') . '</p>';
    }

    public function render_performance_section_description() {
        echo '<p>' . esc_html__('Monitor and configure performance tracking for addon integrations.', 'affiliatewp-cross-domain-full') . '</p>';
    }

    public function render_debug_section_description() {
        echo '<p>' . esc_html__('Configure debugging and logging options for troubleshooting addon issues.', 'affiliatewp-cross-domain-full') . '</p>';
    }

    /**
     * Get default settings
     *
     * @return array Default settings
     */
    private function get_default_settings() {
        return [
            'addons' => [],
            'sync' => [
                'auto_enabled' => true,
                'interval' => 300,
                'conflict_resolution' => 'latest_wins'
            ],
            'performance' => [
                'monitoring_enabled' => true,
                'alerts_enabled' => false,
                'response_time_threshold' => 5000,
                'error_rate_threshold' => 5
            ],
            'debug' => [
                'enabled' => false,
                'logging_level' => 'error'
            ]
        ];
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings
     * @return array Sanitised settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Sanitise addon settings
        if (isset($input['addons']) && is_array($input['addons'])) {
            foreach ($input['addons'] as $addon_slug => $addon_settings) {
                $sanitized_slug = sanitize_key($addon_slug);
                $sanitized['addons'][$sanitized_slug] = [
                    'enabled' => !empty($addon_settings['enabled']),
                    'config' => []
                ];

                if (isset($addon_settings['config']) && is_array($addon_settings['config'])) {
                    foreach ($addon_settings['config'] as $config_key => $config_value) {
                        $sanitized_key = sanitize_key($config_key);
                        if (is_bool($config_value) || $config_value === '1') {
                            $sanitized['addons'][$sanitized_slug]['config'][$sanitized_key] = !empty($config_value);
                        } else {
                            $sanitized['addons'][$sanitized_slug]['config'][$sanitized_key] = sanitize_text_field($config_value);
                        }
                    }
                }
            }
        }

        // Sanitise sync settings
        if (isset($input['sync'])) {
            $sanitized['sync'] = [
                'auto_enabled' => !empty($input['sync']['auto_enabled']),
                'interval' => absint($input['sync']['interval'] ?? 300),
                'conflict_resolution' => sanitize_key($input['sync']['conflict_resolution'] ?? 'latest_wins')
            ];
        }

        // Sanitise performance settings
        if (isset($input['performance'])) {
            $sanitized['performance'] = [
                'monitoring_enabled' => !empty($input['performance']['monitoring_enabled']),
                'alerts_enabled' => !empty($input['performance']['alerts_enabled']),
                'response_time_threshold' => absint($input['performance']['response_time_threshold'] ?? 5000),
                'error_rate_threshold' => absint($input['performance']['error_rate_threshold'] ?? 5)
            ];
        }

        // Sanitise debug settings
        if (isset($input['debug'])) {
            $sanitized['debug'] = [
                'enabled' => !empty($input['debug']['enabled']),
                'logging_level' => sanitize_key($input['debug']['logging_level'] ?? 'error')
            ];
        }

        return array_merge($this->get_default_settings(), $sanitized);
    }

    /**
     * Get configuration description
     *
     * @param string $addon_slug Addon slug
     * @param string $config_key Configuration key
     * @return string Description
     */
    private function get_config_description($addon_slug, $config_key) {
        $descriptions = [
            'track_add_to_cart' => __('Track when items are added to cart', 'affiliatewp-cross-domain-full'),
            'track_purchases' => __('Track completed purchases and conversions', 'affiliatewp-cross-domain-full'),
            'track_form_submissions' => __('Track form submissions as leads', 'affiliatewp-cross-domain-full'),
            'include_product_data' => __('Include detailed product information in tracking data', 'affiliatewp-cross-domain-full'),
            'track_guest_purchases' => __('Track purchases made by guest users', 'affiliatewp-cross-domain-full')
        ];

        return $descriptions[$config_key] ?? __('Configure this addon setting', 'affiliatewp-cross-domain-full');
    }

    /**
     * Get synchronisation status
     *
     * @return array Synchronisation status data
     */
    private function get_synchronisation_status() {
        global $wpdb;
        $domains_table = $this->db_manager->get_table_name('authorized_domains');
        $analytics_table = $this->db_manager->get_table_name('analytics');

        $connected_domains = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$domains_table} WHERE status = 'active' AND verification_status = 'verified'"
        );

        $last_sync = $wpdb->get_var(
            "SELECT MAX(created_at) FROM {$analytics_table} WHERE event_type = 'sync_completed'"
        );

        $sync_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_syncs,
                SUM(CASE WHEN event_type = 'sync_completed' THEN 1 ELSE 0 END) as successful_syncs
             FROM {$analytics_table} 
             WHERE event_type IN ('sync_completed', 'sync_error') 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        $success_rate = 0;
        if ($sync_stats && $sync_stats->total_syncs > 0) {
            $success_rate = ($sync_stats->successful_syncs / $sync_stats->total_syncs) * 100;
        }

        return [
            'connected_domains' => $connected_domains,
            'last_sync' => $last_sync ? human_time_diff(strtotime($last_sync), current_time('timestamp')) . ' ago' : null,
            'success_rate' => round($success_rate, 1)
        ];
    }

    /**
     * Get performance data
     *
     * @return array Performance data
     */
    private function get_performance_data() {
        $detected_addons = $this->addon_detector->get_detected_addons();
        $performance_data = [];

        foreach ($detected_addons as $addon_slug => $addon_info) {
            if ($addon_info['compatible']) {
                $performance_data[$addon_slug] = [
                    'avg_response_time' => rand(50, 500), // Mock data - replace with real metrics
                    'error_rate' => rand(0, 10),
                    'requests_per_hour' => rand(100, 1000),
                    'status' => 'good'
                ];
            }
        }

        return $performance_data;
    }

    /**
     * Get system information
     *
     * @return array System information
     */
    private function get_system_info() {
        return [
            'php_version' => [
                'label' => __('PHP Version', 'affiliatewp-cross-domain-full'),
                'value' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4', '>=') ? 'good' : 'warning'
            ],
            'wp_version' => [
                'label' => __('WordPress Version', 'affiliatewp-cross-domain-full'),
                'value' => get_bloginfo('version'),
                'status' => version_compare(get_bloginfo('version'), '5.0', '>=') ? 'good' : 'warning'
            ],
            'memory_limit' => [
                'label' => __('Memory Limit', 'affiliatewp-cross-domain-full'),
                'value' => ini_get('memory_limit'),
                'status' => 'good'
            ],
            'max_execution_time' => [
                'label' => __('Max Execution Time', 'affiliatewp-cross-domain-full'),
                'value' => ini_get('max_execution_time') . 's',
                'status' => 'good'
            ]
        ];
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, $this->page_slug) === false) {
            return;
        }

        // Check for addon compatibility issues
        $detected_addons = $this->addon_detector->get_detected_addons();
        $incompatible_addons = array_filter($detected_addons, function($addon) {
            return !$addon['compatible'];
        });

        if (!empty($incompatible_addons)) {
            echo '<div class="notice notice-warning">';
            echo '<p>' . sprintf(
                esc_html__('There are %d addon(s) with compatibility issues. Please review the configuration tab for details.', 'affiliatewp-cross-domain-full'),
                count($incompatible_addons)
            ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * AJAX: Refresh addon detection
     */
    public function ajax_refresh_addons() {
        check_ajax_referer('affcd_addon_settings', 'nonce');

        if (!current_user_can('manage_affcd')) {
            wp_die(__('Permission denied', 'affiliatewp-cross-domain-full'));
        }

        $this->addon_detector->refresh_addon_detection();

        wp_send_json_success([
            'message' => __('Addon detection refreshed successfully', 'affiliatewp-cross-domain-full'),
            'addons' => $this->addon_detector->get_detected_addons()
        ]);
    }

    /**
     * AJAX: Toggle addon
     */
    public function ajax_toggle_addon() {
        check_ajax_referer('affcd_addon_settings', 'nonce');

        if (!current_user_can('manage_affcd')) {
            wp_die(__('Permission denied', 'affiliatewp-cross-domain-full'));
        }

        $addon_slug = sanitize_key($_POST['addon'] ?? '');
        $enabled = !empty($_POST['enabled']);

        $settings = get_option($this->option_name, $this->get_default_settings());
        $settings['addons'][$addon_slug]['enabled'] = $enabled;
        
        update_option($this->option_name, $settings);

        wp_send_json_success([
            'message' => $enabled ? 
                __('Addon enabled successfully', 'affiliatewp-cross-domain-full') : 
                __('Addon disabled successfully', 'affiliatewp-cross-domain-full')
        ]);
    }

    /**
     * AJAX: Sync addon configuration
     */
    public function ajax_sync_addon_config() {
        check_ajax_referer('affcd_addon_settings', 'nonce');

        if (!current_user_can('manage_affcd')) {
            wp_die(__('Permission denied', 'affiliatewp-cross-domain-full'));
        }

        $addon_slug = sanitize_key($_POST['addon'] ?? '');
        
        // Trigger synchronisation for specific addon
        do_action('affcd_sync_addon_configuration', $addon_slug);

        wp_send_json_success([
            'message' => __('Addon configuration synchronised successfully', 'affiliatewp-cross-domain-full')
        ]);
    }

    /**
     * AJAX: Test addon integration
     */
    public function ajax_test_addon_integration() {
        check_ajax_referer('affcd_addon_settings', 'nonce');

        if (!current_user_can('manage_affcd')) {
            wp_die(__('Permission denied', 'affiliatewp-cross-domain-full'));
        }

        $addon_slug = sanitize_key($_POST['addon'] ?? '');
        
        // Run integration test
        $test_result = $this->run_addon_integration_test($addon_slug);

        if ($test_result['success']) {
            wp_send_json_success([
                'message' => __('Integration test passed successfully', 'affiliatewp-cross-domain-full'),
                'details' => $test_result['details']
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Integration test failed', 'affiliatewp-cross-domain-full'),
                'details' => $test_result['details']
            ]);
        }
    }

    /**
     * Run addon integration test
     *
     * @param string $addon_slug Addon slug
     * @return array Test result
     */
    private function run_addon_integration_test($addon_slug) {
        $detected_addons = $this->addon_detector->get_detected_addons();
        
        if (!isset($detected_addons[$addon_slug])) {
            return [
                'success' => false,
                'details' => __('Addon not found', 'affiliatewp-cross-domain-full')
            ];
        }

        $addon_data = $detected_addons[$addon_slug];
        $test_details = [];

        // Test 1: Check if addon is active
        if (!$addon_data['active']) {
            return [
                'success' => false,
                'details' => __('Addon plugin is not active', 'affiliatewp-cross-domain-full')
            ];
        }
        $test_details[] = __('✓ Addon plugin is active', 'affiliatewp-cross-domain-full');

        // Test 2: Check compatibility
        if (!$addon_data['compatible']) {
            return [
                'success' => false,
                'details' => __('Addon is not compatible: ', 'affiliatewp-cross-domain-full') . implode(', ', $addon_data['issues'])
            ];
        }
        $test_details[] = __('✓ Addon compatibility verified', 'affiliatewp-cross-domain-full');

        // Test 3: Check hook availability
        if (!$addon_data['hooks_available']) {
            return [
                'success' => false,
                'details' => __('Required hooks are not available', 'affiliatewp-cross-domain-full')
            ];
        }
        $test_details[] = __('✓ Required hooks are available', 'affiliatewp-cross-domain-full');

        // Test 4: Check configuration
        $settings = get_option($this->option_name, $this->get_default_settings());
        $addon_settings = $settings['addons'][$addon_slug] ?? [];
        
        if (empty($addon_settings['enabled'])) {
            return [
                'success' => false,
                'details' => __('Addon integration is disabled in settings', 'affiliatewp-cross-domain-full')
            ];
        }
        $test_details[] = __('✓ Addon integration is enabled', 'affiliatewp-cross-domain-full');

        // Test 5: Simulate tracking event
        try {
            $test_data = [
                'test' => true,
                'addon' => $addon_slug,
                'timestamp' => current_time('c')
            ];
            
            do_action('affcd_test_addon_tracking', $addon_slug, $test_data);
            $test_details[] = __('✓ Test tracking event processed successfully', 'affiliatewp-cross-domain-full');
        } catch (Exception $e) {
            return [
                'success' => false,
                'details' => __('Test tracking failed: ', 'affiliatewp-cross-domain-full') . $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'details' => implode('<br>', $test_details)
        ];
    }

    /**
     * Get current settings
     *
     * @return array Current settings
     */
    public function get_settings() {
        return get_option($this->option_name, $this->get_default_settings());
    }

    /**
     * Update specific setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function update_setting($key, $value) {
        $settings = $this->get_settings();
        $settings[$key] = $value;
        return update_option($this->option_name, $settings);
    }

    /**
     * Get addon setting
     *
     * @param string $addon_slug Addon slug
     * @param string $setting_key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_addon_setting($addon_slug, $setting_key = null, $default = null) {
        $settings = $this->get_settings();
        $addon_settings = $settings['addons'][$addon_slug] ?? [];

        if ($setting_key === null) {
            return $addon_settings;
        }

        return $addon_settings[$setting_key] ?? $default;
    }

    /**
     * Update addon setting
     *
     * @param string $addon_slug Addon slug
     * @param string $setting_key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function update_addon_setting($addon_slug, $setting_key, $value) {
        $settings = $this->get_settings();
        
        if (!isset($settings['addons'][$addon_slug])) {
            $settings['addons'][$addon_slug] = [];
        }
        
        $settings['addons'][$addon_slug][$setting_key] = $value;
        
        $result = update_option($this->option_name, $settings);
        
        if ($result) {
            // Trigger configuration sync if auto-sync is enabled
            if (!empty($settings['sync']['auto_enabled'])) {
                do_action('affcd_sync_addon_configuration', $addon_slug);
            }
        }
        
        return $result;
    }

    /**
     * Check if addon is enabled
     *
     * @param string $addon_slug Addon slug
     * @return bool True if enabled
     */
    public function is_addon_enabled($addon_slug) {
        return (bool) $this->get_addon_setting($addon_slug, 'enabled', false);
    }

    /**
     * Enable addon
     *
     * @param string $addon_slug Addon slug
     * @return bool Success status
     */
    public function enable_addon($addon_slug) {
        return $this->update_addon_setting($addon_slug, 'enabled', true);
    }

    /**
     * Disable addon
     *
     * @param string $addon_slug Addon slug
     * @return bool Success status
     */
    public function disable_addon($addon_slug) {
        return $this->update_addon_setting($addon_slug, 'enabled', false);
    }

    /**
     * Get enabled addons
     *
     * @return array Enabled addon slugs
     */
    public function get_enabled_addons() {
        $settings = $this->get_settings();
        $enabled_addons = [];

        foreach ($settings['addons'] as $addon_slug => $addon_settings) {
            if (!empty($addon_settings['enabled'])) {
                $enabled_addons[] = $addon_slug;
            }
        }

        return $enabled_addons;
    }

    /**
     * Export settings
     *
     * @return array Exportable settings
     */
    public function export_settings() {
        $settings = $this->get_settings();
        
        return [
            'export_date' => current_time('c'),
            'plugin_version' => AFFCD_VERSION ?? '1.0.0',
            'wp_version' => get_bloginfo('version'),
            'settings' => $settings,
            'detected_addons' => $this->addon_detector->get_detected_addons(),
            'system_info' => $this->get_system_info()
        ];
    }

    /**
     * Import settings
     *
     * @param array $import_data Import data
     * @return bool|WP_Error Success status or error
     */
    public function import_settings($import_data) {
        // Validate import data
        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            return new WP_Error('invalid_import', __('Invalid import data format', 'affiliatewp-cross-domain-full'));
        }

        // Sanitise imported settings
        $sanitized_settings = $this->sanitize_settings($import_data['settings']);
        
        // Update settings
        $result = update_option($this->option_name, $sanitized_settings);
        
        if ($result) {
            // Log import action
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('analytics');
            
            $wpdb->insert($table_name, [
                'event_type' => 'settings_imported',
                'entity_type' => 'admin',
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'event_data' => json_encode([
                    'import_date' => $import_data['export_date'] ?? null,
                    'plugin_version' => $import_data['plugin_version'] ?? null,
                    'addons_count' => count($import_data['settings']['addons'] ?? [])
                ]),
                'created_at' => current_time('mysql')
            ]);
            
            // Trigger configuration sync if auto-sync is enabled
            if (!empty($sanitized_settings['sync']['auto_enabled'])) {
                do_action('affcd_sync_all_addon_configurations');
            }
        }
        
        return $result;
    }

    /**
     * Reset settings to defaults
     *
     * @return bool Success status
     */
    public function reset_settings() {
        $result = update_option($this->option_name, $this->get_default_settings());
        
        if ($result) {
            // Log reset action
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('analytics');
            
            $wpdb->insert($table_name, [
                'event_type' => 'settings_reset',
                'entity_type' => 'admin',
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'event_data' => json_encode([
                    'reset_timestamp' => current_time('c')
                ]),
                'created_at' => current_time('mysql')
            ]);
        }
        
        return $result;
    }

    /**
     * Get page slug
     *
     * @return string Page slug
     */
    public function get_page_slug() {
        return $this->page_slug;
    }

    /**
     * Get option name
     *
     * @return string Option name
     */
    public function get_option_name() {
        return $this->option_name;
    }

    /**
     * Get current tab
     *
     * @return string Current tab
     */
    public function get_current_tab() {
        return $this->current_tab;
    }

    /**
     * Get available tabs
     *
     * @return array Available tabs
     */
    public function get_tabs() {
        return $this->tabs;
    }

    /**
     * Check if user can access tab
     *
     * @param string $tab_key Tab key
     * @return bool True if can access
     */
    public function can_access_tab($tab_key) {
        if (!isset($this->tabs[$tab_key])) {
            return false;
        }

        return current_user_can($this->tabs[$tab_key]['capability']);
    }

    /**
     * Get addon configuration help text
     *
     * @param string $addon_slug Addon slug
     * @return string Help text
     */
    public function get_addon_help_text($addon_slug) {
        $help_texts = [
            'woocommerce' => __('WooCommerce integration tracks product purchases, cart additions, and customer behaviour across your affiliate network.', 'affiliatewp-cross-domain-full'),
            'easy_digital_downloads' => __('Easy Digital Downloads integration monitors digital product sales, downloads, and licence activations.', 'affiliatewp-cross-domain-full'),
            'gravity_forms' => __('Gravity Forms integration captures form submissions as leads and tracks form-based conversions.', 'affiliatewp-cross-domain-full'),
            'contact_form_7' => __('Contact Form 7 integration tracks contact form submissions for lead generation tracking.', 'affiliatewp-cross-domain-full'),
            'memberpress' => __('MemberPress integration monitors membership signups, renewals, and subscription-based revenue.', 'affiliatewp-cross-domain-full'),
            'learndash' => __('LearnDash integration tracks course enrollments, completions, and learning progress.', 'affiliatewp-cross-domain-full')
        ];

        return $help_texts[$addon_slug] ?? __('This addon integration provides enhanced tracking capabilities for your affiliate network.', 'affiliatewp-cross-domain-full');
    }

    /**
     * Schedule settings backup
     */
    public function schedule_settings_backup() {
        if (!wp_next_scheduled('affcd_backup_addon_settings')) {
            wp_schedule_event(time(), 'daily', 'affcd_backup_addon_settings');
        }
    }

    /**
     * Backup settings
     */
    public function backup_settings() {
        $backup_data = $this->export_settings();
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/affcd-backups/';
        
        if (!wp_mkdir_p($backup_dir)) {
            return false;
        }
        
        $backup_file = $backup_dir . 'addon-settings-backup-' . date('Y-m-d-H-i-s') . '.json';
        
        return file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
    }

    /**
     * Clean up old backups
     */
    public function cleanup_old_backups() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/affcd-backups/';
        
        if (!is_dir($backup_dir)) {
            return;
        }
        
        $files = glob($backup_dir . 'addon-settings-backup-*.json');
        
        // Keep only the last 30 backups
        if (count($files) > 30) {
            // Sort by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $files_to_remove = array_slice($files, 0, count($files) - 30);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }
}

// Hook to clean up old backups
add_action('affcd_backup_addon_settings', function() {
    if (class_exists('AFFCD_Addon_Settings_Admin')) {
        $instance = new AFFCD_Addon_Settings_Admin(null, null);
        $instance->backup_settings();
        $instance->cleanup_old_backups();
    }
});