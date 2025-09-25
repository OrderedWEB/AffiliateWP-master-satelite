<?php
/**
 * Helper Functions for AffiliateWP Cross Domain Full
 *
 * Global helper functions and utilities for the affiliate cross-domain system.
 * These functions provide common functionality across the entire plugin.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the main plugin instance
 *
 * @return AffiliateWP_Cross_Domain_Plugin_Suite_Master
 */
function affcd_get_instance() {
	// The main bootstrap function returns the singleton instance.
	if ( function_exists( 'AffiliateWP_Cross_Domain_Plugin_Suite_Master' ) ) {
		return AffiliateWP_Cross_Domain_Plugin_Suite_Master();
	}
	// Fallback if the function name changes in the future.
	if ( class_exists( 'AffiliateWP_Cross_Domain_Plugin_Suite_Master' ) ) {
		return AffiliateWP_Cross_Domain_Plugin_Suite_Master::instance();
	}
	return null;
}

/**
 * Log activity to analytics table
 *
 * @param string $event_type Type of event
 * @param array  $event_data Event data
 * @param string $entity_type Optional entity type
 * @param int    $entity_id Optional entity ID
 * @return bool Success status
 */
function affcd_log_activity( $event_type, $event_data = [], $entity_type = '', $entity_id = 0 ) {
	global $wpdb;

	$analytics_table = $wpdb->prefix . 'affcd_analytics';

	$data = [
		'event_type' => sanitize_text_field( $event_type ),
		'entity_type' => sanitize_text_field( $entity_type ),
		'entity_id' => $entity_id ? absint( $entity_id ) : null,
		'domain' => affcd_get_current_domain(),
		'user_id' => get_current_user_id() ?: null,
		'session_id' => affcd_get_session_id(),
		'ip_address' => affcd_get_client_ip(),
		'user_agent' => affcd_get_user_agent(),
		'referrer' => affcd_get_referrer(),
		'event_data' => wp_json_encode( $event_data ),
		'metadata' => wp_json_encode(
			[
				'wordpress_version' => get_bloginfo( 'version' ),
				'plugin_version'    => defined( 'AFFCD_VERSION' ) ? AFFCD_VERSION : '',
				'php_version'       => PHP_VERSION,
				'timestamp'         => current_time( 'mysql' ),
			]
		),
	];

	$formats = [ '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ];

	return ( false !== $wpdb->insert( $analytics_table, $data, $formats ) );
}

/**
 * Get current domain from request
 *
 * @return string Current domain
 */
function affcd_get_current_domain() {
	$domain = '';

	// Check for domain in headers (API requests)
	if ( isset( $_SERVER['HTTP_X_CLIENT_DOMAIN'] ) ) {
		$domain = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_CLIENT_DOMAIN'] ) );
	}

	// Check for domain in request parameters
	if ( empty( $domain ) && isset( $_REQUEST['domain'] ) ) {
		$domain = sanitize_text_field( wp_unslash( $_REQUEST['domain'] ) );
	}

	// Fall back to referrer
	if ( empty( $domain ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$parsed = wp_parse_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		$domain = $parsed['host'] ?? '';
	}

	// Fall back to current site
	if ( empty( $domain ) ) {
		$parsed = wp_parse_url( home_url() );
		$domain = $parsed['host'] ?? '';
	}

	// Normalize (strip www; lower)
	$domain = strtolower( preg_replace( '/^www\./', '', $domain ) );

	return $domain;
}

/**
 * Get client IP address
 *
 * @return string Client IP address
 */
if ( ! function_exists( 'affcd_get_client_ip' ) ) {
    function affcd_get_client_ip() {
        $keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                // Some headers can contain multiple IPs "client, proxy1, proxy2"
                $iplist = explode( ',', $_SERVER[ $key ] );
                foreach ( $iplist as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return $ip;
                    }
                }
            }
        }

        // Fallback
        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}
/**
 * Get user agent string
 *
 * @return string User agent
 */
function affcd_get_user_agent() {
	return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
}

/**
 * Get referrer URL
 *
 * @return string Referrer URL
 */
function affcd_get_referrer() {
	return isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
}

/**
 * Get or generate session ID
 *
 * @return string Session ID
 */
