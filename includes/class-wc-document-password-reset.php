<?php

defined( 'ABSPATH' ) || exit;

/**
 * Permite recuperar a senha usando CPF ou CNPJ, além do e-mail padrão.
 *
 * Estratégia: antes de qualquer handler de "esqueci minha senha" (WooCommerce
 * ou wp-login.php) processar o formulário, traduzimos o CPF/CNPJ informado em
 * $_POST['user_login'] para o login (username) real do usuário. Assim, tanto o
 * fluxo do WooCommerce quanto o do core encontram o usuário normalmente.
 */
class WC_Document_Password_Reset {

    public function __construct() {

        // Roda cedo, antes do WC_Form_Handler::process_lost_password() (prioridade 20)
        // e antes do processamento do wp-login.php (ambos após wp_loaded).
        add_action( 'wp_loaded', [ $this, 'translate_document_to_login' ], 5 );

        // Também cobre o fluxo moderno via filtro dedicado do core (WP 5.7+).
        add_filter( 'lostpassword_user_data', [ $this, 'find_user_by_document' ], 10, 2 );
    }

    /**
     * Mensagem de erro exibida quando o documento é ambíguo (duplicatas legadas).
     */
    private function ambiguous_message() {
        return __( 'Não foi possível identificar a conta por este documento. Por favor, utilize seu e-mail para recuperar a senha.', 'wc-cpf-unique-login' );
    }

    /**
     * Se $_POST['user_login'] for um CPF/CNPJ, substitui pelo login do usuário.
     */
    public function translate_document_to_login() {

        if ( empty( $_POST['user_login'] ) ) {
            return;
        }

        // Restringe a requisições de recuperação de senha.
        $is_lost_request =
            isset( $_POST['wc_reset_password'] )
            || ( isset( $_REQUEST['action'] ) && 'lostpassword' === $_REQUEST['action'] );

        if ( ! $is_lost_request ) {
            return;
        }

        $result = $this->resolve_user_from_document( wp_unslash( $_POST['user_login'] ) );

        // Ambiguidade (duplicata legada): aborta de forma segura via notice do WooCommerce.
        if ( 'ambiguous' === $result ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( $this->ambiguous_message(), 'error' );
                // Zera o login para o handler não prosseguir com conta errada.
                $_POST['user_login'] = '';
            }
            return;
        }

        if ( $result instanceof WP_User ) {
            $_POST['user_login'] = $result->user_login;
        }
    }

    /**
     * Filtro do core: retorna o usuário localizado por CPF/CNPJ quando aplicável.
     */
    public function find_user_by_document( $user_data, $errors ) {

        if ( $user_data instanceof WP_User ) {
            return $user_data;
        }

        if ( empty( $_POST['user_login'] ) ) {
            return $user_data;
        }

        $result = $this->resolve_user_from_document( wp_unslash( $_POST['user_login'] ) );

        if ( 'ambiguous' === $result ) {
            if ( is_wp_error( $errors ) ) {
                $errors->add( 'wc_doc_ambiguous', $this->ambiguous_message() );
            }
            return $user_data;
        }

        return ( $result instanceof WP_User ) ? $result : $user_data;
    }

    /**
     * Localiza um usuário a partir de um CPF/CNPJ.
     *
     * @return WP_User|string|null WP_User se único; 'ambiguous' se houver duplicatas;
     *                             null se não aplicável (e-mail, formato inválido ou inexistente).
     */
    private function resolve_user_from_document( $login ) {

        $login = trim( $login );

        // E-mail segue o fluxo padrão.
        if ( is_email( $login ) ) {
            return null;
        }

        $doc = WC_Document_Unique_Login::normalize( $login );
        $len = strlen( $doc );

        if ( ! in_array( $len, [ 11, 14 ], true ) ) {
            return null;
        }

        $meta_key = ( 11 === $len )
            ? WC_Document_Unique_Login::META_CPF
            : WC_Document_Unique_Login::META_CNPJ;

        $user_ids = WC_Document_Unique_Login::get_all_users_by_document( $meta_key, $doc );

        // Nenhum usuário: deixa o fluxo padrão tratar (mensagem genérica do core).
        if ( empty( $user_ids ) ) {
            return null;
        }

        // Mais de um usuário com o mesmo documento: ambíguo, não escolhe arbitrariamente.
        if ( count( $user_ids ) > 1 ) {
            return 'ambiguous';
        }

        $user = get_user_by( 'id', $user_ids[0] );

        return $user ? $user : null;
    }
}
