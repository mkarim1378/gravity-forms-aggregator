<?php
/**
 * Reads Gravity Forms data via GFAPI and builds unified export rows.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data extraction layer (Phase 2).
 */
final class GFA_Data_Extractor {

	public const BATCH_SIZE = 100;

	/**
	 * List forms for the admin UI.
	 *
	 * @return array<int, array{id: int, title: string, is_active: bool, entry_count: int}>|WP_Error
	 */
	public function get_forms() {
		if ( ! $this->is_gfapi_available() ) {
			return new WP_Error( 'gfa_gfapi_missing', __( 'Gravity Forms API is not available.', 'gravity-forms-aggregator' ) );
		}

		$forms = GFAPI::get_forms( true, false, 'title', 'ASC' );

		if ( is_wp_error( $forms ) ) {
			return $forms;
		}

		$list = array();

		foreach ( $forms as $form ) {
			$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
			if ( $form_id <= 0 ) {
				continue;
			}

			$list[] = array(
				'id'          => $form_id,
				'title'       => isset( $form['title'] ) ? (string) $form['title'] : '',
				'is_active'   => ! empty( $form['is_active'] ),
				'entry_count' => (int) GFAPI::count_entries( $form_id, $this->base_search_criteria() ),
			);
		}

		return $list;
	}

	/**
	 * Count active entries across forms (respects date range).
	 *
	 * @param int[]           $form_ids Sanitized form IDs.
	 * @param GFA_Date_Range  $range    Date filter.
	 * @return int|WP_Error
	 */
	public function count_entries( array $form_ids, GFA_Date_Range $range ) {
		$form_ids = $this->sanitize_form_ids( $form_ids );
		if ( empty( $form_ids ) ) {
			return 0;
		}

		$validation = $range->validate();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! $this->is_gfapi_available() ) {
			return new WP_Error( 'gfa_gfapi_missing', __( 'Gravity Forms API is not available.', 'gravity-forms-aggregator' ) );
		}

		$criteria = $this->build_search_criteria( $range );
		$total    = 0;

		foreach ( $form_ids as $form_id ) {
			$count = GFAPI::count_entries( $form_id, $criteria );
			if ( is_wp_error( $count ) ) {
				return $count;
			}
			$total += (int) $count;
		}

