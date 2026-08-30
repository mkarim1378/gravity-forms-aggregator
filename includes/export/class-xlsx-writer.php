<?php
/**
 * Minimal streaming XLSX writer (OOXML via ZipArchive, no external dependencies).
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds a valid single-sheet .xlsx by streaming row XML to a temp file.
 */
final class GFA_Xlsx_Writer {

	/** @var resource|null */
	private $sheet_handle;

	/** @var string */
	private $sheet_temp_path = '';

	/** @var int */
	private $row_number = 0;

	/**
	 * Whether the PHP zip extension is available.
	 */
	public static function is_supported(): bool {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Open a temp stream for incremental sheet XML.
	 *
	 * @return true|WP_Error
	 */
	public function open() {
		if ( ! self::is_supported() ) {
			return new WP_Error(
				'gfa_xlsx_zip_missing',
				__( 'The PHP Zip extension is required for Excel export.', 'gravity-forms-aggregator' )
			);
		}

		$this->sheet_temp_path = wp_tempnam( 'gfa-xlsx-sheet' );
		if ( false === $this->sheet_temp_path || '' === $this->sheet_temp_path ) {
			return new WP_Error(
				'gfa_xlsx_temp_failed',
				__( 'Could not create a temporary Excel worksheet.', 'gravity-forms-aggregator' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$this->sheet_handle = fopen( $this->sheet_temp_path, 'wb' );
		if ( false === $this->sheet_handle ) {
			$this->cleanup_temp_sheet();
			return new WP_Error(
				'gfa_xlsx_sheet_open_failed',
				__( 'Could not open Excel worksheet stream.', 'gravity-forms-aggregator' )
			);
		}

		$header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetData>';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( false === fwrite( $this->sheet_handle, $header ) ) {
			$this->close_sheet_handle();
			$this->cleanup_temp_sheet();
			return new WP_Error(
				'gfa_xlsx_sheet_header_failed',
				__( 'Could not initialize Excel worksheet.', 'gravity-forms-aggregator' )
			);
		}

		return true;
	}

	/**
	 * Write one row of string cell values.
	 *
	 * @param string[] $values Cell values left-to-right.
	 * @return true|WP_Error
	 */
	public function write_row( array $values ) {
		if ( ! is_resource( $this->sheet_handle ) ) {
			return new WP_Error(
				'gfa_xlsx_sheet_not_open',
				__( 'Excel worksheet is not open.', 'gravity-forms-aggregator' )
			);
		}

		++$this->row_number;
		$row_xml = '<row r="' . (string) $this->row_number . '">';

		foreach ( array_values( $values ) as $index => $value ) {
			$column = self::column_letter( (int) $index );
			$ref    = $column . (string) $this->row_number;
			$row_xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>'
				. self::escape_xml_text( (string) $value )
				. '</t></is></c>';
		}

		$row_xml .= '</row>';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( false === fwrite( $this->sheet_handle, $row_xml ) ) {
			return new WP_Error(
				'gfa_xlsx_row_failed',
				__( 'Could not write Excel row.', 'gravity-forms-aggregator' )
			);
		}

		return true;
	}

	/**
	 * Number of data rows written (excluding any header row the caller wrote).
	 */
	public function get_row_count(): int {
		return $this->row_number;
	}

	/**
	 * Finalize sheet XML and pack the workbook into $output_handle.
	 *
	 * @param resource $output_handle Writable stream for the .xlsx bytes.
	 * @return true|WP_Error
	 */
	public function finalize_to_handle( $output_handle ) {
		if ( ! is_resource( $this->sheet_handle ) ) {
			return new WP_Error(
				'gfa_xlsx_sheet_not_open',
				__( 'Excel worksheet is not open.', 'gravity-forms-aggregator' )
			);
		}

		if ( ! is_resource( $output_handle ) ) {
			return new WP_Error(
				'gfa_xlsx_invalid_handle',
				__( 'Invalid Excel output stream.', 'gravity-forms-aggregator' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( false === fwrite( $this->sheet_handle, '</sheetData></worksheet>' ) ) {
			$this->close_sheet_handle();
			$this->cleanup_temp_sheet();
			return new WP_Error(
				'gfa_xlsx_sheet_footer_failed',
				__( 'Could not finalize Excel worksheet.', 'gravity-forms-aggregator' )
			);
		}

		$this->close_sheet_handle();

		$zip_path = wp_tempnam( 'gfa-xlsx-book' );
		if ( false === $zip_path || '' === $zip_path ) {
			$this->cleanup_temp_sheet();
			return new WP_Error(
				'gfa_xlsx_zip_temp_failed',
				__( 'Could not create temporary Excel archive.', 'gravity-forms-aggregator' )
			);
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$this->cleanup_temp_sheet();
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $zip_path );
			return new WP_Error(
				'gfa_xlsx_zip_open_failed',
				__( 'Could not create Excel archive.', 'gravity-forms-aggregator' )
			);
		}

		$zip->addFromString( '[Content_Types].xml', self::content_types_xml() );
		$zip->addFromString( '_rels/.rels', self::root_rels_xml() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook_xml() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels_xml() );
		$zip->addFromString( 'xl/styles.xml', self::styles_xml() );
		$zip->addFile( $this->sheet_temp_path, 'xl/worksheets/sheet1.xml' );
		$zip->close();

		$this->cleanup_temp_sheet();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$zip_handle = fopen( $zip_path, 'rb' );
		if ( false === $zip_handle ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $zip_path );
			return new WP_Error(
				'gfa_xlsx_zip_read_failed',
				__( 'Could not read Excel archive.', 'gravity-forms-aggregator' )
			);
		}

		while ( ! feof( $zip_handle ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$chunk = fread( $zip_handle, 8192 );
			if ( false === $chunk ) {
				fclose( $zip_handle );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $zip_path );
				return new WP_Error(
					'gfa_xlsx_stream_failed',
					__( 'Could not stream Excel file.', 'gravity-forms-aggregator' )
				);
			}

			if ( '' === $chunk ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			if ( false === fwrite( $output_handle, $chunk ) ) {
				fclose( $zip_handle );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $zip_path );
				return new WP_Error(
					'gfa_xlsx_stream_failed',
					__( 'Could not stream Excel file.', 'gravity-forms-aggregator' )
				);
			}
		}

		fclose( $zip_handle );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $zip_path );

		return true;
	}

	/**
	 * 0-based column index to Excel column letters (A, B, …, AA).
	 *
	 * @param int $index Zero-based column index.
	 */
	public static function column_letter( int $index ): string {
		$index = max( 0, $index ) + 1;
		$letters = '';

		while ( $index > 0 ) {
			$mod      = ( $index - 1 ) % 26;
			$letters  = chr( 65 + $mod ) . $letters;
			$index    = (int) floor( ( $index - 1 ) / 26 );
		}

		return $letters;
	}

	/**
	 * Escape text for OOXML inline string cells.
	 *
	 * @param string $value Raw cell text.
	 */
	public static function escape_xml_text( string $value ): string {
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		if ( null === $value ) {
			$value = '';
		}

		return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Close open resources on destruct.
	 */
	public function __destruct() {
		$this->close_sheet_handle();
		$this->cleanup_temp_sheet();
	}

	/**
	 * @param resource|null $handle Writable sheet handle.
	 */
	private function close_sheet_handle(): void {
		if ( is_resource( $this->sheet_handle ) ) {
			fclose( $this->sheet_handle );
		}

		$this->sheet_handle = null;
	}

	private function cleanup_temp_sheet(): void {
		if ( '' !== $this->sheet_temp_path && file_exists( $this->sheet_temp_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $this->sheet_temp_path );
		}

		$this->sheet_temp_path = '';
	}

	private static function content_types_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private static function root_rels_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private static function workbook_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
			. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private static function workbook_rels_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	private static function styles_xml(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
			. '<borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
			. '</styleSheet>';
	}
}
