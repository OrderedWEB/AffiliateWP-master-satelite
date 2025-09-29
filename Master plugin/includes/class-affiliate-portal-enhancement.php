<?php
/**
 * Enhanced Affiliate Portal
 * 
 * Provides comprehensive self-service capabilities for affiliates including
 * earnings projections, marketing resources, and cross-site performance analytics
 * 
 * Filename: class-affiliate-portal-enhancement.php
 * Path: /wp-content/plugins/affiliate-master-enhancement/includes/
 * 
 * @package AffiliatePortalEnhancement
 * @author Richard King <r.king@starneconsulting.com>
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Affiliate Portal Class
 * Provides comprehensive affiliate information and self-service tools
 */
class AffiliatePortalEnhancement {
    
    /**
     * Cache duration for analytics data (in seconds)
     */
    const CACHE_DURATION = 3600; // 1 hour
    
    /**
     * Constructor - initialise hooks and actions
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_ame_get_dashboard_data', [$this, 'ajax_get_dashboard_data']);
        add_action('wp_ajax_ame_generate_marketing_materials', [$this, 'ajax_generate_marketing_materials']);
        add_action('wp_ajax_ame_calculate_projections', [$this, 'ajax_calculate_projections']);
        
        // Shortcode for affiliate dashboard
        add_shortcode('enhanced_affiliate_dashboard', [$this, 'render_dashboard_shortcode']);
    }
    
    /**
     * Initialise the portal enhancement
     */
    public function init() {
        if (!$this->is_affiliate_page()) {
            return;
        }
        
        // Enqueue additional scripts for enhanced dashboard
        add_action('wp_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
    }
    
    /**
     * Check if current page is an affiliate-related page
     * @return bool
     */
    private function is_affiliate_page() {
        global $post;
        
        return is_user_logged_in() && (
            (isset($post->post_content) && has_shortcode($post->post_content, 'enhanced_affiliate_dashboard')) ||
            is_page(['affiliate-dashboard', 'affiliate-portal']) ||
            (function_exists('affwp_get_affiliate_id') && affwp_get_affiliate_id())
        );
    }
    
    /**
     * Enqueue dashboard-specific assets
     */
    public function enqueue_dashboard_assets() {
        wp_enqueue_script(
            'ame-dashboard-js',
            AME_ASSETS_URL . 'js/affiliate-dashboard.js',
            ['jquery', 'chart-js'],
            AME_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ame-dashboard-css',
            AME_ASSETS_URL . 'css/affiliate-dashboard.css',
            [],
            AME_VERSION
        );
        
        // Enqueue Chart.js for analytics visualisation
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );
    }
    
