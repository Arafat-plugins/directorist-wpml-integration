<?php

$page_ui_translations = [
	'Keyword'     => 'Trefwoord',
	'Search here' => 'Zoek hier',
	'Any value'   => 'Elke waarde',
	'Free'        => 'Gratis',
	'Amenities'   => 'Voorzieningen',
	'Feature'     => 'Kenmerk',
	'Open Now'    => 'Nu open',
	'Min'         => 'Minimum',
	'Max'         => 'Maximum',
];

function apply_filters( $hook, $value, ...$args ) {
	global $page_ui_translations;

	if ( 'directorist_wpml_translate_page_ui_string' !== $hook ) {
		return $value;
	}

	$source = isset( $args[0] ) && is_string( $args[0] ) ? $args[0] : $value;

	return isset( $page_ui_translations[ $source ] ) ? $page_ui_translations[ $source ] : $value;
}

require_once dirname( __DIR__ ) . '/app/Controller/Hook/Search_Form_Field_Translation.php';

use Directorist_WPML_Integration\Controller\Hook\Search_Form_Field_Translation;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$reflection = new ReflectionClass( Search_Form_Field_Translation::class );
$service    = $reflection->newInstanceWithoutConstructor();
$translate  = $reflection->getMethod( 'translate_search_form_fields' );
$translate->setAccessible( true );

$fields = [
	'fields' => [
		'keyword' => [
			'label'       => 'Keyword',
			'placeholder' => 'Search here',
			'description' => 'Any value',
			'options'     => [
				'free'  => 'Free',
				'group' => [
					'label'        => 'Amenities',
					'option_label' => 'Feature',
					'options'      => [
						'open_now' => 'Open Now',
					],
				],
			],
		],
		'pricing' => [
			'widget_name' => 'pricing',
			'label'       => 'Pricing',
		],
	],
];

$translated = $translate->invoke( $service, $fields, 115 );

assert_same( 'Trefwoord', $translated['fields']['keyword']['label'], 'Search field labels must come from the current page ATE map.' );
assert_same( 'Zoek hier', $translated['fields']['keyword']['placeholder'], 'Search field placeholders must come from the current page ATE map.' );
assert_same( 'Elke waarde', $translated['fields']['keyword']['description'], 'Search field descriptions must come from the current page ATE map.' );
assert_same( 'Gratis', $translated['fields']['keyword']['options']['free'], 'Scalar search field options must come from the current page ATE map.' );
assert_same( 'Voorzieningen', $translated['fields']['keyword']['options']['group']['label'], 'Grouped search field labels must come from the current page ATE map.' );
assert_same( 'Kenmerk', $translated['fields']['keyword']['options']['group']['option_label'], 'Grouped search field option labels must come from the current page ATE map.' );
assert_same( 'Nu open', $translated['fields']['keyword']['options']['group']['options']['open_now'], 'Nested search field options must come from the current page ATE map.' );
assert_same( 'Minimum', $translated['fields']['pricing']['price_range_min_placeholder'], 'Empty minimum price placeholders must use the raw ATE source fallback.' );
assert_same( 'Maximum', $translated['fields']['pricing']['price_range_max_placeholder'], 'Empty maximum price placeholders must use the raw ATE source fallback.' );

assert_same(
	$fields,
	$translate->invoke( $service, $fields, 0 ),
	'Invalid directory IDs must not alter search-form meta.'
);

echo "Search form field ATE map tests passed.\n";
