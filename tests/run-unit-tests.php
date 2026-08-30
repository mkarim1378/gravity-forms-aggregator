<?php
/**
 * Unit tests for GFA_Export_Row.
 *
 * Run: php tests/run-unit-tests.php
 *
 * @package GravityFormsAggregator
 */

require __DIR__ . '/bootstrap.php';

$fixtures = require __DIR__ . '/fixtures/sample-rows.php';
$failed   = 0;

/**
 * @param bool   $condition Assertion.
 * @param string $message   Failure message.
 */
function gfa_assert( $condition, $message ) {
	global $failed;

	if ( ! $condition ) {
		echo "FAIL: {$message}\n";
		++$failed;
		return;
	}

	echo "PASS: {$message}\n";
}

// from_array maps all columns from a complete fixture row.
$complete = GFA_Export_Row::from_array( $fixtures['complete'] );
gfa_assert( '1' === $complete->form_id, 'form_id parsed from fixture' );
gfa_assert( 'Contact Form' === $complete->form_title, 'form_title parsed from fixture' );
gfa_assert( '42' === $complete->entry_id, 'entry_id parsed from fixture' );
gfa_assert( 'user@example.com' === $complete->field_value, 'field_value parsed from fixture' );
gfa_assert( $complete->is_valid(), 'complete row is valid' );

// Missing keys normalize to empty strings.
$minimal = GFA_Export_Row::from_array( $fixtures['minimal'] );
gfa_assert( '' === $minimal->form_title, 'missing form_title becomes empty string' );
gfa_assert( '' === $minimal->field_value, 'missing field_value becomes empty string' );
gfa_assert( $minimal->is_valid(), 'minimal row with form_id and entry_id is valid' );

// Invalid row lacks required identifiers.
$invalid = GFA_Export_Row::from_array( array( 'form_title' => 'Orphan' ) );
gfa_assert( ! $invalid->is_valid(), 'row without form_id and entry_id is invalid' );

// Ordered values follow export column order from GFA_Export_Config.
$ordered = $complete->to_ordered_values();
gfa_assert(
	$ordered === array_values( $complete->to_array() ) && count( $ordered ) === count( GFA_Export_Config::get_column_keys() ),
	'to_ordered_values matches export column count and order'
);
gfa_assert(
	$ordered[0] === '1' && $ordered[5] === 'user@example.com',
	'to_ordered_values preserves first and last cell values'
);

// normalize_cell coerces scalars and rejects arrays.
gfa_assert( '99' === GFA_Export_Row::normalize_cell( 99 ), 'normalize_cell stringifies integers' );
gfa_assert( '' === GFA_Export_Row::normalize_cell( array( 'a' ) ), 'normalize_cell rejects arrays' );

// CSV writer outputs header + rows with UTF-8 BOM.
$csv      = new GFA_Csv_Exporter( null );
$handle   = fopen( 'php://memory', 'w+' );
$written  = GFA_Export_Config::csv_uses_bom() ? fwrite( $handle, "\xEF\xBB\xBF" ) : 0;
fputcsv( $handle, array_values( GFA_Export_Config::get_columns() ), ',', '"', '\\' );
$row_count = $csv->write_rows_to_handle(
	$handle,
	array(
		GFA_Export_Row::from_array( $fixtures['complete'] ),
		GFA_Export_Row::from_array( $fixtures['escaped'] ),
	)
);
rewind( $handle );
$csv_content = stream_get_contents( $handle );
fclose( $handle );

gfa_assert( 2 === $row_count, 'write_rows_to_handle writes two data rows' );
gfa_assert( 0 === strpos( $csv_content, "\xEF\xBB\xBF" ), 'CSV begins with UTF-8 BOM' );
gfa_assert( false !== strpos( $csv_content, 'Form ID' ), 'CSV contains standard header labels' );
gfa_assert( false !== strpos( $csv_content, 'user@example.com' ), 'CSV contains unescaped simple value' );
gfa_assert( false !== strpos( $csv_content, 'hello, ""world""' ), 'CSV escapes comma-containing field value' );

// Preview formatter normalizes engine summary for UI responses.
$formatted = GFA_Export_Preview::format_summary(
	array(
		'form_count'     => 3,
		'entry_count'    => 12,
		'date_label'     => '2024-01-01 to 2024-12-31',
		'empty_form_ids' => array( 2, '5' ),
	)
);
gfa_assert( 3 === $formatted['form_count'], 'format_summary preserves form_count' );
gfa_assert( 12 === $formatted['entry_count'], 'format_summary preserves entry_count' );
gfa_assert( true === $formatted['has_entries'], 'format_summary sets has_entries when count > 0' );
gfa_assert( array( 2, 5 ) === $formatted['empty_form_ids'], 'format_summary coerces empty_form_ids to integers' );

