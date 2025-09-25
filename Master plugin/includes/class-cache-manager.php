<?php
/**
 * Cache manager referenced in main plugin but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Cache_Manager {

    private $cache_group = 'affcd';
    private $default_ttl = 300; // 5 minutes
    private $max_age     = 300; // sent to clients
    private $etag_prefix = 'affcd-';

    public function __construct() {
        add_action('init', [$this, 'setup_cache']);
    }

    public function setup_cache() {
        $settings = get_option('affcd_cache_settings', []);
        $this->default_ttl = isset($settings['default_ttl']) ? (int) $settings['default_ttl'] : 300;
        $this->max_age     = isset($settings['client_max_age']) ? (int) $settings['client_max_age'] : $this->default_ttl;

        // Serve from cache early
        add_filter('rest_pre_dispatch', [$this, 'maybe_serve_cached'], 10, 3);
        // Store into cache (NOTE: rest_post_dispatch is a FILTER)
        add_filter('rest_post_dispatch', [$this, 'maybe_cache_response'], 10, 3);
    }

    /**
     * Try to serve a cached response for GET /wp-json/affcd/* if safe.
     */
    public function maybe_serve_cached($result, $server, $request) {
        if ($this->should_not_cache($request)) {
            return $result;
        }

        $cache_key = $this->generate_cache_key($request);
        $cached = $this->get($cache_key);

        if ($cached === false) {
            return $result;
        }

        // ETag handling
        $client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $etag = $cached['headers']['ETag'] ?? '';
        if ($etag && $client_etag === $etag) {
            $resp = new WP_REST_Response(null, 304);
            $this->apply_headers($resp, $cached['headers']);
            $resp->header('X-Cache', 'HIT');
            return $resp;
        }

        $resp = new WP_REST_Response($cached['data'], (int) ($cached['status'] ?? 200));
        $this->apply_headers($resp, $cached['headers'] ?? []);
        $resp->header('X-Cache', 'HIT');
        return $resp;
    }

    /**
     * Store successful responses in cache.
     */
    public function maybe_cache_response($response, $server, $request) {
        if ($this->should_not_cache($request)) {
            return $response;
        }

        // Only cache 200 JSON-ish responses
        $status = (int) $response->get_status();
        if ($status !== 200) {
            return $response;
        }

        $ttl = $this->get_ttl_for_endpoint($request->get_route());
        $cache_key = $this->generate_cache_key($request);

        $data = $response->get_data();

        // Prepare headers to keep (avoid echoing hop-by-hop or varying auth headers)
        $headers = $this->filter_response_headers($response->get_headers());

        // Add strong caching headers
        $etag = $this->etag_prefix . md5(wp_json_encode($data));
        $headers['ETag'] = $etag;

        // Respect existing Cache-Control if present; otherwise set a sane one.
        if (empty($headers['Cache-Control'])) {
            // Clients can use this; server-side TTL remains $ttl
            $headers['Cache-Control'] = 'public, max-age=' . max(0, (int) $this->max_age);
        }

        // Apply headers to the live response
        foreach ($headers as $k => $v) {
            $response->header($k, $v);
        }
        $response->header('X-Cache', 'MISS');

        // Save in cache
        $this->set($cache_key, [
            'data'    => $data,
            'status'  => $status,
            'headers' => $headers,
            'saved_at'=> time(),
        ], $ttl);

        return $response;
    }

    /**
     * Decide if this request should skip cache (auth, unsafe, not our namespace, etc).
     */
    private function should_not_cache(WP_REST_Request $request): bool {
        if ($request->get_method() !== 'GET') {
            return true;
        }
        $route = $request->get_route();
        if (strpos($route, '/affcd/') === false) {
            return true;
        }

        // Explicit no-cache
        $params = $request->get_query_params();
        if (!empty($params['nocache'])) {
            return true;
        }

        // Respect Cache-Control: no-store from client
        $cc = $request->get_header('Cache-Control');
        if ($cc && stripos($cc, 'no-store') !== false) {
            return true;
        }

        // Avoid caching when user context is present
        if (is_user_logged_in()) {
            return true;
        }

        // Avoid caching when auth headers/cookies exist
        if ($request->get_header('Authorization') || $request->get_header('X-WP-Nonce')) {
            return true;
        }
        // Common auth cookies
        foreach ($_COOKIE as $name => $v) {
            if (stripos($name, 'wordpress_logged_in') !== false || stripos($name, 'wp-settings') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * -- Cache backend helpers ------------------------------------------------
     */
    public function get($key) {
        if (function_exists('wp_cache_get')) {
            return wp_cache_get($key, $this->cache_group);
        }
        return get_transient($this->cache_group . '_' . $key);
    }

    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?: $this->default_ttl;
        if (function_exists('wp_cache_set')) {
            return wp_cache_set($key, $data, $this->cache_group, $ttl);
        }
        return set_transient($this->cache_group . '_' . $key, $data, $ttl);
    }

    public function delete($key) {
        if (function_exists('wp_cache_delete')) {
            return wp_cache_delete($key, $this->cache_group);
        }
        return delete_transient($this->cache_group . '_' . $key);
    }

    /**
     * Flush entire group (best-effort fallback for transients).
     */
    public function flush() {
        // Some object-cache drop-ins offer this:
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group($this->cache_group);
        }

        // Fallback: remove transients with our prefix
        global $wpdb;
        $prefix = '_transient_' . $this->cache_group . '_';
        $like   = $wpdb->esc_like($prefix) . '%';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like
        ));
        return true;
    }

    /**
     * -- Keying / policy helpers ---------------------------------------------
     */
    private function generate_cache_key(WP_REST_Request $request): string {
        $route  = $request->get_route();

        // Stable sort query params for consistent keys
        $qs = (array) $request->get_query_params();
        ksort($qs);

        // Include a smidge of caller identity without storing secrets
        $api_key = $request->get_header('X-API-Key');
        $auth    = $request->get_header('Authorization');
        $api_tag = $api_key ? substr(md5($api_key), 0, 8) :
                   ($auth ? substr(md5($auth), 0, 8) : 'anon');

        // Include site URL/locale to avoid cross-site collisions
        $site    = get_site_url();
        $locale  = get_locale();

        $parts = [
            'site'   => $site,
            'loc'    => $locale,
            'route'  => $route,
            'query'  => $qs,
            'agent'  => substr(md5($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 8),
            'apitag' => $api_tag,
        ];

        return md5(wp_json_encode($parts));
    }

    private function get_ttl_for_endpoint(string $route): int {
        // Exact route map
        $ttl_map = [
            '/affcd/v1/codes'         => 600, // 10 minutes
            '/affcd/v1/validate-code' => 60,  // 1 minute
            '/affcd/v1/health'        => 30,  // 30 seconds
        ];

        if (isset($ttl_map[$route])) {
            return (int) $ttl_map[$route];
        }

        // Pattern examples (extend as needed)
        if (strpos($route, '/affcd/v1/codes/') === 0) {
            return 300; // specific code lookups
        }

        return (int) $this->default_ttl;
    }

    /**
     * Only keep safe/cache-relevant headers.
     */
    private function filter_response_headers(array $headers): array {
        $drop = [
            'Set-Cookie', 'Cookie', 'Authorization',
            'Transfer-Encoding', 'Connection', 'Keep-Alive',
            'Proxy-Authenticate', 'Proxy-Authorization', 'TE', 'Trailer', 'Upgrade',
        ];
        $out = [];
        foreach ($headers as $k => $v) {
            if (in_array($k, $drop, true)) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * Apply a header array onto a WP_REST_Response.
     */
    private function apply_headers(WP_REST_Response $resp, array $headers): void {
        foreach ($headers as $k => $v) {
            $resp->header($k, $v);
        }
    }
}
