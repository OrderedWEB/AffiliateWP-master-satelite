<?php

class AffiliateWP_Cross_Domain_Admin_Integration {
    
    /**
     * Initialize admin integration
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            __('Affiliate Cross Domain', 'affiliatewp-cross-domain-full'),
            __('Affiliate Cross Domain', 'affiliatewp-cross-domain-full'),
            'manage_options',
            'affiliate-cross-domain',
            [$this, 'render_dashboard'],
            'dashicons-networking',
            30
        );
        
        // Submenus for each component
        add_submenu_page(
            'affiliate-cross-domain',
            __('Health Monitoring', 'affiliatewp-cross-domain-full'),
            __('Health Monitoring', 'affiliatewp-cross-domain-full'),
            'manage_options',
            'affiliate-health-monitoring',
            [$this, 'render_health_page']
        );
        
        add_submenu_page(
            'affiliate-cross-domain',
            __('Marketing Portal', 'affiliatewp-cross-domain-full'),
            __('Marketing Portal', 'affiliatewp-cross-domain-full'),
            'manage_affiliate_marketing',
            'affiliate-marketing-portal',
            [$this, 'render_portal_page']
        );
        
        add_submenu_page(
            'affiliate-cross-domain',
            __('Commission Calculator', 'affiliatewp-cross-domain-full'),
            __('Commission Calculator', 'affiliatewp-cross-domain-full'),
            'manage_affiliate_commissions',
            'affiliate-commission-calculator',
            [$this, 'render_commission_page']
        );
        
        add_submenu_page(
            'affiliate-cross-domain',
            __('Role Management', 'affiliatewp-cross-domain-full'),
            __('Role Management', 'affiliatewp-cross-domain-full'),
            'manage_options',
            'affiliate-role-management',
            [$this, 'render_roles_page']
        );
        
        add_submenu_page(
            'affiliate-cross-domain',
            __('Data Backflow', 'affiliatewp-cross-domain-full'),
            __('Data Backflow', 'affiliatewp-cross-domain-full'),
            'manage_options',
            'affiliate-data-backflow',
            [$this, 'render_backflow_page']
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'affiliate-') !== false) {
            wp_enqueue_script(
                'affiliate-cross-domain-admin',
                AFFILIATEWP_CROSS_DOMAIN_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery', 'wp-api'],
                AFFILIATEWP_CROSS_DOMAIN_VERSION,
                true
            );
            
            wp_enqueue_style(
                'affiliate-cross-domain-admin',
                AFFILIATEWP_CROSS_DOMAIN_PLUGIN_URL . 'assets/css/admin.css',
                [],
                AFFILIATEWP_CROSS_DOMAIN_VERSION
            );
            
            wp_localize_script('affiliate-cross-domain-admin', 'affiliateAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('affiliatewp-cross-domain/v1/'),
                'nonce' => wp_create_nonce('affiliate_admin_nonce'),
                'strings' => [
                    'confirm_delete' => __('Are you sure you want to delete this?', 'affiliatewp-cross-domain-full'),
                    'processing' => __('Processing...', 'affiliatewp-cross-domain-full'),
                    'success' => __('Operation completed successfully', 'affiliatewp-cross-domain-full'),
                    'error' => __('An error occurred', 'affiliatewp-cross-domain-full')
                ]
            ]);
        }
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!isset($_POST['affiliate_action']) || !wp_verify_nonce($_POST['_wpnonce'], 'affiliate_admin_action')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['affiliate_action']);
        
        switch ($action) {
            case 'run_health_check':
                $this->handle_health_check();
                break;
                
            case 'export_materials':
                $this->handle_export_materials();
                break;
                
            case 'calculate_commission':
                $this->handle_commission_calculation();
                break;
                
            case 'update_roles':
                $this->handle_role_update();
                break;
                
            case 'process_backflow':
                $this->handle_backflow_processing();
                break;
        }
    }
    
    /**
     * Render pages for each component
     */
    public function render_dashboard() {
        $plugin_manager = AffiliateWP_Cross_Domain_Plugin_Manager::instance();
        include AFFILIATEWP_CROSS_DOMAIN_PLUGIN_DIR . 'admin/templates/dashboard.php';
    }
    
    public function render_health_page() {
        $health_monitor = new Affiliate_Link_Health_Monitor();
        $health_data = $health_monitor->generate_health_report();
        include AFFILIATEWP_CROSS_DOMAIN_PLUGIN_DIR . 'admin/templates/health-monitoring.php';
    }
    
    public function render_portal_page() {
        $portal = new Affiliate_Portal_Enhancement();
        $user_id = get_current_user_id();
        $portal_data = $portal->get_portal_dashboard_data($user_id);
        include AFFILIATEWP_CROSS_DOMAIN_PLUGIN_DIR . 'admin/templates/marketing-portal.php';
    }
    
    public function render_commission_page() {
        $calculator = new Enhanced_Commission_Calculator();
        include AFFILIATEWP_CROSS_DOMAIN_PLUGIN_DIR . 'admin/templates/commission-calculator.php';
    }
    
    public function render_roles_page() {
        $role_management = new Enhanced_Role_Management();
        $roles_data = $role_management->get_affiliate_roles_overview();
        include AFFILIATEWP_CROSS_DOMAIN_PLUGIN_DIR . 'admin/templates/role-management.php';
    }
    
    public function render_backflow_page() {
        $backflow = new Satellite_Data_Backflow_Manager();
        $backflow_data = $backflow->get_backflow_dashboard_data();
        include AFFILIATEWP_CROSS_DOMAIN_PLUGIN_DIR . 'admin/templates/data-backflow.php';
    }
}