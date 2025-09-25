<?php
/**
 * Plugin Name: AffiliateWP Cross Domain Affiliate Tracking Master Plugin
 * Plugin URI: https://starneconsulting.com/affiliatewp-cross-domain-full
 * Description: Central hub for cross-domain affiliate discount code validation and management. Extends AffiliateWP with vanity codes, multi-site API endpoints, and comprehensive analytics across client installations. Requires partner satellite plugin on the Client domain.
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
 * Main Plugin Class with Debug Tracking
 */
class AffiliateWP_Cross_Domain_Full {
    
    private static $instance = null;
    private static $debug_step = 0;
    
    /**
     * Database manager instance
     *
     * @var AFFCD_Database_Manager
     */
    public $database_manager;

    /**
     * API endpoints instance
     *
     * @var AFFCD_API_Endpoints
     */
    public $api_endpoints;

    /**
     * Security validator instance
     *
     * @var AFFCD_Security_Validator
     */
    public $security_validator;

    /**
     * Rate limiter instance
     *
     * @var AFFCD_Rate_Limiter
     */
    public $rate_limiter;

    /**
     * Vanity code manager instance
     *
     * @var AFFCD_Vanity_Code_Manager
     */
    public $vanity_code_manager;

    /**
     * Webhook manager instance
     *
     * @var AFFCD_Webhook_Manager
     */
    public $webhook_manager;

    /**
     * Webhook handler instance
     *
     * @var AFFCD_Webhook_Handler
     */
    public $webhook_handler;

    /**
     * Admin menu instance
     *
     * @var AFFCD_Admin_Menu
     */
    public $admin_menu;

    /**
     * Domain manager instance
     *
     * @var AFFCD_Domain_Manager
     */
    public $domain_manager;

    /**
     * Plugin activated flag
     *
     * @var boolean
     */
    private $activated = false;
    
    // Add debug counter
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
        
        // AJAX hooks
        add_action('wp_ajax_affcd_test_connection', [$this, 'ajax_test_connection']);
        self::next_debug_step('AJAX test connection hook registered');
        
        add_action('wp_ajax_nopriv_affcd_validate_code', [$this, 'ajax_validate_code']);
        self::next_debug_step('AJAX validate code hook registered');
        
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
        
