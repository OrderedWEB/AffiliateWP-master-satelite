<?php
/**
 * Configuration Management for Affiliate Client Full Plugin
 *
 * Handles all configuration settings, environment variables, and default values
 * for the client-side affiliate integration system.
 *
 * @package AffiliateClientFull
 * @version 1.0.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get plugin configuration (cached)
 *
 * @return array Configuration array
 */
function affiliate_client_full_get_config() {
	// First try object cache
	$cached = wp_cache_get( 'affiliate_client_full_config_cached', 'affiliate_client_full' );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$config = affiliate_client_full_load_config();

	// Store in object cache for fast reuse
	wp_cache_set( 'affiliate_client_full_config_cached', $config, 'affiliate_client_full', 300 );

	return $config;
}

/**
 * Load configuration from various sources
 *
 * @return array Loaded configuration
 */
function affiliate_client_full_load_config() {
	// Start with default configuration
	$config = affiliate_client_full_get_default_config();

	// Override with database settings (single, validated bag)
	$db_config = get_option( 'affiliate_client_full_config', array() );
	if ( ! empty( $db_config ) && is_array( $db_config ) ) {
		$config = array_merge( $config, $db_config );
	}

	// Override with environment variables
	$env_config = affiliate_client_full_get_env_config();
	if ( ! empty( $env_config ) ) {
		$config = array_merge( $config, $env_config );
	}

	// Override with constants (highest priority)
	$constants_config = affiliate_client_full_get_constants_config();
	if ( ! empty( $constants_config ) ) {
		$config = array_merge( $config, $constants_config );
	}

	// Validate and sanitize configuration
	$config = affiliate_client_full_validate_config( $config );

	return $config;
}

/**
 * Get default configuration
 *
 * @return array Default configuration
 */
function affiliate_client_full_get_default_config() {
	return array(
		// Master domain settings
		'master_domain' => '',
		'api_key'       => '',
		'api_secret'    => '',
		'api_version'   => 'v1',

		// Connection settings
		'connection_timeout' => 30,
		'retry_attempts'     => 3,
		'retry_delay'        => 2, // seconds
		'verify_ssl'         => true,

		// Caching settings
		'cache_enabled'  => true,
		'cache_duration' => 900, // 15 minutes
		'cache_prefix'   => 'aci_',

		// Form settings
		'form_enabled'      => true,
		'popup_enabled'     => true,
		'shortcode_enabled' => true,
		'ajax_enabled'      => true,

		// UI settings
		'form_style'        => 'default',
		'popup_style'       => 'modal',
		'button_text'       => __( 'Apply Discount Code', 'affiliate-client-full' ),
		'placeholder_text'  => __( 'Enter affiliate code', 'affiliate-client-full' ),
		'success_message'   => __( 'Discount code applied successfully!', 'affiliate-client-full' ),
		'error_message'     => __( 'Invalid or expired discount code.', 'affiliate-client-full' ),

		// Validation settings
		'validate_format'    => true,
		'min_code_length'    => 3,
		'max_code_length'    => 50,
		'allowed_characters' => 'alphanumeric_dash_underscore',

		// Tracking settings
		'tracking_enabled'     => true,
		'conversion_tracking'  => true,
		'analytics_enabled'    => true,
		'session_tracking'     => true,

		// Security settings
		'rate_limiting'        => true,
		'max_attempts_per_hour'=> 100,
		'ip_blocking'          => false,
		'honeypot_protection'  => true,

		// Integration settings
		'woocommerce_integration' => true,
		'edd_integration'          => false,
		'zoho_integration'         => false,
		'webhook_integration'      => false,

		// Logging settings
		'logging_enabled'   => false,
		'log_level'         => 'error',
		'log_retention_days'=> 30,
		'debug_mode'        => false,

		// Performance settings
		'lazy_load_assets' => true,
		'minify_assets'    => true,
		'cdn_enabled'      => false,
		'preload_api'      => false,

		// Localization settings
		'language'   => 'en',
		'date_format'=> 'Y-m-d',
		'time_format'=> 'H:i:s',
		'timezone'   => 'UTC',

		// Advanced settings
		'custom_css'          => '',
		'custom_js'          => '',
		'custom_fields'       => array(),
		'webhook_urls'        => array(),
		'notification_emails' => array(),
	);
}

