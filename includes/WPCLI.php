<?php

namespace Websimple\WpTypesense;

use WP_CLI;

class WPCLI {
 
  /**
	 * Singleton instance
	 *
	 * @var WPCLI $instance
	 */
	public static ?WPCLI $instance = null;
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

  public function __construct() {
		add_action( 'cli_init', array( $this, 'register_commands' ) );
	}

  public function register_commands() {
		WP_CLI::add_command( 'typesense info', array( $this, 'info' ), );
	}

  public function info() {
    $health = API::health();
    if ( is_wp_error( $health ) ) {
      WP_CLI::error( sprintf( 'Typesense server is not healthy: %s', $health->get_error_message() ) );
    } else {
      WP_CLI::success( 'Typesense server is healthy.' );
      WP_CLI::line( sprintf( 'Server URL:     %s', Settings::get_server_url() ) );
      WP_CLI::line( sprintf( 'Server version: %s', API::version() ) );
    }
  }

}
