<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'Ecomerce' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '0000' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '?~3=>n:~:K;&z]zz@(f5*,V;Hkb6k%M17Ie/c_Ro.*`N/K_tWehT?I,JYM7j/l>a' );
define( 'SECURE_AUTH_KEY',  'VqMZ8qlL)I>bA=H+ez0im1L}|euUrV&Ift]mVH^k0)SRE[.y~ScoZ?`YzTY SrXM' );
define( 'LOGGED_IN_KEY',    'nntx9@jf?p8-PMZ)K]2aw>FN-fsdsf:5b_S}Cw]C>irv4c6i2dL&jGM).tZ|As2S' );
define( 'NONCE_KEY',        'Gj.@C{Ho[XMa<!;aCUZ5>J4@HS0%gw(%<8F:hX2KIAes9<mnEx=]7]GYWNJ~wb!.' );
define( 'AUTH_SALT',        '~Ict4/u6TWY}k zf{C,2f!Q4=7~1VxIB]kg#&`0Z^.{.&=_ w%#u]J7y#n$iM_7p' );
define( 'SECURE_AUTH_SALT', 'MG%6|^73{.<lwK7Ja3![U:*:m0u+5udRxx 9A!LrCd-b%+6x|C@Xs]qD~3.OX=<u' );
define( 'LOGGED_IN_SALT',   'pRn(&MK;H#UA6*o(Ghs>.5rc+cw::tf:gLQv?(1}se|gZv#glN;E_4x|#:>!8N=c' );
define( 'NONCE_SALT',       '#<[v4bY4%<SOG8iUpN9w;TlxGT]EH1W/XpwOraFl#O^z`B2QG+4Pq~QE=rwi[g;z' );

/**#@-*/

/**
 * WordPress Database Table prefix.
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
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
