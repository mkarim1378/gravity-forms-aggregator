<?php
/**
 * Maps Gravity Forms field definitions to exportable label/value pairs.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field definition → export row mapping.
 */
final class GFA_Field_Mapper {

	/** @var string[] Layout-only field types excluded from export. */
	private const SKIP_TYPES = array( 'html', 'section', 'page', 'captcha' );

	/** @var string[] GF field types treated as phone numbers in phone-only mode. */
	private const PHONE_FIELD_TYPES = array( 'phone' );

	/**
	 * Exportable fields for a form (includes composite sub-inputs).
	 *
	 * @param array  $form GFAPI form array.
	 * @param string $mode Export mode slug.
	 * @return array<int, array{key: string, label: string, field: object|null}>
	 */
	public static function get_exportable_fields( array $form, string $mode = '' ): array {
		if ( '' === $mode ) {
			$mode = GFA_Export_Config::get_default_export_mode();
		}

		$exportable = array();

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $exportable;
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}

			if ( self::should_skip_field( $field ) || ! self::matches_export_mode( $field, $mode ) ) {
				continue;
			}

			if ( ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
				foreach ( $field->inputs as $input ) {
					if ( ! empty( $input['is_hidden'] ) ) {
						continue;
					}

					$key = isset( $input['id'] ) ? (string) $input['id'] : '';
					if ( '' === $key ) {
						continue;
					}

					$exportable[] = array(
						'key'   => $key,
						'label' => self::build_label( $field, $input ),
						'field' => $field,
					);
				}
				continue;
			}

			$exportable[] = array(
				'key'   => (string) $field->id,
				'label' => (string) $field->label,
				'field' => $field,
			);
		}

		/**
		 * Filter exportable fields for a form.
		 *
		 * @param array $exportable Mapped fields.
		 * @param array $form       GF form array.
		 */
		return apply_filters( 'gfa_exportable_fields', $exportable, $form, $mode );
	}

	/**
	 * Whether a field should be included for the given export mode.
	 *
	 * @param object $field GF field object.
	 * @param string $mode  Export mode slug.
	 */
	public static function matches_export_mode( $field, string $mode ): bool {
		if ( GFA_Export_Config::EXPORT_MODE_PHONE_FIELDS !== $mode ) {
			return true;
		}

		$type = isset( $field->type ) ? (string) $field->type : '';

		return in_array( $type, self::PHONE_FIELD_TYPES, true );
	}

	/**
	 * Read and format a single cell from an entry.
	 *
	 * @param array       $entry  GF entry array.
	 * @param array       $mapped Mapped field from get_exportable_fields().
	 * @return string
	 */
	public static function get_field_value( array $entry, array $mapped ): string {
		$key   = $mapped['key'];
		$field = $mapped['field'];
		$value = isset( $entry[ $key ] ) ? $entry[ $key ] : '';

		if ( is_object( $field ) && class_exists( 'GFCommon' ) ) {
			$display = GFCommon::get_lead_field_display( $field, $value, '', false, 'text' );
			if ( is_string( $display ) && '' !== $display ) {
				return wp_strip_all_tags( $display );
			}
		}

		return self::stringify_value( $value );
	}

	/**
	 * @param object $field GF field object.
	 */
	private static function should_skip_field( $field ): bool {
		$type = isset( $field->type ) ? (string) $field->type : '';

		return in_array( $type, self::SKIP_TYPES, true );
	}

	/**
	 * @param object               $field GF field.
	 * @param array<string, mixed> $input Sub-input definition.
	 */
	private static function build_label( $field, array $input ): string {
		$parent = isset( $field->label ) ? trim( (string) $field->label ) : '';
		$child  = isset( $input['label'] ) ? trim( (string) $input['label'] ) : '';

		if ( '' === $parent ) {
			return $child;
		}

		if ( '' === $child || $child === $parent ) {
			return $parent;
		}

		return $parent . ' (' . $child . ')';
	}

	/**
	 * @param mixed $value Raw entry value.
	 */
	private static function stringify_value( $value ): string {
		if ( is_array( $value ) ) {
			$flat = array();

			foreach ( $value as $item ) {
				if ( is_scalar( $item ) && '' !== (string) $item ) {
					$flat[] = (string) $item;
				}
			}

			return implode( ', ', $flat );
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
