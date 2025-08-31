<?php
define('WP_DEBUG', true);
define('WP_HOME', 'https://domain.tld');
define('WP_SITEURL', WP_HOME . '/');
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_CONTENT_URL',WP_HOME.'/wp-content');
//and for now
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    error_log('Composer autoloader not found. Please run "composer install" in the project root.');
}

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'proj' );

/** Database username */
define( 'DB_USER', 'proj_user' );

/** Database password */
define( 'DB_PASSWORD', 'xxx' );

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
define( 'AUTH_KEY',          'xxx' );
define( 'SECURE_AUTH_KEY',   'xxx' );
define( 'LOGGED_IN_KEY',     'xxx' );
define( 'NONCE_KEY',         'xxx' );
define( 'AUTH_SALT',         'xxx' );
define( 'SECURE_AUTH_SALT',  'xxx' );
define( 'LOGGED_IN_SALT',    'xxx' );
define( 'NONCE_SALT',        'xxx' );
define( 'WP_CACHE_KEY_SALT', 'xxx' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
        define('ABSPATH', dirname(__FILE__) . '/wp/');


/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';