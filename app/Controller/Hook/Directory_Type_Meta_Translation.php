<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Type_Meta_Translation {

    /**
     * Prevent recursive metadata lookups while fetching raw values.
     *
     * @var bool
     */
    private static $resolving_post_meta = false;

    /**
     * Prevent recursive metadata lookups while fetching raw values.
     *
     * @var bool
     */
    private static $resolving_term_meta = false;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'get_post_metadata', [ $this, 'translate_post_directory_type_meta' ], 10, 4 );
        add_filter( 'get_term_metadata', [ $this, 'translate_term_directory_type_meta' ], 10, 4 );
    }

    /**
     * Check if the WPML APIs we depend on are available.
     *
     * @return bool
     */
    private function is_wpml_active() {
        return defined( 'ICL_SITEPRESS_VERSION' )
            && function_exists( 'apply_filters' )
            && has_filter( 'wpml_object_id' )
            && has_filter( 'wpml_element_language_details' );
    }

    /**
     * Translate `_directory_type` when it is loaded from posts.
     *
     * Directorist stores directory type references in post meta for both listings
     * and Directorist pages. WPML cannot automatically convert taxonomy IDs in
     * post meta, so we normalize the value into the post's language here.
     *
     * @param mixed  $value     Existing metadata value.
     * @param int    $object_id Post ID.
     * @param string $meta_key  Meta key.
     * @param bool   $single    Whether a single value is requested.
     *
     * @return mixed
     */
    public function translate_post_directory_type_meta( $value, $object_id, $meta_key, $single ) {
        if ( '_directory_type' !== $meta_key || ! $single || self::$resolving_post_meta || ! $this->is_wpml_active() ) {
            return $value;
        }

        $language_code = $this->get_post_language_code( (int) $object_id );
        if ( empty( $language_code ) ) {
            return $value;
        }

        self::$resolving_post_meta = true;
        $raw_value = get_post_meta( $object_id, $meta_key, true );
        self::$resolving_post_meta = false;

        if ( empty( $raw_value ) || ! is_numeric( $raw_value ) ) {
            return $value;
        }

        $translated_value = apply_filters(
            'wpml_object_id',
            (int) $raw_value,
            ATBDP_DIRECTORY_TYPE,
            true,
            $language_code
        );

        return ! empty( $translated_value ) ? (int) $translated_value : (int) $raw_value;
    }

    /**
     * Translate `_directory_type` when it is loaded from terms.
     *
     * Directorist categories and locations store directory type relationships in
     * term meta. We map those IDs into the term language so add-listing/search
     * screens stay in sync even when older translations still hold source IDs.
     *
     * @param mixed  $value     Existing metadata value.
     * @param int    $object_id Term ID.
     * @param string $meta_key  Meta key.
     * @param bool   $single    Whether a single value is requested.
     *
     * @return mixed
     */
    public function translate_term_directory_type_meta( $value, $object_id, $meta_key, $single ) {
        if ( '_directory_type' !== $meta_key || ! $single || self::$resolving_term_meta || ! $this->is_wpml_active() ) {
            return $value;
        }

        $language_code = $this->get_term_language_code( (int) $object_id );
        if ( empty( $language_code ) ) {
            return $value;
        }

        self::$resolving_term_meta = true;
        $raw_value = get_term_meta( $object_id, $meta_key, true );
        self::$resolving_term_meta = false;

        if ( empty( $raw_value ) || ! is_array( $raw_value ) ) {
            return $value;
        }

        $translated_ids = [];

        foreach ( wp_parse_id_list( $raw_value ) as $directory_id ) {
            $translated_id = apply_filters(
                'wpml_object_id',
                $directory_id,
                ATBDP_DIRECTORY_TYPE,
                true,
                $language_code
            );

            if ( ! empty( $translated_id ) ) {
                $translated_ids[] = (int) $translated_id;
            }
        }

        $directory_type_ids = ! empty( $translated_ids )
            ? array_values( array_unique( $translated_ids ) )
            : wp_parse_id_list( $raw_value );

        if ( empty( $directory_type_ids ) ) {
            return $value;
        }

        return [ $directory_type_ids ];
    }

    /**
     * Get a post's WPML language code.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private function get_post_language_code( $post_id ) {
        $post_type = get_post_type( $post_id );

        if ( empty( $post_type ) ) {
            return '';
        }

        $language_info = WPML_Helper::get_language_info( $post_id, $post_type );

        if ( empty( $language_info ) || ! is_object( $language_info ) ) {
            return '';
        }

        if ( ! empty( $language_info->language_code ) ) {
            return (string) $language_info->language_code;
        }

        return ! empty( $language_info->language ) ? (string) $language_info->language : '';
    }

    /**
     * Get a term's WPML language code.
     *
     * @param int $term_id Term ID.
     * @return string
     */
    private function get_term_language_code( $term_id ) {
        $term = get_term( $term_id );

        if ( empty( $term ) || is_wp_error( $term ) || empty( $term->taxonomy ) ) {
            return '';
        }

        $language_info = WPML_Helper::get_language_info( $term_id, $term->taxonomy );

        if ( empty( $language_info ) || ! is_object( $language_info ) ) {
            return '';
        }

        if ( ! empty( $language_info->language_code ) ) {
            return (string) $language_info->language_code;
        }

        return ! empty( $language_info->language ) ? (string) $language_info->language : '';
    }
}
