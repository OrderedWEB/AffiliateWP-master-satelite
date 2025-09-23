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

class AFFILIATE_CLIENT_Conversion_Tracker {

    /**
     * Get current visit ID
     *
     * @return string|null Visit ID
     */
    private function get_current_visit_id() {
        $session_cookie = 'affiliate_client_visit_id';
        return isset($_COOKIE[$session_cookie]) ? Sanitise_text_field($_COOKIE[$session_cookie]) : null;
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
                $ip = Sanitise_text_field($_SERVER[$header]);
                
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
        return isset($_SERVER['HTTP_USER_AGENT']) ? Sanitise_text_field($_SERVER['HTTP_USER_AGENT']) : '';
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
     * Initialse conversion tracker
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
        $reference = Sanitise_text_field($request->get_param('reference'));
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
        $reference = Sanitise_text_field($_POST['reference'] ?? '');
        $data = $_POST['data'] ?? [];

        // Sanitise data
        $data = $this->Sanitise_conversion_data($data);

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
     * Get linear attribution affiliate (placeholder for multi-touch)
     *
     * @return int|null Affiliate ID
     */
    private function get_linear_attribution_affiliate() {
        // For now, fallback to last-click
        // TODO: Implement multi-touch attribution
        return $this->get_last_click_affiliate();
    }

    /**
     * Get time-decay attribution affiliate (placeholder)
     *
     * @return int|null Affiliate ID
     */
    private function get_time_decay_affiliate() {
        // For now, fallback to last-click
        // TODO: Implement time-decay attribution
        return $this->get_last_click_affiliate();
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
     * Sanitise conversion data
     *
     * @param array $data Raw conversion data
     * @return array Sanitised data
     */
    private function Sanitise_conversion_data($data) {
        $Sanitised = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $Sanitised[$key] = Sanitise_text_field($value);
            } elseif (is_numeric($value)) {
                $Sanitised[$key] = is_float($value) ? floatval($value) : intval($value);
            } elseif (is_array($value)) {
                $Sanitised[$key] = $this->Sanitise_conversion_data($value);
            } else {
                $Sanitised[$key] = Sanitise_text_field($value);
            }
        }
        
        return $Sanitised;
    }

    /**