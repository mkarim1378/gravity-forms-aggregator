<?php
/**
 * Admin screen — unified entries list across all Gravity Forms.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the paginated entries list and serves AJAX page loads.
 */
final class GFA_Entries_List_Page {

	public const PAGE_SLUG = 'gfa-entries';

	/** @var self|null */
	private static $instance = null;

	/** @var GFA_Entries_List */
	private $list;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->list = new GFA_Entries_List();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		if ( class_exists( 'GFForms' ) ) {
			add_filter( 'gform_addon_navigation', array( $this, 'register_gf_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'register_tools_menu' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_gfa_entries_list', array( $this, 'ajax_list' ) );
	}

	/**
	 * Register under the Gravity Forms menu.
	 *
	 * @param array<int, array<string, mixed>> $menu_items GF add-on menu definitions.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_gf_menu( array $menu_items ): array {
		$menu_items[] = array(
			'name'       => self::PAGE_SLUG,
			'label'      => __( 'All Entries', 'gravity-forms-aggregator' ),
			'callback'   => array( $this, 'render_page' ),
			'permission' => GFA_Admin_Page::required_capability(),
		);

		return $menu_items;
	}

	/**
	 * Fallback when Gravity Forms is not active.
	 */
	public function register_tools_menu(): void {
		add_management_page(
			__( 'All Form Entries', 'gravity-forms-aggregator' ),
			__( 'All Entries', 'gravity-forms-aggregator' ),
			GFA_Admin_Page::required_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_entries_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'gfa-admin',
			GFA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GFA_VERSION
		);

		wp_enqueue_script(
			'gfa-entries-list',
			GFA_PLUGIN_URL . 'assets/js/entries-list.js',
			array(),
			GFA_VERSION,
			true
		);

		wp_localize_script(
			'gfa-entries-list',
			'gfaEntriesList',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gfa_entries_list' ),
				'i18n'    => array(
					'invalidDateRange' => __( 'The start date must not be after the end date.', 'gravity-forms-aggregator' ),
					'loadFailed'       => __( 'Could not load entries.', 'gravity-forms-aggregator' ),
					'loading'          => __( 'Loading entries…', 'gravity-forms-aggregator' ),
					'noEntries'        => __( 'No entries match the current filters.', 'gravity-forms-aggregator' ),
					'entriesTotal'     => __( '%d entries total', 'gravity-forms-aggregator' ),
					'pageOf'           => __( 'Page %1$d of %2$d', 'gravity-forms-aggregator' ),
					'previous'         => __( 'Previous', 'gravity-forms-aggregator' ),
					'next'             => __( 'Next', 'gravity-forms-aggregator' ),
				),
			)
		);
	}

	/**
	 * AJAX handler — return a paginated entries page.
	 */
	public function ajax_list(): void {
		check_ajax_referer( 'gfa_entries_list', 'nonce' );

		if ( ! current_user_can( GFA_Admin_Page::required_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to view entries.', 'gravity-forms-aggregator' ) ),
				403
			);
		}

		$parsed = $this->parse_list_request();
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
		}

