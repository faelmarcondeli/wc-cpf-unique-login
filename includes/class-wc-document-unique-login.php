<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Unique_Login {

    const META_CPF  = 'billing_cpf';
    const META_CNPJ = 'billing_cnpj';

    public static function init() {

        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-login-ui.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-validator.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-auth.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-ajax.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-lock.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-block.php';

        new WC_Document_Login_UI();
        new WC_Document_Validator();
        new WC_Document_Auth();
        new WC_Document_Ajax();
        new WC_Document_Lock();
        new WC_Document_Block();

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function enqueue_scripts() {

        if ( ! is_account_page() && ! is_checkout() ) {
            return;
        }

        wp_enqueue_script(
            'wc-document-validation',
            WC_DOC_UL_URL . 'assets/js/document-validation.js',
            [ 'jquery' ],
            WC_DOC_UL_VERSION,
            true
        );

        wp_localize_script( 'wc-document-validation', 'wcDocUL', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wc_doc_ul' ),
        ]);
    }

    public static function normalize( $value ) {
        return preg_replace( '/[^0-9]/', '', $value );
    }

    /**
     * Valida CPF (11 dígitos) usando o algoritmo dos dígitos verificadores.
     */
    public static function is_valid_cpf( $cpf ) {

        $cpf = self::normalize( $cpf );

        if ( strlen( $cpf ) !== 11 ) {
            return false;
        }

        if ( preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
            return false;
        }

        for ( $t = 9; $t < 11; $t++ ) {
            $sum = 0;
            for ( $i = 0; $i < $t; $i++ ) {
                $sum += (int) $cpf[ $i ] * ( ( $t + 1 ) - $i );
            }
            $digit = ( ( 10 * $sum ) % 11 ) % 10;
            if ( (int) $cpf[ $t ] !== $digit ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida CNPJ (14 dígitos) usando o algoritmo dos dígitos verificadores.
     */
    public static function is_valid_cnpj( $cnpj ) {

        $cnpj = self::normalize( $cnpj );

        if ( strlen( $cnpj ) !== 14 ) {
            return false;
        }

        if ( preg_match( '/^(\d)\1{13}$/', $cnpj ) ) {
            return false;
        }

        $weights1 = [ 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ];
        $weights2 = [ 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ];

        $sum = 0;
        for ( $i = 0; $i < 12; $i++ ) {
            $sum += (int) $cnpj[ $i ] * $weights1[ $i ];
        }
        $digit1 = ( $sum % 11 < 2 ) ? 0 : 11 - ( $sum % 11 );

        if ( (int) $cnpj[12] !== $digit1 ) {
            return false;
        }

        $sum = 0;
        for ( $i = 0; $i < 13; $i++ ) {
            $sum += (int) $cnpj[ $i ] * $weights2[ $i ];
        }
        $digit2 = ( $sum % 11 < 2 ) ? 0 : 11 - ( $sum % 11 );

        return (int) $cnpj[13] === $digit2;
    }

    public static function get_user_by_document( $meta_key, $value ) {
        global $wpdb;

        $value = preg_replace( '/[^0-9]/', '', $value );

        if ( empty( $value ) ) {
            return null;
        }

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND REPLACE(REPLACE(REPLACE(meta_value, '.', ''), '-', ''), '/', '') = %s
             LIMIT 1",
            $meta_key,
            $value
        ) );
    }
}
