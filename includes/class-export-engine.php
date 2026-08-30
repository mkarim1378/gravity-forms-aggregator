<?php
/**
 * Export engine — validates requests, summarizes data, and streams unified rows.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Phase 4 orchestration layer over GFA_Data_Extractor.
 */
final class GFA_Export_Engine {

	/** @var GFA_Data_Extractor */
	private $extractor;

	public function __construct( ?GFA_Data_Extractor $extractor = null ) {
		$this->extractor = $extractor ?? new GFA_Data_Extractor();
	}

	/**
	 * Entry and form counts for a validated export request.
	 *
	 * @param int[]          $form_ids Sanitized form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param string         $mode     Export mode slug.
	 * @return array{
	 *     form_count: int,
	 *     entry_count: int,
	 *     form_counts: array<int, int>,
	 *     empty_form_ids: int[],
	 *     stale_form_ids: int[],
	 *     date_label: string,
	 *     export_mode: string,
	 *     export_mode_label: string
	 * }|WP_Error
	 */
	public function get_summary( array $form_ids, GFA_Date_Range $range, string $mode = '' ) {
		$validation = $this->extractor->validate_export_request( $form_ids, $range );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$mode = $this->normalize_export_mode( $mode );

		$form_counts = $this->extractor->count_entries_by_form( $form_ids, $range );
		if ( is_wp_error( $form_counts ) ) {
			return $form_counts;
		}

		$entry_count    = 0;
		$empty_form_ids = array();

		foreach ( $form_counts as $form_id => $count ) {
			$entry_count += (int) $count;
			if ( 0 === (int) $count ) {
				$empty_form_ids[] = (int) $form_id;
			}
		}

		$forms = $this->extractor->get_forms();
		$stale = array();
		if ( ! is_wp_error( $forms ) ) {
			$stale = GFA_Form_Insights::get_stale_form_ids( $form_ids, $forms );
		}

		return array(
			'form_count'          => count( $form_counts ),
			'entry_count'         => $entry_count,
			'form_counts'         => $form_counts,
			'empty_form_ids'      => $empty_form_ids,
			'stale_form_ids'      => $stale,
			'date_label'          => $range->get_label(),
			'export_mode'         => $mode,
			'export_mode_label'   => GFA_Export_Config::get_mode_label( $mode ),
		);
	}

	/**
	 * Stream unified export rows for the selected forms and date range.
	 *
	 * Call validate_export_request() first, or rely on iterate_rows validation.
	 *
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param string         $mode     Export mode slug.
	 * @return Generator<int, GFA_Export_Row, mixed, void>
	 */
	public function iterate_rows( array $form_ids, GFA_Date_Range $range, string $mode = '' ): Generator {
		$validation = $this->extractor->validate_export_request( $form_ids, $range );
		if ( is_wp_error( $validation ) ) {
			return;
		}

		$mode = $this->normalize_export_mode( $mode );

		foreach ( $this->extractor->iterate_rows( $form_ids, $range, $mode ) as $row ) {
			yield GFA_Export_Row::from_array( $row );
		}
	}

	/**
	 * Collect up to $limit rows — used by WP-CLI and integration checks.
	 *
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param int            $limit    Max rows to collect (0 = no limit).
	 * @param string         $mode     Export mode slug.
	 * @return array{
	 *     summary: array<string, mixed>,
	 *     rows: array<int, array<string, string>>,
	 *     truncated: bool
	 * }|WP_Error
	 */
	public function collect_sample( array $form_ids, GFA_Date_Range $range, int $limit = 10, string $mode = '' ) {
		$mode    = $this->normalize_export_mode( $mode );
		$summary = $this->get_summary( $form_ids, $range, $mode );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		$limit = max( 0, $limit );
		$rows  = array();

		foreach ( $this->iterate_rows( $form_ids, $range, $mode ) as $row ) {
			if ( ! $row->is_valid() ) {
				continue;
			}

			$rows[] = $row->to_array();

			if ( $limit > 0 && count( $rows ) >= $limit ) {
				break;
			}
		}

		$error = $this->extractor->get_last_error();
		if ( $error instanceof WP_Error ) {
			return $error;
		}

		$truncated = $limit > 0 && count( $rows ) >= $limit;

		return array(
			'summary'   => $summary,
			'rows'      => $rows,
			'truncated' => $truncated,
		);
	}

	/**
	 * Last GFAPI error from the underlying extractor, if any.
	 */
	public function get_last_error(): ?WP_Error {
		return $this->extractor->get_last_error();
	}

	/**
	 * Access the underlying data extractor (forms list, probe, etc.).
	 */
	public function get_extractor(): GFA_Data_Extractor {
		return $this->extractor;
	}

	/**
	 * @param string $mode Raw export mode.
	 */
	private function normalize_export_mode( string $mode ): string {
		$mode = sanitize_key( $mode );

		if ( ! GFA_Export_Config::is_valid_mode( $mode ) ) {
			return GFA_Export_Config::get_default_export_mode();
		}

		return $mode;
	}
}
