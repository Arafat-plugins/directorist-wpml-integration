<?php

function get_bloginfo( $show = '' ) {
	return 'UTF-8';
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function sanitize_title( $text ) {
	$text = strtolower( (string) $text );
	$text = preg_replace( '/[^a-z0-9_]+/', '-', $text );

	return trim( $text, '-' );
}

function apply_filters( $hook_name, $value ) {
	return $value;
}

require_once dirname( __DIR__ ) . '/app/Controller/Hook/Directory_Builder_UI_String_Package.php';

use Directorist_WPML_Integration\Controller\Hook\Directory_Builder_UI_String_Package;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$reflection = new ReflectionClass( Directory_Builder_UI_String_Package::class );
$package    = $reflection->newInstanceWithoutConstructor();

$translate_data = $reflection->getMethod( 'translate_data_recursively' );
$translate_data->setAccessible( true );

$is_placeholder = $reflection->getMethod( 'is_language_prefixed_placeholder' );
$is_placeholder->setAccessible( true );

$strip_placeholder = $reflection->getMethod( 'strip_language_prefixed_placeholder' );
$strip_placeholder->setAccessible( true );

$bridge_strings = $reflection->getMethod( 'get_builder_chrome_bridge_strings' );
$bridge_strings->setAccessible( true );

$source_map = $reflection->getMethod( 'build_completed_translation_source_map' );
$source_map->setAccessible( true );

assert_same( true, $is_placeholder->invoke( $package, 'NL-QA: Video (Optional)', 'nl' ), 'Dutch QA-prefixed builder UI values must be detected.' );
assert_same( false, $is_placeholder->invoke( $package, 'Video (optioneel)', 'nl' ), 'Normal Dutch builder UI values must not be flagged as placeholders.' );
assert_same( 'Video (Optional)', $strip_placeholder->invoke( $package, 'NL-QA: Video (Optional)', 'nl' ), 'Placeholder stripping must recover the source label.' );

$builder_chrome_strings = $bridge_strings->invoke( $package );
foreach ( [ 'Listing Header', 'Listing Contents', 'Custom Single Listing Page', 'Listing Title', 'Enable Custom Single Listing Page', 'Enabling this option will replace the default single listing page. After enabling you must create and assign a new page with generated shortcodes to display single listing content' ] as $builder_chrome_source ) {
	assert_same( true, in_array( $builder_chrome_source, $builder_chrome_strings, true ), 'Visible builder chrome text must be exposed to the Builder ATE package: ' . $builder_chrome_source );
}

$target_builder_data = [
	'id'     => 144,
	'options' => [
		'name' => [
			'value' => 'NL: General',
		],
	],
	'fields' => [
		'search_form_fields' => [
			'sections' => [
				[
					'label'       => 'NL-QA: Search Bar',
					'description' => 'NL: Choose your preferred appearance: Show preview image or hide preview image',
				],
			],
		],
		'submission_form_fields' => [
			'widgets' => [
				'preset' => [
					'widgets' => [
						'tag' => [
							'label' => 'NL-QA: Tag',
						],
					],
				],
			],
		],
	],
];

$source_builder_data = [
	'id'     => 115,
	'options' => [
		'name' => [
			'value' => 'General',
		],
	],
	'fields' => [
		'search_form_fields' => [
			'sections' => [
				[
					'label'       => 'Search Bar',
					'description' => 'Choose your preferred appearance: Show preview image or hide preview image',
				],
			],
		],
		'submission_form_fields' => [
			'widgets' => [
				'preset' => [
					'widgets' => [
						'tag' => [
							'label' => 'Tag',
						],
					],
				],
			],
		],
	],
];

$translated = $translate_data->invoke( $package, $target_builder_data, [], [], [], 'nl', $source_builder_data );

assert_same( 144, $translated['id'], 'Admin builder target directory ID/config must be preserved.' );
assert_same( 'General', $translated['options']['name']['value'], 'Admin builder directory display name must use the source directory_name package key instead of preserving NL placeholders.' );
assert_same( 'Search Bar', $translated['fields']['search_form_fields']['sections'][0]['label'], 'Admin builder must use source UI labels instead of preserving NL-QA placeholders.' );
assert_same( 'Choose your preferred appearance: Show preview image or hide preview image', $translated['fields']['search_form_fields']['sections'][0]['description'], 'Admin builder descriptions must use source text when target data contains NL placeholders.' );
assert_same( 'Tag', $translated['fields']['submission_form_fields']['widgets']['preset']['widgets']['tag']['label'], 'Admin builder sidebar labels must use source text when target data contains NL-QA placeholders.' );

$fallback_translated = $translate_data->invoke(
	$package,
	[
		'fields' => [
			'video' => [
				'label' => 'NL-QA: Video (Optional)',
			],
		],
	],
	[],
	[],
	[],
	'nl',
	[]
);

assert_same( 'Video (Optional)', $fallback_translated['fields']['video']['label'], 'Admin builder must strip target-language placeholders even when no source path exists.' );

$translation_source_map = $source_map->invoke(
	$package,
	[
		'path_specific_image_slider' => [
			'source'      => 'Listing Image/Slider',
			'translation' => 'Vermeldingsafbeelding/slider',
		],
		'duplicate_same_translation' => [
			'source'      => 'Listing Image/Slider',
			'translation' => 'Vermeldingsafbeelding/slider',
		],
		'ambiguous_one' => [
			'source'      => 'Action',
			'translation' => 'Actie',
		],
		'ambiguous_two' => [
			'source'      => 'Action',
			'translation' => 'Handeling',
		],
	],
	'nl'
);

assert_same( true, in_array( 'Vermeldingsafbeelding/slider', $translation_source_map, true ), 'Runtime builder fallback must allow identical source-value translations.' );
assert_same( false, in_array( 'Actie', $translation_source_map, true ), 'Runtime builder fallback must skip ambiguous source-value translations.' );

echo "Directory Builder UI translation placeholder tests passed.\n";
