<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Translation {

    public function __construct() {
        // Modify the WPML admin toolbar language switcher URLs
        add_filter( 'wpml_admin_language_switcher_items', [ $this, 'modify_language_switcher_url' ], 10, 1 );
        
        // Translate directory type names in templates (before array_reduce)
        add_filter( 'directorist_directories_for_template', [ $this, 'translate_directory_names' ], 10, 2 );
        
        // Translate directory type names in the final array structure (after array_reduce)
        // This is a custom filter we'll add support for, but also hook into get_terms results
        add_filter( 'get_terms', [ $this, 'translate_directory_terms' ], 10, 4 );
        
        // Translate directory type names in admin areas (term name filter)
        add_filter( 'term_name', [ $this, 'translate_term_name' ], 10, 2 );
    }

    /**
     * Check if WPML is active
     * 
     * @return bool
     */
    private function is_wpml_active() {
        return (
            defined( 'ICL_SITEPRESS_VERSION' ) &&
            function_exists( 'do_action' ) &&
            function_exists( 'apply_filters' )
        );
    }

    /**
     * Translate directory type names in templates
     * 
     * Hook: directorist_directories_for_template
     * Priority: 10
     * 
     * This hook is called when Directorist retrieves directory types for display in templates.
     * We translate the 'name' field in the returned array.
     * 
     * @param array $directories Array of WP_Term objects
     * @param array $args Arguments passed to directorist_get_directories()
     * @return array Filtered directories with translated names
     */
    public function translate_directory_names( $directories, $args ) {
        // Safety checks
        if ( empty( $directories ) || ! is_array( $directories ) ) {
            return $directories;
        }

        if ( ! $this->is_wpml_active() ) {
            return $directories;
        }

        // Check if this is the directory type taxonomy
        // The directories array contains WP_Term objects from the directory type taxonomy
        if ( empty( $directories ) ) {
            return $directories;
        }

        // Get first term to check taxonomy
        $first_term = reset( $directories );
        if ( ! is_object( $first_term ) || ! isset( $first_term->taxonomy ) ) {
            return $directories;
        }

        // Only translate if it's the directory type taxonomy
        // Check if constant exists, otherwise check taxonomy name
        $is_directory_type = false;
        if ( defined( 'ATBDP_DIRECTORY_TYPE' ) ) {
            $is_directory_type = ( $first_term->taxonomy === ATBDP_DIRECTORY_TYPE );
        } elseif ( defined( 'ATBDP_TYPE' ) ) {
            $is_directory_type = ( $first_term->taxonomy === ATBDP_TYPE );
        } else {
            // Fallback: check by taxonomy name
            $is_directory_type = ( $first_term->taxonomy === 'atbdp_listing_types' );
        }

        if ( ! $is_directory_type ) {
            return $directories;
        }

        // Translate each directory name
        foreach ( $directories as $directory ) {
            if ( ! is_object( $directory ) || ! isset( $directory->name ) || ! isset( $directory->term_id ) ) {
                continue;
            }

            $original_name = $directory->name;
            if ( empty( $original_name ) || ! is_string( $original_name ) ) {
                continue;
            }

            $translated_name = $this->get_linked_term_name( $directory );
            
            // Update the term name in the object
            if ( ! empty( $translated_name ) && $translated_name !== $original_name ) {
                $directory->name = $translated_name;
            }
        }

        return $directories;
    }

    /**
     * Translate directory type and tag terms when retrieved via get_terms()
     * 
     * Hook: get_terms
     * Priority: 10
     * 
     * This hook translates directory type and tag term names when they're retrieved
     * directly via get_terms() or get_term() functions.
     * 
     * @param array|WP_Error $terms Array of term objects or WP_Error
     * @param array $taxonomies Array of taxonomy names
     * @param array $args Query arguments
     * @param WP_Term_Query $term_query The term query object
     * @return array|WP_Error Filtered terms with translated names
     */
    public function translate_directory_terms( $terms, $taxonomies, $args, $term_query ) {
        if ( $this->is_default_directory_query( $taxonomies, $args ) && ( empty( $terms ) || is_wp_error( $terms ) ) ) {
            return $this->get_translated_default_directory_terms( $args );
        }

        // Safety checks
        if ( empty( $terms ) || is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return $terms;
        }

        if ( ! $this->is_wpml_active() ) {
            return $terms;
        }

        // Check if directory type or tags taxonomy is in the query
        $is_target_taxonomy = false;
        $taxonomy_type = '';
        
        if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
            foreach ( $taxonomies as $taxonomy ) {
                // Check for directory type
                if ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $taxonomy === ATBDP_DIRECTORY_TYPE ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'directory_type';
                    break;
                } elseif ( defined( 'ATBDP_TYPE' ) && $taxonomy === ATBDP_TYPE ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'directory_type';
                    break;
                } elseif ( $taxonomy === 'atbdp_listing_types' ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'directory_type';
                    break;
                }
                // Check for tags
                elseif ( defined( 'ATBDP_TAGS' ) && $taxonomy === ATBDP_TAGS ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'tag';
                    break;
                } elseif ( $taxonomy === 'at_biz_dir-tags' ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'tag';
                    break;
                }
            }
        }

        if ( ! $is_target_taxonomy ) {
            return $terms;
        }

        // Translate each term name
        foreach ( $terms as $term ) {
            if ( ! is_object( $term ) || ! isset( $term->name ) || ! isset( $term->term_id ) ) {
                continue;
            }

            // Double-check taxonomy from term object
            if ( isset( $term->taxonomy ) ) {
                if ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $term->taxonomy === ATBDP_DIRECTORY_TYPE ) {
                    $taxonomy_type = 'directory_type';
                } elseif ( defined( 'ATBDP_TYPE' ) && $term->taxonomy === ATBDP_TYPE ) {
                    $taxonomy_type = 'directory_type';
                } elseif ( $term->taxonomy === 'atbdp_listing_types' ) {
                    $taxonomy_type = 'directory_type';
                } elseif ( defined( 'ATBDP_TAGS' ) && $term->taxonomy === ATBDP_TAGS ) {
                    $taxonomy_type = 'tag';
                } elseif ( $term->taxonomy === 'at_biz_dir-tags' ) {
                    $taxonomy_type = 'tag';
                } else {
                    continue; // Skip if not a target taxonomy
                }
            }

            $original_name = $term->name;
            if ( empty( $original_name ) || ! is_string( $original_name ) ) {
                continue;
            }

            $translated_name = $this->get_linked_term_name( $term );
            
            // Update the term name in the object
            if ( ! empty( $translated_name ) && $translated_name !== $original_name ) {
                $term->name = $translated_name;
            }
        }

        return $terms;
    }

    /**
     * Check whether Directorist is looking up the default directory type.
     *
     * Directorist resolves the default directory with get_terms() using the
     * `_default` term meta. Older WPML translations can miss that copied meta,
     * which makes Directorist fall back to directory ID 0 in secondary languages.
     *
     * @param array $taxonomies Queried taxonomies.
     * @param array $args       get_terms() arguments.
     *
     * @return bool
     */
    private function is_default_directory_query( $taxonomies, $args ) {
        if ( ! $this->is_wpml_active() || empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
            return false;
        }

        $directory_taxonomy = defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';

        if ( ! in_array( $directory_taxonomy, $taxonomies, true ) && ! in_array( 'atbdp_listing_types', $taxonomies, true ) ) {
            return false;
        }

        $meta_key   = isset( $args['meta_key'] ) ? $args['meta_key'] : '';
        $meta_value = isset( $args['meta_value'] ) ? (string) $args['meta_value'] : '';

        return '_default' === $meta_key && '1' === $meta_value;
    }

    /**
     * Resolve the current language's default directory from the default language.
     *
     * @param array $args get_terms() arguments.
     * @return array
     */
    private function get_translated_default_directory_terms( $args ) {
        global $wpdb;

        $current_language = apply_filters( 'wpml_current_language', null );
        $default_language = apply_filters( 'wpml_default_language', null );

        if ( empty( $current_language ) || empty( $default_language ) || $current_language === $default_language ) {
            return [];
        }

        $directory_taxonomy = defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';
        $element_type       = WPML_Helper::get_wpml_element_type( $directory_taxonomy );

        if ( empty( $element_type ) ) {
            return [];
        }

        $default_directory_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.term_id
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = t.term_id
                INNER JOIN {$wpdb->prefix}icl_translations tr
                    ON tr.element_id = tt.term_taxonomy_id
                    AND tr.element_type = %s
                WHERE tt.taxonomy = %s
                    AND tm.meta_key = '_default'
                    AND tm.meta_value = '1'
                    AND tr.language_code = %s
                ORDER BY t.term_id ASC
                LIMIT 1",
                $element_type,
                $directory_taxonomy,
                $default_language
            )
        );

        if ( $default_directory_id <= 0 ) {
            return [];
        }

        $translations = WPML_Helper::get_element_translations( $default_directory_id, $directory_taxonomy );

        if ( empty( $translations[ $current_language ]->term_id ) ) {
            return [];
        }

        $translated_term = get_term( (int) $translations[ $current_language ]->term_id, $directory_taxonomy );

        if ( ! $translated_term || is_wp_error( $translated_term ) ) {
            return [];
        }

        $fields = isset( $args['fields'] ) ? $args['fields'] : 'all';

        if ( 'ids' === $fields ) {
            return [ (int) $translated_term->term_id ];
        }

        if ( 'slugs' === $fields ) {
            return [ $translated_term->slug ];
        }

        if ( 'names' === $fields ) {
            return [ $translated_term->name ];
        }

        return [ $translated_term ];
    }

    /**
     * Translate term name for directory types and tags in admin areas
     * 
     * Hook: term_name
     * Priority: 10
     * 
     * This hook translates term names when displayed in admin areas,
     * such as in admin columns, category/location forms, etc.
     * 
     * @param string $name Term name
     * @param object $term Term object
     * @return string Translated term name
     */
    public function translate_term_name( $name, $term ) {
        // Safety checks
        if ( empty( $name ) || ! is_string( $name ) ) {
            return $name;
        }

        if ( empty( $term ) || ! is_object( $term ) ) {
            return $name;
        }

        if ( ! $this->is_wpml_active() ) {
            return $name;
        }

        // Check if this is a directory type or tag term
        $taxonomy_type = '';
        if ( isset( $term->taxonomy ) ) {
            if ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $term->taxonomy === ATBDP_DIRECTORY_TYPE ) {
                $taxonomy_type = 'directory_type';
            } elseif ( defined( 'ATBDP_TYPE' ) && $term->taxonomy === ATBDP_TYPE ) {
                $taxonomy_type = 'directory_type';
            } elseif ( $term->taxonomy === 'atbdp_listing_types' ) {
                $taxonomy_type = 'directory_type';
            } elseif ( defined( 'ATBDP_TAGS' ) && $term->taxonomy === ATBDP_TAGS ) {
                $taxonomy_type = 'tag';
            } elseif ( $term->taxonomy === 'at_biz_dir-tags' ) {
                $taxonomy_type = 'tag';
            }
        }

        if ( empty( $taxonomy_type ) ) {
            return $name;
        }

        // Check if term_id exists
        if ( ! isset( $term->term_id ) || empty( $term->term_id ) ) {
            return $name;
        }

        return $this->get_linked_term_name( $term, $name );
    }

    /**
     * Resolve a term's linked current-language name through WPML taxonomy data.
     *
     * @param object $term          Term object.
     * @param string $fallback_name Optional fallback name.
     * @return string
     */
    private function get_linked_term_name( $term, $fallback_name = '' ) {
        $fallback_name = is_string( $fallback_name ) && '' !== $fallback_name
            ? $fallback_name
            : ( isset( $term->name ) && is_string( $term->name ) ? $term->name : '' );

        if ( ! $this->is_wpml_active() || ! is_object( $term ) || empty( $term->term_id ) || empty( $term->taxonomy ) ) {
            return $fallback_name;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        $default_language = apply_filters( 'wpml_default_language', null );

        if ( empty( $current_language ) || empty( $default_language ) || $current_language === $default_language ) {
            return $fallback_name;
        }

        $translations = WPML_Helper::get_element_translations( (int) $term->term_id, (string) $term->taxonomy );

        if ( empty( $translations[ $current_language ] ) || empty( $translations[ $current_language ]->term_id ) ) {
            return $fallback_name;
        }

        $translated_term = get_term( (int) $translations[ $current_language ]->term_id, (string) $term->taxonomy );

        if ( ! $translated_term || is_wp_error( $translated_term ) || empty( $translated_term->name ) || ! is_string( $translated_term->name ) ) {
            return $fallback_name;
        }

        return $translated_term->name;
    }

    public function modify_language_switcher_url( $languages_links ) {
        foreach ( $languages_links as $lang_code => &$lang_data ) {
            $url_parts = wp_parse_url( $lang_data['url'] );
            
            if ( isset( $url_parts['query'] ) ) {
                parse_str( $url_parts['query'], $query_vars );

                if (
                    isset( $query_vars['post_type'] ) &&
                    $query_vars['post_type'] === 'at_biz_dir' &&
                    isset( $query_vars['page'] ) &&
                    $query_vars['page'] === 'atbdp-directory-types' &&
                    ! empty( $query_vars['listing_type_id'] )
                ) {
                    $translations = WPML_Helper::get_element_translations( $query_vars['listing_type_id'], ATBDP_DIRECTORY_TYPE );

                    if ( empty( $translations ) ) {
                        continue;
                    }

                    if ( isset( $translations[ $lang_code ] ) ) {
                        $translation_id = $translations[ $lang_code ]->element_id;

                        // Change the listing_type_id of the URL to the translation_id
                        $lang_data['url'] = add_query_arg( array(
                            'listing_type_id' => $translation_id,
                        ), $lang_data['url'] );
                    }
                }
            }
        }
        
        return $languages_links;
    }
}
