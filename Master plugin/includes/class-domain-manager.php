<?php
/**
 * Domain Manager Class
 *
 * Handles domain authorization, verification, and management operations.
 * Works in conjunction with the admin interface for complete domain management.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Domain_Manager {

    /**
     * Domains table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'affcd_domains';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'affcd_authorised_domains';
        
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_affcd_get_domains', [$this, 'ajax_get_domains']);
        add_action('wp_ajax_affcd_add_domain', [$this, 'ajax_add_domain']);
        add_action('wp_ajax_affcd_update_domain', [$this, 'ajax_update_domain']);
        add_action('wp_ajax_affcd_delete_domain', [$this, 'ajax_delete_domain']);
        add_action('wp_ajax_affcd_verify_domain', [$this, 'ajax_verify_domain']);
        add_action('wp_ajax_affcd_toggle_domain_status', [$this, 'ajax_toggle_domain_status']);
        add_action('wp_ajax_affcd_bulk_domain_action', [$this, 'ajax_bulk_domain_action']);
        add_action('wp_ajax_affcd_get_domain_stats', [$this, 'ajax_get_domain_stats']);
        add_action('wp_ajax_affcd_export_domains', [$this, 'ajax_export_domains']);
        add_action('wp_ajax_affcd_generate_api_key', [$this, 'ajax_generate_api_key']);
        add_action('wp_ajax_affcd_test_domain_connection', [$this, 'ajax_test_domain_connection']);
    }

    /**
     * Initialse domain manager
     */
    public function init() {
        // Schedule daily domain verification
        if (!wp_next_scheduled('affcd_verify_domains_daily')) {
            wp_schedule_event(time(), 'daily', 'affcd_verify_domains_daily');
        }
        
        add_action('affcd_verify_domains_daily', [$this, 'verify_all_domains']);
    }

    /**
     * Add new domain
     *
     * @param array $data Domain data
     * @return int|WP_Error Domain ID or error
     */
    public function add_domain($data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['domain_url'])) {
            return new WP_Error('missing_domain_url', __('Domain URL is required', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Sanitize domain URL
        $domain_url = affcd_sanitize_domain($data['domain_url']);
        if (empty($domain_url)) {
            return new WP_Error('invalid_domain_url', __('Invalid domain URL format', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Ensure https protocol
        if (!preg_match('/^https?:\/\//', $domain_url)) {
            $domain_url = 'https://' . $domain_url;
        }
        
        // Check if domain already exists
        $existing = $this->get_domain_by_url($domain_url);
        if ($existing) {
            return new WP_Error('domain_exists', __('Domain already exists', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Generate API credentials
        $api_key = affcd_generate_api_key();
        $api_secret = affcd_hash_api_secret(affcd_generate_api_secret());
        $verification_token = affcd_generate_secure_token();
        
        // Prepare domain data
        $domain_data = [
            'domain_url' => $domain_url,
            'domain_name' => sanitize_text_field($data['domain_name'] ?? ''),
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'verification_token' => $verification_token,
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'verification_status' => 'unverified',
            'max_daily_requests' => absint($data['max_daily_requests'] ?? 10000),
            'rate_limit_per_minute' => absint($data['rate_limit_per_minute'] ?? 100),
            'rate_limit_per_hour' => absint($data['rate_limit_per_hour'] ?? 1000),
            'security_level' => sanitize_text_field($data['security_level'] ?? 'medium'),
            'require_https' => !empty($data['require_https']),
            'owner_email' => sanitize_email($data['owner_email'] ?? ''),
            'owner_name' => sanitize_text_field($data['owner_name'] ?? ''),
            'contact_email' => sanitize_email($data['contact_email'] ?? ''),
            'timezone' => sanitize_text_field($data['timezone'] ?? 'UTC'),
            'language' => sanitize_text_field($data['language'] ?? 'en'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => get_current_user_id()
        ];
        
        // Insert domain
        $result = $wpdb->insert(
            $this->table_name,
            $domain_data,
            [
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%d', '%d', '%d', '%s', '%d', '%s', '%s', 
                '%s', '%s', '%s', '%s', '%s', '%d'
            ]
        );
        
        if ($result === false) {
            return new WP_Error('database_error', __('Failed to add domain', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_id = $wpdb->insert_id;
        
        // Clear cache
        wp_cache_delete('all_domains', $this->cache_group);
        
        // Log activity
        affcd_log_activity('domain_added', [
            'domain_id' => $domain_id,
            'domain_url' => $domain_url,
            'status' => $domain_data['status']
        ]);
        
        // Trigger action
        do_action('affcd_domain_added', $domain_id, $domain_data);
        
        return $domain_id;
    }

    /**
     * Update domain
     *
     * @param int $domain_id Domain ID
     * @param array $data Update data
     * @return bool|WP_Error Success or error
     */
    public function update_domain($domain_id, $data) {
        global $wpdb;
        
        $domain_id = absint($domain_id);
        
        // Get existing domain
        $existing = $this->get_domain($domain_id);
        if (!$existing) {
            return new WP_Error('domain_not_found', __('Domain not found', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Prepare update data
        $update_data = [];
        $formats = [];
        
        if (isset($data['domain_name'])) {
            $update_data['domain_name'] = sanitize_text_field($data['domain_name']);
            $formats[] = '%s';
        }
        
        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive', 'suspended', 'pending'])) {
            $update_data['status'] = $data['status'];
            $formats[] = '%s';
        }
        
        if (isset($data['max_daily_requests'])) {
            $update_data['max_daily_requests'] = absint($data['max_daily_requests']);
            $formats[] = '%d';
        }
        
        if (isset($data['rate_limit_per_minute'])) {
            $update_data['rate_limit_per_minute'] = absint($data['rate_limit_per_minute']);
            $formats[] = '%d';
        }
        
        if (isset($data['rate_limit_per_hour'])) {
            $update_data['rate_limit_per_hour'] = absint($data['rate_limit_per_hour']);
            $formats[] = '%d';
        }
        
        if (isset($data['security_level'])) {
            $update_data['security_level'] = sanitize_text_field($data['security_level']);
            $formats[] = '%s';
        }
        
        if (isset($data['require_https'])) {
            $update_data['require_https'] = !empty($data['require_https']) ? 1 : 0;
            $formats[] = '%d';
        }
        
        if (isset($data['owner_email'])) {
            $update_data['owner_email'] = sanitize_email($data['owner_email']);
            $formats[] = '%s';
        }
        
        if (isset($data['owner_name'])) {
            $update_data['owner_name'] = sanitize_text_field($data['owner_name']);
            $formats[] = '%s';
        }
        
        if (isset($data['contact_email'])) {
            $update_data['contact_email'] = sanitize_email($data['contact_email']);
            $formats[] = '%s';
        }
        
        if (isset($data['notes'])) {
            $update_data['notes'] = sanitize_textarea_field($data['notes']);
            $formats[] = '%s';
        }
        
        if (isset($data['webhook_url'])) {
            $update_data['webhook_url'] = esc_url_raw($data['webhook_url']);
            $formats[] = '%s';
        }
        
        if (isset($data['webhook_secret'])) {
            $update_data['webhook_secret'] = sanitize_text_field($data['webhook_secret']);
            $formats[] = '%s';
        }
        
        if (isset($data['webhook_events']) && is_array($data['webhook_events'])) {
            $update_data['webhook_events'] = wp_json_encode($data['webhook_events']);
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_changes', __('No valid changes provided', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Update timestamp
        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';
        
        // Update domain
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $domain_id],
            $formats,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('database_error', __('Failed to update domain', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Clear cache
        wp_cache_delete('domain_' . $domain_id, $this->cache_group);
        wp_cache_delete('all_domains', $this->cache_group);
        
        // Log activity
        affcd_log_activity('domain_updated', [
            'domain_id' => $domain_id,
            'changes' => array_keys($update_data)
        ]);
        
        // Trigger action
        do_action('affcd_domain_updated', $domain_id, $update_data, $existing);
        
        return true;
    }

    /**
     * Delete domain
     *
     * @param int $domain_id Domain ID
     * @return bool|WP_Error Success or error
     */
    public function delete_domain($domain_id) {
        global $wpdb;
        
        $domain_id = absint($domain_id);
        
        // Get existing domain
        $existing = $this->get_domain($domain_id);
        if (!$existing) {
            return new WP_Error('domain_not_found', __('Domain not found', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Delete domain
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $domain_id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('database_error', __('Failed to delete domain', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        // Clear cache
        wp_cache_delete('domain_' . $domain_id, $this->cache_group);
        wp_cache_delete('all_domains', $this->cache_group);
        
        // Log activity
        affcd_log_activity('domain_deleted', [
            'domain_id' => $domain_id,
            'domain_url' => $existing->domain_url
        ]);
        
        // Trigger action
        do_action('affcd_domain_deleted', $domain_id, $existing);
        
        return true;
    }

    /**
     * Get domain by ID
     *
     * @param int $domain_id Domain ID
     * @return object|null Domain object or null
     */
    public function get_domain($domain_id) {
        global $wpdb;
        
        $domain_id = absint($domain_id);
        
        // Check cache
        $cache_key = 'domain_' . $domain_id;
        $domain = wp_cache_get($cache_key, $this->cache_group);
        
        if ($domain === false) {
            $domain = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $domain_id
            ));
            
            if ($domain) {
                wp_cache_set($cache_key, $domain, $this->cache_group, 3600);
            }
        }
        
        return $domain;
    }

    /**
     * Get domain by URL
     *
     * @param string $domain_url Domain URL
     * @return object|null Domain object or null
     */
    public function get_domain_by_url($domain_url) {
        global $wpdb;
        
        $domain_url = esc_url_raw($domain_url);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE domain_url = %s",
            $domain_url
        ));
    }

    /**
     * Get domain by API key
     *
     * @param string $api_key API key
     * @return object|null Domain object or null
     */
    public function get_domain_by_api_key($api_key) {
        global $wpdb;
        
        $api_key = sanitize_text_field($api_key);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE api_key = %s AND status = 'active'",
            $api_key
        ));
    }

    /**
     * Get all domains
     *
     * @param array $args Query arguments
     * @return array Domains list
     */
    public function get_domains($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => '',
            'verification_status' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['verification_status'])) {
            $where_conditions[] = 'verification_status = %s';
            $where_values[] = $args['verification_status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(domain_url LIKE %s OR domain_name LIKE %s OR owner_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build ORDER BY clause
        $allowed_orderby = ['id', 'domain_url', 'domain_name', 'status', 'verification_status', 'created_at', 'last_activity_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Build query
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order}";
        
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }

    /**
     * Get domain statistics
     *
     * @return array Statistics
     */
    public function get_domain_statistics() {
        global $wpdb;
        
        $cache_key = 'domain_statistics';
        $stats = wp_cache_get($cache_key, $this->cache_group);
        
        if ($stats === false) {
            $stats = [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'suspended' => 0,
                'pending' => 0,
                'verified' => 0,
                'unverified' => 0,
                'failed' => 0
            ];
            
            // Get status counts
            $status_counts = $wpdb->get_results(
                "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
                OBJECT_K
            );
            
            foreach ($status_counts as $status => $data) {
                $stats[$status] = intval($data->count);
                $stats['total'] += intval($data->count);
            }
            
            // Get verification counts
            $verification_counts = $wpdb->get_results(
                "SELECT verification_status, COUNT(*) as count FROM {$this->table_name} GROUP BY verification_status",
                OBJECT_K
            );
            
            foreach ($verification_counts as $status => $data) {
                $stats[$status] = intval($data->count);
            }
            
            wp_cache_set($cache_key, $stats, $this->cache_group, 300); // 5 minutes
        }
        
        return $stats;
    }

    /**
     * Verify domain connection
     *
     * @param int $domain_id Domain ID
     * @return array Verification result
     */
    public function verify_domain($domain_id) {
        global $wpdb;
        
        $domain = $this->get_domain($domain_id);
        if (!$domain) {
            return [
                'success' => false,
                'message' => __('Domain not found', 'affiliatewp-cross-domain-plugin-suite')
            ];
        }
        
        // Test connection to WordPress REST API
        $test_url = rtrim($domain->domain_url, '/') . '/wp-json/wp/v2/';
        $start_time = microtime(true);
        
        $response = wp_remote_get($test_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION
            ]
        ]);
        
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        $success = false;
        $message = '';
        
        if (is_wp_error($response)) {
            $message = $response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                $success = true;
                $message = sprintf(__('Connection verified successfully (%sms)', 'affiliatewp-cross-domain-plugin-suite'), $response_time);
            } else {
                $message = sprintf(__('HTTP %d response received', 'affiliatewp-cross-domain-plugin-suite'), $status_code);
            }
        }
        
        // Update domain verification status
        $update_data = [
            'last_verified_at' => current_time('mysql'),
            'last_activity_at' => current_time('mysql')
        ];
        
        if ($success) {
            $update_data['verification_status'] = 'verified';
            $update_data['verification_failures'] = 0;
        } else {
            $update_data['verification_status'] = 'failed';
            $update_data['verification_failures'] = intval($domain->verification_failures) + 1;
            
            // Suspend domain after 5 consecutive failures
            if ($update_data['verification_failures'] >= 5) {
                $update_data['status'] = 'suspended';
                $update_data['suspended_at'] = current_time('mysql');
                $update_data['suspended_reason'] = __('Multiple verification failures', 'affiliatewp-cross-domain-plugin-suite');
            }
        }
        
        $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $domain_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
        
        // Clear cache
        wp_cache_delete('domain_' . $domain_id, $this->cache_group);
        
        // Log verification attempt
        affcd_log_activity('domain_verification', [
            'domain_id' => $domain_id,
            'domain_url' => $domain->domain_url,
            'success' => $success,
            'response_time' => $response_time,
            'failures' => $update_data['verification_failures']
        ]);
        
        return [
            'success' => $success,
            'message' => $message,
            'response_time' => $response_time,
            'failures' => $update_data['verification_failures']
        ];
    }

    /**
     * Verify all domains
     */
    public function verify_all_domains() {
        $domains = $this->get_domains(['status' => 'active', 'limit' => 0]);
        
        foreach ($domains as $domain) {
            $this->verify_domain($domain->id);
            // Add small delay to avoid overwhelming servers
            usleep(500000); // 0.5 seconds
        }
    }

    /**
     * Test domain connection
     *
     * @param string $domain_url Domain URL
     * @return array Test result
     */
    public function test_domain_connection($domain_url) {
        $test_url = rtrim($domain_url, '/') . '/wp-json/wp/v2/';
        $start_time = microtime(true);
        
        $response = wp_remote_get($test_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'AffiliateWP-Cross-Domain/' . AFFCD_VERSION
            ]
        ]);
        
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'response_time' => $response_time
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $success = $status_code === 200;
        
        return [
            'success' => $success,
            'message' => $success 
                ? sprintf(__('Connection successful (%sms)', 'affiliatewp-cross-domain-plugin-suite'), $response_time)
                : sprintf(__('HTTP %d response', 'affiliatewp-cross-domain-plugin-suite'), $status_code),
            'response_time' => $response_time,
            'status_code' => $status_code
        ];
    }

    /**
     * AJAX: Get domains
     */
    public function ajax_get_domains() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $args = [
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'limit' => absint($_POST['length'] ?? 25),
            'offset' => absint($_POST['start'] ?? 0)
        ];
        
        $domains = $this->get_domains($args);
        $total = $this->get_total_domains($args);
        
        $data = [];
        foreach ($domains as $domain) {
            $data[] = [
                'checkbox' => '<input type="checkbox" name="domain_ids[]" value="' . $domain->id . '">',
                'domain_url' => esc_html($domain->domain_url),
                'domain_name' => esc_html($domain->domain_name ?: __('N/A', 'affiliatewp-cross-domain-plugin-suite')),
                'status' => $this->get_status_badge($domain->status),
                'verification_status' => $this->get_verification_badge($domain->verification_status),
                'last_activity' => $domain->last_activity_at ? affcd_time_ago($domain->last_activity_at) : __('Never', 'affiliatewp-cross-domain-plugin-suite'),
                'actions' => $this->get_domain_actions($domain)
            ];
        }
        
        wp_send_json_success([
            'data' => $data,
            'recordsTotal' => $total,
            'recordsFiltered' => $total
        ]);
    }

    /**
     * AJAX: Add domain
     */
    public function ajax_add_domain() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_data = [
            'domain_url' => sanitize_text_field($_POST['domain_url'] ?? ''),
            'domain_name' => sanitize_text_field($_POST['domain_name'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'owner_email' => sanitize_email($_POST['owner_email'] ?? ''),
            'owner_name' => sanitize_text_field($_POST['owner_name'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        ];
        
        $result = $this->add_domain($domain_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => __('Domain added successfully', 'affiliatewp-cross-domain-plugin-suite'),
            'domain_id' => $result
        ]);
    }

    /**
     * AJAX: Update domain
     */
    public function ajax_update_domain() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_id = absint($_POST['domain_id'] ?? 0);
        $domain_data = [
            'domain_name' => sanitize_text_field($_POST['domain_name'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'owner_email' => sanitize_email($_POST['owner_email'] ?? ''),
            'owner_name' => sanitize_text_field($_POST['owner_name'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'webhook_url' => esc_url_raw($_POST['webhook_url'] ?? ''),
            'webhook_secret' => sanitize_text_field($_POST['webhook_secret'] ?? '')
        ];
        
        $result = $this->update_domain($domain_id, $domain_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => __('Domain updated successfully', 'affiliatewp-cross-domain-plugin-suite')
        ]);
    }

    /**
     * AJAX: Delete domain
     */
    public function ajax_delete_domain() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_id = absint($_POST['domain_id'] ?? 0);
        $result = $this->delete_domain($domain_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => __('Domain deleted successfully', 'affiliatewp-cross-domain-plugin-suite')
        ]);
    }

    /**
     * AJAX: Verify domain
     */
    public function ajax_verify_domain() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_id = absint($_POST['domain_id'] ?? 0);
        $result = $this->verify_domain($domain_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Toggle domain status
     */
    public function ajax_toggle_domain_status() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_id = absint($_POST['domain_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $result = $this->update_domain($domain_id, ['status' => $status]);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => sprintf(__('Domain status changed to %s', 'affiliatewp-cross-domain-plugin-suite'), $status)
        ]);
    }

    /**
     * AJAX: Get domain statistics
     */
    public function ajax_get_domain_stats() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $stats = $this->get_domain_statistics();
        wp_send_json_success($stats);
    }

    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $api_key = affcd_generate_api_key();
        
        wp_send_json_success([
            'api_key' => $api_key,
            'message' => __('API key generated successfully', 'affiliatewp-cross-domain-plugin-suite')
        ]);
    }

    /**
     * AJAX: Test domain connection
     */
    public function ajax_test_domain_connection() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }
        
        $domain_id = absint($_POST['domain_id'] ?? 0);
        if ($domain_id) {
            $result = $this->verify_domain($domain_id);
        } else {
            $domain_url = esc_url_raw($_POST['domain_url'] ?? '');
            $result = $this->test_domain_connection($domain_url);
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get status badge HTML
     *
     * @param string $status Status
     * @return string Badge HTML
     */
    private function get_status_badge($status) {
        $badges = [
            'active' => '<span class="status-badge status-active">' . __('Active', 'affiliatewp-cross-domain-plugin-suite') . '</span>',
            'inactive' => '<span class="status-badge status-inactive">' . __('Inactive', 'affiliatewp-cross-domain-plugin-suite') . '</span>',
            'suspended' => '<span class="status-badge status-suspended">' . __('Suspended', 'affiliatewp-cross-domain-plugin-suite') . '</span>',
            'pending' => '<span class="status-badge status-pending">' . __('Pending', 'affiliatewp-cross-domain-plugin-suite') . '</span>'
        ];
        
        return $badges[$status] ?? $status;
    }

    /**
     * Get verification badge HTML
     *
     * @param string $status Verification status
     * @return string Badge HTML
     */
    private function get_verification_badge($status) {
        $badges = [
            'verified' => '<span class="verification-badge verification-verified">' . __('Verified', 'affiliatewp-cross-domain-plugin-suite') . '</span>',
            'unverified' => '<span class="verification-badge verification-unverified">' . __('Unverified', 'affiliatewp-cross-domain-plugin-suite') . '</span>',
            'failed' => '<span class="verification-badge verification-failed">' . __('Failed', 'affiliatewp-cross-domain-plugin-suite') . '</span>'
        ];
        
        return $badges[$status] ?? $status;
    }

    /**
     * Get domain actions HTML
     *
     * @param object $domain Domain object
     * @return string Actions HTML
     */
    private function get_domain_actions($domain) {
        $actions = [];
        
        $actions[] = '<button type="button" class="button button-small test-domain-connection" data-domain-id="' . $domain->id . '">' . __('Test', 'affiliatewp-cross-domain-plugin-suite') . '</button>';
        
        $actions[] = '<button type="button" class="button button-small verify-domain" data-domain-id="' . $domain->id . '">' . __('Verify', 'affiliatewp-cross-domain-plugin-suite') . '</button>';
        
        $actions[] = '<button type="button" class="button button-small edit-domain" data-domain-id="' . $domain->id . '">' . __('Edit', 'affiliatewp-cross-domain-plugin-suite') . '</button>';
        
        $actions[] = '<button type="button" class="button button-small delete-domain" data-domain-id="' . $domain->id . '" data-domain-name="' . esc_attr($domain->domain_url) . '">' . __('Delete', 'affiliatewp-cross-domain-plugin-suite') . '</button>';
        
        return implode(' ', $actions);
    }

    /**
     * Get total domains count
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    private function get_total_domains($args = []) {
        global $wpdb;
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['verification_status'])) {
            $where_conditions[] = 'verification_status = %s';
            $where_values[] = $args['verification_status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(domain_url LIKE %s OR domain_name LIKE %s OR owner_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return intval($wpdb->get_var($query));
    }
}