        self::next_debug_step('All hooks registered successfully');
    }

    /**
     * Include required files
     */
    private function includes() {
        self::next_debug_step('Starting file includes');
        
        // Core files to include
        $files_to_include = [
            'includes/functions.php',
            'includes/class-database-manager.php',
            'includes/class-security-validator.php',
            'includes/class-rate-limiter.php',
            'includes/class-api-endpoints.php',
            'includes/class-vanity-code-manager.php',
            'includes/class-webhook-manager.php',
            'includes/class-webhook-handler.php'
        ];
        
        foreach ($files_to_include as $file) {
            $file_path = AFFCD_PLUGIN_DIR . $file;
            
            if (file_exists($file_path)) {
                self::next_debug_step("Including: {$file}");
                require_once $file_path;
                self::next_debug_step("Successfully included: {$file}");
            } else {
                affcd_debug_log("File not found: {$file}", 'MISSING_FILE');
            }
        }
        
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
            // Use the singleton pattern for database manager to prevent multiple instantiation
            self::next_debug_step('Getting database manager instance');
            $this->database_manager = $this->get_database_manager();
            self::next_debug_step('Database manager ready');
            
            // Initialize security and rate limiting
            self::next_debug_step('Creating security validator');
            $this->security_validator = new AFFCD_Security_Validator();
            self::next_debug_step('Security validator created');
            
            self::next_debug_step('Creating rate limiter');
            $this->rate_limiter = new AFFCD_Rate_Limiter();
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
         // Initialize admin components
if ( is_admin() ) {
    self::next_debug_step('Creating admin components');
    $this->admin_menu     = new AFFCD_Admin_Menu();               // register menus only
    $this->domain_manager = class_exists('AFFCD_Domain_Manager')  // optional if you need it
        ? new AFFCD_Domain_Manager()
        : null;
    self::next_debug_step('Admin components created');
}
            
        } catch (Exception $e) {
            affcd_debug_log('Component initialization error: ' . $e->getMessage(), 'COMPONENT_ERROR');
            throw $e;
        }
        
        self::next_debug_step('Component initialization completed');
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
            // Check WordPress and PHP versions
            affcd_debug_log('Checking requirements', 'ACTIVATION_REQUIREMENTS');
            if (!$this->check_requirements()) {
                affcd_debug_log('Requirements check failed', 'ACTIVATION_ERROR');
                deactivate_plugins(AFFCD_PLUGIN_BASENAME);
                wp_die(__('AffiliateWP Cross Domain Full requires WordPress 5.0+ and PHP 7.4+', 'affiliatewp-cross-domain-plugin-suite'));
            }
            affcd_debug_log('Requirements check passed', 'ACTIVATION_REQUIREMENTS_OK');

            // Create database tables
            affcd_debug_log('About to create database tables', 'ACTIVATION_DB_START');
            $db_manager = $this->get_database_manager();
            
            affcd_debug_log('Calling create_tables()', 'ACTIVATION_DB_CREATE');
            $db_manager->create_tables();
            affcd_debug_log('Database tables created successfully', 'ACTIVATION_DB_OK');

            // Set default options
            affcd_debug_log('Setting default options', 'ACTIVATION_OPTIONS');
            $this->set_default_options();
            affcd_debug_log('Default options set', 'ACTIVATION_OPTIONS_OK');

            // Schedule cleanup events
            affcd_debug_log('Scheduling cleanup events', 'ACTIVATION_SCHEDULE');
            $this->schedule_cleanup_events();
            affcd_debug_log('Cleanup events scheduled', 'ACTIVATION_SCHEDULE_OK');

            affcd_debug_log('=== ACTIVATION COMPLETED SUCCESSFULLY ===', 'ACTIVATION_SUCCESS');
            
        } catch (Exception $e) {
            affcd_debug_log('Activation error: ' . $e->getMessage(), 'ACTIVATION_ERROR');
            affcd_debug_log('Error trace: ' . $e->getTraceAsString(), 'ACTIVATION_TRACE');
            throw $e;
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivation() {
        affcd_debug_log('Deactivation started', 'DEACTIVATION_START');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('affcd_cleanup_expired_codes');
        wp_clear_scheduled_hook('affcd_cleanup_analytics_data');
        
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

        // Drop database tables
        require_once AFFCD_PLUGIN_DIR . 'includes/class-database-manager.php';
        $database_manager = new AFFCD_Database_Manager();
        $database_manager->drop_tables();

        // Remove options
        delete_option('affcd_version');
        delete_option('affcd_settings');
        delete_option('affcd_api_settings');
        delete_option('affcd_security_settings');
        delete_option('affcd_webhook_settings');
        delete_option('affcd_activation_redirect');

        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_affcd_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_affcd_%'");
        
        affcd_debug_log('Uninstall completed', 'UNINSTALL_END');
    }

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

        // Schedule cleanup events
        $this->schedule_cleanup_events();
        
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
    }

    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        affcd_debug_log('Adding plugin action links', 'ACTION_LINKS');
        
        $action_links = [
            '<a href="' . esc_url(admin_url('admin.php?page=affcd-domain-management')) . '">' . __('Settings', 'affiliatewp-cross-domain-plugin-suite') . '</a>',
            '<a href="https://starneconsulting.com/docs/affiliatewp-cross-domain" target="_blank">' . __('Documentation', 'affiliatewp-cross-domain-plugin-suite') . '</a>'
        ];

        return array_merge($action_links, $links);
    }

    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        affcd_debug_log('AJAX test connection called', 'AJAX_TEST_CONNECTION');
        
        check_ajax_referer('affcd_admin_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Implementation would go here
        wp_send_json_success(['message' => 'Test connection functionality']);
    }

    /**
     * AJAX validate code
     */
    public function ajax_validate_code() {
        affcd_debug_log('AJAX validate code called', 'AJAX_VALIDATE_CODE');
        
        // Implementation would go here
        wp_send_json_success(['message' => 'Validate code functionality']);
    }

    /**
     * Cleanup expired codes
     */
    public function cleanup_expired_codes() {
        affcd_debug_log('Cleanup expired codes called', 'CLEANUP_CODES');
        
        // Implementation would go here
    }

    /**
     * Cleanup analytics data
     */
    public function cleanup_analytics_data() {
        affcd_debug_log('Cleanup analytics data called', 'CLEANUP_ANALYTICS');
        
        // Implementation would go here
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
            'affcd_api_settings' => [],
            'affcd_security_settings' => [],
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
     * Schedule cleanup events
     */
    private function schedule_cleanup_events() {
        affcd_debug_log('Scheduling cleanup events', 'CLEANUP_SCHEDULE_START');
        
        if (!wp_next_scheduled('affcd_cleanup_expired_codes')) {
            wp_schedule_event(time(), 'hourly', 'affcd_cleanup_expired_codes');
            affcd_debug_log('Scheduled expired codes cleanup', 'CLEANUP_CODES_SCHEDULED');
        }
        
        if (!wp_next_scheduled('affcd_cleanup_analytics_data')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_analytics_data');
            affcd_debug_log('Scheduled analytics cleanup', 'CLEANUP_ANALYTICS_SCHEDULED');
        }
        
        affcd_debug_log('Cleanup events scheduled', 'CLEANUP_SCHEDULE_END');
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
     * Maybe update database
     */
    private function maybe_update_database() {
        $current_version = get_option('affcd_version', '0.0.0');
        if (version_compare($current_version, AFFCD_VERSION, '<')) {
            $db_manager = $this->get_database_manager();
            if (method_exists($db_manager, 'update_tables')) {
                $db_manager->update_tables($current_version, AFFCD_VERSION);
            }
            update_option('affcd_version', AFFCD_VERSION);
        }
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
}

/**
 * Initialize the plugin
 */
function affiliatewp_cross_domain_full() {
    affcd_debug_log('Plugin function called', 'PLUGIN_FUNCTION');
    return AffiliateWP_Cross_Domain_Full::instance();
}

// Initialize plugin with debug tracking
affcd_debug_log('About to initialize plugin', 'PLUGIN_INIT');
affcd_debug_log('Calling plugin initialization', 'FINAL_INIT');
affiliatewp_cross_domain_full();
affcd_debug_log('Plugin initialization completed', 'FINAL_COMPLETE');