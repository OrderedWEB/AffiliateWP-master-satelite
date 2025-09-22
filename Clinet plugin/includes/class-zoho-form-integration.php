<?php
/**
 * Zoho Form Integration Class
 *
 * Handles integration with Zoho forms by automatically populating
 * affiliate tracking data and managing lead attribution.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFILIATE_CLIENT_Zoho_Form_Integration {

    /**
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
     * Constructor
     *
     * @param array $config Plugin configuration
     * @param AFFILIATE_CLIENT_API_Client $api_client API client instance
     */
    public function __construct($config, $api_client) {
        $this->config = $config;
        $this->api_client = $api_client;
    }

    /**
     * Initialize Zoho form integration
     */
    public function init() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        // Shortcode for Zoho form integration
        add_shortcode('affiliate_zoho_form', [$this, 'zoho_form_shortcode']);
        
        // JavaScript hooks for automatic form population
        add_action('wp_footer', [$this, 'output_zoho_integration_script']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_zoho_scripts']);
        
        // AJAX handlers for Zoho webhooks
        add_action('wp_ajax_affiliate_client_zoho_webhook', [$this, 'handle_zoho_webhook']);
        add_action('wp_ajax_nopriv_affiliate_client_zoho_webhook', [$this, 'handle_zoho_webhook']);
        
        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_zoho_endpoints']);
        
        // Admin settings
        add_action('admin_init', [$this, 'register_zoho_settings']);
    }

    /**
     * Zoho form shortcode
     * 
     * Usage: [affiliate_zoho_form form_id="12345" auto_populate="true"]
     */
    public function zoho_form_shortcode($atts) {
        $atts = shortcode_atts([
            'form_id' => '',
            'form_url' => '',
            'auto_populate' => 'true',
            'height' => '600',
            'width' => '100%',
            'affiliate_field' => 'SingleLine', // Zoho field name for affiliate ID
            'visit_field' => 'SingleLine1', // Zoho field name for visit ID
            'source_field' => 'SingleLine2', // Zoho field name for source
            'campaign_field' => 'SingleLine3', // Zoho field name for campaign
            'track_submissions' => 'true',
            'class' => '',
        ], $atts, 'affiliate_zoho_form');

        if (empty($atts['form_id']) && empty($atts['form_url'])) {
            return '<p class="error">Zoho form ID or URL is required.</p>';
        }

        // Generate form URL if not provided
        if (empty($atts['form_url'])) {
            $atts['form_url'] = $this->generate_zoho_form_url($atts['form_id']);
        }

        // Add affiliate tracking parameters to form URL
        $form_url = $this->add_tracking_parameters($atts['form_url'], $atts);

        return $this->render_zoho_form($form_url, $atts);
    }

    /**
     * Generate Zoho form URL from form ID
     */
    private function generate_zoho_form_url($form_id) {
        // Standard Zoho Forms URL pattern
        return "https://forms.zohopublic.com/yourorganization/form/{$form_id}/formperma";
    }

    /**
     * Add affiliate tracking parameters to form URL
     */
    private function add_tracking_parameters($form_url, $atts) {
        $tracking_params = $this->get_tracking_parameters();
        
        // Map tracking data to Zoho form fields
        $zoho_params = [];
        
        if (!empty($tracking_params['affiliate_id']) && !empty($atts['affiliate_field'])) {
            $zoho_params[$atts['affiliate_field']] = $tracking_params['affiliate_id'];
        }
        
        if (!empty($tracking_params['visit_id']) && !empty($atts['visit_field'])) {
            $zoho_params[$atts['visit_field']] = $tracking_params['visit_id'];
        }
        
        if (!empty($tracking_params['utm_source']) && !empty($atts['source_field'])) {
            $zoho_params[$atts['source_field']] = $tracking_params['utm_source'];
        }
        
        if (!empty($tracking_params['utm_campaign']) && !empty($atts['campaign_field'])) {
            $zoho_params[$atts['campaign_field']] = $tracking_params['utm_campaign'];
        }

        // Add parameters to URL
        if (!empty($zoho_params)) {
            $form_url = add_query_arg($zoho_params, $form_url);
        }

        return $form_url;
    }

    /**
     * Get current tracking parameters
     */
    private function get_tracking_parameters() {
        $params = [];

        // Get affiliate ID from cookie or tracking handler
        $affiliate_id = null;
        if (isset($_COOKIE[$this->config['cookie_name']])) {
            $affiliate_id = intval($_COOKIE[$this->config['cookie_name']]);
        }
        
        // Get visit ID
        $visit_id = null;
        if (isset($_COOKIE['affiliate_client_visit_id'])) {
            $visit_id = sanitize_text_field($_COOKIE['affiliate_client_visit_id']);
        }

        // Get UTM parameters from current page or session
        $utm_params = [
            'utm_source' => $_GET['utm_source'] ?? $_SESSION['utm_source'] ?? 'affiliate',
            'utm_medium' => $_GET['utm_medium'] ?? $_SESSION['utm_medium'] ?? 'referral',
            'utm_campaign' => $_GET['utm_campaign'] ?? $_SESSION['utm_campaign'] ?? '',
            'utm_content' => $_GET['utm_content'] ?? $_SESSION['utm_content'] ?? '',
            'utm_term' => $_GET['utm_term'] ?? $_SESSION['utm_term'] ?? '',
        ];

        return array_merge([
            'affiliate_id' => $affiliate_id,
            'visit_id' => $visit_id,
            'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'timestamp' => current_time('c'),
        ], $utm_params);
    }

    /**
     * Render Zoho form iframe
     */
    private function render_zoho_form($form_url, $atts) {
        $classes = ['affiliate-zoho-form'];
        if (!empty($atts['class'])) {
            $classes[] = $atts['class'];
        }

        $iframe_id = 'zoho-form-' . uniqid();

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" 
             data-form-id="<?php echo esc_attr($atts['form_id']); ?>"
             data-track-submissions="<?php echo esc_attr($atts['track_submissions']); ?>">
            
            <iframe id="<?php echo esc_attr($iframe_id); ?>"
                    src="<?php echo esc_url($form_url); ?>"
                    width="<?php echo esc_attr($atts['width']); ?>"
                    height="<?php echo esc_attr($atts['height']); ?>"
                    frameborder="0"
                    marginheight="0"
                    marginwidth="0"
                    scrolling="auto"
                    title="Zoho Form">
                <p><?php _e('Loading form...', 'affiliate-client-full'); ?></p>
            </iframe>
        </div>

        <?php if ($atts['track_submissions'] === 'true'): ?>
        <script>
        (function() {
            // Track form load
            if (typeof AffiliateClientTracker !== 'undefined') {
                AffiliateClientTracker.trackEvent('zoho_form_loaded', {
                    form_id: '<?php echo esc_js($atts['form_id']); ?>',
                    form_url: '<?php echo esc_js($form_url); ?>'
                });
            }

            // Listen for form submission (if possible via postMessage)
            window.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'zoho_form_submit') {
                    if (typeof AffiliateClientTracker !== 'undefined') {
                        AffiliateClientTracker.trackEvent('zoho_form_submitted', {
                            form_id: '<?php echo esc_js($atts['form_id']); ?>',
                            submission_data: event.data
                        });
                    }
                }
            });
        })();
        </script>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue Zoho integration scripts
     */
    public function enqueue_zoho_scripts() {
        wp_enqueue_script(
            'affiliate-client-zoho-integration',
            AFFILIATE_CLIENT_FULL_PLUGIN_URL . 'assets/js/zoho-integration.js',
            ['jquery'],
            AFFILIATE_CLIENT_FULL_VERSION,
            true
        );

        wp_localize_script('affiliate-client-zoho-integration', 'affiliateClientZoho', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('affiliate-client/v1/'),
            'nonce' => wp_create_nonce('affiliate_client_nonce'),
            'trackingData' => $this->get_tracking_parameters(),
        ]);
    }

    /**
     * Output Zoho integration script
     */
    public function output_zoho_integration_script() {
        if (!$this->config['tracking_enabled']) {
            return;
        }

        $tracking_params = $this->get_tracking_parameters();
        ?>
        <script type="text/javascript">
        window.affiliateClientZohoTracking = {
            affiliateId: <?php echo json_encode($tracking_params['affiliate_id']); ?>,
            visitId: <?php echo json_encode($tracking_params['visit_id']); ?>,
            utmSource: <?php echo json_encode($tracking_params['utm_source']); ?>,
            utmMedium: <?php echo json_encode($tracking_params['utm_medium']); ?>,
            utmCampaign: <?php echo json_encode($tracking_params['utm_campaign']); ?>,
            utmContent: <?php echo json_encode($tracking_params['utm_content']); ?>,
            utmTerm: <?php echo json_encode($tracking_params['utm_term']); ?>,
            
            // Auto-populate function for external Zoho forms
            populateZohoForms: function() {
                // Find all Zoho form iframes and populate hidden fields
                var zohoForms = document.querySelectorAll('iframe[src*="zohopublic.com"]');
                zohoForms.forEach(function(iframe) {
                    // Send tracking data to iframe if possible
                    if (iframe.contentWindow) {
                        iframe.contentWindow.postMessage({
                            type: 'affiliate_tracking_data',
                            data: affiliateClientZohoTracking
                        }, '*');
                    }
                });
            }
        };

        // Auto-populate forms when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (window.affiliateClientZohoTracking) {
                window.affiliateClientZohoTracking.populateZohoForms();
            }
        });
        </script>
        <?php
    }

    /**
     * Register REST API endpoints for Zoho integration
     */
    public function register_zoho_endpoints() {
        register_rest_route('affiliate-client/v1', '/zoho/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_handle_zoho_webhook'],
            'permission_callback' => [$this, 'verify_zoho_webhook'],
        ]);

        register_rest_route('affiliate-client/v1', '/zoho/tracking-data', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_tracking_data'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle Zoho webhook (form submission notification)
     */
    public function handle_zoho_webhook() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_client_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $webhook_data = $_POST['webhook_data'] ?? [];
        $result = $this->process_zoho_submission($webhook_data);
        
        wp_send_json($result);
    }

    /**
     * REST API handler for Zoho webhook
     */
    public function rest_handle_zoho_webhook($request) {
        $webhook_data = $request->get_json_params();
        $result = $this->process_zoho_submission($webhook_data);
        
        return rest_ensure_response($result);
    }

    /**
     * REST API handler for getting tracking data
     */
    public function rest_get_tracking_data($request) {
        $tracking_data = $this->get_tracking_parameters();
        return rest_ensure_response($tracking_data);
    }

    /**
     * Process Zoho form submission
     */
    private function process_zoho_submission($webhook_data) {
        // Extract affiliate tracking data from form submission
        $affiliate_id = $this->extract_field_value($webhook_data, 'affiliate_id');
        $visit_id = $this->extract_field_value($webhook_data, 'visit_id');
        $utm_source = $this->extract_field_value($webhook_data, 'utm_source');
        $utm_campaign = $this->extract_field_value($webhook_data, 'utm_campaign');

        // Track form submission event
        $main_plugin = affiliate_client_full();
        if ($main_plugin && $main_plugin->tracking_handler && $affiliate_id) {
            $main_plugin->tracking_handler->track_event('zoho_form_submission', [
                'form_id' => $webhook_data['form_id'] ?? '',
                'submission_id' => $webhook_data['submission_id'] ?? '',
                'affiliate_id' => $affiliate_id,
                'visit_id' => $visit_id,
                'utm_source' => $utm_source,
                'utm_campaign' => $utm_campaign,
                'submission_data' => $webhook_data,
            ]);
        }

        // Send lead data to main affiliate site
        if ($this->api_client && $affiliate_id) {
            $lead_data = [
                'type' => 'zoho_form_lead',
                'affiliate_id' => $affiliate_id,
                'visit_id' => $visit_id,
                'form_data' => $webhook_data,
                'tracking_data' => [
                    'utm_source' => $utm_source,
                    'utm_campaign' => $utm_campaign,
                    'page_url' => $webhook_data['page_url'] ?? '',
                    'referrer' => $webhook_data['referrer'] ?? '',
                ],
                'timestamp' => current_time('c'),
            ];

            $this->api_client->send_tracking_data($lead_data);
        }

        return [
            'success' => true,
            'message' => 'Zoho form submission processed',
            'affiliate_id' => $affiliate_id,
        ];
    }

    /**
     * Extract field value from Zoho webhook data
     */
    private function extract_field_value($webhook_data, $field_type) {
        // Zoho webhook data structure varies, so we need to search for our tracking fields
        $possible_field_names = [
            'affiliate_id' => ['affiliate_id', 'SingleLine', 'Affiliate_ID', 'ref'],
            'visit_id' => ['visit_id', 'SingleLine1', 'Visit_ID'],
            'utm_source' => ['utm_source', 'SingleLine2', 'UTM_Source', 'source'],
            'utm_campaign' => ['utm_campaign', 'SingleLine3', 'UTM_Campaign', 'campaign'],
        ];

        $field_names = $possible_field_names[$field_type] ?? [];
        
        foreach ($field_names as $field_name) {
            if (isset($webhook_data[$field_name])) {
                return sanitize_text_field($webhook_data[$field_name]);
            }
        }

        return null;
    }

    /**
     * Verify Zoho webhook authenticity
     */
    public function verify_zoho_webhook($request) {
        // Add webhook verification logic here
        // This could include checking IP addresses, signatures, etc.
        
        $webhook_secret = get_option('affiliate_client_zoho_webhook_secret', '');
        if (empty($webhook_secret)) {
            return true; // Allow if no secret is set
        }

        $signature = $request->get_header('X-Zoho-Signature');
        $body = $request->get_body();
        $expected_signature = hash_hmac('sha256', $body, $webhook_secret);

        return hash_equals($signature, $expected_signature);
    }

    /**
     * Register admin settings for Zoho integration
     */
    public function register_zoho_settings() {
        register_setting('affiliate_client_settings', 'affiliate_client_zoho_webhook_secret');
        register_setting('affiliate_client_settings', 'affiliate_client_zoho_organization');
        register_setting('affiliate_client_settings', 'affiliate_client_zoho_default_fields');
    }

    /**
     * Get Zoho form statistics
     */
    public function get_zoho_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_client_logs';
        
        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                JSON_EXTRACT(data, '$.form_id') as form_id,
                COUNT(*) as submission_count,
                COUNT(DISTINCT affiliate_id) as unique_affiliates,
                COUNT(DISTINCT JSON_EXTRACT(data, '$.visit_id')) as unique_visits
            FROM {$table_name}
            WHERE event_type = 'zoho_form_submission'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY JSON_EXTRACT(data, '$.form_id')
            ORDER BY submission_count DESC
        ", $days));
        
        $formatted_stats = [];
        foreach ($stats as $stat) {
            $form_id = trim($stat->form_id, '"');
            $formatted_stats[] = [
                'form_id' => $form_id,
                'submissions' => intval($stat->submission_count),
                'unique_affiliates' => intval($stat->unique_affiliates),
                'unique_visits' => intval($stat->unique_visits),
            ];
        }
        
        return $formatted_stats;
    }

    /**
     * Generate tracking URL for external links
     */
    public function generate_tracking_url($base_url, $affiliate_id = null, $campaign = null) {
        $tracking_params = [];
        
        if ($affiliate_id) {
            $tracking_params['ref'] = $affiliate_id;
        }
        
        if ($campaign) {
            $tracking_params['utm_campaign'] = $campaign;
        }
        
        // Add standard UTM parameters
        $tracking_params['utm_source'] = 'affiliate';
        $tracking_params['utm_medium'] = 'referral';
        
        return add_query_arg($tracking_params, $base_url);
    }
}