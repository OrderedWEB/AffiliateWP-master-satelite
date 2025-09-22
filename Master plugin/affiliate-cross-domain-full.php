<?php
/**
 * Plugin Name: AffiliateWP Cross Domain Affiliate Tracking Master Plugin
 * Plugin URI: https://starneconsulting.com/affiliatewp-cross-domain-full
 * Description: Central hub for cross-domain affiliate discount code validation and management. Extends AffiliateWP with vanity codes, multi-site API endpoints, and comprehensive analytics across client installations.  Requires partner satelite plugin on the Clinet domain.
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

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AFFCD_VERSION', '1.0.0');
define('AFFCD_PLUGIN_FILE', __FILE__);
define('AFFCD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AFFCD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AFFCD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main AffiliateWP Cross Domain Plugin Suite Plugin Class
 */
class AffiliateWP_Cross_Domain_Plugin_Suite_Master {

    /**
     * Plugin instance
     *
     * @var AffiliateWP_Cross_Domain_Plugin_Suite_Master
     */
    private static $instance = null;

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

    /**
     * Get plugin instance
     *
     * @return AffiliateWP_Cross_Domain_Plugin_Suite_Master
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init_components();
    }

    /**
     * Initialse hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activation']);
        register_deactivation_hook(__FILE__, [$this, 'deactivation']);
        register_uninstall_hook(__FILE__, ['AffiliateWP_Cross_Domain_Plugin_Suite_Master', 'uninstall']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init'], 0);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_ajax_affcd_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_nopriv_affcd_validate_code', [$this, 'ajax_validate_code']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_init', [$this, 'admin_init']);
            add_action('admin_notices', [$this, 'admin_notices']);
            add_filter('plugin_action_links_' . AFFCD_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        }

        // Cleanup hooks
        add_action('affcd_cleanup_expired_codes', [$this, 'cleanup_expired_codes']);
        add_action('affcd_cleanup_analytics_data', [$this, 'cleanup_analytics_data']);
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once AFFCD_PLUGIN_DIR . 'includes/functions.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-database-manager.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-security-validator.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-api-endpoints.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-vanity-code-manager.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-webhook-handler.php';

        // Admin classes
        if (is_admin()) {
            require_once AFFCD_PLUGIN_DIR . 'includes/class-admin-menu.php';
            require_once AFFCD_PLUGIN_DIR . 'includes/class-domain-manager.php';
            require_once AFFCD_PLUGIN_DIR . 'admin/class-bulk-operations.php';
            require_once AFFCD_PLUGIN_DIR . 'admin/domain-management.php';
        }
    }

    /**
     * Initialse plugin components
     */
    private function init_components() {
        // Initialise database manager first
        $this->database_manager = new AFFCD_Database_Manager();
        
        // Initialise security and rate limiting
        $this->security_validator = new AFFCD_Security_Validator();
        $this->rate_limiter = new AFFCD_Rate_Limiter();
        
        // Initialise business logic components
        $this->vanity_code_manager = new AFFCD_Vanity_Code_Manager();
        $this->webhook_handler = new AFFCD_Webhook_Handler();
        $this->api_endpoints = new AFFCD_API_Endpoints(
            $this->security_validator,
            $this->rate_limiter,
            $this->vanity_code_manager
        );

        // Initialise admin components
        if (is_admin()) {
            $this->admin_menu = new AFFCD_Admin_Menu();
            $this->domain_manager = new AFFCD_Domain_Manager();
        }
    }

