<?php

$test_translation = '';

function apply_filters( $hook, $value, ...$args ) {
	global $test_translation;

	if ( 'wpml_current_language' === $hook ) {
		return 'nl';
	}

	if ( 'directorist_wpml_translate_page_ui_string' === $hook ) {
		return $test_translation;
	}

	return $value;
}

require_once dirname( __DIR__ ) . '/app/Controller/Hook/Selectfield_Translation.php';

use Directorist_WPML_Integration\Controller\Hook\Selectfield_Translation;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$reflection = new ReflectionClass( Selectfield_Translation::class );
$service    = $reflection->newInstanceWithoutConstructor();
$translate  = $reflection->getMethod( 'translate' );
$translate->setAccessible( true );

$test_translation = 'NL-QA: No results found';
assert_same(
	'No results found',
	$translate->invoke( $service, 'no_results', 'No results found' ),
	'QA placeholders must never render as Select2 translations.'
);

$test_translation = 'Geen resultaten gevonden';
assert_same(
	'Geen resultaten gevonden',
	$translate->invoke( $service, 'no_results', 'No results found' ),
	'Valid Select2 translations must still render.'
);

echo "Directorist Select2 translation guard tests passed.\n";
