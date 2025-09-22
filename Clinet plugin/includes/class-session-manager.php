<?php
/**
 * Session Manager Class
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-session-manager.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Session_Manager {

    /**
     * Session prefix
     */
    private $prefix = 'aci_';

    /**
     * Session expiry time (24 hours)
     */
    private $session_expiry = 86400;

    /**
     * Current session ID
     */
    private $session_id;

    /**
     * Session data
     */
    private $session_data = [];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init_session'], 1);
        add_action('wp_logout', [$this, 'destroy_session']);
        add_action('wp_login', [$this, 'regenerate_session_id'], 10, 2);
        add_action('aci_cleanup_sessions', [$this, 'cleanup_expired_sessions']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('aci_cleanup_sessions')) {
            wp_schedule_event(time(), 'daily', 'aci_cleanup_sessions');
        }
    }

    /**
     * Initialse session
     */
    public function init_session() {
        if (headers_sent()) {
            return;
        }

        // Get or create session ID
        $this->session_id = $this->get_session_id();
        
        // Load session data
        $this->load_session_data();
        
        // Set session cookie if needed
        if (!isset($_COOKIE[$this->get_cookie_name()])) {
            $this->set_session_cookie();
        }
    }

    /**
     * Get session ID
     */
    private function get_session_id() {
        // Check cookie first
        $cookie_name = $this->get_cookie_name();
        if (isset($_COOKIE[$cookie_name])) {
            $session_id = sanitize_text_field($_COOKIE[$cookie_name]);
            if ($this->validate_session_id($session_id)) {
                return $session_id;
            }
        }

        // Generate new session ID
        return $this->generate_session_id();
    }

    /**
     * Generate new session ID
     */
    private function generate_session_id() {
        return wp_generate_password(32, false);
    }

    /**
     * Validate session ID format
     */
    private function validate_session_id($session_id) {
        return !empty($session_id) && preg_match('/^[a-zA-Z0-9]{32}$/', $session_id);
    }

    /**
     * Get cookie name
     */
    private function get_cookie_name() {
        return $this->prefix . 'session_' . COOKIEHASH;
    }

    /**
     * Set session cookie
     */
    private function set_session_cookie() {
        $cookie_name = $this->get_cookie_name();
        $secure = is_ssl();
        $httponly = true;
        $samesite = 'Lax';
        
        setcookie(
            $cookie_name,
            $this->session_id,
            time() + $this->session_expiry,
            COOKIEPATH,
            COOKIE_DOMAIN,
            $secure,
            $httponly
        );
    }

    /**
     * Load session data from database
     */
    private function load_session_data() {
        if (empty($this->session_id)) {
            return;
        }

        $session_key = $this->prefix . 'session_data_' . $this->session_id;
        $session_data = get_option($session_key, []);
        
        // Check if session has expired
        if (!empty($session_data['expires']) && $session_data['expires'] < time()) {
            $this->destroy_session();
            return;
        }

        $this->session_data = $session_data['data'] ?? [];
    }

    /**
     * Save session data to database
     */
    private function save_session_data() {
        if (empty($this->session_id)) {
            return false;
        }

        $session_key = $this->prefix . 'session_data_' . $this->session_id;
        $session_value = [
            'data' => $this->session_data,
            'expires' => time() + $this->session_expiry,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'last_activity' => time()
        ];

        return update_option($session_key, $session_value);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Set session variable
     */
    public function set($key, $value) {
        $this->session_data[$key] = $value;
        $this->save_session_data();
    }

    /**
     * Get session variable
     */
    public function get($key, $default = null) {
        return $this->session_data[$key] ?? $default;
    }

    /**
     * Check if session variable exists
     */
    public function has($key) {
        return isset($this->session_data[$key]);
    }

    /**
     * Remove session variable
     */
    public function remove($key) {
        if (isset($this->session_data[$key])) {
            unset($this->session_data[$key]);
            $this->save_session_data();
        }
    }

    /**
     * Get all session data
     */
    public function all() {
        return $this->session_data;
    }

    /**
     * Clear all session data
     */
    public function clear() {
        $this->session_data = [];
        $this->save_session_data();
    }

    /**
     * Destroy session completely
     */
    public function destroy_session() {
        if (!empty($this->session_id)) {
            // Remove from database
            $session_key = $this->prefix . 'session_data_' . $this->session_id;
            delete_option($session_key);
            
            // Clear cookie
            $cookie_name = $this->get_cookie_name();
            setcookie($cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
        
        $this->session_data = [];
        $this->session_id = null;
    }

    /**
     * Regenerate session ID
     */
    public function regenerate_session_id($user_login = '', $user = null) {
        $old_session_id = $this->session_id;
        $old_data = $this->session_data;
        
        // Generate new session ID
        $this->session_id = $this->generate_session_id();
        $this->session_data = $old_data;
        
        // Save new session
        $this->save_session_data();
        $this->set_session_cookie();
        
        // Remove old session
        if ($old_session_id) {
            $old_session_key = $this->prefix . 'session_data_' . $old_session_id;
            delete_option($old_session_key);
        }
    }

    /**
     * Set affiliate tracking data
     */
    public function set_affiliate_data($affiliate_code, $source_data = []) {
        $affiliate_data = [
            'code' => $affiliate_code,
            'timestamp' => time(),
            'source_url' => $_SERVER['HTTP_REFERER'] ?? '',
            'landing_page' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip()
        ];
        
        // Merge with additional source data
        $affiliate_data = array_merge($affiliate_data, $source_data);
        
        $this->set('affiliate_data', $affiliate_data);
        $this->set('affiliate_code', $affiliate_code);
        
        do_action('aci_affiliate_tracking_set', $affiliate_code, $affiliate_data);
    }

    /**
     * Get affiliate tracking data
     */
    public function get_affiliate_data() {
        return $this->get('affiliate_data', []);
    }

    /**
     * Get current affiliate code
     */
    public function get_affiliate_code() {
        return $this->get('affiliate_code', '');
    }

    /**
     * Check if affiliate is tracked
     */
    public function has_affiliate() {
        return !empty($this->get_affiliate_code());
    }

    /**
     * Clear affiliate tracking
     */
    public function clear_affiliate_data() {
        $this->remove('affiliate_data');
        $this->remove('affiliate_code');
        
        do_action('aci_affiliate_tracking_cleared');
    }

    /**
     * Set cart data
     */
    public function set_cart_data($cart_data) {
        $this->set('cart_data', $cart_data);
        $this->set('cart_updated', time());
    }

    /**
     * Get cart data
     */
    public function get_cart_data() {
        return $this->get('cart_data', []);
    }

    /**
     * Add item to cart
     */
    public function add_cart_item($item) {
        $cart_data = $this->get_cart_data();
        $cart_data[] = $item;
        $this->set_cart_data($cart_data);
    }

    /**
     * Remove item from cart
     */
    public function remove_cart_item($index) {
        $cart_data = $this->get_cart_data();
        if (isset($cart_data[$index])) {
            unset($cart_data[$index]);
            $cart_data = array_values($cart_data); // Re-index array
            $this->set_cart_data($cart_data);
        }
    }

    /**
     * Clear cart
     */
    public function clear_cart() {
        $this->remove('cart_data');
        $this->remove('cart_updated');
    }

    /**
     * Set user preferences
     */
    public function set_user_preferences($preferences) {
        $current_prefs = $this->get('user_preferences', []);
        $updated_prefs = array_merge($current_prefs, $preferences);
        $this->set('user_preferences', $updated_prefs);
    }

    /**
     * Get user preferences
     */
    public function get_user_preferences() {
        return $this->get('user_preferences', []);
    }

    /**
     * Set form data for persistence
     */
    public function set_form_data($form_id, $data) {
        $form_data = $this->get('form_data', []);
        $form_data[$form_id] = [
            'data' => $data,
            'timestamp' => time()
        ];
        $this->set('form_data', $form_data);
    }

    /**
     * Get form data
     */
    public function get_form_data($form_id) {
        $form_data = $this->get('form_data', []);
        return $form_data[$form_id]['data'] ?? [];
    }

    /**
     * Clear old form data (older than 1 hour)
     */
    public function cleanup_form_data() {
        $form_data = $this->get('form_data', []);
        $cutoff_time = time() - 3600; // 1 hour ago
        
        foreach ($form_data as $form_id => $data) {
            if ($data['timestamp'] < $cutoff_time) {
                unset($form_data[$form_id]);
            }
        }
        
        $this->set('form_data', $form_data);
    }

    /**
     * Set flash message
     */
    public function set_flash($type, $message) {
        $flash_data = $this->get('flash_messages', []);
        $flash_data[] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];
        $this->set('flash_messages', $flash_data);
    }

    /**
     * Get and clear flash messages
     */
    public function get_flash_messages() {
        $messages = $this->get('flash_messages', []);
        $this->remove('flash_messages');
        return $messages;
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        
        $cutoff_time = time() - $this->session_expiry;
        $session_prefix = $this->prefix . 'session_data_';
        
        // Get all session options
        $session_options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $session_prefix . '%'
        ));
        
        $cleaned_count = 0;
        
        foreach ($session_options as $option) {
            $session_data = get_option($option->option_name);
            
            if (is_array($session_data) && 
                isset($session_data['expires']) && 
                $session_data['expires'] < time()) {
                
                delete_option($option->option_name);
                $cleaned_count++;
            }
        }
        
        if ($cleaned_count > 0) {
            error_log("ACI Session Manager: Cleaned up {$cleaned_count} expired sessions");
        }
    }

    /**
     * Get session statistics
     */
    public function get_session_stats() {
        global $wpdb;
        
        $session_prefix = $this->prefix . 'session_data_';
        
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $session_prefix . '%'
        ));
        
        return [
            'total_sessions' => intval($total_sessions),
            'current_session_id' => $this->session_id,
            'session_expiry' => $this->session_expiry,
            'data_size' => strlen(serialize($this->session_data))
        ];
    }

    /**
     * Validate session security
     */
    public function validate_session_security() {
        if (empty($this->session_id)) {
            return false;
        }
        
        $session_key = $this->prefix . 'session_data_' . $this->session_id;
        $session_data = get_option($session_key, []);
        
        // Check IP address if enabled
        $check_ip = get_option('aci_session_check_ip', false);
        if ($check_ip && !empty($session_data['ip_address'])) {
            if ($session_data['ip_address'] !== $this->get_client_ip()) {
                $this->destroy_session();
                return false;
            }
        }
        
        // Check user agent if enabled
        $check_ua = get_option('aci_session_check_user_agent', true);
        if ($check_ua && !empty($session_data['user_agent'])) {
            $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($session_data['user_agent'] !== $current_ua) {
                $this->destroy_session();
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get current session ID
     */
    public function get_current_session_id() {
        return $this->session_id;
    }

    /**
     * Check if session is active
     */
    public function is_active() {
        return !empty($this->session_id) && !empty($this->session_data);
    }

    /**
     * Extend session expiry
     */
    public function extend_session() {
        if (!empty($this->session_id)) {
            $this->save_session_data();
            $this->set_session_cookie();
        }
    }
}