<?php
/**
 * Export preview — entry counts and warnings before download.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Phase 7 preview layer over GFA_Export_Engine::get_summary().
 */
final class GFA_Export_Preview {

	/** @var GFA_Export_Engine */
	private $engine;

	public function __construct( ?GFA_Export_Engine $engine = null ) {
		$this->engine = $engine ?? new GFA_Export_Engine();
	}

	/**
	 * Build a preview payload for the selected forms and date range.
	 *
	 * @param int[]          $form_ids Sanitized form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param string         $mode     Export mode slug.
	 * @return array{
	 *     form_count: int,
	 *     entry_count: int,
	 *     date_label: string,
	 *     empty_form_ids: int[],
	 *     stale_form_ids: int[],
	 *     has_entries: bool,
	 *     export_mode: string,
	 *     export_mode_label: string
	 * }|WP_Error
	 */
	public function get_preview( array $form_ids, GFA_Date_Range $range, string $mode = '' ) {
		$summary = $this->engine->get_summary( $form_ids, $range, $mode );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		return self::format_summary( $summary );
	}

	/**
	 * Normalize engine summary for UI/JSON responses.
	 *
	 * @param array<string, mixed> $summary Raw summary from GFA_Export_Engine.
	 * @return array{
	 *     form_count: int,
	 *     entry_count: int,
	 *     date_label: string,
	 *     empty_form_ids: int[],
	 *     stale_form_ids: int[],
	 *     has_entries: bool,
	 *     export_mode: string,
	 *     export_mode_label: string
	 * }
	 */
	public static function format_summary( array $summary ): array {
		$empty_form_ids = array_map(
			'intval',
			array_values( (array) ( $summary['empty_form_ids'] ?? array() ) )
		);
		$stale_form_ids = array_map(
			'intval',
			array_values( (array) ( $summary['stale_form_ids'] ?? array() ) )
		);

		$entry_count = (int) ( $summary['entry_count'] ?? 0 );
		$mode        = isset( $summary['export_mode'] ) ? sanitize_key( (string) $summary['export_mode'] ) : GFA_Export_Config::get_default_export_mode();

		return array(
			'form_count'          => (int) ( $summary['form_count'] ?? 0 ),
			'entry_count'         => $entry_count,
			'date_label'          => (string) ( $summary['date_label'] ?? '' ),
			'empty_form_ids'      => $empty_form_ids,
			'stale_form_ids'      => $stale_form_ids,
			'has_entries'         => $entry_count > 0,
			'export_mode'         => $mode,
			'export_mode_label'   => (string) ( $summary['export_mode_label'] ?? GFA_Export_Config::get_mode_label( $mode ) ),
		);
	}

	/**
	 * Access the underlying export engine.
	 */
	public function get_engine(): GFA_Export_Engine {
		return $this->engine;
	}
}
