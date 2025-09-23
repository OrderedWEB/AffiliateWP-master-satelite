<?php
/**
 * Uninstall Script
 * File: /wp-content/plugins/affiliate-client-integration/uninstall.php
 * Plugin: Affiliate Client Integration
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data on uninstall
 */
class ACI_Uninstaller {

    /**
     * Run uninstall process
     */
    public static function uninstall() {
        // Check if we should preserve data
        $preserve_data = get_option('aci_preserve_data_on_uninstall', false);
        
        if ($preserve_data) {
            // Only clean temporary data
            self::clean_temporary_data();
            return;
        }

        // Full cleanup
        self::clean_options();
        self::clean_transients();
        self::clean_user_meta();
        self::clean_session_data();
        self::clean_logs();
        self::clean_cache();
        self::clear_scheduled_events();
        
        // Log uninstall
        error_log('ACI: Plugin uninstalled and data cleaned up');
    }

    /**
     * Clean plugin options
     */
    private static function clean_options() {
        $options_to_delete = [
            // Main settings
            'aci_master_domain',
            'aci_api_key',
            'aci_api_secret',
            'aci_cache_duration',
            'aci_enable_logging',
            'aci_auto_apply_discounts',
            
            // Display settings
            'aci_display_settings',
            'aci_popup_settings',
            
            // Integration settings
            'aci_zoho_settings',
            
            // Currency settings
            'aci_currency_symbol',
            'aci_currency_position',
            'aci_currency_decimals',
            
            // Tax settings
            'aci_tax_rates',
            'aci_shipping_taxable',
            'aci_shipping_zones',
            
            // Discount settings
            'aci_discount_rules',
            'aci_discount_codes',
            
            // Cache and performance
            'aci_last_sync',
            'aci_connection_status',
            'aci_plugin_version',
            
            // Session settings
            'aci_session_check_ip',
            'aci_session_check_user_agent',
            
            // Logging
            'aci_zoho_activity_log',
            'aci_error_log',
            
            // Preserve data flag
            'aci_preserve_data_on_uninstall'
        ];

        foreach ($options_to_delete as $option) {
            delete_option($option);
        }

        // Delete options with patterns
        global $wpdb;
        
        // Delete conversion tracking data
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aci_conversion_%'");
        
        // Delete discount usage tracking
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aci_discount_usage_%'");
        
        // Delete affiliate validation cache
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aci_affiliate_discount_%'");
    }

    /**
     * Clean transients
     */
    private static function clean_transients() {
        global $wpdb;
        
        // Delete all ACI transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aci_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aci_%'");
        
        // Delete specific transients
        $transients_to_delete = [
            'aci_zoho_access_token',
            'aci_affiliate_codes_cache',
            'aci_master_domain_status',
            'aci_api_rate_limit',
            'aci_connection_test_result'
        ];

        foreach ($transients_to_delete as $transient) {
            delete_transient($transient);
        }
    }

