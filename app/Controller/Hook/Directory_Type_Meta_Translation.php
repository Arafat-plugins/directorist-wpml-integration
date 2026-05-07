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
        if ( self::$resolving_term_meta || ! $this->is_wpml_active() ) {
            return $value;
        }

        if ( '_directory_type' !== $meta_key || ! $single ) {
            return $this->fallback_translated_directory_meta( $value, $object_id, $meta_key, $single );
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
     * Use the source directory type's builder meta when a translation has none.
     *
     * Directory type translations created before WPML copied term meta can miss
     * layout/form settings. Directorist then finds listings but cannot render
     * their cards/forms because keys such as listings_card_grid_view are empty.
     *
     * @param mixed  $value     Existing metadata value.
     * @param int    $object_id Term ID.
     * @param string $meta_key  Meta key.
     * @param bool   $single    Whether a single value is requested.
     *
     * @return mixed
     */
    private function fallback_translated_directory_meta( $value, $object_id, $meta_key, $single ) {
        $object_id = (int) $object_id;

        if ( $object_id <= 0 || empty( $meta_key ) || ! $this->is_directory_type_term( $object_id ) ) {
            return $value;
        }

        if ( $this->term_meta_exists( $object_id, $meta_key ) ) {
            return $value;
        }

        $source_term_id = $this->get_source_directory_type_id( $object_id );
        if ( $source_term_id <= 0 || $source_term_id === $object_id ) {
            return $value;
        }

        if ( ! $this->term_meta_exists( $source_term_id, $meta_key ) ) {
            return $value;
        }

        self::$resolving_term_meta = true;
        $source_value              = get_term_meta( $source_term_id, $meta_key, $single );
        self::$resolving_term_meta = false;

        if ( $single && is_array( $source_value ) ) {
            return [ $source_value ];
        }

        return $source_value;
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

    /**
     * Check whether a term is a Directorist directory type.
     *
     * @param int $term_id Term ID.
     * @return bool
     */
    private function is_directory_type_term( $term_id ) {
        $term = get_term( $term_id );

        if ( empty( $term ) || is_wp_error( $term ) || empty( $term->taxonomy ) ) {
            return false;
        }

        $directory_taxonomy = defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';

        return $directory_taxonomy === $term->taxonomy || 'atbdp_listing_types' === $term->taxonomy;
    }

    /**
     * Check raw term meta existence without triggering metadata filters.
     *
     * @param int    $term_id  Term ID.
     * @param string $meta_key Meta key.
     * @return bool
     */
    private function term_meta_exists( $term_id, $meta_key ) {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_id FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s LIMIT 1",
                (int) $term_id,
                $meta_key
            )
        );
    }

    /**
     * Get the default-language source directory type for a translated term.
     *
     * @param int $term_id Term ID.
     * @return int
     */
    private function get_source_directory_type_id( $term_id ) {
        $directory_taxonomy = defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';
        $default_language   = apply_filters( 'wpml_default_language', null );

        if ( empty( $default_language ) ) {
            return 0;
        }

        $translations = WPML_Helper::get_element_translations( $term_id, $directory_taxonomy );

        if ( empty( $translations ) || ! is_array( $translations ) || empty( $translations[ $default_language ]->term_id ) ) {
            return 0;
        }

        return (int) $translations[ $default_language ]->term_id;
    }
}
