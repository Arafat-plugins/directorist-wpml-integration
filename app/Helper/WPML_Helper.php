<?php

namespace Directorist_WPML_Integration\Helper;

use Directorist_WPML_Integration\Helper\Response;

class WPML_Helper {

    /**
     * Set post translation
     * 
     * @param int $original_post_id 0
     * @param int $translation_post_id 0
     * @param string $language_code en
     * @param string $element_type post
     * @return Response
     */
    public static function set_post_translation( $original_post_id = 0, $translation_post_id = 0, $language_code = 'en', $element_type = 'post' ) {
        $response = new Response;

        $status = [];
        $status['succes']  = false;
        $status['message'] = '';

        // Validation
        if ( empty( $original_post_id ) ) {
            $response->message = __( 'Original post ID is required', 'directorist-wpml-integration' );
            return $response;
        }

        if ( empty( $translation_post_id ) ) {
            $response->message = __( 'Translation post ID is required', 'directorist-wpml-integration' );
            return $response;
        }

        if ( empty( $language_code ) ) {
            $response->message = __( 'Language code is required', 'directorist-wpml-integration' );
            return $response;
        }

        if ( empty( $element_type ) ) {
            $response->message = __( 'Element type is required', 'directorist-wpml-integration' );
            return $response;
        }

        $original_post_language_info = self::get_language_info( $original_post_id, $element_type );
        
        if ( empty( $original_post_language_info ) ) {
            self::assign_language( $original_post_id, $element_type );
            $original_post_language_info = self::get_language_info( $original_post_id, $element_type );
        }

        if ( empty( $original_post_language_info ) ) {
            $response->message = __( 'There is no language is assingned to this directory', 'directorist-wpml-integration' );
            return $response;
        }

        self::assign_language( 
            $translation_post_id, 
            $element_type,
            $language_code,
            $original_post_language_info->trid,
            $original_post_language_info->language_code
        );

        $response->success = true;
        $response->message = __( 'The translation has been set successfully.', 'directorist-wpml-integration' );

        return $response;
    }

    /**
     * Assign Language
     * 
     * @param int $post_id
     * @param string $element_type
     * @param string $language
     * @param int $trid
     * @param string $source_language_code
     * 
     * @return void 
     */
    public static function assign_language( $post_id = 0,  $element_type = '', $language_code = '', $trid = false, $source_language_code = null ) {
        $default_language   = apply_filters( 'wpml_default_language', null );
        $language_code      = ( ! empty( $language_code ) ) ? $language_code : $default_language;
        $raw_element_type   = self::get_raw_element_type( $element_type );
        $wpml_element_type  = self::get_wpml_element_type( $raw_element_type );
        $wpml_element_id    = self::get_wpml_element_id( $post_id, $raw_element_type );

        if ( empty( $wpml_element_id ) || empty( $wpml_element_type ) ) {
            return;
        }

        $set_language_args = [
            'element_id'           => $wpml_element_id,
            'element_type'         => $wpml_element_type,
            'trid'                 => $trid,
            'language_code'        => $language_code,
            'source_language_code' => $source_language_code
        ];

        do_action( 'wpml_set_element_language_details', $set_language_args );
    }

    /**
     * Get Language Info
     * 
     * @param string $post_id
     * @param string $element_type
     * 
     * @return stdClass|false Language Info
     */
    public static function get_language_info( $post_id = 0, $element_type = 'post' ) {
        $raw_element_type = self::get_raw_element_type( $element_type );
        $wpml_element_id  = self::get_wpml_element_id( $post_id, $raw_element_type );

        if ( empty( $wpml_element_id ) || empty( $raw_element_type ) ) {
            return false;
        }

        $get_language_args = [ 
            'element_id'   => $wpml_element_id,
            'element_type' => $raw_element_type,
        ];
    
        return apply_filters( 'wpml_element_language_details', null, $get_language_args );
    }