$empty_preview = GFA_Export_Preview::format_summary(
	array(
		'form_count'     => 1,
		'entry_count'    => 0,
		'date_label'     => 'All dates',
		'empty_form_ids' => array( 1 ),
	)
);
gfa_assert( false === $empty_preview['has_entries'], 'format_summary sets has_entries false for zero entries' );

$stale_preview = GFA_Export_Preview::format_summary(
	array(
		'form_count'       => 2,
		'entry_count'      => 5,
		'date_label'       => 'All dates',
		'empty_form_ids'   => array(),
		'stale_form_ids'   => array( 3 ),
		'export_mode'      => GFA_Export_Config::EXPORT_MODE_PHONE_FIELDS,
		'export_mode_label'=> 'Phone fields only',
	)
);
gfa_assert( array( 3 ) === $stale_preview['stale_form_ids'], 'format_summary includes stale_form_ids' );
gfa_assert(
	GFA_Export_Config::EXPORT_MODE_PHONE_FIELDS === $stale_preview['export_mode'],
	'format_summary preserves export_mode'
);

gfa_assert( GFA_Export_Config::is_valid_mode( GFA_Export_Config::EXPORT_MODE_ALL_FIELDS ), 'all_fields is a valid export mode' );
gfa_assert( GFA_Export_Config::is_valid_mode( GFA_Export_Config::EXPORT_MODE_PHONE_FIELDS ), 'phone_fields is a valid export mode' );
gfa_assert( ! GFA_Export_Config::is_valid_mode( 'invalid_mode' ), 'unknown export mode is rejected' );

gfa_assert(
	true === GFA_Form_Insights::is_stale_active_form( true, null ),
	'active form with no entries is stale'
);
gfa_assert(
	false === GFA_Form_Insights::is_stale_active_form( false, null ),
	'inactive form is not stale'
);
gfa_assert(
	false === GFA_Form_Insights::is_stale_active_form( true, gmdate( 'Y-m-d H:i:s' ) ),
	'active form with a recent entry is not stale'
);

$phone_field = (object) array( 'type' => 'phone' );
$email_field = (object) array( 'type' => 'email' );
gfa_assert(
	true === GFA_Field_Mapper::matches_export_mode( $phone_field, GFA_Export_Config::EXPORT_MODE_PHONE_FIELDS ),
	'phone field matches phone_fields mode'
);
gfa_assert(
	false === GFA_Field_Mapper::matches_export_mode( $email_field, GFA_Export_Config::EXPORT_MODE_PHONE_FIELDS ),
	'email field does not match phone_fields mode'
);
gfa_assert(
	true === GFA_Field_Mapper::matches_export_mode( $email_field, GFA_Export_Config::EXPORT_MODE_ALL_FIELDS ),
	'all_fields mode includes email fields'
);

// XLSX writer produces a valid zip archive with standard headers and data rows.
if ( GFA_Xlsx_Writer::is_supported() ) {
	gfa_assert( 'A' === GFA_Xlsx_Writer::column_letter( 0 ), 'column_letter maps index 0 to A' );
	gfa_assert( 'F' === GFA_Xlsx_Writer::column_letter( 5 ), 'column_letter maps index 5 to F' );
	gfa_assert(
		'hello &amp; world' === GFA_Xlsx_Writer::escape_xml_text( 'hello & world' ),
		'escape_xml_text encodes ampersands'
	);

	$xlsx      = new GFA_Xlsx_Exporter( null );
	$writer    = new GFA_Xlsx_Writer();
	$xlsx_handle = fopen( 'php://memory', 'w+b' );
	$opened    = $writer->open();
	gfa_assert( true === $opened, 'Xlsx writer opens successfully' );

	if ( true === $opened ) {
		$writer->write_row( array_values( GFA_Export_Config::get_columns() ) );
		$xlsx_rows = $xlsx->write_rows_to_writer(
			$writer,
			array(
				GFA_Export_Row::from_array( $fixtures['complete'] ),
				GFA_Export_Row::from_array( $fixtures['escaped'] ),
			)
		);
		gfa_assert( 2 === $xlsx_rows, 'write_rows_to_writer writes two data rows' );

		$finalized = $writer->finalize_to_handle( $xlsx_handle );
		gfa_assert( true === $finalized, 'Xlsx writer finalizes to handle' );

		rewind( $xlsx_handle );
		$xlsx_bytes = stream_get_contents( $xlsx_handle );
		fclose( $xlsx_handle );

		gfa_assert( 0 === strpos( $xlsx_bytes, "PK" ), 'XLSX begins with ZIP signature' );

		$zip_path = tempnam( sys_get_temp_dir(), 'gfa-xlsx-test-' );
		file_put_contents( $zip_path, $xlsx_bytes );
		$zip = new ZipArchive();
		$zip_opened = $zip->open( $zip_path );
		gfa_assert( true === $zip_opened, 'XLSX opens as zip archive' );

		if ( true === $zip_opened ) {
			$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
			gfa_assert( false !== $sheet_xml, 'XLSX contains worksheet part' );
			gfa_assert( false !== strpos( (string) $sheet_xml, 'Form ID' ), 'XLSX sheet contains standard header labels' );
			gfa_assert( false !== strpos( (string) $sheet_xml, 'user@example.com' ), 'XLSX sheet contains exported value' );
			gfa_assert( false !== strpos( (string) $sheet_xml, 'hello, &quot;world&quot;' ), 'XLSX sheet escapes special characters' );
			$zip->close();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $zip_path );
	}
} else {
	echo "SKIP: ZipArchive not available — XLSX tests omitted.\n";
}

