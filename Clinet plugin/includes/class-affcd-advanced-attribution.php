<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced Attribution System
 * Multi-dimensional attribution modeling
 */
class AFFCD_Advanced_Attribution {

    private $parent;
    private $attribution_models = [];
    private $advanced_states = [];
    private $attribution_weights = [];

    public function __construct($parent) {
        $this->parent = $parent;
        $this->init_attribution_models();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Attribution tracking hooks
        add_action('affcd_form_submission', [$this, 'track_attribution_touchpoint'], 5, 3);
        add_action('affcd_identity_linked', [$this, 'recalculate_attribution'], 10, 3);
        add_action('woocommerce_checkout_order_processed', [$this, 'finalize_attribution'], 10, 1);
        
        // Real-time attribution updates
        add_action('wp_footer', [$this, 'inject_attribution_tracking']);
        
        // AJAX handlers
        add_action('wp_ajax_affcd_attribution_touchpoint', [$this, 'ajax_attribution_touchpoint']);
        add_action('wp_ajax_nopriv_affcd_attribution_touchpoint', [$this, 'ajax_attribution_touchpoint']);
        
        // Scheduled attribution optimization
        add_action('affcd_advanced_attribution_optimization', [$this, 'optimize_attribution_models']);
        
        // Attribution reporting
        add_action('affcd_generate_attribution_report', [$this, 'generate_attribution_insights']);
    }

    /**
     * Initialize attribution models
     */
    private function init_attribution_models() {
        $this->attribution_models = [
            'last_click' => [
                'name' => 'Last Click',
                'description' => 'All credit to the last touchpoint before conversion',
                'weight_function' => [$this, 'last_click_weights'],
                'default_weight' => 0.15
            ],
            'first_click' => [
                'name' => 'First Click',
                'description' => 'All credit to the first touchpoint',
                'weight_function' => [$this, 'first_click_weights'],
                'default_weight' => 0.10
            ],
            'linear' => [
                'name' => 'Linear',
                'description' => 'Equal credit distributed across all touchpoints',
                'weight_function' => [$this, 'linear_weights'],
                'default_weight' => 0.20
            ],
            'time_decay' => [
                'name' => 'Time Decay',
                'description' => 'More credit to recent touchpoints',
                'weight_function' => [$this, 'time_decay_weights'],
                'default_weight' => 0.25
            ],
            'position_based' => [
                'name' => 'Position Based (U-Shaped)',
                'description' => '40% first, 40% last, 20% middle touchpoints',
                'weight_function' => [$this, 'position_based_weights'],
                'default_weight' => 0.15
            ],
            'data_driven' => [
                'name' => 'Data-Driven',
                'description' => 'Machine learning optimized attribution',
                'weight_function' => [$this, 'data_driven_weights'],
                'default_weight' => 0.35
            ],
            'advanced_superposition' => [
                'name' => 'Advanced Superposition',
                'description' => 'Multi-dimensional probability-based attribution',
                'weight_function' => [$this, 'advanced_superposition_weights'],
                'default_weight' => 0.40
            ]
        ];

 // Load custom attribution weights
        $this->attribution_weights = get_option('affcd_attribution_weights', [
            'last_click' => 0.15,
            'first_click' => 0.10,
            'linear' => 0.20,
            'time_decay' => 0.25,
            'position_based' => 0.15,
            'data_driven' => 0.35,
            'advanced_superposition' => 0.40
        ]);
    }

    /**
     * Track attribution touchpoint
     */
    public function track_attribution_touchpoint($form_data, $form_id, $plugin_type) {
        $touchpoint_data = [
            'type' => 'form_submission',
            'form_id' => $form_id,
            'plugin_type' => $plugin_type,
            'timestamp' => current_time('mysql'),
            'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => AFFCD_Utils::get_client_ip(),
            'session_id' => $this->get_session_id(),
            'affiliate_id' => $this->detect_affiliate_from_touchpoint(),
            'campaign_data' => $this->extract_campaign_data(),
            'interaction_quality' => $this->calculate_interaction_quality($form_data),
            'conversion_probability' => $this->predict_conversion_probability($form_data)
        ];

        $this->store_attribution_touchpoint($touchpoint_data);
        $this->update_advanced_states($touchpoint_data);
    }

    /**
     * Store attribution touchpoint
     */
    private function store_attribution_touchpoint($touchpoint_data) {
        global $wpdb;

        $touchpoint_id = $wpdb->insert(
            $wpdb->prefix . 'affcd_attribution_touchpoints',
            [
                'session_id' => $touchpoint_data['session_id'],
                'affiliate_id' => $touchpoint_data['affiliate_id'],
                'touchpoint_type' => $touchpoint_data['type'],
                'page_url' => $touchpoint_data['page_url'],
                'referrer' => $touchpoint_data['referrer'],
                'campaign_data' => json_encode($touchpoint_data['campaign_data']),
                'interaction_quality' => $touchpoint_data['interaction_quality'],
                'conversion_probability' => $touchpoint_data['conversion_probability'],
                'ip_address' => $touchpoint_data['ip_address'],
                'user_agent' => $touchpoint_data['user_agent'],
                'metadata' => json_encode($touchpoint_data),
                'timestamp' => $touchpoint_data['timestamp'],
                'site_url' => home_url()
            ]
        );

        return $wpdb->insert_id;
    }

    /**
     * Update advanced states for attribution
     */
    private function update_advanced_states($touchpoint_data) {
        $session_id = $touchpoint_data['session_id'];
        
        // Get existing advanced state for this session
        $advanced_state = $this->get_advanced_state($session_id);
        
        // Calculate new advanced probabilities
        $new_probabilities = $this->calculate_advanced_probabilities($advanced_state, $touchpoint_data);
        
        // Update advanced state
        $this->store_advanced_state($session_id, $new_probabilities);
    }