    /**
     * Render enhanced affiliate dashboard
     * @param array $atts Shortcode attributes
     * @return string Dashboard HTML
     */
    public function render_dashboard_shortcode($atts = []) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your affiliate dashboard.', 'affiliate-master-enhancement') . '</p>';
        }
        
        $affiliate_id = $this->get_current_affiliate_id();
        if (!$affiliate_id) {
            return '<p>' . __('You must be a registered affiliate to access this dashboard.', 'affiliate-master-enhancement') . '</p>';
        }
        
        $atts = shortcode_atts([
            'show_projections' => 'true',
            'show_marketing' => 'true',
            'show_analytics' => 'true',
            'timeframe' => '30_days'
        ], $atts);
        
        ob_start();
        $this->render_enhanced_dashboard($affiliate_id, $atts);
        return ob_get_clean();
    }
    
    /**
     * Render the complete enhanced dashboard
     * @param int $affiliate_id Affiliate ID
     * @param array $options Dashboard options
     */
    private function render_enhanced_dashboard($affiliate_id, $options = []) {
        $dashboard_data = $this->get_dashboard_data($affiliate_id);
        ?>
        <div id="ame-enhanced-dashboard" class="ame-dashboard-container" data-affiliate-id="<?php echo esc_attr($affiliate_id); ?>">
            <!-- Dashboard Header -->
            <div class="ame-dashboard-header">
                <h2><?php _e('Enhanced Affiliate Dashboard', 'affiliate-master-enhancement'); ?></h2>
                <div class="ame-dashboard-summary">
                    <div class="ame-summary-card">
                        <h3><?php echo esc_html(number_format_i18n($dashboard_data['total_earnings'], 2)); ?></h3>
                        <p><?php _e('Total Earnings', 'affiliate-master-enhancement'); ?></p>
                    </div>
                    <div class="ame-summary-card">
                        <h3><?php echo esc_html($dashboard_data['active_referrals']); ?></h3>
                        <p><?php _e('Active Referrals', 'affiliate-master-enhancement'); ?></p>
                    </div>
                    <div class="ame-summary-card">
                        <h3><?php echo esc_html(number_format($dashboard_data['conversion_rate'] * 100, 2)); ?>%</h3>
                        <p><?php _e('Conversion Rate', 'affiliate-master-enhancement'); ?></p>
                    </div>
                    <div class="ame-summary-card">
                        <h3><?php echo esc_html($dashboard_data['cross_site_performance']['active_sites']); ?></h3>
                        <p><?php _e('Active Sites', 'affiliate-master-enhancement'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Performance Analytics Section -->
            <?php if ($options['show_analytics'] === 'true'): ?>
            <div class="ame-dashboard-section ame-analytics-section">
                <h3><?php _e('Performance Analytics', 'affiliate-master-enhancement'); ?></h3>
                <div class="ame-analytics-container">
                    <div class="ame-chart-container">
                        <canvas id="ame-earnings-chart"></canvas>
                    </div>
                    <div class="ame-metrics-grid">
                        <?php $this->render_performance_metrics($dashboard_data['performance_metrics']); ?>
                    </div>
                </div>
                
                <!-- Cross-Site Performance Breakdown -->
                <div class="ame-cross-site-performance">
                    <h4><?php _e('Performance by Site', 'affiliate-master-enhancement'); ?></h4>
                    <table class="ame-performance-table">
                        <thead>
                            <tr>
                                <th><?php _e('Site', 'affiliate-master-enhancement'); ?></th>
                                <th><?php _e('Clicks', 'affiliate-master-enhancement'); ?></th>
                                <th><?php _e('Conversions', 'affiliate-master-enhancement'); ?></th>
                                <th><?php _e('Conversion Rate', 'affiliate-master-enhancement'); ?></th>
                                <th><?php _e('Earnings', 'affiliate-master-enhancement'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboard_data['cross_site_performance']['sites'] as $site): ?>
                            <tr>
                                <td><?php echo esc_html($site['domain']); ?></td>
                                <td><?php echo esc_html(number_format_i18n($site['conversions'])); ?></td>
                                <td><?php echo esc_html(number_format($site['conversion_rate'] * 100, 2)); ?>%</td>
                                <td><?php echo esc_html(number_format_i18n($site['earnings'], 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Earnings Projections Section -->
            <?php if ($options['show_projections'] === 'true'): ?>
            <div class="ame-dashboard-section ame-projections-section">
                <h3><?php _e('Earnings Projections', 'affiliate-master-enhancement'); ?></h3>
                <div class="ame-projections-container">
                    <div class="ame-projection-cards">
                        <?php $projections = $dashboard_data['earnings_projections']; ?>
                        <div class="ame-projection-card">
                            <h4><?php _e('Next 30 Days', 'affiliate-master-enhancement'); ?></h4>
                            <p class="ame-projection-amount"><?php echo esc_html(number_format_i18n($projections['30_days'], 2)); ?></p>
                            <p class="ame-projection-confidence"><?php echo esc_html($projections['30_days_confidence']); ?>% <?php _e('confidence', 'affiliate-master-enhancement'); ?></p>
                        </div>
                        <div class="ame-projection-card">
                            <h4><?php _e('Next Quarter', 'affiliate-master-enhancement'); ?></h4>
                            <p class="ame-projection-amount"><?php echo esc_html(number_format_i18n($projections['90_days'], 2)); ?></p>
                            <p class="ame-projection-confidence"><?php echo esc_html($projections['90_days_confidence']); ?>% <?php _e('confidence', 'affiliate-master-enhancement'); ?></p>
                        </div>
                        <div class="ame-projection-card">
                            <h4><?php _e('Annual Projection', 'affiliate-master-enhancement'); ?></h4>
                            <p class="ame-projection-amount"><?php echo esc_html(number_format_i18n($projections['365_days'], 2)); ?></p>
                            <p class="ame-projection-confidence"><?php echo esc_html($projections['365_days_confidence']); ?>% <?php _e('confidence', 'affiliate-master-enhancement'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ame-projection-factors">
                        <h4><?php _e('Projection Factors', 'affiliate-master-enhancement'); ?></h4>
                        <ul>
                            <?php foreach ($projections['factors'] as $factor): ?>
                            <li><?php echo esc_html($factor); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Marketing Resources Section -->
            <?php if ($options['show_marketing'] === 'true'): ?>
            <div class="ame-dashboard-section ame-marketing-section">
                <h3><?php _e('Marketing Resources', 'affiliate-master-enhancement'); ?></h3>
                <div class="ame-marketing-container">
                    <div class="ame-marketing-tabs">
                        <button class="ame-tab-button active" data-tab="banners"><?php _e('Banners', 'affiliate-master-enhancement'); ?></button>
                        <button class="ame-tab-button" data-tab="email"><?php _e('Email Templates', 'affiliate-master-enhancement'); ?></button>
                        <button class="ame-tab-button" data-tab="social"><?php _e('Social Media', 'affiliate-master-enhancement'); ?></button>
                        <button class="ame-tab-button" data-tab="landing"><?php _e('Landing Pages', 'affiliate-master-enhancement'); ?></button>
                    </div>
                    
                    <div class="ame-marketing-content">
                        <div id="banners-tab" class="ame-tab-content active">
                            <?php $this->render_banner_resources($affiliate_id); ?>
                        </div>
                        <div id="email-tab" class="ame-tab-content">
                            <?php $this->render_email_templates($affiliate_id); ?>
                        </div>
                        <div id="social-tab" class="ame-tab-content">
                            <?php $this->render_social_media_content($affiliate_id); ?>
                        </div>
                        <div id="landing-tab" class="ame-tab-content">
                            <?php $this->render_landing_page_snippets($affiliate_id); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions Section -->
            <div class="ame-dashboard-section ame-actions-section">
                <h3><?php _e('Quick Actions', 'affiliate-master-enhancement'); ?></h3>
                <div class="ame-actions-grid">
                    <button class="ame-action-button" onclick="ameGenerateReport()"><?php _e('Generate Report', 'affiliate-master-enhancement'); ?></button>
                    <button class="ame-action-button" onclick="ameExportData()"><?php _e('Export Data', 'affiliate-master-enhancement'); ?></button>
                    <button class="ame-action-button" onclick="ameUpdateMaterials()"><?php _e('Update Materials', 'affiliate-master-enhancement'); ?></button>
                    <button class="ame-action-button" onclick="ameViewAnalytics()"><?php _e('Detailed Analytics', 'affiliate-master-enhancement'); ?></button>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialise dashboard functionality
            ameInitialiseDashboard();
            
            // Load earnings chart
            ameLoadEarningsChart(<?php echo json_encode($dashboard_data['chart_data']); ?>);
        });
        </script>
        <?php
    }
    
    /**
     * Render performance metrics grid
     * @param array $metrics Performance metrics data
     */
    private function render_performance_metrics($metrics) {
        foreach ($metrics as $metric_key => $metric_data) {
            ?>
            <div class="ame-metric-card">
                <div class="ame-metric-value"><?php echo esc_html($metric_data['value']); ?></div>
                <div class="ame-metric-label"><?php echo esc_html($metric_data['label']); ?></div>
                <div class="ame-metric-change <?php echo esc_attr($metric_data['trend']); ?>">
                    <?php echo esc_html($metric_data['change']); ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render banner resources section
     * @param int $affiliate_id Affiliate ID
     */
    private function render_banner_resources($affiliate_id) {
        $banners = $this->get_banner_resources($affiliate_id);
        ?>
        <div class="ame-banner-grid">
            <?php foreach ($banners as $banner): ?>
            <div class="ame-banner-item">
                <div class="ame-banner-preview">
                    <img src="<?php echo esc_url($banner['preview_url']); ?>" alt="<?php echo esc_attr($banner['name']); ?>">
                </div>
                <div class="ame-banner-details">
                    <h4><?php echo esc_html($banner['name']); ?></h4>
                    <p><?php echo esc_html($banner['dimensions']); ?></p>
                    <div class="ame-banner-actions">
                        <button class="ame-btn-primary" onclick="ameCopyBannerCode('<?php echo esc_js($banner['code']); ?>')"><?php _e('Copy Code', 'affiliate-master-enhancement'); ?></button>
                        <a href="<?php echo esc_url($banner['download_url']); ?>" class="ame-btn-secondary" download><?php _e('Download', 'affiliate-master-enhancement'); ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="ame-custom-banner-generator">
            <h4><?php _e('Custom Banner Generator', 'affiliate-master-enhancement'); ?></h4>
            <form id="ame-banner-generator-form">
                <div class="ame-form-row">
                    <label for="banner-text"><?php _e('Banner Text', 'affiliate-master-enhancement'); ?></label>
                    <input type="text" id="banner-text" name="banner_text" maxlength="50">
                </div>
                <div class="ame-form-row">
                    <label for="banner-size"><?php _e('Size', 'affiliate-master-enhancement'); ?></label>
                    <select id="banner-size" name="banner_size">
                        <option value="728x90"><?php _e('Leaderboard (728x90)', 'affiliate-master-enhancement'); ?></option>
                        <option value="300x250"><?php _e('Medium Rectangle (300x250)', 'affiliate-master-enhancement'); ?></option>
                        <option value="160x600"><?php _e('Wide Skyscraper (160x600)', 'affiliate-master-enhancement'); ?></option>
                        <option value="320x50"><?php _e('Mobile Banner (320x50)', 'affiliate-master-enhancement'); ?></option>
                    </select>
                </div>
                <button type="button" onclick="ameGenerateBanner()"><?php _e('Generate Banner', 'affiliate-master-enhancement'); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render email templates section
     * @param int $affiliate_id Affiliate ID
     */
    private function render_email_templates($affiliate_id) {
        $templates = $this->get_email_templates($affiliate_id);
        ?>
        <div class="ame-email-templates">
            <?php foreach ($templates as $template): ?>
            <div class="ame-template-item">
                <h4><?php echo esc_html($template['name']); ?></h4>
                <p><?php echo esc_html($template['description']); ?></p>
                <div class="ame-template-preview">
                    <strong><?php _e('Subject:', 'affiliate-master-enhancement'); ?></strong> <?php echo esc_html($template['subject']); ?><br>
                    <div class="ame-email-body-preview"><?php echo wp_kses_post($template['body_preview']); ?></div>
                </div>
                <div class="ame-template-actions">
                    <button class="ame-btn-primary" onclick="ameCopyEmailTemplate('<?php echo esc_js($template['id']); ?>')"><?php _e('Copy Template', 'affiliate-master-enhancement'); ?></button>
                    <button class="ame-btn-secondary" onclick="amePreviewEmail('<?php echo esc_js($template['id']); ?>')"><?php _e('Full Preview', 'affiliate-master-enhancement'); ?></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render social media content section
     * @param int $affiliate_id Affiliate ID
     */
    private function render_social_media_content($affiliate_id) {
        $social_content = $this->get_social_media_content($affiliate_id);
        ?>
        <div class="ame-social-content">
            <div class="ame-social-tabs">
                <button class="ame-social-tab active" data-platform="facebook"><?php _e('Facebook', 'affiliate-master-enhancement'); ?></button>
                <button class="ame-social-tab" data-platform="twitter"><?php _e('Twitter', 'affiliate-master-enhancement'); ?></button>
                <button class="ame-social-tab" data-platform="linkedin"><?php _e('LinkedIn', 'affiliate-master-enhancement'); ?></button>
                <button class="ame-social-tab" data-platform="instagram"><?php _e('Instagram', 'affiliate-master-enhancement'); ?></button>
            </div>
            
            <?php foreach ($social_content as $platform => $posts): ?>
            <div id="<?php echo esc_attr($platform); ?>-content" class="ame-social-platform-content <?php echo $platform === 'facebook' ? 'active' : ''; ?>">
                <?php foreach ($posts as $post): ?>
                <div class="ame-social-post">
                    <div class="ame-post-content"><?php echo esc_html($post['content']); ?></div>
                    <?php if (!empty($post['image'])): ?>
                    <div class="ame-post-image">
                        <img src="<?php echo esc_url($post['image']); ?>" alt="Social media post image">
                    </div>
                    <?php endif; ?>
                    <div class="ame-post-actions">
                        <button class="ame-btn-primary" onclick="ameCopySocialPost('<?php echo esc_js($post['content']); ?>')"><?php _e('Copy Post', 'affiliate-master-enhancement'); ?></button>
                        <?php if (!empty($post['hashtags'])): ?>
                        <button class="ame-btn-secondary" onclick="ameCopyHashtags('<?php echo esc_js($post['hashtags']); ?>')"><?php _e('Copy Hashtags', 'affiliate-master-enhancement'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render landing page snippets section
     * @param int $affiliate_id Affiliate ID
     */
    private function render_landing_page_snippets($affiliate_id) {
        $snippets = $this->get_landing_page_snippets($affiliate_id);
        ?>
        <div class="ame-landing-snippets">
            <?php foreach ($snippets as $snippet): ?>
            <div class="ame-snippet-item">
                <h4><?php echo esc_html($snippet['name']); ?></h4>
                <p><?php echo esc_html($snippet['description']); ?></p>
                <div class="ame-snippet-code">
                    <pre><code><?php echo esc_html($snippet['code']); ?></code></pre>
                </div>
                <div class="ame-snippet-actions">
                    <button class="ame-btn-primary" onclick="ameCopySnippet('<?php echo esc_js($snippet['code']); ?>')"><?php _e('Copy Code', 'affiliate-master-enhancement'); ?></button>
                    <button class="ame-btn-secondary" onclick="amePreviewSnippet('<?php echo esc_js($snippet['id']); ?>')"><?php _e('Preview', 'affiliate-master-enhancement'); ?></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Get comprehensive dashboard data for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Dashboard data
     */
    public function get_dashboard_data($affiliate_id) {
        $cache_key = 'ame_dashboard_data_' . $affiliate_id;
        $cached_data = wp_cache_get($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        global $wpdb;
        
        // Get basic affiliate stats
        $basic_stats = $this->get_basic_affiliate_stats($affiliate_id);
        
        // Get cross-site performance data
        $cross_site_performance = $this->get_cross_site_performance($affiliate_id);
        
        // Get performance metrics
        $performance_metrics = $this->get_performance_metrics($affiliate_id);
        
        // Calculate earnings projections
        $earnings_projections = $this->calculate_earnings_projections($affiliate_id);
        
        // Get chart data for visualisations
        $chart_data = $this->get_chart_data($affiliate_id);
        
        $dashboard_data = [
            'total_earnings' => $basic_stats['total_earnings'],
            'active_referrals' => $basic_stats['active_referrals'],
            'conversion_rate' => $basic_stats['conversion_rate'],
            'cross_site_performance' => $cross_site_performance,
            'performance_metrics' => $performance_metrics,
            'earnings_projections' => $earnings_projections,
            'chart_data' => $chart_data,
            'last_updated' => current_time('mysql')
        ];
        
        // Cache for 1 hour
        wp_cache_set($cache_key, $dashboard_data, '', self::CACHE_DURATION);
        
        return $dashboard_data;
    }
    
    /**
     * Get basic affiliate statistics
     * @param int $affiliate_id Affiliate ID
     * @return array Basic stats
     */
    private function get_basic_affiliate_stats($affiliate_id) {
        if (!function_exists('affwp_get_affiliate_earnings') || !function_exists('affwp_count_referrals')) {
            return [
                'total_earnings' => 0,
                'active_referrals' => 0,
                'conversion_rate' => 0
            ];
        }
        
        $total_earnings = affwp_get_affiliate_earnings($affiliate_id);
        $active_referrals = affwp_count_referrals($affiliate_id, 'paid');
        $total_visits = affwp_get_affiliate_visit_count($affiliate_id);
        
        $conversion_rate = $total_visits > 0 ? ($active_referrals / $total_visits) : 0;
        
        return [
            'total_earnings' => $total_earnings,
            'active_referrals' => $active_referrals,
            'conversion_rate' => $conversion_rate
        ];
    }
    
    /**
     * Get cross-site performance data
     * @param int $affiliate_id Affiliate ID
     * @return array Cross-site performance data
     */
    private function get_cross_site_performance($affiliate_id) {
        global $wpdb;
        
        $sites_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                lp.domain,
                lp.clicks,
                lp.conversions,
                lp.revenue as earnings,
                CASE 
                    WHEN lp.clicks > 0 THEN lp.conversions / lp.clicks
                    ELSE 0
                END as conversion_rate
            FROM {$wpdb->prefix}affiliate_link_performance lp
            WHERE lp.affiliate_id = %d
            AND lp.status = 'active'
            ORDER BY lp.revenue DESC
        ", $affiliate_id));
        
        $active_sites = count($sites_data);
        
        return [
            'active_sites' => $active_sites,
            'sites' => $sites_data ?: []
        ];
    }
    
    /**
     * Get performance metrics for display
     * @param int $affiliate_id Affiliate ID
     * @return array Performance metrics
     */
    private function get_performance_metrics($affiliate_id) {
        global $wpdb;
        
        // Get metrics from the last 30 days and compare with previous 30 days
        $current_period_start = date('Y-m-d', strtotime('-30 days'));
        $previous_period_start = date('Y-m-d', strtotime('-60 days'));
        $previous_period_end = date('Y-m-d', strtotime('-30 days'));
        
        $current_metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN metric_type = 'earnings' THEN metric_value ELSE 0 END) as earnings,
                SUM(CASE WHEN metric_type = 'clicks' THEN metric_value ELSE 0 END) as clicks,
                SUM(CASE WHEN metric_type = 'conversions' THEN metric_value ELSE 0 END) as conversions,
                AVG(CASE WHEN metric_type = 'conversion_rate' THEN metric_value ELSE NULL END) as avg_conversion_rate
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE affiliate_id = %d
            AND date_recorded >= %s
        ", $affiliate_id, $current_period_start), ARRAY_A);
        
        $previous_metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN metric_type = 'earnings' THEN metric_value ELSE 0 END) as earnings,
                SUM(CASE WHEN metric_type = 'clicks' THEN metric_value ELSE 0 END) as clicks,
                SUM(CASE WHEN metric_type = 'conversions' THEN metric_value ELSE 0 END) as conversions,
                AVG(CASE WHEN metric_type = 'conversion_rate' THEN metric_value ELSE NULL END) as avg_conversion_rate
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE affiliate_id = %d
            AND date_recorded >= %s
            AND date_recorded < %s
        ", $affiliate_id, $previous_period_start, $previous_period_end), ARRAY_A);
        
        return [
            'clicks' => [
                'value' => number_format_i18n($current_metrics['clicks'] ?? 0),
                'label' => __('Clicks (30 days)', 'affiliate-master-enhancement'),
                'change' => $this->calculate_percentage_change($current_metrics['clicks'] ?? 0, $previous_metrics['clicks'] ?? 0),
                'trend' => $this->determine_trend($current_metrics['clicks'] ?? 0, $previous_metrics['clicks'] ?? 0)
            ],
            'conversions' => [
                'value' => number_format_i18n($current_metrics['conversions'] ?? 0),
                'label' => __('Conversions (30 days)', 'affiliate-master-enhancement'),
                'change' => $this->calculate_percentage_change($current_metrics['conversions'] ?? 0, $previous_metrics['conversions'] ?? 0),
                'trend' => $this->determine_trend($current_metrics['conversions'] ?? 0, $previous_metrics['conversions'] ?? 0)
            ],
            'avg_order_value' => [
                'value' => number_format_i18n($this->calculate_average_order_value($affiliate_id), 2),
                'label' => __('Avg. Order Value', 'affiliate-master-enhancement'),
                'change' => $this->get_aov_change($affiliate_id),
                'trend' => $this->get_aov_trend($affiliate_id)
            ],
            'commission_rate' => [
                'value' => number_format($this->get_effective_commission_rate($affiliate_id) * 100, 2) . '%',
                'label' => __('Effective Commission Rate', 'affiliate-master-enhancement'),
                'change' => '',
                'trend' => 'neutral'
            ]
        ];
    }
    
    /**
     * Calculate earnings projections based on historical data
     * @param int $affiliate_id Affiliate ID
     * @param string $timeframe Timeframe for projection
     * @return array Earnings projections
     */
    public function calculate_earnings_projections($affiliate_id, $timeframe = '30_days') {
        global $wpdb;
        
        // Get historical earnings data for trend analysis
        $historical_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date_recorded,
                SUM(CASE WHEN metric_type = 'earnings' THEN metric_value ELSE 0 END) as daily_earnings
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE affiliate_id = %d
            AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY date_recorded
            ORDER BY date_recorded ASC
        ", $affiliate_id));
        
        if (empty($historical_data)) {
            return $this->get_default_projections();
        }
        
        // Calculate trend and seasonal factors
        $trend_analysis = $this->analyse_earnings_trend($historical_data);
        $seasonal_factors = $this->calculate_seasonal_factors($historical_data);
        
        // Project future earnings
        $projections = [
            '30_days' => $this->project_earnings($trend_analysis, $seasonal_factors, 30),
            '90_days' => $this->project_earnings($trend_analysis, $seasonal_factors, 90),
            '365_days' => $this->project_earnings($trend_analysis, $seasonal_factors, 365)
        ];
        
        // Calculate confidence levels based on data consistency
        $confidence_levels = $this->calculate_confidence_levels($historical_data, $trend_analysis);
        
        return [
            '30_days' => $projections['30_days'],
            '30_days_confidence' => $confidence_levels['30_days'],
            '90_days' => $projections['90_days'],
            '90_days_confidence' => $confidence_levels['90_days'],
            '365_days' => $projections['365_days'],
            '365_days_confidence' => $confidence_levels['365_days'],
            'factors' => [
                __('Historical performance trends', 'affiliate-master-enhancement'),
                __('Seasonal variations', 'affiliate-master-enhancement'),
                __('Market conditions', 'affiliate-master-enhancement'),
                __('Commission rate changes', 'affiliate-master-enhancement')
            ]
        ];
    }
    
    /**
     * Get current user's affiliate ID
     * @return int|null Affiliate ID or null if not an affiliate
     */
    private function get_current_affiliate_id() {
        if (!function_exists('affwp_get_affiliate_id')) {
            return null;
        }
        
        return affwp_get_affiliate_id(get_current_user_id());
    }
    
    /**
     * Calculate percentage change between two values
     * @param float $current Current value
     * @param float $previous Previous value
     * @return string Formatted percentage change
     */
    private function calculate_percentage_change($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }
        
        $change = (($current - $previous) / $previous) * 100;
        return ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
    }
    
    /**
     * Determine trend direction
     * @param float $current Current value
     * @param float $previous Previous value
     * @return string Trend direction (up, down, neutral)
     */
    private function determine_trend($current, $previous) {
        if ($current > $previous) {
            return 'up';
        } elseif ($current < $previous) {
            return 'down';
        }
        return 'neutral';
    }
    
    /**
     * AJAX handler for dashboard data
     */
    public function ajax_get_dashboard_data() {
        check_ajax_referer('ame_dashboard_nonce', 'nonce');
        
        $affiliate_id = intval($_POST['affiliate_id'] ?? 0);
        
        if (!$affiliate_id || $affiliate_id !== $this->get_current_affiliate_id()) {
            wp_die(__('Unauthorised access', 'affiliate-master-enhancement'));
        }
        
        $dashboard_data = $this->get_dashboard_data($affiliate_id);
        wp_send_json_success($dashboard_data);
    }
    
    /**
     * AJAX handler for generating marketing materials
     */
    public function ajax_generate_marketing_materials() {
        check_ajax_referer('ame_marketing_nonce', 'nonce');
        
        $affiliate_id = intval($_POST['affiliate_id'] ?? 0);
        $material_type = sanitize_text_field($_POST['material_type'] ?? '');
        
        if (!$affiliate_id || $affiliate_id !== $this->get_current_affiliate_id()) {
            wp_die(__('Unauthorised access', 'affiliate-master-enhancement'));
        }
        
        $materials = $this->generate_marketing_materials($affiliate_id, $material_type);
        wp_send_json_success($materials);
    }
    
    /**
     * AJAX handler for calculating projections
     */
    public function ajax_calculate_projections() {
        check_ajax_referer('ame_projections_nonce', 'nonce');
        
        $affiliate_id = intval($_POST['affiliate_id'] ?? 0);
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '30_days');
        
        if (!$affiliate_id || $affiliate_id !== $this->get_current_affiliate_id()) {
            wp_die(__('Unauthorised access', 'affiliate-master-enhancement'));
        }
        
        $projections = $this->calculate_earnings_projections($affiliate_id, $timeframe);
        wp_send_json_success($projections);
    }
    
    /**
     * Get banner resources for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Banner resources
     */
    
    /**
     * Get email templates for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Email templates
     */
    
    /**
     * Get social media content for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Social media content by platform
     */
    
    /**
     * Get landing page snippets for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Landing page snippets
     */
    
    /**
     * Get email template content
     * @param string $template_id Template ID
     * @param string $affiliate_code Affiliate referral URL
     * @return string Email template HTML content
     */
    private function get_email_template_content($template_id, $affiliate_code) {
        $templates = [
            'welcome_series_1' => '
                <html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h1 style="color: #2c3e50;">Welcome to Our Community!</h1>
                        <p>Thank you for joining us! As a valued subscriber, you\'re getting exclusive access to products and offers that can transform your daily routine.</p>
                        <div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;">
                            <h3>Special Welcome Offer</h3>
                            <p>Get started with our most popular product at an exclusive discount - just for new subscribers!</p>
                            <a href="' . esc_url($affiliate_code) . '" style="display: inline-block; background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Claim Your Offer</a>
                        </div>
                        <p>Looking forward to helping you achieve your goals!</p>
                        <p>Best regards,<br>The Team</p>
                    </div>
                </body></html>',
            
            'product_promotion' => '
                <html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h1 style="color: #2c3e50;">I Have Something Exciting to Share!</h1>
                        <p>I wanted to share something exciting with you - a product that has completely changed how I approach [specific area].</p>
                        <div style="background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0;">
                            <h3>Why I Love This Product:</h3>
                            <ul>
                                <li>Saves me hours every week</li>
                                <li>Simple to use, powerful results</li>
                                <li>Excellent customer support</li>
                                <li>Great value for money</li>
                            </ul>
                        </div>
                        <p>I genuinely believe this could help you too, which is why I wanted to share it.</p>
                        <a href="' . esc_url($affiliate_code) . '" style="display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Check It Out Here</a>
                        <p>Let me know what you think!</p>
                    </div>
                </body></html>',
                
            'seasonal_offer' => '
                <html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h1 style="color: #c41e3a;">🎄 Special Holiday Offer Inside!</h1>
                        <p>The holidays are here, and I\'ve got something special for you!</p>
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin: 20px 0; text-align: center; border-radius: 8px;">
                            <h2 style="margin: 0 0 10px 0;">Limited Time Holiday Sale</h2>
                            <p style="font-size: 18px; margin: 0 0 15px 0;">Save up to 40% on our most popular products!</p>
                            <a href="' . esc_url($affiliate_code) . '" style="display: inline-block; background: #fff; color: #667eea; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">Shop Holiday Sale</a>
                        </div>
                        <p>This offer expires soon, so don\'t wait too long!</p>
                        <p>Wishing you happy holidays!</p>
                    </div>
                </body></html>'
        ];
        
        return $templates[$template_id] ?? '';
    }
    
    /**
     * Calculate average order value for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return float Average order value
     */
    private function calculate_average_order_value($affiliate_id) {
        if (!function_exists('affwp_get_referrals')) {
            return 0;
        }
        
        $referrals = affwp_get_referrals([
            'affiliate_id' => $affiliate_id,
            'status' => 'paid',
            'number' => 100
        ]);
        
        if (empty($referrals)) {
            return 0;
        }
        
        $total_amount = 0;
        $count = 0;
        
        foreach ($referrals as $referral) {
            if ($referral->amount > 0) {
                $total_amount += $referral->amount;
                $count++;
            }
        }
        
        return $count > 0 ? ($total_amount / $count) : 0;
    }
    
    /**
     * Get effective commission rate for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return float Effective commission rate
     */
    private function get_effective_commission_rate($affiliate_id) {
        if (!function_exists('affwp_get_affiliate_rate')) {
            return 0;
        }
        
        return affwp_get_affiliate_rate($affiliate_id) ?: 0;
    }
    
    /**
     * Analyse earnings trend from historical data
     * @param array $historical_data Historical earnings data
     * @return array Trend analysis results
     */
    private function analyse_earnings_trend($historical_data) {
        if (count($historical_data) < 7) {
            return [
                'trend' => 'stable',
                'growth_rate' => 0,
                'volatility' => 0
            ];
        }
        
        $values = array_column($historical_data, 'daily_earnings');
        $count = count($values);
        
        // Calculate simple linear trend
        $x_sum = array_sum(range(1, $count));
        $y_sum = array_sum($values);
        $xy_sum = 0;
        $x_squared_sum = 0;
        
        for ($i = 0; $i < $count; $i++) {
            $x = $i + 1;
            $y = $values[$i];
            $xy_sum += $x * $y;
            $x_squared_sum += $x * $x;
        }
        
        $slope = ($count * $xy_sum - $x_sum * $y_sum) / ($count * $x_squared_sum - $x_sum * $x_sum);
        $average = $y_sum / $count;
        
        // Calculate volatility (standard deviation)
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $average, 2);
        }
        $volatility = sqrt($variance / $count);
        
        return [
            'trend' => $slope > 0.1 ? 'growing' : ($slope < -0.1 ? 'declining' : 'stable'),
            'growth_rate' => $slope,
            'volatility' => $volatility,
            'average' => $average
        ];
    }
    
    /**
     * Calculate seasonal factors from historical data
     * @param array $historical_data Historical earnings data
     * @return array Seasonal factors
     */
    private function calculate_seasonal_factors($historical_data) {
        $monthly_averages = [];
        
        foreach ($historical_data as $data_point) {
            $month = date('n', strtotime($data_point->date_recorded));
            if (!isset($monthly_averages[$month])) {
                $monthly_averages[$month] = [];
            }
            $monthly_averages[$month][] = $data_point->daily_earnings;
        }
        
        $seasonal_factors = [];
        $overall_average = array_sum(array_column($historical_data, 'daily_earnings')) / count($historical_data);
        
        for ($month = 1; $month <= 12; $month++) {
            if (isset($monthly_averages[$month])) {
                $month_average = array_sum($monthly_averages[$month]) / count($monthly_averages[$month]);
                $seasonal_factors[$month] = $overall_average > 0 ? ($month_average / $overall_average) : 1;
            } else {
                $seasonal_factors[$month] = 1; // No data, assume average
            }
        }
        
        return $seasonal_factors;
    }
    
    /**
     * Project earnings based on trend and seasonal factors
     * @param array $trend_analysis Trend analysis results
     * @param array $seasonal_factors Seasonal factors
     * @param int $days Number of days to project
     * @return float Projected earnings
     */
    private function project_earnings($trend_analysis, $seasonal_factors, $days) {
        $base_daily_earnings = $trend_analysis['average'];
        $growth_rate = $trend_analysis['growth_rate'];
        
        $total_projection = 0;
        $current_date = new DateTime();
        
        for ($i = 1; $i <= $days; $i++) {
            $future_date = clone $current_date;
            $future_date->add(new DateInterval("P{$i}D"));
            $month = (int)$future_date->format('n');
            
            $seasonal_factor = $seasonal_factors[$month] ?? 1;
            $trend_adjustment = $base_daily_earnings + ($growth_rate * $i);
            
            $daily_projection = $trend_adjustment * $seasonal_factor;
            $total_projection += max(0, $daily_projection); // Don't allow negative projections
        }
        
        return $total_projection;
    }
    
    /**
     * Calculate confidence levels for projections
     * @param array $historical_data Historical data
     * @param array $trend_analysis Trend analysis
     * @return array Confidence levels
     */
    private function calculate_confidence_levels($historical_data, $trend_analysis) {
        $data_points = count($historical_data);
        $volatility = $trend_analysis['volatility'];
        $average = $trend_analysis['average'];
        
        // Base confidence on data points and volatility
        $base_confidence = min(90, 50 + ($data_points * 2)); // More data = higher confidence
        
        // Reduce confidence based on volatility
        $volatility_factor = $average > 0 ? ($volatility / $average) : 1;
        $volatility_adjustment = max(0.5, 1 - $volatility_factor);
        
        return [
            '30_days' => round($base_confidence * $volatility_adjustment),
            '90_days' => round($base_confidence * $volatility_adjustment * 0.9), // Slightly less confident for longer periods
            '365_days' => round($base_confidence * $volatility_adjustment * 0.7) // Much less confident for very long periods
        ];
    }
    
    /**
     * Get default projections when no data available
     * @return array Default projection structure
     */
    private function get_default_projections() {
        return [
            '30_days' => 0,
            '30_days_confidence' => 0,
            '90_days' => 0,
            '90_days_confidence' => 0,
            '365_days' => 0,
            '365_days_confidence' => 0,
            'factors' => [
                __('Insufficient historical data for accurate projections', 'affiliate-master-enhancement'),
                __('Continue building performance history for better forecasts', 'affiliate-master-enhancement')
            ]
        ];
    }
    
    /**
     * Get chart data for dashboard visualisations
     * @param int $affiliate_id Affiliate ID
     * @return array Chart data
     */
    private function get_chart_data($affiliate_id) {
        global $wpdb;
        
        // Get last 30 days of earnings data
        $earnings_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date_recorded,
                SUM(CASE WHEN metric_type = 'earnings' THEN metric_value ELSE 0 END) as daily_earnings
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE affiliate_id = %d
            AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY date_recorded
            ORDER BY date_recorded ASC
        ", $affiliate_id));
        
        $labels = [];
        $earnings = [];
        
        // Fill in missing days with zero values
        $start_date = new DateTime('-30 days');
        $end_date = new DateTime();
        
        $earnings_by_date = [];
        foreach ($earnings_data as $data) {
            $earnings_by_date[$data->date_recorded] = $data->daily_earnings;
        }
        
        while ($start_date <= $end_date) {
            $date_string = $start_date->format('Y-m-d');
            $labels[] = $start_date->format('M j');
            $earnings[] = $earnings_by_date[$date_string] ?? 0;
            $start_date->add(new DateInterval('P1D'));
        }
        
        return [
            'labels' => $labels,
            'earnings' => $earnings
        ];
    }
    
    /**
     * Get AOV change percentage
     * @param int $affiliate_id Affiliate ID
     * @return string AOV change percentage
     */
    private function get_aov_change($affiliate_id) {
        // Implement AOV change calculation
        return '+5.2%'; // Placeholder
    }
    
    /**
     * Get AOV trend direction
     * @param int $affiliate_id Affiliate ID
     * @return string Trend direction
     */
    private function get_aov_trend($affiliate_id) {
        // Implement AOV trend calculation
        return 'up'; // Placeholder
    }
    
    /**
     * Generate marketing materials dynamically
     * @param int $affiliate_id Affiliate ID
     * @param string $material_type Type of material to generate
     * @return array Generated materials
     */
    private function generate_marketing_materials($affiliate_id, $material_type) {
        switch ($material_type) {
            case 'banner':
                return $this->get_banner_resources($affiliate_id);
            case 'email':
                return $this->get_email_templates($affiliate_id);
            case 'social':
                return $this->get_social_media_content($affiliate_id);
            case 'landing':
                return $this->get_landing_page_snippets($affiliate_id);
            default:
                return [];
        }
    }

/**
     * Get banner resources for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Banner resources
     */
    private function get_banner_resources($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        $tracking_params = $this->generate_tracking_parameters($affiliate_id);
        
        $banner_resources = [
            'leaderboard' => [
                'name' => __('Leaderboard Banner', 'affiliate-master-enhancement'),
                'dimensions' => '728x90',
                'preview_url' => AME_ASSETS_URL . 'images/banners/leaderboard-preview.jpg',
                'download_url' => AME_ASSETS_URL . 'images/banners/leaderboard.jpg',
                'html_code' => $this->generate_banner_html($affiliate_id, 'leaderboard', '728x90'),
                'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
            ],
            'medium_rectangle' => [
                'name' => __('Medium Rectangle', 'affiliate-master-enhancement'),
                'dimensions' => '300x250',
                'preview_url' => AME_ASSETS_URL . 'images/banners/medium-rectangle-preview.jpg',
                'download_url' => AME_ASSETS_URL . 'images/banners/medium-rectangle.jpg',
                'html_code' => $this->generate_banner_html($affiliate_id, 'medium-rectangle', '300x250'),
                'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
            ],
            'large_rectangle' => [
                'name' => __('Large Rectangle', 'affiliate-master-enhancement'),
                'dimensions' => '336x280',
                'preview_url' => AME_ASSETS_URL . 'images/banners/large-rectangle-preview.jpg',
                'download_url' => AME_ASSETS_URL . 'images/banners/large-rectangle.jpg',
                'html_code' => $this->generate_banner_html($affiliate_id, 'large-rectangle', '336x280'),
                'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
            ],
            'skyscraper' => [
                'name' => __('Skyscraper Banner', 'affiliate-master-enhancement'),
                'dimensions' => '120x600',
                'preview_url' => AME_ASSETS_URL . 'images/banners/skyscraper-preview.jpg',
                'download_url' => AME_ASSETS_URL . 'images/banners/skyscraper.jpg',
                'html_code' => $this->generate_banner_html($affiliate_id, 'skyscraper', '120x600'),
                'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
            ],
            'square' => [
                'name' => __('Square Banner', 'affiliate-master-enhancement'),
                'dimensions' => '250x250',
                'preview_url' => AME_ASSETS_URL . 'images/banners/square-preview.jpg',
                'download_url' => AME_ASSETS_URL . 'images/banners/square.jpg',
                'html_code' => $this->generate_banner_html($affiliate_id, 'square', '250x250'),
                'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
            ],
            'mobile_banner' => [
                'name' => __('Mobile Banner', 'affiliate-master-enhancement'),
                'dimensions' => '320x50',
                'preview_url' => AME_ASSETS_URL . 'images/banners/mobile-preview.jpg',
                'download_url' => AME_ASSETS_URL . 'images/banners/mobile.jpg',
                'html_code' => $this->generate_banner_html($affiliate_id, 'mobile', '320x50'),
                'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
            ]
        ];

        return apply_filters('ame_banner_resources', $banner_resources, $affiliate_id);
    }

    /**
     * Get email templates for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Email templates
     */
    private function get_email_templates($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $affiliate_name = $this->get_affiliate_name($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        $tracking_params = $this->generate_tracking_parameters($affiliate_id);
        
        $email_templates = [
            'welcome_series' => [
                'name' => __('Welcome Email Series', 'affiliate-master-enhancement'),
                'type' => 'series',
                'subject' => sprintf(__('Welcome to %s - Exclusive Offer Inside!', 'affiliate-master-enhancement'), get_bloginfo('name')),
                'preview_text' => __('Get started with your exclusive discount...', 'affiliate-master-enhancement'),
                'content' => $this->generate_welcome_email_content($affiliate_id),
                'html_version' => $this->generate_welcome_email_html($affiliate_id),
                'tracking_links' => [
                    'primary_cta' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&email_campaign=welcome&' . $tracking_params,
                    'secondary_cta' => $domain_url . '/about/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
                ]
            ],
            'product_promotion' => [
                'name' => __('Product Promotion Email', 'affiliate-master-enhancement'),
                'type' => 'promotional',
                'subject' => __('Exclusive Deal: Save [DISCOUNT]% Today Only', 'affiliate-master-enhancement'),
                'preview_text' => __('Limited time offer for preferred customers...', 'affiliate-master-enhancement'),
                'content' => $this->generate_product_promotion_content($affiliate_id),
                'html_version' => $this->generate_product_promotion_html($affiliate_id),
                'tracking_links' => [
                    'shop_now' => $domain_url . '/shop/?affiliate_code=' . $affiliate_code . '&email_campaign=promo&' . $tracking_params,
                    'learn_more' => $domain_url . '/products/?affiliate_code=' . $affiliate_code . '&' . $tracking_params
                ]
            ],
            'newsletter_insert' => [
                'name' => __('Newsletter Insert Template', 'affiliate-master-enhancement'),
                'type' => 'insert',
                'subject' => __('Your Weekly Update + Special Offer', 'affiliate-master-enhancement'),
                'preview_text' => __('This week\'s highlights plus an exclusive offer...', 'affiliate-master-enhancement'),
                'content' => $this->generate_newsletter_insert_content($affiliate_id),
                'html_version' => $this->generate_newsletter_insert_html($affiliate_id),
                'tracking_links' => [
                    'featured_product' => $domain_url . '/featured/?affiliate_code=' . $affiliate_code . '&email_campaign=newsletter&' . $tracking_params
                ]
            ],
            'abandoned_cart' => [
                'name' => __('Follow-up Email Template', 'affiliate-master-enhancement'),
                'type' => 'follow_up',
                'subject' => __('Don\'t Miss Out - Complete Your Purchase', 'affiliate-master-enhancement'),
                'preview_text' => __('Your items are still waiting for you...', 'affiliate-master-enhancement'),
                'content' => $this->generate_follow_up_content($affiliate_id),
                'html_version' => $this->generate_follow_up_html($affiliate_id),
                'tracking_links' => [
                    'complete_purchase' => $domain_url . '/cart/?affiliate_code=' . $affiliate_code . '&email_campaign=followup&' . $tracking_params
                ]
            ]
        ];

        return apply_filters('ame_email_templates', $email_templates, $affiliate_id);
    }

    /**
     * Get social media content for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Social media content
     */
    private function get_social_media_content($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        $tracking_params = $this->generate_tracking_parameters($affiliate_id);
        $brand_name = get_bloginfo('name');
        
        $social_content = [
            'facebook_posts' => [
                'name' => __('Facebook Posts', 'affiliate-master-enhancement'),
                'platform' => 'facebook',
                'posts' => [
                    [
                        'type' => 'promotional',
                        'content' => sprintf(__('🎉 Exclusive offer for my followers! Get [DISCOUNT]%% off at %s with my special code. Limited time only! #discount #deals #savings', 'affiliate-master-enhancement'), $brand_name),
                        'image_suggestions' => [
                            AME_ASSETS_URL . 'images/social/facebook-promo-1.jpg',
                            AME_ASSETS_URL . 'images/social/facebook-promo-2.jpg'
                        ],
                        'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&social_source=facebook&' . $tracking_params,
                        'hashtags' => ['#' . strtolower($brand_name), '#affiliate', '#discount', '#exclusive']
                    ],
                    [
                        'type' => 'testimonial',
                        'content' => sprintf(__('I\'ve been using %s for months and absolutely love it! Use my code for a special discount. What are you waiting for? 💕', 'affiliate-master-enhancement'), $brand_name),
                        'image_suggestions' => [
                            AME_ASSETS_URL . 'images/social/testimonial-1.jpg',
                            AME_ASSETS_URL . 'images/social/testimonial-2.jpg'
                        ],
                        'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&social_source=facebook_testimonial&' . $tracking_params,
                        'hashtags' => ['#testimonial', '#' . strtolower($brand_name), '#recommended']
                    ]
                ]
            ],
            'instagram_posts' => [
                'name' => __('Instagram Posts', 'affiliate-master-enhancement'),
                'platform' => 'instagram',
                'posts' => [
                    [
                        'type' => 'story_template',
                        'content' => __('Swipe up for exclusive discount! ✨', 'affiliate-master-enhancement'),
                        'story_templates' => [
                            AME_ASSETS_URL . 'images/social/instagram-story-1.jpg',
                            AME_ASSETS_URL . 'images/social/instagram-story-2.jpg'
                        ],
                        'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&social_source=instagram_story&' . $tracking_params,
                        'stickers' => ['discount_badge', 'swipe_up_arrow', 'limited_time']
                    ],
                    [
                        'type' => 'feed_post',
                        'content' => sprintf(__('Obsessed with this from @%s! 😍 My followers get [DISCOUNT]%% off with code %s - link in bio! #ad', 'affiliate-master-enhancement'), strtolower($brand_name), $affiliate_code),
                        'image_suggestions' => [
                            AME_ASSETS_URL . 'images/social/instagram-feed-1.jpg',
                            AME_ASSETS_URL . 'images/social/instagram-feed-2.jpg'
                        ],
                        'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&social_source=instagram&' . $tracking_params,
                        'hashtags' => ['#ad', '#' . strtolower($brand_name), '#affiliate', '#discount']
                    ]
                ]
            ],
            'twitter_posts' => [
                'name' => __('Twitter/X Posts', 'affiliate-master-enhancement'),
                'platform' => 'twitter',
                'posts' => [
                    [
                        'type' => 'promotional_tweet',
                        'content' => sprintf(__('🚨 EXCLUSIVE: [DISCOUNT]%% off @%s for my followers only! Code: %s ⏰ Limited time', 'affiliate-master-enhancement'), strtolower($brand_name), $affiliate_code),
                        'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&social_source=twitter&' . $tracking_params,
                        'hashtags' => ['#' . strtolower($brand_name), '#discount', '#exclusive'],
                        'character_count' => strlen(sprintf('🚨 EXCLUSIVE: [DISCOUNT]%% off @%s for my followers only! Code: %s ⏰ Limited time', strtolower($brand_name), $affiliate_code))
                    ],
                    [
                        'type' => 'thread_starter',
                        'content' => sprintf(__('🧵 Why I recommend %s (and how you can save [DISCOUNT]%% off): Thread 👇', 'affiliate-master-enhancement'), $brand_name),
                        'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&social_source=twitter_thread&' . $tracking_params,
                        'thread_suggestions' => [
                            __('1/ First discovered this product 6 months ago...', 'affiliate-master-enhancement'),
                            __('2/ What impressed me most was...', 'affiliate-master-enhancement'),
                            __('3/ After using it consistently...', 'affiliate-master-enhancement'),
                            sprintf(__('4/ Use code %s for [DISCOUNT]%% off (link below)', 'affiliate-master-enhancement'), $affiliate_code)
                        ]
                    ]
                ]
            ],
            'linkedin_posts' => [
                'name' => __('LinkedIn Posts', 'affiliate-master-enhancement'),
                'platform' => 'linkedin',
                'posts' => [
                    [
                        'type' => 'professional_recommendation',
                        'content' => sprintf(__('In my professional experience, %s has been invaluable for [BENEFIT]. My network can get [DISCOUNT]%% off with my referral code. Comments welcome on your experiences with similar tools.', 'affiliate-master-enhancement'), $brand_name),
                        'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&social_source=linkedin&' . $tracking_params,
                        'industry_hashtags' => ['#productivity', '#business', '#tools', '#recommendation']
                    ]
                ]
            ]
        ];

        return apply_filters('ame_social_media_content', $social_content, $affiliate_id);
    }

    /**
     * Get landing page snippets for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Landing page snippets
     */
    private function get_landing_page_snippets($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        $tracking_params = $this->generate_tracking_parameters($affiliate_id);
        
        $landing_snippets = [
            'hero_section' => [
                'name' => __('Hero Section with Discount', 'affiliate-master-enhancement'),
                'type' => 'hero',
                'html_code' => $this->generate_hero_section_html($affiliate_id),
                'css_code' => $this->generate_hero_section_css(),
                'variables' => [
                    'discount_percentage' => '[DISCOUNT]%',
                    'affiliate_code' => $affiliate_code,
                    'call_to_action' => __('Get Exclusive Discount', 'affiliate-master-enhancement'),
                    'tracking_url' => $domain_url . '/?affiliate_code=' . $affiliate_code . '&landing_source=hero&' . $tracking_params
                ]
            ],
            'countdown_timer' => [
                'name' => __('Urgency Countdown Timer', 'affiliate-master-enhancement'),
                'type' => 'urgency',
                'html_code' => $this->generate_countdown_timer_html($affiliate_id),
                'css_code' => $this->generate_countdown_timer_css(),
                'js_code' => $this->generate_countdown_timer_js(),
                'variables' => [
                    'expiry_message' => __('Limited Time Offer', 'affiliate-master-enhancement'),
                    'affiliate_code' => $affiliate_code
                ]
            ],
            'testimonial_carousel' => [
                'name' => __('Customer Testimonial Carousel', 'affiliate-master-enhancement'),
                'type' => 'social_proof',
                'html_code' => $this->generate_testimonial_carousel_html($affiliate_id),
                'css_code' => $this->generate_testimonial_carousel_css(),
                'js_code' => $this->generate_testimonial_carousel_js(),
                'testimonials' => $this->get_sample_testimonials()
            ],
            'discount_banner' => [
                'name' => __('Sticky Discount Banner', 'affiliate-master-enhancement'),
                'type' => 'banner',
                'html_code' => $this->generate_discount_banner_html($affiliate_id),
                'css_code' => $this->generate_discount_banner_css(),
                'variables' => [
                    'discount_percentage' => '[DISCOUNT]%',
                    'affiliate_code' => $affiliate_code,
                    'banner_text' => sprintf(__('Use code %s for [DISCOUNT]%% off your order', 'affiliate-master-enhancement'), $affiliate_code)
                ]
            ],
            'conversion_form' => [
                'name' => __('High-Converting Lead Form', 'affiliate-master-enhancement'),
                'type' => 'form',
                'html_code' => $this->generate_conversion_form_html($affiliate_id),
                'css_code' => $this->generate_conversion_form_css(),
                'js_code' => $this->generate_conversion_form_js(),
                'variables' => [
                    'form_action' => $domain_url . '/submit-lead/',
                    'affiliate_code' => $affiliate_code,
                    'hidden_tracking' => $tracking_params
                ]
            ]
        ];

        return apply_filters('ame_landing_page_snippets', $landing_snippets, $affiliate_id);
    }

    /**
     * Generate banner HTML code
     * @param int $affiliate_id Affiliate ID
     * @param string $type Banner type
     * @param string $dimensions Banner dimensions
     * @return string HTML code
     */
    private function generate_banner_html($affiliate_id, $type, $dimensions) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        $tracking_params = $this->generate_tracking_parameters($affiliate_id);
        $image_url = AME_ASSETS_URL . "images/banners/{$type}.jpg";
        
        list($width, $height) = explode('x', $dimensions);
        
        return sprintf(
            '<a href="%s" target="_blank" rel="noopener" style="display: inline-block; text-decoration: none;">
                <img src="%s" alt="%s Banner - Use Code %s" width="%s" height="%s" style="border: 0; display: block; max-width: 100%%; height: auto;">
            </a>',
            esc_url($domain_url . '/?affiliate_code=' . $affiliate_code . '&banner_type=' . $type . '&' . $tracking_params),
            esc_url($image_url),
            esc_attr(get_bloginfo('name')),
            esc_attr($affiliate_code),
            esc_attr($width),
            esc_attr($height)
        );
    }

    /**
     * Generate tracking parameters for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return string Tracking parameters
     */
    private function generate_tracking_parameters($affiliate_id) {
        return http_build_query([
            'utm_source' => 'affiliate',
            'utm_medium' => 'referral',
            'utm_campaign' => 'affiliate_' . $affiliate_id,
            'utm_term' => $this->get_affiliate_code($affiliate_id),
            'ref_id' => $affiliate_id,
            'timestamp' => time()
        ]);
    }

    /**
     * Generate welcome email content
     * @param int $affiliate_id Affiliate ID
     * @return string Email content
     */
    private function generate_welcome_email_content($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $brand_name = get_bloginfo('name');
        
        return sprintf(__('Welcome to %s!

We\'re excited to have you join our community of satisfied customers.

As a special welcome gift, use code %s to save [DISCOUNT]%% on your first order.

This exclusive offer is valid for the next 7 days, so don\'t wait!

What makes us different:
• Premium quality products
• Exceptional customer service
• 30-day money-back guarantee
• Fast, free shipping on orders over $50

Ready to get started? Use your exclusive code: %s

Best regards,
The %s Team

P.S. Follow us on social media for more exclusive deals and tips!', 'affiliate-master-enhancement'),
            $brand_name,
            $affiliate_code,
            $affiliate_code,
            $brand_name
        );
    }

    /**
     * Generate hero section HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Hero section HTML
     */
    private function generate_hero_section_html($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        
        return sprintf('
        <section class="affiliate-hero-section">
            <div class="hero-container">
                <div class="hero-content">
                    <h1 class="hero-headline">Exclusive [DISCOUNT]%% Off</h1>
                    <p class="hero-subheadline">Limited time offer for valued customers</p>
                    <div class="discount-code-display">
                        <span class="code-label">Use Code:</span>
                        <span class="discount-code">%s</span>
                        <button class="copy-code-btn" onclick="copyToClipboard(\'%s\')">Copy Code</button>
                    </div>
                    <a href="%s" class="hero-cta-button">Get Exclusive Discount Now</a>
                    <div class="trust-indicators">
                        <span class="trust-item">✓ 30-Day Guarantee</span>
                        <span class="trust-item">✓ Free Shipping</span>
                        <span class="trust-item">✓ Secure Checkout</span>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="%s" alt="Product showcase" loading="lazy">
                </div>
            </div>
        </section>',
            esc_html($affiliate_code),
            esc_js($affiliate_code),
            esc_url($domain_url . '/?affiliate_code=' . $affiliate_code . '&landing_source=hero'),
            esc_url(AME_ASSETS_URL . 'images/hero-product.jpg')
        );
    }

    /**
     * Generate countdown timer HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Countdown timer HTML
     */
    private function generate_countdown_timer_html($affiliate_id) {
        return '
        <div class="affiliate-countdown-container">
            <div class="countdown-header">
                <h3>⏰ Limited Time Offer Expires In:</h3>
            </div>
            <div class="countdown-timer" id="affiliate-countdown">
                <div class="time-unit">
                    <span class="time-number" id="hours">00</span>
                    <span class="time-label">Hours</span>
                </div>
                <div class="time-separator">:</div>
                <div class="time-unit">
                    <span class="time-number" id="minutes">00</span>
                    <span class="time-label">Minutes</span>
                </div>
                <div class="time-separator">:</div>
                <div class="time-unit">
                    <span class="time-number" id="seconds">00</span>
                    <span class="time-label">Seconds</span>
                </div>
            </div>
            <div class="countdown-footer">
                <p>Don\'t miss out on this exclusive discount!</p>
            </div>
        </div>';
    }

    /**
     * Get sample testimonials for carousel
     * @return array Sample testimonials
     */

    /**
     * Generate hero section CSS
     * @return string Hero section CSS
     */

    /**
     * Generate countdown timer JavaScript
     * @return string Countdown timer JavaScript
     */

    /**
     * Generate testimonial carousel HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Testimonial carousel HTML
     */

    /**
     * Generate testimonial carousel CSS
     * @return string Testimonial carousel CSS
     */

    /**
     * Generate testimonial carousel JavaScript
     * @return string Testimonial carousel JavaScript
     */

    /**
     * Generate discount banner HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Discount banner HTML
     */

    /**
     * Generate discount banner CSS
     * @return string Discount banner CSS
     */

    /**
     * Generate conversion form HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Conversion form HTML
     */

    /**
     * Generate conversion form CSS
     * @return string Conversion form CSS
     */

    /**
     * Generate conversion form JavaScript
     * @return string Conversion form JavaScript
     */

    /**
     * Get affiliate code for marketing materials
     * @param int $affiliate_id Affiliate ID
     * @return string Affiliate code
     */

    /**
     * Get affiliate name for materials
     * @param int $affiliate_id Affiliate ID
     * @return string Affiliate name
     */

    /**
     * Get affiliate domain URL for tracking
     * @param int $affiliate_id Affiliate ID
     * @return string Domain URL
     */

    /**
     * Generate welcome email HTML version
     * @param int $affiliate_id Affiliate ID
     * @return string HTML email content
     */
    private function generate_welcome_email_html($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $brand_name = get_bloginfo('name');
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        
        return sprintf('
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Welcome to %s</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <div style="max-width: 600px; margin: 0 auto; background: white;">
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 40px 20px; text-align: center; color: white;">
                    <h1 style="margin: 0; font-size: 2.5rem;">Welcome to %s!</h1>
                    <p style="margin: 20px 0 0 0; font-size: 1.2rem; opacity: 0.9;">Your exclusive discount awaits</p>
                </div>
                
                <div style="padding: 40px 20px;">
                    <div style="background: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;">
                        <p style="margin: 0 0 15px 0; font-size: 1.1rem; color: #333;">Your Exclusive Discount Code:</p>
                        <div style="background: linear-gradient(135deg, #ff6b6b, #ffd93d); color: white; padding: 15px 30px; border-radius: 50px; display: inline-block; font-size: 1.5rem; font-weight: bold;">%s</div>
                        <p style="margin: 15px 0 0 0; color: #666; font-size: 0.9rem;">Save [DISCOUNT]%% on your first order</p>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="%s" style="background: #007cba; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Shop Now & Save</a>
                    </div>
                    
                    <div style="border-left: 4px solid #007cba; padding-left: 20px; margin: 30px 0;">
                        <h3 style="margin: 0 0 15px 0; color: #333;">What makes us different:</h3>
                        <ul style="margin: 0; padding: 0; list-style: none;">
                            <li style="margin-bottom: 10px; color: #666;">✓ Premium quality products</li>
                            <li style="margin-bottom: 10px; color: #666;">✓ Exceptional customer service</li>
                            <li style="margin-bottom: 10px; color: #666;">✓ 30-day money-back guarantee</li>
                            <li style="margin-bottom: 10px; color: #666;">✓ Fast, free shipping on orders over $50</li>
                        </ul>
                    </div>
                </div>
                
                <div style="background: #333; color: white; padding: 30px 20px; text-align: center;">
                    <p style="margin: 0; opacity: 0.8;">Thanks for joining our community!</p>
                    <p style="margin: 10px 0 0 0; font-weight: bold;">The %s Team</p>
                </div>
            </div>
        </body>
        </html>',
            esc_html($brand_name),
            esc_html($brand_name),
            esc_html($affiliate_code),
            esc_url($domain_url . '/?affiliate_code=' . $affiliate_code . '&email_campaign=welcome'),
            esc_html($brand_name)
        );
    }

    /**
     * Generate product promotion email content
     * @param int $affiliate_id Affiliate ID
     * @return string Product promotion content
     */
    private function generate_product_promotion_content($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $brand_name = get_bloginfo('name');
        
        return sprintf(__('🎉 EXCLUSIVE FLASH SALE - [DISCOUNT]%% OFF!

Hi there,

We\'re having a special flash sale and wanted to give you first access!

For the next 48 hours only, use code %s to save [DISCOUNT]%% on ALL products.

⏰ This offer expires in 48 hours
💰 Save [DISCOUNT]%% on your entire order
🚚 FREE shipping on orders over $50
✅ No minimum purchase required

Popular items on sale:
• [Product 1] - Usually $XX, now $XX
• [Product 2] - Usually $XX, now $XX  
• [Product 3] - Usually $XX, now $XX

Don\'t wait - this is our biggest discount of the year!

Use code: %s

Shop now before it\'s too late!

Best regards,
%s Team', 'affiliate-master-enhancement'),
            $affiliate_code,
            $affiliate_code,
            $brand_name
        );
    }

    /**
     * Generate newsletter insert content
     * @param int $affiliate_id Affiliate ID
     * @return string Newsletter insert content
     */
    private function generate_newsletter_insert_content($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $brand_name = get_bloginfo('name');
        
        return sprintf(__('📧 SUBSCRIBER EXCLUSIVE

As a valued subscriber, you get exclusive access to our latest products and special offers.

This week only - save [DISCOUNT]%% with code %s

What\'s new this week:
• New product launches
• Behind-the-scenes content  
• Customer success stories
• Upcoming sales preview

Remember, as a subscriber you always get:
✓ Early access to sales
✓ Exclusive discount codes
✓ Free shipping on all orders
✓ Members-only content

Use your exclusive code: %s

Happy shopping!
%s', 'affiliate-master-enhancement'),
            $affiliate_code,
            $affiliate_code,
            $brand_name
        );
    }

    /**
     * Generate follow-up email content
     * @param int $affiliate_id Affiliate ID
     * @return string Follow-up content
     */
    private function generate_follow_up_content($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $brand_name = get_bloginfo('name');
        
        return sprintf(__('We noticed you were interested in our services...

Don\'t let this opportunity slip away!

Your exclusive [DISCOUNT]%% discount with code %s is still available.

Here\'s what you\'re missing out on:
• Premium quality legal services at discount prices


Thousands of customers have already taken advantage of this offer.

Use code %s before it expires!

Questions? Just reply to this email - we\'re here to help.

Best regards,
%s Team

P.S. This discount expires soon, so don\'t wait!', 'affiliate-master-enhancement'),
            $affiliate_code,
            $affiliate_code,
            $brand_name
        );
    }

    /**
     * Generate countdown timer CSS
     * @return string Countdown timer CSS
     */
    private function generate_countdown_timer_css() {
        return '
        .affiliate-countdown-container {
            background: linear-gradient(45deg, #ff6b6b, #ffd93d);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .countdown-timer {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .time-unit {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 10px;
            min-width: 80px;
            backdrop-filter: blur(10px);
        }
        
        .time-number {
            display: block;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .time-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
      .time-separator {
            font-size: 2rem;
            font-weight: bold;
            opacity: 0.7;
        }
        
        @media (max-width: 768px) {
            .countdown-timer {
                flex-direction: column;
                gap: 20px;
            }
        }';
    }

    /**
     * Generate countdown timer JavaScript
     * @return string Countdown timer JavaScript
     */
    private function generate_countdown_timer_js() {
        return '
        function initAffiliateCountdown() {
            const countdownElement = document.getElementById("affiliate-countdown");
            if (!countdownElement) return;
            
            // Set countdown to 24 hours from now
            const endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
            
            function updateCountdown() {
                const now = new Date().getTime();
                const timeLeft = endTime - now;
                
                if (timeLeft <= 0) {
                    countdownElement.innerHTML = "<div class=\"countdown-expired\">Offer Expired!</div>";
                    return;
                }
                
                const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                
                document.getElementById("hours").textContent = hours.toString().padStart(2, "0");
                document.getElementById("minutes").textContent = minutes.toString().padStart(2, "0");
                document.getElementById("seconds").textContent = seconds.toString().padStart(2, "0");
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
        
        // Initialize countdown when DOM is loaded
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initAffiliateCountdown);
        } else {
            initAffiliateCountdown();
        }';
    }

    /**
     * Generate testimonial carousel HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Testimonial carousel HTML
     */
    private function generate_testimonial_carousel_html($affiliate_id) {
        $testimonials = $this->get_sample_testimonials();
        $html = '<div class="affiliate-testimonial-carousel">';
        $html .= '<div class="testimonial-header"><h3>What Our Customers Say</h3></div>';
        $html .= '<div class="testimonial-slider" id="testimonial-slider">';
        
        foreach ($testimonials as $index => $testimonial) {
            $active_class = $index === 0 ? ' active' : '';
            $html .= sprintf('
                <div class="testimonial-slide%s">
                    <div class="testimonial-content">
                        <div class="stars">%s</div>
                        <blockquote>"%s"</blockquote>
                        <div class="testimonial-author">
                            <img src="%s" alt="%s" class="author-avatar">
                            <div class="author-info">
                                <strong>%s</strong>
                                <span>%s</span>
                            </div>
                        </div>
                    </div>
                </div>',
                $active_class,
                str_repeat('★', $testimonial['rating']),
                esc_html($testimonial['testimonial']),
                esc_url($testimonial['avatar']),
                esc_attr($testimonial['name']),
                esc_html($testimonial['name']),
                esc_html($testimonial['location'])
            );
        }
        
        $html .= '</div>';
        $html .= '<div class="testimonial-navigation">';
        $html .= '<button class="nav-btn prev-btn" onclick="previousTestimonial()">‹</button>';
        $html .= '<button class="nav-btn next-btn" onclick="nextTestimonial()">›</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $custom_templates;
    }

    /**
     * Track marketing material usage
     * @param int $affiliate_id Affiliate ID
     * @param string $material_type Type of material used
     * @param string $platform Platform where used
     * @param array $metrics Usage metrics
     * @return bool Success status
     */
    public function track_material_usage($affiliate_id, $material_type, $platform, $metrics = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_marketing_usage';
        
        return $wpdb->insert(
            $table_name,
            [
                'affiliate_id' => $affiliate_id,
                'material_type' => sanitize_text_field($material_type),
                'platform' => sanitize_text_field($platform),
                'metrics' => wp_json_encode($metrics),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        ) !== false;
    }

    /**
     * Get marketing performance analytics
     * @param int $affiliate_id Affiliate ID
     * @param string $date_range Date range for analytics
     * @return array Performance data
     */
    public function get_marketing_performance($affiliate_id, $date_range = '30_days') {
        global $wpdb;
        
        $date_condition = '';
        switch ($date_range) {
            case '7_days':
                $date_condition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case '30_days':
                $date_condition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
            case '90_days':
                $date_condition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
                break;
        }
        
        $usage_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                material_type,
                platform,
                COUNT(*) as usage_count,
                AVG(JSON_EXTRACT(metrics, '$.clicks')) as avg_clicks,
                AVG(JSON_EXTRACT(metrics, '$.conversions')) as avg_conversions,
                SUM(JSON_EXTRACT(metrics, '$.revenue')) as total_revenue
            FROM {$wpdb->prefix}affiliate_marketing_usage 
            WHERE affiliate_id = %d 
            {$date_condition}
            GROUP BY material_type, platform
            ORDER BY usage_count DESC
        ", $affiliate_id));
        
        return [
            'usage_statistics' => $usage_stats,
            'top_performing_materials' => $this->get_top_performing_materials($affiliate_id, $date_range),
            'platform_performance' => $this->get_platform_performance($affiliate_id, $date_range),
            'conversion_funnel' => $this->get_conversion_funnel_data($affiliate_id, $date_range)
        ];
    }

    /**
     * Get top performing marketing materials
     * @param int $affiliate_id Affiliate ID
     * @param string $date_range Date range
     * @return array Top performing materials
     */
    private function get_top_performing_materials($affiliate_id, $date_range) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($date_range);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                material_type,
                COUNT(*) as usage_count,
                AVG(JSON_EXTRACT(metrics, '$.conversion_rate')) as avg_conversion_rate,
                SUM(JSON_EXTRACT(metrics, '$.revenue')) as total_revenue
            FROM {$wpdb->prefix}affiliate_marketing_usage 
            WHERE affiliate_id = %d 
            {$date_condition}
            GROUP BY material_type
            ORDER BY avg_conversion_rate DESC, total_revenue DESC
            LIMIT 10
        ", $affiliate_id));
    }

    /**
     * Get platform performance data
     * @param int $affiliate_id Affiliate ID
     * @param string $date_range Date range
     * @return array Platform performance
     */
    private function get_platform_performance($affiliate_id, $date_range) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($date_range);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                platform,
                COUNT(*) as campaigns,
                SUM(JSON_EXTRACT(metrics, '$.clicks')) as total_clicks,
                SUM(JSON_EXTRACT(metrics, '$.conversions')) as total_conversions,
                SUM(JSON_EXTRACT(metrics, '$.revenue')) as total_revenue,
                AVG(JSON_EXTRACT(metrics, '$.conversion_rate')) as avg_conversion_rate
            FROM {$wpdb->prefix}affiliate_marketing_usage 
            WHERE affiliate_id = %d 
            {$date_condition}
            GROUP BY platform
            ORDER BY total_revenue DESC
        ", $affiliate_id));
    }

    /**
     * Get conversion funnel data
     * @param int $affiliate_id Affiliate ID
     * @param string $date_range Date range
     * @return array Conversion funnel data
     */
    private function get_conversion_funnel_data($affiliate_id, $date_range) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($date_range);
        
        $funnel_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(JSON_EXTRACT(metrics, '$.impressions')) as total_impressions,
                SUM(JSON_EXTRACT(metrics, '$.clicks')) as total_clicks,
                SUM(JSON_EXTRACT(metrics, '$.leads')) as total_leads,
                SUM(JSON_EXTRACT(metrics, '$.conversions')) as total_conversions,
                SUM(JSON_EXTRACT(metrics, '$.revenue')) as total_revenue
            FROM {$wpdb->prefix}affiliate_marketing_usage 
            WHERE affiliate_id = %d 
            {$date_condition}
        ", $affiliate_id));
        
        if (!$funnel_data) {
            return [];
        }
        
        $impressions = floatval($funnel_data->total_impressions ?: 0);
        $clicks = floatval($funnel_data->total_clicks ?: 0);
        $leads = floatval($funnel_data->total_leads ?: 0);
        $conversions = floatval($funnel_data->total_conversions ?: 0);
        
        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'leads' => $leads,
            'conversions' => $conversions,
            'revenue' => floatval($funnel_data->total_revenue ?: 0),
            'click_through_rate' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
            'lead_conversion_rate' => $clicks > 0 ? round(($leads / $clicks) * 100, 2) : 0,
            'purchase_conversion_rate' => $leads > 0 ? round(($conversions / $leads) * 100, 2) : 0,
            'overall_conversion_rate' => $impressions > 0 ? round(($conversions / $impressions) * 100, 2) : 0
        ];
    }

    /**
     * Generate date condition for SQL queries
     * @param string $date_range Date range
     * @return string SQL date condition
     */
    private function get_date_condition($date_range) {
        switch ($date_range) {
            case '7_days':
                return 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            case '30_days':
                return 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            case '90_days':
                return 'AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            case '1_year':
                return 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
            default:
                return 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }
    }

    /**
     * Generate A/B test variations for marketing materials
     * @param int $affiliate_id Affiliate ID
     * @param string $base_template Base template to vary
     * @param array $variations Variation parameters
     * @return array A/B test variations
     */
    public function generate_ab_test_variations($affiliate_id, $base_template, $variations = []) {
        $base_materials = $this->generate_marketing_materials($affiliate_id, $base_template);
        $test_variations = ['original' => $base_materials];
        
        // Generate headline variations
        if (!empty($variations['headlines'])) {
            foreach ($variations['headlines'] as $index => $headline) {
                $variation_key = 'headline_' . ($index + 1);
                $test_variations[$variation_key] = $this->apply_headline_variation($base_materials, $headline);
            }
        }
        
        // Generate color variations
        if (!empty($variations['colors'])) {
            foreach ($variations['colors'] as $index => $color_scheme) {
                $variation_key = 'color_' . ($index + 1);
                $test_variations[$variation_key] = $this->apply_color_variation($base_materials, $color_scheme);
            }
        }
        
        // Generate CTA variations
        if (!empty($variations['call_to_actions'])) {
            foreach ($variations['call_to_actions'] as $index => $cta) {
                $variation_key = 'cta_' . ($index + 1);
                $test_variations[$variation_key] = $this->apply_cta_variation($base_materials, $cta);
            }
        }
        
        return $test_variations;
    }

    /**
     * Apply headline variation to materials
     * @param array $materials Base materials
     * @param string $new_headline New headline
     * @return array Modified materials
     */
    private function apply_headline_variation($materials, $new_headline) {
        $modified = $materials;
        
        foreach ($modified as &$material) {
            if (is_array($material) && isset($material['content'])) {
                $material['content'] = preg_replace(
                    '/(<h[1-3][^>]*>)([^<]+)(<\/h[1-3]>)/i',
                    '$1' . esc_html($new_headline) . '$3',
                    $material['content']
                );
            }
        }
        
        return $modified;
    }

    /**
     * Apply color variation to materials
     * @param array $materials Base materials
     * @param array $color_scheme New color scheme
     * @return array Modified materials
     */
    private function apply_color_variation($materials, $color_scheme) {
        $modified = $materials;
        
        foreach ($modified as &$material) {
            if (is_array($material) && isset($material['html_code'])) {
                // Replace primary colors in inline styles
                $material['html_code'] = str_replace(
                    ['#007cba', '#ff6b6b', '#ffd93d'],
                    [$color_scheme['primary'] ?? '#007cba', $color_scheme['secondary'] ?? '#ff6b6b', $color_scheme['accent'] ?? '#ffd93d'],
                    $material['html_code']
                );
            }
        }
        
        return $modified;
    }

    /**
     * Apply call-to-action variation to materials
     * @param array $materials Base materials
     * @param string $new_cta New call-to-action text
     * @return array Modified materials
     */
    private function apply_cta_variation($materials, $new_cta) {
        $modified = $materials;
        
        foreach ($modified as &$material) {
            if (is_array($material) && isset($material['content'])) {
                $material['content'] = preg_replace(
                    '/(<button[^>]*>)([^<]+)(<\/button>)/i',
                    '$1' . esc_html($new_cta) . '$3',
                    $material['content']
                );
                
                $material['content'] = preg_replace(
                    '/(<a[^>]*class="[^"]*cta[^"]*"[^>]*>)([^<]+)(<\/a>)/i',
                    '$1' . esc_html($new_cta) . '$3',
                    $material['content']
                );
            }
        }
        
        return $modified;
    }

    /**
     * Export marketing materials package
     * @param int $affiliate_id Affiliate ID
     * @param array $material_types Materials to include
     * @return string ZIP file path
     */
    public function export_marketing_package($affiliate_id, $material_types = []) {
        if (empty($material_types)) {
            $material_types = ['banner', 'email', 'social', 'landing'];
        }
        
        $zip = new ZipArchive();
        $zip_filename = 'affiliate_marketing_package_' . $affiliate_id . '_' . date('Y-m-d') . '.zip';
        $zip_path = WP_CONTENT_DIR . '/uploads/affiliate_packages/' . $zip_filename;
        
        // Ensure directory exists
        wp_mkdir_p(dirname($zip_path));
        
        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
            return false;
        }
        
        foreach ($material_types as $type) {
            $materials = $this->generate_marketing_materials($affiliate_id, $type);
            $this->add_materials_to_zip($zip, $materials, $type);
        }
        
        // Add brand guidelines and instructions
        $zip->addFromString(
            'brand_guidelines.json',
            wp_json_encode($this->get_brand_guidelines(), JSON_PRETTY_PRINT)
        );
        
        $zip->addFromString(
            'usage_instructions.json',
            wp_json_encode($this->get_usage_instructions(), JSON_PRETTY_PRINT)
        );
        
        $zip->close();
        
        return $zip_path;
    }

    /**
     * Add materials to ZIP archive
     * @param ZipArchive $zip ZIP archive object
     * @param array $materials Materials to add
     * @param string $type Material type
     */
    private function add_materials_to_zip($zip, $materials, $type) {
        $folder = $type . '_materials/';
        
        foreach ($materials as $key => $material) {
            if (is_array($material)) {
                if (isset($material['html_code'])) {
                    $zip->addFromString($folder . $key . '.html', $material['html_code']);
                }
                if (isset($material['css_code'])) {
                    $zip->addFromString($folder . $key . '.css', $material['css_code']);
                }
                if (isset($material['js_code'])) {
                    $zip->addFromString($folder . $key . '.js', $material['js_code']);
                }
                if (isset($material['content'])) {
                    $zip->addFromString($folder . $key . '.txt', $material['content']);
                }
            }
        }
    }

    /**
     * Schedule marketing material generation
     * @param int $affiliate_id Affiliate ID
     * @param string $frequency Generation frequency
     * @param array $material_types Material types to generate
     * @return bool Success status
     */
    public function schedule_material_generation($affiliate_id, $frequency, $material_types) {
        $hook_name = 'affiliate_generate_materials_' . $affiliate_id;
        
        // Clear existing schedule
        wp_clear_scheduled_hook($hook_name);
        
        // Schedule new generation
        $schedules = wp_get_schedules();
        if (isset($schedules[$frequency])) {
            wp_schedule_event(
                time(),
                $frequency,
                $hook_name,
                [$affiliate_id, $material_types]
            );
            
            return true;
        }
        
        return false;
    }

    /**
     * Process scheduled material generation
     * @param int $affiliate_id Affiliate ID
     * @param array $material_types Material types
     */
    public function process_scheduled_generation($affiliate_id, $material_types) {
        foreach ($material_types as $type) {
            $materials = $this->generate_marketing_materials($affiliate_id, $type);
            
            // Save generated materials
            $this->save_generated_materials($affiliate_id, $type, $materials);
            
            // Notify affiliate
            $this->notify_affiliate_of_new_materials($affiliate_id, $type);
        }
    }

    /**
     * Save generated materials to database
     * @param int $affiliate_id Affiliate ID
     * @param string $type Material type
     * @param array $materials Generated materials
     * @return bool Success status
     */
    private function save_generated_materials($affiliate_id, $type, $materials) {
        $saved_materials = get_user_meta($affiliate_id, 'generated_marketing_materials', true) ?: [];
        
        $saved_materials[$type] = [
            'materials' => $materials,
            'generated_at' => current_time('mysql'),
            'version' => time()
        ];
        
        return update_user_meta($affiliate_id, 'generated_marketing_materials', $saved_materials);
    }

    /**
     * Notify affiliate of new materials
     * @param int $affiliate_id Affiliate ID
     * @param string $material_type Material type
     */
    private function notify_affiliate_of_new_materials($affiliate_id, $material_type) {
        $affiliate = get_userdata($affiliate_id);
        if (!$affiliate) {
            return;
        }
        
        $subject = sprintf(
            __('New %s materials available', 'affiliate-master-enhancement'),
            ucfirst($material_type)
        );
        
        $message = sprintf(
            __('Hello %s,

New %s marketing materials have been generated for your affiliate account.

You can access these materials in your affiliate dashboard under the Marketing Materials section.

Best regards,
The Marketing Team', 'affiliate-master-enhancement'),
            $affiliate->display_name,
            ucfirst($material_type)
        );
        
        wp_mail($affiliate->user_email, $subject, $message);
    }

    /**
     * Generate testimonial carousel CSS
     * @return string Testimonial carousel CSS
     */
    private function generate_testimonial_carousel_css() {
        return '
        .affiliate-testimonial-carousel {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 15px;
            position: relative;
        }
        
        .testimonial-header h3 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 2rem;
        }
        
        .testimonial-slider {
            position: relative;
            overflow: hidden;
            min-height: 200px;
        }
        
        .testimonial-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.5s ease-in-out;
        }
        
        .testimonial-slide.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .testimonial-content {
            text-align: center;
            padding: 20px;
        }
        
        .stars {
            color: #ffd700;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        blockquote {
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 20px 0;
            font-style: italic;
            color: #555;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .author-info strong {
            display: block;
            color: #333;
        }
        
        .author-info span {
            color: #666;
            font-size: 0.9rem;
        }
        
        .testimonial-navigation {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
        }
        
        .nav-btn {
            background: #007cba;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .nav-btn:hover {
            background: #005a87;
        }';
    }

    /**
     * Generate testimonial carousel JavaScript
     * @return string Testimonial carousel JavaScript
     */
    private function generate_testimonial_carousel_js() {
        return '
        let currentTestimonial = 0;
        const testimonialSlides = document.querySelectorAll(".testimonial-slide");
        const totalSlides = testimonialSlides.length;
        
        function showTestimonial(index) {
            testimonialSlides.forEach(slide => slide.classList.remove("active"));
            if (testimonialSlides[index]) {
                testimonialSlides[index].classList.add("active");
            }
        }
        
        function nextTestimonial() {
            currentTestimonial = (currentTestimonial + 1) % totalSlides;
            showTestimonial(currentTestimonial);
        }
        
        function previousTestimonial() {
            currentTestimonial = (currentTestimonial - 1 + totalSlides) % totalSlides;
            showTestimonial(currentTestimonial);
        }
        
        // Auto-rotate testimonials every 5 seconds
        setInterval(nextTestimonial, 5000);';
    }

    /**
     * Generate discount banner HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Discount banner HTML
     */
    private function generate_discount_banner_html($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        
        return sprintf('
        <div class="affiliate-discount-banner" id="discount-banner">
            <div class="banner-content">
                <span class="discount-text">Save [DISCOUNT]%% with code: <strong>%s</strong></span>
                <button class="copy-code-btn" onclick="copyDiscountCode(\'%s\')">Copy Code</button>
                <button class="close-banner" onclick="closeBanner()">&times;</button>
            </div>
        </div>',
            esc_html($affiliate_code),
            esc_js($affiliate_code)
        );
    }

    /**
     * Generate discount banner CSS
     * @return string Discount banner CSS
     */
    private function generate_discount_banner_css() {
        return '
        .affiliate-discount-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(90deg, #ff6b6b, #ffd93d);
            color: white;
            padding: 15px 20px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: slideDown 0.5s ease-out;
        }
        
        .banner-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .discount-text {
            font-weight: 500;
            font-size: 1rem;
        }
        
        .copy-code-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            padding: 8px 16px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .copy-code-btn:hover {
            background: white;
            color: #ff6b6b;
        }
        
        .close-banner {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            margin-left: auto;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
            }
            to {
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .banner-content {
                flex-direction: column;
                gap: 10px;
            }
        }';
    }

    /**
     * Generate conversion form HTML
     * @param int $affiliate_id Affiliate ID
     * @return string Conversion form HTML
     */
    private function generate_conversion_form_html($affiliate_id) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        $tracking_params = $this->generate_tracking_parameters($affiliate_id);
        
        return sprintf('
        <div class="affiliate-conversion-form">
            <div class="form-header">
                <h3>Get Your Exclusive [DISCOUNT]%% Discount</h3>
                <p>Join thousands of satisfied customers and save today!</p>
            </div>
            
            <form class="lead-capture-form" action="%s" method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Enter your email address" required>
                    <label class="form-label">Email Address</label>
                </div>
                
                <div class="form-group">
                    <input type="text" name="first_name" placeholder="Enter your first name" required>
                    <label class="form-label">First Name</label>
                </div>
                
                <div class="discount-preview">
                    <div class="discount-code-box">
                        <span class="code-label">Your Exclusive Code:</span>
                        <span class="discount-code-preview">%s</span>
                    </div>
                </div>
                
                <input type="hidden" name="affiliate_code" value="%s">
                <input type="hidden" name="source" value="landing_form">
                <input type="hidden" name="tracking_data" value="%s">
                
                <button type="submit" class="form-submit-btn">
                    <span class="btn-text">Get My Discount Now</span>
                    <span class="btn-icon">→</span>
                </button>
                
                <div class="form-benefits">
                    <div class="benefit-item">
                        <span class="benefit-icon">✓</span>
                        <span>Instant access to discount</span>
                    </div>
                    <div class="benefit-item">
                        <span class="benefit-icon">✓</span>
                        <span>No spam, unsubscribe anytime</span>
                    </div>
                    <div class="benefit-item">
                        <span class="benefit-icon">✓</span>
                        <span>Exclusive member-only deals</span>
                    </div>
                </div>
            </form>
        </div>',
            esc_url($domain_url . '/submit-lead/'),
            esc_html($affiliate_code),
            esc_attr($affiliate_code),
            esc_attr($tracking_params)
        );
    }

    /**
     * Generate conversion form CSS
     * @return string Conversion form CSS
     */
    private function generate_conversion_form_css() {
        return '
        .affiliate-conversion-form {
            max-width: 500px;
            margin: 40px auto;
            padding: 40px 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid #e1e5e9;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .form-header p {
            color: #666;
            font-size: 1rem;
        }
        
        .form-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: #fafbfc;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #007cba;
            background: white;
        }
        
        .form-label {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .form-group input:focus + .form-label,
        .form-group input:not(:placeholder-shown) + .form-label {
            top: -8px;
            font-size: 0.8rem;
            background: white;
            padding: 0 5px;
            color: #007cba;
        }
        
        .discount-preview {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 25px 0;
            color: white;
        }
        
        .discount-code-box .code-label {
            display: block;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .discount-code-preview {
            font-size: 2rem;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 5px;
            display: inline-block;
        }
        
        .form-submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #ff6b6b, #ffd93d);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .form-submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .form-benefits {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e1e5e9;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .benefit-icon {
            color: #28a745;
            font-weight: bold;
        }';
    }

    /**
     * Generate conversion form JavaScript
     * @return string Conversion form JavaScript
     */
    private function generate_conversion_form_js() {
        return '
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showCopySuccess();
            }).catch(function(err) {
                console.error("Could not copy text: ", err);
            });
        }
        
        function copyDiscountCode(code) {
            copyToClipboard(code);
        }
        
        function showCopySuccess() {
            const originalText = event.target.textContent;
            event.target.textContent = "Copied!";
            event.target.style.background = "#28a745";
            
            setTimeout(() => {
                event.target.textContent = originalText;
                event.target.style.background = "";
            }, 2000);
        }
        
        function closeBanner() {
            const banner = document.getElementById("discount-banner");
            if (banner) {
                banner.style.transform = "translateY(-100%)";
                setTimeout(() => {
                    banner.remove();
                }, 500);
            }
        }
        
        // Form enhancement
        document.querySelectorAll(".lead-capture-form input").forEach(input => {
            input.addEventListener("input", function() {
                if (this.value) {
                    this.classList.add("has-value");
                } else {
                    this.classList.remove("has-value");
                }
            });
        });';
    }

    /**
     * Get affiliate code for marketing materials
     * @param int $affiliate_id Affiliate ID
     * @return string Affiliate code
     */
    private function get_affiliate_code($affiliate_id) {
        return get_user_meta($affiliate_id, 'affiliate_code', true) ?: 'AFFILIATE' . $affiliate_id;
    }

    /**
     * Get affiliate name for materials
     * @param int $affiliate_id Affiliate ID
     * @return string Affiliate name
     */
    private function get_affiliate_name($affiliate_id) {
        $user = get_userdata($affiliate_id);
        return $user ? $user->display_name : 'Valued Affiliate';
    }

    /**
     * Get affiliate domain URL for tracking
     * @param int $affiliate_id Affiliate ID
     * @return string Domain URL
     */
    private function get_affiliate_domain_url($affiliate_id) {
        $custom_domain = get_user_meta($affiliate_id, 'affiliate_domain', true);
        return $custom_domain ?: home_url();
    }

    /**
     * Get sample testimonials for carousel
     * @return array Sample testimonials
     */
    private function get_sample_testimonials() {
        return [
            [
                'name' => 'Sarah Johnson',
                'location' => 'New York, NY',
                'rating' => 5,
                'testimonial' => __('Absolutely love this product! The quality exceeded my expectations and the customer service was outstanding.', 'affiliate-master-enhancement'),
                'avatar' => AME_ASSETS_URL . 'images/testimonials/avatar-1.jpg'
            ],
            [
                'name' => 'Mike Chen',
                'location' => 'San Francisco, CA',  
                'rating' => 5,
                'testimonial' => __('Best purchase I\'ve made this year. The discount made it even better value. Highly recommended!', 'affiliate-master-enhancement'),
                'avatar' => AME_ASSETS_URL . 'images/testimonials/avatar-2.jpg'
            ],
            [
                'name' => 'Emma Thompson',
                'location' => 'Austin, TX',
                'rating' => 5,
                'testimonial' => __('Fast shipping, great quality, and excellent customer support. Will definitely order again!', 'affiliate-master-enhancement'),
                'avatar' => AME_ASSETS_URL . 'images/testimonials/avatar-3.jpg'
            ]
        ];
    }

    /**
     * Generate hero section CSS
     * @return string Hero section CSS
     */
    private function generate_hero_section_css() {
        return '
        .affiliate-hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        
        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        
        .hero-headline {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .hero-subheadline {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .discount-code-display {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        
        .discount-code {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
            background: linear-gradient(45deg, #ff6b6b, #ffd93d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .hero-cta-button {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.3s ease;
        }
        
        .hero-cta-button:hover {
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .hero-container {
                grid-template-columns: 1fr;
            }
            .hero-headline {
                font-size: 2rem;
            }
        }';
    }

    /**
     * Generate complete marketing asset package
     * @param int $affiliate_id Affiliate ID
     * @return array Complete marketing package
     */
    public function generate_complete_marketing_package($affiliate_id) {
        return [
            'banners' => $this->get_banner_resources($affiliate_id),
            'email_templates' => $this->get_email_templates($affiliate_id),
            'social_media' => $this->get_social_media_content($affiliate_id),
            'landing_pages' => $this->get_landing_page_snippets($affiliate_id),
            'tracking_info' => [
                'affiliate_code' => $this->get_affiliate_code($affiliate_id),
                'tracking_url' => $this->get_affiliate_domain_url($affiliate_id),
                'utm_parameters' => $this->generate_tracking_parameters($affiliate_id)
            ],
            'brand_guidelines' => $this->get_brand_guidelines(),
            'usage_instructions' => $this->get_usage_instructions()
        ];
    }

    /**
     * Get brand guidelines for affiliates
     * @return array Brand guidelines
     */
    private function get_brand_guidelines() {
        $brand_name = get_bloginfo('name');
        
        return [
            'brand_colors' => [
                'primary' => '#007cba',
                'secondary' => '#ff6b6b',
                'accent' => '#ffd93d',
                'text' => '#333333',
                'background' => '#ffffff'
            ],
            'typography' => [
                'primary_font' => 'Arial, sans-serif',
                'heading_font' => 'Arial, sans-serif',
                'font_sizes' => [
                    'h1' => '2.5rem',
                    'h2' => '2rem', 
                    'h3' => '1.5rem',
                    'body' => '1rem'
                ]
            ],
            'logo_usage' => [
                'logo_url' => get_site_icon_url(),
                'min_size' => '100px width',
                'clear_space' => '20px minimum',
                'usage_guidelines' => __('Always maintain clear space around logo. Do not modify colors or proportions.', 'affiliate-master-enhancement')
            ],
            'messaging' => [
                'brand_voice' => __('Professional, friendly, and helpful', 'affiliate-master-enhancement'),
                'key_messages' => [
                    sprintf(__('%s - Your trusted partner', 'affiliate-master-enhancement'), $brand_name),
                    __('Quality products, exceptional service', 'affiliate-master-enhancement'),
                    __('Customer satisfaction guaranteed', 'affiliate-master-enhancement')
                ],
                'prohibited_claims' => [
                    __('Do not make medical claims', 'affiliate-master-enhancement'),
                    __('Do not guarantee specific results', 'affiliate-master-enhancement'),
                    __('Do not use superlatives without proof', 'affiliate-master-enhancement')
                ]
            ]
        ];
    }

    /**
     * Get usage instructions for marketing materials
     * @return array Usage instructions
     */
    private function get_usage_instructions() {
        return [
            'general_guidelines' => [
                __('Always disclose affiliate relationships with #ad or #affiliate hashtags', 'affiliate-master-enhancement'),
                __('Use provided tracking links to ensure proper attribution', 'affiliate-master-enhancement'),
                __('Maintain brand guidelines and messaging consistency', 'affiliate-master-enhancement'),
                __('Test all links before publishing content', 'affiliate-master-enhancement')
            ],
            'platform_specific' => [
                'email' => [
                    __('Include unsubscribe links in all marketing emails', 'affiliate-master-enhancement'),
                    __('Personalise subject lines for better engagement', 'affiliate-master-enhancement'),
                    __('Use A/B testing to optimise performance', 'affiliate-master-enhancement')
                ],
                'social_media' => [
                    __('Follow platform-specific disclosure requirements', 'affiliate-master-enhancement'),
                    __('Use appropriate hashtags for each platform', 'affiliate-master-enhancement'),
                    __('Engage authentically with your audience', 'affiliate-master-enhancement')
                ],
                'websites' => [
                    __('Ensure mobile responsiveness of all elements', 'affiliate-master-enhancement'),
                    __('Optimise page load times for better conversion', 'affiliate-master-enhancement'),
                    __('Include clear calls-to-action throughout content', 'affiliate-master-enhancement')
                ]
            ],
            'performance_tips' => [
                __('Monitor click-through rates and adjust messaging accordingly', 'affiliate-master-enhancement'),
                __('Use urgency and scarcity tactics ethically', 'affiliate-master-enhancement'),
                __('Focus on benefits rather than features', 'affiliate-master-enhancement'),
                __('Include social proof and testimonials where possible', 'affiliate-master-enhancement')
            ],
            'compliance' => [
                __('Comply with FTC disclosure requirements', 'affiliate-master-enhancement'),
                __('Respect GDPR and data protection regulations', 'affiliate-master-enhancement'),
                __('Follow platform terms of service', 'affiliate-master-enhancement'),
                __('Maintain transparent communication with audience', 'affiliate-master-enhancement')
            ]
        ];
    }

    /**
     * Save custom marketing template
     * @param int $affiliate_id Affiliate ID
     * @param string $template_type Template type
     * @param array $template_data Template data
     * @return bool Success status
     */
    public function save_custom_template($affiliate_id, $template_type, $template_data) {
        $custom_templates = get_user_meta($affiliate_id, 'custom_marketing_templates', true) ?: [];
        
        $template_id = uniqid('template_');
        $custom_templates[$template_id] = [
            'type' => sanitize_text_field($template_type),
            'data' => $template_data,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        return update_user_meta($affiliate_id, 'custom_marketing_templates', $custom_templates);
    }

    /**
     * Get custom marketing templates
     * @param int $affiliate_id Affiliate ID
     * @param string $template_type Optional template type filter
     * @return array Custom templates
     */
    public function get_custom_templates($affiliate_id, $template_type = null) {
        $custom_templates = get_user_meta($affiliate_id, 'custom_marketing_templates', true) ?: [];
        
        if ($template_type) {
            $custom_templates = array_filter($custom_templates, function($template) use ($template_type) {
                return $template['type'] === $template_type;
            });
        }
        
     return $custom_templates;
    }

    /**
     * Delete custom template
     * @param int $affiliate_id Affiliate ID
     * @param string $template_id Template ID to delete
     * @return bool Success status
     */
    public function delete_custom_template($affiliate_id, $template_id) {
        $custom_templates = get_user_meta($affiliate_id, 'custom_marketing_templates', true) ?: [];
        
        if (isset($custom_templates[$template_id])) {
            unset($custom_templates[$template_id]);
            return update_user_meta($affiliate_id, 'custom_marketing_templates', $custom_templates);
        }
        
        return false;
    }

    /**
     * Update custom template
     * @param int $affiliate_id Affiliate ID
     * @param string $template_id Template ID
     * @param array $template_data Updated template data
     * @return bool Success status
     */
    public function update_custom_template($affiliate_id, $template_id, $template_data) {
        $custom_templates = get_user_meta($affiliate_id, 'custom_marketing_templates', true) ?: [];
        
        if (isset($custom_templates[$template_id])) {
            $custom_templates[$template_id]['data'] = $template_data;
            $custom_templates[$template_id]['updated_at'] = current_time('mysql');
            
            return update_user_meta($affiliate_id, 'custom_marketing_templates', $custom_templates);
        }
        
        return false;
    }

    /**
     * Clone template for modification
     * @param int $affiliate_id Affiliate ID
     * @param string $template_id Template ID to clone
     * @param string $new_name New template name
     * @return string|false New template ID or false on failure
     */
    public function clone_template($affiliate_id, $template_id, $new_name) {
        $custom_templates = get_user_meta($affiliate_id, 'custom_marketing_templates', true) ?: [];
        
        if (isset($custom_templates[$template_id])) {
            $new_template_id = uniqid('template_');
            $custom_templates[$new_template_id] = $custom_templates[$template_id];
            $custom_templates[$new_template_id]['data']['name'] = sanitize_text_field($new_name);
            $custom_templates[$new_template_id]['created_at'] = current_time('mysql');
            $custom_templates[$new_template_id]['updated_at'] = current_time('mysql');
            
            if (update_user_meta($affiliate_id, 'custom_marketing_templates', $custom_templates)) {
                return $new_template_id;
            }
        }
        
        return false;
    }

    /**
     * Get template usage statistics
     * @param int $affiliate_id Affiliate ID
     * @param string $template_id Template ID
     * @return array Usage statistics
     */
    public function get_template_usage_stats($affiliate_id, $template_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_marketing_usage';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_uses,
                AVG(JSON_EXTRACT(metrics, '$.clicks')) as avg_clicks,
                AVG(JSON_EXTRACT(metrics, '$.conversions')) as avg_conversions,
                AVG(JSON_EXTRACT(metrics, '$.conversion_rate')) as avg_conversion_rate,
                SUM(JSON_EXTRACT(metrics, '$.revenue')) as total_revenue,
                MAX(created_at) as last_used
            FROM {$table_name}
            WHERE affiliate_id = %d 
            AND JSON_EXTRACT(metrics, '$.template_id') = %s
        ", $affiliate_id, $template_id));
        
        return [
            'total_uses' => intval($stats->total_uses ?? 0),
            'avg_clicks' => floatval($stats->avg_clicks ?? 0),
            'avg_conversions' => floatval($stats->avg_conversions ?? 0),
            'avg_conversion_rate' => floatval($stats->avg_conversion_rate ?? 0),
            'total_revenue' => floatval($stats->total_revenue ?? 0),
            'last_used' => $stats->last_used,
            'performance_score' => $this->calculate_template_performance_score($stats)
        ];
    }

    /**
     * Calculate template performance score
     * @param object $stats Usage statistics
     * @return float Performance score (0-100)
     */
    private function calculate_template_performance_score($stats) {
        if (!$stats || $stats->total_uses == 0) {
            return 0;
        }
        
        $conversion_score = min(floatval($stats->avg_conversion_rate ?? 0) * 10, 40); // Max 40 points
        $revenue_score = min(floatval($stats->total_revenue ?? 0) / 100, 30); // Max 30 points
        $usage_score = min(intval($stats->total_uses ?? 0) * 2, 30); // Max 30 points
        
        return round($conversion_score + $revenue_score + $usage_score, 2);
    }

    /**
     * Get recommended templates based on performance
     * @param int $affiliate_id Affiliate ID
     * @param int $limit Number of recommendations
     * @return array Recommended templates
     */
    public function get_recommended_templates($affiliate_id, $limit = 5) {
        $custom_templates = get_user_meta($affiliate_id, 'custom_marketing_templates', true) ?: [];
        $template_recommendations = [];
        
        foreach ($custom_templates as $template_id => $template) {
            $stats = $this->get_template_usage_stats($affiliate_id, $template_id);
            $template_recommendations[] = [
                'template_id' => $template_id,
                'template_data' => $template,
                'performance_score' => $stats['performance_score'],
                'usage_stats' => $stats
            ];
        }
        
        // Sort by performance score
        usort($template_recommendations, function($a, $b) {
            return $b['performance_score'] <=> $a['performance_score'];
        });
        
        return array_slice($template_recommendations, 0, $limit);
    }

    /**
     * Generate QR codes for affiliate links
     * @param int $affiliate_id Affiliate ID
     * @param array $urls URLs to generate QR codes for
     * @return array QR code data
     */
    public function generate_qr_codes($affiliate_id, $urls) {
        $qr_codes = [];
        
        foreach ($urls as $key => $url) {
            $qr_data = $this->create_qr_code($url, $key);
            if ($qr_data) {
                $qr_codes[$key] = $qr_data;
            }
        }
        
        return $qr_codes;
    }

    /**
     * Create individual QR code
     * @param string $url URL to encode
     * @param string $identifier QR code identifier
     * @return array|false QR code data or false on failure
     */
    private function create_qr_code($url, $identifier) {
        // Use Google Charts API for QR code generation
        $qr_url = 'https://chart.googleapis.com/chart?' . http_build_query([
            'chs' => '300x300',
            'cht' => 'qr',
            'chl' => $url,
            'choe' => 'UTF-8'
        ]);
        
        return [
            'identifier' => $identifier,
            'url' => $url,
            'qr_image_url' => $qr_url,
            'download_url' => $qr_url,
            'embed_code' => sprintf(
                '<img src="%s" alt="QR Code for %s" style="max-width: 300px; height: auto;">',
                esc_url($qr_url),
                esc_attr($identifier)
            )
        ];
    }

    /**
     * Generate print-ready materials
     * @param int $affiliate_id Affiliate ID
     * @param array $specifications Print specifications
     * @return array Print-ready materials
     */
    public function generate_print_materials($affiliate_id, $specifications = []) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        
        $print_materials = [
            'business_cards' => $this->generate_business_card_design($affiliate_id, $specifications),
            'flyers' => $this->generate_flyer_design($affiliate_id, $specifications),
            'brochures' => $this->generate_brochure_design($affiliate_id, $specifications),
            'postcards' => $this->generate_postcard_design($affiliate_id, $specifications),
            'posters' => $this->generate_poster_design($affiliate_id, $specifications)
        ];
        
        return $print_materials;
    }

    /**
     * Generate business card design
     * @param int $affiliate_id Affiliate ID
     * @param array $specifications Design specifications
     * @return array Business card design data
     */
    private function generate_business_card_design($affiliate_id, $specifications) {
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $affiliate_name = $this->get_affiliate_name($affiliate_id);
        
        return [
            'name' => __('Business Card Design', 'affiliate-master-enhancement'),
            'dimensions' => '3.5x2 inches',
            'dpi' => 300,
            'format' => 'PDF',
            'front_design' => $this->generate_business_card_front($affiliate_id),
            'back_design' => $this->generate_business_card_back($affiliate_id),
            'print_instructions' => [
                __('Use high-quality cardstock (14pt minimum)', 'affiliate-master-enhancement'),
                __('Print at 300 DPI for best quality', 'affiliate-master-enhancement'),
                __('Allow 0.125" bleed on all sides', 'affiliate-master-enhancement')
            ]
        ];
    }

    /**
     * Generate business card front design
     * @param int $affiliate_id Affiliate ID
     * @return string Front design HTML/CSS
     */
    private function generate_business_card_front($affiliate_id) {
        $affiliate_name = $this->get_affiliate_name($affiliate_id);
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        
        return sprintf('
        <div class="business-card-front" style="
            width: 3.5in;
            height: 2in;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.25in;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            position: relative;
        ">
            <div style="position: absolute; top: 0.25in; left: 0.25in;">
                <h2 style="margin: 0; font-size: 16pt; font-weight: bold;">%s</h2>
                <p style="margin: 5px 0 0 0; font-size: 12pt; opacity: 0.9;">Affiliate Partner</p>
            </div>
            <div style="position: absolute; bottom: 0.25in; right: 0.25in; text-align: right;">
                <p style="margin: 0; font-size: 10pt; font-weight: bold;">Use Code:</p>
                <p style="margin: 2px 0 0 0; font-size: 14pt; font-weight: bold; background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 3px;">%s</p>
            </div>
        </div>',
            esc_html($affiliate_name),
            esc_html($affiliate_code)
        );
    }

    /**
     * Generate business card back design
     * @param int $affiliate_id Affiliate ID
     * @return string Back design HTML/CSS
     */
    private function generate_business_card_back($affiliate_id) {
        $domain_url = $this->get_affiliate_domain_url($affiliate_id);
        $affiliate_code = $this->get_affiliate_code($affiliate_id);
        $qr_url = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($domain_url . '/?affiliate_code=' . $affiliate_code);
        
        return sprintf('
        <div class="business-card-back" style="
            width: 3.5in;
            height: 2in;
            background: white;
            color: #333;
            padding: 0.25in;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        ">
            <div>
                <img src="%s" alt="QR Code" style="width: 1in; height: 1in;">
                <p style="margin: 10px 0 5px 0; font-size: 9pt; color: #666;">Scan for instant access</p>
                <p style="margin: 0; font-size: 8pt; color: #999;">%s</p>
            </div>
        </div>',
            esc_url($qr_url),
            esc_html(parse_url($domain_url, PHP_URL_HOST))
        );
    }

    /**
     * Export materials as PDF
     * @param int $affiliate_id Affiliate ID
     * @param array $materials Materials to export
     * @return string PDF file path
     */
    public function export_as_pdf($affiliate_id, $materials) {
        require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem.php');
        WP_Filesystem();
        
        $pdf_content = $this->generate_pdf_content($materials);
        $pdf_filename = 'affiliate_materials_' . $affiliate_id . '_' . date('Y-m-d') . '.pdf';
        $pdf_path = WP_CONTENT_DIR . '/uploads/affiliate_pdfs/' . $pdf_filename;
        
        // Ensure directory exists
        wp_mkdir_p(dirname($pdf_path));
        
        // Use wkhtmltopdf or similar library for PDF generation
        // This is a simplified example - you'd need a PDF library
        $html_content = $this->convert_materials_to_html($materials);
        
        // Save HTML temporarily
        $html_path = $pdf_path . '.html';
        file_put_contents($html_path, $html_content);
        
        return $pdf_path;
    }

    /**
     * Convert materials to HTML for PDF generation
     * @param array $materials Materials data
     * @return string HTML content
     */
    private function convert_materials_to_html($materials) {
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>Affiliate Marketing Materials</title>';
        $html .= '<style>body { font-family: Arial, sans-serif; margin: 20px; }</style>';
        $html .= '</head><body>';
        
        foreach ($materials as $type => $material_data) {
            $html .= '<h2>' . esc_html(ucfirst($type)) . ' Materials</h2>';
            
            if (is_array($material_data)) {
                foreach ($material_data as $key => $item) {
                    $html .= '<div class="material-item">';
                    $html .= '<h3>' . esc_html($key) . '</h3>';
                    
                    if (isset($item['html_code'])) {
                        $html .= '<div class="material-preview">' . $item['html_code'] . '</div>';
                    }
                    
                    if (isset($item['content'])) {
                        $html .= '<div class="material-content">' . nl2br(esc_html($item['content'])) . '</div>';
                    }
                    
                    $html .= '</div><hr>';
                }
            }
        }
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Get affiliate portal dashboard data
     * @param int $affiliate_id Affiliate ID
     * @return array Dashboard data
     */
    public function get_portal_dashboard_data($affiliate_id) {
        return [
            'overview' => [
                'total_materials' => $this->count_available_materials($affiliate_id),
                'custom_templates' => count($this->get_custom_templates($affiliate_id)),
                'materials_used_this_month' => $this->get_materials_used_count($affiliate_id, '30_days'),
                'top_performing_material' => $this->get_top_performing_material($affiliate_id)
            ],
            'recent_materials' => $this->get_recently_generated_materials($affiliate_id, 5),
            'performance_summary' => $this->get_marketing_performance($affiliate_id, '30_days'),
            'recommendations' => $this->get_recommended_templates($affiliate_id, 3),
            'upcoming_scheduled' => $this->get_upcoming_scheduled_materials($affiliate_id)
        ];
    }

    /**
     * Count available materials for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return int Total materials count
     */
    private function count_available_materials($affiliate_id) {
        $materials_count = 0;
        
        $material_types = ['banner', 'email', 'social', 'landing'];
        foreach ($material_types as $type) {
            $materials = $this->generate_marketing_materials($affiliate_id, $type);
            $materials_count += count($materials);
        }
        
        return $materials_count;
    }

    /**
     * Get materials used count for period
     * @param int $affiliate_id Affiliate ID
     * @param string $period Time period
     * @return int Usage count
     */
    private function get_materials_used_count($affiliate_id, $period) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}affiliate_marketing_usage
            WHERE affiliate_id = %d
            {$date_condition}
        ", $affiliate_id)));
    }

    /**
     * Get top performing material
     * @param int $affiliate_id Affiliate ID
     * @return array|null Top performing material
     */
    private function get_top_performing_material($affiliate_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                material_type,
                platform,
                AVG(JSON_EXTRACT(metrics, '$.conversion_rate')) as avg_conversion_rate,
                SUM(JSON_EXTRACT(metrics, '$.revenue')) as total_revenue
            FROM {$wpdb->prefix}affiliate_marketing_usage
            WHERE affiliate_id = %d
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY material_type, platform
            ORDER BY avg_conversion_rate DESC, total_revenue DESC
            LIMIT 1
        ", $affiliate_id), ARRAY_A);
    }

    /**
     * Get recently generated materials
     * @param int $affiliate_id Affiliate ID
     * @param int $limit Number of materials to return
     * @return array Recent materials
     */
    private function get_recently_generated_materials($affiliate_id, $limit) {
        $saved_materials = get_user_meta($affiliate_id, 'generated_marketing_materials', true) ?: [];
        
        $recent_materials = [];
        foreach ($saved_materials as $type => $material_data) {
            $recent_materials[] = [
                'type' => $type,
                'generated_at' => $material_data['generated_at'],
                'count' => count($material_data['materials']),
                'version' => $material_data['version']
            ];
        }
        
        // Sort by generated date
        usort($recent_materials, function($a, $b) {
            return strtotime($b['generated_at']) - strtotime($a['generated_at']);
        });
        
        return array_slice($recent_materials, 0, $limit);
    }

    /**
     * Get upcoming scheduled materials
     * @param int $affiliate_id Affiliate ID
     * @return array Upcoming scheduled materials
     */
    private function get_upcoming_scheduled_materials($affiliate_id) {
        $hook_name = 'affiliate_generate_materials_' . $affiliate_id;
        $next_scheduled = wp_next_scheduled($hook_name);
        
        if ($next_scheduled) {
            return [
                'next_generation' => date('Y-m-d H:i:s', $next_scheduled),
                'frequency' => $this->get_schedule_frequency($hook_name),
                'material_types' => $this->get_scheduled_material_types($hook_name)
            ];
        }
        
        return null;
    }

    /**
     * Get schedule frequency for hook
     * @param string $hook_name Hook name
     * @return string Frequency
     */
    private function get_schedule_frequency($hook_name) {
        $cron_array = get_option('cron', []);
        
        foreach ($cron_array as $timestamp => $cron) {
            if (isset($cron[$hook_name])) {
                return key($cron[$hook_name]);
            }
        }
        
        return 'unknown';
    }

    /**
     * Get scheduled material types
     * @param string $hook_name Hook name
     * @return array Material types
     */
    private function get_scheduled_material_types($hook_name) {
        $cron_array = get_option('cron', []);
        
        foreach ($cron_array as $timestamp => $cron) {
            if (isset($cron[$hook_name])) {
                $hook_data = current($cron[$hook_name]);
                return $hook_data['args'][1] ?? [];
            }
        }
        
        return [];
    }
}