		return $total;
	}

	/**
	 * Per-form entry counts (for preview warnings).
	 *
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @return array<int, int>|WP_Error form_id => count
	 */
	public function count_entries_by_form( array $form_ids, GFA_Date_Range $range ) {
		$form_ids = $this->sanitize_form_ids( $form_ids );
		if ( empty( $form_ids ) ) {
			return array();
		}

		$validation = $range->validate();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! $this->is_gfapi_available() ) {
			return new WP_Error( 'gfa_gfapi_missing', __( 'Gravity Forms API is not available.', 'gravity-forms-aggregator' ) );
		}

		$criteria = $this->build_search_criteria( $range );
		$counts   = array();

		foreach ( $form_ids as $form_id ) {
			$count = GFAPI::count_entries( $form_id, $criteria );
			if ( is_wp_error( $count ) ) {
				return $count;
			}
			$counts[ $form_id ] = (int) $count;
		}

		return $counts;
	}

	/**
	 * Validate inputs before calling iterate_rows().
	 *
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @return true|WP_Error
	 */
	public function validate_export_request( array $form_ids, GFA_Date_Range $range ) {
		$form_ids = $this->sanitize_form_ids( $form_ids );

		if ( empty( $form_ids ) ) {
			return new WP_Error( 'gfa_no_forms', __( 'No valid forms selected.', 'gravity-forms-aggregator' ) );
		}

		$validation = $range->validate();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! $this->is_gfapi_available() ) {
			return new WP_Error( 'gfa_gfapi_missing', __( 'Gravity Forms API is not available.', 'gravity-forms-aggregator' ) );
		}

		return true;
	}

	/**
	 * Iterate unified export rows (long format) with batched entry reads.
	 *
	 * Call validate_export_request() first. On GFAPI failure mid-stream,
	 * iteration stops and get_last_error() returns the WP_Error.
	 *
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @return Generator<int, array<string, string>, mixed, void>
	 */
	public function iterate_rows( array $form_ids, GFA_Date_Range $range ) {
		$this->last_error = null;
		$form_ids         = $this->sanitize_form_ids( $form_ids );
		$criteria         = $this->build_search_criteria( $range );

		foreach ( $form_ids as $form_id ) {
			$form = GFAPI::get_form( $form_id );
			if ( is_wp_error( $form ) ) {
				$this->last_error = $form;
				return;
			}

			$fields     = GFA_Field_Mapper::get_exportable_fields( $form );
			$form_title = isset( $form['title'] ) ? (string) $form['title'] : '';
			$offset     = 0;
			$batch_size = (int) apply_filters( 'gfa_export_batch_size', self::BATCH_SIZE );

			while ( true ) {
				$paging  = array(
					'offset'    => $offset,
					'page_size' => $batch_size,
				);
				$entries = GFAPI::get_entries( $form_id, $criteria, null, $paging );

				if ( is_wp_error( $entries ) ) {
					$this->last_error = $entries;
					return;
				}

				if ( empty( $entries ) ) {
					break;
				}

				foreach ( $entries as $entry ) {
					foreach ( $fields as $mapped ) {
						$row = GFA_Export_Row::from_entry_field( $form_id, $form_title, $entry, $mapped );

						/**
						 * Filter a single export row.
						 *
						 * @param array $row    Export row.
						 * @param array $entry  GF entry.
						 * @param array $mapped Field mapping.
						 */
						$filtered = apply_filters( 'gfa_export_row', $row->to_array(), $entry, $mapped );
						$row      = GFA_Export_Row::from_array( is_array( $filtered ) ? $filtered : $row->to_array() );

						yield $row->to_array();
					}
				}

				if ( count( $entries ) < $batch_size ) {
					break;
				}

				$offset += $batch_size;
			}
		}
	}

	/** @var WP_Error|null */
	private $last_error = null;

	/**
	 * Last GFAPI error from iterate_rows(), if any.
	 *
	 * @return WP_Error|null
	 */
	public function get_last_error(): ?WP_Error {
		return $this->last_error;
	}

	/**
	 * Diagnostic probe for manual / automated testing (Phase 2 sample query).
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function probe( int $form_id ) {
		if ( $form_id <= 0 ) {
			return new WP_Error( 'gfa_invalid_form', __( 'Invalid form ID.', 'gravity-forms-aggregator' ) );
		}

		if ( ! $this->is_gfapi_available() ) {
			return new WP_Error( 'gfa_gfapi_missing', __( 'Gravity Forms API is not available.', 'gravity-forms-aggregator' ) );
		}

		$form = GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$criteria   = $this->base_search_criteria();
		$total      = 0;
		$sample     = GFAPI::get_entries(
			$form_id,
			$criteria,
			array( 'key' => 'date_created', 'direction' => 'DESC' ),
			array( 'offset' => 0, 'page_size' => 1 ),
			$total
		);
		$fields     = GFA_Field_Mapper::get_exportable_fields( $form );
		$sample_row = null;

		if ( ! is_wp_error( $sample ) && ! empty( $sample[0] ) ) {
			$entry      = $sample[0];
			$first      = $fields[0] ?? null;
			$sample_row = $first ? array_merge(
				array(
					'form_id'    => (string) $form_id,
					'form_title' => (string) $form['title'],
					'entry_id'   => (string) $entry['id'],
					'entry_date' => (string) $entry['date_created'],
				),
				array(
					'field_label' => $first['label'],
					'field_value' => GFA_Field_Mapper::get_field_value( $entry, $first ),
				)
			) : null;
		}

		return array(
			'data_source'        => GFA_GF_Schema::DATA_SOURCE,
			'tables'             => GFA_GF_Schema::get_table_names(),
			'example_query'      => GFA_GF_Schema::example_query( $form_id ),
			'form_id'            => $form_id,
			'form_title'         => (string) $form['title'],
			'exportable_fields'  => count( $fields ),
			'active_entry_count' => (int) $total,
			'sample_export_row'  => $sample_row,
		);
	}

	/**
	 * @param int[] $form_ids Raw form IDs.
	 * @return int[]
	 */
	private function sanitize_form_ids( array $form_ids ): array {
		$ids = array();

		foreach ( $form_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Base GF entry filter (active entries only).
	 *
	 * @return array<string, mixed>
	 */
	private function base_search_criteria(): array {
		return array(
			'status' => 'active',
		);
	}

	/**
	 * Merge base criteria with date range.
	 *
	 * @param GFA_Date_Range $range Date filter.
	 * @return array<string, mixed>
	 */
	private function build_search_criteria( GFA_Date_Range $range ): array {
		return array_merge( $this->base_search_criteria(), $range->to_gf_search_criteria() );
	}

	/**
	 * @return bool
	 */
	private function is_gfapi_available(): bool {
		return class_exists( 'GFAPI' );
	}
}
