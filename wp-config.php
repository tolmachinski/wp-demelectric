<?php
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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'demelectric' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'XP0msql' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define('AUTH_KEY',         'DE#><-gH;RdG>?|%;+{erT/vy{i|pYW{Hul@Nbiq#eWCVUdG<R}+%6I!XL!Q/rN>');
define('SECURE_AUTH_KEY',  'snHj6Q$kh,ksV_51$hedTv=Db3T27 P/SFew*i)|Tcb;xu?YBzXC+)C u[[-a]Ki');
define('LOGGED_IN_KEY',    'K7C4X||-==iXxyCDR|jq^ =kpe6i%5HN!$3?o|y&]0h1;h U|M1C-+#,+V_iZ;>q');
define('NONCE_KEY',        '$S:Lbisq{e;a4r(1E+U3z/#vQO .ti(Pu`dw@+N/.yxP9@)Ak+a.zYK_}+O@(W*-');
define('AUTH_SALT',        'uM{`8~,+qK5`Eq?F~B6t8Dca6G|%8`Cmf8Xmn{o#+#kmo!g~%|Yx9MYK.~(:wuk2');
define('SECURE_AUTH_SALT', '8WF8pd(*Z_1dUS3A-gF&1if3TSs@B0I$C| ^{kE7]un-q/xLsj3^=5-8+2n84-wL');
define('LOGGED_IN_SALT',   '~]EXOi:6YJ5stDQ)~o4e[(5K6qCF{Md?^Y<vs3y+8sMvC%x9zBinU|TqdF(-DHZQ');
define('NONCE_SALT',       '|,,*_P5%_^G OuWg/BOk,HT(m=8Sx{g|}LCu}D<;S-)7e1-+)o|cXEsU#w:la&|x');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', ture );


$domain = 'demelectric.molbak.at';

//define('WP_SITEURL', $domain);
//define('WP_HOME',"http:{$domain}");

$httpHost =  isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $domain;

define( 'WP_CONTENT_DIR', dirname( __FILE__ ) . '/wp-content' );
define( 'WP_CONTENT_URL', 'http://' . $httpHost . '/wp-content' );

/** Absolute path to the WordPress directory. */

if ( !defined('ABSPATH') ) {
    define('ABSPATH', dirname(__FILE__) . '/wp');
}

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
