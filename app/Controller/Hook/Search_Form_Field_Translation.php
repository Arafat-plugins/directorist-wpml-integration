<?php
/**
 * Search Form Field Translation Integration
 *
 * Makes Directorist search-form field labels translatable with WPML String
 * Translation by translating the stored `search_form_fields` term meta before
 * Directorist builds the rendered search form.
 *
 * @package Directorist_WPML_Integration
 * @since 2.1.6
 */

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Search_Form_Field_Translation {

    /**
     * WPML String Translation Domain.
     *
     * @var string
     */
    const WPML_DOMAIN = 'directorist-wpml-integration';

    /**
     * Prevent recursive `get_term_meta()` calls while fetching raw values.
     *
     * @var bool
     */
    private static $translating_term_meta = false;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'get_term_metadata', [ $this, 'translate_search_form_fields_meta' ], 10, 4 );
    }

    /**
     * Check if WPML is active.
     *
     * @return bool
     */
    private function is_wpml_active() {
        return defined( 'ICL_SITEPRESS_VERSION' )
            && function_exists( 'do_action' )
            && function_exists( 'apply_filters' );
    }

    /**
     * Translate `search_form_fields` term meta before Directorist reads it.
     *
     * @param mixed  $value Existing metadata value.
     * @param int    $object_id Term ID.
     * @param string $meta_key Meta key.
     * @param bool   $single Whether a single value is requested.
     * @return mixed
     */
    public function translate_search_form_fields_meta( $value, $object_id, $meta_key, $single ) {
        if ( 'search_form_fields' !== $meta_key || ! $single || self::$translating_term_meta || ! $this->is_wpml_active() ) {
            return $value;
        }

        self::$translating_term_meta = true;
        $raw_value = get_term_meta( $object_id, $meta_key, true );
        self::$translating_term_meta = false;

        if ( empty( $raw_value ) || ! is_array( $raw_value ) || empty( $raw_value['fields'] ) || ! is_array( $raw_value['fields'] ) ) {
            return $value;
        }

        return [ $this->translate_search_form_fields( $raw_value, (int) $object_id ) ];
    }

    /**
     * Translate all search form field strings for a directory type.
     *
     * @param array $search_form_fields Search form term meta.
     * @param int   $directory_id Directory type ID.
     * @return array
     */
    private function translate_search_form_fields( $search_form_fields, $directory_id ) {
        $directory_context_id = $this->get_directory_context_id( $directory_id );

        if ( empty( $directory_context_id ) ) {
            return $search_form_fields;
        }

        foreach ( $search_form_fields['fields'] as $field_key => $field_data ) {
            if ( ! is_array( $field_data ) ) {
                continue;
            }

            $field_slug = $this->get_field_slug( $field_key, $field_data );

            $search_form_fields['fields'][ $field_key ] = $this->translate_single_field(
                $field_data,
                $directory_context_id,
                $field_slug
            );
        }

        return $search_form_fields;
    }

    /**
     * Get a stable WPML context ID for a directory type.
     *
     * @param int $directory_id Directory type ID.
     * @return int
     */
    private function get_directory_context_id( $directory_id ) {
        $directory_id = (int) $directory_id;

        if ( $directory_id <= 0 ) {
            return 0;
        }

        $translation_group_id = WPML_Helper::get_element_trid( $directory_id, ATBDP_DIRECTORY_TYPE );

        return $translation_group_id > 0 ? $translation_group_id : $directory_id;
    }

    /**
     * Build a stable field slug for string keys.
     *
     * @param string|int $field_key Field array key.
     * @param array      $field_data Field data.
     * @return string
     */
    private function get_field_slug( $field_key, $field_data ) {
        $candidates = [];

        if ( is_string( $field_key ) || is_numeric( $field_key ) ) {
            $candidates[] = (string) $field_key;
        }

        foreach ( [ 'field_key', 'original_widget_key', 'widget_key', 'widget_name' ] as $candidate_key ) {
            if ( ! empty( $field_data[ $candidate_key ] ) && ( is_string( $field_data[ $candidate_key ] ) || is_numeric( $field_data[ $candidate_key ] ) ) ) {
                $candidates[] = (string) $field_data[ $candidate_key ];
            }
        }

        foreach ( $candidates as $candidate ) {
            $slug = $this->safe_slug( $candidate );

            if ( '' !== $slug ) {
                return $slug;
            }
        }

        return 'field_' . substr( md5( wp_json_encode( $field_data ) ), 0, 10 );
    }

    /**
     * Translate one search-form field definition.
     *
     * @param array  $field_data Field data.
     * @param int    $directory_context_id Stable directory context ID.
     * @param string $field_slug Field identifier.
     * @return array
     */
    private function translate_single_field( $field_data, $directory_context_id, $field_slug ) {
        foreach ( [ 'label', 'placeholder', 'description' ] as $property ) {
            $field_data = $this->translate_field_property( $field_data, $property, $directory_context_id, $field_slug );
        }

        if ( ! empty( $field_data['options'] ) && is_array( $field_data['options'] ) ) {
            $field_data['options'] = $this->translate_option_collection(
                $field_data['options'],
                $directory_context_id,
                $field_slug
            );
        }

        if ( ! empty( $field_data['widget_name'] ) && 'pricing' === $field_data['widget_name'] ) {
            $field_data = $this->translate_min_max_placeholder( $field_data, 'price_range_min_placeholder', 'Min', $directory_context_id, $field_slug );
            $field_data = $this->translate_min_max_placeholder( $field_data, 'price_range_max_placeholder', 'Max', $directory_context_id, $field_slug );
        }

        if ( ! empty( $field_data['widget_name'] ) && 'radius_search' === $field_data['widget_name'] ) {
            if ( ! empty( $field_data['radius_min_placeholder'] ) ) {
                $field_data = $this->translate_field_property( $field_data, 'radius_min_placeholder', $directory_context_id, $field_slug );
            }

            if ( ! empty( $field_data['radius_max_placeholder'] ) ) {
                $field_data = $this->translate_field_property( $field_data, 'radius_max_placeholder', $directory_context_id, $field_slug );
            }
        }

        return $field_data;
    }

    /**
     * Translate one field string property.
     *
     * @param array  $field_data Field data.
     * @param string $property Property name.
     * @param int    $directory_context_id Stable directory context ID.
     * @param string $field_slug Field identifier.
     * @return array
     */
    private function translate_field_property( $field_data, $property, $directory_context_id, $field_slug ) {
        if ( empty( $field_data[ $property ] ) || ! is_string( $field_data[ $property ] ) ) {
            return $field_data;
        }

        $string_name = sprintf( 'search_form_dir_%d_field_%s_%s', $directory_context_id, $field_slug, $property );
        $this->register_wpml_string( $string_name, $field_data[ $property ] );

        $translated = $this->translate_wpml_string( $field_data[ $property ], $string_name );

        if ( ! empty( $translated ) && $translated !== $field_data[ $property ] ) {
            $field_data[ $property ] = $translated;
        }

        return $field_data;
    }

    /**
     * Translate nested option collections.
     *
     * @param array  $options Options array.
     * @param int    $directory_context_id Stable directory context ID.
     * @param string $field_slug Field identifier.
     * @param string $path Current option path.
     * @return array
     */
    private function translate_option_collection( $options, $directory_context_id, $field_slug, $path = 'option' ) {
        foreach ( $options as $key => $option ) {
            $option_identifier = $this->safe_slug( is_string( $key ) || is_numeric( $key ) ? (string) $key : 'item' );

            if ( is_array( $option ) ) {
                if ( ! empty( $option['label'] ) && is_string( $option['label'] ) ) {
                    $identifier = ! empty( $option['value'] ) ? $option['value'] : $option_identifier;
                    $string_name = sprintf(
                        'search_form_dir_%d_field_%s_%s_%s_label',
                        $directory_context_id,
                        $field_slug,
                        $path,
                        $this->safe_slug( $identifier )
                    );

                    $this->register_wpml_string( $string_name, $option['label'] );
                    $translated = $this->translate_wpml_string( $option['label'], $string_name );

                    if ( ! empty( $translated ) && $translated !== $option['label'] ) {
                        $option['label'] = $translated;
                    }
                }

                if ( ! empty( $option['option_label'] ) && is_string( $option['option_label'] ) ) {
                    $identifier = ! empty( $option['option_value'] ) ? $option['option_value'] : $option_identifier;
                    $string_name = sprintf(
                        'search_form_dir_%d_field_%s_%s_%s_option_label',
                        $directory_context_id,
                        $field_slug,
                        $path,
                        $this->safe_slug( $identifier )
                    );

                    $this->register_wpml_string( $string_name, $option['option_label'] );
                    $translated = $this->translate_wpml_string( $option['option_label'], $string_name );

                    if ( ! empty( $translated ) && $translated !== $option['option_label'] ) {
                        $option['option_label'] = $translated;
                    }
                }

                if ( ! empty( $option['options'] ) && is_array( $option['options'] ) ) {
                    $option['options'] = $this->translate_option_collection(
                        $option['options'],
                        $directory_context_id,
                        $field_slug,
                        $path . '_' . $option_identifier
                    );
                }

                $options[ $key ] = $option;
                continue;
            }

            if ( ! is_string( $option ) || '' === $option ) {
                continue;
            }

            $string_name = sprintf(
                'search_form_dir_%d_field_%s_%s_%s',
                $directory_context_id,
                $field_slug,
                $path,
                $option_identifier
            );

            $this->register_wpml_string( $string_name, $option );
            $translated = $this->translate_wpml_string( $option, $string_name );

            if ( ! empty( $translated ) && $translated !== $option ) {
                $options[ $key ] = $translated;
            }
        }

        return $options;
    }

    /**
     * Translate min/max placeholders with default fallbacks.
     *
     * @param array  $field_data Field data.
     * @param string $property Property name.
     * @param string $default Default value.
     * @param int    $directory_context_id Stable directory context ID.
     * @param string $field_slug Field identifier.
     * @return array
     */
    private function translate_min_max_placeholder( $field_data, $property, $default, $directory_context_id, $field_slug ) {
        $value = ! empty( $field_data[ $property ] ) && is_string( $field_data[ $property ] )
            ? $field_data[ $property ]
            : __( $default, 'directorist-wpml-integration' );

        $string_name = sprintf(
            'search_form_dir_%d_field_%s_%s',
            $directory_context_id,
            $field_slug,
            str_replace( [ 'price_range_', 'radius_' ], '', $property )
        );

        $this->register_wpml_string( $string_name, $value );
        $translated = $this->translate_wpml_string( $value, $string_name );

        if ( ! empty( $translated ) ) {
            $field_data[ $property ] = $translated;
        }

        return $field_data;
    }

    /**
     * Register a string with WPML.
     *
     * @param string $string_name String identifier.
     * @param string $string_value String value.
     * @return void
     */
    private function register_wpml_string( $string_name, $string_value ) {
        if ( ! is_string( $string_value ) || '' === $string_value ) {
            return;
        }

        if ( is_admin() && ! empty( $_GET['page'] ) ) {
            $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

            if ( false !== strpos( $page, 'wpml-string-translation' ) ) {
                return;
            }
        }

        do_action( 'wpml_register_single_string', self::WPML_DOMAIN, $string_name, $string_value );
    }

    /**
     * Translate a registered WPML string.
     *
     * @param string $string_value Original string.
     * @param string $string_name String identifier.
     * @return string
     */
    private function translate_wpml_string( $string_value, $string_name ) {
        return apply_filters(
            'wpml_translate_single_string',
            $string_value,
            self::WPML_DOMAIN,
            $string_name
        );
    }

    /**
     * Create a safe slug from a string.
     *
     * @param string $string Input string.
     * @return string
     */
    private function safe_slug( $string ) {
        return sanitize_key( sanitize_title( (string) $string ) );
    }
}
