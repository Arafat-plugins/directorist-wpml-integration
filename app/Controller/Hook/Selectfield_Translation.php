<?php
/**
 * Select2 JS strings translation
 *
 * Directorist uses the Select2 JS library for Category/Location dropdowns.
 * Select2's UI strings (No results found, Searching…, etc.) are hardcoded in
 * the vendor JS, so this class reads their translated values from the current
 * page ATE map and injects them into Select2's default language.
 *
 * For other JS-originated strings: Directorist passes most UI text via
 * wp_localize_script (the "directorist" object) from PHP, so those are already
 * translatable via WPML (domain "directorist"). Only vendor/third-party
 * strings that never go through PHP need to be handled here or in similar hooks.
 *
 * @package Directorist_WPML_Integration
 */

namespace Directorist_WPML_Integration\Controller\Hook;

class Selectfield_Translation {

	/**
	 * Select2 language keys and their default (English) values.
	 * Keys match Select2's i18n API (noResults, searching, etc.).
	 *
	 * @var array<string, string>
	 */
	private static $strings = [
		'no_results'        => 'No results found',
		'searching'         => 'Searching…',
		'loading_more'      => 'Loading more results…',
		'input_too_short'   => 'Please enter {count} or more characters',
		'input_too_long'    => 'Please delete {count} character',
		'input_too_long_pl' => 'Please delete {count} characters',
		'maximum_selected'  => 'You can only select {count} item',
		'maximum_selected_pl' => 'You can only select {count} items',
		'error_loading'     => 'The results could not be loaded.',
		'remove_all_items'  => 'Remove all items',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'inject_select2_language' ], 20 );
	}

	/**
	 * Kept as a no-op for backward compatibility with older integrations.
	 *
	 * Select2 strings are now collected into page ATE jobs by
	 * Page_Shortcode_UI_Translation.
	 */
	public function register_strings() {
	}

	/**
	 * Translate a single string via WPML
	 *
	 * @param string $key    Key in self::$strings
	 * @param string $default Default text
	 * @return string
	 */
	private function translate( $key, $default ) {
		$out = apply_filters( 'directorist_wpml_translate_page_ui_string', $default, $default, 'select2' );
		$language_code = apply_filters( 'wpml_current_language', null );

		if ( ! is_string( $out ) || '' === trim( $out ) || $this->is_language_prefixed_placeholder( $out, $language_code ) ) {
			return $default;
		}

		return $out;
	}

	/**
	 * Reject QA placeholders as real translations.
	 *
	 * @param mixed  $value         Candidate translation.
	 * @param string $language_code Current language code.
	 * @return bool
	 */
	private function is_language_prefixed_placeholder( $value, $language_code = '' ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value         = trim( $value );
		$language_code = strtoupper( (string) $language_code );

		if ( '' !== $language_code && preg_match( '/^' . preg_quote( $language_code, '/' ) . '(?:-QA)?:\s*/', $value ) ) {
			return true;
		}

		return (bool) preg_match( '/^[A-Z]{2,5}(?:-QA)?:\s*/', $value );
	}

	/**
	 * Output translated strings into Select2 defaults so Category/Location dropdowns are translated
	 */
	public function inject_select2_language() {
		if ( ! $this->is_wpml_active() ) {
			return;
		}

		$no_results   = $this->translate( 'no_results', self::$strings['no_results'] );
		$searching    = $this->translate( 'searching', self::$strings['searching'] );
		$loading_more = $this->translate( 'loading_more', self::$strings['loading_more'] );
		$input_short  = $this->translate( 'input_too_short', self::$strings['input_too_short'] );
		$input_long   = $this->translate( 'input_too_long', self::$strings['input_too_long'] );
		$input_long_pl = $this->translate( 'input_too_long_pl', self::$strings['input_too_long_pl'] );
		$max_sel      = $this->translate( 'maximum_selected', self::$strings['maximum_selected'] );
		$max_sel_pl   = $this->translate( 'maximum_selected_pl', self::$strings['maximum_selected_pl'] );
		$error_load   = $this->translate( 'error_loading', self::$strings['error_loading'] );
		$remove_all   = $this->translate( 'remove_all_items', self::$strings['remove_all_items'] );
		$search_label = $this->translate( 'search', 'Search' );

		$js = sprintf(
			"jQuery(function(){if(!jQuery.fn.select2)return;var d=jQuery.fn.select2.defaults.defaults;d.language=d.language||{};var L=d.language;var S=%s;L.search=function(){return S;};L.noResults=function(){return %s;};L.searching=function(){return %s;};L.loadingMore=function(){return %s;};L.inputTooShort=function(e){var n=(e.minimum-(e.input||'').length);return (%s).replace(/{count}/g,n);};L.inputTooLong=function(e){var n=(e.input||'').length-e.maximum;return (n===1?%s:%s).replace(/{count}/g,n);};L.maximumSelected=function(e){return (e.maximum===1?%s:%s).replace(/{count}/g,e.maximum);};L.errorLoading=function(){return %s;};L.removeAllItems=function(){return %s;};jQuery(document).on('select2:open',function(){jQuery('.select2-container--open .select2-search__field').attr('aria-label',S);});});",
			wp_json_encode( $search_label ),
			wp_json_encode( $no_results ),
			wp_json_encode( $searching ),
			wp_json_encode( $loading_more ),
			wp_json_encode( $input_short ),
			wp_json_encode( $input_long ),
			wp_json_encode( $input_long_pl ),
			wp_json_encode( $max_sel ),
			wp_json_encode( $max_sel_pl ),
			wp_json_encode( $error_load ),
			wp_json_encode( $remove_all )
		);

		wp_add_inline_script( 'directorist-select2-script', $js, 'after' );
	}

	/**
	 * Check if WPML is active
	 *
	 * @return bool
	 */
	private function is_wpml_active() {
		return defined( 'ICL_SITEPRESS_VERSION' )
			&& function_exists( 'do_action' )
			&& function_exists( 'apply_filters' );
	}
}
