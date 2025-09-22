<?php
/**
 * Vanity Code Manager for Affiliate Cross Domain System
 * 
 * Plugin: Affiliate Cross Domain System (Master)
 * File: /wp-content/plugins/affiliate-cross-domain-system/includes/class-vanity-code-manager.php
 * 
 * Handles creation, validation, and management of vanity affiliate codes
 * with enhanced security and rate limiting.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Vanity_Code_Manager {

    private $table_name;
    private $usage_table;
    private $security_manager;
    private $cache_prefix = 'affcd_vanity_';
    private $cache_expiration = 3600; // 1 hour

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'affcd_vanity_codes';
        $this->usage_table = $wpdb->prefix . 'affcd_usage_tracking';
        $this->security_manager = new AFFCD_Security_Manager();
        
        // Initialise hooks
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_affcd_create_vanity_code', [$this, 'ajax_create_vanity_code']);
        add_action('wp_ajax_affcd_update_vanity_code', [$this, 'ajax_update_vanity_code']);
        add_action('wp_ajax_affcd_delete_vanity_code', [$this, 'ajax_delete_vanity_code']);
        add_action('wp_ajax_affcd_bulk_vanity_operations', [$this, 'ajax_bulk_operations']);
    }

    /**
     * Initialse the manager
     */
    public function init() {
        $this->maybe_create_tables();
        $this->schedule_cleanup_tasks();
    }

    /**
     * Create database tables if they don't exist
     */
    private function maybe_create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Vanity codes table
        $sql_vanity = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vanity_code varchar(100) NOT NULL,
            affiliate_id bigint(20) unsigned NOT NULL,
            affiliate_code varchar(255) NOT NULL,
            description text,
            usage_count bigint(20) unsigned DEFAULT 0,
            conversion_count bigint(20) unsigned DEFAULT 0,
            revenue_generated decimal(10,2) DEFAULT 0.00,
            status enum('active','inactive','expired','suspended') DEFAULT 'active',
            expires_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY vanity_code (vanity_code),
            KEY affiliate_id (affiliate_id),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Usage tracking table
        $sql_usage = "CREATE TABLE IF NOT EXISTS {$this->usage_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vanity_code_id bigint(20) unsigned NOT NULL,
            domain varchar(255) NOT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text,
            referrer varchar(500),
            converted tinyint(1) DEFAULT 0,
            conversion_value decimal(10,2) DEFAULT 0.00,
            session_id varchar(100),
            tracked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY vanity_code_id (vanity_code_id),
            KEY domain (domain),
            KEY tracked_at (tracked_at),
            KEY converted (converted),
            FOREIGN KEY (vanity_code_id) REFERENCES {$this->table_name}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_vanity);
        dbDelta($sql_usage);
    }

    /**
     * Create a new vanity code
     */
    public function create_vanity_code($data) {
        global $wpdb;
        
        // Validate input data
        $validation = $this->validate_vanity_code_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Security checks
        if (!$this->security_manager->check_rate_limit('create_vanity', get_current_user_id())) {
            return new WP_Error('rate_limit', __('Rate limit exceeded. Please try again later.', 'affiliate-cross-domain'));
        }

        // Sanitize and prepare data
        $vanity_code = sanitize_text_field($data['vanity_code']);
        $affiliate_id = absint($data['affiliate_id']);
        $affiliate_code = sanitize_text_field($data['affiliate_code']);
        $description = sanitize_textarea_field($data['description'] ?? '');
        $expires_at = !empty($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null;

        // Check if vanity code already exists
        if ($this->vanity_code_exists($vanity_code)) {
            return new WP_Error('duplicate_code', __('Vanity code already exists.', 'affiliate-cross-domain'));
        }

        // Verify affiliate exists
        if (!$this->verify_affiliate_exists($affiliate_id)) {
            return new WP_Error('invalid_affiliate', __('Invalid affiliate ID.', 'affiliate-cross-domain'));
        }

        // Insert new vanity code
        $result = $wpdb->insert(
            $this->table_name,
            [
                'vanity_code' => $vanity_code,
                'affiliate_id' => $affiliate_id,
                'affiliate_code' => $affiliate_code,
                'description' => $description,
                'expires_at' => $expires_at,
                'created_by' => get_current_user_id(),
                'status' => 'active'
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create vanity code.', 'affiliate-cross-domain'));
        }

        $vanity_id = $wpdb->insert_id;
        
        // Clear relevant caches
        $this->clear_vanity_code_cache($vanity_code);
        
        // Log the creation
        $this->security_manager->log_activity('vanity_code_created', [
            'vanity_id' => $vanity_id,
            'vanity_code' => $vanity_code,
            'affiliate_id' => $affiliate_id
        ]);

        return $vanity_id;
    }

    /**
     * Update an existing vanity code
     */
    public function update_vanity_code($vanity_id, $data) {
        global $wpdb;
        
        $vanity_id = absint($vanity_id);
        
        // Check if vanity code exists
        $existing = $this->get_vanity_code($vanity_id);
        if (!$existing) {
            return new WP_Error('not_found', __('Vanity code not found.', 'affiliate-cross-domain'));
        }

        // Security check
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        // Validate updated data
        $validation = $this->validate_vanity_code_data($data, $vanity_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Prepare update data
        $update_data = [];
        $update_formats = [];

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $update_formats[] = '%s';
        }

        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive', 'expired', 'suspended'])) {
            $update_data['status'] = $data['status'];
            $update_formats[] = '%s';
        }

        if (isset($data['expires_at'])) {
            $update_data['expires_at'] = !empty($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null;
            $update_formats[] = '%s';
        }

        if (empty($update_data)) {
            return new WP_Error('no_changes', __('No valid changes provided.', 'affiliate-cross-domain'));
        }

        // Perform update
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $vanity_id],
            $update_formats,
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update vanity code.', 'affiliate-cross-domain'));
        }

        // Clear caches
        $this->clear_vanity_code_cache($existing->vanity_code);
        
        // Log the update
        $this->security_manager->log_activity('vanity_code_updated', [
            'vanity_id' => $vanity_id,
            'changes' => array_keys($update_data)
        ]);

        return true;
    }

    /**
     * Delete a vanity code
     */
    public function delete_vanity_code($vanity_id) {
        global $wpdb;
        
        $vanity_id = absint($vanity_id);
        
        // Check if vanity code exists
        $existing = $this->get_vanity_code($vanity_id);
        if (!$existing) {
            return new WP_Error('not_found', __('Vanity code not found.', 'affiliate-cross-domain'));
        }

        // Security check
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        // Delete the vanity code (usage tracking will be deleted by foreign key constraint)
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $vanity_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete vanity code.', 'affiliate-cross-domain'));
        }

        // Clear caches
        $this->clear_vanity_code_cache($existing->vanity_code);
        
        // Log the deletion
        $this->security_manager->log_activity('vanity_code_deleted', [
            'vanity_id' => $vanity_id,
            'vanity_code' => $existing->vanity_code
        ]);

        return true;
    }

    /**
     * Get vanity code by ID or code
     */
    public function get_vanity_code($identifier) {
        global $wpdb;
        
        // Check cache first
        $cache_key = $this->cache_prefix . md5($identifier);
        $cached = wp_cache_get($cache_key, 'affcd_vanity_codes');
        if ($cached !== false) {
            return $cached;
        }

        // Determine if identifier is ID or code
        if (is_numeric($identifier)) {
            $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $identifier);
        } else {
            $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE vanity_code = %s", $identifier);
        }

        $result = $wpdb->get_row($sql);
        
        // Cache the result
        if ($result) {
            wp_cache_set($cache_key, $result, 'affcd_vanity_codes', $this->cache_expiration);
        }

        return $result;
    }

    /**
     * Validate vanity code
     */
    public function validate_vanity_code($vanity_code, $domain = '') {
        // Get vanity code record
        $code_record = $this->get_vanity_code($vanity_code);
        
        if (!$code_record) {
            return [
                'valid' => false,
                'error' => 'invalid_code',
                'message' => __('Invalid vanity code.', 'affiliate-cross-domain')
            ];
        }

        // Check if code is active
        if ($code_record->status !== 'active') {
            return [
                'valid' => false,
                'error' => 'inactive_code',
                'message' => __('Vanity code is not active.', 'affiliate-cross-domain')
            ];
        }

        // Check expiration
        if ($code_record->expires_at && strtotime($code_record->expires_at) < time()) {
            return [
                'valid' => false,
                'error' => 'expired_code',
                'message' => __('Vanity code has expired.', 'affiliate-cross-domain')
            ];
        }

        // Verify domain is authorised (if provided)
        if ($domain && !$this->security_manager->is_domain_authorised($domain)) {
            return [
                'valid' => false,
                'error' => 'unauthorised_domain',
                'message' => __('Domain not authorised.', 'affiliate-cross-domain')
            ];
        }

        // Track usage
        $this->track_usage($code_record->id, $domain);

        return [
            'valid' => true,
            'affiliate_id' => $code_record->affiliate_id,
            'affiliate_code' => $code_record->affiliate_code,
            'vanity_code' => $code_record->vanity_code
        ];
    }

    /**
     * Track vanity code usage
     */
    private function track_usage($vanity_code_id, $domain = '') {
        global $wpdb;
        
        // Insert usage record
        $wpdb->insert(
            $this->usage_table,
            [
                'vanity_code_id' => $vanity_code_id,
                'domain' => sanitize_text_field($domain),
                'user_ip' => $this->security_manager->get_client_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referrer' => sanitize_text_field($_SERVER['HTTP_REFERER'] ?? ''),
                'session_id' => session_id()
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        // Update usage count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET usage_count = usage_count + 1 WHERE id = %d",
            $vanity_code_id
        ));

        // Clear cache for this vanity code
        $vanity_code = $this->get_vanity_code($vanity_code_id);
        if ($vanity_code) {
            $this->clear_vanity_code_cache($vanity_code->vanity_code);
        }
    }

    /**
     * Get vanity codes list with pagination and filtering
     */
    public function get_vanity_codes_list($args = []) {
        global $wpdb;
        
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'status' => '',
            'search' => '',
            'affiliate_id' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(vanity_code LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($args['affiliate_id'])) {
            $where_conditions[] = 'affiliate_id = %d';
            $where_values[] = $args['affiliate_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Build ORDER BY clause
        $allowed_orderby = ['id', 'vanity_code', 'affiliate_id', 'usage_count', 'conversion_count', 'created_at', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        $total_items = $wpdb->get_var($count_sql);
        
        // Get items
        $items_sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $items_values = array_merge($where_values, [$args['per_page'], $offset]);
        $items_sql = $wpdb->prepare($items_sql, $items_values);
        $items = $wpdb->get_results($items_sql);
        
        return [
            'items' => $items,
            'total_items' => $total_items,
            'per_page' => $args['per_page'],
            'total_pages' => ceil($total_items / $args['per_page']),
            'current_page' => $args['page']
        ];
    }

    /**
     * Bulk operations for vanity codes
     */
    public function bulk_operations($action, $vanity_ids) {
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $vanity_ids = array_map('absint', (array) $vanity_ids);
        $results = [];
        
        foreach ($vanity_ids as $vanity_id) {
            switch ($action) {
                case 'activate':
                    $results[$vanity_id] = $this->update_vanity_code($vanity_id, ['status' => 'active']);
                    break;
                case 'deactivate':
                    $results[$vanity_id] = $this->update_vanity_code($vanity_id, ['status' => 'inactive']);
                    break;
                case 'delete':
                    $results[$vanity_id] = $this->delete_vanity_code($vanity_id);
                    break;
                default:
                    $results[$vanity_id] = new WP_Error('invalid_action', __('Invalid bulk action.', 'affiliate-cross-domain'));
            }
        }
        
        return $results;
    }

    /**
     * Validate vanity code data
     */
    private function validate_vanity_code_data($data, $exclude_id = 0) {
        $errors = [];
        
        // Validate vanity code format
        if (empty($data['vanity_code'])) {
            $errors[] = __('Vanity code is required.', 'affiliate-cross-domain');
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['vanity_code'])) {
            $errors[] = __('Vanity code can only contain letters, numbers, hyphens, and underscores.', 'affiliate-cross-domain');
        } elseif (strlen($data['vanity_code']) < 3 || strlen($data['vanity_code']) > 50) {
            $errors[] = __('Vanity code must be between 3 and 50 characters.', 'affiliate-cross-domain');
        }
        
        // Validate affiliate ID
        if (empty($data['affiliate_id']) || !is_numeric($data['affiliate_id'])) {
            $errors[] = __('Valid affiliate ID is required.', 'affiliate-cross-domain');
        }
        
        // Validate affiliate code
        if (empty($data['affiliate_code'])) {
            $errors[] = __('Affiliate code is required.', 'affiliate-cross-domain');
        }
        
        // Validate expiration date
        if (!empty($data['expires_at']) && strtotime($data['expires_at']) === false) {
            $errors[] = __('Invalid expiration date format.', 'affiliate-cross-domain');
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }
        
        return true;
    }

    /**
     * Check if vanity code exists
     */
    private function vanity_code_exists($vanity_code, $exclude_id = 0) {
        global $wpdb;
        
        $sql = "SELECT id FROM {$this->table_name} WHERE vanity_code = %s";
        $params = [$vanity_code];
        
        if ($exclude_id > 0) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return $wpdb->get_var($wpdb->prepare($sql, $params)) !== null;
    }

    /**
     * Verify affiliate exists
     */
    private function verify_affiliate_exists($affiliate_id) {
        return affwp_get_affiliate($affiliate_id) !== false;
    }

    /**
     * Clear vanity code cache
     */
    private function clear_vanity_code_cache($vanity_code) {
        wp_cache_delete($this->cache_prefix . md5($vanity_code), 'affcd_vanity_codes');
        wp_cache_delete($this->cache_prefix . md5($vanity_code) . '_stats', 'affcd_vanity_codes');
    }

    /**
     * Schedule cleanup tasks
     */
    private function schedule_cleanup_tasks() {
        if (!wp_next_scheduled('affcd_cleanup_expired_codes')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_expired_codes');
        }
        
        add_action('affcd_cleanup_expired_codes', [$this, 'cleanup_expired_codes']);
    }

    /**
     * Cleanup expired vanity codes
     */
    public function cleanup_expired_codes() {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            ['status' => 'expired'],
            ['expires_at <' => current_time('mysql'), 'status' => 'active'],
            ['%s'],
            ['%s', '%s']
        );
    }

    /**
     * AJAX: Create vanity code
     */
    public function ajax_create_vanity_code() {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }
        
        $result = $this->create_vanity_code($_POST);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => __('Vanity code created successfully.', 'affiliate-cross-domain'),
                'vanity_id' => $result
            ]);
        }
    }

    /**
     * AJAX: Update vanity code
     */
    public function ajax_update_vanity_code() {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        
        $vanity_id = absint($_POST['vanity_id'] ?? 0);
        $result = $this->update_vanity_code($vanity_id, $_POST);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => __('Vanity code updated successfully.', 'affiliate-cross-domain')
            ]);
        }
    }

    /**
     * AJAX: Delete vanity code
     */
    public function ajax_delete_vanity_code() {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        
        $vanity_id = absint($_POST['vanity_id'] ?? 0);
        $result = $this->delete_vanity_code($vanity_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => __('Vanity code deleted successfully.', 'affiliate-cross-domain')
            ]);
        }
    }

    /**
     * AJAX: Bulk operations
     */
    public function ajax_bulk_operations() {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $vanity_ids = array_map('absint', $_POST['vanity_ids'] ?? []);
        
        $results = $this->bulk_operations($action, $vanity_ids);
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($results as $result) {
            if (is_wp_error($result)) {
                $error_count++;
            } else {
                $success_count++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('%d items processed successfully, %d errors.', 'affiliate-cross-domain'),
                $success_count,
                $error_count
            ),
            'results' => $results
        ]);
    }
}