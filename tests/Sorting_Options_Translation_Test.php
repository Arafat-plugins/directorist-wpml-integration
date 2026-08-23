<?php

$page_ui_translations = [
	'A to Z (title)' => 'A naar Z (titel)',
	'Latest listings' => 'Nieuwste vermeldingen',
	'Grid'            => 'Raster',
	'List'            => 'Lijst',
];

function apply_filters( $hook, $value, ...$args ) {
	global $page_ui_translations;

	if ( 'directorist_wpml_translate_page_ui_string' !== $hook ) {
		return $value;
	}

	$source = isset( $args[0] ) && is_string( $args[0] ) ? $args[0] : $value;

	return isset( $page_ui_translations[ $source ] ) ? $page_ui_translations[ $source ] : $value;
}

function do_action() {
}

if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
	define( 'ICL_SITEPRESS_VERSION', 'test' );
}

require_once dirname( __DIR__ ) . '/app/Controller/Hook/Sorting_Options_Translation.php';

use Directorist_WPML_Integration\Controller\Hook\Sorting_Options_Translation;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$reflection = new ReflectionClass( Sorting_Options_Translation::class );
$service    = $reflection->newInstanceWithoutConstructor();

$orderby = $service->translate_orderby_options(
	[
		'title-asc'  => 'A to Z (title)',
		'date-desc'  => 'Latest listings',
		'custom-key' => 'Custom Label',
	]
);

assert_same( 'A naar Z (titel)', $orderby['title-asc'], 'Known sorting labels must come from the current page ATE map.' );
assert_same( 'Nieuwste vermeldingen', $orderby['date-desc'], 'Known sorting labels must use the stored ATE source string, not runtime gettext.' );
assert_same( 'Custom Label', $orderby['custom-key'], 'Unknown sorting labels must remain unchanged.' );

$listings = (object) [
	'views' => [
		'grid'   => 'Grid',
		'list'   => 'List',
		'custom' => 'Custom View',
	],
];

assert_same(
	'archive/viewas-dropdown',
	$service->translate_view_options_in_template( 'archive/viewas-dropdown', [ 'listings' => $listings ] ),
	'View-option template filtering must not change the template path.'
);

assert_same( 'Raster', $listings->views['grid'], 'Known view labels must come from the current page ATE map.' );
assert_same( 'Lijst', $listings->views['list'], 'Known view labels must come from the current page ATE map.' );
assert_same( 'Custom View', $listings->views['custom'], 'Unknown view labels must remain unchanged.' );

echo "Sorting and view option ATE map tests passed.\n";
