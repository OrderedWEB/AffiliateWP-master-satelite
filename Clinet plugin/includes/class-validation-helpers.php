<?php
/**
 * Validation Helpers PHP Class
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-validation-helpers.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Validation_Helpers {

    /**
     * Validation rules
     */
    private static $rules = [];

    /**
     * Error messages
     */
    private static $messages = [];

    /**
     * Initialize validation helpers
     */
    public static function init() {
        self::load_default_rules();
        self::load_default_messages();
        
        // AJAX handlers
        add_action('wp_ajax_aci_validate_affiliate', [__CLASS__, 'ajax_validate_affiliate']);
        add_action('wp_ajax_nopriv_aci_validate_affiliate', [__CLASS__, 'ajax_validate_affiliate']);
        add_action('wp_ajax_aci_validate_field', [__CLASS__, 'ajax_validate_field']);
        add_action('wp_ajax_nopriv_aci_validate_field', [__CLASS__, 'ajax_validate_field']);
    }

    /**
     * Load default validation rules
     */
    private static function load_default_rules() {
        self::$rules = [
            'required' => [
                'callback' => [__CLASS__, 'validate_required'],
                'message' => 'This field is required'
            ],
            'email' => [
                'callback' => [__CLASS__, 'validate_email'],
                'message' => 'Please enter a valid email address'
            ],
            'url' => [
                'callback' => [__CLASS__, 'validate_url'],
                'message' => 'Please enter a valid URL'
            ],
            'phone' => [
                'callback' => [__CLASS__, 'validate_phone'],
                'message' => 'Please enter a valid phone number'
            ],
            'numeric' => [
                'callback' => [__CLASS__, 'validate_numeric'],
                'message' => 'Please enter a valid number'
            ],
            'integer' => [
                'callback' => [__CLASS__, 'validate_integer'],
                'message' => 'Please enter a whole number'
            ],
            'min_length' => [
                'callback' => [__CLASS__, 'validate_min_length'],
                'message' => 'Must be at least {param} characters long'
            ],
            'max_length' => [
                'callback' => [__CLASS__, 'validate_max_length'],
                'message' => 'Must be no more than {param} characters long'
            ],
            'min_value' => [
                'callback' => [__CLASS__, 'validate_min_value'],
                'message' => 'Value must be at least {param}'
            ],
            'max_value' => [
                'callback' => [__CLASS__, 'validate_max_value'],
                'message' => 'Value must be no more than {param}'
            ],
            'pattern' => [
                'callback' => [__CLASS__, 'validate_pattern'],
                'message' => 'Invalid format'
            ],
            'affiliate_code' => [
                'callback' => [__CLASS__, 'validate_affiliate_code'],
                'message' => 'Invalid affiliate code format'
            ],
            'credit_card' => [
                'callback' => [__CLASS__, 'validate_credit_card'],
                'message' => 'Please enter a valid credit card number'
            ],
            'cvv' => [
                'callback' => [__CLASS__, 'validate_cvv'],
                'message' => 'Please enter a valid CVV'
            ],
            'expiry_date' => [
                'callback' => [__CLASS__, 'validate_expiry_date'],
                'message' => 'Please enter a valid expiry date (MM/YY)'
            ],
            'postal_code' => [
                'callback' => [__CLASS__, 'validate_postal_code'],
                'message' => 'Please enter a valid postal code'
            ]
        ];
    }

    /**
     * Load default error messages
     */
    private static function load_default_messages() {
        self::$messages = [
            'validation_failed' => __('Validation failed', 'affiliate-client-integration'),
            'invalid_affiliate_code' => __('Invalid or expired affiliate code', 'affiliate-client-integration'),
            'affiliate_code_applied' => __('Affiliate code applied successfully', 'affiliate-client-integration'),
            'field_required' => __('This field is required', 'affiliate-client-integration'),
            'invalid_format' => __('Invalid format', 'affiliate-client-integration')
        ];
    }

    /**
     * Validate a single field
     */
    public static function validate_field($value, $rules, $field_name = '') {
        $errors = [];
        
        if (empty($rules)) {
            return ['valid' => true, 'errors' => []];
        }

        $rule_list = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($rule_list as $rule) {
            $rule_parts = explode(':', $rule);
            $rule_name = $rule_parts[0];
            $rule_params = isset($rule_parts[1]) ? explode(',', $rule_parts[1]) : [];

            if (!isset(self::$rules[$rule_name])) {
                continue;
            }

            $rule_config = self::$rules[$rule_name];
            $is_valid = call_user_func($rule_config['callback'], $value, $rule_params, $field_name);

            if (!$is_valid) {
                $message = $rule_config['message'];
                
                // Replace parameter placeholders
                if (!empty($rule_params)) {
                    $message = str_replace('{param}', $rule_params[0], $message);
                }
                
                $errors[] = $message;
                break; // Stop at first error
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate multiple fields
     */
    public static function validate_fields($data, $rules) {
        $results = [];
        $all_valid = true;

        foreach ($rules as $field_name => $field_rules) {
            $value = $data[$field_name] ?? '';
            $result = self::validate_field($value, $field_rules, $field_name);
            
            $results[$field_name] = $result;
            
            if (!$result['valid']) {
                $all_valid = false;
            }
        }

        return [
            'valid' => $all_valid,
            'fields' => $results
        ];
    }

    /**
     * Validate affiliate code with master domain
     */
    public static function validate_affiliate_code_remote($code, $context = []) {
        $master_domain = get_option('aci_master_domain', '');
        $api_key = get_option('aci_api_key', '');

        if (empty($master_domain) || empty($api_key)) {
            return [
                'valid' => false,
                'error' => 'Master domain not configured',
                'code' => 'no_configuration'
            ];
        }

        // Check cache first
        $cache_key = 'aci_affiliate_validation_' . md5($code);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Make API request
        $response = wp_remote_post($master_domain . '/wp-json/affcd/v1/validate', [
            'headers' => [
                'authorisation' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'X-Domain' => home_url()
            ],
            'body' => json_encode([
                'affiliate_code' => $code,
                'context' => $context,
                'timestamp' => current_time('mysql')
            ]),
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message(),
                'code' => 'network_error'
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return [
                'valid' => false,
                'error' => $body['message'] ?? 'Validation failed',
                'code' => 'api_error'
            ];
        }

        $result = [
            'valid' => !empty($body['data']['valid']),
            'data' => $body['data'] ?? [],
            'discount' => $body['data']['discount'] ?? null,
            'affiliate_info' => $body['data']['affiliate'] ?? null
        ];

        // Cache result for 5 minutes
        set_transient($cache_key, $result, 300);

        return $result;
    }

    /**
     * Validation rule callbacks
     */
    public static function validate_required($value, $params = [], $field_name = '') {
        return !empty(trim($value));
    }

    public static function validate_email($value, $params = [], $field_name = '') {
        if (empty($value)) return true; // Let required rule handle empty values
        return is_email($value);
    }

    public static function validate_url($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function validate_phone($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $value);
        
        // Check length (7-15 digits)
        return strlen($phone) >= 7 && strlen($phone) <= 15;
    }

    public static function validate_numeric($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        return is_numeric($value);
    }

    public static function validate_integer($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public static function validate_min_length($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        $min_length = intval($params[0] ?? 0);
        return strlen($value) >= $min_length;
    }

    public static function validate_max_length($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        $max_length = intval($params[0] ?? 999999);
        return strlen($value) <= $max_length;
    }

    public static function validate_min_value($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        $min_value = floatval($params[0] ?? 0);
        return floatval($value) >= $min_value;
    }

    public static function validate_max_value($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        $max_value = floatval($params[0] ?? 999999);
        return floatval($value) <= $max_value;
    }

    public static function validate_pattern($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        $pattern = $params[0] ?? '';
        if (empty($pattern)) return true;
        
        return preg_match('/' . $pattern . '/', $value) === 1;
    }

    public static function validate_affiliate_code($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        
        // Basic format validation
        // Allow alphanumeric, hyphens, underscores, 2-50 characters
        return preg_match('/^[a-zA-Z0-9_-]{2,50}$/', $value) === 1;
    }

    public static function validate_credit_card($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        
        // Remove spaces and hyphens
        $number = preg_replace('/[\s-]/', '', $value);
        
        // Check if all digits and length
        if (!preg_match('/^\d{13,19}$/', $number)) {
            return false;
        }
        
        // Luhn algorithm check
        return self::luhn_check($number);
    }

    public static function validate_cvv($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        return preg_match('/^\d{3,4}$/', $value) === 1;
    }

    public static function validate_expiry_date($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        
        // Expected format: MM/YY or MM/YYYY
        if (!preg_match('/^(\d{2})\/(\d{2,4})$/', $value, $matches)) {
            return false;
        }
        
        $month = intval($matches[1]);
        $year = intval($matches[2]);
        
        // Convert 2-digit year to 4-digit
        if ($year < 100) {
            $year += 2000;
        }
        
        // Validate month
        if ($month < 1 || $month > 12) {
            return false;
        }
        
        // Check if date is in the future
        $expiry_date = mktime(0, 0, 0, $month + 1, 1, $year); // First day of next month
        return $expiry_date > time();
    }

    public static function validate_postal_code($value, $params = [], $field_name = '') {
        if (empty($value)) return true;
        
        $country = $params[0] ?? 'US';
        
        $patterns = [
            'US' => '/^\d{5}(-\d{4})?$/',
            'CA' => '/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/',
            'GB' => '/^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/i',
            'DE' => '/^\d{5}$/',
            'FR' => '/^\d{5}$/',
            'AU' => '/^\d{4}$/',
            'NL' => '/^\d{4} ?[A-Z]{2}$/i'
        ];
        
        $pattern = $patterns[$country] ?? $patterns['US'];
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Luhn algorithm for credit card validation
     */
    private static function luhn_check($number) {
        $sum = 0;
        $is_even = false;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = intval($number[$i]);
            
            if ($is_even) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
            $is_even = !$is_even;
        }
        
        return $sum % 10 === 0;
    }

    /**
     * AJAX handler for affiliate validation
     */
    public static function ajax_validate_affiliate() {
        check_ajax_referer('aci_validate_affiliate', 'nonce');

        $affiliate_code = sanitize_text_field($_POST['affiliate_code'] ?? '');
        
        if (empty($affiliate_code)) {
            wp_send_json_error(__('Affiliate code is required', 'affiliate-client-integration'));
        }

        // Basic format validation first
        $format_validation = self::validate_field($affiliate_code, 'required|affiliate_code');
        
        if (!$format_validation['valid']) {
            wp_send_json_error($format_validation['errors'][0]);
        }

        // Remote validation
        $context = [
            'url' => $_POST['url'] ?? '',
            'referrer' => $_POST['referrer'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        $validation_result = self::validate_affiliate_code_remote($affiliate_code, $context);

        if ($validation_result['valid']) {
            // Store in session
            if (class_exists('ACI_Session_Manager')) {
                $session_manager = new ACI_Session_Manager();
                $session_manager->set_affiliate_data($affiliate_code, [
                    'validation_data' => $validation_result['data'],
                    'timestamp' => time(),
                    'context' => $context
                ]);
            }

            wp_send_json_success([
                'message' => self::$messages['affiliate_code_applied'],
                'valid' => true,
                'data' => $validation_result['data'],
                'discount' => $validation_result['discount']
            ]);
        } else {
            wp_send_json_error($validation_result['error'] ?? self::$messages['invalid_affiliate_code']);
        }
    }

    /**
     * AJAX handler for field validation
     */
    public static function ajax_validate_field() {
        check_ajax_referer('aci_validate_field', 'nonce');

        $field_name = sanitize_text_field($_POST['field_name'] ?? '');
        $field_value = $_POST['field_value'] ?? '';
        $validation_rules = sanitize_text_field($_POST['rules'] ?? '');

        if (empty($field_name) || empty($validation_rules)) {
            wp_send_json_error('Missing required parameters');
        }

        $result = self::validate_field($field_value, $validation_rules, $field_name);

        wp_send_json_success($result);
    }

    /**
     * Add custom validation rule
     */
    public static function add_rule($name, $callback, $message) {
        self::$rules[$name] = [
            'callback' => $callback,
            'message' => $message
        ];
    }

    /**
     * Get validation rule
     */
    public static function get_rule($name) {
        return self::$rules[$name] ?? null;
    }

    /**
     * Remove validation rule
     */
    public static function remove_rule($name) {
        unset(self::$rules[$name]);
    }

    /**
     * Get all validation rules
     */
    public static function get_all_rules() {
        return self::$rules;
    }

    /**
     * Sanitize and validate form data
     */
    public static function sanitize_and_validate($data, $rules, $sanitize_rules = []) {
        $Sanitized_data = [];
        $validation_results = [];
        
        foreach ($data as $field_name => $value) {
            // Sanitize first
            if (isset($sanitize_rules[$field_name])) {
                $sanitize_function = $sanitize_rules[$field_name];
                $Sanitized_data[$field_name] = call_user_func($sanitize_function, $value);
            } else {
                // Default sanitization
                $Sanitized_data[$field_name] = sanitize_text_field($value);
            }
            
            // Then validate
            if (isset($rules[$field_name])) {
                $validation_results[$field_name] = self::validate_field(
                    $Sanitized_data[$field_name], 
                    $rules[$field_name], 
                    $field_name
                );
            }
        }
        
        $all_valid = true;
        foreach ($validation_results as $result) {
            if (!$result['valid']) {
                $all_valid = false;
                break;
            }
        }
        
        return [
            'valid' => $all_valid,
            'data' => $Sanitized_data,
            'validation' => $validation_results
        ];
    }

    /**
     * Generate validation JavaScript configuration
     */
    public static function get_js_config() {
        $rules_for_js = [];
        
        foreach (self::$rules as $name => $rule) {
            $rules_for_js[$name] = [
                'message' => $rule['message']
            ];
        }
        
        return [
            'rules' => $rules_for_js,
            'messages' => self::$messages
        ];
    }
}