<?php
if (!defined('ABSPATH')) { exit; }

/**
 * AFFCD_Signer
 * Generates HMAC signatures and sends signed HTTP requests to Master.
 */
class AFFCD_Signer {

    /** Resolve the satellite's Site ID (configure in your plugin settings). */
    public static function get_site_id(): string {
        // Change option name if you already store it elsewhere.
        return (string) get_option('affcd_site_id', '');
    }

    /** Resolve the shared secret used to sign requests for this site. */
    public static function get_secret_for_site(string $site_id = ''): string {
        $site_id = $site_id ?: self::get_site_id();
        // If you already store secrets differently, replace this.
        return (string) get_option('affcd_secret_' . $site_id, '');
    }

    /** Canonicalize to JSON for signing and transport. */
    public static function canonical_json($data): string {
        // Ensure stable encoding (no spaces, no escaped slashes/unicode)
        return wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Compute HMAC-SHA256(signature) over raw body. */
    public static function signature(string $raw_body, string $secret): string {
        return hash_hmac('sha256', $raw_body, $secret);
    }

    /** Build standard AFFCD headers. */
    public static function build_headers(string $raw_body, ?string $site_id = null, ?string $secret = null): array {
        $site_id = $site_id ?: self::get_site_id();
        $secret  = $secret  ?: self::get_secret_for_site($site_id);
        $ts      = time();
        $sig     = self::signature($raw_body, $secret);

        return [
            'Content-Type'       => 'application/json',
            'User-Agent'         => 'AFFCD-Satellite/1.0',
            'X-AFFCD-Signature'  => $sig,
            'X-AFFCD-Timestamp'  => (string) $ts,
            // Optional but handy for debugging on Master side:
            'X-AFFCD-Site'       => $site_id,
        ];
    }

    /** POST JSON with signature to Master. */
    public static function post_json(string $url, array $payload, array $args = []) {
        $payload = array_merge([
            'schema_version' => '1.0.0',
            'site_id'        => self::get_site_id(),
        ], $payload);

        $raw  = self::canonical_json($payload);
        $hdrs = self::build_headers($raw);

        $defaults = [
            'timeout' => 20,
            'headers' => $hdrs,
            'body'    => $raw,
        ];
        $resp = wp_remote_post($url, array_replace_recursive($defaults, $args));
        return [
            'response' => $resp,
            'code'     => is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp),
            'body'     => is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp),
        ];
    }

    /** GET with signature (for endpoints that require it). */
    public static function get_signed(string $url, array $query = [], array $args = []) {
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }
        // For GET, many servers sign an empty body or canonical query string.
        $raw  = ''; // Adjust if your Master validates GET bodies.
        $hdrs = self::build_headers($raw);

        $defaults = [
            'timeout' => 15,
            'headers' => $hdrs,
        ];
        $resp = wp_remote_get($url, array_replace_recursive($defaults, $args));
        return [
            'response' => $resp,
            'code'     => is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp),
            'body'     => is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp),
        ];
    }
}
