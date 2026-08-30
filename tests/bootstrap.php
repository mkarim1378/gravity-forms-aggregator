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

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $key String key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
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

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value to test.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	/**
	 * @param string $filename Suggested filename prefix.
	 * @return string|false
	 */
	function wp_tempnam( $filename = '' ) {
		unset( $filename );
		return tempnam( sys_get_temp_dir(), 'gfa-' );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/**
	 * @return DateTimeZone
	 */
	function wp_timezone() {
		return new DateTimeZone( 'UTC' );
	}
}

require dirname( __DIR__ ) . '/includes/class-export-config.php';
require dirname( __DIR__ ) . '/includes/class-date-range.php';
require dirname( __DIR__ ) . '/includes/class-data-extractor.php';
require dirname( __DIR__ ) . '/includes/class-export-row.php';
require dirname( __DIR__ ) . '/includes/class-form-insights.php';
require dirname( __DIR__ ) . '/includes/class-field-mapper.php';
require dirname( __DIR__ ) . '/includes/class-entry-field-resolver.php';
require dirname( __DIR__ ) . '/includes/class-entry-summary-row.php';
require dirname( __DIR__ ) . '/includes/class-export-preview.php';
require dirname( __DIR__ ) . '/includes/export/class-csv-exporter.php';
require dirname( __DIR__ ) . '/includes/export/class-xlsx-writer.php';
require dirname( __DIR__ ) . '/includes/export/class-xlsx-exporter.php';
