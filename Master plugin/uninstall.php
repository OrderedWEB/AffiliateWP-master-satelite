<?php
/**
 * Uninstall Script for AffiliateWP Cross Domain Full Master Plugin
 *
 * Handles complete removal of plugin data including database tables,
 * options, scheduled events, user capabilities, and cleanup of
 * temporary files when plugin is uninstalled through WordPress admin.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 * @author Richard King, starneconsulting.com
 */

// Prevent direct access (WordPress includes this file on uninstall)
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Main uninstall class
 */
class AFFCD_Uninstaller {

    /**
     * Database tables to remove (without prefix)
     *
     * @var array
     */
    private static $tables = [
        'affcd_vanity_codes',
        'affcd_vanity_usage',
        'affcd_authorized_domains',
        'affcd_analytics',
        'affcd_rate_limiting',
        'affcd_security_logs',
        'affcd_fraud_detection',
        'affcd_usage_tracking',
        'affcd_api_audit_logs',
        'affcd_performance_metrics',
        'affcd_migrations',
    ];

    /**
     * Plugin options to remove
     *
     * @var array
     */
    private static $options = [
        'affcd_version',
        'affcd_db_version',
        'affcd_installation_date',
        'affcd_activation_count',
        'affcd_settings',
        'affcd_api_settings',
        'affcd_security_settings',
        'affcd_sync_config',
        'affcd_addon_settings',
        'affcd_performance_settings',
        'affcd_backup_settings',
        'affcd_notification_settings',
        'affcd_debug_mode',
        'affcd_maintenance_mode',
        'affcd_license_key',
        'affcd_license_status',
        'affcd_last_update_check',
        'affcd_update_data',
        'affcd_feature_flags',
        'affcd_admin_notices',
        'affcd_wizard_completed',
        'affcd_onboarding_data',
        'affcd_backup_on_uninstall',
        'affcd_analytics_enabled',
    ];

    /**
     * User meta keys to remove
     *
     * @var array
     */
    private static $user_meta_keys = [
        'affcd_admin_preferences',
        'affcd_dashboard_layout',
        'affcd_notification_preferences',
        'affcd_last_login',
        'affcd_session_data',
        'affcd_tour_completed',
        'affcd_help_dismissed',
    ];

    /**
     * Transients to remove (names without _transient_ prefix)
     *
     * @var array
     */
    private static $transients = [
        'affcd_detected_addons',
        'affcd_domain_verification',
        'affcd_api_rate_limits',
        'affcd_security_scan',
        'affcd_performance_data',
        'affcd_update_check',
        'affcd_license_check',
        'affcd_addon_compatibility',
    ];

    /**
     * User capabilities to remove
     *
     * @var array
     */
    private static $capabilities = [
        'manage_affcd',
        'view_affcd_analytics',
        'manage_affcd_domains',
        'manage_affcd_vanity_codes',
        'view_affcd_reports',
        'manage_affcd_security',
        'configure_affcd_api',
        'export_affcd_data',
        'import_affcd_data',
        'manage_affcd_addons',
    ];

    /**
     * Cron jobs (action hooks) to clear
     *
     * @var array
     */
    private static $cron_jobs = [
        'affcd_batch_sync',
        'affcd_full_sync',
        'affcd_cleanup_sync_data',
        'affcd_hourly_maintenance',
        'affcd_daily_maintenance',
        'affcd_weekly_reports',
        'affcd_security_scan',
        'affcd_performance_check',
        'affcd_backup_data',
        'affcd_update_check',
        // from main plugin:
        'affcd_cleanup_expired_codes',
        'affcd_cleanup_analytics_data',
    ];

    /**
     * Run complete uninstallation
     */
    public static function uninstall() {
        // Verify uninstall permissions (best-effort)
        if ( ! self::verify_uninstall_permissions() ) {
            return false;
        }

        self::log_uninstall_start();

        try {
            self::notify_connected_domains();
            self::remove_scheduled_events();
            self::remove_user_capabilities();
            self::remove_user_meta();
            self::remove_transients();
            self::remove_options();
            self::remove_custom_tables();
            self::cleanup_uploaded_files();
            self::cleanup_post_types();
            self::cleanup_htaccess();
            self::remove_custom_indices();
            self::final_cleanup();
            self::log_uninstall_completion();
            return true;

        } catch ( \Exception $e ) {
            self::log_uninstall_error( $e->getMessage() );
            return false;
        }
    }

