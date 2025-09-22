<?php
/**
 * Plugin Name: Affiliate Client Full
 * Plugin URI: https://your-domain.com/affiliate-client-full
 * Description: Client-side affiliate tracking plugin that communicates with AffiliateWP Cross Domain Full. Handles tracking, conversions, and addon integrations for remote affiliate management.
 * Version: 1.0.0
 * Author: Your Company
 * Author URI: https://your-domain.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: affiliate-client-full
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AFFILIATE_CLIENT_FULL_VERSION', '1.0.0');
define('AFFILIATE_CLIENT_FULL_PLUGIN_FILE', __FILE__);
define('AFFILIATE_CLIENT_FULL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AFFILIATE_CLIENT_FULL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AFFILIATE_CLIENT_FULL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Affiliate Client Full Plugin Class
 */
class AffiliateClientFull {

    /**
     * Plugin instance
     *
     * @var AffiliateClientFull
     */
    private static $instance = null;

    /**
     * Tracking handler instance
     *
     * @var AFFILIATE_CLIENT_Tracking_Handler
     */
    public $tracking_handler;

    /**
     * API client instance
     *
     * @var AFFILIATE_CLIENT_API_Client
     */
    public $api_client;

    /**
     * Addon client instance
     *
     * @var AFFILIATE_CLIENT_Addon_Client
     */
    public $addon_client;

    /**
     * Conversion tracker instance
     *
     * @var AFFILIATE_CLIENT_Conversion_Tracker
     */
    public $conversion_tracker;

    /**
     * Discount shortcode instance
     *
     * @var AFFILIATE_CLIENT_Discount_Shortcode
     */
    public $discount_shortcode;

    /**
     * Discount form instance
     *
     * @var AFFILIATE_CLIENT_Discount_Form
     */
    public $discount_form;

    /**
     * Pricing integration instance
     *
     * @var AFFILIATE_CLIENT_Discount_Pricing_Integration
     */
    public $pricing_integration;

    /**
     * Plugin configuration
     *
     * @var array
     */
    public $config;

