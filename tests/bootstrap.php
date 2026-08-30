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

require dirname( __DIR__ ) . '/includes/class-export-config.php';
require dirname( __DIR__ ) . '/includes/class-export-row.php';
