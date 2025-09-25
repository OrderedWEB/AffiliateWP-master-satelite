<?php
/**
 * Webhook Manager
 *
 * Responsible for storing and retrieving webhook configurations,
 * validating whether events are enabled, and coordinating with
 * the Loader and Handler for actual delivery.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Webhook_Manager {

    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $db;

    /**
     * Table name for webhook domains
     *
     * @var string
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $wpdb->prefix . 'affcd_authorized_domains';
    }

    /**
     * Get webhook configuration for a single domain
     *
     * @param string $domain_url Target domain.
     * @return object|null Domain record or null if not found.
     */
    public function get_webhook_for_domain(string $domain_url) {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE domain = %s LIMIT 1",
                $domain_url
            )
        );
    }

    /**
     * Get all domains with webhook configuration
     *
     * @param string|null $status Filter by status (active, inactive, suspended) or null for all.
     * @return array List of domain records.
     */
    public function get_webhook_domains(?string $status = 'active'): array {
        if ($status) {
            return $this->db->get_results(
                $this->db->prepare(
                    "SELECT * FROM {$this->table} WHERE status = %s ORDER BY domain ASC",
                    $status
                )
            ) ?: [];
        }

        return $this->db->get_results(
            "SELECT * FROM {$this->table} ORDER BY domain ASC"
        ) ?: [];
    }

    /**
     * Check whether a given event is enabled for a domain
     *
     * @param string $event  Event key.
     * @param string $domain Domain URL.
     * @return bool True if event is enabled, false otherwise.
     */
    public function is_event_enabled(string $event, string $domain): bool {
        $webhook = $this->get_webhook_for_domain($domain);
        if (!$webhook || empty($webhook->webhook_events)) {
            return true; // default: all events enabled if not restricted
        }

        $events = json_decode((string)$webhook->webhook_events, true);
        if (!is_array($events) || empty($events)) {
            return true;
        }

        return in_array($event, $events, true) || in_array('*', $events, true);
    }

    /**
     * Enable or disable a domain’s webhook subscription
     *
     * @param string $domain Domain URL.
     * @param string $status New status (active, inactive, suspended).
     * @return bool Success.
     */
    public function update_domain_status(string $domain, string $status): bool {
        $allowed = ['active', 'inactive', 'suspended'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $updated = $this->db->update(
            $this->table,
            [
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['domain' => $domain],
            ['%s', '%s'],
            ['%s']
        );

        return $updated !== false;
    }

    /**
     * Register or update a webhook configuration for a domain
     *
     * @param string $domain_url Domain URL.
     * @param string $webhook_url Webhook endpoint URL.
     * @param string $secret Shared secret key.
     * @param array  $events List of event keys (or ['*'] for all).
     * @param string $status Domain status (default active).
     * @return bool Success.
     */
    public function register_webhook(
        string $domain_url,
        string $webhook_url,
        string $secret,
        array $events = ['*'],
        string $status = 'active'
    ): bool {
        $existing = $this->get_webhook_for_domain($domain_url);

        $data = [
            'domain'         => $domain_url,
            'webhook_url'    => esc_url_raw($webhook_url),
            'webhook_secret' => sanitize_text_field($secret),
            'webhook_events' => wp_json_encode(array_values($events)),
            'status'         => $status,
            'updated_at'     => current_time('mysql'),
        ];

        if ($existing) {
            $updated = $this->db->update(
                $this->table,
                $data,
                ['domain' => $domain_url],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%s']
            );
            return $updated !== false;
        }

        $data['created_at'] = current_time('mysql');
        $inserted = $this->db->insert(
            $this->table,
            $data,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        return $inserted !== false;
    }

    /**
     * Remove a webhook configuration for a domain
     *
     * @param string $domain_url Domain URL.
     * @return bool Success.
     */
    public function remove_webhook(string $domain_url): bool {
        $deleted = $this->db->delete(
            $this->table,
            ['domain' => $domain_url],
            ['%s']
        );
        return $deleted !== false;
    }
}
