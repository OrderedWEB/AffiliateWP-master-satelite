<?php
/**
 * Master Plugin Manager
 * Wires submodules together (health monitor, portal, calculator, roles), admin pages,
 * REST + AJAX endpoints, and cron schedules.
 *
 * File: class-plugin-manager.php (fixed/hardened)
 */

if ( ! defined('ABSPATH') ) { exit; }

class AffiliateWP_Cross_Domain_Plugin_Manager {

    /** @var self */
    private static $instance = null;

    /** @var AffiliateLinkHealthMonitor|null */
    private $health = null;

    /** @var AffiliateLinkHealthMonitor|null */
    private $health_monitor = null; // alias, if code elsewhere expects it

    /** @var AffiliatePortalEnhancement|null */
    private $portal = null;

    /** @var EnhancedCommissionCalculator|null */
    private $calculator = null;

    /** @var AffiliateLinkHealthMonitor|null */
    private $health_fallback = null;

    /** @var EnhancedRoleManagement|null */
    private $roles = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Include required files safely (if present)
     */
    private function includes() {
        $base = plugin_dir_path( __FILE__ );

        $paths = [
            'AffiliatePortalEnhancement'       => $base . 'class-affiliate-portal-enhancement.php',
            'EnhancedCommissionCalculator'     => $base . 'class-enhanced-commission-calculator.php',
            'AffiliateLinkHealthMonitor'       => $base . 'class-affiliate-link-health-monitor.php',
            'EnhancedRoleManagement'           => $base . 'class-enhanced-role-management.php',
        ];

        foreach ( $paths as $class => $path ) {
            if ( ! class_exists( $class ) && file_exists( $path ) ) {
                require_once $path;
            }
        }
    }

    /**
     * Prepare components (guard if class missing)
     */
    private function init_components() {
        if ( class_exists('AffiliatePortalEnhancement') ) {
            $this->portal = new AffiliatePortalEnhancement();
        }
        if ( class_exists('EnhancedCommissionCalculator') ) {
            $this->calculator = new EnhancedCommissionCalculator();
        }
        if ( class_exists('AffiliateLinkHealthMonitor') ) {
            $this->health = new AffiliateLinkHealthMonitor();
            $this->health_monitor = $this->health; // alias
            $this->health_fallback = $this->health;
        }
        if ( class_exists('EnhancedRoleManagement') ) {
            $this->roles = new EnhancedRoleManagement();
        }
    }

