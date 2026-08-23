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

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

require_once dirname( __DIR__ ) . '/app/Controller/Hook/Directory_Builder_String_Package.php';

use Directorist_WPML_Integration\Controller\Hook\Directory_Builder_String_Package;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$reflection = new ReflectionClass( Directory_Builder_String_Package::class );
$package    = $reflection->newInstanceWithoutConstructor();

$apply_translations = $reflection->getMethod( 'apply_named_translations_to_meta_value' );
$apply_translations->setAccessible( true );

$build_string = $reflection->getMethod( 'build_string_data' );
$build_string->setAccessible( true );

$build_top_level_string = $reflection->getMethod( 'build_top_level_meta_string_data' );
$build_top_level_string->setAccessible( true );

$is_top_level_value = $reflection->getMethod( 'is_translatable_top_level_meta_value' );
$is_top_level_value->setAccessible( true );

$is_translatable = $reflection->getMethod( 'is_translatable_string' );
$is_translatable->setAccessible( true );

$should_replace_scalar = $reflection->getMethod( 'should_replace_translated_scalar_value' );
$should_replace_scalar->setAccessible( true );

$is_usable_translation = $reflection->getMethod( 'is_usable_translation' );
$is_usable_translation->setAccessible( true );

$has_existing_translation_value = $reflection->getMethod( 'has_existing_translation_value' );
$has_existing_translation_value->setAccessible( true );

$inventory_hash = $reflection->getMethod( 'get_package_inventory_hash' );
$inventory_hash->setAccessible( true );

$get_runtime_strings = $reflection->getMethod( 'get_runtime_ui_strings' );
$get_runtime_strings->setAccessible( true );

$get_translated_directory_name = $reflection->getMethod( 'get_translated_directory_name' );
$get_translated_directory_name->setAccessible( true );

$source = [
	'fields' => [
		'title' => [
			'widget_name' => 'title-v2',
			'label'       => 'Title',
			'placeholder' => 'Enter Title',
		],
		'description' => [
			'widget_name' => 'description',
			'label'       => 'Description',
		],
	],
	'groups' => [
		[
			'key'   => 'general',
			'label' => 'General Information',
		],
	],
];
$source_before = $source;

$german = [
	'fields' => [
		'title' => [
			'widget_name' => 'title',
			'label'       => 'Titel',
			'placeholder' => 'Titel eingeben',
		],
		'description' => [
			'widget_name' => 'description',
			'label'       => 'Beschreibung',
		],
	],
	'groups' => [
		[
			'key'   => 'general',
			'label' => 'Allgemeine Informationen',
		],
	],
];

$french = [
	'fields' => [
		'title' => [
			'widget_name' => 'title',
			'label'       => 'Titre',
			'placeholder' => 'Saisir le titre',
		],
		'description' => [
			'widget_name' => 'description',
			'label'       => 'Description FR',
		],
	],
	'groups' => [
		[
			'key'   => 'general',
			'label' => 'Informations generales',
		],
	],
];

$unchanged_german = $apply_translations->invoke( $package, $source, 'submission_form_fields', [], $german );

assert_same( 'Allgemeine Informationen', $unchanged_german['groups'][0]['label'], 'A missing package translation must preserve the existing German section label.' );
assert_same( 'Titel', $unchanged_german['fields']['title']['label'], 'A missing package translation must preserve the existing German field label.' );
assert_same( 'Titel eingeben', $unchanged_german['fields']['title']['placeholder'], 'A missing package translation must preserve the existing German placeholder.' );
assert_same( 'Beschreibung', $unchanged_german['fields']['description']['label'], 'A missing package translation must preserve the existing German description label.' );
assert_same( 'title-v2', $unchanged_german['fields']['title']['widget_name'], 'Non-translatable builder configuration must continue to follow the source schema.' );
assert_same( $source_before, $source, 'Applying translated fallbacks must not mutate source-language builder data.' );

$description_string = $build_string->invoke(
	$package,
	'submission_form_fields',
	[ 'fields', 'description', 'label' ],
	'Description'
);

$updated_german = $apply_translations->invoke(
	$package,
	$source,
	'submission_form_fields',
	[ $description_string['name'] => 'Neue Beschreibung' ],
	$german
);