    /**
     * Get plugin instance
     *
     * @return AffiliateClientFull
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
        $this->load_config();
        $this->includes();
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Load plugin configuration
     */
    private function load_config() {
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'config.php';
        $this->config = affiliate_client_full_get_config();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-tracking-handler.php';
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-api-client.php';
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-addon-client.php';
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-conversion-tracker.php';
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-discount-shortcode.php';
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-discount-form.php';
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-discount-pricing-integration.php';
        require_once AFFILIATE_CLIENT_FULL_PLUGIN_DIR . 'includes/class-zoho-form-integration.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_footer', [$this, 'output_tracking_script']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('admin_post_affiliate_client_save_settings', [$this, 'save_settings']);
        
        // AJAX hooks
        add_action('wp_ajax_affiliate_client_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_affiliate_client_sync_data', [$this, 'ajax_sync_data']);
        
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Scheduled events
        add_action('affiliate_client_hourly_sync', [$this, 'hourly_sync']);
        add_action('affiliate_client_daily_cleanup', [$this, 'daily_cleanup']);
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->api_client = new AFFILIATE_CLIENT_API_Client($this->config);
        $this->tracking_handler = new AFFILIATE_CLIENT_Tracking_Handler($this->config, $this->api_client);
        $this->addon_client = new AFFILIATE_CLIENT_Addon_Client($this->config, $this->api_client);
        $this->conversion_tracker = new AFFILIATE_CLIENT_Conversion_Tracker($this->config, $this->api_client);
        $this->discount_shortcode = new AFFILIATE_CLIENT_Discount_Shortcode($this->config);
        $this->discount_form = new AFFILIATE_CLIENT_Discount_Form($this->config);
        $this->pricing_integration = new AFFILIATE_CLIENT_Discount_Pricing_Integration($this->config, $this->api_client);
        $this->zoho_integration = new AFFILIATE_CLIENT_Zoho_Form_Integration($this->config, $this->api_client);
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('affiliate-client-full', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        if ($this->tracking_handler) {
            $this->tracking_handler->init();
        }
        
        if ($this->addon_client) {
            $this->addon_client->init();
        }
        
        if ($this->conversion_tracker) {
            $this->conversion_tracker->init();
        }
        
        if ($this->discount_shortcode) {
            $this->discount_shortcode->init();
        }
        
        if ($this->discount_form) {
            $this->discount_form->init();
        }
        
        if ($this->pricing_integration) {
            $this->pricing_integration->init();
        }
        
        if ($this->zoho_integration) {
            $this->zoho_integration->init();
        }
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        wp_enqueue_script(
            'affiliate-client-tracking',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/affiliate-tracking.js',
            ['jquery'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_enqueue_script(
            'affiliate-client-addons',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/addon-handlers.js',
            ['jquery', 'affiliate-client-tracking'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_enqueue_style(
            'affiliate-client-styles',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/affiliate-styles.css',
            [],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_enqueue_style(
            'affiliate-client-addon-styles',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/addon-styles.css',
            ['affiliate-client-styles'],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_enqueue_style(
            'affiliate-client-discount-styles',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/css/discount-styles.css',
            ['affiliate-client-styles'],
            AFFILIATE_CLIENT_FULL_VERSION
        );

        wp_enqueue_script(
            'affiliate-client-discount',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/discount-functionality.js',
            ['jquery', 'affiliate-client-tracking'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        // Localize script with configuration
        wp_localize_script('affiliate-client-tracking', 'affiliateClientConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('affiliate-client/v1/'),
            'nonce' => wp_create_nonce('affiliate_client_nonce'),
            'trackingEnabled' => $this->is_tracking_enabled(),
            'remoteUrl' => $this->config['remote_url'],
            'debug' => $this->config['debug_mode'],
            'cookieDomain' => $this->get_cookie_domain(),
            'cookieExpiry' => $this->config['cookie_expiry'],
            'trackingEvents' => $this->get_tracking_events(),
        ]);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_affiliate-client-full' !== $hook) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'affiliate-client-admin',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_localize_script('affiliate-client-admin', 'affiliateClientAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affiliate_client_admin_nonce'),
            'strings' => [
                'testing' => __('Testing connection...', 'affiliate-client-full'),
                'success' => __('Connection successful!', 'affiliate-client-full'),
                'error' => __('Connection failed', 'affiliate-client-full'),
                'syncing' => __('Syncing data...', 'affiliate-client-full'),
                'syncComplete' => __('Sync completed', 'affiliate-client-full'),
            ]
        ]);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('affiliate-client/v1', '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_track_event'],
            'permission_callback' => '__return_true',
            'args' => [
                'event' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'data' => [
                    'required' => false,
                    'type' => 'object',
                ],
            ],
        ]);

        register_rest_route('affiliate-client/v1', '/convert', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_track_conversion'],
            'permission_callback' => '__return_true',
            'args' => [
                'amount' => [
                    'required' => false,
                    'type' => 'number',
                ],
                'reference' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route('affiliate-client/v1', '/test', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_test_endpoint'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Output tracking script in footer
     */
    public function output_tracking_script() {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        $affiliate_id = $this->tracking_handler->get_current_affiliate_id();
        $visit_id = $this->tracking_handler->get_current_visit_id();

        ?>
        <script type="text/javascript">
        (function() {
            if (typeof AffiliateClientTracker !== 'undefined') {
                AffiliateClientTracker.init({
                    affiliateId: <?php echo json_encode($affiliate_id); ?>,
                    visitId: <?php echo json_encode($visit_id); ?>,
                    pageUrl: <?php echo json_encode(get_permalink()); ?>,
                    pageTitle: <?php echo json_encode(get_the_title()); ?>,
                    timestamp: <?php echo time(); ?>
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Affiliate Client Settings', 'affiliate-client-full'),
            __('Affiliate Client', 'affiliate-client-full'),
            'manage_options',
            'affiliate-client-full',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if (isset($_GET['message']) && $_GET['message'] === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('Settings saved successfully.', 'affiliate-client-full') . '</p></div>';
        }

        $remote_url = get_option('affiliate_client_remote_url', '');
        $api_key = get_option('affiliate_client_api_key', '');
        $tracking_enabled = get_option('affiliate_client_tracking_enabled', true);
        $debug_mode = get_option('affiliate_client_debug_mode', false);
        ?>
        <div class="wrap">
            <h1><?php _e('Affiliate Client Settings', 'affiliate-client-full'); ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="affiliate_client_save_settings">
                <?php wp_nonce_field('affiliate_client_save_settings', 'affiliate_client_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="remote_url"><?php _e('Remote AffiliateWP URL', 'affiliate-client-full'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="remote_url" 
                                       name="remote_url" 
                                       value="<?php echo esc_attr($remote_url); ?>" 
                                       class="regular-text" 
                                       placeholder="https://your-main-site.com"
                                       required>
                                <p class="description">
                                    <?php _e('The URL of your main site running AffiliateWP Cross Domain Full.', 'affiliate-client-full'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php _e('API Key', 'affiliate-client-full'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="api_key" 
                                       name="api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text" 
                                       placeholder="Enter API key from main site">
                                <p class="description">
                                    <?php _e('API key from your main site\'s Domain Management page.', 'affiliate-client-full'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e('Tracking Status', 'affiliate-client-full'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="tracking_enabled" 
                                           value="1" 
                                           <?php checked($tracking_enabled, true); ?>>
                                    <?php _e('Enable affiliate tracking on this site', 'affiliate-client-full'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e('Debug Mode', 'affiliate-client-full'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="debug_mode" 
                                           value="1" 
                                           <?php checked($debug_mode, true); ?>>
                                    <?php _e('Enable debug logging (for troubleshooting)', 'affiliate-client-full'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Settings', 'affiliate-client-full'); ?>">
                    <button type="button" class="button" id="test-connection">
                        <?php _e('Test Connection', 'affiliate-client-full'); ?>
                    </button>
                    <button type="button" class="button" id="sync-data">
                        <?php _e('Sync Data', 'affiliate-client-full'); ?>
                    </button>
                </p>
            </form>
            
            <div id="connection-status" style="margin-top: 20px;"></div>
            
            <?php $this->render_status_dashboard(); ?>
        </div>
        <?php
    }

    /**
     * Render status dashboard
     */
    private function render_status_dashboard() {
        $stats = $this->get_tracking_stats();
        ?>
        <div class="postbox" style="margin-top: 20px;">
            <h3 class="hndle"><?php _e('Tracking Statistics', 'affiliate-client-full'); ?></h3>
            <div class="inside">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Metric', 'affiliate-client-full'); ?></th>
                            <th><?php _e('Today', 'affiliate-client-full'); ?></th>
                            <th><?php _e('This Week', 'affiliate-client-full'); ?></th>
                            <th><?php _e('This Month', 'affiliate-client-full'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php _e('Visits Tracked', 'affiliate-client-full'); ?></td>
                            <td><?php echo number_format($stats['visits_today']); ?></td>
                            <td><?php echo number_format($stats['visits_week']); ?></td>
                            <td><?php echo number_format($stats['visits_month']); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Conversions', 'affiliate-client-full'); ?></td>
                            <td><?php echo number_format($stats['conversions_today']); ?></td>
                            <td><?php echo number_format($stats['conversions_week']); ?></td>
                            <td><?php echo number_format($stats['conversions_month']); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('API Calls', 'affiliate-client-full'); ?></td>
                            <td><?php echo number_format($stats['api_calls_today']); ?></td>
                            <td><?php echo number_format($stats['api_calls_week']); ?></td>
                            <td><?php echo number_format($stats['api_calls_month']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Save plugin settings
     */
    public function save_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['affiliate_client_nonce'], 'affiliate_client_save_settings')) {
            wp_die(__('Permission denied.', 'affiliate-client-full'));
        }

        update_option('affiliate_client_remote_url', sanitize_url($_POST['remote_url']));
        update_option('affiliate_client_api_key', sanitize_text_field($_POST['api_key']));
        update_option('affiliate_client_tracking_enabled', isset($_POST['tracking_enabled']));
        update_option('affiliate_client_debug_mode', isset($_POST['debug_mode']));

        // Update configuration
        $this->load_config();

        wp_redirect(add_query_arg('message', 'saved', wp_get_referer()));
        exit;
    }

    /**
     * AJAX: Test connection to remote site
     */
    public function ajax_test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_admin_nonce') || !current_user_can('manage_options')) {
            wp_die();
        }

        $result = $this->api_client->test_connection();
        wp_send_json($result);
    }

    /**
     * AJAX: Sync data with remote site
     */
    public function ajax_sync_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_admin_nonce') || !current_user_can('manage_options')) {
            wp_die();
        }

        $result = $this->sync_pending_data();
        wp_send_json($result);
    }

    /**
     * REST: Track event
     */
    public function rest_track_event($request) {
        $event = sanitize_text_field($request->get_param('event'));
        $data = $request->get_param('data');

        if ($this->tracking_handler) {
            $result = $this->tracking_handler->track_event($event, $data);
            return rest_ensure_response($result);
        }

        return new WP_Error('tracking_disabled', 'Tracking is disabled', ['status' => 403]);
    }

    /**
     * REST: Track conversion
     */
    public function rest_track_conversion($request) {
        $amount = $request->get_param('amount');
        $reference = sanitize_text_field($request->get_param('reference'));

        if ($this->conversion_tracker) {
            $result = $this->conversion_tracker->track_conversion($amount, $reference);
            return rest_ensure_response($result);
        }

        return new WP_Error('tracking_disabled', 'Conversion tracking is disabled', ['status' => 403]);
    }

    /**
     * REST: Test endpoint for domain verification
     */
    public function rest_test_endpoint($request) {
        return rest_ensure_response([
            'success' => true,
            'affiliate_client' => true,
            'version' => AFFILIATE_CLIENT_FULL_VERSION,
            'timestamp' => current_time('c'),
        ]);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Schedule cron events
        if (!wp_next_scheduled('affiliate_client_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'affiliate_client_hourly_sync');
        }
        
        if (!wp_next_scheduled('affiliate_client_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'affiliate_client_daily_cleanup');
        }
        
        // Set default options
        add_option('affiliate_client_tracking_enabled', true);
        add_option('affiliate_client_debug_mode', false);
        add_option('affiliate_client_cookie_expiry', 30 * DAY_IN_SECONDS);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('affiliate_client_hourly_sync');
        wp_clear_scheduled_hook('affiliate_client_daily_cleanup');
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tracking logs table
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            affiliate_id bigint(20) DEFAULT NULL,
            visit_id varchar(100) DEFAULT NULL,
            data longtext,
            synced tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY synced (synced),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Check if tracking is enabled
     */
    public function is_tracking_enabled() {
        return get_option('affiliate_client_tracking_enabled', true) && 
               !empty($this->config['remote_url']) && 
               !empty($this->config['api_key']);
    }

    /**
     * Get cookie domain
     */
    private function get_cookie_domain() {
        return parse_url(home_url(), PHP_URL_HOST);
    }

    /**
     * Get tracking events configuration
     */
    private function get_tracking_events() {
        return apply_filters('affiliate_client_tracking_events', [
            'page_view',
            'click',
            'form_submit',
            'purchase',
            'signup',
        ]);
    }

    /**
     * Get tracking statistics
     */
    private function get_tracking_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $stats = [
            'visits_today' => 0,
            'visits_week' => 0,
            'visits_month' => 0,
            'conversions_today' => 0,
            'conversions_week' => 0,
            'conversions_month' => 0,
            'api_calls_today' => 0,
            'api_calls_week' => 0,
            'api_calls_month' => 0,
        ];

        // Get visit stats
        $visit_stats = $wpdb->get_results("
            SELECT 
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month
            FROM {$table_name}
            WHERE event_type = 'page_view'
        ");

        if ($visit_stats) {
            $stats['visits_today'] = intval($visit_stats[0]->today);
            $stats['visits_week'] = intval($visit_stats[0]->week);
            $stats['visits_month'] = intval($visit_stats[0]->month);
        }

        // Get conversion stats
        $conversion_stats = $wpdb->get_results("
            SELECT 
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month
            FROM {$table_name}
            WHERE event_type = 'conversion'
        ");

        if ($conversion_stats) {
            $stats['conversions_today'] = intval($conversion_stats[0]->today);
            $stats['conversions_week'] = intval($conversion_stats[0]->week);
            $stats['conversions_month'] = intval($conversion_stats[0]->month);
        }

        // Get API call stats
        $api_stats = $wpdb->get_results("
            SELECT 
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month
            FROM {$table_name}
            WHERE synced = 1
        ");

        if ($api_stats) {
            $stats['api_calls_today'] = intval($api_stats[0]->today);
            $stats['api_calls_week'] = intval($api_stats[0]->week);
            $stats['api_calls_month'] = intval($api_stats[0]->month);
        }

        return $stats;
    }

    /**
     * Hourly sync with remote site
     */
    public function hourly_sync() {
        $this->sync_pending_data();
    }

    /**
     * Daily cleanup
     */
    public function daily_cleanup() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        // Delete synced logs older than 30 days
        $wpdb->query("
            DELETE FROM {$table_name}
            WHERE synced = 1 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Delete failed logs older than 7 days
        $wpdb->query("
            DELETE FROM {$table_name}
            WHERE synced = 0 
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }

    /**
     * Sync pending data with remote site
     */
    private function sync_pending_data() {
        if (!$this->api_client) {
            return ['success' => false, 'message' => 'API client not initialized'];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        // Get unsynced records
        $pending_records = $wpdb->get_results("
            SELECT * FROM {$table_name}
            WHERE synced = 0
            ORDER BY created_at ASC
            LIMIT 100
        ");

        if (empty($pending_records)) {
            return ['success' => true, 'message' => 'No pending data to sync'];
        }

        $synced_count = 0;
        $failed_count = 0;

        foreach ($pending_records as $record) {
            $result = $this->api_client->send_tracking_data([
                'event_type' => $record->event_type,
                'affiliate_id' => $record->affiliate_id,
                'visit_id' => $record->visit_id,
                'data' => json_decode($record->data, true),
                'timestamp' => $record->created_at,
            ]);

            if ($result['success']) {
                $wpdb->update(
                    $table_name,
                    ['synced' => 1],
                    ['id' => $record->id],
                    ['%d'],
                    ['%d']
                );
                $synced_count++;
            } else {
                $failed_count++;
            }
        }

        return [
            'success' => true,
            'message' => sprintf(
                __('Synced %d records, %d failed', 'affiliate-client-full'),
                $synced_count,
                $failed_count
            ),
            'synced' => $synced_count,
            'failed' => $failed_count,
        ];
    }

    /**
     * Log debug message
     */
    public function log($message, $level = 'info') {
        if (!$this->config['debug_mode']) {
            return;
        }

        error_log(sprintf(
            '[Affiliate Client Full] [%s] %s',
            strtoupper($level),
            $message
        ));
    }

    /**
     * Get plugin configuration
     */
    public function get_config() {
        return $this->config;
    }
}

/**
 * Initialize the plugin
 */
function affiliate_client_full() {
    return AffiliateClientFull::instance();
}

// Initialize plugin
affiliate_client_full();