<?php
/**
 * AffiliateWP Ecosystem Integration
 * 
 * Deep integration with all major AffiliateWP add-ons to provide
 * comprehensive cross-domain functionality and enhanced features
 * 
 * Filename: class-affiliatewp-ecosystem-integration.php
 * Path: /wp-content/plugins/affiliate-master-enhancement/includes/
 * 
 * @package AffiliateWPEcosystemIntegration
 * @author Richard King <r.king@starneconsulting.com>
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AffiliateWP Ecosystem Integration Class
 * Provides comprehensive integration with AffiliateWP add-ons
 */
class AffiliateWPEcosystemIntegration {
    
    /**
     * Supported AffiliateWP add-ons with their detection methods
     * @var array
     */
    private $supported_addons = [
        'recurring_referrals' => [
            'class' => 'Affiliate_WP_Recurring_Referrals',
            'function' => 'affiliatewp_recurring_referrals',
            'version_required' => '1.8.0'
        ],
        'multi_level_affiliates' => [
            'class' => 'Affiliate_WP_Multi_Level_Affiliates',
            'function' => 'affiliatewp_multi_level_affiliates',
            'version_required' => '1.3.0'
        ],
        'lifetime_commissions' => [
            'class' => 'AffiliateWP_Lifetime_Commissions',
            'function' => 'affiliatewp_lifetime_commissions',
            'version_required' => '1.0.0'
        ],
        'store_credit' => [
            'class' => 'AffiliateWP_Store_Credit',
            'function' => 'affiliatewp_store_credit',
            'version_required' => '1.0.0'
        ],
        'direct_link_tracking' => [
            'class' => 'AffiliateWP_Direct_Link_Tracking',
            'function' => 'affiliatewp_direct_link_tracking',
            'version_required' => '1.0.0'
        ],
        'custom_affiliate_slugs' => [
            'class' => 'AffiliateWP_Custom_Affiliate_Slugs',
            'function' => 'affiliatewp_custom_affiliate_slugs',
            'version_required' => '1.0.0'
        ],
        'affiliate_landing_pages' => [
            'class' => 'AffiliateWP_Affiliate_Landing_Pages',
            'function' => 'affiliatewp_affiliate_landing_pages',
            'version_required' => '1.0.0'
        ],
        'pushover_notifications' => [
            'class' => 'AffiliateWP_Pushover_Notifications',
            'function' => 'affiliatewp_pushover_notifications',
            'version_required' => '1.0.0'
        ],
        'leaderboard' => [
            'class' => 'AffiliateWP_Leaderboard',
            'function' => 'affiliatewp_leaderboard',
            'version_required' => '1.0.0'
        ],
        'affiliate_product_rates' => [
            'class' => 'AffiliateWP_Affiliate_Product_Rates',
            'function' => 'affiliatewp_affiliate_product_rates',
            'version_required' => '1.0.0'
        ],
        'tiered_affiliate_rates' => [
            'class' => 'AffiliateWP_Tiered_Affiliate_Rates',
            'function' => 'affiliatewp_tiered_affiliate_rates',
            'version_required' => '1.0.0'
        ]
    ];
    
    /**
     * Active add-ons detected during initialisation
     * @var array
     */
    private $active_addons = [];
    
    /**
     * Constructor - initialise ecosystem integration
     */
    public function __construct() {
        add_action('plugins_loaded', [$this, 'detect_active_addons'], 20);
        add_action('init', [$this, 'initialise_integrations'], 25);
        
        // Hook into AffiliateWP core events
        add_action('affwp_insert_referral', [$this, 'handle_referral_creation'], 10, 1);
        add_action('affwp_set_referral_status', [$this, 'handle_referral_status_change'], 10, 3);
        add_filter('affwp_get_affiliate_rate', [$this, 'apply_enhanced_commission_logic'], 10, 3);
        
        // Cross-domain specific hooks
        add_filter('ame_cross_domain_referral_data', [$this, 'enhance_cross_domain_data'], 10, 2);
        add_action('ame_process_satellite_conversion', [$this, 'process_addon_specific_conversion'], 10, 2);
    }
    
    /**
     * Detect which AffiliateWP add-ons are active
     */
    public function detect_active_addons() {
        foreach ($this->supported_addons as $addon_key => $addon_config) {
            if ($this->is_addon_active($addon_config)) {
                $this->active_addons[$addon_key] = $addon_config;
                
                // Store addon version for compatibility checking
                $this->active_addons[$addon_key]['detected_version'] = $this->get_addon_version($addon_config);
            }
        }
        
        // Log detected add-ons for debugging
        if (!empty($this->active_addons)) {
            error_log('AME: Detected AffiliateWP add-ons: ' . implode(', ', array_keys($this->active_addons)));
        }
    }
    
