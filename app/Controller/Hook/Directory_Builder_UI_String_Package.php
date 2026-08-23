<?php
/**
 * Directorist Directory Builder static UI string package integration.
 *
 * @package Directorist_WPML_Integration
 */

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Builder_UI_String_Package {

    const PACKAGE_KIND      = 'Directorist Directory Builder';
    const PACKAGE_KIND_SLUG = 'directorist-directory-builder';

    /**
     * Cache extracted strings per source directory.
     *
     * @var array
     */
    private static $string_cache = [];

    /**
     * Canonical values for normalized string names shared by distinct sources.
     *
     * @var array
     */
    private static $collision_value_cache = [];

    /**
     * Packages already registered during the current request.
     *
     * Avoid rebuilding and writing the same large package twice if WPML fires
     * its package-registration action more than once in a request.
     *
     * @var array
     */
    private static $registered_packages = [];

    /**
     * Completed package translations cached per package context and language.
     *
     * The builder data filter can touch many scalar labels in one request. Read
     * the completed ATE/package rows once and reuse them while Directorist builds
     * the admin Vue payload.
     *
     * @var array
     */
    private static $completed_translation_cache = [];

    /**
     * Completed package translations indexed by normalized source value.
     *
     * This lets runtime builder data reuse path-specific ATE/package strings
     * without doing extra per-label queries.
     *
     * @var array
     */
    private static $completed_translation_source_cache = [];

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'wpml_register_string_packages', [ $this, 'register_builder_ui_packages' ], 40 );
        add_action( 'directorist_after_update_directory_type', [ $this, 'refresh_builder_ui_labels_on_save' ], 40, 1 );
        add_action( 'directorist_builder_edit_assets_enqueued', [ $this, 'enqueue_builder_chrome_translation_bridge' ], 40 );
        add_filter( 'directorist_builder_localize_data', [ $this, 'translate_builder_ui_data' ], 20 );
    }

    /**
     * Register static builder UI labels as strings in the builder package.
     *
     * @return void
     */
    public function register_builder_ui_packages() {
        if ( ! $this->is_wpml_active() || ! $this->should_register_builder_ui_packages() ) {
            return;
        }

        foreach ( $this->get_source_directory_ids() as $directory_id ) {
            $package = $this->get_package( $directory_id );

            if ( empty( $package['name'] ) ) {
                continue;
            }

            $package_key = $package['kind_slug'] . ':' . $package['name'];
            if ( isset( self::$registered_packages[ $package_key ] ) ) {
                continue;
            }

            foreach ( $this->get_builder_ui_strings( $directory_id ) as $string ) {
                $this->register_package_string( $package, $string );
            }

            self::$registered_packages[ $package_key ] = true;
        }
    }

    /**
     * Refresh all visual builder UI strings after a directory save.
     *
     * @param int $directory_id Directory type term ID.
     * @return void
     */
    public function refresh_builder_ui_labels_on_save( $directory_id ) {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        $source_directory_id = $this->get_source_directory_id( (int) $directory_id );
        if ( $source_directory_id <= 0 ) {
            return;
        }

        // Package discovery can run before Directorist has finished preparing
        // every builder layout. Rebuild from the complete save-time payload.
        unset( self::$string_cache[ $source_directory_id ], self::$collision_value_cache[ $source_directory_id ] );

        $package = $this->get_package( $source_directory_id );

        if ( empty( $package['name'] ) ) {
            return;
        }

        foreach ( $this->get_builder_ui_strings( $source_directory_id ) as $string ) {
            $this->register_package_string( $package, $string );
        }
    }

    /**
     * Check whether this request should build WPML UI string packages.
     *
     * Directorist's builder widget files return arrays through require_once.
     * Calling Builder_Data on normal admin pages can consume those arrays before
     * the real Vue payload is prepared, leaving the field palette empty.
     *
     * @return bool
     */
    private function should_register_builder_ui_packages() {
        $default_language = apply_filters( 'wpml_default_language', null );
        $current_language = apply_filters( 'wpml_current_language', null );

        // The package is source content. Registering while WPML is switched to
        // a target language turns translated page and option labels into new
        // source strings and keeps the completed ATE job in an update loop.
        if ( $default_language && $current_language && $default_language !== $current_language ) {
            return false;
        }

        if ( wp_doing_ajax() ) {
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

            return false !== strpos( $action, 'wpml' ) || false !== strpos( $action, 'icl_' );
        }

        if ( ! is_admin() ) {
            return false;
        }

        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( '' === $page ) {
            return false;
        }

        foreach ( [ 'tm/', 'wpml', 'sitepress', 'icl_', 'translation', 'string-translation', 'packages' ] as $wpml_page_marker ) {
            if ( false !== strpos( $page, $wpml_page_marker ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Translate static builder UI strings before Directorist passes data to Vue.
     *
     * @param array $builder_data Builder data.
     * @return array
     */
    public function translate_builder_ui_data( $builder_data ) {
        if ( ! is_array( $builder_data ) || empty( $builder_data['id'] ) || ! $this->is_wpml_active() ) {
            return $builder_data;
        }

        $source_directory_id = $this->get_source_directory_id( (int) $builder_data['id'] );
        if ( $source_directory_id <= 0 ) {
            return $builder_data;
        }

        $source_builder_data = $this->get_source_builder_data( $source_directory_id, false );
        $collision_values    = $this->get_collision_values_for_data( $source_directory_id, ! empty( $source_builder_data ) ? $source_builder_data : $builder_data );
        $target_language  = $this->get_directory_language_code( (int) $builder_data['id'] );

        return $this->translate_data_recursively( $builder_data, $this->get_package( $source_directory_id ), [], $collision_values, $target_language, $source_builder_data );
    }

    /**
     * Translate a builder data array recursively.
     *
     * @param array $data             Builder data.
     * @param array $package          WPML package definition.
     * @param array $path             Current path.
     * @param array $collision_values Canonical values keyed by colliding string name.
     * @param string $target_language  Directory type language code.
     * @param array  $source_data      Source/default builder data at the same path.
     * @return array
     */
    private function translate_data_recursively( $data, $package, $path = [], $collision_values = [], $target_language = '', $source_data = [] ) {
        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );
            $source_value = is_array( $source_data ) && array_key_exists( $key, $source_data ) ? $source_data[ $key ] : null;

            if ( is_array( $value ) ) {
                $data[ $key ] = $this->translate_data_recursively(
                    $value,
                    $package,
                    $current_path,
                    $collision_values,
                    $target_language,
                    is_array( $source_value ) ? $source_value : []
                );
                continue;
            }

            if ( ! $this->is_translatable_ui_string( $key, $value, $current_path ) ) {
                continue;
            }

            $translation_source = $this->get_translation_source_ui_value( $key, $value, $source_value, $current_path, $target_language );
            if ( '' === $translation_source ) {
                continue;
            }

            $string       = $this->build_string_data( $current_path, $translation_source, $collision_values );
            $data[ $key ] = $this->translate_package_string( $translation_source, $string['name'], $package, $target_language );
        }

        return $data;
    }

    /**
     * Resolve the source/default value for a translatable builder UI scalar.
     *
     * @param string|int $key             Array key.
     * @param string     $value           Current target value.
     * @param mixed      $source_value    Source/default value at the same path.
     * @param array      $path            Current path.
     * @param string     $target_language Target language code.
     * @return string
     */
    private function get_translation_source_ui_value( $key, $value, $source_value, array $path, $target_language = '' ) {
        if ( is_string( $source_value ) && $this->is_translatable_ui_string( $key, $source_value, $path ) ) {
            return $source_value;
        }

        if ( $this->is_language_prefixed_placeholder( $value, $target_language ) ) {
            return $this->strip_language_prefixed_placeholder( $value, $target_language );
        }

        return is_string( $value ) ? $value : '';
    }

    /**
     * Get static builder UI strings for a directory type.
     *
     * @param int $source_directory_id Source directory type term ID.
     * @return array
     */
    private function get_builder_ui_strings( $source_directory_id ) {
        $source_directory_id = (int) $source_directory_id;

        if ( isset( self::$string_cache[ $source_directory_id ] ) ) {
            return self::$string_cache[ $source_directory_id ];
        }

        $builder_data     = $this->get_source_builder_data( $source_directory_id );
        $strings          = array_merge(
            $this->extract_strings( $builder_data ),
            $this->get_builder_chrome_bridge_string_data()
        );
        $collision_values = $this->build_collision_values( $strings );

        self::$collision_value_cache[ $source_directory_id ] = $collision_values;

        foreach ( $strings as &$string ) {
            $string = $this->apply_collision_safe_name( $string, $collision_values );
        }
        unset( $string );

        self::$string_cache[ $source_directory_id ] = $strings;

        return self::$string_cache[ $source_directory_id ];
    }

    /**
     * Translate Directorist's static Vue builder chrome after the app mounts.
     *
     * Most builder labels are already part of Directorist's localized builder
     * data and are translated in PHP. A few literals live only inside the Vue
     * bundle. This bridge is admin-builder-only, reads completed ATE/package
     * translations, and keeps public/frontend requests untouched.
     *
     * @return void
     */
    public function enqueue_builder_chrome_translation_bridge() {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        $directory_id = isset( $_GET['listing_type_id'] ) ? absint( $_GET['listing_type_id'] ) : 0;
        if ( $directory_id <= 0 ) {
            return;
        }

        $source_directory_id = $this->get_source_directory_id( $directory_id );
        $target_language     = $this->get_directory_language_code( $directory_id );

        if ( $source_directory_id <= 0 || '' === $target_language ) {
            return;
        }

        $package      = $this->get_package( $source_directory_id );
        $translations = [];

        foreach ( $this->get_builder_chrome_bridge_string_data() as $string ) {
            if ( empty( $string['value'] ) || empty( $string['name'] ) ) {
                continue;
            }

            $translated = $this->translate_package_string( $string['value'], $string['name'], $package, $target_language );

            if ( ! $this->strings_match_after_decoding( $string['value'], $translated ) ) {
                $translations[ $string['value'] ] = $translated;
            }
        }

        foreach ( $this->get_builder_chrome_translation_aliases() as $alias => $source_value ) {
            $string = $this->build_string_data( [ 'builder', 'chrome' ], $source_value );

            if ( empty( $string['name'] ) ) {
                continue;
            }

            $translated = $this->translate_package_string( $source_value, $string['name'], $package, $target_language );

            if ( ! $this->strings_match_after_decoding( $source_value, $translated ) ) {
                $translations[ $alias ] = $translated;
            }
        }

        if ( empty( $translations ) ) {
            return;
        }

        wp_add_inline_script(
            'directorist-multi-directory-builder',
            'window.directoristWpmlBuilderChromeTranslations = ' . wp_json_encode( $translations ) . ';' . $this->get_builder_chrome_translation_script(),
            'after'
        );
    }

    /**
     * Build package string rows for Directorist Vue literals outside PHP data.
     *
     * @return array
     */
    private function get_static_builder_chrome_string_data() {
        $strings = [];

        foreach ( $this->get_static_builder_chrome_strings() as $value ) {
            $strings[] = $this->build_string_data( [ 'builder', 'chrome' ], $value );
        }

        return $strings;
    }

    /**
     * Build package string metadata used by the admin chrome bridge.
     *
     * Some visible Vue labels are already registered from Directorist's builder
     * config payload, but still render outside the translated PHP-localized
     * payload. Keep those in the bridge list without registering duplicate
     * package rows.
     *
     * @return array
     */
    private function get_builder_chrome_bridge_string_data() {
        $strings = [];

        foreach ( $this->get_builder_chrome_bridge_strings() as $value ) {
            $strings[] = $this->build_string_data( [ 'builder', 'chrome' ], $value );
        }

        return $strings;
    }

    /**
     * Get static Directorist builder Vue chrome strings.
     *
     * @return array
     */
    private function get_static_builder_chrome_strings() {
        return [
            'Back',
            'Update',
            'Create Directory',
            'Click here to rename the directory.',
            'Form fields',
            'Learn',
            'Customize listing form',
            'Change Icon',
            'View the form',
            'Preview',
            'Listing form submit button text',
            'Simply drag a field here...',
            'Drop anywhere',
            'Configure Section',
            'Basic options',
            'Advanced options',
            'Confirm',
            'Are you sure?',
            'Yes',
            'Cancel',
            'Not Available',
        ];
    }

    /**
     * Get all labels covered by the admin-builder-only chrome bridge.
     *
     * @return array
     */
    private function get_builder_chrome_bridge_strings() {
        return array_values(
            array_unique(
                array_merge(
                    $this->get_static_builder_chrome_strings(),
                    [
                        'Set layout style',
                        'Choose your preferred appearance: Show preview image or hide preview image',
                        'Search Bar',
                        'Search Filter',
                        'Upload Image Here',
                        'Bottom Left',
                        'Bottom Right',
                        'Want to enable/disable Grid, List or Map views for the All Listings Page?',
                        'Go to settings',
                        'Listing Header',
                        'Listing Contents',
                        'Custom Single Listing Page',
                        'Listing Title',
                        'Enable Custom Single Listing Page',
                        'Enabling this option will replace the default single listing page. After enabling you must create and assign a new page with generated shortcodes to display single listing content',
                    ]
                )
            )
        );
    }

    /**
     * Map DOM-only literal variants to their canonical package source values.
     *
     * @return array
     */
    private function get_builder_chrome_translation_aliases() {
        return [
            'upload image here' => 'Upload Image Here',
        ];
    }

    /**
     * JavaScript used by the admin-builder-only chrome translation bridge.
     *
     * @return string
     */
    private function get_builder_chrome_translation_script() {
        return <<<'JS'
(function () {
    var translations = window.directoristWpmlBuilderChromeTranslations || {};
    var keys = Object.keys(translations);

    if (!keys.length) {
        return;
    }

    function translateText(value) {
        var trimmed = String(value || '').trim();

        if (Object.prototype.hasOwnProperty.call(translations, trimmed)) {
            return translations[trimmed];
        }

        var normalized = trimmed.replace(/\s+/g, ' ');
        return normalized !== trimmed && Object.prototype.hasOwnProperty.call(translations, normalized) ? translations[normalized] : null;
    }

    function replaceTextNode(node) {
        var translated = translateText(node.nodeValue);

        if (translated === null) {
            return;
        }

        var leading = (node.nodeValue.match(/^\s*/) || [''])[0];
        var trailing = (node.nodeValue.match(/\s*$/) || [''])[0];
        node.nodeValue = leading + translated + trailing;
    }

    function translateAttributes(root) {
        var attributeNames = ['title', 'aria-label', 'placeholder', 'data-tooltip'];
        var elements = root.querySelectorAll('*');

        elements.forEach(function (element) {
            attributeNames.forEach(function (attributeName) {
                if (!element.hasAttribute(attributeName)) {
                    return;
                }

                var translated = translateText(element.getAttribute(attributeName));

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

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentNode;

                if (!parent || ['SCRIPT', 'STYLE', 'TEXTAREA'].indexOf(parent.nodeName) !== -1) {
                    return NodeFilter.FILTER_REJECT;
                }

                return translateText(node.nodeValue) === null ? NodeFilter.FILTER_SKIP : NodeFilter.FILTER_ACCEPT;
            }
        });

        var nodes = [];
        var node;

        while ((node = walker.nextNode())) {
            nodes.push(node);
        }

        nodes.forEach(replaceTextNode);
        translateAttributes(root);
        translateCompositeElements(root);
    }

    function translateCompositeElements(root) {
        var elements = [
            {
                selector: '.cptm-preview-notice-text',
                source: 'Want to enable/disable Grid, List or Map views for the All Listings Page?'
            }
        ];

        elements.forEach(function (item) {
            var translated = translateText(item.source);

            if (translated === null) {
                return;
            }

            root.querySelectorAll(item.selector).forEach(function (element) {
                var text = String(element.innerText || element.textContent || '').replace(/\s+/g, ' ').trim();

                if (text === item.source) {
                    element.textContent = translated;
                }
            });
        });
    }

    function run() {
        translateRoot(document.getElementById('atbdp-cpt-manager'));
    }

    function observe() {
        var root = document.getElementById('atbdp-cpt-manager');

        if (!root || root.directoristWpmlChromeObserver) {
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
                run();
            });
        });

        observer.observe(root, { childList: true, subtree: true });
        root.directoristWpmlChromeObserver = observer;
    }

    function init() {
        run();
        observe();
    }

    if (window.DirectoristBuilder && typeof window.DirectoristBuilder.registerAfterMount === 'function') {
        window.DirectoristBuilder.registerAfterMount(init);
    }

    document.addEventListener('directorist:builder:mounted', init);

    if (document.readyState === 'complete') {
        init();
    }
})();
JS;
    }

    /**
     * Build Directorist builder data for the source directory.
     *
     * @param int $source_directory_id Source directory type term ID.
     * @return array
     */
    private function get_source_builder_data( $source_directory_id, $allow_prepared_data = true ) {
        if ( ! class_exists( '\Directorist\Multi_Directory\Builder_Data' ) ) {
            return [];
        }

        $prepared_data = $allow_prepared_data ? $this->get_prepared_builder_data() : [];

        if ( $this->has_builder_widget_data( $prepared_data ) ) {
            return $prepared_data;
        }

        $had_listing_type_id = isset( $_GET['listing_type_id'] );
        $old_listing_type_id = $had_listing_type_id ? $_GET['listing_type_id'] : null;

        $_GET['listing_type_id'] = (int) $source_directory_id;

        try {
            $builder_data = new \Directorist\Multi_Directory\Builder_Data();

            return [
                'fields'  => $builder_data->get_fields(),
                'layouts' => $builder_data->get_layouts(),
                'config'  => $builder_data->get_config(),
                'options' => $builder_data->get_options(),
            ];
        } finally {
            if ( $had_listing_type_id ) {
                $_GET['listing_type_id'] = $old_listing_type_id;
            } else {
                unset( $_GET['listing_type_id'] );
            }
        }
    }

    /**
     * Read Directorist's already-prepared builder data without re-running require_once includes.
     *
     * @return array
     */
    private function get_prepared_builder_data() {
        try {
            $reflection = new \ReflectionClass( '\Directorist\Multi_Directory\Builder_Data' );

            return [
                'fields'  => $this->get_static_property_value( $reflection, 'fields' ),
                'layouts' => $this->get_static_property_value( $reflection, 'layouts' ),
                'config'  => $this->get_static_property_value( $reflection, 'config' ),
                'options' => $this->get_static_property_value( $reflection, 'options' ),
            ];
        } catch ( \ReflectionException $exception ) {
            return [];
        }
    }

    /**
     * Get a protected static property from Builder_Data.
     *
     * @param \ReflectionClass $reflection Reflection instance.
     * @param string           $property   Property name.
     * @return array
     */
    private function get_static_property_value( \ReflectionClass $reflection, $property ) {
        if ( ! $reflection->hasProperty( $property ) ) {
            return [];
        }

        $property_reflection = $reflection->getProperty( $property );
        $property_reflection->setAccessible( true );
        $value = $property_reflection->getValue();

        return is_array( $value ) ? $value : [];
    }

    /**
     * Check whether builder data contains usable left sidebar widget definitions.
     *
     * @param array $builder_data Builder data.
     * @return bool
     */
    private function has_builder_widget_data( $builder_data ) {
        return ! empty( $builder_data['fields']['submission_form_fields']['widgets']['preset']['widgets'] )
            && is_array( $builder_data['fields']['submission_form_fields']['widgets']['preset']['widgets'] )
            && ! empty( $builder_data['fields']['submission_form_fields']['widgets']['custom']['widgets'] )
            && is_array( $builder_data['fields']['submission_form_fields']['widgets']['custom']['widgets'] );
    }

    /**
     * Extract translatable static builder UI strings.
     *
     * @param array $data Builder data.
     * @param array $path Current path.
     * @return array
     */
    private function extract_strings( $data, $path = [] ) {
        $strings = [];

        if ( ! is_array( $data ) ) {
            return $strings;
        }

        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );

            if ( is_array( $value ) ) {
                $strings = array_merge( $strings, $this->extract_strings( $value, $current_path ) );
                continue;
            }

            if ( ! $this->is_translatable_ui_string( $key, $value, $current_path ) ) {
                continue;
            }

            $strings[] = $this->build_string_data( $current_path, $value );
        }

        return $strings;
    }

    /**
     * Resolve collision data without repeating the extraction work in a request.
     *
     * @param int   $source_directory_id Source directory ID.
     * @param array $builder_data        Complete builder data.
     * @return array
     */
    private function get_collision_values_for_data( $source_directory_id, array $builder_data ) {
        if ( isset( self::$collision_value_cache[ $source_directory_id ] ) ) {
            return self::$collision_value_cache[ $source_directory_id ];
        }

        self::$collision_value_cache[ $source_directory_id ] = $this->build_collision_values(
            $this->extract_strings( $builder_data )
        );

        return self::$collision_value_cache[ $source_directory_id ];
    }

    /**
     * Choose one stable value for every normalized name shared by distinct strings.
     *
     * @param array $strings Extracted builder strings.
     * @return array
     */
    private function build_collision_values( array $strings ) {
        $values_by_name = [];

        foreach ( $strings as $string ) {
            if ( empty( $string['name'] ) || ! isset( $string['value'] ) || ! is_string( $string['value'] ) ) {
                continue;
            }

            $values_by_name[ $string['name'] ][ $string['value'] ] = true;
        }

        $collision_values = [];

        foreach ( $values_by_name as $name => $values ) {
            if ( count( $values ) < 2 ) {
                continue;
            }

            $source_values = array_keys( $values );
            sort( $source_values, SORT_STRING );

            $collision_values[ $name ] = $source_values[0];
        }

        return $collision_values;
    }

    /**
     * Keep the canonical value on the legacy name and hash only other variants.
     *
     * @param array $string           Builder string data.
     * @param array $collision_values Canonical values keyed by colliding string name.
     * @return array
     */
    private function apply_collision_safe_name( array $string, array $collision_values ) {
        if ( empty( $string['name'] ) || ! isset( $collision_values[ $string['name'] ] ) || $collision_values[ $string['name'] ] === $string['value'] ) {
            return $string;
        }

        $string['name'] = substr( $string['name'], 0, 111 ) . '_' . substr( md5( $string['value'] ), 0, 8 );

        return $string;
    }

    /**
     * Build WPML string metadata for a static builder UI string.
     *
     * @param array  $path             Builder data path.
     * @param string $value            Source string.
     * @param array  $collision_values Canonical values keyed by colliding string name.
     * @return array
     */
    private function build_string_data( $path, $value, $collision_values = [] ) {
        if ( $this->is_directory_name_value_path( $path ) ) {
            return [
                'name'  => 'directory_name',
                'title' => 'Directory Name',
                'type'  => 'LINE',
                'value' => $value,
            ];
        }

        $value_key = $this->safe_slug( wp_strip_all_tags( $value ) );
        $name      = 'builder_ui__' . $value_key;

        if ( strlen( $name ) > 120 ) {
            $name = 'builder_ui__' . substr( $value_key, 0, 80 ) . '_' . substr( md5( $value ), 0, 8 );
        }

        return $this->apply_collision_safe_name(
            [
                'name'  => $name,
                'title' => 'Builder UI: ' . implode( ' > ', array_map( [ $this, 'humanize_path_part' ], $path ) ),
                'type'  => strlen( wp_strip_all_tags( $value ) ) > 120 ? 'AREA' : 'LINE',
                'value' => $value,
            ],
            $collision_values
        );
    }

    /**
     * Register a WPML package string.
     *
     * @param array $package Package data.
     * @param array $string  String data.
     * @return void
     */
    private function register_package_string( $package, $string ) {
        if ( empty( $string['value'] ) || ! is_string( $string['value'] ) ) {
            return;
        }

        Directory_Builder_String_Package::track_package_string( $package, $string['name'], $string );

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
    }

    /**
     * Translate a package string.
     *
     * @param string $value           Source value.
     * @param string $string_name     String name.
     * @param array  $package         Package data.
     * @param string $target_language Explicit target language.
     * @return string
     */
    private function translate_package_string( $value, $string_name, $package, $target_language = '' ) {
        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return $value;
        }

        if ( $target_language && ! empty( $package['kind_slug'] ) && ! empty( $package['name'] ) ) {
            $completed_translations = $this->get_completed_package_translations( $package, $target_language );

            if ( ! empty( $completed_translations[ $string_name ] ) ) {
                $translation = $completed_translations[ $string_name ];

                if (
                    ! empty( $translation['translation'] )
                    && is_string( $translation['translation'] )
                    && ! $this->strings_match_after_decoding( $value, $translation['translation'] )
                    && ! $this->is_language_prefixed_placeholder( $translation['translation'], $target_language )
                    && (
                        empty( $translation['source'] )
                        || $this->strings_match_after_decoding( $value, $translation['source'] )
                    )
                ) {
                    return $translation['translation'];
                }
            }

            $translation_by_source = $this->get_completed_package_translation_by_source_value( $package, $value, $target_language );
            if ( '' !== $translation_by_source ) {
                return $translation_by_source;
            }
        }

        $error_level = error_reporting();
        error_reporting( $error_level & ~E_WARNING & ~E_DEPRECATED );

        try {
            if ( $target_language && function_exists( 'icl_translate' ) && ! empty( $package['kind_slug'] ) && ! empty( $package['name'] ) ) {
                $has_translation = null;
                $translated      = \icl_translate(
                    $package['kind_slug'] . '-' . $package['name'],
                    $string_name,
                    $value,
                    false,
                    $has_translation,
                    $target_language
                );
            } else {
                $translated = apply_filters( 'wpml_translate_string', $value, $string_name, $package );
            }
        } finally {
            error_reporting( $error_level );
        }

        return is_string( $translated ) && '' !== trim( $translated ) && ! $this->is_language_prefixed_placeholder( $translated, $target_language ) ? $translated : $value;
    }

    /**
     * Resolve a completed package translation by matching the source value.
     *
     * Runtime builder payloads sometimes expose the same visual label through a
     * value-based key while the ATE package registered the original builder path.
     * This fallback stays package/language scoped and ignores ambiguous values.
     *
     * @param array  $package       Package data.
     * @param string $source_value  Source value.
     * @param string $language_code Target language.
     * @return string
     */
    private function get_completed_package_translation_by_source_value( $package, $source_value, $language_code ) {
        if ( ! is_string( $source_value ) || '' === trim( $source_value ) || empty( $package['kind_slug'] ) || empty( $package['name'] ) || empty( $language_code ) ) {
            return '';
        }

        $context   = $package['kind_slug'] . '-' . $package['name'];
        $cache_key = $context . ':' . $language_code;

        if ( ! isset( self::$completed_translation_source_cache[ $cache_key ] ) ) {
            self::$completed_translation_source_cache[ $cache_key ] = $this->build_completed_translation_source_map(
                $this->get_completed_package_translations( $package, $language_code ),
                $language_code
            );
        }

        $lookup_key = $this->get_translation_source_lookup_key( $source_value );

        return '' !== $lookup_key && ! empty( self::$completed_translation_source_cache[ $cache_key ][ $lookup_key ] )
            ? self::$completed_translation_source_cache[ $cache_key ][ $lookup_key ]
            : '';
    }

    /**
     * Build a source-value lookup map from completed package translations.
     *
     * @param array  $translations  Completed translations keyed by string name.
     * @param string $language_code Target language.
     * @return array
     */
    private function build_completed_translation_source_map( array $translations, $language_code ) {
        $source_map = [];
        $ambiguous  = [];

        foreach ( $translations as $translation ) {
            if ( empty( $translation['source'] ) || empty( $translation['translation'] ) || ! is_string( $translation['source'] ) || ! is_string( $translation['translation'] ) ) {
                continue;
            }

            $lookup_key = $this->get_translation_source_lookup_key( $translation['source'] );
            if ( '' === $lookup_key || isset( $ambiguous[ $lookup_key ] ) || $this->is_language_prefixed_placeholder( $translation['translation'], $language_code ) ) {
                continue;
            }

            if ( isset( $source_map[ $lookup_key ] ) && $source_map[ $lookup_key ] !== $translation['translation'] ) {
                unset( $source_map[ $lookup_key ] );
                $ambiguous[ $lookup_key ] = true;
                continue;
            }

            $source_map[ $lookup_key ] = $translation['translation'];
        }

        return $source_map;
    }

    /**
     * Normalize a source value for lookup.
     *
     * @param string $value Source value.
     * @return string
     */
    private function get_translation_source_lookup_key( $value ) {
        if ( ! is_string( $value ) ) {
            return '';
        }

        $charset = get_bloginfo( 'charset' );
        $value   = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, $charset ) ) );

        if ( '' === $value ) {
            return '';
        }

        return md5( preg_replace( '/\s+/', ' ', $value ) );
    }

    /**
     * Read completed package translations for a language.
     *
     * This is intentionally scoped to the current builder package/language and
     * only runs on the admin builder data path. It does not write to WPML tables
     * and keeps public requests untouched.
     *
     * @param array  $package       Package data.
     * @param string $language_code Target language.
     * @return array
     */
    private function get_completed_package_translations( $package, $language_code ) {
        global $wpdb;

        if ( empty( $package['kind_slug'] ) || empty( $package['name'] ) || empty( $language_code ) ) {
            return [];
        }

        $context   = $package['kind_slug'] . '-' . $package['name'];
        $cache_key = $context . ':' . $language_code;

        if ( isset( self::$completed_translation_cache[ $cache_key ] ) ) {
            return self::$completed_translation_cache[ $cache_key ];
        }

        $complete_status = defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10;
        $rows            = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.name, s.value AS source_value, st.value AS translated_value
                FROM {$wpdb->prefix}icl_strings s
                INNER JOIN {$wpdb->prefix}icl_string_translations st
                    ON st.string_id = s.id
                    AND st.language = %s
                WHERE s.context = %s
                    AND st.status = %d
                    AND st.value <> ''",
                $language_code,
                $context,
                $complete_status
            )
        );

        $translations = [];

        foreach ( $rows as $row ) {
            if (
                empty( $row->name )
                || ! is_string( $row->translated_value )
                || '' === trim( $row->translated_value )
                || $this->strings_match_after_decoding( $row->source_value, $row->translated_value )
                || $this->is_language_prefixed_placeholder( $row->translated_value, $language_code )
            ) {
                continue;
            }

            $translations[ $row->name ] = [
                'source'      => is_string( $row->source_value ) ? $row->source_value : '',
                'translation' => $row->translated_value,
            ];
        }

        self::$completed_translation_cache[ $cache_key ] = $translations;

        return self::$completed_translation_cache[ $cache_key ];
    }

    /**
     * Compare strings after decoding HTML entities and trimming whitespace.
     *
     * @param string $left  First value.
     * @param string $right Second value.
     * @return bool
     */
    private function strings_match_after_decoding( $left, $right ) {
        $charset = get_bloginfo( 'charset' );

        $left  = trim( html_entity_decode( (string) $left, ENT_QUOTES | ENT_HTML5, $charset ) );
        $right = trim( html_entity_decode( (string) $right, ENT_QUOTES | ENT_HTML5, $charset ) );

        return $left === $right;
    }

    /**
     * Detect QA/import placeholders such as "NL: Title" for the target language.
     *
     * @param mixed  $value         Value to check.
     * @param string $language_code Target language code.
     * @return bool
     */
    private function is_language_prefixed_placeholder( $value, $language_code = '' ) {
        if ( ! is_string( $value ) || '' === trim( $value ) || '' === trim( $language_code ) ) {
            return false;
        }

        $charset         = get_bloginfo( 'charset' );
        $value           = trim( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, $charset ) );
        $language_prefix = strtoupper( str_replace( '_', '-', trim( $language_code ) ) );

        return 0 === strpos( $value, $language_prefix . ': ' )
            || 0 === strpos( $value, $language_prefix . '-QA: ' );
    }

    /**
     * Strip the target-language QA/import prefix from a placeholder value.
     *
     * @param string $value         Placeholder value.
     * @param string $language_code Target language code.
     * @return string
     */
    private function strip_language_prefixed_placeholder( $value, $language_code = '' ) {
        if ( ! $this->is_language_prefixed_placeholder( $value, $language_code ) ) {
            return is_string( $value ) ? $value : '';
        }

        $charset         = get_bloginfo( 'charset' );
        $value           = trim( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, $charset ) );
        $language_prefix = preg_quote( strtoupper( str_replace( '_', '-', trim( $language_code ) ) ), '/' );

        return trim( preg_replace( '/^' . $language_prefix . '(?:-QA)?:\s*/', '', $value ) );
    }

    /**
     * Check if a scalar builder config value is user-facing text.
     *
     * @param string|int $key   Array key.
     * @param mixed      $value Value.
     * @param array      $path  Current path.
     * @return bool
     */
    private function is_translatable_ui_string( $key, $value, $path = [] ) {
        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return false;
        }

        $trimmed_value = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );

        if ( '' === $trimmed_value || is_numeric( $trimmed_value ) || in_array( strtolower( $trimmed_value ), [ 'true', 'false', 'yes', 'no', 'on', 'off' ], true ) ) {
            return false;
        }

        if ( filter_var( $trimmed_value, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $key  = $this->safe_slug( $key );
        $path = array_map( [ $this, 'safe_slug' ], (array) $path );

        if ( preg_match( '/\b(la|las|lar|lab|fa|fas|far|fab)\s+[a-z0-9_-]+\b/i', $trimmed_value ) ) {
            return false;
        }

        if ( in_array( 'icon', $path, true ) && in_array( $key, [ 'placeholder', 'value' ], true ) ) {
            return false;
        }

        if ( 'value' === $key ) {
            return $this->is_textual_value_path( $path );
        }

        $allowed_keys = [
            'add_new_group_button_label',
            'button_label',
            'button_text',
            'default_group_label',
            'description',
            'label',
            'model_header_text',
            'placeholder',
            'restricted_fields_warning_text',
            'select_files_label',
            'text',
            'title',
        ];

        if ( in_array( $key, $allowed_keys, true ) ) {
            return true;
        }

        foreach ( [ 'label', 'placeholder', 'description', 'text', 'title', 'heading', 'message', 'notice' ] as $suffix ) {
            if ( strlen( $key ) > strlen( $suffix ) && substr( $key, -strlen( $suffix ) ) === $suffix ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a generic value entry stores display text.
     *
     * @param array $path Sanitized path including the value key.
     * @return bool
     */
    private function is_textual_value_path( $path ) {
        if ( empty( $path ) || 'value' !== end( $path ) || count( $path ) < 2 ) {
            return false;
        }

        if ( $this->is_directory_name_value_path( $path ) ) {
            return true;
        }

        $parent_key = $path[ count( $path ) - 2 ];

        return in_array(
            $parent_key,
            [
                'button_label',
                'cancel_button_label',
                'confirm_button_label',
                'default_group_label',
                'description',
                'heading',
                'label',
                'model_header_text',
                'placeholder',
                'search_button_text',
                'section_title',
                'select_files_label',
                'show_readmore_text',
                'submit_button_label',
                'text',
                'title',
            ],
            true
        );
    }

    /**
     * Check for Directorist's directory display name path.
     *
     * @param array $path Sanitized or raw path including the value key.
     * @return bool
     */
    private function is_directory_name_value_path( $path ) {
        $path = array_map( [ $this, 'safe_slug' ], (array) $path );

        return count( $path ) >= 3
            && 'options' === $path[0]
            && 'name' === $path[ count( $path ) - 2 ]
            && 'value' === end( $path );
    }

    /**
     * Get source directory IDs in the default language.
     *
     * @return array
     */
    private function get_source_directory_ids() {
        global $wpdb;

        $taxonomy         = $this->get_directory_taxonomy();
        $element_type     = WPML_Helper::get_wpml_element_type( $taxonomy );
        $default_language = apply_filters( 'wpml_default_language', null );

        if ( empty( $taxonomy ) || empty( $element_type ) || empty( $default_language ) ) {
            return [];
        }

        return wp_parse_id_list(
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT tt.term_id
                    FROM {$wpdb->term_taxonomy} tt
                    LEFT JOIN {$wpdb->prefix}icl_translations tr
                        ON tr.element_id = tt.term_taxonomy_id
                        AND tr.element_type = %s
                    WHERE tt.taxonomy = %s
                        AND ( tr.language_code = %s OR tr.language_code IS NULL )",
                    $element_type,
                    $taxonomy,
                    $default_language
                )
            )
        );
    }

    /**
     * Get the default-language source directory type for a term.
     *
     * @param int $directory_id Directory type term ID.
     * @return int
     */
    private function get_source_directory_id( $directory_id ) {
        $default_language = apply_filters( 'wpml_default_language', null );
        $translations     = WPML_Helper::get_element_translations( (int) $directory_id, $this->get_directory_taxonomy() );

        if ( ! empty( $default_language ) && ! empty( $translations[ $default_language ]->term_id ) ) {
            return (int) $translations[ $default_language ]->term_id;
        }

        return (int) $directory_id;
    }

    /**
     * Get the language assigned to the directory term currently being edited.
     *
     * WPML's admin content switcher can point at a translated term while its
     * global string language still resolves to the default language. Passing
     * the term language explicitly keeps package lookups aligned with the
     * builder that Directorist is rendering.
     *
     * @param int $directory_id Directory type term ID.
     * @return string
     */
    private function get_directory_language_code( $directory_id ) {
        $language_info = WPML_Helper::get_element_language_info( (int) $directory_id, $this->get_directory_taxonomy() );

        if ( ! is_object( $language_info ) || empty( $language_info->language_code ) ) {
            return '';
        }

        return sanitize_key( $language_info->language_code );
    }

    /**
     * Build a WPML package definition for a source directory type.
     *
     * @param int $source_directory_id Source directory type term ID.
     * @return array
     */
    private function get_package( $source_directory_id ) {
        $term = get_term( (int) $source_directory_id, $this->get_directory_taxonomy() );
        $trid = WPML_Helper::get_element_trid( (int) $source_directory_id, $this->get_directory_taxonomy() );

        return [
            'kind'      => self::PACKAGE_KIND,
            'kind_slug' => self::PACKAGE_KIND_SLUG,
            'name'      => $trid > 0 ? 'directory_builder_' . $trid : 'directory_builder_' . (int) $source_directory_id,
            'title'     => sprintf(
                'Directorist Directory Builder: %s',
                ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) ? $term->name : (int) $source_directory_id
            ),
            'edit_link' => admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-directory-types&listing_type_id=' . (int) $source_directory_id . '&action=edit' ),
            'view_link' => home_url( '/' ),
        ];
    }

    /**
     * Check if WPML string package APIs are available.
     *
     * @return bool
     */
    private function is_wpml_active() {
        return defined( 'ICL_SITEPRESS_VERSION' )
            && has_action( 'wpml_register_string' )
            && has_filter( 'wpml_translate_string' )
            && has_filter( 'wpml_object_id' );
    }

    /**
     * Get Directorist directory type taxonomy key.
     *
     * @return string
     */
    private function get_directory_taxonomy() {
        return defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';
    }

    /**
     * Sanitize a path segment for stable WPML string names.
     *
     * @param string $value Raw segment.
     * @return string
     */
    private function safe_slug( $value ) {
        $value = strtolower( (string) $value );
        $value = preg_replace( '/[^a-z0-9_]+/', '_', $value );
        $value = trim( $value, '_' );

        return '' === $value ? 'item' : $value;
    }

    /**
     * Convert a path segment into a readable title piece.
     *
     * @param string $value Raw path segment.
     * @return string
     */
    private function humanize_path_part( $value ) {
        return ucwords( str_replace( '_', ' ', $this->safe_slug( $value ) ) );
    }
}
