<?php
/**
 * Date range filter rules for entry export.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parses and validates from/to dates for GF entry queries.
 *
 * Behavior (Phase 1):
 * - Both empty  → no date filter (all entries)
 * - From only   → entries on/after from (start of day, site TZ)
 * - To only     → entries on/before to (end of day, site TZ)
 * - Both set    → inclusive range; from must not be after to
 */
final class GFA_Date_Range {

	/** @var string|null Y-m-d */
	private $from;

	/** @var string|null Y-m-d */
	private $to;

	/**
	 * @param string|null $from From date (any strtotime-compatible string).
	 * @param string|null $to   To date (any strtotime-compatible string).
	 */
	public function __construct( ?string $from, ?string $to ) {
		$this->from = self::normalize_date( $from );
		$this->to   = self::normalize_date( $to );
	}

	/**
	 * Whether any date filter applies.
	 */
	public function has_filter(): bool {
		return null !== $this->from || null !== $this->to;
	}

	/**
	 * @return string|null Normalized from date (Y-m-d).
	 */
	public function get_from(): ?string {
		return $this->from;
	}

	/**
	 * @return string|null Normalized to date (Y-m-d).
	 */
	public function get_to(): ?string {
		return $this->to;
	}

	/**
	 * Validate range; returns WP_Error when from is after to.
	 *
	 * @return true|WP_Error
	 */
	public function validate() {
		if ( null !== $this->from && null !== $this->to && $this->from > $this->to ) {
			return new WP_Error(
				'gfa_invalid_date_range',
				__( 'The start date must not be after the end date.', 'gravity-forms-aggregator' )
			);
		}

		return true;
	}

	/**
	 * Bounds for GFAPI / SQL filters (site timezone, inclusive).
	 *
	 * @return array{start: string|null, end: string|null} MySQL datetime strings or null.
	 */
	public function to_query_bounds(): array {
		$tz = wp_timezone();

		$start = null;
		$end   = null;

		if ( null !== $this->from ) {
			$start = ( new DateTimeImmutable( $this->from . ' 00:00:00', $tz ) )
				->format( 'Y-m-d H:i:s' );
		}

		if ( null !== $this->to ) {
			$end = ( new DateTimeImmutable( $this->to . ' 23:59:59', $tz ) )
				->format( 'Y-m-d H:i:s' );
		}

		/**
		 * Filter computed query date bounds.
		 *
		 * @param array{start: string|null, end: string|null} $bounds Start/end datetimes.
		 * @param GFA_Date_Range                              $range  Date range instance.
		 */
		return apply_filters( 'gfa_date_range_bounds', compact( 'start', 'end' ), $this );
	}

	/**
	 * Build search criteria compatible with GFAPI::get_entries().
	 *
	 * @return array<string, mixed>
	 */
	public function to_gf_search_criteria(): array {
		$bounds = $this->to_query_bounds();
		$filter = array();

		if ( null !== $bounds['start'] ) {
			$filter['start_date'] = $bounds['start'];
		}

		if ( null !== $bounds['end'] ) {
			$filter['end_date'] = $bounds['end'];
		}

		/**
		 * Filter GF entry search criteria derived from the date range.
		 *
		 * @param array<string, mixed> $filter Search criteria fragment.
		 * @param GFA_Date_Range       $range  Date range instance.
		 */
		return apply_filters( 'gfa_export_query_args', $filter, $this );
	}

	/**
	 * Human-readable summary for preview UI.
	 */
	public function get_label(): string {
		if ( ! $this->has_filter() ) {
			return __( 'All dates', 'gravity-forms-aggregator' );
		}

		if ( null !== $this->from && null !== $this->to ) {
			return sprintf(
				/* translators: 1: start date, 2: end date */
				__( '%1$s to %2$s', 'gravity-forms-aggregator' ),
				$this->from,
				$this->to
			);
		}

		if ( null !== $this->from ) {
			return sprintf(
				/* translators: %s: start date */
				__( 'From %s onward', 'gravity-forms-aggregator' ),
				$this->from
			);
		}

		return sprintf(
			/* translators: %s: end date */
			__( 'Up to %s', 'gravity-forms-aggregator' ),
			$this->to
		);
	}

	/**
	 * Normalize user input to Y-m-d or null when empty/invalid.
	 *
	 * @param string|null $value Raw date input.
	 * @return string|null
	 */
	private static function normalize_date( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( sanitize_text_field( $value ) );

		if ( '' === $value ) {
			return null;
		}

		$tz = wp_timezone();

		$parsed = DateTimeImmutable::createFromFormat( 'Y-m-d', $value, $tz );
		if ( $parsed instanceof DateTimeImmutable ) {
			return $parsed->format( 'Y-m-d' );
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return null;
		}

		return ( new DateTimeImmutable( '@' . $timestamp ) )
			->setTimezone( $tz )
			->format( 'Y-m-d' );
	}
}
