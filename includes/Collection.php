<?php

namespace Websimple\WpTypesense;

/**
 * Typesense collection management class
 */
class Collection {

	/**
	 * Singleton instance
	 *
	 * @var Collection $instance
	 */
	public static ?Collection $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Collection
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Initialize collection hooks
	 */
	public function __construct() {
		add_action( 'wp_typesense_bulk_delete', array( $this, 'bulk_delete' ), 10, 2 );
		add_action( 'wp_typesense_bulk_upsert', array( $this, 'bulk_upsert' ), 10, 3 );
	}

	/**
	 * Bulk delete documents from Typesense, batching if necessary
	 *
	 * @param array  $document_ids Document IDs.
	 * @param string $collection_name Collection name.
	 */
	public function bulk_delete( $document_ids, $collection_name ) {
		// Process in batches if exceeding batch size.
		if ( count( $document_ids ) > WP_TYPESENSE_BATCH_SIZE ) {
			$this->batch_process( 'wp_typesense_bulk_delete', $document_ids, $collection_name );
			return;
		}
		$filter_by = sprintf( 'id:[%s]', implode( ',', $document_ids ) );
		try {
			API::get_client()->collections[ $collection_name ]->documents->delete( array( 'filter_by' => $filter_by ) );
		} catch ( \Typesense\Exceptions\TypesenseClientError $e ) {
			Notice::error( sprintf( __( 'Bulk delete error: %s', 'wp-typesense' ), $e->getMessage() ) );
		}
	}

	/**
	 * Bulk upsert documents to Typesense, batching if necessary
	 *
	 * @param array  $entity_ids Entity IDs.
	 * @param string $entity_type Entity type.
	 * @param string $collection_name Collection name.
	 */
	public function bulk_upsert( $entity_ids, $entity_type, $collection_name ) {
		// Process in batches if exceeding batch size.
		if ( count( $entity_ids ) > WP_TYPESENSE_BATCH_SIZE ) {
			$this->batch_process( 'wp_typesense_bulk_upsert', $entity_ids, $entity_type, $collection_name );
			return;
		}
		$documents = array_filter(
			array_map(
				function ( $entity_id ) use ( $entity_type, $collection_name ) {
					$document = Document::get_data( $collection_name, $entity_type, $entity_id );
					return is_wp_error( $document ) ? null : $document;
				},
				$entity_ids
			)
		);
		if ( empty( $documents ) ) {
			Notice::warning( __( 'No valid documents to import', 'wp-typesense' ) );
			return;
		}
		try {
			API::get_client()->collections[ $collection_name ]->documents->import( API::jsonl_encode( $documents ), array( 'action' => 'upsert' ) );
		} catch ( \Typesense\Exceptions\TypesenseClientError $e ) {
			Notice::error( sprintf( __( 'Bulk upsert error: %s', 'wp-typesense' ), $e->getMessage() ) );
		}
	}

	/**
	 * Remove invalid documents from a collection
	 *
	 * @param string $collection_name Collection name.
	 *
	 * @return int Number of deleted documents.
	 */
	public function prune( $collection_name ) {
			// TODO: Export in pages to handle large collections.
			$documents           = API::jsonl_decode( API::get_client()->collections[ $collection_name ]->documents->export() );
			$delete_document_ids = array();
		foreach ( $documents as $document ) {
			if ( ! isset( $document['id'] ) ) {
				continue;
			}
			$decoded = Document::decode_id( $document['id'] );
			switch ( $decoded['entity_type'] ) {
				case 'post':
					if ( ! in_array( get_post_status( $decoded['entity_id'] ), apply_filters( 'wp_typesense_indexed_post_status', array( 'publish' ) ) ) ) {
						$delete_document_ids[] = $document['id'];
					}
					break;
				case 'term':
					$term = get_term( $decoded['entity_id'] );
					if ( ! $term || is_wp_error( $term ) ) {
						$delete_document_ids[] = $document['id'];
					}
					break;
				default:
					$delete_document_ids[] = $document['id'];
					break;
			}
		}
		if ( empty( $delete_document_ids ) ) {
			return 0;
		}
		$this->bulk_delete( $delete_document_ids, $collection_name );
		return count( $delete_document_ids );
	}

	/**
	 * Reindex all documents in a collection
	 *
	 * @param string $collection_name Collection name.
	 *
	 * @return int Number of reindexed documents.
	 */
	public function reindex( $collection_name ) {
		$collection      = API::get_client()->collections[ $collection_name ]->retrieve();
		$reindexed_count = 0;
		foreach ( $collection['metadata']['post_types'] ?? array() as $post_type ) {
			$post_ids = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => apply_filters( 'wp_typesense_indexed_post_status', array( 'publish' ) ),
					'fields'         => 'ids',
					'posts_per_page' => -1,
				)
			);
			if ( is_wp_error( $post_ids ) || ! is_array( $post_ids ) ) {
				continue;
			}
			$this->bulk_upsert( $post_ids, 'post', $collection_name );
			$reindexed_count += count( $post_ids );
		}
		foreach ( $collection['metadata']['taxonomies'] ?? array() as $taxonomy ) {
			$term_ids = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
				continue;
			}
			$this->bulk_upsert( $term_ids, 'term', $collection_name );
			$reindexed_count += count( $term_ids );
		}
		return $reindexed_count;
	}

	/**
	 * Process data in batches
	 *
	 * @param string $hook Action hook name.
	 * @param array  $ids IDs.
	 * @param mixed  ...$args Additional arguments.
	 */
	private function batch_process( $hook, $ids, ...$args ) {
		if ( empty( $ids ) ) {
			return;
		}
		foreach ( array_chunk( $ids, WP_TYPESENSE_BATCH_SIZE ) as $batch ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( $hook, array( $batch, ...$args ) );
			} else {
				do_action( $hook, $batch, ...$args );
			}
		}
	}
}