function affcd_get_session_id() {
	if ( session_id() ) {
		return session_id();
	}

	// Generate a pseudo session ID for stateless requests (hourly windowed)
	$identifier = affcd_get_client_ip() . affcd_get_user_agent() . gmdate( 'Y-m-d H' );
	return substr( md5( $identifier ), 0, 32 );
}

/**
 * Check if domain is authorized
 *
 * @param string $domain Domain to check (host only, no scheme)
 * @return bool|object Domain record if authorized, false otherwise
 */
function affcd_is_domain_authorized( $domain ) {
	global $wpdb;

	if ( empty( $domain ) ) {
		return false;
	}

	$table_name = $wpdb->prefix . 'affcd_authorized_domains';

	// Check cache first
	$cache_key = 'affcd_authorized_domain_' . md5( $domain );
	$cached    = wp_cache_get( $cache_key, 'affcd_domains' );

	if ( false !== $cached ) {
		return $cached;
	}

	// Try exact with https:// prefix (common storage form)
	$domain_record = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE domain_url = %s AND status = 'active'",
			'https://' . $domain
		)
	);

	// Try any scheme
	if ( ! $domain_record ) {
		$domain_record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE (domain_url = %s OR domain_url = %s OR domain_url LIKE %s) AND status = 'active'",
				'http://' . $domain,
				'https://' . $domain,
				'%://' . $wpdb->esc_like( $domain ) . '%'
			)
		);
	}

	// Cache result for 15 minutes
	wp_cache_set( $cache_key, $domain_record, 'affcd_domains', 900 );

	return $domain_record;
}

/**
 * Generate API key
 *
 * @param int $length Key length
 * @return string Generated API key
 */
function affcd_generate_api_key( $length = 32 ) {
	return wp_generate_password( $length, false, false );
}

/**
 * Generate API secret
 *
 * @param int $length Secret length
 * @return string Generated API secret
 */
function affcd_generate_api_secret( $length = 64 ) {
	return hash( 'sha256', wp_generate_uuid4() . time() . wp_rand() );
}

/**
 * Validate API key format
 *
 * @param string $api_key API key to validate
 * @return bool Valid format
 */
function affcd_validate_api_key_format( $api_key ) {
	return ( ! empty( $api_key ) && strlen( $api_key ) >= 20 && strlen( $api_key ) <= 100 );
}

/**
 * Hash API secret
 *
 * @param string $secret Secret to hash
 * @return string Hashed secret
 */
function affcd_hash_api_secret( $secret ) {
	return password_hash( $secret, PASSWORD_ARGON2ID );
}

/**
 * Verify API secret
 *
 * @param string $secret Plain secret
 * @param string $hash Hashed secret
 * @return bool Verification result
 */
function affcd_verify_api_secret( $secret, $hash ) {
	return password_verify( $secret, $hash );
}

/**
 * Generate secure token
 *
 * @param int $length Token length
 * @return string Generated token
 */
function affcd_generate_secure_token( $length = 32 ) {
	$length = max( 2, (int) $length );
	return bin2hex( random_bytes( (int) floor( $length / 2 ) ) );
}

/**
 * Format currency amount
 *
 * @param float  $amount Amount to format
 * @param string $currency Currency code
 * @return string Formatted amount
 */
function affcd_format_currency( $amount, $currency = 'USD' ) {
	$symbol_map = [
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'JPY' => '¥',
		'CAD' => 'C$',
		'AUD' => 'A$',
	];

	$symbol = $symbol_map[ $currency ] ?? $currency;
	return $symbol . number_format( (float) $amount, 2 );
}

/**
 * Sanitize domain (host) value
 *
 * @param string $domain Domain (host or URL)
 * @return string Sanitized host (no scheme/path) or empty string if invalid
 */
function affcd_sanitize_domain( $domain ) {
	$domain = trim( strtolower( (string) $domain ) );

	// If a URL, parse the host
	if ( false !== strpos( $domain, '://' ) ) {
		$parts  = wp_parse_url( $domain );
		$domain = $parts['host'] ?? '';
	}

	// Strip www. and trailing slash
	$domain = preg_replace( '/^www\./', '', $domain );
	$domain = rtrim( $domain, '/' );

	// Validate by reconstructing a URL
	$test = 'https://' . $domain;
	return filter_var( $test, FILTER_VALIDATE_URL ) ? $domain : '';
}