/**
 * Get configuration from environment variables
 *
 * @return array Environment configuration
 */
function affiliate_client_full_get_env_config() {
	$env_config   = array();
	$env_mappings = array(
		'ACI_MASTER_DOMAIN'     => 'master_domain',
		'ACI_API_KEY'           => 'api_key',
		'ACI_API_SECRET'        => 'api_secret',
		'ACI_CACHE_ENABLED'     => 'cache_enabled',
		'ACI_DEBUG_MODE'        => 'debug_mode',
		'ACI_VERIFY_SSL'        => 'verify_ssl',
		'ACI_CONNECTION_TIMEOUT'=> 'connection_timeout',
		'ACI_RETRY_ATTEMPTS'    => 'retry_attempts',
	);

	foreach ( $env_mappings as $env_var => $config_key ) {
		$value = getenv( $env_var );
		if ( false !== $value ) {
			$env_config[ $config_key ] = affiliate_client_full_convert_env_value( $value );
		}
	}

	return $env_config;
}

/**
 * Get configuration from constants
 *
 * @return array Constants configuration
 */
function affiliate_client_full_get_constants_config() {
	$constants_config  = array();
	$constant_mappings = array(
		'ACI_MASTER_DOMAIN' => 'master_domain',
		'ACI_API_KEY'       => 'api_key',
		'ACI_API_SECRET'    => 'api_secret',
		'ACI_CACHE_ENABLED' => 'cache_enabled',
		'ACI_DEBUG_MODE'    => 'debug_mode',
		'ACI_VERIFY_SSL'    => 'verify_ssl',
	);

	foreach ( $constant_mappings as $constant => $config_key ) {
		if ( defined( $constant ) ) {
			$constants_config[ $config_key ] = constant( $constant );
		}
	}

	return $constants_config;
}

/**
 * Convert environment variable value to appropriate type
 *
 * @param string $value Environment variable value
 * @return mixed Converted value
 */
function affiliate_client_full_convert_env_value( $value ) {
	$value_l = strtolower( (string) $value );

	// Convert boolean strings
	if ( in_array( $value_l, array( 'true', '1', 'yes', 'on' ), true ) ) {
		return true;
	}
	if ( in_array( $value_l, array( 'false', '0', 'no', 'off' ), true ) ) {
		return false;
	}

	// Convert numeric strings
	if ( is_numeric( $value ) ) {
		return ( false !== strpos( (string) $value, '.' ) ) ? floatval( $value ) : intval( $value );
	}

	// Return as string
	return (string) $value;
}

/**
 * Validate and sanitize configuration
 *
 * @param array $config Raw configuration
 * @return array Validated configuration
 */