    /**
     * Get advanced state for session
     */
    private function get_advanced_state($session_id) {
        global $wpdb;

        $state = $wpdb->get_var($wpdb->prepare("
            SELECT advanced_state FROM {$wpdb->prefix}affcd_advanced_states 
            WHERE session_id = %s
        ", $session_id));

        return $state ? json_decode($state, true) : [
            'affiliate_probabilities' => [],
            'touchpoint_weights' => [],
            'conversion_likelihood' => 0.5,
            'attribution_entropy' => 1.0
        ];
    }

    /**
     * Calculate advanced probabilities
     */
    private function calculate_advanced_probabilities($current_state, $touchpoint_data) {
        $affiliate_id = $touchpoint_data['affiliate_id'];
        $interaction_quality = $touchpoint_data['interaction_quality'];
        $conversion_probability = $touchpoint_data['conversion_probability'];

        // Initialize if first touchpoint
        if (empty($current_state['affiliate_probabilities'])) {
            $current_state['affiliate_probabilities'] = [];
            $current_state['touchpoint_weights'] = [];
        }

        // Update affiliate probability using advanced superposition
        if ($affiliate_id) {
            $current_prob = $current_state['affiliate_probabilities'][$affiliate_id] ?? 0;
            
            // Advanced interference calculation
            $new_prob = $this->advanced_interference($current_prob, $interaction_quality, $conversion_probability);
            
            // Normalize probabilities to maintain advanced coherence
            $current_state['affiliate_probabilities'][$affiliate_id] = $new_prob;
            $current_state = $this->normalize_advanced_probabilities($current_state);
        }

        // Update conversion likelihood
        $current_state['conversion_likelihood'] = min(
            $current_state['conversion_likelihood'] + ($conversion_probability * 0.1),
            1.0
        );

        // Calculate attribution entropy (measure of uncertainty)
        $current_state['attribution_entropy'] = $this->calculate_attribution_entropy($current_state['affiliate_probabilities']);

        // Store touchpoint weight
        $touchpoint_weight = $this->calculate_touchpoint_weight($touchpoint_data, $current_state);
        $current_state['touchpoint_weights'][] = $touchpoint_weight;

        return $current_state;
    }

    /**
     * Advanced interference calculation
     */
    private function advanced_interference($current_prob, $quality, $conversion_prob) {
        // Advanced wave function interference
        $amplitude = sqrt($current_prob);
        $new_amplitude = sqrt($conversion_prob * $quality);
        
        // Constructive/destructive interference
        $phase_difference = $this->calculate_phase_difference($quality);
        $interference = $amplitude + $new_amplitude * cos($phase_difference);
        
        // Convert back to probability
        return min(pow($interference, 2), 1.0);
    }

    /**
     * Calculate phase difference for advanced interference
     */
    private function calculate_phase_difference($quality) {
        // High quality interactions have constructive interference
        // Low quality interactions have destructive interference
        return ($quality - 0.5) * pi();
    }

    /**
     * Normalize advanced probabilities
     */
    private function normalize_advanced_probabilities($state) {
        $total_prob = array_sum($state['affiliate_probabilities']);
        
        if ($total_prob > 1.0) {
            foreach ($state['affiliate_probabilities'] as $affiliate_id => $prob) {
                $state['affiliate_probabilities'][$affiliate_id] = $prob / $total_prob;
            }
        }

        return $state;
    }

    /**
     * Calculate attribution entropy
     */
    private function calculate_attribution_entropy($probabilities) {
        if (empty($probabilities)) {
            return 1.0;
        }

        $entropy = 0;
        foreach ($probabilities as $prob) {
            if ($prob > 0) {
                $entropy -= $prob * log($prob, 2);
            }
        }

        return $entropy;
    }

    /**
     * Store advanced state
     */
    private function store_advanced_state($session_id, $advanced_state) {
        global $wpdb;

        $wpdb->replace(
            $wpdb->prefix . 'affcd_advanced_states',
            [
                'session_id' => $session_id,
                'advanced_state' => json_encode($advanced_state),
                'last_updated' => current_time('mysql'),
                'site_url' => home_url()
            ]
        );
    }

    /**
     * Finalize attribution on conversion
     */
    public function finalize_attribution($order_id) {
        $session_id = $this->get_session_id();
        $conversion_value = $this->get_conversion_value($order_id);
        
        // Get all touchpoints for this session
        $touchpoints = $this->get_session_touchpoints($session_id);
        
        if (empty($touchpoints)) {
            return;
        }

        // Get advanced state
        $advanced_state = $this->get_advanced_state($session_id);

        // Calculate attribution for each model
        $attribution_results = [];
        foreach ($this->attribution_models as $model_name => $model) {
            $attribution_results[$model_name] = $this->calculate_model_attribution(
                $touchpoints, 
                $conversion_value, 
                $model, 
                $advanced_state
            );
        }

        // Calculate weighted final attribution
        $final_attribution = $this->calculate_weighted_attribution($attribution_results);

        // Store attribution results
        $this->store_attribution_results($order_id, $session_id, $attribution_results, $final_attribution);

        // Send to master site
        $this->sync_attribution_with_master($order_id, $final_attribution, $attribution_results);
    }

    /**
     * Calculate model-specific attribution
     */
    private function calculate_model_attribution($touchpoints, $conversion_value, $model, $advanced_state) {
        $weight_function = $model['weight_function'];
        $weights = call_user_func($weight_function, $touchpoints, $advanced_state);
        
        $attribution = [];
        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = $touchpoint['affiliate_id'];
            if ($affiliate_id) {
                $attributed_value = $conversion_value * $weights[$index];
                
                if (!isset($attribution[$affiliate_id])) {
                    $attribution[$affiliate_id] = 0;
                }
                $attribution[$affiliate_id] += $attributed_value;
            }
        }

        return $attribution;
    }

    /**
     * Last click attribution weights
     */
    private function last_click_weights($touchpoints, $advanced_state) {
        $weights = array_fill(0, count($touchpoints), 0);
        
        // Find last touchpoint with affiliate
        for ($i = count($touchpoints) - 1; $i >= 0; $i--) {
            if (!empty($touchpoints[$i]['affiliate_id'])) {
                $weights[$i] = 1.0;
                break;
            }
        }

        return $weights;
    }

    /**
     * First click attribution weights
     */
    private function first_click_weights($touchpoints, $advanced_state) {
        $weights = array_fill(0, count($touchpoints), 0);
        
        // Find first touchpoint with affiliate
        foreach ($touchpoints as $index => $touchpoint) {
            if (!empty($touchpoint['affiliate_id'])) {
                $weights[$index] = 1.0;
                break;
            }
        }

        return $weights;
    }

    /**
     * Linear attribution weights
     */
    private function linear_weights($touchpoints, $advanced_state) {
        $affiliate_touchpoints = array_filter($touchpoints, function($tp) {
            return !empty($tp['affiliate_id']);
        });

        $count = count($affiliate_touchpoints);
        if ($count === 0) {
            return array_fill(0, count($touchpoints), 0);
        }

        $weight_per_touchpoint = 1.0 / $count;
        $weights = [];

        foreach ($touchpoints as $touchpoint) {
            $weights[] = !empty($touchpoint['affiliate_id']) ? $weight_per_touchpoint : 0;
        }

        return $weights;
    }

    /**
     * Time decay attribution weights
     */
    private function time_decay_weights($touchpoints, $advanced_state) {
        $weights = [];
        $total_weight = 0;
        $decay_rate = 0.7; // 7-day half-life

        $conversion_time = time();

        // Calculate decay weights
        foreach ($touchpoints as $touchpoint) {
            if (empty($touchpoint['affiliate_id'])) {
                $weights[] = 0;
                continue;
            }

            $touchpoint_time = strtotime($touchpoint['timestamp']);
            $time_diff_days = ($conversion_time - $touchpoint_time) / 86400;
            
            $weight = pow($decay_rate, $time_diff_days);
            $weights[] = $weight;
            $total_weight += $weight;
        }

        // Normalize weights
        if ($total_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_weight;
            }
        }

        return $weights;
    }

    /**
     * Position-based (U-shaped) attribution weights
     */
    private function position_based_weights($touchpoints, $advanced_state) {
        $affiliate_indices = [];
        foreach ($touchpoints as $index => $touchpoint) {
            if (!empty($touchpoint['affiliate_id'])) {
                $affiliate_indices[] = $index;
            }
        }

        $weights = array_fill(0, count($touchpoints), 0);
        $count = count($affiliate_indices);

        if ($count === 0) {
            return $weights;
        }

        if ($count === 1) {
            $weights[$affiliate_indices[0]] = 1.0;
        } elseif ($count === 2) {
            $weights[$affiliate_indices[0]] = 0.5; // First
            $weights[$affiliate_indices[1]] = 0.5; // Last
        } else {
            // First gets 40%, last gets 40%, middle gets 20% split
            $weights[$affiliate_indices[0]] = 0.4; // First
            $weights[$affiliate_indices[$count - 1]] = 0.4; // Last
            
            $middle_weight = 0.2 / ($count - 2);
            for ($i = 1; $i < $count - 1; $i++) {
                $weights[$affiliate_indices[$i]] = $middle_weight;
            }
        }

        return $weights;
    }

/**
 * Data-driven attribution weights
 * Calculate optimal attribution weights using statistical analysis and heuristics,
 * uses historical performance data and multi-factor weighting
 *
 * @param array $touchpoints Array of customer touchpoints
 * @param array $advanced_state Current attribution state data
 * @return array Normalised attribution weights
 */
private function data_driven_weights($touchpoints, $advanced_state) {
    if (empty($touchpoints)) {
        return [];
    }
    
    $weights = [];
    $total_weight = 0;
    $touchpoint_count = count($touchpoints);
    
    // Calculate historical conversion rates for context
    $historical_context = $this->get_historical_attribution_context($touchpoints);
    
    // Analyse the complete customer journey
    $journey_analysis = $this->analyse_journey_structure($touchpoints);
    
    foreach ($touchpoints as $index => $touchpoint) {
        if (empty($touchpoint['affiliate_id'])) {
            $weights[] = 0;
            continue;
        }
        
        // Base quality score (0-1)
        $quality_score = floatval($touchpoint['interaction_quality'] ?? 0.5);
        
        // Conversion probability (0-1)
        $conversion_prob = floatval($touchpoint['conversion_probability'] ?? 0.5);
        
        // Recency factor - exponential decay (0-1)
        $recency_factor = $this->calculate_recency_factor($touchpoint['timestamp']);
        
        // Position factor - considers both early and late touchpoints (0-1)
        $position_factor = $this->calculate_position_factor($index, $touchpoint_count);
        
        // Channel effectiveness factor based on historical performance (0-2)
        $channel_factor = $this->calculate_channel_effectiveness(
            $touchpoint['channel'] ?? 'unknown',
            $historical_context
        );
        
        // Touchpoint type factor - different types have different influence (0.5-1.5)
        $type_factor = $this->calculate_touchpoint_type_factor($touchpoint['type'] ?? 'click');
        
        // Engagement depth factor - measures interaction quality (0-1.5)
        $engagement_factor = $this->calculate_engagement_depth($touchpoint);
        
        // Incremental value factor - contribution beyond other touchpoints (0-1.2)
        $incremental_factor = $this->calculate_incremental_value(
            $touchpoint,
            $touchpoints,
            $index
        );
        
        // Journey context factor - how this fits in the overall journey (0.8-1.2)
        $journey_context_factor = $this->calculate_journey_context_factor(
            $touchpoint,
            $journey_analysis,
            $index
        );
        
        // Affiliate performance factor - historical conversion rate (0.5-1.5)
        $affiliate_factor = $this->calculate_affiliate_performance_factor(
            $touchpoint['affiliate_id'],
            $historical_context
        );
        
        // Time-to-conversion factor - timing appropriateness (0.8-1.2)
        $timing_factor = $this->calculate_timing_appropriateness(
            $touchpoint['timestamp'],
            $touchpoints
        );
        
        // Calculate composite weight using multiplicative model
        $weight = $quality_score 
                * $conversion_prob 
                * $recency_factor 
                * $position_factor 
                * $channel_factor 
                * $type_factor 
                * $engagement_factor 
                * $incremental_factor 
                * $journey_context_factor 
                * $affiliate_factor 
                * $timing_factor;
        
        // Apply advanced state adjustments if available
        if (!empty($advanced_state['attribution_boost'][$index])) {
            $weight *= floatval($advanced_state['attribution_boost'][$index]);
        }
        
        // Store individual factor contributions for debugging
        $touchpoint['weight_factors'] = [
            'quality' => $quality_score,
            'conversion_prob' => $conversion_prob,
            'recency' => $recency_factor,
            'position' => $position_factor,
            'channel' => $channel_factor,
            'type' => $type_factor,
            'engagement' => $engagement_factor,
            'incremental' => $incremental_factor,
            'journey_context' => $journey_context_factor,
            'affiliate' => $affiliate_factor,
            'timing' => $timing_factor
        ];
        
        $weights[] = $weight;
        $total_weight += $weight;
    }
    
    // Normalise weights to sum to 1.0
    if ($total_weight > 0) {
        foreach ($weights as $key => &$weight) {
            $weight /= $total_weight;
            
            // Apply minimum threshold (no touchpoint gets less than 1%)
            $min_weight = 0.01;
            if ($weight < $min_weight && $weight > 0) {
                $weight = $min_weight;
            }
        }
        
        // Re-normalise after applying minimum thresholds
        $adjusted_total = array_sum($weights);
        if ($adjusted_total > 0 && abs($adjusted_total - 1.0) > 0.001) {
            foreach ($weights as &$weight) {
                $weight /= $adjusted_total;
            }
        }
    }
    
    // Store attribution decision data for analysis
    $this->store_attribution_decision($touchpoints, $weights, $advanced_state);
    
    return $weights;
}

/**
 * Get historical attribution context for weighting decisions
 *
 * @param array $touchpoints Current touchpoints
 * @return array Historical context data
 */
private function get_historical_attribution_context($touchpoints) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_attribution_insights';
    
