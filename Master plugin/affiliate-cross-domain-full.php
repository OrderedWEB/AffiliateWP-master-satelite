<?php
/**
 * Plugin Name: AffiliateWP Cross Domain Affiliate Tracking Master Plugin
 * Plugin URI: https://starneconsulting.com/affiliatewp-cross-domain-full
 * Description: Central hub for cross-domain affiliate discount code validation and management. Extends AffiliateWP with vanity codes, multi-site API endpoints, comprehensive analytics, health monitoring, portal enhancement, and advanced commission management across client installations. Requires partner satellite plugin on the Client domain.
 * Version: 1.0.0
 * Author: Richard King, Starne Consulting
 * Author URI: https://starneconsulting.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: affiliatewp-cross-domain-plugin-suite
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constants
 */
define('AFFCD_VERSION', '1.0.0');
define('AFFCD_PLUGIN_FILE', __FILE__);
define('AFFCD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AFFCD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AFFCD_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AME_ASSETS_URL', AFFCD_PLUGIN_URL . 'assets/');

// Legacy constants for backwards compatibility
define('AFFILIATEWP_CROSS_DOMAIN_VERSION', AFFCD_VERSION);
define('AFFILIATEWP_CROSS_DOMAIN_PLUGIN_FILE', AFFCD_PLUGIN_FILE);
define('AFFILIATEWP_CROSS_DOMAIN_PLUGIN_DIR', AFFCD_PLUGIN_DIR);
define('AFFILIATEWP_CROSS_DOMAIN_PLUGIN_URL', AFFCD_PLUGIN_URL);
define('AFFILIATEWP_CROSS_DOMAIN_PLUGIN_BASENAME', AFFCD_PLUGIN_BASENAME);

/**
 * Database debug logging function
 */
function affcd_db_debug_log($message, $step = '', $query = '') {
    $timestamp = date('Y-m-d H:i:s');
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    
    $log_entry = "[{$timestamp}] DB_STEP: {$step} | MESSAGE: {$message} | MEMORY: " . size_format($memory_usage) . " | PEAK: " . size_format($memory_peak);
    
    if ($query) {
        $log_entry .= " | QUERY: " . substr($query, 0, 200) . "...";
    }
    
    $log_entry .= "\n";
    
    // Log to WordPress debug log
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($log_entry);
    }
    
    // Also log to custom file
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/affcd-database-debug.log';
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * General debug logging function
 */
function affcd_debug_log($message, $step = '') {
    $timestamp = date('Y-m-d H:i:s');
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    
    $log_entry = "[{$timestamp}] STEP: {$step} | MESSAGE: {$message} | MEMORY: " . size_format($memory_usage) . " | PEAK: " . size_format($memory_peak) . "\n";
    
    // Log to WordPress debug log
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($log_entry);
    }
    
    // Also log to custom file for easier tracking
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/affcd-activation-debug.log';
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Track activation start
affcd_debug_log('Plugin file loaded', 'FILE_LOAD');

/**
 * Main Plugin Class with Debug Tracking and Enhanced Components
 */
class AffiliateWP_Cross_Domain_Full {
    
    private static $instance = null;
    private static $debug_step = 0;
    
    /**
     * Original core components
     */
    public $database_manager;
    public $api_endpoints;
    public $security_validator;
    public $rate_limiter;
    public $vanity_code_manager;
    public $webhook_manager;
    public $webhook_handler;
    public $admin_menu;
    public $domain_manager;
    
    public $plugin_manager;
    public $health_monitor;
    public $portal_enhancement;
    public $commission_calculator;
    public $role_management;
    public $backflow_manager;
    public $ajax_handlers;

    /**
     * Plugin activated flag
     */
    private $activated = false;
    
    /**
     * Debug counter
     */
    private static function next_debug_step($description) {
        self::$debug_step++;
        affcd_debug_log($description, 'STEP_' . self::$debug_step);
    }

