<?php

namespace Websimple\WpTypesense;

/**
 * Bootstrap WP Typesense plugin class
 */
class Bootstrap {
	/**
	 * Singleton instance
	 *
	 * @var Bootstrap $instance
	 */
	public static ?Bootstrap $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Bootstrap
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Initialize bootstrap hooks
	 */
	public function __construct() {
		$this->autoload();
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}

	/**
	 * Load Composer autoloader
	 */
	public function autoload() {
		require_once WP_TYPESENSE_ROOT_DIR_PATH . '/vendor/autoload.php';
	}

	/**
	 * Initialize plugin classes
	 */
	public function init_plugin() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		API::get_instance();
		Collection::get_instance();
		Document::get_instance();
		Hooks::get_instance();
		Schemas::get_instance();
		Settings::get_instance();
		WPCLI::get_instance();
	}

	/**
	 * Load plugin text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-typesense', false, dirname( WP_TYPESENSE_BASENAME ) . '/languages' );
	}

	/**
	 * Display an admin notice with the given message
	 *
	 * @param string $message The message to display.
	 */
	public static function admin_notice( $message ) {
		if ( ! is_admin() ) {
			return;
		}
		add_action(
			'admin_notices',
			function () use ( $message ) {
				echo '<div class="notice notice-error"><p><strong>WP Typesense error</strong>:' . esc_html( $message ) . '</p></div>';
			}
		);
	}
}

Bootstrap::get_instance();
