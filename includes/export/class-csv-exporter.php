<?php
/**
 * Streams unified export data as a downloadable CSV file.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSV writer with UTF-8 BOM and RFC 4180-style escaping via fputcsv().
 */
final class GFA_Csv_Exporter {

	/** @var GFA_Export_Engine */
	private $engine;

	public function __construct( ?GFA_Export_Engine $engine = null ) {
		$this->engine = $engine;
	}

	/**
	 * Build CSV in a temp stream, then send as a file download.
	 *
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @return true|WP_Error
	 */
	public function download( array $form_ids, GFA_Date_Range $range ) {
		if ( ! $this->engine instanceof GFA_Export_Engine ) {
			return new WP_Error(
				'gfa_csv_engine_missing',
				__( 'Export engine is not available.', 'gravity-forms-aggregator' )
			);
		}

		$handle = fopen( 'php://temp', 'w+' );
		if ( false === $handle ) {
			return new WP_Error(
				'gfa_csv_stream_failed',
				__( 'Could not open export stream.', 'gravity-forms-aggregator' )
			);
		}

		$row_count = $this->write_to_handle( $handle, $form_ids, $range );
		if ( is_wp_error( $row_count ) ) {
			fclose( $handle );
			return $row_count;
		}

		$error = $this->engine->get_last_error();
		if ( $error instanceof WP_Error ) {
			fclose( $handle );
			return $error;
		}

		rewind( $handle );

		$filename = GFA_Export_Config::build_filename( GFA_Export_Config::FORMAT_CSV, (int) $row_count );
		$this->send_download_headers( $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fpassthru
		fpassthru( $handle );
		fclose( $handle );

		exit;
	}

	/**
	 * Write CSV header and rows to an open write handle.
	 *
	 * @param resource       $handle   Writable stream.
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @return int|WP_Error Number of data rows written (excluding header).
	 */
	public function write_to_handle( $handle, array $form_ids, GFA_Date_Range $range ) {
		if ( ! $this->engine instanceof GFA_Export_Engine ) {
			return new WP_Error(
				'gfa_csv_engine_missing',
				__( 'Export engine is not available.', 'gravity-forms-aggregator' )
			);
		}

		if ( ! is_resource( $handle ) ) {
			return new WP_Error(
				'gfa_csv_invalid_handle',
				__( 'Invalid export stream handle.', 'gravity-forms-aggregator' )
			);
		}

		if ( GFA_Export_Config::csv_uses_bom() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fwrite( $handle, "\xEF\xBB\xBF" );
		}

		$header_written = $this->put_csv_row( $handle, array_values( GFA_Export_Config::get_columns() ) );
		if ( false === $header_written ) {
			return new WP_Error(
				'gfa_csv_header_failed',
				__( 'Could not write CSV header.', 'gravity-forms-aggregator' )
			);
		}

		return $this->write_rows_to_handle( $handle, $this->engine->iterate_rows( $form_ids, $range ) );
	}

	/**
	 * Write export rows to an open handle (header not included).
	 *
	 * @param resource $handle Writable stream.
	 * @param iterable $rows   GFA_Export_Row instances or row arrays.
	 * @return int|WP_Error Number of data rows written.
	 */
	public function write_rows_to_handle( $handle, iterable $rows ) {
		if ( ! is_resource( $handle ) ) {
			return new WP_Error(
				'gfa_csv_invalid_handle',
				__( 'Invalid export stream handle.', 'gravity-forms-aggregator' )
			);
		}

		$row_count = 0;

		foreach ( $rows as $row ) {
			if ( $row instanceof GFA_Export_Row ) {
				$export_row = $row;
			} elseif ( is_array( $row ) ) {
				$export_row = GFA_Export_Row::from_array( $row );
			} else {
				continue;
			}

			if ( ! $export_row->is_valid() ) {
				continue;
			}

			$written = $this->put_csv_row( $handle, $export_row->to_ordered_values() );
			if ( false === $written ) {
				return new WP_Error(
					'gfa_csv_row_failed',
					__( 'Could not write CSV row.', 'gravity-forms-aggregator' )
				);
			}

			++$row_count;
		}

		return $row_count;
	}

	/**
	 * @param resource $handle Writable stream.
	 * @param string[] $fields Field values.
	 * @return int|false
	 */
	private function put_csv_row( $handle, array $fields ) {
		return fputcsv( $handle, $fields, ',', '"', '\\' );
	}

	/**
	 * @param string $filename Download filename.
	 */
	private function send_download_headers( string $filename ): void {
		if ( function_exists( 'ob_get_level' ) ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
		}

		nocache_headers();

		$mime = GFA_Export_Config::get_mime_type( GFA_Export_Config::FORMAT_CSV );
		if ( null !== $mime ) {
			header( 'Content-Type: ' . $mime );
		}

		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'X-Content-Type-Options: nosniff' );
	}
}
