<?php
/**
 * CORS handler class referenced in main plugin but not implemented
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_CORS_Handler {

    /**
     * Default allowed headers merged with preflight request headers.
     * Note: header names are case-insensitive; we output canonical casing.
     * @var string[]
     */
    private $default_allowed_headers = [
        'Content-Type',
        'Authorization',
        'X-API-Key',
        'X-WP-Nonce',
        'Accept',
        'Accept-Language',
        'Cache-Control'
    ];

    /**
     * Default allowed methods for API
     * @var string[]
     */
    private $default_allowed_methods = ['GET','POST','PUT','PATCH','DELETE','OPTIONS'];

    public function __construct() {
        // Handle preflight very early in the request.
        add_action('init', [$this, 'handle_preflight'], 0);

        // Add CORS headers to REST responses (only for our namespace).
        add_action('rest_api_init', [$this, 'hook_rest_cors']);
    }

    /**
     * Preflight handler (OPTIONS). Only for our REST namespace.
     */
    public function handle_preflight() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
            return;
        }
        if (!$this->is_affcd_rest_request()) {
            return;
        }
        $this->send_cors_headers();

        // Send minimal OK and stop WP from bootstrapping further.
        if (!headers_sent()) {
            header('Content-Length: 0');
            header('HTTP/1.1 204 No Content');
        }
        exit;
    }

    /**
     * Attach filter to add CORS headers to normal REST responses.
     */
    public function hook_rest_cors() {
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers_to_response'], 10, 4);
    }

    /**
     * REST response CORS header injector.
     */
    public function add_cors_headers_to_response($served, $result, $request, $server) {
        if (!$this->is_affcd_rest_request()) {
            return $served;
        }
        $this->send_cors_headers();
        return $served;
    }

    /**
     * Determine if current request targets our plugin's REST routes.
     */
    private function is_affcd_rest_request(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // Match /wp-json/affcd/ or any sub-route under it
        return (strpos($uri, '/wp-json/') !== false) && (strpos($uri, '/affcd/') !== false);
    }

    /**
     * Emit CORS headers when enabled and origin is allowed.
     * - Reflects a specific Origin (no "*") when credentials are allowed.
     * - Echoes back requested headers + safe defaults.
     */
    private function send_cors_headers(): void {
        if (headers_sent()) {
            return;
        }

        $settings = get_option('affcd_api_settings', []);
        $cors_enabled = $settings['enable_cors'] ?? true;
        if (!$cors_enabled) {
            return;
        }

        $origin = $this->get_request_origin();
        $allow_credentials = true; // we reflect origin; safe to allow credentials

        // Compute allow list
        // Option shape can be:
        // - 'affcd_allowed_domains' => ['https://example.com', 'https://*.example.org']
        // Backward-compatible with prior array-of-arrays ['url' => '...'].
        $allowed = get_option('affcd_allowed_domains', []);

        $is_allowed = $this->is_origin_allowed($origin, $allowed);

        // Clean prior headers if any were set by other plugins.
        @header_remove('Access-Control-Allow-Origin');
        @header_remove('Vary');

        if ($origin && $is_allowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            // So caches vary by Origin
            header('Vary: Origin', false);
            if ($allow_credentials) {
                header('Access-Control-Allow-Credentials: true');
            }
            // Cache preflight for 1 day
            header('Access-Control-Max-Age: 86400');
        } else {
            // If no/invalid origin, don't emit ACAO; browser will block.
            return;
        }

        // Methods
        $allowed_methods = $this->get_allowed_methods($settings);
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowed_methods));

        // Headers: merge defaults with requested headers, de-dup, canonicalize
        $requested_headers = $this->get_requested_headers();
        $allow_headers = $this->normalize_header_names(array_unique(array_merge($this->default_allowed_headers, $requested_headers)));

        header('Access-Control-Allow-Headers: ' . implode(', ', $allow_headers));
    }

    /**
     * Get Origin header.
     */
    private function get_request_origin(): string {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        // Some proxies place origin in lowercase key; PHP normalizes to uppercase/underscored.
        return is_string($origin) ? trim($origin) : '';
    }

    /**
     * Parse Access-Control-Request-Headers and return array of header names.
     */
    private function get_requested_headers(): array {
        $h = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        if (!$h) {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $h)));
        return $this->normalize_header_names($parts);
    }

    /**
     * Normalize header names to canonical casing (first letter upper, dash-separated).
     */
    private function normalize_header_names(array $headers): array {
        $out = [];
        foreach ($headers as $name) {
            $name = strtolower($name);
            $name = implode('-', array_map(static function($seg) {
                return $seg === '' ? '' : strtoupper($seg[0]) . substr($seg, 1);
            }, explode('-', $name)));
            $out[] = $name;
        }
        return array_values(array_unique($out));
    }

    /**
     * Return allowed methods (settings override -> default).
     */
    private function get_allowed_methods(array $settings): array {
        if (!empty($settings['cors_allowed_methods']) && is_array($settings['cors_allowed_methods'])) {
            $methods = array_map('strtoupper', $settings['cors_allowed_methods']);
            // Always include OPTIONS for preflight
            if (!in_array('OPTIONS', $methods, true)) {
                $methods[] = 'OPTIONS';
            }
            return $methods;
        }
        return $this->default_allowed_methods;
    }

    /**
     * Check if an origin is allowed against allowlist (supports wildcards like https://*.example.com).
     *
     * @param string $origin Full origin, e.g., https://sub.example.com
     * @param array  $allowlist List of strings or arrays with 'url' key
     */
    private function is_origin_allowed(string $origin, array $allowlist): bool {
        if ($origin === '') {
            return false;
        }
        $parsed = parse_url($origin);
        $scheme = $parsed['scheme'] ?? '';
        $host   = $parsed['host'] ?? '';
        $port   = isset($parsed['port']) ? (':' . $parsed['port']) : '';

        if ($scheme === '' || $host === '') {
            return false;
        }

        foreach ($allowlist as $entry) {
            $url = is_array($entry) ? ($entry['url'] ?? '') : $entry;
            if (!$url) {
                continue;
            }
            $p = parse_url($url);
            $ascheme = $p['scheme'] ?? $scheme; // default to request scheme if missing in config
            $ahost   = $p['host'] ?? '';
            $aport   = isset($p['port']) ? (':' . $p['port']) : '';

            if ($ahost === '') {
                continue;
            }

            // Wildcard host support: *.example.com
            if (strpos($ahost, '*.') === 0) {
                $root = substr($ahost, 2); // example.com
                if ($this->host_matches_wildcard($host, $root) && $ascheme === $scheme && ($aport === '' || $aport === $port)) {
                    return true;
                }
            } else {
                // Exact host match
                if (strcasecmp($host, $ahost) === 0 && $ascheme === $scheme && ($aport === '' || $aport === $port)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Wildcard host matcher: matches sub.example.com against example.com
     * but NOT the bare root (i.e., example.com won't match *.example.com)
     */
    private function host_matches_wildcard(string $host, string $root): bool {
        if ($host === '' || $root === '') {
            return false;
        }
        if (strcasecmp($host, $root) === 0) {
            return false; // bare root shouldn't match wildcard
        }
        // Ends with ".root"
        return (bool) preg_match('/\.'.preg_quote($root, '/').'$/i', $host);
    }
}
