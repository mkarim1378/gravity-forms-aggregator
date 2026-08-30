<?php
/**
 * Minimal bootstrap for unit tests (no full WordPress load).
 *
 * @package GravityFormsAggregator
 */

define( 'ABSPATH', __DIR__ . '/../' );

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		return $value;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for standalone tests.
	 */
	class WP_Error {
		/** @var string */
		private $message;

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code, $message ) {
			$this->message = $message;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

require dirname( __DIR__ ) . '/includes/class-export-config.php';
require dirname( __DIR__ ) . '/includes/class-export-row.php';
require dirname( __DIR__ ) . '/includes/export/class-csv-exporter.php';
