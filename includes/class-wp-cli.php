<?php
/**
 * WP-CLI commands for development and extraction testing.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * wp gfa probe <form_id>
 * wp gfa extract --form-ids=1,2 [--from=] [--to=] [--limit=10]
 */
final class GFA_WP_CLI {

	/**
	 * Probe GFAPI connectivity and sample export row for a form.
	 *
	 * ## OPTIONS
	 *
	 * <form_id>
	 * : Gravity Forms form ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gfa probe 1
	 *
	 * @param array<int, string> $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function probe( array $args, array $assoc_args ): void {
		$form_id = isset( $args[0] ) ? absint( $args[0] ) : 0;

		if ( $form_id <= 0 ) {
			WP_CLI::error( 'Provide a valid form ID.' );
		}

		$extractor = new GFA_Data_Extractor();
		$result    = $extractor->probe( $form_id );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::log( 'Data source: ' . $result['data_source'] );
		WP_CLI::log( 'Form: [' . $result['form_id'] . '] ' . $result['form_title'] );
		WP_CLI::log( 'Exportable fields: ' . $result['exportable_fields'] );
		WP_CLI::log( 'Active entries: ' . $result['active_entry_count'] );

		if ( ! empty( $result['sample_export_row'] ) ) {
			WP_CLI::log( 'Sample row:' );
			WP_CLI\Utils\format_items( 'table', array( $result['sample_export_row'] ), array_keys( $result['sample_export_row'] ) );
		} else {
			WP_CLI::warning( 'No entries found for sample row.' );
		}
	}

	/**
	 * Run the export engine against selected forms (integration test).
	 *
	 * ## OPTIONS
	 *
	 * [--form-ids=<ids>]
	 * : Comma-separated form IDs.
	 *
	 * [--from=<date>]
	 * : Start date (Y-m-d).
	 *
	 * [--to=<date>]
	 * : End date (Y-m-d).
	 *
	 * [--limit=<n>]
	 * : Max sample rows to print (default: 10, 0 = summary only).
	 *
	 * ## EXAMPLES
	 *
	 *     wp gfa extract --form-ids=1,2
	 *     wp gfa extract --form-ids=1 --from=2024-01-01 --to=2024-12-31 --limit=5
	 *
	 * @param array<int, string> $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function extract( array $args, array $assoc_args ): void {
		$form_ids = $this->parse_form_ids( $assoc_args );
		if ( empty( $form_ids ) ) {
			WP_CLI::error( 'Provide --form-ids=1,2 with at least one valid form ID.' );
		}

		$from  = isset( $assoc_args['from'] ) ? (string) $assoc_args['from'] : '';
		$to    = isset( $assoc_args['to'] ) ? (string) $assoc_args['to'] : '';
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;

		$range  = new GFA_Date_Range( '' !== $from ? $from : null, '' !== $to ? $to : null );
		$engine = new GFA_Export_Engine();

		if ( 0 === $limit ) {
			$summary = $engine->get_summary( $form_ids, $range );
			if ( is_wp_error( $summary ) ) {
				WP_CLI::error( $summary->get_error_message() );
			}

			WP_CLI::log( 'Date range: ' . $summary['date_label'] );
			WP_CLI::log( 'Forms: ' . $summary['form_count'] );
			WP_CLI::log( 'Entries: ' . $summary['entry_count'] );

			if ( ! empty( $summary['empty_form_ids'] ) ) {
				WP_CLI::warning( 'Empty forms: ' . implode( ', ', $summary['empty_form_ids'] ) );
			}

			WP_CLI::success( 'Summary complete.' );
			return;
		}

		$result = $engine->collect_sample( $form_ids, $range, $limit );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$summary = $result['summary'];
		WP_CLI::log( 'Date range: ' . $summary['date_label'] );
		WP_CLI::log( 'Entries: ' . $summary['entry_count'] );
		WP_CLI::log( 'Sample rows: ' . count( $result['rows'] ) . ( $result['truncated'] ? ' (truncated)' : '' ) );

		if ( ! empty( $result['rows'] ) ) {
			WP_CLI\Utils\format_items( 'table', $result['rows'], GFA_Export_Config::get_column_keys() );
		} else {
			WP_CLI::warning( 'No export rows found for the given criteria.' );
		}

		WP_CLI::success( 'Extraction complete.' );
	}

	/**
	 * Export selected forms to a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * [--form-ids=<ids>]
	 * : Comma-separated form IDs.
	 *
	 * [--from=<date>]
	 * : Start date (Y-m-d).
	 *
	 * [--to=<date>]
	 * : End date (Y-m-d).
	 *
	 * [--output=<path>]
	 * : Write CSV to this file path (default: stdout).
	 *
	 * ## EXAMPLES
	 *
	 *     wp gfa export-csv --form-ids=1,2 --output=/tmp/export.csv
	 *     wp gfa export-csv --form-ids=1 --from=2024-01-01
	 *
	 * @param array<int, string>   $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function export_csv( array $args, array $assoc_args ): void {
		$form_ids = $this->parse_form_ids( $assoc_args );
		if ( empty( $form_ids ) ) {
			WP_CLI::error( 'Provide --form-ids=1,2 with at least one valid form ID.' );
		}

		$from    = isset( $assoc_args['from'] ) ? (string) $assoc_args['from'] : '';
		$to      = isset( $assoc_args['to'] ) ? (string) $assoc_args['to'] : '';
		$output  = isset( $assoc_args['output'] ) ? (string) $assoc_args['output'] : '';
		$range   = new GFA_Date_Range( '' !== $from ? $from : null, '' !== $to ? $to : null );
		$engine  = new GFA_Export_Engine();
		$exporter = new GFA_Csv_Exporter( $engine );

		if ( '' !== $output ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$handle = fopen( $output, 'w' );
			if ( false === $handle ) {
				WP_CLI::error( 'Could not open output file: ' . $output );
			}

			$row_count = $exporter->write_to_handle( $handle, $form_ids, $range );
			fclose( $handle );

			if ( is_wp_error( $row_count ) ) {
				WP_CLI::error( $row_count->get_error_message() );
			}

			$error = $engine->get_last_error();
			if ( $error instanceof WP_Error ) {
				WP_CLI::error( $error->get_error_message() );
			}

			WP_CLI::success( sprintf( 'Wrote %d row(s) to %s', (int) $row_count, $output ) );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( 'php://output', 'w' );
		if ( false === $handle ) {
			WP_CLI::error( 'Could not open stdout.' );
		}

		$row_count = $exporter->write_to_handle( $handle, $form_ids, $range );
		fclose( $handle );

		if ( is_wp_error( $row_count ) ) {
			WP_CLI::error( $row_count->get_error_message() );
		}

		$error = $engine->get_last_error();
		if ( $error instanceof WP_Error ) {
			WP_CLI::error( $error->get_error_message() );
		}

		WP_CLI::log( '' );
		WP_CLI::success( sprintf( 'Exported %d row(s) to stdout.', (int) $row_count ) );
	}

	/**
	 * Export selected forms to an XLSX file.
	 *
	 * ## OPTIONS
	 *
	 * [--form-ids=<ids>]
	 * : Comma-separated form IDs.
	 *
	 * [--from=<date>]
	 * : Start date (Y-m-d).
	 *
	 * [--to=<date>]
	 * : End date (Y-m-d).
	 *
	 * [--output=<path>]
	 * : Write XLSX to this file path (default: stdout).
	 *
	 * ## EXAMPLES
	 *
	 *     wp gfa export-xlsx --form-ids=1,2 --output=/tmp/export.xlsx
	 *     wp gfa export-xlsx --form-ids=1 --from=2024-01-01
	 *
	 * @param array<int, string>   $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function export_xlsx( array $args, array $assoc_args ): void {
		if ( ! GFA_Xlsx_Writer::is_supported() ) {
			WP_CLI::error( 'The PHP Zip extension is required for Excel export.' );
		}

		$form_ids = $this->parse_form_ids( $assoc_args );
		if ( empty( $form_ids ) ) {
			WP_CLI::error( 'Provide --form-ids=1,2 with at least one valid form ID.' );
		}

		$from     = isset( $assoc_args['from'] ) ? (string) $assoc_args['from'] : '';
		$to       = isset( $assoc_args['to'] ) ? (string) $assoc_args['to'] : '';
		$output   = isset( $assoc_args['output'] ) ? (string) $assoc_args['output'] : '';
		$range    = new GFA_Date_Range( '' !== $from ? $from : null, '' !== $to ? $to : null );
		$engine   = new GFA_Export_Engine();
		$exporter = new GFA_Xlsx_Exporter( $engine );

		if ( '' !== $output ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$handle = fopen( $output, 'wb' );
			if ( false === $handle ) {
				WP_CLI::error( 'Could not open output file: ' . $output );
			}

			$row_count = $exporter->write_to_handle( $handle, $form_ids, $range );
			fclose( $handle );

			if ( is_wp_error( $row_count ) ) {
				WP_CLI::error( $row_count->get_error_message() );
			}

			$error = $engine->get_last_error();
			if ( $error instanceof WP_Error ) {
				WP_CLI::error( $error->get_error_message() );
			}

			WP_CLI::success( sprintf( 'Wrote %d row(s) to %s', (int) $row_count, $output ) );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( 'php://output', 'wb' );
		if ( false === $handle ) {
			WP_CLI::error( 'Could not open stdout.' );
		}

		$row_count = $exporter->write_to_handle( $handle, $form_ids, $range );
		fclose( $handle );

		if ( is_wp_error( $row_count ) ) {
			WP_CLI::error( $row_count->get_error_message() );
		}

		$error = $engine->get_last_error();
		if ( $error instanceof WP_Error ) {
			WP_CLI::error( $error->get_error_message() );
		}

		WP_CLI::log( '' );
		WP_CLI::success( sprintf( 'Exported %d row(s) to stdout.', (int) $row_count ) );
	}

	/**
	 * @param array<string, mixed> $assoc_args CLI associative args.
	 * @return int[]
	 */
	private function parse_form_ids( array $assoc_args ): array {
		if ( empty( $assoc_args['form-ids'] ) ) {
			return array();
		}

		$parts = explode( ',', (string) $assoc_args['form-ids'] );
		$ids   = array();

		foreach ( $parts as $part ) {
			$id = absint( trim( $part ) );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}
}
