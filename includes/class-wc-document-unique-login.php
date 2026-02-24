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
        
        new WC_Document_Login_UI();
        new WC_Document_Validator();
        new WC_Document_Auth();
        new WC_Document_Ajax();
        new WC_Document_Lock();

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
            '1.0',
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

    public static function get_user_by_document( $meta_key, $value ) {
    global $wpdb;

    // Valor SEM máscara (do login)
    $value = preg_replace( '/[^0-9]/', '', $value );

    return $wpdb->get_var( $wpdb->prepare(
        "
        SELECT user_id
        FROM {$wpdb->usermeta}
        WHERE meta_key = %s
        AND REPLACE(
            REPLACE(
                REPLACE(meta_value, '.', ''),
            '-', ''),
        '/', '') = %s
        LIMIT 1
        ",
        $meta_key,
        $value
    ) );
    }
}