    /**
     * Duplicats a term from given term ID
     * 
     * @param int $term_id 0
     * @param string $taxonomy ''
     * @param string $new_term_name ''
     * @return Response
     */
    public static function create_duplicate_term( $term_id = 0, $taxonomy = '', $new_term_name = '' ) {
        $response = new Response();

        if ( empty( $term_id ) ) {
            $response->message = __( 'The term ID is required.', 'directorist-wpml-integration' );
            return $response;
        }

        if ( empty( $taxonomy ) ) {
            $response->message = __( 'The taxonomy is required.', 'directorist-wpml-integration' );
            return $response;
        }

        $original_term = get_term_by( 'id', $term_id, $taxonomy );

        if ( is_wp_error( $original_term ) ) {
            $response->message = __( 'The term ID or taxonomy is not valid.', 'directorist-wpml-integration' );
            return $response;
        }

	    $new_term = wp_insert_term( $new_term_name, $original_term->taxonomy );

        if ( is_wp_error( $new_term ) ) {
            $response->message = __( 'Couldn\'t duplicate the term, please try again.', 'directorist-wpml-integration' );
            return $response;
        }

        $original_term_meta = get_term_meta( $original_term->term_id );

        // Duplicate the term meta
        if ( ! empty( $original_term_meta ) ) {
            foreach ( $original_term_meta as $original_term_meta_key => $original_term_meta_value ) {
                update_term_meta( $new_term['term_id'], $original_term_meta_key, maybe_unserialize( $original_term_meta_value[0] ) );
            }
        }

        $response->success = true;
        $response->data    = [ 'new_term_id' => $new_term['term_id'] ];
        $response->message = __( 'The term has been duplicated successfully', 'directorist-wpml-integration' );
        
        return $response;
    }

    /**
     * Get element language info
     * 
     * @param int $element_id
     * @param string $element_type
     * 
     * @return object $language_info
     */
    public static function get_element_language_info( $element_id, $element_type ) {
        return self::get_language_info( $element_id, $element_type );
    }

    /**
     * Get element translations
     * 
     * @param int $element_id
     * @param string $element_type
     * 
     * @return object $language_info
     */
    public static function get_element_translations( $element_id, $element_type ) {
        $raw_element_type  = self::get_raw_element_type( $element_type );
        $wpml_element_id   = self::get_wpml_element_id( $element_id, $raw_element_type );
        $wpml_element_type = self::get_wpml_element_type( $raw_element_type );

        if ( empty( $wpml_element_id ) || empty( $wpml_element_type ) ) {
            return [];
        }

        $translation_id = apply_filters( 'wpml_element_trid', null, $wpml_element_id, $wpml_element_type );

        if ( empty( $translation_id ) ) {
            return [];
        }

        $translations = apply_filters( 'wpml_get_element_translations', null, $translation_id, $wpml_element_type );

        return self::normalize_translations( $translations, $raw_element_type );
    }

    /**
     * Get the WPML translation group ID for an element.
     *
     * @param int    $element_id Element ID.
     * @param string $element_type Post type or taxonomy key.
     *
     * @return int
     */
    public static function get_element_trid( $element_id, $element_type ) {
        $raw_element_type  = self::get_raw_element_type( $element_type );
        $wpml_element_id   = self::get_wpml_element_id( $element_id, $raw_element_type );
        $wpml_element_type = self::get_wpml_element_type( $raw_element_type );

        if ( empty( $wpml_element_id ) || empty( $wpml_element_type ) ) {
            return 0;
        }

        return (int) apply_filters( 'wpml_element_trid', null, $wpml_element_id, $wpml_element_type );
    }

    /**
     * Normalize an element type into the raw WPML-friendly key.
     *
     * @param string $element_type Element type.
     * @return string
     */
    public static function get_raw_element_type( $element_type ) {
        if ( ! is_string( $element_type ) || '' === $element_type ) {
            return '';
        }

        if ( 0 === strpos( $element_type, 'post_' ) ) {
            return substr( $element_type, 5 );
        }

        if ( 0 === strpos( $element_type, 'tax_' ) ) {
            return substr( $element_type, 4 );
        }

        return $element_type;
    }

    /**
     * Get the fully-qualified WPML element type.
     *
     * @param string $element_type Element type.
     * @return string
     */
    public static function get_wpml_element_type( $element_type ) {
        $raw_element_type = self::get_raw_element_type( $element_type );

        if ( '' === $raw_element_type ) {
            return '';
        }

        return (string) apply_filters( 'wpml_element_type', $raw_element_type );
    }