		$result = $this->list->get_page(
			$parsed['form_ids'],
			$parsed['range'],
			$parsed['page'],
			$parsed['per_page'],
			$parsed['search']
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'rows'        => $result['rows'],
				'total'       => $result['total'],
				'page'        => $result['page'],
				'per_page'    => $result['per_page'],
				'total_pages' => $result['total_pages'],
				'date_label'  => $result['date_label'],
				'html'        => $this->render_table_rows( $result['rows'] ),
			)
		);
	}

	/**
	 * Render the admin entries list screen.
	 */
	public function render_page(): void {
		if ( ! current_user_can( GFA_Admin_Page::required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gravity-forms-aggregator' ) );
		}

		$from_date = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to_date   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$parsed = $this->parse_list_request_from_values( $from_date, $to_date, $page, GFA_Entries_List::DEFAULT_PER_PAGE, array(), $search );
		$result = null;
		$error  = '';

		if ( is_wp_error( $parsed ) ) {
			$error = $parsed->get_error_message();
		} else {
			$result = $this->list->get_page(
				$parsed['form_ids'],
				$parsed['range'],
				$parsed['page'],
				$parsed['per_page'],
				$parsed['search']
			);

			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			}
		}
		?>
		<div class="wrap gfa-admin-wrap gfa-entries-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'All entries from every Gravity Form in one place — newest first.', 'gravity-forms-aggregator' ); ?>
			</p>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div class="gfa-panel gfa-panel-entries-filters">
				<h2><?php esc_html_e( 'Filters', 'gravity-forms-aggregator' ); ?></h2>
				<div class="gfa-date-fields">
					<p>
						<label for="gfa-entries-search"><?php esc_html_e( 'Search', 'gravity-forms-aggregator' ); ?></label>
						<input
							type="search"
							id="gfa-entries-search"
							class="gfa-form-search"
							value="<?php echo esc_attr( $search ); ?>"
							placeholder="<?php esc_attr_e( 'Name or phone number…', 'gravity-forms-aggregator' ); ?>"
						/>
					</p>
					<p>
						<label for="gfa-entries-from-date"><?php esc_html_e( 'From date', 'gravity-forms-aggregator' ); ?></label>
						<input type="date" id="gfa-entries-from-date" value="<?php echo esc_attr( $from_date ); ?>" />
					</p>
					<p>
						<label for="gfa-entries-to-date"><?php esc_html_e( 'To date', 'gravity-forms-aggregator' ); ?></label>
						<input type="date" id="gfa-entries-to-date" value="<?php echo esc_attr( $to_date ); ?>" />
					</p>
				</div>
				<p class="gfa-client-error" id="gfa-entries-date-error" role="alert" hidden></p>
				<p class="submit">
					<button type="button" class="button button-primary" id="gfa-entries-apply">
						<?php esc_html_e( 'Apply filters', 'gravity-forms-aggregator' ); ?>
					</button>
				</p>
			</div>

			<div class="gfa-panel gfa-panel-entries-list">
				<div class="gfa-entries-list-header">
					<p class="gfa-entries-meta" id="gfa-entries-meta">
						<?php
						if ( $result ) {
							printf(
								/* translators: %d: total entry count */
								esc_html__( '%d entries total', 'gravity-forms-aggregator' ),
								(int) $result['total']
							);
						}
						?>
					</p>
					<p class="gfa-entries-loading" id="gfa-entries-loading" hidden>
						<span class="spinner is-active" aria-hidden="true"></span>
						<?php esc_html_e( 'Loading entries…', 'gravity-forms-aggregator' ); ?>
					</p>
				</div>

				<table class="widefat striped gfa-entries-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Form', 'gravity-forms-aggregator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'gravity-forms-aggregator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Mobile', 'gravity-forms-aggregator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Payment', 'gravity-forms-aggregator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date & time', 'gravity-forms-aggregator' ); ?></th>
						</tr>
					</thead>
					<tbody id="gfa-entries-tbody">
						<?php
						if ( $result ) {
							echo $this->render_table_rows( $result['rows'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</tbody>
				</table>

				<p class="gfa-entries-empty" id="gfa-entries-empty" <?php echo ( $result && $result['total'] > 0 ) ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'No entries match the current filters.', 'gravity-forms-aggregator' ); ?>
				</p>

				<?php if ( $result && $result['total_pages'] > 1 ) : ?>
					<div class="gfa-entries-pagination" id="gfa-entries-pagination">
						<?php echo $this->render_pagination( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php else : ?>
					<div class="gfa-entries-pagination" id="gfa-entries-pagination" hidden></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, string>> $rows Summary rows.
	 */
	private function render_table_rows( array $rows ): string {
		if ( empty( $rows ) ) {
			return '';
		}

		ob_start();

		foreach ( $rows as $row ) {
			$form_title = (string) ( $row['form_title'] ?? '' );
			$form_url   = (string) ( $row['form_url'] ?? '' );
			$name       = (string) ( $row['name'] ?? '' );
			$mobile     = (string) ( $row['mobile'] ?? '' );
			$payment    = (string) ( $row['payment_status'] ?? '' );
			$date       = (string) ( $row['entry_date_display'] ?? '' );

			if ( '' === $name ) {
				$name = '—';
			}
			if ( '' === $mobile ) {
				$mobile = '—';
			}
			if ( '' === $payment ) {
				$payment = '—';
			}
			if ( '' === $date && ! empty( $row['entry_date'] ) ) {
				$date = (string) $row['entry_date'];
			}
			?>
			<tr>
				<td>
					<?php if ( '' !== $form_url && '' !== $form_title ) : ?>
						<a href="<?php echo esc_url( $form_url ); ?>"><?php echo esc_html( $form_title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $form_title ); ?>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $name ); ?></td>
				<td dir="ltr"><?php echo esc_html( $mobile ); ?></td>
				<td><?php echo esc_html( $payment ); ?></td>
				<td><?php echo esc_html( $date ); ?></td>
			</tr>
			<?php
		}

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $result Page result from GFA_Entries_List::get_page().
	 */
	private function render_pagination( array $result ): string {
		$page        = (int) ( $result['page'] ?? 1 );
		$total_pages = (int) ( $result['total_pages'] ?? 1 );

		ob_start();
		?>
		<button type="button" class="button gfa-entries-page-btn" data-page="<?php echo esc_attr( (string) max( 1, $page - 1 ) ); ?>" <?php disabled( $page <= 1 ); ?>>
			<?php esc_html_e( 'Previous', 'gravity-forms-aggregator' ); ?>
		</button>
		<span class="gfa-entries-page-label">
			<?php
			printf(
				/* translators: 1: current page, 2: total pages */
				esc_html__( 'Page %1$d of %2$d', 'gravity-forms-aggregator' ),
				$page,
				$total_pages
			);
			?>
		</span>
		<button type="button" class="button gfa-entries-page-btn" data-page="<?php echo esc_attr( (string) min( $total_pages, $page + 1 ) ); ?>" <?php disabled( $page >= $total_pages ); ?>>
			<?php esc_html_e( 'Next', 'gravity-forms-aggregator' ); ?>
		</button>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return array{form_ids: int[], range: GFA_Date_Range, page: int, per_page: int, search: string}|WP_Error
	 */
	private function parse_list_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list params.
		$from_date = isset( $_REQUEST['gfa_from_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['gfa_from_date'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list params.
		$to_date = isset( $_REQUEST['gfa_to_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['gfa_to_date'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list params.
		$page = isset( $_REQUEST['gfa_page'] ) ? max( 1, absint( $_REQUEST['gfa_page'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list params.
		$per_page = isset( $_REQUEST['gfa_per_page'] ) ? absint( $_REQUEST['gfa_per_page'] ) : GFA_Entries_List::DEFAULT_PER_PAGE;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list params.
		$search = isset( $_REQUEST['gfa_search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['gfa_search'] ) ) : '';

		return $this->parse_list_request_from_values( $from_date, $to_date, $page, $per_page, array(), $search );
	}

	/**
	 * @param string $from_date From date (Y-m-d).
	 * @param string $to_date   To date (Y-m-d).
	 * @param int    $page      Page number.
	 * @param int    $per_page  Rows per page.
	 * @param int[]  $form_ids  Form IDs (empty = all).
	 * @param string $search    Name or mobile substring.
	 * @return array{form_ids: int[], range: GFA_Date_Range, page: int, per_page: int, search: string}|WP_Error
	 */
	private function parse_list_request_from_values( string $from_date, string $to_date, int $page, int $per_page, array $form_ids, string $search = '' ) {
		$from_raw = trim( $from_date );
		$to_raw   = trim( $to_date );

		if ( '' !== $from_raw && ! $this->is_valid_date_input( $from_raw ) ) {
			return new WP_Error(
				'gfa_invalid_from_date',
				__( 'The start date is not valid.', 'gravity-forms-aggregator' )
			);
		}

		if ( '' !== $to_raw && ! $this->is_valid_date_input( $to_raw ) ) {
			return new WP_Error(
				'gfa_invalid_to_date',
				__( 'The end date is not valid.', 'gravity-forms-aggregator' )
			);
		}

		$range = new GFA_Date_Range(
			'' !== $from_raw ? $from_raw : null,
			'' !== $to_raw ? $to_raw : null
		);

		$date_validation = $range->validate();
		if ( is_wp_error( $date_validation ) ) {
			return $date_validation;
		}

		return array(
			'form_ids' => $form_ids,
			'range'    => $range,
			'page'     => max( 1, $page ),
			'per_page' => min( GFA_Entries_List::MAX_PER_PAGE, max( 1, $per_page ) ),
			'search'   => trim( $search ),
		);
	}

	/**
	 * @param string $value Raw Y-m-d input.
	 */
	private function is_valid_date_input( string $value ): bool {
		$probe = new GFA_Date_Range( $value, null );
		return null !== $probe->get_from();
	}

	/**
	 * @param string $hook_suffix Admin hook suffix.
	 */
	private function is_entries_screen( string $hook_suffix ): bool {
		if ( false !== strpos( $hook_suffix, self::PAGE_SLUG ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}
}
