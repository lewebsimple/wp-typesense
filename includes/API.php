<?php

namespace Websimple\WpTypesense;

use Exception;
use Typesense\Client;

/**
 * Typesense API integration class
 */
class API {

	private Client $client;

	/**
	 * Singleton instance
	 *
	 * @var API $instance
	 */
	public static ?API $instance = null;
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	public function __construct() {
		$url_parts = parse_url(Settings::get_server_url());
		$this->client = new Client([
			'api_key'         => Settings::get_admin_api_key(),
			'nodes'           => [
				[
					'host'     => $url_parts['host'],
					'port'     => $url_parts['port'] ?? 443,
					'protocol' => $url_parts['scheme'],
				],
			],
		]);
	}

	static function get_client(): Client {
		return self::get_instance()->client;
	}

	static function healthy(): bool {
		try {
			$health = self::get_client()->getHealth()->retrieve();
			return ($health['ok'] ?? null) === true;
		} catch (Exception $e) {
			return false;
		}
	}

	static function version() {
		try {
			return self::get_client()->getDebug()->retrieve()['version'];
		} catch(Exception $e) {
			return '(unknown)';
		}
	}

}