    /**
     * Get plugin instance (singleton)
     */
    public static function instance() {
        self::next_debug_step('Instance method called');
        
        if (null === self::$instance) {
            self::next_debug_step('Creating new instance');
            self::$instance = new self();
            self::next_debug_step('Instance created successfully');
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        self::next_debug_step('Constructor started');
        
        try {
            $this->init_hooks();
            self::next_debug_step('Hooks initialized');
            
            $this->includes();
            self::next_debug_step('Files included');
            
            $this->init_components();
            self::next_debug_step('Components initialized');
            
        } catch (Exception $e) {
            affcd_debug_log('Constructor error: ' . $e->getMessage(), 'CONSTRUCTOR_ERROR');
            throw $e;
        }
        
        self::next_debug_step('Constructor completed');
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        self::next_debug_step('Starting hook registration');
        
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, [$this, 'activation']);
        self::next_debug_step('Activation hook registered');
        
        register_deactivation_hook(__FILE__, [$this, 'deactivation']);
        self::next_debug_step('Deactivation hook registered');
        
        register_uninstall_hook(__FILE__, ['AffiliateWP_Cross_Domain_Full', 'uninstall']);
        self::next_debug_step('Uninstall hook registered');

        // WordPress core hooks
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        self::next_debug_step('Textdomain hook registered');
        
        add_action('init', [$this, 'init'], 0);
        self::next_debug_step('Init hook registered');
        
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        self::next_debug_step('REST API hook registered');
        
        // Legacy AJAX hooks
        add_action('wp_ajax_affcd_test_connection', [$this, 'ajax_test_connection']);
        self::next_debug_step('Legacy AJAX test connection hook registered');
        
        add_action('wp_ajax_nopriv_affcd_validate_code', [$this, 'ajax_validate_code']);
        self::next_debug_step('Legacy AJAX validate code hook registered');
        
        // Admin hooks
        if (is_admin()) {
            self::next_debug_step('Registering admin hooks');
            
            add_action('admin_init', [$this, 'admin_init']);
            self::next_debug_step('Admin init hook registered');
            
            add_action('admin_notices', [$this, 'admin_notices']);
            self::next_debug_step('Admin notices hook registered');
            
            add_filter('plugin_action_links_' . AFFCD_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
            self::next_debug_step('Plugin action links filter registered');
        }

        // Cleanup hooks
        add_action('affcd_cleanup_expired_codes', [$this, 'cleanup_expired_codes']);
        self::next_debug_step('Cleanup expired codes hook registered');
        
        add_action('affcd_cleanup_analytics_data', [$this, 'cleanup_analytics_data']);
        self::next_debug_step('Cleanup analytics hook registered');
        
        // Enhanced component hooks
        add_action('affiliate_health_check_cron', [$this, 'run_health_check_cron']);
        add_action('affiliate_log_cleanup_cron', [$this, 'run_log_cleanup_cron']);
        add_action('affiliate_generate_materials_cron', [$this, 'run_materials_generation_cron']);
        
        self::next_debug_step('All hooks registered successfully');
    }

/**
 * Include required files
 */
private function includes() {
    self::next_debug_step('Starting file includes');
    
    // CRITICAL: Load core files FIRST before any enhanced components
    $core_files = [
        'includes/functions.php',
        'includes/class-database-manager.php',
        'includes/class-security-validator.php',
        'includes/class-rate-limiter.php',
        'includes/class-api-endpoints.php',
        'includes/class-vanity-code-manager.php',
        'includes/class-webhook-manager.php',
        'includes/class-webhook-handler.php'
        
    ];
    
    foreach ($core_files as $file) {
        $file_path = AFFCD_PLUGIN_DIR . $file;
        
        if (file_exists($file_path)) {
            self::next_debug_step("Including: {$file}");
            require_once $file_path;
            self::next_debug_step("Successfully included: {$file}");
        } else {
            affcd_debug_log("File not found: {$file}", 'MISSING_FILE');
        }
    }
    
    // NOW load enhanced component files AFTER core is loaded
    $enhanced_files = [
        'includes/class-plugin-manager.php',
        'includes/class-affiliate-link-health-monitor.php',
        'includes/class-affiliate-portal-enhancement.php',
        'includes/class-enhanced-commission-calculator.php',
        'includes/class-enhanced-role-management.php',
        'includes/class-satellite-data-backflow-manager.php',
        'includes/class-ajax-handlers.php'
    ];
    
    foreach ($enhanced_files as $file) {
        $file_path = AFFCD_PLUGIN_DIR . $file;
        
        if (file_exists($file_path)) {
            self::next_debug_step("Including: {$file}");
            require_once $file_path;
            self::next_debug_step("Successfully included: {$file}");
        } else {
            affcd_debug_log("File not found: {$file}", 'MISSING_FILE');
        }
    }
    // Load unified router
require_once AFFCD_PLUGIN_DIR . 'includes/class-api-router.php';

// Boot it (optionally pass allowed origins and custom permission callbacks)
add_action('plugins_loaded', function () {
    new AFFCD_API_Router([
        'allowed_origins' => apply_filters('affcd_allowed_origins', []),
        // 'integration_permission_cb' => [MyAuth::class, 'validate_request'],
        // 'admin_permission_cb'       => [MyAuth::class, 'validate_admin'],
    ]);
});

    // Admin includes
    if (is_admin()) {
        self::next_debug_step('Including admin files');
        
        $admin_files = [
            'includes/class-admin-menu.php',
            'admin/class-domain-manager.php',
            'admin/class-bulk-operations.php',
            'admin/domain-management.php'
        ];
        
        foreach ($admin_files as $file) {
            $file_path = AFFCD_PLUGIN_DIR . $file;
            
            if (file_exists($file_path)) {
                self::next_debug_step("Including admin file: {$file}");
                require_once $file_path;
                self::next_debug_step("Successfully included admin file: {$file}");
            } else {
                affcd_debug_log("Admin file not found: {$file}", 'MISSING_ADMIN_FILE');
            }
        }
    }
    
    self::next_debug_step('All includes completed');
}

    /**
     * Initialize plugin components
     */
    private function init_components() {
        self::next_debug_step('Starting component initialization');
        
        try {
            // Initialize core components first
            $this->init_core_components();
            self::next_debug_step('Core components initialized');
            
            // Initialize enhanced components
            $this->init_enhanced_components();
            self::next_debug_step('Enhanced components initialized');
            
            // Wire components together
            $this->wire_components();
            self::next_debug_step('Components wired together');
            
        } catch (Exception $e) {
            affcd_debug_log('Component initialization error: ' . $e->getMessage(), 'COMPONENT_ERROR');
            throw $e;
        }
        
        self::next_debug_step('Component initialization completed');
    }
    
    /**
     * Initialize core legacy components
     */
    private function init_core_components() {
        // Use singleton pattern for database manager
        self::next_debug_step('Getting database manager instance');
        $this->database_manager = $this->get_database_manager();
        self::next_debug_step('Database manager ready');
        
        // Initialize security and rate limiting
        self::next_debug_step('Creating security validator');
        $this->security_validator = new AFFCD_Security_Validator();
        self::next_debug_step('Security validator created');


$rate_limiter_file = AFFCD_PLUGIN_DIR . 'includes/class-rate-limiter.php';
affcd_debug_log('Checking rate limiter file: ' . $rate_limiter_file, 'RATE_LIMITER_CHECK');
affcd_debug_log('File exists: ' . (file_exists($rate_limiter_file) ? 'YES' : 'NO'), 'RATE_LIMITER_EXISTS');
affcd_debug_log('Class exists: ' . (class_exists('AFFCD_Rate_Limiter') ? 'YES' : 'NO'), 'RATE_LIMITER_CLASS');

// If file exists but class doesn't, manually require it
if (file_exists($rate_limiter_file) && !class_exists('AFFCD_Rate_Limiter')) {
    affcd_debug_log('Manually requiring rate limiter file', 'RATE_LIMITER_MANUAL_LOAD');
    require_once $rate_limiter_file;
}

// Check again after manual load
affcd_debug_log('Class exists after manual load: ' . (class_exists('AFFCD_Rate_Limiter') ? 'YES' : 'NO'), 'RATE_LIMITER_FINAL_CHECK');
        self::next_debug_step('Creating rate limiter');
       $this->rate_limiter = AFFCD_Rate_Limiter::instance();
        self::next_debug_step('Rate limiter created');
        
        // Initialize business logic components
        self::next_debug_step('Creating vanity code manager');
        $this->vanity_code_manager = new AFFCD_Vanity_Code_Manager();
        self::next_debug_step('Vanity code manager created');
        
        self::next_debug_step('Creating webhook manager');
        $this->webhook_manager = new AFFCD_Webhook_Manager();
        self::next_debug_step('Webhook manager created');

        self::next_debug_step('Creating webhook handler');
        $this->webhook_handler = new AFFCD_Webhook_Handler($this->webhook_manager);
        self::next_debug_step('Webhook handler created');
        
        self::next_debug_step('Creating API endpoints');
        $this->api_endpoints = new AFFCD_API_Endpoints(
            $this->security_validator,
            $this->rate_limiter,
            $this->vanity_code_manager
        );
        self::next_debug_step('API endpoints created');

        // Initialize admin components
        if (is_admin()) {
            self::next_debug_step('Creating admin components');
            $this->admin_menu = new AFFCD_Admin_Menu();
            $this->domain_manager = class_exists('AFFCD_Domain_Manager') 
                ? new AFFCD_Domain_Manager() 
                : null;
            self::next_debug_step('Admin components created');
        }
    }
    
   /**
 * Initialize enhanced components
 */
private function init_enhanced_components() {
    // DON'T initialize plugin manager here - it causes circular dependency
    // Instead, let it initialize on 'init' hook
    
    // Only initialize components that don't call back to main plugin
    if (class_exists('Affiliate_Link_Health_Monitor')) {
        self::next_debug_step('Creating health monitor');
        $this->health_monitor = new Affiliate_Link_Health_Monitor();
        self::next_debug_step('Health monitor created');
    }
    
    if (class_exists('Affiliate_Portal_Enhancement')) {
        self::next_debug_step('Creating portal enhancement');
        $this->portal_enhancement = new Affiliate_Portal_Enhancement();
        self::next_debug_step('Portal enhancement created');
    }
    
    if (class_exists('SatelliteDataBackflowManager')) {
        self::next_debug_step('Creating backflow manager');
        $this->backflow_manager = new SatelliteDataBackflowManager(AFFCD_PLUGIN_FILE);
        self::next_debug_step('Backflow manager created');
    }
    
    if (is_admin() && class_exists('AffiliateWP_Cross_Domain_AJAX_Handlers')) {
        self::next_debug_step('Creating AJAX handlers');
        $this->ajax_handlers = new AffiliateWP_Cross_Domain_AJAX_Handlers();
        self::next_debug_step('AJAX handlers created');
    }
    
    // Defer problematic components to 'init' hook
    add_action('init', [$this, 'init_deferred_components'], 1);
}

/**
 * Initialize components that need to be deferred
 */
public function init_deferred_components() {
    // Now safe to initialize these
    if (class_exists('AffiliateWP_Cross_Domain_Plugin_Manager') && !$this->plugin_manager) {
        $this->plugin_manager = AffiliateWP_Cross_Domain_Plugin_Manager::instance();
    }
    
    if (class_exists('Enhanced_Commission_Calculator') && !$this->commission_calculator) {
        $this->commission_calculator = new Enhanced_Commission_Calculator();
    }
    
    if (class_exists('EnhancedRoleManagement') && !$this->role_management) {
        $this->role_management = EnhancedRoleManagement::instance(AFFCD_PLUGIN_FILE);
    }
}
    /**
     * Wire components together with dependencies
     */
    private function wire_components() {
        // Pass rate limiter to health monitor if both exist
        if ($this->health_monitor && $this->rate_limiter && method_exists($this->health_monitor, 'set_rate_limiter')) {
            $this->health_monitor->set_rate_limiter($this->rate_limiter);
        }
        
        // Pass commission calculator to portal enhancement if both exist
        if ($this->portal_enhancement && $this->commission_calculator && method_exists($this->portal_enhancement, 'set_commission_calculator')) {
            $this->portal_enhancement->set_commission_calculator($this->commission_calculator);
        }
        
        // Pass components to plugin manager if it exists
        if ($this->plugin_manager) {
            if ($this->role_management && method_exists($this->plugin_manager, 'set_role_management')) {
                $this->plugin_manager->set_role_management($this->role_management);
            }
            
            if ($this->backflow_manager && method_exists($this->plugin_manager, 'set_backflow_manager')) {
                $this->plugin_manager->set_backflow_manager($this->backflow_manager);
            }
        }
    }

    /**
     * Get database manager instance (singleton pattern)
     */
    private function get_database_manager() {
        if (!$this->database_manager) {
            affcd_debug_log('Creating new database manager instance', 'DB_MANAGER_CREATE');
            $this->database_manager = new AFFCD_Database_Manager();
        }
        return $this->database_manager;
    }

    /**
     * Plugin activation
     */
    public function activation() {
        affcd_debug_log('=== ACTIVATION STARTED ===', 'ACTIVATION_BEGIN');
        
        try {
            // Check requirements
            affcd_debug_log('Checking requirements', 'ACTIVATION_REQUIREMENTS');
            if (!$this->check_requirements()) {
                affcd_debug_log('Requirements check failed', 'ACTIVATION_ERROR');
                deactivate_plugins(AFFCD_PLUGIN_BASENAME);
                wp_die(__('AffiliateWP Cross Domain Full requires WordPress 5.0+ and PHP 7.4+', 'affiliatewp-cross-domain-plugin-suite'));
            }
            affcd_debug_log('Requirements check passed', 'ACTIVATION_REQUIREMENTS_OK');

            // Create database tables
            affcd_debug_log('About to create database tables', 'ACTIVATION_DB_START');
            $this->create_all_database_tables();
            affcd_debug_log('All database tables created successfully', 'ACTIVATION_DB_OK');

            // Set default options
            affcd_debug_log('Setting default options', 'ACTIVATION_OPTIONS');
            $this->set_default_options();
            affcd_debug_log('Default options set', 'ACTIVATION_OPTIONS_OK');

            // Initialize roles
            if ($this->role_management) {
                affcd_debug_log('Creating affiliate roles', 'ACTIVATION_ROLES');
                $this->role_management->create_affiliate_roles();
                affcd_debug_log('Affiliate roles created', 'ACTIVATION_ROLES_OK');
            }

            // Schedule cleanup events
            affcd_debug_log('Scheduling cleanup events', 'ACTIVATION_SCHEDULE');
            $this->schedule_all_events();
            affcd_debug_log('All events scheduled', 'ACTIVATION_SCHEDULE_OK');

            // Set activation redirect flag
            add_option('affcd_activation_redirect', true);

            affcd_debug_log('=== ACTIVATION COMPLETED SUCCESSFULLY ===', 'ACTIVATION_SUCCESS');
            
        } catch (Exception $e) {
            affcd_debug_log('Activation error: ' . $e->getMessage(), 'ACTIVATION_ERROR');
            affcd_debug_log('Error trace: ' . $e->getTraceAsString(), 'ACTIVATION_TRACE');
            throw $e;
        }
    }
    
    /**
     * Create all database tables for all components
     */
    private function create_all_database_tables() {
        // Core database tables
        $db_manager = $this->get_database_manager();
        $db_manager->create_tables();
        
        // Enhanced component tables
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Health monitoring table
        $health_table = $wpdb->prefix . 'affiliate_health_log';
        $health_sql = "CREATE TABLE $health_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            status varchar(50) NOT NULL,
            details longtext,
            meta_key varchar(255),
            meta_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Marketing usage table
        $marketing_table = $wpdb->prefix . 'affiliate_marketing_usage';
        $marketing_sql = "CREATE TABLE $marketing_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) NOT NULL,
            material_type varchar(100) NOT NULL,
            platform varchar(100) NOT NULL,
            metrics longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY material_type (material_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Commission calculations table
        $commission_table = $wpdb->prefix . 'affiliate_commission_calculations';
        $commission_sql = "CREATE TABLE $commission_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) NOT NULL,
            calculation_type varchar(100) NOT NULL,
            base_amount decimal(10,2) NOT NULL,
            calculated_amount decimal(10,2) NOT NULL,
            tier_level int(11) DEFAULT 1,
            calculation_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY calculation_type (calculation_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Rate limiting table
        $rate_limit_table = $wpdb->prefix . 'affiliate_rate_limits';
        $rate_limit_sql = "CREATE TABLE $rate_limit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            endpoint varchar(255) NOT NULL,
            request_count int(11) NOT NULL DEFAULT 0,
            window_start datetime NOT NULL,
            blocked_until datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_endpoint (identifier, endpoint),
            KEY window_start (window_start),
            KEY blocked_until (blocked_until)
        ) $charset_collate;";
        
        // Backflow data table
        $backflow_table = $wpdb->prefix . 'affiliate_backflow_data';
        $backflow_sql = "CREATE TABLE $backflow_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_domain varchar(255) NOT NULL,
            data_type varchar(100) NOT NULL,
            payload longtext NOT NULL,
            status varchar(50) DEFAULT 'pending',
            processed_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_domain (source_domain),
            KEY data_type (data_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($health_sql);
        dbDelta($marketing_sql);
        dbDelta($commission_sql);
        dbDelta($rate_limit_sql);
        dbDelta($backflow_sql);
    }
    
    /**
     * Schedule all events for all components
     */
    private function schedule_all_events() {
        // Legacy cleanup events
        if (!wp_next_scheduled('affcd_cleanup_expired_codes')) {
            wp_schedule_event(time(), 'hourly', 'affcd_cleanup_expired_codes');
        }
        
        if (!wp_next_scheduled('affcd_cleanup_analytics_data')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_analytics_data');
        }
        
        // Enhanced component events
        if (!wp_next_scheduled('affiliate_health_check_cron')) {
            wp_schedule_event(time(), 'five_minutes', 'affiliate_health_check_cron');
        }
        
        if (!wp_next_scheduled('affiliate_log_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'affiliate_log_cleanup_cron');
        }
        
        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'affiliatewp-cross-domain-plugin-suite')
        ];
        
        $schedules['fifteen_minutes'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'affiliatewp-cross-domain-plugin-suite')
        ];
        
        return $schedules;
    }

    /**
     * Plugin deactivation
     */
    public function deactivation() {
        affcd_debug_log('Deactivation started', 'DEACTIVATION_START');
        
        // Clear all scheduled events
        wp_clear_scheduled_hook('affcd_cleanup_expired_codes');
        wp_clear_scheduled_hook('affcd_cleanup_analytics_data');
        wp_clear_scheduled_hook('affiliate_health_check_cron');
        wp_clear_scheduled_hook('affiliate_log_cleanup_cron');
        
        // Clear any component-specific scheduled events
        $cron_jobs = get_option('cron', []);
        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $data) {
                if (strpos($hook, 'affiliate_generate_materials_') === 0) {
                    wp_clear_scheduled_hook($hook);
                }
            }
        }
        
