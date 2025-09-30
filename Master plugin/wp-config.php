<?php
define( 'WP_CACHE', false ); // By Speed Optimizer by SiteGround

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'dboiws9bhlb6dw' );

/** Database username */
define( 'DB_USER', 'ubeqdojtbv0tz' );

/** Database password */
define( 'DB_PASSWORD', 'i4zidaflgsha' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '|X2&-hIfj{u!qpv5obcMPsO>@oE/g@Yn+K*FOcp6Iq_hkX<^%&.u0XlLN?)>q[$q' );
define( 'SECURE_AUTH_KEY',   'h`*})UFI0{rxE`bwbl3^Hdv,aP6&$bRXB!?VQGKFqph+HkIPTb:n?@h298F)777%' );
define( 'LOGGED_IN_KEY',     '|]r?Cv#z5qi=FK~oJ0:b%?>)exL`cxT<,~2WjHn;Hw%qJ+noR 5lB*x=G>S~`/y]' );
define( 'NONCE_KEY',         '%8_&Od%]EF|mibt>I5V Bt_A>Q 3iWLteQzX|cQCfkuR:9T$L~y^5^cYSH,|+O@e' );
define( 'AUTH_SALT',         'A?J~m/?_qGjC]:df@W)Ka]_z38<{%-b8_IAaDXC11n*L2Ip~[S<Yd?5H.))KMSt>' );
define( 'SECURE_AUTH_SALT',  'j#41UbOFnJBBcUHA{x f5P5{IE?VrVQ+[;+5]_ZwV+R`%)L|-d35iBx6GP~u6?1l' );
define( 'LOGGED_IN_SALT',    '@_66?Z7@?TgTefHH)G25[n^cwAY,TG*{YSA6R0Lb%T%Ou 68z@N=sB3&sTe~LY2S' );
define( 'NONCE_SALT',        'E@X*+=Q3%EX.*rY;vxDYg!#-B[XxM91<AY<#D:bN,o4b1wipY-r+47y-9tXGrZPE' );
define( 'WP_CACHE_KEY_SALT', 'GTlE]=:)Mo6q//]s,s9Qbygl ;O>9&U{$< qo7OJ*} _A!q`)$f/hrXt$<*;:iUn' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'xrq_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
}


@ini_set('log_errors','On');
@ini_set('display_errors','Off');
@ini_set('error-log-viewer', '/home/customer/www/affiliates.theitalianbureau.com/public_html/wp-content/plugins/error-log-viewer/log/php-errors.log');

define( 'WP_DEBUG', false );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
@include_once('/var/lib/sec/wp-settings-pre.php'); // Added by SiteGround WordPress management system
require_once ABSPATH . 'wp-settings.php';
@include_once('/var/lib/sec/wp-settings.php'); // Added by SiteGround WordPress management system


//Enable error logging.
@ini_set('log_errors', 'On');
@ini_set('error_log', '/home/customer/www/affiliates.theitalianbureau.com/public_html/wp-content/elm-error-logs/php-errors.log');

//Don't show errors to site visitors.
@ini_set('display_errors', 'Off');
if ( !defined('WP_DEBUG_DISPLAY') ) {
	define('WP_DEBUG_DISPLAY', false);
}

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);