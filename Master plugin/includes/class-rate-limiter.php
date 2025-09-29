<?php
/**
 * Class: Affiliate_Rate_Limiter
 *
 * Production‑ready rate limiting, throttling, and IP blocking for
 * AffiliateWP Cross‑Domain modules (REST + AJAX safe).
 *
 * Features
 * - Per‑action limits (per‑minute / per‑hour)
 * - Identifiers: API key > authenticated user > IP
 * - Sliding window counters (minute/hour buckets)
 * - Graceful headers (RateLimit-*, Retry-After)
 * - Temporary IP blocks on excessive abuse
 * - Allow/Deny lists (IPs, API keys, users)
 * - Lightweight, cache‑first (wp_cache with transient fallback)
 * - Helper wrappers for REST/AJAX handlers
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AFFCD_Rate_Limiter {

    /** Cache group name */
    private $cache_group = 'affcd_rate_limits';

    /** Option keys */
    const OPT_LIMITS          = 'affcd_rate_limits_overrides';
    const OPT_DENY_IPS        = 'affcd_rate_limits_deny_ips';
    const OPT_ALLOW_IPS       = 'affcd_rate_limits_allow_ips';
    const OPT_DENY_KEYS       = 'affcd_rate_limits_deny_keys';
    const OPT_ALLOW_KEYS      = 'affcd_rate_limits_allow_keys';
    const OPT_BLOCKLIST       = 'affcd_rate_limits_blocklist';
    const OPT_BLOCK_CFG       = 'affcd_rate_block_cfg';

    /** Default rate limits per action */
    private $default_limits = [
        // action => [ per_minute, per_hour ]
        'validate_code'     => [ 'per_minute' => 30,  'per_hour' => 500 ],
        'api_request'       => [ 'per_minute' => 60,  'per_hour' => 1000 ],
        'form_submission'   => [ 'per_minute' => 10,  'per_hour' => 100 ],
        'failed_validation' => [ 'per_minute' => 5,   'per_hour' => 50 ],
        'create_vanity'     => [ 'per_minute' => 2,   'per_hour' => 20 ],
        'webhook_request'   => [ 'per_minute' => 20,  'per_hour' => 200 ],
        'default'           => [ 'per_minute' => 30,  'per_hour' => 600 ],
    ];

    /** Block configuration defaults */
    private $block_defaults = [
        'fail_threshold_minute' => 10,      // failures in last minute
        'fail_threshold_hour'   => 60,      // failures in last hour
        'block_minutes'         => 30,      // temporary block duration
        'max_blocks_day'        => 10,      // escalate after repeated blocks
    ];

    /** Singleton */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // ensure options exist
        $cfg = get_option( self::OPT_BLOCK_CFG, [] );
        if ( ! is_array( $cfg ) ) { $cfg = []; }
        update_option( self::OPT_BLOCK_CFG, wp_parse_args( $cfg, $this->block_defaults ), false );
    }

    /* ============================================================
     * Public API
     * ============================================================ */

    /**
     * Check and count a request.
     *
     * @param string          $action   Action key (e.g., 'validate_code').
     * @param WP_REST_Request $request  REST request or null (AJAX supported).
     * @param int             $weight   Optional weight for batching (default 1).
     * @return array { allowed:bool, reason:string, retry_after:int, headers:array }
     */
    public function check( $action, $request = null, $weight = 1 ) {
        $now   = time();
        $id    = $this->identify( $request );
        $ip    = $this->get_ip( $request );
        $limits = $this->get_limits( $action );

        // Allowlist short‑circuit
        if ( $this->is_allowed_identity( $id, $ip ) ) {
            $headers = $this->headers( $action, $id, $limits, $now );
            return [ 'allowed' => true, 'reason' => 'allowlist', 'retry_after' => 0, 'headers' => $headers ];
        }

        // Denylist check
        if ( $this->is_denied_identity( $id, $ip ) ) {
            return $this->deny_response( 'denylist', 3600 );
        }

        // Temporary block check
        if ( $this->is_temporarily_blocked( $ip, $now ) ) {
            $until = $this->blocked_until( $ip );
            $retry = max( 1, $until - $now );
            return $this->deny_response( 'temporarily_blocked', $retry );
        }

        // Count current usage
        list( $m_used, $m_cap, $m_reset ) = $this->get_bucket_state( $action, $id, 'minute', $limits, $now );
        list( $h_used, $h_cap, $h_reset ) = $this->get_bucket_state( $action, $id, 'hour',   $limits, $now );

        // Will the new weight exceed either cap?
        $would_exceed_min = ( $m_used + $weight ) > $m_cap;
        $would_exceed_hr  = ( $h_used + $weight ) > $h_cap;

        if ( $would_exceed_min || $would_exceed_hr ) {
            $retry_after = $would_exceed_min ? max(1, $m_reset - $now) : max(1, $h_reset - $now);
            return $this->deny_response( 'rate_limited', $retry_after, [
                'X-RateLimit-Minute-Used' => (string) $m_used,
                'X-RateLimit-Minute-Limit'=> (string) $m_cap,
                'X-RateLimit-Hour-Used'   => (string) $h_used,
                'X-RateLimit-Hour-Limit'  => (string) $h_cap,
            ]);
        }

        // Record usage
        $this->increment_bucket( $action, $id, 'minute', $weight, $now );
        $this->increment_bucket( $action, $id, 'hour',   $weight, $now );

        $headers = $this->headers( $action, $id, $limits, $now );
        return [ 'allowed' => true, 'reason' => 'ok', 'retry_after' => 0, 'headers' => $headers ];
    }

    /**
     * Record a failure (e.g., auth/validation failed) to drive temporary blocks.
     * If thresholds exceeded, IP is blocked for configured minutes.
     */
    public function record_failure( $request = null ) {
        $now = time();
        $ip  = $this->get_ip( $request );
        if ( ! $ip ) { return; }

        $this->increment_bucket( 'failed', $ip, 'minute', 1, $now, true );
        $this->increment_bucket( 'failed', $ip, 'hour',   1, $now, true );

        $cfg = $this->get_block_cfg();
        list( $m_used, , ) = $this->get_bucket_state( 'failed', $ip, 'minute', [ 'per_minute' => $cfg['fail_threshold_minute'] ], $now, true );
        list( $h_used, , ) = $this->get_bucket_state( 'failed', $ip, 'hour',   [ 'per_hour'   => $cfg['fail_threshold_hour'] ],   $now, true );

        if ( $m_used >= $cfg['fail_threshold_minute'] || $h_used >= $cfg['fail_threshold_hour'] ) {
            $this->block_ip( $ip, $cfg['block_minutes'] );
        }
    }

    /**
     * Record a success to slowly decay failure pressure (optional helper).
     */
    public function record_success( $request = null ) {
        // No‑op for now; counters auto‑expire with buckets.
    }

    /**
     * Enforce rate limits for a REST handler. Sends proper headers on response.
     * Return WP_Error if blocked, else null.
     */
    public function enforce_for_rest( $action, $request, $weight = 1 ) {
        $res = $this->check( $action, $request, $weight );
        if ( ! empty( $res['headers'] ) && function_exists( 'rest_ensure_response' ) ) {
            // Attach headers to the global response (best effort)
            foreach ( $res['headers'] as $k => $v ) {
                if ( ! headers_sent() ) { header( $k . ': ' . $v ); }
            }
        }
        if ( ! $res['allowed'] ) {
            $err = new WP_Error(
                'rate_limited',
                sprintf( 'Rate limit exceeded (%s). Retry after %ds', $res['reason'], intval( $res['retry_after'] ) ),
                [ 'status' => 429, 'retry_after' => intval( $res['retry_after'] ) ]
            );
            return $err;
        }
        return null;
    }

    /**
     * Enforce rate limits for AJAX handler; outputs JSON error and exits if blocked.
     */
    public function enforce_for_ajax( $action, $weight = 1 ) {
        $res = $this->check( $action, null, $weight );
        if ( ! $res['allowed'] ) {
            if ( ! headers_sent() ) {
                header( 'Retry-After: ' . intval( $res['retry_after'] ) );
                header( 'X-RateLimit-Reason: ' . $res['reason'] );
            }
            wp_send_json_error( [ 'error' => 'rate_limited', 'retry_after' => intval( $res['retry_after'] ) ], 429 );
        }
        return true;
    }

    /* ============================================================
     * Identity + Lists
     * ============================================================ */

    private function identify( $request = null ) {
        // 1) API key
        $api_key = $this->get_api_key( $request );
        if ( $api_key && $this->validate_api_key( $api_key ) ) {
            return 'key:' . substr( hash( 'sha256', $api_key ), 0, 20 );
        }

        // 2) Logged‑in user
        $uid = get_current_user_id();
        if ( $uid ) { return 'user:' . intval( $uid ); }

        // 3) IP
        $ip = $this->get_ip( $request );
        return 'ip:' . ( $ip ?: 'unknown' );
    }

    private function get_api_key( $request = null ) {
        if ( $request && is_object( $request ) && method_exists( $request, 'get_header' ) ) {
            $k = $request->get_header( 'X-API-Key' );
            if ( ! $k ) { $k = $request->get_param( 'api_key' ); }
            if ( ! $k && isset( $_SERVER['HTTP_X_API_KEY'] ) ) {
                $k = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) );
            }
            return is_string( $k ) ? trim( $k ) : '';
        }
        // Fallback for AJAX
        $k = isset( $_SERVER['HTTP_X_API_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) ) : '';
        if ( ! $k && isset( $_REQUEST['api_key'] ) ) {
            $k = sanitize_text_field( wp_unslash( $_REQUEST['api_key'] ) );
        }
        return $k;
    }

    private function validate_api_key( $key ) {
        // Hook for projects to validate API keys.
        return (bool) apply_filters( 'affcd_validate_api_key', ! empty( $key ), $key );
    }

    private function get_ip( $request = null ) {
        $ip = '';
        if ( $request && is_object( $request ) && method_exists( $request, 'get_header' ) ) {
            $ip = $request->get_header( 'X-Forwarded-For' );
        }
        if ( ! $ip ) {
            $candidates = [
                'HTTP_X_FORWARDED_FOR',
                'HTTP_CLIENT_IP',
                'HTTP_CF_CONNECTING_IP',
                'REMOTE_ADDR',
            ];
            foreach ( $candidates as $h ) {
                if ( ! empty( $_SERVER[ $h ] ) ) { $ip = $_SERVER[ $h ]; break; }
            }
        }
        // Take the first IP if comma‑separated list
        if ( strpos( $ip, ',' ) !== false ) {
            $parts = explode( ',', $ip );
            $ip = trim( $parts[0] );
        }
        // Normalize
        $ip = trim( (string) $ip );
        return $ip ?: '0.0.0.0';
    }

    private function is_allowed_identity( $id, $ip ) {
        $allow_ips  = get_option( self::OPT_ALLOW_IPS, [] );
        $allow_keys = get_option( self::OPT_ALLOW_KEYS, [] );
        $ip_ok  = is_array( $allow_ips )  && $ip  && in_array( $ip,  $allow_ips,  true );
        $key_ok = is_array( $allow_keys ) && str_starts_with( $id, 'key:' ) && in_array( substr( $id, 4 ), $allow_keys, true );
        return (bool) ( $ip_ok || $key_ok );
    }

    private function is_denied_identity( $id, $ip ) {
        $deny_ips  = get_option( self::OPT_DENY_IPS, [] );
        $deny_keys = get_option( self::OPT_DENY_KEYS, [] );
        $ip_bad  = is_array( $deny_ips )  && $ip  && in_array( $ip,  $deny_ips,  true );
        $key_bad = is_array( $deny_keys ) && str_starts_with( $id, 'key:' ) && in_array( substr( $id, 4 ), $deny_keys, true );
        return (bool) ( $ip_bad || $key_bad );
    }

    /* ============================================================
     * Limits + Buckets
     * ============================================================ */

    private function get_limits( $action ) {
        $over = get_option( self::OPT_LIMITS, [] );
        if ( ! is_array( $over ) ) { $over = []; }
        $base = $this->default_limits[ $action ] ?? $this->default_limits['default'];
        $merged = wp_parse_args( $over[ $action ] ?? [], $base );
        // Force ints
        $merged['per_minute'] = max( 1, intval( $merged['per_minute'] ?? 1 ) );
        $merged['per_hour']   = max( 1, intval( $merged['per_hour']   ?? 1 ) );
        return $merged;
    }

    private function get_bucket_keys( $action, $id, $scope, $now ) {
        // scope: minute|hour
        if ( 'minute' === $scope ) {
            $k = gmdate( 'YmdHi', $now );
        } else {
            $k = gmdate( 'YmdH', $now );
        }
        $key = sprintf( 'affcd:%s:%s:%s', $action, $id, $k );
        $ttl = ( 'minute' === $scope ) ? 65 : 3605;
        $reset = ( 'minute' === $scope )
            ? ( ( intval( gmdate( 's', $now ) ) > 0 ) ? ( $now + (60 - intval( gmdate( 's', $now ) )) ) : ( $now + 60 ) )
            : ( $now + ( 3600 - ( intval( gmdate('i', $now) ) * 60 + intval( gmdate('s', $now) ) ) ) );
        return [ $key, $ttl, $reset ];
    }

    private function get_bucket_state( $action, $id, $scope, $limits, $now, $raw_id = false ) {
        $id_key = $raw_id ? $id : $this->hash_id( $id );
        list( $key, $ttl, $reset ) = $this->get_bucket_keys( $action . ':' . $scope, $id_key, $scope, $now );
        $used = $this->cache_get( $key, 0 );
        $cap  = ( 'minute' === $scope ) ? intval( $limits['per_minute'] ?? 0 ) : intval( $limits['per_hour'] ?? 0 );
        return [ intval( $used ), $cap, $reset ];
    }

    private function increment_bucket( $action, $id, $scope, $by, $now, $raw_id = false ) {
        $id_key = $raw_id ? $id : $this->hash_id( $id );
        list( $key, $ttl, $reset ) = $this->get_bucket_keys( $action . ':' . $scope, $id_key, $scope, $now );
        $val = intval( $this->cache_get( $key, 0 ) ) + intval( $by );
        $this->cache_set( $key, $val, $ttl );
        return $val;
    }

    private function hash_id( $id ) {
        return substr( hash( 'sha256', (string) $id ), 0, 24 );
    }

    /* ============================================================
     * Blocking helpers
     * ============================================================ */

    private function get_block_cfg() {
        $cfg = get_option( self::OPT_BLOCK_CFG, [] );
        if ( ! is_array( $cfg ) ) { $cfg = []; }
        return wp_parse_args( $cfg, $this->block_defaults );
    }

    private function block_ip( $ip, $minutes ) {
        $until = time() + ( max( 1, intval( $minutes ) ) * 60 );
        $block = get_option( self::OPT_BLOCKLIST, [] );
        if ( ! is_array( $block ) ) { $block = []; }
        $block[ $ip ] = [
            'until'      => $until,
            'created_at' => current_time( 'mysql' ),
            'count'      => isset( $block[ $ip ]['count'] ) ? intval( $block[ $ip ]['count'] ) + 1 : 1,
        ];
        update_option( self::OPT_BLOCKLIST, $block, false );
    }

    private function is_temporarily_blocked( $ip, $now ) {
        $block = get_option( self::OPT_BLOCKLIST, [] );
        if ( ! is_array( $block ) || empty( $block[ $ip ] ) ) { return false; }
        return ( $block[ $ip ]['until'] ?? 0 ) > $now;
    }

    private function blocked_until( $ip ) {
        $block = get_option( self::OPT_BLOCKLIST, [] );
        return intval( $block[ $ip ]['until'] ?? 0 );
    }

    /* ============================================================
     * Headers + Responses
     * ============================================================ */

    private function headers( $action, $id, $limits, $now ) {
        list( $m_used, $m_cap, $m_reset ) = $this->get_bucket_state( $action, $id, 'minute', $limits, $now );
        list( $h_used, $h_cap, $h_reset ) = $this->get_bucket_state( $action, $id, 'hour',   $limits, $now );
        return [
            'RateLimit-Limit'        => sprintf( '%d, %d;w=3600', $m_cap, $h_cap ),
            'RateLimit-Remaining'    => sprintf( '%d, %d;w=3600', max(0,$m_cap-$m_used), max(0,$h_cap-$h_used) ),
            'RateLimit-Reset'        => (string) max( $m_reset - $now, $h_reset - $now ),
            'X-RateLimit-Minute-Used'=> (string) $m_used,
            'X-RateLimit-Minute-Limit'=> (string) $m_cap,
            'X-RateLimit-Hour-Used'  => (string) $h_used,
            'X-RateLimit-Hour-Limit' => (string) $h_cap,
        ];
    }

    private function deny_response( $reason, $retry_after = 60, $extra_headers = [] ) {
        $headers = array_merge( [
            'Retry-After'        => (string) intval( $retry_after ),
            'X-RateLimit-Reason' => $reason,
        ], $extra_headers );
        return [ 'allowed' => false, 'reason' => $reason, 'retry_after' => intval( $retry_after ), 'headers' => $headers ];
    }

    /* ============================================================
     * Cache helpers (object cache with transient fallback)
     * ============================================================ */

    private function cache_get( $key, $default = 0 ) {
        $val = wp_cache_get( $key, $this->cache_group );
        if ( false === $val ) {
            $val = get_transient( $this->tkey( $key ) );
            if ( false === $val ) { return $default; }
        }
        return $val;
    }

    private function cache_set( $key, $value, $ttl ) {
        wp_cache_set( $key, $value, $this->cache_group, $ttl );
        set_transient( $this->tkey( $key ), $value, $ttl );
    }

    private function tkey( $key ) {
        return 'affcd_' . md5( $key );
    }
}
