<?php

namespace Websimple\WpTypesense;

/**
 * Typesense API integration class
 */
class API {

	/**
	 * Singleton instance
	 *
	 * @var API $instance
	 */
	public static ?API $instance = null;
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

  /**
	 * Make a request to the Typesense server
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param mixed  $data Request data.
	 *
	 * @return mixed
	 */
	private function request( $endpoint, string $method = 'GET', $data = null ) {
		$args = array(
			'method'  => $method,
			'headers' => array(
				'X-TYPESENSE-API-KEY' => Settings::get_admin_api_key(),
				'Content-Type'        => 'application/json',
			),
		);
		if ( ! empty( $data ) ) {
			$args['body'] = is_string( $data ) ? $data : wp_json_encode( $data );
		}
		$result = wp_remote_request( trailingslashit( Settings::get_server_url() ) . $endpoint, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			$response_code = wp_remote_retrieve_response_code( $result );
			$response_body = wp_remote_retrieve_body( $result );
			$response_body = $this->is_json( $response_body ) ? json_decode( $response_body, true ) : $response_body;
			switch ( $response_code ) {
				case '400':
				case '401':
				case '404':
				case '405':
				case '409':
					return $this->format_error( $response_code, $response_body, $result );
			}
			if ( isset( $response_body['code'] ) ) {
				return new \WP_Error( $response_body['code'], $response_body['error'] ?? 'Unknown Typesense error' );
			}
			return $response_body;
		}
	}

  /**
	 * Check if a string is JSON
	 *
	 * @param string $value String to check.
	 *
	 * @return bool
	 */
	private function is_json( $value ): bool {
		json_decode( $value );
		return json_last_error() === JSON_ERROR_NONE;
	}

  /**
	 * Format an error response from Typesense into a WP_Error object
	 *
	 * @param int   $code HTTP status code.
	 * @param array $body Response body.
	 * @param mixed $result Response object.
	 *
	 * @return \WP_Error
	 */
	private function format_error( $code, $body, $result ) {
		$message  = wp_remote_retrieve_response_message( $result );
		$message .= ! is_null( $body ) && isset( $body['message'] ) ? ' : ' . $body['message'] : '';
		$error    = new \WP_Error( $code, $message );
		return $error;
	}

  /**
	 * Get the server health
	 *
	 * @return mixed
	 */
	static function health() {
		return self::get_instance()->request( 'health' );
	}

  /**
   * Get the server version
   */
  static function version() {
    return self::get_instance()->request( 'debug' )['version'] ?? null;
  }

	/**
	 * List all collections
	 * @return mixed
	 */
	static function collection_list() {
		return self::get_instance()->request( 'collections' );
	}
}
