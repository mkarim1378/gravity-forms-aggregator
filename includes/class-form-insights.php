<?php
/**
 * Form insights — stale active form detection.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detects active forms with no recent entries.
 */
final class GFA_Form_Insights {

	/**
	 * Days without entries before an active form is considered stale.
	 */
	public static function get_stale_threshold_days(): int {
		$days = (int) apply_filters( 'gfa_stale_form_days', 90 );

		return max( 1, $days );
	}

	/**
	 * Whether an active form has no entries within the stale threshold.
	 *
	 * @param bool        $is_active        Form active flag.
	 * @param string|null $last_entry_date  MySQL datetime of newest entry, or null.
	 */
	public static function is_stale_active_form( bool $is_active, ?string $last_entry_date ): bool {
		if ( ! $is_active ) {
			return false;
		}

		if ( null === $last_entry_date || '' === $last_entry_date ) {
			return true;
		}

		$cutoff = strtotime( '-' . self::get_stale_threshold_days() . ' days', time() );
		$last   = strtotime( $last_entry_date );

		if ( false === $cutoff || false === $last ) {
			return false;
		}

		return $last < $cutoff;
	}

	/**
	 * Latest active entry date for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return string|null MySQL datetime or null when no entries exist.
	 */
	public static function get_last_entry_date( int $form_id ): ?string {
		if ( $form_id <= 0 || ! class_exists( 'GFAPI' ) ) {
			return null;
		}

		$entries = GFAPI::get_entries(
			$form_id,
			array( 'status' => 'active' ),
			array( 'key' => 'date_created', 'direction' => 'DESC' ),
			array( 'offset' => 0, 'page_size' => 1 )
		);

		if ( is_wp_error( $entries ) || empty( $entries[0]['date_created'] ) ) {
			return null;
		}

		return (string) $entries[0]['date_created'];
	}

	/**
	 * Stale active forms from a list of form IDs.
	 *
	 * @param int[]                             $form_ids Selected form IDs.
	 * @param array<int, array<string, mixed>>  $forms    Forms from get_forms().
	 * @return int[]
	 */
	public static function get_stale_form_ids( array $form_ids, array $forms ): array {
		$indexed = array();

		foreach ( $forms as $form ) {
			$id = isset( $form['id'] ) ? (int) $form['id'] : 0;
			if ( $id > 0 ) {
				$indexed[ $id ] = $form;
			}
		}

		$stale = array();

		foreach ( $form_ids as $form_id ) {
			$form_id = (int) $form_id;
			if ( $form_id <= 0 || ! isset( $indexed[ $form_id ] ) ) {
				continue;
			}

			$form = $indexed[ $form_id ];
			if ( ! empty( $form['is_stale'] ) ) {
				$stale[] = $form_id;
			}
		}

		return $stale;
	}
}
