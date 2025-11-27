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

	/**
	 * Get singleton instance
	 *
	 * @return Document
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
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
	public static function encode_id( $collection_name, $entity_type, $entity_id ) {
		return "{$collection_name}+{$entity_type}+{$entity_id}";
	}

	/**
	 * Decode document ID
	 *
	 * @param string $document_id Document ID.
	 *
	 * @return array
	 */
	public static function decode_id( $document_id ) {
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
	public static function get_data( $collection_name, $entity_type, $entity_id ) {
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
		$document['id'] = self::encode_id( $collection_name, $entity_type, $entity_id );
		return $document;
	}
}
