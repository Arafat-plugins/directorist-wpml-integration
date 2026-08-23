<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Email_Translation {

    /**
     * Constuctor
     * 
     * @return void
     */
    public function __construct() {
        add_action( 'directorist_before_send_email', [ $this, 'before_send_email' ], 20, 1 );
        add_action( 'directorist_after_send_email', [ $this, 'after_send_email' ], 20, 1 );
    }

    /**
     * Before Send Email
     * 
     * @param array $args
     * @return void
     */
    public function before_send_email( $args = [] ) {

        if ( empty( $args['recipient_type'] ) ) {
            return;
        }

        if ( 'user' !== $args['recipient_type'] ) {
            return;
        }

        if ( empty( $args['listing_id'] ) ) {
            return;
        }

        $element_id   = $args['listing_id'];
        $element_type = ATBDP_POST_TYPE;

        $get_language_args = [ 
            'element_id'   => $element_id,
            'element_type' => $element_type
        ];

        $language_info = apply_filters( 'wpml_element_language_details', null, $get_language_args );
        $language      = apply_filters( 'wpml_current_language', null );

        set_transient( 'directorist_wpml_integration_before_change_current_language', $language );

        if ( ! empty( $language_info ) ) {
            $language = $language_info->language_code;
        }

        do_action( 'wpml_switch_language', $language );
    }

    /**
     * Before Send Email
     * 
     * @param int $listing_id
     * @return void
     */
    public function after_send_email() {
        $previous_language = get_transient( 'directorist_wpml_integration_before_change_current_language' );
        
        if ( empty( $previous_language ) ) {
            return;
        }

        do_action( 'wpml_switch_language', $previous_language );
        delete_transient( 'directorist_wpml_integration_before_change_current_language' );
    }

    
}
