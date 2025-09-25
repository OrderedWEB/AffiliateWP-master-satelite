<?php
/**
 * Analytics tracker referenced in API endpoints but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Analytics_Tracker {

    /**
     * Track a usage event (validation or conversion)
     *
     * @param string $domain   Requesting domain (URL or host)
     * @param string $code     Affiliate / vanity code
     * @param bool   $conversion Whether this event is a conversion
     * @param array  $metadata  Extra context (keys supported below)
     *   - domain_to (string URL/host)
     *   - status ('success'|'failed'|'pending'|'timeout')
     *   - session_id (string)
     *   - user_id (int)
     *   - conversion_value (float)
     *   - currency (string, default 'GBP')
     *   - commission_rate (float)
     *   - commission_amount (float)
     *   - utm_parameters (array)
     *   - device_info (array)
     *   - geographic_info (array)
     *   - error_code (string)
     *   - error_message (string)
     *   - api_version (string, default '1.0')
     *   - request_data / response_data (mixed)
     *   - processing_time_ms (int)
     *   - webhook_sent (bool), webhook_response_code (int)
     *   - metadata (array) — any extra data
     * @return int|false Insert ID on success, false on failure
     */
    public function track_usage($domain, $code, $conversion = false, $metadata = []) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_usage_tracking';

        $domain_from = $this->normalize_host($domain);
        $domain_to   = isset($metadata['domain_to']) ? $this->normalize_host($metadata['domain_to']) : null;

        $event_type  = $conversion ? 'conversion' : 'validation';
        $status      = in_array(($metadata['status'] ?? 'success'), ['success','failed','pending','timeout'], true)
            ? $metadata['status'] : 'success';

        $data = [
            'domain_from'        => $domain_from,
            'domain_to'          => $domain_to,
            'affiliate_code'     => sanitize_text_field($code),
            'event_type'         => $event_type,
            'status'             => $status,
            'session_id'         => sanitize_text_field($metadata['session_id'] ?? ''),
            'user_id'            => isset($metadata['user_id']) ? absint($metadata['user_id']) : null,
            'ip_address'         => $this->get_client_ip(),
            'user_agent'         => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referrer'           => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
            'request_data'       => isset($metadata['request_data']) ? wp_json_encode($metadata['request_data']) : null,
            'response_data'      => isset($metadata['response_data']) ? wp_json_encode($metadata['response_data']) : null,
            'processing_time_ms' => isset($metadata['processing_time_ms']) ? intval($metadata['processing_time_ms']) : null,
            'conversion_value'   => isset($metadata['conversion_value']) ? floatval($metadata['conversion_value']) : null,
            'currency'           => sanitize_text_field($metadata['currency'] ?? 'GBP'),
            'commission_rate'    => isset($metadata['commission_rate']) ? floatval($metadata['commission_rate']) : null,
            'commission_amount'  => isset($metadata['commission_amount']) ? floatval($metadata['commission_amount']) : null,
            'utm_parameters'     => isset($metadata['utm_parameters']) ? wp_json_encode($metadata['utm_parameters']) : null,
            'device_info'        => isset($metadata['device_info']) ? wp_json_encode($metadata['device_info']) : null,
            'geographic_info'    => isset($metadata['geographic_info']) ? wp_json_encode($metadata['geographic_info']) : null,
            'error_code'         => isset($metadata['error_code']) ? sanitize_text_field($metadata['error_code']) : null,
            'error_message'      => isset($metadata['error_message']) ? sanitize_textarea_field($metadata['error_message']) : null,
            'retry_count'        => isset($metadata['retry_count']) ? intval($metadata['retry_count']) : 0,
            'webhook_sent'       => !empty($metadata['webhook_sent']) ? 1 : 0,
            'webhook_response_code' => isset($metadata['webhook_response_code']) ? intval($metadata['webhook_response_code']) : null,
            'api_version'        => sanitize_text_field($metadata['api_version'] ?? '1.0'),
            'metadata'           => isset($metadata['metadata']) ? wp_json_encode($metadata['metadata']) : null,
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
        ];

        $formats = [
            '%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%d','%f','%s','%f','%f','%s','%s','%s','%s','%d','%d','%d','%s','%s','%s','%s'
        ];

        $result = $wpdb->insert($table_name, $data, $formats);

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Domain-level stats (last N days)
     */
    public function get_domain_stats($domain, $days = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        $since      = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $host       = $this->normalize_host($domain);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*)                                         AS total_requests,
                COUNT(DISTINCT session_id)                       AS unique_sessions,
                SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END)                 AS conversions,
                SUM(CASE WHEN conversion_value IS NOT NULL THEN conversion_value ELSE 0 END) AS total_value
             FROM {$table_name}
             WHERE domain_from = %s AND created_at >= %s",
            $host, $since
        ));
    }

    /**
     * Code-level stats (last N days)
     */
    public function get_code_stats($code, $days = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_usage_tracking';
        $since      = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*)                                         AS total_uses,
                COUNT(DISTINCT domain_from)                      AS unique_domains,
                SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END)                 AS conversions,
                AVG(CASE WHEN conversion_value IS NOT NULL THEN conversion_value ELSE NULL END) AS avg_value
             FROM {$table_name}
             WHERE affiliate_code = %s AND created_at >= %s",
            sanitize_text_field($code), $since
        ));
    }

    private function normalize_host($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        // If it's a bare host, parse_url will fail; prepend scheme
        if (strpos($value, '://') === false) {
            $value = 'https://' . $value;
        }
        $host = parse_url($value, PHP_URL_HOST);
        return strtolower($host ?: '');
    }

    private function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $raw = $_SERVER[$header] ?? '';
            if ($raw) {
                $ip = trim(explode(',', $raw)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
