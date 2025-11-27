<?php
/**
 * Plugin Name: WP Typesense
 * Plugin URI: https://github.com/lewebsimple/wp-typesense
 * Description: Low-level Typesense integration for WordPress.
 * Version: 0.3.1
 * Author: Pascal Martineau <pascal@lewebsimple.ca>
 * Author URI: https://websimple.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-typesense
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_TYPESENSE_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_TYPESENSE_VERSION', '0.3.1' );
define( 'WP_TYPESENSE_ROOT_DIR_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WP_TYPESENSE_ROOT_DIR_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
if ( ! defined( 'WP_TYPESENSE_BATCH_SIZE' ) ) {
	define( 'WP_TYPESENSE_BATCH_SIZE', 50 );
}

require_once WP_TYPESENSE_ROOT_DIR_PATH . '/includes/Bootstrap.php';
