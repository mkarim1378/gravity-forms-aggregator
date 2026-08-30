<?php
/**
 * Plugin bootstrap and dependency checks.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
final class GFA_Plugin {

	/** @var self|null */
	private static $instance = null;

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
		register_activation_hook( GFA_PLUGIN_FILE, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Load text domain; bail with admin notice if Gravity Forms is missing.
	 */
	public function init(): void {
		load_plugin_textdomain(
			'gravity-forms-aggregator',
			false,
			dirname( plugin_basename( GFA_PLUGIN_FILE ) ) . '/languages'
		);

		if ( ! $this->is_gravity_forms_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_gf_notice' ) );
			return;
		}
	}

	/**
	 * Require Gravity Forms on activation.
	 */
	public function activate(): void {
		if ( ! $this->is_gravity_forms_active() ) {
			deactivate_plugins( plugin_basename( GFA_PLUGIN_FILE ) );
			wp_die(
				esc_html__(
					'Gravity Forms Aggregator requires Gravity Forms to be installed and active.',
					'gravity-forms-aggregator'
				),
				esc_html__( 'Plugin Activation Error', 'gravity-forms-aggregator' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * @return bool
	 */
	public function is_gravity_forms_active(): bool {
		return class_exists( 'GFForms' );
	}

	/**
	 * Admin notice when GF is not available.
	 */
	public function render_missing_gf_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Gravity Forms Aggregator requires Gravity Forms to be installed and active.',
				'gravity-forms-aggregator'
			)
		);
	}
}