/**
 * Get affiliate by code
 *
 * @param string $code Affiliate code
 * @return object|false Affiliate object or false
 */
function affcd_get_affiliate_by_code( $code ) {
	if ( ! function_exists( 'affwp_get_affiliate_by' ) ) {
		return false;
	}

	$code = sanitize_text_field( $code );

	// Try multiple keys; adjust as needed for your vanity mapping
	return affwp_get_affiliate_by( 'user_login', $code ) ?: affwp_get_affiliate_by( 'payment_email', $code );
}

/**
 * Calculate discount amount
 *
 * @param float  $total Order total
 * @param string $type Discount type (percentage|fixed)
 * @param float  $value Discount value
 * @return float Discount amount
 */
function affcd_calculate_discount( $total, $type, $value ) {
	$total = (float) $total;
	$value = (float) $value;

	switch ( $type ) {
		case 'percentage':
			return max( 0.0, ( $total * $value ) / 100 );
		case 'fixed':
			return max( 0.0, min( $value, $total ) );
		default:
			return 0.0;
	}
}

/**
 * Get plugin settings
 *
 * @param string $key Optional setting key
 * @param mixed  $default Default value
 * @return mixed Settings value
 */
function affcd_get_settings( $key = '', $default = null ) {
	$settings = get_option( 'affcd_settings', [] );

	if ( '' === $key ) {
		return $settings;
	}

	return $settings[ $key ] ?? $default;
}

/**
 * Update plugin setting
 *
 * @param string $key Setting key
 * @param mixed  $value Setting value
 * @return bool Update success
 */
function affcd_update_setting( $key, $value ) {
	$settings         = affcd_get_settings();
	$settings[ $key ] = $value;
	return update_option( 'affcd_settings', $settings );
}

/**
 * Check if feature is enabled
 *
 * @param string $feature Feature name
 * @return bool Feature status
 */
function affcd_is_feature_enabled( $feature ) {
	return (bool) affcd_get_settings( $feature . '_enabled', false );
}

/**
 * Get rate limit for action
 *
 * @param string $action Action type
 * @return array{requests:int,window:int} Rate limit configuration
 */
function affcd_get_rate_limit( $action ) {
	$limits = [
		'validate_code'     => [ 'requests' => 100,  'window' => 3600 ],
		'api_request'       => [ 'requests' => 1000, 'window' => 3600 ],
		'form_submission'   => [ 'requests' => 50,   'window' => 3600 ],
		'failed_validation' => [ 'requests' => 10,   'window' => 300 ],
	];

	return $limits[ $action ] ?? [ 'requests' => 60, 'window' => 3600 ];
}

/**
 * Send webhook notification
 *
 * @param string $domain Domain to notify (host only)
 * @param string $event Event type
 * @param array  $data Event data
 * @return bool Success status
 */
function affcd_send_webhook( $domain, $event, $data = [] ) {
	$domain = affcd_sanitize_domain( $domain );

	$domain_record = affcd_is_domain_authorized( $domain );

	if ( ! $domain_record || empty( $domain_record->webhook_url ) ) {
		return false;
	}

	// Check if event is enabled
	$webhook_events = json_decode( (string) $domain_record->webhook_events, true ) ?: [];
	if ( ! empty( $webhook_events ) && ! in_array( $event, $webhook_events, true ) ) {
		return false;
	}

	$payload = [
		'event'     => $event,
		'domain'    => $domain,
		'timestamp' => current_time( 'mysql' ),
		'data'      => $data,
	];

	// Add signature if secret is configured
	if ( ! empty( $domain_record->webhook_secret ) ) {
		$payload['signature'] = hash_hmac( 'sha256', wp_json_encode( $payload ), (string) $domain_record->webhook_secret );
	}

	$response = wp_remote_post(
		esc_url_raw( $domain_record->webhook_url ),
		[
			'headers' => [
				'Content-Type'   => 'application/json',
				'User-Agent'     => 'AffiliateWP-Cross-Domain/' . ( defined( 'AFFCD_VERSION' ) ? AFFCD_VERSION : 'dev' ),
				'X-AFFCD-Event'  => $event,
				'X-AFFCD-Domain' => $domain,
			],
			'body'     => wp_json_encode( $payload ),
			'timeout'  => 15,
			'blocking' => false, // Send asynchronously
		]
	);

	$success = ! is_wp_error( $response );

	// Update webhook statistics
	global $wpdb;
	$domains_table = $wpdb->prefix . 'affcd_authorized_domains';

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$domains_table} SET 
			 webhook_last_sent = NOW(),
			 webhook_failures  = CASE WHEN %d = 1 THEN 0 ELSE webhook_failures + 1 END
			 WHERE id = %d",
			$success ? 1 : 0,
			(int) $domain_record->id
		)
	);

	return $success;
}

