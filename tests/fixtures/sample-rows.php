<?php
/**
 * Sample unified export rows for unit tests.
 *
 * @package GravityFormsAggregator
 */

return array(
	'complete' => array(
		'form_id'     => '1',
		'form_title'  => 'Contact Form',
		'entry_id'    => '42',
		'entry_date'  => '2024-06-15 10:30:00',
		'field_label' => 'Email',
		'field_value' => 'user@example.com',
	),
	'escaped' => array(
		'form_id'     => '1',
		'form_title'  => 'Contact Form',
		'entry_id'    => '43',
		'entry_date'  => '2024-06-16 11:00:00',
		'field_label' => 'Notes',
		'field_value' => 'hello, "world"',
	),
	'minimal' => array(
		'form_id'  => '2',
		'entry_id' => '7',
	),
);
