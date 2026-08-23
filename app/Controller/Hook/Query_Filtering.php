<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Query_Filtering {

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct() {
        // Filter listing queries to show only current language
        add_action( 'pre_get_posts', [ $this, 'filter_listing_queries' ], 10, 1 );
        
        // Ensure suppress_filters is false for Directorist queries
        // This runs BEFORE the query is created, allowing WPML to filter at SQL level
        add_filter( 'directorist_all_listings_query_arguments', [ $this, 'ensure_wpml_filtering' ], 10, 1 );
        add_filter( 'atbdp_listing_search_query_argument', [ $this, 'ensure_wpml_filtering' ], 10, 1 );
        add_filter( 'directorist_dashboard_query_arguments', [ $this, 'ensure_wpml_filtering' ], 10, 1 );
        
        // Also filter author listings query arguments (like author profile page)
        add_filter( 'atbdp_author_listings_query_arguments', [ $this, 'ensure_wpml_filtering' ], 10, 1 );
        
        // Filter DB query results AFTER the query runs
        // This is the critical filter that ensures count matches filtered results
        add_filter( 'directorist_listings_query_results', [ $this, 'filter_query_results' ], 5, 1 );
    }

    /**
     * Filter listing queries to show only current language
     * 
     * @param \WP_Query $query
     * @return void
     */
    public function filter_listing_queries( $query ) {
        // Check if WPML is active
        if ( ! function_exists( 'apply_filters' ) || ! has_filter( 'wpml_current_language' ) ) {
            return;
        }

        // Only filter on frontend (allow AJAX requests)
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        // Only filter Directorist listing queries
        if ( ! isset( $query->query_vars['post_type'] ) || ATBDP_POST_TYPE !== $query->query_vars['post_type'] ) {
            return;
        }

        // Don't filter if suppress_filters is true (but we'll set it to false via filter)
        if ( ! empty( $query->query_vars['suppress_filters'] ) ) {
            $query->query_vars['suppress_filters'] = false;
        }

        // Translate taxonomy term IDs in tax_query to current language
        if ( ! empty( $query->query_vars['tax_query'] ) && is_array( $query->query_vars['tax_query'] ) ) {
            $query->query_vars['tax_query'] = $this->translate_tax_query_terms( $query->query_vars['tax_query'] );
        }

        // Accept directory type IDs from any translation in the same WPML group.
        if ( ! empty( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
            $query->query_vars['meta_query'] = $this->normalize_directory_type_meta_query( $query->query_vars['meta_query'] );
        }

        // WPML will automatically filter by current language when suppress_filters is false
        // This ensures WPML's query filtering hooks are applied
    }

    /**
     * Translate taxonomy query terms to current language
     * 
     * @param array $tax_query Tax query array
     * @return array Modified tax query
     */
    private function translate_tax_query_terms( $tax_query ) {
        // Check if WPML is active
        if ( ! has_filter( 'wpml_current_language' ) || ! has_filter( 'wpml_object_id' ) ) {
            return $tax_query;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        
        if ( empty( $current_language ) ) {
            return $tax_query;
        }

        foreach ( $tax_query as $key => $query ) {
            if ( ! is_array( $query ) || empty( $query['terms'] ) ) {
                continue;
            }

            $taxonomy = isset( $query['taxonomy'] ) ? $query['taxonomy'] : '';
            
            if ( empty( $taxonomy ) ) {
                continue;
            }

            // Skip if not a Directorist taxonomy
            if ( ! in_array( $taxonomy, [ ATBDP_CATEGORY, ATBDP_LOCATION, ATBDP_TAGS, ATBDP_TYPE ] ) ) {
                continue;
            }

            // Translate term IDs to current language
            $terms = $query['terms'];
            
            if ( ! is_array( $terms ) ) {
                $terms = [ $terms ];
            }

            $translated_terms = [];
            
            foreach ( $terms as $term ) {
                if ( is_numeric( $term ) ) {
                    // Term ID - translate it
                    $translated_term_id = apply_filters( 
                        'wpml_object_id', 
                        $term, 
                        $taxonomy, 
                        false, 
                        $current_language 
                    );
                    
                    if ( $translated_term_id ) {
                        $translated_terms[] = $translated_term_id;
                    }
                } else {
                    // Term slug - get translated slug
                    $term_obj = get_term_by( 'slug', $term, $taxonomy );
                    
                    if ( $term_obj ) {
                        $translated_term_id = apply_filters( 
                            'wpml_object_id', 
                            $term_obj->term_id, 
                            $taxonomy, 
                            false, 
                            $current_language 
                        );
                        
                        if ( $translated_term_id ) {
                            $translated_term = get_term( $translated_term_id, $taxonomy );
                            
                            if ( $translated_term && ! is_wp_error( $translated_term ) ) {
                                // Use ID if field is term_id, slug if field is slug
                                if ( isset( $query['field'] ) && 'slug' === $query['field'] ) {
                                    $translated_terms[] = $translated_term->slug;
                                } else {
                                    $translated_terms[] = $translated_term_id;
                                }
                            }
                        }
                    }
                }
            }

            if ( ! empty( $translated_terms ) ) {
                $tax_query[ $key ]['terms'] = $translated_terms;
            } else {
                // No translated terms found, set to empty to avoid showing all
                $tax_query[ $key ]['terms'] = [ -1 ]; // Will return no results
            }
        }

        return $tax_query;
    }

    /**
     * Ensure WPML filtering is enabled for Directorist queries
     * 
     * This hook runs BEFORE the query is created in DB::get_listings_data().
     * We ensure suppress_filters is false so WPML can add language filtering
     * to the SQL query via posts_where, posts_join filters.
     * 
     * @param array $args Query arguments
     * @return array Modified query arguments
     */
    public function ensure_wpml_filtering( $args ) {
        // Check if WPML is active
        if ( ! has_filter( 'wpml_current_language' ) ) {
            return $args;
        }

        // If post_type is Directorist listing, ensure WPML can filter
        if ( isset( $args['post_type'] ) && ATBDP_POST_TYPE === $args['post_type'] ) {
            // Force suppress_filters to false so WPML's posts_where/join filters work
            $args['suppress_filters'] = false;
            
            // Translate taxonomy term IDs in tax_query to current language
            // This ensures category/location/tag filters work correctly
            if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
                $args['tax_query'] = $this->translate_tax_query_terms( $args['tax_query'] );
            }

            if ( ! empty( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
                $args['meta_query'] = $this->normalize_directory_type_meta_query( $args['meta_query'] );
            }
        } else {
            // For other post types, just ensure suppress_filters is false if it was true
            if ( isset( $args['suppress_filters'] ) && $args['suppress_filters'] ) {
                $args['suppress_filters'] = false;
            }
        }

        return $args;
    }

    /**
     * Expand `_directory_type` meta queries to include all translated directory IDs.
     *
     * Listings translated before our sync hooks ran can still store the source
     * language directory ID in post meta. Matching the whole WPML translation
     * group keeps those listings visible on the correct archive page.
     *
     * @param array $meta_query Meta query array.
     * @return array
     */
    private function normalize_directory_type_meta_query( $meta_query ) {
        if ( ! is_array( $meta_query ) || ! has_filter( 'wpml_element_trid' ) || ! has_filter( 'wpml_get_element_translations' ) ) {
            return $meta_query;
        }

        foreach ( $meta_query as $key => $clause ) {
            if ( ! is_array( $clause ) ) {
                continue;
            }

            if ( isset( $clause['key'] ) && '_directory_type' === $clause['key'] && ! empty( $clause['value'] ) ) {
                $directory_ids = $this->get_directory_type_translation_ids( $clause['value'] );

                if ( empty( $directory_ids ) ) {
                    continue;
                }

                $compare = isset( $clause['compare'] ) ? strtoupper( $clause['compare'] ) : '=';

                if ( in_array( $compare, [ '!=', 'NOT IN' ], true ) ) {
                    $meta_query[ $key ]['compare'] = count( $directory_ids ) > 1 ? 'NOT IN' : '!=';
                } else {
                    $meta_query[ $key ]['compare'] = count( $directory_ids ) > 1 ? 'IN' : '=';
                }

                $meta_query[ $key ]['value'] = count( $directory_ids ) > 1 ? $directory_ids : $directory_ids[0];

                continue;
            }

            $meta_query[ $key ] = $this->normalize_directory_type_meta_query( $clause );
        }

        return $meta_query;
    }

    /**
     * Collect all translated term IDs for one or more directory types.
     *
     * @param mixed $directory_ids One ID or a list of IDs.
     * @return array
     */
    private function get_directory_type_translation_ids( $directory_ids ) {
        $directory_ids = wp_parse_id_list( (array) $directory_ids );

        if ( empty( $directory_ids ) ) {
            return [];
        }

        $translated_group_ids = [];

        foreach ( $directory_ids as $directory_id ) {
            $translated_group_ids[] = (int) $directory_id;

            $translations = WPML_Helper::get_element_translations( $directory_id, ATBDP_DIRECTORY_TYPE );
            if ( empty( $translations ) || ! is_array( $translations ) ) {
                continue;
            }

            foreach ( $translations as $translation ) {
                if ( ! is_object( $translation ) ) {
                    continue;
                }

                if ( ! empty( $translation->term_id ) ) {
                    $translated_group_ids[] = (int) $translation->term_id;
                    continue;
                }

                if ( ! empty( $translation->element_id ) ) {
                    $translated_group_ids[] = (int) $translation->element_id;
                }
            }
        }

        return array_values( array_unique( array_filter( wp_parse_id_list( $translated_group_ids ) ) ) );
    }

    /**
     * Filter query results to ensure only current language listings
     * 
     * This is a safety net filter. WPML should already filter at SQL level
     * when suppress_filters is false, but we double-check here for edge cases.
     * 
     * @param object $results Query results object
     * @return object Filtered results
     */
    public function filter_query_results( $results ) {
        // Validate results object
        if ( ! is_object( $results ) ) {
            return $results;
        }

        // Ensure ids is an array
        if ( ! isset( $results->ids ) || ! is_array( $results->ids ) ) {
            $results->ids = [];
        }

        // If no IDs, set total to 0 and return early
        if ( empty( $results->ids ) ) {
            $results->ids = [];
            $results->total = 0;
            $results->total_pages = 0;
            return $results;
        }

        // Check if WPML is active
        if ( ! has_filter( 'wpml_current_language' ) || ! has_filter( 'wpml_element_language_details' ) ) {
            return $results;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        $default_language = apply_filters( 'wpml_default_language', null );
        
        if ( empty( $current_language ) ) {
            return $results;
        }

        // Format element_type correctly for WPML (post_{post_type})
        $element_type = 'post_' . ATBDP_POST_TYPE;
        
        // Try to use global sitepress for more reliable language detection
        global $sitepress;
        $use_global = ( isset( $sitepress ) && is_object( $sitepress ) && method_exists( $sitepress, 'get_language_for_element' ) );
        
        // Filter IDs to only include listings in current language
        $filtered_ids = [];
        
        foreach ( $results->ids as $listing_id ) {
            // Skip invalid IDs
            if ( empty( $listing_id ) || ! is_numeric( $listing_id ) ) {
                continue;
            }
            
            $listing_id = (int) $listing_id;
            
            // Get language code for this listing
            $listing_language = null;
            
            if ( $use_global ) {
                // Use global sitepress object (more reliable)
                $listing_language = $sitepress->get_language_for_element( $listing_id, $element_type );
            } else {
                // Fallback to filter
                $language_info = apply_filters( 
                    'wpml_element_language_details', 
                    null, 
                    [
                        'element_id'   => $listing_id,
                        'element_type' => $element_type
                    ]
                );
                
                // Extract language code from language_info
                if ( ! empty( $language_info ) && is_object( $language_info ) ) {
                    if ( isset( $language_info->language_code ) ) {
                        $listing_language = $language_info->language_code;
                    } elseif ( isset( $language_info->language ) ) {
                        // Some WPML versions use 'language' instead of 'language_code'
                        $listing_language = $language_info->language;
                    }
                }
            }
            
            // Include listing if:
            // 1. Language matches current language, OR
            // 2. No language info found AND current language is default (fallback for unassigned listings)
            $should_include = false;
            
            if ( ! empty( $listing_language ) ) {
                // Listing has language info - include if it matches current language
                $should_include = ( $listing_language === $current_language );
            } else {
                // No language info found - include if current language is default
                // This handles edge cases where listings don't have language assigned
                if ( ! empty( $default_language ) && $current_language === $default_language ) {
                    $should_include = true;
                }
            }
            
            if ( $should_include ) {
                $filtered_ids[] = $listing_id;
            }
        }

        // Only update if we actually filtered something out
        // If WPML already filtered correctly at SQL level, this should be a no-op
        if ( count( $filtered_ids ) !== count( $results->ids ) ) {
            $results->ids = $filtered_ids;
            $filtered_count = count( $filtered_ids );
            
            // Force update total - this is critical for the count display
            $results->total = $filtered_count;
            
            // Also update found_posts if it exists (some templates might use this)
            if ( isset( $results->found_posts ) ) {
                $results->found_posts = $filtered_count;
            }
            
            // Recalculate total pages based on filtered count
            if ( ! empty( $results->per_page ) && $results->per_page > 0 ) {
                $results->total_pages = $filtered_count > 0 ? ceil( $filtered_count / $results->per_page ) : 0;
            } else {
                $results->total_pages = $filtered_count > 0 ? 1 : 0;
            }
        }

        return $results;
    }
}
