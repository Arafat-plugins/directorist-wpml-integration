<?php
/**
 * Plugin Name: Directorist WPML Integration
 * Plugin URI: https://github.com/sovware/directorist-wpml-integration
 * Description: WPML integration plugin for Directorist.
 * Requires Plugins: directorist
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 7.0
 * Author: wpWax
 * Author URI: https://directorist.com/about-us
 * Text Domain: directorist-wpml-integration
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

require dirname( __FILE__ ) . '/vendor/autoload.php';
require dirname( __FILE__ ) . '/app.php';

if ( ! function_exists( 'Directorist_WPML_Integration' ) ) {
    function Directorist_WPML_Integration() {
        return Directorist_WPML_Integration::get_instance();
    }
}

add_action( 'directorist_loaded', 'Directorist_WPML_Integration' );
