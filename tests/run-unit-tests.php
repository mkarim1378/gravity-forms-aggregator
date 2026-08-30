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

if ( $failed > 0 ) {
	echo "\n{$failed} test(s) failed.\n";
	exit( 1 );
}

echo "\nAll unit tests passed.\n";
exit( 0 );
