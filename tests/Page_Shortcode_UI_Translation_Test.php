<?php

function get_bloginfo( $show = '' ) {
	return 'UTF-8';
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
}

function wp_kses_post( $text ) {
	return (string) $text;
}

function __( $text, $domain = 'default' ) {
	if ( 'Pricing' === $text && 'directorist' === $domain ) {
		return 'Prijsstelling';
	}

	if ( 'No Results found!' === $text && 'directorist' === $domain ) {
		return 'Geen resultaten gevonden!';
	}

	if ( 'Status' === $text && 'directorist' === $domain ) {
		return 'NL-QA: Status';
	}

	if ( 'Grid' === $text && 'directorist' === $domain ) {
		return 'Raster';
	}

	if ( 'List' === $text && 'directorist' === $domain ) {
		return 'Lijst';
	}

	if ( 'Map' === $text && 'directorist' === $domain ) {
		return 'Kaart';
	}

	if ( 'Log Out' === $text && 'directorist' === $domain ) {
		return 'Afmelden';
	}

	return $text;
}

function _x( $text, $context, $domain = 'default' ) {
	return $text;
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function get_option( $option, $default = false ) {
	global $test_options;

	if ( isset( $test_options ) && is_array( $test_options ) && array_key_exists( $option, $test_options ) ) {
		return $test_options[ $option ];
	}

	return $default;
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_title( $text ) {
	$text = strtolower( (string) $text );
	$text = preg_replace( '/[^a-z0-9_]+/', '-', $text );

	return trim( $text, '-' );
}

function apply_filters( $hook, $value ) {
	global $test_filter_values;

	if ( 'wpml_element_type' === $hook ) {
		return 'tax_' . $value;
	}

	return isset( $test_filter_values[ $hook ] ) ? $test_filter_values[ $hook ] : $value;
}

function do_action() {
}

function has_filter() {
	return true;
}

if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
	define( 'ICL_SITEPRESS_VERSION', 'test' );
}

require_once dirname( __DIR__ ) . '/app/Helper/WPML_Helper.php';
require_once dirname( __DIR__ ) . '/app/Controller/Hook/Page_Shortcode_UI_Translation.php';

use Directorist_WPML_Integration\Controller\Hook\Page_Shortcode_UI_Translation;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$reflection = new ReflectionClass( Page_Shortcode_UI_Translation::class );
$translator = $reflection->newInstanceWithoutConstructor();

$build_map = $reflection->getMethod( 'build_translation_map' );
$build_map->setAccessible( true );

$replace_output = $reflection->getMethod( 'replace_output_strings' );
$replace_output->setAccessible( true );

$is_usable_page_ui_translation = $reflection->getMethod( 'is_usable_page_ui_translation' );
$is_usable_page_ui_translation->setAccessible( true );

$should_replace_page_ui_translation = $reflection->getMethod( 'should_replace_page_ui_translation' );
$should_replace_page_ui_translation->setAccessible( true );

$decode_wpml_translation_field = $reflection->getMethod( 'decode_wpml_translation_field' );
$decode_wpml_translation_field->setAccessible( true );

$get_page_ui_translations_from_completed_fields = $reflection->getMethod( 'get_page_ui_translations_from_completed_fields' );
$get_page_ui_translations_from_completed_fields->setAccessible( true );

$collect_listing_archive_strings = $reflection->getMethod( 'collect_listing_archive_strings' );
$collect_listing_archive_strings->setAccessible( true );

$collect_shortcode_strings = $reflection->getMethod( 'collect_shortcode_strings' );
$collect_shortcode_strings->setAccessible( true );

$collect_page_ui_strings = $reflection->getMethod( 'collect_page_ui_strings' );
$collect_page_ui_strings->setAccessible( true );

$extract_elementor_widgets = $reflection->getMethod( 'extract_elementor_widgets' );
$extract_elementor_widgets->setAccessible( true );

$extract_builder_strings = $reflection->getMethod( 'extract_builder_strings' );
$extract_builder_strings->setAccessible( true );

$apply_search_form_field_fallbacks = $reflection->getMethod( 'apply_search_form_field_fallbacks' );
$apply_search_form_field_fallbacks->setAccessible( true );

$apply_shortcode_native_fallback_aliases = $reflection->getMethod( 'apply_shortcode_native_fallback_aliases' );
$apply_shortcode_native_fallback_aliases->setAccessible( true );

$apply_shortcode_dynamic_output_aliases = $reflection->getMethod( 'apply_shortcode_dynamic_output_aliases' );
$apply_shortcode_dynamic_output_aliases->setAccessible( true );

$replace_shortcode_dynamic_fragments = $reflection->getMethod( 'replace_shortcode_dynamic_fragments' );
$replace_shortcode_dynamic_fragments->setAccessible( true );

$current_page_map = $reflection->getProperty( 'current_page_map' );
$current_page_map->setAccessible( true );

$source_strings = [
	'general'       => 'General Information',
	'save'          => 'Save &amp; Preview',
	'phone'         => 'Phone',
	'title'         => 'Title',
	'description'   => 'Description',
	'category'      => 'Category',
	'or'            => 'or',
	'in'            => 'in',
	'on'            => 'on',
	'and'           => 'and',
	'duplicate_one' => 'Duplicate',
	'duplicate_two' => 'Duplicate',
];

$translated_strings = [
	'general'       => 'Allgemeine Informationen',
	'save'          => 'Speichern und Vorschau',
	'phone'         => 'Telefon',
	'title'         => 'Titel',
	'description'   => 'Beschreibung',
	'category'      => 'Kategorie',
	'or'            => 'oder',
	'in'            => 'innen',
	'on'            => 'auf',
	'and'           => 'und',
	'duplicate_one' => 'Erste',
	'duplicate_two' => 'Zweite',
];

$map = $build_map->invoke( $translator, $source_strings, $translated_strings );

assert_same( 'Erste', $map['Duplicate'], 'The first duplicate source must win deterministically.' );
assert_same( 'Save & Preview', array_key_first( array_filter( $map, static function ( $value ) {
	return 'Speichern und Vorschau' === $value;
} ) ), 'HTML entities must be normalized before lookup.' );

$placeholder_map = $build_map->invoke(
	$translator,
	[
		'publish_title' => 'You are about to publish',
		'video_label'   => 'Video (Optional)',
	],
	[
		'publish_title' => 'NL: You are about to publish',
		'video_label'   => 'Video (optioneel)',
	],
	'nl'
);

assert_same( false, array_key_exists( 'You are about to publish', $placeholder_map ), 'Page UI maps must ignore target-language QA placeholders.' );
assert_same( 'Video (optioneel)', $placeholder_map['Video (Optional)'], 'Valid page UI translations must remain usable while placeholders are ignored.' );

$input = '<section>'
	. '<h2>  General Information  </h2>'
	. '<p>Allgemeine Informationen</p>'
	. '<p>Information and Preview words stay intact.</p>'
	. '<p>Subcategory</p>'
	. '<span>or</span><span>in</span><span>on</span><span>and</span>'
	. '<button title="Save &amp; Preview" aria-label="Phone">Save &amp; Preview</button>'
	. '<label>Title: *</label><input aria-label="Title: *">'
	. '<label>Description:</label><label>Phone:</label>'
	. '<span data-label="Telephone">Phone</span>'
	. '<div>General <b>Information</b></div>'
	. '<script>var label = "Phone";</script>'
	. '<style>.Phone { display:block; }</style>'
	. '</section>';

$expected = '<section>'
	. '<h2>  Allgemeine Informationen  </h2>'
	. '<p>Allgemeine Informationen</p>'
	. '<p>Information and Preview words stay intact.</p>'
	. '<p>Subcategory</p>'
	. '<span>oder</span><span>innen</span><span>auf</span><span>und</span>'
	. '<button title="Speichern und Vorschau" aria-label="Telefon">Speichern und Vorschau</button>'
	. '<label>Titel: *</label><input aria-label="Titel: *">'
	. '<label>Beschreibung:</label><label>Telefon:</label>'
	. '<span data-label="Telephone">Telefon</span>'
	. '<div>General <b>Information</b></div>'
	. '<script>var label = "Phone";</script>'
	. '<style>.Phone { display:block; }</style>'
	. '</section>';

assert_same( $expected, $replace_output->invoke( $translator, $input, $map ), 'Only complete visible nodes and exact attribute values may be replaced.' );
assert_same( $expected, $replace_output->invoke( $translator, $expected, $map ), 'Running the replacement twice must be idempotent.' );

$current_page_map->setValue(
	$translator,
	[
		'Add to Favorite Button' => 'Toevoegen aan favorieten',
		'Listings Pagination'    => 'Paginering van vermeldingen',
	]
);

assert_same(
	'<nav aria-label="Paginering van vermeldingen"><button aria-label="Toevoegen aan favorieten"></button></nav>',
	$translator->translate_block_output(
		'<nav aria-label="Listings Pagination"><button aria-label="Add to Favorite Button"></button></nav>',
		[ 'blockName' => 'directorist/all-listing' ]
	),
	'Server-rendered Directorist blocks must use the same exact page ATE map as shortcodes.'
);

assert_same(
	'<button aria-label="Add to Favorite Button"></button>',
	$translator->translate_block_output(
		'<button aria-label="Add to Favorite Button"></button>',
		[ 'blockName' => 'core/button' ]
	),
	'Unrelated Gutenberg blocks must remain unchanged.'
);

$directorist_elementor_widget = new class() {
	public function get_name() {
		return 'directorist_all_listing';
	}
};

assert_same(
	'<button aria-label="Toevoegen aan favorieten"></button>',
	$translator->translate_elementor_widget_output(
		'<button aria-label="Add to Favorite Button"></button>',
		$directorist_elementor_widget
	),
	'Native Directorist Elementor output must use the same exact page ATE map.'
);

$unrelated_elementor_widget = new class() {
	public function get_name() {
		return 'heading';
	}
};

assert_same(
	'<button aria-label="Add to Favorite Button"></button>',
	$translator->translate_elementor_widget_output(
		'<button aria-label="Add to Favorite Button"></button>',
		$unrelated_elementor_widget
	),
	'Unrelated Elementor widgets must remain unchanged.'
);

$test_filter_values = [
	'wpml_current_language' => 'nl',
	'wpml_default_language' => 'en',
];

$wpdb = new class() {
	public $prefix = 'wp_';
	public $terms = 'wp_terms';
	public $term_taxonomy = 'wp_term_taxonomy';
	public $queries = 0;

	public function prepare( $query, ...$args ) {
		return [
			'query' => $query,
			'args'  => $args,
		];
	}

	public function get_var( $prepared ) {
		++$this->queries;

		$slug = end( $prepared['args'] );
		$map  = [
			'general' => 'nl-general',
			'test'    => 'nl-test',
			'tokyo'   => 'nl-tokyo',
			'london'  => 'nl-london',
		];

		return isset( $map[ $slug ] ) ? $map[ $slug ] : null;
	}
};

$taxonomy_widget = new class() {
	private $settings = [
		'type'     => [ 'general' ],
		'cat'      => [ 'test' ],
		'location' => 'tokyo,london',
		'tag'      => [ 10 ],
	];

	public function get_name() {
		return 'directorist_location';
	}

	public function get_settings() {
		return $this->settings;
	}

	public function set_settings( $key, $value ) {
		$this->settings[ $key ] = $value;
	}
};

$translator->translate_elementor_widget_taxonomy_settings( $taxonomy_widget );

assert_same(
	[
		'type'     => [ 'nl-general' ],
		'cat'      => [ 'nl-test' ],
		'location' => 'nl-tokyo,nl-london',
		'tag'      => [ 10 ],
	],
	$taxonomy_widget->get_settings(),
	'Slug-based Directorist Elementor taxonomy selectors must use their linked target-language slugs while numeric IDs remain for WPML core conversion.'
);
assert_same( 4, $wpdb->queries, 'Elementor taxonomy slug translation must perform only one lookup for each unique selected slug.' );

$current_page_map->setValue( $translator, null );

assert_same(
	'Ga naar volgende',
	$decode_wpml_translation_field->invoke( $translator, base64_encode( gzcompress( 'Ga naar volgende' ) ), 'base64' ),
	'Completed WPML custom-field data must decode before page meta sync.'
);

assert_same(
	true,
	$is_usable_page_ui_translation->invoke( $translator, 'Go to Next', 'Ga naar volgende', 'nl' ),
	'A real translated ATE value must be usable for page UI meta sync.'
);

assert_same(
	false,
	$is_usable_page_ui_translation->invoke( $translator, 'Go to Next', 'NL: Go to Next', 'nl' ),
	'Language-prefixed QA placeholders must not be imported as real translations.'
);

assert_same(
	true,
	$should_replace_page_ui_translation->invoke( $translator, 'Go to Next', 'NL: Go to Next', 'nl' ),
	'Stale target-language placeholders in translated page meta may be repaired.'
);

assert_same(
	true,
	$should_replace_page_ui_translation->invoke( $translator, 'Filters', 'NL: Filters', 'nl' ),
	'Stale placeholders may be repaired even when the correct translation intentionally matches the source.'
);

assert_same(
	false,
	$should_replace_page_ui_translation->invoke( $translator, 'Go to Next', 'Ga naar volgende', 'nl' ),
	'Existing valid translated page meta must not be overwritten.'
);

$completed_fields = [
	'field-_directorist_wpml_page_ui_strings-0-directorist_page_ui_add_listing_add_listing_publish_title' => [
		'field_type' => 'field-_directorist_wpml_page_ui_strings-0-directorist_page_ui_add_listing_add_listing_publish_title',
		'data'       => 'Je staat op het punt om te publiceren',
	],
	'field-_directorist_wpml_page_ui_strings-0-directorist_page_ui_add_listing_add_listing_publish_title-name' => [
		'field_type' => 'field-_directorist_wpml_page_ui_strings-0-directorist_page_ui_add_listing_add_listing_publish_title-name',
		'data'       => '_directorist_wpml_page_ui_strings-0-directorist_page_ui_add_listing_add_listing_publish_title',
	],
	'unrelated' => [
		'field_type' => 'post_title',
		'data'       => 'Vermelding toevoegen',
	],
];

assert_same(
	[
		'directorist_page_ui_add_listing_add_listing_publish_title' => 'Je staat op het punt om te publiceren',
	],
	$get_page_ui_translations_from_completed_fields->invoke( $translator, $completed_fields ),
	'WPML completed translation hook payload must map page UI custom-field values by meta subkey.'
);

$archive_strings = [];
$collect_listing_archive_strings->invokeArgs( $translator, [ &$archive_strings, 'all_listing', 0, false ] );

assert_same(
	true,
	in_array( 'Filter', $archive_strings, true ),
	'All Listings page UI must expose the singular archive sidebar filter button through ATE.'
);

assert_same(
	true,
	in_array( 'Sidebar Filter Toggle Button', $archive_strings, true ),
	'All Listings page UI must expose the filter toggle accessibility label through ATE.'
);

assert_same(
	true,
	in_array( 'Sidebar Filter Close Button', $archive_strings, true ),
	'All Listings page UI must expose the filter close accessibility label through ATE.'
);

foreach ( [ 'Add to Favorite Button', 'grid view', 'list view', 'map view', 'Listings Pagination' ] as $archive_accessibility_string ) {
	assert_same(
		true,
		in_array( $archive_accessibility_string, $archive_strings, true ),
		'Listing card, view, and pagination accessibility text must be exposed through page ATE.'
	);
}

$card_builder_strings = [];
$extract_builder_strings->invokeArgs(
	$translator,
	[
		&$card_builder_strings,
		'all_listing',
		'listings_card_grid_view',
		[
			'selectedWidgets' => [
				[
					'label'   => 'Category',
					'options' => [
						'label'     => 'Category Settings',
						'showLabel' => 'Show Label',
						'icon'      => 'Icon',
					],
				],
			],
		],
	]
);

assert_same(
	true,
	in_array( 'Category', $card_builder_strings, true ),
	'The visible listing-card widget label must remain in page ATE.'
);

foreach ( [ 'Category Settings', 'Show Label', 'Icon' ] as $card_internal_string ) {
	assert_same(
		false,
		in_array( $card_internal_string, $card_builder_strings, true ),
		'Listing-card option-panel configuration must not pollute page ATE.'
	);
}

$search_result_strings = [];
$collect_listing_archive_strings->invokeArgs( $translator, [ &$search_result_strings, 'search_result', 0, true ] );

assert_same(
	true,
	in_array( 'Items Found', $search_result_strings, true ),
	'Search Result page UI must expose its result-count title through ATE.'
);

$all_categories_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$all_categories_strings, 'all_categories', [] ] );

