<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Page_Setup_Translation {

    /**
     * Directorist page setup option keys.
     *
     * @var string[]
     */
    private $page_option_keys = [
        'add_listing_page',
        'all_listing_page',
        'user_dashboard',
        'signin_signup_page',
        'author_profile_page',
        'all_categories_page',
        'single_category_page',
        'all_locations_page',
        'single_location_page',
        'single_tag_page',
        'search_listing',
        'search_result_page',
        'checkout_page',
        'payment_receipt_page',
        'transaction_failure_page',
        'privacy_policy',
        'terms_conditions',
    ];

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'option_atbdp_option', [ $this, 'translate_page_setup_option_ids' ] );
        add_filter( 'pre_update_option_atbdp_option', [ $this, 'normalize_page_setup_option_ids' ], 10, 3 );
    }

    /**
     * Translate stored source page IDs into the current admin language for display.
     *
     * Directorist loads page setup values from the raw atbdp_option array while
     * the select options are built from get_pages(), which WPML already filters
     * by the current admin language. Without mapping the saved IDs, the selected
     * values appear empty after switching the admin language from the top bar.
     *
     * @param mixed $options Stored Directorist options.
     * @return mixed
     */
    public function translate_page_setup_option_ids( $options ) {
        if ( ! $this->should_translate_page_setup_options() || ! is_array( $options ) ) {
            return $options;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        $default_language = apply_filters( 'wpml_default_language', null );

        if ( empty( $current_language ) || empty( $default_language ) || $current_language === $default_language ) {
            return $options;
        }

        foreach ( $this->page_option_keys as $option_key ) {
            if ( empty( $options[ $option_key ] ) || ! is_numeric( $options[ $option_key ] ) ) {
                continue;
            }

            $translated_id = apply_filters(
                'wpml_object_id',
                (int) $options[ $option_key ],
                'page',
                false,
                $current_language
            );

            if ( ! empty( $translated_id ) ) {
                $options[ $option_key ] = (int) $translated_id;
            }
        }

        return $options;
    }

    /**
     * Normalize page setup selections back to the default language before save.
     *
     * This keeps Directorist's canonical settings in the source language even if
     * an admin saves the settings while browsing the dashboard in another
     * language.
     *
     * @param mixed  $new_value New option value.
     * @param mixed  $old_value Previous option value.
     * @param string $option    Option name.
     * @return mixed
     */
    public function normalize_page_setup_option_ids( $new_value, $old_value, $option ) {
        if ( ! $this->should_normalize_page_setup_options() || ! is_array( $new_value ) ) {
            return $new_value;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        $default_language = apply_filters( 'wpml_default_language', null );

        if ( empty( $current_language ) || empty( $default_language ) || $current_language === $default_language ) {
            return $new_value;
        }

        foreach ( $this->page_option_keys as $option_key ) {
            if ( empty( $new_value[ $option_key ] ) || ! is_numeric( $new_value[ $option_key ] ) ) {
                continue;
            }

            $source_id = apply_filters(
                'wpml_object_id',
                (int) $new_value[ $option_key ],
                'page',
                false,
                $default_language
            );

            if ( ! empty( $source_id ) ) {
                $new_value[ $option_key ] = (int) $source_id;
            }
        }

        return $new_value;
    }

    /**
     * Check whether we should translate page setup options for the admin screen.
     *
     * @return bool
     */
    private function should_translate_page_setup_options() {
        if ( ! $this->is_wpml_ready() || ! is_admin() ) {
            return false;
        }

        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( 'atbdp-settings' === $page ) {
            return true;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

        return false !== strpos( $request_uri, 'page=atbdp-settings' );
    }

    /**
     * Check whether we should normalize page setup options during save.
     *
     * @return bool
     */
    private function should_normalize_page_setup_options() {
        if ( ! $this->is_wpml_ready() || ! is_admin() || ! wp_doing_ajax() ) {
            return false;
        }

        if ( empty( $_POST['action'] ) ) {
            return false;
        }

        return 'save_settings_data' === sanitize_text_field( wp_unslash( $_POST['action'] ) );
    }

    /**
     * Check whether the WPML APIs we need are available.
     *
     * @return bool
     */
    private function is_wpml_ready() {
        return defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'apply_filters' ) && has_filter( 'wpml_object_id' );
    }
}
