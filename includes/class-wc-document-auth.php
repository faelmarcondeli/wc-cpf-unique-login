<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Auth {

    public function __construct() {

        add_filter( 'authenticate', [ $this, 'authenticate_with_document' ], 1, 3 );

        add_action( 'plugins_loaded', [ $this, 'remove_default_authenticators' ], 20 );
    }

    public function remove_default_authenticators() {
        remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
        remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
    }

    public function authenticate_with_document( $user, $username, $password ) {

        if ( $user instanceof WP_User ) {
            return $user;
        }

        if ( empty( $username ) || empty( $password ) ) {
            return $user;
        }

        if ( is_email( $username ) ) {
            return wp_authenticate_email_password( $user, $username, $password );
        }

        $doc = WC_Document_Unique_Login::normalize( $username );
        $len = strlen( $doc );

        if ( ! in_array( $len, [ 11, 14 ], true ) ) {
            return wp_authenticate_username_password( $user, $username, $password );
        }

        $meta_key = ( $len === 11 )
            ? WC_Document_Unique_Login::META_CPF
            : WC_Document_Unique_Login::META_CNPJ;

        $user_id = WC_Document_Unique_Login::get_user_by_document( $meta_key, $doc );

        if ( ! $user_id ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'woocommerce' )
            );
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'woocommerce' )
            );
        }

        if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'woocommerce' )
            );
        }

        return $user;
    }
}
