<?php

namespace Websimple\WpTypesense;

/**
 * Document management class
 */
class Document {
	/**
	 * Singleton instance
	 *
	 * @var Document $instance
	 */
	public static ?Document $instance = null;
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	public function __construct() {
		add_action( 'wp_typesense_bulk_upsert', array( $this, 'bulk_upsert' ) );
	}

	/**
	 * Encode document ID
	 *
	 * @param string $collection_name Collection name.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 *
	 * @return string
	 */
	public static function encode_document_id( $collection_name, $entity_type, $entity_id ) {
		return "{$collection_name}+{$entity_type}+{$entity_id}";
	}

	/**
	 * Decode document ID
	 *
	 * @param string $document_id Document ID.
	 *
	 * @return array
	 */
	public static function decode_document_id( $document_id ) {
		$parts = explode( '+', $document_id );
		return array(
			'collection_name' => $parts[0] ?? '',
			'entity_type'     => $parts[1] ?? '',
			'entity_id'       => $parts[2] ?? 0,
		);
	}

	/**
	 * Get document data
	 *
	 * @param string $collection_name Collection name.
	 * @param string $entity_type Entity type (post / term).
	 * @param int    $entity_id Entity ID.
	 *
	 * @return array
	 */
	public static function get_document_data( $collection_name, $entity_type, $entity_id ) {
		$document = apply_filters(
			'wp_typesense_document',
			array(),
			array(
				'collection_name' => $collection_name,
				'entity_type'     => $entity_type,
				'entity_id'       => $entity_id,
			)
		);
		if ( empty( $document ) ) {
			return new \WP_Error( 'empty_document', 'Document data is empty' );
		}
		$document['id'] = self::encode_document_id( $collection_name, $entity_type, $entity_id );
		return $document;
	}

	public function bulk_upsert( $data ) {
		$collection_name = $data['collection_name'] ?? false;
		$entity_type     = $data['entity_type'] ?? false;
		$entity_ids      = $data['entity_ids'] ?? array();
		if ( ! $collection_name || ! $entity_type || empty( $entity_ids ) ) {
			return;
		}
		$documents       = array_map(
			function ( $entity_id ) use ( $collection_name, $entity_type ) {
				return Document::get_instance()->get_document_data( $collection_name, $entity_type, $entity_id );
			},
			$entity_ids
		);
		$documents_jsonl = implode( "\n", array_map( 'wp_json_encode', $documents ) );
		API::get_client()->collections[ $collection_name ]->documents->import( $documents_jsonl, array( 'action' => 'upsert' ) );
	}

	public static function prune_collection( $collection_name ) {
		$documents           = API::jsonl_decode( API::get_client()->collections[ $collection_name ]->documents->export() );
		$delete_document_ids = array();
		foreach ( $documents as $document ) {
			if ( ! isset( $document['id'] ) ) {
				continue;
			}
			$decoded = self::decode_document_id( $document['id'] );
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

	public static function reindex_collection( $collection_name ) {
		$collection      = API::get_client()->collections["demo_posts"]->retrieve();
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
				array(
					'collection_name' => $collection_name,
					'entity_type'     => 'post',
					'entity_ids'      => $post_ids,
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
				array(
					'collection_name' => $collection_name,
					'entity_type'     => 'term',
					'entity_ids'      => $term_ids,
				),
			);
			$reindexed_count += count( $term_ids );
		}
		return $reindexed_count;
	}

	private static function batch_process( $hook, $data ) {
		foreach ( array_chunk( $data, WP_TYPESENSE_BATCH_SIZE ) as $batch ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( $hook, array( $batch ) );
			} else {
				do_action( $hook, $batch );
			}
		}
	}
}
