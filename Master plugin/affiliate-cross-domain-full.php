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
 * Main Plugin Class
 */
final class AffiliateWP_Cross_Domain_Plugin_Suite_Master {

    /** @var self */
    private static $instance = null;

    /** @var AFFCD_Database_Manager */
    public $database_manager;

    /** @var AFFCD_API_Endpoints */
    public $api_endpoints;

    /** @var AFFCD_Security_Validator */
    public $security_validator;

    /** @var AFFCD_Rate_Limiter */
    public $rate_limiter;

    /** @var AFFCD_Vanity_Code_Manager */
    public $vanity_code_manager;

    /** @var AFFCD_Webhook_Loader */
    public $webhook_loader;

    /** @var AFFCD_Webhook_Handler */
    public $webhook_handler;

    /** @var AFFCD_Admin_Menu */
    public $admin_menu;

    /** @var AFFCD_Domain_Manager */
    public $domain_manager;

    /** @var bool */
    private $activated = false;

    /**
     * Singleton
     */
    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * CTOR
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init_components();
    }

    /**
     * Hooks
     */
    private function init_hooks(): void {
        register_activation_hook(AFFCD_PLUGIN_FILE, [$this, 'activation']);
        register_deactivation_hook(AFFCD_PLUGIN_FILE, [$this, 'deactivation']);
        register_uninstall_hook(AFFCD_PLUGIN_FILE, ['AffiliateWP_Cross_Domain_Plugin_Suite_Master', 'uninstall']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init'], 0);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // AJAX
        add_action('wp_ajax_affcd_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_affcd_validate_code', [$this, 'ajax_validate_code']);
        add_action('wp_ajax_nopriv_affcd_validate_code', [$this, 'ajax_validate_code']);

        // Admin
        if (is_admin()) {
            add_action('admin_init', [$this, 'admin_init']);
            add_filter('plugin_action_links_' . AFFCD_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        }

        // Cron schedules
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Cleanup jobs
        add_action('affcd_cleanup_expired_codes', [$this, 'cleanup_expired_codes']);
        add_action('affcd_cleanup_analytics_data', [$this, 'cleanup_analytics_data']);
    }

    /**
     * Includes
     */
    private function includes(): void {
        // Core
        require_once AFFCD_PLUGIN_DIR . 'includes/functions.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-database-manager.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-security-validator.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-api-endpoints.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-vanity-code-manager.php';

        // Webhook pieces
        require_once AFFCD_PLUGIN_DIR . 'includes/class-webhook-manager.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        require_once AFFCD_PLUGIN_DIR . 'includes/class-webhook-loader.php';

        // Admin
        if (is_admin()) {
            require_once AFFCD_PLUGIN_DIR . 'includes/class-admin-menu.php';
            require_once AFFCD_PLUGIN_DIR . 'includes/class-domain-manager.php';
            require_once AFFCD_PLUGIN_DIR . 'admin/class-bulk-operations.php';
            require_once AFFCD_PLUGIN_DIR . 'admin/domain-management.php';
        }
    }

    /**
     * Instantiate components
     */
    private function init_components(): void {
        // Database first
        $this->database_manager  = new AFFCD_Database_Manager();

        // Security + rate limiting
        $this->security_validator = new AFFCD_Security_Validator();
        $this->rate_limiter       = new AFFCD_Rate_Limiter();

        // Business logic
        $this->vanity_code_manager = new AFFCD_Vanity_Code_Manager();

        // Webhooks: Loader wires Manager ↔ Handler
        $this->webhook_loader  = new AFFCD_Webhook_Loader();
        $this->webhook_handler = $this->webhook_loader->get_handler();

        // API (inject guards)
        $this->api_endpoints = new AFFCD_API_Endpoints(
            $this->security_validator,
            $this->rate_limiter,
            $this->vanity_code_manager
        );

        // Admin
        if (is_admin()) {
            $this->admin_menu     = new AFFCD_Admin_Menu();
            $this->domain_manager = new AFFCD_Domain_Manager();
        }
    }

    /**
     * Runtime init
     */
    public function init(): void {
        if (!$this->check_affiliatewp_dependency()) {
            return;
        }

        $this->activated = true;
        do_action('affcd_loaded', $this);

        $this->schedule_cleanup_events();
    }

    /**
     * Textdomain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'affiliatewp-cross-domain-plugin-suite',
            false,
            dirname(AFFCD_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * REST routes
     */
    public function register_rest_routes(): void {
        if ($this->api_endpoints) {
            $this->api_endpoints->register_routes();
        }
    }

    /**
     * Admin init: settings + DB updates + activation redirect
     */
    public function admin_init(): void {
        $this->register_admin_settings();
        $this->maybe_update_database();

        if (get_option('affcd_activation_redirect', false)) {
            delete_option('affcd_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=affcd-domain-management'));
                exit;
            }
        }
    }

    /**
     * Activation
     */
    public function activation(): void {
        if (!$this->check_requirements()) {
            deactivate_plugins(AFFCD_PLUGIN_BASENAME);
            wp_die(__('AffiliateWP Cross Domain Plugin Suite requires WordPress 5.0+ and PHP 7.4+', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Create/upgrade tables
        $this->database_manager = new AFFCD_Database_Manager();
        $this->database_manager->create_tables();

        // Defaults
        $this->set_default_options();

        // Cron
        $this->schedule_cleanup_events();

        // Activation redirect flag
        add_option('affcd_activation_redirect', true);

        if (function_exists('affcd_log_activity')) {
            affcd_log_activity('plugin_activated', [
                'version'   => AFFCD_VERSION,
                'timestamp' => current_time('mysql')
            ]);
        }
    }

    /**
     * Deactivation
     */
    public function deactivation(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook('affcd_cleanup_expired_codes');
        wp_clear_scheduled_hook('affcd_cleanup_analytics_data');

        if (function_exists('affcd_log_activity')) {
            affcd_log_activity('plugin_deactivated', [
                'version'   => AFFCD_VERSION,
                'timestamp' => current_time('mysql')
            ]);
        }
    }

    /**
     * Uninstall (static)
     */
    public static function uninstall(): void {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        require_once AFFCD_PLUGIN_DIR . 'includes/class-database-manager.php';

        // Drop tables
        $database_manager = new AFFCD_Database_Manager();
        $database_manager->drop_tables();

        // Options cleanup
        delete_option('affcd_version');
        delete_option('affcd_settings');
        delete_option('affcd_api_settings');
        delete_option('affcd_security_settings');
        delete_option('affcd_webhook_settings');
        delete_option('affcd_activation_redirect');

        // Transients cleanup
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_affcd_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_affcd_%'");
    }

    /**
     * Requirements
     */
    private function check_requirements(): bool {
        global $wp_version;
        return version_compare(PHP_VERSION, '7.4', '>=') && version_compare($wp_version, '5.0', '>=');
    }

    /**
     * AffiliateWP dependency
     */
    private function check_affiliatewp_dependency(): bool {
        // Soft dependency: allow plugin to run but surface admin notice if missing.
        if (!function_exists('affiliate_wp') && !function_exists('affwp_get_affiliate')) {
            if (is_admin()) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-warning is-dismissible"><p><strong>'
                        . esc_html__('AffiliateWP Cross Domain Plugin Suite', 'affiliatewp-cross-domain-plugin-suite')
                        . '</strong>: '
                        . esc_html__('AffiliateWP is recommended for full functionality.', 'affiliatewp-cross-domain-plugin-suite')
                        . ' <a href="https://affiliatewp.com" target="_blank" rel="noopener noreferrer">'
                        . esc_html__('Get AffiliateWP', 'affiliatewp-cross-domain-plugin-suite')
                        . '</a></p></div>';
                });
            }
        }
        return true;
    }

    /**
     * Default options
     */
    private function set_default_options(): void {
        if (!get_option('affcd_version')) {
            add_option('affcd_version', AFFCD_VERSION);
        }
        if (!get_option('affcd_settings')) {
            add_option('affcd_settings', [
                'api_enabled'                => true,
                'rate_limit_enabled'         => true,
                'rate_limit_requests_hour'   => 1000,
                'cache_enabled'              => true,
                'cache_duration'             => 900,
                'cleanup_enabled'            => true,
                'cleanup_expired_codes_days' => 30,
                'cleanup_analytics_days'     => 90,
                'webhook_enabled'            => true,
                'security_level'             => 'high'
            ]);
        }
    }

    /**
     * DB migration
     */
    private function maybe_update_database(): void {
        $current_version = get_option('affcd_version', '0.0.0');
        if (version_compare($current_version, AFFCD_VERSION, '<')) {
            if ($this->database_manager instanceof AFFCD_Database_Manager) {
                $this->database_manager->update_tables($current_version, AFFCD_VERSION);
            }
            update_option('affcd_version', AFFCD_VERSION);
        }
    }

    /**
     * Schedules
     */
    public function add_cron_schedules(array $schedules): array {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Once Weekly', 'affiliatewp-cross-domain-plugin-suite'),
            ];
        }
        return $schedules;
    }

    private function schedule_cleanup_events(): void {
        if (!wp_next_scheduled('affcd_cleanup_expired_codes')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'affcd_cleanup_expired_codes');
        }
        if (!wp_next_scheduled('affcd_cleanup_analytics_data')) {
            wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'weekly', 'affcd_cleanup_analytics_data');
        }
    }

    /**
     * Cleanup jobs
     */
    public function cleanup_expired_codes(): void {
        global $wpdb;

        // Prefer manager routine if available
        if ($this->vanity_code_manager && method_exists($this->vanity_code_manager, 'cleanup_expired_codes')) {
            $this->vanity_code_manager->cleanup_expired_codes();
            return;
        }

        // Fallback: delete expired codes from typical table if it exists
        $table = $wpdb->prefix . 'affcd_vanity_codes';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists === $table) {
            $wpdb->query("DELETE FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        }
    }

    public function cleanup_analytics_data(): void {
        global $wpdb;

        $settings     = get_option('affcd_settings', []);
        $cleanup_days = isset($settings['cleanup_analytics_days']) ? (int)$settings['cleanup_analytics_days'] : 90;

        $analytics_table = $wpdb->prefix . 'affcd_analytics';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $analytics_table));
        if ($exists === $analytics_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$analytics_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $cleanup_days
            ));
        }
    }

    /**
     * Settings registration + sanitizers
     */
    private function register_admin_settings(): void {
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

    public function sanitize_api_settings($input): array {
        return [
            'enabled'        => !empty($input['enabled']),
            'rate_limit'     => absint($input['rate_limit'] ?? 1000),
            'cache_duration' => absint($input['cache_duration'] ?? 900),
            'debug_mode'     => !empty($input['debug_mode']),
        ];
    }

    public function sanitize_security_settings($input): array {
        $level = $input['security_level'] ?? 'high';
        if (!in_array($level, ['low','medium','high'], true)) {
            $level = 'high';
        }
        return [
            'jwt_secret'      => sanitize_text_field($input['jwt_secret'] ?? ''),
            'allowed_origins' => sanitize_textarea_field($input['allowed_origins'] ?? ''),
            'require_https'   => !empty($input['require_https']),
            'security_level'  => $level,
        ];
    }

    public function sanitize_webhook_settings($input): array {
        $events = is_array($input['events'] ?? null) ? array_values($input['events']) : [];
        return [
            'enabled' => !empty($input['enabled']),
            'url'     => esc_url_raw($input['url'] ?? ''),
            'secret'  => sanitize_text_field($input['secret'] ?? ''),
            'events'  => $events,
        ];
    }

    /**
     * AJAX: Test connection to a client domain’s health endpoint
     */
    public function ajax_test_connection(): void {
        check_ajax_referer('affcd_admin_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_url = esc_url_raw($_POST['domain'] ?? '');
        if (empty($domain_url)) {
            wp_send_json_error(__('Domain is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $health_url = trailingslashit($domain_url) . 'wp-json/affiliate-client/v1/health';
        $response = wp_remote_get($health_url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'AffiliateWP-CrossDomain/' . AFFCD_VERSION,
            ],
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => __('Connection failed', 'affiliatewp-cross-domain-plugin-suite'),
                'error'   => $response->get_error_message(),
            ]);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code === 200 && isset($data['status']) && $data['status'] === 'ok') {
            wp_send_json_success([
                'message'        => __('Connection successful', 'affiliatewp-cross-domain-plugin-suite'),
                'response_code'  => $code,
                'response_time'  => $data['response_time'] ?? null,
                'plugin_version' => $data['plugin_version'] ?? null,
            ]);
        }

        wp_send_json_error([
            'message'       => __('Health check failed', 'affiliatewp-cross-domain-plugin-suite'),
            'response_code' => $code,
            'body'          => $body,
        ]);
    }

    /**
     * AJAX: Validate code (public + logged-in)
     */
    public function ajax_validate_code(): void {
        // Basic rate limit by IP/requester
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($this->rate_limiter && !$this->rate_limiter->allow('validate_code', $ip)) {
            wp_send_json_error(__('Rate limit exceeded. Try again later.', 'affiliatewp-cross-domain-plugin-suite'), 429);
        }

        $code   = sanitize_text_field($_POST['code'] ?? '');
        $domain = sanitize_text_field($_POST['domain'] ?? '');

        if ($code === '') {
            wp_send_json_error(__('Code is required', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $this->vanity_code_manager->validate_code($code, $domain);
        // $result should already be an array with success/error; just pass through
        wp_send_json($result);
    }

    /**
     * Plugin action links
     */
    public function plugin_action_links(array $links): array {
        $action_links = [
            '<a href="' . esc_url(admin_url('admin.php?page=affcd-domain-management')) . '">' . esc_html__('Settings', 'affiliatewp-cross-domain-plugin-suite') . '</a>',
            '<a href="https://starneconsulting.com/docs/affiliatewp-cross-domain-plugin-suite" target="_blank" rel="noopener noreferrer">' . esc_html__('Documentation', 'affiliatewp-cross-domain-plugin-suite') . '</a>',
        ];
        return array_merge($action_links, $links);
    }

    /**
     * Helper
     */
    public function is_activated(): bool {
        return $this->activated;
    }

    public function get_version(): string {
        return AFFCD_VERSION;
    }
}

/**
 * Bootstrap
 */
function affcd_master(): AffiliateWP_Cross_Domain_Plugin_Suite_Master {
    return AffiliateWP_Cross_Domain_Plugin_Suite_Master::instance();
}
affcd_master();
