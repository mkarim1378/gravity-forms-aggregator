<?php
/**
 * Streams unified export data as a downloadable XLSX file.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Excel exporter using GFA_Xlsx_Writer (ZipArchive + OOXML, no Composer deps).
 */
final class GFA_Xlsx_Exporter {

	/** @var GFA_Export_Engine */
	private $engine;

	public function __construct( ?GFA_Export_Engine $engine = null ) {
		$this->engine = $engine;
	}

	/**
	 * Build XLSX in a temp stream, then send as a file download.
	 *
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param string         $mode     Export mode slug.
	 * @return true|WP_Error
	 */
	public function download( array $form_ids, GFA_Date_Range $range, string $mode = '' ) {
		if ( ! $this->engine instanceof GFA_Export_Engine ) {
			return new WP_Error(
				'gfa_xlsx_engine_missing',
				__( 'Export engine is not available.', 'gravity-forms-aggregator' )
			);
		}

		$mode = $this->normalize_export_mode( $mode );

		GFA_Export_Runtime::prepare_for_export(
			array(
				'form_ids'    => $form_ids,
				'range'       => $range,
				'format'      => GFA_Export_Config::FORMAT_XLSX,
				'export_mode' => $mode,
			)
		);

		$handle = fopen( 'php://temp', 'w+' );
		if ( false === $handle ) {
			return new WP_Error(
				'gfa_xlsx_stream_failed',
				__( 'Could not open export stream.', 'gravity-forms-aggregator' )
			);
		}

		$row_count = $this->write_to_handle( $handle, $form_ids, $range, $mode );
		if ( is_wp_error( $row_count ) ) {
			fclose( $handle );
			return $row_count;
		}

		$error = $this->engine->get_last_error();
		if ( $error instanceof WP_Error ) {
			fclose( $handle );
			return $error;
		}

		$summary = $this->engine->get_summary( $form_ids, $range, $mode );
		if ( ! is_wp_error( $summary ) ) {
			$this->record_history( $summary, GFA_Export_Config::FORMAT_XLSX, (int) $row_count );
		}

		GFA_Export_Runtime::finish_export(
			array(
				'form_ids'    => $form_ids,
				'range'       => $range,
				'format'      => GFA_Export_Config::FORMAT_XLSX,
				'export_mode' => $mode,
				'row_count'   => (int) $row_count,
			)
		);

		rewind( $handle );

		$filename = GFA_Export_Config::build_filename( GFA_Export_Config::FORMAT_XLSX, (int) $row_count );
		$this->send_download_headers( $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fpassthru
		fpassthru( $handle );
		fclose( $handle );

		exit;
	}

	/**
	 * Write XLSX header and rows to an open write handle.
	 *
	 * @param resource       $handle   Writable stream.
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param string         $mode     Export mode slug.
	 * @return int|WP_Error Number of data rows written (excluding header).
	 */
	public function write_to_handle( $handle, array $form_ids, GFA_Date_Range $range, string $mode = '' ) {
		if ( ! $this->engine instanceof GFA_Export_Engine ) {
			return new WP_Error(
				'gfa_xlsx_engine_missing',
				__( 'Export engine is not available.', 'gravity-forms-aggregator' )
			);
		}

		if ( ! is_resource( $handle ) ) {
			return new WP_Error(
				'gfa_xlsx_invalid_handle',
				__( 'Invalid export stream handle.', 'gravity-forms-aggregator' )
			);
		}

		$writer = new GFA_Xlsx_Writer();
		$opened = $writer->open();
		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		$header_written = $writer->write_row( array_values( GFA_Export_Config::get_columns() ) );
		if ( is_wp_error( $header_written ) ) {
			return $header_written;
		}

		$row_count = $this->write_rows_to_writer( $writer, $this->engine->iterate_rows( $form_ids, $range, $mode ) );
		if ( is_wp_error( $row_count ) ) {
			return $row_count;
		}

		$finalized = $writer->finalize_to_handle( $handle );
		if ( is_wp_error( $finalized ) ) {
			return $finalized;
		}

		return $row_count;
	}

	/**
	 * Write export rows to an open Xlsx writer (header not included).
	 *
	 * @param GFA_Xlsx_Writer $writer Open writer instance.
	 * @param iterable        $rows   GFA_Export_Row instances or row arrays.
	 * @return int|WP_Error Number of data rows written.
	 */
	public function write_rows_to_writer( GFA_Xlsx_Writer $writer, iterable $rows ) {
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

			$written = $writer->write_row( $export_row->to_ordered_values() );
			if ( is_wp_error( $written ) ) {
				return $written;
			}

			++$row_count;
		}

		return $row_count;
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

		$mime = GFA_Export_Config::get_mime_type( GFA_Export_Config::FORMAT_XLSX );
		if ( null !== $mime ) {
			header( 'Content-Type: ' . $mime );
		}

		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * @param array<string, mixed> $summary  Export summary.
	 * @param string               $format Export format.
	 * @param int                  $rows   Rows written.
	 */
	private function record_history( array $summary, string $format, int $rows ): void {
		if ( ! function_exists( 'get_current_user_id' ) ) {
			return;
		}

		GFA_Export_History::record(
			get_current_user_id(),
			array(
				'timestamp'   => time(),
				'form_ids'    => array_keys( (array) ( $summary['form_counts'] ?? array() ) ),
				'entry_count' => (int) ( $summary['entry_count'] ?? $rows ),
				'format'      => $format,
				'export_mode' => (string) ( $summary['export_mode'] ?? GFA_Export_Config::get_default_export_mode() ),
				'date_label'  => (string) ( $summary['date_label'] ?? '' ),
			)
		);
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
