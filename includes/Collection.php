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
		add_action( 'wp_typesense_bulk_upsert', array( $this, 'bulk_upsert' ), 10, 2 );
	}


	/**
	 * Bulk upsert documents to Typesense
	 *
	 * @param array $entity_ids Entity IDs.
	 * @param array $args Additional arguments: collection_name, entity_type.
	 */
	public function bulk_upsert( $entity_ids, $args ) {
		if ( empty( $args['collection_name'] ) || empty( $args['entity_type'] ) || empty( $entity_ids ) ) {
			return;
		}
		$documents = array_map(
			function ( $entity_id ) use ( $args ) {
				return Document::get_instance()->get_data( $args['collection_name'], $args['entity_type'], $entity_id );
			},
			$entity_ids
		);
		API::get_client()->collections[ $args['collection_name'] ]->documents->import( API::jsonl_encode( $documents ), array( 'action' => 'upsert' ) );
	}

	/**
	 * Remove invalid documents from a collection
	 *
	 * @param string $collection_name Collection name.
	 *
	 * @return int Number of deleted documents.
	 */
	public static function prune( $collection_name ) {
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
		API::get_client()->collections[ $collection_name ]->documents->delete( array( 'filter_by' => sprintf( 'id:[%s]', implode( ',', $delete_document_ids ) ) ) );
		return count( $delete_document_ids );
	}

	/**
	 * Reindex all documents in a collection
	 *
	 * @param string $collection_name Collection name.
	 *
	 * @return int Number of reindexed documents.
	 */
	public static function reindex( $collection_name ) {
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
			self::batch_process(
				'wp_typesense_bulk_upsert',
				$post_ids,
				array(
					'collection_name' => $collection_name,
					'entity_type'     => 'post',
				),
			);
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
			self::batch_process(
				'wp_typesense_bulk_upsert',
				$term_ids,
				array(
					'collection_name' => $collection_name,
					'entity_type'     => 'term',
				),
			);
			$reindexed_count += count( $term_ids );
		}
		return $reindexed_count;
	}

	/**
	 * Process data in batches
	 *
	 * @param string $hook Action hook name.
	 * @param array  $entity_ids Entity IDs.
	 * @param array  $args Additional arguments.
	 */
	private static function batch_process( $hook, $entity_ids, $args ) {
		foreach ( array_chunk( $entity_ids, WP_TYPESENSE_BATCH_SIZE ) as $batch ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( $hook, array( $batch, $args ) );
			} else {
				do_action( $hook, $batch, $args );
			}
		}
	}
}
