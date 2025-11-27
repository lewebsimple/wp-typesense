<?php

namespace Websimple\WpTypesense;

/**
 * Hooks for synchronizing WordPress and Typesense
 */
class Hooks {
	/**
	 * Singleton instance
	 *
	 * @var Hooks $instance
	 */
	public static ?Hooks $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Hooks
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Initialize synchronization hooks
	 */
	public function __construct() {
		// Posts
		add_action( 'wp_after_insert_post', array( $this, 'post_updated' ) );
		add_action( 'before_delete_post', array( $this, 'post_deleted' ) );

		// Taxonomy terms
		add_action( 'saved_term', array( $this, 'term_updated' ) );
		add_action( 'delete_term', array( $this, 'term_deleted' ) );

		// WooCommerce
		add_action( 'woocommerce_product_set_stock', array( $this, 'post_updated' ) );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'post_updated' ) );
	}

	/**
	 * Handle post update event
	 *
	 * @param WP_Post|WC_Product|int $post Post object, product object, or post ID.
	 */
	public function post_updated( $post ) {
		if ( is_a( $post, 'WP_Post' ) ) {
			$post_id = $post->ID;
		} elseif ( is_a( $post, 'WC_Product' ) ) {
			$post_id = $post->get_id();
		} elseif ( is_numeric( $post ) ) {
			$post_id = $post;
		} else {
			return;
		}
		$should_remove = ! in_array( get_post_status( $post_id ), apply_filters( 'wp_typesense_indexed_post_status', array( 'publish' ) ) );
		try {
			foreach ( Schemas::get_post_collections( $post_id ) as $collection_name ) {
				if ( $should_remove ) {
					$document_id = Document::encode_id( $collection_name, 'post', $post_id );
					API::get_client()->collections[ $collection_name ]->documents[ $document_id ]->delete();
				} else {
					$document = Document::get_data( $collection_name, 'post', $post_id );
					if ( is_wp_error( $document ) ) {
						continue;
					}
					API::get_client()->collections[ $collection_name ]->documents->upsert( $document );
				}
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Handle post deletion event
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_deleted( $post_id ) {
		try {
			foreach ( Schemas::get_post_collections( $post_id ) as $collection_name ) {
				$document_id = Document::encode_id( $collection_name, 'post', $post_id );
				API::get_client()->collections[ $collection_name ]->documents[ $document_id ]->delete();
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Handle term update event
	 *
	 * @param int $term_id Term ID.
	 */
	public function term_updated( $term_id ) {
		try {
			foreach ( Schemas::get_term_collections( $term_id ) as $collection_name ) {
				$document = Document::get_data( $collection_name, 'term', $term_id );
				if ( is_wp_error( $document ) ) {
					continue;
				}
				API::get_client()->collections[ $collection_name ]->documents->upsert( $document );
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Handle term deletion event
	 *
	 * @param int $term_id Term ID.
	 */
	public function term_deleted( $term_id ) {
		try {
			foreach ( Schemas::get_term_collections( $term_id ) as $collection_name ) {
				$document_id = Document::encode_id( $collection_name, 'term', $term_id );
				API::get_client()->collections[ $collection_name ]->documents[ $document_id ]->delete();
			}
		} catch ( \Exception $e ) {
			return;
		}
	}
}