assert_same(
	true,
	in_array( 'No Results found!', $all_categories_strings, true ),
	'All Categories page UI must expose Directorist\'s native empty-state text through ATE.'
);

$all_locations_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$all_locations_strings, 'all_locations', [] ] );

assert_same(
	true,
	in_array( 'No Results found!', $all_locations_strings, true ),
	'All Locations page UI must expose Directorist\'s native empty-state text through ATE.'
);

$account_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$account_strings, 'signin_signup', [] ] );

foreach ( [ 'Username or Email Address', 'Remember Me', 'Sign Up', 'Lost your password? Please enter your email address. You will receive a link to create a new password via email.', 'Get New Password' ] as $account_string ) {
	assert_same(
		true,
		in_array( $account_string, $account_strings, true ),
		'Sign In page ATE must expose native login, registration, and password-recovery strings.'
	);
}

$custom_account_strings = [];
$collect_shortcode_strings->invokeArgs(
	$translator,
	[
		&$custom_account_strings,
		'signin_signup',
		[
			'signin_username_label' => 'Member ID',
		],
	]
);

assert_same(
	true,
	in_array( 'Member ID', $custom_account_strings, true ),
	'Sign In page ATE must use a shortcode or block label override when one is configured.'
);

$account_button_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$account_button_strings, 'account_button', [] ] );