        affcd_debug_log('Deactivation completed', 'DEACTIVATION_END');
    }

    /**
     * Plugin uninstall (static method)
     */
    public static function uninstall() {
        affcd_debug_log('Uninstall started', 'UNINSTALL_START');
        
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        global $wpdb;

        // Drop all database tables
        $tables_to_drop = [
            $wpdb->prefix . 'affiliate_health_log',
            $wpdb->prefix . 'affiliate_marketing_usage',
            $wpdb->prefix . 'affiliate_commission_calculations',
            $wpdb->prefix . 'affiliate_rate_limits',
            $wpdb->prefix . 'affiliate_backflow_data'
        ];
        
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Drop core legacy tables
        require_once AFFCD_PLUGIN_DIR . 'includes/class-database-manager.php';
        if (class_exists('AFFCD_Database_Manager')) {
            $database_manager = new AFFCD_Database_Manager();
            $database_manager->drop_tables();
        }

        // Remove all options
        $options_to_delete = [
            'affcd_version',
            'affcd_settings',
            'affcd_api_settings',
            'affcd_security_settings',
            'affcd_webhook_settings',
            'affcd_activation_redirect',
            'affiliate_health_monitoring_enabled',
            'affiliate_system_config'
        ];
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }

        // Clear transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_affcd_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_affcd_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_affiliate_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_affiliate_%'");
        
        // Remove custom user roles
        if (class_exists('Enhanced_Role_Management')) {
            $role_management = new Enhanced_Role_Management();
            $role_management->remove_affiliate_roles();
        }
        
        affcd_debug_log('Uninstall completed', 'UNINSTALL_END');
    }

