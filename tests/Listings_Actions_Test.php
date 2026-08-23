<?php

namespace {
	define( 'ATBDP_POST_TYPE', 'at_biz_dir' );
	define( 'ATBDP_DIRECTORY_TYPE', 'atbdp_listing_types' );

	$GLOBALS['listing_meta'] = [
		127 => 115,
		154 => 115,
		184 => 130,
		185 => 130,
		198 => 0,
		200 => 0,
	];
	$GLOBALS['listing_terms'] = [
		127 => [ 115 ],
		154 => [ 115 ],
		184 => [ 130 ],
		185 => [ 130 ],
		198 => [],
		200 => [],
	];

	function get_post_type( $post_id ) {
		return isset( $GLOBALS['listing_meta'][ $post_id ] ) ? ATBDP_POST_TYPE : '';
	}

	function get_post_meta( $post_id, $key, $single = false ) {
		return isset( $GLOBALS['listing_meta'][ $post_id ] ) ? $GLOBALS['listing_meta'][ $post_id ] : '';
	}

	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['listing_meta'][ $post_id ] = (int) $value;
	}

	function wp_get_object_terms( $post_id, $taxonomy, $args = [] ) {
		return isset( $GLOBALS['listing_terms'][ $post_id ] ) ? $GLOBALS['listing_terms'][ $post_id ] : [];
	}

	function wp_set_object_terms( $post_id, $terms, $taxonomy, $append = false ) {
		$GLOBALS['listing_terms'][ $post_id ] = array_map( 'intval', $terms );
	}

	function is_wp_error( $value ) {
		return false;
	}

	function term_exists( $term_id, $taxonomy = '' ) {
		return in_array( (int) $term_id, [ 115, 130, 135 ], true ) ? (int) $term_id : 0;
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	function sanitize_title( $value ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
	}

	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}

	function get_bloginfo( $key ) {
		return 'UTF-8';
	}

	function add_action() {
		return true;
	}

	function add_filter() {
		return true;
	}
}

namespace Directorist_WPML_Integration\Helper {
	class WPML_Helper {
		public static function get_element_translations( $element_id, $element_type ) {
			if ( ATBDP_DIRECTORY_TYPE === $element_type && in_array( (int) $element_id, [ 115, 130, 135 ], true ) ) {
				return [
					'en' => (object) [ 'term_id' => 115, 'element_id' => 115, 'original' => true ],
					'fr' => (object) [ 'term_id' => 130, 'element_id' => 130 ],
					'de' => (object) [ 'term_id' => 135, 'element_id' => 135 ],
				];
			}

			$families = [
				127 => [ 'en' => 127, 'fr' => 184, 'de' => 200 ],
				154 => [ 'en' => 154, 'fr' => 185, 'de' => 198 ],
			];

			foreach ( $families as $translations ) {
				if ( in_array( (int) $element_id, $translations, true ) ) {
					$result = [];
					foreach ( $translations as $language => $post_id ) {
						$result[ $language ] = (object) [
							'element_id' => $post_id,
							'original'   => 'en' === $language,
						];
					}

					return $result;
				}
			}

			return [];
		}

		public static function get_language_info( $element_id, $element_type ) {
			$languages = [
				115 => 'en', 130 => 'fr', 135 => 'de',
				127 => 'en', 154 => 'en', 184 => 'fr', 185 => 'fr', 198 => 'de', 200 => 'de',
			];

			return isset( $languages[ $element_id ] ) ? (object) [ 'language_code' => $languages[ $element_id ] ] : false;
		}
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/app/Controller/Hook/Directory_Builder_String_Package.php';
	require_once dirname( __DIR__ ) . '/app/Controller/Hook/Listings_Actions.php';

	use Directorist_WPML_Integration\Controller\Hook\Directory_Builder_String_Package;
	use Directorist_WPML_Integration\Controller\Hook\Listings_Actions;

	function assert_listing_directory( $listing_id, $directory_id, $message ) {
		$meta  = isset( $GLOBALS['listing_meta'][ $listing_id ] ) ? $GLOBALS['listing_meta'][ $listing_id ] : 0;
		$terms = isset( $GLOBALS['listing_terms'][ $listing_id ] ) ? $GLOBALS['listing_terms'][ $listing_id ] : [];

		if ( $directory_id === $meta && [ $directory_id ] === $terms ) {
			return;
		}

		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}

	function assert_same( $expected, $actual, $message ) {
		if ( $expected === $actual ) {
			return;
		}

		fwrite( STDERR, $message . PHP_EOL );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
		fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}

	$reflection = new ReflectionClass( Listings_Actions::class );
	$listings   = $reflection->newInstanceWithoutConstructor();
	new Directory_Builder_String_Package();