    /**
     * Plugin initialisation
     */
    public function init() {
        // Check AffiliateWP dependency
        if (!$this->check_affiliatewp_dependency()) {
            return;
        }

        // Set activated flag
        $this->activated = true;

        // Initialise components that require WordPress to be loaded
        do_action('affcd_loaded', $this);

        // Schedule cleanup events
        $this->schedule_cleanup_events();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'affiliatewp-cross-domain-plugin-suite',
            false,
            dirname(AFFCD_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        if ($this->api_endpoints) {
            $this->api_endpoints->register_routes();
        }
    }

    /**
     * Admin initialisation
     */
    public function admin_init() {
        // Register admin settings
        $this->register_admin_settings();
        
        // Check for database updates
        $this->maybe_update_database();
    }

    /**
     * Plugin activation
     */
    public function activation() {
        // Check WordPress and PHP versions
        if (!$this->check_requirements()) {
            deactivate_plugins(AFFCD_PLUGIN_BASENAME);
            wp_die(__('AffiliateWP Cross Domain Plugin Suite requires WordPress 5.0+ and PHP 7.4+', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Create database tables
        $this->database_manager = new AFFCD_Database_Manager();
        $this->database_manager->create_tables();

        // Set default options
        $this->set_default_options();

        // Schedule cleanup events
        $this->schedule_cleanup_events();

        // Set activation flag
        add_option('affcd_activation_redirect', true);

        // Log activation
        affcd_log_activity('plugin_activated', [
            'version' => AFFCD_VERSION,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Plugin deactivation
     */
    public function deactivation() {
        // Clear scheduled events
        wp_clear_scheduled_hook('affcd_cleanup_expired_codes');
        wp_clear_scheduled_hook('affcd_cleanup_analytics_data');

        // Log deactivation
        affcd_log_activity('plugin_deactivated', [
            'version' => AFFCD_VERSION,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove database tables
        $database_manager = new AFFCD_Database_Manager();
        $database_manager->drop_tables();

        // Remove options
        delete_option('affcd_version');
        delete_option('affcd_settings');
        delete_option('affcd_api_settings');
        delete_option('affcd_security_settings');
        delete_option('affcd_webhook_settings');
        delete_option('affcd_cache_settings');

        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_affcd_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_affcd_%'");
    }

    /**
     * Check plugin requirements
     */
    private function check_requirements() {
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
            add_action('admin_notices', [$this, 'affiliatewp_missing_notice']);
            return false;
        }
        return true;
    }

    /**
     * AffiliateWP missing notice
     */
    public function affiliatewp_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('AffiliateWP Cross Domain Plugin Suite', 'affiliatewp-cross-domain-plugin-suite'); ?></strong>: 
                <?php _e('This plugin requires AffiliateWP to be installed and activated.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                <a href="https://affiliatewp.com" target="_blank"><?php _e('Get AffiliateWP', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        add_option('affcd_version', AFFCD_VERSION);
        
        $default_settings = [
            'api_enabled' => true,
            'rate_limit_enabled' => true,
            'rate_limit_requests_per_hour' => 1000,
            'cache_enabled' => true,
            'cache_duration' => 900, // 15 minutes
            'cleanup_enabled' => true,
            'cleanup_expired_codes_days' => 30,
            'cleanup_analytics_days' => 90,
            'webhook_enabled' => false,
            'security_level' => 'high'
        ];

        add_option('affcd_settings', $default_settings);
    }

    /**
     * Register admin settings
     */
    private function register_admin_settings() {
        register_setting('affcd_settings', 'affcd_api_settings', [
            'sanitize_callback' => [$this, 'sanitize_api_settings']
        ]);

        register_setting('affcd_settings', 'affcd_security_settings', [
            'sanitize_callback' => [$this, 'sanitize_security_settings']
        ]);

        register_setting('affcd_settings', 'affcd_webhook_settings', [
            'sanitize_callback' => [$this, 'sanitize_webhook_settings']
        ]);
    }

    /**
     * Schedule cleanup events
     */
    private function schedule_cleanup_events() {
        if (!wp_next_scheduled('affcd_cleanup_expired_codes')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_expired_codes');
        }

        if (!wp_next_scheduled('affcd_cleanup_analytics_data')) {
            wp_schedule_event(time(), 'weekly', 'affcd_cleanup_analytics_data');
        }
    }

    /**
     * Maybe update database
     */
    private function maybe_update_database() {
        $current_version = get_option('affcd_version', '0.0.0');
        
        if (version_compare($current_version, AFFCD_VERSION, '<')) {
            $this->database_manager->update_tables($current_version, AFFCD_VERSION);
            update_option('affcd_version', AFFCD_VERSION);
        }
    }

    /**
     * Cleanup expired codes
     */
    public function cleanup_expired_codes() {
        if ($this->vanity_code_manager) {
            $this->vanity_code_manager->cleanup_expired_codes();
        }
    }

    /**
     * Cleanup analytics data
     */
    public function cleanup_analytics_data() {
        global $wpdb;
        
        $settings = get_option('affcd_settings', []);
        $cleanup_days = $settings['cleanup_analytics_days'] ?? 90;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}affcd_analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $cleanup_days
        ));
    }

    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('affcd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if (empty($domain)) {
            wp_send_json_error(__('Domain is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $this->domain_manager->test_domain_connection($domain);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX validate code (for testing)
     */
    public function ajax_validate_code() {
        $code = sanitize_text_field($_POST['code'] ?? '');
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if (empty($code)) {
            wp_send_json_error(__('Code is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $this->vanity_code_manager->validate_code($code, $domain);
        wp_send_json($result);
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show activation redirect notice
        if (get_option('affcd_activation_redirect', false)) {
            delete_option('affcd_activation_redirect');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php _e('AffiliateWP Cross Domain Plugin Suite activated successfully!', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    <a href="<?php echo admin_url('admin.php?page=affcd-settings'); ?>"><?php _e('Configure Settings', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        $action_links = [
            'settings' => '<a href="' . admin_url('admin.php?page=affcd-settings') . '">' . __('Settings', 'affiliatewp-cross-domain-plugin-suite') . '</a>',
            'docs' => '<a href="https://starneconsulting.com/docs/affiliatewp-cross-domain-plugin-suite" target="_blank">' . __('Documentation', 'affiliatewp-cross-domain-plugin-suite') . '</a>'
        ];

        return array_merge($action_links, $links);
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
 * Initialse the plugin
 */
function AffiliateWP_Cross_Domain_Plugin_Suite_Master() {
    return AffiliateWP_Cross_Domain_Plugin_Suite_Master::instance();
}

// Initialise plugin
AffiliateWP_Cross_Domain_Plugin_Suite_Master();