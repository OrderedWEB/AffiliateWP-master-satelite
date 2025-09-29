<?php
/**
 * Affiliate Link Health Monitor
 * 
 * Monitors and maintains affiliate link health across all domains
 * with automated healing, performance tracking, and alert systems
 * 
 * Filename: class-affiliate-link-health-monitor.php
 * Path: /wp-content/plugins/affiliate-master-enhancement/includes/
 * 
 * @package AffiliateLinkHealthMonitor
 * @author Richard King <r.king@starneconsulting.com>
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Affiliate Link Health Monitor Class
 * Continuously monitors and maintains affiliate link health
 */
class AffiliateLinkHealthMonitor {
    
    /**
     * Health check timeout in seconds
     */
    const HEALTH_CHECK_TIMEOUT = 10;
    
    /**
     * Maximum consecutive failures before marking as broken
     */
    const MAX_CONSECUTIVE_FAILURES = 3;
    
    /**
     * Cache duration for health check results
     */
    const CACHE_DURATION = 900; // 15 minutes
    
    /**
     * Constructor - initialise health monitor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        
        // Schedule health checks
        add_action('ame_hourly_health_check', [$this, 'run_health_check']);
        add_action('ame_daily_comprehensive_check', [$this, 'run_comprehensive_health_check']);
        
        // AJAX handlers for manual health checks
        add_action('wp_ajax_ame_manual_health_check', [$this, 'ajax_manual_health_check']);
        add_action('wp_ajax_ame_fix_broken_links', [$this, 'ajax_fix_broken_links']);
        
        // Register API endpoints
        add_action('rest_api_init', [$this, 'register_health_api_endpoints']);
        
        // Hook into affiliate link generation for real-time monitoring
        add_filter('affwp_referral_link', [$this, 'validate_link_on_generation'], 20, 2);
    }
    
    /**
     * Initialise health monitoring
     */
    public function init() {
        // Set up monitoring schedules if not already scheduled
        if (!wp_next_scheduled('ame_hourly_health_check')) {
            wp_schedule_event(time(), 'hourly', 'ame_hourly_health_check');
        }
        
        if (!wp_next_scheduled('ame_daily_comprehensive_check')) {
            wp_schedule_event(time(), 'daily', 'ame_daily_comprehensive_check');
        }
    }
    
    /**
     * Monitor comprehensive link health across all domains
     * @return array Health report
     */
    public function monitor_link_health() {
        $domains = $this->get_all_affiliated_domains();
        $health_report = [
            'overall_status' => 'healthy',
            'total_domains' => count($domains),
            'healthy_domains' => 0,
            'warning_domains' => 0,
            'critical_domains' => 0,
            'domains' => [],
            'summary' => [
                'total_links_checked' => 0,
                'healthy_links' => 0,
                'broken_links' => 0,
                'slow_links' => 0,
                'auto_healed_links' => 0
            ],
            'recommendations' => [],
            'last_check' => current_time('mysql')
        ];
        
        foreach ($domains as $domain) {
            $domain_health = $this->check_domain_health($domain);
            $health_report['domains'][$domain] = $domain_health;
            
            // Update counters
            $health_report['summary']['total_links_checked'] += $domain_health['links_checked'];
            $health_report['summary']['healthy_links'] += $domain_health['healthy_links'];
            $health_report['summary']['broken_links'] += $domain_health['broken_links'];
            $health_report['summary']['slow_links'] += $domain_health['slow_links'];
            $health_report['summary']['auto_healed_links'] += $domain_health['auto_healed_links'];
            
            // Categorise domain status
            switch ($domain_health['status']) {
                case 'healthy':
                    $health_report['healthy_domains']++;
                    break;
                case 'warning':
                    $health_report['warning_domains']++;
                    if ($health_report['overall_status'] === 'healthy') {
                        $health_report['overall_status'] = 'warning';
                    }
                    break;
                case 'critical':
                    $health_report['critical_domains']++;
                    $health_report['overall_status'] = 'critical';
                    break;
            }
        }
        
        // Generate recommendations
        $health_report['recommendations'] = $this->generate_health_recommendations($health_report);
        
        // Store health report for dashboard access
        update_option('ame_last_health_report', $health_report);
        
        return $health_report;
    }
    
    /**
     * Check health of a specific domain
     * @param string $domain Domain to check
     * @return array Domain health data
     */
    private function check_domain_health($domain) {
        $cache_key = 'ame_domain_health_' . md5($domain);
        $cached_health = wp_cache_get($cache_key);
        
        if ($cached_health !== false) {
            return $cached_health;
        }
        
        $domain_health = [
            'domain' => $domain,
            'status' => 'healthy',
            'response_time' => 0,
            'api_status' => 'operational',
            'links_checked' => 0,
            'healthy_links' => 0,
            'broken_links' => 0,
            'slow_links' => 0,
            'auto_healed_links' => 0,
            'conversion_rates' => [],
            'error_rates' => 0,
            'issues' => [],
            'recommended_actions' => [],
            'last_successful_check' => null,
            'check_timestamp' => current_time('mysql')
        ];
        
        // Test domain response time
        $response_time_result = $this->test_domain_response_time($domain);
        $domain_health['response_time'] = $response_time_result['response_time'];
        
        if ($response_time_result['status'] === 'timeout') {
            $domain_health['issues'][] = 'Domain connection timeout';
            $domain_health['status'] = 'critical';
        } elseif ($response_time_result['response_time'] > 5000) {
            $domain_health['issues'][] = 'Slow response time (' . $response_time_result['response_time'] . 'ms)';
            $domain_health['status'] = 'warning';
        }
        
        // Test affiliate API endpoints
        $api_status = $this->test_affiliate_api_endpoints($domain);
        $domain_health['api_status'] = $api_status['status'];
        
        if ($api_status['status'] !== 'operational') {
            $domain_health['issues'] = array_merge($domain_health['issues'], $api_status['issues']);
            $domain_health['status'] = 'critical';
        }
        
        // Analyse recent conversion rates
        $conversion_analysis = $this->analyse_recent_conversion_rates($domain);
        $domain_health['conversion_rates'] = $conversion_analysis;
        
        if ($conversion_analysis['trend'] === 'declining' && $conversion_analysis['decline_percentage'] > 20) {
            $domain_health['issues'][] = 'Significant conversion rate decline (' . $conversion_analysis['decline_percentage'] . '%)';
            if ($domain_health['status'] === 'healthy') {
                $domain_health['status'] = 'warning';
            }
        }
        
        // Calculate error rates
        $error_rates = $this->calculate_error_rates($domain);
        $domain_health['error_rates'] = $error_rates;
        
        if ($error_rates > 0.05) { // 5% error rate threshold
            $domain_health['issues'][] = 'High error rate (' . number_format($error_rates * 100, 1) . '%)';
            if ($domain_health['status'] === 'healthy') {
                $domain_health['status'] = 'warning';
            }
        }
        
        // Check specific affiliate links on this domain
        $link_health = $this->check_affiliate_links_health($domain);
        $domain_health['links_checked'] = $link_health['total_checked'];
        $domain_health['healthy_links'] = $link_health['healthy'];
        $domain_health['broken_links'] = $link_health['broken'];
        $domain_health['slow_links'] = $link_health['slow'];
        
        if ($link_health['broken'] > 0) {
            $domain_health['issues'][] = $link_health['broken'] . ' broken affiliate links detected';
            if ($domain_health['status'] === 'healthy') {
                $domain_health['status'] = 'warning';
            }
        }
        
        // Attempt auto-healing of broken links
        if ($link_health['broken'] > 0) {
            $healing_results = $this->auto_heal_broken_links($domain, $link_health['broken_links']);
            $domain_health['auto_healed_links'] = $healing_results['healed_count'];
            
            if ($healing_results['healed_count'] > 0) {
                $domain_health['issues'][] = 'Auto-healed ' . $healing_results['healed_count'] . ' broken links';
            }
        }
        
        // Generate improvement recommendations
        $domain_health['recommended_actions'] = $this->generate_improvement_recommendations($domain, $domain_health);
        
        // Update last successful check timestamp
        if ($domain_health['status'] !== 'critical') {
            $domain_health['last_successful_check'] = current_time('mysql');
        }
        
        // Cache the results
        wp_cache_set($cache_key, $domain_health, '', self::CACHE_DURATION);
        
        return $domain_health;
    }
    
