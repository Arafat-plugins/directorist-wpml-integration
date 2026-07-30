<?php
/**
 * Sorting Options Translation
 * 
 * Translates the hardcoded sorting/orderby options and view options in Directorist listings.
 * These strings use __() with 'directorist' text domain but may not be picked up
 * by WPML's automatic scanning, so we register and translate them manually.
 * 
 * @package Directorist_WPML_Integration
 * @since 2.1.7
 */

namespace Directorist_WPML_Integration\Controller\Hook;

class Sorting_Options_Translation {

    /**
     * WPML String Translation Domain
     * 
     * @var string
     */
    const WPML_DOMAIN = 'directorist-wpml-integration';

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
        
        // Register strings on init (only once, admin side preferred)
        add_action( 'init', [ $this, 'register_sorting_strings' ], 20 );
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
     * Register sorting and view strings with WPML String Translation
     * 
     * This ensures the strings appear in WPML > String Translation
     * under our domain for easy translation.
     * 
     * @return void
     */
    public function register_sorting_strings() {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        // Only register on admin or if explicitly needed
        // This reduces frontend overhead
        if ( ! is_admin() && ! $this->is_first_frontend_load() ) {
            return;
        }

        // Register sorting options
        $sorting_strings = $this->get_sorting_strings();
        foreach ( $sorting_strings as $key => $label ) {
            $string_name = 'sorting_option_' . $key;
            $this->register_wpml_string( $string_name, $label );
        }

        // Register view options
        $view_strings = $this->get_view_strings();
        foreach ( $view_strings as $key => $label ) {
            $string_name = 'view_option_' . $key;
            $this->register_wpml_string( $string_name, $label );
        }
    }

    /**
     * Check if this might be first frontend load (for initial registration)
     * 
     * @return bool
     */
    private function is_first_frontend_load() {
        // Check if any sorting strings are already registered
        // If not, we should register them
        if ( ! function_exists( 'icl_get_string_id' ) ) {
            return true;
        }

        $test_string_id = icl_get_string_id( 'A to Z (title)', self::WPML_DOMAIN, 'sorting_option_title-asc' );
        
        return empty( $test_string_id );
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

            $string_name = 'sorting_option_' . $key;
            
            // Get the original English string (in case Directorist already translated it)
            $original_label = $sorting_strings[ $key ];
            
            // Register the string (WPML ignores duplicates)
            $this->register_wpml_string( $string_name, $original_label );
            
            // Translate the string
            $translated = $this->translate_wpml_string( $original_label, $string_name );
            
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

            $string_name = 'view_option_' . $key;
            $original_label = $view_strings[ $key ];
            
            // Register the string (WPML ignores duplicates)
            $this->register_wpml_string( $string_name, $original_label );
            
            // Translate the string
            $translated = $this->translate_wpml_string( $original_label, $string_name );
            
            // Only update if we got a translation
            if ( ! empty( $translated ) && $translated !== $original_label ) {
                $listings->views[ $key ] = $translated;
            }
        }

        return $template;
    }

    /**
     * Register string with WPML
     * 
     * @param string $string_name String name/context
     * @param string $string_value String value
     * @return void
     */
    private function register_wpml_string( $string_name, $string_value ) {
        if ( ! function_exists( 'do_action' ) ) {
            return;
        }

        $default_language = apply_filters( 'wpml_default_language', null );
        $current_language = apply_filters( 'wpml_current_language', null );

        if ( $default_language && $current_language && $default_language !== $current_language ) {
            return;
        }
        
        if ( is_string( $string_value ) && ! empty( $string_value ) ) {
            do_action( 'wpml_register_single_string', self::WPML_DOMAIN, $string_name, $string_value );
        }
    }

    /**
     * Translate WPML string
     * 
     * @param string $string_value Original string value
     * @param string $string_name String name/context
     * @return string Translated string
     */
    private function translate_wpml_string( $string_value, $string_name ) {
        if ( ! function_exists( 'apply_filters' ) ) {
            return $string_value;
        }
        
        return apply_filters(
            'wpml_translate_single_string',
            $string_value,
            self::WPML_DOMAIN,
            $string_name
        );
    }
}
