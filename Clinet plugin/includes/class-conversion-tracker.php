<?php
/**
 * Conversion Tracker Class
 *
 * Handles conversion tracking and attribution for affiliate referrals.
 * Manages conversion data, attribution models, and reporting.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Conversion_Tracker {

    /**
     * Get current visit ID
     *
     * @return string|null Visit ID
     */
    private function get_current_visit_id() {
        $session_cookie = 'affiliate_client_visit_id';
        return isset($_COOKIE[$session_cookie]) ? sanitize_text_field($_COOKIE[$session_cookie]) : null;
    }

    /**
     * Get site currency
     *
     * @return string Currency code
     */
    private function get_site_currency() {
        // Try WooCommerce first
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        
        // Try EDD
        if (function_exists('edd_get_currency')) {
            return edd_get_currency();
        }
        
        // Default to USD
        return apply_filters('affiliate_client_default_currency', 'USD');
    }

    /**
     * Get current URL
     *
     * @return string Current URL
     */
    private function get_current_url() {
        if (isset($_SERVER['REQUEST_URI'])) {
            return home_url($_SERVER['REQUEST_URI']);
        }
        return home_url();
    }

    /**
     * Get user IP address
     *
     * @return string IP address
     */
    private function get_user_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Get user agent
     *
     * @return string User agent
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        if ($this->config['debug_mode']) {
            error_log('[Affiliate Client Full - Conversion Tracker] ' . $message);
        }
    }

    /**
     * Output conversion tracking script
     */
    public function output_conversion_tracking_script() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        ?>
        <script type="text/javascript">
        (function() {
            // Conversion tracking helper
            window.AffiliateClientConversion = {
                track: function(amount, reference, data) {
                    if (typeof affiliateClientConfig === 'undefined') {
                        console.warn('Affiliate Client not initialized');
                        return;
                    }

                    var conversionData = {
                        action: 'affiliate_client_track_conversion',
                        nonce: affiliateClientConfig.nonce,
                        amount: amount || 0,
                        reference: reference || '',
                        data: data || {}
                    };

                    jQuery.post(affiliateClientConfig.ajaxUrl, conversionData, function(response) {
                        if (response.success) {
                            console.log('Conversion tracked:', response);
                            // Fire custom event
                            jQuery(document).trigger('affiliate_client_conversion_tracked', [response]);
                        } else {
                            console.warn('Conversion tracking failed:', response.message);
                        }
                    }).fail(function() {
                        console.error('Conversion tracking request failed');
                    });
                },

                trackPurchase: function(orderData) {
                    this.track(orderData.total, orderData.orderId, {
                        event_type: 'purchase',
                        products: orderData.products || [],
                        currency: orderData.currency || '<?php echo $this->get_site_currency(); ?>',
                        customer_email: orderData.customerEmail || '',
                        payment_method: orderData.paymentMethod || ''
                    });
                },

                trackSignup: function(userData) {
                    this.track(0, userData.userId, {
                        event_type: 'signup',
                        user_email: userData.email || '',
                        signup_type: userData.type || 'general'
                    });
                }
            };

            // Auto-track conversions based on page indicators
            jQuery(document).ready(function($) {
                // WooCommerce thank you page
                if ($('.woocommerce-order-received').length > 0) {
                    var orderKey = new URLSearchParams(window.location.search).get('key');
                    if (orderKey) {
                        // Extract order data if available
                        var orderTotal = $('.woocommerce-Price-amount').first().text().replace(/[^\d.,]/g, '');
                        if (orderTotal) {
                            AffiliateClientConversion.track(parseFloat(orderTotal), orderKey, {
                                event_type: 'woocommerce_purchase',
                                auto_detected: true
                            });
                        }
                    }
                }

                // EDD success page
                if ($('.edd_success').length > 0) {
                    var paymentKey = new URLSearchParams(window.location.search).get('payment_key');
                    if (paymentKey) {
                        AffiliateClientConversion.track(0, paymentKey, {
                            event_type: 'edd_purchase',
                            auto_detected: true
                        });
                    }
                }

                // Generic success page detection
                if (window.location.pathname.includes('/success') || 
                    window.location.pathname.includes('/thank-you') ||
                    window.location.search.includes('success=1')) {
                    
                    // Only track if not already tracked
                    if (!sessionStorage.getItem('affiliate_conversion_tracked')) {
                        AffiliateClientConversion.track(0, window.location.href, {
                            event_type: 'generic_success',
                            auto_detected: true
                        });
                        sessionStorage.setItem('affiliate_conversion_tracked', '1');
                    }
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Get conversion statistics
     *
     * @param int $days Number of days to look back
     * @return array Conversion statistics
     */
    public function get_conversion_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_conversions,
                COUNT(DISTINCT affiliate_id) as unique_affiliates,
                SUM(CAST(JSON_EXTRACT(data, '$.amount') AS DECIMAL(10,2))) as total_amount,
                AVG(CAST(JSON_EXTRACT(data, '$.amount') AS DECIMAL(10,2))) as average_amount,
                SUM(CASE WHEN synced = 1 THEN 1 ELSE 0 END) as synced_conversions
            FROM {$table_name}
            WHERE event_type = 'conversion'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
        
        return [
            'total_conversions' => intval($stats->total_conversions ?? 0),
            'unique_affiliates' => intval($stats->unique_affiliates ?? 0),
            'total_amount' => floatval($stats->total_amount ?? 0),
            'average_amount' => floatval($stats->average_amount ?? 0),
            'synced_conversions' => intval($stats->synced_conversions ?? 0),
            'sync_rate' => $stats->total_conversions > 0 ? round(($stats->synced_conversions / $stats->total_conversions) * 100, 2) : 0,
        ];
    }

    /**
     * Get conversion attribution breakdown
     *
     * @param int $days Number of days to look back
     * @return array Attribution breakdown
     */
    public function get_attribution_breakdown($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                JSON_EXTRACT(data, '$.attribution_model') as attribution_model,
                COUNT(*) as conversion_count,
                SUM(CAST(JSON_EXTRACT(data, '$.amount') AS DECIMAL(10,2))) as total_amount
            FROM {$table_name}
            WHERE event_type = 'conversion'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY JSON_EXTRACT(data, '$.attribution_model')
        ", $days));
        
        $breakdown = [];
        foreach ($results as $row) {
            $model = trim($row->attribution_model, '"') ?: 'unknown';
            $breakdown[$model] = [
                'conversions' => intval($row->conversion_count),
                'amount' => floatval($row->total_amount),
            ];
        }
        
        return $breakdown;
    }

    /**
     * Get top converting affiliates
     *
     * @param int $limit Number of affiliates to return
     * @param int $days Number of days to look back
     * @return array Top affiliates
     */
    public function get_top_affiliates($limit = 10, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                affiliate_id,
                COUNT(*) as conversion_count,
                SUM(CAST(JSON_EXTRACT(data, '$.amount') AS DECIMAL(10,2))) as total_amount,
                AVG(CAST(JSON_EXTRACT(data, '$.amount') AS DECIMAL(10,2))) as average_amount
            FROM {$table_name}
            WHERE event_type = 'conversion'
            AND affiliate_id IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY affiliate_id
            ORDER BY total_amount DESC
            LIMIT %d
        ", $days, $limit));
        
        $affiliates = [];
        foreach ($results as $row) {
            $affiliates[] = [
                'affiliate_id' => intval($row->affiliate_id),
                'conversions' => intval($row->conversion_count),
                'total_amount' => floatval($row->total_amount),
                'average_amount' => floatval($row->average_amount),
            ];
        }
        
        return $affiliates;
    }

    /**
     * Get unsynced conversions
     *
     * @param int $limit Number of conversions to return
     * @return array Unsynced conversions
     */
    public function get_unsynced_conversions($limit = 100) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE event_type = 'conversion'
            AND synced = 0
            ORDER BY created_at ASC
            LIMIT %d
        ", $limit));
    }

    /**
     * Retry failed conversions
     *
     * @return array Retry results
     */
    public function retry_failed_conversions() {
        $unsynced = $this->get_unsynced_conversions(50);
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($unsynced as $conversion) {
            $conversion_data = json_decode($conversion->data, true);
            $result = $this->send_conversion_to_remote($conversion_data, $conversion->id);
            
            if ($result['success']) {
                $this->mark_conversion_synced($conversion->id);
                $success_count++;
            } else {
                $failed_count++;
            }
        }
        
        return [
            'processed' => count($unsynced),
            'success' => $success_count,
            'failed' => $failed_count,
        ];
    }

    /**
     * Set attribution model
     *
     * @param string $model Attribution model
     * @return bool Success status
     */
    public function set_attribution_model($model) {
        $valid_models = ['first_click', 'last_click', 'linear', 'time_decay'];
        
        if (!in_array($model, $valid_models)) {
            return false;
        }
        
        $this->attribution_model = $model;
        return update_option('affiliate_client_attribution_model', $model);
    }

    /**
     * Get current attribution model
     *
     * @return string Attribution model
     */
    public function get_attribution_model() {
        return $this->attribution_model;
    }

    /**
     * Clean up old conversion data
     *
     * @param int $days Number of days to retain
     * @return int Number of records deleted
     */
    public function cleanup_old_conversions($days = 365) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$table_name}
            WHERE event_type = 'conversion'
            AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND synced = 1
        ", $days));
        
        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Export conversion data
     *
     * @param array $filters Export filters
     * @return array Export data
     */
    public function export_conversions($filters = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        $where_conditions = ["event_type = 'conversion'"];
        $where_values = [];
        
        // Apply filters
        if (!empty($filters['start_date'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $filters['end_date'];
        }
        
        if (!empty($filters['affiliate_id'])) {
            $where_conditions[] = "affiliate_id = %d";
            $where_values[] = $filters['affiliate_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC",
                $where_values
            );
        } else {
            $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";
        }
        
        $conversions = $wpdb->get_results($query);
        
        $export_data = [];
        foreach ($conversions as $conversion) {
            $data = json_decode($conversion->data, true);
            $export_data[] = [
                'id' => $conversion->id,
                'affiliate_id' => $conversion->affiliate_id,
                'amount' => $data['amount'] ?? 0,
                'currency' => $data['currency'] ?? '',
                'reference' => $data['reference'] ?? '',
                'attribution_model' => $data['attribution_model'] ?? '',
                'synced' => $conversion->synced ? 'Yes' : 'No',
                'created_at' => $conversion->created_at,
            ];
        }
        
        return $export_data;
    }
}
     * Plugin configuration
     *
     * @var array
     */
    private $config;

    /**
     * API client instance
     *
     * @var AFFILIATE_CLIENT_API_Client
     */
    private $api_client;

    /**
     * Attribution model
     *
     * @var string
     */
    private $attribution_model;

    /**
     * Constructor
     *
     * @param array $config Plugin configuration
     * @param AFFILIATE_CLIENT_API_Client $api_client API client instance
     */
    public function __construct($config, $api_client) {
        $this->config = $config;
        $this->api_client = $api_client;
        $this->attribution_model = get_option('affiliate_client_attribution_model', 'last_click');
    }

    /**
     * Initialize conversion tracker
     */
    public function init() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_conversion_endpoints']);
        
        // AJAX handlers
        add_action('wp_ajax_affiliate_client_track_conversion', [$this, 'ajax_track_conversion']);
        add_action('wp_ajax_nopriv_affiliate_client_track_conversion', [$this, 'ajax_track_conversion']);
        
        // JavaScript tracking
        add_action('wp_footer', [$this, 'output_conversion_tracking_script']);
    }

    /**
     * Track a conversion
     *
     * @param float $amount Conversion amount
     * @param string $reference Conversion reference (order ID, etc.)
     * @param array $additional_data Additional conversion data
     * @return array Tracking result
     */
    public function track_conversion($amount = 0, $reference = null, $additional_data = []) {
        // Get affiliate ID based on attribution model
        $affiliate_id = $this->get_attributed_affiliate();
        
        if (!$affiliate_id) {
            return [
                'success' => false,
                'message' => 'No affiliate to attribute conversion to',
            ];
        }

        // Prepare conversion data
        $conversion_data = $this->prepare_conversion_data($amount, $reference, $additional_data, $affiliate_id);
        
        // Validate conversion
        $validation = $this->validate_conversion($conversion_data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        // Check for duplicate conversions
        if ($this->is_duplicate_conversion($conversion_data)) {
            return [
                'success' => false,
                'message' => 'Duplicate conversion detected',
            ];
        }

        // Log conversion locally
        $log_id = $this->log_conversion($conversion_data);
        
        if (!$log_id) {
            return [
                'success' => false,
                'message' => 'Failed to log conversion locally',
            ];
        }

        // Send to remote site
        $api_result = $this->send_conversion_to_remote($conversion_data, $log_id);
        
        if ($api_result['success']) {
            $this->mark_conversion_synced($log_id);
            
            // Fire action for other plugins
            do_action('affiliate_client_conversion_tracked', $conversion_data, $log_id);
            
            return [
                'success' => true,
                'message' => 'Conversion tracked successfully',
                'conversion_id' => $log_id,
                'affiliate_id' => $affiliate_id,
                'amount' => $amount,
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send conversion to remote site: ' . ($api_result['error'] ?? 'Unknown error'),
                'local_id' => $log_id,
            ];
        }
    }

    /**
     * Register REST API endpoints
     */
    public function register_conversion_endpoints() {
        register_rest_route('affiliate-client/v1', '/conversion', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_track_conversion'],
            'permission_callback' => '__return_true',
            'args' => [
                'amount' => [
                    'required' => false,
                    'type' => 'number',
                    'default' => 0,
                ],
                'reference' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'data' => [
                    'required' => false,
                    'type' => 'object',
                ],
            ],
        ]);
    }

    /**
     * REST API conversion tracking
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_track_conversion($request) {
        $amount = floatval($request->get_param('amount'));
        $reference = sanitize_text_field($request->get_param('reference'));
        $data = $request->get_param('data') ?: [];

        $result = $this->track_conversion($amount, $reference, $data);
        
        return rest_ensure_response($result);
    }

    /**
     * AJAX conversion tracking
     */
    public function ajax_track_conversion() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $amount = floatval($_POST['amount'] ?? 0);
        $reference = sanitize_text_field($_POST['reference'] ?? '');
        $data = $_POST['data'] ?? [];

        // Sanitize data
        $data = $this->sanitize_conversion_data($data);

        $result = $this->track_conversion($amount, $reference, $data);
        
        wp_send_json($result);
    }

    /**
     * Get attributed affiliate based on attribution model
     *
     * @return int|null Affiliate ID
     */
    private function get_attributed_affiliate() {
        switch ($this->attribution_model) {
            case 'first_click':
                return $this->get_first_click_affiliate();
            case 'last_click':
                return $this->get_last_click_affiliate();
            case 'linear':
                return $this->get_linear_attribution_affiliate();
            case 'time_decay':
                return $this->get_time_decay_affiliate();
            default:
                return $this->get_last_click_affiliate();
        }
    }

    /**
     * Get first-click affiliate
     *
     * @return int|null Affiliate ID
     */
    private function get_first_click_affiliate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }

        $affiliate_id = $wpdb->get_var($wpdb->prepare("
            SELECT affiliate_id
            FROM {$table_name}
            WHERE visit_id = %s
            AND affiliate_id IS NOT NULL
            AND event_type = 'referral'
            ORDER BY created_at ASC
            LIMIT 1
        ", $visit_id));

        return $affiliate_id ? intval($affiliate_id) : null;
    }

    /**
     * Get last-click affiliate
     *
     * @return int|null Affiliate ID
     */
    private function get_last_click_affiliate() {
        // Check cookie first
        if (isset($_COOKIE[$this->config['cookie_name']])) {
            return intval($_COOKIE[$this->config['cookie_name']]);
        }

        // Fallback to database
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }

        $affiliate_id = $wpdb->get_var($wpdb->prepare("
            SELECT affiliate_id
            FROM {$table_name}
            WHERE visit_id = %s
            AND affiliate_id IS NOT NULL
            ORDER BY created_at DESC
            LIMIT 1
        ", $visit_id));

        return $affiliate_id ? intval($affiliate_id) : null;
    }

/**
     * Get linear attribution affiliate - COMPLETE IMPLEMENTATION
     * Credits are split equally among all touchpoints
     *
     * @return array Attribution data with all affiliates and their shares
     */
    private function get_linear_attribution_affiliate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_tracking_events';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }
        
        // Get all affiliate touchpoints in the attribution window
        $touchpoints = $wpdb->get_results($wpdb->prepare(
            "SELECT affiliate_id, created_at, event_type, event_data
             FROM {$table_name}
             WHERE visit_id = %s
             AND event_type IN ('affiliate_visit', 'affiliate_click', 'affiliate_interaction')
             AND affiliate_id IS NOT NULL
             AND affiliate_id > 0
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY created_at ASC",
            $visit_id,
            $this->attribution_window
        ));
        
        if (empty($touchpoints)) {
            return null;
        }
        
        // Count unique affiliates
        $unique_affiliates = [];
        foreach ($touchpoints as $touchpoint) {
            $affiliate_id = intval($touchpoint->affiliate_id);
            if (!isset($unique_affiliates[$affiliate_id])) {
                $unique_affiliates[$affiliate_id] = [
                    'affiliate_id' => $affiliate_id,
                    'touchpoint_count' => 0,
                    'first_touch' => $touchpoint->created_at,
                    'last_touch' => $touchpoint->created_at,
                    'attribution_weight' => 0
                ];
            }
            $unique_affiliates[$affiliate_id]['touchpoint_count']++;
            $unique_affiliates[$affiliate_id]['last_touch'] = $touchpoint->created_at;
        }
        
        // Calculate equal weights
        $affiliate_count = count($unique_affiliates);
        $weight_per_affiliate = 1.0 / $affiliate_count;
        
        foreach ($unique_affiliates as $affiliate_id => $data) {
            $unique_affiliates[$affiliate_id]['attribution_weight'] = $weight_per_affiliate;
            $unique_affiliates[$affiliate_id]['attribution_percentage'] = ($weight_per_affiliate * 100);
        }
        
        // Return primary affiliate (last touch) with full attribution data
        $last_touchpoint = end($touchpoints);
        $primary_affiliate_id = intval($last_touchpoint->affiliate_id);
        
        // Store attribution data for reporting
        $this->store_attribution_data($visit_id, 'linear', $unique_affiliates);
        
        return $primary_affiliate_id;
    }


 /**
     * Get time-decay attribution affiliate - COMPLETE IMPLEMENTATION
     * More recent touchpoints get more credit with exponential decay
     *
     * @return array Attribution data with weighted affiliates
     */
    private function get_time_decay_affiliate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_tracking_events';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }
        
        // Get all affiliate touchpoints
        $touchpoints = $wpdb->get_results($wpdb->prepare(
            "SELECT affiliate_id, created_at, event_type, event_data
             FROM {$table_name}
             WHERE visit_id = %s
             AND event_type IN ('affiliate_visit', 'affiliate_click', 'affiliate_interaction')
             AND affiliate_id IS NOT NULL
             AND affiliate_id > 0
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY created_at ASC",
            $visit_id,
            $this->attribution_window
        ));
        
        if (empty($touchpoints)) {
            return null;
        }
        
        // Time decay calculation with 7-day half-life
        $decay_rate = 0.693; // ln(2) for half-life calculation
        $half_life_days = 7;
        $current_time = current_time('timestamp');
        
        $weighted_affiliates = [];
        $total_weight = 0;
        
        foreach ($touchpoints as $touchpoint) {
            $affiliate_id = intval($touchpoint->affiliate_id);
            $touchpoint_time = strtotime($touchpoint->created_at);
            $days_ago = ($current_time - $touchpoint_time) / 86400; // Convert seconds to days
            
            // Exponential decay formula: weight = e^(-decay_rate * days / half_life)
            $weight = exp(-$decay_rate * $days_ago / $half_life_days);
            
            if (!isset($weighted_affiliates[$affiliate_id])) {
                $weighted_affiliates[$affiliate_id] = [
                    'affiliate_id' => $affiliate_id,
                    'total_weight' => 0,
                    'touchpoint_count' => 0,
                    'first_touch' => $touchpoint->created_at,
                    'last_touch' => $touchpoint->created_at,
                    'touchpoints' => []
                ];
            }
            
            $weighted_affiliates[$affiliate_id]['total_weight'] += $weight;
            $weighted_affiliates[$affiliate_id]['touchpoint_count']++;
            $weighted_affiliates[$affiliate_id]['last_touch'] = $touchpoint->created_at;
            $weighted_affiliates[$affiliate_id]['touchpoints'][] = [
                'timestamp' => $touchpoint->created_at,
                'days_ago' => $days_ago,
                'weight' => $weight
            ];
            
            $total_weight += $weight;
        }
        
        // Normalize weights to percentages
        foreach ($weighted_affiliates as $affiliate_id => $data) {
            $weighted_affiliates[$affiliate_id]['attribution_weight'] = $data['total_weight'] / $total_weight;
            $weighted_affiliates[$affiliate_id]['attribution_percentage'] = ($data['total_weight'] / $total_weight) * 100;
        }
        
        // Store attribution data
        $this->store_attribution_data($visit_id, 'time_decay', $weighted_affiliates);
        
        // Return affiliate with highest weight
        uasort($weighted_affiliates, function($a, $b) {
            return $b['attribution_weight'] <=> $a['attribution_weight'];
        });
        
        $primary_affiliate = reset($weighted_affiliates);
        return $primary_affiliate['affiliate_id'];
    }

   */
    private function get_position_based_attribution_affiliate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_tracking_events';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }
        
        $touchpoints = $wpdb->get_results($wpdb->prepare(
            "SELECT affiliate_id, created_at, event_type
             FROM {$table_name}
             WHERE visit_id = %s
             AND event_type IN ('affiliate_visit', 'affiliate_click', 'affiliate_interaction')
             AND affiliate_id IS NOT NULL
             AND affiliate_id > 0
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY created_at ASC",
            $visit_id,
            $this->attribution_window
        ));
        
        if (empty($touchpoints)) {
            return null;
        }
        
        $touchpoint_count = count($touchpoints);
        $weighted_affiliates = [];
        
        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = intval($touchpoint->affiliate_id);
            
            // Calculate position-based weight
            if ($touchpoint_count === 1) {
                // Single touchpoint gets 100%
                $weight = 1.0;
            } elseif ($touchpoint_count === 2) {
                // Two touchpoints: 50% each
                $weight = 0.5;
            } else {
                // Multiple touchpoints: 40% first, 40% last, 20% divided among middle
                if ($index === 0) {
                    $weight = 0.4; // First touch
                } elseif ($index === $touchpoint_count - 1) {
                    $weight = 0.4; // Last touch
                } else {
                    // Middle touches share 20%
                    $middle_count = $touchpoint_count - 2;
                    $weight = 0.2 / $middle_count;
                }
            }
            
            if (!isset($weighted_affiliates[$affiliate_id])) {
                $weighted_affiliates[$affiliate_id] = [
                    'affiliate_id' => $affiliate_id,
                    'total_weight' => 0,
                    'positions' => []
                ];
            }
            
            $weighted_affiliates[$affiliate_id]['total_weight'] += $weight;
            $weighted_affiliates[$affiliate_id]['positions'][] = [
                'index' => $index,
                'weight' => $weight,
                'position' => ($index === 0) ? 'first' : (($index === $touchpoint_count - 1) ? 'last' : 'middle')
            ];
        }
        
        // Calculate percentages
        foreach ($weighted_affiliates as $affiliate_id => $data) {
            $weighted_affiliates[$affiliate_id]['attribution_percentage'] = $data['total_weight'] * 100;
        }
        
        // Store attribution data
        $this->store_attribution_data($visit_id, 'position_based', $weighted_affiliates);
        
        // Return affiliate with highest weight
        uasort($weighted_affiliates, function($a, $b) {
            return $b['total_weight'] <=> $a['total_weight'];
        });
        
        $primary_affiliate = reset($weighted_affiliates);
        return $primary_affiliate['affiliate_id'];
    }

    /**
     * Get data-driven attribution affiliate - COMPLETE IMPLEMENTATION
     * Uses machine learning-inspired approach based on conversion probability
     *
     * @return int|null Primary affiliate ID
     */
    private function get_data_driven_attribution_affiliate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_tracking_events';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }
        
        $touchpoints = $wpdb->get_results($wpdb->prepare(
            "SELECT affiliate_id, created_at, event_type, event_data
             FROM {$table_name}
             WHERE visit_id = %s
             AND event_type IN ('affiliate_visit', 'affiliate_click', 'affiliate_interaction')
             AND affiliate_id IS NOT NULL
             AND affiliate_id > 0
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY created_at ASC",
            $visit_id,
            $this->attribution_window
        ));
        
        if (empty($touchpoints)) {
            return null;
        }
        
        $weighted_affiliates = [];
        $total_weight = 0;
        
        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = intval($touchpoint->affiliate_id);
            $event_data = json_decode($touchpoint->event_data, true) ?: [];
            
            // Calculate quality score based on multiple factors
            $quality_factors = [
                'engagement_time' => $event_data['time_on_page'] ?? 0,
                'scroll_depth' => $event_data['scroll_depth'] ?? 0,
                'interactions' => $event_data['click_count'] ?? 0,
                'page_views' => $event_data['page_views'] ?? 1,
                'return_visit' => $event_data['return_visit'] ?? false
            ];
            
            // Weighted quality score (0-1)
            $quality_score = $this->calculate_quality_score($quality_factors);
            
            // Position factor (recent touches get slight boost)
            $recency_factor = 1 + (0.1 * ($index / max(1, count($touchpoints) - 1)));
            
            // Conversion probability estimation
            $conversion_probability = $this->estimate_conversion_probability($affiliate_id, $quality_factors);
            
            // Combined weight
            $weight = $quality_score * $recency_factor * $conversion_probability;
            
            if (!isset($weighted_affiliates[$affiliate_id])) {
                $weighted_affiliates[$affiliate_id] = [
                    'affiliate_id' => $affiliate_id,
                    'total_weight' => 0,
                    'quality_scores' => [],
                    'conversion_probabilities' => []
                ];
            }
            
            $weighted_affiliates[$affiliate_id]['total_weight'] += $weight;
            $weighted_affiliates[$affiliate_id]['quality_scores'][] = $quality_score;
            $weighted_affiliates[$affiliate_id]['conversion_probabilities'][] = $conversion_probability;
            
            $total_weight += $weight;
        }
        
        // Normalize weights
        foreach ($weighted_affiliates as $affiliate_id => $data) {
            $weighted_affiliates[$affiliate_id]['attribution_weight'] = $data['total_weight'] / $total_weight;
            $weighted_affiliates[$affiliate_id]['attribution_percentage'] = ($data['total_weight'] / $total_weight) * 100;
        }
        
        // Store attribution data
        $this->store_attribution_data($visit_id, 'data_driven', $weighted_affiliates);
        
        // Return affiliate with highest weight
        uasort($weighted_affiliates, function($a, $b) {
            return $b['attribution_weight'] <=> $a['attribution_weight'];
        });
        
        $primary_affiliate = reset($weighted_affiliates);
        return $primary_affiliate['affiliate_id'];
    }

    /**
     * Calculate quality score from engagement factors
     *
     * @param array $factors Engagement factors
     * @return float Quality score (0-1)
     */
    private function calculate_quality_score($factors) {
        $scores = [];
        
        // Time on page score (0-1, plateaus at 5 minutes)
        $time_score = min(1.0, ($factors['engagement_time'] ?? 0) / 300);
        $scores[] = $time_score * 0.3; // 30% weight
        
        // Scroll depth score (0-1)
        $scroll_score = min(1.0, ($factors['scroll_depth'] ?? 0) / 100);
        $scores[] = $scroll_score * 0.2; // 20% weight
        
        // Interaction score (0-1, plateaus at 10 interactions)
        $interaction_score = min(1.0, ($factors['interactions'] ?? 0) / 10);
        $scores[] = $interaction_score * 0.25; // 25% weight
        
        // Page view score (0-1, plateaus at 5 pages)
        $pageview_score = min(1.0, ($factors['page_views'] ?? 1) / 5);
        $scores[] = $pageview_score * 0.15; // 15% weight
        
        // Return visitor bonus
        $return_bonus = ($factors['return_visit'] ?? false) ? 0.1 : 0;
        $scores[] = $return_bonus; // 10% weight
        
        return array_sum($scores);
    }

    /**
     * Estimate conversion probability for affiliate
     *
     * @param int $affiliate_id Affiliate ID
     * @param array $factors Quality factors
     * @return float Probability (0-1)
     */
    private function estimate_conversion_probability($affiliate_id, $factors) {
        global $wpdb;
        
        // Get historical conversion rate for this affiliate
        $stats_table = $wpdb->prefix . 'aci_attribution_stats';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                total_conversions,
                total_visits,
                avg_quality_score
             FROM {$stats_table}
             WHERE affiliate_id = %d",
            $affiliate_id
        ));
        
        if ($stats && $stats->total_visits > 0) {
            $historical_rate = $stats->total_conversions / $stats->total_visits;
            $quality_score = $this->calculate_quality_score($factors);
            
            // Adjust probability based on current quality vs historical average
            if ($stats->avg_quality_score > 0) {
                $quality_multiplier = $quality_score / $stats->avg_quality_score;
                return min(1.0, $historical_rate * $quality_multiplier);
            }
            
            return $historical_rate;
        }
        
        // Default probability for new affiliates
        return 0.5;
    }

    /**
     * Store attribution data for reporting
     *
     * @param string $visit_id Visit ID
     * @param string $model Attribution model used
     * @param array $attribution_data Attribution breakdown
     */
    private function store_attribution_data($visit_id, $model, $attribution_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_attribution_results';
        
        $wpdb->insert(
            $table_name,
            [
                'visit_id' => $visit_id,
                'attribution_model' => $model,
                'attribution_data' => json_encode($attribution_data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Update attribution statistics for affiliate
     *
     * @param int $affiliate_id Affiliate ID
     * @param float $quality_score Quality score
     * @param bool $converted Whether this visit converted
     */
    private function update_attribution_statistics($affiliate_id, $quality_score, $converted = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_attribution_stats';
        
        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE affiliate_id = %d",
            $affiliate_id
        ));
        
        if ($exists) {
            // Update existing record
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name} SET
                    total_visits = total_visits + 1,
                    total_conversions = total_conversions + %d,
                    avg_quality_score = ((avg_quality_score * total_visits) + %f) / (total_visits + 1),
                    updated_at = NOW()
                 WHERE affiliate_id = %d",
                $converted ? 1 : 0,
                $quality_score,
                $affiliate_id
            ));
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                [
                    'affiliate_id' => $affiliate_id,
                    'total_visits' => 1,
                    'total_conversions' => $converted ? 1 : 0,
                    'avg_quality_score' => $quality_score,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%f', '%s', '%s']
            );
        }
    }

    /**
     * Get current visit ID
     *
     * @return string|null Visit ID
     */
    private function get_current_visit_id() {
        if (isset($_COOKIE['aci_visit_id'])) {
            return sanitize_text_field($_COOKIE['aci_visit_id']);
        }
        return null;
    }

    /**
     * Prepare conversion data
     *
     * @param float $amount Conversion amount
     * @param string $reference Conversion reference
     * @param array $additional_data Additional data
     * @param int $affiliate_id Affiliate ID
     * @return array Prepared conversion data
     */
    private function prepare_conversion_data($amount, $reference, $additional_data, $affiliate_id) {
        $base_data = [
            'event_type' => 'conversion',
            'amount' => floatval($amount),
            'reference' => $reference,
            'affiliate_id' => $affiliate_id,
            'visit_id' => $this->get_current_visit_id(),
            'currency' => $this->get_site_currency(),
            'timestamp' => current_time('c'),
            'url' => $this->get_current_url(),
            'user_ip' => $this->get_user_ip(),
            'user_agent' => $this->get_user_agent(),
            'attribution_model' => $this->attribution_model,
        ];

        // Merge with additional data
        return array_merge($base_data, $additional_data);
    }

    /**
     * Validate conversion data
     *
     * @param array $conversion_data Conversion data
     * @return array Validation result
     */
    private function validate_conversion($conversion_data) {
        $errors = [];

        // Check required fields
        if (empty($conversion_data['affiliate_id'])) {
            $errors[] = 'Affiliate ID is required';
        }

        if (!is_numeric($conversion_data['amount']) || $conversion_data['amount'] < 0) {
            $errors[] = 'Valid conversion amount is required';
        }

        // Check affiliate exists (cache for performance)
        $affiliate_valid = $this->validate_affiliate_id($conversion_data['affiliate_id']);
        if (!$affiliate_valid) {
            $errors[] = 'Invalid affiliate ID';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? 'Valid' : implode(', ', $errors),
        ];
    }

    /**
     * Check for duplicate conversions
     *
     * @param array $conversion_data Conversion data
     * @return bool True if duplicate
     */
    private function is_duplicate_conversion($conversion_data) {
        if (empty($conversion_data['reference'])) {
            return false; // Can't check without reference
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'affiliate_client_logs';

        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id
            FROM {$table_name}
            WHERE event_type = 'conversion'
            AND JSON_EXTRACT(data, '$.reference') = %s
            AND affiliate_id = %d
            LIMIT 1
        ", $conversion_data['reference'], $conversion_data['affiliate_id']));

        return !empty($existing);
    }

    /**
     * Log conversion to database
     *
     * @param array $conversion_data Conversion data
     * @return int|false Log ID or false on failure
     */
    private function log_conversion($conversion_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'event_type' => 'conversion',
                'affiliate_id' => $conversion_data['affiliate_id'],
                'visit_id' => $conversion_data['visit_id'],
                'data' => json_encode($conversion_data),
                'synced' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            $this->log_error('Failed to log conversion: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Send conversion to remote site
     *
     * @param array $conversion_data Conversion data
     * @param int $log_id Local log ID
     * @return array API result
     */
    private function send_conversion_to_remote($conversion_data, $log_id) {
        if (!$this->api_client->is_available()) {
            return [
                'success' => false,
                'error' => 'API client not available',
            ];
        }

        $api_data = [
            'conversion_data' => $conversion_data,
            'local_log_id' => $log_id,
            'site_url' => home_url(),
            'client_version' => $this->config['version'],
        ];

        return $this->api_client->send_conversion_data($api_data);
    }

    /**
     * Mark conversion as synced
     *
     * @param int $log_id Log ID
     * @return bool Success status
     */
    private function mark_conversion_synced($log_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        return $wpdb->update(
            $table_name,
            ['synced' => 1],
            ['id' => $log_id],
            ['%d'],
            ['%d']
        ) !== false;
    }

    /**
     * Validate affiliate ID
     *
     * @param int $affiliate_id Affiliate ID
     * @return bool True if valid
     */
    private function validate_affiliate_id($affiliate_id) {
        $cache_key = 'affiliate_client_validate_id_' . $affiliate_id;
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        $result = $this->api_client->validate_affiliate($affiliate_id);
        $is_valid = $result['valid'] ?? false;

        // Cache for 5 minutes
        set_transient($cache_key, $is_valid, 5 * MINUTE_IN_SECONDS);

        return $is_valid;
    }

    /**
     * Sanitize conversion data
     *
     * @param array $data Raw conversion data
     * @return array Sanitized data
     */
    private function sanitize_conversion_data($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize_conversion_data($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
/**
     * Get current visit ID
     *
     * @return string|null Visit ID
     */
    private function get_current_visit_id() {
        $session_cookie = 'affiliate_client_visit_id';
        return isset($_COOKIE[$session_cookie]) ? sanitize_text_field($_COOKIE[$session_cookie]) : null;
    }

    /**
     * Get site currency
     *
     * @return string Currency code
     */
    private function get_site_currency() {
        // Try WooCommerce first
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        
        // Try EDD
        if (function_exists('edd_get_currency')) {
            return edd_get_currency();
        }
        
        // Default to EUR
        return apply_filters('affiliate_client_default_currency', 'EUR');
    }

    /**
     * Get current URL
     *
     * @return string Current URL
     */
    private function get_current_url() {
        if (isset($_SERVER['REQUEST_URI'])) {
            return home_url(sanitize_text_field($_SERVER['REQUEST_URI']));
        }
        return home_url();
    }

    /**
     * Get user IP address
     *
     * @return string IP address
     */
    private function get_user_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Get user agent
     *
     * @return string User agent
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        if ($this->config['debug_mode']) {
            error_log('[Affiliate Client Integration - Conversion Tracker] ' . $message);
        }
    }

    /**
     * Output conversion tracking script
     */
    public function output_conversion_tracking_script() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        ?>
        <script type="text/javascript">
        (function() {
            // Conversion tracking helper
            window.ACI_Conversion = {
                track: function(amount, reference, data) {
                    if (typeof aciConfig === 'undefined') {
                        console.warn('ACI not initialized');
                        return;
                    }

                    var conversionData = {
                        action: 'aci_track_conversion',
                        nonce: aciConfig.nonce,
                        amount: amount || 0,
                        reference: reference || '',
                        data: data || {}
                    };

                    jQuery.post(aciConfig.ajaxUrl, conversionData, function(response) {
                        if (response.success) {
                            console.log('Conversion tracked:', response);
                            jQuery(document).trigger('aci:conversion:tracked', [response]);
                        } else {
                            console.warn('Conversion tracking failed:', response.message);
                        }
                    }).fail(function() {
                        console.error('Conversion tracking request failed');
                    });
                }
            };
        })();
        </script>
        <?php
    }

    /**
     * Get attributed affiliate based on attribution model
     *
     * @return int|null Affiliate ID
     */
    private function get_attributed_affiliate() {
        switch ($this->attribution_model) {
            case 'first_click':
                return $this->get_first_click_affiliate();
            case 'last_click':
                return $this->get_last_click_affiliate();
            case 'linear':
                return $this->get_linear_attribution_affiliate();
            case 'time_decay':
                return $this->get_time_decay_affiliate();
            default:
                return $this->get_last_click_affiliate();
        }
    }

    /**
     * Get first-click affiliate
     *
     * @return int|null Affiliate ID
     */
    private function get_first_click_affiliate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_tracking_events';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }
        
        $affiliate_id = $wpdb->get_var($wpdb->prepare(
            "SELECT affiliate_id FROM {$table_name} 
             WHERE visit_id = %s 
             AND event_type = 'affiliate_visit' 
             ORDER BY created_at ASC 
             LIMIT 1",
            $visit_id
        ));
        
        return $affiliate_id ? intval($affiliate_id) : null;
    }

    /**
     * Get last-click affiliate
     *
     * @return int|null Affiliate ID
     */
    private function get_last_click_affiliate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_tracking_events';
        $visit_id = $this->get_current_visit_id();
        
        if (!$visit_id) {
            return null;
        }
        
        $affiliate_id = $wpdb->get_var($wpdb->prepare(
            "SELECT affiliate_id FROM {$table_name} 
             WHERE visit_id = %s 
             AND event_type = 'affiliate_visit' 
             ORDER BY created_at DESC 
             LIMIT 1",
            $visit_id
        ));
        
        return $affiliate_id ? intval($affiliate_id) : null;
    }

    /**
     * Get linear attribution affiliate
     * Credits are split equally among all touchpoints
     *
     * @return int|null Affiliate ID (most recent for simplicity)
     */
    private function get_linear_attribution_affiliate() {
        return $this->get_last_click_affiliate();
    }

    /**
     * Get time-decay attribution affiliate
     * More recent touchpoints get more credit
     *
     * @return int|null Affiliate ID
     */
    private function get_time_decay_affiliate() {
        return $this->get_last_click_affiliate();
    }

    /**
     * Prepare conversion data
     *
     * @param float $amount Conversion amount
     * @param string|null $reference Conversion reference
     * @param array $additional_data Additional data
     * @param int $affiliate_id Affiliate ID
     * @return array Conversion data
     */
    private function prepare_conversion_data($amount, $reference, $additional_data, $affiliate_id) {
        return [
            'affiliate_id' => $affiliate_id,
            'visit_id' => $this->get_current_visit_id(),
            'amount' => floatval($amount),
            'currency' => $this->get_site_currency(),
            'reference' => $reference ? sanitize_text_field($reference) : '',
            'attribution_model' => $this->attribution_model,
            'url' => $this->get_current_url(),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => $this->get_user_agent(),
            'created_at' => current_time('mysql'),
            'additional_data' => $additional_data,
        ];
    }

    /**
     * Validate conversion data
     *
     * @param array $conversion_data Conversion data
     * @return array Validation result
     */
    private function validate_conversion($conversion_data) {
        $errors = [];

        // Validate affiliate ID
        if (empty($conversion_data['affiliate_id'])) {
            $errors[] = 'Affiliate ID is required';
        } elseif (!$this->validate_affiliate_id($conversion_data['affiliate_id'])) {
            $errors[] = 'Invalid affiliate ID';
        }

        // Validate amount
        if ($conversion_data['amount'] < 0) {
            $errors[] = 'Amount cannot be negative';
        }

        // Validate currency
        if (empty($conversion_data['currency'])) {
            $errors[] = 'Currency is required';
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? 'Valid' : implode(', ', $errors),
        ];
    }

    /**
     * Check for duplicate conversions
     *
     * @param array $conversion_data Conversion data
     * @return bool True if duplicate
     */
    private function is_duplicate_conversion($conversion_data) {
        if (empty($conversion_data['reference'])) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'aci_conversions';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} 
             WHERE reference = %s 
             AND affiliate_id = %d 
             LIMIT 1",
            $conversion_data['reference'],
            $conversion_data['affiliate_id']
        ));

        return !empty($existing);
    }

    /**
     * Log conversion to database
     *
     * @param array $conversion_data Conversion data
     * @return int|false Log ID or false on failure
     */
    private function log_conversion($conversion_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'affiliate_id' => $conversion_data['affiliate_id'],
                'visit_id' => $conversion_data['visit_id'],
                'amount' => $conversion_data['amount'],
                'currency' => $conversion_data['currency'],
                'reference' => $conversion_data['reference'],
                'attribution_model' => $conversion_data['attribution_model'],
                'url' => $conversion_data['url'],
                'ip_address' => $conversion_data['ip_address'],
                'user_agent' => $conversion_data['user_agent'],
                'additional_data' => json_encode($conversion_data['additional_data']),
                'synced' => 0,
                'created_at' => $conversion_data['created_at'],
            ],
            [
                '%d', '%s', '%f', '%s', '%s', '%s', 
                '%s', '%s', '%s', '%s', '%d', '%s'
            ]
        );

        if ($result === false) {
            $this->log_error('Failed to log conversion: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Send conversion to remote site
     *
     * @param array $conversion_data Conversion data
     * @param int $log_id Local log ID
     * @return array API result
     */
    private function send_conversion_to_remote($conversion_data, $log_id) {
        if (!$this->api_client->is_available()) {
            return [
                'success' => false,
                'error' => 'API client not available',
            ];
        }

        $api_data = [
            'conversion_data' => $conversion_data,
            'local_log_id' => $log_id,
            'site_url' => home_url(),
            'client_version' => AFFILIATE_CLIENT_FULL_VERSION,
        ];

        return $this->api_client->send_conversion_data($api_data);
    }

    /**
     * Mark conversion as synced
     *
     * @param int $log_id Log ID
     * @return bool Success status
     */
    private function mark_conversion_synced($log_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        
        return $wpdb->update(
            $table_name,
            ['synced' => 1],
            ['id' => $log_id],
            ['%d'],
            ['%d']
        ) !== false;
    }

    /**
     * Validate affiliate ID
     *
     * @param int $affiliate_id Affiliate ID
     * @return bool True if valid
     */
    private function validate_affiliate_id($affiliate_id) {
        $cache_key = 'aci_validate_affiliate_' . $affiliate_id;
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        $result = $this->api_client->validate_affiliate($affiliate_id);
        $is_valid = $result['valid'] ?? false;

        set_transient($cache_key, $is_valid, 5 * MINUTE_IN_SECONDS);

        return $is_valid;
    }

    /**
     * Track conversion
     *
     * @param float $amount Conversion amount
     * @param string|null $reference Conversion reference
     * @param array $additional_data Additional conversion data
     * @return array Tracking result
     */
    public function track_conversion($amount = 0, $reference = null, $additional_data = []) {
        $affiliate_id = $this->get_attributed_affiliate();
        
        if (!$affiliate_id) {
            return [
                'success' => false,
                'message' => 'No affiliate to attribute conversion to',
            ];
        }

        $conversion_data = $this->prepare_conversion_data($amount, $reference, $additional_data, $affiliate_id);
        
        $validation = $this->validate_conversion($conversion_data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        if ($this->is_duplicate_conversion($conversion_data)) {
            return [
                'success' => false,
                'message' => 'Duplicate conversion detected',
            ];
        }

        $log_id = $this->log_conversion($conversion_data);
        
        if (!$log_id) {
            return [
                'success' => false,
                'message' => 'Failed to log conversion locally',
            ];
        }

        $api_result = $this->send_conversion_to_remote($conversion_data, $log_id);
        
        if ($api_result['success']) {
            $this->mark_conversion_synced($log_id);
            
            do_action('aci_conversion_tracked', $conversion_data, $log_id);
            
            return [
                'success' => true,
                'message' => 'Conversion tracked successfully',
                'conversion_id' => $log_id,
                'affiliate_id' => $affiliate_id,
                'amount' => $amount,
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send conversion to remote site: ' . ($api_result['error'] ?? 'Unknown error'),
                'local_id' => $log_id,
            ];
        }
    }

    /**
     * Register REST API endpoints
     */
    public function register_conversion_endpoints() {
        register_rest_route('aci/v1', '/conversion', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_track_conversion'],
            'permission_callback' => '__return_true',
            'args' => [
                'amount' => [
                    'required' => false,
                    'type' => 'number',
                    'default' => 0,
                ],
                'reference' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'data' => [
                    'required' => false,
                    'type' => 'object',
                ],
            ],
        ]);
    }

    /**
     * REST API conversion tracking
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_track_conversion($request) {
        $amount = floatval($request->get_param('amount'));
        $reference = sanitize_text_field($request->get_param('reference'));
        $data = $request->get_param('data') ?: [];

        $result = $this->track_conversion($amount, $reference, $data);
        
        return rest_ensure_response($result);
    }

    /**
     * AJAX conversion tracking
     */
    public function ajax_track_conversion() {
        check_ajax_referer('aci_nonce', 'nonce');

        $amount = floatval($_POST['amount'] ?? 0);
        $reference = sanitize_text_field($_POST['reference'] ?? '');
        $data = $_POST['data'] ?? [];

        $data = $this->sanitize_conversion_data($data);

        $result = $this->track_conversion($amount, $reference, $data);
        
        wp_send_json($result);
    }

    /**
     * Get conversion statistics
     *
     * @param array $filters Statistics filters
     * @return array Statistics data
     */
    public function get_conversion_statistics($filters = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($filters['start_date'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['end_date'];
        }
        
        if (!empty($filters['affiliate_id'])) {
            $where_conditions[] = 'affiliate_id = %d';
            $where_values[] = $filters['affiliate_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_conversions,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount,
                    currency
                FROM {$table_name}
                WHERE {$where_clause}
                GROUP BY currency",
                $where_values
            );
        } else {
            $query = "SELECT 
                COUNT(*) as total_conversions,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount,
                currency
            FROM {$table_name}
            WHERE {$where_clause}
            GROUP BY currency";
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Retry failed conversions
     *
     * @param int $limit Maximum number to retry
     * @return int Number of conversions retried
     */
    public function retry_failed_conversions($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        
        $failed_conversions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE synced = 0 
             ORDER BY created_at ASC 
             LIMIT %d",
            $limit
        ));
        
        $retry_count = 0;
        
        foreach ($failed_conversions as $conversion) {
            $conversion_data = [
                'affiliate_id' => $conversion->affiliate_id,
                'visit_id' => $conversion->visit_id,
                'amount' => $conversion->amount,
                'currency' => $conversion->currency,
                'reference' => $conversion->reference,
                'attribution_model' => $conversion->attribution_model,
                'url' => $conversion->url,
                'ip_address' => $conversion->ip_address,
                'user_agent' => $conversion->user_agent,
                'additional_data' => json_decode($conversion->additional_data, true),
                'created_at' => $conversion->created_at,
            ];
            
            $result = $this->send_conversion_to_remote($conversion_data, $conversion->id);
            
            if ($result['success']) {
                $this->mark_conversion_synced($conversion->id);
                $retry_count++;
            }
        }
        
        return $retry_count;
    }

    /**
     * Cleanup old conversions
     *
     * @param int $days Days to keep
     * @return int Number of conversions deleted
     */
    public function cleanup_old_conversions($days = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name}
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
             AND synced = 1",
            $days
        ));
        
        return $deleted !== false ? $deleted : 0;
    }

     /**
     * Use the signer when sending events
     */
    $master_base = trailingslashit( get_option( 'affcd_master_base_url' ) ); 
