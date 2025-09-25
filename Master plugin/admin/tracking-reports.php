<?php
/**
 * Tracking Reports Admin Page
 *
 * Provides comprehensive tracking and analytics reporting for affiliate
 * cross-domain activities including code usage, conversions, domain
 * performance, and detailed analytics dashboards.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 * @author Richard King, Starne Consulting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Tracking_Reports {

    /**
     * Database manager instance
     *
     * @var AFFCD_Database_Manager
     */
    private $database_manager;

    /**
     * Security manager instance
     *
     * @var AFFCD_Security_Manager
     */
    private $security_manager;

    /**
     * Cache prefix
     *
     * @var string
     */
    private $cache_prefix = 'affcd_reports_';

    /**
     * Constructor
     */
    public function __construct() {
        $this->database_manager = new AFFCD_Database_Manager();
        $this->security_manager = new AFFCD_Security_Manager();
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_affcd_get_report_data', [$this, 'ajax_get_report_data']);
        add_action('wp_ajax_affcd_export_report', [$this, 'ajax_export_report']);
        add_action('wp_ajax_affcd_refresh_report_cache', [$this, 'ajax_refresh_cache']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'affiliate-wp',
            __('Tracking Reports', 'affiliate-cross-domain-full'),
            __('Tracking Reports', 'affiliate-cross-domain-full'),
            'manage_affiliates',
            'affcd-tracking-reports',
            [$this, 'render_reports_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'affcd-tracking-reports') === false) {
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

        // Date range picker
        wp_enqueue_script(
            'daterangepicker',
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js',
            ['jquery', 'moment'],
            '3.0.5',
            true
        );

        wp_enqueue_style(
            'daterangepicker',
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.css',
            [],
            '3.0.5'
        );

        // Moment.js
        wp_enqueue_script(
            'moment',
            'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
            [],
            '2.29.4',
            true
        );

        // Custom reports script
        wp_enqueue_script(
            'affcd-tracking-reports',
            AFFCD_PLUGIN_URL . 'admin/js/tracking-reports.js',
            ['jquery', 'chartjs', 'daterangepicker', 'moment'],
            AFFCD_VERSION,
            true
        );

        wp_enqueue_style(
            'affcd-tracking-reports',
            AFFCD_PLUGIN_URL . 'admin/css/tracking-reports.css',
            [],
            AFFCD_VERSION
        );

        wp_localize_script('affcd-tracking-reports', 'affcdReports', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_reports_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'affiliate-cross-domain-full'),
                'error' => __('Error loading data', 'affiliate-cross-domain-full'),
                'noData' => __('No data available for selected period', 'affiliate-cross-domain-full'),
                'exported' => __('Report exported successfully', 'affiliate-cross-domain-full'),
                'refreshed' => __('Cache refreshed successfully', 'affiliate-cross-domain-full'),
                'confirmDelete' => __('Are you sure you want to delete this data?', 'affiliate-cross-domain-full')
            ],
            'dateFormat' => get_option('date_format'),
            'currency' => affcd_get_currency_symbol()
        ]);
    }

    /**
     * Render reports page
     */
    public function render_reports_page() {
        if (!current_user_can('manage_affiliates')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'affiliate-cross-domain-full'));
        }

        $active_tab = $_GET['tab'] ?? 'overview';
        ?>
        <div class="wrap affcd-tracking-reports">
            <h1 class="wp-heading-inline">
                <?php _e('Affiliate Tracking Reports', 'affiliate-cross-domain-full'); ?>
            </h1>
            
            <hr class="wp-header-end">

            <!-- Report Controls -->
            <div class="affcd-report-controls">
                <div class="date-range-selector">
                    <label for="report-date-range"><?php _e('Date Range:', 'affiliate-cross-domain-full'); ?></label>
                    <input type="text" id="report-date-range" class="regular-text" 
                           value="<?php echo date('Y-m-d') . ' - ' . date('Y-m-d'); ?>">
                </div>
                
                <div class="domain-filter">
                    <label for="domain-filter"><?php _e('Domain:', 'affiliate-cross-domain-full'); ?></label>
                    <select id="domain-filter" class="regular-text">
                        <option value=""><?php _e('All Domains', 'affiliate-cross-domain-full'); ?></option>
                        <?php foreach ($this->get_active_domains() as $domain): ?>
                            <option value="<?php echo esc_attr($domain->domain_url); ?>">
                                <?php echo esc_html($domain->domain_url); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="affiliate-filter">
                    <label for="affiliate-filter"><?php _e('Affiliate:', 'affiliate-cross-domain-full'); ?></label>
                    <select id="affiliate-filter" class="regular-text">
                        <option value=""><?php _e('All Affiliates', 'affiliate-cross-domain-full'); ?></option>
                        <?php foreach ($this->get_active_affiliates() as $affiliate): ?>
                            <option value="<?php echo esc_attr($affiliate->affiliate_id); ?>">
                                <?php echo esc_html($affiliate->name ?: 'Affiliate #' . $affiliate->affiliate_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="report-actions">
                    <button type="button" id="refresh-reports" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'affiliate-cross-domain-full'); ?>
                    </button>
                    
                    <button type="button" id="export-reports" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export', 'affiliate-cross-domain-full'); ?>
                    </button>
                </div>
            </div>

            <!-- Report Navigation Tabs -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="?page=affcd-tracking-reports&tab=overview" 
                   class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'affiliate-cross-domain-full'); ?>
                </a>
                <a href="?page=affcd-tracking-reports&tab=codes" 
                   class="nav-tab <?php echo $active_tab === 'codes' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Code Performance', 'affiliate-cross-domain-full'); ?>
                </a>
                <a href="?page=affcd-tracking-reports&tab=domains" 
                   class="nav-tab <?php echo $active_tab === 'domains' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Domain Analytics', 'affiliate-cross-domain-full'); ?>
                </a>
                <a href="?page=affcd-tracking-reports&tab=affiliates" 
                   class="nav-tab <?php echo $active_tab === 'affiliates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Affiliate Performance', 'affiliate-cross-domain-full'); ?>
                </a>
                <a href="?page=affcd-tracking-reports&tab=conversions" 
                   class="nav-tab <?php echo $active_tab === 'conversions' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Conversions', 'affiliate-cross-domain-full'); ?>
                </a>
                <a href="?page=affcd-tracking-reports&tab=security" 
                   class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Security Events', 'affiliate-cross-domain-full'); ?>
                </a>
            </nav>

            <!-- Report Content -->
            <div class="affcd-report-content">
                <?php
                switch ($active_tab) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'codes':
                        $this->render_codes_tab();
                        break;
                    case 'domains':
                        $this->render_domains_tab();
                        break;
                    case 'affiliates':
                        $this->render_affiliates_tab();
                        break;
                    case 'conversions':
                        $this->render_conversions_tab();
                        break;
                    case 'security':
                        $this->render_security_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render overview tab
     */
    private function render_overview_tab() {
        $stats = $this->get_overview_statistics();
        ?>
        <div class="affcd-overview-tab">
            <!-- Key Performance Indicators -->
            <div class="affcd-kpi-grid">
                <div class="affcd-kpi-card">
                    <div class="kpi-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value" id="total-validations"><?php echo number_format($stats['total_validations']); ?></div>
                        <div class="kpi-label"><?php _e('Total Code Validations', 'affiliate-cross-domain-full'); ?></div>
                        <div class="kpi-change <?php echo $stats['validations_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($stats['validations_change'] >= 0 ? '+' : '') . $stats['validations_change'] . '%'; ?>
                        </div>
                    </div>
                </div>

                <div class="affcd-kpi-card">
                    <div class="kpi-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value" id="total-conversions"><?php echo number_format($stats['total_conversions']); ?></div>
                        <div class="kpi-label"><?php _e('Total Conversions', 'affiliate-cross-domain-full'); ?></div>
                        <div class="kpi-change <?php echo $stats['conversions_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($stats['conversions_change'] >= 0 ? '+' : '') . $stats['conversions_change'] . '%'; ?>
                        </div>
                    </div>
                </div>

                <div class="affcd-kpi-card">
                    <div class="kpi-icon">
                        <span class="dashicons dashicons-money"></span>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value" id="conversion-rate"><?php echo number_format($stats['conversion_rate'], 2); ?>%</div>
                        <div class="kpi-label"><?php _e('Conversion Rate', 'affiliate-cross-domain-full'); ?></div>
                        <div class="kpi-change <?php echo $stats['conversion_rate_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($stats['conversion_rate_change'] >= 0 ? '+' : '') . number_format($stats['conversion_rate_change'], 2) . '%'; ?>
                        </div>
                    </div>
                </div>

                <div class="affcd-kpi-card">
                    <div class="kpi-icon">
                        <span class="dashicons dashicons-networking"></span>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value" id="active-domains"><?php echo number_format($stats['active_domains']); ?></div>
                        <div class="kpi-label"><?php _e('Active Domains', 'affiliate-cross-domain-full'); ?></div>
                        <div class="kpi-change neutral">
                            <?php echo number_format($stats['total_domains']); ?> <?php _e('total', 'affiliate-cross-domain-full'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="affcd-charts-row">
                <div class="affcd-chart-container half-width">
                    <h3><?php _e('Validation Trends', 'affiliate-cross-domain-full'); ?></h3>
                    <canvas id="validation-trends-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="affcd-chart-container half-width">
                    <h3><?php _e('Conversion Trends', 'affiliate-cross-domain-full'); ?></h3>
                    <canvas id="conversion-trends-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="affcd-recent-activity">
                <h3><?php _e('Recent Activity', 'affiliate-cross-domain-full'); ?></h3>
                <div class="activity-list" id="recent-activity">
                    <?php $this->render_recent_activity(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render codes tab
     */
    private function render_codes_tab() {
        ?>
        <div class="affcd-codes-tab">
            <div class="affcd-chart-container">
                <h3><?php _e('Top Performing Codes', 'affiliate-cross-domain-full'); ?></h3>
                <canvas id="top-codes-chart" width="800" height="400"></canvas>
            </div>

            <div class="affcd-codes-table">
                <h3><?php _e('Code Performance Details', 'affiliate-cross-domain-full'); ?></h3>
                <table class="wp-list-table widefat fixed striped" id="codes-performance-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Code', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Affiliate', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Validations', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Conversions', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Conversion Rate', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Revenue', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Last Used', 'affiliate-cross-domain-full'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="codes-table-body">
                        <!-- Data loaded via AJAX -->
                        <tr><td colspan="7" class="loading"><?php _e('Loading...', 'affiliate-cross-domain-full'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render domains tab
     */
    private function render_domains_tab() {
        ?>
        <div class="affcd-domains-tab">
            <div class="affcd-chart-container">
                <h3><?php _e('Domain Performance Overview', 'affiliate-cross-domain-full'); ?></h3>
                <canvas id="domains-performance-chart" width="800" height="400"></canvas>
            </div>

            <div class="affcd-domains-grid">
                <div class="domain-metrics-card">
                    <h4><?php _e('Request Volume', 'affiliate-cross-domain-full'); ?></h4>
                    <canvas id="domain-requests-chart" width="400" height="300"></canvas>
                </div>
                
                <div class="domain-metrics-card">
                    <h4><?php _e('Success Rates', 'affiliate-cross-domain-full'); ?></h4>
                    <canvas id="domain-success-chart" width="400" height="300"></canvas>
                </div>
            </div>

            <div class="affcd-domains-table">
                <h3><?php _e('Domain Analytics Details', 'affiliate-cross-domain-full'); ?></h3>
                <table class="wp-list-table widefat fixed striped" id="domains-analytics-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Domain', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Status', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Total Requests', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Success Rate', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Avg Response Time', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Last Activity', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Actions', 'affiliate-cross-domain-full'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="domains-table-body">
                        <!-- Data loaded via AJAX -->
                        <tr><td colspan="7" class="loading"><?php _e('Loading...', 'affiliate-cross-domain-full'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render affiliates tab
     */
    private function render_affiliates_tab() {
        ?>
        <div class="affcd-affiliates-tab">
            <div class="affcd-chart-container">
                <h3><?php _e('Top Affiliates by Performance', 'affiliate-cross-domain-full'); ?></h3>
                <canvas id="top-affiliates-chart" width="800" height="400"></canvas>
            </div>

            <div class="affcd-affiliates-table">
                <h3><?php _e('Affiliate Performance Details', 'affiliate-cross-domain-full'); ?></h3>
                <table class="wp-list-table widefat fixed striped" id="affiliates-performance-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Affiliate', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Codes Used', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Total Validations', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Conversions', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Conversion Rate', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Revenue Generated', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Top Domain', 'affiliate-cross-domain-full'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="affiliates-table-body">
                        <!-- Data loaded via AJAX -->
                        <tr><td colspan="7" class="loading"><?php _e('Loading...', 'affiliate-cross-domain-full'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render conversions tab
     */
    private function render_conversions_tab() {
        ?>
        <div class="affcd-conversions-tab">
            <div class="affcd-charts-row">
                <div class="affcd-chart-container half-width">
                    <h3><?php _e('Conversion Timeline', 'affiliate-cross-domain-full'); ?></h3>
                    <canvas id="conversion-timeline-chart" width="400" height="300"></canvas>
                </div>
                
                <div class="affcd-chart-container half-width">
                    <h3><?php _e('Revenue by Source', 'affiliate-cross-domain-full'); ?></h3>
                    <canvas id="revenue-sources-chart" width="400" height="300"></canvas>
                </div>
            </div>

            <div class="affcd-conversions-table">
                <h3><?php _e('Recent Conversions', 'affiliate-cross-domain-full'); ?></h3>
                <table class="wp-list-table widefat fixed striped" id="conversions-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Date', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Code', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Affiliate', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Domain', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Amount', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Commission', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Status', 'affiliate-cross-domain-full'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="conversions-table-body">
                        <!-- Data loaded via AJAX -->
                        <tr><td colspan="7" class="loading"><?php _e('Loading...', 'affiliate-cross-domain-full'); ?></td></tr>
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
        <div class="affcd-security-tab">
            <div class="affcd-charts-row">
                <div class="affcd-chart-container half-width">
                    <h3><?php _e('Security Events by Severity', 'affiliate-cross-domain-full'); ?></h3>
                    <canvas id="security-severity-chart" width="400" height="300"></canvas>
                </div>
                
                <div class="affcd-chart-container half-width">
                    <h3><?php _e('Security Events Timeline', 'affiliate-cross-domain-full'); ?></h3>
                    <canvas id="security-timeline-chart" width="400" height="300"></canvas>
                </div>
            </div>

            <div class="affcd-security-table">
                <h3><?php _e('Security Event Log', 'affiliate-cross-domain-full'); ?></h3>
                <table class="wp-list-table widefat fixed striped" id="security-events-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Date/Time', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Event Type', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Severity', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Source IP', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Domain', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Details', 'affiliate-cross-domain-full'); ?></th>
                            <th scope="col"><?php _e('Actions', 'affiliate-cross-domain-full'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="security-table-body">
                        <!-- Data loaded via AJAX -->
                        <tr><td colspan="7" class="loading"><?php _e('Loading...', 'affiliate-cross-domain-full'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        $activities = $this->get_recent_activity(10);
        
        if (empty($activities)) {
            echo '<p>' . __('No recent activity found.', 'affiliate-cross-domain-full') . '</p>';
            return;
        }

        echo '<ul class="activity-feed">';
        foreach ($activities as $activity) {
            $icon = $this->get_activity_icon($activity->event_type);
            $time_ago = human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ago';
            
            echo '<li class="activity-item ' . esc_attr($activity->event_type) . '">';
            echo '<div class="activity-icon">' . $icon . '</div>';
            echo '<div class="activity-content">';
            echo '<div class="activity-description">' . esc_html($this->format_activity_description($activity)) . '</div>';
            echo '<div class="activity-meta">';
            echo '<span class="activity-time">' . esc_html($time_ago) . '</span>';
            if (!empty($activity->domain)) {
                echo ' • <span class="activity-domain">' . esc_html($activity->domain) . '</span>';
            }
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Get activity icon
     */
    private function get_activity_icon($event_type) {
        $icons = [
            'code_validation_success' => '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>',
            'code_validation_failed' => '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>',
            'conversion_recorded' => '<span class="dashicons dashicons-money-alt" style="color: #00a0d2;"></span>',
            'domain_authorized' => '<span class="dashicons dashicons-networking" style="color: #46b450;"></span>',
            'security_violation' => '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span>',
            'api_request' => '<span class="dashicons dashicons-rest-api" style="color: #826eb4;"></span>',
        ];

        return $icons[$event_type] ?? '<span class="dashicons dashicons-info"></span>';
    }

    /**
     * Format activity description
     */
    private function format_activity_description($activity) {
        $data = json_decode($activity->event_data, true);
        
        switch ($activity->event_type) {
            case 'code_validation_success':
                return sprintf(__('Code "%s" validated successfully', 'affiliate-cross-domain-full'), $data['code'] ?? 'Unknown');
                
            case 'code_validation_failed':
                return sprintf(__('Failed validation attempt for code "%s"', 'affiliate-cross-domain-full'), $data['code'] ?? 'Unknown');
                
            case 'conversion_recorded':
                return sprintf(__('Conversion recorded: %s', 'affiliate-cross-domain-full'), affcd_format_currency($data['amount'] ?? 0));
                
            case 'domain_authorized':
                return sprintf(__('Domain "%s" authorized', 'affiliate-cross-domain-full'), $data['domain'] ?? 'Unknown');
                
            case 'security_violation':
                return sprintf(__('Security violation: %s', 'affiliate-cross-domain-full'), $data['violation_type'] ?? 'Unknown');
                
            case 'api_request':
                return sprintf(__('API request to %s', 'affiliate-cross-domain-full'), $data['endpoint'] ?? 'unknown endpoint');
                
            default:
                return ucwords(str_replace('_', ' ', $activity->event_type));
        }
    }

    /**
     * Get overview statistics
     */
    private function get_overview_statistics() {
        $cache_key = $this->cache_prefix . 'overview_stats';
        $stats = wp_cache_get($cache_key, 'affcd_reports');
        
        if ($stats === false) {
            global $wpdb;
            
            $analytics_table = $wpdb->prefix . 'affcd_analytics';
            $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
            $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
            $usage_table = $wpdb->prefix . 'affcd_vanity_usage';
            
            // Get current period stats (last 30 days)
            $current_period_start = date('Y-m-d H:i:s', strtotime('-30 days'));
            $previous_period_start = date('Y-m-d H:i:s', strtotime('-60 days'));
            $previous_period_end = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            // Total validations
            $current_validations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$usage_table} WHERE created_at >= %s",
                $current_period_start
            ));
            
            $previous_validations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$usage_table} 
                 WHERE created_at >= %s AND created_at < %s",
                $previous_period_start,
                $previous_period_end
            ));
            
            // Total conversions (assuming conversion tracking in analytics table)
            $current_conversions = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$analytics_table} 
                 WHERE event_type = 'conversion' AND created_at >= %s",
                $current_period_start
            ));
            
            $previous_conversions = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$analytics_table} 
                 WHERE event_type = 'conversion' 
                 AND created_at >= %s AND created_at < %s",
                $previous_period_start,
                $previous_period_end
            ));
            
            // Calculate conversion rate
            $conversion_rate = $current_validations > 0 ? ($current_conversions / $current_validations) * 100 : 0;
            $previous_conversion_rate = $previous_validations > 0 ? ($previous_conversions / $previous_validations) * 100 : 0;
            
            // Domain counts
            $active_domains = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$domains_table} WHERE status = 'active'"
            );
            
            $total_domains = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$domains_table}"
            );
            
            $stats = [
                'total_validations' => intval($current_validations),
                'validations_change' => $previous_validations > 0 ? 
                    round((($current_validations - $previous_validations) / $previous_validations) * 100, 2) : 0,
                'total_conversions' => intval($current_conversions),
                'conversions_change' => $previous_conversions > 0 ? 
                    round((($current_conversions - $previous_conversions) / $previous_conversions) * 100, 2) : 0,
                'conversion_rate' => round($conversion_rate, 2),
                'conversion_rate_change' => round($conversion_rate - $previous_conversion_rate, 2),
                'active_domains' => intval($active_domains),
                'total_domains' => intval($total_domains)
            ];
            
            wp_cache_set($cache_key, $stats, 'affcd_reports', 900); // Cache for 15 minutes
        }
        
        return $stats;
    }

    /**
     * Get recent activity
     */
    private function get_recent_activity($limit = 10) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$analytics_table} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get active domains
     */
    private function get_active_domains() {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        
        return $wpdb->get_results(
            "SELECT domain_url FROM {$domains_table} 
             WHERE status = 'active' 
             ORDER BY domain_url ASC"
        );
    }

    /**
     * Get active affiliates
     */
    private function get_active_affiliates() {
        if (!function_exists('affiliate_wp')) {
            return [];
        }
        
        return affiliate_wp()->affiliates->get_affiliates([
            'status' => 'active',
            'orderby' => 'name',
            'order' => 'ASC'
        ]);
    }

    /**
     * AJAX: Get report data
     */
    public function ajax_get_report_data() {
        check_ajax_referer('affcd_reports_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain-full'));
        }
        
        $report_type = sanitize_text_field($_POST['report_type'] ?? '');
        $date_range = sanitize_text_field($_POST['date_range'] ?? '');
        $domain_filter = sanitize_text_field($_POST['domain_filter'] ?? '');
        $affiliate_filter = sanitize_text_field($_POST['affiliate_filter'] ?? '');
        
        $data = $this->get_report_data($report_type, [
            'date_range' => $date_range,
            'domain' => $domain_filter,
            'affiliate' => $affiliate_filter
        ]);
        
        wp_send_json_success($data);
    }

    /**
     * Get report data
     */
    private function get_report_data($report_type, $filters = []) {
        switch ($report_type) {
            case 'codes_performance':
                return $this->get_codes_performance_data($filters);
            case 'domains_analytics':
                return $this->get_domains_analytics_data($filters);
            case 'affiliates_performance':
                return $this->get_affiliates_performance_data($filters);
            case 'conversions':
                return $this->get_conversions_data($filters);
            case 'security_events':
                return $this->get_security_events_data($filters);
            case 'validation_trends':
                return $this->get_validation_trends_data($filters);
            case 'conversion_trends':
                return $this->get_conversion_trends_data($filters);
            default:
                return ['error' => __('Unknown report type', 'affiliate-cross-domain-full')];
        }
    }

    /**
     * Get codes performance data
     */
    private function get_codes_performance_data($filters) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $usage_table = $wpdb->prefix . 'affcd_vanity_usage';
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Apply date range filter
        if (!empty($filters['date_range'])) {
            $date_parts = explode(' - ', $filters['date_range']);
            if (count($date_parts) == 2) {
                $where_conditions[] = "u.created_at BETWEEN %s AND %s";
                $where_values[] = $date_parts[0] . ' 00:00:00';
                $where_values[] = $date_parts[1] . ' 23:59:59';
            }
        }
        
        // Apply domain filter
        if (!empty($filters['domain'])) {
            $where_conditions[] = "u.domain = %s";
            $where_values[] = $filters['domain'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                v.vanity_code,
                v.affiliate_id,
                COUNT(u.id) as validations,
                COUNT(CASE WHEN a.event_type = 'conversion' THEN 1 END) as conversions,
                COALESCE(SUM(CASE WHEN a.event_type = 'conversion' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(a.event_data, '$.amount')) AS DECIMAL(10,2)) END), 0) as revenue,
                MAX(u.created_at) as last_used
             FROM {$vanity_table} v
             LEFT JOIN {$usage_table} u ON v.id = u.vanity_code_id
             LEFT JOIN {$analytics_table} a ON a.entity_id = v.id AND a.entity_type = 'vanity_code'
             WHERE {$where_clause}
             GROUP BY v.id, v.vanity_code, v.affiliate_id
             ORDER BY validations DESC
             LIMIT 50",
            ...$where_values
        ));
        
        // Calculate conversion rates and format data
        $formatted_results = [];
        foreach ($results as $result) {
            $conversion_rate = $result->validations > 0 ? ($result->conversions / $result->validations) * 100 : 0;
            
            $formatted_results[] = [
                'code' => $result->vanity_code,
                'affiliate_id' => $result->affiliate_id,
                'affiliate_name' => $this->get_affiliate_name($result->affiliate_id),
                'validations' => intval($result->validations),
                'conversions' => intval($result->conversions),
                'conversion_rate' => round($conversion_rate, 2),
                'revenue' => floatval($result->revenue),
                'last_used' => $result->last_used
            ];
        }
        
        return $formatted_results;
    }

    /**
     * Get domains analytics data
     */
    private function get_domains_analytics_data($filters) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Apply date range filter for analytics
        if (!empty($filters['date_range'])) {
            $date_parts = explode(' - ', $filters['date_range']);
            if (count($date_parts) == 2) {
                $where_conditions[] = "a.created_at BETWEEN %s AND %s";
                $where_values[] = $date_parts[0] . ' 00:00:00';
                $where_values[] = $date_parts[1] . ' 23:59:59';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                d.domain_url,
                d.status,
                d.total_requests,
                d.blocked_requests,
                d.last_activity_at,
                COUNT(a.id) as analytics_events,
                AVG(a.response_time) as avg_response_time,
                COUNT(CASE WHEN a.event_type = 'api_request' THEN 1 END) as api_requests,
                COUNT(CASE WHEN a.event_type = 'validation_success' THEN 1 END) as successful_validations
             FROM {$domains_table} d
             LEFT JOIN {$analytics_table} a ON a.domain = d.domain_url AND {$where_clause}
             GROUP BY d.id, d.domain_url, d.status, d.total_requests, d.blocked_requests, d.last_activity_at
             ORDER BY d.total_requests DESC",
            ...$where_values
        ));
        
        $formatted_results = [];
        foreach ($results as $result) {
            $success_rate = $result->total_requests > 0 ? 
                (($result->total_requests - $result->blocked_requests) / $result->total_requests) * 100 : 0;
            
            $formatted_results[] = [
                'domain' => $result->domain_url,
                'status' => $result->status,
                'total_requests' => intval($result->total_requests),
                'success_rate' => round($success_rate, 2),
                'avg_response_time' => round(floatval($result->avg_response_time), 2),
                'last_activity' => $result->last_activity_at,
                'api_requests' => intval($result->api_requests),
                'successful_validations' => intval($result->successful_validations)
            ];
        }
        
        return $formatted_results;
    }

    /**
     * Get affiliates performance data
     */
    private function get_affiliates_performance_data($filters) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $usage_table = $wpdb->prefix . 'affcd_vanity_usage';
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Apply date range filter
        if (!empty($filters['date_range'])) {
            $date_parts = explode(' - ', $filters['date_range']);
            if (count($date_parts) == 2) {
                $where_conditions[] = "u.created_at BETWEEN %s AND %s";
                $where_values[] = $date_parts[0] . ' 00:00:00';
                $where_values[] = $date_parts[1] . ' 23:59:59';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                v.affiliate_id,
                COUNT(DISTINCT v.id) as codes_used,
                COUNT(u.id) as total_validations,
                COUNT(CASE WHEN a.event_type = 'conversion' THEN 1 END) as conversions,
                COALESCE(SUM(CASE WHEN a.event_type = 'conversion' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(a.event_data, '$.amount')) AS DECIMAL(10,2)) END), 0) as revenue,
                u.domain as top_domain,
                COUNT(CASE WHEN u.domain = (
                    SELECT domain FROM {$usage_table} u2 
                    WHERE u2.vanity_code_id IN (
                        SELECT id FROM {$vanity_table} v2 WHERE v2.affiliate_id = v.affiliate_id
                    )
                    GROUP BY domain ORDER BY COUNT(*) DESC LIMIT 1
                ) THEN 1 END) as top_domain_count
             FROM {$vanity_table} v
             LEFT JOIN {$usage_table} u ON v.id = u.vanity_code_id
             LEFT JOIN {$analytics_table} a ON a.entity_id = v.id AND a.entity_type = 'vanity_code'
             WHERE {$where_clause}
             GROUP BY v.affiliate_id
             ORDER BY total_validations DESC
             LIMIT 50",
            ...$where_values
        ));
        
        $formatted_results = [];
        foreach ($results as $result) {
            $conversion_rate = $result->total_validations > 0 ? 
                ($result->conversions / $result->total_validations) * 100 : 0;
            
            // Get top domain for this affiliate
            $top_domain = $wpdb->get_var($wpdb->prepare(
                "SELECT domain 
                 FROM {$usage_table} u
                 JOIN {$vanity_table} v ON u.vanity_code_id = v.id
                 WHERE v.affiliate_id = %d
                 GROUP BY domain 
                 ORDER BY COUNT(*) DESC 
                 LIMIT 1",
                $result->affiliate_id
            ));
            
            $formatted_results[] = [
                'affiliate_id' => $result->affiliate_id,
                'affiliate_name' => $this->get_affiliate_name($result->affiliate_id),
                'codes_used' => intval($result->codes_used),
                'total_validations' => intval($result->total_validations),
                'conversions' => intval($result->conversions),
                'conversion_rate' => round($conversion_rate, 2),
                'revenue' => floatval($result->revenue),
                'top_domain' => $top_domain ?: __('N/A', 'affiliate-cross-domain-full')
            ];
        }
        
        return $formatted_results;
    }

    /**
     * Get conversions data
     */
    private function get_conversions_data($filters) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        
        $where_conditions = ["a.event_type = 'conversion'"];
        $where_values = [];
        
        // Apply date range filter
        if (!empty($filters['date_range'])) {
            $date_parts = explode(' - ', $filters['date_range']);
            if (count($date_parts) == 2) {
                $where_conditions[] = "a.created_at BETWEEN %s AND %s";
                $where_values[] = $date_parts[0] . ' 00:00:00';
                $where_values[] = $date_parts[1] . ' 23:59:59';
            }
        }
        
        // Apply domain filter
        if (!empty($filters['domain'])) {
            $where_conditions[] = "a.domain = %s";
            $where_values[] = $filters['domain'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                a.created_at,
                a.domain,
                a.event_data,
                v.vanity_code,
                v.affiliate_id
             FROM {$analytics_table} a
             LEFT JOIN {$vanity_table} v ON a.entity_id = v.id AND a.entity_type = 'vanity_code'
             WHERE {$where_clause}
             ORDER BY a.created_at DESC
             LIMIT 100",
            ...$where_values
        ));
        
        $formatted_results = [];
        foreach ($results as $result) {
            $event_data = json_decode($result->event_data, true);
            
            $formatted_results[] = [
                'date' => $result->created_at,
                'code' => $result->vanity_code ?: __('Unknown', 'affiliate-cross-domain-full'),
                'affiliate_id' => $result->affiliate_id,
                'affiliate_name' => $this->get_affiliate_name($result->affiliate_id),
                'domain' => $result->domain,
                'amount' => floatval($event_data['amount'] ?? 0),
                'commission' => floatval($event_data['commission'] ?? 0),
                'status' => $event_data['status'] ?? 'pending'
            ];
        }
        
        return $formatted_results;
    }

    /**
     * Get security events data
     */
    private function get_security_events_data($filters) {
        if (!$this->security_manager) {
            return [];
        }
        
        $filter_array = [];
        
        // Apply date range filter
        if (!empty($filters['date_range'])) {
            $date_parts = explode(' - ', $filters['date_range']);
            if (count($date_parts) == 2) {
                $filter_array['date_from'] = $date_parts[0] . ' 00:00:00';
                $filter_array['date_to'] = $date_parts[1] . ' 23:59:59';
            }
        }
        
        // Apply domain filter
        if (!empty($filters['domain'])) {
            $filter_array['domain'] = $filters['domain'];
        }
        
        return $this->security_manager->get_security_logs(100, 0, $filter_array);
    }

    /**
     * Get validation trends data
     */
    private function get_validation_trends_data($filters) {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'affcd_vanity_usage';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Apply date range or default to last 30 days
        if (!empty($filters['date_range'])) {
            $date_parts = explode(' - ', $filters['date_range']);
            if (count($date_parts) == 2) {
                $where_conditions[] = "created_at BETWEEN %s AND %s";
                $where_values[] = $date_parts[0] . ' 00:00:00';
                $where_values[] = $date_parts[1] . ' 23:59:59';
            }
        } else {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as validations
             FROM {$usage_table}
             WHERE {$where_clause}
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            ...$where_values
        ));
        
        return [
            'labels' => array_column($results, 'date'),
            'data' => array_map('intval', array_column($results, 'validations'))
        ];
    }

    /**
     * Get conversion trends data
     */
    private function get_conversion_trends_data($filters) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        
        $where_conditions = ["event_type = 'conversion'"];
        $where_values = [];
        
        // Apply date range or default to last 30 days
        if (!empty($filters['date_range'])) {
            $date_parts = explode(' - ', $filters['date_range']);
            if (count($date_parts) == 2) {
                $where_conditions[] = "created_at BETWEEN %s AND %s";
                $where_values[] = $date_parts[0] . ' 00:00:00';
                $where_values[] = $date_parts[1] . ' 23:59:59';
            }
        } else {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as conversions,
                SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.amount')) AS DECIMAL(10,2))) as revenue
             FROM {$analytics_table}
             WHERE {$where_clause}
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            ...$where_values
        ));
        
        return [
            'labels' => array_column($results, 'date'),
            'conversions' => array_map('intval', array_column($results, 'conversions')),
            'revenue' => array_map('floatval', array_column($results, 'revenue'))
        ];
    }

    /**
     * Get affiliate name
     */
    private function get_affiliate_name($affiliate_id) {
        if (!function_exists('affwp_get_affiliate')) {
            return __('Unknown', 'affiliate-cross-domain-full');
        }
        
        $affiliate = affwp_get_affiliate($affiliate_id);
        if (!$affiliate) {
            return __('Unknown', 'affiliate-cross-domain-full');
        }
        
        $user = get_userdata($affiliate->user_id);
        return $user ? $user->display_name : __('Unknown', 'affiliate-cross-domain-full');
    }

    /**
     * AJAX: Export report
     */
    public function ajax_export_report() {
        check_ajax_referer('affcd_reports_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain-full'));
        }
        
        $report_type = sanitize_text_field($_POST['report_type'] ?? '');
        $date_range = sanitize_text_field($_POST['date_range'] ?? '');
        $domain_filter = sanitize_text_field($_POST['domain_filter'] ?? '');
        $affiliate_filter = sanitize_text_field($_POST['affiliate_filter'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        $data = $this->get_report_data($report_type, [
            'date_range' => $date_range,
            'domain' => $domain_filter,
            'affiliate' => $affiliate_filter
        ]);
        
        if (empty($data) || isset($data['error'])) {
            wp_send_json_error(__('No data to export', 'affiliate-cross-domain-full'));
        }
        
        $export_result = $this->export_report_data($data, $report_type, $format);
        
        if (is_wp_error($export_result)) {
            wp_send_json_error($export_result->get_error_message());
        }
        
        wp_send_json_success([
            'download_url' => $export_result['url'],
            'filename' => $export_result['filename']
        ]);
    }

    /**
     * Export report data
     */
    private function export_report_data($data, $report_type, $format = 'csv') {
        $upload_dir = wp_upload_dir();
        $exports_dir = $upload_dir['basedir'] . '/affcd-exports/';
        
        if (!file_exists($exports_dir)) {
            wp_mkdir_p($exports_dir);
        }
        
        $filename = 'affcd-' . $report_type . '-' . date('Y-m-d-H-i-s') . '.' . $format;
        $filepath = $exports_dir . $filename;
        
        switch ($format) {
            case 'csv':
                $this->export_to_csv($data, $filepath, $report_type);
                break;
            case 'json':
                $this->export_to_json($data, $filepath);
                break;
            default:
                return new WP_Error('invalid_format', __('Invalid export format', 'affiliate-cross-domain-full'));
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
    private function export_to_csv($data, $filepath, $report_type) {
        $file = fopen($filepath, 'w');
        
        if (empty($data)) {
            fputcsv($file, [__('No data available', 'affiliate-cross-domain-full')]);
            fclose($file);
            return;
        }
        
        // Write headers based on first row keys
        $headers = array_keys($data[0]);
        fputcsv($file, array_map('ucwords', str_replace('_', ' ', $headers)));
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
    }

    /**
     * Export to JSON
     */
    private function export_to_json($data, $filepath) {
        $export_data = [
            'generated_at' => current_time('mysql'),
            'data' => $data
        ];
        
        file_put_contents($filepath, wp_json_encode($export_data, JSON_PRETTY_PRINT));
    }

    /**
     * AJAX: Refresh cache
     */
    public function ajax_refresh_cache() {
        check_ajax_referer('affcd_reports_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain-full'));
        }
        
        // Clear all report caches
        wp_cache_flush_group('affcd_reports');
        
        // Clear specific cache keys
        $cache_keys = [
            'overview_stats',
            'codes_performance',
            'domains_analytics',
            'affiliates_performance',
            'conversions_data',
            'security_events'
        ];
        
        foreach ($cache_keys as $key) {
            wp_cache_delete($this->cache_prefix . $key, 'affcd_reports');
        }
        
        wp_send_json_success(__('Cache refreshed successfully', 'affiliate-cross-domain-full'));
    }
}

// Initialize the tracking reports
if (is_admin()) {
    new AFFCD_Tracking_Reports();
}