/**
 * Format time ago
 *
 * @param string $datetime DateTime string
 * @return string Human readable time ago
 */
function affcd_time_ago( $datetime ) {
	if ( empty( $datetime ) ) {
		return __( 'Never', 'affiliatewp-cross-domain-plugin-suite' );
	}

	return sprintf(
		/* translators: %s: human readable time difference */
		__( '%s ago', 'affiliatewp-cross-domain-plugin-suite' ),
		human_time_diff( strtotime( $datetime ), current_time( 'timestamp' ) )
	);
}

/**
 * Get risk level color
 *
 * @param int $risk_score Risk score (0-100)
 * @return string Color class
 */
function affcd_get_risk_color( $risk_score ) {
	$risk_score = (int) $risk_score;

	if ( $risk_score >= 80 ) {
		return 'red';
	} elseif ( $risk_score >= 60 ) {
		return 'orange';
	} elseif ( $risk_score >= 40 ) {
		return 'yellow';
	}
	return 'green';
}

/**
 * Clean old data
 *
 * @param string $table_type Table type to clean
 * @param int    $days_old Days to keep data
 * @return int Rows affected
 */
function affcd_clean_old_data( $table_type, $days_old = 90 ) {
	global $wpdb;

	$table_map = [
		'analytics'     => $wpdb->prefix . 'affcd_analytics',
		'security_logs' => $wpdb->prefix . 'affcd_security_logs',
		'rate_limiting' => $wpdb->prefix . 'affcd_rate_limiting',
		'vanity_usage'  => $wpdb->prefix . 'affcd_vanity_usage',
	];

	if ( ! isset( $table_map[ $table_type ] ) ) {
		return 0;
	}

	$table_name  = $table_map[ $table_type ];
	$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

	$date_column = ( 'rate_limiting' === $table_type ) ? 'window_end' : 'created_at';

	$additional_where = '';
	if ( 'security_logs' === $table_type ) {
		$additional_where = " AND investigation_status = 'resolved'";
	}

	return (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table_name} WHERE {$date_column} < %s{$additional_where}",
			$cutoff_date
		)
	);
}

/**
 * Validate vanity code format
 *
 * @param string $code Code to validate
 * @return bool Valid format
 */
function affcd_validate_vanity_code_format( $code ) {
	$code = (string) $code;

	return (
		$code !== '' &&
		strlen( $code ) >= 3 &&
		strlen( $code ) <= 50 &&
		preg_match( '/^[a-zA-Z0-9_-]+$/', $code )
	);
}

/**
 * Generate random vanity code
 *
 * @param int $length Code length
 * @return string Generated code
 */
function affcd_generate_vanity_code( $length = 8 ) {
	$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$code       = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$code .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
	}

	return $code;
}

/**
 * Check if code is reserved
 *
 * @param string $code Code to check
 * @return bool Is reserved
 */
function affcd_is_code_reserved( $code ) {
	$reserved_codes = [
		'admin', 'api', 'www', 'mail', 'email', 'support', 'help',
		'test', 'demo', 'sample', 'example', 'default', 'null',
		'undefined', 'true', 'false', 'yes', 'no',
	];

	return in_array( strtolower( (string) $code ), $reserved_codes, true );
}

/**
 * Get plugin cache group
 *
 * @param string $type Cache type
 * @return string Cache group
 */
function affcd_get_cache_group( $type ) {
	return 'affcd_' . sanitize_key( $type );
}

