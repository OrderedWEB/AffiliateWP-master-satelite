<?php
/**
 * Domain Manager (service layer)
 *
 * CRUD, verification, and utility helpers for Authorized Domains.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Domain_Manager {

    /** @var AFFCD_Database_Manager */
    private $db_manager;

    /** @var string */
    private $table;

    public function __construct($db_manager = null) {
        // Allow injection, but work standalone if needed.
        if ($db_manager instanceof AFFCD_Database_Manager) {
            $this->db_manager = $db_manager;
        } else {
            require_once AFFCD_PLUGIN_DIR . 'includes/class-database-manager.php';
            $this->db_manager = new AFFCD_Database_Manager();
        }

        $this->table = $this->db_manager->get_table_name('authorized_domains');
    }

    /**
     * Get all domains (for admin list)
     *
     * @return array of stdClass rows
     */
    public function get_all_domains() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY created_at DESC");
    }

    /**
     * Get one domain by ID
     */
    public function get_domain($id) {
        global $wpdb;
        $id = absint($id);
        if (!$id) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }

    /**
     * Get domain by URL
     */
    public function get_domain_by_url($url) {
        global $wpdb;
        $url = $this->normalize_domain_url($url);
        if (!$url) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE domain_url = %s",
            $url
        ));
    }

    /**
     * Add a new domain
     *
     * @param array $data
     * @return int|WP_Error Insert ID on success
     */
    public function add_domain($data) {
        global $wpdb;

        $url = $this->normalize_domain_url($data['domain_url'] ?? '');
        if (!$url) {
            return new WP_Error('invalid_domain', __('Invalid domain URL.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Unique constraint on domain_url
        if ($this->get_domain_by_url($url)) {
            return new WP_Error('domain_exists', __('Domain already exists.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $status = $data['status'] ?? 'pending';
        if (!in_array($status, ['active', 'inactive', 'suspended', 'pending'], true)) {
            $status = 'pending';
        }

        $insert = [
            'domain_url'          => $url,
            'domain_name'         => sanitize_text_field($data['domain_name'] ?? ''),
            'api_key'             => $this->generate_api_key(),
            'api_secret'          => $this->generate_api_secret(),
            'status'              => $status,
            'verification_status' => 'unverified',
            'verification_method' => 'file',
            'owner_email'         => sanitize_email($data['owner_email'] ?? ''),
            'owner_name'          => sanitize_text_field($data['owner_name'] ?? ''),
            'created_at'          => current_time('mysql'),
            'updated_at'          => current_time('mysql'),
        ];

        $fmt = ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'];

        $ok = $wpdb->insert($this->table, $insert, $fmt);
        if (!$ok) {
            return new WP_Error('db_insert_failed', __('Failed to add domain.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a domain
     *
     * @param int   $id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update_domain($id, $data) {
        global $wpdb;
        $id = absint($id);
        if (!$id) {
            return new WP_Error('invalid_id', __('Invalid domain ID.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $allowed = [
            'domain_url','domain_name','status','verification_status','verification_token','verification_method',
            'max_daily_requests','current_daily_requests','daily_reset_at',
            'rate_limit_per_minute','rate_limit_per_hour',
            'allowed_endpoints','blocked_endpoints',
            'allowed_ips','blocked_ips',
            'security_level','require_https',
            'webhook_url','webhook_secret','webhook_events','webhook_last_sent','webhook_failures',
            'total_requests','blocked_requests','error_requests','last_request_at',
            'last_verified_at','verification_failures',
            'statistics','metadata',
            'owner_user_id','owner_email','owner_name',
            'contact_email','contact_phone',
            'timezone','language','notes','tags',
            'expires_at','suspended_at','suspended_reason','suspended_by',
            'last_activity_at',
        ];

        $update = [];
        $format = [];

        foreach ($data as $key => $val) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            switch ($key) {
                case 'require_https':
                    $update[$key] = (int) !empty($val);
                    $format[] = '%d';
                    break;

                case 'max_daily_requests':
                case 'current_daily_requests':
                case 'rate_limit_per_minute':
                case 'rate_limit_per_hour':
                case 'webhook_failures':
                case 'total_requests':
                case 'blocked_requests':
                case 'error_requests':
                case 'owner_user_id':
                case 'suspended_by':
                    $update[$key] = (int) $val;
                    $format[] = '%d';
                    break;

                case 'daily_reset_at':
                case 'last_request_at':
                case 'last_verified_at':
                case 'expires_at':
                case 'suspended_at':
                case 'last_activity_at':
                    $update[$key] = $val ? date('Y-m-d H:i:s', strtotime($val)) : null;
                    $format[] = '%s';
                    break;

                case 'allowed_endpoints':
                case 'blocked_endpoints':
                case 'allowed_ips':
                case 'blocked_ips':
                case 'webhook_events':
                case 'statistics':
                case 'metadata':
                    $update[$key] = is_string($val) ? $val : wp_json_encode($val);
                    $format[] = '%s';
                    break;

                default:
                    $update[$key] = is_string($val) ? sanitize_text_field($val) : $val;
                    $format[] = '%s';
            }
        }

        if (empty($update)) {
            // Nothing to update
            return false;
        }

        $update['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $res = $wpdb->update($this->table, $update, ['id' => $id], $format, ['%d']);
        if ($res === false) {
            return new WP_Error('db_update_failed', __('Failed to update domain.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        return true;
    }

    /**
     * Delete a domain
     */
    public function delete_domain($id) {
        global $wpdb;
        $id = absint($id);
        if (!$id) {
            return new WP_Error('invalid_id', __('Invalid domain ID.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        $res = $wpdb->delete($this->table, ['id' => $id], ['%d']);
        if ($res === false) {
            return new WP_Error('db_delete_failed', __('Failed to delete domain.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        return true;
    }

    /**
     * Regenerate API key
     *
     * @return string|WP_Error New key
     */
    public function regenerate_api_key($id) {
        global $wpdb;
        $id = absint($id);
        if (!$id) {
            return new WP_Error('invalid_id', __('Invalid domain ID.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $new_key = $this->generate_api_key();
        $res = $wpdb->update(
            $this->table,
            ['api_key' => $new_key, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s','%s'],
            ['%d']
        );
        if ($res === false) {
            return new WP_Error('db_update_failed', __('Failed to regenerate API key.', 'affiliatewp-cross-domain-plugin-suite'));
        }
        return $new_key;
    }

    /**
     * Mark domain verified (and activate)
     *
     * @return array { success: bool, message: string }
     */
    public function verify_domain($id) {
        $res = $this->update_domain($id, [
            'verification_status' => 'verified',
            'last_verified_at'    => current_time('mysql'),
            'status'              => 'active',
        ]);

        if (is_wp_error($res) || $res === false) {
            return [
                'success' => false,
                'message' => is_wp_error($res) ? $res->get_error_message() : __('Verification failed.', 'affiliatewp-cross-domain-plugin-suite'),
            ];
        }

        return [
            'success' => true,
            'message' => __('Domain verified successfully.', 'affiliatewp-cross-domain-plugin-suite'),
        ];
    }

    /**
     * Helpers
     */
    private function normalize_domain_url($url) {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        // Default to https if scheme omitted
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            ? strtolower($parts['scheme'])
            : 'https';
        $host   = strtolower($parts['host']);
        $port   = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path   = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return "{$scheme}://{$host}{$port}{$path}";
    }

    private function generate_api_key() {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password(40, false, false);
        }
        return bin2hex(random_bytes(20));
    }

    private function generate_api_secret() {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password(64, true, true);
        }
        return bin2hex(random_bytes(32));
    }
}
