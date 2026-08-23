<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Search_Form_Filter {

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct() {
        // Filter search queries to only show current language listings
        add_filter( 'directorist_all_listings_query_arguments', [ $this, 'filter_search_query' ], 10, 1 );
        add_filter( 'atbdp_listing_search_query_argument', [ $this, 'filter_search_query' ], 10, 1 );
    }

    /**
     * Filter search query to only include current language listings
     * 
     * @param array $args Query arguments
     * @return array Modified query arguments
     */
    public function filter_search_query( $args ) {
        // Ensure suppress_filters is false so WPML can filter
        if ( isset( $args['post_type'] ) && ATBDP_POST_TYPE === $args['post_type'] ) {
            $args['suppress_filters'] = false;
        }

        // If taxonomy query exists, translate term IDs to current language
        if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            $args['tax_query'] = $this->translate_tax_query_terms( $args['tax_query'] );
        }

        return $args;
    }

    /**
     * Filter taxonomy terms to show only current language terms
     * 
     * @param array $terms Terms array
     * @param string $taxonomy Taxonomy name
     * @return array Filtered terms
     */
    public function filter_taxonomy_terms( $terms, $taxonomy ) {
        if ( empty( $terms ) || ! is_array( $terms ) ) {
            return $terms;
        }

        // Check if WPML is active
        if ( ! has_filter( 'wpml_current_language' ) || ! has_filter( 'wpml_element_language_details' ) ) {
            return $terms;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        
        if ( empty( $current_language ) ) {
            return $terms;
        }

        $filtered_terms = [];

        foreach ( $terms as $term ) {
            if ( is_object( $term ) ) {
                $term_id = $term->term_id;
            } elseif ( is_array( $term ) && isset( $term['term_id'] ) ) {
                $term_id = $term['term_id'];
            } else {
                continue;
            }

            // Check if term is in current language
            $term_language = WPML_Helper::get_language_info( $term_id, $taxonomy );

            if ( ! empty( $term_language ) && $term_language->language_code === $current_language ) {
                $filtered_terms[] = $term;
            }
        }

        return $filtered_terms;
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
                                $translated_terms[] = $translated_term->slug;
                            }
                        }
                    }
                }
            }

            if ( ! empty( $translated_terms ) ) {
                $tax_query[ $key ]['terms'] = $translated_terms;
            } else {
                // No translated terms found, remove this query to avoid showing all
                unset( $tax_query[ $key ] );
            }
        }

        $normalized_tax_query = [];

        if ( isset( $tax_query['relation'] ) ) {
            $normalized_tax_query['relation'] = $tax_query['relation'];
        }

        foreach ( $tax_query as $key => $query ) {
            if ( 'relation' === $key || ! is_array( $query ) ) {
                continue;
            }

            $normalized_tax_query[] = $query;
        }

        return $normalized_tax_query;
    }

    /**
     * Ensure search queries filter by language
     * 
     * @param array $args Query arguments
     * @return void
     */
    public function ensure_search_language_filter( $args ) {
        if ( isset( $args['post_type'] ) && ATBDP_POST_TYPE === $args['post_type'] ) {
            $args['suppress_filters'] = false;
        }
    }
}

