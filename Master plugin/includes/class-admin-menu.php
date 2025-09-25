<?php
/**
 * Complete Admin Interface Class
 * File: /wp-content/plugins/affiliate-cross-domain-full/includes/class-admin-menu.php
 * Plugin: AffiliateWP Cross Domain Full
 * Author: Richard King, Starne Consulting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AFFCD_Admin_Menu handles all admin interface functionality including
 * dashboard, settings, analytics, and management pages.
 */
class AFFCD_Admin_Menu {

    /**
     * Database manager instance
     *
     * @var AFFCD_Database_Manager
     */
    private $database_manager;

    /**
     * Vanity code manager instance
     *
     * @var AFFCD_Vanity_Code_Manager
     */
    private $vanity_code_manager;

    /**
     * Webhook handler instance
     *
     * @var AFFCD_Webhook_Handler
     */
    private $webhook_handler;

    /**
     * Bulk operations instance
     *
     * @var AFFCD_Bulk_Operations
     */
    private $bulk_operations;

    /**
     * Main menu slug
     *
     * @var string
     */
    private $menu_slug = 'affcd-dashboard';

    /**
     * Constructor
     */
    public function __construct() {
        $this->database_manager = new AFFCD_Database_Manager();
        $this->vanity_code_manager = new AFFCD_Vanity_Code_Manager();
        $this->webhook_handler = new AFFCD_Webhook_Handler();
        $this->bulk_operations = new AFFCD_Bulk_Operations();
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
        
        // AJAX handlers for admin interface
        add_action('wp_ajax_affcd_get_dashboard_stats', [$this, 'ajax_get_dashboard_stats']);
        add_action('wp_ajax_affcd_get_analytics_data', [$this, 'ajax_get_analytics_data']);
        add_action('wp_ajax_affcd_get_performance_metrics', [$this, 'ajax_get_performance_metrics']);
        add_action('wp_ajax_affcd_export_report', [$this, 'ajax_export_report']);
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_pages() {
        if (!current_user_can('manage_affiliates')) {
            return;
        }

        // Main dashboard page
        add_menu_page(
            __('Cross Domain Affiliate', 'affiliate-cross-domain-full'),
            __('Cross Domain', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            $this->menu_slug,
            [$this, 'render_dashboard_page'],
            'dashicons-networking',
            30
        );

        // Dashboard (rename main page)
        add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'affiliate-cross-domain-full'),
            __('Dashboard', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            $this->menu_slug,
            [$this, 'render_dashboard_page']
        );

        // Vanity Codes
        add_submenu_page(
            $this->menu_slug,
            __('Vanity Codes', 'affiliate-cross-domain-full'),
            __('Vanity Codes', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-vanity-codes',
            [$this, 'render_vanity_codes_page']
        );

        // Authorized Domains
        add_submenu_page(
            $this->menu_slug,
            __('Authorized Domains', 'affiliate-cross-domain-full'),
            __('Domains', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-domains',
            [$this, 'render_domains_page']
        );

        // Analytics & Reports
        add_submenu_page(
            $this->menu_slug,
            __('Analytics & Reports', 'affiliate-cross-domain-full'),
            __('Analytics', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-analytics',
            [$this, 'render_analytics_page']
        );

        // Webhook Management
        add_submenu_page(
            $this->menu_slug,
            __('Webhook Management', 'affiliate-cross-domain-full'),
            __('Webhooks', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-webhooks',
            [$this, 'render_webhooks_page']
        );

        // Bulk Operations
        add_submenu_page(
            $this->menu_slug,
            __('Bulk Operations', 'affiliate-cross-domain-full'),
            __('Bulk Operations', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-bulk-operations',
            [$this, 'render_bulk_operations_page']
        );

        // System Status
        add_submenu_page(
            $this->menu_slug,
            __('System Status', 'affiliate-cross-domain-full'),
            __('System Status', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-system-status',
            [$this, 'render_system_status_page']
        );

        // Settings
        add_submenu_page(
            $this->menu_slug,
            __('Settings', 'affiliate-cross-domain-full'),
            __('Settings', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix Current admin page hook suffix
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our admin pages
        if (strpos($hook_suffix, 'affcd') === false && strpos($hook_suffix, 'cross-domain') === false) {
            return;
        }

        // Enqueue Chart.js for analytics
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );

        // Admin CSS
        wp_enqueue_style(
            'affcd-admin-css',
            AFFCD_PLUGIN_URL . 'assets/admin/css/admin.css',
            ['wp-admin', 'dashicons'],
            AFFCD_VERSION
        );

        // Admin JavaScript
        wp_enqueue_script(
            'affcd-admin-js',
            AFFCD_PLUGIN_URL . 'assets/admin/js/admin.js',
            ['jquery', 'chart-js', 'wp-util'],
            AFFCD_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('affcd-admin-js', 'affcdAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_admin_nonce'),
            'webhook_nonce' => wp_create_nonce('affcd_webhook_nonce'),
            'bulk_nonce' => wp_create_nonce('affcd_bulk_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'affiliate-cross-domain-full'),
                'error' => __('An error occurred', 'affiliate-cross-domain-full'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'affiliate-cross-domain-full'),
                'confirm_bulk_delete' => __('Are you sure you want to delete the selected items?', 'affiliate-cross-domain-full'),
                'no_items_selected' => __('Please select items to perform bulk actions.', 'affiliate-cross-domain-full')
            ]
        ]);

        // Add select2 for enhanced select boxes
        wp_enqueue_script(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );

        wp_enqueue_style(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css',
            [],
            '4.1.0'
        );
    }

    /**
     * Render main dashboard page
     */
    public function render_dashboard_page() {
        $stats = $this->get_dashboard_statistics();
        $recent_activity = $this->get_recent_activity();
        $system_health = affcd_get_health_status();
        
        ?>
        <div class="wrap affcd-dashboard">
            <h1 class="wp-heading-inline"><?php _e('Cross Domain Affiliate Dashboard', 'affiliate-cross-domain-full'); ?></h1>
            
            <div class="affcd-dashboard-widgets">
                <!-- Key Metrics Row -->
                <div class="affcd-stats-row">
                    <div class="affcd-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-tickets-alt"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_codes']); ?></h3>
                            <p><?php _e('Total Codes', 'affiliate-cross-domain-full'); ?></p>
                            <span class="stat-trend <?php echo $stats['codes_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $stats['codes_trend'] >= 0 ? '+' : ''; ?><?php echo $stats['codes_trend']; ?>%
                            </span>
                        </div>
                    </div>

                    <div class="affcd-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active_domains']); ?></h3>
                            <p><?php _e('Active Domains', 'affiliate-cross-domain-full'); ?></p>
                            <span class="stat-detail"><?php echo $stats['total_domains']; ?> <?php _e('total', 'affiliate-cross-domain-full'); ?></span>
                        </div>
                    </div>

                    <div class="affcd-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['monthly_conversions']); ?></h3>
                            <p><?php _e('This Month Conversions', 'affiliate-cross-domain-full'); ?></p>
                            <span class="stat-trend positive">
                                +<?php echo $stats['conversion_growth']; ?>%
                            </span>
                        </div>
                    </div>

                    <div class="affcd-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo affcd_format_currency($stats['monthly_revenue']); ?></h3>
                            <p><?php _e('This Month Revenue', 'affiliate-cross-domain-full'); ?></p>
                            <span class="stat-detail"><?php echo affcd_format_currency($stats['avg_order_value']); ?> <?php _e('AOV', 'affiliate-cross-domain-full'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="affcd-charts-row">
                    <div class="affcd-chart-container">
                        <div class="chart-header">
                            <h3><?php _e('Usage Trends (Last 30 Days)', 'affiliate-cross-domain-full'); ?></h3>
                            <div class="chart-controls">
                                <select id="usage-chart-period">
                                    <option value="30"><?php _e('Last 30 Days', 'affiliate-cross-domain-full'); ?></option>
                                    <option value="7"><?php _e('Last 7 Days', 'affiliate-cross-domain-full'); ?></option>
                                    <option value="90"><?php _e('Last 90 Days', 'affiliate-cross-domain-full'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="chart-content">
                            <canvas id="usage-trends-chart" width="800" height="300"></canvas>
                        </div>
                    </div>

                    <div class="affcd-chart-container">
                        <div class="chart-header">
                            <h3><?php _e('Top Performing Codes', 'affiliate-cross-domain-full'); ?></h3>
                        </div>
                        <div class="chart-content">
                            <canvas id="top-codes-chart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Activity and Health Row -->
                <div class="affcd-info-row">
                    <div class="affcd-info-card">
                        <h3><?php _e('Recent Activity', 'affiliate-cross-domain-full'); ?></h3>
                        <div class="activity-list">
                            <?php if (empty($recent_activity)): ?>
                                <p class="no-activity"><?php _e('No recent activity.', 'affiliate-cross-domain-full'); ?></p>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <span class="dashicons dashicons-<?php echo $this->get_activity_icon($activity->activity_type); ?>"></span>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?php echo esc_html($activity->description); ?></div>
                                            <div class="activity-meta">
                                                <?php if ($activity->domain): ?>
                                                    <span class="activity-domain"><?php echo esc_html($activity->domain); ?></span>
                                                <?php endif; ?>
                                                <span class="activity-time"><?php echo human_time_diff(strtotime($activity->created_at)); ?> <?php _e('ago', 'affiliate-cross-domain-full'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="activity-footer">
                                    <a href="<?php echo admin_url('admin.php?page=affcd-analytics'); ?>" class="button"><?php _e('View All Activity', 'affiliate-cross-domain-full'); ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="affcd-info-card">
                        <h3><?php _e('System Health', 'affiliate-cross-domain-full'); ?></h3>
                        <div class="health-status">
                            <div class="health-item <?php echo $system_health['database'] ? 'status-ok' : 'status-error'; ?>">
                                <span class="health-indicator"></span>
                                <span class="health-label"><?php _e('Database Connection', 'affiliate-cross-domain-full'); ?></span>
                                <span class="health-status"><?php echo $system_health['database'] ? __('OK', 'affiliate-cross-domain-full') : __('Error', 'affiliate-cross-domain-full'); ?></span>
                            </div>

                            <div class="health-item <?php echo $system_health['api_endpoint'] ? 'status-ok' : 'status-error'; ?>">
                                <span class="health-indicator"></span>
                                <span class="health-label"><?php _e('API Endpoint', 'affiliate-cross-domain-full'); ?></span>
                                <span class="health-status"><?php echo $system_health['api_endpoint'] ? __('OK', 'affiliate-cross-domain-full') : __('Error', 'affiliate-cross-domain-full'); ?></span>
                            </div>

                            <div class="health-item <?php echo $system_health['webhooks'] ? 'status-ok' : 'status-warning'; ?>">
                                <span class="health-indicator"></span>
                                <span class="health-label"><?php _e('Webhook System', 'affiliate-cross-domain-full'); ?></span>
                                <span class="health-status"><?php echo $system_health['webhooks'] ? __('OK', 'affiliate-cross-domain-full') : __('Warning', 'affiliate-cross-domain-full'); ?></span>
                            </div>

                            <div class="health-item <?php echo $system_health['cache'] ? 'status-ok' : 'status-warning'; ?>">
                                <span class="health-indicator"></span>
                                <span class="health-label"><?php _e('Cache System', 'affiliate-cross-domain-full'); ?></span>
                                <span class="health-status"><?php echo $system_health['cache'] ? __('OK', 'affiliate-cross-domain-full') : __('Warning', 'affiliate-cross-domain-full'); ?></span>
                            </div>
                        </div>
                        
                        <div class="health-footer">
                            <a href="<?php echo admin_url('admin.php?page=affcd-system-status'); ?>" class="button"><?php _e('View System Status', 'affiliate-cross-domain-full'); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Row -->
                <div class="affcd-actions-row">
                    <div class="quick-action-card">
                        <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes&action=add'); ?>" class="action-link">
                            <div class="action-icon">
                                <span class="dashicons dashicons-plus-alt"></span>
                            </div>
                            <div class="action-text">
                                <h4><?php _e('Add Vanity Code', 'affiliate-cross-domain-full'); ?></h4>
                                <p><?php _e('Create a new discount code', 'affiliate-cross-domain-full'); ?></p>
                            </div>
                        </a>
                    </div>

                    <div class="quick-action-card">
                        <a href="<?php echo admin_url('admin.php?page=affcd-domains&action=add'); ?>" class="action-link">
                            <div class="action-icon">
                                <span class="dashicons dashicons-admin-site-alt3"></span>
                            </div>
                            <div class="action-text">
                                <h4><?php _e('Add Domain', 'affiliate-cross-domain-full'); ?></h4>
                                <p><?php _e('Authorise a new domain', 'affiliate-cross-domain-full'); ?></p>
                            </div>
                        </a>
                    </div>

                    <div class="quick-action-card">
                        <a href="<?php echo admin_url('admin.php?page=affcd-analytics'); ?>" class="action-link">
                            <div class="action-icon">
                                <span class="dashicons dashicons-chart-bar"></span>
                            </div>
                            <div class="action-text">
                                <h4><?php _e('View Analytics', 'affiliate-cross-domain-full'); ?></h4>
                                <p><?php _e('Detailed performance reports', 'affiliate-cross-domain-full'); ?></p>
                            </div>
                        </a>
                    </div>

                    <div class="quick-action-card">
                        <button type="button" class="action-link test-all-connections" data-nonce="<?php echo wp_create_nonce('affcd_test_connections'); ?>">
                            <div class="action-icon">
                                <span class="dashicons dashicons-performance"></span>
                            </div>
                            <div class="action-text">
                                <h4><?php _e('Test Connections', 'affiliate-cross-domain-full'); ?></h4>
                                <p><?php _e('Verify all domain connections', 'affiliate-cross-domain-full'); ?></p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize dashboard
            affcdDashboard.init();
            
            // Auto-refresh stats every 5 minutes
            setInterval(function() {
                affcdDashboard.refreshStats();
            }, 300000);
        });
        </script>
        <?php
    }

    /**
     * Render vanity codes management page
     */
    public function render_vanity_codes_page() {
        // Handle form submissions
        if (isset($_POST['action'])) {
            $this->handle_vanity_code_action();
        }

        $codes = $this->vanity_code_manager->get_all_codes();
        $total_codes = count($codes);
        $active_codes = count(array_filter($codes, function($code) {
            return $code->status === 'active';
        }));
        
        ?>
        <div class="wrap affcd-vanity-codes">
            <h1 class="wp-heading-inline"><?php _e('Vanity Codes', 'affiliate-cross-domain-full'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes&action=add'); ?>" class="page-title-action"><?php _e('Add New Code', 'affiliate-cross-domain-full'); ?></a>
            
            <div class="affcd-codes-summary">
                <div class="codes-stat">
                    <span class="stat-number"><?php echo $total_codes; ?></span>
                    <span class="stat-label"><?php _e('Total Codes', 'affiliate-cross-domain-full'); ?></span>
                </div>
                <div class="codes-stat">
                    <span class="stat-number"><?php echo $active_codes; ?></span>
                    <span class="stat-label"><?php _e('Active Codes', 'affiliate-cross-domain-full'); ?></span>
                </div>
            </div>

            <?php $this->display_vanity_codes_table(); ?>
        </div>
        <?php
    }

    /**
     * Render domains management page
     */
    public function render_domains_page() {
        // Handle form submissions
        if (isset($_POST['action'])) {
            $this->handle_domains_action();
        }

        $domains = $this->database_manager->get_authorized_domains();
        
        ?>
        <div class="wrap affcd-domains">
            <h1 class="wp-heading-inline"><?php _e('Authorised Domains', 'affiliate-cross-domain-full'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=affcd-domains&action=add'); ?>" class="page-title-action"><?php _e('Add Domain', 'affiliate-cross-domain-full'); ?></a>
            
            <?php $this->display_domains_table($domains); ?>
        </div>
        <?php
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        $analytics_data = $this->get_analytics_data();
        
        ?>
        <div class="wrap affcd-analytics">
            <h1 class="wp-heading-inline"><?php _e('Analytics & Reports', 'affiliate-cross-domain-full'); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="#overview" class="nav-tab nav-tab-active"><?php _e('Overview', 'affiliate-cross-domain-full'); ?></a>
                <a href="#codes" class="nav-tab"><?php _e('Codes', 'affiliate-cross-domain-full'); ?></a>
                <a href="#domains" class="nav-tab"><?php _e('Domains', 'affiliate-cross-domain-full'); ?></a>
                <a href="#performance" class="nav-tab"><?php _e('Performance', 'affiliate-cross-domain-full'); ?></a>
            </div>

            <div id="overview" class="tab-content active">
                <?php $this->render_analytics_overview($analytics_data); ?>
            </div>

            <div id="codes" class="tab-content">
                <?php $this->render_code_analytics($analytics_data); ?>
            </div>

            <div id="domains" class="tab-content">
                <?php $this->render_domain_analytics($analytics_data); ?>
            </div>

            <div id="performance" class="tab-content">
                <?php $this->render_performance_analytics($analytics_data); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render webhooks management page
     */
    public function render_webhooks_page() {
        $webhooks = $this->webhook_handler->get_all_webhooks();
        
        ?>
        <div class="wrap affcd-webhooks">
            <h1 class="wp-heading-inline"><?php _e('Webhook Management', 'affiliate-cross-domain-full'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=affcd-webhooks&action=add'); ?>" class="page-title-action"><?php _e('Add Webhook', 'affiliate-cross-domain-full'); ?></a>
            
            <?php $this->display_webhooks_table($webhooks); ?>
        </div>
        <?php
    }

    /**
     * Render bulk operations page
     */
    public function render_bulk_operations_page() {
        ?>
        <div class="wrap affcd-bulk-operations">
            <h1 class="wp-heading-inline"><?php _e('Bulk Operations', 'affiliate-cross-domain-full'); ?></h1>
            
            <div class="bulk-operations-sections">
                <div class="bulk-section">
                    <h2><?php _e('Bulk Code Operations', 'affiliate-cross-domain-full'); ?></h2>
                    <?php $this->render_bulk_code_operations(); ?>
                </div>

                <div class="bulk-section">
                    <h2><?php _e('Bulk Domain Operations', 'affiliate-cross-domain-full'); ?></h2>
                    <?php $this->render_bulk_domain_operations(); ?>
                </div>

                <div class="bulk-section">
                    <h2><?php _e('Data Export/Import', 'affiliate-cross-domain-full'); ?></h2>
                    <?php $this->render_data_operations(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render system status page
     */
    public function render_system_status_page() {
        $system_info = $this->get_system_information();
        
        ?>
        <div class="wrap affcd-system-status">
            <h1 class="wp-heading-inline"><?php _e('System Status', 'affiliate-cross-domain-full'); ?></h1>
            
            <div class="system-status-grid">
                <div class="status-section">
                    <h2><?php _e('System Health', 'affiliate-cross-domain-full'); ?></h2>
                    <?php $this->render_system_health_checks(); ?>
                </div>

                <div class="status-section">
                    <h2><?php _e('System Information', 'affiliate-cross-domain-full'); ?></h2>
                    <?php $this->render_system_information($system_info); ?>
                </div>

                <div class="status-section">
                    <h2><?php _e('Log Files', 'affiliate-cross-domain-full'); ?></h2>
                    <?php $this->render_log_files(); ?>
                </div>
            </div>
        </div>
        <?php
    }
/**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            $this->handle_settings_save();
        }

        $settings = get_option('affcd_settings', []);
        
        ?>
        <div class="wrap affcd-settings">
            <h1 class="wp-heading-inline"><?php _e('Settings', 'affiliate-cross-domain-full'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('affcd_settings', 'affcd_settings_nonce'); ?>
                
                <div class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'affiliate-cross-domain-full'); ?></a>
                    <a href="#api" class="nav-tab"><?php _e('API Settings', 'affiliate-cross-domain-full'); ?></a>
                    <a href="#security" class="nav-tab"><?php _e('Security', 'affiliate-cross-domain-full'); ?></a>
                    <a href="#notifications" class="nav-tab"><?php _e('Notifications', 'affiliate-cross-domain-full'); ?></a>
                    <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'affiliate-cross-domain-full'); ?></a>
                </div>

                <div id="general" class="tab-content active">
                    <?php $this->render_general_settings($settings); ?>
                </div>

                <div id="api" class="tab-content">
                    <?php $this->render_api_settings($settings); ?>
                </div>

                <div id="security" class="tab-content">
                    <?php $this->render_security_settings($settings); ?>
                </div>

                <div id="notifications" class="tab-content">
                    <?php $this->render_notification_settings($settings); ?>
                </div>

                <div id="advanced" class="tab-content">
                    <?php $this->render_advanced_settings($settings); ?>
                </div>

                <?php submit_button(__('Save Settings', 'affiliate-cross-domain-full'), 'primary', 'submit'); ?>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update active content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
                
                // Update URL hash
                window.location.hash = target;
            });
            
            // Show tab based on URL hash
            if (window.location.hash) {
                var hashTab = $('a[href="' + window.location.hash + '"]');
                if (hashTab.length) {
                    hashTab.trigger('click');
                }
            }
            
            // API key generation
            window.affcdGenerateApiKey = function() {
                if (confirm('<?php _e('Generate a new API key? This will invalidate the current key and affect all connected domains.', 'affiliate-cross-domain-full'); ?>')) {
                    var newKey = 'affcd_' + Math.random().toString(36).substr(2, 32) + Math.random().toString(36).substr(2, 16);
                    $('input[name="affcd_settings[api_key]"]').val(newKey);
                }
            };
            
            // Test webhook functionality
            $('.test-webhook').on('click', function() {
                var $button = $(this);
                var webhookUrl = $('input[name="affcd_settings[webhook_test_url]"]').val();
                
                if (!webhookUrl) {
                    alert('<?php _e('Please enter a webhook URL to test.', 'affiliate-cross-domain-full'); ?>');
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e('Testing...', 'affiliate-cross-domain-full'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'affcd_test_webhook',
                        url: webhookUrl,
                        nonce: '<?php echo wp_create_nonce('affcd_test_webhook'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Webhook test successful!', 'affiliate-cross-domain-full'); ?>');
                        } else {
                            alert('<?php _e('Webhook test failed: ', 'affiliate-cross-domain-full'); ?>' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php _e('Webhook test failed due to network error.', 'affiliate-cross-domain-full'); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Test Webhook', 'affiliate-cross-domain-full'); ?>');
                    }
                });
            });
            
            // Clear cache functionality
            $('.clear-cache').on('click', function() {
                var $button = $(this);
                
                if (!confirm('<?php _e('Are you sure you want to clear all cache?', 'affiliate-cross-domain-full'); ?>')) {
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e('Clearing...', 'affiliate-cross-domain-full'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'affcd_clear_cache',
                        nonce: '<?php echo wp_create_nonce('affcd_clear_cache'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Cache cleared successfully!', 'affiliate-cross-domain-full'); ?>');
                        } else {
                            alert('<?php _e('Cache clear failed.', 'affiliate-cross-domain-full'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Cache clear failed due to network error.', 'affiliate-cross-domain-full'); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Clear Cache', 'affiliate-cross-domain-full'); ?>');
                    }
                });
            });
            
            // System test functionality
            $('.system-test').on('click', function() {
                var $button = $(this);
                
                $button.prop('disabled', true).text('<?php _e('Testing...', 'affiliate-cross-domain-full'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'affcd_system_test',
                        nonce: '<?php echo wp_create_nonce('affcd_system_test'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var results = response.data;
                            var message = '<?php _e('System Test Results:', 'affiliate-cross-domain-full'); ?>\n\n';
                            
                            $.each(results, function(test, result) {
                                message += test + ': ' + (result ? '<?php _e('PASS', 'affiliate-cross-domain-full'); ?>' : '<?php _e('FAIL', 'affiliate-cross-domain-full'); ?>') + '\n';
                            });
                            
                            alert(message);
                        } else {
                            alert('<?php _e('System test failed.', 'affiliate-cross-domain-full'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('System test failed due to network error.', 'affiliate-cross-domain-full'); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Run System Test', 'affiliate-cross-domain-full'); ?>');
                    }
                });
            });
        });
        </script>

        <style>
        .affcd-settings .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        
        .affcd-settings .tab-content {
            display: none;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-top: none;
        }
        
        .affcd-settings .tab-content.active {
            display: block;
        }
        
        .affcd-settings .form-table th {
            width: 250px;
            padding-left: 0;
        }
        
        .affcd-settings .form-table td {
            padding-left: 20px;
        }
        
        .affcd-settings .description {
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        
        .affcd-settings .button-group {
            margin-top: 10px;
        }
        
        .affcd-settings .button-group .button {
            margin-right: 10px;
        }
        
        .affcd-settings .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .affcd-settings .status-indicator.ok {
            background-color: #46b450;
        }
        
        .affcd-settings .status-indicator.warning {
            background-color: #ffb900;
        }
        
        .affcd-settings .status-indicator.error {
            background-color: #dc3232;
        }
        
        .affcd-settings .settings-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .affcd-settings .settings-section:last-child {
            border-bottom: none;
        }
        
        .affcd-settings .settings-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .affcd-settings .code-field {
            font-family: Consolas, Monaco, 'Courier New', monospace;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        
        .affcd-settings .readonly-field {
            background: #f7f7f7;
            color: #666;
        }
        
        .affcd-settings .settings-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 12px;
            margin: 15px 0;
        }
        
        .affcd-settings .settings-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 12px;
            margin: 15px 0;
            color: #721c24;
        }
        
        .affcd-settings .checkbox-group label {
            display: block;
            margin-bottom: 8px;
        }
        
        .affcd-settings .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
        }
        </style>
        <?php
    }

    /**
     * Render advanced settings
     *
     * @param array $settings
     */
    private function render_advanced_settings($settings) {
        ?>
        <div class="advanced-settings">
            <div class="settings-section">
                <h3><?php _e('Performance Settings', 'affiliate-cross-domain-full'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Database Optimisation', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="affcd_settings[db_optimisation]" value="1" <?php checked(isset($settings['db_optimisation']) ? $settings['db_optimisation'] : 1); ?>>
                                <?php _e('Enable database query optimisation', 'affiliate-cross-domain-full'); ?>
                            </label>
                            <p class="description"><?php _e('Optimises database queries for better performance. Recommended for high-traffic sites.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Object Cache', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="affcd_settings[object_cache]" value="1" <?php checked(isset($settings['object_cache']) ? $settings['object_cache'] : 1); ?>>
                                <?php _e('Use object caching when available', 'affiliate-cross-domain-full'); ?>
                            </label>
                            <p class="description"><?php _e('Utilises WordPress object caching for improved performance.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Lazy Loading', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="affcd_settings[lazy_loading]" value="1" <?php checked(isset($settings['lazy_loading']) ? $settings['lazy_loading'] : 0); ?>>
                                <?php _e('Enable lazy loading for analytics data', 'affiliate-cross-domain-full'); ?>
                            </label>
                            <p class="description"><?php _e('Loads analytics data on demand to improve initial page load times.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h3><?php _e('Developer Settings', 'affiliate-cross-domain-full'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('API Version', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <select name="affcd_settings[api_version]">
                                <option value="v1" <?php selected(isset($settings['api_version']) ? $settings['api_version'] : 'v1', 'v1'); ?>>Version 1.0 (Current)</option>
                                <option value="v2" <?php selected(isset($settings['api_version']) ? $settings['api_version'] : 'v1', 'v2'); ?>>Version 2.0 (Beta)</option>
                            </select>
                            <p class="description"><?php _e('Select API version for cross-domain communications.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Custom Headers', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <textarea name="affcd_settings[custom_headers]" rows="4" cols="50" placeholder="X-Custom-Header: value"><?php echo esc_textarea(isset($settings['custom_headers']) ? $settings['custom_headers'] : ''); ?></textarea>
                            <p class="description"><?php _e('Custom HTTP headers to send with API requests. One header per line in format "Header-Name: value".', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Webhook Retry Logic', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <input type="number" name="affcd_settings[webhook_retries]" value="<?php echo esc_attr(isset($settings['webhook_retries']) ? $settings['webhook_retries'] : 3); ?>" min="0" max="10">
                            <span><?php _e('retry attempts', 'affiliate-cross-domain-full'); ?></span>
                            <p class="description"><?php _e('Number of retry attempts for failed webhook deliveries.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Webhook Test URL', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <input type="url" name="affcd_settings[webhook_test_url]" value="<?php echo esc_attr(isset($settings['webhook_test_url']) ? $settings['webhook_test_url'] : ''); ?>" class="regular-text">
                            <button type="button" class="button test-webhook"><?php _e('Test Webhook', 'affiliate-cross-domain-full'); ?></button>
                            <p class="description"><?php _e('URL for testing webhook functionality.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h3><?php _e('Maintenance & Cleanup', 'affiliate-cross-domain-full'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto Cleanup', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="affcd_settings[auto_cleanup]" value="1" <?php checked(isset($settings['auto_cleanup']) ? $settings['auto_cleanup'] : 1); ?>>
                                <?php _e('Enable automatic cleanup of old data', 'affiliate-cross-domain-full'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically removes old logs and temporary data based on retention settings.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Cleanup Frequency', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <select name="affcd_settings[cleanup_frequency]">
                                <option value="daily" <?php selected(isset($settings['cleanup_frequency']) ? $settings['cleanup_frequency'] : 'weekly', 'daily'); ?>><?php _e('Daily', 'affiliate-cross-domain-full'); ?></option>
                                <option value="weekly" <?php selected(isset($settings['cleanup_frequency']) ? $settings['cleanup_frequency'] : 'weekly', 'weekly'); ?>><?php _e('Weekly', 'affiliate-cross-domain-full'); ?></option>
                                <option value="monthly" <?php selected(isset($settings['cleanup_frequency']) ? $settings['cleanup_frequency'] : 'weekly', 'monthly'); ?>><?php _e('Monthly', 'affiliate-cross-domain-full'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('System Status', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <div class="button-group">
                                <button type="button" class="button clear-cache"><?php _e('Clear Cache', 'affiliate-cross-domain-full'); ?></button>
                                <button type="button" class="button system-test"><?php _e('Run System Test', 'affiliate-cross-domain-full'); ?></button>
                                <a href="<?php echo admin_url('admin.php?page=affcd-system-status'); ?>" class="button"><?php _e('View System Status', 'affiliate-cross-domain-full'); ?></a>
                            </div>
                            <p class="description"><?php _e('Maintenance tools for system administration.', 'affiliate-cross-domain-full'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h3><?php _e('Integration Settings', 'affiliate-cross-domain-full'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('AffiliateWP Integration', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <?php if (affcd_is_affiliatewp_active()): ?>
                                <span class="status-indicator ok"></span>
                                <?php _e('AffiliateWP is active and integrated', 'affiliate-cross-domain-full'); ?>
                                <div class="button-group">
                                    <a href="<?php echo admin_url('admin.php?page=affiliate-wp'); ?>" class="button"><?php _e('AffiliateWP Settings', 'affiliate-cross-domain-full'); ?></a>
                                    <button type="button" class="button" onclick="window.location.href='<?php echo wp_nonce_url(admin_url('admin.php?page=affcd-settings&action=sync_codes'), 'sync_codes'); ?>'"><?php _e('Sync Codes', 'affiliate-cross-domain-full'); ?></button>
                                </div>
                            <?php else: ?>
                                <span class="status-indicator warning"></span>
                                <?php _e('AffiliateWP is not active', 'affiliate-cross-domain-full'); ?>
                                <p class="description"><?php _e('Install and activate AffiliateWP for full functionality.', 'affiliate-cross-domain-full'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('WooCommerce Integration', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="affcd_settings[woocommerce_integration]" value="1" <?php checked(isset($settings['woocommerce_integration']) ? $settings['woocommerce_integration'] : 1); ?>>
                                <?php _e('Enable WooCommerce integration', 'affiliate-cross-domain-full'); ?>
                            </label>
                            <?php if (class_exists('WooCommerce')): ?>
                                <span class="status-indicator ok"></span>
                                <?php _e('WooCommerce detected', 'affiliate-cross-domain-full'); ?>
                            <?php else: ?>
                                <span class="status-indicator warning"></span>
                                <?php _e('WooCommerce not detected', 'affiliate-cross-domain-full'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Easy Digital Downloads', 'affiliate-cross-domain-full'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="affcd_settings[edd_integration]" value="1" <?php checked(isset($settings['edd_integration']) ? $settings['edd_integration'] : 1); ?>>
                                <?php _e('Enable Easy Digital Downloads integration', 'affiliate-cross-domain-full'); ?>
                            </label>
                            <?php if (class_exists('Easy_Digital_Downloads')): ?>
                                <span class="status-indicator ok"></span>
                                <?php _e('Easy Digital Downloads detected', 'affiliate-cross-domain-full'); ?>
                            <?php else: ?>
                                <span class="status-indicator warning"></span>
                                <?php _e('Easy Digital Downloads not detected', 'affiliate-cross-domain-full'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-note">
                <strong><?php _e('Note:', 'affiliate-cross-domain-full'); ?></strong>
                <?php _e('Advanced settings should only be modified by experienced developers. Incorrect configuration may affect system functionality.', 'affiliate-cross-domain-full'); ?>
            </div>
        </div>
        <?php
    /**
     * Handle export data
     */
    private function handle_export_data() {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_data')) {
            wp_die(__('Security check failed.', 'affiliate-cross-domain-full'));
        }

        $export_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'overview';
        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';

        try {
            $export_url = $this->generate_export_file($export_type, $format, '30');
            wp_redirect($export_url);
        } catch (Exception $e) {
            wp_redirect(add_query_arg('message', 'export_failed', wp_get_referer()));
        }
        
        exit;
    }

    /**
     * Process add vanity code
     */
    private function process_add_vanity_code() {
        $code_data = [
            'code' => sanitize_text_field($_POST['code']),
            'discount_type' => sanitize_text_field($_POST['discount_type']),
            'discount_value' => floatval($_POST['discount_value']),
            'description' => sanitize_textarea_field($_POST['description']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'usage_limit' => absint($_POST['usage_limit']),
            'status' => sanitize_text_field($_POST['status'])
        ];

        $result = $this->vanity_code_manager->create_code($code_data);
        
        if ($result) {
            wp_redirect(add_query_arg('message', 'code_added', admin_url('admin.php?page=affcd-vanity-codes')));
        } else {
            wp_redirect(add_query_arg('message', 'code_add_failed', wp_get_referer()));
        }
        
        exit;
    }

    /**
     * Process edit vanity code
     */
    private function process_edit_vanity_code() {
        $code_id = absint($_POST['code_id']);
        
        $code_data = [
            'code' => sanitize_text_field($_POST['code']),
            'discount_type' => sanitize_text_field($_POST['discount_type']),
            'discount_value' => floatval($_POST['discount_value']),
            'description' => sanitize_textarea_field($_POST['description']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'usage_limit' => absint($_POST['usage_limit']),
            'status' => sanitize_text_field($_POST['status'])
        ];

        $result = $this->vanity_code_manager->update_code($code_id, $code_data);
        
        if ($result) {
            wp_redirect(add_query_arg('message', 'code_updated', admin_url('admin.php?page=affcd-vanity-codes')));
        } else {
            wp_redirect(add_query_arg('message', 'code_update_failed', wp_get_referer()));
        }
        
        exit;
    }

    /**
     * Process delete vanity code
     */
    private function process_delete_vanity_code() {
        $code_id = absint($_POST['code_id']);
        
        $result = $this->vanity_code_manager->delete_code($code_id);
        
        if ($result) {
            wp_redirect(add_query_arg('message', 'code_deleted', admin_url('admin.php?page=affcd-vanity-codes')));
        } else {
            wp_redirect(add_query_arg('message', 'code_delete_failed', wp_get_referer()));
        }
        
        exit;
    }

    /**
     * Process add domain
     */
    private function process_add_domain() {
        $domain_data = [
            'domain' => sanitize_text_field($_POST['domain']),
            'name' => sanitize_text_field($_POST['name']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'webhook_url' => esc_url_raw($_POST['webhook_url']),
            'status' => sanitize_text_field($_POST['status']),
            'rate_limit' => absint($_POST['rate_limit'])
        ];

        $result = $this->database_manager->add_authorized_domain($domain_data);
        
        if ($result) {
            wp_redirect(add_query_arg('message', 'domain_added', admin_url('admin.php?page=affcd-domains')));
        } else {
            wp_redirect(add_query_arg('message', 'domain_add_failed', wp_get_referer()));
        }
        
        exit;
    }

    /**
     * Process edit domain
     */
    private function process_edit_domain() {
        $domain_id = absint($_POST['domain_id']);
        
        $domain_data = [
            'domain' => sanitize_text_field($_POST['domain']),
            'name' => sanitize_text_field($_POST['name']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'webhook_url' => esc_url_raw($_POST['webhook_url']),
            'status' => sanitize_text_field($_POST['status']),
            'rate_limit' => absint($_POST['rate_limit'])
        ];

        $result = $this->database_manager->update_authorized_domain($domain_id, $domain_data);
        
        if ($result) {
            wp_redirect(add_query_arg('message', 'domain_updated', admin_url('admin.php?page=affcd-domains')));
        } else {
            wp_redirect(add_query_arg('message', 'domain_update_failed', wp_get_referer()));
        }
        
        exit;
    }

    /**
     * Process delete domain
     */
    private function process_delete_domain() {
        $domain_id = absint($_POST['domain_id']);
        
        $result = $this->database_manager->delete_authorized_domain($domain_id);
        
        if ($result) {
            wp_redirect(add_query_arg('message', 'domain_deleted', admin_url('admin.php?page=affcd-domains')));
        } else {
            wp_redirect(add_query_arg('message', 'domain_delete_failed', wp_get_referer()));
        }
        
        exit;
    }

    /**
     * Process test domain
     */
    private function process_test_domain() {
        $domain = sanitize_text_field($_POST['domain']);
        
        $result = $this->database_manager->test_domain_connection($domain);
        
        if ($result) {
            wp_send_json_success(['message' => __('Domain connection test successful.', 'affiliate-cross-domain-full')]);
        } else {
            wp_send_json_error(['message' => __('Domain connection test failed.', 'affiliate-cross-domain-full')]);
        }
    }
}

/**
 * Helper function to format currency
 *
 * @param float $amount
 * @param string $currency
 * @return string
 */
function affcd_format_currency($amount, $currency = null) {
    if ($currency === null) {
        $settings = get_option('affcd_settings', []);
        $currency = isset($settings['default_currency']) ? $settings['default_currency'] : 'GBP';
    }

    $symbols = [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
        'CAD' => 'C$',
        'AUD' => 'A$'
    ];

    $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency . ' ';
    
    return $symbol . number_format($amount, 2);
}

/**
 * Helper function to get system health status
 *
 * @return array
 */
function affcd_get_health_status() {
    $health_checks = [];

    // Database connection check
    global $wpdb;
    $health_checks['database'] = $wpdb->check_connection() !== false;

    // API endpoint check
    $api_response = wp_remote_get(site_url('/wp-json/affcd/v1/health'));
    $health_checks['api_endpoint'] = !is_wp_error($api_response) && wp_remote_retrieve_response_code($api_response) === 200;

    // Webhook system check
    $webhook_handler = new AFFCD_Webhook_Handler();
    $health_checks['webhooks'] = $webhook_handler->test_webhook_system();

    // Cache system check
    $cache_test_key = 'affcd_cache_test_' . time();
    wp_cache_set($cache_test_key, 'test_value', '', 60);
    $health_checks['cache'] = wp_cache_get($cache_test_key) === 'test_value';
    wp_cache_delete($cache_test_key);

    // File permissions check
    $upload_dir = wp_upload_dir();
    $health_checks['file_permissions'] = is_writable($upload_dir['basedir']);

    // SSL check
    $health_checks['ssl'] = is_ssl() || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Memory check
    $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
    $memory_usage = memory_get_usage(true);
    $health_checks['memory'] = $memory_usage < ($memory_limit * 0.8); // Less than 80% usage

    return $health_checks;
}

/**
 * Helper function to get detailed health status with descriptions
 *
 * @return array
 */
function affcd_get_detailed_health_status() {
    $basic_health = affcd_get_health_status();
    $detailed_health = [];

    foreach ($basic_health as $check_name => $is_healthy) {
        $check_info = [
            'status' => $is_healthy ? 'ok' : 'error',
            'title' => '',
            'description' => '',
            'actions' => []
        ];

        switch ($check_name) {
            case 'database':
                $check_info['title'] = __('Database Connection', 'affiliate-cross-domain-full');
                $check_info['description'] = $is_healthy 
                    ? __('Database connection is working properly.', 'affiliate-cross-domain-full')
                    : __('Database connection failed. Check your database configuration.', 'affiliate-cross-domain-full');
                break;

            case 'api_endpoint':
                $check_info['title'] = __('API Endpoint', 'affiliate-cross-domain-full');
                $check_info['description'] = $is_healthy 
                    ? __('API endpoint is responding correctly.', 'affiliate-cross-domain-full')
                    : __('API endpoint is not responding. Check permalink settings.', 'affiliate-cross-domain-full');
                if (!$is_healthy) {
                    $check_info['actions'][] = [
                        'label' => __('Check Permalinks', 'affiliate-cross-domain-full'),
                        'url' => admin_url('options-permalink.php'),
                        'type' => 'primary'
                    ];
                }
                break;

            case 'webhooks':
                $check_info['title'] = __('Webhook System', 'affiliate-cross-domain-full');
                $check_info['description'] = $is_healthy 
                    ? __('Webhook system is functioning correctly.', 'affiliate-cross-domain-full')
                    : __('Webhook system has issues. Check webhook configuration.', 'affiliate-cross-domain-full');
                if (!$is_healthy) {
                    $check_info['status'] = 'warning';
                    $check_info['actions'][] = [
                        'label' => __('Manage Webhooks', 'affiliate-cross-domain-full'),
                        'url' => admin_url('admin.php?page=affcd-webhooks'),
                        'type' => 'secondary'
                    ];
                }
                break;

            case 'cache':
                $check_info['title'] = __('Cache System', 'affiliate-cross-domain-full');
                $check_info['description'] = $is_healthy 
                    ? __('Cache system is working properly.', 'affiliate-cross-domain-full')
                    : __('Cache system is not functioning. Performance may be affected.', 'affiliate-cross-domain-full');
                if (!$is_healthy) {
                    $check_info['status'] = 'warning';
                }
                break;

            case 'file_permissions':
                $check_info['title'] = __('File Permissions', 'affiliate-cross-domain-full');
                $check_info['description'] = $is_healthy 
                    ? __('File permissions are correctly configured.', 'affiliate-cross-domain-full')
                    : __('Upload directory is not writable. File operations may fail.', 'affiliate-cross-domain-full');
                break;

            case 'ssl':
                $check_info['title'] = __('SSL/HTTPS', 'affiliate-cross-domain-full');
                $check_info['description'] = $is_healthy 
                    ? __('Site is properly configured for HTTPS.', 'affiliate-cross-domain-full')
                    : __('Site is not using HTTPS. API security may be compromised.', 'affiliate-cross-domain-full');
                if (!$is_healthy) {
                    $check_info['status'] = 'warning';
                }
                break;

            case 'memory':
                $check_info['title'] = __('Memory Usage', 'affiliate-cross-domain-full');
                $check_info['description'] = $is_healthy 
                    ? __('Memory usage is within acceptable limits.', 'affiliate-cross-domain-full')
                    : __('Memory usage is high. Consider increasing memory limit.', 'affiliate-cross-domain-full');
                if (!$is_healthy) {
                    $check_info['status'] = 'warning';
                }
                break;
        }

        $detailed_health[$check_name] = $check_info;
    }

    return $detailed_health;
}

/**
 * Helper function to log admin actions
 *
 * @param string $action
 * @param string $description
 * @param array $data
 */
function affcd_log_admin_action($action, $description, $data = []) {
    $log_entry = [
        'timestamp' => current_time('mysql'),
        'user_id' => get_current_user_id(),
        'user_name' => wp_get_current_user()->display_name,
        'action' => $action,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'data' => wp_json_encode($data)
    ];

    // Log to database
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'affcd_admin_logs',
        $log_entry,
        ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    // Log to file if debug mode is enabled
    $settings = get_option('affcd_settings', []);
    if (!empty($settings['debug_mode'])) {
        error_log('[AFFCD Admin] ' . $description . ' by ' . $log_entry['user_name'] . ' (' . $log_entry['ip_address'] . ')');
    }
}

/**
 * Helper function to validate domain format
 *
 * @param string $domain
 * @return bool
 */
function affcd_validate_domain($domain) {
    // Remove protocol if present
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    
    // Remove trailing slash
    $domain = rtrim($domain, '/');
    
    // Check if domain is valid
    return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
}

/**
 * Helper function to sanitize and validate API key
 *
 * @param string $api_key
 * @return string|false
 */
function affcd_validate_api_key($api_key) {
    $api_key = sanitize_text_field($api_key);
    
    // API key should be alphanumeric with underscores, 32-64 characters
    if (preg_match('/^[a-zA-Z0-9_]{32,64}$/', $api_key)) {
        return $api_key;
    }
    
    return false;
}

/**
 * Helper function to generate secure API key
 *
 * @return string
 */
function affcd_generate_api_key() {
    return 'affcd_' . wp_generate_password(32, false, false);
}

/**
 * Helper function to get plugin version
 *
 * @return string
 */
function affcd_get_plugin_version() {
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $plugin_data = get_plugin_data(AFFCD_PLUGIN_FILE);
    return $plugin_data['Version'] ?? '1.0.0';
}

/**
 * Helper function to check if AffiliateWP is active
 *
 * @return bool
 */
function affcd_is_affiliatewp_active() {
    return function_exists('affiliate_wp') && class_exists('Affiliate_WP');
}

/**
 * Helper function to get admin notice HTML
 *
 * @param string $message
 * @param string $type
 * @return string
 */
function affcd_get_admin_notice($message, $type = 'success') {
    $allowed_types = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $allowed_types)) {
        $type = 'info';
    }
    
    return sprintf(
        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
        esc_attr($type),
        esc_html($message)
    );
}

/**
 * Helper function to display admin notices
 */
function affcd_display_admin_notices() {
    if (!current_user_can('manage_affiliates')) {
        return;
    }

    $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
    
    $notices = [
        'code_added' => [
            'message' => __('Vanity code added successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'code_updated' => [
            'message' => __('Vanity code updated successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'code_deleted' => [
            'message' => __('Vanity code deleted successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'domain_added' => [
            'message' => __('Domain added successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'domain_updated' => [
            'message' => __('Domain updated successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'domain_deleted' => [
            'message' => __('Domain deleted successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'connection_success' => [
            'message' => __('Domain connection test successful.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'connection_failed' => [
            'message' => __('Domain connection test failed.', 'affiliate-cross-domain-full'),
            'type' => 'error'
        ],
        'sync_success' => [
            'message' => __('Codes synchronised successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'sync_failed' => [
            'message' => __('Code synchronisation failed.', 'affiliate-cross-domain-full'),
            'type' => 'error'
        ],
        'cache_cleared' => [
            'message' => __('Cache cleared successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ],
        'export_failed' => [
            'message' => __('Export failed. Please try again.', 'affiliate-cross-domain-full'),
            'type' => 'error'
        ],
        'settings_saved' => [
            'message' => __('Settings saved successfully.', 'affiliate-cross-domain-full'),
            'type' => 'success'
        ]
    ];

    if (isset($notices[$message])) {
        echo affcd_get_admin_notice($notices[$message]['message'], $notices[$message]['type']);
    }
}

// Hook to display admin notices
add_action('admin_notices', 'affcd_display_admin_notices');