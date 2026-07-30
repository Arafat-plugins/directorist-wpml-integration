<?php
/**
 * Directorist settings and email-template ATE packages.
 *
 * @package Directorist_WPML_Integration
 */

namespace Directorist_WPML_Integration\Controller\Hook;

class Admin_Text_Translation {

	const SETTINGS_KIND      = 'Directorist Settings';
	const SETTINGS_KIND_SLUG = 'directorist-settings';
	const SETTINGS_NAME      = 'directorist_settings';

	const EMAIL_KIND      = 'Directorist Email Templates';
	const EMAIL_KIND_SLUG = 'directorist-email-templates';
	const EMAIL_NAME      = 'directorist_email_templates';

	const SETTINGS_UI_KIND      = 'Directorist Settings UI';
	const SETTINGS_UI_KIND_SLUG = 'directorist-settings-ui';
	const SETTINGS_UI_NAME      = 'directorist_settings_ui';

	const ADMIN_TEXT_CONTEXT = 'admin_texts_atbdp_option';

	/**
	 * Packages registered in the current request.
	 *
	 * @var array
	 */
	private static $registered_packages = [];

	/**
	 * Existing strings, cached by package.
	 *
	 * @var array
	 */
	private static $package_string_cache = [];

	/**
	 * Current package string names.
	 *
	 * @var array
	 */
	private static $current_package_strings = [];

	/**
	 * Unfiltered source options plus Directorist defaults.
	 *
	 * @var array|null
	 */
	private static $source_options = null;

	/**
	 * Completed package translations cached by target language.
	 *
	 * @var array
	 */
	private static $runtime_translation_cache = [];

	/**
	 * Settings UI source-to-target map cached by language.
	 *
	 * @var array
	 */
	private static $settings_ui_runtime_cache = [];