foreach ( [ 'Account', 'Close', 'Add Listing', 'Log Out', 'Username or Email Address', 'My Listing' ] as $account_button_string ) {
	assert_same(
		true,
		in_array( $account_button_string, $account_button_strings, true ),
		'Account Button block ATE must cover logged-out and logged-in native states.'
	);
}

$elementor_widget_names = [
	'directorist_add_listing',
	'directorist_all_authors',
	'directorist_all_categories',
	'directorist_all_listing',
	'directorist_all_locations',
	'directorist_author_profile',
	'directorist_checkout',
	'directorist_payment_receipt',
	'directorist_search_listing',
	'directorist_search_result',
	'directorist_category',
	'directorist_location',
	'directorist_tag',
	'directorist_transaction_failure',
	'directorist_user_dashboard',
	'directorist_user_login',
];

$elementor_elements = [];
foreach ( $elementor_widget_names as $index => $widget_name ) {
	$elementor_elements[] = [
		'id'         => 'widget-' . $index,
		'elType'     => 'widget',
		'widgetType' => $widget_name,
		'settings'   => [
			'type'         => [ 115 ],
			'default_type' => 115,
			'header_title' => 'Elementor visible setting',
		],
	];
}

$elementor_data = [
	[
		'id'       => 'container',
		'elType'   => 'container',
		'elements' => $elementor_elements,
	],
];