    // Get channel performance data
    $channels = array_unique(array_column($touchpoints, 'channel'));
    $channel_performance = [];
    
    foreach ($channels as $channel) {
        if (empty($channel)) continue;
        
        $perf = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(avg_conversion_value) as avg_value,
                AVG(touchpoint_count) as avg_touchpoints,
                COUNT(*) as total_conversions
             FROM {$table_name}
             WHERE JSON_EXTRACT(attribution_model, '$.channel') = %s
             AND last_updated >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
            $channel
        ), ARRAY_A);
        
        $channel_performance[$channel] = [
            'avg_value' => floatval($perf['avg_value'] ?? 0),
            'avg_touchpoints' => floatval($perf['avg_touchpoints'] ?? 0),
            'conversions' => intval($perf['total_conversions'] ?? 0)
        ];
    }
    
    // Get affiliate performance data
    $affiliate_ids = array_unique(array_column($touchpoints, 'affiliate_id'));
    $affiliate_performance = [];
    
    foreach ($affiliate_ids as $affiliate_id) {
        if (empty($affiliate_id)) continue;
        
        $perf = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                total_conversions,
                total_value,
                avg_conversion_value
             FROM {$table_name}
             WHERE affiliate_id = %d
             ORDER BY last_updated DESC
             LIMIT 1",
            $affiliate_id
        ), ARRAY_A);
        
        $affiliate_performance[$affiliate_id] = [
            'total_conversions' => intval($perf['total_conversions'] ?? 0),
            'total_value' => floatval($perf['total_value'] ?? 0),
            'avg_value' => floatval($perf['avg_conversion_value'] ?? 0)
        ];
    }
    
    return [
        'channels' => $channel_performance,
        'affiliates' => $affiliate_performance
    ];
}

/**
 * Analyse journey structure to understand customer path
 *
 * @param array $touchpoints Array of touchpoints
 * @return array Journey analysis data
 */
private function analyse_journey_structure($touchpoints) {
    $analysis = [
        'total_touchpoints' => count($touchpoints),
        'journey_duration' => 0,
        'channel_diversity' => 0,
        'engagement_trend' => 'stable',
        'key_moments' => []
    ];
    
    if (empty($touchpoints)) {
        return $analysis;
    }
    
    // Calculate journey duration
    $first_timestamp = strtotime($touchpoints[0]['timestamp']);
    $last_timestamp = strtotime($touchpoints[count($touchpoints) - 1]['timestamp']);
    $analysis['journey_duration'] = ($last_timestamp - $first_timestamp) / 3600; // hours
    
    // Calculate channel diversity
    $unique_channels = array_unique(array_column($touchpoints, 'channel'));
    $analysis['channel_diversity'] = count($unique_channels);
    
    // Analyse engagement trend (increasing, decreasing, stable)
    $early_engagement = 0;
    $late_engagement = 0;
    $midpoint = floor(count($touchpoints) / 2);
    
    for ($i = 0; $i < count($touchpoints); $i++) {
        $engagement = floatval($touchpoints[$i]['interaction_quality'] ?? 0.5);
        
        if ($i < $midpoint) {
            $early_engagement += $engagement;
        } else {
            $late_engagement += $engagement;
        }
    }
    
    $early_avg = $midpoint > 0 ? $early_engagement / $midpoint : 0;
    $late_avg = (count($touchpoints) - $midpoint) > 0 ? $late_engagement / (count($touchpoints) - $midpoint) : 0;
    
    if ($late_avg > $early_avg * 1.2) {
        $analysis['engagement_trend'] = 'increasing';
    } elseif ($late_avg < $early_avg * 0.8) {
        $analysis['engagement_trend'] = 'decreasing';
    }
    
    // Identify key moments (high engagement points)
    foreach ($touchpoints as $index => $touchpoint) {
        $quality = floatval($touchpoint['interaction_quality'] ?? 0);
        if ($quality >= 0.8) {
            $analysis['key_moments'][] = $index;
        }
    }
    
    return $analysis;
}

/**
 * Calculate recency factor using exponential decay
 *
 * @param string $timestamp Touchpoint timestamp
 * @return float Recency factor between 0 and 1
 */
private function calculate_recency_factor($timestamp) {
    $touchpoint_time = strtotime($timestamp);
    $current_time = current_time('timestamp');
    
    // Time difference in hours
    $hours_ago = ($current_time - $touchpoint_time) / 3600;
    
    // Exponential decay with half-life of 168 hours (7 days)
    $half_life = 168;
    $decay_rate = log(2) / $half_life;
    
    $recency_factor = exp(-$decay_rate * $hours_ago);
    
    // Ensure minimum value of 0.1 for very old touchpoints
    return max($recency_factor, 0.1);
}

/**
 * Calculate position factor considering U-shaped attribution bias
 *
 * @param int $index Touchpoint index
 * @param int $total_count Total number of touchpoints
 * @return float Position factor between 0.5 and 1.5
 */
private function calculate_position_factor($index, $total_count) {
    if ($total_count <= 1) {
        return 1.0;
    }
    
    // First touchpoint gets 40% boost (discovery/awareness)
    if ($index === 0) {
        return 1.4;
    }
    
    // Last touchpoint gets 50% boost (conversion trigger)
    if ($index === $total_count - 1) {
        return 1.5;
    }
    
    // Middle touchpoints get less weight but still valuable
    // U-shaped curve: higher at start and end, lower in middle
    $normalized_position = $index / ($total_count - 1);
    
    // Parabolic function: y = -0.6x² + 0.6x + 0.8
    // This gives values between 0.8-1.0 for middle touchpoints
    $position_factor = -0.6 * pow($normalized_position - 0.5, 2) + 0.95;
    
    return max($position_factor, 0.5);
}

/**
 * Calculate channel effectiveness factor based on historical performance
 *
 * @param string $channel Channel name
 * @param array $historical_context Historical performance data
 * @return float Channel factor between 0.5 and 2.0
 */
private function calculate_channel_effectiveness($channel, $historical_context) {
    if (empty($channel) || !isset($historical_context['channels'][$channel])) {
        return 1.0; // Neutral if no data
    }
    
    $channel_data = $historical_context['channels'][$channel];
    $conversions = intval($channel_data['conversions']);
    
    // Need sufficient data for reliable factor
    if ($conversions < 10) {
        return 1.0;
    }
    
    $avg_value = floatval($channel_data['avg_value']);
    
    // Calculate factor based on conversion value
    // Channels with higher average values get higher weights
    if ($avg_value >= 500) {
        return 1.8;
    } elseif ($avg_value >= 250) {
        return 1.5;
    } elseif ($avg_value >= 100) {
        return 1.2;
    } elseif ($avg_value >= 50) {
        return 1.0;
    } elseif ($avg_value >= 20) {
        return 0.8;
    } else {
        return 0.6;
    }
}

