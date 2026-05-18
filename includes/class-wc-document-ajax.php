<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_wc_validate_document', [ $this, 'validate' ] );
        add_action( 'wp_ajax_nopriv_wc_validate_document', [ $this, 'validate' ] );
    }

    public function validate() {

        check_ajax_referer( 'wc_doc_ul', 'nonce' );

        $doc  = WC_Document_Unique_Login::normalize( $_POST['document'] ?? '' );
        $type = sanitize_text_field( $_POST['type'] ?? '' );

        if ( empty( $doc ) ) {
            wp_send_json_success( [ 'exists' => false, 'valid' => true ] );
        }

        // Validação dos dígitos verificadores (algoritmo CPF/CNPJ).
        $is_valid = $type === 'cnpj'
            ? WC_Document_Unique_Login::is_valid_cnpj( $doc )
            : WC_Document_Unique_Login::is_valid_cpf( $doc );

        if ( ! $is_valid ) {
            wp_send_json_success( [ 'exists' => false, 'valid' => false ] );
        }

        $meta_key = $type === 'cnpj'
            ? WC_Document_Unique_Login::META_CNPJ
            : WC_Document_Unique_Login::META_CPF;

        $exists = WC_Document_Unique_Login::document_exists_for_other_user(
            $meta_key,
            $doc,
            get_current_user_id()
        );

        wp_send_json_success( [
            'exists' => (bool) $exists,
            'valid'  => true,
        ] );
    }
}
