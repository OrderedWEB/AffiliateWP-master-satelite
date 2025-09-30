<?php
/**
 * Satellite Data Backflow Manager - Complete Helper Methods Implementation
 * 
 * File: /wp-content/plugins/affiliate-master-enhancement/includes/class-satellite-data-backflow-manager-helpers.php
 * Plugin: Cross-Domain Affiliate System Enhancement (Master)
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * 
 * COMPLETE IMPLEMENTATION - All placeholder methods fully implemented
 * Add these methods to the SatelliteDataBackflowManager class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * COMPLETE HELPER METHODS - Add these to SatelliteDataBackflowManager class
 */

    /**
     * Sanitize page visits data
     * @param array $page_visits Raw page visit data
     * @return array Sanitized page visits
     */
    private function sanitize_page_visits($page_visits) {
        if (!is_array($page_visits)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($page_visits as $visit) {
            $sanitized[] = [
                'url' => esc_url_raw($visit['url'] ?? ''),
                'title' => sanitize_text_field($visit['title'] ?? ''),
                'timestamp' => sanitize_text_field($visit['timestamp'] ?? ''),
                'time_spent' => intval($visit['time_spent'] ?? 0),
                'scroll_depth' => floatval($visit['scroll_depth'] ?? 0),
                'interactions' => intval($visit['interactions'] ?? 0),
                'exit_page' => (bool)($visit['exit_page'] ?? false)
            ];
        }
        
        return $sanitized;
    }

    /**
     * Identify affiliate touchpoints from page visits
     * @param array $page_visits Page visit data
     * @return array Affiliate touchpoints
     */
    private function identify_affiliate_touchpoints($page_visits) {
        $touchpoints = [];
        
        foreach ($page_visits as $visit) {
            $url = $visit['url'] ?? '';
            
            // Check for affiliate parameters in URL
            $parsed_url = parse_url($url);
            if (isset($parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_params);
                
                $affiliate_params = ['ref', 'aff', 'affiliate', 'partner', 'referrer'];
                foreach ($affiliate_params as $param) {
                    if (isset($query_params[$param])) {
                        $touchpoints[] = [
                            'type' => 'url_parameter',
                            'parameter' => $param,
                            'value' => $query_params[$param],
                            'url' => $url,
                            'timestamp' => $visit['timestamp'] ?? current_time('mysql'),
                            'page_title' => $visit['title'] ?? '',
                            'time_spent' => $visit['time_spent'] ?? 0
                        ];
                        break; // One touchpoint per page
                    }
                }
            }
            
            // Check for affiliate links clicked
            if (isset($visit['affiliate_click']) && $visit['affiliate_click']) {
                $touchpoints[] = [
                    'type' => 'affiliate_link',
                    'url' => $url,
                    'timestamp' => $visit['timestamp'] ?? current_time('mysql'),
                    'page_title' => $visit['title'] ?? ''
                ];
            }
        }
        
        return $touchpoints;
    }

    /**
     * Map conversion path from interactions
     * @param array $interactions User interactions
     * @return array Conversion path
     */
    private function map_conversion_path($interactions) {
        if (empty($interactions)) {
            return [];
        }
        
        $path = [];
        $stages = ['awareness', 'consideration', 'decision', 'conversion'];
        
        foreach ($interactions as $interaction) {
            $type = $interaction['type'] ?? 'unknown';
            $timestamp = $interaction['timestamp'] ?? current_time('mysql');
            
            // Classify interaction into funnel stage
            $stage = $this->classify_interaction_stage($type, $interaction);
            
            $path[] = [
                'stage' => $stage,
                'interaction_type' => $type,
                'timestamp' => $timestamp,
                'page_url' => $interaction['page_url'] ?? '',
                'action' => $interaction['action'] ?? '',
                'value' => $interaction['value'] ?? null
            ];
        }
        
        // Sort by timestamp
        usort($path, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });
        
        return $path;
    }

    /**
     * Classify interaction into funnel stage
     * @param string $type Interaction type
     * @param array $interaction Interaction data
     * @return string Funnel stage
     */
    private function classify_interaction_stage($type, $interaction) {
        // Awareness stage indicators
        $awareness_types = ['page_view', 'affiliate_visit', 'landing', 'search'];
        if (in_array($type, $awareness_types)) {
            return 'awareness';
        }
        
        // Consideration stage indicators
        $consideration_types = ['product_view', 'category_browse', 'comparison', 'review_read'];
        if (in_array($type, $consideration_types)) {
            return 'consideration';
        }
        
        // Decision stage indicators
        $decision_types = ['add_to_cart', 'wishlist_add', 'price_check', 'specification_view'];
        if (in_array($type, $decision_types)) {
            return 'decision';
        }
        
        // Conversion stage indicators
        $conversion_types = ['purchase', 'checkout', 'payment', 'order_complete'];
        if (in_array($type, $conversion_types)) {
            return 'conversion';
        }
        
        return 'unknown';
    }

    /**
     * Calculate affiliate influence score
     * @param array $data Journey data
     * @return float Influence score (0-1)
     */
    private function calculate_affiliate_influence($data) {
        $influence_factors = [];
        
        // Factor 1: Number of touchpoints (0-0.3)
        $touchpoint_count = count($data['page_visits'] ?? []);
        $touchpoint_score = min(0.3, $touchpoint_count * 0.05);
        $influence_factors['touchpoints'] = $touchpoint_score;
        
        // Factor 2: Time to conversion (0-0.2)
        $time_to_conversion = $data['time_to_conversion'] ?? 0;
        $time_score = $time_to_conversion > 0 ? min(0.2, 0.2 * (1 - ($time_to_conversion / 2592000))) : 0; // 30 days max
        $influence_factors['timing'] = $time_score;
        
        // Factor 3: Engagement quality (0-0.3)
        $total_time = $data['total_time_spent'] ?? 0;
        $pages_visited = $data['pages_visited'] ?? 0;
        $engagement_score = min(0.3, ($total_time / 600) * 0.15 + ($pages_visited / 10) * 0.15); // 10 min / 10 pages ideal
        $influence_factors['engagement'] = $engagement_score;
        
        // Factor 4: Conversion value (0-0.2)
        $conversion_value = $data['conversion_value'] ?? 0;
        $value_score = $conversion_value > 0 ? min(0.2, ($conversion_value / 1000) * 0.2) : 0; // $1000 reference
        $influence_factors['value'] = $value_score;
        
        // Total influence score
        $total_influence = array_sum($influence_factors);
        
        return min(1.0, $total_influence);
    }

    /**
     * Store journey data in database
     * @param array $data Journey data to store
     */
    private function store_journey_data($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_user_journeys';
        
        $wpdb->insert(
            $table_name,
            [
                'session_id' => $data['session_id'] ?? '',
                'affiliate_id' => $data['affiliate_id'] ?? null,
                'domain' => $data['domain'] ?? '',
                'entry_point' => $data['entry_point'] ?? '',
                'page_visits' => json_encode($data['page_visits'] ?? []),
                'conversion_path' => json_encode($data['conversion_path'] ?? []),
                'total_time_spent' => $data['total_time_spent'] ?? 0,
                'pages_visited' => $data['pages_visited'] ?? 0,
                'converted' => $data['converted'] ?? false,
                'conversion_value' => $data['conversion_value'] ?? 0,
                'influence_score' => $data['influence_score'] ?? 0,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%s']
        );
    }

    /**
     * Trigger cart abandonment recovery workflow
     * @param array $data Cart abandonment data
     */
    private function trigger_abandonment_recovery($data) {
        // Only trigger for significant cart values
        if (($data['cart_value'] ?? 0) < 50) {
            return;
        }
        
        // Check if we have contact information
        if (empty($data['email']) && empty($data['phone'])) {
            return;
        }
        
        // Schedule recovery email
        $recovery_data = [
            'session_id' => $data['session_id'] ?? '',
            'affiliate_id' => $data['affiliate_id'] ?? null,
            'cart_value' => $data['cart_value'] ?? 0,
            'cart_items' => $data['cart_items'] ?? 0,
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'abandonment_stage' => $data['abandonment_stage'] ?? 'cart',
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ];
        
        // Queue recovery action
        wp_schedule_single_event(
            strtotime('+1 hour'),
            'ame_send_abandonment_recovery',
            [$recovery_data]
        );
        
        // Log the trigger
        error_log('AME: Cart abandonment recovery triggered for session ' . ($data['session_id'] ?? 'unknown'));
    }

    /**
     * Update performance baseline metrics
     * @param string $domain Domain name
     * @param array $data Performance data
     */
    private function update_performance_baseline($domain, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_performance_baselines';
        
        // Get existing baseline
        $baseline = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE domain = %s",
            $domain
        ));
        
        if ($baseline) {
            // Update rolling average
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name} SET
                    avg_page_load_time = ((avg_page_load_time * sample_count) + %f) / (sample_count + 1),
                    avg_time_to_interactive = ((avg_time_to_interactive * sample_count) + %f) / (sample_count + 1),
                    avg_largest_contentful_paint = ((avg_largest_contentful_paint * sample_count) + %f) / (sample_count + 1),
                    avg_first_input_delay = ((avg_first_input_delay * sample_count) + %f) / (sample_count + 1),
                    sample_count = sample_count + 1,
                    last_updated = NOW()
                 WHERE domain = %s",
                $data['page_load_time'] ?? 0,
                $data['time_to_interactive'] ?? 0,
                $data['largest_contentful_paint'] ?? 0,
                $data['first_input_delay'] ?? 0,
                $domain
            ));
        } else {
            // Insert new baseline
            $wpdb->insert(
                $table_name,
                [
                    'domain' => $domain,
                    'avg_page_load_time' => $data['page_load_time'] ?? 0,
                    'avg_time_to_interactive' => $data['time_to_interactive'] ?? 0,
                    'avg_largest_contentful_paint' => $data['largest_contentful_paint'] ?? 0,
                    'avg_first_input_delay' => $data['first_input_delay'] ?? 0,
                    'sample_count' => 1,
                    'created_at' => current_time('mysql'),
                    'last_updated' => current_time('mysql')
                ],
                ['%s', '%f', '%f', '%f', '%f', '%d', '%s', '%s']
            );
        }
    }

    /**
     * Process attribution touchpoints
     * @param array $touchpoints Raw touchpoint data
     * @return array Processed touchpoints
     */
    private function process_attribution_touchpoints($touchpoints) {
        if (!is_array($touchpoints)) {
            return [];
        }
        
        $processed = [];
        foreach ($touchpoints as $touchpoint) {
            $processed[] = [
                'affiliate_id' => intval($touchpoint['affiliate_id'] ?? 0),
                'type' => sanitize_text_field($touchpoint['type'] ?? 'unknown'),
                'timestamp' => sanitize_text_field($touchpoint['timestamp'] ?? current_time('mysql')),
                'page_url' => esc_url_raw($touchpoint['page_url'] ?? ''),
                'referrer' => esc_url_raw($touchpoint['referrer'] ?? ''),
                'value' => floatval($touchpoint['value'] ?? 0),
                'channel' => sanitize_text_field($touchpoint['channel'] ?? 'direct'),
                'device_type' => sanitize_text_field($touchpoint['device_type'] ?? 'desktop'),
                'days_before_conversion' => intval($touchpoint['days_before_conversion'] ?? 0)
            ];
        }
        
        return $processed;
    }

    /**
     * Generate unique batch ID
     * @return string Batch ID
     */
    private function generate_batch_id() {
        return 'batch_' . date('Ymd_His') . '_' . wp_generate_password(8, false);
    }

    /**
     * Determine data priority for queue processing
     * @param array $data Data to prioritize
     * @return int Priority level (1-10, 1 highest)
     */
    private function determine_data_priority($data) {
        $data_type = $data['data_type'] ?? 'unknown';
        
        // Priority mapping
        $priority_map = [
            'conversion' => 1,           // Highest priority
            'cart_abandonment' => 2,
            'attribution' => 3,
            'user_journey' => 4,
            'engagement' => 5,
            'social_proof' => 6,
            'site_performance' => 7,
            'default' => 5
        ];
        
        $base_priority = $priority_map[$data_type] ?? $priority_map['default'];
        
        // Adjust priority based on value
        if (isset($data['conversion_value']) && $data['conversion_value'] > 1000) {
            $base_priority = max(1, $base_priority - 1); // Bump up high-value conversions
        }
        
        // Adjust priority based on affiliate tier
        if (isset($data['affiliate_id'])) {
            $affiliate_tier = $this->get_affiliate_tier($data['affiliate_id']);
            if ($affiliate_tier === 'premium') {
                $base_priority = max(1, $base_priority - 1);
            }
        }
        
        return min(10, max(1, $base_priority));
    }

    /**
     * Get affiliate tier
     * @param int $affiliate_id Affiliate ID
     * @return string Tier level
     */
    private function get_affiliate_tier($affiliate_id) {
        $tier = wp_cache_get('affiliate_tier_' . $affiliate_id, 'ame_affiliates');
        
        if (false === $tier) {
            // Query affiliate tier from database
            $tier = affwp_get_affiliate_meta($affiliate_id, 'tier', true) ?: 'standard';
            wp_cache_set('affiliate_tier_' . $affiliate_id, $tier, 'ame_affiliates', 3600);
        }
        
        return $tier;
    }

    /**
     * Update real-time metrics for affiliate
     * @param int $affiliate_id Affiliate ID
     * @param string $domain Domain
     * @param array $metrics Metrics to update
     */
    private function update_realtime_metrics($affiliate_id, $domain, $metrics) {
        $cache_key = 'ame_realtime_' . $affiliate_id . '_' . md5($domain);
        
        // Get current metrics
        $current = wp_cache_get($cache_key, 'ame_metrics') ?: [];
        
        // Merge with new metrics
        foreach ($metrics as $metric => $value) {
            if (!isset($current[$metric])) {
                $current[$metric] = 0;
            }
            $current[$metric] += $value;
        }
        
        $current['last_updated'] = time();
        
        // Cache for 5 minutes
        wp_cache_set($cache_key, $current, 'ame_metrics', 300);
        
        // Also update permanent storage every 100 updates
        if (($current['update_count'] ?? 0) % 100 === 0) {
            $this->persist_realtime_metrics($affiliate_id, $domain, $current);
        }
    }

    /**
     * Persist real-time metrics to database
     * @param int $affiliate_id Affiliate ID
     * @param string $domain Domain
     * @param array $metrics Metrics to persist
     */
    private function persist_realtime_metrics($affiliate_id, $domain, $metrics) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_realtime_metrics';
        
        $wpdb->replace(
            $table_name,
            [
                'affiliate_id' => $affiliate_id,
                'domain' => $domain,
                'metrics_data' => json_encode($metrics),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    /**
     * Update journey insights
     * @param array $data Journey data
     */
    private function update_journey_insights($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_journey_insights';
        
        // Extract journey patterns
        $patterns = $this->extract_journey_patterns($data);
        
        // Update or insert insights
        foreach ($patterns as $pattern_type => $pattern_data) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table_name} 
                    (affiliate_id, domain, pattern_type, pattern_data, occurrence_count, last_seen)
                 VALUES (%d, %s, %s, %s, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                    occurrence_count = occurrence_count + 1,
                    pattern_data = %s,
                    last_seen = NOW()",
                $data['affiliate_id'] ?? 0,
                $data['domain'] ?? '',
                $pattern_type,
                json_encode($pattern_data),
                json_encode($pattern_data)
            ));
        }
    }

    /**
     * Extract journey patterns from data
     * @param array $data Journey data
     * @return array Patterns found
     */
    private function extract_journey_patterns($data) {
        $patterns = [];
        
        // Entry point pattern
        if (!empty($data['entry_point'])) {
            $patterns['entry_point'] = [
                'url' => $data['entry_point'],
                'converted' => $data['converted'] ?? false
            ];
        }
        
        // Page sequence pattern
        if (!empty($data['page_visits'])) {
            $page_sequence = array_slice(
                array_column($data['page_visits'], 'url'),
                0,
                5 // First 5 pages
            );
            $patterns['page_sequence'] = [
                'sequence' => $page_sequence,
                'converted' => $data['converted'] ?? false
            ];
        }
        
        // Time-to-conversion pattern
        if ($data['converted'] ?? false) {
            $time_bucket = $this->get_time_bucket($data['time_to_conversion'] ?? 0);
            $patterns['time_to_conversion'] = [
                'bucket' => $time_bucket,
                'exact_time' => $data['time_to_conversion'] ?? 0
            ];
        }
        
        return $patterns;
    }

    /**
     * Get time bucket for time-to-conversion
     * @param int $seconds Time in seconds
     * @return string Time bucket
     */
    private function get_time_bucket($seconds) {
        if ($seconds < 300) return 'immediate'; // < 5 min
        if ($seconds < 3600) return 'same_session'; // < 1 hour
        if ($seconds < 86400) return 'same_day'; // < 24 hours
        if ($seconds < 604800) return 'same_week'; // < 7 days
        return 'extended'; // > 7 days
    }

    /**
     * Update conversion attribution
     * @param array $data Journey data with conversion
     */
    private function update_conversion_attribution($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_conversion_attribution';
        
        $wpdb->insert(
            $table_name,
            [
                'session_id' => $data['session_id'] ?? '',
                'affiliate_id' => $data['affiliate_id'] ?? null,
                'domain' => $data['domain'] ?? '',
                'conversion_value' => $data['conversion_value'] ?? 0,
                'touchpoint_count' => count($data['affiliate_touchpoints'] ?? []),
                'first_touch' => $this->get_first_touchpoint_time($data),
                'last_touch' => $this->get_last_touchpoint_time($data),
                'time_to_conversion' => $data['time_to_conversion'] ?? 0,
                'influence_score' => $data['influence_score'] ?? 0,
                'attribution_model' => 'multi_touch',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%f', '%d', '%s', '%s', '%d', '%f', '%s', '%s']
        );
    }

    /**
     * Get first touchpoint timestamp
     * @param array $data Journey data
     * @return string Timestamp
     */
    private function get_first_touchpoint_time($data) {
        $touchpoints = $data['affiliate_touchpoints'] ?? [];
        if (empty($touchpoints)) {
            return current_time('mysql');
        }
        
        $timestamps = array_column($touchpoints, 'timestamp');
        sort($timestamps);
        
        return $timestamps[0] ?? current_time('mysql');
    }

    /**
     * Get last touchpoint timestamp
     * @param array $data Journey data
     * @return string Timestamp
     */
    private function get_last_touchpoint_time($data) {
        $touchpoints = $data['affiliate_touchpoints'] ?? [];
        if (empty($touchpoints)) {
            return current_time('mysql');
        }
        
        $timestamps = array_column($touchpoints, 'timestamp');
        rsort($timestamps);
        
        return $timestamps[0] ?? current_time('mysql');
    }

    /**
     * Extract domain from referral
     * @param object $referral Referral object
     * @return string Domain
     */
    private function extract_domain_from_referral($referral) {
        if (empty($referral->context)) {
            return '';
        }
        
        $parsed = parse_url($referral->context);
        return $parsed['host'] ?? '';
    }

    /**
     * Process cross-domain attribution
     * @param object $referral Referral object
     */
    private function process_cross_domain_attribution($referral) {
        global $wpdb;
        
        $domain = $this->extract_domain_from_referral($referral);
        
        if (empty($domain)) {
            return;
        }
        
        // Track cross-domain conversion
        $table_name = $wpdb->prefix . 'affiliate_cross_domain_conversions';
        
        $wpdb->insert(
            $table_name,
            [
                'referral_id' => $referral->referral_id,
                'affiliate_id' => $referral->affiliate_id,
                'source_domain' => $domain,
                'target_domain' => home_url(),
                'amount' => $referral->amount,
                'currency' => affwp_get_currency(),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%f', '%s', '%s']
        );
    }

    /**
     * Update conversion metrics for referral
     * @param object $referral Referral object
     */
    private function update_conversion_metrics($referral) {
        $affiliate_id = $referral->affiliate_id;
        $amount = $referral->amount;
        
        // Update daily metrics
        $this->update_daily_conversion_metrics($affiliate_id, $amount);
        
        // Update lifetime metrics
        $this->update_lifetime_conversion_metrics($affiliate_id, $amount);
        
        // Update domain-specific metrics
        $domain = $this->extract_domain_from_referral($referral);
        if ($domain) {
            $this->update_domain_conversion_metrics($affiliate_id, $domain, $amount);
        }
    }

    /**
     * Update daily conversion metrics
     * @param int $affiliate_id Affiliate ID
     * @param float $amount Conversion amount
     */
    private function update_daily_conversion_metrics($affiliate_id, $amount) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_daily_metrics';
        $today = date('Y-m-d');
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name} 
                (affiliate_id, metric_date, conversion_count, conversion_amount)
             VALUES (%d, %s, 1, %f)
             ON DUPLICATE KEY UPDATE
                conversion_count = conversion_count + 1,
                conversion_amount = conversion_amount + %f",
            $affiliate_id,
            $today,
            $amount,
            $amount
        ));
    }

    /**
     * Update lifetime conversion metrics
     * @param int $affiliate_id Affiliate ID
     * @param float $amount Conversion amount
     */
    private function update_lifetime_conversion_metrics($affiliate_id, $amount) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_lifetime_metrics';
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, total_conversions, total_amount)
             VALUES (%d, 1, %f)
             ON DUPLICATE KEY UPDATE
                total_conversions = total_conversions + 1,
                total_amount = total_amount + %f,
                last_conversion = NOW()",
            $affiliate_id,
            $amount,
            $amount
        ));
    }

    /**
     * Update domain-specific conversion metrics
     * @param int $affiliate_id Affiliate ID
     * @param string $domain Domain
     * @param float $amount Conversion amount
     */
    private function update_domain_conversion_metrics($affiliate_id, $domain, $amount) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_domain_metrics';
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, domain, conversion_count, conversion_amount)
             VALUES (%d, %s, 1, %f)
             ON DUPLICATE KEY UPDATE
                conversion_count = conversion_count + 1,
                conversion_amount = conversion_amount + %f,
                last_conversion = NOW()",
            $affiliate_id,
            $domain,
            $amount,
            $amount
        ));
    }

    /**
     * Update abandonment analytics
     * @param array $data Cart abandonment data
     */
    private function update_abandonment_analytics($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_abandonment_analytics';
        
        // Update aggregated analytics
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, domain, abandonment_stage, total_abandonments, total_value, avg_cart_value, avg_time_in_cart)
             VALUES (%d, %s, %s, 1, %f, %f, %d)
             ON DUPLICATE KEY UPDATE
                total_abandonments = total_abandonments + 1,
                total_value = total_value + %f,
                avg_cart_value = (total_value + %f) / (total_abandonments + 1),
                avg_time_in_cart = ((avg_time_in_cart * total_abandonments) + %d) / (total_abandonments + 1),
                last_updated = NOW()",
            $data['affiliate_id'] ?? 0,
            $data['domain'] ?? '',
            $data['abandonment_stage'] ?? 'cart',
            $data['cart_value'] ?? 0,
            $data['cart_value'] ?? 0,
            $data['time_in_cart'] ?? 0,
            $data['cart_value'] ?? 0,
            $data['cart_value'] ?? 0,
            $data['time_in_cart'] ?? 0
        ));
    }

    /**
     * Update social proof metrics
     * @param array $data Social proof data
     */
    private function update_social_proof_metrics($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_social_proof_metrics';
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, domain, proof_type, display_count, interaction_count, conversion_count)
             VALUES (%d, %s, %s, 1, %d, %d)
             ON DUPLICATE KEY UPDATE
                display_count = display_count + 1,
                interaction_count = interaction_count + %d,
                conversion_count = conversion_count + %d,
                effectiveness_rate = (conversion_count + %d) / (display_count + 1),
                last_updated = NOW()",
            $data['affiliate_id'] ?? 0,
            $data['domain'] ?? '',
            $data['proof_type'] ?? 'unknown',
            $data['interaction_count'] ?? 0,
            $data['conversion_attributed'] ?? 0,
            $data['interaction_count'] ?? 0,
            $data['conversion_attributed'] ?? 0,
            $data['conversion_attributed'] ?? 0
        ));
    }

    /**
     * Check performance thresholds
     * @param array $data Performance data
     * @return bool Whether thresholds are exceeded
     */
    private function check_performance_thresholds($data) {
        $thresholds = [
            'page_load_time' => 3000, // 3 seconds
            'time_to_interactive' => 5000, // 5 seconds
            'largest_contentful_paint' => 2500, // 2.5 seconds
            'first_input_delay' => 100 // 100ms
        ];
        
        $exceeded = [];
        
        foreach ($thresholds as $metric => $threshold) {
            if (isset($data[$metric]) && $data[$metric] > $threshold) {
                $exceeded[$metric] = [
                    'value' => $data[$metric],
                    'threshold' => $threshold,
                    'percentage_over' => (($data[$metric] - $threshold) / $threshold) * 100
                ];
            }
        }
        
        // Log warnings for exceeded thresholds
        if (!empty($exceeded)) {
            error_log('AME: Performance thresholds exceeded for ' . ($data['domain'] ?? 'unknown') . ': ' . json_encode($exceeded));
            
            // Trigger alert for significant performance issues
            if (count($exceeded) >= 2) {
                do_action('ame_performance_alert', $data['domain'], $exceeded);
            }
        }
        
        return !empty($exceeded);
    }

    /**
     * Update attribution insights
     * @param array $data Attribution data
     */
    private function update_attribution_insights($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_attribution_insights';
        
        $attribution_model = $data['attribution_model'] ?? 'last_click';
        $touchpoint_count = $data['touchpoint_count'] ?? 0;
        $conversion_value = $data['conversion_value'] ?? 0;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, attribution_model, touchpoint_count, total_conversions, total_value, avg_touchpoints, avg_conversion_value)
             VALUES (%d, %s, %d, 1, %f, %d, %f)
             ON DUPLICATE KEY UPDATE
                total_conversions = total_conversions + 1,
                total_value = total_value + %f,
                avg_touchpoints = ((avg_touchpoints * total_conversions) + %d) / (total_conversions + 1),
                avg_conversion_value = ((avg_conversion_value * total_conversions) + %f) / (total_conversions + 1),
                last_updated = NOW()",
            $data['affiliate_id'] ?? 0,
            $attribution_model,
            $touchpoint_count,
            $conversion_value,
            $touchpoint_count,
            $conversion_value,
            $conversion_value,
            $touchpoint_count,
            $conversion_value
        ));
    }
    /**
     * Update lifetime value statistics for an affiliate
     *
     * @param array $data Lifetime value data
     * @return void
     */
    private function update_lifetime_value($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_lifetime_value';
        
        $order_value = $data['order_value'] ?? 0;
        $commission_earned = $data['commission_earned'] ?? 0;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, total_orders, total_revenue, total_commission, avg_order_value, customer_count)
             VALUES (%d, 1, %f, %f, %f, 1)
             ON DUPLICATE KEY UPDATE
                total_orders = total_orders + 1,
                total_revenue = total_revenue + %f,
                total_commission = total_commission + %f,
                avg_order_value = (total_revenue + %f) / (total_orders + 1),
                customer_count = customer_count + IF(%d > 0, 1, 0),
                last_order_date = NOW()",
            $data['affiliate_id'] ?? 0,
            $order_value,
            $commission_earned,
            $order_value,
            $order_value,
            $commission_earned,
            $order_value,
            $data['new_customer'] ?? 0
        ));
    }

    /**
     * Update performance metrics for an affiliate
     *
     * @param array $data Performance metrics data
     * @return void
     */
    private function update_performance_metrics($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_performance_metrics';
        
        $conversion_rate = $data['conversion_rate'] ?? 0;
        $click_count = $data['click_count'] ?? 0;
        $conversion_count = $data['conversion_count'] ?? 0;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, total_clicks, total_conversions, conversion_rate, revenue_per_click)
             VALUES (%d, %d, %d, %f, %f)
             ON DUPLICATE KEY UPDATE
                total_clicks = total_clicks + %d,
                total_conversions = total_conversions + %d,
                conversion_rate = (total_conversions + %d) / GREATEST(total_clicks + %d, 1) * 100,
                revenue_per_click = total_revenue / GREATEST(total_clicks + %d, 1),
                last_updated = NOW()",
            $data['affiliate_id'] ?? 0,
            $click_count,
            $conversion_count,
            $conversion_rate,
            $data['revenue_per_click'] ?? 0,
            $click_count,
            $conversion_count,
            $conversion_count,
            $click_count,
            $click_count
        ));
    }

    /**
     * Update cohort analysis data for an affiliate
     *
     * @param array $data Cohort analysis data
     * @return void
     */
    private function update_cohort_analysis($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_cohort_analysis';
        
        $cohort_month = $data['cohort_month'] ?? date('Y-m-01');
        $revenue = $data['revenue'] ?? 0;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, cohort_month, orders_count, revenue, active_customers)
             VALUES (%d, %s, 1, %f, 1)
             ON DUPLICATE KEY UPDATE
                orders_count = orders_count + 1,
                revenue = revenue + %f,
                active_customers = active_customers + IF(%d > 0, 1, 0),
                last_activity = NOW()",
            $data['affiliate_id'] ?? 0,
            $cohort_month,
            $revenue,
            $revenue,
            $data['new_customer'] ?? 0
        ));
    }

    /**
     * Update segment performance data for an affiliate
     *
     * @param array $data Segment performance data
     * @return void
     */
    private function update_segment_performance($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_segment_performance';
        
        $segment_type = $data['segment_type'] ?? 'unknown';
        $segment_value = $data['segment_value'] ?? 'default';
        $revenue = $data['revenue'] ?? 0;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, segment_type, segment_value, orders_count, revenue, conversion_rate)
             VALUES (%d, %s, %s, 1, %f, %f)
             ON DUPLICATE KEY UPDATE
                orders_count = orders_count + 1,
                revenue = revenue + %f,
                conversion_rate = (conversions + %d) / GREATEST(clicks + %d, 1) * 100,
                last_updated = NOW()",
            $data['affiliate_id'] ?? 0,
            $segment_type,
            $segment_value,
            $revenue,
            $data['conversion_rate'] ?? 0,
            $revenue,
            $data['conversions'] ?? 0,
            $data['clicks'] ?? 0
        ));
    }

    /**
     * Update retention metrics for an affiliate
     *
     * @param array $data Retention metrics data
     * @return void
     */
    private function update_retention_metrics($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_retention_metrics';
        
        $period_type = $data['period_type'] ?? 'monthly';
        $retained_customers = $data['retained_customers'] ?? 0;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, period_type, total_customers, retained_customers, retention_rate)
             VALUES (%d, %s, 1, %d, %f)
             ON DUPLICATE KEY UPDATE
                total_customers = total_customers + 1,
                retained_customers = retained_customers + %d,
                retention_rate = (retained_customers + %d) / GREATEST(total_customers + 1, 1) * 100,
                last_calculated = NOW()",
            $data['affiliate_id'] ?? 0,
            $period_type,
            $retained_customers,
            $data['retention_rate'] ?? 0,
            $retained_customers,
            $retained_customers
        ));
    }

    /**
     * Update trend analysis data for an affiliate
     *
     * @param array $data Trend analysis data
     * @return void
     */
    private function update_trend_analysis($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_trend_analysis';
        
        $trend_period = $data['trend_period'] ?? date('Y-m-d');
        $metric_type = $data['metric_type'] ?? 'revenue';
        $metric_value = $data['metric_value'] ?? 0;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_name}
                (affiliate_id, trend_period, metric_type, metric_value, growth_rate)
             VALUES (%d, %s, %s, %f, %f)
             ON DUPLICATE KEY UPDATE
                metric_value = metric_value + %f,
                growth_rate = %f,
                last_updated = NOW()",
            $data['affiliate_id'] ?? 0,
            $trend_period,
            $metric_type,
            $metric_value,
            $data['growth_rate'] ?? 0,
            $metric_value,
            $data['growth_rate'] ?? 0
        ));
    }

    /**
     * Sanitise incoming data to prevent SQL injection and XSS
     *
     * @param array $data Raw data array
     * @return array Sanitised data array
     */
    private function sanitise_data($data) {
        $sanitised = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitised[$key] = $this->sanitise_data($value);
            } elseif (is_numeric($value)) {
                $sanitised[$key] = floatval($value);
            } else {
                $sanitised[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitised;
    }

    /**
     * Validate required fields in data array
     *
     * @param array $data Data to validate
     * @param array $required_fields Required field names
     * @return bool True if all required fields present
     */
    private function validate_required_fields($data, $required_fields) {
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->log_error("Missing required field: {$field}");
                return false;
            }
        }
        
        return true;
    }

    /**
     * Log error message to WordPress error log
     *
     * @param string $message Error message
     * @return void
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('Satellite Data Backflow Manager: ' . $message);
        }
    }

    /**
     * Get current UTC timestamp
     *
     * @return string UTC timestamp in Y-m-d H:i:s format
     */
    private function get_utc_timestamp() {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Check if table exists in database
     *
     * @param string $table_name Table name without prefix
     * @return bool True if table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . $table_name;
        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name);
        
        return $wpdb->get_var($query) === $full_table_name;
    }

    /**
     * Verify data integrity before processing
     *
     * @param array $data Data to verify
     * @return bool True if data passes integrity checks
     */
    private function verify_data_integrity($data) {
        if (empty($data)) {
            $this->log_error('Empty data provided for verification');
            return false;
        }
        
        if (!isset($data['affiliate_id']) || intval($data['affiliate_id']) <= 0) {
            $this->log_error('Invalid affiliate_id in data');
            return false;
        }
        
        return true;
    }
}