function affiliate_client_full_validate_config( $config ) {
	$validated = array();

	// Validate master domain
	$validated['master_domain'] = affiliate_client_full_validate_domain( $config['master_domain'] ?? '' );

	// Validate API credentials
	$validated['api_key']     = sanitize_text_field( $config['api_key'] ?? '' );
	$validated['api_secret']  = sanitize_text_field( $config['api_secret'] ?? '' );
	$validated['api_version'] = sanitize_text_field( $config['api_version'] ?? 'v1' );

	// Validate connection settings
	$validated['connection_timeout'] = absint( $config['connection_timeout'] ?? 30 );
	$validated['retry_attempts']     = max( 1, min( 5, absint( $config['retry_attempts'] ?? 3 ) ) );
	$validated['retry_delay']        = max( 1, absint( $config['retry_delay'] ?? 2 ) );
	$validated['verify_ssl']         = ! empty( $config['verify_ssl'] );

	// Validate caching settings
	$validated['cache_enabled']  = ! empty( $config['cache_enabled'] );
	$validated['cache_duration'] = max( 60, absint( $config['cache_duration'] ?? 900 ) );
	$validated['cache_prefix']   = sanitize_key( $config['cache_prefix'] ?? 'aci_' );

	// Validate form settings
	$validated['form_enabled']      = ! empty( $config['form_enabled'] );
	$validated['popup_enabled']     = ! empty( $config['popup_enabled'] );
	$validated['shortcode_enabled'] = ! empty( $config['shortcode_enabled'] );
	$validated['ajax_enabled']      = ! empty( $config['ajax_enabled'] );

	// Validate UI settings
	$validated['form_style']       = sanitize_text_field( $config['form_style'] ?? 'default' );
	$validated['popup_style']      = sanitize_text_field( $config['popup_style'] ?? 'modal' );
	$validated['button_text']      = sanitize_text_field( $config['button_text'] ?? __( 'Apply Discount Code', 'affiliate-client-full' ) );
	$validated['placeholder_text'] = sanitize_text_field( $config['placeholder_text'] ?? __( 'Enter affiliate code', 'affiliate-client-full' ) );
	$validated['success_message']  = sanitize_text_field( $config['success_message'] ?? __( 'Discount code applied successfully!', 'affiliate-client-full' ) );
	$validated['error_message']    = sanitize_text_field( $config['error_message'] ?? __( 'Invalid or expired discount code.', 'affiliate-client-full' ) );

	// Validate code validation settings
	$validated['validate_format']    = ! empty( $config['validate_format'] );
	$validated['min_code_length']    = max( 1, absint( $config['min_code_length'] ?? 3 ) );
	$validated['max_code_length']    = min( 100, max( 3, absint( $config['max_code_length'] ?? 50 ) ) );
	$validated['allowed_characters'] = sanitize_text_field( $config['allowed_characters'] ?? 'alphanumeric_dash_underscore' );

	// Validate tracking settings
	$validated['tracking_enabled']    = ! empty( $config['tracking_enabled'] );
	$validated['conversion_tracking'] = ! empty( $config['conversion_tracking'] );
	$validated['analytics_enabled']   = ! empty( $config['analytics_enabled'] );
	$validated['session_tracking']    = ! empty( $config['session_tracking'] );

	// Validate security settings
	$validated['rate_limiting']        = ! empty( $config['rate_limiting'] );
	$validated['max_attempts_per_hour'] = max( 10, min( 1000, absint( $config['max_attempts_per_hour'] ?? 100 ) ) );
	$validated['ip_blocking']          = ! empty( $config['ip_blocking'] );
	$validated['honeypot_protection']  = ! empty( $config['honeypot_protection'] );

	// Validate integration settings
	$validated['woocommerce_integration'] = ! empty( $config['woocommerce_integration'] );
	$validated['edd_integration']         = ! empty( $config['edd_integration'] );
	$validated['zoho_integration']        = ! empty( $config['zoho_integration'] );
	$validated['webhook_integration']     = ! empty( $config['webhook_integration'] );

	// Validate logging settings
	$validated['logging_enabled']   = ! empty( $config['logging_enabled'] );
	$validated['log_level']         = in_array( $config['log_level'] ?? 'error', array( 'debug', 'info', 'warning', 'error' ), true ) ? $config['log_level'] : 'error';
	$validated['log_retention_days']= max( 1, min( 365, absint( $config['log_retention_days'] ?? 30 ) ) );
	$validated['debug_mode']        = ! empty( $config['debug_mode'] );

	// Validate performance settings
	$validated['lazy_load_assets'] = ! empty( $config['lazy_load_assets'] );
	$validated['minify_assets']    = ! empty( $config['minify_assets'] );
	$validated['cdn_enabled']      = ! empty( $config['cdn_enabled'] );
	$validated['preload_api']      = ! empty( $config['preload_api'] );

	// Validate localization settings
	$validated['language']    = sanitize_text_field( $config['language'] ?? 'en' );
	$validated['date_format'] = sanitize_text_field( $config['date_format'] ?? 'Y-m-d' );
	$validated['time_format'] = sanitize_text_field( $config['time_format'] ?? 'H:i:s' );
	$validated['timezone']    = sanitize_text_field( $config['timezone'] ?? 'UTC' );

	// Validate advanced settings
	$validated['custom_css']          = wp_strip_all_tags( $config['custom_css'] ?? '' );
	$validated['custom_js']           = wp_strip_all_tags( $config['custom_js'] ?? '' );
	$validated['custom_fields']       = is_array( $config['custom_fields'] ?? array() ) ? $config['custom_fields'] : array();
	$validated['webhook_urls']        = affiliate_client_full_validate_webhook_urls( $config['webhook_urls'] ?? array() );
	$validated['notification_emails'] = affiliate_client_full_validate_email_list( $config['notification_emails'] ?? array() );

	return $validated;
}

