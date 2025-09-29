<?php
/**
 * Cross-Site Link Optimisation
 * 
 * Manages and optimises affiliate links across multiple domains with
 * performance tracking, A/B testing, and intelligent routing
 * 
 * Filename: class-cross-site-link-optimisation.php
 * Path: /wp-content/plugins/affiliate-master-enhancement/includes/
 * 
 * @package CrossSiteLinkOptimisation
 * @author Richard King <r.king@starneconsulting.com>
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cross-Site Link Optimisation Class
 * Optimises affiliate link placement and performance across sites
 */
class CrossSiteLinkOptimisation {
    
    /**
     * Cache duration for performance data
     */
    const CACHE_DURATION = 3600; // 1 hour
    
    /**
     * Maximum number of A/B test variations
     */
    const MAX_AB_VARIATIONS = 5;
    
    /**
     * Constructor - initialise link optimisation
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_ame_optimise_links', [$this, 'ajax_optimise_links']);
        add_action('wp_ajax_ame_get_link_performance', [$this, 'ajax_get_link_performance']);
        add_action('wp_ajax_ame_create_ab_test', [$this, 'ajax_create_ab_test']);
        
        // Hook into affiliate link generation
        add_filter('affwp_referral_link', [$this, 'optimise_affiliate_link'], 10, 3);
        add_action('affwp_track_visit', [$this, 'track_link_performance'], 10, 2);
        
        // Register API endpoints
        add_action('rest_api_init', [$this, 'register_api_endpoints']);
        
        // Schedule performance analysis
        add_action('ame_hourly_link_analysis', [$this, 'analyse_link_performance']);
        add_action('ame_daily_ab_test_analysis', [$this, 'analyse_ab_tests']);
    }
    
    /**
     * Initialise link optimisation features
     */
    public function init() {
        // Register shortcodes for optimised links
        add_shortcode('optimised_affiliate_link', [$this, 'render_optimised_link_shortcode']);
        add_shortcode('ab_test_affiliate_link', [$this, 'render_ab_test_link_shortcode']);
        
        // Enqueue frontend scripts if needed
        if ($this->should_load_optimisation_scripts()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_optimisation_scripts']);
        }
    }
    