/**
 * Calculate touchpoint type factor
 *
 * @param string $type Touchpoint type
 * @return float Type factor between 0.5 and 1.5
 */
private function calculate_touchpoint_type_factor($type) {
    $type_weights = [
        'purchase_intent' => 1.5,    // Viewing pricing, checkout
        'high_engagement' => 1.4,     // Demo request, trial signup
        'content_download' => 1.2,    // Whitepaper, guide download
        'form_submission' => 1.3,     // Contact forms, quotes
        'product_view' => 1.1,        // Product page views
        'click' => 1.0,               // Standard clicks
        'impression' => 0.6,          // Ad impressions
        'email_open' => 0.7,          // Email opens
        'social_engagement' => 0.8    // Social interactions
    ];
    
    return $type_weights[$type] ?? 1.0;
}

/**
 * Calculate engagement depth factor
 *
 * @param array $touchpoint Touchpoint data
 * @return float Engagement factor between 0.5 and 1.5
 */
private function calculate_engagement_depth($touchpoint) {
    $base_factor = 1.0;
    
    // Time spent
    $time_spent = intval($touchpoint['time_spent'] ?? 0);
    if ($time_spent >= 300) {
        $base_factor += 0.3; // 5+ minutes
    } elseif ($time_spent >= 120) {
        $base_factor += 0.2; // 2-5 minutes
    } elseif ($time_spent >= 30) {
        $base_factor += 0.1; // 30s-2 minutes
    }
    
    // Pages viewed in session
    $pages_viewed = intval($touchpoint['pages_viewed'] ?? 1);
    if ($pages_viewed >= 5) {
        $base_factor += 0.2;
    } elseif ($pages_viewed >= 3) {
        $base_factor += 0.1;
    }
    
    // Interactions (clicks, scrolls, etc.)
    $interactions = intval($touchpoint['interactions'] ?? 0);
    if ($interactions >= 10) {
        $base_factor += 0.2;
    } elseif ($interactions >= 5) {
        $base_factor += 0.1;
    }
    
    return min($base_factor, 1.5);
}

/**
 * Calculate incremental value contributed by this touchpoint
 *
 * @param array $touchpoint Current touchpoint
 * @param array $all_touchpoints All touchpoints in journey
 * @param int $current_index Current touchpoint index
 * @return float Incremental factor between 0.7 and 1.2
 */
private function calculate_incremental_value($touchpoint, $all_touchpoints, $current_index) {
    $incremental_factor = 1.0;
    
    $current_channel = $touchpoint['channel'] ?? '';
    $current_affiliate = $touchpoint['affiliate_id'] ?? 0;
    
    // Check if this is a new channel in the journey
    $new_channel = true;
    for ($i = 0; $i < $current_index; $i++) {
        if (($all_touchpoints[$i]['channel'] ?? '') === $current_channel) {
            $new_channel = false;
            break;
        }
    }
    
    if ($new_channel) {
        $incremental_factor += 0.15; // New channel adds value
    }
    
    // Check if this affiliate brought new value
    $repeated_affiliate = false;
    for ($i = max(0, $current_index - 3); $i < $current_index; $i++) {
        if (($all_touchpoints[$i]['affiliate_id'] ?? 0) === $current_affiliate) {
            $repeated_affiliate = true;
            break;
        }
    }
    
    if ($repeated_affiliate) {
        $incremental_factor -= 0.15; // Repeated recent touchpoint less valuable
    }
    
    // Check for journey progression (moved to higher-intent pages)
    if ($current_index > 0) {
        $prev_quality = floatval($all_touchpoints[$current_index - 1]['interaction_quality'] ?? 0.5);
        $current_quality = floatval($touchpoint['interaction_quality'] ?? 0.5);
        
        if ($current_quality > $prev_quality + 0.2) {
            $incremental_factor += 0.1; // Significant quality increase
        }
    }
    
    return max(min($incremental_factor, 1.2), 0.7);
}

/**
 * Calculate journey context factor
 *
 * @param array $touchpoint Current touchpoint
 * @param array $journey_analysis Journey structure analysis
 * @param int $index Touchpoint index
 * @return float Context factor between 0.8 and 1.2
 */
private function calculate_journey_context_factor($touchpoint, $journey_analysis, $index) {
    $factor = 1.0;
    
    // Boost key moment touchpoints
    if (in_array($index, $journey_analysis['key_moments'])) {
        $factor += 0.15;
    }
    
    // Adjust based on engagement trend
    $total = $journey_analysis['total_touchpoints'];
    $is_late_stage = $index >= ($total * 0.7);
    
    if ($journey_analysis['engagement_trend'] === 'increasing' && $is_late_stage) {
        $factor += 0.1; // Late touchpoints more important in growing engagement
    } elseif ($journey_analysis['engagement_trend'] === 'decreasing' && !$is_late_stage) {
        $factor += 0.1; // Early touchpoints more important in declining engagement
    }
    
    // Penalise if journey is too long (fatigue factor)
    if ($journey_analysis['journey_duration'] > 720) { // 30 days
        $factor -= 0.1;
    }
    
    return max(min($factor, 1.2), 0.8);
}

/**
 * Calculate affiliate performance factor
 *
 * @param int $affiliate_id Affiliate ID
 * @param array $historical_context Historical performance data
 * @return float Performance factor between 0.5 and 1.5
 */
private function calculate_affiliate_performance_factor($affiliate_id, $historical_context) {
    if (empty($affiliate_id) || !isset($historical_context['affiliates'][$affiliate_id])) {
        return 1.0;
    }
    
    $perf = $historical_context['affiliates'][$affiliate_id];
    $conversions = intval($perf['total_conversions']);
    
    if ($conversions < 5) {
        return 1.0; // Insufficient data
    }
    
    $avg_value = floatval($perf['avg_value']);
    
    // High-performing affiliates get boosted weights
    if ($avg_value >= 500) {
        return 1.5;
    } elseif ($avg_value >= 250) {
        return 1.3;
    } elseif ($avg_value >= 100) {
        return 1.1;
    } elseif ($avg_value >= 50) {
        return 1.0;
    } elseif ($avg_value >= 20) {
        return 0.8;
    } else {
        return 0.6;
    }
}

/**
 * Calculate timing appropriateness factor
 *
 * @param string $timestamp Touchpoint timestamp
 * @param array $all_touchpoints All journey touchpoints
 * @return float Timing factor between 0.8 and 1.2
 */
private function calculate_timing_appropriateness($timestamp, $all_touchpoints) {
    $touchpoint_time = strtotime($timestamp);
    $hour = intval(date('H', $touchpoint_time));
    $day_of_week = intval(date('N', $touchpoint_time)); // 1-7
    
    $factor = 1.0;
    
    // Business hours boost (9-17)
    if ($hour >= 9 && $hour <= 17) {
        $factor += 0.1;
    }
    
    // Weekday boost
    if ($day_of_week >= 1 && $day_of_week <= 5) {
        $factor += 0.05;
    }
    
    // Check spacing between touchpoints
    if (count($all_touchpoints) > 1) {
        $timestamps = array_column($all_touchpoints, 'timestamp');
        $timestamps = array_map('strtotime', $timestamps);
        sort($timestamps);
        
        $current_pos = array_search($touchpoint_time, $timestamps);
        
        if ($current_pos > 0) {
            $time_since_prev = ($touchpoint_time - $timestamps[$current_pos - 1]) / 3600;
            
            // Optimal spacing: 2-48 hours
            if ($time_since_prev >= 2 && $time_since_prev <= 48) {
                $factor += 0.05;
            }
        }
    }
    
    return max(min($factor, 1.2), 0.8);
}

/**
 * Store attribution decision for analysis and improvement
 *
 * @param array $touchpoints Touchpoints in journey
 * @param array $weights Calculated weights
 * @param array $advanced_state Attribution state
 * @return void
 */