    /**
     * Check if specific add-on is active
     * @param array $addon_config Add-on configuration
     * @return bool Whether add-on is active
     */
    private function is_addon_active($addon_config) {
        // Check by class existence
        if (!empty($addon_config['class']) && class_exists($addon_config['class'])) {
            return true;
        }
        
        // Check by function existence
        if (!empty($addon_config['function']) && function_exists($addon_config['function'])) {
            return true;
        }
        
        // Check by defined constants (fallback method)
        $constant_name = 'AFFILIATEWP_' . strtoupper($addon_config['class'] ?? '') . '_VERSION';
        if (defined($constant_name)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get version of detected add-on
     * @param array $addon_config Add-on configuration
     * @return string Add-on version or 'unknown'
     */
    private function get_addon_version($addon_config) {
        // Try to get version from class constant
        if (!empty($addon_config['class']) && class_exists($addon_config['class'])) {
            $class = $addon_config['class'];
            if (defined($class . '::VERSION')) {
                return constant($class . '::VERSION');
            }
        }
        
        // Try to get version from global constant
        $constant_name = 'AFFILIATEWP_' . strtoupper(str_replace(['Affiliate_WP_', 'AffiliateWP_'], '', $addon_config['class'] ?? '')) . '_VERSION';
        if (defined($constant_name)) {
            return constant($constant_name);
        }
        
        return 'unknown';
    }
    
    /**
     * Initialise integrations for active add-ons
     */
    public function initialise_integrations() {
        foreach ($this->active_addons as $addon_key => $addon_config) {
            $this->configure_addon_integration($addon_key, $addon_config);
        }
        
        // Register enhanced API endpoints for add-on functionality
        add_action('rest_api_init', [$this, 'register_addon_api_endpoints']);
    }
    
    /**
     * Configure integration for specific add-on
     * @param string $addon_key Add-on identifier
     * @param array $addon_config Add-on configuration
     */
    private function configure_addon_integration($addon_key, $addon_config) {
        switch ($addon_key) {
            case 'recurring_referrals':
                $this->setup_recurring_referrals_integration();
                break;
                
            case 'multi_level_affiliates':
                $this->setup_multi_level_affiliates_integration();
                break;
                
            case 'lifetime_commissions':
                $this->setup_lifetime_commissions_integration();
                break;
                
            case 'store_credit':
                $this->setup_store_credit_integration();
                break;
                
            case 'direct_link_tracking':
                $this->setup_direct_link_tracking_integration();
                break;
                
            case 'custom_affiliate_slugs':
                $this->setup_custom_slugs_integration();
                break;
                
            case 'affiliate_landing_pages':
                $this->setup_landing_pages_integration();
                break;
                
            case 'pushover_notifications':
                $this->setup_pushover_integration();
                break;
                
            case 'leaderboard':
                $this->setup_leaderboard_integration();
                break;
                
            case 'affiliate_product_rates':
                $this->setup_product_rates_integration();
                break;
                
            case 'tiered_affiliate_rates':
                $this->setup_tiered_rates_integration();
                break;
        }
    }
    
    /**
     * Setup Recurring Referrals integration
     */
    private function setup_recurring_referrals_integration() {
        // Hook into recurring referral creation
        add_action('affwp_recurring_referrals_insert_referral', [$this, 'track_recurring_referral'], 10, 2);
        
        // Enhance cross-domain recurring referral tracking
        add_filter('ame_referral_data', [$this, 'add_recurring_referral_data'], 10, 2);
        
        // API endpoint for recurring referral status
        add_action('ame_register_api_endpoints', function() {
            register_rest_route('affiliate-enhancement/v1', '/recurring-referrals/(?P<affiliate_id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'get_recurring_referrals_data'],
                'permission_callback' => [$this, 'check_affiliate_permissions']
            ]);
        });
    }
    
    /**
     * Setup Multi-Level Affiliates integration
     */
    private function setup_multi_level_affiliates_integration() {
        // Hook into multi-level commission calculations
        add_filter('affwp_calc_referral_amount', [$this, 'calculate_multi_level_commissions'], 10, 5);
        
        // Track multi-level referral chains across domains
        add_action('ame_process_cross_domain_referral', [$this, 'process_multi_level_chain'], 10, 2);
        
        // Enhanced dashboard data for multi-level structure
        add_filter('ame_dashboard_data', [$this, 'add_multi_level_dashboard_data'], 10, 2);
    }
    
    /**
     * Setup Lifetime Commissions integration
     */
    private function setup_lifetime_commissions_integration() {
        // Track lifetime commission relationships across domains
        add_action('ame_affiliate_conversion', [$this, 'check_lifetime_commission_eligibility'], 10, 2);
        
        // Enhance affiliate portal with lifetime commission data
        add_filter('ame_affiliate_portal_data', [$this, 'add_lifetime_commission_data'], 10, 2);
    }
    
    /**
     * Setup Store Credit integration
     */
    private function setup_store_credit_integration() {
        // Handle store credit across multiple domains
        add_filter('ame_commission_payment_methods', [$this, 'add_store_credit_option'], 10, 1);
        
        // API for checking store credit balance across sites
        add_action('ame_register_api_endpoints', function() {
            register_rest_route('affiliate-enhancement/v1', '/store-credit/(?P<affiliate_id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'get_store_credit_balance'],
                'permission_callback' => [$this, 'check_affiliate_permissions']
            ]);
        });
    }
    
    /**
     * Setup Direct Link Tracking integration
     */
    private function setup_direct_link_tracking_integration() {
        // Enhance direct link tracking for cross-domain scenarios
        add_filter('ame_track_affiliate_visit', [$this, 'enhance_direct_link_tracking'], 10, 3);
        
        // Generate cross-domain compatible direct links
        add_filter('ame_generate_affiliate_link', [$this, 'generate_cross_domain_direct_link'], 10, 3);
    }
    
    /**
     * Setup Custom Affiliate Slugs integration
     */
    private function setup_custom_slugs_integration() {
        // Ensure custom slugs work across all domains
        add_filter('ame_resolve_affiliate_identifier', [$this, 'resolve_custom_slug'], 10, 2);
        
        // Sync custom slugs across satellite sites
        add_action('affwp_update_affiliate', [$this, 'sync_custom_slug_changes'], 10, 2);
    }
    
    /**
     * Setup Affiliate Landing Pages integration
     */
    private function setup_landing_pages_integration() {
        // Cross-reference landing pages across domains
        add_filter('ame_affiliate_marketing_materials', [$this, 'add_landing_page_materials'], 10, 2);
        
        // Track landing page performance across sites
        add_action('ame_track_landing_page_visit', [$this, 'track_cross_domain_landing_performance'], 10, 3);
    }
    
    /**
     * Setup Pushover Notifications integration
     */
    private function setup_pushover_integration() {
        // Enhanced notifications for cross-domain events
        add_action('ame_cross_domain_conversion', [$this, 'send_cross_domain_notification'], 10, 2);
        
        // Aggregate notifications for multi-site performance
        add_filter('affwp_pushover_notification_message', [$this, 'enhance_notification_message'], 10, 3);
    }
    
