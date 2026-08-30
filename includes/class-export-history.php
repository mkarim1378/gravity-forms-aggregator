<?php
/**
 * Recent export log per user (user meta, no custom tables).
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight export history for the admin UI.
 */
final class GFA_Export_History {

	private const META_KEY = 'gfa_export_history';

	private const MAX_ENTRIES = 10;

	/**
	 * Record a completed export for the current user.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $entry   Export metadata.
	 */
	public static function record( int $user_id, array $entry ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$normalized = self::normalize_entry( $entry );
		if ( null === $normalized ) {
			return;
		}

		$history   = self::list_entries( $user_id );
		$history[] = $normalized;

		if ( count( $history ) > self::MAX_ENTRIES ) {
			$history = array_slice( $history, -1 * self::MAX_ENTRIES );
		}

		update_user_meta( $user_id, self::META_KEY, $history );

		/**
		 * Fires when an export is logged to user history.
		 *
		 * @param array<string, mixed> $normalized History entry.
		 * @param int                  $user_id    User ID.
		 */
		do_action( 'gfa_export_history_recorded', $normalized, $user_id );
	}

	/**
	 * List recent exports for a user (newest last).
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_entries( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$stored = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$entries = array();

		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$normalized = self::normalize_entry( $entry );
			if ( null !== $normalized ) {
				$entries[] = $normalized;
			}
		}

		return $entries;
	}

	/**
	 * @param array<string, mixed> $entry Raw entry.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_entry( array $entry ): ?array {
		$form_ids = array_values(
			array_filter(
				array_map( 'absint', (array) ( $entry['form_ids'] ?? array() ) ),
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);

		if ( empty( $form_ids ) ) {
			return null;
		}

		$format = isset( $entry['format'] ) ? sanitize_key( (string) $entry['format'] ) : GFA_Export_Config::FORMAT_CSV;
		if ( ! GFA_Export_Config::is_valid_format( $format ) ) {
			$format = GFA_Export_Config::FORMAT_CSV;
		}

		$mode = isset( $entry['export_mode'] ) ? sanitize_key( (string) $entry['export_mode'] ) : GFA_Export_Config::get_default_export_mode();
		if ( ! GFA_Export_Config::is_valid_mode( $mode ) ) {
			$mode = GFA_Export_Config::get_default_export_mode();
		}

		return array(
			'timestamp'   => isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : time(),
			'form_ids'    => $form_ids,
			'form_count'  => count( $form_ids ),
			'entry_count' => max( 0, (int) ( $entry['entry_count'] ?? 0 ) ),
			'format'      => $format,
			'export_mode' => $mode,
			'date_label'  => isset( $entry['date_label'] ) ? sanitize_text_field( (string) $entry['date_label'] ) : '',
		);
	}
}
