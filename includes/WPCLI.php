<?php

namespace Websimple\WpTypesense;

use Exception;
use WP_CLI;

/**
 * WP-CLI commands class for WP Typesense
 */
class WPCLI {
	/**
	 * Singleton instance
	 *
	 * @var WPCLI $instance
	 */
	public static ?WPCLI $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return WPCLI
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Initialize WP-CLI hooks
	 */
	public function __construct() {
		add_action( 'cli_init', array( $this, 'register_commands' ) );
	}

	/**
	 * Register WP-CLI commands
	 */
	public function register_commands() {
		WP_CLI::add_command( 'typesense info', array( $this, 'info' ), );
		WP_CLI::add_command( 'typesense collection drop', array( $this, 'collection_drop' ), );
		WP_CLI::add_command( 'typesense collection list', array( $this, 'collection_list' ), );
		WP_CLI::add_command( 'typesense collection prune', array( $this, 'collection_prune' ), );
		WP_CLI::add_command( 'typesense collection reindex', array( $this, 'collection_reindex' ), );
	}

	/**
	 * Display Typesense server information
	 */
	public function info() {
		try {
			API::get_client()->getHealth();
			WP_CLI::success( 'Typesense server is healthy.' );
			WP_CLI::line( sprintf( 'Server URL:     %s', Settings::get_server_url() ) );
			WP_CLI::line( sprintf( 'Server version: %s', API::version() ) );
		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( 'Typesense server is not healthy: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Drop a Typesense collection
	 *
	 * @param array $args Command arguments.
	 */
	public function collection_drop( $args ) {
		if ( empty( $collection_name = $args[0] ?? '' ) ) {
			WP_CLI::error( 'Collection name is required.' );
		}
		try {
			delete_option( "wp_typesense_schema_{$collection_name}" );
			API::get_client()->collections[ $collection_name ]->delete();
			WP_CLI::success( sprintf( 'Collection "%s" dropped successfully.', $collection_name ) );
		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( 'Failed to drop collection "%s": %s', $collection_name, $e->getMessage() ) );
		}
	}

	/**
	 * List all Typesense collections
	 */
	public function collection_list() {
		try {
			$collections = API::get_client()->collections->retrieve();
			if ( empty( $collections ) ) {
				WP_CLI::line( 'No collections found.' );
			} else {
				$items = array();
				foreach ( $collections as $collection ) {
					$items[] = array(
						'name'          => $collection['name'],
						'fields'        => count( $collection['fields'] ),
						'num_documents' => $collection['num_documents'],
					);
				}
				WP_CLI\Utils\format_items( 'table', $items, array( 'name', 'fields', 'num_documents' ) );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( 'Failed to retrieve collections: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Prune invalid documents from a collection
	 *
	 * @param array $args Command arguments.
	 */
	public function collection_prune( $args ) {
		if ( empty( $collection_name = $args[0] ?? '' ) ) {
			WP_CLI::error( 'Collection name is required.' );
		}
		try {
			$deleted_count = Collection::prune( $collection_name );
			WP_CLI::success( sprintf( 'Pruned %d documents from collection "%s" successfully.', $deleted_count, $collection_name ) );
		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( 'Failed to prune collection: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Reindex all documents in a collection
	 *
	 * @param array $args Command arguments.
	 */
	public function collection_reindex( $args ) {
		if ( empty( $collection_name = $args[0] ?? '' ) ) {
			WP_CLI::error( 'Collection name is required.' );
		}
		try {
			$reindexed_count = Collection::reindex( $collection_name );
			WP_CLI::success( sprintf( 'Reindexed %d documents from collection "%s" successfully.', $reindexed_count, $collection_name ) );
		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( 'Failed to reindex collection: %s', $e->getMessage() ) );
		}
	}
}
