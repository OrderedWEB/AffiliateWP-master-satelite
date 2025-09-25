<?php
/**
 * Synchronous Webhook Handler
 *
 * Sends signed webhook payloads immediately (blocking request) while
 * delegating domain/webhook configuration to the Manager. Includes
 * event filtering, logging, and simple test utilities.
 *
 * @package AffiliateWP_Cross_Domain_Full
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Webhook_Handler {

    /**
     * @var AFFCD_Webhook_Manager
     */
    private $manager;

    /**
     * HTTP timeout seconds
     * @var int
     */
    private $timeout;

    /**
     * User agent
     * @var string
     */
    private $user_agent;

    /**
     * Supported event keys (for downstream clarity)
     * @var string[]
     */
    private $supported_events = [
        'code_validated',
        'code_used',
        'conversion_tracked',
        'domain_status_changed',
        'affiliate_status_changed',
        'code_created',
        'code_updated',
        'code_deleted',
        'bulk_operation_completed',
        'webhook_test',
    ];

    /**
     * Constructor
     *
     * @param AFFCD_Webhook_Manager $manager
     */
    public function __construct($manager) {
        $this->manager    = $manager;
        $settings         = get_option('affcd_webhook_settings', []);
        $this->timeout    = isset($settings['timeout']) && (int)$settings['timeout'] > 0 ? (int)$settings['timeout'] : 30;
        $this->user_agent = 'AffiliateWP-CrossDomain/' . (defined('AFFCD_VERSION') ? AFFCD_VERSION : '1.0.0');
    }

    /**
     * Send webhook to a specific domain by resolving its registered config.
     *
     * @param string $domain_url
     * @param string $event
     * @param array  $data
     * @return array result
     */
    public function send_to_domain($domain_url, $event, array $data = []) {
        $webhook = $this->manager_get_webhook_for_domain($domain_url);
        if (!$webhook) {
            return $this->result_error('no_webhook', __('No webhook registered for domain', 'affiliatewp-cross-domain-full'));
        }

        if (!$this->is_event_allowed_for_webhook($webhook, $event)) {
            return $this->result_error('event_not_subscribed', __('Domain is not subscribed to this event', 'affiliatewp-cross-domain-full'));
        }

        return $this->deliver_url(
            $webhook->webhook_url,
            (string)$webhook->webhook_secret,
            $event,
            $data,
            [
                'X-AFFCD-Domain' => $domain_url,
            ]
        );
    }

    /**
     * Broadcast to all configured domains that subscribe to the event.
     *
     * @param string $event
     * @param array  $data
     * @return array keyed by domain_url
     */
    public function broadcast($event, array $data = []) {
        $domains = $this->manager->get_webhook_domains();
        $results = [];

        if (empty($domains)) {
            return $results;
        }

        foreach ($domains as $d) {
            // Each entry should contain: domain_url, webhook_url, webhook_events, webhook_secret, status
            if (empty($d->webhook_url) || ($d->status ?? '') !== 'active') {
                continue;
            }
            if (!$this->is_event_allowed_json($d->webhook_events, $event)) {
                continue;
            }

            $results[$d->domain_url] = $this->deliver_url(
                $d->webhook_url,
                (string)($d->webhook_secret ?? ''),
                $event,
                $data,
                [
                    'X-AFFCD-Domain' => $d->domain_url,
                ]
            );
        }

        return $results;
    }

    /**
     * Send a test webhook to a configured domain.
     *
     * @param string $domain_url
     * @return array
     */
    public function send_test_to_domain($domain_url) {
        $payload = [
            'message'   => 'This is a test webhook from AFFCD',
            'test_id'   => wp_generate_uuid4(),
            'test_time' => current_time('c'),
        ];
        return $this->send_to_domain($domain_url, 'webhook_test', $payload);
    }

    /**
     * Send a test webhook directly to a URL (bypassing manager).
     *
     * @param string $webhook_url
     * @param string $secret
     * @return array
     */
    public function send_test_to_url($webhook_url, $secret = '') {
        $payload = [
            'message'   => 'This is a test webhook from AFFCD',
            'test_id'   => wp_generate_uuid4(),
            'test_time' => current_time('c'),
        ];
        return $this->deliver_url($webhook_url, (string)$secret, 'webhook_test', $payload);
    }

    /**
     * Core delivery — synchronous POST with signing and logging.
     *
     * @param string $webhook_url
     * @param string $secret
     * @param string $event
     * @param array  $data
     * @param array  $extra_headers
     * @return array
     */
    public function deliver_url($webhook_url, $secret, $event, array $data = [], array $extra_headers = []) {
        if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return $this->result_error('invalid_url', __('Invalid webhook URL', 'affiliatewp-cross-domain-full'));
        }

        // Build payload
        $payload = [
            'event'     => $event,
            'data'      => $data,
            'timestamp' => time(),
            'site_url'  => get_site_url(),
            'version'   => defined('AFFCD_VERSION') ? AFFCD_VERSION : '1.0.0',
        ];

        $payload_json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature    = 'sha256=' . hash_hmac('sha256', $payload_json, (string)$secret);

        $headers = array_merge([
            'Content-Type'      => 'application/json',
            'User-Agent'        => $this->user_agent,
            'X-AFFCD-Event'     => $event,
            'X-AFFCD-Delivery'  => wp_generate_uuid4(),
            'X-AFFCD-Timestamp' => (string)$payload['timestamp'],
            'X-AFFCD-Signature' => $signature,
        ], $extra_headers);

        $start = microtime(true);
        $response = wp_remote_post($webhook_url, [
            'headers'   => $headers,
            'body'      => $payload_json,
            'timeout'   => $this->timeout,
            'blocking'  => true,
            'sslverify' => true,
            'redirection' => 0,
        ]);
        $elapsed_ms = (int)round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $this->log_delivery('webhook_failed', $webhook_url, $event, [
                'error'           => $error,
                'execution_ms'    => $elapsed_ms,
            ]);
            return [
                'success'      => false,
                'error'        => $error,
                'response_code'=> 0,
                'time_ms'      => $elapsed_ms,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300) {
            $this->log_delivery('webhook_delivered', $webhook_url, $event, [
                'response_code' => $code,
                'execution_ms'  => $elapsed_ms,
            ]);
            return [
                'success'       => true,
                'response_code' => $code,
                'time_ms'       => $elapsed_ms,
                'body'          => $body,
            ];
        }

        $this->log_delivery('webhook_failed', $webhook_url, $event, [
            'response_code' => $code,
            'execution_ms'  => $elapsed_ms,
            'body_excerpt'  => mb_substr((string)$body, 0, 500),
        ]);

        return [
            'success'       => false,
            'response_code' => $code,
            'time_ms'       => $elapsed_ms,
            'body'          => $body,
        ];
    }

    /**
     * Verify incoming signature (for receivers).
     *
     * @param string $payload_json
     * @param string $signatureHeader e.g. "sha256=..."
     * @param string $secret
     * @return bool
     */
    public function verify_signature($payload_json, $signatureHeader, $secret) {
        $expected = 'sha256=' . hash_hmac('sha256', (string)$payload_json, (string)$secret);
        return hash_equals($expected, (string)$signatureHeader);
    }

    /* 
     * Internal helpers
     **/

    /**
     * Resolve manager's single-domain webhook record.
     *
     * @param string $domain_url
     * @return object|null
     */
    private function manager_get_webhook_for_domain($domain_url) {
        // Use manager's public accessor if present; otherwise fallback to direct query via manager API.
        if (method_exists($this->manager, 'get_webhook_for_domain')) {
            return $this->manager->get_webhook_for_domain($domain_url);
        }

        // Fallback: try public list and filter
        $domains = $this->manager->get_webhook_domains();
        foreach ((array)$domains as $d) {
            if (isset($d->domain_url) && $d->domain_url === $domain_url) {
                return $d;
            }
        }
        return null;
    }

    /**
     * Check if the event is allowed per webhook record (object has JSON string "webhook_events")
     *
     * @param object $webhook
     * @param string $event
     * @return bool
     */
    private function is_event_allowed_for_webhook($webhook, $event) {
        $json = isset($webhook->webhook_events) ? $webhook->webhook_events : '';
        return $this->is_event_allowed_json($json, $event);
    }

    /**
     * Shared JSON whitelist logic
     *
     * @param string $json
     * @param string $event
     * @return bool
     */
    private function is_event_allowed_json($json, $event) {
        if (empty($json)) {
            return true; // subscribe to all
        }
        $arr = json_decode($json, true);
        if (!is_array($arr) || empty($arr)) {
            return true;
        }
        return in_array($event, $arr, true) || in_array('*', $arr, true);
    }

    /**
     * Uniform error result format
     */
    private function result_error($code, $message) {
        return [
            'success'       => false,
            'error'         => $message,
            'error_code'    => $code,
            'response_code' => 0,
            'time_ms'       => 0,
        ];
    }

    /**
     * Log webhook delivery analytics (uses global helper if present).
     *
     * @param string $type  webhook_delivered|webhook_failed
     * @param string $url
     * @param string $event
     * @param array  $extra
     */
    private function log_delivery($type, $url, $event, array $extra = []) {
        if (function_exists('affcd_log_activity')) {
            affcd_log_activity($type, array_merge([
                'webhook_url' => $url,
                'event'       => $event,
            ], $extra), 'webhook', 0);
        }
    }
}
