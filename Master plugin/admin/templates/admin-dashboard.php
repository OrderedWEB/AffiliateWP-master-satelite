<?php
/**
 * Admin menu class referenced in main plugin but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Admin_Menu {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
    }
    
    public function add_admin_menu() {
        // Check if AffiliateWP exists, if not create our own menu
        if (function_exists('affiliate_wp')) {
            $parent_slug = 'affiliate-wp';
        } else {
            // Create main menu
            add_menu_page(
                __('Affiliate System', 'affiliatewp-cross-domain-plugin-suite'),
                __('Affiliate System', 'affiliatewp-cross-domain-plugin-suite'),
                'manage_options',
                'affcd-main',
                [$this, 'render_main_page'],
                'dashicons-networking',
                30
            );
            $parent_slug = 'affcd-main';
        }
        
        // Add submenu pages
        add_submenu_page(
            $parent_slug,
            __('Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'),
            __('Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'),
            'manage_affiliates',
            'affcd-vanity-codes',
            [$this, 'render_vanity_codes_page']
        );
        
        add_submenu_page(
            $parent_slug,
            __('Analytics', 'affiliatewp-cross-domain-plugin-suite'),
            __('Analytics', 'affiliatewp-cross-domain-plugin-suite'),
            'manage_affiliates',
            'affcd-analytics',
            [$this, 'render_analytics_page']
        );
        
        add_submenu_page(
            $parent_slug,
            __('Settings', 'affiliatewp-cross-domain-plugin-suite'),
            __('Settings', 'affiliatewp-cross-domain-plugin-suite'),
            'manage_options',
            'affcd-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function admin_init() {
        // Register settings
        register_setting('affcd_settings', 'affcd_api_settings');
        register_setting('affcd_settings', 'affcd_security_settings');
        register_setting('affcd_settings', 'affcd_webhook_settings');
        register_setting('affcd_settings', 'affcd_cache_settings');
    }
    
    public function render_main_page() {
        if (function_exists('affiliate_wp')) {
            // Redirect to AffiliateWP if it exists
            wp_redirect(admin_url('admin.php?page=affiliate-wp'));
            exit;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Affiliate Cross-Domain System', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>
            
            <div class="affcd-dashboard">
                <div class="affcd-dashboard-widgets">
                    <?php $this->render_dashboard_widget_stats(); ?>
                    <?php $this->render_dashboard_widget_recent_activity(); ?>
                    <?php $this->render_dashboard_widget_quick_actions(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .affcd-dashboard-widgets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .affcd-widget {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        .affcd-widget h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        </style>
        <?php
    }
    
    public function render_vanity_codes_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>
            <p><?php _e('Manage discount codes for cross-domain affiliate system.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
            
            <?php
            // Include WP_List_Table for codes
            if (!class_exists('AFFCD_Vanity_Codes_List_Table')) {
                require_once AFFCD_ADMIN_DIR . 'class-vanity-codes-list-table.php';
            }
            
            $list_table = new AFFCD_Vanity_Codes_List_Table();
            $list_table->prepare_items();
            $list_table->display();
            ?>
        </div>
        <?php
    }
    
    public function render_analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>
            <p><?php _e('View usage statistics and performance metrics.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
            
            <?php $this->render_analytics_dashboard(); ?>
        </div>
        <?php
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Settings', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('affcd_settings'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="master_api_key"><?php _e('Master API Key', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <?php $api_settings = get_option('affcd_api_settings', []); ?>
                                <input type="text" 
                                       id="master_api_key" 
                                       name="affcd_api_settings[master_api_key]" 
                                       value="<?php echo esc_attr($api_settings['master_api_key'] ?? ''); ?>" 
                                       class="regular-text" 
                                       readonly>
                                <button type="button" class="button" onclick="generateApiKey()">
                                    <?php _e('Generate New', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                                <p class="description">
                                    <?php _e('Used by client sites to authenticate API requests.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        function generateApiKey() {
            if (confirm('Generate a new API key? This will invalidate the current key.')) {
                var newKey = 'affcd_' + Math.random().toString(36).substr(2, 32);
                document.getElementById('master_api_key').value = newKey;
            }
        }
        </script>
        <?php
    }
    
    private function render_dashboard_widget_stats() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="affcd-widget">
            <h3><?php _e('System Statistics', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="affcd-stats-grid">
                <div class="affcd-stat">
                    <span class="affcd-stat-number"><?php echo number_format($stats['total_codes']); ?></span>
                    <span class="affcd-stat-label"><?php _e('Active Codes', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </div>
                <div class="affcd-stat">
                    <span class="affcd-stat-number"><?php echo number_format($stats['total_domains']); ?></span>
                    <span class="affcd-stat-label"><?php _e('authorised Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </div>
                <div class="affcd-stat">
                    <span class="affcd-stat-number"><?php echo number_format($stats['total_requests']); ?></span>
                    <span class="affcd-stat-label"><?php _e('API Requests (30d)', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </div>
                <div class="affcd-stat">
                    <span class="affcd-stat-number"><?php echo number_format($stats['total_conversions']); ?></span>
                    <span class="affcd-stat-label"><?php _e('Conversions (30d)', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </div>
            </div>
        </div>
        
        <style>
        .affcd-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .affcd-stat {
            text-align: center;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .affcd-stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        .affcd-stat-label {
            font-size: 12px;
            color: #666;
        }
        </style>
        <?php
    }
    
    private function render_dashboard_widget_recent_activity() {
        $recent_activity = $this->get_recent_activity();
        ?>
        <div class="affcd-widget">
            <h3><?php _e('Recent Activity', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="affcd-activity-list">
                <?php if (empty($recent_activity)): ?>
                    <p><?php _e('No recent activity.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                <?php else: ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="affcd-activity-item">
                            <span class="affcd-activity-time"><?php echo human_time_diff(strtotime($activity->created_at)); ?> ago</span>
                            <span class="affcd-activity-text">
                                Code <strong><?php echo esc_html($activity->code); ?></strong> 
                                <?php if ($activity->conversion): ?>
                                    converted on <?php echo esc_html($activity->domain); ?>
                                <?php else: ?>
                                    validated on <?php echo esc_html($activity->domain); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .affcd-activity-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .affcd-activity-item:last-child {
            border-bottom: none;
        }
        .affcd-activity-time {
            color: #666;
            font-size: 11px;
            display: block;
        }
        .affcd-activity-text {
            font-size: 13px;
        }
        </style>
        <?php
    }
    
    private function render_dashboard_widget_quick_actions() {
        ?>
        <div class="affcd-widget">
            <h3><?php _e('Quick Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="affcd-quick-actions">
                <p>
                    <a href="<?php echo admin_url('admin.php?page=affcd-domain-management'); ?>" class="button button-primary">
                        <?php _e('Manage Domains', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </a>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes'); ?>" class="button">
                        <?php _e('Add New Code', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </a>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=affcd-analytics'); ?>" class="button">
                        <?php _e('View Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </a>
                </p>
                <p>
                    <button type="button" class="button" onclick="testAllConnections()">
                        <?php _e('Test All Connections', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </button>
                </p>
            </div>
        </div>
        
        <script>
        function testAllConnections() {
            if (confirm('Test connections to all authorised domains?')) {
                window.location.href = '<?php echo admin_url('admin.php?page=affcd-domain-management&action=test_all'); ?>';
            }
        }
        </script>
        <?php
    }
    
    private function render_analytics_dashboard() {
        $analytics = $this->get_analytics_data();
        ?>
        <div class="affcd-analytics-dashboard">
            <div class="affcd-analytics-section">
                <h3><?php _e('Usage Over Time', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <canvas id="usageChart" width="400" height="200"></canvas>
            </div>
            
            <div class="affcd-analytics-section">
                <h3><?php _e('Top Performing Codes', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Code', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Uses', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Conversion Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['top_codes'] as $code): ?>
                            <tr>
                                <td><strong><?php echo esc_html($code->code); ?></strong></td>
                                <td><?php echo number_format($code->total_uses); ?></td>
                                <td><?php echo number_format($code->conversions); ?></td>
                                <td><?php echo $code->total_uses > 0 ? round(($code->conversions / $code->total_uses) * 100, 1) : 0; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="affcd-analytics-section">
                <h3><?php _e('Domain Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Requests', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Total Value', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['domain_performance'] as $domain): ?>
                            <tr>
                                <td><?php echo esc_html($domain->domain); ?></td>
                                <td><?php echo number_format($domain->total_requests); ?></td>
                                <td><?php echo number_format($domain->conversions); ?></td>
                                <td>$<?php echo number_format($domain->total_value, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .affcd-analytics-dashboard {
            display: grid;
            gap: 20px;
        }
        .affcd-analytics-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        .affcd-analytics-section h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        </style>
        <?php
    }
    
    private function get_dashboard_stats() {
        global $wpdb;
        
        // Get total active codes
        $total_codes = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affcd_vanity_codes WHERE is_active = 1"
        );
        
        // Get total domains
        $domains = get_option('affcd_allowed_domains', []);
        $total_domains = count($domains);
        
        // Get recent requests (30 days)
        $total_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affcd_usage_tracking 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Get recent conversions (30 days)
        $total_conversions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affcd_usage_tracking 
             WHERE conversion = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return [
            'total_codes' => (int) $total_codes,
            'total_domains' => (int) $total_domains,
            'total_requests' => (int) $total_requests,
            'total_conversions' => (int) $total_conversions
        ];
    }
    
    private function get_recent_activity() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT code, domain, conversion, created_at 
             FROM {$wpdb->prefix}affcd_usage_tracking 
             ORDER BY created_at DESC 
             LIMIT 10"
        );
    }
    
    private function get_analytics_data() {
        global $wpdb;
        
        // Top performing codes
        $top_codes = $wpdb->get_results(
            "SELECT 
                code,
                COUNT(*) as total_uses,
                SUM(CASE WHEN conversion = 1 THEN 1 ELSE 0 END) as conversions
             FROM {$wpdb->prefix}affcd_usage_tracking 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY code 
             ORDER BY conversions DESC, total_uses DESC 
             LIMIT 10"
        );
        
        // Domain performance
        $domain_performance = $wpdb->get_results(
            "SELECT 
                domain,
                COUNT(*) as total_requests,
                SUM(CASE WHEN conversion = 1 THEN 1 ELSE 0 END) as conversions,
                SUM(CASE WHEN conversion = 1 THEN conversion_value ELSE 0 END) as total_value
             FROM {$wpdb->prefix}affcd_usage_tracking 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY domain 
             ORDER BY total_value DESC, conversions DESC 
             LIMIT 10"
        );
        
        return [
            'top_codes' => $top_codes,
            'domain_performance' => $domain_performance
        ];
    }
}