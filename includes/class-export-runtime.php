<?php
/**
 * Export runtime — time limits and lifecycle hooks for large exports.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prepares PHP for long-running streamed exports.
 */
final class GFA_Export_Runtime {

	/**
	 * Extend execution time and fire pre-export hook.
	 *
	 * @param array<string, mixed> $context Export context (form_ids, range, format, mode).
	 */
	public static function prepare_for_export( array $context ): void {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$seconds = (int) apply_filters( 'gfa_export_time_limit', 300 );
		if ( $seconds > 0 && function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
			@set_time_limit( $seconds );
		}

		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}

		/**
		 * Fires before a CSV or Excel export begins.
		 *
		 * @param array<string, mixed> $context Export context.
		 */
		do_action( 'gfa_before_export', $context );
	}

	/**
	 * Fire post-export hook after rows are written.
	 *
	 * @param array<string, mixed> $context Export context including row_count.
	 */
	public static function finish_export( array $context ): void {
		/**
		 * Fires after export rows are written and before the download response ends.
		 *
		 * @param array<string, mixed> $context Export context.
		 */
		do_action( 'gfa_after_export', $context );
	}
}
