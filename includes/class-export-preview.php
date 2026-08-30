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
	 * @return array{
	 *     form_count: int,
	 *     entry_count: int,
	 *     date_label: string,
	 *     empty_form_ids: int[],
	 *     has_entries: bool
	 * }|WP_Error
	 */
	public function get_preview( array $form_ids, GFA_Date_Range $range ) {
		$summary = $this->engine->get_summary( $form_ids, $range );
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
	 *     has_entries: bool
	 * }
	 */
	public static function format_summary( array $summary ): array {
		$empty_form_ids = array_map(
			'intval',
			array_values( (array) ( $summary['empty_form_ids'] ?? array() ) )
		);

		$entry_count = (int) ( $summary['entry_count'] ?? 0 );

		return array(
			'form_count'     => (int) ( $summary['form_count'] ?? 0 ),
			'entry_count'    => $entry_count,
			'date_label'     => (string) ( $summary['date_label'] ?? '' ),
			'empty_form_ids' => $empty_form_ids,
			'has_entries'    => $entry_count > 0,
		);
	}

	/**
	 * Access the underlying export engine.
	 */
	public function get_engine(): GFA_Export_Engine {
		return $this->engine;
	}
}
