<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Directory_Type_Translation_Management_Button {

    /**
     * Admin script handle used by the integration plugin.
     *
     * @var string
     */
    const SCRIPT_HANDLE = 'directorist-wpml-integration-admin-main-script';

    /**
     * Admin style handle used by the integration plugin.
     *
     * @var string
     */
    const STYLE_HANDLE = 'directorist-wpml-integration-admin-main-style';

    /**
     * Directorist directory types list page hook suffix.
     *
     * @var string
     */
    const PAGE_HOOK_SUFFIX = 'at_biz_dir_page_atbdp-directory-types';

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_button_assets' ] );
    }

    /**
     * Add the WPML translation management button assets on the directory list page.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return void
     */
    public function enqueue_button_assets( $hook_suffix ) {
        if ( ! $this->should_render_button( $hook_suffix ) ) {
            return;
        }

        if ( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) || wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
            wp_add_inline_script( self::SCRIPT_HANDLE, $this->get_inline_script(), 'after' );
        }

        if ( wp_style_is( self::STYLE_HANDLE, 'registered' ) || wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
            wp_add_inline_style( self::STYLE_HANDLE, $this->get_inline_style() );
        }
    }

    /**
     * Check whether the button should render.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return bool
     */
    private function should_render_button( $hook_suffix ) {
        if ( self::PAGE_HOOK_SUFFIX !== $hook_suffix ) {
            return false;
        }

        $page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

        if ( 'atbdp-directory-types' !== $page || 'edit' === $action ) {
            return false;
        }

        if ( ! $this->is_wpml_translation_management_available() ) {
            return false;
        }

        return true;
    }

    /**
     * Check whether WPML and Translation Management are available.
     *
     * @return bool
     */
    private function is_wpml_translation_management_available() {
        return defined( 'ICL_SITEPRESS_VERSION' ) && defined( 'WPML_TM_VERSION' );
    }

    /**
     * Build the Translation Management dashboard URL.
     *
     * @return string
     */
    private function get_translation_dashboard_url() {
        return (string) apply_filters(
            'directorist_wpml_integration_translation_dashboard_url',
            admin_url( 'admin.php?page=tm/menu/main.php' )
        );
    }

    /**
     * Build the button label.
     *
     * @return string
     */
    private function get_button_label() {
        return (string) apply_filters(
            'directorist_wpml_integration_translation_dashboard_label',
            __( 'WPML Translations', 'directorist-wpml-integration' )
        );
    }

    /**
     * Get the inline script that injects the button into Directorist UI.
     *
     * @return string
     */
    private function get_inline_script() {
        $config = [
            'buttonLabel' => $this->get_button_label(),
            'buttonUrl'   => esc_url_raw( $this->get_translation_dashboard_url() ),
        ];

        $script = <<<'JS'
( function() {
    var config = __CONFIG__;

    if ( ! config || ! config.buttonUrl ) {
        return;
    }

    var createIcon = function() {
        return [
            '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">',
                '<path d="M10 1.66663C5.39763 1.66663 1.66667 5.39759 1.66667 9.99996C1.66667 14.6023 5.39763 18.3333 10 18.3333C14.6024 18.3333 18.3333 14.6023 18.3333 9.99996C18.3333 5.39759 14.6024 1.66663 10 1.66663ZM10 3.33329C11.4566 3.33329 12.7756 3.9192 13.7381 4.86993H10.8333C10.3731 4.86993 10 5.24303 10 5.70327C10 6.16351 10.3731 6.5366 10.8333 6.5366H15.0206C15.7237 7.52553 16.1388 8.73495 16.1388 10.0416C16.1388 10.2477 16.1284 10.4513 16.1082 10.6522H11.6667C11.2064 10.6522 10.8333 11.0253 10.8333 11.4855C10.8333 11.9457 11.2064 12.3188 11.6667 12.3188H15.6493C14.7488 14.549 12.5664 16.1388 10 16.1388C8.36215 16.1388 6.88104 15.4912 5.79199 14.4388H8.33333C8.79357 14.4388 9.16667 14.0657 9.16667 13.6055C9.16667 13.1452 8.79357 12.7721 8.33333 12.7721H4.6891C4.10842 11.8887 3.76987 10.8301 3.76987 9.69471C3.76987 9.49445 3.78038 9.29665 3.80076 9.10188H8.33333C8.79357 9.10188 9.16667 8.72878 9.16667 8.26854C9.16667 7.8083 8.79357 7.43521 8.33333 7.43521H4.24066C5.1793 5.06416 7.49382 3.33329 10 3.33329Z" fill="currentColor"/>',
            '</svg>'
        ].join( '' );
    };

    var injectButton = function() {
        var wrapper = document.querySelector( '.directorist-total-types .directorist_link-block-wrapper' );

        if ( ! wrapper || wrapper.querySelector( '.directorist-wpml-translation-management-button' ) ) {
            return;
        }

        var button = document.createElement( 'button' );
        button.type = 'button';
        button.className = 'directorist_link-block directorist_link-block-primary-outline directorist-wpml-translation-management-button';
        button.innerHTML = [
            '<span class="directorist_link-icon directorist-wpml-translation-management-button__icon">' + createIcon() + '</span>',
            '<span class="directorist_link-text"></span>'
        ].join( '' );

        button.querySelector( '.directorist_link-text' ).textContent = config.buttonLabel || '';
        button.addEventListener( 'click', function() {
            window.location.href = config.buttonUrl;
        } );

        wrapper.insertBefore( button, wrapper.firstElementChild );
    };

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', injectButton );
        return;
    }

    injectButton();
}() );
JS;

        return str_replace( '__CONFIG__', wp_json_encode( $config ), $script );
    }

    /**
     * Get small admin style adjustments for the injected button.
     *
     * @return string
     */
    private function get_inline_style() {
        return '
            .directorist-wpml-translation-management-button {
                white-space: nowrap;
            }

            .directorist-wpml-translation-management-button__icon svg {
                display: block;
            }
        ';
    }
}
