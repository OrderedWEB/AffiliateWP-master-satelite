<?php
/**
 * Affiliate Client Full Configuration
 *
 * Configuration settings for the affiliate client plugin.
 * This file centralizes all configuration options and provides
 * default values for the plugin functionality.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin configuration
 *
 * @return array Configuration array
 */
function affiliate_client_full_get_config() {
    
    // Get saved options from database
    $remote_url = get_option('affiliate_client_remote_url', '');
    $api_key = get_option('affiliate_client_api_key', '');
    $tracking_enabled = get_option('affiliate_client_tracking_enabled', true);
    $debug_mode = get_option('affiliate_client_debug_mode', false);
    $cookie_expiry = get_option('affiliate_client_cookie_expiry', 30 * DAY_IN_SECONDS);
    
    return [
        // Remote connection settings
        'remote_url' => trailingslashit($remote_url),
        'api_key' => $api_key,
        'api_version' => 'v1',
        'api_timeout' => 30,
        
        // Tracking settings
        'tracking_enabled' => $tracking_enabled,
        'cookie_name' => 'affiliate_client_ref',
        'cookie_expiry' => $cookie_expiry,
        'cookie_path' => '/',
        'cookie_domain' => parse_url(home_url(), PHP_URL_HOST),
        'cookie_secure' => is_ssl(),
        'cookie_httponly' => false, // Needs to be accessible via JavaScript
        
        // Debug and logging
        'debug_mode' => $debug_mode,
        'log_level' => $debug_mode ? 'debug' : 'error',
        'max_log_entries' => 1000,
        
        // API rate limiting
        'api_rate_limit' => 100, // requests per hour
        'api_retry_attempts' => 3,
        'api_retry_delay' => 2, // seconds
        
        // Data sync settings
        'sync_interval' => 'hourly',
        'sync_batch_size' => 100,
        'sync_retry_limit' => 5,
        'data_retention_days' => 30,
        
        // Tracking events configuration
        'default_events' => [
            'page_view' => [
                'enabled' => true,
                'priority' => 10,
                'data' => ['url', 'title', 'referrer'],
            ],
            'click' => [
                'enabled' => true,
                'priority' => 20,
                'data' => ['element', 'url', 'position'],
            ],
            'form_submit' => [
                'enabled' => true,
                'priority' => 30,
                'data' => ['form_id', 'form_data'],
            ],
            'purchase' => [
                'enabled' => true,
                'priority' => 40,
                'data' => ['amount', 'currency', 'products'],
            ],
            'signup' => [
                'enabled' => true,
                'priority' => 50,
                'data' => ['user_id', 'user_email'],
            ],
        ],
        
        // Addon integrations
        'supported_addons' => [
            'woocommerce' => [
                'enabled' => class_exists('WooCommerce'),
                'hooks' => [
                    'woocommerce_thankyou' => 'track_purchase',
                    'woocommerce_add_to_cart' => 'track_add_to_cart',
                ],
            ],
            'easy_digital_downloads' => [
                'enabled' => class_exists('Easy_Digital_Downloads'),
                'hooks' => [
                    'edd_complete_purchase' => 'track_purchase',
                    'edd_post_add_to_cart' => 'track_add_to_cart',
                ],
            ],
            'memberpress' => [
                'enabled' => defined('MEPR_PLUGIN_SLUG'),
                'hooks' => [
                    'mepr-event-transaction-completed' => 'track_membership_purchase',
                    'mepr-event-member-signup' => 'track_member_signup',
                ],
            ],
            'lifter_lms' => [
                'enabled' => class_exists('LifterLMS'),
                'hooks' => [
                    'llms_user_enrolled_in_course' => 'track_course_enrollment',
                    'lifterlms_order_complete' => 'track_course_purchase',
                ],
            ],
            'gravity_forms' => [
                'enabled' => class_exists('GFForms'),
                'hooks' => [
                    'gform_after_submission' => 'track_form_submission',
                ],
            ],
            'contact_form_7' => [
                'enabled' => defined('WPCF7_VERSION'),
                'hooks' => [
                    'wpcf7_mail_sent' => 'track_form_submission',
                ],
            ],
        ],
        
        // Security settings
        'encrypt_data' => true,
        'validate_origin' => true,
        'allowed_origins' => [home_url()],
        'nonce_lifetime' => 12 * HOUR_IN_SECONDS,
        
        // Performance settings
        'cache_affiliate_data' => true,
        'cache_duration' => 15 * MINUTE_IN_SECONDS,
        'lazy_load_tracking' => true,
        'minify_js' => !defined('WP_DEBUG') || !WP_DEBUG,
        
        // Frontend display settings
        'show_debug_info' => $debug_mode && current_user_can('manage_options'),
        'console_logging' => $debug_mode,
        'visual_debug' => false,
        
        // Database settings
        'table_prefix' => 'affiliate_client_',
        'use_custom_tables' => true,
        'cleanup_frequency' => 'daily',
        'max_log_age_days' => 30,
        
        // Error handling
        'fail_silently' => true, // Don't break site if tracking fails
        'error_reporting' => $debug_mode,
        'email_errors' => false,
        'admin_email' => get_option('admin_email'),
        
        // Feature flags
        'features' => [
            'enhanced_tracking' => true,
            'conversion_attribution' => true,
            'multi_touch_attribution' => false,
            'offline_tracking' => true,
            'cross_device_tracking' => false,
            'gdpr_compliance' => true,
        ],
        
        // GDPR/Privacy settings
        'privacy' => [
            'anonymize_ips' => true,
            'respect_dnt' => true, // Do Not Track header
            'cookie_consent_required' => false,
            'data_retention_days' => 365,
            'allow_data_export' => true,
            'allow_data_deletion' => true,
        ],
        
        // Localization
        'text_domain' => 'affiliate-client-full',
        'languages_dir' => 'languages',
        
        // Plugin metadata
        'version' => AFFILIATE_CLIENT_FULL_VERSION,
        'plugin_file' => AFFILIATE_CLIENT_FULL_PLUGIN_FILE,
        'plugin_dir' => AFFILIATE_CLIENT_FULL_PLUGIN_DIR,
        'plugin_url' => AFFILIATE_CLIENT_FULL_PLUGIN_URL,
        'plugin_basename' => AFFILIATE_CLIENT_FULL_PLUGIN_BASENAME,
    ];
}