$elementor_widgets = $extract_elementor_widgets->invoke( $translator, json_encode( $elementor_data ) );

assert_same(
	16,
	count( $elementor_widgets ),
	'Every installed general Directorist Elementor widget must resolve to its native page ATE context.'
);

assert_same(
	[
		'default_type' => 115,
		'type'         => [ 115 ],
	],
	$elementor_widgets[0]['attrs'],
	'Elementor collection must retain only directory selectors and avoid duplicating visible widget settings in page ATE.'
);

$elementor_page_data = $elementor_data;
foreach ( $elementor_page_data[0]['elements'] as &$elementor_page_widget ) {
	$elementor_page_widget['settings'] = [];
}
unset( $elementor_page_widget );

$elementor_page_strings = $collect_page_ui_strings->invoke( $translator, '', $elementor_page_data );

foreach ( [ 'Finish', 'No authors found', 'No Results found!', 'Items Found', 'Contact Info', 'Order Summary', 'Thank you for your order!', 'Search here', 'Your Transaction was not successful. Please contact support', 'My Listing', 'Username or Email Address' ] as $elementor_native_string ) {
	assert_same(
		true,
		in_array( $elementor_native_string, $elementor_page_strings, true ),
		'Elementor page ATE must include native UI for every detected Directorist widget context.'
	);
}