/**
 * @param int    $id    Field ID.
 * @param string $type  GF field type.
 * @param string $label Field label.
 * @return stdClass
 */
function gfa_mock_field( $id, $type, $label ) {
	$field        = new stdClass();
	$field->id    = $id;
	$field->type  = $type;
	$field->label = $label;

	return $field;
}

$resolved_form = GFA_Entry_Field_Resolver::resolve(
	array(
		'fields' => array(
			gfa_mock_field( 1, 'text', 'نام و نام خانوادگی' ),
			gfa_mock_field( 2, 'phone', 'شماره موبایل' ),
			gfa_mock_field( 3, 'product', 'Product' ),
		),
	)
);
gfa_assert( null !== $resolved_form['name'], 'resolver finds name field by Persian label' );
gfa_assert( '1' === $resolved_form['name']['key'], 'resolver maps name field key' );
gfa_assert( null !== $resolved_form['mobile'], 'resolver finds phone field' );
gfa_assert( '2' === $resolved_form['mobile']['key'], 'resolver maps mobile field key' );
gfa_assert( true === $resolved_form['has_payment'], 'resolver detects payment-enabled form' );

$text_mobile_form = GFA_Entry_Field_Resolver::resolve(
	array(
		'fields' => array(
			gfa_mock_field( 4, 'text', 'نام' ),
			gfa_mock_field( 5, 'text', 'موبایل' ),
		),
	)
);
gfa_assert( '4' === $text_mobile_form['name']['key'], 'resolver prefers text name field' );
gfa_assert( '5' === $text_mobile_form['mobile']['key'], 'resolver finds mobile text field by label' );
gfa_assert( false === $text_mobile_form['has_payment'], 'resolver reports no payment fields' );

gfa_assert( 'Paid' === GFA_Entry_Summary_Row::format_payment_status( array( 'payment_status' => 'Paid' ) ), 'payment status label for Paid' );
gfa_assert( '' === GFA_Entry_Summary_Row::format_payment_status( array( 'payment_status' => '' ) ), 'empty payment status stays empty' );
gfa_assert( 'Custom' === GFA_Entry_Summary_Row::format_payment_status( array( 'payment_status' => 'Custom' ) ), 'unknown payment status passes through' );

$range = new GFA_Date_Range( null, null );
$extractor = new GFA_Data_Extractor();

$no_search = $extractor->build_form_entry_search_criteria(
	$range,
	'',
	array( 'key' => '1', 'label' => 'Name', 'field' => null ),
	array( 'key' => '2', 'label' => 'Mobile', 'field' => null )
);
gfa_assert( true === $no_search['searchable'], 'empty search leaves form searchable' );
gfa_assert( ! isset( $no_search['criteria']['field_filters'] ), 'empty search adds no field filters' );

$name_only = $extractor->build_form_entry_search_criteria(
	$range,
	'Ali',
	array( 'key' => '1', 'label' => 'Name', 'field' => null ),
	null
);
gfa_assert( true === $name_only['searchable'], 'name-only search is searchable' );
gfa_assert(
	1 === count( $name_only['criteria']['field_filters'] ),
	'name-only search adds one field filter'
);
gfa_assert(
	'contains' === $name_only['criteria']['field_filters'][0]['operator'],
	'name search uses contains operator'
);

$both_fields = $extractor->build_form_entry_search_criteria(
	$range,
	'0912',
	array( 'key' => '1', 'label' => 'Name', 'field' => null ),
	array( 'key' => '2', 'label' => 'Mobile', 'field' => null )
);
gfa_assert( true === $both_fields['searchable'], 'name and mobile search is searchable' );
gfa_assert(
	'any' === $both_fields['criteria']['field_filters'][0]['mode'],
	'name/mobile search uses OR mode'
);

$unmapped = $extractor->build_form_entry_search_criteria( $range, 'test', null, null );
gfa_assert( false === $unmapped['searchable'], 'search with no mapped fields is not searchable' );

if ( $failed > 0 ) {
	echo "\n{$failed} test(s) failed.\n";
	exit( 1 );
}

echo "\nAll unit tests passed.\n";
exit( 0 );
