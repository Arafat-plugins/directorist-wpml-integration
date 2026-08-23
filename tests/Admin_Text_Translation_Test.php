<?php

define( 'ICL_TM_COMPLETE', 10 );
define( 'ARRAY_A', 'ARRAY_A' );

$test_language = 'nl';

function apply_filters( $hook, $value ) {
	global $test_language;

	if ( 'wpml_current_language' === $hook ) {
		return $test_language;
	}

	if ( 'wpml_default_language' === $hook ) {
		return 'en';
	}

	return $value;
}

function get_bloginfo( $show = '' ) {
	return 'UTF-8';
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

function is_email( $value ) {
	return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
}

$test_is_admin = false;
$test_wp_doing_ajax = false;

function is_admin() {
	global $test_is_admin;

	return $test_is_admin;
}

function wp_doing_ajax() {
	global $test_wp_doing_ajax;

	return $test_wp_doing_ajax;
}

function wp_unslash( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}

class Admin_Text_Translation_Test_DB {
	public $prefix = 'wp_';
	public $queries = 0;

	public function prepare( $query, ...$args ) {
		return $query;
	}

	public function get_results( $query, $format = null ) {
		$this->queries++;

		return [
			[
				'name'             => 'all_listing_title',
				'source_value'     => 'Items Found',
				'translated_value' => 'Vermeldingen gevonden',
			],
			[
				'name'             => 'email_sub_new_listing',
				'source_value'     => '[==SITE_NAME==] Listing ==LISTING_TITLE== Received',
				'translated_value' => '[==SITE_NAME==] Vermelding ==LISTING_TITLE== ontvangen',
			],
			[
				'name'             => 'email_sub_broken',
				'source_value'     => 'Listing ==LISTING_TITLE==',
				'translated_value' => 'Vermelding',
			],
		];
	}
}

$wpdb = new Admin_Text_Translation_Test_DB();

require_once dirname( __DIR__ ) . '/app/Controller/Hook/Admin_Text_Translation.php';

use Directorist_WPML_Integration\Controller\Hook\Admin_Text_Translation;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$reflection = new ReflectionClass( Admin_Text_Translation::class );
$service    = $reflection->newInstanceWithoutConstructor();

$email_key = $reflection->getMethod( 'is_email_template_key' );
$email_key->setAccessible( true );
$extension_key = $reflection->getMethod( 'is_extension_option_key' );
$extension_key->setAccessible( true );
$translatable_value = $reflection->getMethod( 'is_translatable_value' );
$translatable_value->setAccessible( true );
$target_language = $reflection->getMethod( 'get_current_target_language' );
$target_language->setAccessible( true );
$settings_ui_sources = $reflection->getMethod( 'get_settings_ui_source_strings' );
$settings_ui_sources->setAccessible( true );
$settings_ui_script = $reflection->getMethod( 'get_settings_ui_translation_script' );
$settings_ui_script->setAccessible( true );
$admin_menu_script = $reflection->getMethod( 'get_admin_menu_translation_script' );
$admin_menu_script->setAccessible( true );
$settings_ui_php_sources = $reflection->getMethod( 'extract_settings_ui_php_source_strings' );
$settings_ui_php_sources->setAccessible( true );
$directorist_admin_screen = $reflection->getMethod( 'is_directorist_admin_screen' );
$directorist_admin_screen->setAccessible( true );

assert_same( true, $email_key->invoke( $service, 'email_sub_new_listing' ), 'Native email subject keys must belong to the email package.' );
assert_same( true, $email_key->invoke( $service, 'email_tmpl_new_listing' ), 'Native email body keys must belong to the email package.' );
assert_same( false, $email_key->invoke( $service, 'email_from_name' ), 'Sender name is a setting, not an email template.' );
assert_same( true, $extension_key->invoke( $service, 'email_sub_new_claim' ), 'Claim Listing email keys must stay outside native packages.' );
assert_same( true, $extension_key->invoke( $service, 'booking_label' ), 'Booking extension settings must stay outside native packages.' );
assert_same( true, $extension_key->invoke( $service, 'chat_button_label' ), 'Chat extension settings must stay outside native packages.' );
assert_same( false, $extension_key->invoke( $service, 'email_sub_new_listing' ), 'Native listing email keys must not be classified as extension strings.' );
assert_same( false, $translatable_value->invoke( $service, '123' ), 'Numeric configuration must not enter ATE.' );
assert_same( false, $translatable_value->invoke( $service, 'admin@example.com' ), 'Email configuration must not enter ATE.' );
assert_same( true, $translatable_value->invoke( $service, 'Filters' ), 'User-facing text must enter ATE.' );

$ui_sources = $settings_ui_sources->invoke( $service );
foreach ( [ 'Settings tabs', 'Search settings...', 'Save changes', 'Default address', 'Used as the fallback map center when a listing has no address set.', 'Where users submit a new listing.', 'Shows the main directory archive.', 'Shown when a payment fails.', 'Select page', 'Copy shortcode' ] as $settings_ui_source ) {
	assert_same( true, in_array( $settings_ui_source, $ui_sources, true ), 'Native Directorist settings UI text must enter the Settings UI ATE package: ' . $settings_ui_source );
}
foreach ( [ 'Apply schema to', 'Order created', 'Listing owner notifications', 'All listings page meta title', 'Default: 640x360 px. If changed, regenerate thumbnails via this plugin for proper functionality.', 'If changed, regenerate thumbnails via plugin for proper functionality.', 'View directory', 'Allow users add and display booking for a listing.' ] as $settings_ui_source ) {
	assert_same( true, in_array( $settings_ui_source, $ui_sources, true ), 'Native supplemental Directorist settings UI text must enter the Settings UI ATE package: ' . $settings_ui_source );
}
foreach ( [ 'All Listings', 'Add New Listing', 'Categories', 'Locations', 'Reviews', 'All Chats', 'Directory Builder', 'Orders', 'Tags', 'Themes & Extensions', 'Tools', 'All Directories', 'Directory names', 'Deprecated Directorist items require upgrades', 'Show deprecated items', 'Manage Plugins', 'Manage Themes' ] as $admin_menu_source ) {
	assert_same( true, in_array( $admin_menu_source, $ui_sources, true ), 'Native Directorist admin menu text must enter the Settings UI ATE package: ' . $admin_menu_source );
}
foreach ( [ 'left_sidebar', 'example@email.com', '#ffffff' ] as $technical_source ) {
	assert_same( false, in_array( $technical_source, $ui_sources, true ), 'Technical or extension-owned settings-panel values must not enter the Settings UI ATE package: ' . $technical_source );
}
$fallback_sources = $settings_ui_php_sources->invoke(
	$service,
	"'label' => __( 'All Listings Layout', 'directorist' ),\n" .
	"'label' => __( 'Hide Top Search Bar', 'directorist' ),\n" .
	"'options' => [ 'grid' => __( 'Grid', 'directorist' ), 'map' => __( 'Map', 'directorist' ) ],\n" .
	"'label' => __( 'Search Bar Title', 'directorist' ),\n" .
	"'description' => 'Shown above the search form.',\n" .
	"'button-label' => __( 'Import JSON', 'directorist' ),\n" .
	"'label' => __( 'Disable Single Listing View' ),\n" .
	"'label' => __( 'Show Single Listings to Logged-In Users Only', 'directorist' ),\n" .
	"'value' => 'left_sidebar',\n"
);
foreach ( [ 'All Listings Layout', 'Hide Top Search Bar', 'Search Bar Title', 'Shown above the search form.', 'Import JSON', 'Disable Single Listing View', 'Show Single Listings to Logged-In Users Only' ] as $fallback_source ) {
	assert_same( true, in_array( $fallback_source, $fallback_sources, true ), 'PHP source fallback must extract visible settings UI text: ' . $fallback_source );
}
foreach ( [ 'Grid', 'Map' ] as $option_source ) {
	assert_same( false, in_array( $option_source, $fallback_sources, true ), 'PHP source fallback must not bulk-import option containers: ' . $option_source );
}
assert_same( false, in_array( 'left_sidebar', $fallback_sources, true ), 'PHP source fallback must ignore technical values.' );
foreach ( $fallback_sources as $fallback_source ) {
	assert_same( false, false !== strpos( $fallback_source, "' ), 'value' =>" ), 'PHP source fallback must not over-capture adjacent settings fields.' );
}
$ui_script = $settings_ui_script->invoke( $service );
assert_same(
	false,
	false !== strpos( $ui_script, 'menu-posts-at_biz_dir' ),
	'The Settings UI bridge must not own admin menu translation.'
);
assert_same(
	true,
	false !== strpos( $ui_script, 'settingsUiBootstrapAttempts' ),
	'The Settings UI bridge must retry while the settings app root is mounting.'
);
assert_same(
	true,
	false !== strpos( $ui_script, 'punctuationMatch' ),
	'The Settings UI bridge must translate strings when the Vue UI appends terminal punctuation.'
);
$menu_script = $admin_menu_script->invoke( $service );
assert_same(
	true,
	false !== strpos( $menu_script, 'menu-posts-at_biz_dir' ),
	'The admin menu bridge must scope translation to the Directorist menu branch only.'
);
assert_same(
	true,
	false !== strpos( $menu_script, 'directorist-deprecated-item-notice' ),
	'The admin menu bridge must translate Directorist admin notices outside Directorist screens.'
);
assert_same(
	true,
	false !== strpos( $menu_script, 'countMatch' ),
	'The admin menu bridge must preserve numeric counts in translated labels.'
);
assert_same(
	true,
	false !== strpos( $menu_script, 'directoristWpmlAdminMenuObserver' ),
	'The admin menu bridge must observe Directorist admin chrome mutations.'
);
assert_same(
	false,
	false !== strpos( $menu_script, 'atbdp-settings-manager' ),
	'The admin menu bridge must not depend on the settings manager app root.'
);
foreach ( [ 'If changed, regenerate thumbnails via', 'this', 'plugin for proper functionality.' ] as $inline_fragment_source ) {
	assert_same( false, in_array( $inline_fragment_source, $ui_sources, true ), 'Link-split orphan settings help fragments must not enter ATE as standalone fields: ' . $inline_fragment_source );
}

assert_same(
	'Vermeldingen gevonden',
	$service->translate_directorist_option( 'Items Found', 'all_listing_title' ),
	'Completed package settings must translate at the Directorist option boundary.'
);
assert_same(
	'[==SITE_NAME==] Vermelding ==LISTING_TITLE== ontvangen',
	$service->translate_directorist_option( '[==SITE_NAME==] Listing ==LISTING_TITLE== Received', 'email_sub_new_listing' ),
	'Completed package email values must preserve and translate every placeholder.'
);
assert_same(
	'Listing ==LISTING_TITLE==',
	$service->translate_directorist_option( 'Listing ==LISTING_TITLE==', 'email_sub_broken' ),
	'Translations with missing placeholders must never be used.'
);
assert_same(
	'Different source',
	$service->translate_directorist_option( 'Different source', 'all_listing_title' ),
	'A stale package source must never replace a changed Directorist option.'
);
assert_same(
	'Claim',
	$service->translate_directorist_option( 'Claim', 'email_sub_new_claim' ),
	'Extension-owned Claim Listing strings must remain untouched.'
);
assert_same( 1, $wpdb->queries, 'All option translations in one language must reuse one package query per request.' );

$test_language  = 'en';
$test_is_admin  = true;
$_GET           = [
	'page'      => 'atbdp-settings',
	'post_type' => 'at_biz_dir',
	'lang'      => 'nl',
];
assert_same( 'nl', $target_language->invoke( $service ), 'Directorist settings screen must respect WPML admin content language from the URL.' );
assert_same( true, $directorist_admin_screen->invoke( $service ), 'Directorist settings screen must be treated as a Directorist admin screen.' );

$_GET = [
	'page'      => 'atbdp-directory-types',
	'post_type' => 'at_biz_dir',
	'lang'      => 'nl',
];
assert_same( 'nl', $target_language->invoke( $service ), 'Directorist non-settings admin screens must also respect WPML admin content language from the URL.' );
assert_same( true, $directorist_admin_screen->invoke( $service ), 'Directorist builder screen must be treated as a Directorist admin screen.' );

$_GET = [
	'post_type' => 'atbdp_chats',
	'lang'      => 'nl',
];
assert_same( true, $directorist_admin_screen->invoke( $service ), 'Directorist live chat screen must be treated as a Directorist admin screen.' );

$_GET['lang'] = 'all';
assert_same( '', $target_language->invoke( $service ), 'WPML all-languages admin mode must not be treated as a target language.' );

$_GET = [
	'page' => 'options-general.php',
	'lang' => 'nl',
];
assert_same( 'nl', $target_language->invoke( $service ), 'Admin menu translation must respect WPML admin content language even outside Directorist screens.' );
assert_same( false, $directorist_admin_screen->invoke( $service ), 'Unrelated admin screens must remain outside the Directorist settings-app scope.' );

$test_is_admin = false;
$_GET          = [];

$test_language = 'en';
assert_same(
	'Items Found',
	$service->translate_directorist_option( 'Items Found', 'all_listing_title' ),
	'Source-language Directorist options must remain unchanged.'
);
assert_same( 1, $wpdb->queries, 'Source-language reads must not query target package translations.' );

echo "Directorist settings/email ATE package tests passed.\n";