assert_same(
	false,
	in_array( 'Elementor visible setting', $elementor_page_strings, true ),
	'Elementor settings owned by wpml-config.xml must not be duplicated in the hidden page UI field.'
);

$dashboard_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$dashboard_strings, 'user_dashboard', [] ] );

foreach ( [ 'My Listing', 'Favorite Listings', 'Preferences', 'Rejected', 'Status', 'Save Changes' ] as $dashboard_string ) {
	assert_same(
		true,
		in_array( $dashboard_string, $dashboard_strings, true ),
		'Dashboard page ATE must expose native tab, status, and profile UI strings.'
	);
}

$author_profile_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$author_profile_strings, 'author_profile', [] ] );

foreach ( [ 'Contact Info', 'Author Listings', 'Member since %s ago', '%s Reviews', 'Listings' ] as $author_profile_string ) {
	assert_same(
		true,
		in_array( $author_profile_string, $author_profile_strings, true ),
		'Author Profile page ATE must expose native author labels and dynamic count formats.'
	);
}

$all_authors_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$all_authors_strings, 'all_authors', [] ] );

foreach ( [ 'View All Listings', 'All', 'No authors found' ] as $all_authors_string ) {
	assert_same(
		true,
		in_array( $all_authors_string, $all_authors_strings, true ),
		'All Authors page ATE must expose its native filter, button, and empty-state labels.'
	);
}