	foreach ( [ [ 198, 154 ], [ 200, 127 ] ] as $fixture ) {
		$job = (object) [
			'original_doc_id' => $fixture[1],
			'language_code'   => 'de',
		];

		$listings->update_directory_type_after_listing_translation( $fixture[0], [], $job );
		$listings->update_directory_type_after_listing_translation( $fixture[0], [], $job );
	}

	assert_listing_directory( 127, 115, 'English listing 127 must remain mapped to directory 115.' );
	assert_listing_directory( 154, 115, 'English listing 154 must remain mapped to directory 115.' );
	assert_listing_directory( 184, 130, 'French listing 184 must remain mapped to directory 130.' );
	assert_listing_directory( 185, 130, 'French listing 185 must remain mapped to directory 130.' );
	assert_listing_directory( 198, 135, 'German listing 198 must map both meta and taxonomy relationship to directory 135.' );
	assert_listing_directory( 200, 135, 'German listing 200 must map both meta and taxonomy relationship to directory 135.' );

	$collect_listing_ui_strings = $reflection->getMethod( 'collect_single_listing_ui_strings' );
	$collect_listing_ui_strings->setAccessible( true );
	$apply_listing_ui_strings = $reflection->getMethod( 'apply_single_listing_ui_strings' );
	$apply_listing_ui_strings->setAccessible( true );

	$header = [
		[
			'placeholderKey' => 'header-group',
			'placeholders' => [
				[
					'placeholderKey' => 'actions',
					'selectedWidgets' => [
						[ 'widget_name' => 'existing_widget', 'widget_key' => 'existing-widget', 'label' => 'Existing Action' ],
						[ 'widget_name' => 'future_widget', 'widget_key' => 'future-widget', 'label' => 'Future Action' ],
					],
				],
			],
		],
	];
	$contents = [
		'fields' => [
			'custom-content' => [
				'widget_name' => 'custom_content',
				'widget_key'  => 'custom-content',
				'label'       => 'Custom Content Label',
				'content'     => 'Custom content body',
			],
		],
		'groups' => [
			[ 'id' => 'existing-section', 'label' => 'Existing Section' ],
			[ 'id' => 'future-section', 'label' => 'Future Section' ],
		],
	];
	$form = [
		'fields' => [
			'title' => [
				'field_key'   => 'listing_title',
				'label'       => 'Listing Title',
				'placeholder' => 'Enter a title',
				'widget_name' => 'title',
			],
			'choice' => [
				'field_key'   => 'custom-choice',
				'label'       => 'Dynamic Choice',
				'field_key_2' => 'technical-key',
				'options'     => [
					[ 'option_value' => 'first', 'option_label' => 'First Option' ],
					[ 'option_value' => 'second', 'option_label' => 'Second Option' ],
				],
			],
		],
		'groups' => [
			[ 'id' => 'main', 'label' => 'Main Section' ],
		],
	];

	$source_ui_strings = $collect_listing_ui_strings->invoke( $listings, $header, $contents, $form );
	$expected_values   = [
		'Existing Action',
		'Future Action',
		'Custom Content Label',
		'Custom content body',
		'Existing Section',
		'Future Section',
		'Listing Title',
		'Enter a title',
		'Dynamic Choice',
		'First Option',
		'Second Option',
		'Main Section',
	];

	foreach ( $expected_values as $expected_value ) {
		assert_same( true, in_array( $expected_value, $source_ui_strings, true ), 'Every active or future layout string must enter the listing ATE inventory dynamically.' );
	}

	$translated_ui_strings = [];
	foreach ( $source_ui_strings as $key => $value ) {
		$translated_ui_strings[ $key ] = 'Translated: ' . $value;
	}

	$translated_layouts = $apply_listing_ui_strings->invoke( $listings, $header, $contents, $form, $translated_ui_strings, 'xx' );
	$translated_strings = $collect_listing_ui_strings->invoke(
		$listings,
		$translated_layouts['header'],
		$translated_layouts['contents'],
		$translated_layouts['form']
	);

	foreach ( $translated_ui_strings as $key => $value ) {
		assert_same( $value, $translated_strings[ $key ], 'Every collected listing UI string must accept any target-language ATE value.' );
	}

	assert_same( 'future_widget', $translated_layouts['header'][0]['placeholders'][0]['selectedWidgets'][1]['widget_name'], 'Widget identities must never be translated.' );
	assert_same( 'custom-choice', $translated_layouts['form']['fields']['choice']['field_key'], 'Field keys must never be translated.' );
	assert_same( 'first', $translated_layouts['form']['fields']['choice']['options'][0]['option_value'], 'Option values must never be translated.' );

	echo "Listing directory translation relationship tests passed.\n";
}
