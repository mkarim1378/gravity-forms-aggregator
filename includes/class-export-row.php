<?php
/**
 * Unified export row (long format — one field value per row).
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Value object for a single export row.
 */
final class GFA_Export_Row {

	/** @var string */
	public $form_id;

	/** @var string */
	public $form_title;

	/** @var string */
	public $entry_id;

	/** @var string */
	public $entry_date;

	/** @var string */
	public $field_label;

	/** @var string */
	public $field_value;

	/**
	 * @param array<string, mixed> $data Row data keyed by column slug.
	 */
	public static function from_array( array $data ): self {
		$row = new self();

		$row->form_id     = self::normalize_cell( $data['form_id'] ?? '' );
		$row->form_title  = self::normalize_cell( $data['form_title'] ?? '' );
		$row->entry_id    = self::normalize_cell( $data['entry_id'] ?? '' );
		$row->entry_date  = self::normalize_cell( $data['entry_date'] ?? '' );
		$row->field_label = self::normalize_cell( $data['field_label'] ?? '' );
		$row->field_value = self::normalize_cell( $data['field_value'] ?? '' );

		return $row;
	}

	/**
	 * Build a row from entry context and mapped field data.
	 *
	 * @param int    $form_id    Form ID.
	 * @param string $form_title Form title.
	 * @param array  $entry      GF entry array.
	 * @param array  $mapped     Mapped field from GFA_Field_Mapper.
	 */
	public static function from_entry_field( int $form_id, string $form_title, array $entry, array $mapped ): self {
		return self::from_array(
			array(
				'form_id'     => (string) $form_id,
				'form_title'  => $form_title,
				'entry_id'    => isset( $entry['id'] ) ? (string) $entry['id'] : '',
				'entry_date'  => isset( $entry['date_created'] ) ? (string) $entry['date_created'] : '',
				'field_label' => $mapped['label'] ?? '',
				'field_value' => GFA_Field_Mapper::get_field_value( $entry, $mapped ),
			)
		);
	}

	/**
	 * Row keyed by export column slug.
	 *
	 * @return array<string, string>
	 */
	public function to_array(): array {
		return array(
			'form_id'     => $this->form_id,
			'form_title'  => $this->form_title,
			'entry_id'    => $this->entry_id,
			'entry_date'  => $this->entry_date,
			'field_label' => $this->field_label,
			'field_value' => $this->field_value,
		);
	}

	/**
	 * Values in export column order (for CSV/XLSX writers).
	 *
	 * @return string[]
	 */
	public function to_ordered_values(): array {
		$values = array();

		foreach ( GFA_Export_Config::get_column_keys() as $key ) {
			$values[] = $this->to_array()[ $key ] ?? '';
		}

		return $values;
	}

	/**
	 * Whether the row has the minimum identifiers required for export.
	 */
	public function is_valid(): bool {
		return '' !== $this->form_id && '' !== $this->entry_id;
	}

	/**
	 * @param mixed $value Raw cell value.
	 */
	public static function normalize_cell( $value ): string {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