private function store_attribution_decision($touchpoints, $weights, $advanced_state) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_attribution_decisions';
    
    $decision_data = [
        'touchpoint_count' => count($touchpoints),
        'weights' => $weights,
        'advanced_state' => $advanced_state,
        'timestamp' => current_time('mysql')
    ];
    
    $wpdb->insert(
        $table_name,
        [
            'decision_data' => json_encode($decision_data),
            'created_at' => current_time('mysql')
        ],
        ['%s', '%s']
    );
}

    /**
     * Advanced superposition attribution weights
     */
    private function advanced_superposition_weights($touchpoints, $advanced_state) {
        if (empty($advanced_state['affiliate_probabilities'])) {
            return $this->linear_weights($touchpoints, $advanced_state);
        }

        $weights = [];
        $total_advanced_weight = 0;

        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = $touchpoint['affiliate_id'] ?? null;
            
            if (!$affiliate_id) {
                $weights[] = 0;
                continue;
            }

            // Get advanced probability for this affiliate
            $advanced_prob = $advanced_state['affiliate_probabilities'][$affiliate_id] ?? 0;
            
            // Apply advanced uncertainty principle
            $uncertainty = $advanced_state['attribution_entropy'] ?? 1.0;
            $advanced_weight = $advanced_prob * (1 + $uncertainty * 0.1);
            
            // Apply touchpoint quality modifier
            $quality = $touchpoint['interaction_quality'] ?? 0.5;
            $advanced_weight *= (0.5 + $quality * 0.5);

            $weights[] = $advanced_weight;
            $total_advanced_weight += $advanced_weight;
        }

        // Advanced normalization (maintains superposition)
        if ($total_advanced_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_advanced_weight;
            }
        }

        return $weights;
    }

    /**
     * Calculate weighted final attribution
     */
    private function calculate_weighted_attribution($attribution_results) {
        $final_attribution = [];

        foreach ($attribution_results as $model_name => $model_attribution) {
            $model_weight = $this->attribution_weights[$model_name] ?? 0;
            
            foreach ($model_attribution as $affiliate_id => $value) {
                if (!isset($final_attribution[$affiliate_id])) {
                    $final_attribution[$affiliate_id] = 0;
                }
                $final_attribution[$affiliate_id] += $value * $model_weight;
            }
        }

        return $final_attribution;
    }

    /**
     * Store attribution results
     */
    private function store_attribution_results($order_id, $session_id, $model_results, $final_attribution) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'affcd_attribution_results',
            [
                'order_id' => $order_id,
                'session_id' => $session_id,
                'model_results' => json_encode($model_results),
                'final_attribution' => json_encode($final_attribution),
                'attribution_confidence' => $this->calculate_attribution_confidence($model_results),
                'advanced_entropy' => $this->get_advanced_state($session_id)['attribution_entropy'] ?? 1.0,
                'created_at' => current_time('mysql'),
                'site_url' => home_url()
            ]
        );
    }

    /**
     * Calculate attribution confidence
     */
    private function calculate_attribution_confidence($model_results) {
        if (empty($model_results)) {
            return 0;
        }

        // Calculate consistency across models
        $affiliate_totals = [];
        foreach ($model_results as $model_name => $results) {
            foreach ($results as $affiliate_id => $value) {
                if (!isset($affiliate_totals[$affiliate_id])) {
                    $affiliate_totals[$affiliate_id] = [];
                }
                $affiliate_totals[$affiliate_id][$model_name] = $value;
            }
        }

        // Calculate variance for each affiliate
        $total_variance = 0;
        $affiliate_count = 0;

        foreach ($affiliate_totals as $affiliate_id => $model_values) {
            if (count($model_values) > 1) {
                $mean = array_sum($model_values) / count($model_values);
                $variance = 0;
                
                foreach ($model_values as $value) {
                    $variance += pow($value - $mean, 2);
                }
                $variance /= count($model_values);
                
                $total_variance += $variance;
                $affiliate_count++;
            }
        }

        if ($affiliate_count === 0) {
            return 1.0;
        }

        $avg_variance = $total_variance / $affiliate_count;
        
        // Convert variance to confidence (inverse relationship)
        return max(0, min(1, 1 - ($avg_variance / 100)));
    }

    /**
     * Sync attribution with master site
     */
    private function sync_attribution_with_master($order_id, $final_attribution, $model_results) {
        $sync_data = [
            'order_id' => $order_id,
            'site_url' => home_url(),
            'final_attribution' => $final_attribution,
            'model_results' => $model_results,
            'attribution_timestamp' => current_time('mysql'),
            'advanced_enhanced' => true
        ];

        $this->parent->api_client->sync_attribution_data($sync_data);
    }

    /**
     * Inject attribution tracking script
     */
    public function inject_attribution_tracking() {
        ?>
        <script>
        // Advanced Attribution Tracking
        (function() {
            var attributionData = {
                session_id: affcdSatellite.sessionId,
                page_interactions: [],
                micro_conversions: [],
                engagement_score: 0,
                conversion_signals: []
            };

            // Track micro-conversions
            trackMicroConversions();
            
            // Track engagement quality
            trackEngagementQuality();
            
            // Track conversion signals
            trackConversionSignals();

            function trackMicroConversions() {
                // Track scroll milestones
                var scrollMilestones = [25, 50, 75, 90];
                var trackedMilestones = [];

                jQuery(window).scroll(function() {
                    var scrollPercent = (jQuery(window).scrollTop() / (jQuery(document).height() - jQuery(window).height())) * 100;
                    
                    scrollMilestones.forEach(function(milestone) {
                        if (scrollPercent >= milestone && trackedMilestones.indexOf(milestone) === -1) {
                            trackedMilestones.push(milestone);
                            attributionData.micro_conversions.push({
                                type: 'scroll_milestone',
                                value: milestone,
                                timestamp: Date.now()
                            });
                        }
                    });
                });

                // Track time on page milestones
                var timeMilestones = [30, 60, 120, 300]; // seconds
                var startTime = Date.now();

                timeMilestones.forEach(function(milestone) {
                    setTimeout(function() {
                        if (document.visibilityState === 'visible') {
                            attributionData.micro_conversions.push({
                                type: 'time_milestone',
                                value: milestone,
                                timestamp: Date.now()
                            });
                        }
                    }, milestone * 1000);
                });

                // Track interaction events
                jQuery(document).on('click', 'a, button', function() {
                    attributionData.page_interactions.push({
                        type: 'click',
                        element: this.tagName,
                        timestamp: Date.now()
                    });
                });
            }

            function trackEngagementQuality() {
                var engagementFactors = {
                    timeOnPage: 0,
                    scrollDepth: 0,
                    interactions: 0,
                    returnVisitor: localStorage.getItem('affcd_return_visitor') ? 1 : 0
                };

                // Mark as return visitor
                if (!localStorage.getItem('affcd_return_visitor')) {
                    localStorage.setItem('affcd_return_visitor', Date.now());
                }

                // Calculate engagement score periodically
                setInterval(function() {
                    engagementFactors.timeOnPage = (Date.now() - startTime) / 1000;
                    engagementFactors.scrollDepth = (jQuery(window).scrollTop() / (jQuery(document).height() - jQuery(window).height())) * 100;
                    engagementFactors.interactions = attributionData.page_interactions.length;

                    // Calculate weighted engagement score
                    var score = (
                        Math.min(engagementFactors.timeOnPage / 300, 1) * 0.3 +
                        Math.min(engagementFactors.scrollDepth / 100, 1) * 0.3 +
                        Math.min(engagementFactors.interactions / 10, 1) * 0.2 +
                        engagementFactors.returnVisitor * 0.2
                    );

                    attributionData.engagement_score = score;
                }, 10000); // Update every 10 seconds
            }

            function trackConversionSignals() {
                // Track form focus events
                jQuery('form input, form textarea').on('focus', function() {
                    attributionData.conversion_signals.push({
                        type: 'form_focus',
                        form_id: jQuery(this).closest('form').attr('id'),
                        timestamp: Date.now()
                    });
                });

                // Track add to cart events (if WooCommerce)
                jQuery(document).on('click', '.add_to_cart_button', function() {
                    attributionData.conversion_signals.push({
                        type: 'add_to_cart_intent',
                        product_id: jQuery(this).data('product_id'),
                        timestamp: Date.now()
                    });
                });

                // Track checkout page visits
                if (window.location.href.indexOf('checkout') !== -1) {
                    attributionData.conversion_signals.push({
                        type: 'checkout_page_visit',
                        timestamp: Date.now()
                    });
                }
            }

            // Send attribution data periodically
            setInterval(function() {
                if (attributionData.micro_conversions.length > 0 || attributionData.conversion_signals.length > 0) {
                    jQuery.post(affcdSatellite.ajaxUrl, {
                        action: 'affcd_attribution_touchpoint',
                        nonce: affcdSatellite.nonce,
                        attribution_data: attributionData
                    });

                    // Clear sent data
                    attributionData.micro_conversions = [];
                    attributionData.conversion_signals = [];
                }
            }, 30000); // Every 30 seconds

            // Send final data on page unload
            window.addEventListener('beforeunload', function() {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(affcdSatellite.ajaxUrl, new URLSearchParams({
                        action: 'affcd_attribution_touchpoint',
                        nonce: affcdSatellite.nonce,
                        attribution_data: JSON.stringify(attributionData),
                        final_send: true
                    }));
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Helper methods
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['affcd_session_id'])) {
            $_SESSION['affcd_session_id'] = 'affcd_' . uniqid() . '_' . time();
        }
        
        return $_SESSION['affcd_session_id'];
    }

    private function detect_affiliate_from_touchpoint() {
        // Check URL parameters, cookies, etc.
        if (isset($_GET['ref'])) {
            return intval($_GET['ref']);
        }
        
        if (isset($_COOKIE['affcd_affiliate_id'])) {
            return intval($_COOKIE['affcd_affiliate_id']);
        }
        
        return get_option('affcd_default_affiliate_id', null);
    }

    private function extract_campaign_data() {
        return [
            'utm_source' => $_GET['utm_source'] ?? null,
            'utm_medium' => $_GET['utm_medium'] ?? null,
            'utm_campaign' => $_GET['utm_campaign'] ?? null,
            'utm_term' => $_GET['utm_term'] ?? null,
            'utm_content' => $_GET['utm_content'] ?? null
        ];
    }

    private function calculate_interaction_quality($form_data) {
        // Analyze form data quality
        $quality_score = 0.5; // Base score
        
        // Check for complete email
        if (!empty($form_data['email']) && filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $quality_score += 0.2;
        }
        
        // Check for phone number
        if (!empty($form_data['phone'])) {
            $quality_score += 0.1;
        }
        
        // Check for name completeness
        if (!empty($form_data['name']) && str_word_count($form_data['name']) >= 2) {
            $quality_score += 0.1;
        }
        
        return min($quality_score, 1.0);
    }


/**
 * Predict conversion probability for form submission
 * Uses statistical analysis and behavioural heuristics
 * Considers multiple signals to provide accurate probability estimation
 *
 * @param array $form_data Form submission data
 * @return float Probability between 0 and 1
 */
private function predict_conversion_probability($form_data) {
    $probability = 0.10; // Conservative base probability
    $signal_weights = [];
    
    // Intent signal analysis (weight: 0.30)
    $intent_score = $this->analyse_form_intent_signals($form_data);
    $probability += $intent_score * 0.30;
    $signal_weights['intent'] = $intent_score;
    
    // Email domain quality analysis (weight: 0.20)
    $email_score = $this->analyse_email_domain_quality($form_data);
    $probability += $email_score * 0.20;
    $signal_weights['email_quality'] = $email_score;
    
    // Form completion quality (weight: 0.15)
    $completion_score = $this->analyse_form_completion_quality($form_data);
    $probability += $completion_score * 0.15;
    $signal_weights['completion'] = $completion_score;
    
    // Behavioural context analysis (weight: 0.15)
    $behaviour_score = $this->analyse_behavioural_context($form_data);
    $probability += $behaviour_score * 0.15;
    $signal_weights['behaviour'] = $behaviour_score;
    
    // Historical conversion patterns (weight: 0.10)
    $historical_score = $this->analyse_historical_conversion_patterns($form_data);
    $probability += $historical_score * 0.10;
    $signal_weights['historical'] = $historical_score;
    
    // Temporal and contextual factors (weight: 0.10)
    $temporal_score = $this->analyse_temporal_context();
    $probability += $temporal_score * 0.10;
    $signal_weights['temporal'] = $temporal_score;
    
    // Apply confidence adjustment based on data availability
    $confidence_adjustment = $this->calculate_prediction_confidence($form_data);
    $probability *= $confidence_adjustment;
    
    // Store prediction metadata for model improvement
    $this->store_conversion_prediction_data($form_data, $probability, $signal_weights);
    
    // Ensure probability stays within valid range with reasonable bounds
    return max(0.01, min($probability, 0.95));
}

/**
 * Analyse form intent signals to determine purchase readiness
 *
 * @param array $form_data Form submission data
 * @return float Intent score between 0 and 1
 */
private function analyse_form_intent_signals($form_data) {
    $intent_score = 0.0;
    $form_content = strtolower(json_encode($form_data));
    
    // High-intent indicators with graduated weights
    $high_intent = [
        'purchase' => 0.95,
        'buy now' => 0.93,
        'quote' => 0.90,
        'pricing' => 0.85,
        'demo' => 0.82,
        'consultation' => 0.80,
        'trial' => 0.75,
        'implementation' => 0.78,
        'enterprise' => 0.77,
        'contract' => 0.88,
        'proposal' => 0.83
    ];
    
    $medium_intent = [
        'information' => 0.45,
        'learn more' => 0.48,
        'case study' => 0.52,
        'whitepaper' => 0.50,
        'webinar' => 0.55,
        'download' => 0.47,
        'ebook' => 0.46,
        'guide' => 0.49
    ];
    
    $low_intent = [
        'newsletter' => 0.15,
        'subscribe' => 0.18,
        'blog' => 0.12,
        'updates' => 0.14,
        'follow' => 0.10
    ];
    
    // Check for high-intent signals
    foreach ($high_intent as $signal => $weight) {
        if (stripos($form_content, $signal) !== false) {
            $intent_score = max($intent_score, $weight);
        }
    }
    
    // Check medium-intent if no high-intent found
    if ($intent_score < 0.60) {
        foreach ($medium_intent as $signal => $weight) {
            if (stripos($form_content, $signal) !== false) {
                $intent_score = max($intent_score, $weight);
            }
        }
    }
    
    // Check low-intent as baseline
    if ($intent_score < 0.20) {
        foreach ($low_intent as $signal => $weight) {
            if (stripos($form_content, $signal) !== false) {
                $intent_score = max($intent_score, $weight);
            }
        }
    }
    
    // Boost for multiple high-value fields
    $high_value_field_count = 0;
    $high_value_fields = ['company', 'job_title', 'phone', 'budget', 'timeline', 'team_size', 'industry'];
    
    foreach ($high_value_fields as $field) {
        if (!empty($form_data[$field])) {
            $high_value_field_count++;
        }
    }
    
    if ($high_value_field_count >= 4) {
        $intent_score *= 1.20;
    } elseif ($high_value_field_count >= 2) {
        $intent_score *= 1.10;
    }
    
    // Boost for urgency indicators
    $urgency_keywords = ['urgent', 'asap', 'immediately', 'soon', 'this week', 'this month'];
    foreach ($urgency_keywords as $keyword) {
        if (stripos($form_content, $keyword) !== false) {
            $intent_score *= 1.15;
            break;
        }
    }
    
    return min($intent_score, 1.0);
}

/**
 * Analyse email domain quality and business legitimacy
 *
 * @param array $form_data Form submission data
 * @return float Email quality score between 0 and 1
 */
private function analyse_email_domain_quality($form_data) {
    if (empty($form_data['email'])) {
        return 0.30; // Neutral for missing email
    }
    
    $email = strtolower(trim($form_data['email']));
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 0.05;
    }
    
    $domain = substr(strrchr($email, '@'), 1);
    
    // Free consumer email providers (lower conversion likelihood)
    $consumer_providers = [
        'gmail.com' => 0.30,
        'yahoo.com' => 0.25,
        'hotmail.com' => 0.28,
        'outlook.com' => 0.32,
        'aol.com' => 0.22,
        'icloud.com' => 0.29,
        'live.com' => 0.27,
        'mail.com' => 0.24,
        'protonmail.com' => 0.35,
        'gmx.com' => 0.23,
        'yandex.com' => 0.26,
        'zoho.com' => 0.33
    ];
    
    if (isset($consumer_providers[$domain])) {
        return $consumer_providers[$domain];
    }
    
    // Disposable/temporary email detection (very low conversion)
    $disposable_patterns = [
        'temp', 'disposable', 'trash', 'guerrilla', '10minute', 
        'throwaway', 'fake', 'spam', 'mailinator', 'tempmail'
    ];
    
    foreach ($disposable_patterns as $pattern) {
        if (stripos($domain, $pattern) !== false) {
            return 0.02;
        }
    }
    
    // Role-based emails (lower personal engagement)
    $role_prefixes = ['info@', 'admin@', 'support@', 'sales@', 'noreply@', 'contact@', 'help@'];
    foreach ($role_prefixes as $prefix) {
        if (strpos($email, $prefix) === 0) {
            return 0.45; // Still valuable but less personal
        }
    }
    
    // Corporate/business email (higher conversion likelihood)
    $business_score = 0.70;
    
    // Premium business TLDs
    $premium_tlds = [
        '.edu' => 1.15,
        '.gov' => 1.20,
        '.mil' => 1.18,
        '.ac.uk' => 1.12,
        '.edu.au' => 1.12,
        '.org' => 1.05
    ];
    
    foreach ($premium_tlds as $tld => $multiplier) {
        if (substr($domain, -strlen($tld)) === $tld) {
            $business_score *= $multiplier;
            break;
        }
    }
    
    // Check domain reputation and age
    $domain_reputation = $this->check_domain_reputation_score($domain);
    $business_score *= $domain_reputation;
    
    // Check for company name in email matching form data
    if (!empty($form_data['company'])) {
        $company_name = strtolower(preg_replace('/[^a-z0-9]/', '', $form_data['company']));
        $domain_name = strtolower(preg_replace('/[^a-z0-9]/', '', explode('.', $domain)[0]));
        
        similar_text($company_name, $domain_name, $similarity);
        
        if ($similarity > 60) {
            $business_score *= 1.15; // Email domain matches company name
        }
    }
    
    return min($business_score, 1.0);
}

/**
 * Analyse form completion quality and data richness
 *
 * @param array $form_data Form submission data
 * @return float Completion quality score between 0 and 1
 */
private function analyse_form_completion_quality($form_data) {
    $total_fields = count($form_data);
    
    if ($total_fields === 0) {
        return 0.0;
    }
    
    $quality_score = 0.0;
    $filled_quality_fields = 0;
    
    // Field quality assessment
    foreach ($form_data as $key => $value) {
        $value = trim($value);
        
        if (empty($value)) {
            continue;
        }
        
        // Check for substantive responses (not just "test", "asdf", etc.)
        if ($this->is_quality_response($value)) {
            $filled_quality_fields++;
            
            // Award points based on response length and type
            $field_score = 0;
            
            if (strlen($value) > 50) {
                $field_score += 0.20; // Detailed response
            } elseif (strlen($value) > 20) {
                $field_score += 0.12; // Good response
            } elseif (strlen($value) > 5) {
                $field_score += 0.06; // Basic response
            } else {
                $field_score += 0.02; // Minimal response
            }
            
            // Bonus for key business fields
            $key_fields = ['company', 'job_title', 'phone', 'industry', 'budget', 'timeline'];
            if (in_array($key, $key_fields)) {
                $field_score *= 1.5;
            }
            
            $quality_score += $field_score;
        }
    }
    
    // Completeness ratio
    $completeness = $filled_quality_fields / $total_fields;
    
    // Combined score: 60% quality, 40% completeness
    $final_score = ($quality_score / max($total_fields, 1)) * 0.60 + $completeness * 0.40;
    
    // Bonus for extremely thorough submissions
    if ($filled_quality_fields >= 8) {
        $final_score *= 1.15;
    }
    
    return min($final_score, 1.0);
}

/**
 * Analyse behavioural context from session data
 *
 * @param array $form_data Form submission data
 * @return float Behaviour score between 0 and 1
 */
private function analyse_behavioural_context($form_data) {
    $behaviour_score = 0.5; // Neutral baseline
    
    // Session engagement indicators
    $pages_visited = intval($form_data['pages_visited'] ?? $_COOKIE['session_pages'] ?? 1);
    $session_duration = intval($form_data['session_duration'] ?? $_COOKIE['session_duration'] ?? 0);
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Multi-page engagement (strong buying signal)
    if ($pages_visited >= 10) {
        $behaviour_score += 0.30;
    } elseif ($pages_visited >= 5) {
        $behaviour_score += 0.20;
    } elseif ($pages_visited >= 3) {
        $behaviour_score += 0.10;
    }
    
    // Time on site engagement
    if ($session_duration >= 600) { // 10+ minutes
        $behaviour_score += 0.25;
    } elseif ($session_duration >= 300) { // 5+ minutes
        $behaviour_score += 0.15;
    } elseif ($session_duration >= 120) { // 2+ minutes
        $behaviour_score += 0.08;
    }
    
    // Referrer quality
    if (!empty($referrer)) {
        $referrer_lower = strtolower($referrer);
        
        // Direct traffic or internal navigation (strong intent)
        if (stripos($referrer, $_SERVER['HTTP_HOST']) !== false) {
            $behaviour_score += 0.15;
        }
        // Search engine (research phase)
        elseif (preg_match('/(google|bing|yahoo|duckduckgo)/i', $referrer)) {
            $behaviour_score += 0.10;
        }
        // Social media (awareness phase)
        elseif (preg_match('/(facebook|twitter|linkedin|instagram)/i', $referrer)) {
            $behaviour_score += 0.05;
        }
    }
    
    // UTM campaign tracking
    if (!empty($form_data['utm_campaign']) || !empty($_GET['utm_campaign'])) {
        $behaviour_score += 0.08;
    }
    
    // Returning visitor (tracked via cookie or session)
    $returning_visitor = !empty($_COOKIE['returning_visitor']) || 
                        !empty($form_data['returning_visitor']);
    
    if ($returning_visitor) {
        $behaviour_score += 0.12; // Return visits indicate genuine interest
    }
    
    return min($behaviour_score, 1.0);
}

/**
 * Analyse historical conversion patterns for similar submissions
 *
 * @param array $form_data Form submission data
 * @return float Historical score between 0 and 1
 */
private function analyse_historical_conversion_patterns($form_data) {
    global $wpdb;
    
    $form_id = $form_data['form_id'] ?? 'unknown';
    $table_name = $wpdb->prefix . 'affiliate_form_submissions';
    
    // Get historical conversion rate for this form
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_submissions,
            SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
            AVG(CASE WHEN converted = 1 THEN conversion_value ELSE 0 END) as avg_value
         FROM {$table_name}
         WHERE form_id = %s
         AND submission_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        $form_id
    ), ARRAY_A);
    
    $total = intval($stats['total_submissions'] ?? 0);
    $conversions = intval($stats['conversions'] ?? 0);
    
    // Need minimum sample size for reliable prediction
    if ($total < 20) {
        return 0.50; // Neutral score with insufficient data
    }
    
    $conversion_rate = $conversions / $total;
    
    // Apply Laplace smoothing for small samples
    $smoothed_rate = ($conversions + 1) / ($total + 2);
    
    // Weight by sample size confidence
    $confidence = min($total / 100, 1.0);
    $final_rate = ($conversion_rate * $confidence) + ($smoothed_rate * (1 - $confidence));
    
    // Check for similar patterns (same industry, company size, etc.)
    if (!empty($form_data['industry'])) {
        $industry_rate = $this->get_industry_conversion_rate($form_data['industry']);
        
        // Blend form-specific and industry rates
        $final_rate = ($final_rate * 0.7) + ($industry_rate * 0.3);
    }
    
    return min($final_rate, 1.0);
}

