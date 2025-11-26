<?php

namespace Websimple\WpTypesense;

/**
 * Typesense collection schemas class
 */
class Schemas {
	/**
	 * Typesense collection schemas
	 *
	 * @see https://typesense.org/docs/27.1/api/collections.html#with-pre-defined-schema
	 * @var array $schemas
	 */
	private array $schemas = array();

	/**
	 * Post types collections map
	 *
	 * @var array $post_types_map
	 */
	private array $post_types_map = array();

	/**
	 * Taxonomies collections map
	 *
	 * @var array $taxonomies_map
	 */
	private array $taxonomies_map = array();


	/**
	 * Singleton instance
	 *
	 * @var Schemas $instance
	 */
	public static ?Schemas $instance = null;
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		foreach ( apply_filters( 'wp_typesense_schemas', array() ) as $collection_name => $schema ) {
			if ( $collection_name !== $schema['name'] ) {
				throw new \Exception( 'Collection name must match schema name' );
			}

			// Map post types and taxonomies to collections
			foreach ( $schema['post_types'] ?? array() as $post_type ) {
				$this->post_types_map[ $post_type ][] = $collection_name;
			}
			unset( $schema['post_types'] );
			foreach ( $schema['taxonomies'] ?? array() as $taxonomy ) {
				$this->taxonomies_map[ $taxonomy ][] = $collection_name;
			}
			unset( $schema['taxonomies'] );

			// Make sure ID field is present
			if ( ! in_array( 'id', array_column( $schema['fields'], 'name' ) ) ) {
				array_unshift(
					$schema['fields'],
					array(
						'name' => 'id',
						'type' => 'string',
					)
				);
			}

			// Synchronize schema
			if ( is_wp_error( $this->sync_schema( $schema ) ) ) {
				continue;
			}
			$this->schemas[ $collection_name ] = $schema;
		}
	}

	public static function get_post_collections( $post_id ) {
		$post_type = get_post_type( $post_id );
		return self::get_instance()->post_types_map[ $post_type ] ?? array();
	}

	public static function get_term_collections( $term_id ) {
		$taxonomy = get_term( $term_id )->taxonomy ?? '';
		return self::get_instance()->taxonomies_map[ $taxonomy ] ?? array();
	}

	private function sync_schema( $schema ) {
		$collection_name = $schema['name'] ?? '(unknown)';
		try {
			if ( empty( $collection_name ) ) {
				throw new \Exception( 'Typesense schema must have a name' );
			}

			// Check if cached schema matches
			$cached_schema = get_option( "wp_typesense_schema_{$collection_name}", null );
			if ( wp_json_encode( $cached_schema ) === wp_json_encode( $schema ) ) {
				return true;
			}

			// Check if collection exists
			$collections = API::get_client()->getCollections()->retrieve();
			$key         = array_search( $collection_name, array_column( $collections, 'name' ), true );
			if ( $key === false ) {
				// Create collection
				$result = API::get_client()->collections->create( $schema );
			} else {
				// Update collection
				$existing = $collections[ $key ];

				// Prepare update schema (only add new fields and drop removed fields
				$update_schema         = array( 'fields' => array() );
				$existing_fields_names = array_column( $existing['fields'], 'name' );
				$new_fields_names      = array_column( $schema['fields'], 'name' );
				foreach ( $schema['fields'] as $field ) {
					if ( $field['name'] === 'id' ) {
						continue;
					}
					if ( ! in_array( $field['name'], $existing_fields_names, true ) ) {
						$update_schema['fields'][] = $field;
					}
				}
				foreach ( $existing['fields'] as $field ) {
					if ( ! in_array( $field['name'], $new_fields_names, true ) ) {
						$update_schema['fields'][] = array(
							'name' => $field['name'],
							'drop' => true,
						);
					}
				}

				$result = API::get_client()->collections[ $collection_name ]->update( $update_schema );
			}

			// Cache schema
			update_option( "wp_typesense_schema_{$collection_name}", $schema );

			return $result;
		} catch ( \Exception $e ) {
			add_action(
				'admin_notices',
				function () use ( $e, $collection_name ) {
					printf(
						'<div class="notice notice-error is-dismissible"><p><strong>WP Typesense error</strong> (%s): %s</p></div>',
						esc_html( $collection_name ),
						esc_html( $e->getMessage() ),
					);
				}
			);
			return new \WP_Error( 'sync_schema_error', $e->getMessage() );
		}
	}
}
