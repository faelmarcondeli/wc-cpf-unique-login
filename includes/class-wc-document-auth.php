<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Auth {

    public function __construct() {

        // Intercepta autenticação ANTES do WP
        add_filter( 'authenticate', [ $this, 'authenticate_with_document' ], 1, 3 );

        // Remove autenticadores padrão NO TEMPO CERTO
        add_action( 'plugins_loaded', [ $this, 'remove_default_authenticators' ], 20 );
    }

    /**
     * Remove autenticação padrão do WP
     */
    public function remove_default_authenticators() {
        remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
        remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
    }

    /**
     * Autenticação com CPF / CNPJ / Email
     */
    public function authenticate_with_document( $user, $username, $password ) {

        // Já autenticado
        if ( $user instanceof WP_User ) {
            return $user;
        }

        // EMAIL → delega para WP (manual)
        if ( is_email( $username ) ) {
            return wp_authenticate_email_password( null, $username, $password );
        }

        $doc = WC_Document_Unique_Login::normalize( $username );
        $len = strlen( $doc );

        // Não é CPF nem CNPJ
        if ( ! in_array( $len, [ 11, 14 ], true ) ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'woocommerce' )
            );
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

        // ✅ Validação REAL da senha
        if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            return new WP_Error(
                'invalid_login',
                __( 'Documento ou senha inválidos.', 'woocommerce' )
            );
        }

        return $user;
    }
}