// Initialize the enhanced role management system

    /**
     * Initialize plugin
     */
    public function init() {
        affcd_debug_log('Plugin init started', 'INIT_START');
        
        // Check AffiliateWP dependency
        if (!$this->check_affiliatewp_dependency()) {
            return;
        }

        // Set activated flag
        $this->activated = true;

        // Initialize components that require WordPress to be loaded
        do_action('affcd_loaded', $this);

        // Schedule cleanup events if not already scheduled
        $this->schedule_all_events();
        
        affcd_debug_log('Plugin init completed', 'INIT_END');
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        affcd_debug_log('Loading textdomain', 'TEXTDOMAIN_START');
        
        load_plugin_textdomain(
            'affiliatewp-cross-domain-plugin-suite',
            false,
            dirname(AFFCD_PLUGIN_BASENAME) . '/languages/'
        );
        
        affcd_debug_log('Textdomain loaded', 'TEXTDOMAIN_END');
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        affcd_debug_log('Registering REST routes', 'REST_ROUTES_START');
        
        if ($this->api_endpoints) {
            $this->api_endpoints->register_routes();
        }
        
        // Register enhanced component REST routes
        if ($this->plugin_manager && method_exists($this->plugin_manager, 'register_rest_routes')) {
            $this->plugin_manager->register_rest_routes();
        }
        
        affcd_debug_log('REST routes registered', 'REST_ROUTES_END');
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        affcd_debug_log('Admin init started', 'ADMIN_INIT_START');
        
        // Register admin settings
        $this->register_admin_settings();
        
        // Check for database updates
        $this->maybe_update_database();
        
        affcd_debug_log('Admin init completed', 'ADMIN_INIT_END');
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check for activation redirect
        if (get_option('affcd_activation_redirect', false)) {
            delete_option('affcd_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=affcd-domain-management'));
                exit;
            }
        }
        
        // Show health monitoring notices if enabled
        if ($this->health_monitor && get_option('affiliate_health_monitoring_enabled', true)) {
            $this->show_health_notices();
        }
    }
    
    /**
     * Show health monitoring notices
     */
    private function show_health_notices() {
        $health_stats = $this->health_monitor->get_health_dashboard_stats();
        
        if (isset($health_stats['critical_issues']) && $health_stats['critical_issues'] > 0) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . __('AffiliateWP Cross Domain Plugin Suite', 'affiliatewp-cross-domain-plugin-suite') . '</strong>: ';
            echo sprintf(__('%d critical health issues detected. ', 'affiliatewp-cross-domain-plugin-suite'), $health_stats['critical_issues']);
            echo '<a href="' . admin_url('admin.php?page=affiliate-health-monitoring') . '">' . __('View Health Dashboard', 'affiliatewp-cross-domain-plugin-suite') . '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        affcd_debug_log('Adding plugin action links', 'ACTION_LINKS');
        
        $action_links = [
            '<a href="' . esc_url(admin_url('admin.php?page=affiliate-cross-domain')) . '">' . __('Dashboard', 'affiliatewp-cross-domain-plugin-suite') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=affcd-domain-management')) . '">' . __('Settings', 'affiliatewp-cross-domain-plugin-suite') . '</a>',
            '<a href="https://starneconsulting.com/docs/affiliatewp-cross-domain" target="_blank">' . __('Documentation', 'affiliatewp-cross-domain-plugin-suite') . '</a>'
        ];

        return array_merge($action_links, $links);
    }

    /**
     * Legacy AJAX test connection (maintained for backwards compatibility)
     */
    public function ajax_test_connection() {
        affcd_debug_log('Legacy AJAX test connection called', 'AJAX_TEST_CONNECTION');
        
        check_ajax_referer('affcd_admin_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Use health monitor to test connections if available
        if ($this->health_monitor) {
            $test_result = $this->health_monitor->trigger_manual_health_check();
            wp_send_json_success([
                'message' => __('Connection test completed', 'affiliatewp-cross-domain-plugin-suite'),
                'health_status' => $test_result
            ]);
        } else {
            wp_send_json_success(['message' => __('Basic connection test - legacy mode', 'affiliatewp-cross-domain-plugin-suite')]);
        }
    }

    /**
     * Legacy AJAX validate code (maintained for backwards compatibility)
     */
    public function ajax_validate_code() {
        affcd_debug_log('Legacy AJAX validate code called', 'AJAX_VALIDATE_CODE');
        
        // Use vanity code manager if available
        if ($this->vanity_code_manager && method_exists($this->vanity_code_manager, 'validate_code')) {
            $code = sanitize_text_field($_POST['code'] ?? '');
            $domain = sanitize_text_field($_POST['domain'] ?? '');
            
            $result = $this->vanity_code_manager->validate_code($code, $domain);
            wp_send_json_success($result);
        } else {
            wp_send_json_success(['message' => __('Validate code functionality - legacy mode', 'affiliatewp-cross-domain-plugin-suite')]);
        }
    }

    /**
     * Run health check cron job
     */
    public function run_health_check_cron() {
        if ($this->health_monitor) {
            $this->health_monitor->run_scheduled_health_check();
        }
    }
    
    /**
     * Run log cleanup cron job
     */
    public function run_log_cleanup_cron() {
        if ($this->health_monitor) {
            $this->health_monitor->cleanup_health_logs();
        }
        
        // Also run legacy cleanup
        $this->cleanup_expired_codes();
        $this->cleanup_analytics_data();
    }
    
    /**
     * Run materials generation cron job
     */
    public function run_materials_generation_cron() {
        if ($this->portal_enhancement) {
            // This would be called by individual affiliate schedules
            // Implementation handled by portal enhancement component
        }
    }

    /**
     * Cleanup expired codes (legacy)
     */
    public function cleanup_expired_codes() {
        affcd_debug_log('Cleanup expired codes called', 'CLEANUP_CODES');
        
        if ($this->vanity_code_manager && method_exists($this->vanity_code_manager, 'cleanup_expired_codes')) {
            $this->vanity_code_manager->cleanup_expired_codes();
        }
    }

    /**
     * Cleanup analytics data (legacy)
     */
    public function cleanup_analytics_data() {
        affcd_debug_log('Cleanup analytics data called', 'CLEANUP_ANALYTICS');
        
        global $wpdb;
        
        // Clean up old analytics data (older than 90 days)
        $tables_to_clean = [
            $wpdb->prefix . 'affiliate_health_log',
            $wpdb->prefix . 'affiliate_marketing_usage',
            $wpdb->prefix . 'affiliate_commission_calculations'
        ];
        
        foreach ($tables_to_clean as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
                ));
            }
        }
    }

    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        affcd_debug_log('Checking PHP version: ' . PHP_VERSION, 'REQUIREMENTS_PHP');
        affcd_debug_log('Checking WP version: ' . get_bloginfo('version'), 'REQUIREMENTS_WP');
        
        global $wp_version;
        return (
            version_compare(PHP_VERSION, '7.4', '>=') &&
            version_compare($wp_version, '5.0', '>=')
        );
    }

    /**
     * Check AffiliateWP dependency
     */
    private function check_affiliatewp_dependency() {
        if (!function_exists('affiliate_wp')) {
            if (is_admin()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p><strong>' . __('AffiliateWP Cross Domain Plugin Suite', 'affiliatewp-cross-domain-plugin-suite') . '</strong>: ';
                    echo __('AffiliateWP is recommended for full functionality.', 'affiliatewp-cross-domain-plugin-suite');
                    echo ' <a href="https://affiliatewp.com" target="_blank">' . __('Get AffiliateWP', 'affiliatewp-cross-domain-plugin-suite') . '</a></p>';
                    echo '</div>';
                });
            }
        }
        return true; // Allow plugin to run without AffiliateWP
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        affcd_debug_log('Setting default options', 'DEFAULT_OPTIONS_START');
        
        $default_options = [
            'affcd_version' => AFFCD_VERSION,
            'affcd_settings' => [],
            'affcd_api_settings' => [
                'enabled' => true,
                'rate_limit' => 1000,
                'cache_duration' => 900,
                'debug_mode' => false
            ],
            'affcd_security_settings' => [
                'jwt_secret' => wp_generate_password(64, true, true),
                'allowed_origins' => '',
                'require_https' => true,
                'security_level' => 'high'
            ],
            'affcd_webhook_settings' => [
                'enabled' => false,
                'url' => '',
                'secret' => wp_generate_password(32, true, true),
                'events' => []
            ],
            'affiliate_health_monitoring_enabled' => true,
            'affiliate_system_config' => [
                'portal_enhancement_enabled' => true,
                'commission_calculator_enabled' => true,
                'role_management_enabled' => true,
                'backflow_manager_enabled' => true
            ]
        ];
        
        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
                affcd_debug_log("Added option: {$option_name}", 'OPTION_ADDED');
            }
        }
        
        affcd_debug_log('Default options set', 'DEFAULT_OPTIONS_END');
    }

    /**
     * Register admin settings
     */
    private function register_admin_settings() {
        // API Settings
        register_setting('affcd_settings', 'affcd_api_settings', [
            'sanitize_callback' => [$this, 'sanitize_api_settings']
        ]);

        // Security Settings
        register_setting('affcd_settings', 'affcd_security_settings', [
            'sanitize_callback' => [$this, 'sanitize_security_settings']
        ]);

        // Webhook Settings
        register_setting('affcd_settings', 'affcd_webhook_settings', [
            'sanitize_callback' => [$this, 'sanitize_webhook_settings']
        ]);
        
        // System Configuration Settings
        register_setting('affcd_settings', 'affiliate_system_config', [
            'sanitize_callback' => [$this, 'sanitize_system_config']
        ]);
    }

    /**
     * Sanitize API settings
     */
    public function sanitize_api_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['rate_limit'] = absint($input['rate_limit'] ?? 1000);
        $sanitized['cache_duration'] = absint($input['cache_duration'] ?? 900);
        $sanitized['debug_mode'] = !empty($input['debug_mode']);

        return $sanitized;
    }

    /**
     * Sanitize security settings
     */
    public function sanitize_security_settings($input) {
        $sanitized = [];
        
        $sanitized['jwt_secret'] = sanitize_text_field($input['jwt_secret'] ?? '');
        $sanitized['allowed_origins'] = sanitize_textarea_field($input['allowed_origins'] ?? '');
        $sanitized['require_https'] = !empty($input['require_https']);
        $sanitized['security_level'] = in_array($input['security_level'], ['low', 'medium', 'high']) 
            ? $input['security_level'] 
            : 'high';

        return $sanitized;
    }

    /**
     * Sanitize webhook settings
     */
    public function sanitize_webhook_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['url'] = esc_url_raw($input['url'] ?? '');
        $sanitized['secret'] = sanitize_text_field($input['secret'] ?? '');
        $sanitized['events'] = is_array($input['events']) ? $input['events'] : [];

        return $sanitized;
    }
    
    /**
     * Sanitize system configuration
     */
    public function sanitize_system_config($input) {
        $sanitized = [];
        
        $sanitized['portal_enhancement_enabled'] = !empty($input['portal_enhancement_enabled']);
        $sanitized['commission_calculator_enabled'] = !empty($input['commission_calculator_enabled']);
        $sanitized['role_management_enabled'] = !empty($input['role_management_enabled']);
        $sanitized['backflow_manager_enabled'] = !empty($input['backflow_manager_enabled']);
        $sanitized['health_monitoring_enabled'] = !empty($input['health_monitoring_enabled']);
        
        return $sanitized;
    }

    /**
     * Maybe update database
     */
    private function maybe_update_database() {
        $current_version = get_option('affcd_version', '0.0.0');
        if (version_compare($current_version, AFFCD_VERSION, '<')) {
            // Run database updates
            $this->create_all_database_tables(); // This will handle table creation/updates
            
            // Update version
            update_option('affcd_version', AFFCD_VERSION);
            
            affcd_debug_log("Database updated from {$current_version} to " . AFFCD_VERSION, 'DATABASE_UPDATE');
        }
    }

    /**
     * Get comprehensive plugin status
     */
    public function get_plugin_status() {
        return [
            'activated' => $this->activated,
            'version' => AFFCD_VERSION,
            'components' => [
                'database_manager' => !empty($this->database_manager),
                'api_endpoints' => !empty($this->api_endpoints),
                'security_validator' => !empty($this->security_validator),
                'rate_limiter' => !empty($this->rate_limiter),
                'vanity_code_manager' => !empty($this->vanity_code_manager),
                'webhook_manager' => !empty($this->webhook_manager),
                'health_monitor' => !empty($this->health_monitor),
                'portal_enhancement' => !empty($this->portal_enhancement),
                'commission_calculator' => !empty($this->commission_calculator),
                'role_management' => !empty($this->role_management),
                'backflow_manager' => !empty($this->backflow_manager),
                'plugin_manager' => !empty($this->plugin_manager)
            ],
            'affiliatewp_active' => function_exists('affiliate_wp'),
            'database_version' => get_option('affcd_version', '0.0.0')
        ];
    }

    /**
     * Check if plugin is activated and ready
     */
    public function is_activated() {
        return $this->activated;
    }

    /**
     * Get plugin version
     */
    public function get_version() {
        return AFFCD_VERSION;
    }
    
    /**
     * Get component instance by name
     */
    public function get_component($component_name) {
        if (property_exists($this, $component_name)) {
            return $this->{$component_name};
        }
        return null;
    }
    
    /**
     * Check if component is active
     */
    public function is_component_active($component_name) {
        $component = $this->get_component($component_name);
        return !empty($component);
    }
}

