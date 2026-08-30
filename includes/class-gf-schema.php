<?php
/**
 * Gravity Forms database schema reference and data-source decision.
 *
 * Phase 2 decision: use GFAPI (not direct SQL) for stability across GF versions.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Documents GF tables and the chosen read strategy.
 */
final class GFA_GF_Schema {

	public const DATA_SOURCE = 'gfapi';

	/**
	 * Standard Gravity Forms custom table names (GF 2.3+).
	 *
	 * @return array<string, string> Logical name => full table name.
	 */
	public static function get_table_names(): array {
		global $wpdb;

		return array(
			'form'       => $wpdb->prefix . 'gf_form',
			'form_meta'  => $wpdb->prefix . 'gf_form_meta',
			'entry'      => $wpdb->prefix . 'gf_entry',
			'entry_meta' => $wpdb->prefix . 'gf_entry_meta',
		);
	}

	/**
	 * Entry columns used by the export layer.
	 *
	 * @return string[]
	 */
	public static function get_entry_columns(): array {
		return array(
			'id',
			'form_id',
			'date_created',
			'date_updated',
			'is_starred',
			'is_read',
			'ip',
			'source_url',
			'user_agent',
			'currency',
			'payment_status',
			'payment_date',
			'payment_amount',
			'transaction_id',
			'is_fulfilled',
			'created_by',
			'transaction_type',
			'status',
		);
	}

	/**
	 * Example GFAPI query (reference / manual test).
	 *
	 * @param int $form_id Form ID to sample.
	 * @return array<string, mixed> Example call shape with live table names.
	 */
	public static function example_query( int $form_id ): array {
		return array(
			'data_source'     => self::DATA_SOURCE,
			'tables'          => self::get_table_names(),
			'gfapi_calls'     => array(
				'forms'   => 'GFAPI::get_forms()',
				'form'    => 'GFAPI::get_form( $form_id )',
				'count'   => 'GFAPI::count_entries( $form_id, $search_criteria )',
				'entries' => 'GFAPI::get_entries( $form_id, $search_criteria, null, $paging, $total )',
			),
			'search_criteria' => array(
				'status'     => 'active',
				'start_date' => '2026-01-01 00:00:00',
				'end_date'   => '2026-08-30 23:59:59',
			),
			'paging'          => array(
				'offset'    => 0,
				'page_size' => 100,
			),
			'form_id'         => $form_id,
		);
	}
}
