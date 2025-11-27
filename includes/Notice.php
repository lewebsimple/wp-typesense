<?php

namespace Websimple\WpTypesense;

/**
 * Admin notice helper class
 */
class Notice {

	/**
	 * Singleton instance
	 *
	 * @var Notice $instance
	 */
	public static ?Notice $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Notice
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Transient key for storing notices
	 */
	private const TRANSIENT_KEY = 'wp_typesense_notices_';

	/**
	 * Transient expiration time (60 seconds)
	 */
	private const TRANSIENT_EXPIRATION = 60;

	/**
	 * Initialize notice hooks
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Display stored notices
	 */
	public function display_notices() {
		if ( ! is_admin() ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$notices = get_transient( self::TRANSIENT_KEY . $user_id );
		if ( ! $notices || ! is_array( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$type    = isset( $notice['type'] ) ? $notice['type'] : 'info';
			$message = isset( $notice['message'] ) ? $notice['message'] : '';

			if ( empty( $message ) ) {
				continue;
			}

			printf(
				'<div class="notice notice-%s is-dismissible"><p><strong>WP Typesense</strong>: %s</p></div>',
				esc_attr( $type ),
				esc_html( $message )
			);
		}

		// Delete transient after displaying notices
		delete_transient( self::TRANSIENT_KEY . $user_id );
	}

	/**
	 * Add a notice to the queue
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type (error, warning, success, info).
	 */
	private static function add_notice( $message, $type = 'info' ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$notices = get_transient( self::TRANSIENT_KEY . $user_id );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);

		set_transient( self::TRANSIENT_KEY . $user_id, $notices, self::TRANSIENT_EXPIRATION );
	}

	/**
	 * Add an error notice
	 *
	 * @param string $message Error message.
	 */
	public static function error( $message ) {
		self::add_notice( $message, 'error' );
	}

	/**
	 * Add a success notice
	 *
	 * @param string $message Success message.
	 */
	public static function success( $message ) {
		self::add_notice( $message, 'success' );
	}

	/**
	 * Add a warning notice
	 *
	 * @param string $message Warning message.
	 */
	public static function warning( $message ) {
		self::add_notice( $message, 'warning' );
	}

	/**
	 * Add an info notice
	 *
	 * @param string $message Info message.
	 */
	public static function info( $message ) {
		self::add_notice( $message, 'info' );
	}
}