    /**
     * Test domain response time
     * @param string $domain Domain to test
     * @return array Response time data
     */
    private function test_domain_response_time($domain) {
        $start_time = microtime(true);
        
        $response = wp_remote_get('https://' . $domain, [
            'timeout' => self::HEALTH_CHECK_TIMEOUT,
            'headers' => [
                'User-Agent' => 'AME Health Monitor/1.0'
            ]
        ]);
        
        $end_time = microtime(true);
        $response_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        
        if (is_wp_error($response)) {
            return [
                'status' => 'timeout',
                'response_time' => self::HEALTH_CHECK_TIMEOUT * 1000,
                'error' => $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        return [
            'status' => ($response_code >= 200 && $response_code < 300) ? 'success' : 'error',
            'response_time' => round($response_time),
            'response_code' => $response_code
        ];
    }
    
    /**
     * Test affiliate API endpoints on domain
     * @param string $domain Domain to test
     * @return array API status data
     */
    private function test_affiliate_api_endpoints($domain) {
        $api_endpoints = [
            '/wp-json/affiliate/v1/health',
            '/wp-json/affiliate/v1/validate-code',
            '/wp-json/wp/v2/posts'  // Basic WordPress API test
        ];
        
        $api_status = [
            'status' => 'operational',
            'endpoints_tested' => count($api_endpoints),
            'successful_endpoints' => 0,
            'failed_endpoints' => 0,
            'issues' => []
        ];
        
        foreach ($api_endpoints as $endpoint) {
            $test_result = $this->test_api_endpoint($domain, $endpoint);
            
            if ($test_result['success']) {
                $api_status['successful_endpoints']++;
            } else {
                $api_status['failed_endpoints']++;
                $api_status['issues'][] = "Endpoint {$endpoint}: " . $test_result['error'];
            }
        }
        
        // Determine overall API status
        $success_rate = $api_status['successful_endpoints'] / $api_status['endpoints_tested'];
        
        if ($success_rate < 0.5) {
            $api_status['status'] = 'critical';
        } elseif ($success_rate < 0.8) {
            $api_status['status'] = 'degraded';
        }
        
        return $api_status;
    }
    
    /**
     * Test individual API endpoint
     * @param string $domain Domain
     * @param string $endpoint Endpoint path
     * @return array Test result
     */
    private function test_api_endpoint($domain, $endpoint) {
        $url = 'https://' . $domain . $endpoint;

        $start = microtime(true);
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => [
                'User-Agent' => 'AME Health Monitor/1.0'
            ]
        ]);
        $elapsed_ms = (microtime(true) - $start) * 1000;
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'response_time' => round($elapsed_ms)
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        return [
            'success' => ($response_code >= 200 && $response_code < 400),
            'response_code' => $response_code,
            'response_time' => round($elapsed_ms)
        ];
    }
    
    /**
     * Analyse recent conversion rates for domain
     * @param string $domain Domain to analyse
     * @return array Conversion rate analysis
     */
    private function analyse_recent_conversion_rates($domain) {
        global $wpdb;
        
        // Get conversion rates for the last 30 days vs previous 30 days
        $current_period = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(CASE WHEN metric_type = 'conversion_rate' THEN metric_value ELSE NULL END)
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE domain = %s
            AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ", $domain));
        
        $previous_period = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(CASE WHEN metric_type = 'conversion_rate' THEN metric_value ELSE NULL END)
            FROM {$wpdb->prefix}affiliate_enhanced_analytics
            WHERE domain = %s
            AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            AND date_recorded < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ", $domain));
        
        $current_rate = floatval($current_period) * 100;
        $previous_rate = floatval($previous_period) * 100;
        
        $change_percentage = 0;
        $trend = 'stable';
        
        if ($previous_rate > 0) {
            $change_percentage = (($current_rate - $previous_rate) / $previous_rate) * 100;
            
            if (abs($change_percentage) > 5) {
                $trend = $change_percentage > 0 ? 'improving' : 'declining';
            }
        }
        
        return [
            'current_rate' => $current_rate,
            'previous_rate' => $previous_rate,
            'change_percentage' => round(abs($change_percentage), 1),
            'trend' => $trend,
            'decline_percentage' => $trend === 'declining' ? round(abs($change_percentage), 1) : 0
        ];
    }
    
