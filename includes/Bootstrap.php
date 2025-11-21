<?php

namespace Websimple\WpTypesense;

class Bootstrap {
	
  /**
	 * Singleton instance
	 *
	 * @var Bootstrap $instance
	 */
	public static ?Bootstrap $instance = null;
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

  public function __construct() {
		$this->autoload();
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}
  
	public function autoload() {
		require_once WP_TYPESENSE_ROOT_DIR_PATH . '/vendor/autoload.php';
	}

	public function init_plugin() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		API::get_instance();
		Hooks::get_instance();
		Settings::get_instance();
		WPCLI::get_instance();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'wp-typesense', false, dirname( WP_TYPESENSE_BASENAME ) . '/languages' );
	}

}

Bootstrap::get_instance();
