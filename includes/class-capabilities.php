<?php
/**
 * Custom capability for export access.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and grants the gfa_export_forms capability.
 */
final class GFA_Capabilities {

	public const CAP_EXPORT = 'gfa_export_forms';

	/**
	 * Hook default capability filter.
	 */
	public static function register(): void {
		add_filter( 'gfa_export_capability', array( __CLASS__, 'default_capability' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync_administrator_cap' ) );
	}

	/**
	 * Ensure administrators receive the export cap after upgrades (not only on activation).
	 */
	public static function maybe_sync_administrator_cap(): void {
		$synced_version = get_option( 'gfa_capabilities_synced', '' );
		if ( GFA_VERSION === $synced_version ) {
			return;
		}

		self::activate();
		update_option( 'gfa_capabilities_synced', GFA_VERSION, false );
	}

	/**
	 * Default capability for export screens and downloads.
	 *
	 * @param string $capability Filtered capability.
	 */
	public static function default_capability( string $capability ): string {
		unset( $capability );

		return self::CAP_EXPORT;
	}

	/**
	 * Grant export capability to the administrator role on activation.
	 */
	public static function activate(): void {
		$role = get_role( 'administrator' );
		if ( $role instanceof WP_Role ) {
			$role->add_cap( self::CAP_EXPORT );
		}
	}
}
