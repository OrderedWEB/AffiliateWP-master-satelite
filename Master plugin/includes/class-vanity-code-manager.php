<?php
/**
 * Vanity Code Manager for Affiliate Cross Domain System
 *
 * Handles creation, validation, tracking, and management of vanity affiliate codes
 * with enhanced security, caching, and usage monitoring.
 *
 * @package AffiliateWP_Cross_Domain_Full
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Vanity_Code_Manager
{
    /**
     * @var string Vanity codes table name.
     */
    private $table_name;

    /**
     * @var string Usage tracking table name.
     */
    private $usage_table;

    /**
     * @var AFFCD_Security_Manager
     */
    private $security_manager;

    /**
     * @var string Cache key prefix.
     */
    private $cache_prefix = 'affcd_vanity_';

    /**
     * @var int Cache expiration in seconds.
     */
    private $cache_expiration = 3600; // 1 hour

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name     = $wpdb->prefix . 'affcd_vanity_codes';
        $this->usage_table    = $wpdb->prefix . 'affcd_usage_tracking';
        $this->security_manager = new AFFCD_Security_Manager();

        add_action('init', [$this, 'init']);
        add_action('wp_ajax_affcd_create_vanity_code', [$this, 'ajax_create_vanity_code']);
        add_action('wp_ajax_affcd_update_vanity_code', [$this, 'ajax_update_vanity_code']);
        add_action('wp_ajax_affcd_delete_vanity_code', [$this, 'ajax_delete_vanity_code']);
        add_action('wp_ajax_affcd_bulk_vanity_operations', [$this, 'ajax_bulk_operations']);
    }

    /**
     * Initialize manager: create tables, schedule cleanups.
     */
    public function init()
    {
        $this->maybe_create_tables();
        $this->schedule_cleanup_tasks();
    }

    /**
     * Create database tables if missing.
     */
    private function maybe_create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_vanity);
        dbDelta($sql_usage);
    }

    /**
     * Create a vanity code.
     *
     * @param array $data
     * @return int|WP_Error Insert ID or error.
     */
    public function create_vanity_code($data)
    {
        global $wpdb;

        $validation = $this->validate_vanity_code_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!$this->security_manager->check_rate_limit('create_vanity', get_current_user_id())) {
            return new WP_Error('rate_limit', __('Rate limit exceeded. Please try again later.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $vanity_code   = sanitize_text_field($data['vanity_code']);
        $affiliate_id  = absint($data['affiliate_id']);
        $affiliate_code = sanitize_text_field($data['affiliate_code']);
        $description   = sanitize_textarea_field($data['description'] ?? '');
        $expires_at    = !empty($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null;

        if ($this->vanity_code_exists($vanity_code)) {
            return new WP_Error('duplicate_code', __('Vanity code already exists.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        if (!$this->verify_affiliate_exists($affiliate_id)) {
            return new WP_Error('invalid_affiliate', __('Invalid affiliate ID.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'vanity_code'   => $vanity_code,
                'affiliate_id'  => $affiliate_id,
                'affiliate_code'=> $affiliate_code,
                'description'   => $description,
                'expires_at'    => $expires_at,
                'created_by'    => get_current_user_id(),
                'status'        => 'active',
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create vanity code.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $vanity_id = $wpdb->insert_id;
        $this->clear_vanity_code_cache($vanity_code);

        $this->security_manager->log_activity('vanity_code_created', [
            'vanity_id'   => $vanity_id,
            'vanity_code' => $vanity_code,
            'affiliate_id'=> $affiliate_id,
        ]);

        return $vanity_id;
    }

    /**
     * Update vanity code.
     *
     * @param int $vanity_id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update_vanity_code($vanity_id, $data)
    {
        global $wpdb;
        $vanity_id = absint($vanity_id);

        $existing = $this->get_vanity_code($vanity_id);
        if (!$existing) {
            return new WP_Error('not_found', __('Vanity code not found.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $validation = $this->validate_vanity_code_data($data, $vanity_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $update_data   = [];
        $update_format = [];

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $update_format[] = '%s';
        }

        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive', 'expired', 'suspended'], true)) {
            $update_data['status'] = $data['status'];
            $update_format[] = '%s';
        }

        if (isset($data['expires_at'])) {
            $update_data['expires_at'] = !empty($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null;
            $update_format[] = '%s';
        }

        if (empty($update_data)) {
            return new WP_Error('no_changes', __('No valid changes provided.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $vanity_id],
            $update_format,
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update vanity code.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $this->clear_vanity_code_cache($existing->vanity_code);

        $this->security_manager->log_activity('vanity_code_updated', [
            'vanity_id' => $vanity_id,
            'changes'   => array_keys($update_data),
        ]);

        return true;
    }

    /**
     * Delete vanity code.
     *
     * @param int $vanity_id
     * @return bool|WP_Error
     */
    public function delete_vanity_code($vanity_id)
    {
        global $wpdb;
        $vanity_id = absint($vanity_id);

        $existing = $this->get_vanity_code($vanity_id);
        if (!$existing) {
            return new WP_Error('not_found', __('Vanity code not found.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $vanity_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete vanity code.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $this->clear_vanity_code_cache($existing->vanity_code);

        $this->security_manager->log_activity('vanity_code_deleted', [
            'vanity_id'   => $vanity_id,
            'vanity_code' => $existing->vanity_code,
        ]);

        return true;
    }

    /**
     * Get vanity code by ID or code string.
     *
     * @param int|string $identifier
     * @return object|null
     */
    public function get_vanity_code($identifier)
    {
        global $wpdb;
        $cache_key = $this->cache_prefix . md5($identifier);

        $cached = wp_cache_get($cache_key, 'affcd_vanity_codes');
        if ($cached !== false) {
            return $cached;
        }

        if (is_numeric($identifier)) {
            $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $identifier);
        } else {
            $sql = $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE vanity_code = %s", $identifier);
        }

        $result = $wpdb->get_row($sql);
        if ($result) {
            wp_cache_set($cache_key, $result, 'affcd_vanity_codes', $this->cache_expiration);
        }

        return $result;
    }

    /**
     * Validate vanity code usage.
     *
     * @param string $vanity_code
     * @param string $domain
     * @return array
     */
    public function validate_vanity_code($vanity_code, $domain = '')
    {
        $code_record = $this->get_vanity_code($vanity_code);

        if (!$code_record) {
            return ['valid' => false, 'error' => 'invalid_code', 'message' => __('Invalid vanity code.', 'affiliatewp-cross-domain-plugin-suite')];
        }

        if ($code_record->status !== 'active') {
            return ['valid' => false, 'error' => 'inactive_code', 'message' => __('Vanity code is not active.', 'affiliatewp-cross-domain-plugin-suite')];
        }

        if ($code_record->expires_at && strtotime($code_record->expires_at) < time()) {
            return ['valid' => false, 'error' => 'expired_code', 'message' => __('Vanity code has expired.', 'affiliatewp-cross-domain-plugin-suite')];
        }

        if ($domain && !$this->security_manager->is_domain_authorized($domain)) {
            return ['valid' => false, 'error' => 'unauthorized_domain', 'message' => __('Domain not authorized.', 'affiliatewp-cross-domain-plugin-suite')];
        }

        $this->track_usage($code_record->id, $domain);

        return [
            'valid'         => true,
            'affiliate_id'  => $code_record->affiliate_id,
            'affiliate_code'=> $code_record->affiliate_code,
            'vanity_code'   => $code_record->vanity_code,
        ];
    }

    /**
     * Track vanity code usage.
     *
     * @param int $vanity_code_id
     * @param string $domain
     */
    private function track_usage($vanity_code_id, $domain = '')
    {
        global $wpdb;

        $wpdb->insert(
            $this->usage_table,
            [
                'vanity_code_id' => $vanity_code_id,
                'domain'         => sanitize_text_field($domain),
                'user_ip'        => $this->security_manager->get_client_ip(),
                'user_agent'     => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referrer'       => sanitize_text_field($_SERVER['HTTP_REFERER'] ?? ''),
                'session_id'     => session_id(),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET usage_count = usage_count + 1 WHERE id = %d",
            $vanity_code_id
        ));

        $vanity_code = $this->get_vanity_code($vanity_code_id);
        if ($vanity_code) {
            $this->clear_vanity_code_cache($vanity_code->vanity_code);
        }
    }

    /**
     * Cleanup expired vanity codes (cron).
     */
    public function cleanup_expired_codes()
    {
        global $wpdb;
        $now = current_time('mysql');

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = 'expired' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < %s",
            $now
        ));
    }

    /**
     * Schedule cleanup tasks.
     */
    private function schedule_cleanup_tasks()
    {
        if (!wp_next_scheduled('affcd_cleanup_expired_codes')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_expired_codes');
        }
        add_action('affcd_cleanup_expired_codes', [$this, 'cleanup_expired_codes']);
    }

    /**
     * Clear vanity code caches.
     *
     * @param string $vanity_code
     */
    private function clear_vanity_code_cache($vanity_code)
    {
        wp_cache_delete($this->cache_prefix . md5($vanity_code), 'affcd_vanity_codes');
        wp_cache_delete($this->cache_prefix . md5($vanity_code) . '_stats', 'affcd_vanity_codes');
    }

    /*  AJAX handlers (create, update, delete, bulk)  */

    public function ajax_create_vanity_code()
    {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        if (!current_user_can('manage_affiliates')) {
            wp_die(__('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        $result = $this->create_vanity_code($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(['message' => __('Vanity code created.', 'affiliatewp-cross-domain-plugin-suite'), 'vanity_id' => $result]);
    }

    public function ajax_update_vanity_code()
    {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        $vanity_id = absint($_POST['vanity_id'] ?? 0);
        $result = $this->update_vanity_code($vanity_id, $_POST);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(['message' => __('Vanity code updated.', 'affiliatewp-cross-domain-plugin-suite')]);
    }

    public function ajax_delete_vanity_code()
    {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        $vanity_id = absint($_POST['vanity_id'] ?? 0);
        $result = $this->delete_vanity_code($vanity_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(['message' => __('Vanity code deleted.', 'affiliatewp-cross-domain-plugin-suite')]);
    }

    public function ajax_bulk_operations()
    {
        check_ajax_referer('affcd_vanity_nonce', 'nonce');
        $action      = sanitize_text_field($_POST['bulk_action'] ?? '');
        $vanity_ids  = array_map('absint', $_POST['vanity_ids'] ?? []);
        $results     = $this->bulk_operations($action, $vanity_ids);

        $success_count = count(array_filter($results, fn($r) => !is_wp_error($r)));
        $error_count   = count($results) - $success_count;

        wp_send_json_success([
            'message' => sprintf(__('%d items succeeded, %d errors.', 'affiliatewp-cross-domain-plugin-suite'), $success_count, $error_count),
            'results' => $results,
        ]);
    }

    /*  Utility + validation  */

    private function validate_vanity_code_data($data, $exclude_id = 0)
    {
        $errors = [];

        if (empty($data['vanity_code'])) {
            $errors[] = __('Vanity code is required.', 'affiliatewp-cross-domain-plugin-suite');
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['vanity_code'])) {
            $errors[] = __('Vanity code can only contain letters, numbers, hyphens, and underscores.', 'affiliatewp-cross-domain-plugin-suite');
        } elseif (strlen($data['vanity_code']) < 3 || strlen($data['vanity_code']) > 50) {
            $errors[] = __('Vanity code must be 3–50 characters.', 'affiliatewp-cross-domain-plugin-suite');
        }

        if (empty($data['affiliate_id']) || !is_numeric($data['affiliate_id'])) {
            $errors[] = __('Affiliate ID required.', 'affiliatewp-cross-domain-plugin-suite');
        }
        if (empty($data['affiliate_code'])) {
            $errors[] = __('Affiliate code required.', 'affiliatewp-cross-domain-plugin-suite');
        }
        if (!empty($data['expires_at']) && strtotime($data['expires_at']) === false) {
            $errors[] = __('Invalid expiration date format.', 'affiliatewp-cross-domain-plugin-suite');
        }

        return $errors ? new WP_Error('validation_failed', implode(' ', $errors)) : true;
    }

    private function vanity_code_exists($vanity_code, $exclude_id = 0)
    {
        global $wpdb;
        $sql = "SELECT id FROM {$this->table_name} WHERE vanity_code = %s";
        $params = [$vanity_code];
        if ($exclude_id > 0) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }
        return $wpdb->get_var($wpdb->prepare($sql, $params)) !== null;
    }

    private function verify_affiliate_exists($affiliate_id)
    {
        return function_exists('affwp_get_affiliate') && affwp_get_affiliate($affiliate_id) !== false;
    }

    public function bulk_operations($action, $vanity_ids)
    {
        if (!current_user_can('manage_affiliates')) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }
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
                    $results[$vanity_id] = new WP_Error('invalid_action', __('Invalid bulk action.', 'affiliatewp-cross-domain-plugin-suite'));
            }
        }
        return $results;
    }
}
