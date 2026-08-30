<?php
/**
 * MVP export scope: columns, modes, formats, and filename rules.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Locked export configuration for Phase 1.
 */
final class GFA_Export_Config {

	public const EXPORT_MODE_ALL_FIELDS   = 'all_fields';
	public const EXPORT_MODE_PHONE_FIELDS = 'phone_fields';

	public const FORMAT_CSV  = 'csv';
	public const FORMAT_XLSX = 'xlsx';

	/**
	 * MVP export columns (long format — one row per field value).
	 *
	 * @return array<string, string> Column key => header label.
	 */
	public static function get_columns(): array {
		$columns = array(
			'form_id'     => __( 'Form ID', 'gravity-forms-aggregator' ),
			'form_title'  => __( 'Form Title', 'gravity-forms-aggregator' ),
			'entry_id'    => __( 'Entry ID', 'gravity-forms-aggregator' ),
			'entry_date'  => __( 'Entry Date', 'gravity-forms-aggregator' ),
			'field_label' => __( 'Field Label', 'gravity-forms-aggregator' ),
			'field_value' => __( 'Field Value', 'gravity-forms-aggregator' ),
		);

		/**
		 * Filter export column definitions.
		 *
		 * @param array<string, string> $columns Column key => header label.
		 */
		return apply_filters( 'gfa_export_columns', $columns );
	}

	/**
	 * Column keys in export order.
	 *
	 * @return string[]
	 */
	public static function get_column_keys(): array {
		return array_keys( self::get_columns() );
	}

	/**
	 * Default export mode for MVP.
	 */
	public static function get_default_export_mode(): string {
		return self::EXPORT_MODE_ALL_FIELDS;
	}

	/**
	 * Supported export modes.
	 *
	 * @return array<string, string> Mode slug => label.
	 */
	public static function get_export_modes(): array {
		$modes = array(
			self::EXPORT_MODE_ALL_FIELDS   => __( 'All fields', 'gravity-forms-aggregator' ),
			self::EXPORT_MODE_PHONE_FIELDS => __( 'Phone fields only', 'gravity-forms-aggregator' ),
		);

		/**
		 * Filter available export modes.
		 *
		 * @param array<string, string> $modes Mode slug => label.
		 */
		return apply_filters( 'gfa_export_modes', $modes );
	}

	/**
	 * Validate export mode slug.
	 *
	 * @param string $mode User-selected mode.
	 */
	public static function is_valid_mode( string $mode ): bool {
		return array_key_exists( $mode, self::get_export_modes() );
	}

	/**
	 * Human-readable label for an export mode.
	 *
	 * @param string $mode Mode slug.
	 */
	public static function get_mode_label( string $mode ): string {
		$modes = self::get_export_modes();

		return $modes[ $mode ] ?? $mode;
	}

	/**
	 * Supported download formats for MVP.
	 *
	 * @return string[]
	 */
	public static function get_supported_formats(): array {
		return array( self::FORMAT_CSV, self::FORMAT_XLSX );
	}

	/**
	 * MIME type for a supported format.
	 *
	 * @param string $format Export format slug.
	 * @return string|null
	 */
	public static function get_mime_type( string $format ): ?string {
		$map = array(
			self::FORMAT_CSV  => 'text/csv; charset=UTF-8',
			self::FORMAT_XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);

		return $map[ $format ] ?? null;
	}

	/**
	 * CSV encoding for Persian/Excel compatibility.
	 */
	public static function get_csv_encoding(): string {
		return 'UTF-8';
	}

	/**
	 * Whether to prepend UTF-8 BOM to CSV output.
	 */
	public static function csv_uses_bom(): bool {
		return true;
	}

	/**
	 * Build a download filename: gf-export_{date}_{count}.{ext}
	 *
	 * @param string $format       csv|xlsx
	 * @param int    $record_count Number of exported rows.
	 * @return string
	 */
	public static function build_filename( string $format, int $record_count ): string {
		$date  = gmdate( 'Y-m-d' );
		$count = max( 0, $record_count );

		return sprintf( 'gf-export_%s_%d.%s', $date, $count, $format );
	}

	/**
	 * Validate export format slug.
	 *
	 * @param string $format User-selected format.
	 * @return bool
	 */
	public static function is_valid_format( string $format ): bool {
		return in_array( $format, self::get_supported_formats(), true );
	}
}
