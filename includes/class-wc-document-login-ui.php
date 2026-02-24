<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Login_UI {

    public function __construct() {

        // Placeholder e label do WooCommerce
        add_filter( 'woocommerce_form_field_args', [ $this, 'change_login_field_text' ], 10, 3 );

        // Fallback wp-login.php
        add_filter( 'gettext', [ $this, 'change_wp_login_text' ], 20, 3 );
    }

    /**
     * Altera placeholder e label do campo de login do WooCommerce
     */
    public function change_login_field_text( $args, $key, $value ) {

        if ( $key !== 'username' ) {
            return $args;
        }

        // Apenas em formulários de login
        if ( ! is_account_page() && ! is_checkout() ) {
            return $args;
        }

        $args['label']       = __( 'CPF, CNPJ ou e-mail', 'woocommerce' );
        $args['placeholder'] = __( 'CPF, CNPJ ou e-mail', 'woocommerce' );

        return $args;
    }

    /**
     * Fallback para wp-login.php / textos traduzidos
     */
    public function change_wp_login_text( $translated, $text, $domain ) {

        if (
            $domain === 'woocommerce' &&
            in_array( $text, [
                'Nome de usuário ou e-mail',
                'Username or email address',
                'Username or Email Address'
            ], true )
        ) {
            return __( 'CPF, CNPJ ou e-mail', 'woocommerce' );
        }

        return $translated;
    }
}
