<?php
/**
 * Heuristic mapping of common GF fields (name, mobile) per form.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves name and mobile fields from form definitions using label/type hints.
 */
final class GFA_Entry_Field_Resolver {

	/** @var string[] Longest-first name label substrings (Persian + English). */
	private const NAME_LABEL_HINTS = array(
		'نام و نام خانوادگی',
		'نام خانوادگی',
		'full name',
		'نام',
		'name',
	);

	/** @var string[] Mobile label substrings. */
	private const MOBILE_LABEL_HINTS = array(
		'شماره موبایل',
		'شماره تماس',
		'موبایل',
		'تلفن همراه',
		'تلفن',
		'mobile',
		'cell phone',
		'cell',
		'phone',
	);

	/** @var string[] GF field types that indicate a payment-enabled form. */
	private const PAYMENT_FIELD_TYPES = array(
		'product',
		'option',
		'shipping',
		'total',
		'creditcard',
		'paypal',
		'stripe',
		'square',
		'authorize',
	);

	/**
	 * Resolve exportable name/mobile mappings for a form.
	 *
	 * @param array $form GFAPI form array.
	 * @return array{
	 *     name: array{key: string, label: string, field: object|null}|null,
	 *     mobile: array{key: string, label: string, field: object|null}|null,
	 *     has_payment: bool
	 * }
	 */
	public static function resolve( array $form ): array {
		$name_field   = null;
		$name_score   = 0;
		$mobile_field = null;
		$mobile_score = 0;

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return array(
				'name'        => null,
				'mobile'      => null,
				'has_payment' => false,
			);
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}

			$name_candidate = self::score_name_field( $field );
			if ( $name_candidate > $name_score ) {
				$name_score = $name_candidate;
				$name_field = self::to_mapped_field( $field );
			}

			$mobile_candidate = self::score_mobile_field( $field );
			if ( $mobile_candidate > $mobile_score ) {
				$mobile_score = $mobile_candidate;
				$mobile_field = self::to_mapped_field( $field );
			}
		}

		return array(
			'name'        => $name_field,
			'mobile'      => $mobile_field,
			'has_payment' => self::form_has_payment_fields( $form ),
		);
	}

	/**
	 * @param object $field GF field object.
	 */
	private static function score_name_field( $field ): int {
		$type = isset( $field->type ) ? (string) $field->type : '';

		if ( ! in_array( $type, array( 'text', 'textarea' ), true ) ) {
			return 0;
		}

		$label = self::field_label_text( $field );
		if ( '' === $label ) {
			return 0;
		}

		foreach ( self::NAME_LABEL_HINTS as $index => $hint ) {
			if ( self::contains_ci( $label, $hint ) ) {
				return 100 - $index;
			}
		}

		return 0;
	}

	/**
	 * @param object $field GF field object.
	 */
	private static function score_mobile_field( $field ): int {
		$type = isset( $field->type ) ? (string) $field->type : '';

		if ( 'phone' === $type ) {
			return 100;
		}

		if ( 'text' !== $type ) {
			return 0;
		}

		$label = self::field_label_text( $field );
		if ( '' === $label ) {
			return 0;
		}

		foreach ( self::MOBILE_LABEL_HINTS as $index => $hint ) {
			if ( self::contains_ci( $label, $hint ) ) {
				return 90 - $index;
			}
		}

		return 0;
	}

	/**
	 * @param array $form GFAPI form array.
	 */
	private static function form_has_payment_fields( array $form ): bool {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}

			$type = isset( $field->type ) ? (string) $field->type : '';
			if ( in_array( $type, self::PAYMENT_FIELD_TYPES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param object $field GF field object.
	 * @return array{key: string, label: string, field: object|null}|null
	 */
	private static function to_mapped_field( $field ): ?array {
		if ( ! is_object( $field ) || ! isset( $field->id ) ) {
			return null;
		}

		return array(
			'key'   => (string) $field->id,
			'label' => self::field_label_text( $field ),
			'field' => $field,
		);
	}

	/**
	 * @param object $field GF field object.
	 */
	private static function field_label_text( $field ): string {
		$label = isset( $field->label ) ? trim( (string) $field->label ) : '';
		if ( '' !== $label ) {
			return $label;
		}

		return isset( $field->adminLabel ) ? trim( (string) $field->adminLabel ) : '';
	}

	/**
	 * @param string $haystack Text to search in.
	 * @param string $needle   Substring.
	 */
	private static function contains_ci( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return false;
		}

		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}

		return false !== stripos( $haystack, $needle );
	}
}
