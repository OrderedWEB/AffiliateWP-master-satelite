<?php
/**
 * Zoho Integration Class
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-zoho-integration.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Zoho_Integration {

    /**
     * Zoho API configuration
     */
    private $client_id;
    private $client_secret;
    private $refresh_token;
    private $access_token;
    private $api_domain;
    private $books_org_id;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_configuration();
        add_action('aci_process_affiliate_conversion', [$this, 'create_zoho_invoice'], 10, 2);
        add_action('aci_affiliate_discount_applied', [$this, 'log_discount_in_zoho'], 10, 3);
    }

    /**
     * Load Zoho configuration from WordPress options
     */
    private function load_configuration() {
        $zoho_settings = get_option('aci_zoho_settings', []);
        
        $this->client_id = $zoho_settings['client_id'] ?? '';
        $this->client_secret = $zoho_settings['client_secret'] ?? '';
        $this->refresh_token = $zoho_settings['refresh_token'] ?? '';
        $this->api_domain = $zoho_settings['api_domain'] ?? 'https://www.zohoapis.com';
        $this->books_org_id = $zoho_settings['books_org_id'] ?? '';
        
        // Load cached access token
        $this->access_token = get_transient('aci_zoho_access_token');
    }

    /**
     * Get valid access token
     */
    private function get_access_token() {
        if (!$this->access_token) {
            $this->refresh_access_token();
        }
        return $this->access_token;
    }

    /**
     * Refresh Zoho access token
     */
    private function refresh_access_token() {
        if (!$this->refresh_token || !$this->client_id || !$this->client_secret) {
            error_log('ACI Zoho: Missing authentication credentials');
            return false;
        }

        $response = wp_remote_post('https://accounts.zoho.com/oauth/v2/token', [
            'body' => [
                'refresh_token' => $this->refresh_token,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token'
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log('ACI Zoho: Token refresh failed - ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['access_token'])) {
            error_log('ACI Zoho: Invalid token response - ' . wp_remote_retrieve_body($response));
            return false;
        }

        $this->access_token = $body['access_token'];
        
        // Cache token for 55 minutes (expires in 1 hour)
        set_transient('aci_zoho_access_token', $this->access_token, 3300);
        
        return true;
    }

    /**
     * Make authenticated API request to Zoho
     */
    private function api_request($endpoint, $method = 'GET', $data = null) {
        $access_token = $this->get_access_token();
        
        if (!$access_token) {
            return new WP_Error('auth_failed', 'Failed to get valid access token');
        }

        $url = $this->api_domain . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('ACI Zoho API Error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 400) {
            error_log('ACI Zoho API Error: HTTP ' . $response_code . ' - ' . wp_remote_retrieve_body($response));
            return new WP_Error('api_error', 'Zoho API returned error: ' . $response_code);
        }

        return $response_body;
    }

    /**
     * Create invoice in Zoho Books when affiliate conversion occurs
     */
    public function create_zoho_invoice($affiliate_code, $conversion_data) {
        if (!$this->books_org_id) {
            error_log('ACI Zoho: Books organization ID not configured');
            return false;
        }

        // Get customer information
        $customer_data = $this->prepare_customer_data($conversion_data);
        $customer_id = $this->get_or_create_customer($customer_data);
        
        if (!$customer_id) {
            error_log('ACI Zoho: Failed to create/get customer');
            return false;
        }

        // Prepare invoice data
        $invoice_data = [
            'customer_id' => $customer_id,
            'date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'reference_number' => 'AFF-' . $affiliate_code . '-' . time(),
            'line_items' => $this->prepare_line_items($conversion_data),
            'notes' => sprintf(
                'Invoice generated from affiliate conversion. Affiliate Code: %s, Domain: %s',
                $affiliate_code,
                $conversion_data['domain'] ?? 'Unknown'
            ),
            'custom_fields' => [
                [
                    'label' => 'Affiliate Code',
                    'value' => $affiliate_code
                ],
                [
                    'label' => 'Source Domain',
                    'value' => $conversion_data['domain'] ?? ''
                ]
            ]
        ];

        // Apply affiliate discount if applicable
        if (!empty($conversion_data['discount_amount'])) {
            $invoice_data['discount'] = $conversion_data['discount_amount'];
            $invoice_data['discount_type'] => 'amount';
        }

        $endpoint = '/books/v3/invoices?organization_id=' . $this->books_org_id;
        $response = $this->api_request($endpoint, 'POST', $invoice_data);

        if (is_wp_error($response)) {
            error_log('ACI Zoho: Invoice creation failed - ' . $response->get_error_message());
            return false;
        }

        if (isset($response['invoice']['invoice_id'])) {
            // Store invoice reference
            update_option('aci_conversion_' . $affiliate_code . '_' . time(), [
                'zoho_invoice_id' => $response['invoice']['invoice_id'],
                'zoho_invoice_number' => $response['invoice']['invoice_number'],
                'conversion_data' => $conversion_data,
                'created_at' => current_time('mysql')
            ]);

            do_action('aci_zoho_invoice_created', $response['invoice'], $affiliate_code, $conversion_data);
            
            return $response['invoice']['invoice_id'];
        }

        return false;
    }

    /**
     * Prepare customer data for Zoho
     */
    private function prepare_customer_data($conversion_data) {
        return [
            'contact_name' => $conversion_data['customer_name'] ?? 'Affiliate Customer',
            'company_name' => $conversion_data['company_name'] ?? '',
            'email' => $conversion_data['customer_email'] ?? '',
            'phone' => $conversion_data['customer_phone'] ?? '',
            'billing_address' => [
                'address' => $conversion_data['billing_address'] ?? '',
                'city' => $conversion_data['billing_city'] ?? '',
                'state' => $conversion_data['billing_state'] ?? '',
                'zip' => $conversion_data['billing_zip'] ?? '',
                'country' => $conversion_data['billing_country'] ?? ''
            ]
        ];
    }

    /**
     * Get existing customer or create new one
     */
    private function get_or_create_customer($customer_data) {
        if (empty($customer_data['email'])) {
            return false;
        }

        // Search for existing customer by email
        $search_endpoint = '/books/v3/contacts?organization_id=' . $this->books_org_id . '&email=' . urlencode($customer_data['email']);
        $search_response = $this->api_request($search_endpoint);

        if (!is_wp_error($search_response) && !empty($search_response['contacts'])) {
            return $search_response['contacts'][0]['contact_id'];
        }

        // Create new customer
        $create_endpoint = '/books/v3/contacts?organization_id=' . $this->books_org_id;
        $create_response = $this->api_request($create_endpoint, 'POST', $customer_data);

        if (is_wp_error($create_response)) {
            return false;
        }

        return $create_response['contact']['contact_id'] ?? false;
    }

    /**
     * Prepare line items for invoice
     */
    private function prepare_line_items($conversion_data) {
        $line_items = [];

        if (!empty($conversion_data['products'])) {
            foreach ($conversion_data['products'] as $product) {
                $line_items[] = [
                    'name' => $product['name'] ?? 'Product',
                    'description' => $product['description'] ?? '',
                    'rate' => floatval($product['price'] ?? 0),
                    'quantity' => intval($product['quantity'] ?? 1),
                    'tax_id' => $product['tax_id'] ?? null
                ];
            }
        } else {
            // Default line item if no products specified
            $line_items[] = [
                'name' => 'Affiliate Conversion',
                'description' => 'Purchase through affiliate link',
                'rate' => floatval($conversion_data['total_amount'] ?? 0),
                'quantity' => 1
            ];
        }

        return $line_items;
    }

    /**
     * Log discount application in Zoho
     */
    public function log_discount_in_zoho($affiliate_code, $discount_amount, $conversion_data) {
        // Create a credit note or adjustment entry for the discount
        if (!$this->books_org_id || empty($discount_amount)) {
            return;
        }

        $note_data = [
            'date' => date('Y-m-d'),
            'reference_number' => 'DISC-' . $affiliate_code . '-' . time(),
            'notes' => sprintf(
                'Affiliate discount applied. Code: %s, Amount: %s',
                $affiliate_code,
                $discount_amount
            ),
            'line_items' => [
                [
                    'name' => 'Affiliate Discount',
                    'description' => 'Discount applied for affiliate code: ' . $affiliate_code,
                    'rate' => floatval($discount_amount),
                    'quantity' => 1
                ]
            ]
        ];

        // Log the discount for reporting
        $this->log_affiliate_activity('discount_applied', [
            'affiliate_code' => $affiliate_code,
            'discount_amount' => $discount_amount,
            'conversion_data' => $conversion_data
        ]);
    }

    /**
     * Log affiliate activity
     */
    private function log_affiliate_activity($activity_type, $data) {
        $log_entry = [
            'activity_type' => $activity_type,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'domain' => $data['conversion_data']['domain'] ?? ''
        ];

        $existing_logs = get_option('aci_zoho_activity_log', []);
        $existing_logs[] = $log_entry;

        // Keep only last 1000 entries
        if (count($existing_logs) > 1000) {
            $existing_logs = array_slice($existing_logs, -1000);
        }

        update_option('aci_zoho_activity_log', $existing_logs);
    }

    /**
     * Test Zoho connection
     */
    public function test_connection() {
        $endpoint = '/books/v3/organizations';
        $response = $this->api_request($endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            ];
        }

        if (isset($response['organizations'])) {
            return [
                'success' => true,
                'message' => 'Successfully connected to Zoho',
                'organizations' => $response['organizations']
            ];
        }

        return [
            'success' => false,
            'message' => 'Unexpected response from Zoho API'
        ];
    }

    /**
     * Get Zoho Books organizations
     */
    public function get_organizations() {
        $endpoint = '/books/v3/organizations';
        $response = $this->api_request($endpoint);

        if (is_wp_error($response)) {
            return [];
        }

        return $response['organizations'] ?? [];
    }

    /**
     * Sync affiliate data to Zoho CRM
     */
    public function sync_affiliate_to_crm($affiliate_data) {
        $contact_data = [
            'Last_Name' => $affiliate_data['name'] ?? 'Affiliate',
            'Email' => $affiliate_data['email'] ?? '',
            'Phone' => $affiliate_data['phone'] ?? '',
            'Lead_Source' => 'Affiliate Program',
            'Description' => 'Affiliate Partner - Code: ' . ($affiliate_data['code'] ?? ''),
            'Tag' => ['Affiliate Partner']
        ];

        $endpoint = '/crm/v2/Contacts';
        $response = $this->api_request($endpoint, 'POST', ['data' => [$contact_data]]);

        return !is_wp_error($response);
    }
}