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
        wp_send_json_success([ 'exists' => false ]);
    }

    $meta_key = $type === 'cnpj'
        ? WC_Document_Unique_Login::META_CNPJ
        : WC_Document_Unique_Login::META_CPF;

    $found_user_id = WC_Document_Unique_Login::get_user_by_document( $meta_key, $doc );
    $current_user  = get_current_user_id();

    /**
     * 🔑 REGRA:
     * - Não existe → OK
     * - Existe e é do próprio usuário → OK
     * - Existe e é de outro usuário → ERRO
     */
    if ( ! $found_user_id || ( $current_user && (int) $found_user_id === (int) $current_user ) ) {
        wp_send_json_success([ 'exists' => false ]);
    }

    wp_send_json_success([ 'exists' => true ]);
}

}