/**
 * Validate domain URL
 *
 * @param string $domain Domain URL
 * @return string Validated domain URL
 */
function affiliate_client_full_validate_domain( $domain ) {
	if ( empty( $domain ) ) {
		return '';
	}

	$domain = (string) $domain;

	// Add protocol if missing
	if ( ! preg_match( '#^https?://#i', $domain ) ) {
		$domain = 'https://' . $domain;
	}

	// Validate URL format
	if ( ! filter_var( $domain, FILTER_VALIDATE_URL ) ) {
		return '';
	}

	// Ensure HTTPS in production
	if ( 'production' === affiliate_client_full_get_current_environment() && 0 !== strpos( $domain, 'https://' ) ) {
		return '';
	}

	return rtrim( $domain, '/' );
}

/**
 * Validate webhook URLs array
 *
 * @param array $urls Webhook URLs
 * @return array Validated URLs
 */
function affiliate_client_full_validate_webhook_urls( $urls ) {
	if ( ! is_array( $urls ) ) {
		return array();
	}

	$validated_urls = array();
	foreach ( $urls as $url ) {
		$validated_url = esc_url_raw( $url );
		if ( ! empty( $validated_url ) && filter_var( $validated_url, FILTER_VALIDATE_URL ) ) {
			$validated_urls[] = $validated_url;
		}
	}

	return $validated_urls;
}

/**
 * Validate email list
 *
 * @param array $emails Email addresses
 * @return array Validated emails
 */
function affiliate_client_full_validate_email_list( $emails ) {
	if ( ! is_array( $emails ) ) {
		return array();
	}

	$validated_emails = array();
	foreach ( $emails as $email ) {
		$validated_email = sanitize_email( $email );
		if ( ! empty( $validated_email ) && is_email( $validated_email ) ) {
			$validated_emails[] = $validated_email;
		}
	}

	return $validated_emails;
}

/**
 * Check if running in development environment
 *
 * @return bool Is development
 */
function affiliate_client_full_is_development() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';

	return ( defined( 'WP_DEBUG' ) && WP_DEBUG )
		|| ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
		|| in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
		|| ( false !== strpos( $host, '.local' ) );
}

/**
 * Update configuration setting (persists to single option)
 *
 * @param string $key Configuration key
 * @param mixed  $value Configuration value
 * @return bool Update success
 */
function affiliate_client_full_update_config( $key, $value ) {
	$config        = get_option( 'affiliate_client_full_config', array() );
	$config[ $key ] = $value;
	$updated       = update_option( 'affiliate_client_full_config', $config );

	if ( $updated ) {
		affiliate_client_full_clear_config_cache();
	}

	return $updated;
}

/**
 * Get configuration setting
 *
 * @param string $key Configuration key
 * @param mixed  $default Default value
 * @return mixed Configuration value
 */
function affiliate_client_full_get_config_value( $key, $default = null ) {
	$config = affiliate_client_full_get_config();
	return $config[ $key ] ?? $default;
}

/**
 * Check if configuration is valid
 *
 * @return bool|array Validation result
 */
