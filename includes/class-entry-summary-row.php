<?php
/**
 * Wide-format entry row for the unified entries list.
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

/**
 * One row per Gravity Forms entry (summary columns).
 */
final class GFA_Entry_Summary_Row {

	/** @var string */
	public $entry_id;

	/** @var string */
	public $form_id;

	/** @var string */
	public $form_title;

	/** @var string */
	public $form_url;

	/** @var string */
	public $name;

	/** @var string */
	public $mobile;

	/** @var string */
	public $payment_status;

	/** @var string */
	public $entry_date;

	/** @var string */
	public $entry_date_display;

	/**
	 * @param array<string, mixed> $data Row data.
	 */
	public static function from_array( array $data ): self {
		$row = new self();

		$row->entry_id            = self::cell( $data['entry_id'] ?? '' );
		$row->form_id             = self::cell( $data['form_id'] ?? '' );
		$row->form_title          = self::cell( $data['form_title'] ?? '' );
		$row->form_url            = self::cell( $data['form_url'] ?? '' );
		$row->name                = self::cell( $data['name'] ?? '' );
		$row->mobile              = self::cell( $data['mobile'] ?? '' );
		$row->payment_status      = self::cell( $data['payment_status'] ?? '' );
		$row->entry_date          = self::cell( $data['entry_date'] ?? '' );
		$row->entry_date_display  = self::cell( $data['entry_date_display'] ?? '' );

		return $row;
	}

	/**
	 * Build a summary row from GF entry context.
	 *
	 * @param array       $entry      GF entry array.
	 * @param int         $form_id    Form ID.
	 * @param string      $form_title Form title.
	 * @param array|null  $name_field Mapped name field or null.
	 * @param array|null  $mobile_field Mapped mobile field or null.
	 */
	public static function from_entry(
		array $entry,
		int $form_id,
		string $form_title,
		?array $name_field,
		?array $mobile_field
	): self {
		$date_raw = isset( $entry['date_created'] ) ? (string) $entry['date_created'] : '';
		$display  = '';

		if ( '' !== $date_raw ) {
			$timestamp = strtotime( $date_raw );
			if ( false !== $timestamp ) {
				$display = wp_date(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					$timestamp
				);
			}
		}

		return self::from_array(
			array(
				'entry_id'           => isset( $entry['id'] ) ? (string) $entry['id'] : '',
				'form_id'            => (string) $form_id,
				'form_title'         => $form_title,
				'form_url'           => self::get_form_entries_url( $form_id ),
				'name'               => $name_field ? GFA_Field_Mapper::get_field_value( $entry, $name_field ) : '',
				'mobile'             => $mobile_field ? GFA_Field_Mapper::get_field_value( $entry, $mobile_field ) : '',
				'payment_status'     => self::format_payment_status( $entry ),
				'entry_date'         => $date_raw,
				'entry_date_display' => $display,
			)
		);
	}

	/**
	 * Admin URL for a form's entries screen.
	 *
	 * @param int $form_id Form ID.
	 */
	public static function get_form_entries_url( int $form_id ): string {
		return admin_url( 'admin.php?page=gf_entries&id=' . absint( $form_id ) );
	}

	/**
	 * Human-readable payment status from a GF entry.
	 *
	 * @param array $entry GF entry array.
	 */
	public static function format_payment_status( array $entry ): string {
		$status = isset( $entry['payment_status'] ) ? trim( (string) $entry['payment_status'] ) : '';
		if ( '' === $status ) {
			return '';
		}

		$labels = array(
			'Paid'       => __( 'Paid', 'gravity-forms-aggregator' ),
			'Processing' => __( 'Processing', 'gravity-forms-aggregator' ),
			'Pending'    => __( 'Pending', 'gravity-forms-aggregator' ),
			'Failed'     => __( 'Failed', 'gravity-forms-aggregator' ),
			'Authorized' => __( 'Authorized', 'gravity-forms-aggregator' ),
			'Refunded'   => __( 'Refunded', 'gravity-forms-aggregator' ),
			'Voided'     => __( 'Voided', 'gravity-forms-aggregator' ),
			'Cancelled'  => __( 'Cancelled', 'gravity-forms-aggregator' ),
			'Active'     => __( 'Active', 'gravity-forms-aggregator' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * @return array<string, string>
	 */
	public function to_array(): array {
		return array(
			'entry_id'           => $this->entry_id,
			'form_id'            => $this->form_id,
			'form_title'         => $this->form_title,
			'form_url'           => $this->form_url,
			'name'               => $this->name,
			'mobile'             => $this->mobile,
			'payment_status'     => $this->payment_status,
			'entry_date'         => $this->entry_date,
			'entry_date_display' => $this->entry_date_display,
		);
	}

	/**
	 * @param mixed $value Raw cell value.
	 */
	private static function cell( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
