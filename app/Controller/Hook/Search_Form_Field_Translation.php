<?php
/**
 * Search Form Field Translation Integration
 *
 * Makes Directorist search-form field labels translatable from the current
 * page ATE map before Directorist builds the rendered search form.
 *
 * @package Directorist_WPML_Integration
 * @since 2.1.6
 */

namespace Directorist_WPML_Integration\Controller\Hook;

class Search_Form_Field_Translation {

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
        if ( (int) $directory_id <= 0 ) {
            return $search_form_fields;
        }

        foreach ( $search_form_fields['fields'] as $field_key => $field_data ) {
            if ( ! is_array( $field_data ) ) {
                continue;
            }

            $search_form_fields['fields'][ $field_key ] = $this->translate_single_field( $field_data );
        }

        return $search_form_fields;
    }

    /**
     * Translate one search-form field definition.
     *
     * @param array  $field_data Field data.
     * @return array
     */
    private function translate_single_field( $field_data ) {
        foreach ( [ 'label', 'placeholder', 'description' ] as $property ) {
            $field_data = $this->translate_field_property( $field_data, $property );
        }

        if ( ! empty( $field_data['options'] ) && is_array( $field_data['options'] ) ) {
            $field_data['options'] = $this->translate_option_collection( $field_data['options'] );
        }

        if ( ! empty( $field_data['widget_name'] ) && 'pricing' === $field_data['widget_name'] ) {
            $field_data = $this->translate_min_max_placeholder( $field_data, 'price_range_min_placeholder', 'Min' );
            $field_data = $this->translate_min_max_placeholder( $field_data, 'price_range_max_placeholder', 'Max' );
        }

        if ( ! empty( $field_data['widget_name'] ) && 'radius_search' === $field_data['widget_name'] ) {
            if ( ! empty( $field_data['radius_min_placeholder'] ) ) {
                $field_data = $this->translate_field_property( $field_data, 'radius_min_placeholder' );
            }

            if ( ! empty( $field_data['radius_max_placeholder'] ) ) {
                $field_data = $this->translate_field_property( $field_data, 'radius_max_placeholder' );
            }
        }

        return $field_data;
    }

    /**
     * Translate one field string property.
     *
     * @param array  $field_data Field data.
     * @param string $property Property name.
     * @return array
     */
    private function translate_field_property( $field_data, $property ) {
        if ( empty( $field_data[ $property ] ) || ! is_string( $field_data[ $property ] ) ) {
            return $field_data;
        }

        $translated = $this->translate_page_ui_string( $field_data[ $property ], 'search_form' );

        if ( ! empty( $translated ) && $translated !== $field_data[ $property ] ) {
            $field_data[ $property ] = $translated;
        }

        return $field_data;
    }

    /**
     * Translate nested option collections.
     *
     * @param array  $options Options array.
     * @return array
     */
    private function translate_option_collection( $options ) {
        foreach ( $options as $key => $option ) {
            if ( is_array( $option ) ) {
                if ( ! empty( $option['label'] ) && is_string( $option['label'] ) ) {
                    $translated = $this->translate_page_ui_string( $option['label'], 'search_form' );

                    if ( ! empty( $translated ) && $translated !== $option['label'] ) {
                        $option['label'] = $translated;
                    }
                }

                if ( ! empty( $option['option_label'] ) && is_string( $option['option_label'] ) ) {
                    $translated = $this->translate_page_ui_string( $option['option_label'], 'search_form' );

                    if ( ! empty( $translated ) && $translated !== $option['option_label'] ) {
                        $option['option_label'] = $translated;
                    }
                }

                if ( ! empty( $option['options'] ) && is_array( $option['options'] ) ) {
                    $option['options'] = $this->translate_option_collection( $option['options'] );
                }

                $options[ $key ] = $option;
                continue;
            }

            if ( ! is_string( $option ) || '' === $option ) {
                continue;
            }

            $translated = $this->translate_page_ui_string( $option, 'search_form' );

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
     * @return array
     */
    private function translate_min_max_placeholder( $field_data, $property, $default ) {
        $value = ! empty( $field_data[ $property ] ) && is_string( $field_data[ $property ] )
            ? $field_data[ $property ]
            : $default;

        $translated = $this->translate_page_ui_string( $value, 'search_form' );

        if ( ! empty( $translated ) ) {
            $field_data[ $property ] = $translated;
        }

        return $field_data;
    }

    /**
     * Translate a source string from the current page ATE map.
     *
     * @param string $string_value Original string.
     * @param string $context      Optional page context.
     * @return string
     */
    private function translate_page_ui_string( $string_value, $context = '' ) {
        return apply_filters( 'directorist_wpml_translate_page_ui_string', $string_value, $string_value, $context );
    }
}