assert_same( 'Neue Beschreibung', $updated_german['fields']['description']['label'], 'A completed ATE value must replace the corresponding existing translation.' );
assert_same( 'Titel', $updated_german['fields']['title']['label'], 'Updating one ATE field must not reset another translated field.' );

$second_pass = $apply_translations->invoke( $package, $source, 'submission_form_fields', [], $updated_german );
assert_same( $updated_german, $second_pass, 'Repeated package repair must be idempotent.' );
assert_same( $source_before, $source, 'Repeated repair must leave source-language builder data unchanged.' );

$top_level_string = $build_top_level_string->invoke( $package, 'pending_confirmation_msg', 'Listing Submission: Pending confirmation message', 'Thank you for your submission.' );
assert_same( 'top_meta__pending_confirmation_msg', $top_level_string['name'], 'Top-level term meta string names must remain readable and stable.' );
assert_same( true, $is_top_level_value->invoke( $package, 'pending_confirmation_msg', 'Thank you for your submission.' ), 'Native visual top-level term meta must be exposed.' );
assert_same( false, $is_top_level_value->invoke( $package, 'booking_payment_type', 'directorist' ), 'Non-whitelisted technical top-level term meta must stay hidden.' );
assert_same( false, $is_top_level_value->invoke( $package, 'start_chat_button', 'Start Chatting' ), 'Extension-owned top-level term meta must stay out of the native package.' );
assert_same( false, $is_top_level_value->invoke( $package, 'pending_confirmation_msg', 'https://example.com' ), 'URLs must not be exposed as translatable top-level term meta.' );

assert_same(
	true,
	$is_translatable->invoke( $package, 'value', 'Back', [ '0', 'placeholders', '0', 'selectedWidgets', '0', 'options', 'fields', 'label', 'value' ] ),
	'Visual builder option-control values must be translatable.'
);
assert_same(
	false,
	$is_translatable->invoke( $package, 'value', 'service', [ 'fields', 'booking_type', 'value' ] ),
	'Generic value keys must remain protected from translation.'
);
assert_same(
	false,
	$is_translatable->invoke( $package, 'value', 'las la-phone', [ '0', 'placeholders', '0', 'selectedWidgets', '0', 'options', 'fields', 'icon', 'value' ] ),
	'Icon/config option values must remain protected from translation.'
);

assert_same( true, $should_replace_scalar->invoke( $package, 'Save & Preview', 'Save &amp; Preview' ), 'Source-equal translated scalars should be replaceable after entity decoding.' );
assert_same( false, $should_replace_scalar->invoke( $package, 'Save & Preview', 'Speichern und Vorschau' ), 'Existing non-empty translated scalar values must be preserved.' );
assert_same( true, $should_replace_scalar->invoke( $package, 'Video (Optional)', 'NL-QA: Video (Optional)', 'nl' ), 'Target-language QA placeholder builder values must be replaceable.' );
assert_same( false, $is_usable_translation->invoke( $package, 'Video (Optional)', 'NL-QA: Video (Optional)', 'nl' ), 'Target-language QA placeholders must not be treated as usable package translations.' );
assert_same( false, $has_existing_translation_value->invoke( $package, 'NL-QA: Video (Optional)', 'nl' ), 'Target-language QA placeholders must not be preserved as existing translations.' );

$source_term       = (object) [ 'name' => 'General' ];
$directory_name    = 'directory_name';
$legacy_name_field = 'directory_type_115_name';

assert_same(
	'Algemeen',
	$get_translated_directory_name->invoke( $package, $source_term, 115, [ $directory_name => 'Algemeen' ], 'nl' ),
	'Translated directory names must use a real ATE value.'
);
assert_same(
	'General',
	$get_translated_directory_name->invoke( $package, $source_term, 115, [ $directory_name => 'NL-QA: General' ], 'nl' ),
	'Translated directory names must reject target-language QA placeholders.'
);
assert_same(
	'Algemeen',
	$get_translated_directory_name->invoke( $package, $source_term, 115, [ $directory_name => 'NL-QA: General', $legacy_name_field => 'Algemeen' ], 'nl' ),
	'Translated directory names must skip placeholders and still accept a valid compatibility field.'
);

$video_string = $build_string->invoke(
	$package,
	'submission_form_fields',
	[ 'fields', 'video', 'label' ],
	'Video (Optional)'
);

