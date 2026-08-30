<?php
/**
 * Unified entries list — wide-format rows across multiple forms.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Merges GF entries from multiple forms sorted by date_created DESC.
 */
final class GFA_Entries_List {

	public const DEFAULT_PER_PAGE = 25;

	public const MAX_PER_PAGE = 100;

	private const MERGE_BATCH_SIZE = 100;

	/** @var GFA_Data_Extractor */
	private $extractor;

	public function __construct( ?GFA_Data_Extractor $extractor = null ) {
		$this->extractor = $extractor ?? new GFA_Data_Extractor();
	}

	public static function get_form_entries_url( int $form_id ): string {
		return GFA_Entry_Summary_Row::get_form_entries_url( $form_id );
	}

	/**
	 * Human-readable payment status from a GF entry.
	 *
	 * @param array $entry GF entry array.
	 */
	public static function format_payment_status( array $entry ): string {
		return GFA_Entry_Summary_Row::format_payment_status( $entry );
	}

	/**
	 * Paginated summary rows across forms (newest entries first).
	 *
	 * @param int[]          $form_ids Empty = all forms.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param int            $page     1-based page number.
	 * @param int            $per_page Rows per page.
	 * @param string         $search   Name or mobile substring (empty = all entries).
	 * @return array{
	 *     rows: array<int, array<string, string>>,
	 *     total: int,
	 *     page: int,
	 *     per_page: int,
	 *     total_pages: int,
	 *     date_label: string
	 * }|WP_Error
	 */
	public function get_page( array $form_ids, GFA_Date_Range $range, int $page, int $per_page = self::DEFAULT_PER_PAGE, string $search = '' ) {
		$form_ids = $this->resolve_form_ids( $form_ids );
		if ( is_wp_error( $form_ids ) ) {
			return $form_ids;
		}

		if ( empty( $form_ids ) ) {
			return new WP_Error(
				'gfa_no_forms',
				__( 'No Gravity Forms found.', 'gravity-forms-aggregator' )
			);
		}

		$validation = $range->validate();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			return new WP_Error(
				'gfa_gfapi_missing',
				__( 'Gravity Forms API is not available.', 'gravity-forms-aggregator' )
			);
		}

		$contexts = $this->build_form_contexts( $form_ids, $range, $search );
		if ( is_wp_error( $contexts ) ) {
			return $contexts;
		}

		$total = $this->count_context_entries( $contexts );
		if ( is_wp_error( $total ) ) {
			return $total;
		}

		$page     = max( 1, $page );
		$per_page = min( self::MAX_PER_PAGE, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $this->fetch_merged_page( $contexts, $offset, $per_page );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$total_pages = (int) $total > 0 ? (int) ceil( $total / $per_page ) : 1;

		return array(
			'rows'        => $rows,
			'total'       => (int) $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => max( 1, $total_pages ),
			'date_label'  => $range->get_label(),
		);
	}

