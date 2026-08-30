<?php
/**
 * WP-CLI commands for development and Phase 2 probing.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * wp gfa probe <form_id>
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
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
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
}