	/**
	 * Small admin-menu/admin-notice map cached by language.
	 *
	 * @var array
	 */
	private static $admin_menu_runtime_cache = [];

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'wpml_active_string_package_kinds', [ $this, 'register_package_kinds' ] );
		add_action( 'wpml_register_string_packages', [ $this, 'register_packages' ], 30 );
		add_action( 'wpml_register_string_packages', [ $this, 'cleanup_unused_package_strings' ], 110 );
		add_filter( 'wpml_get_translatable_item', [ $this, 'prepare_package_for_translation_management' ], 20, 3 );
		add_action( 'wpml_save_external', [ $this, 'sync_admin_texts_after_package_translation' ], 20, 3 );
		add_action( 'admin_init', [ $this, 'sync_completed_package_translations' ], 130 );
		add_action( 'updated_option', [ $this, 'refresh_packages_after_settings_save' ], 30, 3 );
		add_filter( 'directorist_option', [ $this, 'translate_directorist_option' ], 30, 2 );
		add_filter( 'option_atbdp_option', [ $this, 'translate_settings_screen_options' ], 30 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_menu_translation_bridge' ], 90 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_ui_translation_bridge' ], 100 );
	}

	/**
	 * Register both package kinds with Translation Management.
	 *
	 * @param array $kinds Package kind definitions.
	 * @return array
	 */
	public function register_package_kinds( $kinds ) {
		$kinds[ self::SETTINGS_KIND_SLUG ] = [
			'title'  => self::SETTINGS_KIND,
			'slug'   => self::SETTINGS_KIND_SLUG,
			'plural' => 'Directorist Settings',
		];
		$kinds[ self::EMAIL_KIND_SLUG ] = [
			'title'  => self::EMAIL_KIND,
			'slug'   => self::EMAIL_KIND_SLUG,
			'plural' => 'Directorist Email Templates',
		];
		$kinds[ self::SETTINGS_UI_KIND_SLUG ] = [
			'title'  => self::SETTINGS_UI_KIND,
			'slug'   => self::SETTINGS_UI_KIND_SLUG,
			'plural' => 'Directorist Settings UI',
		];

		return $kinds;
	}

	/**
	 * Register the current native option values as two independent ATE packages.
	 *
	 * @param bool $force Register immediately after the source option is saved.
	 */
	public function register_packages( $force = false ) {
		if ( ! $this->is_wpml_active() || ( ! $force && ! $this->should_register_packages() ) ) {
			return;
		}

		$strings = $this->get_package_strings();

		foreach ( [ 'settings', 'email', 'ui' ] as $package_type ) {
			$package = $this->get_package( $package_type );

			if ( isset( self::$registered_packages[ $package['kind_slug'] ] ) ) {
				continue;
			}

			self::$current_package_strings[ $package['kind_slug'] ] = [];

			foreach ( $strings[ $package_type ] as $string ) {
				self::$current_package_strings[ $package['kind_slug'] ][ $string['name'] ] = true;
				$this->register_package_string( $package, $string );
			}

			self::$registered_packages[ $package['kind_slug'] ] = true;
		}
	}

	/**
	 * Refresh package strings after Directorist saves its option array.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 */
	public function refresh_packages_after_settings_save( $option, $old_value, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( 'atbdp_option' !== $option || ! is_admin() || ! $this->is_wpml_active() ) {
			return;
		}

		self::$registered_packages    = [];
		self::$package_string_cache   = [];
		self::$current_package_strings = [];
		self::$source_options         = null;
		self::$settings_ui_runtime_cache = [];

		$this->register_packages( true );
		$this->cleanup_unused_package_strings();
	}

	/**
	 * Mark these package objects as external so their strings reach ATE.
	 *
	 * @param mixed      $item    Translatable item.
	 * @param int|object $package Package identifier.
	 * @param string     $type    Element prefix.
	 * @return mixed
	 */
	public function prepare_package_for_translation_management( $item, $package, $type = 'package' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $this->is_our_package_item( $item ) ) {
			return $item;
		}

		$item->external_type = true;

		if ( method_exists( $item, 'update_strings_data' ) ) {
			$item->update_strings_data();
		}

		return $item;
	}

	/**
	 * Mirror completed ATE package values into WPML's existing admin-text rows.
	 *
	 * Directorist already reads these rows through wpml-config.xml, so this adds
	 * no frontend filter, scan, or database write.
	 *
	 * @param string   $element_type_prefix Element prefix.
	 * @param object   $job                 WPML job.
	 * @param callable $decoder             WPML field decoder.
	 */
	public function sync_admin_texts_after_package_translation( $element_type_prefix, $job, $decoder ) {
		if ( 'package' !== $element_type_prefix || ! is_object( $job ) || empty( $job->language_code ) || empty( $job->elements ) || ! is_array( $job->elements ) ) {
			return;
		}

		$package_context = apply_filters( 'wpml_get_package_type_prefix', $element_type_prefix, $job->original_doc_id );
		$package_type    = $this->get_package_type_from_context( $package_context );

		if ( '' === $package_type ) {
			return;
		}

		if ( 'ui' === $package_type ) {
			return;
		}

		$translations = $this->get_job_translations( $job, $decoder, $package_context );
		if ( empty( $translations ) ) {
			return;
		}

		$this->sync_admin_text_translations( $job->language_code, $translations, $package_type );
	}

	/**
	 * Sync completed package rows during bounded admin/WP-CLI requests.
	 */
	public function sync_completed_package_translations() {
		if ( ! $this->is_wpml_active() || ! $this->should_sync_completed_packages() ) {
			return;
		}

		foreach ( [ 'settings', 'email' ] as $package_type ) {
			foreach ( $this->get_completed_translations_by_language( $this->get_package( $package_type ) ) as $language => $translations ) {
				$this->sync_admin_text_translations( $language, $translations, $package_type );
			}
		}
	}

	/**
	 * Remove stale package fields only while WPML refreshes package content.
	 */
	public function cleanup_unused_package_strings() {
		global $wpdb;

		if ( ! function_exists( 'icl_unregister_string' ) ) {
			return;
		}

		foreach ( [ 'settings', 'email', 'ui' ] as $package_type ) {
			$package       = $this->get_package( $package_type );
			$current_names = isset( self::$current_package_strings[ $package['kind_slug'] ] )
				? self::$current_package_strings[ $package['kind_slug'] ]
				: [];

			if ( empty( $current_names ) ) {
				continue;
			}

			$package_id = $this->get_package_id( $package );
			if ( $package_id <= 0 ) {
				continue;
			}

			$registered_names = (array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT name FROM {$wpdb->prefix}icl_strings WHERE string_package_id = %d",
					$package_id
				)
			);
			$context = $package['kind_slug'] . '-' . $package['name'];

			foreach ( array_diff( $registered_names, array_keys( $current_names ) ) as $unused_name ) {
				icl_unregister_string( $context, $unused_name );
			}
		}
	}

	/**
	 * Translate a configured Directorist text at its native option boundary.
	 *
	 * WPML's generic admin-text filter does not consistently apply nested
	 * atbdp_option translations in frontend, cron, and mailer requests. Load the
	 * two small package maps once per target-language request and reuse them for
	 * every Directorist option read. This path never writes to the database.
	 *
	 * @param mixed  $value Option value.
	 * @param string $key   Directorist option key.
	 * @return mixed
	 */
	public function translate_directorist_option( $value, $key ) {
		if ( ! is_string( $value ) || '' === trim( $value ) || $this->is_extension_option_key( $key ) ) {
			return $value;
		}

		$current_language = $this->get_current_target_language();

		if ( '' === $current_language ) {
			return $value;
		}

		$translations = $this->get_runtime_translations( $current_language );
		if ( empty( $translations[ $key ] ) ) {
			return $value;
		}

		$translation = $translations[ $key ];

		if (
			! $this->strings_match( $value, $translation['source'] )
			|| ! $this->is_usable_translation( $value, $translation['value'], $current_language )
		) {
			return $value;
		}

		return $translation['value'];
	}

	/**
	 * Show package values in Directorist's settings editor.
	 *
	 * The settings Vue payload reads the raw option array instead of calling
	 * get_directorist_option(). Limit this array translation to the non-AJAX
	 * settings display request so a target-language view can never write its
	 * translated values back over the source option during Save.
	 *
	 * @param mixed $options Directorist option array.
	 * @return mixed
	 */
	public function translate_settings_screen_options( $options ) {
		if ( ! is_array( $options ) || ! is_admin() || wp_doing_ajax() ) {
			return $options;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'atbdp-settings' !== $page ) {
			return $options;
		}

		foreach ( $options as $key => $value ) {
			$options[ $key ] = $this->translate_directorist_option( $value, $key );
		}

		return $options;
	}

	/**
	 * Translate Directorist's JavaScript-only settings chrome from its ATE map.
	 *
	 * The bridge is loaded only on the Directorist settings screen, depends on
	 * the existing settings-manager bundle, and performs no database writes.
	 *
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_settings_ui_translation_bridge( $hook_suffix = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $this->is_directorist_settings_screen() || ! $this->is_wpml_active() ) {
			return;
		}

		$current_language = $this->get_current_target_language();

		if ( '' === $current_language ) {
			return;
		}

		$translations = $this->get_settings_ui_runtime_translations( $current_language );
		if ( empty( $translations ) || ! wp_script_is( 'directorist-settings-manager', 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script(
			'directorist-settings-manager',
			'window.directoristWpmlSettingsUiTranslations = ' . wp_json_encode( $translations ) . ';' . $this->get_settings_ui_translation_script(),
			'after'
		);
	}

	/**
	 * Translate the Directorist wp-admin menu branch on every admin screen.
	 *
	 * This is intentionally separate from the settings-manager bridge because
	 * WordPress rebuilds the sidebar menu on every admin page. The script is
	 * admin-only, scoped to Directorist menu/notices, reads the completed
	 * Settings UI ATE package map, and performs no database writes.
	 *
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_admin_menu_translation_bridge( $hook_suffix = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! is_admin() || wp_doing_ajax() || ! $this->is_wpml_active() || ! $this->has_directorist_admin_menu() ) {
			return;
		}

		$current_language = $this->get_current_target_language();

		if ( '' === $current_language ) {
			return;
		}

		$translations = $this->get_admin_menu_runtime_translations( $current_language );
		if ( empty( $translations ) ) {
			return;
		}

		$handle = 'directorist-wpml-admin-menu-translation';

		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script(
				$handle,
				false,
				[],
				defined( 'DIRECTORIST_WPML_INTEGRATION_SCRIPT_VERSION' ) ? DIRECTORIST_WPML_INTEGRATION_SCRIPT_VERSION : null,
				true
			);
		}

		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			'window.directoristWpmlAdminMenuTranslations = ' . wp_json_encode( $translations ) . ';' . $this->get_admin_menu_translation_script(),
			'after'
		);
	}

	/**
	 * Build settings and email package rows from the integration's admin-text
	 * configuration and the current unfiltered Directorist option array.
	 *
	 * @param bool $include_ui Include the Settings UI source-file package.
	 * @return array
	 */
	private function get_package_strings( $include_ui = true ) {
		$strings = [
			'settings' => [],
			'email'    => [],
			'ui'       => $include_ui ? $this->get_settings_ui_package_strings() : [],
		];
		$options = $this->get_source_directorist_options();

		foreach ( $this->get_admin_text_option_keys() as $key => $config ) {
			if ( ! empty( $config['is_post_id'] ) || $this->is_extension_option_key( $key ) || ! array_key_exists( $key, $options ) ) {
				continue;
			}

			$value = $options[ $key ];
			if ( ! $this->is_translatable_value( $value ) ) {
				continue;
			}

			$package_type = $this->is_email_template_key( $key ) ? 'email' : 'settings';
			$strings[ $package_type ][] = [
				'name'  => $key,
				'value' => trim( (string) $value ),
				'title' => $this->get_string_title( $key, $package_type ),
				'type'  => false !== strpos( (string) $value, "\n" ) || strlen( wp_strip_all_tags( (string) $value ) ) > 120 ? 'AREA' : 'LINE',
			];
		}

		return $strings;
	}

	/**
	 * Build deterministic ATE rows from Directorist's native settings redesign.
	 *
	 * @return array
	 */
	private function get_settings_ui_package_strings() {
		$strings = [];

		foreach ( $this->get_settings_ui_source_strings() as $source ) {
			$strings[] = [
				'name'  => 'settings_ui_' . substr( sha1( $source ), 0, 20 ),
				'value' => $source,
				'title' => 'Directorist Settings UI: ' . $source,
				'type'  => strlen( $source ) > 120 ? 'AREA' : 'LINE',
			];
		}

		return $strings;
	}

	/**
	 * Extract native labels from the active Directorist settings source.
	 *
	 * The source file is read only during bounded package-registration flows.
	 * Restrict extraction to the settings redesign configuration so builder and
	 * extension component literals do not leak into this package.
	 *
	 * @return array
	 */
	private function get_settings_ui_source_strings() {
		$strings = array_merge(
			$this->get_static_settings_ui_strings(),
			$this->get_supplemental_settings_ui_strings()
		);
		$path    = defined( 'DIRECTORIST_ASSETS_DIR' )
			? trailingslashit( DIRECTORIST_ASSETS_DIR ) . 'js/admin-settings-manager.js'
			: '';

		if ( '' !== $path && is_readable( $path ) ) {
			$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( is_string( $source ) ) {
				$start = strpos( $source, 'var FIELD_OVERRIDES = {' );
				$end   = false !== $start ? strpos( $source, 'var resolveSettingsHashTarget', $start ) : false;

				if ( false !== $start && false !== $end && $end > $start ) {
					$settings_source = substr( $source, $start, $end - $start );
					$property_names  = [
						'advancedLabel',
						'button_label',
						'buttonLabel',
						'buttonLabelOnProcessing',
						'description',
						'label',
						'label_default',
						'label_on_progress',
						'label_saved',
						'placeholder',
						'browseButtonLabel',
						'browseDescription',
						'browseTitle',
						'schema',
						'seoAdvancedLabel',
						'title',
					];
					$pattern         = '/(?:' . implode( '|', array_map( 'preg_quote', $property_names ) ) . ')\s*:\s*(?:\'((?:\\\\.|[^\'])*)\'|"((?:\\\\.|[^"])*)")/';

					if ( preg_match_all( $pattern, $settings_source, $matches, PREG_SET_ORDER ) ) {
						foreach ( $matches as $match ) {
							$value = isset( $match[1] ) && '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' );
							$value = $this->normalize_settings_ui_source( stripcslashes( $value ) );

							if ( '' !== $value ) {
								$strings[] = $value;
							}
						}
					}

					$menu_pattern = '/makeMenu\(\s*[^,\r\n]+,\s*(?:\'((?:\\\\.|[^\'])*)\'|"((?:\\\\.|[^"])*)")/';

					if ( preg_match_all( $menu_pattern, $settings_source, $menu_matches, PREG_SET_ORDER ) ) {
						foreach ( $menu_matches as $match ) {
							$value = isset( $match[1] ) && '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' );
							$value = $this->normalize_settings_ui_source( stripcslashes( $value ) );

							if ( '' !== $value ) {
								$strings[] = $value;
							}
						}
					}
				}
			}
		}

		$strings = array_merge( $strings, $this->get_settings_panel_field_strings() );
		$strings = array_values( array_unique( array_filter( array_map( [ $this, 'normalize_settings_ui_source' ], $strings ) ) ) );
		sort( $strings, SORT_NATURAL | SORT_FLAG_CASE );

		return $strings;
	}

	/**
	 * Collect native PHP settings-panel labels/descriptions used by the Vue UI.
	 *
	 * These strings are read only while WPML/Directorist registers packages.
	 * Runtime admin translation still uses the small completed-translation map,
	 * so this adds no frontend scan and no hot-path filesystem work.
	 *
	 * @return array
	 */
	private function get_settings_panel_field_strings() {
		return $this->get_settings_panel_source_file_strings();
	}

	/**
	 * Extract native settings panel labels from Directorist's PHP source file.
	 *
	 * Runtime object fields can be unavailable during WPML package registration,
	 * so this deterministic fallback keeps ATE coverage stable without adding
	 * any frontend work or broad filesystem scans.
	 *
	 * @return array
	 */
	private function get_settings_panel_source_file_strings() {
		$path = defined( 'ATBDP_CLASS_DIR' )
			? trailingslashit( ATBDP_CLASS_DIR ) . 'class-settings-panel.php'
			: '';

		if ( '' === $path || ! is_readable( $path ) ) {
			return [];
		}

		$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $source ) ) {
			return [];
		}

		return $this->extract_settings_ui_php_source_strings( $source );
	}

	/**
	 * Extract user-facing settings UI literals from PHP settings source.
	 *
	 * @param string $source PHP source.
	 * @return array
	 */
	private function extract_settings_ui_php_source_strings( $source ) {
		$strings   = [];
		$text_keys = '(?:advancedLabel|button-label|button-label-on-processing|button_label|buttonLabel|buttonLabelOnProcessing|description|desc|empty_label|help|instructions|label|label_default|label_on_progress|label_saved|legend|modal_subtitle|modal_title|placeholder|sub_title|subtitle|title|tooltip)';
		$patterns  = [
			[
				'pattern'     => '/[\'"]' . $text_keys . '[\'"]\s*=>\s*(?:__|esc_html__|esc_attr__)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])directorist\3\s*\)/s',
				'value_index' => 2,
			],
			[
				'pattern'     => '/[\'"]' . $text_keys . '[\'"]\s*=>\s*(?:__|esc_html__|esc_attr__)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*\)/s',
				'value_index' => 2,
			],
			[
				'pattern'     => '/[\'"]' . $text_keys . '[\'"]\s*=>\s*(?:_x|esc_html_x|esc_attr_x)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])(?:\\\\.|(?!\3).)*\3\s*,\s*([\'"])directorist\4\s*\)/s',
				'value_index' => 2,
			],
			[
				'pattern'     => '/[\'"]' . $text_keys . '[\'"]\s*=>\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s',
				'value_index' => 2,
			],
		];

		foreach ( $patterns as $config ) {
			$pattern = $config['pattern'];

			if ( ! preg_match_all( $pattern, (string) $source, $matches, PREG_SET_ORDER ) ) {
				continue;
			}

			foreach ( $matches as $match ) {
				$value = isset( $match[ $config['value_index'] ] ) ? $match[ $config['value_index'] ] : '';
				$value = $this->normalize_settings_ui_source( stripcslashes( $value ) );

				if ( '' !== $value ) {
					$strings[] = $value;
				}
			}
		}

		return $strings;
	}

	/**
	 * Native settings-manager/admin-menu chrome that lives outside the redesign map.
	 *
	 * These are English source strings only. Target-language values always come
	 * from ATE and are never embedded in production code.
	 *
	 * @return array
	 */
	private function get_static_settings_ui_strings() {
		return [
			'Directorist',
			'All Listings',
			'Add New Listing',
			'Categories',
			'Locations',
			'Reviews',
			'All Chats',
			'Directory Builder',
			'Orders',
			'Tags',
			'Themes & Extensions',
			'Tools',
			'Settings',
			'All Directories',
			'Directory names',
			'Deprecated Directorist items require upgrades',
			'Deprecated Directorist item requires an upgrade',
			'Directorist detected deprecated themes or extensions that are not compatible with this version. Upgrade them to the compatible versions listed below.',
			'Directorist detected a deprecated theme or extension that is not compatible with this version. Upgrade it to the compatible version listed below.',
			'Show deprecated items',
			'Manage Plugins',
			'Manage Themes',
			'Type',
			'Item',
			'Installed Version',
			'Required Version',
			'Status',
			'Settings area',
			'Settings breadcrumb',
			'Settings tabs',
			'Search settings...',
			'No settings match',
			'Try a shorter word.',
			'. Try a shorter word.',
			'RESULTS',
			'TO NAVIGATE',
			'TO CLOSE',
			'Tutorials',
			'Docs',
			'Help',
			'Save changes',
			'Saving...',
			'Saved',
			'Unsaved changes',
			'Stay on this page',
			'Save changes before leaving?',
			'Unsaved changes will be lost.',
			'Leave without saving',
			'Save & leave',
			'Show advanced settings',
			'Advanced',
			'Schedule & timing',
			'Scroll settings tabs left',
			'Scroll settings tabs right',
			'Select...',
			'Select page',
			'Where users submit a new listing.',
			'Shows the main directory archive.',
			'Where users manage their listings and account.',
			'Used for login, registration, and account access.',
			'Shows public author profile pages.',
			'Lists all directory categories.',
			'Shows listings from one category.',
			'Lists all directory locations.',
			'Shows listings from one location.',
			'Shows listings from one tag.',
			'Displays the directory search form.',
			'Shows results after a directory search.',
			'Used when a user pays for a listing.',
			'Shown after a successful payment.',
			'Shown when a payment fails.',
			'Linked from directory registration and submission flows.',
			'Copy shortcode',
			'Copy & Paste shortcodes into a new or existing page',
			'Default address',
			'Used as the fallback map center when a listing has no address set.',
			'Default: 640x360 px.',
			'If changed, regenerate thumbnails via this plugin for proper functionality.',
			'Edit',
			'Close',
			'Cancel',
			'Save',
			'Something went wrong',
			'days in trash',
		];
	}

	/**
	 * Native settings texts that are assembled outside the normal static keys.
	 *
	 * These English source strings are registered for WPML ATE only. The Dutch
	 * values still come from completed WPML translations and are not hardcoded in
	 * production code.
	 *
	 * @return array
	 */
	private function get_supplemental_settings_ui_strings() {
		return [
			'Default: 640x360 px. If changed, regenerate thumbnails via this plugin for proper functionality.',
			'Default: 640x360 px. If changed, regenerate thumbnails via plugin for proper functionality.',
			'If changed, regenerate thumbnails via plugin for proper functionality.',
			'Toggle a channel per event. Click Edit to customize subject, body, and push wording.',
			'Admin notifications',
			'Listing owner notifications',
			'Order created',
			'A new order has been placed',
			'Order completed',
			'An order has been fulfilled',
			'Payment received',
			'A payment has been confirmed',
			'New listing submitted',
			'A listing is waiting for review',
			'Listing approved or published',
			'A listing has gone live',
			'Listing edited',
			'A listing was updated by its owner',
			'Listing deleted',
			'A listing has been removed',
			'Listing renewed',
			'A listing plan has been renewed',
			'Listing contact form',
			'A visitor messaged via a listing',
			'Listing review',
			'A new review has been posted',
			'Listing submitted',
			'Confirmation their listing was received',
			'Their listing is now live',
			'Listing rejected',
			'Their listing was not approved',
			'Confirmation their edit was saved',
			'Their listing has been removed',
			'Listing nearly expired',
			'Their listing expires soon',
			'Listing expired',
			'Their listing plan has ended',
			'Remind to renew',
			'Renewal reminder after expiry',
			'Confirmation their listing was renewed',
			'Confirmation their order was placed',
			'Their order has been fulfilled',
			'Confirmation of a successful payment',
			'A visitor messaged via their listing',
			'Someone reviewed their listing',
			'Registration confirmation',
			'Welcome email sent after a new account is created',
			'Email verification',
			'Verification link sent when email verification is required',
			'Create as many badges as you want. Each badge has its own conditions and style. A listing can match multiple badges at once.',
			'Reset defaults',
			'General: Listing age (days) less or equal 3',
			'Label with an optional icon.',
			'Icon with hover text.',
			'For admin only.',
			'Visitors see this text on the badge.',
			'All (AND)',
			'Any (OR)',
			'greater or equal',
			'less or equal',
			'equal to',
			'General: View count greater or equal 5',
			'Meta for the other pages',
			'All listings page meta title',
			'All listings page meta description',
			'Add listing page meta title',
			'Add listing page meta description',
			'All categories page meta title',
			'All categories page meta description',
			'All locations page meta title',
			'All locations page meta description',
			'Search page meta title',
			'The search form / search home page.',
			'Search page meta description',
			'Search results meta title',
			'Used when search-friendly title is off.',
			'Search results meta description',
			'Account page meta title',
			'Single sign in / sign up page.',
			'Account page meta description',
			'Apply schema to',
			'Extension settings are available in the settings panel: Live Chat, Booking.',
			'30+ extensions available including PayPal, Stripe, Live Chat, Universal Search, Booking, and Pricing Plans.',
			'View directory',
			'Allow users add and display booking for a listing.',
		];
	}

	/**
	 * Normalize one extracted UI source.
	 *
	 * @param mixed $value Candidate source.
	 * @return string
	 */
	private function normalize_settings_ui_source( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) ) );

		if (
			'' === $value
			|| is_numeric( $value )
			|| ( function_exists( 'is_email' ) && is_email( $value ) )
			|| filter_var( $value, FILTER_VALIDATE_URL )
			|| preg_match( '/^(?:#[a-f0-9]{3,8}|true|false|null|undefined|yes|no|on|off)$/i', $value )
			|| preg_match( '/^[a-z0-9]+(?:_[a-z0-9]+)+$/i', $value )
		) {
			return '';
		}

		return preg_replace( '/\s+/u', ' ', $value );
	}
	/**
	 * Read option keys from wpml-config.xml so new native admin-text entries
	 * automatically join the matching ATE package.
	 *
	 * @return array
	 */
	private function get_admin_text_option_keys() {
		$path = dirname( __DIR__, 3 ) . '/wpml-config.xml';
		if ( ! function_exists( 'simplexml_load_file' ) || ! is_readable( $path ) ) {
			return [];
		}

		$xml = simplexml_load_file( $path );
		if ( ! $xml || empty( $xml->{'admin-texts'} ) ) {
			return [];
		}

		$keys = [];
		foreach ( $xml->{'admin-texts'}->key as $root_key ) {
			if ( 'atbdp_option' !== (string) $root_key['name'] ) {
				continue;
			}

			foreach ( $root_key->key as $key ) {
				$name = (string) $key['name'];
				if ( '' === $name ) {
					continue;
				}

				$keys[ $name ] = [
					'is_post_id' => 'post-ids' === (string) $key['type'],
				];
			}
		}

		return $keys;
	}

	/**
	 * Read the source option directly, bypassing WPML's option filters.
	 *
	 * @return array
	 */
	private function get_raw_directorist_options() {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				'atbdp_option'
			)
		);
		$options = maybe_unserialize( $value );

		return is_array( $options ) ? $options : [];
	}

	/**
	 * Add current Directorist defaults for valid admin-text keys that have not
	 * yet been persisted on an older/fresh installation.
	 *
	 * @return array
	 */
	private function get_source_directorist_options() {
		if ( null !== self::$source_options ) {
			return self::$source_options;
		}

		$options = $this->get_raw_directorist_options();

		if ( function_exists( 'ATBDP' ) && ! empty( ATBDP()->settings_panel ) && is_callable( [ ATBDP()->settings_panel, 'prepare_settings' ] ) ) {
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			ATBDP()->settings_panel->prepare_settings();

			foreach ( (array) ATBDP()->settings_panel->fields as $key => $field ) {
				if ( ! array_key_exists( $key, $options ) && is_array( $field ) && array_key_exists( 'value', $field ) ) {
					$options[ $key ] = $field['value'];
				}
			}
		}

		self::$source_options = $options;

		return self::$source_options;
	}

	/**
	 * Check whether an option is useful user-facing text.
	 *
	 * @param mixed $value Option value.
	 * @return bool
	 */
	private function is_translatable_value( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$value = trim( $value );
		$plain = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );

		if ( '' === $plain || is_numeric( $plain ) || is_email( $plain ) || filter_var( $plain, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		if ( preg_match( '/^(?:#[a-f0-9]{3,8}|true|false|yes|no|on|off)$/i', $plain ) ) {
			return false;
		}

		return ! preg_match( '/^[a-z0-9]+(?:_[a-z0-9]+)+$/i', $plain );
	}

	/**
	 * Keep extension-owned Claim Listing values outside these native packages.
	 *
	 * @param string $key Option key.
	 * @return bool
	 */
	private function is_extension_option_key( $key ) {
		return (bool) preg_match( '/(?:^|_)(?:booking|chat|claim)(?:_|$)/', (string) $key );
	}

	/**
	 * Identify native Directorist subject/body option keys.
	 *
	 * @param string $key Option key.
	 * @return bool
	 */
	private function is_email_template_key( $key ) {
		return (bool) preg_match( '/^email_(?:sub|tmpl)_/', (string) $key );
	}

	/**
	 * Build a readable ATE field title.
	 *
	 * @param string $key          Option key.
	 * @param string $package_type Package type.
	 * @return string
	 */
	private function get_string_title( $key, $package_type ) {
		$label = ucwords( str_replace( '_', ' ', preg_replace( '/^email_(?:sub|tmpl)_/', '', $key ) ) );

		if ( 'email' === $package_type ) {
			return ( 0 === strpos( $key, 'email_sub_' ) ? 'Email Subject: ' : 'Email Body: ' ) . $label;
		}

		return 'Directorist Setting: ' . $label;
	}

	/**
	 * Register one string, avoiding WPML's write path when it is unchanged.
	 *
	 * @param array $package Package data.
	 * @param array $string  String data.
	 */
	private function register_package_string( array $package, array $string ) {
		$existing = $this->get_existing_package_strings( $package );

		if (
			isset( $existing[ $string['name'] ] )
			&& $existing[ $string['name'] ]['value'] === $string['value']
			&& $existing[ $string['name'] ]['type'] === $string['type']
		) {
			return;
		}

		$error_level = error_reporting();
		error_reporting( $error_level & ~E_WARNING & ~E_DEPRECATED );

		try {
			do_action(
				'wpml_register_string',
				$string['value'],
				$string['name'],
				$package,
				$string['title'],
				$string['type']
			);
		} finally {
			error_reporting( $error_level );
		}

		$package_key = $package['kind_slug'] . ':' . $package['name'];
		self::$package_string_cache[ $package_key ][ $string['name'] ] = [
			'value' => $string['value'],
			'type'  => $string['type'],
		];
	}

	/**
	 * Cache existing package strings.
	 *
	 * @param array $package Package data.
	 * @return array
	 */
	private function get_existing_package_strings( array $package ) {
		global $wpdb;

		$package_key = $package['kind_slug'] . ':' . $package['name'];
		if ( array_key_exists( $package_key, self::$package_string_cache ) ) {
			return self::$package_string_cache[ $package_key ];
		}

		self::$package_string_cache[ $package_key ] = [];
		$package_id = $this->get_package_id( $package );

		if ( $package_id <= 0 ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, value, type FROM {$wpdb->prefix}icl_strings WHERE string_package_id = %d",
				$package_id
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			self::$package_string_cache[ $package_key ][ $row['name'] ] = [
				'value' => $row['value'],
				'type'  => $row['type'],
			];
		}

		return self::$package_string_cache[ $package_key ];
	}

	/**
	 * Decode completed external-package fields.
	 *
	 * @param object   $job             WPML job.
	 * @param callable $decoder         Field decoder.
	 * @param string   $package_context Package context.
	 * @return array
	 */
	private function get_job_translations( $job, $decoder, $package_context ) {
		$translations = [];

		foreach ( $job->elements as $field ) {
			if ( empty( $field->field_translate ) || empty( $field->field_type ) ) {
				continue;
			}

			$field_context = apply_filters( 'wpml_save_external_package_field_context', $package_context, $field, $job );
			if ( $field_context !== $package_context ) {
				continue;
			}

			$format     = isset( $field->field_format ) ? $field->field_format : '';
			$field_data = isset( $field->field_data_translated ) ? $field->field_data_translated : '';
			$translated = is_callable( $decoder ) ? $decoder( $field_data, $format ) : $field_data;

			if ( is_string( $translated ) && '' !== trim( $translated ) ) {
				$translations[ $field->field_type ] = $translated;
			}
		}

		return $translations;
	}

	/**
	 * Mirror package values into the existing admin-text string context.
	 *
	 * @param string $language     Target language.
	 * @param array  $translations Translations keyed by option key.
	 * @param string $package_type Package type.
	 */
	private function sync_admin_text_translations( $language, array $translations, $package_type ) {
		if ( '' === $language || ! function_exists( 'icl_add_string_translation' ) ) {
			return;
		}

		$allowed = [];
		foreach ( $this->get_package_strings( false )[ $package_type ] as $string ) {
			$allowed[ $string['name'] ] = $string['value'];
		}
		$updated_ids = [];

		foreach ( $translations as $key => $translated_value ) {
			if (
				! isset( $allowed[ $key ] )
				|| ! $this->is_usable_translation( $allowed[ $key ], $translated_value, $language )
			) {
				continue;
			}

			$string_id = $this->get_or_register_admin_text_string_id( $key, $allowed[ $key ] );
			if ( $string_id <= 0 || ! $this->should_update_admin_text_translation( $string_id, $language, $allowed[ $key ] ) ) {
				continue;
			}

			icl_add_string_translation(
				$string_id,
				$language,
				$translated_value,
				defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10
			);
			$updated_ids[] = $string_id;
		}

		if ( $updated_ids && function_exists( 'wpml_st_flush_string_cache_for_ids' ) ) {
			wpml_st_flush_string_cache_for_ids( array_values( array_unique( $updated_ids ) ) );
		}
	}

	/**
	 * Find or register an exact atbdp_option admin-text string.
	 *
	 * @param string $key    Option key.
	 * @param string $source Source value.
	 * @return int
	 */
	private function get_or_register_admin_text_string_id( $key, $source ) {
		global $wpdb;

		$name = '[atbdp_option]' . $key;
		$id   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name = %s LIMIT 1",
				self::ADMIN_TEXT_CONTEXT,
				$name
			)
		);

		if ( $id <= 0 && function_exists( 'icl_register_string' ) ) {
			$id = (int) icl_register_string( self::ADMIN_TEXT_CONTEXT, $name, $source );
		}

		return $id;
	}

	/**
	 * Preserve completed human translations that predate the ATE package.
	 *
	 * @param int    $string_id String ID.
	 * @param string $language  Target language.
	 * @param string $source    Source value.
	 * @return bool
	 */
	private function should_update_admin_text_translation( $string_id, $language, $source ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT value, status FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = %s LIMIT 1",
				$string_id,
				$language
			)
		);

		if ( ! $row || empty( $row->value ) || (int) $row->status !== ( defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10 ) ) {
			return true;
		}

		return $this->strings_match( $source, $row->value ) || $this->is_qa_placeholder( $row->value, $language );
	}

	/**
	 * Validate a translated value without altering placeholders.
	 *
	 * @param string $source      Source value.
	 * @param mixed  $translation Candidate.
	 * @param string $language    Language code.
	 * @return bool
	 */
	private function is_usable_translation( $source, $translation, $language ) {
		return is_string( $translation )
			&& '' !== trim( $translation )
			&& ! $this->strings_match( $source, $translation )
			&& ! $this->is_qa_placeholder( $translation, $language )
			&& $this->get_placeholders( $source ) === $this->get_placeholders( $translation );
	}

	/**
	 * Extract Directorist template placeholders.
	 *
	 * @param string $value Text.
	 * @return array
	 */
	private function get_placeholders( $value ) {
		preg_match_all( '/==[A-Z0-9_]+==/', (string) $value, $matches );
		$placeholders = isset( $matches[0] ) ? $matches[0] : [];
		sort( $placeholders );

		return $placeholders;
	}

	/**
	 * Compare normalized source/translation text.
	 *
	 * @param string $left  Left value.
	 * @param string $right Right value.
	 * @return bool
	 */
	private function strings_match( $left, $right ) {
		return html_entity_decode( trim( (string) $left ), ENT_QUOTES, get_bloginfo( 'charset' ) )
			=== html_entity_decode( trim( (string) $right ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * Reject QA placeholders as real translations.
	 *
	 * @param string $value    Value.
	 * @param string $language Language code.
	 * @return bool
	 */
	private function is_qa_placeholder( $value, $language ) {
		return (bool) preg_match( '/^' . preg_quote( strtoupper( (string) $language ), '/' ) . '(?:-QA)?:\s*/', trim( (string) $value ) );
	}

	/**
	 * Read completed package translations, grouped by language.
	 *
	 * @param array $package Package data.
	 * @return array
	 */
	private function get_completed_translations_by_language( array $package ) {
		global $wpdb;

		$package_id = $this->get_package_id( $package );
		if ( $package_id <= 0 ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.name, st.language, st.value
				FROM {$wpdb->prefix}icl_strings s
				INNER JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id = s.id
				WHERE s.string_package_id = %d AND st.status = %d AND st.value <> ''",
				$package_id,
				defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10
			),
			ARRAY_A
		);
		$translations = [];

		foreach ( $rows as $row ) {
			$translations[ $row['language'] ][ $row['name'] ] = $row['value'];
		}

		return $translations;
	}

	/**
	 * Load completed translations for both native packages in one indexed query.
	 *
	 * @param string $language Target language.
	 * @return array
	 */
	private function get_runtime_translations( $language ) {
		global $wpdb;

		if ( isset( self::$runtime_translation_cache[ $language ] ) ) {
			return self::$runtime_translation_cache[ $language ];
		}

		self::$runtime_translation_cache[ $language ] = [];

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.name, s.value AS source_value, st.value AS translated_value
				FROM {$wpdb->prefix}icl_string_packages p
				INNER JOIN {$wpdb->prefix}icl_strings s ON s.string_package_id = p.ID
				INNER JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id = s.id
				WHERE p.kind_slug IN (%s, %s)
					AND st.language = %s
					AND st.status = %d
					AND st.value <> ''",
				self::SETTINGS_KIND_SLUG,
				self::EMAIL_KIND_SLUG,
				$language,
				defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			self::$runtime_translation_cache[ $language ][ $row['name'] ] = [
				'source' => $row['source_value'],
				'value'  => $row['translated_value'],
			];
		}

		return self::$runtime_translation_cache[ $language ];
	}

	/**
	 * Load completed Settings UI translations in one indexed admin-only query.
	 *
	 * @param string $language Target language.
	 * @return array
	 */
	private function get_settings_ui_runtime_translations( $language ) {
		global $wpdb;

		if ( isset( self::$settings_ui_runtime_cache[ $language ] ) ) {
			return self::$settings_ui_runtime_cache[ $language ];
		}

		self::$settings_ui_runtime_cache[ $language ] = [];
		$package = $this->get_package( 'ui' );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.value AS source_value, st.value AS translated_value
				FROM {$wpdb->prefix}icl_string_packages p
				INNER JOIN {$wpdb->prefix}icl_strings s ON s.string_package_id = p.ID
				INNER JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id = s.id
				WHERE p.kind_slug = %s
					AND p.name = %s
					AND st.language = %s
					AND st.status = %d
					AND st.value <> ''",
				$package['kind_slug'],
				$package['name'],
				$language,
				defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			if ( ! $this->is_usable_translation( $row['source_value'], $row['translated_value'], $language ) ) {
				continue;
			}

			self::$settings_ui_runtime_cache[ $language ][ $row['source_value'] ] = $row['translated_value'];
		}

		return self::$settings_ui_runtime_cache[ $language ];
	}

	/**
	 * Load the small admin-menu/admin-notice subset from the Settings UI package.
	 *
	 * @param string $language Target language.
	 * @return array
	 */
	private function get_admin_menu_runtime_translations( $language ) {
		global $wpdb;

		if ( isset( self::$admin_menu_runtime_cache[ $language ] ) ) {
			return self::$admin_menu_runtime_cache[ $language ];
		}

		self::$admin_menu_runtime_cache[ $language ] = [];

		$sources = array_values( array_unique( $this->get_admin_bridge_source_strings() ) );
		if ( empty( $sources ) ) {
			return self::$admin_menu_runtime_cache[ $language ];
		}

		$package      = $this->get_package( 'ui' );
		$placeholders = implode( ',', array_fill( 0, count( $sources ), '%s' ) );
		$args         = array_merge(
			[
				$package['kind_slug'],
				$package['name'],
				$language,
				defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10,
			],
			$sources
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.value AS source_value, st.value AS translated_value
				FROM {$wpdb->prefix}icl_string_packages p
				INNER JOIN {$wpdb->prefix}icl_strings s ON s.string_package_id = p.ID
				INNER JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id = s.id
				WHERE p.kind_slug = %s
					AND p.name = %s
					AND st.language = %s
					AND st.status = %d
					AND st.value <> ''
					AND s.value IN ({$placeholders})",
				...$args
			),
			ARRAY_A
		);

		$allowed_sources = array_fill_keys( $sources, true );

		foreach ( $rows as $row ) {
			if (
				empty( $allowed_sources[ $row['source_value'] ] )
				|| ! $this->is_usable_translation( $row['source_value'], $row['translated_value'], $language )
			) {
				continue;
			}

			self::$admin_menu_runtime_cache[ $language ][ $row['source_value'] ] = $row['translated_value'];
		}

		return self::$admin_menu_runtime_cache[ $language ];
	}

	/**
	 * Source strings the lightweight all-admin bridge is allowed to touch.
	 *
	 * @return array
	 */
	private function get_admin_bridge_source_strings() {
		return [
			'Directorist',
			'All Listings',
			'Add New Listing',
			'Categories',
			'Locations',
			'Tags',
			'Directory Builder',
			'Reviews',
			'All Chats',
			'Orders',
			'Settings',
			'Themes & Extensions',
			'All Directories',
			'Directory names',
			'Deprecated Directorist items require upgrades',
			'Deprecated Directorist item requires an upgrade',
			'Directorist detected deprecated themes or extensions that are not compatible with this version. Upgrade them to the compatible versions listed below.',
			'Directorist detected a deprecated theme or extension that is not compatible with this version. Upgrade it to the compatible version listed below.',
			'Show deprecated items',
			'Manage Plugins',
			'Manage Themes',
			'Type',
			'Item',
			'Installed Version',
			'Required Version',
			'Status',
		];
	}

	/**
	 * JavaScript bridge for Directorist's Vue-only settings strings.
	 *
	 * @return string
	 */
	private function get_settings_ui_translation_script() {
		return <<<'JS'
(function () {
    var translations = window.directoristWpmlSettingsUiTranslations || {};
    var settingsUiBootstrapAttempts = 0;
    var maxSettingsUiBootstrapAttempts = 80;
    var treeWalkerFilter = window.NodeFilter || {
        SHOW_TEXT: 4,
        FILTER_ACCEPT: 1,
        FILTER_REJECT: 2,
        FILTER_SKIP: 3
    };

    if (!Object.keys(translations).length) {
        return;
    }

    function translatedValue(value) {
        var trimmed = String(value || '').trim();

        if (Object.prototype.hasOwnProperty.call(translations, trimmed)) {
            return translations[trimmed];
        }

        var normalized = trimmed.replace(/\s+/g, ' ');

		if (Object.prototype.hasOwnProperty.call(translations, normalized)) {
			return translations[normalized];
		}

		var punctuationMatch = normalized.match(/^(.+?)([.!?])$/);

		if (punctuationMatch && Object.prototype.hasOwnProperty.call(translations, punctuationMatch[1])) {
			var baseTranslation = translations[punctuationMatch[1]];

			return /[.!?]$/.test(baseTranslation) ? baseTranslation : baseTranslation + punctuationMatch[2];
		}

		var resultsMatch = normalized.match(/^(\d+)\s+RESULTS$/);

		if (resultsMatch && translations.RESULTS) {
			return resultsMatch[1] + ' ' + translations.RESULTS;
        }

        return null;
    }

    function translateTextNode(node) {
        var translated = translatedValue(node.nodeValue);

        if (translated === null) {
            return;
        }

        var leading = (node.nodeValue.match(/^\s*/) || [''])[0];
        var trailing = (node.nodeValue.match(/\s*$/) || [''])[0];
        node.nodeValue = leading + translated + trailing;
    }

    function translateAttributes(root) {
        var attributeNames = ['aria-label', 'placeholder', 'title', 'data-tooltip'];
        var elements = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));

        elements.forEach(function (element) {
            if (!element || !element.getAttribute) {
                return;
            }

            attributeNames.forEach(function (attributeName) {
                if (!element.hasAttribute(attributeName)) {
                    return;
                }

                var translated = translatedValue(element.getAttribute(attributeName));

                if (translated !== null) {
                    element.setAttribute(attributeName, translated);
                }
            });
        });
    }

    function translateRoot(root) {
        if (!root) {
            return;
        }

        var walker = document.createTreeWalker(root, treeWalkerFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentNode;

                if (!parent || ['SCRIPT', 'STYLE', 'TEXTAREA'].indexOf(parent.nodeName) !== -1) {
                    return treeWalkerFilter.FILTER_REJECT;
                }

                return translatedValue(node.nodeValue) === null ? treeWalkerFilter.FILTER_SKIP : treeWalkerFilter.FILTER_ACCEPT;
            }
        });
        var nodes = [];
        var node;

        while ((node = walker.nextNode())) {
            nodes.push(node);
        }

        nodes.forEach(translateTextNode);
        translateAttributes(root);
    }

    function scheduleNextBootstrap() {
        if (settingsUiBootstrapAttempts >= maxSettingsUiBootstrapAttempts) {
            return;
        }

        settingsUiBootstrapAttempts += 1;
        window.setTimeout(init, 100);
    }

    function init() {
        var root = document.getElementById('atbdp-settings-manager');

        if (!root) {
            scheduleNextBootstrap();
            return;
        }

        translateRoot(root);

        if (root.directoristWpmlSettingsUiObserver) {
            scheduleNextBootstrap();
            return;
        }

        var scheduled = false;
        var observer = new MutationObserver(function () {
            if (scheduled) {
                return;
            }

            scheduled = true;
            window.requestAnimationFrame(function () {
                scheduled = false;
                translateRoot(root);
            });
        });

        observer.observe(root, {
            attributes: true,
            attributeFilter: ['aria-label', 'placeholder', 'title', 'data-tooltip'],
            characterData: true,
            childList: true,
            subtree: true
        });
        root.directoristWpmlSettingsUiObserver = observer;
        scheduleNextBootstrap();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
JS;
	}

	/**
	 * JavaScript bridge for the Directorist wp-admin menu branch.
	 *
	 * @return string
	 */
	private function get_admin_menu_translation_script() {
		return <<<'JS'
(function () {
    var translations = window.directoristWpmlAdminMenuTranslations || {};
    var menuBootstrapAttempts = 0;
    var maxMenuBootstrapAttempts = 40;
    var treeWalkerFilter = window.NodeFilter || {
        SHOW_TEXT: 4,
        FILTER_ACCEPT: 1,
        FILTER_REJECT: 2,
        FILTER_SKIP: 3
    };

    if (!Object.keys(translations).length) {
        return;
    }

    function translatedValue(value) {
        var trimmed = String(value || '').trim();

        if (Object.prototype.hasOwnProperty.call(translations, trimmed)) {
            return translations[trimmed];
        }

		var normalized = trimmed.replace(/\s+/g, ' ');

		if (Object.prototype.hasOwnProperty.call(translations, normalized)) {
			return translations[normalized];
		}

		var countMatch = normalized.match(/^(.+?)\s+\((\d+)\)$/);

		if (countMatch && Object.prototype.hasOwnProperty.call(translations, countMatch[1])) {
			return translations[countMatch[1]] + ' (' + countMatch[2] + ')';
		}

		var punctuationMatch = normalized.match(/^(.+?)([.!?])$/);

		if (punctuationMatch && Object.prototype.hasOwnProperty.call(translations, punctuationMatch[1])) {
			var baseTranslation = translations[punctuationMatch[1]];

			return /[.!?]$/.test(baseTranslation) ? baseTranslation : baseTranslation + punctuationMatch[2];
		}

		return null;
    }

    function translateTextNode(node) {
        var translated = translatedValue(node.nodeValue);

        if (translated === null) {
            return;
        }

        var leading = (node.nodeValue.match(/^\s*/) || [''])[0];
        var trailing = (node.nodeValue.match(/\s*$/) || [''])[0];
        node.nodeValue = leading + translated + trailing;
    }

    function translateAttributes(root) {
        var attributeNames = ['aria-label', 'title'];
        var elements = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));

        elements.forEach(function (element) {
            if (!element || !element.getAttribute) {
                return;
            }

            attributeNames.forEach(function (attributeName) {
                if (!element.hasAttribute(attributeName)) {
                    return;
                }

                var translated = translatedValue(element.getAttribute(attributeName));

                if (translated !== null) {
                    element.setAttribute(attributeName, translated);
                }
            });
        });
    }

    function translateRoot(root) {
        if (!root) {
            return;
        }

        var walker = document.createTreeWalker(root, treeWalkerFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentNode;

                if (!parent || ['SCRIPT', 'STYLE', 'TEXTAREA'].indexOf(parent.nodeName) !== -1) {
                    return treeWalkerFilter.FILTER_REJECT;
                }

                return translatedValue(node.nodeValue) === null ? treeWalkerFilter.FILTER_SKIP : treeWalkerFilter.FILTER_ACCEPT;
            }
        });
        var nodes = [];
        var node;

        while ((node = walker.nextNode())) {
            nodes.push(node);
        }

        nodes.forEach(translateTextNode);
        translateAttributes(root);
    }

	function scheduleNextBootstrap() {
		if (menuBootstrapAttempts >= maxMenuBootstrapAttempts) {
			return;
		}

		menuBootstrapAttempts += 1;
		window.setTimeout(init, 100);
	}

	function translateDirectoristAdminChrome() {
		translateRoot(document.getElementById('menu-posts-at_biz_dir'));

		document.querySelectorAll('.directorist-deprecated-item-notice').forEach(translateRoot);

		if (document.body && document.body.classList.contains('post-type-at_biz_dir')) {
			document.querySelectorAll('#wpbody-content .wrap, #wpbody-content [class*="directorist"]').forEach(translateRoot);
		}
	}

	function observeRoot(root) {
		if (!root || root.directoristWpmlAdminMenuObserver) {
			return;
		}

		var scheduled = false;
		var observer = new MutationObserver(function () {
			if (scheduled) {
				return;
			}

			scheduled = true;
			window.requestAnimationFrame(function () {
				scheduled = false;
				translateDirectoristAdminChrome();
			});
		});

		observer.observe(root, {
			attributes: true,
			attributeFilter: ['aria-label', 'title'],
			characterData: true,
			childList: true,
			subtree: true
		});
		root.directoristWpmlAdminMenuObserver = observer;
	}

	function init() {
		var menuRoot = document.getElementById('menu-posts-at_biz_dir');

		if (!menuRoot) {
			scheduleNextBootstrap();
		}

		translateDirectoristAdminChrome();
		observeRoot(document.getElementById('adminmenuwrap'));
		observeRoot(document.getElementById('wpbody-content'));
	}

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
JS;
	}

	/**
	 * Resolve the target language for runtime/admin display translation.
	 *
	 * WPML can leave wpml_current_language on the default language for custom
	 * admin screens even when the admin content language is carried by
	 * `?lang=xx`. Respect that request language on admin requests; frontend
	 * requests continue using WPML's current-language filter.
	 *
	 * @return string
	 */
	private function get_current_target_language() {
		$current_language = (string) apply_filters( 'wpml_current_language', '' );
		$default_language = $this->get_default_language();

		if ( '' === $default_language ) {
			return '';
		}

		if ( '' !== $current_language && $current_language !== $default_language ) {
			return $current_language;
		}

		$requested_language = $this->get_requested_admin_language();
		if ( '' !== $requested_language && $requested_language !== $default_language ) {
			return $requested_language;
		}

		return '';
	}

	/**
	 * Resolve WPML's default language.
	 *
	 * @return string
	 */
	private function get_default_language() {
		return (string) apply_filters( 'wpml_default_language', '' );
	}

	/**
	 * Read the target admin language from WPML's admin content-language URL.
	 *
	 * @return string
	 */
	private function get_requested_admin_language() {
		if ( ! function_exists( 'is_admin' ) || ! function_exists( 'wp_doing_ajax' ) ) {
			return '';
		}

		if ( ! is_admin() || wp_doing_ajax() || empty( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		$language = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET['lang'] ) : $_GET['lang']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $this->normalize_language_code( $language );
	}

	/**
	 * Normalize a WPML language code from request/sitepress values.
	 *
	 * @param mixed $language Raw language.
	 * @return string
	 */
	private function normalize_language_code( $language ) {
		$language = strtolower( trim( preg_replace( '/[^a-z0-9_-]/i', '', (string) $language ) ) );

		if ( in_array( $language, [ 'all', 'default', '_default_' ], true ) ) {
			return '';
		}

		if ( ! preg_match( '/^[a-z]{2,3}(?:[-_][a-z0-9]{2,5})?$/', $language ) ) {
			return '';
		}

		return $language;
	}

	/**
	 * Check the exact Directorist settings admin screen.
	 *
	 * @return bool
	 */
	private function is_directorist_settings_screen() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}

		$page      = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 'atbdp-settings' === $page && ( '' === $post_type || 'at_biz_dir' === $post_type );
	}

	/**
	 * Check whether the current admin screen belongs to Directorist.
	 *
	 * @return bool
	 */
	private function is_directorist_admin_screen() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $post_type, [ 'at_biz_dir', 'atbdp_chats' ], true ) ) {
			return true;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $page, [ 'atbdp-settings', 'atbdp-directory-types', 'atbdp-extension', 'directorist-orders' ], true ) ) {
			return true;
		}

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $taxonomy, [ 'at_biz_dir-category', 'at_biz_dir-location', 'at_biz_dir-tags' ], true );
	}

	/**
	 * Check whether Directorist has registered its admin menu branch.
	 *
	 * @return bool
	 */
	private function has_directorist_admin_menu() {
		global $menu, $submenu;

		if ( ! is_array( $submenu ) || ! empty( $submenu['edit.php?post_type=at_biz_dir'] ) ) {
			return true;
		}

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( is_array( $item ) && ! empty( $item[2] ) && 'edit.php?post_type=at_biz_dir' === $item[2] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Resolve a package ID.
	 *
	 * @param array $package Package data.
	 * @return int
	 */
	private function get_package_id( array $package ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = %s AND name = %s LIMIT 1",
				$package['kind_slug'],
				$package['name']
			)
		);
	}

	/**
	 * Package definition.
	 *
	 * @param string $package_type Package type.
	 * @return array
	 */
	private function get_package( $package_type ) {
		if ( 'ui' === $package_type ) {
			return [
				'kind'      => self::SETTINGS_UI_KIND,
				'kind_slug' => self::SETTINGS_UI_KIND_SLUG,
				'name'      => self::SETTINGS_UI_NAME,
				'title'     => 'Directorist Settings Tabs, Labels and Descriptions',
				'edit_link' => admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-settings' ),
				'view_link' => admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-settings' ),
			];
		}

		$is_email = 'email' === $package_type;

		return [
			'kind'      => $is_email ? self::EMAIL_KIND : self::SETTINGS_KIND,
			'kind_slug' => $is_email ? self::EMAIL_KIND_SLUG : self::SETTINGS_KIND_SLUG,
			'name'      => $is_email ? self::EMAIL_NAME : self::SETTINGS_NAME,
			'title'     => $is_email ? 'Directorist Email Subjects and Templates' : 'Directorist Settings and Admin Texts',
			'edit_link' => admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-settings' ),
			'view_link' => home_url( '/' ),
		];
	}

	/**
	 * Resolve our package type from WPML's context.
	 *
	 * @param string $context Package context.
	 * @return string
	 */
	private function get_package_type_from_context( $context ) {
		if ( self::SETTINGS_KIND_SLUG . '-' . self::SETTINGS_NAME === $context ) {
			return 'settings';
		}

		if ( self::EMAIL_KIND_SLUG . '-' . self::EMAIL_NAME === $context ) {
			return 'email';
		}

		if ( self::SETTINGS_UI_KIND_SLUG . '-' . self::SETTINGS_UI_NAME === $context ) {
			return 'ui';
		}

		return '';
	}

	/**
	 * Check whether a translatable item belongs to either package.
	 *
	 * @param mixed $item Item.
	 * @return bool
	 */
	private function is_our_package_item( $item ) {
		return is_object( $item )
			&& class_exists( '\WPML_Package' )
			&& is_a( $item, '\WPML_Package' )
			&& ! empty( $item->kind_slug )
			&& in_array( $item->kind_slug, [ self::SETTINGS_KIND_SLUG, self::EMAIL_KIND_SLUG, self::SETTINGS_UI_KIND_SLUG ], true );
	}

	/**
	 * Keep registration on bounded WPML/Directorist admin flows.
	 *
	 * Package values are always read directly from the unfiltered source option,
	 * so the current admin content language does not affect the source strings.
	 *
	 * @return bool
	 */
	private function should_register_packages() {
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

			return false !== strpos( $action, 'wpml' ) || false !== strpos( $action, 'icl_' );
		}

		if ( is_admin() ) {
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

			return in_array(
				$page,
				[
					'atbdp-settings',
					'tm/menu/main.php',
					'tm/menu/translations-queue.php',
					'tm/menu/settings',
					'wpml-package-management',
					'wpml-string-translation/menu/string-translation.php',
				],
				true
			);
		}

		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Limit completed-translation sync to admin/package inspection flows.
	 *
	 * @return bool
	 */
	private function should_sync_completed_packages() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		return in_array(
			$page,
			[
				'atbdp-settings',
				'tm/menu/main.php',
				'tm/menu/translations-queue.php',
				'wpml-package-management',
				'wpml-string-translation/menu/string-translation.php',
			],
			true
		);
	}

	/**
	 * Check required WPML APIs.
	 *
	 * @return bool
	 */
	private function is_wpml_active() {
		return defined( 'ICL_SITEPRESS_VERSION' )
			&& has_action( 'wpml_register_string' )
			&& has_filter( 'wpml_translate_string' );
	}
}