    /**
     * Clean user meta
     */
    private static function clean_user_meta() {
        global $wpdb;
        
        // Delete user meta related to affiliate tracking
        $user_meta_keys = [
            'aci_affiliate_referrals',
            'aci_affiliate_conversions',
            'aci_last_affiliate_code',
            'aci_affiliate_preferences'
        ];

        foreach ($user_meta_keys as $meta_key) {
            $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key]);
        }
    }

    /**
     * Clean session data
     */
    private static function clean_session_data() {
        global $wpdb;
        
        // Delete all session data
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aci_session_data_%'");
        
        // Clean up any session files if using file-based sessions
        $upload_dir = wp_upload_dir();
        $session_dir = $upload_dir['basedir'] . '/aci-sessions/';
        
        if (is_dir($session_dir)) {
            $files = glob($session_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($session_dir);
        }
    }

    /**
     * Clean logs
     */
    private static function clean_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aci-logs/';
        
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '*.log');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($log_dir);
        }
    }

    /**
     * Clean cache files
     */
    private static function clean_cache() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/aci-cache/';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($cache_dir);
        }

        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clean temporary data only
     */
    private static function clean_temporary_data() {
        // Clean transients
        self::clean_transients();
        
        // Clean session data
        self::clean_session_data();
        
        // Clean cache
        self::clean_cache();
        
        // Clean logs
        self::clean_logs();
        
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Delete temporary options
        $temp_options = [
            'aci_last_sync',
            'aci_connection_status',
            'aci_error_log',
            'aci_zoho_activity_log'
        ];

        foreach ($temp_options as $option) {
            delete_option($option);
        }
    }

    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        $scheduled_hooks = [
            'aci_cleanup_sessions',
            'aci_sync_affiliate_codes',
            'aci_cleanup_logs',
            'aci_validate_connection',
            'aci_cleanup_cache'
        ];

        foreach ($scheduled_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * Clean database tables (if any custom tables were created)
     */
    private static function clean_database_tables() {
        global $wpdb;
        
        // Example: Drop custom tables if they exist
        $custom_tables = [
            $wpdb->prefix . 'aci_affiliate_tracking',
            $wpdb->prefix . 'aci_conversion_logs',
            $wpdb->prefix . 'aci_session_data'
        ];

        foreach ($custom_tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * Clean up .htaccess rules (if any were added)
     */
    private static function clean_htaccess_rules() {
        if (!function_exists('flush_rewrite_rules')) {
            return;
        }

        // Remove any custom rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Remove plugin capabilities
     */
    private static function remove_capabilities() {
        $roles = ['administrator', 'editor', 'author'];
        $capabilities = [
            'manage_affiliate_integration',
            'view_affiliate_reports',
            'configure_affiliate_settings'
        ];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
    }

    /**
     * Send uninstall notification (if configured)
     */
    private static function send_uninstall_notification() {
        $master_domain = get_option('aci_master_domain', '');
        $api_key = get_option('aci_api_key', '');
        
        if (!empty($master_domain) && !empty($api_key)) {
            wp_remote_post($master_domain . '/wp-json/affcd/v1/uninstall', [
                'headers' => [
                    'authorisation' => 'Bearer ' . $api_key,
                    'X-Domain' => home_url()
                ],
                'body' => [
                    'domain' => home_url(),
                    'timestamp' => current_time('mysql'),
                    'reason' => 'plugin_uninstalled'
                ],
                'timeout' => 10
            ]);
        }
    }

    /**
     * Create uninstall report
     */
    private static function create_uninstall_report() {
        $report = [
            'timestamp' => current_time('mysql'),
            'site_url' => home_url(),
            'plugin_version' => get_option('aci_plugin_version', 'unknown'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'options_cleaned' => true,
            'transients_cleaned' => true,
            'user_meta_cleaned' => true,
            'session_data_cleaned' => true,
            'logs_cleaned' => true,
            'cache_cleaned' => true
        ];

        // Save report temporarily (will be cleaned by WordPress after uninstall)
        update_option('aci_uninstall_report', $report, false);
    }
}

/**
 * Multisite support
 */
if (is_multisite()) {
    // Get all sites
    $sites = get_sites(['number' => 0]);
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        ACI_Uninstaller::uninstall();
        restore_current_blog();
    }
} else {
    // Single site
    ACI_Uninstaller::uninstall();
}

/**
 * Final cleanup actions
 */

// Send uninstall notification before cleaning settings
ACI_Uninstaller::send_uninstall_notification();

// Create uninstall report
ACI_Uninstaller::create_uninstall_report();

// Remove capabilities
ACI_Uninstaller::remove_capabilities();

// Clean database tables
ACI_Uninstaller::clean_database_tables();

// Clean htaccess rules
ACI_Uninstaller::clean_htaccess_rules();

// Force garbage collection
if (function_exists('gc_collect_cycles')) {
    gc_collect_cycles();
}

/**
 * Log final uninstall completion
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('ACI: Uninstall process completed successfully');
}