$placeholder_existing = [
	'fields' => [
		'video' => [
			'label' => 'NL-QA: Video (Optional)',
		],
	],
];

$placeholder_source = [
	'fields' => [
		'video' => [
			'label' => 'Video (Optional)',
		],
	],
];

$placeholder_result = $apply_translations->invoke(
	$package,
	$placeholder_source,
	'submission_form_fields',
	[ $video_string['name'] => 'NL-QA: Video (Optional)' ],
	$placeholder_existing,
	[],
	[],
	'nl'
);

assert_same( 'Video (Optional)', $placeholder_result['fields']['video']['label'], 'A stale placeholder ATE value must fall back to source instead of leaking NL-QA text.' );

assert_same(
	$inventory_hash->invoke( $package, [ 'runtime_directorist_preview_changes', 'directory_name' ] ),
	$inventory_hash->invoke( $package, [ 'directory_name', 'runtime_directorist_preview_changes' ] ),
	'Package inventory hashes must be stable regardless of registration order.'
);
assert_same( true, '' !== $inventory_hash->invoke( $package, [ 'directory_name' ] ), 'Non-empty package inventories must produce a hash.' );

$runtime_strings = $get_runtime_strings->invoke( $package );
$native_count    = 0;

foreach ( $runtime_strings as $runtime_name => $runtime_string ) {
	if ( 0 === strpos( $runtime_name, 'runtime_directorist_native_' ) ) {
		$native_count++;
	}
}

assert_same( 139, $native_count, 'All audited native Directorist runtime strings must be exposed to ATE.' );
assert_same( 'Username or Email Address', $runtime_strings['runtime_directorist_native_username_or_email_address']['value'], 'Native login username/email label must be in the runtime package inventory.' );
assert_same( 'Allowed Files', $runtime_strings['runtime_directorist_native_allowed_files']['value'], 'Uppercase file label must stay distinct.' );
assert_same( 'Allowed files', $runtime_strings['runtime_directorist_native_allowed_files_2']['value'], 'Lowercase file label must stay distinct.' );
assert_same( 'Listing', $runtime_strings['runtime_directorist_native_listing']['value'], 'Uppercase listing label must stay distinct.' );
assert_same( 'listing', $runtime_strings['runtime_directorist_native_listing_2']['value'], 'Lowercase listing label must stay distinct.' );
assert_same( "Don't have an account? <a href='%s'>Sign up</a>", $runtime_strings['runtime_directorist_native_don_t_have_an_account_sign_up']['value'], 'Native runtime strings must preserve HTML and placeholders.' );
assert_same( "Searching\xE2\x80\xA6", $runtime_strings['runtime_directorist_select2_searching']['value'], 'Select2 searching source must keep the UTF-8 ellipsis.' );

$unchanged_french = $apply_translations->invoke( $package, $source, 'submission_form_fields', [], $french );
assert_same( 'Titre', $unchanged_french['fields']['title']['label'], 'German repair logic must preserve an existing French builder value.' );
assert_same( 'Informations generales', $unchanged_french['groups'][0]['label'], 'French section data must remain unchanged.' );

$xml = simplexml_load_file( dirname( __DIR__ ) . '/wpml-config.xml' );
if ( false === $xml ) {
	fwrite( STDERR, "wpml-config.xml could not be parsed.\n" );
	exit( 1 );
}

$builder_keys = [
	'submission_form_fields',
	'search_form_fields',
	'single_listing_header',
	'single_listings_contents',
	'listings_card_grid_view',
	'listings_card_list_view',
	'submit_button_label',
];

$actions = [];
foreach ( $xml->{'custom-term-fields'}->{'custom-term-field'} as $field ) {
	$actions[ (string) $field ] = (string) $field['action'];
}

foreach ( $builder_keys as $builder_key ) {
	assert_same( 'copy-once', isset( $actions[ $builder_key ] ) ? $actions[ $builder_key ] : null, $builder_key . ' must not continuously overwrite translated builder data.' );
}

assert_same( 'copy', isset( $actions['_default'] ) ? $actions['_default'] : null, 'The Directorist default-directory flag must continue to sync to every language.' );

echo "Directory Builder translation preservation tests passed.\n";
