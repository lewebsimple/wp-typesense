<?php

namespace Websimple\WpTypesense;

/**
 * WP Typesense settings menu, pages and fields.
 */
class Settings {
	/**
	 * Singleton instance
	 *
	 * @var Settings $instance
	 */
	public static ?Settings $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Initialize settings hooks
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'plugin_action_links_' . WP_TYPESENSE_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Register settings admin menu
	 */
	public function admin_menu() {
		add_options_page(
			'Typesense',
			'Typesense',
			'manage_options',
			'wp-typesense',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function admin_init() {
		register_setting( 'wp-typesense-settings', 'wp_typesense_server_url' );
		register_setting( 'wp-typesense-settings', 'wp_typesense_admin_api_key' );
		register_setting( 'wp-typesense-settings', 'wp_typesense_search_api_key' );
	}

	/**
	 * Render settings admin page
	 */
	public function admin_page() {
		require_once WP_TYPESENSE_ROOT_DIR_PATH . '/templates/admin-page-settings.php';
	}

	/**
	 * Add settings link to plugin actions
	 *
	 * @param array $links Plugin action links.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=wp-typesense' ), __( 'Settings', 'wp-typesense' ) ) );
		return $links;
	}

	/**
	 * Get Typesense server URL
	 *
	 * @return string
	 */
	public static function get_server_url() {
		return defined( 'WP_TYPESENSE_SERVER_URL' ) ? constant( 'WP_TYPESENSE_SERVER_URL' ) : get_option( 'wp_typesense_server_url', '' );
	}

	/**
	 * Get Typesense admin API key
	 *
	 * @return string
	 */
	public static function get_admin_api_key() {
		return defined( 'WP_TYPESENSE_ADMIN_API_KEY' ) ? constant( 'WP_TYPESENSE_ADMIN_API_KEY' ) : get_option( 'wp_typesense_admin_api_key', '' );
	}

	/**
	 * Get Typesense search API key
	 *
	 * @return string
	 */
	public static function get_search_api_key() {
		return defined( 'WP_TYPESENSE_SEARCH_API_KEY' ) ? constant( 'WP_TYPESENSE_SEARCH_API_KEY' ) : get_option( 'wp_typesense_search_api_key', '' );
	}
}
