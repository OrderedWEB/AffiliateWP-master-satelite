<?php
/**
 * Plugin Name: Affiliate Client Integration
 * Plugin URI:  https://affiliate-system.com
 * Description: Client-side integration for Affiliate cross-domain tracking: validates codes, tracks visits/conversions, and talks to your master site.
 * Version:     1.0.1
 * Author:      Affiliate System Team
 * Author URI:  https://affiliate-system.com
 * License:     GPL-2.0-or-later
 * Text Domain: affiliate-client-integration
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants
 */
define( 'AFFCI_VERSION', '1.0.1' );
define( 'AFFCI_FILE', __FILE__ );
define( 'AFFCI_DIR', plugin_dir_path( __FILE__ ) );
define( 'AFFCI_URL', plugin_dir_url( __FILE__ ) );
define( 'AFFCI_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload/includes
 */
// Config & helpers (optional but recommended)
if ( file_exists( AFFCI_DIR . 'config.php' ) ) {
	require_once AFFCI_DIR . 'config.php';
}

$include_files = array(
	'includes/class-api-client.php',
	'includes/class-ajax-handler.php',
	'includes/class-addon-client.php',
	'includes/class-site-health.php',
);

foreach ( $include_files as $rel ) {
	$path = AFFCI_DIR . $rel;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

/**
 * Main bootstrap class
 */
if ( ! class_exists( 'Affiliate_Client_Integration' ) ) :
class Affiliate_Client_Integration {

	/** @var Affiliate_Client_Integration */
	private static $instance;

	/** @var AFFCI_API_Client|null */
	public $api = null;

	/** @var AFFCI_Ajax_Handler|null */
	public $ajax = null;

	/** @var AFFCI_Addon_Client|null */
	public $addons = null;

	/**
	 * Singleton
	 */
	public static function instance() : self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Ctor
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'i18n' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

		register_activation_hook( AFFCI_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( AFFCI_FILE, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Init components
	 */
	public function init() {
		// API client
		if ( class_exists( 'AFFCI_API_Client' ) ) {
			$this->api = new AFFCI_API_Client();
		}

		// AJAX handlers
		if ( class_exists( 'AFFCI_Ajax_Handler' ) ) {
			$this->ajax = new AFFCI_Ajax_Handler( $this->api );
			$this->ajax->hooks();
		}

		// Addons / integrations
		if ( class_exists( 'AFFCI_Addon_Client' ) ) {
			$this->addons = new AFFCI_Addon_Client( $this->api );
			if ( method_exists( $this->addons, 'init' ) ) {
				$this->addons->init();
			}
		}
	}

	/**
	 * Internationalization
	 */
	public function i18n() {
		load_plugin_textdomain( 'affiliate-client-integration', false, dirname( AFFCI_BASENAME ) . '/languages' );
	}

	/**
	 * Front assets
	 */
	public function enqueue_front() {
		$deps = array( 'jquery' );
		wp_register_script(
			'affci-front',
			AFFCI_URL . 'assets/js/affci-front.js',
			$deps,
			AFFCI_VERSION,
			true
		);

		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'affci_front' ),
		);
		wp_localize_script( 'affci-front', 'AFFCI', $config );
		wp_enqueue_script( 'affci-front' );

		wp_register_style(
			'affci-front',
			AFFCI_URL . 'assets/css/affci-front.css',
			array(),
			AFFCI_VERSION
		);
		wp_enqueue_style( 'affci-front' );
	}

	/**
	 * Admin assets
	 */
	public function enqueue_admin( $hook ) {
		if ( 'settings_page_affiliate-client-integration' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'affci-admin', AFFCI_URL . 'assets/css/affci-admin.css', array(), AFFCI_VERSION );
		wp_enqueue_script( 'affci-admin', AFFCI_URL . 'assets/js/affci-admin.js', array( 'jquery' ), AFFCI_VERSION, true );
		wp_localize_script(
			'affci-admin',
			'AFFCIAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'affci_admin' ),
				'i18n'    => array(
					'saving'  => __( 'Saving…', 'affiliate-client-integration' ),
					'saved'   => __( 'Saved.', 'affiliate-client-integration' ),
					'error'   => __( 'Error', 'affiliate-client-integration' ),
				),
			)
		);
	}

	/**
	 * Settings screen
	 */
	public function admin_menu() {
		add_options_page(
			__( 'Affiliate Client Integration', 'affiliate-client-integration' ),
			__( 'Affiliate Client', 'affiliate-client-integration' ),
			'manage_options',
			'affiliate-client-integration',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'affci', 'affci_master_domain', array(
			'type'              => 'string',
			'sanitize_callback' => function( $val ) {
				$val = trim( (string) $val );
				if ( '' === $val ) {
					return '';
				}
				if ( ! preg_match( '#^https?://#i', $val ) ) {
					$val = 'https://' . $val;
				}
				$val = esc_url_raw( $val );
				return untrailingslashit( $val );
			},
			'default'           => '',
		) );
		register_setting( 'affci', 'affci_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'affci', 'affci_timeout', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 10,
		) );
		register_setting( 'affci', 'affci_verify_ssl', array(
			'type'              => 'boolean',
			'sanitize_callback' => static function( $v ) { return (bool) $v; },
			'default'           => true,
		) );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$domain    = get_option( 'affci_master_domain', '' );
		$api_key   = get_option( 'affci_api_key', '' );
		$timeout   = get_option( 'affci_timeout', 10 );
		$verify_ssl= (bool) get_option( 'affci_verify_ssl', true );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Affiliate Client Integration', 'affiliate-client-integration' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'affci' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="affci_master_domain"><?php esc_html_e( 'Master domain', 'affiliate-client-integration' ); ?></label></th>
						<td>
							<input type="url" id="affci_master_domain" class="regular-text" name="affci_master_domain" value="<?php echo esc_attr( $domain ); ?>" placeholder="https://master.example.com" required>
							<p class="description"><?php esc_html_e( 'The primary site that hosts AffiliateWP Cross-Domain services.', 'affiliate-client-integration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="affci_api_key"><?php esc_html_e( 'API key', 'affiliate-client-integration' ); ?></label></th>
						<td>
							<input type="text" id="affci_api_key" class="regular-text" name="affci_api_key" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="affci_timeout"><?php esc_html_e( 'Timeout (seconds)', 'affiliate-client-integration' ); ?></label></th>
						<td>
							<input type="number" id="affci_timeout" class="small-text" min="5" max="60" name="affci_timeout" value="<?php echo esc_attr( (int) $timeout ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verify SSL', 'affiliate-client-integration' ); ?></th>
						<td>
							<label><input type="checkbox" name="affci_verify_ssl" value="1" <?php checked( $verify_ssl ); ?>> <?php esc_html_e( 'Verify HTTPS certificates when calling the master site', 'affiliate-client-integration' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Activation
	 */
	public static function activate() {
		// Placeholder for DB table creation or schedules
		if ( ! get_option( 'affci_timeout', false ) ) {
			add_option( 'affci_timeout', 10 );
		}
		if ( get_option( 'affci_verify_ssl', null ) === null ) {
			add_option( 'affci_verify_ssl', true );
		}
	}

	/**
	 * Deactivation
	 */
	public static function deactivate() {
		// Placeholder for removing schedules, etc.
	}
}
endif;

/**
 * Initialize plugin
 */
function affci() : Affiliate_Client_Integration {
	return Affiliate_Client_Integration::instance();
}
affci();
