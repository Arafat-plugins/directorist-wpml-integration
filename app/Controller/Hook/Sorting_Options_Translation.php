<?php
/**
 * Sorting Options Translation
 * 
 * Translates hardcoded sorting/orderby and view options from the current page
 * ATE map. Page_Shortcode_UI_Translation collects these labels into the page
 * job, so this class does not register String Translation rows.
 * 
 * @package Directorist_WPML_Integration
 * @since 2.1.7
 */

namespace Directorist_WPML_Integration\Controller\Hook;

class Sorting_Options_Translation {

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct() {
        // Filter the orderby options to translate labels
        add_filter( 'atbdp_get_listings_orderby_options', [ $this, 'translate_orderby_options' ], 20, 1 );
        
        // Filter the view as link list via template filter
        // Since there's no filter for atbdp_get_listings_view_options, we filter the template output
        add_filter( 'directorist_template', [ $this, 'translate_view_options_in_template' ], 10, 2 );
        
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
     * Get all sorting option strings that need translation
     * 
     * These match the strings in atbdp_get_listings_orderby_options()
     * located in directorist/includes/helper-functions.php
     * 
     * @return array Key => English string pairs
     */
    private function get_sorting_strings() {
        return [
            'title-asc'  => 'A to Z (title)',
            'title-desc' => 'Z to A (title)',
            'date-desc'  => 'Latest listings',
            'date-asc'   => 'Oldest listings',
            'views-desc' => 'Popular listings',
            'price-asc'  => 'Price (low to high)',
            'price-desc' => 'Price (high to low)',
            'rand'       => 'Random listings',
        ];
    }

    /**
     * Get all view option strings that need translation
     * 
     * These match the strings in atbdp_get_listings_view_options()
     * located in directorist/includes/helper-functions.php
     * 
     * @return array Key => English string pairs
     */
    private function get_view_strings() {
        return [
            'grid' => 'Grid',
            'list' => 'List',
            'map'  => 'Map',
        ];
    }

    /**
     * Translate orderby options
     * 
     * Hook: atbdp_get_listings_orderby_options
     * Priority: 20 (after Directorist filters out disabled options)
     * 
     * @param array $orderby_options Array of orderby options [key => label]
     * @return array Translated orderby options
     */
    public function translate_orderby_options( $orderby_options ) {
        if ( ! $this->is_wpml_active() ) {
            return $orderby_options;
        }

        if ( empty( $orderby_options ) || ! is_array( $orderby_options ) ) {
            return $orderby_options;
        }

        $sorting_strings = $this->get_sorting_strings();

        foreach ( $orderby_options as $key => $label ) {
            // Check if this is a known sorting option
            if ( ! isset( $sorting_strings[ $key ] ) ) {
                continue;
            }

            $original_label = $sorting_strings[ $key ];
            $translated      = $this->translate_page_ui_string( $original_label, 'listing_archive' );
            
            // Only update if we got a translation
            if ( ! empty( $translated ) && $translated !== $original_label ) {
                $orderby_options[ $key ] = $translated;
            }
        }

        return $orderby_options;
    }

    /**
     * Translate view options in template args
     * 
     * Hook: directorist_template
     * Priority: 10
     * 
     * Since there's no filter for atbdp_get_listings_view_options(),
     * we intercept the template loading and modify the listings object's views property.
     * 
     * @param string $template Template name
     * @param array $args Template arguments
     * @return string Template name (unchanged)
     */
    public function translate_view_options_in_template( $template, $args ) {
        // Only process viewas dropdown template
        if ( strpos( $template, 'archive/viewas-dropdown' ) === false ) {
            return $template;
        }

        if ( ! $this->is_wpml_active() ) {
            return $template;
        }

        // Check if listings object exists with views property
        if ( empty( $args['listings'] ) || ! is_object( $args['listings'] ) ) {
            return $template;
        }

        $listings = $args['listings'];
        
        if ( ! isset( $listings->views ) || ! is_array( $listings->views ) ) {
            return $template;
        }

        $view_strings = $this->get_view_strings();

        foreach ( $listings->views as $key => $label ) {
            if ( ! isset( $view_strings[ $key ] ) ) {
                continue;
            }

            $original_label = $view_strings[ $key ];
            $translated      = $this->translate_page_ui_string( $original_label, 'listing_archive' );
            
            // Only update if we got a translation
            if ( ! empty( $translated ) && $translated !== $original_label ) {
                $listings->views[ $key ] = $translated;
            }
        }

        return $template;
    }

    /**
     * Translate a label from the current page ATE map.
     *
     * @param string $string_value Original string value.
     * @param string $context      Optional page context.
     * @return string
     */
    private function translate_page_ui_string( $string_value, $context = '' ) {
        if ( ! function_exists( 'apply_filters' ) ) {
            return $string_value;
        }

        return apply_filters( 'directorist_wpml_translate_page_ui_string', $string_value, $string_value, $context );
    }
}