// Initialize the enhanced role management system (moved after class)
if (!function_exists('affcd_init_enhanced_role_management')) {
    /**
     * Initialize Enhanced Role Management
     * 
     * @param string $plugin_file Main plugin file path
     */
    function affcd_init_enhanced_role_management($plugin_file = null) {
        return EnhancedRoleManagement::instance($plugin_file);
    }
}


/**
 * Initialize the plugin
 */
function affiliatewp_cross_domain_full() {
    affcd_debug_log('Plugin function called', 'PLUGIN_FUNCTION');
    return AffiliateWP_Cross_Domain_Full::instance();
}

/**
 * Get plugin instance (global access function)
 */
function affcd() {
    return affiliatewp_cross_domain_full();
}

// Initialize plugin with debug tracking
affcd_debug_log('About to initialize plugin', 'PLUGIN_INIT');
affcd_debug_log('Calling plugin initialization', 'FINAL_INIT');
$GLOBALS['affiliatewp_cross_domain_full'] = affiliatewp_cross_domain_full();
affcd_debug_log('Plugin initialization completed', 'FINAL_COMPLETE');

// Resolve per-site secrets for HMAC validation
add_filter('affcd_secret_for_site', function ($secret, $site_id) {
    // You could look this up from your Domain Manager table or WP options
    return get_option('affcd_secret_' . $site_id) ?: '';
}, 10, 2);