$checkout_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$checkout_strings, 'checkout', [] ] );

foreach ( [ 'Order Summary', 'Choose a payment method', 'Pay Now', 'Processing...', 'Monetization is not active on this site. if you are an admin, you can enable it from the settings panel.' ] as $checkout_string ) {
	assert_same(
		true,
		in_array( $checkout_string, $checkout_strings, true ),
		'Checkout page ATE must expose native checkout UI and reachable error states.'
	);
}

$receipt_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$receipt_strings, 'payment_receipt', [] ] );

foreach ( [ 'Thank you for your order!', 'Payment Instruction', 'Payment Status', 'Discount ( %d%% )', 'Retry Payment', 'Sorry! No order id has been provided.' ] as $receipt_string ) {
	assert_same(
		true,
		in_array( $receipt_string, $receipt_strings, true ),
		'Payment Receipt page ATE must expose native receipt labels, dynamic formats, actions, and errors.'
	);
}

$failure_strings = [];
$collect_shortcode_strings->invokeArgs( $translator, [ &$failure_strings, 'transaction_failure', [] ] );

assert_same(
	true,
	in_array( 'Your Transaction was not successful. Please contact support', $failure_strings, true ),
	'Transaction Failure page ATE must expose the native failure message.'
);

$search_form_fields = [
	'fields' => [
		'pricing' => [
			'label' => '',
		],
	],
];

