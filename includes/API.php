<?php

namespace Websimple\WpTypesense;

use Exception;
use Typesense\Client;

/**
 * Typesense API integration class
 */
class API {
	/**
	 * Typesense client
	 *
	 * @var Client $client
	 */
	private Client $client;

	/**
	 * Singleton instance
	 *
	 * @var API $instance
	 */
	public static ?API $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return API
	 */
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	/**
	 * Initialize Typesense client
	 */
	public function __construct() {
		$url_parts    = wp_parse_url( Settings::get_server_url() );
		$this->client = new Client(
			array(
				'api_key' => Settings::get_admin_api_key(),
				'nodes'   => array(
					array(
						'host'     => $url_parts['host'],
						'port'     => $url_parts['port'] ?? 443,
						'protocol' => $url_parts['scheme'],
					),
				),
			)
		);
	}

	/**
	 * Get Typesense client instance
	 *
	 * @return Client
	 */
	public static function get_client(): Client {
		return self::get_instance()->client;
	}

	/**
	 * Check if Typesense server is healthy
	 *
	 * @return bool
	 */
	public static function healthy(): bool {
		try {
			$health = self::get_client()->getHealth()->retrieve();
			return ( $health['ok'] ?? null ) === true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Get Typesense server version
	 *
	 * @return string
	 */
	public static function version() {
		try {
			return self::get_client()->getDebug()->retrieve()['version'];
		} catch ( Exception $e ) {
			return '(unknown)';
		}
	}

	/**
	 * Decode JSONL string to array
	 *
	 * @param string $jsonl JSONL formatted string.
	 *
	 * @return array
	 */
	public static function jsonl_decode( string $jsonl ): array {
		$results = array();
		foreach ( preg_split( '/\R/', $jsonl ) as $line ) {
			$line = trim( $line );
			if ( $line === '' ) { continue;
			}
			$obj = json_decode( $line, true );
			if ( is_array( $obj ) ) {
				$results[] = $obj;
			}
		}
		return $results;
	}

	/**
	 * Encode array to JSONL string
	 *
	 * @param array $data Array of data to encode.
	 *
	 * @return string
	 */
	public static function jsonl_encode( array $data ): string {
		$lines = array();
		foreach ( $data as $item ) {
			$lines[] = wp_json_encode( $item );
		}
		return implode( "\n", $lines );
	}
}