function affiliate_client_full_validate_configuration() {
	$config = affiliate_client_full_get_config();
	$errors = array();

	// Check required settings
	if ( empty( $config['master_domain'] ) ) {
		$errors[] = __( 'Master domain is not configured', 'affiliate-client-full' );
	}

	if ( empty( $config['api_key'] ) ) {
		$errors[] = __( 'API key is not configured', 'affiliate-client-full' );
	}

	// Validate master domain connectivity
	if ( ! empty( $config['master_domain'] ) ) {
		$response = wp_remote_get(
			$config['master_domain'] . '/wp-json/',
			array(
				'timeout'   => 10,
				'sslverify' => (bool) ( $config['verify_ssl'] ?? true ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$errors[] = sprintf( __( 'Cannot connect to master domain: %s', 'affiliate-client-full' ), $response->get_error_message() );
		}
	}

	return empty( $errors ) ? true : $errors;
}

/**
 * Export configuration
 *
 * @param bool $include_secrets Include sensitive data
 * @return array Configuration export
 */
function affiliate_client_full_export_config( $include_secrets = false ) {
	$config = affiliate_client_full_get_config();

	if ( ! $include_secrets ) {
		// Remove sensitive data
		unset( $config['api_key'], $config['api_secret'] );
	}

	return array(
		'version'     => defined( 'AFFILIATE_CLIENT_FULL_VERSION' ) ? AFFILIATE_CLIENT_FULL_VERSION : '1.0.0',
		'exported_at' => current_time( 'mysql' ),
		'site_url'    => home_url(),
		'config'      => $config,
	);
}

/**
 * Import configuration
 *
 * @param array $import_data Imported configuration data
 * @param bool  $overwrite Overwrite existing settings
 * @return bool|WP_Error Import result
 */
function affiliate_client_full_import_config( $import_data, $overwrite = false ) {
	if ( ! is_array( $import_data ) || ! isset( $import_data['config'] ) ) {
		return new WP_Error( 'invalid_import', __( 'Invalid configuration data', 'affiliate-client-full' ) );
	}

	$current_config  = get_option( 'affiliate_client_full_config', array() );
	$imported_config = (array) $import_data['config'];

	// Validate imported configuration
	$validated_config = affiliate_client_full_validate_config( $imported_config );

	$new_config = $overwrite ? $validated_config : array_merge( $current_config, $validated_config );

	$result = update_option( 'affiliate_client_full_config', $new_config );

	if ( $result ) {
		// Clear any cached configuration
		affiliate_client_full_clear_config_cache();

		// Log the import
		if ( function_exists( 'affiliate_client_full_log' ) ) {
			affiliate_client_full_log(
				'info',
				'Configuration imported successfully',
				array(
					'imported_keys' => array_keys( $validated_config ),
					'overwrite'     => $overwrite,
				)
			);
		}

		return true;
	}

	return new WP_Error( 'import_failed', __( 'Failed to save imported configuration', 'affiliate-client-full' ) );
}

/**
 * Reset configuration to defaults
 *
 * @param bool $keep_credentials Keep API credentials
 * @return bool Reset success
 */
function affiliate_client_full_reset_config( $keep_credentials = true ) {
	$default_config = affiliate_client_full_get_default_config();

	if ( $keep_credentials ) {
		$current_config                 = get_option( 'affiliate_client_full_config', array() );
		$default_config['master_domain']= $current_config['master_domain'] ?? '';
		$default_config['api_key']      = $current_config['api_key'] ?? '';
		$default_config['api_secret']   = $current_config['api_secret'] ?? '';
	}

	$result = update_option( 'affiliate_client_full_config', $default_config );

	if ( $result ) {
		affiliate_client_full_clear_config_cache();

		if ( function_exists( 'affiliate_client_full_log' ) ) {
			affiliate_client_full_log( 'info', 'Configuration reset to defaults', array( 'kept_credentials' => $keep_credentials ) );
		}
	}

	return $result;
}

/**
 * Get configuration schema for validation
 *
 * @return array Configuration schema
 */
function affiliate_client_full_get_config_schema() {
	return array(
		'master_domain' => array(
			'type'        => 'string',
			'required'    => true,
			'validation'  => 'url',
			'description' => __( 'Master domain URL for API communication', 'affiliate-client-full' ),
		),
		'api_key' => array(
			'type'        => 'string',
			'required'    => true,
			'sensitive'   => true,
			'min_length'  => 20,
			'description' => __( 'API key for authentication', 'affiliate-client-full' ),
		),
		'api_secret' => array(
			'type'        => 'string',
			'required'    => false,
			'sensitive'   => true,
			'min_length'  => 32,
			'description' => __( 'API secret for enhanced security', 'affiliate-client-full' ),
		),
		'cache_enabled' => array(
			'type'        => 'boolean',
			'default'     => true,
			'description' => __( 'Enable response caching', 'affiliate-client-full' ),
		),
		'cache_duration' => array(
			'type'        => 'integer',
			'min'         => 60,
			'max'         => 3600,
			'default'     => 900,
			'description' => __( 'Cache duration in seconds', 'affiliate-client-full' ),
		),
		'connection_timeout' => array(
			'type'        => 'integer',
			'min'         => 5,
			'max'         => 60,
			'default'     => 30,
			'description' => __( 'API connection timeout in seconds', 'affiliate-client-full' ),
		),
		'retry_attempts' => array(
			'type'        => 'integer',
			'min'         => 1,
			'max'         => 5,
			'default'     => 3,
			'description' => __( 'Number of retry attempts for failed requests', 'affiliate-client-full' ),
		),
		'debug_mode' => array(
			'type'        => 'boolean',
			'default'     => false,
			'description' => __( 'Enable debug logging', 'affiliate-client-full' ),
		),
	);
}

/**
 * Validate configuration against schema
 *
 * @param array $config Configuration to validate
 * @return array Validation errors
 */
function affiliate_client_full_validate_config_schema( $config ) {
	$schema = affiliate_client_full_get_config_schema();
	$errors = array();

	foreach ( $schema as $key => $rules ) {
		$value = $config[ $key ] ?? null;

		// Check required fields
		if ( ! empty( $rules['required'] ) && ( is_null( $value ) || '' === $value ) ) {
			/* translators: %s: config key */
			$errors[ $key ] = sprintf( __( '%s is required', 'affiliate-client-full' ), $key );
			continue;
		}

		// Skip validation if value is empty and not required
		if ( is_null( $value ) || '' === $value ) {
			continue;
		}

		// Type validation
		switch ( $rules['type'] ) {
			case 'string':
				if ( ! is_string( $value ) ) {
					$errors[ $key ] = sprintf( __( '%s must be a string', 'affiliate-client-full' ), $key );
				}
				break;
			case 'integer':
				$val_str = is_int( $value ) ? (string) $value : (string) $value;
				if ( ! is_int( $value ) && ! ctype_digit( $val_str ) ) {
					$errors[ $key ] = sprintf( __( '%s must be an integer', 'affiliate-client-full' ), $key );
				}
				break;
			case 'boolean':
				if ( ! is_bool( $value ) && ! in_array( $value, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) {
					$errors[ $key ] = sprintf( __( '%s must be a boolean', 'affiliate-client-full' ), $key );
				}
				break;
		}

		// Length validation for strings
		if ( 'string' === $rules['type'] ) {
			if ( ! empty( $rules['min_length'] ) && strlen( (string) $value ) < $rules['min_length'] ) {
				$errors[ $key ] = sprintf( __( '%1$s must be at least %2$d characters long', 'affiliate-client-full' ), $key, $rules['min_length'] );
			}
			if ( ! empty( $rules['max_length'] ) && strlen( (string) $value ) > $rules['max_length'] ) {
				$errors[ $key ] = sprintf( __( '%1$s must not exceed %2$d characters', 'affiliate-client-full' ), $key, $rules['max_length'] );
			}
		}

		// Range validation for integers
		if ( 'integer' === $rules['type'] ) {
			$int_value = (int) $value;
			if ( ! empty( $rules['min'] ) && $int_value < (int) $rules['min'] ) {
				$errors[ $key ] = sprintf( __( '%1$s must be at least %2$d', 'affiliate-client-full' ), $key, $rules['min'] );
			}
			if ( ! empty( $rules['max'] ) && $int_value > (int) $rules['max'] ) {
				$errors[ $key ] = sprintf( __( '%1$s must not exceed %2$d', 'affiliate-client-full' ), $key, $rules['max'] );
			}
		}

		// Custom validation
		if ( ! empty( $rules['validation'] ) ) {
			switch ( $rules['validation'] ) {
				case 'url':
					if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
						$errors[ $key ] = sprintf( __( '%s must be a valid URL', 'affiliate-client-full' ), $key );
					}
					break;
				case 'email':
					if ( ! is_email( $value ) ) {
						$errors[ $key ] = sprintf( __( '%s must be a valid email address', 'affiliate-client-full' ), $key );
					}
					break;
			}
		}
	}

	return $errors;
}

/**
 * Get configuration with environment-specific overrides
 *
 * @param string $environment Environment name (development, staging, production)
 * @return array Environment-specific configuration
 */
function affiliate_client_full_get_environment_config( $environment = null ) {
	if ( null === $environment ) {
		$environment = affiliate_client_full_get_current_environment();
	}

	$config       = affiliate_client_full_get_config();
	$env_overrides= get_option( "affiliate_client_full_config_{$environment}", array() );

	return array_merge( $config, is_array( $env_overrides ) ? $env_overrides : array() );
}

/**
 * Detect current environment
 *
 * @return string Current environment
 */
function affiliate_client_full_get_current_environment() {
	// Check for explicit environment setting
	if ( defined( 'ACI_ENVIRONMENT' ) ) {
		return (string) ACI_ENVIRONMENT;
	}

	// Check for WordPress environment constants
	if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
		return (string) WP_ENVIRONMENT_TYPE;
	}

	// Auto-detect based on various indicators
	if ( affiliate_client_full_is_development() ) {
		return 'development';
	}

	$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	if ( false !== strpos( $host, 'staging' ) || false !== strpos( $host, 'test' ) ) {
		return 'staging';
	}

	return 'production';
}

/**
 * Encrypt sensitive configuration data
 *
 * @param string $data Data to encrypt
 * @return string Encrypted data
 */
function affiliate_client_full_encrypt_config_data( $data ) {
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		return base64_encode( (string) $data ); // Fallback to base64 encoding
	}

	$key       = affiliate_client_full_get_encryption_key();
	$iv        = openssl_random_pseudo_bytes( 16 );
	$encrypted = openssl_encrypt( (string) $data, 'AES-256-CBC', $key, 0, $iv );

	return base64_encode( $iv . $encrypted );
}

