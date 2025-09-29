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
define( 'ACI_VERSION', '1.0.1' );
define( 'ACI_FILE', __FILE__ );
define( 'ACI_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACI_URL', plugin_dir_url( __FILE__ ) );
define( 'ACI_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload/includes
 */
// Config & helpers (optional but recommended)
if ( file_exists( ACI_DIR . 'config.php' ) ) {
	require_once ACI_DIR . 'config.php';
}

$include_files = array(
	'includes/class-api-client.php',
	'includes/class-ajax-handler.php',
	'includes/class-addon-client.php',
	'includes/class-site-health.php',
);

foreach ( $include_files as $rel ) {
	$path = ACI_DIR . $rel;
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

	/** @var ACI_API_Client|null */
	public $api = null;

	/** @var ACI_Ajax_Handler|null */
	public $ajax = null;

	/** @var ACI_Addon_Client|null */
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

		register_activation_hook( ACI_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( ACI_FILE, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Init components
	 */
	public function init() {
		// API client
		if ( class_exists( 'ACI_API_Client' ) ) {
			$this->api = new ACI_API_Client();
		}

		// AJAX handlers
		if ( class_exists( 'ACI_Ajax_Handler' ) ) {
			$this->ajax = new ACI_Ajax_Handler( $this->api );
			$this->ajax->hooks();
		}

		// Addons / integrations
		if ( class_exists( 'ACI_Addon_Client' ) ) {
			$this->addons = new ACI_Addon_Client( $this->api );
			if ( method_exists( $this->addons, 'init' ) ) {
				$this->addons->init();
			}
		}
	}

	/**
	 * Internationalization
	 */
	public function i18n() {
		load_plugin_textdomain( 'affiliate-client-integration', false, dirname( ACI_BASENAME ) . '/languages' );
	}

	/**
	 * Front assets
	 */
	public function enqueue_front() {
		$deps = array( 'jquery' );
		wp_register_script(
			'aci-front',
			ACI_URL . 'assets/js/affiliate-tracking.js',
			$deps,
			ACI_VERSION,
			true
		);

		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aci_front' ),
		);
		wp_localize_script( 'aci-front', 'AFFCI', $config );
		wp_enqueue_script( 'aci-front' );

		wp_register_style(
			'aci-front',
			ACI_URL . 'assets/css/aci-front.css',
			array(),
			ACI_VERSION
		);
		wp_enqueue_style( 'aci-front' );
	}

	/**
	 * Admin assets
	 */
	public function enqueue_admin( $hook ) {
		if ( 'settings_page_affiliate-client-integration' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'aci-admin', ACI_URL . 'assets/css/aci-admin.css', array(), ACI_VERSION );
		wp_enqueue_script( 'aci-admin', ACI_URL . 'admin/js/admin-settings.js', array( 'jquery' ), ACI_VERSION, true );
		wp_localize_script(
			'aci-admin',
			'AFFCIAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aci_admin' ),
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
		register_setting( 'aci', 'aci_master_domain', array(
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
		register_setting( 'aci', 'aci_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'aci', 'aci_timeout', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 10,
		) );
		register_setting( 'aci', 'aci_verify_ssl', array(
			'type'              => 'boolean',
			'sanitize_callback' => static function( $v ) { return (bool) $v; },
			'default'           => true,
		) );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$domain    = get_option( 'aci_master_domain', '' );
		$api_key   = get_option( 'aci_api_key', '' );
		$timeout   = get_option( 'aci_timeout', 10 );
		$verify_ssl= (bool) get_option( 'aci_verify_ssl', true );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Affiliate Client Integration', 'affiliate-client-integration' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'aci' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aci_master_domain"><?php esc_html_e( 'Master domain', 'affiliate-client-integration' ); ?></label></th>
						<td>
							<input type="url" id="aci_master_domain" class="regular-text" name="aci_master_domain" value="<?php echo esc_attr( $domain ); ?>" placeholder="https://master.example.com" required>
							<p class="description"><?php esc_html_e( 'The primary site that hosts AffiliateWP Cross-Domain services.', 'affiliate-client-integration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aci_api_key"><?php esc_html_e( 'API key', 'affiliate-client-integration' ); ?></label></th>
						<td>
							<input type="text" id="aci_api_key" class="regular-text" name="aci_api_key" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aci_timeout"><?php esc_html_e( 'Timeout (seconds)', 'affiliate-client-integration' ); ?></label></th>
						<td>
							<input type="number" id="aci_timeout" class="small-text" min="5" max="60" name="aci_timeout" value="<?php echo esc_attr( (int) $timeout ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verify SSL', 'affiliate-client-integration' ); ?></th>
						<td>
							<label><input type="checkbox" name="aci_verify_ssl" value="1" <?php checked( $verify_ssl ); ?>> <?php esc_html_e( 'Verify HTTPS certificates when calling the master site', 'affiliate-client-integration' ); ?></label>
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
		if ( ! get_option( 'aci_timeout', false ) ) {
			add_option( 'aci_timeout', 10 );
		}
		if ( get_option( 'aci_verify_ssl', null ) === null ) {
			add_option( 'aci_verify_ssl', true );
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
function aci() : Affiliate_Client_Integration {
	return Affiliate_Client_Integration::instance();
}
aci();