/**
 * Analyse temporal context for optimal conversion timing
 *
 * @return float Temporal score between 0 and 1
 */
private function analyse_temporal_context() {
    $hour = intval(current_time('H'));
    $day = intval(current_time('N')); // 1=Monday, 7=Sunday
    
    $temporal_score = 0.5;
    
    // Business hours premium (9 AM - 5 PM)
    if ($hour >= 9 && $hour < 17) {
        $temporal_score += 0.25;
    }
    // Extended business hours (7 AM - 9 PM)
    elseif ($hour >= 7 && $hour < 21) {
        $temporal_score += 0.15;
    }
    // Off-hours penalty
    else {
        $temporal_score -= 0.10;
    }
    
    // Weekday premium (Monday-Friday)
    if ($day >= 1 && $day <= 5) {
        $temporal_score += 0.20;
    }
    // Weekend moderate reduction
    else {
        $temporal_score += 0.05;
    }
    
    // Peak conversion times (Tuesday-Thursday, 10-11 AM or 2-3 PM)
    if ($day >= 2 && $day <= 4) {
        if (($hour >= 10 && $hour < 11) || ($hour >= 14 && $hour < 15)) {
            $temporal_score += 0.10;
        }
    }
    
    // Month-end boost (budget spending pressure)
    $day_of_month = intval(current_time('j'));
    if ($day_of_month >= 25) {
        $temporal_score += 0.05;
    }
    
    return min($temporal_score, 1.0);
}