/**
 * Decrypt sensitive configuration data
 *
 * @param string $encrypted_data Encrypted data
 * @return string Decrypted data
 */
function affiliate_client_full_decrypt_config_data( $encrypted_data ) {
	if ( ! function_exists( 'openssl_decrypt' ) ) {
		return base64_decode( (string) $encrypted_data ); // Fallback from base64 encoding
	}

	$data      = base64_decode( (string) $encrypted_data );
	$iv        = substr( $data, 0, 16 );
	$encrypted = substr( $data, 16 );
	$key       = affiliate_client_full_get_encryption_key();

	return (string) openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
}

/**
 * Get or generate encryption key
 *
 * @return string Encryption key
 */
function affiliate_client_full_get_encryption_key() {
	$key = get_option( 'affiliate_client_full_encryption_key' );

	if ( ! $key ) {
		$key = wp_generate_password( 32, false );
		update_option( 'affiliate_client_full_encryption_key', $key );
	}

	return (string) $key;
}

/**
 * Clear configuration cache
 */
function affiliate_client_full_clear_config_cache() {
	// Clear object cache entry
	wp_cache_delete( 'affiliate_client_full_config_cached', 'affiliate_client_full' );

	/**
	 * Allow 3rd-parties to react to configuration cache clearing.
	 */
	do_action( 'affiliate_client_full_config_cache_cleared' );
}