    /**
     * Calculate error rates for domain
     * @param string $domain Domain to analyse
     * @return float Error rate
     */
    private function calculate_error_rates($domain) {
        global $wpdb;
        
        // Calculate error rate from API logs (if available)
        $error_rate = $wpdb->get_var($wpdb->prepare("
            SELECT 
                COUNT(CASE WHEN response_code >= 400 THEN 1 END) / COUNT(*) as error_rate
            FROM {$wpdb->prefix}affiliate_api_logs
            WHERE domain = %s
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ", $domain));
        
        return floatval($error_rate);
    }
    
    /**
     * Check health of affiliate links on specific domain
     * @param string $domain Domain to check
     * @return array Link health data
     */
    private function check_affiliate_links_health($domain) {
        global $wpdb;
        
        // Get affiliate links for this domain
        $affiliate_links = $wpdb->get_results($wpdb->prepare("
            SELECT 
                id,
                link_url,
                affiliate_id,
                status,
                last_click_date,
                clicks,
                conversions
            FROM {$wpdb->prefix}affiliate_link_performance
            WHERE domain = %s
            AND status IN ('active', 'inactive')
            ORDER BY clicks DESC
            LIMIT 50
        ", $domain));
        
        $link_health = [
            'total_checked' => count($affiliate_links),
            'healthy' => 0,
            'broken' => 0,
            'slow' => 0,
            'broken_links' => []
        ];
        
        foreach ($affiliate_links as $link) {
            $link_status = $this->test_individual_link($link->link_url);
            
            if ($link_status['status'] === 'broken') {
                $link_health['broken']++;
                $link_health['broken_links'][] = [
                    'id' => $link->id,
                    'url' => $link->link_url,
                    'affiliate_id' => $link->affiliate_id,
                    'error' => $link_status['error']
                ];
            } elseif ($link_status['status'] === 'slow') {
                $link_health['slow']++;
            } else {
                $link_health['healthy']++;
            }
        }
        
        return $link_health;
    }
    
    /**
     * Test individual affiliate link
     * @param string $link_url Link URL to test
     * @return array Link test result
     */
    private function test_individual_link($link_url) {
        $start = microtime(true);
        $response = wp_remote_head($link_url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'AME Link Health Monitor/1.0'
            ]
        ]);
        $elapsed_ms = (microtime(true) - $start) * 1000;
        
        if (is_wp_error($response)) {
            return [
                'status' => 'broken',
                'error' => $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code >= 400) {
            return [
                'status' => 'broken',
                'error' => 'HTTP ' . $response_code,
                'response_code' => $response_code
            ];
        }
        
        if ($elapsed_ms > 3000) {
            return [
                'status' => 'slow',
                'response_time' => round($elapsed_ms)
            ];
        }
        
        return [
            'status' => 'healthy',
            'response_code' => $response_code,
            'response_time' => round($elapsed_ms)
        ];
    }
    
    /**
     * Auto-heal broken affiliate links
     * @param string $domain Domain containing broken links
     * @param array $broken_links Array of broken link data
     * @return array Healing results
     */
    private function auto_heal_broken_links($domain, $broken_links) {
        $healing_results = [
            'total_attempts' => count($broken_links),
            'healed_count' => 0,
            'failed_count' => 0,
            'healing_actions' => []
        ];
        
        foreach ($broken_links as $broken_link) {
            $healing_result = $this->attempt_link_repair($broken_link);
            
            if ($healing_result['success']) {
                $healing_results['healed_count']++;
                $healing_results['healing_actions'][] = [
                    'link_id' => $broken_link['id'],
                    'action' => $healing_result['action'],
                    'status' => 'success'
                ];
                
                // Log successful auto-repair
                $this->log_successful_auto_repair($broken_link, $healing_result);
            } else {
                $healing_results['failed_count']++;
                $healing_results['healing_actions'][] = [
                    'link_id' => $broken_link['id'],
                    'action' => 'escalation',
                    'status' => 'failed',
                    'error' => $healing_result['error']
                ];
                
                // Escalate to manual review
                $this->escalate_to_manual_review($broken_link, $healing_result['error']);
            }
        }
        
        return $healing_results;
    }
    
    /**
     * Attempt to repair a broken affiliate link
     * @param array $broken_link Broken link data
     * @return array Repair result
     */
    private function attempt_link_repair($broken_link) {
        global $wpdb;
        
        // Strategy 1: Check if it's a temporary redirect issue
        $response = wp_remote_get($broken_link['url'], [
            'timeout' => 10,
            'redirection' => 10
        ]);
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code >= 200 && $response_code < 400) {
                // Link is working now, update status
                $wpdb->update(
                    $wpdb->prefix . 'affiliate_link_performance',
                    ['status' => 'active', 'updated_at' => current_time('mysql')],
                    ['id' => $broken_link['id']],
                    ['%s', '%s'],
                    ['%d']
                );
                
                return [
                    'success' => true,
                    'action' => 'status_update',
                    'message' => 'Link is now responding correctly'
                ];
            }
        }
        
        // Strategy 2: Try to regenerate affiliate link
        if (!empty($broken_link['affiliate_id'])) {
            // NOTE: In many setups, a base URL is needed to generate a referral link.
            $new_affiliate_url = affwp_get_affiliate_referral_url([
                'affiliate_id' => $broken_link['affiliate_id'],
            ]);
            
            if ($new_affiliate_url && $new_affiliate_url !== $broken_link['url']) {
                // Test the new URL
                $test_response = wp_remote_head($new_affiliate_url, ['timeout' => 5]);
                
                if (!is_wp_error($test_response) && wp_remote_retrieve_response_code($test_response) < 400) {
                    // Update with new URL
                    $wpdb->update(
                        $wpdb->prefix . 'affiliate_link_performance',
                        [
                            'link_url' => $new_affiliate_url,
                            'status' => 'active',
                            'updated_at' => current_time('mysql')
                        ],
                        ['id' => $broken_link['id']],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );
                    
                    return [
                        'success' => true,
                        'action' => 'url_regeneration',
                        'message' => 'Regenerated affiliate URL successfully'
                    ];
                }
            }
        }
        
        // Strategy 3: Mark as inactive and schedule for review
        $wpdb->update(
            $wpdb->prefix . 'affiliate_link_performance',
            [
                'status' => 'broken',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $broken_link['id']],
            ['%s', '%s'],
            ['%d']
        );
        
        return [
            'success' => false,
            'action' => 'mark_broken',
            'error' => 'Unable to automatically repair link'
        ];
    }
    
    /**
     * Escalate broken link to manual review
     * @param array $broken_link Broken link data
     * @param string $error_message Error message
     */
    private function escalate_to_manual_review($broken_link, $error_message) {
        global $wpdb;
        
        // Insert into manual review queue
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_manual_review_queue',
            [
                'link_id' => $broken_link['id'],
                'affiliate_id' => $broken_link['affiliate_id'],
                'issue_type' => 'broken_link',
                'description' => $error_message,
                'priority' => 'medium',
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        // Send notification to administrators
        $this->send_escalation_notification($broken_link, $error_message);
    }
    
    /**
     * Send notification about escalated issues
     * @param array $broken_link Broken link data
     * @param string $error_message Error message
     */
    private function send_escalation_notification($broken_link, $error_message) {
        $admin_email = get_option('admin_email');
        $subject = __('Affiliate Link Requires Manual Review', 'affiliate-master-enhancement');
        
        $message = sprintf(
            __("A broken affiliate link could not be automatically repaired and requires manual review:\n\nLink URL: %s\nAffiliate ID: %d\nError: %s\n\nPlease review this in the affiliate management dashboard.", 'affiliate-master-enhancement'),
            $broken_link['url'],
            $broken_link['affiliate_id'],
            $error_message
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Log successful auto-repair
     * @param array $broken_link Repaired link data
     * @param array $healing_result Healing result
     */
    private function log_successful_auto_repair($broken_link, $healing_result) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_health_log',
            [
                'link_id' => $broken_link['id'],
                'action' => 'auto_repair',
                'action_type' => $healing_result['action'],
                'description' => $healing_result['message'],
                'status' => 'success',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        // Update repair statistics
        $repair_stats = get_option('ame_auto_repair_stats', [
            'total_repairs' => 0,
            'successful_repairs' => 0,
            'repair_types' => []
        ]);
        
        $repair_stats['total_repairs']++;
        $repair_stats['successful_repairs']++;
        
        if (!isset($repair_stats['repair_types'][$healing_result['action']])) {
            $repair_stats['repair_types'][$healing_result['action']] = 0;
        }
        $repair_stats['repair_types'][$healing_result['action']]++;
        
        update_option('ame_auto_repair_stats', $repair_stats);
    }
    
    /**
     * Generate improvement recommendations for domain
     * @param string $domain Domain
     * @param array $domain_health Domain health data
     * @return array Recommendations
     */
    private function generate_improvement_recommendations($domain, $domain_health) {
        $recommendations = [];
        
        // Response time recommendations
        if ($domain_health['response_time'] > 3000) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'performance',
                'title' => 'Improve Server Response Time',
                'description' => 'Current response time is ' . $domain_health['response_time'] . 'ms. Consider optimising server performance.',
                'actions' => [
                    'Check server resources and scaling',
                    'Implement caching solutions',
                    'Optimise database queries',
                    'Consider CDN implementation'
                ]
            ];
        }
        
        // API recommendations
        if ($domain_health['api_status'] !== 'operational') {
            $recommendations[] = [
                'priority' => 'critical',
                'category' => 'api',
                'title' => 'Fix API Issues',
                'description' => 'Affiliate API endpoints are not responding correctly.',
                'actions' => [
                    'Check API endpoint configurations',
                    'Verify plugin installations',
                    'Test API authentication',
                    'Review server error logs'
                ]
            ];
        }
        
        // Conversion rate recommendations
        if (isset($domain_health['conversion_rates']['trend']) && 
            $domain_health['conversion_rates']['trend'] === 'declining') {
            
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'conversion',
                'title' => 'Address Declining Conversion Rates',
                'description' => 'Conversion rates have declined by ' . $domain_health['conversion_rates']['decline_percentage'] . '%.',
                'actions' => [
                    'Review recent site changes',
                    'Test affiliate link visibility',
                    'Analyse user experience issues',
                    'Conduct A/B tests on link placement'
                ]
            ];
        }
        
        // Broken links recommendations
        if ($domain_health['broken_links'] > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'links',
                'title' => 'Fix Broken Affiliate Links',
                'description' => $domain_health['broken_links'] . ' broken affiliate links detected.',
                'actions' => [
                    'Review and update broken links',
                    'Check affiliate program status',
                    'Implement link monitoring',
                    'Set up automated notifications'
                ]
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Generate overall health recommendations
     * @param array $health_report Complete health report
     * @return array Overall recommendations
     */
    private function generate_health_recommendations($health_report) {
        $recommendations = [];
        
        // Overall system health recommendations
        if ($health_report['overall_status'] === 'critical') {
            $recommendations[] = [
                'priority' => 'critical',
                'title' => 'Immediate Action Required',
                'description' => 'Critical issues detected across ' . $health_report['critical_domains'] . ' domains.',
                'action' => 'Review critical domain issues immediately and implement fixes.'
            ];
        }
        
        // Performance recommendations
        if ($health_report['summary']['slow_links'] > ($health_report['summary']['total_links_checked'] * 0.1)) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Performance Optimisation Needed',
                'description' => 'High percentage of slow-performing links detected.',
                'action' => 'Implement performance optimisation strategies across domains.'
            ];
        }
        
        // Auto-healing recommendations
        if ($health_report['summary']['auto_healed_links'] > 0) {
            $recommendations[] = [
                'priority' => 'low',
                'title' => 'Monitor Auto-Healed Links',
                'description' => $health_report['summary']['auto_healed_links'] . ' links were automatically repaired.',
                'action' => 'Monitor these links closely to ensure repairs are stable.'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Run scheduled health check
     */
    public function run_health_check() {
        $start = microtime(true);
        $health_report = $this->monitor_link_health();
        $duration_ms = round((microtime(true) - $start) * 1000, 2);

        // Log health check completion into health log for trends
        $this->log_health_event(
            0,
            'health_check',
            $health_report['overall_status'],
            sprintf('Scheduled health check completed in %sms', $duration_ms)
        );

        // Update last run time (used in schedule info)
        update_option('affiliate_last_health_check', current_time('mysql'));
        
        // Send alerts if critical issues found
        if ($health_report['overall_status'] === 'critical') {
            $this->send_critical_health_alert($health_report);
        }
        
        // Also log to error_log for quick diagnostics
        error_log('AME: Health check completed - Status: ' . $health_report['overall_status'] . ' - ' . $duration_ms . 'ms');
    }
    
    /**
     * Run comprehensive health check (daily)
     */
    public function run_comprehensive_health_check() {
        // Run standard health check
        $this->run_health_check();
        
        // Additional comprehensive checks
        $this->cleanup_old_health_data();
        $this->update_performance_baselines();
        $this->generate_health_trends_report();
    }
    
    /**
     * Send critical health alert
     * @param array $health_report Health report
     */
    private function send_critical_health_alert($health_report) {
        $admin_email = get_option('admin_email');
        $subject = __('CRITICAL: Affiliate System Health Alert', 'affiliate-master-enhancement');
        
        $message = sprintf(
            __("Critical issues detected in your affiliate system:\n\nCritical Domains: %d\nBroken Links: %d\nOverall Status: %s\n\nPlease review the affiliate dashboard immediately to address these issues.", 'affiliate-master-enhancement'),
            $health_report['critical_domains'],
            $health_report['summary']['broken_links'],
            strtoupper($health_report['overall_status'])
        );
        
        wp_mail($admin_email, $subject, $message);
        
        // Send push notification if available
        if (function_exists('affiliatewp_pushover_notifications')) {
            $pushover = affiliatewp_pushover_notifications();
            $pushover->send_admin_notification($message, [
                'title' => $subject,
                'priority' => 2 // Emergency priority
            ]);
        }
    }
    
    /**
     * Clean up old health data
     */
    private function cleanup_old_health_data() {
        global $wpdb;
        
        // Clean up health logs older than 90 days
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}affiliate_health_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        
        // Clean up manual review queue items older than 30 days if resolved
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}affiliate_manual_review_queue 
            WHERE status = 'resolved' 
            AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Clean up old API logs
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}affiliate_api_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        error_log('AME: Health data cleanup completed');
    }
    
    /**
     * Update performance baselines
     */
    private function update_performance_baselines() {
        global $wpdb;
        
        $domains = $this->get_all_affiliated_domains();
        
        foreach ($domains as $domain) {
            // Calculate baseline metrics
            $baseline_data = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    AVG(response_time) as avg_response_time,
                    AVG(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as success_rate,
                    COUNT(*) as total_requests
                FROM {$wpdb->prefix}affiliate_api_logs
                WHERE domain = %s
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ", $domain));
            
            if ($baseline_data && $baseline_data->total_requests > 100) {
                update_option('ame_baseline_' . md5($domain), [
                    'response_time' => $baseline_data->avg_response_time,
                    'success_rate' => $baseline_data->success_rate,
                    'updated_at' => current_time('mysql'),
                    'sample_size' => $baseline_data->total_requests
                ]);
            }
        }
    }
    
    /**
     * Generate health trends report
     */
    private function generate_health_trends_report() {
        global $wpdb;
        
        // Get health trends over the last 30 days
        $trend_data = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(CASE WHEN action = 'auto_repair' AND status = 'success' THEN 1 END) as successful_repairs,
                COUNT(CASE WHEN action = 'health_check' AND status = 'warning' THEN 1 END) as warnings,
                COUNT(CASE WHEN action = 'health_check' AND status = 'critical' THEN 1 END) as critical_issues,
                COUNT(CASE WHEN action = 'link_validation' AND status = 'failed' THEN 1 END) as broken_links
            FROM {$wpdb->prefix}affiliate_health_log
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        
        // Calculate trend metrics
        $trend_metrics = [
            'total_health_checks' => 0,
            'successful_repairs' => 0,
            'warnings_issued' => 0,
            'critical_issues' => 0,
            'broken_links_found' => 0,
            'average_daily_issues' => 0,
            'improvement_trend' => 'stable'
        ];
        
        foreach ($trend_data as $day_data) {
            $trend_metrics['successful_repairs'] += (int) $day_data->successful_repairs;
            $trend_metrics['warnings_issued'] += (int) $day_data->warnings;
            $trend_metrics['critical_issues'] += (int) $day_data->critical_issues;
            $trend_metrics['broken_links_found'] += (int) $day_data->broken_links;
            $trend_metrics['total_health_checks'] += (int) $day_data->warnings + (int) $day_data->critical_issues;
        }
        
        if (count($trend_data) > 0) {
            $trend_metrics['average_daily_issues'] = 
                ($trend_metrics['warnings_issued'] + $trend_metrics['critical_issues']) / count($trend_data);
            
            // Determine improvement trend
            $recent_issues = array_slice($trend_data, 0, 7); // Last 7 days
            $older_issues = array_slice($trend_data, 7, 7);   // Previous 7 days
            
            if (count($recent_issues) > 0 && count($older_issues) > 0) {
                $recent_avg = array_sum(array_map(function($day) {
                    return (int) $day->warnings + (int) $day->critical_issues;
                }, $recent_issues)) / count($recent_issues);
                
                $older_avg = array_sum(array_map(function($day) {
                    return (int) $day->warnings + (int) $day->critical_issues;
                }, $older_issues)) / count($older_issues);
                
                if ($recent_avg < $older_avg * 0.8) {
                    $trend_metrics['improvement_trend'] = 'improving';
                } elseif ($recent_avg > $older_avg * 1.2) {
                    $trend_metrics['improvement_trend'] = 'declining';
                }
            }
        }
        
        update_option('ame_health_trends', [
            'metrics' => $trend_metrics,
            'daily_data' => $trend_data,
            'generated_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Validate link on generation (real-time monitoring)
     * @param string $link Generated affiliate link
     * @param int $affiliate_id Affiliate ID
     * @return string Validated link
     */
    public function validate_link_on_generation($link, $affiliate_id) {
        // Quick validation check (non-blocking removed to actually get a response)
        $cache_key = 'ame_link_valid_' . md5($link);
        $is_valid = wp_cache_get($cache_key);
        
        if ($is_valid === false) {
            $start = microtime(true);
            $response = wp_remote_head($link, [
                'timeout' => 3,
                'headers' => [
                    'User-Agent' => 'AME Link Validator/1.0'
                ]
            ]);
            $elapsed_ms = (microtime(true) - $start) * 1000;
            
            $is_valid = !is_wp_error($response);
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                $is_valid = ($response_code >= 200 && $response_code < 400);
            }
            
            wp_cache_set($cache_key, $is_valid, '', 300); // Cache for 5 minutes
            
            // Log API request for monitoring
            $this->log_api_request($link, $response);
        }
        
        if (!$is_valid) {
            // Log potential issue for follow-up
            $this->log_health_event($affiliate_id, 'link_validation', 'warning', 
                'Potentially invalid affiliate link generated: ' . $link);
        }
        
        return $link;
    }
    
    /**
     * Log API request for monitoring
     * @param string $url Request URL
     * @param mixed $response Request response
     */
    private function log_api_request($url, $response) {
        global $wpdb;
        
        $parsed_url = parse_url($url);
        $domain = $parsed_url['host'] ?? '';
        $endpoint = $parsed_url['path'] ?? '';
        
        $response_code = null;
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_api_logs',
            [
                'domain' => $domain,
                'endpoint' => $endpoint,
                'method' => 'HEAD',
                'response_code' => $response_code,
                'user_agent' => 'AME Link Validator/1.0',
                'ip_address' => $this->get_server_ip(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Log health event
     * @param int $affiliate_id Affiliate ID
     * @param string $action Action performed
     * @param string $status Event status
     * @param string $description Event description
     */
    private function log_health_event($affiliate_id, $action, $status, $description) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_health_log',
            [
                'affiliate_id' => (int) $affiliate_id,
                'action' => $action,
                'status' => $status,
                'description' => is_string($description) ? $description : wp_json_encode($description),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Get server IP address
     * @return string Server IP
     */
    private function get_server_ip() {
        return $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
    }
    
    /**
     * Register health monitoring API endpoints
     */
    public function register_health_api_endpoints() {
        // Health status endpoint (public)
        register_rest_route('affiliate-enhancement/v1', '/health/status', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_health_status'],
            'permission_callback' => '__return_true'
        ]);
        
        // Detailed health report endpoint
        register_rest_route('affiliate-enhancement/v1', '/health/report', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_health_report'],
            'permission_callback' => [$this, 'check_health_permissions']
        ]);
        
        // Manual health check endpoint
        register_rest_route('affiliate-enhancement/v1', '/health/check', [
            'methods' => 'POST',
            'callback' => [$this, 'api_run_health_check'],
            'permission_callback' => [$this, 'check_health_permissions']
        ]);
        
        // Fix broken links endpoint
        register_rest_route('affiliate-enhancement/v1', '/health/fix-links', [
            'methods' => 'POST',
            'callback' => [$this, 'api_fix_broken_links'],
            'permission_callback' => [$this, 'check_health_permissions']
        ]);
        
        // Health trends endpoint
        register_rest_route('affiliate-enhancement/v1', '/health/trends', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_health_trends'],
            'permission_callback' => [$this, 'check_health_permissions']
        ]);
        
        // Domain health endpoint
        register_rest_route('affiliate-enhancement/v1', '/health/domain/(?P<domain>[a-zA-Z0-9.-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_domain_health'],
            'permission_callback' => [$this, 'check_health_permissions']
        ]);
    }
    
    /**
     * API endpoint for basic health status
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function api_get_health_status($request) {
        $last_report = get_option('ame_last_health_report');
        
        if (!$last_report) {
            return rest_ensure_response([
                'status' => 'unknown',
                'message' => 'No health data available',
                'last_check' => null
            ]);
        }
        
        return rest_ensure_response([
            'status' => $last_report['overall_status'],
            'last_check' => $last_report['last_check'],
            'total_domains' => $last_report['total_domains'],
            'healthy_domains' => $last_report['healthy_domains'],
            'warning_domains' => $last_report['warning_domains'],
            'critical_domains' => $last_report['critical_domains'],
            'summary' => $last_report['summary']
        ]);
    }
    
    /**
     * API endpoint for detailed health report
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function api_get_health_report($request) {
        $health_report = get_option('ame_last_health_report');
        
        if (!$health_report) {
            return new WP_Error('no_health_data', 'No health report available', ['status' => 404]);
        }
        
        // Add additional context for API consumers
        $health_report['api_version'] = '1.0';
        $health_report['report_age_minutes'] = $this->calculate_report_age($health_report['last_check']);
        
        return rest_ensure_response($health_report);
    }
    
    /**
     * API endpoint for manual health check
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function api_run_health_check($request) {
        // Check if a health check is already running
        $check_in_progress = get_transient('ame_health_check_running');
        
        if ($check_in_progress) {
            return new WP_Error('check_in_progress', 'Health check already in progress', ['status' => 409]);
        }
        
        // Set flag to prevent concurrent checks
        set_transient('ame_health_check_running', true, 600); // 10 minutes
        
        try {
            // Run health check
            $start = microtime(true);
            $health_report = $this->monitor_link_health();
            $duration_ms = round((microtime(true) - $start) * 1000, 2);

            // Mirror the logging done in scheduled checks
            $this->log_health_event(0, 'health_check', $health_report['overall_status'], 'Manual health check completed');

            delete_transient('ame_health_check_running');
            
            return rest_ensure_response([
                'status' => 'completed',
                'report' => $health_report,
                'check_duration_ms' => $duration_ms
            ]);
            
        } catch (Exception $e) {
            delete_transient('ame_health_check_running');
            
            return new WP_Error('check_failed', 'Health check failed: ' . $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * API endpoint for fixing broken links
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function api_fix_broken_links($request) {
        $domain = sanitize_text_field($request->get_param('domain'));
        $force_check = $request->get_param('force_check') === 'true';
        
        if (!$domain) {
            return new WP_Error('missing_domain', 'Domain parameter required', ['status' => 400]);
        }
        
        // Validate domain exists in system
        if (!in_array($domain, $this->get_all_affiliated_domains(), true)) {
            return new WP_Error('invalid_domain', 'Domain not found in affiliate system', ['status' => 404]);
        }
        
        // Get broken links for domain
        if ($force_check) {
            $domain_health = $this->check_domain_health($domain);
        } else {
            $last_report = get_option('ame_last_health_report');
            $domain_health = $last_report['domains'][$domain] ?? null;
            
            if (!$domain_health) {
                return new WP_Error('no_health_data', 'No health data available for domain', ['status' => 404]);
            }
        }
        
        if ($domain_health['broken_links'] === 0) {
            return rest_ensure_response([
                'status' => 'no_action_needed',
                'message' => 'No broken links found for this domain',
                'domain' => $domain,
                'last_check' => $domain_health['check_timestamp']
            ]);
        }
        
        // Get broken link details and attempt healing
        $link_health = $this->check_affiliate_links_health($domain);
        $healing_results = $this->auto_heal_broken_links($domain, $link_health['broken_links']);
        
        return rest_ensure_response([
            'status' => 'completed',
            'domain' => $domain,
            'results' => $healing_results,
            'summary' => [
                'total_broken_links' => count($link_health['broken_links']),
                'successfully_healed' => $healing_results['healed_count'],
                'failed_to_heal' => $healing_results['failed_count'],
                'escalated_for_review' => $healing_results['failed_count']
            ]
        ]);
    }
    
    /**
     * API endpoint for health trends
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function api_get_health_trends($request) {
        $trends = get_option('ame_health_trends');
        
        if (!$trends) {
            return new WP_Error('no_trend_data', 'No trend data available', ['status' => 404]);
        }
        
        // Add timeframe parameter support
        $timeframe = $request->get_param('timeframe') ?: '30days';
        
        if ($timeframe !== '30days') {
            // Generate trends for different timeframes if requested
            $trends = $this->generate_trends_for_timeframe($timeframe);
        }
        
        return rest_ensure_response($trends);
    }
    
    /**
     * API endpoint for specific domain health
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function api_get_domain_health($request) {
        $domain = urldecode($request['domain']);
        $force_check = $request->get_param('force_check') === 'true';
        
        if ($force_check) {
            $domain_health = $this->check_domain_health($domain);
        } else {
            $last_report = get_option('ame_last_health_report');
            $domain_health = $last_report['domains'][$domain] ?? null;
            
            if (!$domain_health) {
                return new WP_Error('domain_not_found', 'Domain health data not available', ['status' => 404]);
            }
        }
        
        // Add additional domain-specific insights
        $domain_health['insights'] = $this->generate_domain_insights($domain, $domain_health);
        $domain_health['historical_performance'] = $this->get_domain_historical_performance($domain);
        
        return rest_ensure_response($domain_health);
    }
    
    /**
     * Check health monitoring permissions
     * @param WP_REST_Request $request Request object
     * @return bool Permission result
     */
    public function check_health_permissions($request) {
        return current_user_can('manage_affiliates') || current_user_can('manage_options');
    }
    
    /**
     * AJAX handler for manual health check
     */
    public function ajax_manual_health_check() {
        check_ajax_referer('ame_health_check', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die('Insufficient permissions');
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        try {
            if ($domain) {
                // Check specific domain
                $health_data = $this->check_domain_health($domain);
                wp_send_json_success([
                    'type' => 'domain_check',
                    'domain' => $domain,
                    'data' => $health_data
                ]);
            } else {
                // Check all domains
                $health_report = $this->monitor_link_health();
                wp_send_json_success([
                    'type' => 'full_check',
                    'data' => $health_report
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error('Health check failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for fixing broken links
     */
    public function ajax_fix_broken_links() {
        check_ajax_referer('ame_fix_links', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die('Insufficient permissions');
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $link_ids = array_map('intval', $_POST['link_ids'] ?? []);
        
        if (!$domain) {
            wp_send_json_error('Domain is required');
        }
        
        try {
            if (!empty($link_ids)) {
                // Fix specific links
                $healing_results = $this->fix_specific_links($link_ids);
            } else {
                // Fix all broken links for domain
                $link_health = $this->check_affiliate_links_health($domain);
                $healing_results = $this->auto_heal_broken_links($domain, $link_health['broken_links']);
            }
            
            wp_send_json_success($healing_results);
            
        } catch (Exception $e) {
            wp_send_json_error('Link repair failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Calculate report age in minutes
     * @param string $timestamp Report timestamp
     * @return int Age in minutes
     */
    private function calculate_report_age($timestamp) {
        $report_time = strtotime($timestamp);
        $current_time = time();
        return round(($current_time - $report_time) / 60);
    }
    
    /**
     * Generate trends for specific timeframe
     * @param string $timeframe Timeframe specification
     * @return array Trend data
     */
    private function generate_trends_for_timeframe($timeframe) {
        // Implementation would generate trends for different timeframes
        // For now, return default trends
        return get_option('ame_health_trends', []);
    }
    
    /**
     * Generate domain-specific insights
     * @param string $domain Domain name
     * @param array $domain_health Domain health data
     * @return array Domain insights
     */
    private function generate_domain_insights($domain, $domain_health) {
        $insights = [];
        
        // Performance insights
        if ($domain_health['response_time'] > 3000) {
            $insights[] = [
                'type' => 'performance',
                'severity' => 'warning',
                'message' => 'Response time is slower than recommended (>3s)',
                'recommendation' => 'Consider implementing caching or optimizing server resources'
            ];
        }
        
        // Link health insights
        if ($domain_health['broken_links'] > 0) {
            $insights[] = [
                'type' => 'links',
                'severity' => 'high',
                'message' => sprintf('%d broken affiliate links detected', $domain_health['broken_links']),
                'recommendation' => 'Run automatic link repair or review links manually'
            ];
        }
        
        // API health insights
        if ($domain_health['api_status'] !== 'operational') {
            $insights[] = [
                'type' => 'api',
                'severity' => 'critical',
                'message' => 'API endpoints not responding correctly',
                'recommendation' => 'Check plugin installation and server configuration'
            ];
        }
        
        // Conversion rate insights
        if (isset($domain_health['conversion_rates']['trend']) && 
            $domain_health['conversion_rates']['trend'] === 'declining') {
            
            $insights[] = [
                'type' => 'conversion',
                'severity' => 'medium',
                'message' => 'Conversion rates are declining',
                'recommendation' => 'Review recent changes and optimize affiliate link placement'
            ];
        }
        
        return $insights;
    }
    
    /**
     * Get domain historical performance
     * @param string $domain Domain name
     * @return array Historical performance data
     */
    private function get_domain_historical_performance($domain) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                AVG(response_time) as avg_response_time,
                COUNT(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 END) / COUNT(*) * 100 as success_rate,
                COUNT(*) as total_requests
            FROM {$wpdb->prefix}affiliate_api_logs
            WHERE domain = %s
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", $domain));
    }
    
    /**
     * Fix specific links by ID
     * @param array $link_ids Array of link IDs to fix
     * @return array Healing results
     */
    private function fix_specific_links($link_ids) {
        global $wpdb;
        
        $healing_results = [
            'total_attempts' => count($link_ids),
            'healed_count' => 0,
            'failed_count' => 0,
            'healing_actions' => []
        ];
        
        foreach ($link_ids as $link_id) {
            $link = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}affiliate_link_performance
                WHERE id = %d
            ", $link_id));
            
            if (!$link) {
                $healing_results['failed_count']++;
                continue;
            }
            
            $broken_link_data = [
                'id' => $link->id,
                'url' => $link->link_url,
                'affiliate_id' => $link->affiliate_id,
                'error' => 'Manual repair requested'
            ];
            
            $healing_result = $this->attempt_link_repair($broken_link_data);
            
            if ($healing_result['success']) {
                $healing_results['healed_count']++;
                $healing_results['healing_actions'][] = [
                    'link_id' => $link_id,
                    'action' => $healing_result['action'],
                    'status' => 'success'
                ];
            } else {
                $healing_results['failed_count']++;
                $healing_results['healing_actions'][] = [
                    'link_id' => $link_id,
                    'action' => 'manual_review_required',
                    'status' => 'failed',
                    'error' => $healing_result['error']
                ];
            }
        }
        
        return $healing_results;
    }
    
    /**
     * Get all affiliated domains
     * @return array List of domains
     */
    private function get_all_affiliated_domains() {
        global $wpdb;
        
        // Get domains from authorized domains table
        $domains = $wpdb->get_col("
            SELECT domain 
            FROM {$wpdb->prefix}affiliate_authorized_domains 
            WHERE is_active = 1
        ");
        
        // Fallback to domains from link performance data
        if (empty($domains)) {
            $domains = $wpdb->get_col("
                SELECT DISTINCT domain 
                FROM {$wpdb->prefix}affiliate_link_performance 
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
        }
        
        return array_filter($domains);
    }
    
    /**
     * Get health monitoring statistics
     * @return array Health statistics
     */
    public function get_health_statistics() {
        $repair_stats = get_option('ame_auto_repair_stats', [
            'total_repairs' => 0,
            'successful_repairs' => 0,
            'repair_types' => []
        ]);
        
        $last_report = get_option('ame_last_health_report');
        $health_trends = get_option('ame_health_trends', []);
        
        return [
            'repair_statistics' => $repair_stats,
            'last_health_report' => $last_report,
            'health_trends' => $health_trends,
            'monitoring_uptime' => $this->calculate_monitoring_uptime(),
            'system_performance' => $this->get_system_performance_metrics()
        ];
    }
    
    /**
     * Calculate monitoring system uptime
     * @return array Uptime statistics
     */
    private function calculate_monitoring_uptime() {
        global $wpdb;
        
        $uptime_data = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_checks,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_checks,
                COUNT(CASE WHEN status IN ('warning', 'critical') THEN 1 END) as issues_detected,
                MIN(created_at) as first_check,
                MAX(created_at) as last_check
            FROM {$wpdb->prefix}affiliate_health_log
            WHERE action = 'health_check'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $total_checks = intval($uptime_data->total_checks ?? 0);
        $successful_checks = intval($uptime_data->successful_checks ?? 0);
        $issues_detected = intval($uptime_data->issues_detected ?? 0);
        
        $uptime_percentage = $total_checks > 0 ? (($successful_checks / $total_checks) * 100) : 100;
        
        return [
            'uptime_percentage' => round($uptime_percentage, 2),
            'total_checks' => $total_checks,
            'successful_checks' => $successful_checks,
            'issues_detected' => $issues_detected,
            'first_check' => $uptime_data->first_check ?? null,
            'last_check' => $uptime_data->last_check ?? null,
            'monitoring_period_days' => 30
        ];
    }

    /**
     * System performance metrics (API, memory, CPU, errors)
     * @return array
     */
    private function get_system_performance_metrics() {
        global $wpdb;
        
        $performance_data = $wpdb->get_row("
            SELECT 
                AVG(CASE WHEN meta_key = 'response_time' THEN meta_value END) as avg_response_time,
                MAX(CASE WHEN meta_key = 'response_time' THEN meta_value END) as max_response_time,
                COUNT(CASE WHEN meta_key = 'error_rate' AND meta_value > 5 THEN 1 END) as high_error_periods,
                COUNT(CASE WHEN meta_key = 'memory_usage' THEN 1 END) as memory_checks,
                AVG(CASE WHEN meta_key = 'memory_usage' THEN meta_value END) as avg_memory_usage,
                MAX(CASE WHEN meta_key = 'memory_usage' THEN meta_value END) as peak_memory_usage
            FROM {$wpdb->prefix}affiliate_health_log 
            WHERE action = 'performance_check'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

        $cpu_data = $wpdb->get_row("
            SELECT 
                AVG(meta_value) as avg_cpu_usage,
                MAX(meta_value) as peak_cpu_usage
            FROM {$wpdb->prefix}affiliate_health_log 
            WHERE action = 'performance_check'
            AND meta_key = 'cpu_usage'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");

        return [
            'response_time' => [
                'average' => round(floatval($performance_data->avg_response_time ?? 0), 2),
                'maximum' => round(floatval($performance_data->max_response_time ?? 0), 2),
                'status' => $this->evaluate_response_time_status($performance_data->avg_response_time ?? 0)
            ],
            'memory_usage' => [
                'average_mb' => round(floatval($performance_data->avg_memory_usage ?? 0) / 1024 / 1024, 2),
                'peak_mb' => round(floatval($performance_data->peak_memory_usage ?? 0) / 1024 / 1024, 2),
                'checks_performed' => intval($performance_data->memory_checks ?? 0),
                'status' => $this->evaluate_memory_usage_status($performance_data->peak_memory_usage ?? 0)
            ],
            'cpu_usage' => [
                'average_percent' => round(floatval($cpu_data->avg_cpu_usage ?? 0), 2),
                'peak_percent' => round(floatval($cpu_data->peak_cpu_usage ?? 0), 2),
                'status' => $this->evaluate_cpu_usage_status($cpu_data->avg_cpu_usage ?? 0)
            ],
            'error_incidents' => [
                'high_error_periods' => intval($performance_data->high_error_periods ?? 0),
                'status' => intval($performance_data->high_error_periods ?? 0) > 5 ? 'warning' : 'healthy'
            ]
        ];
    }

    /**
     * Evaluate response time performance status
     * @param float $avg_response_time Average response time in milliseconds
     * @return string Performance status
     */
    private function evaluate_response_time_status($avg_response_time) {
        if ($avg_response_time > 3000) {
            return 'critical';
        } elseif ($avg_response_time > 1500) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Evaluate memory usage status
     * @param int $peak_memory_bytes Peak memory usage in bytes
     * @return string Memory status
     */
    private function evaluate_memory_usage_status($peak_memory_bytes) {
        $peak_memory_mb = $peak_memory_bytes / 1024 / 1024;
        
        if ($peak_memory_mb > 512) {
            return 'critical';
        } elseif ($peak_memory_mb > 256) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Evaluate CPU usage status
     * @param float $avg_cpu_percent Average CPU usage percentage
     * @return string CPU status
     */
    private function evaluate_cpu_usage_status($avg_cpu_percent) {
        if ($avg_cpu_percent > 80) {
            return 'critical';
        } elseif ($avg_cpu_percent > 50) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Get detailed API endpoint health metrics
     * @return array Endpoint health data
     */
    private function get_api_endpoint_health() {
        global $wpdb;
        
        $endpoints = [
            '/validate-code',
            '/create-code',
            '/analytics',
            '/health-check'
        ];

        $endpoint_health = [];

        foreach ($endpoints as $endpoint) {
            $health_data = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_requests,
                    COUNT(CASE WHEN status = 'error' THEN 1 END) as error_requests,
                    AVG(CASE WHEN meta_key = 'response_time' THEN meta_value END) as avg_response_time,
                    MAX(created_at) as last_request
                FROM {$wpdb->prefix}affiliate_health_log 
                WHERE action = 'api_request'
                AND details LIKE %s
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ", '%' . $endpoint . '%'));

            $total_requests = intval($health_data->total_requests ?? 0);
            $successful_requests = intval($health_data->successful_requests ?? 0);
            $error_requests = intval($health_data->error_requests ?? 0);
            $success_rate = $total_requests > 0 ? (($successful_requests / $total_requests) * 100) : 100;

            $endpoint_health[$endpoint] = [
                'total_requests' => $total_requests,
                'successful_requests' => $successful_requests,
                'error_requests' => $error_requests,
                'success_rate' => round($success_rate, 2),
                'avg_response_time' => round(floatval($health_data->avg_response_time ?? 0), 2),
                'last_request' => $health_data->last_request ?? null,
                'status' => $this->determine_endpoint_status($success_rate, $health_data->avg_response_time ?? 0),
                'requests_per_hour' => $this->calculate_requests_per_hour($total_requests)
            ];
        }

        return $endpoint_health;
    }

    /**
     * Determine endpoint health status
     * @param float $success_rate Success rate percentage
     * @param float $avg_response_time Average response time
     * @return string Health status
     */
    private function determine_endpoint_status($success_rate, $avg_response_time) {
        if ($success_rate < 90 || $avg_response_time > 3000) {
            return 'critical';
        } elseif ($success_rate < 95 || $avg_response_time > 1500) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Calculate requests per hour based on 24-hour total
     * @param int $total_requests Total requests in 24 hours
     * @return int Requests per hour
     */
    private function calculate_requests_per_hour($total_requests) {
        return intval($total_requests / 24);
    }

    /**
     * Get database health metrics
     * @return array Database health information
     */
    private function get_database_health() {
        global $wpdb;
        
        // Check table sizes and row counts
        $table_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                table_rows
            FROM information_schema.TABLES 
            WHERE table_schema = %s
            AND table_name LIKE %s
        ", DB_NAME, $wpdb->prefix . 'affiliate_%'));

        // Check for slow queries in the last hour
        $slow_queries = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}affiliate_health_log 
            WHERE action = 'slow_query'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");

        // Check connection pool status (retrieve value column)
        $threads_row = $wpdb->get_row("SHOW STATUS LIKE 'Threads_connected'", ARRAY_N);
        $vars_row    = $wpdb->get_row("SHOW VARIABLES LIKE 'max_connections'", ARRAY_N);
        $active_connections = isset($threads_row[1]) ? (int)$threads_row[1] : 0;
        $max_connections    = isset($vars_row[1])    ? (int)$vars_row[1]    : 0;

        // Sum sizes
        $total_size_mb = 0.0;
        if (is_array($table_stats)) {
            foreach ($table_stats as $t) {
                $total_size_mb += (float) ($t->size_mb ?? 0);
            }
        }

        return [
            'tables' => $table_stats,
            'slow_queries_last_hour' => $slow_queries,
            'active_connections' => $active_connections,
            'max_connections' => $max_connections,
            'connection_usage_percent' => $max_connections > 0 
                ? round(($active_connections / $max_connections) * 100, 2) 
                : 0,
            'total_size_mb' => round($total_size_mb, 2),
            'status' => $this->evaluate_database_status($slow_queries, $active_connections, $max_connections)
        ];
    }

    /**
     * Evaluate database performance status
     * @param int $slow_queries Number of slow queries
     * @param int $active_connections Active database connections
     * @param int $max_connections Maximum allowed connections
     * @return string Database status
     */
    private function evaluate_database_status($slow_queries, $active_connections, $max_connections) {
        $connection_usage = $max_connections > 0 ? ($active_connections / $max_connections) * 100 : 0;
        
        if ($slow_queries > 10 || $connection_usage > 80) {
            return 'critical';
        } elseif ($slow_queries > 5 || $connection_usage > 60) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Generate comprehensive health report
     * @return array Complete system health report
     */
    public function generate_health_report() {
        $report_timestamp = current_time('mysql');
        
        $health_report = [
            'timestamp' => $report_timestamp,
            'system_uptime' => $this->calculate_monitoring_uptime(),
            'performance_metrics' => $this->get_system_performance_metrics(),
            'api_endpoints' => $this->get_api_endpoint_health(),
            'database_health' => $this->get_database_health(),
            'security_incidents' => $this->get_recent_security_incidents(),
            'resource_usage' => $this->get_resource_usage_summary(),
            'recommendations' => $this->generate_system_recommendations()
        ];

        // Store health report for historical tracking
        $this->store_health_report($health_report);

        return $health_report;
    }

    /**
     * Get recent security incidents
     * @return array Security incident data
     */
    private function get_recent_security_incidents() {
        global $wpdb;
        
        $incidents = $wpdb->get_results("
            SELECT 
                action,
                status,
                details,
                created_at,
                COUNT(*) as incident_count
            FROM {$wpdb->prefix}affiliate_health_log 
            WHERE action IN ('rate_limit_exceeded', 'invalid_api_key', 'suspicious_activity', 'failed_authentication')
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY action, status, DATE(created_at)
            ORDER BY created_at DESC
            LIMIT 50
        ");

        $total_incidents = 0;
        if (is_array($incidents)) {
            foreach ($incidents as $i) {
                $total_incidents += (int) ($i->incident_count ?? 0);
            }
        }
        
        return [
            'incidents' => $incidents,
            'total_count' => $total_incidents,
            'severity_level' => $this->calculate_security_severity($total_incidents),
            'trending' => $this->calculate_security_trend($incidents)
        ];
    }

    /**
     * Calculate security severity level
     * @param int $incident_count Total incident count
     * @return string Severity level
     */
    private function calculate_security_severity($incident_count) {
        if ($incident_count > 100) {
            return 'critical';
        } elseif ($incident_count > 20) {
            return 'high';
        } elseif ($incident_count > 5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate security incident trend
     * @param array $incidents Security incidents
     * @return string Trend direction
     */
    private function calculate_security_trend($incidents) {
        if (empty($incidents) || count($incidents) < 2) {
            return 'stable';
        }

        $mid = (int) floor(count($incidents) / 2);
        $recent_incidents = array_slice($incidents, 0, $mid);
        $older_incidents  = array_slice($incidents, $mid);

        $recent_total = array_sum(array_map(function($row){ return (int) ($row->incident_count ?? 0); }, $recent_incidents));
        $older_total  = array_sum(array_map(function($row){ return (int) ($row->incident_count ?? 0); }, $older_incidents));

        if ($recent_total > $older_total * 1.2) {
            return 'increasing';
        } elseif ($recent_total < $older_total * 0.8) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Get resource usage summary
     * @return array Resource usage data
     */
    private function get_resource_usage_summary() {
        // Get WordPress memory limit and current usage
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);

        // Get disk space information
        $disk_free_bytes = @disk_free_space(ABSPATH);
        $disk_total_bytes = @disk_total_space(ABSPATH);

        $disk_free_bytes = $disk_free_bytes !== false ? $disk_free_bytes : 0;
        $disk_total_bytes = $disk_total_bytes !== false ? $disk_total_bytes : 0;

        return [
            'memory' => [
                'limit_mb' => $memory_limit > 0 ? round($memory_limit / 1024 / 1024, 2) : null,
                'current_mb' => round($memory_usage / 1024 / 1024, 2),
                'peak_mb' => round($memory_peak / 1024 / 1024, 2),
                'usage_percent' => $memory_limit > 0 ? round(($memory_usage / $memory_limit) * 100, 2) : null
            ],
            'disk_space' => [
                'free_gb' => round($disk_free_bytes / 1024 / 1024 / 1024, 2),
                'total_gb' => round($disk_total_bytes / 1024 / 1024 / 1024, 2),
                'used_percent' => $disk_total_bytes > 0 ? round((($disk_total_bytes - $disk_free_bytes) / $disk_total_bytes) * 100, 2) : null
            ],
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'server_load' => $this->get_server_load_average()
        ];
    }

    /**
     * Get server load average if available
     * @return string Server load information
     */
    private function get_server_load_average() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2]);
        }
        return 'N/A';
    }

    /**
     * Generate health recommendations based on system status
     * (Renamed to avoid conflict with domain aggregation recommendations)
     * @return array Health recommendations
     */
    private function generate_system_recommendations() {
        $recommendations = [];
        
        $performance = $this->get_system_performance_metrics();
        $database = $this->get_database_health();
        $resources = $this->get_resource_usage_summary();

        // Performance recommendations
        if ($performance['response_time']['status'] === 'critical') {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'performance',
                'message' => 'API response times are critically high. Consider optimising database queries and implementing response caching.',
                'action' => 'optimise_response_time'
            ];
        }

        // Memory usage recommendations
        if (is_numeric($resources['memory']['usage_percent']) && $resources['memory']['usage_percent'] > 80) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'resources',
                'message' => 'Memory usage is high. Consider increasing PHP memory limit or optimising memory-intensive operations.',
                'action' => 'increase_memory_limit'
            ];
        }

        // Database recommendations
        if ($database['slow_queries_last_hour'] > 10) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'database',
                'message' => 'Multiple slow database queries detected. Review and optimise query performance.',
                'action' => 'optimise_database_queries'
            ];
        }

        // Disk space recommendations
        if (is_numeric($resources['disk_space']['used_percent']) && $resources['disk_space']['used_percent'] > 85) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'resources',
                'message' => 'Disk space is running low. Clean up old log files and consider increasing storage capacity.',
                'action' => 'free_disk_space'
            ];
        }

        return $recommendations;
    }

    /**
     * Store health report in database for historical analysis
     * @param array $health_report Complete health report data
     * @return bool Success status
     */
    private function store_health_report($health_report) {
        global $wpdb;
        
        $report_data = wp_json_encode($health_report);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'affiliate_health_log',
            [
                'action' => 'health_report',
                'status' => 'completed',
                'details' => $report_data,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );

        // Clean up old health reports (keep last 30 days)
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}affiliate_health_log 
            WHERE action = 'health_report' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        return $result !== false;
    }

    /**
     * Get health check schedule information
     * @return array Schedule information
     */
    public function get_health_check_schedule() {
        $next_hourly = wp_next_scheduled('ame_hourly_health_check');
        $next_daily  = wp_next_scheduled('ame_daily_comprehensive_check');
        
        return [
            'next_run_hourly' => $next_hourly ? date('Y-m-d H:i:s', $next_hourly) : 'Not scheduled',
            'next_run_daily'  => $next_daily  ? date('Y-m-d H:i:s', $next_daily)  : 'Not scheduled',
            'frequencies'     => ['hourly', 'daily'],
            'last_run'        => get_option('affiliate_last_health_check', 'Never'),
            'auto_alerts'     => (bool) get_option('affiliate_health_alerts_enabled', false),
            'email_notifications' => (bool) get_option('affiliate_health_email_notifications', false)
        ];
    }

    /**
     * Manual health check trigger
     * @return array Health check results
     */
    public function trigger_manual_health_check() {
        $start_time = microtime(true);
        
        // Run comprehensive health check
        $health_report = $this->generate_health_report();
        
        // Log manual health check execution
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        $this->log_health_event(
            0,
            'manual_health_check',
            'completed',
            [
                'execution_time_ms' => $execution_time,
                'triggered_by' => get_current_user_id(),
                'report_summary' => [
                    'uptime_percentage' => $health_report['system_uptime']['uptime_percentage'] ?? null,
                    'performance_status' => $health_report['performance_metrics']['response_time']['status'] ?? null,
                    'security_incidents' => $health_report['security_incidents']['total_count'] ?? null
                ]
            ]
        );

        return [
            'success' => true,
            'execution_time_ms' => $execution_time,
            'health_report' => $health_report,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Clean up old health log entries
     * @param int $days Number of days to retain
     * @return int Number of entries cleaned
     */
    public function cleanup_health_logs($days = 30) {
        global $wpdb;
        
        $deleted_count = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}affiliate_health_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND action NOT IN ('health_report', 'critical_alert')
        ", $days));

        $this->log_health_event(0, 'log_cleanup', 'completed', [
            'entries_deleted' => (int) $deleted_count,
            'retention_days' => $days
        ]);

        return (int) $deleted_count;
    }
}