/**
 * Calculate prediction confidence based on available data
 *
 * @param array $form_data Form submission data
 * @return float Confidence adjustment between 0.7 and 1.0
 */
private function calculate_prediction_confidence($form_data) {
    $confidence = 1.0;
    $data_points = 0;
    
    // Count available data points
    $key_indicators = ['email', 'company', 'phone', 'industry', 'pages_visited', 'session_duration'];
    
    foreach ($key_indicators as $indicator) {
        if (!empty($form_data[$indicator])) {
            $data_points++;
        }
    }
    
    // Reduce confidence if insufficient data
    if ($data_points < 2) {
        $confidence = 0.70;
    } elseif ($data_points < 4) {
        $confidence = 0.85;
    } elseif ($data_points >= 5) {
        $confidence = 1.0;
    }
    
    return $confidence;
}

/**
 * Check if response value is quality (not spam or test)
 *
 * @param string $value Field value
 * @return bool True if quality response
 */
private function is_quality_response($value) {
    $value_lower = strtolower(trim($value));
    
    // Spam/test patterns
    $spam_patterns = [
        '/^(test|asdf|qwerty|xxx|none|n\/a|na)$/i',
        '/^(.)\1{3,}$/', // Repeated characters
        '/^\d{8,}$/', // Long number strings
        '/<script|javascript:/i',
        '/\b(viagra|cialis|casino|lottery)\b/i'
    ];
    
    foreach ($spam_patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return false;
        }
    }
    
    // Too short to be meaningful
    if (strlen($value) < 2) {
        return false;
    }
    
    return true;
}

/**
 * Check domain reputation score from cache or database
 *
 * @param string $domain Email domain
 * @return float Reputation multiplier between 0.5 and 1.2
 */
