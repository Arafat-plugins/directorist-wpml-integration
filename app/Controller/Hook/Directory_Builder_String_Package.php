<?php
/**
 * Directorist Directory Builder string package integration.
 *
 * Exposes Directorist Directory Builder labels/placeholders as WPML string
 * packages so they can be sent through Translation Dashboard/ATE instead of
 * requiring users to manually edit each translated directory type.
 *
 * @package Directorist_WPML_Integration
 */

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Builder_String_Package {

    const PACKAGE_KIND      = 'Directorist Directory Builder';
    const PACKAGE_KIND_SLUG = 'directorist-directory-builder';

    /**
     * Directory type term meta keys that store builder-controlled UI text.
     *
     * @var array
     */
    private $builder_meta_keys = [
        'submission_form_fields',
        'search_form_fields',
        'single_listings_contents',
        'listings_card_grid_view',
        'listings_card_list_view',
        'submit_button_label',
    ];

    /**
     * Add-listing template strings that are not stored in builder meta.
     *
     * @var array
     */
    private $template_ui_strings = [
        'add_listing_wizard_finish'     => [
            'value' => 'Finish',
            'title' => 'Add Listing Wizard: Finish button',
        ],
        'add_listing_wizard_save_next'  => [
            'value' => 'Save & Next',
            'title' => 'Add Listing Wizard: Save and next button',
        ],
        'add_listing_wizard_go_to_next' => [
            'value' => 'Go to Next',
            'title' => 'Add Listing Wizard: Next aria label',
        ],
    ];

    /**
     * Prevent recursive term meta lookups.
     *
     * @var bool
     */
    private static $resolving_term_meta = false;

    /**
     * Track packages registered during a request.
     *
     * @var array
     */
    private static $registered_packages = [];

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'wpml_active_string_package_kinds', [ $this, 'register_package_kind' ] );
        add_action( 'init', [ $this, 'register_directory_builder_packages' ], 30 );
        add_action( 'wpml_register_string_packages', [ $this, 'register_directory_builder_packages' ] );

        add_action( 'created_' . $this->get_directory_taxonomy(), [ $this, 'register_term_package_on_save' ], 30, 2 );
        add_action( 'edited_' . $this->get_directory_taxonomy(), [ $this, 'register_term_package_on_save' ], 30, 2 );
        add_action( 'directorist_after_update_directory_type', [ $this, 'refresh_directory_builder_package_on_save' ], 30, 1 );

        add_filter( 'get_term_metadata', [ $this, 'translate_builder_term_meta' ], 30, 4 );
        add_filter( 'atbdp_add_listing_page_template', [ $this, 'translate_add_listing_template_ui' ], 20, 2 );
    }

    /**
     * Register package kind for WPML Translation Dashboard.
     *
     * @param array $kinds Package kind definitions.
     * @return array
     */
    public function register_package_kind( $kinds ) {
        $kinds[ self::PACKAGE_KIND_SLUG ] = [
            'title'  => self::PACKAGE_KIND,
            'slug'   => self::PACKAGE_KIND_SLUG,
            'plural' => 'Directorist Directory Builders',
        ];

        return $kinds;
    }

    /**
     * Register all default-language directory builder packages.
     *
     * @return void
     */
    public function register_directory_builder_packages() {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
            return;
        }

        foreach ( $this->get_source_directory_ids() as $directory_id ) {
            $this->register_directory_builder_package( $directory_id );
        }
    }

    /**
     * Register package after a directory term is created/edited.
     *
     * @param int $term_id Term ID.
     * @param int $tt_id   Term taxonomy ID.
     * @return void
     */
    public function register_term_package_on_save( $term_id, $tt_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $this->refresh_directory_builder_package_on_save( $term_id );
    }

    /**
     * Refresh package strings after Directorist saves directory builder data.
     *
     * @param int $directory_id Directory type term ID.
     * @return void
     */
    public function refresh_directory_builder_package_on_save( $directory_id ) {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        $source_directory_id = $this->get_source_directory_id( (int) $directory_id );
        if ( $source_directory_id <= 0 ) {
            return;
        }

        $package = $this->get_package( $source_directory_id );
        if ( ! empty( $package['name'] ) ) {
            unset( self::$registered_packages[ $package['name'] ] );
        }

        $this->register_directory_builder_package( $source_directory_id );
    }

    /**
     * Register all translatable builder strings for one directory type.
     *
     * @param int $directory_id Directory type term ID.
     * @return void
     */
    public function register_directory_builder_package( $directory_id ) {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        $source_directory_id = $this->get_source_directory_id( (int) $directory_id );
        if ( $source_directory_id <= 0 ) {
            return;
        }

        $package = $this->get_package( $source_directory_id );
        if ( empty( $package['name'] ) || isset( self::$registered_packages[ $package['name'] ] ) ) {
            return;
        }

        self::$registered_packages[ $package['name'] ] = true;

        do_action( 'wpml_start_string_package_registration', $package );

        foreach ( $this->builder_meta_keys as $meta_key ) {
            $meta_value = $this->get_raw_term_meta( $source_directory_id, $meta_key, true );

            if ( is_array( $meta_value ) ) {
                foreach ( $this->extract_strings( $meta_value, $meta_key ) as $string ) {
                    $this->register_package_string( $package, $string );
                }

                continue;
            }

            if ( $this->is_translatable_string( $meta_key, $meta_value ) ) {
                $this->register_package_string(
                    $package,
                    $this->build_string_data( $meta_key, [ $meta_key ], $meta_value )
                );
            }
        }

        foreach ( $this->template_ui_strings as $string_name => $string_data ) {
            if ( empty( $string_data['value'] ) || ! is_string( $string_data['value'] ) ) {
                continue;
            }

            $this->register_package_string(
                $package,
                [
                    'name'  => $string_name,
                    'title' => $string_data['title'],
                    'type'  => 'LINE',
                    'value' => $string_data['value'],
                ]
            );
        }

        do_action( 'wpml_delete_unused_package_strings', $package );
    }

    /**
     * Translate Directorist builder term meta before Directorist renders it.
     *
     * @param mixed  $value     Existing metadata value.
     * @param int    $object_id Term ID.
     * @param string $meta_key  Meta key.
     * @param bool   $single    Whether a single value was requested.
     * @return mixed
     */
    public function translate_builder_term_meta( $value, $object_id, $meta_key, $single ) {
        if ( self::$resolving_term_meta || ! $single || ! in_array( $meta_key, $this->builder_meta_keys, true ) || ! $this->is_wpml_active() ) {
            return $value;
        }

        $object_id = (int) $object_id;
        if ( $object_id <= 0 || ! $this->is_directory_type_term( $object_id ) ) {
            return $value;
        }

        $meta_value = $this->normalize_filtered_meta_value( $value );

        if ( null === $meta_value ) {
            self::$resolving_term_meta = true;
            $meta_value                = get_term_meta( $object_id, $meta_key, true );
            self::$resolving_term_meta = false;
        }

        if ( ! is_array( $meta_value ) && ! $this->is_translatable_string( $meta_key, $meta_value ) ) {
            return $value;
        }

        $source_directory_id = $this->get_source_directory_id( $object_id );
        if ( $source_directory_id <= 0 ) {
            return $value;
        }

        if ( is_array( $meta_value ) ) {
            $translated_value = $this->translate_meta_value( $meta_value, $source_directory_id, $meta_key );

            return [ $translated_value ];
        }

        $string = $this->build_string_data( $meta_key, [ $meta_key ], $meta_value );

        return $this->translate_package_string( $meta_value, $string['name'], $this->get_package( $source_directory_id ) );
    }

    /**
     * Translate Add Listing wizard strings that are not stored in term meta.
     *
     * @param string $template_output Rendered Add Listing template output.
     * @param array  $args            Template arguments.
     * @return string
     */
    public function translate_add_listing_template_ui( $template_output, $args ) {
        if ( empty( $template_output ) || ! is_string( $template_output ) || ! $this->is_wpml_active() ) {
            return $template_output;
        }

        $directory_id = $this->get_directory_id_from_template_args( $args );
        if ( $directory_id <= 0 ) {
            return $template_output;
        }

        $source_directory_id = $this->get_source_directory_id( $directory_id );
        if ( $source_directory_id <= 0 ) {
            return $template_output;
        }

        $package = $this->get_package( $source_directory_id );

        foreach ( $this->template_ui_strings as $string_name => $string_data ) {
            $translated = $this->translate_package_string( $string_data['value'], $string_name, $package );

            if ( empty( $translated ) || $translated === $string_data['value'] ) {
                continue;
            }

            $template_output = str_replace( '>' . $string_data['value'] . '<', '>' . $translated . '<', $template_output );
            $template_output = str_replace( '>' . $string_data['value'], '>' . $translated, $template_output );
            $template_output = str_replace( '"' . $string_data['value'] . '"', '"' . esc_attr( $translated ) . '"', $template_output );
        }

        return $template_output;
    }

    /**
     * Translate all translatable strings in a builder meta array.
     *
     * @param array  $data                Builder meta value.
     * @param int    $source_directory_id Source directory type term ID.
     * @param string $meta_key            Meta key.
     * @param array  $path                Current nested path.
     * @return array
     */
    private function translate_meta_value( $data, $source_directory_id, $meta_key, $path = [] ) {
        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );

            if ( is_array( $value ) ) {
                $data[ $key ] = $this->translate_meta_value( $value, $source_directory_id, $meta_key, $current_path );
                continue;
            }

            if ( ! $this->is_translatable_string( $key, $value ) ) {
                continue;
            }

            $string = $this->build_string_data( $meta_key, $current_path, $value );
            $data[ $key ] = $this->translate_package_string( $value, $string['name'], $this->get_package( $source_directory_id ) );
        }

        return $data;
    }

    /**
     * Translate a package string while preserving the source value as fallback.
     *
     * @param string $value       Source value.
     * @param string $string_name WPML string name.
     * @param array  $package     WPML package definition.
     * @return string
     */
    private function translate_package_string( $value, $string_name, $package ) {
        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return $value;
        }

        $error_level = error_reporting();
        error_reporting( $error_level & ~E_WARNING & ~E_DEPRECATED );

        try {
            $translated = apply_filters( 'wpml_translate_string', $value, $string_name, $package );
        } finally {
            error_reporting( $error_level );
        }

        if ( ! is_string( $translated ) || '' === trim( $translated ) ) {
            return $value;
        }

        return $translated;
    }

    /**
     * Register a single builder string in a WPML package.
     *
     * @param array $package WPML package definition.
     * @param array $string  String data.
     * @return void
     */
    private function register_package_string( $package, $string ) {
        if ( empty( $string['value'] ) || ! is_string( $string['value'] ) ) {
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
    }

    /**
     * Resolve the current directory ID from Add Listing template args.
     *
     * @param array $args Template args.
     * @return int
     */
    private function get_directory_id_from_template_args( $args ) {
        if ( ! empty( $args['single_directory'] ) ) {
            return (int) $args['single_directory'];
        }

        if ( ! empty( $args['listing_form'] ) && is_object( $args['listing_form'] ) && method_exists( $args['listing_form'], 'get_current_listing_type' ) ) {
            return (int) $args['listing_form']->get_current_listing_type();
        }

        if ( ! empty( $_REQUEST['directory_type'] ) ) {
            $directory_type = sanitize_text_field( wp_unslash( $_REQUEST['directory_type'] ) );

            if ( is_numeric( $directory_type ) ) {
                return (int) $directory_type;
            }

            $term = get_term_by( 'slug', $directory_type, $this->get_directory_taxonomy() );
            if ( $term && ! is_wp_error( $term ) ) {
                return (int) $term->term_id;
            }
        }

        return function_exists( 'directorist_get_default_directory' ) ? (int) directorist_get_default_directory() : 0;
    }

    /**
     * Extract translatable strings from a builder meta array.
     *
     * @param array  $data     Builder meta value.
     * @param string $meta_key Meta key.
     * @param array  $path     Current nested path.
     * @return array
     */
    private function extract_strings( $data, $meta_key, $path = [] ) {
        $strings = [];

        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );

            if ( is_array( $value ) ) {
                $strings = array_merge( $strings, $this->extract_strings( $value, $meta_key, $current_path ) );
                continue;
            }

            if ( ! $this->is_translatable_string( $key, $value ) ) {
                continue;
            }

            $strings[] = $this->build_string_data( $meta_key, $current_path, $value );
        }

        return $strings;
    }

    /**
     * Build WPML string metadata.
     *
     * @param string $meta_key Meta key.
     * @param array  $path     Nested array path.
     * @param string $value    Original string value.
     * @return array
     */
    private function build_string_data( $meta_key, $path, $value ) {
        $path_key = implode( '__', array_map( [ $this, 'safe_slug' ], $path ) );
        $name     = 'builder_' . $this->safe_slug( $meta_key ) . '__' . $path_key;

        if ( strlen( $name ) > 150 ) {
            $name = 'builder_' . $this->safe_slug( $meta_key ) . '__' . md5( implode( '/', $path ) );
        }

        $title = $this->humanize_meta_key( $meta_key ) . ': ' . implode( ' > ', array_map( [ $this, 'humanize_path_part' ], $path ) );
        $type  = strlen( wp_strip_all_tags( $value ) ) > 120 ? 'AREA' : 'LINE';

        return [
            'name'  => $name,
            'title' => $title,
            'type'  => $type,
            'value' => $value,
        ];
    }

    /**
     * Check whether a scalar value should be exposed for translation.
     *
     * @param string|int $key   Array key.
     * @param mixed      $value Value.
     * @return bool
     */
    private function is_translatable_string( $key, $value ) {
        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return false;
        }

        $key = $this->safe_slug( $key );

        $blocked_keys = [
            'active_template',
            'align',
            'can_move',
            'date_type',
            'field_key',
            'hook',
            'icon',
            'lock',
            'only_for_admin',
            'required',
            'type',
            'value',
            'widget_group',
            'widget_key',
            'widget_name',
            'option_value',
        ];

        if ( in_array( $key, $blocked_keys, true ) ) {
            return false;
        }

        $allowed_keys = [
            'button_label',
            'description',
            'heading',
            'label',
            'option_label',
            'placeholder',
            'search_button_label',
            'search_button_text',
            'show_readmore_text',
            'submit_button_label',
            'text',
            'title',
        ];

        if ( in_array( $key, $allowed_keys, true ) ) {
            return true;
        }

        foreach ( [ '_label', '_placeholder', '_description', '_text', '_title', '_heading' ] as $suffix ) {
            if ( strlen( $key ) > strlen( $suffix ) && substr( $key, -strlen( $suffix ) ) === $suffix ) {
                return true;
            }
        }

        return false;
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
        $directory_id = (int) $directory_id;

        if ( $directory_id <= 0 ) {
            return 0;
        }

        $default_language = apply_filters( 'wpml_default_language', null );
        $translations     = WPML_Helper::get_element_translations( $directory_id, $this->get_directory_taxonomy() );

        if ( ! empty( $default_language ) && ! empty( $translations[ $default_language ]->term_id ) ) {
            return (int) $translations[ $default_language ]->term_id;
        }

        return $directory_id;
    }

    /**
     * Build a WPML package definition for a source directory type.
     *
     * @param int $source_directory_id Source directory type term ID.
     * @return array
     */
    private function get_package( $source_directory_id ) {
        $source_directory_id = (int) $source_directory_id;
        $term                = get_term( $source_directory_id, $this->get_directory_taxonomy() );
        $trid                = WPML_Helper::get_element_trid( $source_directory_id, $this->get_directory_taxonomy() );

        $name = $trid > 0 ? 'directory_builder_' . $trid : 'directory_builder_' . $source_directory_id;

        return [
            'kind'      => self::PACKAGE_KIND,
            'kind_slug' => self::PACKAGE_KIND_SLUG,
            'name'      => $name,
            'title'     => sprintf(
                'Directorist Directory Builder: %s',
                ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) ? $term->name : $source_directory_id
            ),
            'edit_link' => admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-directory-types&listing_type_id=' . $source_directory_id . '&action=edit' ),
            'view_link' => home_url( '/' ),
        ];
    }

    /**
     * Get raw term meta without triggering metadata filters.
     *
     * @param int    $term_id  Term ID.
     * @param string $meta_key Meta key.
     * @param bool   $single   Whether to return a single value.
     * @return mixed
     */
    private function get_raw_term_meta( $term_id, $meta_key, $single = true ) {
        global $wpdb;

        $values = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s ORDER BY meta_id ASC",
                (int) $term_id,
                $meta_key
            )
        );

        if ( empty( $values ) ) {
            return $single ? '' : [];
        }

        $values = array_map( 'maybe_unserialize', $values );

        return $single ? $values[0] : $values;
    }

    /**
     * Normalize a value coming from the get_term_metadata filter.
     *
     * @param mixed $value Filtered metadata value.
     * @return mixed|null
     */
    private function normalize_filtered_meta_value( $value ) {
        if ( null === $value ) {
            return null;
        }

        if ( is_array( $value ) && array_key_exists( 0, $value ) ) {
            return $value[0];
        }

        return $value;
    }

    /**
     * Check if a term is a Directorist directory type.
     *
     * @param int $term_id Term ID.
     * @return bool
     */
    private function is_directory_type_term( $term_id ) {
        $term = get_term( (int) $term_id );

        return $term && ! is_wp_error( $term ) && $this->get_directory_taxonomy() === $term->taxonomy;
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
     * Sanitize a string for identifier use.
     *
     * @param string $value Raw value.
     * @return string
     */
    private function safe_slug( $value ) {
        $slug = sanitize_key( sanitize_title( (string) $value ) );

        return '' !== $slug ? $slug : 'item';
    }

    /**
     * Make meta key readable.
     *
     * @param string $meta_key Meta key.
     * @return string
     */
    private function humanize_meta_key( $meta_key ) {
        return ucwords( str_replace( '_', ' ', (string) $meta_key ) );
    }

    /**
     * Make a nested path item readable in ATE.
     *
     * @param string $path_part Raw path item.
     * @return string
     */
    private function humanize_path_part( $path_part ) {
        if ( is_numeric( $path_part ) ) {
            return '#' . (string) $path_part;
        }

        return ucwords( str_replace( [ '_', '-' ], ' ', (string) $path_part ) );
    }
}
