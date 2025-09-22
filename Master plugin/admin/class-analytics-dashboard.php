<?php
/**
 * Analytics Dashboard for Affiliate Cross Domain System
 * 
 * Plugin: Affiliate Cross Domain System (Master)
 * File: /wp-content/plugins/affiliate-cross-domain-system/admin/class-analytics-dashboard.php
 * 
 * Provides comprehensive analytics and reporting functionality
 * with real-time data visualization and performance metrics.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Analytics_Dashboard {

    private $vanity_manager;
    private $security_manager;
    private $cache_prefix = 'affcd_analytics_';
    private $cache_expiration = 900; // 15 minutes

    /**
     * Constructor
     */
    public function __construct() {
        $this->vanity_manager = new AFFCD_Vanity_Code_Manager();
        $this->security_manager = new AFFCD_Security_Manager();
        
        // Initialize hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_affcd_get_analytics_data', [$this, 'ajax_get_analytics_data']);
        add_action('wp_ajax_affcd_export_analytics', [$this, 'ajax_export_analytics']);
        add_action('wp_ajax_affcd_generate_report', [$this, 'ajax_generate_report']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Schedule analytics tasks
        add_action('affcd_update_analytics_cache', [$this, 'update_analytics_cache']);
        add_action('affcd_generate_daily_report', [$this, 'generate_daily_report']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'affiliate-wp',
            __('Analytics Dashboard', 'affiliate-cross-domain'),
            __('Analytics', 'affiliate-cross-domain'),
            'manage_affiliates',
            'affcd-analytics',
            [$this, 'render_analytics_page']
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'affcd-analytics') === false) {
            return;
        }

        // Chart.js for data visualization
        wp_enqueue_script(
            'chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );

        // Custom analytics script
        wp_enqueue_script(
            'affcd-analytics',
            AFFCD_PLUGIN_URL . 'admin/js/analytics-dashboard.js',
            ['jquery', 'chartjs'],
            AFFCD_VERSION,
            true
        );

        wp_enqueue_style(
            'affcd-analytics',
            AFFCD_PLUGIN_URL . 'admin/css/analytics-dashboard.css',
            [],
            AFFCD_VERSION
        );

        // Localize script
        wp_localize_script('affcd-analytics', 'affcdAnalytics', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_analytics_nonce'),
            'i18n' => [
                'loading' => __('Loading...', 'affiliate-cross-domain'),
                'error' => __('Error loading data', 'affiliate-cross-domain'),
                'noData' => __('No data available', 'affiliate-cross-domain'),
                'exportSuccess' => __('Export completed successfully', 'affiliate-cross-domain'),
                'reportGenerated' => __('Report generated successfully', 'affiliate-cross-domain')
            ]
        ]);
    }

    /**
     * Render analytics dashboard page
     */
    public function render_analytics_page() {
        $current_tab = $_GET['tab'] ?? 'overview';
        $date_range = $_GET['range'] ?? '7d';
        
        ?>
        <div class="wrap affcd-analytics-dashboard">
            <h1><?php _e('Analytics Dashboard', 'affiliate-cross-domain'); ?></h1>

            <!-- Date Range Selector -->
            <div class="affcd-date-controls">
                <select id="affcd-date-range" data-current="<?php echo esc_attr($date_range); ?>">
                    <option value="24h" <?php selected($date_range, '24h'); ?>><?php _e('Last 24 Hours', 'affiliate-cross-domain'); ?></option>
                    <option value="7d" <?php selected($date_range, '7d'); ?>><?php _e('Last 7 Days', 'affiliate-cross-domain'); ?></option>
                    <option value="30d" <?php selected($date_range, '30d'); ?>><?php _e('Last 30 Days', 'affiliate-cross-domain'); ?></option>
                    <option value="90d" <?php selected($date_range, '90d'); ?>><?php _e('Last 90 Days', 'affiliate-cross-domain'); ?></option>
                    <option value="custom"><?php _e('Custom Range', 'affiliate-cross-domain'); ?></option>
                </select>
                
                <div id="affcd-custom-date-range" style="display: none;">
                    <input type="date" id="affcd-start-date" />
                    <span>to</span>
                    <input type="date" id="affcd-end-date" />
                    <button type="button" id="affcd-apply-custom-range" class="button"><?php _e('Apply', 'affiliate-cross-domain'); ?></button>
                </div>
                
                <button type="button" id="affcd-refresh-data" class="button"><?php _e('Refresh', 'affiliate-cross-domain'); ?></button>
                <button type="button" id="affcd-export-data" class="button button-primary"><?php _e('Export Data', 'affiliate-cross-domain'); ?></button>
            </div>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="?page=affcd-analytics&tab=overview" 
                   class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-analytics&tab=performance" 
                   class="nav-tab <?php echo $current_tab === 'performance' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Performance', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-analytics&tab=affiliates" 
                   class="nav-tab <?php echo $current_tab === 'affiliates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Affiliates', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-analytics&tab=domains" 
                   class="nav-tab <?php echo $current_tab === 'domains' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Domains', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-analytics&tab=geographic" 
                   class="nav-tab <?php echo $current_tab === 'geographic' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Geographic', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-analytics&tab=security" 
                   class="nav-tab <?php echo $current_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Security', 'affiliate-cross-domain'); ?>
                </a>
            </nav>

            <!-- Loading Indicator -->
            <div id="affcd-loading" class="affcd-loading" style="display: none;">
                <div class="spinner is-active"></div>
                <span><?php _e('Loading analytics data...', 'affiliate-cross-domain'); ?></span>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="affcd-analytics-content">
                <?php
                switch ($current_tab) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'performance':
                        $this->render_performance_tab();
                        break;
                    case 'affiliates':
                        $this->render_affiliates_tab();
                        break;
                    case 'domains':
                        $this->render_domains_tab();
                        break;
                    case 'geographic':
                        $this->render_geographic_tab();
                        break;
                    case 'security':
                        $this->render_security_tab();
                        break;
                }
                ?>
            </div>
        </div>

        <style>
        .affcd-analytics-dashboard {
            margin: 20px 20px 0 2px;
        }
        .affcd-date-controls {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .affcd-date-controls select,
        .affcd-date-controls input {
            margin-right: 10px;
        }
        .affcd-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .affcd-stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        .affcd-stat-value {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .affcd-stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .affcd-chart-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .affcd-chart-title {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .affcd-loading {
            text-align: center;
            padding: 40px;
        }
        .affcd-loading .spinner {
            float: none;
            margin: 0 auto 20px;
        }
        .affcd-data-table {
            width: 100%;
            background: #fff;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .affcd-data-table th,
        .affcd-data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .affcd-data-table th {
            background: #f9f9f9;
            font-weight: bold;
        }
        .affcd-trend-up {
            color: #46b450;
        }
        .affcd-trend-down {
            color: #dc3232;
        }
        .affcd-trend-neutral {
            color: #ffb900;
        }
        </style>
        <?php
    }

    /**
     * Render overview tab
     */
    private function render_overview_tab() {
        ?>
        <div class="overview-tab">
            <!-- Key Metrics Cards -->
            <div class="affcd-stats-grid" id="affcd-key-metrics">
                <!-- Cards will be populated via AJAX -->
            </div>

            <!-- Charts Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="affcd-chart-container">
                    <div class="affcd-chart-title"><?php _e('Traffic Trends', 'affiliate-cross-domain'); ?></div>
                    <canvas id="affcd-traffic-chart" width="400" height="200"></canvas>
                </div>
                  <div class="affcd-chart-container">
                    <div class="affcd-chart-title"><?php _e('Conversion Rates', 'affiliate-cross-domain'); ?></div>
                    <canvas id="affcd-conversion-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Recent Activity', 'affiliate-cross-domain'); ?></div>
                <div id="affcd-recent-activity">
                    <!-- Activity will be populated via AJAX -->
                </div>
            </div>

            <!-- Top Performing Codes -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Top Performing Vanity Codes', 'affiliate-cross-domain'); ?></div>
                <table class="affcd-data-table" id="affcd-top-codes">
                    <thead>
                        <tr>
                            <th><?php _e('Code', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Usage', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Conversions', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Revenue', 'affiliate-cross-domain'); ?></th>
                </tr>
                <?php foreach (array_slice($data['overview']['top_codes'], 0, 5) as $code): ?>
                    <tr>
                        <td><?php echo esc_html($code['vanity_code']); ?></td>
                        <td><?php echo number_format($code['usage_count']); ?></td>
                        <td>$<?php echo number_format($code['revenue'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p><?php _e('No activity in the last 24 hours.', 'affiliate-cross-domain'); ?></p>
        <?php endif; ?>
        
        <p><small><?php _e('This is an automated report from your Affiliate Cross Domain System.', 'affiliate-cross-domain'); ?></small></p>
        <?php
        return ob_get_clean();
    }
}></th>
                            <th><?php _e('Conversion Rate', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render performance tab
     */
    private function render_performance_tab() {
        ?>
        <div class="performance-tab">
            <!-- Performance Metrics -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Performance Overview', 'affiliate-cross-domain'); ?></div>
                <div class="affcd-stats-grid" id="affcd-performance-metrics">
                    <!-- Metrics will be populated via AJAX -->
                </div>
            </div>

            <!-- Performance Charts -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="affcd-chart-container">
                    <div class="affcd-chart-title"><?php _e('Revenue Over Time', 'affiliate-cross-domain'); ?></div>
                    <canvas id="affcd-revenue-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="affcd-chart-container">
                    <div class="affcd-chart-title"><?php _e('Code Performance Comparison', 'affiliate-cross-domain'); ?></div>
                    <canvas id="affcd-performance-comparison" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Device & Browser Analytics -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Device & Browser Analytics', 'affiliate-cross-domain'); ?></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                    <canvas id="affcd-device-chart"></canvas>
                    <canvas id="affcd-browser-chart"></canvas>
                    <canvas id="affcd-os-chart"></canvas>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render affiliates tab
     */
    private function render_affiliates_tab() {
        ?>
        <div class="affiliates-tab">
            <!-- Affiliate Performance Table -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Affiliate Performance', 'affiliate-cross-domain'); ?></div>
                <table class="affcd-data-table" id="affcd-affiliate-performance">
                    <thead>
                        <tr>
                            <th><?php _e('Affiliate', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Codes', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Total Usage', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Conversions', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Revenue', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Avg. Order Value', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Conversion Rate', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Affiliate Comparison Chart -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Affiliate Revenue Comparison', 'affiliate-cross-domain'); ?></div>
                <canvas id="affcd-affiliate-comparison" width="800" height="400"></canvas>
            </div>
        </div>
        <?php
    }

    /**
     * Render domains tab
     */
    private function render_domains_tab() {
        ?>
        <div class="domains-tab">
            <!-- Domain Performance -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Domain Performance', 'affiliate-cross-domain'); ?></div>
                <table class="affcd-data-table" id="affcd-domain-performance">
                    <thead>
                        <tr>
                            <th><?php _e('Domain', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Total Requests', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Successful Validations', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Conversions', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Revenue', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Success Rate', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Status', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Domain Activity Chart -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Domain Activity Over Time', 'affiliate-cross-domain'); ?></div>
                <canvas id="affcd-domain-activity" width="800" height="400"></canvas>
            </div>
        </div>
        <?php
    }

    /**
     * Render geographic tab
     */
    private function render_geographic_tab() {
        ?>
        <div class="geographic-tab">
            <!-- Geographic Distribution -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="affcd-chart-container">
                    <div class="affcd-chart-title"><?php _e('Traffic by Country', 'affiliate-cross-domain'); ?></div>
                    <canvas id="affcd-country-chart"></canvas>
                </div>
                
                <div class="affcd-chart-container">
                    <div class="affcd-chart-title"><?php _e('Revenue by Region', 'affiliate-cross-domain'); ?></div>
                    <canvas id="affcd-region-revenue"></canvas>
                </div>
            </div>

            <!-- Geographic Performance Table -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Geographic Performance Details', 'affiliate-cross-domain'); ?></div>
                <table class="affcd-data-table" id="affcd-geographic-performance">
                    <thead>
                        <tr>
                            <th><?php _e('Country', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Region', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Sessions', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Conversions', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Revenue', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Conversion Rate', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Avg. Order Value', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render security tab
     */
    private function render_security_tab() {
        ?>
        <div class="security-tab">
            <!-- Security Metrics -->
            <div class="affcd-stats-grid" id="affcd-security-metrics">
                <!-- Metrics will be populated via AJAX -->
            </div>

            <!-- Security Events Chart -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Security Events Over Time', 'affiliate-cross-domain'); ?></div>
                <canvas id="affcd-security-events" width="800" height="400"></canvas>
            </div>

            <!-- Recent Security Events -->
            <div class="affcd-chart-container">
                <div class="affcd-chart-title"><?php _e('Recent Security Events', 'affiliate-cross-domain'); ?></div>
                <table class="affcd-data-table" id="affcd-security-events-table">
                    <thead>
                        <tr>
                            <th><?php _e('Timestamp', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Event Type', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Severity', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Source IP', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Domain', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Details', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get analytics data for specified period
     */
    public function get_analytics_data($period = '7d', $start_date = null, $end_date = null) {
        $cache_key = $this->cache_prefix . md5($period . $start_date . $end_date);
        $cached_data = wp_cache_get($cache_key, 'affcd_analytics');
        
        if ($cached_data !== false) {
            return $cached_data;
        }

        global $wpdb;
        
        // Calculate date range
        $date_range = $this->calculate_date_range($period, $start_date, $end_date);
        
        $data = [
            'period' => $period,
            'date_range' => $date_range,
            'overview' => $this->get_overview_data($date_range),
            'performance' => $this->get_performance_data($date_range),
            'affiliates' => $this->get_affiliates_data($date_range),
            'domains' => $this->get_domains_data($date_range),
            'geographic' => $this->get_geographic_data($date_range),
            'security' => $this->get_security_data($date_range)
        ];

        // Cache the data
        wp_cache_set($cache_key, $data, 'affcd_analytics', $this->cache_expiration);
        
        return $data;
    }

    /**
     * Get overview data
     */
    private function get_overview_data($date_range) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        
        // Key metrics
        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT v.id) as total_codes,
                COUNT(DISTINCT CASE WHEN v.status = 'active' THEN v.id END) as active_codes,
                COALESCE(SUM(u.conversion_value), 0) as total_revenue,
                COUNT(u.id) as total_usage,
                COUNT(CASE WHEN u.converted = 1 THEN u.id END) as total_conversions,
                COUNT(DISTINCT u.user_ip) as unique_visitors,
                COUNT(DISTINCT u.domain) as active_domains
             FROM {$vanity_table} v
             LEFT JOIN {$usage_table} u ON v.id = u.vanity_code_id 
                AND u.tracked_at BETWEEN %s AND %s",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        // Calculate conversion rate
        $metrics['conversion_rate'] = $metrics['total_usage'] > 0 ? 
            round(($metrics['total_conversions'] / $metrics['total_usage']) * 100, 2) : 0;

        // Calculate average order value
        $metrics['avg_order_value'] = $metrics['total_conversions'] > 0 ? 
            round($metrics['total_revenue'] / $metrics['total_conversions'], 2) : 0;

        // Traffic trends over time
        $traffic_trends = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(tracked_at) as date,
                COUNT(*) as usage_count,
                COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions,
                SUM(conversion_value) as revenue
             FROM {$usage_table}
             WHERE tracked_at BETWEEN %s AND %s
             GROUP BY DATE(tracked_at)
             ORDER BY date",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        // Top performing codes
        $top_codes = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                v.vanity_code,
                v.description,
                COUNT(u.id) as usage_count,
                COUNT(CASE WHEN u.converted = 1 THEN u.id END) as conversions,
                SUM(u.conversion_value) as revenue,
                ROUND((COUNT(CASE WHEN u.converted = 1 THEN u.id END) / COUNT(u.id)) * 100, 2) as conversion_rate
             FROM {$vanity_table} v
             LEFT JOIN {$usage_table} u ON v.id = u.vanity_code_id 
                AND u.tracked_at BETWEEN %s AND %s
             GROUP BY v.id
             HAVING usage_count > 0
             ORDER BY revenue DESC, conversions DESC
             LIMIT 10",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        // Recent activity
        $recent_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                v.vanity_code,
                u.domain,
                u.converted,
                u.conversion_value,
                u.tracked_at
             FROM {$usage_table} u
             JOIN {$vanity_table} v ON u.vanity_code_id = v.id
             WHERE u.tracked_at BETWEEN %s AND %s
             ORDER BY u.tracked_at DESC
             LIMIT 20",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        return [
            'metrics' => $metrics,
            'traffic_trends' => $traffic_trends,
            'top_codes' => $top_codes,
            'recent_activity' => $recent_activity
        ];
    }

    /**
     * Get performance data
     */
    private function get_performance_data($date_range) {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        
        // Device analytics
        $device_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                device_type,
                COUNT(*) as count,
                COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions,
                SUM(conversion_value) as revenue
             FROM {$usage_table}
             WHERE tracked_at BETWEEN %s AND %s
             GROUP BY device_type",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        // Browser analytics
        $browser_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                browser,
                COUNT(*) as count,
                COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions
             FROM {$usage_table}
             WHERE tracked_at BETWEEN %s AND %s AND browser IS NOT NULL
             GROUP BY browser
             ORDER BY count DESC
             LIMIT 10",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        // Revenue over time
        $revenue_trends = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(tracked_at) as date,
                SUM(conversion_value) as revenue,
                COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions
             FROM {$usage_table}
             WHERE tracked_at BETWEEN %s AND %s
             GROUP BY DATE(tracked_at)
             ORDER BY date",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        return [
            'device_stats' => $device_stats,
            'browser_stats' => $browser_stats,
            'revenue_trends' => $revenue_trends
        ];
    }

    /**
     * Get affiliates data
     */
    private function get_affiliates_data($date_range) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        
        $affiliate_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                v.affiliate_id,
                COUNT(DISTINCT v.id) as total_codes,
                COUNT(u.id) as total_usage,
                COUNT(CASE WHEN u.converted = 1 THEN u.id END) as conversions,
                SUM(u.conversion_value) as revenue,
                ROUND(AVG(CASE WHEN u.converted = 1 THEN u.conversion_value END), 2) as avg_order_value,
                ROUND((COUNT(CASE WHEN u.converted = 1 THEN u.id END) / COUNT(u.id)) * 100, 2) as conversion_rate
             FROM {$vanity_table} v
             LEFT JOIN {$usage_table} u ON v.id = u.vanity_code_id 
                AND u.tracked_at BETWEEN %s AND %s
             GROUP BY v.affiliate_id
             HAVING total_usage > 0
             ORDER BY revenue DESC",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        // Enhance with affiliate names
        foreach ($affiliate_performance as &$performance) {
            $affiliate = affwp_get_affiliate($performance['affiliate_id']);
            if ($affiliate) {
                $user = get_userdata($affiliate->user_id);
                $performance['affiliate_name'] = $user ? $user->display_name : 'Unknown';
                $performance['affiliate_email'] = $user ? $user->user_email : '';
            } else {
                $performance['affiliate_name'] = 'Unknown Affiliate';
                $performance['affiliate_email'] = '';
            }
        }

        return [
            'performance' => $affiliate_performance
        ];
    }

    /**
     * Get domains data
     */
    private function get_domains_data($date_range) {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        
        $domain_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.domain,
                d.status,
                COUNT(u.id) as total_requests,
                COUNT(CASE WHEN u.converted = 1 THEN u.id END) as conversions,
                SUM(u.conversion_value) as revenue,
                ROUND((COUNT(CASE WHEN u.converted = 1 THEN u.id END) / COUNT(u.id)) * 100, 2) as success_rate
             FROM {$usage_table} u
             LEFT JOIN {$domains_table} d ON u.domain = d.domain
             WHERE u.tracked_at BETWEEN %s AND %s
             GROUP BY u.domain
             ORDER BY total_requests DESC",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        return [
            'performance' => $domain_performance
        ];
    }

    /**
     * Get geographic data
     */
    private function get_geographic_data($date_range) {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        
        $geographic_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                country_code,
                region,
                COUNT(*) as sessions,
                COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions,
                SUM(conversion_value) as revenue,
                ROUND((COUNT(CASE WHEN converted = 1 THEN 1 END) / COUNT(*)) * 100, 2) as conversion_rate,
                ROUND(AVG(CASE WHEN converted = 1 THEN conversion_value END), 2) as avg_order_value
             FROM {$usage_table}
             WHERE tracked_at BETWEEN %s AND %s 
               AND country_code IS NOT NULL
             GROUP BY country_code, region
             ORDER BY sessions DESC",
            $date_range['start'], $date_range['end']
        ), ARRAY_A);

        return [
            'performance' => $geographic_performance
        ];
    }

    /**
     * Get security data
     */
    private function get_security_data($date_range) {
        return $this->security_manager->get_security_dashboard_data();
    }

    /**
     * Calculate date range
     */
    private function calculate_date_range($period, $start_date = null, $end_date = null) {
        if ($period === 'custom' && $start_date && $end_date) {
            return [
                'start' => $start_date . ' 00:00:00',
                'end' => $end_date . ' 23:59:59'
            ];
        }

        $end = current_time('mysql');
        
        switch ($period) {
            case '24h':
                $start = date('Y-m-d H:i:s', strtotime('-24 hours'));
                break;
            case '7d':
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30d':
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90d':
                $start = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * AJAX: Get analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('affcd_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $period = sanitize_text_field($_POST['period'] ?? '7d');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $tab = sanitize_text_field($_POST['tab'] ?? 'overview');

        $data = $this->get_analytics_data($period, $start_date, $end_date);

        wp_send_json_success([
            'data' => $data[$tab] ?? $data,
            'period' => $period,
            'date_range' => $data['date_range']
        ]);
    }

    /**
     * AJAX: Export analytics data
     */
    public function ajax_export_analytics() {
        check_ajax_referer('affcd_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $period = sanitize_text_field($_POST['period'] ?? '7d');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        $data = $this->get_analytics_data($period);
        $export_result = $this->export_data($data, $format);

        if (is_wp_error($export_result)) {
            wp_send_json_error($export_result->get_error_message());
        }

        wp_send_json_success([
            'download_url' => $export_result['url'],
            'filename' => $export_result['filename']
        ]);
    }

    /**
     * Export analytics data
     */
    private function export_data($data, $format = 'csv') {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/affcd-exports/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $filename = 'affcd-analytics-' . date('Y-m-d-H-i-s') . '.' . $format;
        $filepath = $export_dir . $filename;

        switch ($format) {
            case 'csv':
                $this->export_to_csv($data, $filepath);
                break;
            case 'json':
                $this->export_to_json($data, $filepath);
                break;
            default:
                return new WP_Error('invalid_format', __('Invalid export format.', 'affiliate-cross-domain'));
        }

        return [
            'url' => $upload_dir['baseurl'] . '/affcd-exports/' . $filename,
            'filename' => $filename,
            'filepath' => $filepath
        ];
    }

    /**
     * Export to CSV
     */
    private function export_to_csv($data, $filepath) {
        $file = fopen($filepath, 'w');
        
        // Overview metrics
        fputcsv($file, ['Section', 'Metric', 'Value']);
        foreach ($data['overview']['metrics'] as $key => $value) {
            fputcsv($file, ['Overview', ucwords(str_replace('_', ' ', $key)), $value]);
        }
        
        // Top codes
        fputcsv($file, []);
        fputcsv($file, ['Top Performing Codes']);
        fputcsv($file, ['Code', 'Usage', 'Conversions', 'Revenue', 'Conversion Rate']);
        foreach ($data['overview']['top_codes'] as $code) {
            fputcsv($file, [
                $code['vanity_code'],
                $code['usage_count'],
                $code['conversions'],
                $code['revenue'],
                $code['conversion_rate'] . '%'
            ]);
        }

        fclose($file);
    }

    /**
     * Export to JSON
     */
    private function export_to_json($data, $filepath) {
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Update analytics cache
     */
    public function update_analytics_cache() {
        $periods = ['24h', '7d', '30d', '90d'];
        
        foreach ($periods as $period) {
            $this->get_analytics_data($period);
        }
    }

    /**
     * Generate daily report
     */
    public function generate_daily_report() {
        $data = $this->get_analytics_data('24h');
        
        // Email report to administrators
        $admin_emails = get_option('affcd_report_emails', [get_option('admin_email')]);
        
        $subject = sprintf(
            __('[%s] Daily Affiliate Analytics Report', 'affiliate-cross-domain'),
            get_bloginfo('name')
        );
        
        $message = $this->format_email_report($data);
        
        foreach ($admin_emails as $email) {
            wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
        }
    }

    /**
     * Format email report
     */
    private function format_email_report($data) {
        ob_start();
        ?>
        <h2><?php _e('Daily Analytics Report', 'affiliate-cross-domain'); ?></h2>
        
        <h3><?php _e('Key Metrics (Last 24 Hours)', 'affiliate-cross-domain'); ?></h3>
        <ul>
            <li><?php _e('Total Usage:', 'affiliate-cross-domain'); ?> <?php echo number_format($data['overview']['metrics']['total_usage']); ?></li>
            <li><?php _e('Conversions:', 'affiliate-cross-domain'); ?> <?php echo number_format($data['overview']['metrics']['total_conversions']); ?></li>
            <li><?php _e('Revenue:', 'affiliate-cross-domain'); ?> $<?php echo number_format($data['overview']['metrics']['total_revenue'], 2); ?></li>
            <li><?php _e('Conversion Rate:', 'affiliate-cross-domain'); ?> <?php echo $data['overview']['metrics']['conversion_rate']; ?>%</li>
        </ul>

        <h3><?php _e('Top Performing Codes', 'affiliate-cross-domain'); ?></h3>
        <?php if (!empty($data['overview']['top_codes'])): ?>
            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th><?php _e('Code', 'affiliate-cross-domain'); ?></th>
                    <th><?php _e('Usage', 'affiliate-cross-domain'); ?></th>
                    <th><?php _e('Revenue', 'affiliate-cross-domain'); ?