private function check_domain_reputation_score($domain) {
    $cache_key = 'affcd_domain_reputation_' . md5($domain);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return floatval($cached);
    }
    
    $reputation = 1.0;
    
    // Check against known spam domains
    $spam_list = get_option('affcd_spam_domains', []);
    if (in_array($domain, $spam_list)) {
        $reputation = 0.50;
    }
    
    // Check against verified business domains
    $verified_list = get_option('affcd_verified_domains', []);
    if (in_array($domain, $verified_list)) {
        $reputation = 1.20;
    }
    
    // Cache for 7 days
    set_transient($cache_key, $reputation, 7 * DAY_IN_SECONDS);
    
    return $reputation;
}

/**
 * Get industry-specific conversion rate
 *
 * @param string $industry Industry name
 * @return float Industry conversion rate between 0 and 1
 */
private function get_industry_conversion_rate($industry) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_form_submissions';
    
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions
         FROM {$table_name}
         WHERE JSON_EXTRACT(form_data, '$.industry') = %s
         AND submission_date >= DATE_SUB(NOW(), INTERVAL 180 DAY)",
        $industry
    ), ARRAY_A);
    
    $total = intval($stats['total'] ?? 0);
    $conversions = intval($stats['conversions'] ?? 0);
    
    if ($total < 10) {
        return 0.50; // Default if insufficient data
    }
    
    return $conversions / $total;
}

/**
 * Store conversion prediction data for model refinement
 *
 * @param array $form_data Form submission data
 * @param float $probability Predicted probability
 * @param array $signal_weights Individual signal weights
 * @return void
 */
private function store_conversion_prediction_data($form_data, $probability, $signal_weights) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_conversion_predictions';
    
    $wpdb->insert(
        $table_name,
        [
            'form_id' => $form_data['form_id'] ?? 'unknown',
            'predicted_probability' => $probability,
            'signal_weights' => json_encode($signal_weights),
            'form_data_hash' => md5(json_encode($form_data)),
            'predicted_at' => current_time('mysql'),
            'converted' => 0 // Updated later when actual outcome is known
        ],
        ['%s', '%f', '%s', '%s', '%s', '%d']
    );
}


    private function calculate_recency_factor($timestamp) {
        $hours_ago = (time() - strtotime($timestamp)) / 3600;
        return max(0.1, 1 - ($hours_ago / 168)); // Decay over 1 week
    }

    private function calculate_position_factor($index, $total) {
        if ($total <= 1) return 1.0;
        
        // U-shaped curve: higher weight for first and last positions
        $normalized_position = $index / ($total - 1);
        return 0.5 + 0.5 * abs(2 * $normalized_position - 1);
    }

    private function get_conversion_value($order_id) {
        if (function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            return $order ? $order->get_total() : 0;
        }
        return 100; // Default value
    }

    private function get_session_touchpoints($session_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}affcd_attribution_touchpoints 
            WHERE session_id = %s 
            ORDER BY timestamp ASC
        ", $session_id), ARRAY_A);
    }
}

/**
 * Create database tables for features
 */
function create_affcd_advanced_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

 // Viral opportunities table
    $sql_viral = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_viral_opportunities (
        id int(11) NOT NULL AUTO_INCREMENT,
        customer_email varchar(255) NOT NULL,
        customer_name varchar(255),
        trigger_type varchar(50) NOT NULL,
        viral_score decimal(5,2) DEFAULT 50.00,
        incentive_offered decimal(5,2) DEFAULT 0.00,
        viral_token varchar(255),
        metadata longtext,
        status varchar(50) DEFAULT 'scheduled',
        affiliate_id int(11),
        conversion_date datetime NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY email_status (customer_email, status),
        KEY trigger_viral_score (trigger_type, viral_score),
        KEY created_status (created_at, status)
    ) $charset_collate;";

    // Identity data table
    $sql_identity = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_identity_data (
        id int(11) NOT NULL AUTO_INCREMENT,
        identity_hash varchar(255) NOT NULL,
        source varchar(100) NOT NULL,
        email varchar(255),
        email_hash varchar(255),
        phone varchar(50),
        full_name varchar(255),
        name_parts json,
        device_fingerprint varchar(255),
        ip_address varchar(45),
        user_agent text,
        session_id varchar(255),
        additional_data longtext,
        site_url varchar(255),
        collected_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY identity_hash (identity_hash),
        KEY email_hash (email_hash),
        KEY device_fingerprint (device_fingerprint),
        KEY session_collected (session_id, collected_at),
        KEY source_site (source, site_url)
    ) $charset_collate;";

    // Identity links table
    $sql_identity_links = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_identity_links (
        id int(11) NOT NULL AUTO_INCREMENT,
        identity_hash_1 varchar(255) NOT NULL,
        identity_hash_2 varchar(255) NOT NULL,
        link_type varchar(100) NOT NULL,
        confidence_level varchar(50) NOT NULL,
        link_strength decimal(5,2) NOT NULL,
        match_data longtext,
        status varchar(50) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        verified_at datetime NULL,
        PRIMARY KEY (id),
        KEY identity_pair (identity_hash_1, identity_hash_2),
        KEY link_confidence (link_type, confidence_level),
        KEY strength_status (link_strength, status)
    ) $charset_collate;";

    // Attribution touchpoints table
    $sql_attribution = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_attribution_touchpoints (
        id int(11) NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        affiliate_id int(11),
        touchpoint_type varchar(100) NOT NULL,
        page_url text,
        referrer text,
        campaign_data json,
        interaction_quality decimal(3,2) DEFAULT 0.50,
        conversion_probability decimal(3,2) DEFAULT 0.50,
        ip_address varchar(45),
        user_agent text,
        metadata longtext,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        site_url varchar(255),
        PRIMARY KEY (id),
        KEY session_affiliate (session_id, affiliate_id),
        KEY touchpoint_timestamp (touchpoint_type, timestamp),
        KEY quality_probability (interaction_quality, conversion_probability)
    ) $charset_collate;";

    // Advanced states table
    $sql_advanced = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_advanced_states (
        id int(11) NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        advanced_state longtext NOT NULL,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        site_url varchar(255),
        PRIMARY KEY (id),
        UNIQUE KEY session_site (session_id, site_url),
        KEY updated_site (last_updated, site_url)
    ) $charset_collate;";

    // Attribution results table
    $sql_results = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_attribution_results (
        id int(11) NOT NULL AUTO_INCREMENT,
        order_id varchar(255) NOT NULL,
        session_id varchar(255) NOT NULL,
        model_results longtext NOT NULL,
        final_attribution longtext NOT NULL,
        attribution_confidence decimal(3,2) DEFAULT 0.50,
        advanced_entropy decimal(3,2) DEFAULT 1.00,
        total_conversion_value decimal(10,2) DEFAULT 0.00,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        site_url varchar(255),
        synced_to_master tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY order_session (order_id, session_id),
        KEY confidence_entropy (attribution_confidence, advanced_entropy),
        KEY created_synced (created_at, synced_to_master)
    ) $charset_collate;";

    // Viral performance tracking table
    $sql_viral_performance = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_viral_performance (
        id int(11) NOT NULL AUTO_INCREMENT,
        viral_opportunity_id int(11) NOT NULL,
        metric_type varchar(100) NOT NULL,
        metric_value decimal(10,2) NOT NULL,
        measurement_date date NOT NULL,
        affiliate_id int(11),
        referral_count int(11) DEFAULT 0,
        revenue_generated decimal(10,2) DEFAULT 0.00,
        viral_coefficient decimal(5,3) DEFAULT 0.000,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY opportunity_metric (viral_opportunity_id, metric_type),
        KEY affiliate_performance (affiliate_id, measurement_date),
        KEY viral_coefficient_date (viral_coefficient, measurement_date)
    ) $charset_collate;";

    // Cross-platform sessions table
    $sql_sessions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_cross_platform_sessions (
        id int(11) NOT NULL AUTO_INCREMENT,
        unified_session_id varchar(255) NOT NULL,
        platform_session_id varchar(255) NOT NULL,
        platform_type varchar(100) NOT NULL,
        device_info json,
        first_seen datetime DEFAULT CURRENT_TIMESTAMP,
        last_seen datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        total_interactions int(11) DEFAULT 1,
        conversion_events int(11) DEFAULT 0,
        site_url varchar(255),
        PRIMARY KEY (id),
        UNIQUE KEY platform_session (platform_session_id, platform_type),
        KEY unified_platform (unified_session_id, platform_type),
        KEY activity_tracking (last_seen, total_interactions)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_viral);
    dbDelta($sql_identity);
    dbDelta($sql_identity_links);
    dbDelta($sql_attribution);
    dbDelta($sql_advanced);
    dbDelta($sql_results);
    dbDelta($sql_viral_performance);
    dbDelta($sql_sessions);
}