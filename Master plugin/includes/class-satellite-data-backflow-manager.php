<?php
/**
 * Satellite Data Backflow Manager - Fixed Version
 * 
 * Manages comprehensive data collection and processing from satellite sites
 * including user journey tracking, conversion attribution, and analytics
 * 
 * Filename: class-satellite-data-backflow-manager.php
 * Path: /wp-content/plugins/affiliate-master-enhancement/includes/
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * Version: 1.0.1
 * 
 * FIXED: Line 356 SQL syntax error
 * FIXED: Activation hook constant issue
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied');
}

class SatelliteDataBackflowManager {
    
    /**
     * Maximum batch size for data processing
     */
    const MAX_BATCH_SIZE = 100;
    
    /**
     * Data retention period in days
     */
    const DATA_RETENTION_DAYS = 365;
    
    /**
     * Cache duration for processed data
     */
    const CACHE_DURATION = 3600;
    
    /**
     * Plugin file path for activation hooks
     * @var string
     */
    private static $plugin_file;

    /**
     * Constructor - initialise backflow manager
     * 
     * @param string $plugin_file Optional plugin file path
     */
    public function __construct($plugin_file = null) {
        if ($plugin_file) {
            self::$plugin_file = $plugin_file;
        }
        
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'setup_data_collection_endpoints']);
        add_action('ame_process_data_queue', [$this, 'process_data_queue']);
        add_action('ame_cleanup_old_data', [$this, 'cleanup_old_data']);
        add_action('affwp_insert_referral', [$this, 'process_referral_data'], 10, 1);
        add_action('affwp_set_referral_status', [$this, 'handle_referral_status_change'], 10, 3);
        add_action('wp_ajax_ame_process_satellite_data', [$this, 'ajax_process_satellite_data']);
        add_action('wp_ajax_ame_get_backflow_stats', [$this, 'ajax_get_backflow_stats']);
        
        // Register activation hook if plugin file is set
        if (self::$plugin_file && file_exists(self::$plugin_file)) {
            register_activation_hook(self::$plugin_file, [$this, 'create_backflow_tables']);
        }
    }
    
    /**
     * Initialise backflow manager
     */
    public function init() {
        if (!wp_next_scheduled('ame_process_data_queue')) {
            wp_schedule_event(time(), 'hourly', 'ame_process_data_queue');
        }
        
        if (!wp_next_scheduled('ame_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'ame_cleanup_old_data');
        }
    }
    
    /**
     * Setup comprehensive data collection endpoints
     */
    public function setup_data_collection_endpoints() {
        register_rest_route('affiliate/v1', '/track-engagement', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_engagement_tracking'],
            'permission_callback' => [$this, 'verify_satellite_authorization'],
            'args' => [
                'affiliate_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'engagement_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['click', 'view', 'hover', 'scroll', 'time_spent']
                ],
                'domain' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);
        
        register_rest_route('affiliate/v1', '/track-user-journey', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_user_journey_data'],
            'permission_callback' => [$this, 'verify_satellite_authorization']
        ]);
        
        register_rest_route('affiliate/v1', '/track-abandonment', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_cart_abandonment_data'],
            'permission_callback' => [$this, 'verify_satellite_authorization']
        ]);
        
        register_rest_route('affiliate/v1', '/track-social-proof', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_social_proof_metrics'],
            'permission_callback' => [$this, 'verify_satellite_authorization']
        ]);
        
        register_rest_route('affiliate/v1', '/track-performance', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_site_performance_data'],
            'permission_callback' => [$this, 'verify_satellite_authorization']
        ]);
        
        register_rest_route('affiliate/v1', '/track-attribution', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_attribution_tracking'],
            'permission_callback' => [$this, 'verify_satellite_authorization']
        ]);
        
        register_rest_route('affiliate/v1', '/batch-data', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_batch_data_submission'],
            'permission_callback' => [$this, 'verify_satellite_authorization']
        ]);
    }
    
    /**
     * Handle engagement tracking from satellite sites
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_engagement_tracking($request) {
        $engagement_data = $request->get_json_params();
        
        if (empty($engagement_data['affiliate_id']) || empty($engagement_data['engagement_type'])) {
            return new WP_Error('invalid_data', 'Missing required fields', ['status' => 400]);
        }
        
        $processed_data = [
            'data_type' => 'engagement',
            'affiliate_id' => intval($engagement_data['affiliate_id']),
            'domain' => sanitize_text_field($engagement_data['domain'] ?? ''),
            'engagement_type' => sanitize_text_field($engagement_data['engagement_type']),
            'engagement_value' => floatval($engagement_data['engagement_value'] ?? 1),
            'page_url' => esc_url_raw($engagement_data['page_url'] ?? ''),
            'timestamp' => current_time('mysql'),
            'metadata' => json_encode($engagement_data['metadata'] ?? [])
        ];
        
        $this->queue_data_for_processing($processed_data);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Engagement tracked successfully'
        ]);
    }
    
    /**
     * Handle user journey data collection
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_user_journey_data($request) {
        $journey_data = $request->get_json_params();
        
        if (empty($journey_data['session_id'])) {
            return new WP_Error('invalid_data', 'Session ID is required', ['status' => 400]);
        }
        
        $processed_journey = [
            'data_type' => 'user_journey',
            'session_id' => sanitize_text_field($journey_data['session_id']),
            'affiliate_id' => intval($journey_data['affiliate_id'] ?? 0),
            'domain' => sanitize_text_field($journey_data['domain'] ?? ''),
            'entry_point' => esc_url_raw($journey_data['entry_point'] ?? ''),
            'page_visits' => $this->sanitize_page_visits($journey_data['page_visits'] ?? []),
            'affiliate_touchpoints' => $this->identify_affiliate_touchpoints($journey_data['page_visits'] ?? []),
            'conversion_path' => $this->map_conversion_path($journey_data['interactions'] ?? []),
            'time_to_conversion' => intval($journey_data['time_to_conversion'] ?? 0),
            'total_time_spent' => intval($journey_data['total_time_spent'] ?? 0),
            'pages_visited' => intval($journey_data['pages_visited'] ?? 0),
            'converted' => boolval($journey_data['converted'] ?? false),
            'conversion_value' => floatval($journey_data['conversion_value'] ?? 0),
            'influence_score' => $this->calculate_affiliate_influence($journey_data),
            'timestamp' => current_time('mysql'),
            'metadata' => json_encode($journey_data['metadata'] ?? [])
        ];
        
        $this->store_journey_data($processed_journey);
        $this->queue_data_for_processing($processed_journey);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'User journey data processed',
            'attribution_score' => $processed_journey['influence_score']
        ]);
    }
    
    /**
     * Handle cart abandonment tracking
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_cart_abandonment_data($request) {
        $abandonment_data = $request->get_json_params();
        
        $processed_data = [
            'data_type' => 'cart_abandonment',
            'session_id' => sanitize_text_field($abandonment_data['session_id'] ?? ''),
            'affiliate_id' => intval($abandonment_data['affiliate_id'] ?? 0),
            'domain' => sanitize_text_field($abandonment_data['domain'] ?? ''),
            'cart_value' => floatval($abandonment_data['cart_value'] ?? 0),
            'cart_items' => intval($abandonment_data['cart_items'] ?? 0),
            'abandonment_stage' => sanitize_text_field($abandonment_data['stage'] ?? ''),
            'time_in_cart' => intval($abandonment_data['time_in_cart'] ?? 0),
            'affiliate_attribution' => boolval($abandonment_data['affiliate_attributed'] ?? false),
            'recovery_email_sent' => false,
            'timestamp' => current_time('mysql'),
            'metadata' => json_encode($abandonment_data)
        ];
        
        $this->queue_data_for_processing($processed_data);
        
        if ($processed_data['affiliate_attribution'] && $processed_data['cart_value'] > 0) {
            $this->trigger_abandonment_recovery($processed_data);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Abandonment data tracked'
        ]);
    }
    
    /**
     * Handle social proof metrics
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_social_proof_metrics($request) {
        $social_data = $request->get_json_params();
        
        $processed_data = [
            'data_type' => 'social_proof',
            'affiliate_id' => intval($social_data['affiliate_id'] ?? 0),
            'domain' => sanitize_text_field($social_data['domain'] ?? ''),
            'proof_type' => sanitize_text_field($social_data['proof_type'] ?? ''),
            'proof_value' => sanitize_text_field($social_data['proof_value'] ?? ''),
            'display_location' => sanitize_text_field($social_data['display_location'] ?? ''),
            'interaction_count' => intval($social_data['interaction_count'] ?? 0),
            'conversion_attributed' => boolval($social_data['conversion_attributed'] ?? false),
            'timestamp' => current_time('mysql'),
            'metadata' => json_encode($social_data['metadata'] ?? [])
        ];
        
        $this->queue_data_for_processing($processed_data);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Social proof metrics tracked'
        ]);
    }
    
    /**
     * Handle site performance data
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_site_performance_data($request) {
        $performance_data = $request->get_json_params();
        
        $processed_data = [
            'data_type' => 'site_performance',
            'domain' => sanitize_text_field($performance_data['domain'] ?? ''),
            'page_load_time' => floatval($performance_data['page_load_time'] ?? 0),
            'first_contentful_paint' => floatval($performance_data['first_contentful_paint'] ?? 0),
            'largest_contentful_paint' => floatval($performance_data['largest_contentful_paint'] ?? 0),
            'cumulative_layout_shift' => floatval($performance_data['cumulative_layout_shift'] ?? 0),
            'first_input_delay' => floatval($performance_data['first_input_delay'] ?? 0),
            'affiliate_link_render_time' => floatval($performance_data['affiliate_link_render_time'] ?? 0),
            'device_type' => sanitize_text_field($performance_data['device_type'] ?? ''),
            'connection_type' => sanitize_text_field($performance_data['connection_type'] ?? ''),
            'user_agent' => sanitize_text_field($performance_data['user_agent'] ?? ''),
            'timestamp' => current_time('mysql'),
            'metadata' => json_encode($performance_data['metadata'] ?? [])
        ];
        
        $this->queue_data_for_processing($processed_data);
        $this->update_performance_baseline($processed_data['domain'], $processed_data);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Performance data tracked'
        ]);
    }
    
    /**
     * Handle attribution tracking data
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_attribution_tracking($request) {
        $attribution_data = $request->get_json_params();
        
        if (empty($attribution_data['conversion_id'])) {
            return new WP_Error('invalid_data', 'Conversion ID is required', ['status' => 400]);
        }
        
        $processed_data = [
            'data_type' => 'attribution',
            'conversion_id' => sanitize_text_field($attribution_data['conversion_id']),
            'session_id' => sanitize_text_field($attribution_data['session_id'] ?? ''),
            'affiliate_id' => intval($attribution_data['affiliate_id'] ?? 0),
            'domain' => sanitize_text_field($attribution_data['domain'] ?? ''),
            'touchpoints' => $this->process_attribution_touchpoints($attribution_data['touchpoints'] ?? []),
            'attribution_model' => sanitize_text_field($attribution_data['attribution_model'] ?? 'last_click'),
            'conversion_value' => floatval($attribution_data['conversion_value'] ?? 0),
            'commission_amount' => floatval($attribution_data['commission_amount'] ?? 0),
            'time_to_conversion' => intval($attribution_data['time_to_conversion'] ?? 0),
            'touchpoint_count' => intval(count($attribution_data['touchpoints'] ?? [])),
            'timestamp' => current_time('mysql'),
            'metadata' => json_encode($attribution_data['metadata'] ?? [])
        ];
        
        $attribution_results = $this->calculate_multi_touch_attribution($processed_data);
        $processed_data['attribution_results'] = json_encode($attribution_results);
        
        $this->queue_data_for_processing($processed_data);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Attribution data processed',
            'attribution_breakdown' => $attribution_results
        ]);
    }
    
    /**
     * Handle batch data submission from satellite sites
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_batch_data_submission($request) {
        $batch_data = $request->get_json_params();
        
        if (empty($batch_data['data']) || !is_array($batch_data['data'])) {
            return new WP_Error('invalid_batch', 'Invalid batch data format', ['status' => 400]);
        }
        
        if (count($batch_data['data']) > self::MAX_BATCH_SIZE) {
            return new WP_Error('batch_too_large', 'Batch size exceeds maximum limit', ['status' => 413]);
        }
        
        $processed_count = 0;
        $errors = [];
        
        foreach ($batch_data['data'] as $index => $data_item) {
            try {
                $this->process_batch_data_item($data_item);
                $processed_count++;
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $response_data = [
            'success' => true,
            'processed_count' => $processed_count,
            'total_count' => count($batch_data['data']),
            'batch_id' => $this->generate_batch_id()
        ];
        
        if (!empty($errors)) {
            $response_data['errors'] = $errors;
        }
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Process individual batch data item
     * @param array $data_item Data item to process
     */
    private function process_batch_data_item($data_item) {
        if (empty($data_item['data_type'])) {
            throw new Exception('Data type not specified');
        }
        
        $data_item['timestamp'] = $data_item['timestamp'] ?? current_time('mysql');
        $data_item['batch_processed'] = true;
        
        $this->queue_data_for_processing($data_item);
    }
    
    /**
     * Queue data for processing
     * @param array $data_item Data to queue
     */
    private function queue_data_for_processing($data_item) {
        global $wpdb;
        
        $queue_data = [
            'data_type' => $data_item['data_type'],
            'data_payload' => json_encode($data_item),
            'priority' => $this->determine_data_priority($data_item),
            'status' => 'pending',
            'retry_count' => 0,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_data_queue',
            $queue_data,
            ['%s', '%s', '%d', '%s', '%d', '%s']
        );
    }
    
    /**
     * Process queued data (scheduled task)
     * FIXED: Line 356 - Corrected SQL syntax error
     */
    public function process_data_queue() {
        global $wpdb;
        
        // FIXED: Added proper WHERE clause and removed separate LIMIT
        $queue_items = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}affiliate_data_queue
            WHERE status = %s
            ORDER BY priority DESC, created_at ASC
            LIMIT %d",
            'pending',
            self::MAX_BATCH_SIZE
        ));
        
        foreach ($queue_items as $queue_item) {
            try {
                $this->process_queue_item($queue_item);
                
                $wpdb->update(
                    $wpdb->prefix . 'affiliate_data_queue',
                    ['status' => 'processed', 'processed_at' => current_time('mysql')],
                    ['id' => $queue_item->id],
                    ['%s', '%s'],
                    ['%d']
                );
                
            } catch (Exception $e) {
                $retry_count = intval($queue_item->retry_count) + 1;
                
                if ($retry_count >= 3) {
                    $wpdb->update(
                        $wpdb->prefix . 'affiliate_data_queue',
                        [
                            'status' => 'failed',
                            'retry_count' => $retry_count,
                            'error_message' => $e->getMessage(),
                            'processed_at' => current_time('mysql')
                        ],
                        ['id' => $queue_item->id],
                        ['%s', '%d', '%s', '%s'],
                        ['%d']
                    );
                } else {
                    $wpdb->update(
                        $wpdb->prefix . 'affiliate_data_queue',
                        ['retry_count' => $retry_count],
                        ['id' => $queue_item->id],
                        ['%d'],
                        ['%d']
                    );
                }
                
                error_log('AME: Failed to process queue item ' . $queue_item->id . ': ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Process individual queue item
     * @param object $queue_item Queue item to process
     */
    private function process_queue_item($queue_item) {
        $data = json_decode($queue_item->data_payload, true);
        
        if (!$data) {
            throw new Exception('Invalid data payload');
        }
        
        switch ($queue_item->data_type) {
            case 'engagement':
                $this->process_engagement_data($data);
                break;
                
            case 'user_journey':
                $this->process_user_journey_data($data);
                break;
                
            case 'cart_abandonment':
                $this->process_cart_abandonment_data($data);
                break;
                
            case 'social_proof':
                $this->process_social_proof_data($data);
                break;
                
            case 'site_performance':
                $this->process_site_performance_data($data);
                break;
                
            case 'attribution':
                $this->process_attribution_data($data);
                break;
                
            default:
                throw new Exception('Unknown data type: ' . $queue_item->data_type);
        }
    }
    
    // Processing methods for each data type
    
    private function process_engagement_data($data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_enhanced_analytics',
            [
                'affiliate_id' => $data['affiliate_id'],
                'metric_type' => 'engagement_' . $data['engagement_type'],
                'metric_value' => $data['engagement_value'],
                'domain' => $data['domain'],
                'date_recorded' => date('Y-m-d', strtotime($data['timestamp'])),
                'metadata' => $data['metadata'],
                'created_at' => $data['timestamp']
            ],
            ['%d', '%s', '%f', '%s', '%s', '%s', '%s']
        );
        
        $this->update_realtime_metrics($data['affiliate_id'], $data['domain'], [
            'engagement_' . $data['engagement_type'] => $data['engagement_value']
        ]);
    }
    
    private function process_user_journey_data($data) {
        $this->update_journey_insights($data);
        
        if ($data['converted']) {
            $this->update_conversion_attribution($data);
        }
    }
    
    private function process_cart_abandonment_data($data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_cart_abandonment',
            [
                'session_id' => $data['session_id'],
                'affiliate_id' => $data['affiliate_id'],
                'domain' => $data['domain'],
                'cart_value' => $data['cart_value'],
                'cart_items' => $data['cart_items'],
                'abandonment_stage' => $data['abandonment_stage'],
                'time_in_cart' => $data['time_in_cart'],
                'affiliate_attribution' => $data['affiliate_attribution'],
                'created_at' => $data['timestamp']
            ],
            ['%s', '%d', '%s', '%f', '%d', '%s', '%d', '%d', '%s']
        );
        
        $this->update_abandonment_analytics($data);
    }
    
    private function process_social_proof_data($data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_social_proof',
            [
                'affiliate_id' => $data['affiliate_id'],
                'domain' => $data['domain'],
                'proof_type' => $data['proof_type'],
                'proof_value' => $data['proof_value'],
                'display_location' => $data['display_location'],
                'interaction_count' => $data['interaction_count'],
                'conversion_attributed' => $data['conversion_attributed'],
                'created_at' => $data['timestamp']
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );
        
        $this->update_social_proof_metrics($data);
    }
    
    private function process_site_performance_data($data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_site_performance',
            [
                'domain' => $data['domain'],
                'page_load_time' => $data['page_load_time'],
                'first_contentful_paint' => $data['first_contentful_paint'],
                'largest_contentful_paint' => $data['largest_contentful_paint'],
                'cumulative_layout_shift' => $data['cumulative_layout_shift'],
                'first_input_delay' => $data['first_input_delay'],
                'affiliate_link_render_time' => $data['affiliate_link_render_time'],
                'device_type' => $data['device_type'],
                'connection_type' => $data['connection_type'],
                'created_at' => $data['timestamp']
            ],
            ['%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s']
        );
        
        $this->check_performance_thresholds($data);
    }
    
    private function process_attribution_data($data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_attribution_analysis',
            [
                'conversion_id' => $data['conversion_id'],
                'session_id' => $data['session_id'],
                'affiliate_id' => $data['affiliate_id'],
                'domain' => $data['domain'],
                'touchpoints' => json_encode($data['touchpoints']),
                'attribution_model' => $data['attribution_model'],
                'conversion_value' => $data['conversion_value'],
                'commission_amount' => $data['commission_amount'],
                'time_to_conversion' => $data['time_to_conversion'],
                'touchpoint_count' => $data['touchpoint_count'],
                'attribution_results' => $data['attribution_results'],
                'created_at' => $data['timestamp']
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s']
        );
        
        $this->update_attribution_insights($data);
    }
    
    /**
     * Process referral data when referral is created
     * @param int $referral_id Referral ID
     */
    public function process_referral_data($referral_id) {
        $referral = affwp_get_referral($referral_id);
        
        if (!$referral) {
            return;
        }
        
        $referral_data = [
            'data_type' => 'referral_created',
            'referral_id' => $referral_id,
            'affiliate_id' => $referral->affiliate_id,
            'amount' => $referral->amount,
            'description' => $referral->description,
            'reference' => $referral->reference,
            'context' => $referral->context,
            'campaign' => $referral->campaign,
            'status' => $referral->status,
            'currency' => affwp_get_currency(),
            'domain' => $this->extract_domain_from_referral($referral),
            'timestamp' => current_time('mysql')
        ];
        
        $this->queue_data_for_processing($referral_data);
        $this->process_cross_domain_attribution($referral);
    }
    
    /**
     * Handle referral status changes
     * @param int $referral_id Referral ID
     * @param string $new_status New status
     * @param string $old_status Old status
     */
    public function handle_referral_status_change($referral_id, $new_status, $old_status) {
        $referral = affwp_get_referral($referral_id);
        
        if (!$referral) {
            return;
        }
        
        $status_change_data = [
            'data_type' => 'referral_status_change',
            'referral_id' => $referral_id,
            'affiliate_id' => $referral->affiliate_id,
            'new_status' => $new_status,
            'old_status' => $old_status,
            'amount' => $referral->amount,
            'timestamp' => current_time('mysql')
        ];
        
        $this->queue_data_for_processing($status_change_data);
        
        if ($new_status === 'paid') {
            $this->update_conversion_metrics($referral);
        }
    }
    
    /**
     * Calculate multi-touch attribution
     * @param array $conversion_data Conversion data with touchpoints
     * @return array Attribution results
     */
    public function calculate_multi_touch_attribution($conversion_data) {
        $touchpoints = $conversion_data['touchpoints'];
        $conversion_value = $conversion_data['conversion_value'];
        
        if (empty($touchpoints) || $conversion_value <= 0) {
            return [];
        }
        
        $attribution_models = [
            'first_touch' => $this->first_touch_attribution($touchpoints, $conversion_value),
            'last_touch' => $this->last_touch_attribution($touchpoints, $conversion_value),
            'linear' => $this->linear_attribution($touchpoints, $conversion_value),
            'time_decay' => $this->time_decay_attribution($touchpoints, $conversion_value),
            'position_based' => $this->position_based_attribution($touchpoints, $conversion_value),
            'data_driven' => $this->data_driven_attribution($touchpoints, $conversion_value)
        ];
        
        return $attribution_models;
    }
    
    private function first_touch_attribution($touchpoints, $conversion_value) {
        if (empty($touchpoints)) {
            return [];
        }
        
        $first_touchpoint = $touchpoints[0];
        
        return [
            [
                'affiliate_id' => $first_touchpoint['affiliate_id'] ?? 0,
                'touchpoint_type' => $first_touchpoint['type'] ?? 'unknown',
                'attribution_value' => $conversion_value,
                'attribution_percentage' => 100
            ]
        ];
    }
    
    private function last_touch_attribution($touchpoints, $conversion_value) {
        if (empty($touchpoints)) {
            return [];
        }
        
        $last_touchpoint = end($touchpoints);
        
        return [
            [
                'affiliate_id' => $last_touchpoint['affiliate_id'] ?? 0,
                'touchpoint_type' => $last_touchpoint['type'] ?? 'unknown',
                'attribution_value' => $conversion_value,
                'attribution_percentage' => 100
            ]
        ];
    }
    
    private function linear_attribution($touchpoints, $conversion_value) {
        if (empty($touchpoints)) {
            return [];
        }
        
        $attribution_per_touchpoint = $conversion_value / count($touchpoints);
        $attribution_breakdown = [];
        
        foreach ($touchpoints as $touchpoint) {
            $attribution_breakdown[] = [
                'affiliate_id' => $touchpoint['affiliate_id'] ?? 0,
                'touchpoint_type' => $touchpoint['type'] ?? 'unknown',
                'attribution_value' => $attribution_per_touchpoint,
                'attribution_percentage' => (100 / count($touchpoints))
            ];
        }
        
        return $attribution_breakdown;
    }
    
    private function time_decay_attribution($touchpoints, $conversion_value) {
        if (empty($touchpoints)) {
            return [];
        }
        
        $decay_rate = 0.5;
        $attribution_breakdown = [];
        $total_weight = 0;
        
        $weights = [];
        foreach ($touchpoints as $index => $touchpoint) {
            $days_before_conversion = $touchpoint['days_before_conversion'] ?? ($index + 1);
            $weight = pow($decay_rate, $days_before_conversion - 1);
            $weights[] = $weight;
            $total_weight += $weight;
        }
        
        foreach ($touchpoints as $index => $touchpoint) {
            $attribution_percentage = ($weights[$index] / $total_weight) * 100;
            $attribution_value = ($weights[$index] / $total_weight) * $conversion_value;
            
            $attribution_breakdown[] = [
                'affiliate_id' => $touchpoint['affiliate_id'] ?? 0,
                'touchpoint_type' => $touchpoint['type'] ?? 'unknown',
                'attribution_value' => $attribution_value,
                'attribution_percentage' => $attribution_percentage,
                'weight' => $weights[$index]
            ];
        }
        
        return $attribution_breakdown;
    }
    
    private function position_based_attribution($touchpoints, $conversion_value) {
        if (empty($touchpoints)) {
            return [];
        }
        
        $attribution_breakdown = [];
        $touchpoint_count = count($touchpoints);
        
        if ($touchpoint_count === 1) {
            $attribution_breakdown[] = [
                'affiliate_id' => $touchpoints[0]['affiliate_id'] ?? 0,
                'touchpoint_type' => $touchpoints[0]['type'] ?? 'unknown',
                'attribution_value' => $conversion_value,
                'attribution_percentage' => 100
            ];
        } elseif ($touchpoint_count === 2) {
            foreach ($touchpoints as $touchpoint) {
                $attribution_breakdown[] = [
                    'affiliate_id' => $touchpoint['affiliate_id'] ?? 0,
                    'touchpoint_type' => $touchpoint['type'] ?? 'unknown',
                    'attribution_value' => $conversion_value * 0.5,
                    'attribution_percentage' => 50
                ];
            }
        } else {
            $middle_count = $touchpoint_count - 2;
            $middle_attribution_each = $middle_count > 0 ? (0.2 / $middle_count) : 0;
            
            foreach ($touchpoints as $index => $touchpoint) {
                if ($index === 0) {
                    $attribution_percentage = 40;
                    $attribution_value = $conversion_value * 0.4;
                } elseif ($index === $touchpoint_count - 1) {
                    $attribution_percentage = 40;
                    $attribution_value = $conversion_value * 0.4;
                } else {
                    $attribution_percentage = $middle_attribution_each * 100;
                    $attribution_value = $conversion_value * $middle_attribution_each;
                }
                
                $attribution_breakdown[] = [
                    'affiliate_id' => $touchpoint['affiliate_id'] ?? 0,
                    'touchpoint_type' => $touchpoint['type'] ?? 'unknown',
                    'attribution_value' => $attribution_value,
                    'attribution_percentage' => $attribution_percentage
                ];
            }
        }
        
        return $attribution_breakdown;
    }
    
    private function data_driven_attribution($touchpoints, $conversion_value) {
        return $this->position_based_attribution($touchpoints, $conversion_value);
    }
    
    /**
     * Verify satellite authorization
     * @param WP_REST_Request $request Request object
     * @return bool Authorization result
     */
    public function verify_satellite_authorization($request) {
        $api_key = $request->get_header('X-API-Key');
        $domain = $request->get_header('X-Origin-Domain') ?: $request->get_param('domain');
        
        if (!$api_key || !$domain) {
            return false;
        }
        
        global $wpdb;
        
        $authorized = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}affiliate_authorized_domains
            WHERE domain = %s
            AND api_key = %s
            AND is_active = 1
        ", $domain, $api_key));
        
        return $authorized > 0;
    }
    
    /**
     * AJAX handler for processing satellite data
     */
    public function ajax_process_satellite_data() {
        check_ajax_referer('ame_satellite_data', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die('Insufficient permissions');
        }
        
        $data_type = sanitize_text_field($_POST['data_type'] ?? '');
        $data_payload = $_POST['data_payload'] ?? [];
        
        if (!$data_type || empty($data_payload)) {
            wp_send_json_error('Invalid data provided');
        }
        
        try {
            $this->queue_data_for_processing([
                'data_type' => $data_type,
                'data_payload' => $data_payload,
                'timestamp' => current_time('mysql')
            ]);
            
            wp_send_json_success(['message' => 'Data queued for processing']);
        } catch (Exception $e) {
            wp_send_json_error('Failed to process data: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting backflow statistics
     */
    public function ajax_get_backflow_stats() {
        check_ajax_referer('ame_backflow_stats', 'nonce');
        
        if (!current_user_can('view_affiliate_analytics')) {
            wp_die('Insufficient permissions');
        }
        
        $stats = $this->get_backflow_statistics();
        wp_send_json_success($stats);
    }
    
    /**
     * Get comprehensive backflow statistics
     * @return array Backflow statistics
     */
    public function get_backflow_statistics() {
        global $wpdb;
        
        $queue_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                AVG(CASE WHEN status = 'processed' 
                    THEN TIMESTAMPDIFF(SECOND, created_at, processed_at) 
                    END) as avg_processing_time
            FROM {$wpdb->prefix}affiliate_data_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        return [
            'queue_statistics' => [
                'total_items_24h' => intval($queue_stats->total_items ?? 0),
                'pending' => intval($queue_stats->pending ?? 0),
                'processed' => intval($queue_stats->processed ?? 0),
                'failed' => intval($queue_stats->failed ?? 0),
                'success_rate' => $queue_stats->total_items > 0 ? 
                    round(($queue_stats->processed / $queue_stats->total_items) * 100, 2) : 0,
                'average_processing_time' => round($queue_stats->avg_processing_time ?? 0, 2)
            ],
            'system_health' => $this->assess_backflow_system_health()
        ];
    }
    
    private function assess_backflow_system_health() {
        global $wpdb;
        
        $pending_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}affiliate_data_queue 
            WHERE status = 'pending'
        ");
        
        $error_rate = $wpdb->get_var("
            SELECT 
                COUNT(CASE WHEN status = 'failed' THEN 1 END) / COUNT(*) * 100
            FROM {$wpdb->prefix}affiliate_data_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        $health_status = 'healthy';
        $issues = [];
        
        if ($pending_count > 1000) {
            $health_status = 'warning';
            $issues[] = 'High queue backlog detected';
        }
        
        if ($error_rate > 5) {
            $health_status = 'critical';
            $issues[] = 'High error rate detected';
        }
        
        return [
            'status' => $health_status,
            'pending_queue_size' => intval($pending_count),
            'error_rate_percent' => round($error_rate ?? 0, 2),
            'issues' => $issues
        ];
    }
    
    /**
     * Clean up old data (scheduled task)
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        $retention_date = date('Y-m-d', strtotime('-' . self::DATA_RETENTION_DAYS . ' days'));
        
        $deleted_queue = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}affiliate_data_queue
            WHERE status IN ('processed', 'failed')
            AND created_at < %s
        ", $retention_date));
        
        error_log("AME: Data cleanup completed - Queue: {$deleted_queue}");
    }
    
    /**
     * Create data backflow database tables
     */
    public static function create_backflow_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $queue_table = $wpdb->prefix . 'affiliate_data_queue';
        $sql_queue = "CREATE TABLE IF NOT EXISTS $queue_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            data_type varchar(50) NOT NULL,
            data_payload longtext NOT NULL,
            priority tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
            status enum('pending', 'processing', 'processed', 'failed') NOT NULL DEFAULT 'pending',
            retry_count tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY data_type_index (data_type),
            KEY status_index (status),
            KEY priority_index (priority),
            KEY created_at_index (created_at)
        ) $charset_collate;";
        
        $cart_table = $wpdb->prefix . 'affiliate_cart_abandonment';
        $sql_cart = "CREATE TABLE IF NOT EXISTS $cart_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            domain varchar(255) NOT NULL,
            cart_value decimal(15,2) NOT NULL DEFAULT 0.00,
            cart_items int(11) UNSIGNED NOT NULL DEFAULT 0,
            abandonment_stage varchar(50) DEFAULT NULL,
            time_in_cart int(11) UNSIGNED NOT NULL DEFAULT 0,
            affiliate_attribution tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id_index (session_id),
            KEY affiliate_id_index (affiliate_id)
        ) $charset_collate;";
        
        $social_table = $wpdb->prefix . 'affiliate_social_proof';
        $sql_social = "CREATE TABLE IF NOT EXISTS $social_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            domain varchar(255) NOT NULL,
            proof_type varchar(50) NOT NULL,
            proof_value text,
            display_location varchar(100) DEFAULT NULL,
            interaction_count int(11) UNSIGNED NOT NULL DEFAULT 0,
            conversion_attributed tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id_index (affiliate_id)
        ) $charset_collate;";
        
        $performance_table = $wpdb->prefix . 'affiliate_site_performance';
        $sql_performance = "CREATE TABLE IF NOT EXISTS $performance_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            page_load_time decimal(8,3) DEFAULT NULL,
            first_contentful_paint decimal(8,3) DEFAULT NULL,
            device_type varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain_index (domain)
        ) $charset_collate;";
        
        $attribution_table = $wpdb->prefix . 'affiliate_attribution_analysis';
        $sql_attribution = "CREATE TABLE IF NOT EXISTS $attribution_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversion_id varchar(100) NOT NULL,
            affiliate_id bigint(20) UNSIGNED DEFAULT NULL,
            domain varchar(255) NOT NULL,
            conversion_value decimal(15,2) NOT NULL DEFAULT 0.00,
            touchpoint_count int(11) UNSIGNED NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY conversion_id_unique (conversion_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_queue);
        dbDelta($sql_cart);
        dbDelta($sql_social);
        dbDelta($sql_performance);
        dbDelta($sql_attribution);
    }
    
    // Helper methods
    private function sanitize_page_visits($visits) { return is_array($visits) ? $visits : []; }
    private function identify_affiliate_touchpoints($visits) { return []; }
    private function map_conversion_path($interactions) { return is_array($interactions) ? $interactions : []; }
    private function calculate_affiliate_influence($data) { return 0.5; }
    private function store_journey_data($data) { /* Store in DB */ }
    private function trigger_abandonment_recovery($data) { /* Trigger recovery */ }
    private function update_performance_baseline($domain, $data) { /* Update baseline */ }
    private function process_attribution_touchpoints($touchpoints) { return is_array($touchpoints) ? $touchpoints : []; }
    private function generate_batch_id() { return 'batch_' . uniqid(); }
    private function determine_data_priority($data) { return 5; }
    private function update_realtime_metrics($affiliate_id, $domain, $metrics) { /* Update metrics */ }
    private function update_journey_insights($data) { /* Update insights */ }
    private function update_conversion_attribution($data) { /* Update attribution */ }
    private function extract_domain_from_referral($referral) { return ''; }
    private function process_cross_domain_attribution($referral) { /* Process attribution */ }
    private function update_conversion_metrics($referral) { /* Update metrics */ }
    private function update_abandonment_analytics($data) { /* Update analytics */ }
    private function update_social_proof_metrics($data) { /* Update metrics */ }
    private function check_performance_thresholds($data) { /* Check thresholds */ }
    private function update_attribution_insights($data) { /* Update insights */ }
}