    /**
     * Hooks (admin, REST, AJAX, cron)
     */
    private function init_hooks() {
        add_action( 'admin_menu', [ $this, 'add_admin_menus' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // AJAX endpoints (admin)
        add_action( 'wp_ajax_affiliate_manual_health_check', [ $this, 'ajax_manual_health_check' ] );
        add_action( 'wp_ajax_affiliate_cleanup_logs',        [ $this, 'ajax_cleanup_logs' ] );
        add_action( 'wp_ajax_affiliate_generate_materials',  [ $this, 'ajax_generate_materials' ] );
        add_action( 'wp_ajax_affiliate_save_custom_template',[ $this, 'ajax_save_custom_template' ] );
        add_action( 'wp_ajax_affiliate_export_marketing_package', [ $this, 'ajax_export_marketing_package' ] );

        // Cron: daily health check passthrough (if submodule installed)
        add_action( 'ame_daily_master_health_check', [ $this, 'cron_daily_health_check' ] );

        // Ensure schedules exist
        add_action( 'init', [ $this, 'init_schedules' ] );
    }

    /**
     * Ensure cron schedules are set
     */
    public function init_schedules() {
        if ( ! wp_next_scheduled( 'ame_daily_master_health_check' ) ) {
            wp_schedule_event( time(), 'daily', 'ame_daily_master_health_check' );
        }
    }

    /**
     * Admin menus
     */
    public function add_admin_menus() {
        if ( ! current_user_can('manage_options') ) { return; }

        add_menu_page(
            __( 'Affiliate Cross-Domain', 'affiliatewp-cross-domain-full' ),
            __( 'Affiliate Suite', 'affiliatewp-cross-domain-full' ),
            'manage_options',
            'affiliate-cross-domain',
            [ $this, 'render_dashboard_page' ],
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'affiliate-cross-domain',
            __( 'Health Monitor', 'affiliatewp-cross-domain-full' ),
            __( 'Health Monitor', 'affiliatewp-cross-domain-full' ),
            'manage_options',
            'affiliate-health-monitor',
            [ $this, 'render_health_monitor_page' ]
        );

        add_submenu_page(
            'affiliate-cross-domain',
            __( 'Marketing Materials', 'affiliatewp-cross-domain-full' ),
            __( 'Marketing Materials', 'affiliatewp-cross-domain-full' ),
            'manage_affiliate_marketing',
            'affiliate-marketing-materials',
            [ $this, 'render_marketing_materials_page' ]
        );

        add_submenu_page(
            'affiliate-cross-domain',
            __( 'Portal Enhancement', 'affiliatewp-cross-domain-full' ),
            __( 'Portal Enhancement', 'affiliatewp-cross-domain-full' ),
            'manage_options',
            'affiliate-portal-enhn', /* avoid very long slug */
            [ $this, 'render_portal_enhancement_page' ]
        );
    }

    /** Basic admin pages (very lightweight) */
    public function render_dashboard_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Affiliate Suite Dashboard', 'affiliatewp-cross-domain-full' ) . '</h1>';
        echo '<p>' . esc_html__( 'Use the submenu to access Health Monitor, Marketing Materials, or Portal settings.', 'affiliatewp-cross-domain-full' ) . '</p></div>';
    }
    public function render_health_monitor_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Health Monitor', 'affiliatewp-cross-domain-full' ) . '</h1>';
        if ( $this->health ) {
            $report = get_option('ame_last_health_report');
            if ( $report ) {
                echo '<pre style="max-height:400px;overflow:auto;background:#fff;border:1px solid #ddd;padding:12px;">' . esc_html( print_r( $report, true ) ) . '</pre>';
            } else {
                echo '<p>' . esc_html__( 'No health report available yet.', 'affiliatewp-cross-domain-full' ) . '</p>';
            }
        } else {
            echo '<p>' . esc_html__( 'Health monitor module not active/installed.', 'affiliatewp-cross-domain-full' ) . '</p>';
        }
        echo '</div>';
    }
    public function render_marketing_materials_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Marketing Materials', 'affiliatewp-cross-domain-full' ) . '</h1>';
        echo '<p>' . esc_html__( 'Generate and manage marketing materials via the REST or AJAX endpoints.', 'affiliatewp-cross-domain-full' ) . '</p></div>';
    }
    public function render_portal_enhancement_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Portal Enhancement', 'affiliatewp-cross-domain-full' ) . '</h1>';
        if ( $this->portal ) {
            echo '<p>' . esc_html__( 'Portal enhancement module loaded.', 'affiliatewp-cross-domain-full' ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'Portal enhancement module not active/installed.', 'affiliatewp-cross-domain-full' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * REST routes
     */
    public function register_rest_routes() {
        register_rest_route( 'affiliatewp-cross-domain/v1', '/health', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_health_status' ],
            'permission_callback' => [ $this, 'check_health_permissions' ],
        ] );

        register_rest_route( 'affiliatewp-cross-domain/v1', '/health/check', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_trigger_health_check' ],
            'permission_callback' => [ $this, 'check_health_permissions' ],
        ] );

        register_rest_route( 'affiliatewp-cross-domain/v1', '/materials/(?P<type>\\w+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_materials' ],
            'permission_callback' => [ $this, 'check_materials_permissions' ],
            'args'                => [
                'type' => [ 'type' => 'string', 'required' => true ]
            ]
        ] );

        register_rest_route( 'affiliatewp-cross-domain/v1', '/materials/generate', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_generate_materials' ],
            'permission_callback' => [ $this, 'check_materials_permissions' ],
        ] );

        register_rest_route( 'affiliatewp-cross-domain/v1', '/analytics/(?P<user_id>\\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_analytics' ],
            'permission_callback' => [ $this, 'check_analytics_permissions' ],
            'args'                => [
                'user_id' => [ 'type' => 'integer', 'required' => true ]
            ]
        ] );
    }

    /** Permission callbacks */
    public function check_health_permissions( $request ) {
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_affiliates' );
    }
    public function check_materials_permissions( $request ) {
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_affiliate_marketing' );
    }
    public function check_analytics_permissions( $request ) {
        $user_id = intval( $request->get_param('user_id') );
        $current = get_current_user_id();
        if ( ! $user_id || $current === $user_id ) {
            return is_user_logged_in();
        }
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_affiliates' );
    }

    /** REST: Health status */
    public function rest_get_health_status( $request ) {
        if ( $this->health && method_exists( $this->health, 'get_health_statistics' ) ) {
            $stats = $this->health->get_health_statistics();
        } else {
            $stats = [ 'status' => 'unavailable', 'message' => 'Health module missing' ];
        }
        return rest_ensure_response( $stats );
    }

    /** REST: Trigger health check */
    public function rest_trigger_health_check( $request ) {
        if ( ! $this->health || ! method_exists( $this->health, 'run_health_check' ) ) {
            return new WP_Error( 'no_health_module', 'Health monitor not available', [ 'status' => 501 ] );
        }
        $this->health->run_health_check();
        $report = get_option( 'ame_last_health_report' );
        return rest_ensure_response( [
            'status'  => 'queued_or_completed',
            'report'  => $report,
            'checked' => current_time( 'mysql' ),
        ] );
    }

    /** REST: Get materials */
    public function rest_get_materials( $request ) {
        $type = sanitize_text_field( $request->get_param('type') );
        if ( ! $this->portal || ! method_exists( $this->portal, 'get_materials' ) ) {
            return new WP_Error( 'no_portal', 'Portal enhancement not available', [ 'status' => 501 ] );
        }
        $materials = $this->portal->get_materials( $type );
        return rest_ensure_response( [
            'status' => 'success',
            'data'   => $materials,
            'type'   => $type,
        ] );
    }

    /** REST: Generate materials */
    public function rest_generate_materials( $request ) {
        if ( ! $this->portal || ! method_exists( $this->portal, 'generate_materials' ) ) {
            return new WP_Error( 'no_portal', 'Portal enhancement not available', [ 'status' => 501 ] );
        }
        $type = sanitize_text_field( $request->get_param('type') ?: 'banner' );
        $params = is_array( $request->get_params() ) ? $request->get_params() : [];
        $result = $this->portal->generate_materials( $type, $params );
        return rest_ensure_response( [
            'status' => 'success',
            'result' => $result,
            'type'   => $type,
        ] );
    }

    /** REST: Get analytics (simple proxy to calculator if present) */
    public function rest_get_analytics( $request ) {
        $user_id = intval( $request->get_param('user_id') );
        if ( ! $user_id ) {
            return new WP_Error( 'missing_user_id', 'user_id required', [ 'status' => 400 ] );
        }
        if ( $this->calculator && method_exists( $this->calculator, 'get_affiliate_commission_history' ) ) {
            return rest_ensure_response( $this->calculator->get_affiliate_commission_history( $request ) );
        }
        return rest_ensure_response( [
            'status'  => 'unavailable',
            'message' => 'Calculator module missing',
        ] );
    }

    /* ===================== AJAX ===================== */

    public function ajax_manual_health_check() {
        check_ajax_referer( 'affiliate_health_check', 'nonce' );
        if ( ! current_user_can('manage_options') && ! current_user_can('manage_affiliates') ) {
            wp_die( 'Insufficient permissions' );
        }
        if ( $this->health && method_exists( $this->health, 'run_health_check' ) ) {
            $this->health->run_health_check();
            $report = get_option( 'ame_last_health_report' );
            wp_send_json_success( [ 'report' => $report ] );
        } else {
            wp_send_json_error( 'Health monitor not available' );
        }
    }

    public function ajax_cleanup_logs() {
        check_ajax_referer( 'affiliate_cleanup_logs', 'nonce' );
        if ( ! current_user_can('manage_options') ) {
            wp_die( 'Insufficient permissions' );
        }
        if ( $this->health && method_exists( $this->health, 'cleanup_health_logs' ) ) {
            $deleted = $this->health->cleanup_health_logs( 30 );
            wp_send_json_success( [ 'deleted' => intval( $deleted ) ] );
        } else {
            wp_send_json_error( 'Cleanup not available' );
        }
    }

    public function ajax_generate_materials() {
        check_ajax_referer( 'affiliate_generate_materials', 'nonce' );
        if ( ! current_user_can('manage_affiliate_marketing') && ! current_user_can('manage_options') ) {
            wp_die( 'Insufficient permissions' );
        }
        $type = sanitize_text_field( $_POST['type'] ?? 'banner' );
        $params = isset($_POST['params']) ? (array) $_POST['params'] : [];
        if ( $this->portal && method_exists( $this->portal, 'generate_materials' ) ) {
            $result = $this->portal->generate_materials( $type, $params );
            wp_send_json_success( [ 'result' => $result ] );
        } else {
            wp_send_json_error( 'Portal generator not available' );
        }
    }

    public function ajax_save_custom_template() {
        check_ajax_referer( 'affiliate_save_custom_template', 'nonce' );
        if ( ! current_user_can('manage_affiliate_marketing') && ! current_user_can('manage_options') ) {
            wp_die( 'Insufficient permissions' );
        }
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        $body = wp_kses_post( $_POST['body'] ?? '' );
        if ( ! $name ) {
            wp_send_json_error( 'Template name required' );
        }
        // Store under an option namespaced
        $templates = get_option( 'affiliate_marketing_templates', [] );
        if ( ! is_array( $templates ) ) { $templates = []; }
        $templates[ $name ] = [
            'body'       => $body,
            'updated_at' => current_time( 'mysql' ),
            'updated_by' => get_current_user_id(),
        ];
        update_option( 'affiliate_marketing_templates', $templates, false );
        wp_send_json_success( [ 'saved' => true ] );
    }

    public function ajax_export_marketing_package() {
        check_ajax_referer( 'affiliate_export_marketing_package', 'nonce' );
        if ( ! current_user_can('manage_affiliate_marketing') && ! current_user_can('manage_options') ) {
            wp_die( 'Insufficient permissions' );
        }
        // Provide a barebones export (JSON) of stored templates
        $templates = get_option( 'affiliate_marketing_templates', [] );
        if ( headers_sent() ) {
            wp_send_json_success( [ 'templates' => $templates ] );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=marketing-templates-' . date('Ymd-His') . '.json' );
        echo wp_json_encode( $templates );
        exit;
    }

    /* ===================== CRON ===================== */

    public function cron_daily_health_check() {
        if ( $this->health && method_exists( $this->health, 'run_health_check' ) ) {
            $this->health->run_health_check();
        }
    }
}

// Bootstrap the manager
AffiliateWP_Cross_Domain_Plugin_Manager::instance();