/**
 * Get configuration health status
 *
 * @return array Health status
 */
function affiliate_client_full_get_config_health() {
	$config = affiliate_client_full_get_config();
	$health = array(
		'status'          => 'healthy',
		'issues'          => array(),
		'recommendations' => array(),
	);

	// Check critical settings
	if ( empty( $config['master_domain'] ) ) {
		$health['status']   = 'critical';
		$health['issues'][] = __( 'Master domain not configured', 'affiliate-client-full' );
	}

	if ( empty( $config['api_key'] ) ) {
		$health['status']   = 'critical';
		$health['issues'][] = __( 'API key not configured', 'affiliate-client-full' );
	}

	// Check connection
	if ( ! empty( $config['master_domain'] ) ) {
		$response = wp_remote_get(
			$config['master_domain'] . '/wp-json/',
			array(
				'timeout'   => 5,
				'sslverify' => (bool) ( $config['verify_ssl'] ?? true ),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( 'healthy' === $health['status'] ) {
				$health['status'] = 'warning';
			}
			$health['issues'][] = sprintf( __( 'Cannot connect to master domain: %s', 'affiliate-client-full' ), $response->get_error_message() );
		}
	}

	// Performance recommendations
	if ( empty( $config['cache_enabled'] ) ) {
		$health['recommendations'][] = __( 'Enable caching for better performance', 'affiliate-client-full' );
	}

	if ( ! empty( $config['debug_mode'] ) && 'production' === affiliate_client_full_get_current_environment() ) {
		$health['recommendations'][] = __( 'Disable debug mode in production', 'affiliate-client-full' );
	}

	return $health;
}

/**
 * Configuration change hooks
 */
add_action( 'update_option_affiliate_client_full_config', 'affiliate_client_full_clear_config_cache', 10, 0 );
add_action( 'add_option_affiliate_client_full_config', 'affiliate_client_full_clear_config_cache', 10, 0 );
