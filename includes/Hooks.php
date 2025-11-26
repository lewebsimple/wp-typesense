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
	public static function get_instance() {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}
}