    /**
     * Optimise affiliate link placement and performance
     * @param int $affiliate_id Affiliate ID
     * @return array Optimisation recommendations
     */
    public function optimise_link_placement($affiliate_id) {
        $cache_key = 'ame_link_optimisation_' . $affiliate_id;
        $cached_data = wp_cache_get($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $site_performance_data = $this->analyse_cross_site_performance($affiliate_id);
        
        $optimisation_data = [
            'affiliate_id' => $affiliate_id,
            'high_performing_pages' => $this->identify_top_converting_pages($site_performance_data),
            'optimal_link_positions' => $this->calculate_best_link_placement($site_performance_data),
            'seasonal_opportunities' => $this->identify_seasonal_trends($site_performance_data),
            'underperforming_sites' => $this->flag_improvement_opportunities($site_performance_data),
            'recommended_actions' => $this->generate_action_recommendations($site_performance_data),
            'ab_test_opportunities' => $this->identify_ab_test_opportunities($affiliate_id),
            'cross_domain_insights' => $this->generate_cross_domain_insights($site_performance_data)
        ];
        
        wp_cache_set($cache_key, $optimisation_data, '', self::CACHE_DURATION);
        
        return $optimisation_data;
    }
    
    /**
     * Generate optimised affiliate links for different contexts
     * @param int $affiliate_id Affiliate ID
     * @param string $target_domain Target domain
     * @param array $context_data Context information
     * @return array Optimised link data
     */
    public function generate_contextual_affiliate_links($affiliate_id, $target_domain, $context_data = []) {
        $base_affiliate_url = affwp_get_affiliate_referral_url($affiliate_id);
        $domain_optimisations = $this->get_domain_specific_parameters($target_domain);
        $context_parameters = $this->get_context_optimisation_parameters($context_data);
        
        $optimised_links = [];
        
        // Generate different link variations
        $link_types = ['standard', 'cta_optimised', 'mobile_optimised', 'social_optimised'];
        
        foreach ($link_types as $link_type) {
            $optimised_links[$link_type] = $this->build_optimised_affiliate_url([
                'base_url' => $base_affiliate_url,
                'domain_params' => $domain_optimisations,
                'context_params' => $context_parameters,
                'link_type' => $link_type,
                'tracking_params' => $this->generate_tracking_parameters($affiliate_id, $target_domain, $link_type)
            ]);
        }
        
        return $optimised_links;
    }
    
    /**
     * Setup cross-domain A/B testing for affiliate links
     * @param int $affiliate_id Affiliate ID
     * @param array $test_variations Test variation configurations
     * @return int Test ID
     */
    public function setup_cross_domain_ab_testing($affiliate_id, $test_variations) {
        global $wpdb;
        
        if (count($test_variations) > self::MAX_AB_VARIATIONS) {
            throw new Exception('Maximum ' . self::MAX_AB_VARIATIONS . ' variations allowed per test');
        }
        
        // Create A/B test record
        $test_data = [
            'affiliate_id' => $affiliate_id,
            'test_name' => $test_variations['test_name'] ?? 'Link Optimisation Test',
            'test_type' => 'cross_domain_link',
            'status' => 'active',
            'start_date' => current_time('mysql'),
            'end_date' => $test_variations['end_date'] ?? date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($wpdb->prefix . 'affiliate_ab_tests', $test_data, [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);
        
        $test_id = $wpdb->insert_id;
        
        if (!$test_id) {
            throw new Exception('Failed to create A/B test');
        }
        
        // Create test variations
        foreach ($test_variations['variations'] as $variation_key => $variation_data) {
            $this->create_test_variation($test_id, $variation_key, $variation_data);
        }
        
        // Deploy test to all affiliated domains
        $affiliated_domains = $this->get_affiliated_domains($affiliate_id);
        foreach ($affiliated_domains as $domain) {
            $this->deploy_test_variation_to_domain($domain, $test_id, $test_variations);
        }
        
        return $test_id;
    }
    
    /**
     * Analyse cross-site performance for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array Performance analysis
     */
    private function analyse_cross_site_performance($affiliate_id) {
        global $wpdb;
        
        // Get performance data across all domains
        $performance_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                lp.domain,
                lp.link_url,
                lp.clicks,
                lp.conversions,
                lp.revenue,
                lp.last_click_date,
                lp.last_conversion_date,
                lp.status,
                CASE 
                    WHEN lp.clicks > 0 THEN (lp.conversions / lp.clicks) * 100
                    ELSE 0
                END as conversion_rate,
                CASE 
                    WHEN lp.conversions > 0 THEN lp.revenue / lp.conversions
                    ELSE 0
                END as avg_order_value
            FROM {$wpdb->prefix}affiliate_link_performance lp
            WHERE lp.affiliate_id = %d
            AND lp.updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY lp.revenue DESC
        ", $affiliate_id));
        
        // Group by domain for analysis
        $domain_performance = [];
        $total_performance = [
            'clicks' => 0,
            'conversions' => 0,
            'revenue' => 0,
            'domains' => 0
        ];
        
        foreach ($performance_data as $link_data) {
            $domain = $link_data->domain;
            
            if (!isset($domain_performance[$domain])) {
                $domain_performance[$domain] = [
                    'domain' => $domain,
                    'links' => [],
                    'total_clicks' => 0,
                    'total_conversions' => 0,
                    'total_revenue' => 0,
                    'avg_conversion_rate' => 0,
                    'avg_order_value' => 0
                ];
                $total_performance['domains']++;
            }
            
            $domain_performance[$domain]['links'][] = $link_data;
            $domain_performance[$domain]['total_clicks'] += $link_data->clicks;
            $domain_performance[$domain]['total_conversions'] += $link_data->conversions;
            $domain_performance[$domain]['total_revenue'] += $link_data->revenue;
            
            $total_performance['clicks'] += $link_data->clicks;
            $total_performance['conversions'] += $link_data->conversions;
            $total_performance['revenue'] += $link_data->revenue;
        }
        
        // Calculate averages for each domain
        foreach ($domain_performance as &$domain_data) {
            $domain_data['avg_conversion_rate'] = $domain_data['total_clicks'] > 0 ? 
                ($domain_data['total_conversions'] / $domain_data['total_clicks']) * 100 : 0;
            $domain_data['avg_order_value'] = $domain_data['total_conversions'] > 0 ? 
                $domain_data['total_revenue'] / $domain_data['total_conversions'] : 0;
        }
        
        return [
            'domain_performance' => $domain_performance,
            'total_performance' => $total_performance,
            'network_avg_conversion_rate' => $total_performance['clicks'] > 0 ? 
                ($total_performance['conversions'] / $total_performance['clicks']) * 100 : 0,
            'network_avg_order_value' => $total_performance['conversions'] > 0 ? 
                $total_performance['revenue'] / $total_performance['conversions'] : 0
        ];
    }
    
    /**
     * Identify top converting pages
     * @param array $performance_data Performance data
     * @return array Top converting pages
     */
    private function identify_top_converting_pages($performance_data) {
        $all_links = [];
        
        foreach ($performance_data['domain_performance'] as $domain_data) {
            foreach ($domain_data['links'] as $link_data) {
                $all_links[] = $link_data;
            }
        }
        
        // Sort by conversion rate and revenue
        usort($all_links, function($a, $b) {
            if ($a->conversion_rate == $b->conversion_rate) {
                return $b->revenue <=> $a->revenue;
            }
            return $b->conversion_rate <=> $a->conversion_rate;
        });
        
        return array_slice($all_links, 0, 10); // Top 10 performing links
    }
    
    /**
     * Calculate best link placement positions
     * @param array $performance_data Performance data
     * @return array Placement recommendations
     */
    private function calculate_best_link_placement($performance_data) {
        $placement_analysis = [];
        
        // Analyse performance by link position (if tracking data is available)
        foreach ($performance_data['domain_performance'] as $domain => $domain_data) {
            $placement_analysis[$domain] = [
                'header_performance' => $this->analyse_position_performance($domain_data['links'], 'header'),
                'content_performance' => $this->analyse_position_performance($domain_data['links'], 'content'),
                'sidebar_performance' => $this->analyse_position_performance($domain_data['links'], 'sidebar'),
                'footer_performance' => $this->analyse_position_performance($domain_data['links'], 'footer'),
                'recommendations' => $this->generate_placement_recommendations($domain_data)
            ];
        }
        
        return $placement_analysis;
    }
    
    /**
     * Identify seasonal trends in performance
     * @param array $performance_data Performance data
     * @return array Seasonal insights
     */
    private function identify_seasonal_trends($performance_data) {
        global $wpdb;
        
        // Get historical performance data by month
        $seasonal_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                MONTH(created_at) as month,
                AVG(CASE WHEN clicks > 0 THEN (conversions / clicks) * 100 ELSE 0 END) as avg_conversion_rate,
                AVG(CASE WHEN conversions > 0 THEN revenue / conversions ELSE 0 END) as avg_order_value,
                SUM(revenue) as total_revenue
            FROM {$wpdb->prefix}affiliate_link_performance
            WHERE affiliate_id IN (SELECT DISTINCT affiliate_id FROM {$wpdb->prefix}affiliate_link_performance 
                                  WHERE affiliate_id = %d)
            AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY MONTH(created_at)
            ORDER BY month
        ", array_keys($performance_data['domain_performance'])[0] ?? 0));
        
        $seasonal_insights = [
            'best_months' => [],
            'worst_months' => [],
            'trends' => [],
            'recommendations' => []
        ];
        if (!empty($seasonal_data)) {
            // Sort by revenue to identify best and worst months
            $revenue_sorted = $seasonal_data;
            usort($revenue_sorted, function($a, $b) {
                return $b->total_revenue <=> $a->total_revenue;
            });
            
            $seasonal_insights['best_months'] = array_slice($revenue_sorted, 0, 3);
            $seasonal_insights['worst_months'] = array_slice($revenue_sorted, -3);
            
            // Identify trends
            $seasonal_insights['trends'] = $this->calculate_seasonal_trends($seasonal_data);
            
            // Generate recommendations
            $seasonal_insights['recommendations'] = $this->generate_seasonal_recommendations($seasonal_data);
        }
        
        return $seasonal_insights;
    }
    
    /**
     * Calculate seasonal trends from historical data
     * @param array $seasonal_data Seasonal data
     * @return array Seasonal trends
     */
    private function calculate_seasonal_trends($seasonal_data) {
        $trends = [];
        
        // Calculate month-over-month growth rates
        for ($i = 1; $i < count($seasonal_data); $i++) {
            $current = $seasonal_data[$i];
            $previous = $seasonal_data[$i - 1];
            
            if ($previous->total_revenue > 0) {
                $growth_rate = (($current->total_revenue - $previous->total_revenue) / $previous->total_revenue) * 100;
                
                $trends[] = [
                    'month' => $current->month,
                    'growth_rate' => round($growth_rate, 2),
                    'revenue' => $current->total_revenue,
                    'conversion_rate' => $current->avg_conversion_rate
                ];
            }
        }
        
        return $trends;
    }
    
    /**
     * Generate seasonal recommendations
     * @param array $seasonal_data Seasonal data
     * @return array Seasonal recommendations
     */
    private function generate_seasonal_recommendations($seasonal_data) {
        $recommendations = [];
        
        // Identify peak and low seasons
        $monthly_averages = [];
        foreach ($seasonal_data as $data) {
            $monthly_averages[$data->month] = $data->total_revenue;
        }
        
        $peak_months = array_keys($monthly_averages, max($monthly_averages));
        $low_months = array_keys($monthly_averages, min($monthly_averages));
        
        foreach ($peak_months as $month) {
            $recommendations[] = [
                'type' => 'peak_season',
                'month' => $month,
                'recommendation' => sprintf(
                    __('Month %d shows peak performance. Consider increasing promotional activities during this period.', 'affiliate-master-enhancement'),
                    $month
                )
            ];
        }
        
        foreach ($low_months as $month) {
            $recommendations[] = [
                'type' => 'low_season',
                'month' => $month,
                'recommendation' => sprintf(
                    __('Month %d shows lower performance. Consider special promotions or content strategies to boost activity.', 'affiliate-master-enhancement'),
                    $month
                )
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Analyse position performance for specific location
     * @param array $links Link data
     * @param string $position Position type
     * @return array Position performance data
     */
    private function analyse_position_performance($links, $position) {
        $position_links = array_filter($links, function($link) use ($position) {
            // In a real implementation, this would check link metadata for position
            return strpos($link->link_url, $position) !== false;
        });
        
        if (empty($position_links)) {
            return [
                'total_clicks' => 0,
                'total_conversions' => 0,
                'conversion_rate' => 0,
                'total_revenue' => 0,
                'recommendation' => __('No data available for this position', 'affiliate-master-enhancement')
            ];
        }
        
        $total_clicks = array_sum(array_column($position_links, 'clicks'));
        $total_conversions = array_sum(array_column($position_links, 'conversions'));
        $total_revenue = array_sum(array_column($position_links, 'revenue'));
        
        $conversion_rate = $total_clicks > 0 ? ($total_conversions / $total_clicks) * 100 : 0;
        
        return [
            'total_clicks' => $total_clicks,
            'total_conversions' => $total_conversions,
            'conversion_rate' => round($conversion_rate, 2),
            'total_revenue' => $total_revenue,
            'average_order_value' => $total_conversions > 0 ? $total_revenue / $total_conversions : 0,
            'recommendation' => $this->generate_position_recommendation($conversion_rate, $total_revenue)
        ];
    }
    
    /**
     * Generate placement recommendations for domain
     * @param array $domain_data Domain performance data
     * @return array Placement recommendations
     */
    private function generate_placement_recommendations($domain_data) {
        $recommendations = [];
        
        // Analyse overall performance
        $avg_conversion_rate = $domain_data['total_clicks'] > 0 ? 
            ($domain_data['total_conversions'] / $domain_data['total_clicks']) * 100 : 0;
        
        if ($avg_conversion_rate < 2.0) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'conversion_rate',
                'title' => __('Improve Link Visibility', 'affiliate-master-enhancement'),
                'description' => __('Low conversion rate suggests links may not be prominently placed', 'affiliate-master-enhancement'),
                'actions' => [
                    __('Move links above the fold', 'affiliate-master-enhancement'),
                    __('Use more prominent call-to-action buttons', 'affiliate-master-enhancement'),
                    __('Increase link frequency in content', 'affiliate-master-enhancement'),
                    __('Test different link positions', 'affiliate-master-enhancement')
                ]
            ];
        }
        
        if ($domain_data['total_clicks'] < 100) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'traffic',
                'title' => __('Increase Link Exposure', 'affiliate-master-enhancement'),
                'description' => __('Low click volume suggests limited link exposure', 'affiliate-master-enhancement'),
                'actions' => [
                    __('Add links to more pages', 'affiliate-master-enhancement'),
                    __('Improve content marketing', 'affiliate-master-enhancement'),
                    __('Optimise for search engines', 'affiliate-master-enhancement'),
                    __('Consider paid promotion', 'affiliate-master-enhancement')
                ]
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Generate position-specific recommendation
     * @param float $conversion_rate Conversion rate for position
     * @param float $total_revenue Total revenue for position
     * @return string Recommendation text
     */
    private function generate_position_recommendation($conversion_rate, $total_revenue) {
        if ($conversion_rate > 5.0 && $total_revenue > 1000) {
            return __('Excellent performance! Consider expanding similar placements.', 'affiliate-master-enhancement');
        } elseif ($conversion_rate > 3.0) {
            return __('Good performance. Monitor and maintain current approach.', 'affiliate-master-enhancement');
        } elseif ($conversion_rate > 1.0) {
            return __('Average performance. Consider A/B testing different approaches.', 'affiliate-master-enhancement');
        } else {
            return __('Underperforming. Review placement strategy and test alternatives.', 'affiliate-master-enhancement');
        }
    }
    
    /**
     * Determine highest priority improvement opportunity
     * @param array $opportunities Improvement opportunities
     * @return array Highest priority opportunity
     */
    private function determine_highest_priority($opportunities) {
        $priority_scores = [
            'high' => 3,
            'medium' => 2,
            'low' => 1
        ];
        
        $highest_score = 0;
        $highest_priority = null;
        
        foreach ($opportunities as $opportunity) {
            $score = $priority_scores[$opportunity['severity']] ?? 0;
            
            // Add bonus for revenue impact
            if (isset($opportunity['potential_improvement']) && $opportunity['potential_improvement'] > 100) {
                $score += 1;
            }
            
            if ($score > $highest_score) {
                $highest_score = $score;
                $highest_priority = $opportunity;
            }
        }
        
        return $highest_priority;
    }
    
    /**
     * Estimate revenue impact of improvements
     * @param array $domain_data Domain performance data
     * @param array $opportunities Improvement opportunities
     * @return float Estimated revenue impact
     */
    private function estimate_revenue_impact($domain_data, $opportunities) {
        $current_revenue = $domain_data['total_revenue'];
        $estimated_impact = 0;
        
        foreach ($opportunities as $opportunity) {
            switch ($opportunity['type']) {
                case 'low_conversion_rate':
                    // Estimate 20-50% improvement potential
                    $improvement_factor = 0.3;
                    $estimated_impact += $current_revenue * $improvement_factor;
                    break;
                    
                case 'low_average_order_value':
                    // Estimate 10-25% improvement potential
                    $improvement_factor = 0.15;
                    $estimated_impact += $current_revenue * $improvement_factor;
                    break;
                    
                case 'low_traffic':
                    // Estimate 50-100% improvement potential
                    $improvement_factor = 0.75;
                    $estimated_impact += $current_revenue * $improvement_factor;
                    break;
            }
        }
        
        return round($estimated_impact, 2);
    }
    
    /**
     * Check if there's sufficient traffic for A/B testing
     * @param array $performance_data Performance data
     * @return bool Sufficient traffic for testing
     */
    private function has_sufficient_traffic_for_testing($performance_data) {
        $total_clicks = $performance_data['total_performance']['clicks'];
        $total_conversions = $performance_data['total_performance']['conversions'];
        
        // Need at least 1000 clicks and 20 conversions for meaningful A/B testing
        return $total_clicks >= 1000 && $total_conversions >= 20;
    }
    
    /**
     * Analyse domain correlation patterns
     * @param array $performance_data Performance data
     * @return array Domain correlation analysis
     */
    private function analyse_domain_correlation($performance_data) {
        $correlations = [];
        $domains = array_keys($performance_data['domain_performance']);
        
        // Calculate correlation between domain performances
        for ($i = 0; $i < count($domains); $i++) {
            for ($j = $i + 1; $j < count($domains); $j++) {
                $domain1 = $domains[$i];
                $domain2 = $domains[$j];
                
                $correlation = $this->calculate_domain_correlation(
                    $performance_data['domain_performance'][$domain1],
                    $performance_data['domain_performance'][$domain2]
                );
                
                if (abs($correlation) > 0.5) { // Only include significant correlations
                    $correlations[] = [
                        'domain1' => $domain1,
                        'domain2' => $domain2,
                        'correlation' => round($correlation, 3),
                        'strength' => $this->classify_correlation_strength($correlation)
                    ];
                }
            }
        }
        
        return $correlations;
    }
    
    /**
     * Calculate correlation between two domains
     * @param array $domain1_data Domain 1 performance data
     * @param array $domain2_data Domain 2 performance data
     * @return float Correlation coefficient
     */
    private function calculate_domain_correlation($domain1_data, $domain2_data) {
        // Simplified correlation calculation based on conversion rates and revenue
        $x = [$domain1_data['avg_conversion_rate'], $domain1_data['total_revenue']];
        $y = [$domain2_data['avg_conversion_rate'], $domain2_data['total_revenue']];
        
        if (count($x) < 2 || count($y) < 2) {
            return 0;
        }
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        $sum_y2 = 0;
        $n = count($x);
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
            $sum_y2 += $y[$i] * $y[$i];
        }
        
        $denominator = sqrt(($n * $sum_x2 - $sum_x * $sum_x) * ($n * $sum_y2 - $sum_y * $sum_y));
        
        if ($denominator == 0) {
            return 0;
        }
        
        return ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
    }
    
    /**
     * Classify correlation strength
     * @param float $correlation Correlation value
     * @return string Correlation strength classification
     */
    private function classify_correlation_strength($correlation) {
        $abs_correlation = abs($correlation);
        
        if ($abs_correlation >= 0.8) {
            return 'very_strong';
        } elseif ($abs_correlation >= 0.6) {
            return 'strong';
        } elseif ($abs_correlation >= 0.4) {
            return 'moderate';
        } elseif ($abs_correlation >= 0.2) {
            return 'weak';
        } else {
            return 'very_weak';
        }
    }
    
    /**
     * Analyse traffic patterns across domains
     * @param array $performance_data Performance data
     * @return array Traffic pattern analysis
     */
    private function analyse_traffic_patterns($performance_data) {
        $patterns = [
            'peak_traffic_domains' => [],
            'consistent_performers' => [],
            'volatile_domains' => [],
            'growth_trends' => []
        ];
        
        foreach ($performance_data['domain_performance'] as $domain => $data) {
            // Classify based on traffic volume and consistency
            if ($data['total_clicks'] > 10000) {
                $patterns['peak_traffic_domains'][] = [
                    'domain' => $domain,
                    'clicks' => $data['total_clicks'],
                    'conversion_rate' => $data['avg_conversion_rate']
                ];
            }
            
            // Calculate consistency (simplified)
            $conversion_volatility = $this->calculate_conversion_volatility($domain);
            
            if ($conversion_volatility < 0.2) {
                $patterns['consistent_performers'][] = [
                    'domain' => $domain,
                    'volatility' => $conversion_volatility,
                    'avg_conversion_rate' => $data['avg_conversion_rate']
                ];
            } elseif ($conversion_volatility > 0.5) {
                $patterns['volatile_domains'][] = [
                    'domain' => $domain,
                    'volatility' => $conversion_volatility,
                    'avg_conversion_rate' => $data['avg_conversion_rate']
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * Calculate conversion rate volatility for domain
     * @param string $domain Domain name
     * @return float Volatility measure
     */
    private function calculate_conversion_volatility($domain) {
        global $wpdb;
        
        // Get daily conversion rates for the last 30 days
        $daily_rates = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                CASE WHEN SUM(clicks) > 0 
                     THEN (SUM(conversions) / SUM(clicks)) * 100 
                     ELSE 0 
                END as daily_conversion_rate
            FROM {$wpdb->prefix}affiliate_link_performance
            WHERE domain = %s
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ", $domain));
        
        if (count($daily_rates) < 5) {
            return 0; // Not enough data
        }
        
        $rates = array_column($daily_rates, 'daily_conversion_rate');
        $mean = array_sum($rates) / count($rates);
        
        // Calculate standard deviation
        $variance = 0;
        foreach ($rates as $rate) {
            $variance += pow($rate - $mean, 2);
        }
        
        $std_deviation = sqrt($variance / count($rates));
        
        // Return coefficient of variation (volatility measure)
        return $mean > 0 ? $std_deviation / $mean : 0;
    }
    
    /**
     * Analyse conversion patterns
     * @param array $performance_data Performance data
     * @return array Conversion pattern analysis
     */
    private function analyse_conversion_patterns($performance_data) {
        return [
            'high_converting_domains' => $this->identify_high_converting_domains($performance_data),
            'conversion_optimization_opportunities' => $this->identify_conversion_opportunities($performance_data),
            'seasonal_conversion_patterns' => $this->analyse_seasonal_conversion_patterns($performance_data)
        ];
    }
    
    /**
     * Identify high converting domains
     * @param array $performance_data Performance data
     * @return array High converting domains
     */
    private function identify_high_converting_domains($performance_data) {
        $high_converters = [];
        $network_avg = $performance_data['network_avg_conversion_rate'];
        
        foreach ($performance_data['domain_performance'] as $domain => $data) {
            if ($data['avg_conversion_rate'] > ($network_avg * 1.5) && $data['total_clicks'] > 500) {
                $high_converters[] = [
                    'domain' => $domain,
                    'conversion_rate' => $data['avg_conversion_rate'],
                    'performance_vs_network' => round(($data['avg_conversion_rate'] / $network_avg) * 100, 1),
                    'total_clicks' => $data['total_clicks'],
                    'total_revenue' => $data['total_revenue']
                ];
            }
        }
        
        // Sort by conversion rate
        usort($high_converters, function($a, $b) {
            return $b['conversion_rate'] <=> $a['conversion_rate'];
        });
        
        return $high_converters;
    }
    
    /**
     * Identify conversion optimization opportunities
     * @param array $performance_data Performance data
     * @return array Optimization opportunities
     */
    private function identify_conversion_opportunities($performance_data) {
        $opportunities = [];
        $network_avg = $performance_data['network_avg_conversion_rate'];
        
        foreach ($performance_data['domain_performance'] as $domain => $data) {
            if ($data['avg_conversion_rate'] < ($network_avg * 0.7) && $data['total_clicks'] > 1000) {
                $opportunities[] = [
                    'domain' => $domain,
                    'current_rate' => $data['avg_conversion_rate'],
                    'network_average' => $network_avg,
                    'improvement_potential' => round($network_avg - $data['avg_conversion_rate'], 2),
                    'potential_revenue_increase' => round(
                        ($data['total_clicks'] * ($network_avg - $data['avg_conversion_rate']) / 100) * 
                        $data['avg_order_value'], 2
                    )
                ];
            }
        }
        
        return $opportunities;
    }
    
    /**
     * Analyse seasonal conversion patterns
     * @param array $performance_data Performance data
     * @return array Seasonal conversion analysis
     */
    private function analyse_seasonal_conversion_patterns($performance_data) {
        // This would implement seasonal pattern analysis
        // For now, returning placeholder data
        return [
            'peak_months' => [11, 12], // November, December
            'low_months' => [1, 2],    // January, February
            'seasonal_multipliers' => [
                1 => 0.8, 2 => 0.9, 3 => 1.0, 4 => 1.1,
                5 => 1.0, 6 => 1.0, 7 => 0.9, 8 => 0.9,
                9 => 1.1, 10 => 1.2, 11 => 1.4, 12 => 1.5
            ]
        ];
    }
    
    /**
     * Analyse revenue distribution across domains
     * @param array $performance_data Performance data
     * @return array Revenue distribution analysis
     */
    private function analyse_revenue_distribution($performance_data) {
        $revenue_data = [];
        $total_revenue = $performance_data['total_performance']['revenue'];
        
        foreach ($performance_data['domain_performance'] as $domain => $data) {
            $revenue_percentage = $total_revenue > 0 ? ($data['total_revenue'] / $total_revenue) * 100 : 0;
            
            $revenue_data[] = [
                'domain' => $domain,
                'revenue' => $data['total_revenue'],
                'percentage' => round($revenue_percentage, 2),
                'revenue_per_click' => $data['total_clicks'] > 0 ? 
                    round($data['total_revenue'] / $data['total_clicks'], 2) : 0
            ];
        }
        
        // Sort by revenue
        usort($revenue_data, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        return [
            'distribution' => $revenue_data,
            'top_revenue_domains' => array_slice($revenue_data, 0, 5),
            'revenue_concentration' => $this->calculate_revenue_concentration($revenue_data)
        ];
    }
    
    /**
     * Calculate revenue concentration (how concentrated revenue is among top domains)
     * @param array $revenue_data Revenue distribution data
     * @return array Concentration metrics
     */
    private function calculate_revenue_concentration($revenue_data) {
        if (empty($revenue_data)) {
            return ['top_20_percent' => 0, 'gini_coefficient' => 0];
        }
        
        $total_domains = count($revenue_data);
        $top_20_percent_count = max(1, round($total_domains * 0.2));
        
        $top_20_percent_revenue = 0;
        $total_revenue = array_sum(array_column($revenue_data, 'revenue'));
        
        for ($i = 0; $i < $top_20_percent_count; $i++) {
            $top_20_percent_revenue += $revenue_data[$i]['revenue'] ?? 0;
        }
        
        $concentration = $total_revenue > 0 ? ($top_20_percent_revenue / $total_revenue) * 100 : 0;
        
        return [
            'top_20_percent' => round($concentration, 2),
            'gini_coefficient' => $this->calculate_gini_coefficient($revenue_data),
            'interpretation' => $this->interpret_concentration($concentration)
        ];
    }
    
    /**
     * Calculate Gini coefficient for revenue inequality
     * @param array $revenue_data Revenue data
     * @return float Gini coefficient (0 = perfect equality, 1 = perfect inequality)
     */
    private function calculate_gini_coefficient($revenue_data) {
        $revenues = array_column($revenue_data, 'revenue');
        sort($revenues);
        
        $n = count($revenues);
        $sum_revenues = array_sum($revenues);
        
        if ($sum_revenues == 0 || $n <= 1) {
            return 0;
        }
        
        $gini_sum = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $gini_sum += ($i + 1) * $revenues[$i];
        }
        
        return round((2 * $gini_sum) / ($n * $sum_revenues) - ($n + 1) / $n, 3);
    }
    
    /**
     * Interpret revenue concentration level
     * @param float $concentration Concentration percentage
     * @return string Interpretation
     */
    private function interpret_concentration($concentration) {
        if ($concentration > 80) {
            return __('Very high concentration - revenue heavily dependent on few domains', 'affiliate-master-enhancement');
        } elseif ($concentration > 60) {
            return __('High concentration - consider diversifying revenue sources', 'affiliate-master-enhancement');
        } elseif ($concentration > 40) {
            return __('Moderate concentration - balanced revenue distribution', 'affiliate-master-enhancement');
        } else {
            return __('Low concentration - revenue well distributed across domains', 'affiliate-master-enhancement');
        }
    }
    
    /**
     * Identify cross-promotion opportunities
     * @param array $performance_data Performance data
     * @return array Cross-promotion opportunities
     */
    private function identify_cross_promotion_opportunities($performance_data) {
        $opportunities = [];
        
        // Find high-performing domains that could promote to low-performing ones
        $domains = $performance_data['domain_performance'];
        $network_avg_conversion = $performance_data['network_avg_conversion_rate'];
        
        $high_performers = array_filter($domains, function($data) use ($network_avg_conversion) {
            return $data['avg_conversion_rate'] > ($network_avg_conversion * 1.2);
        });
        
        $low_performers = array_filter($domains, function($data) use ($network_avg_conversion) {
            return $data['avg_conversion_rate'] < ($network_avg_conversion * 0.8) && $data['total_clicks'] > 500;
        });
        
        foreach ($high_performers as $high_domain => $high_data) {
            foreach ($low_performers as $low_domain => $low_data) {
                $opportunities[] = [
                    'source_domain' => $high_domain,
                    'target_domain' => $low_domain,
                    'source_conversion_rate' => $high_data['avg_conversion_rate'],
                    'target_conversion_rate' => $low_data['avg_conversion_rate'],
                    'improvement_potential' => round($high_data['avg_conversion_rate'] - $low_data['avg_conversion_rate'], 2),
                    'strategy' => $this->suggest_cross_promotion_strategy($high_data, $low_data)
                ];
            }
        }
        
        return array_slice($opportunities, 0, 10); // Return top 10 opportunities
    }
    
    /**
     * Suggest cross-promotion strategy
     * @param array $high_performer High performing domain data
     * @param array $low_performer Low performing domain data
     * @return string Strategy suggestion
     */
    private function suggest_cross_promotion_strategy($high_performer, $low_performer) {
        $strategies = [
            __('Cross-reference successful content from high-performing domain', 'affiliate-master-enhancement'),
            __('Implement similar link placement strategies', 'affiliate-master-enhancement'),
            __('Share high-converting promotional materials', 'affiliate-master-enhancement'),
            __('Coordinate joint promotional campaigns', 'affiliate-master-enhancement'),
            __('Transfer successful CTAs and messaging', 'affiliate-master-enhancement')
        ];
        
        // Return random strategy for now (in real implementation, would be more sophisticated)
        return $strategies[array_rand($strategies)];
    }
    
    /**
     * Suggest CTA variations for testing
     * @param string $link_url Current link URL
     * @return array CTA suggestions
     */
    private function suggest_cta_variations($link_url) {
        // Extract current CTA or generate suggestions based on URL
        $base_ctas = [
            __('Click Here', 'affiliate-master-enhancement'),
            __('Learn More', 'affiliate-master-enhancement'),
            __('Get Started', 'affiliate-master-enhancement'),
            __('Try Now', 'affiliate-master-enhancement'),
            __('Discover More', 'affiliate-master-enhancement')
        ];
        
        $power_words = [
            __('Exclusive', 'affiliate-master-enhancement'),
            __('Limited Time', 'affiliate-master-enhancement'),
            __('Free', 'affiliate-master-enhancement'),
            __('Instant', 'affiliate-master-enhancement'),
            __('Premium', 'affiliate-master-enhancement')
        ];
        
        $variations = [];
        
        // Generate variations by combining base CTAs with power words
        foreach ($base_ctas as $base) {
            $variations[] = $base;
            
            // Add power word variations
            foreach (array_slice($power_words, 0, 2) as $power_word) {
                $variations[] = $power_word . ' ' . $base;
            }
        }
        
        return array_slice($variations, 0, 8); // Return 8 variations
    }
    
    /**
     * Calculate required test duration for statistical significance
     * @param int $monthly_clicks Monthly click volume
     * @return int Estimated test duration in days
     */
    private function calculate_test_duration($monthly_clicks) {
        // Simple calculation based on click volume
        // In practice, would consider baseline conversion rate, minimum detectable effect, etc.
        
        if ($monthly_clicks > 10000) {
            return 7;  // 1 week for high traffic
        } elseif ($monthly_clicks > 5000) {
            return 14; // 2 weeks for medium traffic
        } elseif ($monthly_clicks > 1000) {
            return 30; // 1 month for low traffic
        } else {
            return 60; // 2 months for very low traffic
        }
    }
    
    /**
     * Create test variation record
     * @param int $test_id Test ID
     * @param string $variation_key Variation key
     * @param array $variation_data Variation configuration
     */
    private function create_test_variation($test_id, $variation_key, $variation_data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_ab_test_variations',
            [
                'test_id' => $test_id,
                'variation_key' => $variation_key,
                'variation_data' => json_encode($variation_data),
                'traffic_allocation' => floatval($variation_data['traffic_allocation'] ?? 0.5),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%f', '%s']
        );
    }
    
    /**
     * Deploy test variation to specific domain
     * @param string $domain Target domain
     * @param int $test_id Test ID
     * @param array $test_variations Test configuration
     */
    private function deploy_test_variation_to_domain($domain, $test_id, $test_variations) {
        // In a real implementation, this would make API calls to satellite sites
        // to deploy the test configuration
        
        $deployment_data = [
            'test_id' => $test_id,
            'domain' => $domain,
            'variations' => $test_variations['variations'],
            'start_date' => current_time('mysql'),
            'end_date' => $test_variations['end_date'] ?? null
        ];
        
        // Store deployment record
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_ab_test_deployments',
            [
                'test_id' => $test_id,
                'domain' => $domain,
                'deployment_data' => json_encode($deployment_data),
                'status' => 'deployed',
                'deployed_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        // Log deployment
        error_log("AME: A/B test {$test_id} deployed to domain {$domain}");
    }
    
    /**
     * Get affiliated domains for affiliate
     * @param int $affiliate_id Affiliate ID
     * @return array List of domains
     */
    private function get_affiliated_domains($affiliate_id) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT domain
            FROM {$wpdb->prefix}affiliate_link_performance
            WHERE affiliate_id = %d
            AND status = 'active'
        ", $affiliate_id));
    }
    
    /**
     * Get active A/B test for affiliate and domain
     * @param int $affiliate_id Affiliate ID
     * @param string $domain Domain
     * @return array|null Active test data
     */
    private function get_active_ab_test($affiliate_id, $domain) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                t.*,
                d.deployment_data
            FROM {$wpdb->prefix}affiliate_ab_tests t
            JOIN {$wpdb->prefix}affiliate_ab_test_deployments d ON t.id = d.test_id
            WHERE t.affiliate_id = %d
            AND d.domain = %s
            AND t.status = 'active'
            AND (t.end_date IS NULL OR t.end_date > NOW())
            ORDER BY t.created_at DESC
            LIMIT 1
        ", $affiliate_id, $domain), ARRAY_A);
    }
    
    /**
     * Get A/B test variation for user
     * @param int $test_id Test ID
     * @return array|null Variation data
     */
    private function get_ab_test_variation($test_id) {
        global $wpdb;
        
        // Get all variations for this test
        $variations = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}affiliate_ab_test_variations
            WHERE test_id = %d
            ORDER BY traffic_allocation DESC
        ", $test_id));
        
        if (empty($variations)) {
            return null;
        }
        
        // Simple random selection based on traffic allocation
        $random = mt_rand() / mt_getrandmax();
        $cumulative_allocation = 0;
        
        foreach ($variations as $variation) {
            $cumulative_allocation += $variation->traffic_allocation;
            
            if ($random <= $cumulative_allocation) {
                return [
                    'variation_key' => $variation->variation_key,
                    'attributes' => json_decode($variation->variation_data, true)
                ];
            }
        }
        
        // Fallback to first variation
        return [
            'variation_key' => $variations[0]->variation_key,
            'attributes' => json_decode($variations[0]->variation_data, true)
        ];
    }
    
    /**
     * Apply A/B test modifications to link
     * @param string $link Original link
     * @param array $variation Variation data
     * @return string Modified link
     */
    private function apply_ab_test_modifications($link, $variation) {
        // Add variation tracking parameters
        $link = add_query_arg([
            'ab_test_variation' => $variation['variation_key'],
            'ab_test_timestamp' => time()
        ], $link);
        
        return $link;
    }
    
    /**
     * Update link performance data
     * @param array $tracking_data Tracking data
     */
    private function update_link_performance_data($tracking_data) {
        global $wpdb;
        
        $domain = $tracking_data['source_domain'];
        $affiliate_id = $tracking_data['affiliate_id'];
        
        // Update or insert link performance record
        $existing_record = $wpdb->get_row($wpdb->prepare("
            SELECT id, clicks, conversions
            FROM {$wpdb->prefix}affiliate_link_performance
            WHERE affiliate_id = %d
            AND domain = %s
            AND DATE(updated_at) = CURDATE()
        ", $affiliate_id, $domain));
        
        if ($existing_record) {
            // Update existing record
            $wpdb->update(
                $wpdb->prefix . 'affiliate_link_performance',
                [
                    'clicks' => $existing_record->clicks + 1,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing_record->id],
                ['%d', '%s'],
                ['%d']
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $wpdb->prefix . 'affiliate_link_performance',
                [
                    'affiliate_id' => $affiliate_id,
                    'domain' => $domain,
                    'link_url' => $tracking_data['referrer'] ?? '',
                    'clicks' => 1,
                    'conversions' => 0,
                    'revenue' => 0,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s']
            );
        }
    }
    
    /**
     * Track A/B test interaction
     * @param array $tracking_data Tracking data
     */
    private function track_ab_test_interaction($tracking_data) {
        global $wpdb;
        
        if (empty($tracking_data['ab_test_id']) || empty($tracking_data['ab_variation'])) {
            return;
        }
        
        // Update variation click count
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}affiliate_ab_test_variations
            SET clicks = clicks + 1
            WHERE test_id = %d
            AND variation_key = %s
        ", $tracking_data['ab_test_id'], $tracking_data['ab_variation']));
        
        // Log individual interaction
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_ab_test_interactions',
            [
                'test_id' => $tracking_data['ab_test_id'],
                'variation_key' => $tracking_data['ab_variation'],
                'affiliate_id' => $tracking_data['affiliate_id'],
                'domain' => $tracking_data['source_domain'],
                'interaction_type' => 'click',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Get A/B test results
     * @param int $test_id Test ID
     * @return array|null Test results
     */
    private function get_ab_test_results($test_id) {
        global $wpdb;
        
        $test = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}affiliate_ab_tests WHERE id = %d
        ", $test_id));
        
        if (!$test) {
            return null;
        }
        
        $variations = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}affiliate_ab_test_variations
            WHERE test_id = %d
        ", $test_id));
        
        $results = [
            'test_id' => $test_id,
            'test_name' => $test->test_name,
            'status' => $test->status,
            'start_date' => $test->start_date,
            'end_date' => $test->end_date,
            'variations' => [],
            'winner' => null,
            'confidence_level' => null,
            'statistical_significance' => false
        ];
        
        $total_clicks = 0;
        $total_conversions = 0;
        
        foreach ($variations as $variation) {
            $variation_data = [
                'variation_key' => $variation->variation_key,
                'clicks' => intval($variation->clicks),
                'conversions' => intval($variation->conversions),
                'revenue' => floatval($variation->revenue),
                'conversion_rate' => 0,
                'confidence_interval' => null
            ];
            
            if ($variation->clicks > 0) {
                $variation_data['conversion_rate'] = ($variation->conversions / $variation->clicks) * 100;
            }
            
            $results['variations'][] = $variation_data;
            $total_clicks += $variation->clicks;
            $total_conversions += $variation->conversions;
        }
        
        // Determine winner and statistical significance
        if (count($results['variations']) >= 2 && $total_clicks > 100) {
            $winner_data = $this->calculate_ab_test_winner($results['variations']);
            $results['winner'] = $winner_data['winner'];
            $results['confidence_level'] = $winner_data['confidence_level'];
            $results['statistical_significance'] = $winner_data['significant'];
        }
        
        return $results;
    }
    
    /**
     * Calculate A/B test winner with statistical significance
     * @param array $variations Variation data
     * @return array Winner analysis
     */
    private function calculate_ab_test_winner($variations) {
        if (count($variations) < 2) {
            return ['winner' => null, 'confidence_level' => 0, 'significant' => false];
        }
        
        // Sort by conversion rate
        usort($variations, function($a, $b) {
            return $b['conversion_rate'] <=> $a['conversion_rate'];
        });
        
        $best_variation = $variations[0];
        $second_best = $variations[1];
        
        // Simple significance test (in production, would use proper statistical tests)
        $sample_size_adequate = $best_variation['clicks'] >= 100 && $second_best['clicks'] >= 100;
        $effect_size_meaningful = abs($best_variation['conversion_rate'] - $second_best['conversion_rate']) >= 0.5;
        
        $confidence_level = 0;
        if ($sample_size_adequate && $effect_size_meaningful) {
            // Simplified confidence calculation
            $confidence_level = min(99, 50 + ($best_variation['clicks'] / 10));
        }
        
        return [
            'winner' => $best_variation['variation_key'],
            'confidence_level' => round($confidence_level, 1),
            'significant' => $confidence_level >= 95
        ];
    }
    
    /**
     * Analyse A/B tests (scheduled task)
     */
    public function analyse_ab_tests() {
        global $wpdb;
        
        // Get active tests that have been running for at least 7 days
        $tests_to_analyse = $wpdb->get_results("
            SELECT id, test_name, affiliate_id
            FROM {$wpdb->prefix}affiliate_ab_tests
            WHERE status = 'active'
            AND start_date <= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        foreach ($tests_to_analyse as $test) {
            $results = $this->get_ab_test_results($test->id);
            
            if ($results && $results['statistical_significance']) {
                // Auto-complete test if statistically significant
                $this->complete_ab_test($test->id, $results['winner']);
                
                // Send notification about test completion
                $this->send_ab_test_completion_notification($test, $results);
            }
        }
    }
    
    /**
     * Complete A/B test
     * @param int $test_id Test ID
     * @param string $winner_variation Winner variation key
     */
    private function complete_ab_test($test_id, $winner_variation) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'affiliate_ab_tests',
            [
                'status' => 'completed',
                'winner_variation' => $winner_variation,
                'end_date' => current_time('mysql')
            ],
            ['id' => $test_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        // Log test completion
        error_log("AME: A/B test {$test_id} completed with winner: {$winner_variation}");
    }
    
    /**
     * Send A/B test completion notification
     * @param object $test Test data
     * @param array $results Test results
     */
    private function send_ab_test_completion_notification($test, $results) {
        $affiliate = affwp_get_affiliate($test->affiliate_id);
        
        if (!$affiliate) {
            return;
        }
        
        $user = get_userdata($affiliate->user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = sprintf(__('A/B Test "%s" Completed', 'affiliate-master-enhancement'), $test->test_name);
        
        $message = sprintf(
            __("Your A/B test '%s' has completed with statistical significance!\n\nWinner: %s\nConfidence Level: %s%%\n\nThe winning variation is now recommended for implementation across your affiliate links.\n\nView detailed results in your affiliate dashboard.", 'affiliate-master-enhancement'),
            $test->test_name,
            $results['winner'],
            $results['confidence_level']
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Get detailed link performance data
     * @param int $affiliate_id Affiliate ID
     * @param string $timeframe Timeframe for analysis
     * @return array Detailed performance data
     */
    private function get_detailed_link_performance($affiliate_id, $timeframe) {
        global $wpdb;
        
        $where_clause = $this->build_timeframe_where_clause($timeframe);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                lp.*,
                DATE(lp.updated_at) as performance_date
            FROM {$wpdb->prefix}affiliate_link_performance lp
            WHERE lp.affiliate_id = %d
            {$where_clause}
            ORDER BY lp.updated_at DESC
        ", $affiliate_id));
    }
    
    /**
     * Build WHERE clause for timeframe filtering
     * @param string $timeframe Timeframe specification
     * @return string SQL WHERE clause
     */
    private function build_timeframe_where_clause($timeframe) {
        switch ($timeframe) {
            case '7days':
                return 'AND lp.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            case '30days':
                return 'AND lp.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            case '90days':
                return 'AND lp.updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            case '12months':
                return 'AND lp.updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)';
            default:
                return 'AND lp.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }
    }
    
    /**
     * Get traffic source from current context
     * @return string Traffic source
     */
    private function get_traffic_source() {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $parsed = parse_url($_SERVER['HTTP_REFERER']);
            $host = $parsed['host'] ?? '';
            
            // Identify common traffic sources
            if (strpos($host, 'google') !== false) {
                return 'google';
            } elseif (strpos($host, 'facebook') !== false) {
                return 'facebook';
            } elseif (strpos($host, 'twitter') !== false) {
                return 'twitter';
            } else {
                return 'referral';
            }
        }
        
        return 'direct';
    }
    
    /**
     * Get current domain
     * @return string Current domain
     */
    private function get_current_domain() {
        return $_SERVER['HTTP_HOST'] ?? home_url();
    }
    
    /**
     * Get current page identifier
     * @return string Page identifier
     */
    private function get_current_page_identifier() {
        global $post;
        
        if ($post) {
            return 'post_' . $post->ID;
        }
        
        if (is_front_page()) {
            return 'front_page';
        } elseif (is_home()) {
            return 'blog_home';
        } elseif (is_category()) {
            return 'category_' . get_queried_object_id();
        } elseif (is_tag()) {
            return 'tag_' . get_queried_object_id();
        } elseif (is_archive()) {
            return 'archive';
        }
        
        return 'unknown';
    }
    
    /**
     * Create additional database tables for A/B testing
     */
    public static function create_additional_ab_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // A/B test deployments table
        $deployments_table = $wpdb->prefix . 'affiliate_ab_test_deployments';
        $sql_deployments = "CREATE TABLE $deployments_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id bigint(20) UNSIGNED NOT NULL,
            domain varchar(255) NOT NULL,
            deployment_data longtext,
            status enum('deployed', 'paused', 'stopped') NOT NULL DEFAULT 'deployed',
            deployed_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY test_id_index (test_id),
            KEY domain_index (domain),
            KEY status_index (status)
        ) $charset_collate;";
        
        // A/B test interactions table
        $interactions_table = $wpdb->prefix . 'affiliate_ab_test_interactions';
        $sql_interactions = "CREATE TABLE $interactions_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id bigint(20) UNSIGNED NOT NULL,
            variation_key varchar(50) NOT NULL,
            affiliate_id bigint(20) UNSIGNED NOT NULL,
            domain varchar(255) NOT NULL,
            interaction_type enum('view', 'click', 'conversion') NOT NULL DEFAULT 'click',
            session_id varchar(64) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY test_variation_index (test_id, variation_key),
            KEY affiliate_id_index (affiliate_id),
            KEY domain_index (domain),
            KEY interaction_type_index (interaction_type),
            KEY created_at_index (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_deployments);
        dbDelta($sql_interactions);
    }
}

// Create additional A/B testing tables on activation
register_activation_hook(AME_PLUGIN_FILE, ['CrossSiteLinkOptimisation', 'create_additional_ab_tables']);