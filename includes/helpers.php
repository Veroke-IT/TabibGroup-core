<?php 

defined( 'ABSPATH' ) || exit;

// This function returns the base URL for the TabibGroup API, depending on whether test mode is enabled or not.
if ( ! function_exists( 'tg_base_url' ) ) {
    function tg_base_url() {
        static $url = null;

        if ( null === $url ) {
            $url = get_option( 'enable_test_mode', false )
                ? 'https://api-tabibgroup-revamp-dev.vproj.com'
                : 'https://tabibgroup.net';
        }

        return $url;
    }
}

// This function is used to generate the full API URL based on the base URL and the provided path.
if ( ! function_exists( 'tg_api_url' ) ) {
    function tg_api_url( $path = '' ) {
        return rtrim( tg_base_url(), '/' ) . '/' . ltrim( $path, '/' );
    }
}