$search_form_fields = $apply_search_form_field_fallbacks->invoke( $translator, 'search_form_fields', $search_form_fields );

assert_same(
	'Pricing',
	$search_form_fields['fields']['pricing']['label'],
	'Search page ATE inventory must mirror Directorist\'s native Pricing fallback when the saved label is empty.'
);

$search_form_fields['fields']['pricing']['label'] = 'Custom pricing label';
$search_form_fields = $apply_search_form_field_fallbacks->invoke( $translator, 'search_form_fields', $search_form_fields );

assert_same(
	'Custom pricing label',
	$search_form_fields['fields']['pricing']['label'],
	'An explicit pricing label must remain unchanged.'
);

$pricing_map = [
	'Pricing' => 'Prijzen',
];

assert_same(
	[
		'Pricing'      => 'Prijzen',
		'Prijsstelling' => 'Prijzen',
	],
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $pricing_map, 'all_listing' ),
	'All Listings must let its exact page ATE value override Directorist\'s already-localized Pricing fallback.'
);

assert_same(
	[
		'Pricing'       => 'Prijzen',
		'Prijsstelling' => 'Prijzen',
	],
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $pricing_map, 'search_result' ),
	'Search Result must let its exact page ATE value override Directorist\'s already-localized Pricing fallback.'
);

assert_same(
	[
		'Pricing'       => 'Prijzen',
		'Prijsstelling' => 'Prijzen',
	],
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $pricing_map, 'category' ),
	'Category archives must let their exact page ATE value override Directorist\'s already-localized Pricing fallback.'
);

assert_same(
	$pricing_map,
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $pricing_map, 'add_listing' ),
	'Native pricing aliases must not affect shortcode contexts without the Directorist search form.'
);

$view_map = [
	'grid view' => 'rasterweergave',
	'list view' => 'lijstweergave',
	'map view'  => 'kaartweergave',
];

assert_same(
	[
		'grid view'   => 'rasterweergave',
		'list view'   => 'lijstweergave',
		'map view'    => 'kaartweergave',
		'raster view' => 'rasterweergave',
		'lijst view'  => 'lijstweergave',
		'kaart view'  => 'kaartweergave',
	],
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $view_map, 'all_listing' ),
	'Listing view accessibility labels must use their exact ATE values after Directorist gettext runs.'
);

$account_button_map = [
	'Log Out' => 'Uitloggen',
];

assert_same(
	[
		'Log Out'  => 'Uitloggen',
		'Afmelden' => 'Uitloggen',
	],
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $account_button_map, 'account_button' ),
	'Account Button native gettext output must use its page ATE value.'
);

$empty_state_map = [
	'No Results found!' => 'Geen resultaten gevonden!',
];

assert_same(
	[
		'No Results found!'        => 'Geen resultaten gevonden!',
		'Geen resultaten gevonden!' => 'Geen resultaten gevonden!',
	],
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $empty_state_map, 'all_categories' ),
	'All Categories must let its exact page ATE value override Directorist\'s already-localized empty state.'
);

assert_same(
	$empty_state_map,
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $empty_state_map, 'category' ),
	'Taxonomy-index empty-state aliases must not affect listing archive contexts.'
);

$dashboard_status_map = [
	'Status' => 'Status',
];

assert_same(
	'Status',
	$build_map->invoke( $translator, [ 'status' => 'Status' ], [ 'status' => 'Status' ], 'nl' )['Status'],
	'A legitimate translation that matches its source must remain available for a runtime gettext alias.'
);

