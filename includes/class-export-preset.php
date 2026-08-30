<?php
/**
 * Per-user export selection presets (user meta, no custom tables).
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Save and load frequently used export selections.
 */
final class GFA_Export_Preset {

	private const META_KEY = 'gfa_export_presets';

	private const MAX_PRESETS = 20;

	/**
	 * List presets for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_presets( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$stored = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$presets = array();

		foreach ( $stored as $preset ) {
			if ( ! is_array( $preset ) ) {
				continue;
			}

			$normalized = self::normalize_preset( $preset );
			if ( null !== $normalized ) {
				$presets[] = $normalized;
			}
		}

		return $presets;
	}

	/**
	 * Save or update a preset for the current user.
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $name    Preset label.
	 * @param array<string, mixed> $state   form_ids, from_date, to_date, export_mode.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function save_preset( int $user_id, string $name, array $state ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return new WP_Error(
				'gfa_preset_name_required',
				__( 'Preset name is required.', 'gravity-forms-aggregator' )
			);
		}

		$preset = self::build_preset( $name, $state );
		if ( null === $preset ) {
			return new WP_Error(
				'gfa_preset_invalid',
				__( 'Preset must include at least one form.', 'gravity-forms-aggregator' )
			);
		}

		$presets = self::list_presets( $user_id );
		$found   = false;

		foreach ( $presets as $index => $existing ) {
			if ( $existing['name'] === $preset['name'] ) {
				$presets[ $index ] = $preset;
				$found             = true;
				break;
			}
		}

		if ( ! $found ) {
			if ( count( $presets ) >= self::MAX_PRESETS ) {
				return new WP_Error(
					'gfa_preset_limit',
					__( 'Maximum number of presets reached.', 'gravity-forms-aggregator' )
				);
			}

			$presets[] = $preset;
		}

		update_user_meta( $user_id, self::META_KEY, $presets );

		return $preset;
	}

	/**
	 * Delete a preset by name.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Preset label.
	 * @return true|WP_Error
	 */
	public static function delete_preset( int $user_id, string $name ) {
		$name    = sanitize_text_field( $name );
		$presets = self::list_presets( $user_id );
		$next    = array();

		foreach ( $presets as $preset ) {
			if ( $preset['name'] !== $name ) {
				$next[] = $preset;
			}
		}

		if ( count( $next ) === count( $presets ) ) {
			return new WP_Error(
				'gfa_preset_not_found',
				__( 'Preset not found.', 'gravity-forms-aggregator' )
			);
		}

		update_user_meta( $user_id, self::META_KEY, $next );

		return true;
	}

	/**
	 * @param array<string, mixed> $preset Raw preset.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_preset( array $preset ): ?array {
		$name = isset( $preset['name'] ) ? sanitize_text_field( (string) $preset['name'] ) : '';
		if ( '' === $name ) {
			return null;
		}

		$form_ids = array_values(
			array_filter(
				array_map( 'absint', (array) ( $preset['form_ids'] ?? array() ) ),
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);

		if ( empty( $form_ids ) ) {
			return null;
		}

		$mode = isset( $preset['export_mode'] ) ? sanitize_key( (string) $preset['export_mode'] ) : GFA_Export_Config::get_default_export_mode();
		if ( ! GFA_Export_Config::is_valid_mode( $mode ) ) {
			$mode = GFA_Export_Config::get_default_export_mode();
		}

		return array(
			'name'        => $name,
			'form_ids'    => $form_ids,
			'from_date'   => isset( $preset['from_date'] ) ? sanitize_text_field( (string) $preset['from_date'] ) : '',
			'to_date'     => isset( $preset['to_date'] ) ? sanitize_text_field( (string) $preset['to_date'] ) : '',
			'export_mode' => $mode,
		);
	}

	/**
	 * @param string               $name  Preset label.
	 * @param array<string, mixed> $state Selection state.
	 * @return array<string, mixed>|null
	 */
	private static function build_preset( string $name, array $state ): ?array {
		return self::normalize_preset(
			array(
				'name'        => $name,
				'form_ids'    => $state['form_ids'] ?? array(),
				'from_date'   => $state['from_date'] ?? '',
				'to_date'     => $state['to_date'] ?? '',
				'export_mode' => $state['export_mode'] ?? GFA_Export_Config::get_default_export_mode(),
			)
		);
	}
}
