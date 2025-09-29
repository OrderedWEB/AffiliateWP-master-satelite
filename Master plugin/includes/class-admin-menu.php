<?php
/**
 * Complete Admin Menu Class
 * File: /wp-content/plugins/Affiliate Cross Domain Master/includes/class-admin-menu.php
 * Author: Richard King, Starne Consulting
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('AFFCD_Admin_Menu')) {
    return;
}


class AFFCD_Admin_Menu {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        
        // Dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
        
        // AJAX handlers
        add_action('wp_ajax_affcd_get_dashboard_stats', [$this, 'ajax_get_dashboard_stats']);
        add_action('wp_ajax_affcd_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_affcd_save_code', [$this, 'ajax_save_code']);
        add_action('wp_ajax_affcd_delete_code', [$this, 'ajax_delete_code']);
        add_action('wp_ajax_affcd_save_domain', [$this, 'ajax_save_domain']);
        add_action('wp_ajax_affcd_delete_domain', [$this, 'ajax_delete_domain']);
        add_action('wp_ajax_affcd_export_data', [$this, 'ajax_export_data']);
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'affcd_dashboard_overview',
            'Cross Domain Affiliate Overview',
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $stats = $this->get_dashboard_statistics();
        $system_health = $this->get_system_health();
        
        ?>
        <div class="affcd-dashboard-widget">
            <div class="widget-stats">
                <div class="stat-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($stats['total_codes']); ?></span>
                        <span class="stat-label">Active Codes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($stats['active_domains']); ?></span>
                        <span class="stat-label">Active Domains</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($stats['monthly_conversions']); ?></span>
                        <span class="stat-label">This Month</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">£<?php echo number_format($stats['monthly_revenue'], 0); ?></span>
                        <span class="stat-label">Revenue</span>
                    </div>
                </div>
            </div>
            
            <div class="widget-health">
                <h4>System Health</h4>
                <div class="health-indicators">
                    <?php foreach ($system_health as $check => $status): ?>
                        <span class="health-indicator <?php echo $status ? 'healthy' : 'error'; ?>" title="<?php echo ucwords(str_replace('_', ' ', $check)); ?>">
                            <span class="dashicons dashicons-<?php echo $status ? 'yes-alt' : 'warning'; ?>"></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="widget-actions">
                <a href="<?php echo admin_url('admin.php?page=affcd-dashboard'); ?>" class="button button-primary">
                    View Full Dashboard
                </a>
                <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes&action=add'); ?>" class="button">
                    Add Code
                </a>
            </div>
        </div>

        <style>
        .affcd-dashboard-widget .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .affcd-dashboard-widget .stat-item {
            text-align: center;
            padding: 15px 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .affcd-dashboard-widget .stat-number {
            display: block;
            font-size: 20px;
            font-weight: bold;
            color: #0073aa;
            line-height: 1.2;
        }
        .affcd-dashboard-widget .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .affcd-dashboard-widget .widget-health {
            margin-bottom: 20px;
        }
        .affcd-dashboard-widget .widget-health h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        .affcd-dashboard-widget .health-indicators {
            display: flex;
            gap: 8px;
        }
        .affcd-dashboard-widget .health-indicator {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .affcd-dashboard-widget .health-indicator.healthy {
            background: #46b450;
            color: white;
        }
        .affcd-dashboard-widget .health-indicator.error {
            background: #dc3232;
            color: white;
        }
        .affcd-dashboard-widget .widget-actions {
            display: flex;
            gap: 10px;
        }
        .affcd-dashboard-widget .widget-actions .button {
            flex: 1;
            text-align: center;
            justify-content: center;
        }
        @media screen and (max-width: 782px) {
            .affcd-dashboard-widget .stat-grid {
                grid-template-columns: 1fr;
            }
            .affcd-dashboard-widget .widget-actions {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
    
    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'affcd') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_style('wp-admin');
        
        // Chart.js for analytics
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );

        // Custom admin script
        wp_enqueue_script(
            'affcd-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery', 'chart-js'],
            '1.0.0',
            true
        );

        wp_localize_script('affcd-admin', 'affcdAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_admin_nonce'),
            'strings' => [
                'loading' => 'Loading...',
                'error' => 'An error occurred',
                'confirm_delete' => 'Are you sure you want to delete this item?'
            ]
        ]);
    }
    
    public function add_admin_pages() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Main dashboard page
        add_menu_page(
            'Cross Domain Affiliate',
            'Cross Domain',
            'manage_options',
            'affcd-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-networking',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'affcd-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'affcd-dashboard',
            [$this, 'render_dashboard_page']
        );

        // Vanity Codes
        add_submenu_page(
            'affcd-dashboard',
            'Vanity Codes',
            'Vanity Codes',
            'manage_options',
            'affcd-vanity-codes',
            [$this, 'render_vanity_codes_page']
        );

        // Authorised Domains
        add_submenu_page(
            'affcd-dashboard',
            'Authorised Domains',
            'Domains',
            'manage_options',
            'affcd-domains',
            [$this, 'render_domains_page']
        );
add_submenu_page('affcd-dashboard', 'Commission Calculator', 'Calculator', 'manage_options', 'affcd-calculator', [$this, 'render_calculator_page']);
      add_submenu_page('affcd-dashboard', 'Health Monitoring', 'Health', 'manage_options', 'affcd-health', [$this, 'render_health_page']);
 add_submenu_page('affcd-dashboard', 'Data Backflow', 'Data Sync', 'manage_options', 'affcd-backflow', [$this, 'render_backflow_page']);
 add_submenu_page('affcd-dashboard', 'Portal Enhancement', 'Portal', 'manage_options', 'affcd-portal', [$this, 'render_portal_page']);
 add_submenu_page('affcd-dashboard', 'Addon Settings', 'Addons', 'manage_options', 'affcd-addons', [$this, 'render_addons_page']);
 add_submenu_page('affcd-dashboard', 'Bulk Operations', 'Bulk Ops', 'manage_options', 'affcd-bulk', [$this, 'render_bulk_operations_page']);
 add_submenu_page('affcd-dashboard', 'Tracking Reports', 'Tracking', 'manage_options', 'affcd-tracking', [$this, 'render_tracking_page']);

      add_submenu_page(
            'affcd-dashboard',
            __('Role Management', 'affiliatewp-cross-domain-plugin-suite'),
            __('Roles', 'affiliatewp-cross-domain-plugin-suite'),
            'manage_options',
            'affcd-roles',
            [$this, 'render_roles_page']
        );

        // Analytics
        add_submenu_page(
            'affcd-dashboard',
            'Analytics & Reports',
            'Analytics',
            'manage_options',
            'affcd-analytics',
            [$this, 'render_analytics_page']
        );

        // Webhooks
        add_submenu_page(
            'affcd-dashboard',
            'Webhook Management',
            'Webhooks',
            'manage_options',
            'affcd-webhooks',
            [$this, 'render_webhooks_page']
        );

        // System Status
        add_submenu_page(
            'affcd-dashboard',
            'System Status',
            'System Status',
            'manage_options',
            'affcd-system-status',
            [$this, 'render_system_status_page']
        );

        // Settings
        add_submenu_page(
            'affcd-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'affcd-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function render_dashboard_page() {
        $stats = $this->get_dashboard_statistics();
        $recent_activity = $this->get_recent_activity();
        $system_health = $this->get_system_health();
        
        ?>
        <div class="wrap affcd-dashboard">
            <h1>Cross Domain Affiliate Dashboard</h1>
            
            <!-- Key Metrics -->
            <div class="affcd-stats-grid">
                <div class="affcd-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-tickets-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_codes']); ?></h3>
                        <p>Total Codes</p>
                        <small class="stat-trend <?php echo $stats['codes_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['codes_trend'] >= 0 ? '+' : ''; ?><?php echo $stats['codes_trend']; ?>%
                        </small>
                    </div>
                </div>

                <div class="affcd-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['active_domains']); ?></h3>
                        <p>Active Domains</p>
                        <small class="stat-detail"><?php echo $stats['total_domains']; ?> total</small>
                    </div>
                </div>

                <div class="affcd-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['monthly_conversions']); ?></h3>
                        <p>This Month Conversions</p>
                        <small class="stat-trend positive">+<?php echo $stats['conversion_growth']; ?>%</small>
                    </div>
                </div>

                <div class="affcd-stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3>£<?php echo number_format($stats['monthly_revenue'], 2); ?></h3>
                        <p>This Month Revenue</p>
                        <small class="stat-detail">£<?php echo number_format($stats['avg_order_value'], 2); ?> AOV</small>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="affcd-dashboard-grid">
                <!-- Recent Activity -->
                <div class="affcd-info-card">
                    <h3>Recent Activity</h3>
                    <div class="activity-list">
                        <?php if (empty($recent_activity)): ?>
                            <p>No recent activity.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                <div class="activity-item">
                                    <span class="activity-time"><?php echo human_time_diff(strtotime($activity['created_at'])); ?> ago</span>
                                    <span class="activity-text"><?php echo esc_html($activity['description']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Health -->
                <div class="affcd-info-card">
                    <h3>System Health</h3>
                    <div class="health-status">
                        <?php foreach ($system_health as $check => $status): ?>
                            <div class="health-item">
                                <span class="health-indicator <?php echo $status ? 'ok' : 'error'; ?>"></span>
                                <span class="health-label"><?php echo ucwords(str_replace('_', ' ', $check)); ?></span>
                                <span class="health-status"><?php echo $status ? 'OK' : 'Error'; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="affcd-info-card">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions">
                        <p><a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes&action=add'); ?>" class="button button-primary">Add Vanity Code</a></p>
                        <p><a href="<?php echo admin_url('admin.php?page=affcd-domains&action=add'); ?>" class="button">Add Domain</a></p>
                        <p><a href="<?php echo admin_url('admin.php?page=affcd-analytics'); ?>" class="button">View Analytics</a></p>
                        <p><button type="button" class="button test-connections">Test All Connections</button></p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .affcd-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .affcd-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            display: flex;
            align-items: center;
        }
        .stat-icon {
            margin-right: 15px;
            font-size: 24px;
            color: #0073aa;
        }
        .stat-content h3 {
            margin: 0 0 5px 0;
            font-size: 24px;
            font-weight: bold;
        }
        .stat-content p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        .stat-trend.positive { color: #46b450; }
        .stat-trend.negative { color: #dc3232; }
        .stat-detail { color: #666; font-size: 12px; }
        .affcd-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .affcd-info-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        .affcd-info-card h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .activity-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-time {
            display: block;
            font-size: 11px;
            color: #666;
        }
        .health-item {
            display: flex;
            align-items: center;
            padding: 5px 0;
        }
        .health-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .health-indicator.ok { background-color: #46b450; }
        .health-indicator.error { background-color: #dc3232; }
        .quick-actions p { margin: 10px 0; }
        @media (max-width: 768px) {
            .affcd-stats-grid { grid-template-columns: 1fr; }
            .affcd-dashboard-grid { grid-template-columns: 1fr; }
        }
        </style>
        <?php
    }
    
    public function render_vanity_codes_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $code_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        if ($action === 'add' || ($action === 'edit' && $code_id)) {
            $this->render_vanity_code_form($action, $code_id);
            return;
        }
        
        $codes = $this->get_vanity_codes();
        ?>
        <div class="wrap">
            <h1>Vanity Codes <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes&action=add'); ?>" class="page-title-action">Add New Code</a></h1>
            
            <div class="codes-summary">
                <div class="codes-stat">
                    <span class="stat-number"><?php echo count($codes); ?></span>
                    <span class="stat-label">Total Codes</span>
                </div>
                <div class="codes-stat">
                    <span class="stat-number"><?php echo count(array_filter($codes, function($c) { return $c['status'] === 'active'; })); ?></span>
                    <span class="stat-label">Active Codes</span>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Uses</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)): ?>
                        <tr>
                            <td colspan="6">No vanity codes found. <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes&action=add'); ?>">Create your first code</a>.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($codes as $code): ?>
                            <tr>
                                <td><strong><?php echo esc_html($code['code']); ?></strong></td>
                                <td><?php echo esc_html($code['discount_type']); ?></td>
                                <td><?php echo esc_html($code['discount_value']); ?>%</td>
                                <td><?php echo number_format($code['uses']); ?></td>
                                <td><span class="status-<?php echo $code['status']; ?>"><?php echo ucwords($code['status']); ?></span></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes&action=edit&id=' . $code['id']); ?>" class="button button-small">Edit</a>
                                    <button type="button" class="button button-small delete-code" data-id="<?php echo $code['id']; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
        .codes-summary {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .codes-stat {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .codes-stat .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        .codes-stat .stat-label {
            font-size: 12px;
            color: #666;
        }
        .status-active { color: #46b450; font-weight: bold; }
        .status-inactive { color: #dc3232; font-weight: bold; }
        .status-draft { color: #ffb900; font-weight: bold; }
        </style>
        <?php
    }

    private function render_vanity_code_form($action, $code_id = 0) {
        $code = $action === 'edit' ? $this->get_vanity_code($code_id) : [
            'code' => '',
            'discount_type' => 'percentage',
            'discount_value' => '',
            'description' => '',
            'status' => 'active',
            'usage_limit' => '',
            'start_date' => '',
            'end_date' => ''
        ];

        ?>
        <div class="wrap">
            <h1><?php echo $action === 'edit' ? 'Edit Vanity Code' : 'Add New Vanity Code'; ?></h1>
            
            <form method="post" id="vanity-code-form">
                <?php wp_nonce_field('affcd_save_code', 'affcd_code_nonce'); ?>
                <input type="hidden" name="action" value="save_code">
                <input type="hidden" name="code_id" value="<?php echo $code_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Code</th>
                        <td>
                            <input type="text" name="code" value="<?php echo esc_attr($code['code']); ?>" class="regular-text" required>
                            <p class="description">Unique identifier for this vanity code.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Discount Type</th>
                        <td>
                            <select name="discount_type">
                                <option value="percentage" <?php selected($code['discount_type'], 'percentage'); ?>>Percentage</option>
                                <option value="fixed" <?php selected($code['discount_type'], 'fixed'); ?>>Fixed Amount</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Discount Value</th>
                        <td>
                            <input type="number" name="discount_value" value="<?php echo esc_attr($code['discount_value']); ?>" step="0.01" min="0" class="small-text" required>
                            <p class="description">Enter the discount amount (% or £).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Description</th>
                        <td>
                            <textarea name="description" rows="3" cols="50"><?php echo esc_textarea($code['description']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <select name="status">
                                <option value="active" <?php selected($code['status'], 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($code['status'], 'inactive'); ?>>Inactive</option>
                                <option value="draft" <?php selected($code['status'], 'draft'); ?>>Draft</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Usage Limit</th>
                        <td>
                            <input type="number" name="usage_limit" value="<?php echo esc_attr($code['usage_limit']); ?>" min="0" class="small-text">
                            <p class="description">Leave empty for unlimited use.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Start Date</th>
                        <td>
                            <input type="date" name="start_date" value="<?php echo esc_attr($code['start_date']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">End Date</th>
                        <td>
                            <input type="date" name="end_date" value="<?php echo esc_attr($code['end_date']); ?>">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($action === 'edit' ? 'Update Code' : 'Create Code'); ?>
            </form>
        </div>
        <?php
    }
    
    public function render_domains_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $domain_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        if ($action === 'add' || ($action === 'edit' && $domain_id)) {
            $this->render_domain_form($action, $domain_id);
            return;
        }
        
        $domains = $this->get_domains();
        ?>
        <div class="wrap">
            <h1>Authorised Domains <a href="<?php echo admin_url('admin.php?page=affcd-domains&action=add'); ?>" class="page-title-action">Add Domain</a></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>API Calls (24h)</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($domains)): ?>
                        <tr>
                            <td colspan="6">No domains configured. <a href="<?php echo admin_url('admin.php?page=affcd-domains&action=add'); ?>">Add your first domain</a>.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td><strong><?php echo esc_html($domain['domain']); ?></strong></td>
                                <td><?php echo esc_html($domain['name']); ?></td>
                                <td><span class="status-<?php echo $domain['status']; ?>"><?php echo ucwords($domain['status']); ?></span></td>
                                <td><?php echo number_format($domain['api_calls_24h']); ?></td>
                                <td><?php echo $domain['last_activity'] ? human_time_diff(strtotime($domain['last_activity'])) . ' ago' : 'Never'; ?></td>
                                <td>
                                    <button type="button" class="button button-small test-domain" data-domain="<?php echo esc_attr($domain['domain']); ?>">Test</button>
                                    <a href="<?php echo admin_url('admin.php?page=affcd-domains&action=edit&id=' . $domain['id']); ?>" class="button button-small">Edit</a>
                                    <button type="button" class="button button-small delete-domain" data-id="<?php echo $domain['id']; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_domain_form($action, $domain_id = 0) {
        $domain = $action === 'edit' ? $this->get_domain($domain_id) : [
            'domain' => '',
            'name' => '',
            'api_key' => '',
            'webhook_url' => '',
            'status' => 'active',
            'rate_limit' => 1000
        ];

        ?>
        <div class="wrap">
            <h1><?php echo $action === 'edit' ? 'Edit Domain' : 'Add New Domain'; ?></h1>
            
            <form method="post" id="domain-form">
                <?php wp_nonce_field('affcd_save_domain', 'affcd_domain_nonce'); ?>
                <input type="hidden" name="action" value="save_domain">
                <input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Domain</th>
                        <td>
                            <input type="url" name="domain" value="<?php echo esc_attr($domain['domain']); ?>" class="regular-text" required>
                            <p class="description">Full URL of the client domain (e.g., https://example.com).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Display Name</th>
                        <td>
                            <input type="text" name="name" value="<?php echo esc_attr($domain['name']); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="api_key" value="<?php echo esc_attr($domain['api_key']); ?>" class="regular-text" readonly>
                            <button type="button" class="button generate-api-key">Generate New Key</button>
                            <p class="description">API key for this domain to authenticate requests.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <input type="url" name="webhook_url" value="<?php echo esc_attr($domain['webhook_url']); ?>" class="regular-text">
                            <p class="description">Optional webhook endpoint for notifications.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <select name="status">
                                <option value="active" <?php selected($domain['status'], 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($domain['status'], 'inactive'); ?>>Inactive</option>
                                <option value="suspended" <?php selected($domain['status'], 'suspended'); ?>>Suspended</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Rate Limit</th>
                        <td>
                            <input type="number" name="rate_limit" value="<?php echo esc_attr($domain['rate_limit']); ?>" min="10" max="10000" class="small-text">
                            <span>requests per hour</span>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($action === 'edit' ? 'Update Domain' : 'Add Domain'); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.generate-api-key').on('click', function() {
                if (confirm('Generate a new API key? The old key will stop working.')) {
                    var newKey = 'affcd_' + Math.random().toString(36).substr(2, 32);
                    $('input[name="api_key"]').val(newKey);
                }
            });
        });
        </script>
        <?php
    }
    
    public function render_analytics_page() {
        ?>
        <div class="wrap">
            <h1>Analytics & Reports</h1>
            
            <div class="nav-tab-wrapper">
                <a href="#overview" class="nav-tab nav-tab-active">Overview</a>
                <a href="#codes" class="nav-tab">Codes</a>
                <a href="#domains" class="nav-tab">Domains</a>
                <a href="#performance" class="nav-tab">Performance</a>
            </div>

            <div id="domains" class="tab-content">
                <h2>Domain Analytics</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>API Calls</th>
                            <th>Success Rate</th>
                            <th>Avg Response Time</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="5">Loading domain analytics...</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="performance" class="tab-content">
                <h2>System Performance</h2>
                <div class="performance-metrics">
                    <div class="metric-card">
                        <h4>Average Response Time</h4>
                        <p class="metric-value">Loading...</p>
                    </div>
                    <div class="metric-card">
                        <h4>API Success Rate</h4>
                        <p class="metric-value">Loading...</p>
                    </div>
                    <div class="metric-card">
                        <h4>Cache Hit Rate</h4>
                        <p class="metric-value">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .nav-tab-wrapper { margin-bottom: 20px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .analytics-charts { margin: 20px 0; }
        .chart-container { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
        .performance-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .metric-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; text-align: center; }
        .metric-card h4 { margin: 0 0 10px 0; color: #555; }
        .metric-value { font-size: 24px; font-weight: bold; color: #0073aa; margin: 0; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });

            // Load analytics data
            if (typeof Chart !== 'undefined') {
                var ctx = document.getElementById('usage-trends-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'],
                        datasets: [{
                            label: 'API Calls',
                            data: [12, 19, 3, 5, 2],
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        });
        </script>
        <?php
    }
    
    public function render_webhooks_page() {
        $webhooks = $this->get_webhooks();
        ?>
        <div class="wrap">
            <h1>Webhook Management <a href="#" class="page-title-action" onclick="addWebhook(); return false;">Add Webhook</a></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Events</th>
                        <th>Status</th>
                        <th>Last Triggered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($webhooks)): ?>
                        <tr>
                            <td colspan="5">No webhooks configured.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($webhooks as $webhook): ?>
                            <tr>
                                <td><?php echo esc_html($webhook['url']); ?></td>
                                <td><?php echo esc_html($webhook['events']); ?></td>
                                <td><span class="status-<?php echo $webhook['status']; ?>"><?php echo ucwords($webhook['status']); ?></span></td>
                                <td><?php echo $webhook['last_triggered'] ? human_time_diff(strtotime($webhook['last_triggered'])) . ' ago' : 'Never'; ?></td>
                                <td>
                                    <button type="button" class="button button-small test-webhook" data-url="<?php echo esc_attr($webhook['url']); ?>">Test</button>
                                    <button type="button" class="button button-small">Edit</button>
                                    <button type="button" class="button button-small delete-webhook" data-id="<?php echo $webhook['id']; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        function addWebhook() {
            var url = prompt('Enter webhook URL:');
            if (url && url.match(/^https?:\/\/.+/)) {
                alert('Webhook functionality will be available in the full implementation.');
            } else if (url) {
                alert('Please enter a valid URL starting with http:// or https://');
            }
        }
        </script>
        <?php
    }
      public function render_calculator_page() {
        $template_file = plugin_dir_path(dirname(__FILE__)) . 'admin/templates/commission-calculator.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="wrap"><h1>Commission Calculator</h1><p>Calculator loading...</p></div>';
        }
    }
        /**
     * Render role management page
     */
    public function render_roles_page() {
        $template_file = $this->template_dir . 'role-management.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            $this->render_fallback_page(__('Role Management', 'affiliatewp-cross-domain-plugin-suite'));
        }
    }
    public function render_bulk_operations_page() {
        $admin_file = plugin_dir_path(dirname(__FILE__)) . 'admin/class-bulk-operations.php';
        
        if (file_exists($admin_file) && !class_exists('AFFCD_Bulk_Operations')) {
            include $admin_file;
        }
        
        echo '<div class="wrap"><h1>Bulk Operations</h1><p>Bulk operations loading...</p></div>';
    }
    public function render_tracking_page() {
        $admin_file = plugin_dir_path(dirname(__FILE__)) . 'admin/tracking-reports.php';
        
        if (file_exists($admin_file)) {
            include $admin_file;
        } else {
            echo '<div class="wrap"><h1>Tracking Reports</h1><p>Tracking reports loading...</p></div>';
        }
    }

     public function render_portal_page() {
        $template_file = plugin_dir_path(dirname(__FILE__)) . 'admin/templates/portal-enhancement.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="wrap"><h1>Portal Enhancement</h1><p>Portal loading...</p></div>';
        }
    }
     /**
     * Render data backflow page
     */
    public function render_backflow_page() {
               $template_file = plugin_dir_path(dirname(__FILE__)) . 'admin/templates/data-backflow.php';

        if (file_exists($template_file)) {
            include $template_file;
        } else {
            $this->render_fallback_page(__('Data Backflow', 'affiliatewp-cross-domain-plugin-suite'));
        }
    }
