<?php
/**
 * Plugin Name: Cross-Domain Affiliate System Enhancement
 * Plugin URI: https://starneconsulting.com
 * Description: Enhanced affiliate management system with comprehensive cross-domain integration, advanced analytics, and affiliate portal enhancements
 * Version: 1.0.0
 * Author: Richard King
 * Author URI: mailto:r.king@starneconsulting.com
 * License: GPL v2 or later
 * Text Domain: affiliate-master-enhancement
 * Domain Path: /languages
 * 
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AME_VERSION', '1.0.0');
define('AME_PLUGIN_FILE', __FILE__);
define('AME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AME_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AME_INCLUDES_DIR', AME_PLUGIN_DIR . 'includes/');
define('AME_ASSETS_URL', AME_PLUGIN_URL . 'assets/');

/**
 * Main plugin class for Cross-Domain Affiliate System Enhancement
 * Coordinates all enhancement modules and ensures proper initialisation
 */
class CrossDomainAffiliateSystemEnhancement {
    
    /**
     * Plugin instance
     * @var CrossDomainAffiliateSystemEnhancement
     */
    private static $instance = null;
    
    /**
     * Enhancement modules
     * @var array
     */
    private $modules = [];
    
    /**
     * Get plugin instance
     * @return CrossDomainAffiliateSystemEnhancement
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - initialise plugin
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_modules();
    }
    
    /**
     * Load required files and dependencies
     */
    private function load_dependencies() {
        // Core enhancement classes
        require_once AME_INCLUDES_DIR . 'class-affiliate-portal-enhancement.php';
        require_once AME_INCLUDES_DIR . 'class-affiliatewp-ecosystem-integration.php';
        require_once AME_INCLUDES_DIR . 'class-enhanced-commission-calculator.php';
        require_once AME_INCLUDES_DIR . 'class-cross-site-link-optimisation.php';
        require_once AME_INCLUDES_DIR . 'class-affiliate-link-health-monitor.php';
        require_once AME_INCLUDES_DIR . 'class-satellite-data-backflow-manager.php';
        require_once AME_INCLUDES_DIR . 'class-enhanced-role-management.php';
        require_once AME_INCLUDES_DIR . 'class-system-readiness-assessment.php';
        
        // Admin interface enhancements
        require_once AME_INCLUDES_DIR . 'admin/class-admin-dashboard-enhancement.php';
        require_once AME_INCLUDES_DIR . 'admin/class-affiliate-management-interface.php';
        
        // API enhancements
        require_once AME_INCLUDES_DIR . 'api/class-enhanced-rest-endpoints.php';
        require_once AME_INCLUDES_DIR . 'api/class-affiliate-analytics-api.php';
        
        // Utility classes
        require_once AME_INCLUDES_DIR . 'utilities/class-performance-monitor.php';
        require_once AME_INCLUDES_DIR . 'utilities/class-data-validator.php';
        require_once AME_INCLUDES_DIR . 'utilities/class-cache-manager.php';
    }
    
    /**
     * Initialise WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Hook into AffiliateWP if available
        add_action('plugins_loaded', [$this, 'check_affiliatewp_dependency']);
    }
    
    /**
     * Initialise enhancement modules
     */
    private function init_modules() {
        if (!$this->is_affiliatewp_active()) {
            add_action('admin_notices', [$this, 'show_affiliatewp_required_notice']);
            return;
        }
        
        // Initialise core enhancement modules
        $this->modules['portal_enhancement'] = new AffiliatePortalEnhancement();
        $this->modules['ecosystem_integration'] = new AffiliateWPEcosystemIntegration();
        $this->modules['commission_calculator'] = new EnhancedCommissionCalculator();
        $this->modules['link_optimisation'] = new CrossSiteLinkOptimisation();
        $this->modules['link_health_monitor'] = new AffiliateLinkHealthMonitor();
        $this->modules['backflow_manager'] = new SatelliteDataBackflowManager();
        $this->modules['role_management'] = new EnhancedRoleManagement();
        $this->modules['readiness_assessment'] = new SystemReadinessAssessment();
        
        // Initialise admin interfaces
        if (is_admin()) {
            $this->modules['admin_dashboard'] = new AdminDashboardEnhancement();
            $this->modules['affiliate_management'] = new AffiliateManagementInterface();
        }
        
        // Initialise API enhancements
        $this->modules['rest_endpoints'] = new EnhancedRestEndpoints();
        $this->modules['analytics_api'] = new AffiliateAnalyticsAPI();
    }
    