    /**
     * Setup Leaderboard integration
     */
    private function setup_leaderboard_integration() {
        // Create cross-domain leaderboards
        add_filter('affwp_leaderboard_query_args', [$this, 'enhance_leaderboard_data'], 10, 1);
        
        // API for cross-site leaderboard data
        add_action('ame_register_api_endpoints', function() {
            register_rest_route('affiliate-enhancement/v1', '/leaderboard/cross-domain', [
                'methods' => 'GET',
                'callback' => [$this, 'get_cross_domain_leaderboard'],
                'permission_callback' => '__return_true'
            ]);
        });
    }
    
    /**
     * Setup Affiliate Product Rates integration
     */
    private function setup_product_rates_integration() {
        // Apply product-specific rates across domains
        add_filter('ame_calculate_commission', [$this, 'apply_product_specific_rates'], 10, 4);
        
        // Sync product rate changes across sites
        add_action('affwp_update_product_rate', [$this, 'sync_product_rate_changes'], 10, 3);
    }
    
    /**
     * Setup Tiered Affiliate Rates integration
     */
    private function setup_tiered_rates_integration() {
        // Apply tiered rates based on cross-domain performance
        add_filter('affwp_get_affiliate_rate', [$this, 'apply_cross_domain_tiered_rates'], 10, 3);
        
        // Update tiers based on aggregate performance
        add_action('ame_daily_performance_update', [$this, 'update_affiliate_tiers'], 10, 1);
    }
    
    /**
     * Handle referral creation with add-on enhancements
     * @param int $referral_id Referral ID
     */
    public function handle_referral_creation($referral_id) {
        $referral = affwp_get_referral($referral_id);
        if (!$referral) {
            return;
        }
        
        // Process multi-level affiliates if active
        if (isset($this->active_addons['multi_level_affiliates'])) {
            $this->process_multi_level_referral($referral);
        }
        
        // Handle recurring referrals setup if active
        if (isset($this->active_addons['recurring_referrals'])) {
            $this->setup_recurring_tracking($referral);
        }
        
        // Check lifetime commission eligibility
        if (isset($this->active_addons['lifetime_commissions'])) {
            $this->check_lifetime_eligibility($referral);
        }
        
        // Send enhanced notifications
        if (isset($this->active_addons['pushover_notifications'])) {
            $this->send_enhanced_notification($referral);
        }
    }
    
    /**
     * Handle referral status changes with add-on integration
     * @param int $referral_id Referral ID
     * @param string $new_status New status
     * @param string $old_status Previous status
     */
    public function handle_referral_status_change($referral_id, $new_status, $old_status) {
        $referral = affwp_get_referral($referral_id);
        if (!$referral) {
            return;
        }
        
        // Update multi-level commissions if needed
        if (isset($this->active_addons['multi_level_affiliates']) && $new_status === 'paid') {
            $this->pay_multi_level_commissions($referral);
        }
        
        // Handle store credit payments
        if (isset($this->active_addons['store_credit']) && $new_status === 'paid') {
            $this->process_store_credit_payment($referral);
        }
        
        // Update tiered rates if performance changed
        if (isset($this->active_addons['tiered_affiliate_rates'])) {
            $this->check_tier_updates($referral->affiliate_id);
        }
        
        // Send status change notifications
        if (isset($this->active_addons['pushover_notifications'])) {
            $this->notify_status_change($referral, $new_status, $old_status);
        }
    }
    
    /**
     * Apply enhanced commission logic with add-on integration
     * @param float $rate Current rate
     * @param int $affiliate_id Affiliate ID
     * @param array $args Additional arguments
     * @return float Enhanced rate
     */
    public function apply_enhanced_commission_logic($rate, $affiliate_id, $args = []) {
        // Apply tiered rates based on cross-domain performance
        if (isset($this->active_addons['tiered_affiliate_rates'])) {
            $rate = $this->calculate_tiered_rate($rate, $affiliate_id, $args);
        }
        
        // Apply product-specific rates
        if (isset($this->active_addons['affiliate_product_rates']) && !empty($args['product_id'])) {
            $rate = $this->apply_product_rate($rate, $affiliate_id, $args['product_id']);
        }
        
        // Apply cross-domain performance bonuses
        $rate = $this->apply_cross_domain_bonus($rate, $affiliate_id, $args);
        
        return $rate;
    }
    
    /**
     * Process multi-level referral creation
     * @param object $referral Referral object
     */
    private function process_multi_level_referral($referral) {
        if (!class_exists('Affiliate_WP_Multi_Level_Affiliates')) {
            return;
        }
        
        $multi_level = affiliatewp_multi_level_affiliates();
        $parent_affiliates = $multi_level->get_parent_affiliates($referral->affiliate_id);
        
        foreach ($parent_affiliates as $level => $parent_id) {
            $commission_rate = $multi_level->get_level_rate($level, $parent_id);
            
            if ($commission_rate > 0) {
                $commission_amount = $referral->amount * ($commission_rate / 100);
                
                // Create parent referral with cross-domain tracking
                $parent_referral_data = [
                    'affiliate_id' => $parent_id,
                    'amount' => $commission_amount,
                    'description' => sprintf(__('Level %d commission from affiliate #%d', 'affiliate-master-enhancement'), $level, $referral->affiliate_id),
                    'reference' => $referral->reference,
                    'context' => 'multi_level_' . $level,
                    'campaign' => $referral->campaign,
                    'parent_referral_id' => $referral->referral_id
                ];
                
                $parent_referral_id = affwp_add_referral($parent_referral_data);
                
                // Track the multi-level relationship
                $this->track_multi_level_relationship($referral->referral_id, $parent_referral_id, $level);
            }
        }
    }
    