	/**
	 * @param int[] $form_ids Candidate form IDs (empty = all).
	 * @return int[]|WP_Error
	 */
	private function resolve_form_ids( array $form_ids ) {
		$ids = array();

		foreach ( $form_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		$ids = array_values( array_unique( $ids ) );
		if ( ! empty( $ids ) ) {
			return $ids;
		}

		$forms = $this->extractor->get_forms();
		if ( is_wp_error( $forms ) ) {
			return $forms;
		}

		foreach ( $forms as $form ) {
			$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
			if ( $form_id > 0 ) {
				$ids[] = $form_id;
			}
		}

		return $ids;
	}

	/**
	 * @param int[]          $form_ids Form IDs.
	 * @param GFA_Date_Range $range    Date filter.
	 * @param string         $search   Name or mobile substring.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function build_form_contexts( array $form_ids, GFA_Date_Range $range, string $search ) {
		$contexts = array();

		foreach ( $form_ids as $form_id ) {
			$form = GFAPI::get_form( $form_id );
			if ( is_wp_error( $form ) ) {
				return $form;
			}

			$resolved = GFA_Entry_Field_Resolver::resolve( $form );
			$search_criteria = $this->extractor->build_form_entry_search_criteria(
				$range,
				$search,
				$resolved['name'],
				$resolved['mobile']
			);

			$contexts[] = array(
				'form_id'      => $form_id,
				'form_title'   => isset( $form['title'] ) ? (string) $form['title'] : '',
				'name_field'   => $resolved['name'],
				'mobile_field' => $resolved['mobile'],
				'criteria'     => $search_criteria['criteria'],
				'searchable'   => $search_criteria['searchable'],
				'offset'       => 0,
				'buffer'       => array(),
				'buffer_index' => 0,
				'exhausted'    => ! $search_criteria['searchable'],
				'no_more'      => ! $search_criteria['searchable'],
			);
		}

		return $contexts;
	}

	/**
	 * @param array<int, array<string, mixed>> $contexts Per-form merge state.
	 * @return int|WP_Error
	 */
	private function count_context_entries( array $contexts ) {
		$total = 0;

		foreach ( $contexts as $context ) {
			if ( empty( $context['searchable'] ) ) {
				continue;
			}

			$count = GFAPI::count_entries( (int) $context['form_id'], $context['criteria'] );
			if ( is_wp_error( $count ) ) {
				return $count;
			}

			$total += (int) $count;
		}

		return $total;
	}

	/**
	 * Multi-way merge of per-form entry streams (date_created DESC).
	 *
	 * @param array<int, array<string, mixed>> $contexts Per-form merge state.
	 * @param int                              $offset   Global offset.
	 * @param int                              $limit    Max rows to return.
	 * @return array<int, array<string, string>>|WP_Error
	 */
	private function fetch_merged_page( array $contexts, int $offset, int $limit ) {
		$sorting = array(
			'key'       => 'date_created',
			'direction' => 'DESC',
		);

		$results = array();
		$skipped = 0;
		$taken   = 0;

		while ( $taken < $limit ) {
			$best_index = null;
			$best_date  = null;

			foreach ( $contexts as $index => $context ) {
				if ( $context['exhausted'] ) {
					continue;
				}

				if ( $context['buffer_index'] >= count( $context['buffer'] ) ) {
					$loaded = $this->load_next_batch( $context, $sorting );
					if ( is_wp_error( $loaded ) ) {
						return $loaded;
					}
					$contexts[ $index ] = $loaded;

					if ( $loaded['exhausted'] ) {
						continue;
					}
				}

				$entry = $contexts[ $index ]['buffer'][ $contexts[ $index ]['buffer_index'] ];
				$date  = isset( $entry['date_created'] ) ? (string) $entry['date_created'] : '';

				if ( null === $best_date || $date > $best_date ) {
					$best_date  = $date;
					$best_index = $index;
				}
			}

			if ( null === $best_index ) {
				break;
			}

			$winner  = &$contexts[ $best_index ];
			$entry   = $winner['buffer'][ $winner['buffer_index'] ];
			$winner['buffer_index']++;

			if ( $winner['buffer_index'] >= count( $winner['buffer'] ) && $winner['no_more'] ) {
				$winner['exhausted'] = true;
			}

			if ( $skipped < $offset ) {
				++$skipped;
				continue;
			}

			$summary = GFA_Entry_Summary_Row::from_entry(
				$entry,
				(int) $winner['form_id'],
				$winner['form_title'],
				$winner['name_field'],
				$winner['mobile_field']
			);

			$results[] = $summary->to_array();
			++$taken;
		}

		$error = $this->extractor->get_last_error();
		if ( $error instanceof WP_Error ) {
			return $error;
		}

		return $results;
	}

	/**
	 * @param array<string, mixed>  $context Per-form merge state.
	 * @param array<string, string> $sorting GF sort definition.
	 * @return array<string, mixed>|WP_Error
	 */
	private function load_next_batch( array $context, array $sorting ) {
		$paging = array(
			'offset'    => (int) $context['offset'],
			'page_size' => self::MERGE_BATCH_SIZE,
		);

		$entries = GFAPI::get_entries( (int) $context['form_id'], $context['criteria'], $sorting, $paging );
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		$context['buffer']       = is_array( $entries ) ? $entries : array();
		$context['buffer_index'] = 0;
		$context['offset']      += count( $context['buffer'] );

		if ( empty( $context['buffer'] ) ) {
			$context['exhausted'] = true;
			$context['no_more']   = true;
		} elseif ( count( $context['buffer'] ) < self::MERGE_BATCH_SIZE ) {
			$context['no_more'] = true;
		}

		return $context;
	}
}
