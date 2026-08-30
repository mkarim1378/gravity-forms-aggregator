<?php
/**
 * Admin export UI — form selection, date filter, export trigger (Phase 3).
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the export admin screen and validates export requests (no file output yet).
 */
final class GFA_Admin_Page {

	public const PAGE_SLUG = 'gfa-export';

	/** @var self|null */
	private static $instance = null;

	/** @var GFA_Export_Engine */
	private $engine;

	/** @var GFA_Export_Preview */
	private $preview;

	/** @var array<string, mixed> */
	private $form_state = array();

	/** @var array<string, mixed> */
	private $extraction_summary = array();

	/** @var string */
	private $notice_type = '';

	/** @var string */
	private $notice_message = '';

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
		$this->engine  = new GFA_Export_Engine();
		$this->preview = new GFA_Export_Preview( $this->engine );
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_gfa_export_preview', array( $this, 'ajax_preview' ) );
	}

	/**
	 * Required capability for this screen and export actions.
	 */
	public static function required_capability(): string {
		/**
		 * Filter the capability required to access the export admin page.
		 *
		 * @param string $capability Default manage_options.
		 */
		return apply_filters( 'gfa_export_capability', 'manage_options' );
	}

	/**
	 * Add submenu under Gravity Forms (fallback: Tools).
	 */
	public function register_menu(): void {
		$capability = self::required_capability();
		$menu_title   = __( 'Aggregate Export', 'gravity-forms-aggregator' );
		$page_title   = __( 'Gravity Forms Aggregator', 'gravity-forms-aggregator' );
		$callback     = array( $this, 'render_page' );

		if ( class_exists( 'GFForms' ) ) {
			add_submenu_page(
				'gf_edit_forms',
				$page_title,
				$menu_title,
				$capability,
				self::PAGE_SLUG,
				$callback
			);
			return;
		}

		add_management_page(
			$page_title,
			$menu_title,
			$capability,
			self::PAGE_SLUG,
			$callback
		);
	}

	/**
	 * Load admin assets only on the export screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_export_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'gfa-admin',
			GFA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GFA_VERSION
		);

		wp_enqueue_script(
			'gfa-admin',
			GFA_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			GFA_VERSION,
			true
		);

		wp_localize_script(
			'gfa-admin',
			'gfaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gfa_export_preview' ),
				'i18n'    => array(
					'noFormsSelected'    => __( 'Select at least one form to export.', 'gravity-forms-aggregator' ),
					'invalidDateRange'   => __( 'The start date must not be after the end date.', 'gravity-forms-aggregator' ),
					'previewTitle'       => __( 'Export preview', 'gravity-forms-aggregator' ),
					'formsSelected'      => __( 'Forms selected: %d', 'gravity-forms-aggregator' ),
					'entriesFound'       => __( 'Entries found: %d', 'gravity-forms-aggregator' ),
					'dateRange'          => __( 'Date range: %s', 'gravity-forms-aggregator' ),
					'emptyFormsWarning'  => __( 'No entries in the selected date range for form ID(s): %s', 'gravity-forms-aggregator' ),
					'noEntriesWarning'   => __( 'No entries match the current selection. The export file will contain headers only.', 'gravity-forms-aggregator' ),
					'previewLoading'     => __( 'Counting entries…', 'gravity-forms-aggregator' ),
					'previewFailed'      => __( 'Could not load the export preview.', 'gravity-forms-aggregator' ),
					'exporting'          => __( 'Exporting…', 'gravity-forms-aggregator' ),
				),
			)
		);
	}

	/**
	 * Handle export form POST — CSV or XLSX download.
	 */
	public function handle_post(): void {
		if ( ! isset( $_POST['gfa_export_action'] ) ) {
			return;
		}

		if ( ! current_user_can( self::required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to export forms.', 'gravity-forms-aggregator' ) );
		}

		check_admin_referer( 'gfa_export', 'gfa_export_nonce' );

		$this->form_state = $this->read_form_state_from_post();
		$parsed           = $this->parse_export_request( $this->form_state );

		if ( is_wp_error( $parsed ) ) {
			$this->notice_type    = 'error';
			$this->notice_message = $parsed->get_error_message();
			return;
		}

		if ( GFA_Export_Config::FORMAT_CSV === $parsed['format'] ) {
			$exporter = new GFA_Csv_Exporter( $this->engine );
			$result   = $exporter->download( $parsed['form_ids'], $parsed['range'] );

			if ( is_wp_error( $result ) ) {
				$this->notice_type    = 'error';
				$this->notice_message = $result->get_error_message();
			}

			return;
		}

		if ( GFA_Export_Config::FORMAT_XLSX === $parsed['format'] ) {
			$exporter = new GFA_Xlsx_Exporter( $this->engine );
			$result   = $exporter->download( $parsed['form_ids'], $parsed['range'] );

			if ( is_wp_error( $result ) ) {
				$this->notice_type    = 'error';
				$this->notice_message = $result->get_error_message();
			}

			return;
		}
	}

	/**
	 * AJAX handler — return entry counts and warnings before download.
	 */
	public function ajax_preview(): void {
		check_ajax_referer( 'gfa_export_preview', 'nonce' );

		if ( ! current_user_can( self::required_capability() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to preview exports.', 'gravity-forms-aggregator' ) ),
				403
			);
		}

		$state  = $this->read_form_state_from_request();
		$parsed = $this->parse_selection_request( $state );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
		}

		$preview = $this->preview->get_preview( $parsed['form_ids'], $parsed['range'] );
		if ( is_wp_error( $preview ) ) {
			wp_send_json_error( array( 'message' => $preview->get_error_message() ) );
		}

		wp_send_json_success( $preview );
	}

	/**
	 * Render the admin export screen.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gravity-forms-aggregator' ) );
		}

		$forms = $this->engine->get_extractor()->get_forms();
		if ( is_wp_error( $forms ) ) {
			$this->render_notice( 'error', $forms->get_error_message() );
			return;
		}

		if ( empty( $this->form_state ) ) {
			$this->form_state = $this->default_form_state();
		}

		if ( '' !== $this->notice_message ) {
			$this->render_notice( $this->notice_type, $this->notice_message );
		}

		if ( ! empty( $this->extraction_summary ) ) {
			$this->render_extraction_summary( $this->extraction_summary );
		}

		$selected_ids = array_map( 'absint', (array) ( $this->form_state['form_ids'] ?? array() ) );
		$from_date    = (string) ( $this->form_state['from_date'] ?? '' );
		$to_date      = (string) ( $this->form_state['to_date'] ?? '' );
		$format       = (string) ( $this->form_state['format'] ?? GFA_Export_Config::FORMAT_CSV );
		?>
		<div class="wrap gfa-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Select forms and an optional date range, then choose an export format.', 'gravity-forms-aggregator' ); ?>
			</p>

			<form method="post" action="" id="gfa-export-form" class="gfa-export-form" novalidate>
				<?php wp_nonce_field( 'gfa_export', 'gfa_export_nonce' ); ?>
				<input type="hidden" name="gfa_export_action" value="1" />
				<input type="hidden" name="gfa_export_format" id="gfa-export-format" value="<?php echo esc_attr( $format ); ?>" />

				<div class="gfa-panel gfa-panel-forms">
					<div class="gfa-panel-header">
						<h2><?php esc_html_e( 'Forms', 'gravity-forms-aggregator' ); ?></h2>
						<div class="gfa-panel-actions">
							<label class="gfa-search-label" for="gfa-form-search">
								<span class="screen-reader-text"><?php esc_html_e( 'Search forms', 'gravity-forms-aggregator' ); ?></span>
								<input
									type="search"
									id="gfa-form-search"
									class="gfa-form-search"
									placeholder="<?php esc_attr_e( 'Search by name or ID…', 'gravity-forms-aggregator' ); ?>"
								/>
							</label>
							<button type="button" class="button gfa-select-all">
								<?php esc_html_e( 'Select all', 'gravity-forms-aggregator' ); ?>
							</button>
							<button type="button" class="button gfa-deselect-all">
								<?php esc_html_e( 'Deselect all', 'gravity-forms-aggregator' ); ?>
							</button>
						</div>
					</div>

					<?php if ( empty( $forms ) ) : ?>
						<p><?php esc_html_e( 'No Gravity Forms found.', 'gravity-forms-aggregator' ); ?></p>
					<?php else : ?>
						<table class="widefat striped gfa-forms-table">
							<thead>
								<tr>
									<td class="check-column">
										<input
											type="checkbox"
											id="gfa-check-all"
											aria-label="<?php esc_attr_e( 'Select all forms', 'gravity-forms-aggregator' ); ?>"
										/>
									</td>
									<th scope="col"><?php esc_html_e( 'ID', 'gravity-forms-aggregator' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Title', 'gravity-forms-aggregator' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Entries', 'gravity-forms-aggregator' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'gravity-forms-aggregator' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $forms as $form ) : ?>
									<?php
									$form_id   = (int) $form['id'];
									$checked   = in_array( $form_id, $selected_ids, true );
									$row_class = $form['is_active'] ? '' : 'gfa-form-inactive';
									?>
									<tr
										class="<?php echo esc_attr( $row_class ); ?>"
										data-form-title="<?php echo esc_attr( strtolower( (string) $form['title'] ) ); ?>"
										data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
									>
										<th scope="row" class="check-column">
											<input
												type="checkbox"
												name="gfa_form_ids[]"
												value="<?php echo esc_attr( (string) $form_id ); ?>"
												class="gfa-form-checkbox"
												<?php checked( $checked ); ?>
											/>
										</th>
										<td><?php echo esc_html( (string) $form_id ); ?></td>
										<td><?php echo esc_html( (string) $form['title'] ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $form['entry_count'] ) ); ?></td>
										<td>
											<?php
											echo $form['is_active']
												? esc_html__( 'Active', 'gravity-forms-aggregator' )
												: esc_html__( 'Inactive', 'gravity-forms-aggregator' );
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div class="gfa-panel gfa-panel-dates">
					<h2><?php esc_html_e( 'Date range', 'gravity-forms-aggregator' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Leave both fields empty to include all entry dates.', 'gravity-forms-aggregator' ); ?>
					</p>
					<div class="gfa-date-fields">
						<p>
							<label for="gfa-from-date"><?php esc_html_e( 'From date', 'gravity-forms-aggregator' ); ?></label>
							<input
								type="date"
								id="gfa-from-date"
								name="gfa_from_date"
								value="<?php echo esc_attr( $from_date ); ?>"
							/>
						</p>
						<p>
							<label for="gfa-to-date"><?php esc_html_e( 'To date', 'gravity-forms-aggregator' ); ?></label>
							<input
								type="date"
								id="gfa-to-date"
								name="gfa_to_date"
								value="<?php echo esc_attr( $to_date ); ?>"
							/>
						</p>
					</div>
					<p class="gfa-client-error" id="gfa-date-error" role="alert" hidden></p>
				</div>

				<div class="gfa-panel gfa-panel-preview" id="gfa-preview-panel" hidden>
					<h2><?php esc_html_e( 'Export preview', 'gravity-forms-aggregator' ); ?></h2>
					<p class="gfa-preview-loading" id="gfa-preview-loading" hidden>
						<span class="spinner is-active" aria-hidden="true"></span>
						<?php esc_html_e( 'Counting entries…', 'gravity-forms-aggregator' ); ?>
					</p>
					<p class="gfa-preview-error gfa-client-error" id="gfa-preview-error" role="alert" hidden></p>
					<ul class="gfa-summary-list" id="gfa-preview-summary" hidden></ul>
					<p class="gfa-summary-warning" id="gfa-preview-empty-forms" hidden></p>
					<p class="gfa-summary-warning" id="gfa-preview-no-entries" hidden></p>
				</div>

				<div class="gfa-panel gfa-panel-export">
					<h2><?php esc_html_e( 'Export', 'gravity-forms-aggregator' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Preview the entry count before downloading, or export directly.', 'gravity-forms-aggregator' ); ?>
					</p>
					<p class="gfa-client-error" id="gfa-form-error" role="alert" hidden></p>
					<p class="submit gfa-export-buttons">
						<button
							type="button"
							class="button gfa-preview-button"
							id="gfa-preview-button"
							<?php disabled( empty( $forms ) ); ?>
						>
							<?php esc_html_e( 'Preview export', 'gravity-forms-aggregator' ); ?>
						</button>
						<button
							type="submit"
							class="button button-primary gfa-export-button"
							data-format="<?php echo esc_attr( GFA_Export_Config::FORMAT_CSV ); ?>"
							<?php disabled( empty( $forms ) ); ?>
						>
							<?php esc_html_e( 'Export CSV', 'gravity-forms-aggregator' ); ?>
						</button>
						<button
							type="submit"
							class="button gfa-export-button"
							data-format="<?php echo esc_attr( GFA_Export_Config::FORMAT_XLSX ); ?>"
							<?php disabled( empty( $forms ) ); ?>
						>
							<?php esc_html_e( 'Export Excel', 'gravity-forms-aggregator' ); ?>
						</button>
					</p>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * @param string $type    notice-error|notice-success.
	 * @param string $message Notice text.
	 */
	private function render_notice( string $type, string $message ): void {
		$class = 'notice-error' === $type ? 'notice notice-error' : 'notice notice-success';
		printf(
			'<div class="%s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	/**
	 * Show extraction summary after a successful export request (Phase 4).
	 *
	 * @param array<string, mixed> $summary Summary from GFA_Export_Engine::get_summary().
	 */
	private function render_extraction_summary( array $summary ): void {
		?>
		<div class="gfa-panel gfa-panel-summary">
			<h2><?php esc_html_e( 'Extraction summary', 'gravity-forms-aggregator' ); ?></h2>
			<ul class="gfa-summary-list">
				<li>
					<?php
					printf(
						/* translators: %d: number of selected forms */
						esc_html__( 'Forms selected: %d', 'gravity-forms-aggregator' ),
						(int) ( $summary['form_count'] ?? 0 )
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: %d: number of entries */
						esc_html__( 'Entries found: %d', 'gravity-forms-aggregator' ),
						(int) ( $summary['entry_count'] ?? 0 )
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: %s: date range label */
						esc_html__( 'Date range: %s', 'gravity-forms-aggregator' ),
						esc_html( (string) ( $summary['date_label'] ?? '' ) )
					);
					?>
				</li>
			</ul>
			<?php if ( ! empty( $summary['empty_form_ids'] ) ) : ?>
				<p class="gfa-summary-warning">
					<?php
					printf(
						/* translators: %s: comma-separated form IDs */
						esc_html__( 'No entries in the selected date range for form ID(s): %s', 'gravity-forms-aggregator' ),
						esc_html( implode( ', ', array_map( 'strval', (array) $summary['empty_form_ids'] ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return array<string, mixed>
	 */
	private function default_form_state(): array {
		return array(
			'form_ids'  => array(),
			'from_date' => '',
			'to_date'   => '',
			'format'    => GFA_Export_Config::FORMAT_CSV,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_form_state_from_post(): array {
		return $this->read_form_state_from_request( true );
	}

	/**
	 * Read form/date selection from POST or AJAX request.
	 *
	 * @param bool $include_format Whether to read export format (POST export only).
	 * @return array<string, mixed>
	 */
	private function read_form_state_from_request( bool $include_format = false ): array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$raw_ids = isset( $_POST['gfa_form_ids'] ) ? (array) wp_unslash( $_POST['gfa_form_ids'] ) : array();

		$state = array(
			'form_ids'  => array_map( 'absint', $raw_ids ),
			'from_date' => isset( $_POST['gfa_from_date'] ) ? sanitize_text_field( wp_unslash( $_POST['gfa_from_date'] ) ) : '',
			'to_date'   => isset( $_POST['gfa_to_date'] ) ? sanitize_text_field( wp_unslash( $_POST['gfa_to_date'] ) ) : '',
		);

		if ( $include_format ) {
			$state['format'] = isset( $_POST['gfa_export_format'] )
				? sanitize_key( wp_unslash( $_POST['gfa_export_format'] ) )
				: GFA_Export_Config::FORMAT_CSV;
		}

		return $state;
	}

	/**
	 * Validate form/date selection; returns parsed request or WP_Error.
	 *
	 * @param array<string, mixed> $state Form state.
	 * @return array{form_ids: int[], range: GFA_Date_Range}|WP_Error
	 */
	private function parse_selection_request( array $state ) {
		$form_ids = array_values(
			array_filter(
				array_map( 'absint', (array) ( $state['form_ids'] ?? array() ) ),
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);

		$from_raw = trim( (string) ( $state['from_date'] ?? '' ) );
		$to_raw   = trim( (string) ( $state['to_date'] ?? '' ) );

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

		$request_validation = $this->engine->get_extractor()->validate_export_request( $form_ids, $range );
		if ( is_wp_error( $request_validation ) ) {
			return $request_validation;
		}

		$valid_ids = $this->filter_existing_form_ids( $form_ids );
		if ( empty( $valid_ids ) ) {
			return new WP_Error(
				'gfa_no_forms',
				__( 'No valid forms selected.', 'gravity-forms-aggregator' )
			);
		}

		if ( count( $valid_ids ) !== count( $form_ids ) ) {
			return new WP_Error(
				'gfa_invalid_form_ids',
				__( 'One or more selected forms do not exist.', 'gravity-forms-aggregator' )
			);
		}

		return array(
			'form_ids' => $valid_ids,
			'range'    => $range,
		);
	}

	/**
	 * Validate export inputs; returns parsed request or WP_Error.
	 *
	 * @param array<string, mixed> $state Form state.
	 * @return array{form_ids: int[], range: GFA_Date_Range, format: string}|WP_Error
	 */
	private function parse_export_request( array $state ) {
		$parsed = $this->parse_selection_request( $state );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$format = sanitize_key( (string) ( $state['format'] ?? '' ) );

		if ( ! GFA_Export_Config::is_valid_format( $format ) ) {
			return new WP_Error(
				'gfa_invalid_format',
				__( 'Invalid export format selected.', 'gravity-forms-aggregator' )
			);
		}

		return array(
			'form_ids' => $parsed['form_ids'],
			'range'    => $parsed['range'],
			'format'   => $format,
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
	 * Keep only form IDs that exist in Gravity Forms.
	 *
	 * @param int[] $form_ids Candidate IDs.
	 * @return int[]
	 */
	private function filter_existing_form_ids( array $form_ids ): array {
		$forms = $this->engine->get_extractor()->get_forms();
		if ( is_wp_error( $forms ) || empty( $forms ) ) {
			return array();
		}

		$existing = array();
		foreach ( $forms as $form ) {
			$existing[ (int) $form['id'] ] = true;
		}

		$valid = array();
		foreach ( $form_ids as $form_id ) {
			if ( isset( $existing[ $form_id ] ) ) {
				$valid[] = $form_id;
			}
		}

		return $valid;
	}

	/**
	 * @param string $hook_suffix Admin hook suffix.
	 */
	private function is_export_screen( string $hook_suffix ): bool {
		return false !== strpos( $hook_suffix, self::PAGE_SLUG );
	}
}