    /**
     * Track multi-level relationship in database
     * @param int $child_referral_id Child referral ID
     * @param int $parent_referral_id Parent referral ID
     * @param int $level Commission level
     */
    private function track_multi_level_relationship($child_referral_id, $parent_referral_id, $level) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_multi_level_tracking',
            [
                'child_referral_id' => $child_referral_id,
                'parent_referral_id' => $parent_referral_id,
                'commission_level' => $level,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s']
        );
    }
    
    /**
     * Calculate tiered rate based on cross-domain performance
     * @param float $base_rate Base commission rate
     * @param int $affiliate_id Affiliate ID
     * @param array $args Additional arguments
     * @return float Calculated rate
     */
    private function calculate_tiered_rate($base_rate, $affiliate_id, $args) {
        if (!class_exists('AffiliateWP_Tiered_Affiliate_Rates')) {
            return $base_rate;
        }
        
        $tiered_rates = affiliatewp_tiered_affiliate_rates();
        $affiliate_tier = $this->get_affiliate_tier($affiliate_id);
        
        if ($affiliate_tier && isset($affiliate_tier['rate'])) {
            return $affiliate_tier['rate'];
        }
        
        return $base_rate;
    }
    
    /**
     * Get affiliate tier based on cross-domain performance
     * @param int $affiliate_id Affiliate ID
     * @return array|null Tier information
     */
    private function get_affiliate_tier($affiliate_id) {
        global $wpdb;
        
        // Calculate cross-domain performance metrics
        $performance_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN metric_type = 'earnings' THEN metric_value ELSE 0 END) as total_earnings,
                SUM(CASE WHEN metric_type = 'referrals' THEN metric_value ELSE 0 END) as total_referrals,
                COUNT(DISTINCT domain) as active_domains
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE affiliate_id = %d
            AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ", $affiliate_id));
        
        if (!$performance_data) {
            return null;
        }
        
        // Define tier thresholds (these would be configurable in a real implementation)
        $tiers = [
            'bronze' => ['earnings' => 100, 'referrals' => 5, 'rate' => 0.05],
            'silver' => ['earnings' => 500, 'referrals' => 15, 'rate' => 0.07],
            'gold' => ['earnings' => 1000, 'referrals' => 30, 'rate' => 0.10],
            'platinum' => ['earnings' => 2500, 'referrals' => 50, 'rate' => 0.12]
        ];
        
        $current_tier = null;
        foreach ($tiers as $tier_name => $requirements) {
            if ($performance_data->total_earnings >= $requirements['earnings'] && 
                $performance_data->total_referrals >= $requirements['referrals']) {
                $current_tier = [
                    'name' => $tier_name,
                    'rate' => $requirements['rate'],
                    'requirements' => $requirements
                ];
            }
        }
        
        return $current_tier;
    }
    
    /**
     * Apply cross-domain performance bonus
     * @param float $base_rate Base rate
     * @param int $affiliate_id Affiliate ID  
     * @param array $args Additional arguments
     * @return float Enhanced rate
     */
    private function apply_cross_domain_bonus($base_rate, $affiliate_id, $args) {
        global $wpdb;
        
        // Get number of active domains for this affiliate
        $active_domains = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT domain)
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE affiliate_id = %d
            AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND metric_type = 'conversion'
            AND metric_value > 0
        ", $affiliate_id));
        
        // Apply bonus based on cross-domain activity
        $bonus_multiplier = 1.0;
        if ($active_domains >= 5) {
            $bonus_multiplier = 1.15; // 15% bonus for 5+ active domains
        } elseif ($active_domains >= 3) {
            $bonus_multiplier = 1.10; // 10% bonus for 3+ active domains
        } elseif ($active_domains >= 2) {
            $bonus_multiplier = 1.05; // 5% bonus for 2+ active domains
        }
        
        return $base_rate * $bonus_multiplier;
    }
    
    /**
     * Register API endpoints for add-on functionality
     */
    public function register_addon_api_endpoints() {
        // Multi-level affiliates data endpoint
        if (isset($this->active_addons['multi_level_affiliates'])) {
            register_rest_route('affiliate-enhancement/v1', '/multi-level/(?P<affiliate_id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'get_multi_level_data'],
                'permission_callback' => [$this, 'check_affiliate_permissions']
            ]);
        }
        
        // Recurring referrals status endpoint
        if (isset($this->active_addons['recurring_referrals'])) {
            register_rest_route('affiliate-enhancement/v1', '/recurring/(?P<affiliate_id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'get_recurring_data'],
                'permission_callback' => [$this, 'check_affiliate_permissions']
            ]);
        }
        
        // Cross-domain performance summary
        register_rest_route('affiliate-enhancement/v1', '/performance/cross-domain/(?P<affiliate_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cross_domain_performance'],
            'permission_callback' => [$this, 'check_affiliate_permissions']
        ]);
    }
    
    /**
     * Get multi-level affiliate data via API
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function get_multi_level_data($request) {
        $affiliate_id = intval($request['affiliate_id']);
        
        if (!class_exists('Affiliate_WP_Multi_Level_Affiliates')) {
            return new WP_Error('addon_not_active', 'Multi-Level Affiliates add-on is not active', ['status' => 404]);
        }
        
        $multi_level = affiliatewp_multi_level_affiliates();
        
        $data = [
            'affiliate_id' => $affiliate_id,
            'parent_affiliates' => $multi_level->get_parent_affiliates($affiliate_id),
            'child_affiliates' => $this->get_child_affiliates($affiliate_id),
            'level_commissions' => $this->get_level_commission_history($affiliate_id),
            'total_multi_level_earnings' => $this->calculate_total_multi_level_earnings($affiliate_id)
        ];
        
        return rest_ensure_response($data);
    }
    
    /**
     * Get recurring referrals data via API
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function get_recurring_data($request) {
        $affiliate_id = intval($request['affiliate_id']);
        
        if (!class_exists('Affiliate_WP_Recurring_Referrals')) {
            return new WP_Error('addon_not_active', 'Recurring Referrals add-on is not active', ['status' => 404]);
        }
        
        global $wpdb;
        
        $recurring_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.referral_id,
                r.amount,
                r.description,
                r.status,
                r.date,
                rm.meta_value as subscription_id
            FROM {$wpdb->prefix}affiliate_wp_referrals r
            LEFT JOIN {$wpdb->prefix}affiliate_wp_referralmeta rm ON r.referral_id = rm.referral_id 
                AND rm.meta_key = 'subscription_id'
            WHERE r.affiliate_id = %d
            AND r.context LIKE '%recurring%'
            ORDER BY r.date DESC
            LIMIT 50
        ", $affiliate_id));
        
        $summary = [
            'total_recurring_referrals' => count($recurring_data),
            'total_recurring_earnings' => array_sum(array_column($recurring_data, 'amount')),
            'active_subscriptions' => $this->count_active_recurring_subscriptions($affiliate_id),
            'referrals' => $recurring_data
        ];
        
        return rest_ensure_response($summary);
    }
    
    /**
     * Get cross-domain performance data via API
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function get_cross_domain_performance($request) {
        $affiliate_id = intval($request['affiliate_id']);
        
        global $wpdb;
        
        $performance_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                domain,
                SUM(CASE WHEN metric_type = 'earnings' THEN metric_value ELSE 0 END) as total_earnings,
                SUM(CASE WHEN metric_type = 'clicks' THEN metric_value ELSE 0 END) as total_clicks,
                SUM(CASE WHEN metric_type = 'conversions' THEN metric_value ELSE 0 END) as total_conversions,
                AVG(CASE WHEN metric_type = 'conversion_rate' THEN metric_value ELSE NULL END) as avg_conversion_rate
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE affiliate_id = %d
            AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY domain
            ORDER BY total_earnings DESC
        ", $affiliate_id));
        
        $summary = [
            'affiliate_id' => $affiliate_id,
            'total_domains' => count($performance_data),
            'cross_domain_total_earnings' => array_sum(array_column($performance_data, 'total_earnings')),
            'cross_domain_total_clicks' => array_sum(array_column($performance_data, 'total_clicks')),
            'cross_domain_total_conversions' => array_sum(array_column($performance_data, 'total_conversions')),
            'domain_breakdown' => $performance_data,
            'top_performing_domain' => !empty($performance_data) ? $performance_data[0]->domain : null,
            'performance_tier' => $this->get_affiliate_tier($affiliate_id)
        ];
        
        return rest_ensure_response($summary);
    }
    
    /**
     * Check affiliate permissions for API access
     * @param WP_REST_Request $request Request object
     * @return bool Permission check result
     */
    public function check_affiliate_permissions($request) {
        $affiliate_id = intval($request['affiliate_id'] ?? 0);
        $current_user_id = get_current_user_id();
        
        // Check if user is the affiliate owner
        if (function_exists('affwp_get_affiliate_user_id')) {
            $affiliate_user_id = affwp_get_affiliate_user_id($affiliate_id);
            if ($current_user_id === $affiliate_user_id) {
                return true;
            }
        }
        
        // Check if user has admin capabilities
        if (current_user_can('manage_affiliates') || current_user_can('view_affiliate_analytics')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get child affiliates for multi-level structure
     * @param int $affiliate_id Parent affiliate ID
     * @return array Child affiliates
     */
    private function get_child_affiliates($affiliate_id) {
        if (!class_exists('Affiliate_WP_Multi_Level_Affiliates')) {
            return [];
        }
        
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.affiliate_id,
                a.user_id,
                u.user_login,
                u.user_email,
                SUM(r.amount) as total_earnings
            FROM {$wpdb->prefix}affiliate_wp_affiliates a
            LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}affiliate_wp_referrals r ON a.affiliate_id = r.affiliate_id AND r.status = 'paid'
            WHERE a.parent_affiliate_id = %d
            GROUP BY a.affiliate_id
            ORDER BY total_earnings DESC
        ", $affiliate_id));
    }
    
    /**
     * Get level commission history for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Commission history
     */
    private function get_level_commission_history($affiliate_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                mlt.commission_level,
                COUNT(mlt.parent_referral_id) as referral_count,
                SUM(r.amount) as total_commission
            FROM {$wpdb->prefix}affiliate_multi_level_tracking mlt
            JOIN {$wpdb->prefix}affiliate_wp_referrals r ON mlt.parent_referral_id = r.referral_id
            WHERE r.affiliate_id = %d
            AND r.status = 'paid'
            GROUP BY mlt.commission_level
            ORDER BY mlt.commission_level ASC
        ", $affiliate_id));
    }
    
    /**
     * Calculate total multi-level earnings for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return float Total multi-level earnings
     */
    private function calculate_total_multi_level_earnings($affiliate_id) {
        global $wpdb;
        
        $total = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(r.amount)
            FROM {$wpdb->prefix}affiliate_multi_level_tracking mlt
            JOIN {$wpdb->prefix}affiliate_wp_referrals r ON mlt.parent_referral_id = r.referral_id
            WHERE r.affiliate_id = %d
            AND r.status = 'paid'
        ", $affiliate_id));
        
        return floatval($total);
    }
    
    /**
     * Count active recurring subscriptions for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return int Active subscription count
     */
    private function count_active_recurring_subscriptions($affiliate_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT rm.meta_value)
            FROM {$wpdb->prefix}affiliate_wp_referrals r
            JOIN {$wpdb->prefix}affiliate_wp_referralmeta rm ON r.referral_id = rm.referral_id
            WHERE r.affiliate_id = %d
            AND r.context LIKE '%recurring%'
            AND r.status IN ('paid', 'unpaid')
            AND rm.meta_key = 'subscription_id'
            AND rm.meta_value IS NOT NULL
        ", $affiliate_id));
    }
    
    /**
     * Get list of active add-ons
     * @return array Active add-ons
     */
    public function get_active_addons() {
        return $this->active_addons;
    }
    
    /**
     * Check if specific add-on is active and integrated
     * @param string $addon_key Add-on key
     * @return bool Whether add-on is active
     */
    public function is_addon_integrated($addon_key) {
        return isset($this->active_addons[$addon_key]);
    }
    
    /**
     * Get add-on integration status for dashboard display
     * @return array Integration status
     */
    public function get_integration_status() {
        $status = [];
        
        foreach ($this->supported_addons as $addon_key => $addon_config) {
            $status[$addon_key] = [
                'name' => $this->get_addon_friendly_name($addon_key),
                'active' => isset($this->active_addons[$addon_key]),
                'version' => $this->active_addons[$addon_key]['detected_version'] ?? 'N/A',
                'required_version' => $addon_config['version_required'],
                'compatible' => $this->is_version_compatible($addon_key),
                'features' => $this->get_addon_enhanced_features($addon_key)
            ];
        }
        
        return $status;
    }
    
    /**
     * Get friendly name for add-on
     * @param string $addon_key Add-on key
     * @return string Friendly name
     */
    private function get_addon_friendly_name($addon_key) {
        $names = [
            'recurring_referrals' => __('Recurring Referrals', 'affiliate-master-enhancement'),
            'multi_level_affiliates' => __('Multi-Level Affiliates', 'affiliate-master-enhancement'),
            'lifetime_commissions' => __('Lifetime Commissions', 'affiliate-master-enhancement'),
            'store_credit' => __('Store Credit', 'affiliate-master-enhancement'),
            'direct_link_tracking' => __('Direct Link Tracking', 'affiliate-master-enhancement'),
            'custom_affiliate_slugs' => __('Custom Affiliate Slugs', 'affiliate-master-enhancement'),
            'affiliate_landing_pages' => __('Affiliate Landing Pages', 'affiliate-master-enhancement'),
            'pushover_notifications' => __('Pushover Notifications', 'affiliate-master-enhancement'),
            'leaderboard' => __('Leaderboard', 'affiliate-master-enhancement'),
            'affiliate_product_rates' => __('Affiliate Product Rates', 'affiliate-master-enhancement'),
            'tiered_affiliate_rates' => __('Tiered Affiliate Rates', 'affiliate-master-enhancement')
        ];
        
        return $names[$addon_key] ?? ucwords(str_replace('_', ' ', $addon_key));
    }
    
    /**
     * Check if detected version is compatible
     * @param string $addon_key Add-on key
     * @return bool Compatibility status
     */
    private function is_version_compatible($addon_key) {
        if (!isset($this->active_addons[$addon_key])) {
            return false;
        }
        
        $detected_version = $this->active_addons[$addon_key]['detected_version'];
        $required_version = $this->supported_addons[$addon_key]['version_required'];
        
        if ($detected_version === 'unknown') {
            return true; // Assume compatible if version unknown
        }
        
        return version_compare($detected_version, $required_version, '>=');
    }
    
    /**
     * Get enhanced features provided by add-on integration
     * @param string $addon_key Add-on key
     * @return array Enhanced features
     */
    private function get_addon_enhanced_features($addon_key) {
        $features = [
            'recurring_referrals' => [
                __('Cross-domain recurring referral tracking', 'affiliate-master-enhancement'),
                __('Subscription lifecycle management', 'affiliate-master-enhancement'),
                __('Recurring commission projections', 'affiliate-master-enhancement')
            ],
            'multi_level_affiliates' => [
                __('Multi-level commission distribution across domains', 'affiliate-master-enhancement'),
                __('Cross-domain affiliate hierarchy tracking', 'affiliate-master-enhancement'),
                __('Enhanced multi-level analytics', 'affiliate-master-enhancement')
            ],
            'lifetime_commissions' => [
                __('Lifetime value tracking across all domains', 'affiliate-master-enhancement'),
                __('Customer relationship attribution', 'affiliate-master-enhancement'),
                __('Long-term commission forecasting', 'affiliate-master-enhancement')
            ],
            'store_credit' => [
                __('Cross-domain store credit management', 'affiliate-master-enhancement'),
                __('Centralised credit balance tracking', 'affiliate-master-enhancement'),
                __('Multi-site redemption capabilities', 'affiliate-master-enhancement')
            ],
            'direct_link_tracking' => [
                __('Enhanced direct link analytics', 'affiliate-master-enhancement'),
                __('Cross-domain link performance tracking', 'affiliate-master-enhancement'),
                __('Intelligent link routing', 'affiliate-master-enhancement')
            ],
            'custom_affiliate_slugs' => [
                __('Cross-domain slug synchronisation', 'affiliate-master-enhancement'),
                __('Universal slug recognition', 'affiliate-master-enhancement'),
                __('Brand-consistent affiliate URLs', 'affiliate-master-enhancement')
            ],
            'affiliate_landing_pages' => [
                __('Cross-domain landing page performance', 'affiliate-master-enhancement'),
                __('Centralised landing page management', 'affiliate-master-enhancement'),
                __('A/B testing across multiple sites', 'affiliate-master-enhancement')
            ],
            'pushover_notifications' => [
                __('Cross-domain event notifications', 'affiliate-master-enhancement'),
                __('Aggregated performance alerts', 'affiliate-master-enhancement'),
                __('Multi-site activity summaries', 'affiliate-master-enhancement')
            ],
            'leaderboard' => [
                __('Cross-domain affiliate rankings', 'affiliate-master-enhancement'),
                __('Network-wide performance comparison', 'affiliate-master-enhancement'),
                __('Multi-site competition tracking', 'affiliate-master-enhancement')
            ],
            'affiliate_product_rates' => [
                __('Cross-domain product rate management', 'affiliate-master-enhancement'),
                __('Synchronised rate updates', 'affiliate-master-enhancement'),
                __('Product-specific performance tracking', 'affiliate-master-enhancement')
            ],
            'tiered_affiliate_rates' => [
                __('Cross-domain tier calculations', 'affiliate-master-enhancement'),
                __('Network-wide performance tiers', 'affiliate-master-enhancement'),
                __('Automated tier advancement', 'affiliate-master-enhancement')
            ]
        ];
        
        return $features[$addon_key] ?? [];
    }
    
    /**
     * Enhance cross-domain referral data with add-on information
     * @param array $data Referral data
     * @param object $referral Referral object
     * @return array Enhanced data
     */
    public function enhance_cross_domain_data($data, $referral) {
        // Add multi-level information if available
        if (isset($this->active_addons['multi_level_affiliates'])) {
            $data['multi_level_info'] = $this->get_multi_level_info($referral);
        }
        
        // Add recurring referral information
        if (isset($this->active_addons['recurring_referrals'])) {
            $data['recurring_info'] = $this->get_recurring_info($referral);
        }
        
        // Add lifetime commission eligibility
        if (isset($this->active_addons['lifetime_commissions'])) {
            $data['lifetime_eligible'] = $this->is_lifetime_commission_eligible($referral);
        }
        
        // Add tier information
        if (isset($this->active_addons['tiered_affiliate_rates'])) {
            $data['affiliate_tier'] = $this->get_affiliate_tier($referral->affiliate_id);
        }
        
        return $data;
    }
    
    /**
     * Process add-on specific conversion logic
     * @param array $conversion_data Conversion data
     * @param string $source_domain Source domain
     */
    public function process_addon_specific_conversion($conversion_data, $source_domain) {
        // Handle recurring referral setup
        if (isset($this->active_addons['recurring_referrals']) && !empty($conversion_data['subscription_data'])) {
            $this->setup_cross_domain_recurring_tracking($conversion_data, $source_domain);
        }
        
        // Process multi-level commissions
        if (isset($this->active_addons['multi_level_affiliates'])) {
            $this->process_cross_domain_multi_level($conversion_data, $source_domain);
        }
        
        // Update affiliate tiers based on new conversion
        if (isset($this->active_addons['tiered_affiliate_rates'])) {
            $this->update_affiliate_tier_from_conversion($conversion_data);
        }
        
        // Send enhanced notifications
        if (isset($this->active_addons['pushover_notifications'])) {
            $this->send_cross_domain_conversion_notification($conversion_data, $source_domain);
        }
    }
    
    /**
     * Get multi-level information for referral
     * @param object $referral Referral object
     * @return array Multi-level information
     */
    private function get_multi_level_info($referral) {
        if (!class_exists('Affiliate_WP_Multi_Level_Affiliates')) {
            return [];
        }
        
        $multi_level = affiliatewp_multi_level_affiliates();
        
        return [
            'has_parent_affiliates' => !empty($multi_level->get_parent_affiliates($referral->affiliate_id)),
            'parent_affiliates' => $multi_level->get_parent_affiliates($referral->affiliate_id),
            'commission_levels' => $this->get_commission_levels($referral->affiliate_id),
            'is_multi_level_eligible' => $this->is_multi_level_eligible($referral)
        ];
    }
    
    /**
     * Get recurring information for referral
     * @param object $referral Referral object
     * @return array Recurring information
     */
    private function get_recurring_info($referral) {
        return [
            'is_recurring' => strpos($referral->context, 'recurring') !== false,
            'subscription_id' => affwp_get_referral_meta($referral->referral_id, 'subscription_id', true),
            'recurring_amount' => affwp_get_referral_meta($referral->referral_id, 'recurring_amount', true),
            'billing_cycle' => affwp_get_referral_meta($referral->referral_id, 'billing_cycle', true)
        ];
    }
    
    /**
     * Check if referral is eligible for lifetime commissions
     * @param object $referral Referral object
     * @return bool Lifetime eligibility
     */
    private function is_lifetime_commission_eligible($referral) {
        if (!class_exists('AffiliateWP_Lifetime_Commissions')) {
            return false;
        }
        
        // Check if customer is already linked to this affiliate
        $customer_id = $this->get_customer_id_from_referral($referral);
        if (!$customer_id) {
            return false;
        }
        
        return affiliatewp_lifetime_commissions()->is_customer_linked($customer_id, $referral->affiliate_id);
    }
    
    /**
     * Setup cross-domain recurring tracking
     * @param array $conversion_data Conversion data
     * @param string $source_domain Source domain
     */
    private function setup_cross_domain_recurring_tracking($conversion_data, $source_domain) {
        if (!isset($conversion_data['subscription_data']) || !isset($conversion_data['referral_id'])) {
            return;
        }
        
        $referral_id = $conversion_data['referral_id'];
        $subscription_data = $conversion_data['subscription_data'];
        
        // Store cross-domain subscription metadata
        affwp_update_referral_meta($referral_id, 'cross_domain_subscription', true);
        affwp_update_referral_meta($referral_id, 'subscription_source_domain', $source_domain);
        affwp_update_referral_meta($referral_id, 'subscription_id', $subscription_data['subscription_id']);
        affwp_update_referral_meta($referral_id, 'billing_cycle', $subscription_data['billing_cycle']);
        affwp_update_referral_meta($referral_id, 'recurring_amount', $subscription_data['recurring_amount']);
        
        // Schedule recurring referral creation
        wp_schedule_single_event(
            strtotime($subscription_data['next_payment_date']),
            'ame_create_cross_domain_recurring_referral',
            [$referral_id, $subscription_data]
        );
    }
    
    /**
     * Process cross-domain multi-level commissions
     * @param array $conversion_data Conversion data
     * @param string $source_domain Source domain
     */
    private function process_cross_domain_multi_level($conversion_data, $source_domain) {
        if (!isset($conversion_data['referral_id'])) {
            return;
        }
        
        $referral = affwp_get_referral($conversion_data['referral_id']);
        if (!$referral) {
            return;
        }
        
        // Add cross-domain metadata to referral
        affwp_update_referral_meta($referral->referral_id, 'cross_domain_source', $source_domain);
        
        // Process multi-level commissions with cross-domain tracking
        $this->process_multi_level_referral($referral);
    }
    
    /**
     * Update affiliate tier based on new conversion
     * @param array $conversion_data Conversion data
     */
    private function update_affiliate_tier_from_conversion($conversion_data) {
        if (!isset($conversion_data['affiliate_id'])) {
            return;
        }
        
        $affiliate_id = $conversion_data['affiliate_id'];
        $current_tier = $this->get_affiliate_tier($affiliate_id);
        
        // Check if affiliate qualifies for tier upgrade
        $new_tier = $this->calculate_new_tier($affiliate_id, $conversion_data);
        
        if ($new_tier && (!$current_tier || $new_tier['name'] !== $current_tier['name'])) {
            $this->update_affiliate_tier($affiliate_id, $new_tier);
            
            // Send tier upgrade notification if applicable
            if ($current_tier && $new_tier['rate'] > $current_tier['rate']) {
                $this->send_tier_upgrade_notification($affiliate_id, $current_tier, $new_tier);
            }
        }
    }
    
    /**
     * Calculate new tier for affiliate based on performance
     * @param int $affiliate_id Affiliate ID
     * @param array $conversion_data Recent conversion data
     * @return array|null New tier information
     */
    private function calculate_new_tier($affiliate_id, $conversion_data) {
        // This would implement the tier calculation logic
        // For now, returning null to indicate no tier change
        return null;
    }
    
    /**
     * Update affiliate tier in database
     * @param int $affiliate_id Affiliate ID
     * @param array $new_tier New tier information
     */
    private function update_affiliate_tier($affiliate_id, $new_tier) {
        // Store tier information in affiliate meta
        affwp_update_affiliate_meta($affiliate_id, 'performance_tier', $new_tier['name']);
        affwp_update_affiliate_meta($affiliate_id, 'tier_rate', $new_tier['rate']);
        affwp_update_affiliate_meta($affiliate_id, 'tier_updated', current_time('mysql'));
        
        // Log tier change for audit
        error_log("AME: Affiliate {$affiliate_id} tier updated to {$new_tier['name']} with rate {$new_tier['rate']}");
    }
    
    /**
     * Send cross-domain conversion notification
     * @param array $conversion_data Conversion data
     * @param string $source_domain Source domain
     */
    private function send_cross_domain_conversion_notification($conversion_data, $source_domain) {
        if (!class_exists('AffiliateWP_Pushover_Notifications')) {
            return;
        }
        
        $message = sprintf(
            __('New cross-domain conversion from %s: %s commission earned', 'affiliate-master-enhancement'),
            $source_domain,
            affwp_currency_filter($conversion_data['commission_amount'] ?? 0)
        );
        
        $pushover = affiliatewp_pushover_notifications();
        $pushover->send_notification($conversion_data['affiliate_id'], $message, [
            'title' => __('Cross-Domain Conversion', 'affiliate-master-enhancement'),
            'url' => admin_url('admin.php?page=affiliate-wp-referrals&referral=' . ($conversion_data['referral_id'] ?? 0))
        ]);
    }
    
    /**
     * Send tier upgrade notification
     * @param int $affiliate_id Affiliate ID
     * @param array $old_tier Previous tier
     * @param array $new_tier New tier
     */
    private function send_tier_upgrade_notification($affiliate_id, $old_tier, $new_tier) {
        if (!class_exists('AffiliateWP_Pushover_Notifications')) {
            return;
        }
        
        $message = sprintf(
            __('Congratulations! You\'ve been upgraded from %s to %s tier. Your new commission rate is %s%%', 'affiliate-master-enhancement'),
            $old_tier['name'],
            $new_tier['name'],
            number_format($new_tier['rate'] * 100, 1)
        );
        
        $pushover = affiliatewp_pushover_notifications();
        $pushover->send_notification($affiliate_id, $message, [
            'title' => __('Tier Upgrade!', 'affiliate-master-enhancement'),
            'priority' => 1 // High priority notification
        ]);
    }
    
    /**
     * Get commission levels for multi-level affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Commission levels
     */
    private function get_commission_levels($affiliate_id) {
        if (!class_exists('Affiliate_WP_Multi_Level_Affiliates')) {
            return [];
        }
        
        $multi_level = affiliatewp_multi_level_affiliates();
        $levels = [];
        
        for ($level = 1; $level <= 5; $level++) { // Assuming max 5 levels
            $rate = $multi_level->get_level_rate($level, $affiliate_id);
            if ($rate > 0) {
                $levels[$level] = [
                    'level' => $level,
                    'rate' => $rate,
                    'rate_type' => 'percentage' // Could be extended to support different rate types
                ];
            }
        }
        
        return $levels;
    }
    
    /**
     * Check if referral is eligible for multi-level commissions
     * @param object $referral Referral object
     * @return bool Multi-level eligibility
     */
    private function is_multi_level_eligible($referral) {
        if (!class_exists('Affiliate_WP_Multi_Level_Affiliates')) {
            return false;
        }
        
        $multi_level = affiliatewp_multi_level_affiliates();
        $parent_affiliates = $multi_level->get_parent_affiliates($referral->affiliate_id);
        
        return !empty($parent_affiliates);
    }
    
    /**
     * Get customer ID from referral for lifetime commission tracking
     * @param object $referral Referral object
     * @return int|null Customer ID
     */
    private function get_customer_id_from_referral($referral) {
        // This would need to be implemented based on the specific e-commerce integration
        // For now, return null
        return null;
    }
    
    /**
     * Create database table for multi-level tracking if not exists
     */
    public static function create_multi_level_tracking_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_multi_level_tracking';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            child_referral_id bigint(20) UNSIGNED NOT NULL,
            parent_referral_id bigint(20) UNSIGNED NOT NULL,
            commission_level tinyint(2) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY child_referral_index (child_referral_id),
            KEY parent_referral_index (parent_referral_id),
            KEY commission_level_index (commission_level)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

// Create multi-level tracking table on activation
register_activation_hook(AME_PLUGIN_FILE, ['AffiliateWPEcosystemIntegration', 'create_multi_level_tracking_table']);