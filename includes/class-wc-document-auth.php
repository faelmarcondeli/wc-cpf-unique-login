<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Auth {

    public function __construct() {
        add_filter( 'authenticate', [ $this, 'authenticate_with_document' ], 15, 3 );
    }

    public function authenticate_with_document( $user, $username, $password ) {

        if ( $user instanceof WP_User || is_wp_error( $user ) ) {
            return $user;
        }

        if ( empty( $username ) || empty( $password ) ) {
            return $user;
        }

        if ( is_email( $username ) ) {
            return $user;
        }

        $doc = WC_Document_Unique_Login::normalize( $username );
        $len = strlen( $doc );

        if ( ! in_array( $len, [ 11, 14 ], true ) ) {
            return $user;
        }

        $meta_key = ( $len === 11 )
            ? WC_Document_Unique_Login::META_CPF
            : WC_Document_Unique_Login::META_CNPJ;

        $user_id = WC_Document_Unique_Login::get_user_by_document( $meta_key, $doc );

        if ( ! $user_id ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'wc-cpf-unique-login' )
            );
        }

        $found_user = get_user_by( 'id', $user_id );

        if ( ! $found_user ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'wc-cpf-unique-login' )
            );
        }

        if ( ! wp_check_password( $password, $found_user->user_pass, $found_user->ID ) ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'wc-cpf-unique-login' )
            );
        }

        return $found_user;
    }
}