    /**
     * Convert a WordPress object ID into the ID WPML stores internally.
     *
     * WPML uses term_taxonomy_id for taxonomy items and post_id for posts.
     *
     * @param int    $element_id Element ID.
     * @param string $element_type Element type.
     * @return int
     */
    public static function get_wpml_element_id( $element_id, $element_type ) {
        $element_id = (int) $element_id;

        if ( $element_id <= 0 ) {
            return 0;
        }

        $taxonomy = self::get_taxonomy_name( $element_type );

        if ( '' === $taxonomy ) {
            return $element_id;
        }

        $term_taxonomy_id = self::get_term_taxonomy_id( $element_id, $taxonomy );
        if ( $term_taxonomy_id > 0 ) {
            return $term_taxonomy_id;
        }

        if ( self::get_term_id_from_term_taxonomy_id( $element_id, $taxonomy ) > 0 ) {
            return $element_id;
        }

        return $element_id;
    }

    /**
     * Convert a WPML taxonomy element ID back into a WordPress term ID.
     *
     * @param int    $element_id Element ID.
     * @param string $element_type Element type.
     * @return int
     */
    public static function get_wordpress_element_id( $element_id, $element_type ) {
        $element_id = (int) $element_id;

        if ( $element_id <= 0 ) {
            return 0;
        }

        $taxonomy = self::get_taxonomy_name( $element_type );

        if ( '' === $taxonomy ) {
            return $element_id;
        }

        $term_id = self::get_term_id_from_term_taxonomy_id( $element_id, $taxonomy );
        if ( $term_id > 0 ) {
            return $term_id;
        }

        if ( self::get_term_taxonomy_id( $element_id, $taxonomy ) > 0 ) {
            return $element_id;
        }

        return $element_id;
    }

    /**
     * Check whether an element type refers to a taxonomy.
     *
     * @param string $element_type Element type.
     * @return bool
     */
    public static function is_taxonomy_element_type( $element_type ) {
        return '' !== self::get_taxonomy_name( $element_type );
    }

    /**
     * Normalize translation objects so taxonomy translations expose term IDs.
     *
     * @param mixed  $translations Translation data.
     * @param string $element_type Element type.
     * @return array
     */
    public static function normalize_translations( $translations, $element_type ) {
        if ( empty( $translations ) || ! is_array( $translations ) ) {
            return [];
        }

        if ( ! self::is_taxonomy_element_type( $element_type ) ) {
            return $translations;
        }

        foreach ( $translations as $language_code => $translation ) {
            if ( ! is_object( $translation ) ) {
                continue;
            }

            $wpml_element_id = 0;

            if ( ! empty( $translation->element_id ) ) {
                $wpml_element_id = (int) $translation->element_id;
            } elseif ( ! empty( $translation->term_taxonomy_id ) ) {
                $wpml_element_id = (int) $translation->term_taxonomy_id;
            } elseif ( ! empty( $translation->term_id ) ) {
                $wpml_element_id = self::get_wpml_element_id( $translation->term_id, $element_type );
            }

            if ( $wpml_element_id <= 0 ) {
                continue;
            }

            $translations[ $language_code ]->term_id = self::get_wordpress_element_id( $wpml_element_id, $element_type );
        }

        return $translations;
    }

    /**
     * Resolve a raw taxonomy key from an element type.
     *
     * @param string $element_type Element type.
     * @return string
     */
    private static function get_taxonomy_name( $element_type ) {
        $raw_element_type = self::get_raw_element_type( $element_type );

        if ( '' === $raw_element_type || ! taxonomy_exists( $raw_element_type ) ) {
            return '';
        }

        return $raw_element_type;
    }

    /**
     * Resolve term_taxonomy_id without using term APIs that WPML filters by language.
     *
     * @param int    $term_id Term ID.
     * @param string $taxonomy Taxonomy name.
     * @return int
     */
    private static function get_term_taxonomy_id( $term_id, $taxonomy ) {
        global $wpdb;

        $term_id = (int) $term_id;

        if ( $term_id <= 0 || empty( $taxonomy ) || ! isset( $wpdb->term_taxonomy ) ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s LIMIT 1",
                $term_id,
                $taxonomy
            )
        );
    }

    /**
     * Resolve term ID from term_taxonomy_id without triggering WPML term filters.
     *
     * @param int    $term_taxonomy_id Term taxonomy ID.
     * @param string $taxonomy Taxonomy name.
     * @return int
     */
    private static function get_term_id_from_term_taxonomy_id( $term_taxonomy_id, $taxonomy ) {
        global $wpdb;

        $term_taxonomy_id = (int) $term_taxonomy_id;

        if ( $term_taxonomy_id <= 0 || empty( $taxonomy ) || ! isset( $wpdb->term_taxonomy ) ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d AND taxonomy = %s LIMIT 1",
                $term_taxonomy_id,
                $taxonomy
            )
        );
    }

}