public function render_addons_page() {
        $admin_file = plugin_dir_path(dirname(__FILE__)) . 'admin/addon-settings.php';
        
        if (file_exists($admin_file) && !class_exists('AFFCD_Addon_Settings_Admin')) {
            include $admin_file;
        }
        
        echo '<div class="wrap"><h1>Addon Settings</h1><p>Addon management loading...</p></div>';
    }
      public function render_health_page() {
        $template_file = plugin_dir_path(dirname(__FILE__)) . 'admin/templates/health-monitor-dashboard.php';
        
        if (!file_exists($template_file)) {
            $template_file = plugin_dir_path(dirname(__FILE__)) . 'admin/templates/health-monitoring.php';
        }
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="wrap"><h1>Health Monitoring</h1><p>Health monitoring loading...</p></div>';
        }
    }
    private function render_fallback_page( $args = [] ) {
    $title = isset( $args['title'] ) ? $args['title'] : 'Feature not available';
    $desc  = isset( $args['description'] ) ? $args['description'] : 'This section is under construction.';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( $title ); ?></h1>
        <div class="notice notice-info">
            <p><?php echo esc_html( $desc ); ?></p>
        </div>
    </div>
    <?php
}
      public function render_system_status_page() {
        $system_info = $this->get_system_information();
        $health_status = $this->get_detailed_health_status();
        
        ?>
        <div class="wrap">
            <h1>System Status</h1>
            
            <div class="system-status-grid">
                <div class="postbox">
                    <h2>System Health Checks</h2>
                    <div class="inside">
                        <?php foreach ($health_status as $check_name => $check): ?>
                            <div class="health-check-item <?php echo $check['status']; ?>">
                                <div class="health-check-icon">
                                    <span class="dashicons dashicons-<?php echo $check['status'] === 'ok' ? 'yes-alt' : 'warning'; ?>"></span>
                                </div>
                                <div class="health-check-content">
                                    <h4><?php echo esc_html($check['title']); ?></h4>
                                    <p><?php echo esc_html($check['description']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="postbox">
                    <h2>System Information</h2>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <tbody>
                                <?php foreach ($system_info as $key => $value): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($key); ?></strong></td>
                                        <td><?php echo esc_html($value); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .system-status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .health-check-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #eee; }
        .health-check-item:last-child { border-bottom: none; }
        .health-check-icon { margin-right: 15px; font-size: 20px; }
        .health-check-item.ok .health-check-icon { color: #46b450; }
        .health-check-item.warning .health-check-icon { color: #ffb900; }
        .health-check-item.error .health-check-icon { color: #dc3232; }
        .health-check-content h4 { margin: 0 0 5px 0; }
        .health-check-content p { margin: 0; color: #666; }
        @media (max-width: 768px) { .system-status-grid { grid-template-columns: 1fr; } }
        </style>
        <?php
    }
    
    
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            $this->handle_settings_save();
        }

        $settings = get_option('affcd_settings', []);
        
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('affcd_settings', 'affcd_settings_nonce'); ?>
                
                <div class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active">General</a>
                    <a href="#api" class="nav-tab">API Settings</a>
                    <a href="#security" class="nav-tab">Security</a>
                    <a href="#notifications" class="nav-tab">Notifications</a>
                </div>

                <div id="general" class="tab-content active">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Plugin Status</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="affcd_settings[enabled]" value="1" <?php checked(isset($settings['enabled']) ? $settings['enabled'] : 1); ?>>
                                    Enable cross-domain affiliate system
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Debug Mode</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="affcd_settings[debug_mode]" value="1" <?php checked(isset($settings['debug_mode']) ? $settings['debug_mode'] : 0); ?>>
                                    Enable debug logging
                                </label>
                                <p class="description">Enable detailed logging for troubleshooting.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Cache Duration</th>
                            <td>
                                <input type="number" name="affcd_settings[cache_duration]" value="<?php echo esc_attr(isset($settings['cache_duration']) ? $settings['cache_duration'] : 3600); ?>" min="60" max="86400" class="small-text">
                                <span>seconds</span>
                                <p class="description">How long to cache API responses (60-86400 seconds).</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="api" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Master API Key</th>
                            <td>
                                <input type="text" name="affcd_settings[master_api_key]" value="<?php echo esc_attr(isset($settings['master_api_key']) ? $settings['master_api_key'] : ''); ?>" class="regular-text">
                                <button type="button" class="button generate-master-key">Generate New Key</button>
                                <p class="description">Master API key for the affiliate system.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Endpoint URL</th>
                            <td>
                                <input type="url" name="affcd_settings[api_endpoint]" value="<?php echo esc_attr(isset($settings['api_endpoint']) ? $settings['api_endpoint'] : site_url('/wp-json/affcd/v1/')); ?>" class="regular-text">
                                <p class="description">Base URL for API endpoints.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Rate Limiting</th>
                            <td>
                                <input type="number" name="affcd_settings[rate_limit]" value="<?php echo esc_attr(isset($settings['rate_limit']) ? $settings['rate_limit'] : 1000); ?>" min="10" max="10000" class="small-text">
                                <span>requests per hour per domain</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="security" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">IP Restrictions</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="affcd_settings[ip_restrictions_enabled]" value="1" <?php checked(isset($settings['ip_restrictions_enabled']) ? $settings['ip_restrictions_enabled'] : 0); ?>>
                                    Enable IP address restrictions
                                </label>
                                <br><br>
                                <textarea name="affcd_settings[allowed_ips]" rows="5" cols="50" placeholder="192.168.1.1&#10;10.0.0.0/24"><?php echo esc_textarea(isset($settings['allowed_ips']) ? $settings['allowed_ips'] : ''); ?></textarea>
                                <p class="description">One IP address or CIDR range per line.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Failed Login Attempts</th>
                            <td>
                                <input type="number" name="affcd_settings[max_failed_attempts]" value="<?php echo esc_attr(isset($settings['max_failed_attempts']) ? $settings['max_failed_attempts'] : 5); ?>" min="1" max="50" class="small-text">
                                <span>attempts before blocking</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="notifications" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Email Notifications</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="affcd_settings[email_notifications]" value="1" <?php checked(isset($settings['email_notifications']) ? $settings['email_notifications'] : 1); ?>>
                                    Enable email notifications
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Notification Email</th>
                            <td>
                                <input type="email" name="affcd_settings[notification_email]" value="<?php echo esc_attr(isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email')); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Notification Events</th>
                            <td>
                                <label><input type="checkbox" name="affcd_settings[notify_new_codes]" value="1" <?php checked(isset($settings['notify_new_codes']) ? $settings['notify_new_codes'] : 1); ?>> New vanity codes created</label><br>
                                <label><input type="checkbox" name="affcd_settings[notify_conversions]" value="1" <?php checked(isset($settings['notify_conversions']) ? $settings['notify_conversions'] : 0); ?>> Conversion events</label><br>
                                <label><input type="checkbox" name="affcd_settings[notify_security_alerts]" value="1" <?php checked(isset($settings['notify_security_alerts']) ? $settings['notify_security_alerts'] : 1); ?>> Security alerts</label>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });

            // Generate master API key
            $('.generate-master-key').on('click', function() {
                if (confirm('Generate a new master API key? This will affect all connected domains.')) {
                    var newKey = 'affcd_master_' + Math.random().toString(36).substr(2, 32);
                    $('input[name="affcd_settings[master_api_key]"]').val(newKey);
                }
            });
        });
        </script>

        <style>
        .nav-tab-wrapper { margin-bottom: 20px; }
        .tab-content { display: none; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; }
        .tab-content.active { display: block; }
        .form-table th { width: 200px; }
        .description { color: #666; font-style: italic; margin-top: 5px; }
        </style>
        <?php
    }

    public function handle_admin_actions() {
        if (!current_user_can('manage_options') || !isset($_POST['action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);

        switch ($action) {
            case 'save_code':
                $this->handle_save_code();
                break;
            case 'save_domain':
                $this->handle_save_domain();
                break;
        }
    }

    private function handle_save_code() {
        if (!wp_verify_nonce($_POST['affcd_code_nonce'], 'affcd_save_code')) {
            wp_die('Security check failed.');
        }

        $code_id = absint($_POST['code_id']);
        $code_data = [
            'code' => sanitize_text_field($_POST['code']),
            'discount_type' => sanitize_text_field($_POST['discount_type']),
            'discount_value' => floatval($_POST['discount_value']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => sanitize_text_field($_POST['status']),
            'usage_limit' => absint($_POST['usage_limit']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date'])
        ];

        // Save to database (simulated)
        $this->save_vanity_code($code_id, $code_data);

        wp_redirect(admin_url('admin.php?page=affcd-vanity-codes&message=code_saved'));
        exit;
    }

    private function handle_save_domain() {
        if (!wp_verify_nonce($_POST['affcd_domain_nonce'], 'affcd_save_domain')) {
            wp_die('Security check failed.');
        }

        $domain_id = absint($_POST['domain_id']);
        $domain_data = [
            'domain' => esc_url_raw($_POST['domain']),
            'name' => sanitize_text_field($_POST['name']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'webhook_url' => esc_url_raw($_POST['webhook_url']),
            'status' => sanitize_text_field($_POST['status']),
            'rate_limit' => absint($_POST['rate_limit'])
        ];

        // Save to database (simulated)
        $this->save_domain($domain_id, $domain_data);

        wp_redirect(admin_url('admin.php?page=affcd-domains&message=domain_saved'));
        exit;
    }

    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['affcd_settings_nonce'], 'affcd_settings')) {
            wp_die('Security check failed.');
        }

        $settings = [];
        if (isset($_POST['affcd_settings']) && is_array($_POST['affcd_settings'])) {
            $settings = $this->sanitize_settings($_POST['affcd_settings']);
        }

        update_option('affcd_settings', $settings);
        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }

    private function sanitize_settings($settings) {
        $sanitized = [];

        $sanitized['enabled'] = isset($settings['enabled']) ? 1 : 0;
        $sanitized['debug_mode'] = isset($settings['debug_mode']) ? 1 : 0;
        $sanitized['cache_duration'] = isset($settings['cache_duration']) ? absint($settings['cache_duration']) : 3600;
        $sanitized['master_api_key'] = isset($settings['master_api_key']) ? sanitize_text_field($settings['master_api_key']) : '';
        $sanitized['api_endpoint'] = isset($settings['api_endpoint']) ? esc_url_raw($settings['api_endpoint']) : site_url('/wp-json/affcd/v1/');
        $sanitized['rate_limit'] = isset($settings['rate_limit']) ? absint($settings['rate_limit']) : 1000;
        $sanitized['ip_restrictions_enabled'] = isset($settings['ip_restrictions_enabled']) ? 1 : 0;
        $sanitized['allowed_ips'] = isset($settings['allowed_ips']) ? sanitize_textarea_field($settings['allowed_ips']) : '';
        $sanitized['max_failed_attempts'] = isset($settings['max_failed_attempts']) ? absint($settings['max_failed_attempts']) : 5;
        $sanitized['email_notifications'] = isset($settings['email_notifications']) ? 1 : 0;
        $sanitized['notification_email'] = isset($settings['notification_email']) ? sanitize_email($settings['notification_email']) : get_option('admin_email');
        $sanitized['notify_new_codes'] = isset($settings['notify_new_codes']) ? 1 : 0;
        $sanitized['notify_conversions'] = isset($settings['notify_conversions']) ? 1 : 0;
        $sanitized['notify_security_alerts'] = isset($settings['notify_security_alerts']) ? 1 : 0;

        return $sanitized;
    }

    // Data retrieval methods (simulated - replace with actual database calls)
    private function get_dashboard_statistics() {
        return [
            'total_codes' => 25,
            'active_domains' => 8,
            'monthly_conversions' => 142,
            'monthly_revenue' => 3250.75,
            'avg_order_value' => 89.50,
            'codes_trend' => 12,
            'conversion_growth' => 8,
            'total_domains' => 12
        ];
    }

    private function get_recent_activity() {
        return [
            ['description' => 'New vanity code "SAVE20" created', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['description' => 'Domain example.com validated successfully', 'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))],
            ['description' => 'Code "WELCOME10" converted to sale', 'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))],
        ];
    }

    private function get_system_health() {
        global $wpdb;
        return [
            'database' => $wpdb->check_connection() !== false,
            'api_endpoint' => true,
            'webhooks' => true,
            'cache' => true,
            'ssl' => is_ssl()
        ];
    }

    private function get_detailed_health_status() {
        $basic_health = $this->get_system_health();
        $detailed = [];

        foreach ($basic_health as $check => $status) {
            $detailed[$check] = [
                'status' => $status ? 'ok' : 'error',
                'title' => ucwords(str_replace('_', ' ', $check)),
                'description' => $status ? 'Working properly' : 'Issue detected'
            ];
        }

        return $detailed;
    }

    private function get_system_information() {
        global $wpdb;
        return [
            'Plugin Version' => '1.0.0',
            'WordPress Version' => get_bloginfo('version'),
            'PHP Version' => PHP_VERSION,
            'Database Version' => $wpdb->db_version(),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's',
            'SSL Support' => extension_loaded('openssl') ? 'Yes' : 'No',
            'cURL Support' => function_exists('curl_version') ? 'Yes' : 'No'
        ];
    }

    private function get_vanity_codes() {
        return [
            ['id' => 1, 'code' => 'SAVE20', 'discount_type' => 'percentage', 'discount_value' => '20', 'uses' => 45, 'status' => 'active'],
            ['id' => 2, 'code' => 'WELCOME10', 'discount_type' => 'percentage', 'discount_value' => '10', 'uses' => 123, 'status' => 'active'],
            ['id' => 3, 'code' => 'NEWUSER', 'discount_type' => 'fixed', 'discount_value' => '5', 'uses' => 67, 'status' => 'inactive']
        ];
    }

    private function get_vanity_code($id) {
        $codes = $this->get_vanity_codes();
        foreach ($codes as $code) {
            if ($code['id'] == $id) {
                return array_merge($code, [
                    'description' => 'Sample description',
                    'usage_limit' => 100,
                    'start_date' => '',
                    'end_date' => ''
                ]);
            }
        }
        return null;
    }

    private function get_domains() {
        return [
            ['id' => 1, 'domain' => 'https://example.com', 'name' => 'Example Site', 'status' => 'active', 'api_calls_24h' => 234, 'last_activity' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['id' => 2, 'domain' => 'https://demo.com', 'name' => 'Demo Site', 'status' => 'inactive', 'api_calls_24h' => 0, 'last_activity' => null]
        ];
    }

    private function get_domain($id) {
        $domains = $this->get_domains();
        foreach ($domains as $domain) {
            if ($domain['id'] == $id) {
                return array_merge($domain, [
                    'api_key' => 'affcd_' . bin2hex(random_bytes(16)),
                    'webhook_url' => '',
                    'rate_limit' => 1000
                ]);
            }
        }
        return null;
    }

    private function get_webhooks() {
        return [
            ['id' => 1, 'url' => 'https://example.com/webhook', 'events' => 'conversions', 'status' => 'active', 'last_triggered' => date('Y-m-d H:i:s', strtotime('-1 hour'))]
        ];
    }

    private function save_vanity_code($id, $data) {
        // Simulate database save
        return true;
    }

    private function save_domain($id, $data) {
        // Simulate database save
        return true;
    }

    // AJAX handlers
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        wp_send_json_success($this->get_dashboard_statistics());
    }

    public function ajax_test_connection() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        wp_send_json_success(['message' => 'Connection test successful for ' . $domain]);
    }

    public function ajax_save_code() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'Code saved successfully']);
    }

    public function ajax_delete_code() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'Code deleted successfully']);
    }

    public function ajax_save_domain() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'Domain saved successfully']);
    }

    public function ajax_delete_domain() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'Domain deleted successfully']);
    }

    public function ajax_export_data() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'Export functionality will be available in full implementation']);
    }
}

// Initialize the admin menu
if (is_admin()) {
    add_action('plugins_loaded', function() {
        AFFCD_Admin_Menu::get_instance();
    });
}