    /**
     * Verify uninstall permissions
     *
     * @return bool
     */
    private static function verify_uninstall_permissions() {
        // If we have a logged-in user context, ensure they can delete plugins
        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_plugins' ) ) {
            return false;
        }
        return true;
    }

    /**
     * Log uninstall start
     */
    private static function log_uninstall_start() {
        if ( function_exists( 'current_time' ) ) {
            error_log( 'AFFCD Master Plugin: Starting uninstallation at ' . current_time( 'c' ) );
        } else {
            error_log( 'AFFCD Master Plugin: Starting uninstallation' );
        }
    }

    /**
     * Notify connected domains about uninstallation
     */
    private static function notify_connected_domains() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_authorized_domains';

        // Check if table exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ) );

        if ( $exists !== $table_name ) {
            return;
        }

        $domains = $wpdb->get_results(
            "SELECT domain_url, api_key, webhook_url FROM {$table_name}
             WHERE status = 'active' AND webhook_url IS NOT NULL"
        );

        if ( empty( $domains ) ) {
            return;
        }

        foreach ( $domains as $domain ) {
            if ( empty( $domain->webhook_url ) ) {
                continue;
            }
            self::send_uninstall_notification( $domain );
        }
    }

    /**
     * Send uninstall notification to domain
     *
     * @param object $domain Domain object
     */
    private static function send_uninstall_notification( $domain ) {
        $notification_data = [
            'event_type'   => 'master_plugin_uninstalled',
            'master_domain'=> get_site_url(),
            'timestamp'    => function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ),
            'message'      => 'The master plugin has been uninstalled. Please update your client configuration.',
        ];

        // Non-blocking notify; ignore failures
        wp_remote_post(
            esc_url_raw( $domain->webhook_url ),
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . sanitize_text_field( (string) $domain->api_key ),
                ],
                'body'    => wp_json_encode( $notification_data ),
                'timeout' => 10,
                'blocking'=> false,
            ]
        );
    }

    /**
     * Remove scheduled events for our hooks
     */
    private static function remove_scheduled_events() {
        foreach ( self::$cron_jobs as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }
    }

    /**
     * Remove user capabilities from core roles and custom roles
     */
    private static function remove_user_capabilities() {
        $roles = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];

        foreach ( $roles as $role_name ) {
            $role = get_role( $role_name );
            if ( $role ) {
                foreach ( self::$capabilities as $capability ) {
                    $role->remove_cap( $capability );
                }
            }
        }

        // Remove custom roles (if created by plugin)
        $custom_roles = [ 'affcd_manager', 'affcd_analyst', 'affcd_operator' ];
        foreach ( $custom_roles as $custom_role ) {
            remove_role( $custom_role );
        }
    }

    /**
     * Remove user meta data
     */
    private static function remove_user_meta() {
        global $wpdb;

        foreach ( self::$user_meta_keys as $meta_key ) {
            $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
        }

        // Remove any user meta with affcd prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta}
             WHERE meta_key LIKE 'affcd\_%' ESCAPE '\\\\'"
        );
    }

    /**
     * Remove transients
     */
    private static function remove_transients() {
        foreach ( self::$transients as $transient ) {
            delete_transient( $transient );
            delete_site_transient( $transient );
        }

        global $wpdb;

        // Options table transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_affcd\_%' ESCAPE '\\\\'
                OR option_name LIKE '_transient_timeout_affcd\_%' ESCAPE '\\\\'"
        );

        if ( is_multisite() ) {
            // Network transients
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta}
                 WHERE meta_key LIKE '_site_transient_affcd\_%' ESCAPE '\\\\'
                    OR meta_key LIKE '_site_transient_timeout_affcd\_%' ESCAPE '\\\\'"
            );
        }
    }

    /**
     * Remove plugin options
     */
    private static function remove_options() {
        foreach ( self::$options as $option ) {
            delete_option( $option );
            delete_site_option( $option );
        }

        global $wpdb;

        // Remove any options with affcd prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE 'affcd\_%' ESCAPE '\\\\'"
        );

        if ( is_multisite() ) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta}
                 WHERE meta_key LIKE 'affcd\_%' ESCAPE '\\\\'"
            );
        }
    }

    /**
     * Remove custom database tables
     */
    private static function remove_custom_tables() {
        global $wpdb;

        // Disable FK checks (MySQL/MariaDB; ignore failures)
        $wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );

        foreach ( self::$tables as $table_name ) {
            $full = $wpdb->prefix . $table_name;
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full ) );
            if ( $exists === $full ) {
                $wpdb->query( "DROP TABLE IF EXISTS `{$full}`" );
            }
        }

        $wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
    }

    /**
     * Clean up uploaded files (logs, backups, caches)
     */
    private static function cleanup_uploaded_files() {
        $upload_dir = wp_upload_dir();

        $paths = [
            trailingslashit( $upload_dir['basedir'] ) . 'affcd/',
            trailingslashit( $upload_dir['basedir'] ) . 'affcd-backups/',
            trailingslashit( WP_CONTENT_DIR ) . 'logs/affcd/',
        ];

        foreach ( $paths as $path ) {
            if ( is_dir( $path ) ) {
                self::delete_directory_recursive( $path );
            }
        }
    }

    /**
     * Delete directory and all contents recursively
     *
     * @param string $dir Directory path
     * @return bool
     */
    private static function delete_directory_recursive( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return false;
        }

        $files = array_diff( scandir( $dir ), [ '.', '..' ] );

        foreach ( $files as $file ) {
            $file_path = $dir . DIRECTORY_SEPARATOR . $file;
            if ( is_dir( $file_path ) ) {
                self::delete_directory_recursive( $file_path );
            } else {
                @unlink( $file_path );
            }
        }

        return @rmdir( $dir );
    }

    /**
     * Clean up custom post types and taxonomies
     */
    private static function cleanup_post_types() {
        global $wpdb;

        // Remove posts of custom post types
        $custom_post_types = [ 'affcd_vanity_code', 'affcd_domain', 'affcd_report' ];

        foreach ( $custom_post_types as $post_type ) {
            // Prefer wp_delete_post to let WP clean relationships/comments
            $post_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
                $post_type
            ) );

            if ( function_exists( 'wp_delete_post' ) ) {
                foreach ( (array) $post_ids as $pid ) {
                    wp_delete_post( (int) $pid, true ); // force delete
                }
            } else {
                // Fallback: manual cleanup
                foreach ( (array) $post_ids as $pid ) {
                    $pid = (int) $pid;
                    $wpdb->delete( $wpdb->postmeta, [ 'post_id' => $pid ], [ '%d' ] );
                    $wpdb->delete( $wpdb->posts, [ 'ID' => $pid ], [ '%d' ] );
                    $wpdb->query( $wpdb->prepare(
                        "DELETE FROM {$wpdb->term_relationships} WHERE object_id = %d",
                        $pid
                    ) );
                    $wpdb->query( $wpdb->prepare(
                        "DELETE FROM {$wpdb->comments} WHERE comment_post_ID = %d",
                        $pid
                    ) );
                }
            }
        }

        // Clean up custom taxonomies
        $custom_taxonomies = [ 'affcd_domain_category', 'affcd_code_category' ];

        foreach ( $custom_taxonomies as $taxonomy ) {
            // Delete term relationships and taxonomy rows
            $wpdb->query( $wpdb->prepare(
                "DELETE tr FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE tt.taxonomy = %s",
                $taxonomy
            ) );

            $wpdb->delete( $wpdb->term_taxonomy, [ 'taxonomy' => $taxonomy ], [ '%s' ] );
        }
    }

    /**
     * Clean up .htaccess modifications
     */
    private static function cleanup_htaccess() {
        $htaccess_file = ABSPATH . '.htaccess';

        if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
            return;
        }

        $htaccess_content = file_get_contents( $htaccess_file );
        if ( $htaccess_content === false ) {
            return;
        }

        // Remove AFFCD-specific rules
        $affcd_rules_pattern = '/# BEGIN AFFCD.*?# END AFFCD\s*/s';
        $cleaned_content     = preg_replace( $affcd_rules_pattern, '', $htaccess_content );

        if ( $cleaned_content !== $htaccess_content ) {
            file_put_contents( $htaccess_file, $cleaned_content );
        }

        if ( function_exists( 'flush_rewrite_rules' ) ) {
            flush_rewrite_rules();
        }
    }

    /**
     * Remove custom database indices (safe existence check)
     */
    private static function remove_custom_indices() {
        global $wpdb;

        $indices = [
            [ 'name' => 'idx_affcd_user_meta', 'table' => $wpdb->usermeta ],
            [ 'name' => 'idx_affcd_post_meta', 'table' => $wpdb->postmeta ],
            [ 'name' => 'idx_affcd_options',   'table' => $wpdb->options  ],
        ];

        foreach ( $indices as $idx ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(1)
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = %s
                   AND index_name = %s",
                $idx['table'],
                $idx['name']
            ) );

            if ( $exists ) {
                $wpdb->query( "DROP INDEX `{$idx['name']}` ON `{$idx['table']}`" );
            }
        }
    }

    /**
     * Final cleanup operations
     */
    private static function final_cleanup() {
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        if ( function_exists( 'wp_cache_flush_group' ) ) {
            // Some object caches support per-group flush
            wp_cache_flush_group( 'affcd' );
        }

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        // Remove any temporary files
        $temp_dir = trailingslashit( sys_get_temp_dir() ) . 'affcd/';
        if ( is_dir( $temp_dir ) ) {
            self::delete_directory_recursive( $temp_dir );
        }

        // Clean up session data
        if ( function_exists( 'session_status' ) && session_status() === PHP_SESSION_ACTIVE ) {
            unset( $_SESSION['affcd'] );
        }

        // Remove environment variables
        if ( function_exists( 'putenv' ) ) {
            @putenv( 'AFFCD_ENVIRONMENT' );
            @putenv( 'AFFCD_DEBUG' );
        }
    }

    /**
     * Log uninstall completion
     */
    private static function log_uninstall_completion() {
        if ( function_exists( 'current_time' ) ) {
            error_log( 'AFFCD Master Plugin: Uninstallation completed at ' . current_time( 'c' ) );
        } else {
            error_log( 'AFFCD Master Plugin: Uninstallation completed' );
        }
    }

    /**
     * Log uninstall error
     *
     * @param string $error_message
     */
    private static function log_uninstall_error( $error_message ) {
        error_log( 'AFFCD Master Plugin: Uninstallation error - ' . $error_message );
    }

    /**
     * Create uninstall backup (optional)
     *
     * @return string|null Backup file path or null if failed/disabled
     */
    public static function create_uninstall_backup() {
        global $wpdb;

        $backup_data = [
            'timestamp'       => function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ),
            'wp_version'      => get_bloginfo( 'version' ),
            'plugin_version'  => get_option( 'affcd_version', '1.0.0' ),
            'settings'        => [],
            'tables_data'     => [],
        ];

        // Backup plugin settings
        foreach ( self::$options as $option ) {
            $value = get_option( $option );
            if ( $value !== false ) {
                $backup_data['settings'][ $option ] = $value;
            }
        }

        // Backup critical table data
        $critical_tables = [ 'affcd_vanity_codes', 'affcd_authorized_domains' ];

        foreach ( $critical_tables as $table_name ) {
            $full = $wpdb->prefix . $table_name;
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full ) );
            if ( $exists === $full ) {
                $backup_data['tables_data'][ $table_name ] = $wpdb->get_results( "SELECT * FROM `{$full}`", ARRAY_A );
            }
        }

        $upload_dir  = wp_upload_dir();
        $backup_dir  = trailingslashit( $upload_dir['basedir'] );
        $backup_path = $backup_dir . 'affcd_uninstall_backup_' . gmdate( 'Y-m-d_H-i-s' ) . '.json';

        if ( ! is_dir( $backup_dir ) && ! wp_mkdir_p( $backup_dir ) ) {
            return null;
        }

        $written = file_put_contents( $backup_path, wp_json_encode( $backup_data, JSON_PRETTY_PRINT ) );

        return $written ? $backup_path : null;
    }

    /**
     * Send uninstall analytics (if permitted)
     */
    public static function send_uninstall_analytics() {
        if ( ! get_option( 'affcd_analytics_enabled', false ) ) {
            return;
        }

        $analytics_data = [
            'event'          => 'plugin_uninstalled',
            'plugin_version' => get_option( 'affcd_version', '1.0.0' ),
            'wp_version'     => get_bloginfo( 'version' ),
            'php_version'    => PHP_VERSION,
            'site_url'       => get_site_url(),
            'timestamp'      => function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ),
            'usage_stats'    => self::get_usage_statistics(),
        ];

        wp_remote_post(
            'https://analytics.example.com/uninstall',
            [
                'body'     => wp_json_encode( $analytics_data ),
                'headers'  => [ 'Content-Type' => 'application/json' ],
                'timeout'  => 5,
                'blocking' => false,
            ]
        );
    }

    /**
     * Get basic usage statistics for analytics
     *
     * @return array
     */
    private static function get_usage_statistics() {
        global $wpdb;

        $stats = [
            'installation_date' => get_option( 'affcd_installation_date' ),
            'total_domains'     => 0,
            'total_vanity_codes'=> 0,
            'total_conversions' => 0,
        ];

        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $domains_table ) ) === $domains_table ) {
            $stats['total_domains'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$domains_table}`" );
        }

        $codes_table = $wpdb->prefix . 'affcd_vanity_codes';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $codes_table ) ) === $codes_table ) {
            $stats['total_vanity_codes'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$codes_table}`" );
        }

        $tracking_table = $wpdb->prefix . 'affcd_usage_tracking';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tracking_table ) ) === $tracking_table ) {
            $stats['total_conversions'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$tracking_table}` WHERE event_type = 'conversion'"
            );
        }

        return $stats;
    }

    /**
     * Validate uninstall safety (manual mode)
     *
     * @return array{safe_to_uninstall:bool,warnings:array,critical_issues:array}
     */
    public static function validate_uninstall_safety() {
        global $wpdb;

        $validation = [
            'safe_to_uninstall' => true,
            'warnings'          => [],
            'critical_issues'   => [],
        ];

        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $domains_table ) ) === $domains_table ) {
            $active_domains = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$domains_table}` WHERE status = 'active'"
            );
            if ( $active_domains > 0 ) {
                $validation['warnings'][] = sprintf(
                    'There are %d active domains that will be affected',
                    $active_domains
                );
            }
        }

        $tracking_table = $wpdb->prefix . 'affcd_usage_tracking';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tracking_table ) ) === $tracking_table ) {
            $recent_activity = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$tracking_table}`
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            if ( $recent_activity > 0 ) {
                $validation['warnings'][] = sprintf(
                    'There has been recent tracking activity (%d events in the last 7 days)',
                    $recent_activity
                );
            }

            $pending_syncs = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$tracking_table}`
                 WHERE event_type = 'sync_queue' AND status = 'pending'"
            );
            if ( $pending_syncs > 0 ) {
                $validation['critical_issues'][] = sprintf(
                    'There are %d pending sync operations',
                    $pending_syncs
                );
                $validation['safe_to_uninstall'] = false;
            }
        }

        return $validation;
    }

    /**
     * Handle multisite uninstallation (per-site cleanup)
     */
    public static function handle_multisite_uninstall() {
        if ( ! is_multisite() ) {
            return;
        }

        $sites = get_sites( [ 'number' => 0 ] );

        foreach ( $sites as $site ) {
            switch_to_blog( (int) $site->blog_id );
            self::remove_options();
            self::remove_transients();
            self::remove_custom_tables();
            restore_current_blog();
        }

        // Remove network-wide options
        $network_options = [
            'affcd_network_settings',
            'affcd_network_license',
            'affcd_network_version',
        ];
        foreach ( $network_options as $option ) {
            delete_site_option( $option );
        }
    }

    /**
     * Emergency rollback (best-effort)
     *
     * @param string $backup_file
     * @return bool
     */
    public static function emergency_rollback( $backup_file ) {
        if ( ! $backup_file || ! file_exists( $backup_file ) ) {
            return false;
        }

        $backup_data = json_decode( file_get_contents( $backup_file ), true );
        if ( ! is_array( $backup_data ) ) {
            return false;
        }

        try {
            // Restore settings
            if ( ! empty( $backup_data['settings'] ) && is_array( $backup_data['settings'] ) ) {
                foreach ( $backup_data['settings'] as $option => $value ) {
                    update_option( $option, $value );
                }
            }

            // Restore critical table data
            if ( ! empty( $backup_data['tables_data'] ) && is_array( $backup_data['tables_data'] ) ) {
                foreach ( $backup_data['tables_data'] as $table_name => $rows ) {
                    self::restore_table_data( $table_name, (array) $rows );
                }
            }

            return true;

        } catch ( \Exception $e ) {
            error_log( 'AFFCD Emergency Rollback Error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Restore table data from backup
     *
     * @param string $table_name
     * @param array  $table_data
     */
    private static function restore_table_data( $table_name, array $table_data ) {
        global $wpdb;

        $full = $wpdb->prefix . $table_name;
        foreach ( $table_data as $row ) {
            $wpdb->insert( $full, $row );
        }
    }
}

/**
 * Manual uninstall flow (safety prompts)
 */
$manual_uninstall = isset( $_GET['manual_uninstall'] ) && $_GET['manual_uninstall'] === '1';

if ( $manual_uninstall ) {
    $validation = AFFCD_Uninstaller::validate_uninstall_safety();

    if ( ! $validation['safe_to_uninstall'] ) {
        wp_die(
            'Cannot safely uninstall the plugin due to critical issues: ' .
            esc_html( implode( ', ', $validation['critical_issues'] ) ) .
            '. Please resolve these issues first.',
            'Unsafe Uninstall'
        );
    }

    if ( ! empty( $validation['warnings'] ) ) {
        $warning_message = 'Warning: ' . esc_html( implode( ', ', $validation['warnings'] ) ) .
                           '. Are you sure you want to continue?';

        if ( ! isset( $_GET['confirm_uninstall'] ) || $_GET['confirm_uninstall'] !== '1' ) {
            wp_die(
                $warning_message . '<br><br>' .
                '<a href="' . esc_url( add_query_arg( [ 'confirm_uninstall' => '1' ] ) ) . '">' .
                'Yes, proceed with uninstallation</a> | ' .
                '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Cancel</a>',
                'Confirm Uninstallation'
            );
        }
    }
}

// Create backup before uninstalling (if enabled)
$backup_enabled = (bool) get_option( 'affcd_backup_on_uninstall', true );
$backup_file    = null;

if ( $backup_enabled ) {
    $backup_file = AFFCD_Uninstaller::create_uninstall_backup();
}

// Handle multisite if applicable
if ( is_multisite() ) {
    AFFCD_Uninstaller::handle_multisite_uninstall();
}

// Send analytics if permitted
AFFCD_Uninstaller::send_uninstall_analytics();

// Perform the main uninstallation
$uninstall_success = AFFCD_Uninstaller::uninstall();

// If uninstall failed and we have a backup, log message (and show prompt in manual mode)
if ( ! $uninstall_success && $backup_file ) {
    $rollback_message = 'Uninstallation failed. A backup was created at: ' . esc_html( $backup_file ) .
                        '. You may manually restore if needed.';
    error_log( 'AFFCD Uninstall: ' . $rollback_message );

    if ( $manual_uninstall ) {
        wp_die(
            $rollback_message . '<br><br>' .
            'Please check the error logs and try again, or contact support.',
            'Uninstallation Failed'
        );
    }
}

// Final confirmation message for manual uninstall
if ( $manual_uninstall && $uninstall_success ) {
    $success_message = 'AffiliateWP Cross Domain Full has been successfully uninstalled.';
    if ( $backup_file ) {
        $success_message .= ' A backup of your data was created at: ' . esc_html( $backup_file );
    }

    wp_die(
        $success_message . '<br><br>' .
        '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Return to Plugins</a>',
        'Uninstallation Complete'
    );
}