assert_same(
	[
		'Status'        => 'Status',
		'NL-QA: Status' => 'Status',
	],
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $dashboard_status_map, 'user_dashboard' ),
	'Dashboard page ATE must override a runtime-localized native label with its exact page value.'
);

assert_same(
	$dashboard_status_map,
	$apply_shortcode_native_fallback_aliases->invoke( $translator, $dashboard_status_map, 'all_listing' ),
	'Account and dashboard gettext aliases must stay scoped to their own shortcode contexts.'
);

$dashboard_count_map = [
	'My Listing' => 'Mijn vermelding',
];

assert_same(
	[
		'My Listing'     => 'Mijn vermelding',
		'My Listing (2)' => 'Mijn vermelding (2)',
	],
	$apply_shortcode_dynamic_output_aliases->invoke(
		$translator,
		$dashboard_count_map,
		'user_dashboard',
		'<a>My Listing (2)</a>'
	),
	'Dashboard page ATE must translate the tab label while preserving its dynamic listing count.'
);

assert_same(
	$dashboard_count_map,
	$apply_shortcode_dynamic_output_aliases->invoke(
		$translator,
		$dashboard_count_map,
		'all_listing',
		'<a>My Listing (2)</a>'
	),
	'Dashboard count aliases must not affect unrelated shortcode contexts.'
);

$author_dynamic_map = [
	'Member since %s ago' => 'Lid sinds %s geleden',
	'%s Reviews'          => '%s Beoordelingen',
];

assert_same(
	'Lid sinds 2 maanden geleden',
	$apply_shortcode_dynamic_output_aliases->invoke(
		$translator,
		$author_dynamic_map,
		'author_profile',
		'<p>Member since 2 maanden ago</p><span>5 Reviews</span>'
	)['Member since 2 maanden ago'],
	'Author Profile must translate its member-since format while preserving the runtime duration.'
);

assert_same(
	'5 Beoordelingen',
	$apply_shortcode_dynamic_output_aliases->invoke(
		$translator,
		$author_dynamic_map,
		'author_profile',
		'<p>Member since 2 maanden ago</p><span>5 Reviews</span>'
	)['5 Reviews'],
	'Author Profile must translate its review format while preserving the runtime count.'
);

$receipt_dynamic_map = [
	'Discount ( %d%% )' => 'Korting ( %d%% )',
	'Tax ( %d%% )'      => 'Belasting ( %d%% )',
];

assert_same(
	'Korting ( 15% )',
	$apply_shortcode_dynamic_output_aliases->invoke(
		$translator,
		$receipt_dynamic_map,
		'payment_receipt',
		'<td>Discount ( 15% )</td><td>Tax ( 7% )</td>'
	)['Discount ( 15% )'],
	'Payment Receipt must translate a percent label while preserving its runtime rate.'
);

assert_same(
	'<span class="directorist-listing-count"><span>2</span> Vermeldingen</span><p>Vermelding</p>',
	$replace_shortcode_dynamic_fragments->invoke(
		$translator,
		'<span class="directorist-listing-count"><span>2</span> Vermelding</span><p>Vermelding</p>',
		[
			'Listing'  => 'Vermelding',
			'Listings' => 'Vermeldingen',
		],
		'author_profile'
	),
	'Author Profile must select the ATE plural label from the runtime count without changing identical text elsewhere.'
);

$bank_instruction_source = 'Pay #==ORDER_ID== now.';
$test_options            = [
	'atbdp_option' => [
		'bank_transfer_instruction' => $bank_instruction_source,
	],
];

assert_same(
	'<td>Betaal #42 nu.</td>',
	$replace_shortcode_dynamic_fragments->invoke(
		$translator,
		'<td>Pay #42 now.</td>',
		[
			$bank_instruction_source => 'Betaal #==ORDER_ID== nu.',
		],
		'payment_receipt'
	),
	'Payment Receipt must translate its bank instruction while preserving the runtime order ID.'
);

echo "Page shortcode UI translation regression tests passed.\n";