/**
 * Get specific configuration value
 *
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function affiliate_client_full_get_config_value($key, $default = null) {
    $config = affiliate_client_full_get_config();
    
    // Support dot notation for nested values
    if (strpos($key, '.') !== false) {
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * Update configuration value
 *
 * @param string $key Configuration key
 * @param mixed $value New value
 * @return bool Success status
 */
function affiliate_client_full_update_config_value($key, $value) {
    switch ($key) {
        case 'remote_url':
            return update_option('affiliate_client_remote_url', $value);
        case 'api_key':
            return update_option('affiliate_client_api_key', $value);
        case 'tracking_enabled':
            return update_option('affiliate_client_tracking_enabled', $value);
        case 'debug_mode':
            return update_option('affiliate_client_debug_mode', $value);
        case 'cookie_expiry':
            return update_option('affiliate_client_cookie_expiry', $value);
        default:
            return update_option('affiliate_client_' . $key, $value);
    }
}

/**
 * Get addon configuration
 *
 * @param string $addon_slug Addon slug
 * @return array|false Addon configuration or false if not found
 */
function affiliate_client_full_get_addon_config($addon_slug) {
    $config = affiliate_client_full_get_config();
    $addons = $config['supported_addons'];
    
    return isset($addons[$addon_slug]) ? $addons[$addon_slug] : false;
}

/**
 * Check if addon is enabled and available
 *
 * @param string $addon_slug Addon slug
 * @return bool True if addon is enabled and available
 */
function affiliate_client_full_is_addon_enabled($addon_slug) {
    $addon_config = affiliate_client_full_get_addon_config($addon_slug);
    return $addon_config && $addon_config['enabled'];
}

/**
 * Get tracking events configuration
 *
 * @return array Tracking events configuration
 */
function affiliate_client_full_get_tracking_events() {
    $config = affiliate_client_full_get_config();
    $default_events = $config['default_events'];
    
    // Allow filtering of events
    return apply_filters('affiliate_client_tracking_events', $default_events);
}

/**
 * Check if specific tracking event is enabled
 *
 * @param string $event_type Event type to check
 * @return bool True if event is enabled
 */
function affiliate_client_full_is_event_enabled($event_type) {
    $events = affiliate_client_full_get_tracking_events();
    return isset($events[$event_type]) && $events[$event_type]['enabled'];
}

/**
 * Get API endpoint URL
 *
 * @param string $endpoint Endpoint path
 * @return string Full API URL
 */
function affiliate_client_full_get_api_url($endpoint = '') {
    $config = affiliate_client_full_get_config();
    $base_url = rtrim($config['remote_url'], '/');
    $api_version = $config['api_version'];
    
    return $base_url . '/wp-json/affcd/' . $api_version . '/' . ltrim($endpoint, '/');
}

/**
 * Get default HTTP request arguments for API calls
 *
 * @return array Default request arguments
 */
function affiliate_client_full_get_default_request_args() {
    $config = affiliate_client_full_get_config();
    
    return [
        'timeout' => $config['api_timeout'],
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateClientFull/' . $config['version'],
            'X-API-Key' => $config['api_key'],
            'X-Site-URL' => home_url(),
        ],
        'sslverify' => !$config['debug_mode'],
    ];
}

/**
 * Validate configuration
 *
 * @return array Validation results with errors
 */
function affiliate_client_full_validate_config() {
    $config = affiliate_client_full_get_config();
    $errors = [];
    
    // Check required settings
    if (empty($config['remote_url'])) {
        $errors[] = __('Remote URL is required', 'affiliate-client-full');
    } elseif (!filter_var($config['remote_url'], FILTER_VALIDATE_URL)) {
        $errors[] = __('Remote URL is not valid', 'affiliate-client-full');
    }
    
    if (empty($config['api_key'])) {
        $errors[] = __('API key is required', 'affiliate-client-full');
    } elseif (strlen($config['api_key']) < 32) {
        $errors[] = __('API key appears to be invalid (too short)', 'affiliate-client-full');
    }
    
    // Check cookie settings
    if ($config['cookie_expiry'] < DAY_IN_SECONDS) {
        $errors[] = __('Cookie expiry should be at least 1 day', 'affiliate-client-full');
    }
    
    // Check API timeout
    if ($config['api_timeout'] < 5 || $config['api_timeout'] > 120) {
        $errors[] = __('API timeout should be between 5 and 120 seconds', 'affiliate-client-full');
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Reset configuration to defaults
 *
 * @param bool $keep_connection_settings Whether to keep remote URL and API key
 * @return bool Success status
 */
function affiliate_client_full_reset_config($keep_connection_settings = true) {
    $options_to_delete = [
        'affiliate_client_tracking_enabled',
        'affiliate_client_debug_mode',
        'affiliate_client_cookie_expiry',
    ];
    
    if (!$keep_connection_settings) {
        $options_to_delete[] = 'affiliate_client_remote_url';
        $options_to_delete[] = 'affiliate_client_api_key';
    }
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    return true;
}