    /**
     * Check if AffiliateWP is active and meets minimum requirements
     * @return bool
     */
    private function is_affiliatewp_active() {
        return class_exists('Affiliate_WP') && version_compare(AFFILIATEWP_VERSION, '2.8.0', '>=');
    }
    
    /**
     * Check AffiliateWP dependency on plugins loaded
     */
    public function check_affiliatewp_dependency() {
        if (!$this->is_affiliatewp_active()) {
            add_action('admin_notices', [$this, 'show_affiliatewp_required_notice']);
            return;
        }
        
        // Integrate with AffiliateWP hooks
        add_action('affwp_insert_referral', [$this, 'handle_referral_created'], 10, 1);
        add_action('affwp_set_referral_status', [$this, 'handle_referral_status_change'], 10, 3);
        add_filter('affwp_get_affiliate_rate', [$this, 'apply_enhanced_commission_rates'], 10, 3);
    }
    
    /**
     * Show AffiliateWP required notice
     */
    public function show_affiliatewp_required_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Cross-Domain Affiliate System Enhancement', 'affiliate-master-enhancement'); ?></strong>
                <?php _e('requires AffiliateWP version 2.8.0 or higher to be installed and activated.', 'affiliate-master-enhancement'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('affiliate-master-enhancement', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Enqueue admin assets
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on affiliate-related admin pages
        if (strpos($hook, 'affiliate') === false && !in_array($hook, ['dashboard', 'index.php'])) {
            return;
        }
        
        wp_enqueue_script(
            'ame-admin-js',
            AME_ASSETS_URL . 'js/admin-enhancement.js',
            ['jquery', 'wp-api-fetch', 'wp-components'],
            AME_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ame-admin-css',
            AME_ASSETS_URL . 'css/admin-enhancement.css',
            [],
            AME_VERSION
        );
        
        // Localise script with admin data
        wp_localize_script('ame-admin-js', 'ameAdmin', [
            'apiUrl' => rest_url('affiliate-enhancement/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => wp_get_current_user()->ID,
            'capabilities' => $this->get_current_user_affiliate_capabilities(),
            'i18n' => $this->get_admin_i18n_strings()
        ]);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        wp_enqueue_script(
            'ame-frontend-js',
            AME_ASSETS_URL . 'js/frontend-enhancement.js',
            ['jquery'],
            AME_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ame-frontend-css',
            AME_ASSETS_URL . 'css/frontend-enhancement.css',
            [],
            AME_VERSION
        );
        
        // Localise frontend script
        wp_localize_script('ame-frontend-js', 'ameFrontend', [
            'apiUrl' => rest_url('affiliate-enhancement/v1/'),
            'domain' => home_url(),
            'affiliateId' => $this->get_current_user_affiliate_id(),
            'trackingEnabled' => $this->is_tracking_enabled()
        ]);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        if (isset($this->modules['rest_endpoints'])) {
            $this->modules['rest_endpoints']->register_routes();
        }
        
        if (isset($this->modules['analytics_api'])) {
            $this->modules['analytics_api']->register_routes();
        }
    }
    
    /**
     * Handle referral creation for enhanced tracking
     * @param int $referral_id Referral ID
     */
    public function handle_referral_created($referral_id) {
        if (isset($this->modules['backflow_manager'])) {
            $this->modules['backflow_manager']->process_referral_data($referral_id);
        }
    }
    
    /**
     * Handle referral status changes
     * @param int $referral_id Referral ID
     * @param string $new_status New status
     * @param string $old_status Old status
     */
    public function handle_referral_status_change($referral_id, $new_status, $old_status) {
        if (isset($this->modules['commission_calculator'])) {
            $this->modules['commission_calculator']->handle_status_change($referral_id, $new_status, $old_status);
        }
    }
    
    /**
     * Apply enhanced commission rates
     * @param float $rate Current rate
     * @param int $affiliate_id Affiliate ID
     * @param array $args Additional arguments
     * @return float Modified rate
     */
    public function apply_enhanced_commission_rates($rate, $affiliate_id, $args = []) {
        if (isset($this->modules['commission_calculator'])) {
            return $this->modules['commission_calculator']->calculate_enhanced_rate($rate, $affiliate_id, $args);
        }
        
        return $rate;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        if (!$this->is_affiliatewp_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires AffiliateWP to be installed and activated.', 'affiliate-master-enhancement'));
        }
        
        $this->create_database_tables();
        $this->create_enhanced_roles();
        $this->set_default_options();
        
        // Schedule monitoring cron jobs
        if (!wp_next_scheduled('ame_monitor_system_health')) {
            wp_schedule_event(time(), 'hourly', 'ame_monitor_system_health');
        }
        
        if (!wp_next_scheduled('ame_update_analytics_cache')) {
            wp_schedule_event(time(), 'daily', 'ame_update_analytics_cache');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('ame_monitor_system_health');
        wp_clear_scheduled_hook('ame_update_analytics_cache');
        
        flush_rewrite_rules();
    }
    
    /**
     * Create required database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Enhanced affiliate analytics table
        $analytics_table = $wpdb->prefix . 'affiliate_enhanced_analytics';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value decimal(15,4) NOT NULL DEFAULT 0.0000,
            domain varchar(255) NOT NULL DEFAULT '',
            date_recorded date NOT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_metric_index (affiliate_id, metric_type),
            KEY domain_date_index (domain, date_recorded),
            KEY performance_index (affiliate_id, metric_type, date_recorded)
        ) $charset_collate;";
        
        // Link performance tracking table
        $link_performance_table = $wpdb->prefix . 'affiliate_link_performance';
        $sql_link_performance = "CREATE TABLE $link_performance_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            link_url varchar(500) NOT NULL,
            domain varchar(255) NOT NULL,
            clicks bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            conversions bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            revenue decimal(15,2) NOT NULL DEFAULT 0.00,
            last_click_date datetime DEFAULT NULL,
            last_conversion_date datetime DEFAULT NULL,
            status enum('active', 'inactive', 'broken') NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY link_domain_unique (link_url(191), domain),
            KEY affiliate_performance_index (affiliate_id, status),
            KEY domain_status_index (domain, status)
        ) $charset_collate;";
        
        // User journey tracking table
        $journey_table = $wpdb->prefix . 'affiliate_user_journeys';
        $sql_journey = "CREATE TABLE $journey_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            domain varchar(255) NOT NULL,
            entry_point varchar(500) NOT NULL,
            touchpoints longtext,
            conversion_data longtext,
            journey_duration int UNSIGNED NOT NULL DEFAULT 0,
            pages_visited int UNSIGNED NOT NULL DEFAULT 0,
            converted tinyint(1) NOT NULL DEFAULT 0,
            conversion_value decimal(15,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_index (session_id),
            KEY affiliate_conversion_index (affiliate_id, converted),
            KEY domain_date_index (domain, created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_analytics);
        dbDelta($sql_link_performance);
        dbDelta($sql_journey);
    }
    
    /**
     * Create enhanced user roles
     */
    private function create_enhanced_roles() {
        if (isset($this->modules['role_management'])) {
            $this->modules['role_management']->create_enhanced_roles();
        }
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = [
            'ame_monitoring_enabled' => true,
            'ame_cache_duration' => 3600, // 1 hour
            'ame_analytics_retention_days' => 90,
            'ame_link_check_frequency' => 'daily',
            'ame_performance_thresholds' => [
                'response_time' => 2000, // milliseconds
                'conversion_rate' => 0.02, // 2%
                'error_rate' => 0.05 // 5%
            ]
        ];
        
        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }
    
    /**
     * Get current user affiliate capabilities
     * @return array
     */
    private function get_current_user_affiliate_capabilities() {
        $user = wp_get_current_user();
        $capabilities = [];
        
        $affiliate_caps = [
            'manage_affiliate_codes',
            'view_affiliate_analytics',
            'edit_affiliate_settings',
            'export_affiliate_data',
            'manage_affiliate_users',
            'access_advanced_analytics',
            'create_custom_vanity_codes',
            'access_marketing_automation'
        ];
        
        foreach ($affiliate_caps as $cap) {
            $capabilities[$cap] = $user->has_cap($cap);
        }
        
        return $capabilities;
    }
    
    /**
     * Get admin internationalisation strings
     * @return array
     */
    private function get_admin_i18n_strings() {
        return [
            'loading' => __('Loading...', 'affiliate-master-enhancement'),
            'error' => __('An error occurred', 'affiliate-master-enhancement'),
            'success' => __('Operation completed successfully', 'affiliate-master-enhancement'),
            'confirmDelete' => __('Are you sure you want to delete this item?', 'affiliate-master-enhancement'),
            'saveChanges' => __('Save Changes', 'affiliate-master-enhancement'),
            'cancel' => __('Cancel', 'affiliate-master-enhancement'),
            'edit' => __('Edit', 'affiliate-master-enhancement'),
            'delete' => __('Delete', 'affiliate-master-enhancement'),
            'view' => __('View', 'affiliate-master-enhancement')
        ];
    }
    
    /**
     * Determine if frontend assets should be loaded
     * @return bool
     */
    private function should_load_frontend_assets() {
        // Load on pages with affiliate content or tracking
        return is_user_logged_in() && (
            $this->get_current_user_affiliate_id() ||
            has_shortcode(get_post()->post_content ?? '', 'affiliate_') ||
            is_page(['affiliate-dashboard', 'affiliate-portal'])
        );
    }
    
    /**
     * Get current user's affiliate ID if they are an affiliate
     * @return int|null
     */
    private function get_current_user_affiliate_id() {
        if (!function_exists('affwp_get_affiliate_id')) {
            return null;
        }
        
        return affwp_get_affiliate_id(wp_get_current_user()->ID);
    }
    
    /**
     * Check if tracking is enabled for current user/context
     * @return bool
     */
    private function is_tracking_enabled() {
        return get_option('ame_monitoring_enabled', true) && !current_user_can('manage_options');
    }
    
    /**
     * Get module instance
     * @param string $module_name Module name
     * @return object|null
     */
    public function get_module($module_name) {
        return isset($this->modules[$module_name]) ? $this->modules[$module_name] : null;
    }
}

// Initialise plugin
CrossDomainAffiliateSystemEnhancement::get_instance();

// Schedule monitoring tasks
add_action('ame_monitor_system_health', function() {
    $plugin = CrossDomainAffiliateSystemEnhancement::get_instance();
    $monitor = $plugin->get_module('link_health_monitor');
    
    if ($monitor) {
        $monitor->run_health_check();
    }
});

add_action('ame_update_analytics_cache', function() {
    $plugin = CrossDomainAffiliateSystemEnhancement::get_instance();
    $analytics = $plugin->get_module('analytics_api');
    
    if ($analytics) {
        $analytics->update_analytics_cache();
    }
});