/**
 * Clear plugin cache
 *
 * @param string $type Optional cache type
 * @return bool Success status
 */
function affcd_clear_cache( $type = '' ) {
	// If object cache supports per-group flush:
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		if ( '' === $type ) {
			$cache_groups = [ 'domains', 'codes', 'analytics', 'security' ];
			foreach ( $cache_groups as $group ) {
				wp_cache_flush_group( affcd_get_cache_group( $group ) );
			}
			return true;
		}
		return (bool) wp_cache_flush_group( affcd_get_cache_group( $type ) );
	}

	// Fallback: flush entire cache (coarser)
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
		return true;
	}

	return false;
}

/**
 * Get WordPress timezone
 *
 * @return DateTimeZone WordPress timezone
 */
function affcd_get_timezone() {
	$timezone_string = get_option( 'timezone_string' );

	if ( ! empty( $timezone_string ) ) {
		return new DateTimeZone( $timezone_string );
	}

	$offset  = (float) get_option( 'gmt_offset' );
	$hours   = (int) $offset;
	$minutes = abs( ( $offset - floor( $offset ) ) * 60 );
	$offset_string = sprintf( '%+03d:%02d', $hours, $minutes );

	return new DateTimeZone( $offset_string );
}

/**
 * Convert UTC datetime to local timezone
 *
 * @param string $utc_datetime UTC datetime string
 * @return string Local datetime string
 */
function affcd_utc_to_local( $utc_datetime ) {
	$utc_date = new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) );
	$utc_date->setTimezone( affcd_get_timezone() );
	return $utc_date->format( 'Y-m-d H:i:s' );
}

/**
 * Convert local datetime to UTC
 *
 * @param string $local_datetime Local datetime string
 * @return string UTC datetime string
 */
function affcd_local_to_utc( $local_datetime ) {
	$local_date = new DateTime( $local_datetime, affcd_get_timezone() );
	$local_date->setTimezone( new DateTimeZone( 'UTC' ) );
	return $local_date->format( 'Y-m-d H:i:s' );
}

/**
 * Get plugin admin URL
 *
 * @param string $page Page slug
 * @param array  $args Additional arguments
 * @return string Admin URL
 */
function affcd_get_admin_url( $page = '', $args = [] ) {
	$base_page = function_exists( 'affiliate_wp' ) ? 'affiliate-wp' : 'affcd-main';

	if ( ! empty( $page ) ) {
		$args['page'] = 'affcd-' . sanitize_key( $page );
	} else {
		$args['page'] = $base_page;
	}

	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Check if current user can manage affiliates
 *
 * @return bool Permission status
 */
function affcd_current_user_can_manage() {
	return current_user_can( 'manage_affiliates' ) || current_user_can( 'manage_options' );
}

/**
 * Get error message for code
 *
 * @param string $error_code Error code
 * @return string Error message
 */
function affcd_get_error_message( $error_code ) {
	$messages = [
		'invalid_code'         => __( 'The affiliate code is invalid or has expired.', 'affiliatewp-cross-domain-plugin-suite' ),
		'inactive_code'        => __( 'The affiliate code is not currently active.', 'affiliatewp-cross-domain-plugin-suite' ),
		'expired_code'         => __( 'The affiliate code has expired.', 'affiliatewp-cross-domain-plugin-suite' ),
		'usage_limit_reached'  => __( 'The affiliate code has reached its usage limit.', 'affiliatewp-cross-domain-plugin-suite' ),
		'domain_not_authorized'=> __( 'Your domain is not authorized to use this service.', 'affiliatewp-cross-domain-plugin-suite' ),
		'rate_limit_exceeded'  => __( 'Too many requests. Please try again later.', 'affiliatewp-cross-domain-plugin-suite' ),
		'invalid_request'      => __( 'The request is invalid or malformed.', 'affiliatewp-cross-domain-plugin-suite' ),
		'authentication_failed'=> __( 'Authentication failed. Please check your API credentials.', 'affiliatewp-cross-domain-plugin-suite' ),
	];

	return $messages[ $error_code ] ?? __( 'An unexpected error occurred.', 'affiliatewp-cross-domain-plugin-suite' );
}