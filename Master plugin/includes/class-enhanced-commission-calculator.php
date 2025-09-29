<?php
/**
 * Enhanced Commission Calculator Class
 * 
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-enhanced-commission-calculator.php
 * Plugin: Affiliate Client Integration (Satellite)
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * Version: 1.0.0
 * 
 * Handles commission calculations with enhanced features including tiered rates,
 * performance bonuses, time-based adjustments, and multi-currency support.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied');
}

class ACI_Enhanced_Commission_Calculator {

    /**
     * Plugin instance reference
     * @var object
     */
    private $plugin;

    /**
     * Database manager instance
     * @var ACI_Database_Manager
     */
    private $db_manager;

    /**
     * Settings manager instance  
     * @var ACI_Settings_Manager
     */
    private $settings;

    /**
     * Commission rules cache
     * @var array
     */
    private $commission_rules = [];

    /**
     * Performance metrics cache
     * @var array
     */
    private $performance_cache = [];

    /**
     * Currency rates cache
     * @var array
     */
    private $currency_rates = [];

    /**
     * Default commission structure
     * @var array
     */
    private $default_structure = [
        'base_rate' => 0.10,
        'max_rate' => 0.25,
        'min_threshold' => 100.00,
        'currency' => 'USD',
        'calculation_method' => 'percentage'
    ];

    /**
     * Constructor
     * 
     * @param object $plugin Main plugin instance
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->settings = $plugin->get_settings();
        
        // Initialize database manager if available
        if (method_exists($plugin, 'get_database_manager')) {
            $this->db_manager = $plugin->get_database_manager();
        }

        // Load commission rules and performance data
        $this->load_commission_rules();
        $this->load_currency_rates();
        
        // Register hooks
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_aci_calculate_commission', [$this, 'ajax_calculate_commission']);
        add_action('wp_ajax_aci_update_commission_rules', [$this, 'ajax_update_commission_rules']);
        add_filter('aci_commission_amount', [$this, 'apply_performance_bonuses'], 10, 3);
        add_filter('aci_commission_amount', [$this, 'apply_time_adjustments'], 15, 3);
    }

    /**
     * Initialize calculator
     */
    public function init() {
        // Validate required dependencies
        if (!$this->validate_dependencies()) {
            return false;
        }

        // Schedule performance metrics update
        if (!wp_next_scheduled('aci_update_performance_metrics')) {
            wp_schedule_event(time(), 'hourly', 'aci_update_performance_metrics');
        }
        
        // Schedule currency rates update
        if (!wp_next_scheduled('aci_update_currency_rates')) {
            wp_schedule_event(time(), 'daily', 'aci_update_currency_rates');
        }

        return true;
    }

    /**
     * Validate required dependencies
     * 
     * @return bool Validation result
     */
    private function validate_dependencies() {
        $required_classes = [
            'ACI_Session_Manager',
            'ACI_API_Client',
            'ACI_Price_Calculator'
        ];

        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                error_log("ACI Enhanced Commission Calculator: Missing required class {$class}");
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate commission amount with enhanced features
     * 
     * @param float $sale_amount Sale amount
     * @param string $affiliate_code Affiliate code
     * @param array $options Calculation options
     * @return array Commission calculation result
     */
    public function calculate_commission($sale_amount, $affiliate_code, $options = []) {
        $default_options = [
            'currency' => 'USD',
            'apply_bonuses' => true,
            'apply_time_adjustments' => true,
            'include_breakdown' => true,
            'validate_thresholds' => true
        ];
        
        $options = array_merge($default_options, $options);
        
        // Input validation and sanitisation
        $sale_amount = $this->sanitise_amount($sale_amount);
        $affiliate_code = $this->sanitise_affiliate_code($affiliate_code);
        
        if ($sale_amount <= 0) {
            return $this->build_error_response('Invalid sale amount');
        }
        
        if (empty($affiliate_code)) {
            return $this->build_error_response('Invalid affiliate code');
        }

        try {
            // Get affiliate data and commission rules
            $affiliate_data = $this->get_affiliate_data($affiliate_code);
            if (!$affiliate_data) {
                return $this->build_error_response('Affiliate not found');
            }

            // Get applicable commission rules
            $rules = $this->get_commission_rules($affiliate_code, $affiliate_data);
            
            // Validate minimum thresholds
            if ($options['validate_thresholds'] && !$this->meets_minimum_threshold($sale_amount, $rules)) {
                return $this->build_error_response('Sale amount below minimum threshold');
            }

            // Calculate base commission
            $base_commission = $this->calculate_base_commission($sale_amount, $rules, $options['currency']);
            
            // Apply performance bonuses
            $commission_with_bonuses = $base_commission;
            if ($options['apply_bonuses']) {
                $commission_with_bonuses = $this->apply_performance_bonuses_calculation(
                    $base_commission, 
                    $affiliate_data, 
                    $sale_amount
                );
            }

            // Apply time-based adjustments
            $final_commission = $commission_with_bonuses;
            if ($options['apply_time_adjustments']) {
                $final_commission = $this->apply_time_adjustments_calculation(
                    $commission_with_bonuses, 
                    $affiliate_data, 
                    $sale_amount
                );
            }

            // Build response
            $response = [
                'success' => true,
                'commission_amount' => $final_commission,
                'currency' => $options['currency'],
                'affiliate_code' => $affiliate_code,
                'sale_amount' => $sale_amount,
                'effective_rate' => $this->calculate_effective_rate($final_commission, $sale_amount),
                'calculated_at' => current_time('c')
            ];

            // Include detailed breakdown if requested
            if ($options['include_breakdown']) {
                $response['breakdown'] = [
                    'base_commission' => $base_commission,
                    'performance_bonus' => $commission_with_bonuses - $base_commission,
                    'time_adjustment' => $final_commission - $commission_with_bonuses,
                    'rules_applied' => $this->get_applied_rules_summary($rules),
                    'calculation_method' => $rules['calculation_method'] ?? 'percentage'
                ];
            }

            // Log successful calculation
            $this->log_commission_calculation($response, $affiliate_data);
            
            return $response;
            
        } catch (Exception $e) {
            error_log("ACI Commission Calculator Error: " . $e->getMessage());
            return $this->build_error_response('Calculation error occurred');
        }
    }

    /**
     * Calculate base commission using configured rules
     * 
     * @param float $sale_amount Sale amount
     * @param array $rules Commission rules
     * @param string $currency Target currency
     * @return float Base commission amount
     */
    private function calculate_base_commission($sale_amount, $rules, $currency) {
        // Convert amount to calculation currency if needed
        $calculation_amount = $this->convert_currency($sale_amount, $currency, $rules['currency'] ?? 'USD');
        
        $commission = 0;
        
        switch ($rules['calculation_method'] ?? 'percentage') {
            case 'percentage':
                $commission = $calculation_amount * ($rules['base_rate'] ?? $this->default_structure['base_rate']);
                break;
                
            case 'tiered':
                $commission = $this->calculate_tiered_commission($calculation_amount, $rules['tiers'] ?? []);
                break;
                
            case 'flat':
                $commission = $rules['flat_amount'] ?? 0;
                break;
                
            case 'progressive':
                $commission = $this->calculate_progressive_commission($calculation_amount, $rules);
                break;
                
            default:
                $commission = $calculation_amount * $this->default_structure['base_rate'];
        }

        // Apply maximum commission cap
        $max_commission = $rules['max_commission'] ?? PHP_FLOAT_MAX;
        $commission = min($commission, $max_commission);
        
        // Convert back to target currency
        return $this->convert_currency($commission, $rules['currency'] ?? 'USD', $currency);
    }

    /**
     * Calculate tiered commission based on sale amount
     * 
     * @param float $amount Sale amount
     * @param array $tiers Commission tiers
     * @return float Commission amount
     */
    private function calculate_tiered_commission($amount, $tiers) {
        if (empty($tiers)) {
            return $amount * $this->default_structure['base_rate'];
        }
        
        // Sort tiers by minimum amount
        usort($tiers, function($a, $b) {
            return ($a['min_amount'] ?? 0) <=> ($b['min_amount'] ?? 0);
        });
        
        $commission = 0;
        $remaining_amount = $amount;
        
        foreach ($tiers as $tier) {
            $tier_min = $tier['min_amount'] ?? 0;
            $tier_max = $tier['max_amount'] ?? PHP_FLOAT_MAX;
            $tier_rate = $tier['rate'] ?? $this->default_structure['base_rate'];
            
            if ($remaining_amount <= 0) {
                break;
            }
            
            if ($amount >= $tier_min) {
                $tier_applicable_amount = min($remaining_amount, $tier_max - $tier_min);
                $commission += $tier_applicable_amount * $tier_rate;
                $remaining_amount -= $tier_applicable_amount;
            }
        }
        
        return $commission;
    }

    /**
     * Calculate progressive commission with escalating rates
     * 
     * @param float $amount Sale amount
     * @param array $rules Progressive rules
     * @return float Commission amount
     */
    private function calculate_progressive_commission($amount, $rules) {
        $base_rate = $rules['base_rate'] ?? $this->default_structure['base_rate'];
        $progression_factor = $rules['progression_factor'] ?? 0.01;
        $progression_cap = $rules['progression_cap'] ?? $this->default_structure['max_rate'];
        
        // Calculate progressive rate based on amount
        $progressive_multiplier = min(($amount / 1000) * $progression_factor, $progression_cap - $base_rate);
        $effective_rate = $base_rate + $progressive_multiplier;
        
        return $amount * $effective_rate;
    }

    /**
     * Apply performance bonuses to commission
     * 
     * @param float $base_commission Base commission amount
     * @param array $affiliate_data Affiliate performance data
     * @param float $sale_amount Sale amount
     * @return float Commission with bonuses
     */
    private function apply_performance_bonuses_calculation($base_commission, $affiliate_data, $sale_amount) {
        $performance_metrics = $this->get_performance_metrics($affiliate_data['id'] ?? 0);
        
        if (empty($performance_metrics)) {
            return $base_commission;
        }
        
        $bonus_multiplier = 1.0;
        $bonus_rules = $this->settings->get('performance_bonus_rules', []);
        
        // Volume bonus
        if (isset($bonus_rules['volume']) && isset($performance_metrics['monthly_volume'])) {
            $volume_tiers = $bonus_rules['volume']['tiers'] ?? [];
            foreach ($volume_tiers as $tier) {
                if ($performance_metrics['monthly_volume'] >= ($tier['min_volume'] ?? 0)) {
                    $bonus_multiplier += $tier['bonus_rate'] ?? 0;
                }
            }
        }
        
        // Conversion rate bonus
        if (isset($bonus_rules['conversion_rate']) && isset($performance_metrics['conversion_rate'])) {
            $conversion_threshold = $bonus_rules['conversion_rate']['threshold'] ?? 0.05;
            $conversion_bonus = $bonus_rules['conversion_rate']['bonus_rate'] ?? 0.10;
            
            if ($performance_metrics['conversion_rate'] >= $conversion_threshold) {
                $bonus_multiplier += $conversion_bonus;
            }
        }
        
        // Consistency bonus
        if (isset($bonus_rules['consistency']) && isset($performance_metrics['consistency_score'])) {
            $consistency_threshold = $bonus_rules['consistency']['threshold'] ?? 0.8;
            $consistency_bonus = $bonus_rules['consistency']['bonus_rate'] ?? 0.05;
            
            if ($performance_metrics['consistency_score'] >= $consistency_threshold) {
                $bonus_multiplier += $consistency_bonus;
            }
        }
        
        // Cap maximum bonus multiplier
        $max_multiplier = $bonus_rules['max_multiplier'] ?? 2.0;
        $bonus_multiplier = min($bonus_multiplier, $max_multiplier);
        
        return $base_commission * $bonus_multiplier;
    }

    /**
     * Apply time-based adjustments to commission
     * 
     * @param float $commission Commission amount
     * @param array $affiliate_data Affiliate data
     * @param float $sale_amount Sale amount
     * @return float Adjusted commission
     */
    private function apply_time_adjustments_calculation($commission, $affiliate_data, $sale_amount) {
        $time_rules = $this->settings->get('time_adjustment_rules', []);
        
        if (empty($time_rules)) {
            return $commission;
        }
        
        $adjustment_factor = 1.0;
        $current_time = current_time('timestamp');
        
        // Seasonal adjustments
        if (isset($time_rules['seasonal'])) {
            $month = date('n', $current_time);
            $seasonal_multipliers = $time_rules['seasonal']['multipliers'] ?? [];
            
            if (isset($seasonal_multipliers[$month])) {
                $adjustment_factor *= $seasonal_multipliers[$month];
            }
        }
        
        // Time-of-day adjustments
        if (isset($time_rules['hourly'])) {
            $hour = date('G', $current_time);
            $hourly_rules = $time_rules['hourly'];
            
            foreach ($hourly_rules as $rule) {
                $start_hour = $rule['start_hour'] ?? 0;
                $end_hour = $rule['end_hour'] ?? 23;
                
                if ($hour >= $start_hour && $hour <= $end_hour) {
                    $adjustment_factor *= $rule['multiplier'] ?? 1.0;
                    break;
                }
            }
        }
        
        // Day-of-week adjustments
        if (isset($time_rules['daily'])) {
            $day_of_week = date('N', $current_time);
            $daily_multipliers = $time_rules['daily']['multipliers'] ?? [];
            
            if (isset($daily_multipliers[$day_of_week])) {
                $adjustment_factor *= $daily_multipliers[$day_of_week];
            }
        }
        
        // New affiliate boost
        if (isset($time_rules['new_affiliate_boost'])) {
            $affiliate_start_date = strtotime($affiliate_data['date_registered'] ?? '');
            $boost_period_days = $time_rules['new_affiliate_boost']['period_days'] ?? 30;
            $boost_multiplier = $time_rules['new_affiliate_boost']['multiplier'] ?? 1.2;
            
            if ($affiliate_start_date && ($current_time - $affiliate_start_date) <= ($boost_period_days * DAY_IN_SECONDS)) {
                $adjustment_factor *= $boost_multiplier;
            }
        }
        
        // Cap adjustment factor
        $min_factor = $time_rules['min_adjustment_factor'] ?? 0.5;
        $max_factor = $time_rules['max_adjustment_factor'] ?? 2.0;
        $adjustment_factor = max($min_factor, min($max_factor, $adjustment_factor));
        
        return $commission * $adjustment_factor;
    }

    /**
     * Get commission rules for specific affiliate
     * 
     * @param string $affiliate_code Affiliate code
     * @param array $affiliate_data Affiliate data
     * @return array Commission rules
     */
    private function get_commission_rules($affiliate_code, $affiliate_data) {
        $cache_key = "commission_rules_{$affiliate_code}";
        
        if (isset($this->commission_rules[$cache_key])) {
            return $this->commission_rules[$cache_key];
        }
        
        // Start with default structure
        $rules = $this->default_structure;
        
        // Apply global settings overrides
        $global_settings = $this->settings->get('commission_settings', []);
        $rules = array_merge($rules, $global_settings);
        
        // Apply affiliate-specific rules if they exist
        $affiliate_rules = $this->get_affiliate_specific_rules($affiliate_data);
        if (!empty($affiliate_rules)) {
            $rules = array_merge($rules, $affiliate_rules);
        }
        
        // Apply user group rules
        $user_group_rules = $this->get_user_group_rules($affiliate_data);
        if (!empty($user_group_rules)) {
            $rules = array_merge($rules, $user_group_rules);
        }
        
        // Cache the rules
        $this->commission_rules[$cache_key] = $rules;
        
        return $rules;
    }

    /**
     * Get performance metrics for affiliate
     * 
     * @param int $affiliate_id Affiliate ID
     * @return array Performance metrics
     */
    private function get_performance_metrics($affiliate_id) {
        $cache_key = "performance_{$affiliate_id}";
        
        if (isset($this->performance_cache[$cache_key])) {
            return $this->performance_cache[$cache_key];
        }
        
        if (!$this->db_manager) {
            return [];
        }
        
        global $wpdb;
        
        // Get 30-day metrics
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(sale_amount) as monthly_volume,
                SUM(commission_amount) as monthly_commissions,
                AVG(conversion_rate) as avg_conversion_rate,
                STDDEV(conversion_rate) as conversion_consistency
            FROM {$wpdb->prefix}aci_commission_logs 
            WHERE affiliate_id = %d 
            AND created_at >= %s
        ", $affiliate_id, $thirty_days_ago), ARRAY_A);
        
        if (!$metrics) {
            return [];
        }
        
        // Calculate consistency score
        $consistency_score = 0;
        if ($metrics['avg_conversion_rate'] > 0 && $metrics['conversion_consistency'] > 0) {
            $consistency_score = max(0, 1 - ($metrics['conversion_consistency'] / $metrics['avg_conversion_rate']));
        }
        
        $processed_metrics = [
            'monthly_volume' => floatval($metrics['monthly_volume'] ?? 0),
            'monthly_transactions' => intval($metrics['total_transactions'] ?? 0),
            'monthly_commissions' => floatval($metrics['monthly_commissions'] ?? 0),
            'conversion_rate' => floatval($metrics['avg_conversion_rate'] ?? 0),
            'consistency_score' => $consistency_score,
            'calculated_at' => current_time('c')
        ];
        
        // Cache for 1 hour
        $this->performance_cache[$cache_key] = $processed_metrics;
        
        return $processed_metrics;
    }

    /**
     * Convert currency amounts
     * 
     * @param float $amount Amount to convert
     * @param string $from_currency Source currency
     * @param string $to_currency Target currency
     * @return float Converted amount
     */
    private function convert_currency($amount, $from_currency, $to_currency) {
        if ($from_currency === $to_currency) {
            return $amount;
        }
        
        $rate = $this->get_exchange_rate($from_currency, $to_currency);
        return $amount * $rate;
    }

    /**
     * Get exchange rate between currencies
     * 
     * @param string $from_currency Source currency
     * @param string $to_currency Target currency  
     * @return float Exchange rate
     */
    private function get_exchange_rate($from_currency, $to_currency) {
        $rate_key = "{$from_currency}_{$to_currency}";
        
        if (isset($this->currency_rates[$rate_key])) {
            return $this->currency_rates[$rate_key];
        }
        
        // Default to 1.0 if no rate available
        return 1.0;
    }

    /**
     * Load commission rules from settings
     */
    private function load_commission_rules() {
        $this->commission_rules = get_option('aci_commission_rules_cache', []);
    }

    /**
     * Load currency exchange rates
     */
    private function load_currency_rates() {
        $this->currency_rates = get_option('aci_currency_rates_cache', [
            'USD_USD' => 1.0,
            'USD_EUR' => 0.85,
            'USD_GBP' => 0.73,
            'EUR_USD' => 1.18,
            'GBP_USD' => 1.37
        ]);
    }

    /**
     * Sanitise sale amount input
     * 
     * @param mixed $amount Input amount
     * @return float Sanitised amount
     */
    private function sanitise_amount($amount) {
        return max(0, floatval(preg_replace('/[^0-9.]/', '', strval($amount))));
    }

    /**
     * Sanitise affiliate code input
     * 
     * @param mixed $code Input code
     * @return string Sanitised code
     */
    private function sanitise_affiliate_code($code) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', strval($code));
    }

    /**
     * Build error response structure
     * 
     * @param string $message Error message
     * @return array Error response
     */
    private function build_error_response($message) {
        return [
            'success' => false,
            'error' => $message,
            'commission_amount' => 0,
            'calculated_at' => current_time('c')
        ];
    }

    /**
     * AJAX handler for commission calculation
     */
    public function ajax_calculate_commission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aci_calculate_commission')) {
            wp_die(json_encode(['error' => 'Invalid nonce']), 403);
        }
        
        // Extract parameters
        $sale_amount = $_POST['sale_amount'] ?? 0;
        $affiliate_code = $_POST['affiliate_code'] ?? '';
        $options = $_POST['options'] ?? [];
        
        // Calculate commission
        $result = $this->calculate_commission($sale_amount, $affiliate_code, $options);
        
        wp_send_json($result);
    }

    /**
     * Get affiliate data from master plugin
     * 
     * @param string $affiliate_code Affiliate code
     * @return array|null Affiliate data
     */
    private function get_affiliate_data($affiliate_code) {
        // This would typically call the master plugin API
        if (class_exists('ACI_API_Client')) {
            $api_client = new ACI_API_Client();
            return $api_client->get_affiliate_by_code($affiliate_code);
        }
        
        return null;
    }

    /**
     * Check if sale meets minimum threshold
     * 
     * @param float $sale_amount Sale amount
     * @param array $rules Commission rules
     * @return bool Whether threshold is met
     */
    private function meets_minimum_threshold($sale_amount, $rules) {
        $min_threshold = $rules['min_threshold'] ?? $this->default_structure['min_threshold'];
        return $sale_amount >= $min_threshold;
    }

    /**
     * Calculate effective commission rate
     * 
     * @param float $commission Commission amount
     * @param float $sale_amount Sale amount
     * @return float Effective rate as percentage
     */
    private function calculate_effective_rate($commission, $sale_amount) {
        if ($sale_amount <= 0) {
            return 0;
        }
        return round(($commission / $sale_amount) * 100, 4);
    }

    /**
     * Get applied rules summary
     * 
     * @param array $rules Applied rules
     * @return array Rules summary
     */
    private function get_applied_rules_summary($rules) {
        return [
            'calculation_method' => $rules['calculation_method'] ?? 'percentage',
            'base_rate' => $rules['base_rate'] ?? 0,
            'tiers_count' => isset($rules['tiers']) ? count($rules['tiers']) : 0,
            'max_commission' => $rules['max_commission'] ?? null
        ];
    }

    /**
     * Log commission calculation for audit trail
     * 
     * @param array $response Calculation response
     * @param array $affiliate_data Affiliate data
     */
    private function log_commission_calculation($response, $affiliate_data) {
        if (!$this->db_manager) {
            return;
        }
        
        global $wpdb;
        
        $log_data = [
            'affiliate_id' => $affiliate_data['id'] ?? 0,
            'affiliate_code' => $response['affiliate_code'],
            'sale_amount' => $response['sale_amount'],
            'commission_amount' => $response['commission_amount'],
            'effective_rate' => $response['effective_rate'],
            'currency' => $response['currency'],
            'calculation_data' => json_encode($response['breakdown'] ?? []),
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert(
            $wpdb->prefix . 'aci_commission_logs',
            $log_data,
            ['%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s']
        );
    }

    /**
     * Get affiliate-specific commission rules
     * 
     * @param array $affiliate_data Affiliate data
     * @return array Affiliate-specific rules
     */
    private function get_affiliate_specific_rules($affiliate_data) {
        // Implementation would query affiliate-specific overrides
        return [];
    }

    /**
     * Get user group-based commission rules
     * 
     * @param array $affiliate_data Affiliate data
     * @return array User group rules
     */
    private function get_user_group_rules($affiliate_data) {
        // Implementation would apply group-based rules
        return [];
    }
}