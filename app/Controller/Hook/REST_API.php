<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class REST_API {

    public function __construct() {
        add_action( 'directorist_rest_before_query', [ $this, 'set_content_language_before_rest_query' ], 20, 2 );
        add_action( 'directorist_rest_response', [ $this, 'set_content_language_in_rest_response_header' ], 20, 2 );
    }

    // Set content language before rest query
    public function set_content_language_before_rest_query( $type, $request ) {
        $language = ! empty( $request['language'] ) ? $request['language'] : '';

        if ( empty( $language ) && ! empty( $request['wpml_lang'] ) ) {
            $language = $request['wpml_lang'];
        }

        if ( empty( $language ) ) {
            $language = apply_filters( 'wpml_default_language', null );
        }

        if ( ! empty( $language ) ) {
            do_action( 'wpml_switch_language', $language );
        }
    }

    // Set content language in REST response header
    public function set_content_language_in_rest_response_header( $response ) {
        $current_language = apply_filters( 'wpml_current_language', null );
        $response->header( 'Content-Language', $current_language );
        
        return $response;
    }

}