$convert_url = $master_base . 'convert';

$payload = [
    'event_id'     => wp_generate_uuid4(),
    'event_type'   => 'purchase',
    'occurred_at'  => gmdate('c'),
    'affiliate_ref'=> [ 'affiliate_code' => $affiliate_code ],
    'order'        => [ 'order_id' => $order_id, 'total' => $total, 'currency' => $currency ],
    'idempotency_key' => AFFCD_Signer::get_site_id() . '|' . $order_id,
];

$res = AFFCD_Signer::post_json( $convert_url, $payload );

if ( $res['code'] !== 200 ) {
    error_log( 'AFFCD convert failed: ' . print_r( $res, true ) );
}

    /**
     * Export conversion data
     *
     * @param array $filters Export filters
     * @return array Export data
     */
    public function export_conversions($filters = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aci_conversions';
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($filters['start_date'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['end_date'];
        }
        
        if (!empty($filters['affiliate_id'])) {
            $where_conditions[] = 'affiliate_id = %d';
            $where_values[] = $filters['affiliate_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC",
                $where_values
            );
        } else {
            $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";
        }
        
        $conversions = $wpdb->get_results($query, ARRAY_A);
        
        $export_data = [];
        foreach ($conversions as $conversion) {
            $additional_data = json_decode($conversion['additional_data'], true);
            $export_data[] = [
                'id' => $conversion['id'],
                'affiliate_id' => $conversion['affiliate_id'],
                'visit_id' => $conversion['visit_id'],
                'amount' => $conversion['amount'],
                'currency' => $conversion['currency'],
                'reference' => $conversion['reference'],
                'attribution_model' => $conversion['attribution_model'],
                'url' => $conversion['url'],
                'synced' => $conversion['synced'] ? 'Yes' : 'No',
                'created_at' => $conversion['created_at'],
                'additional_data' => $additional_data,
            ];
        }
        
        return $export_data;
    }
}