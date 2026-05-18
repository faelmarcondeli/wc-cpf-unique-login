<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Validator {

    public function __construct() {
        add_action( 'woocommerce_register_post', [ $this, 'validate_unique' ], 10, 3 );
        add_action( 'woocommerce_created_customer', [ $this, 'save_normalized' ] );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_checkout' ], 10, 2 );
    }

    public function validate_unique( $username, $email, $errors ) {

        // Evita dupla execução na mesma requisição (WooCommerce pode disparar
        // este hook mais de uma vez durante o checkout com criação de conta).
        static $already_validated = false;
        if ( $already_validated ) {
            return;
        }
        $already_validated = true;

        $current_user_id = get_current_user_id();
        $email_lower     = strtolower( $email );

        if ( ! empty( $_POST['billing_cpf'] ) ) {
            $cpf = WC_Document_Unique_Login::normalize( $_POST['billing_cpf'] );

            if ( ! WC_Document_Unique_Login::is_valid_cpf( $cpf ) ) {
                $errors->add( 'cpf_invalid', __( 'CPF inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } elseif ( $this->is_duplicate( WC_Document_Unique_Login::META_CPF, $cpf, $current_user_id, $email_lower ) ) {
                $errors->add( 'cpf_exists', __( 'Este CPF já está cadastrado.', 'wc-cpf-unique-login' ) );
            }
        }

        if ( ! empty( $_POST['billing_cnpj'] ) ) {
            $cnpj = WC_Document_Unique_Login::normalize( $_POST['billing_cnpj'] );

            if ( ! WC_Document_Unique_Login::is_valid_cnpj( $cnpj ) ) {
                $errors->add( 'cnpj_invalid', __( 'CNPJ inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } elseif ( $this->is_duplicate( WC_Document_Unique_Login::META_CNPJ, $cnpj, $current_user_id, $email_lower ) ) {
                $errors->add( 'cnpj_exists', __( 'Este CNPJ já está cadastrado.', 'wc-cpf-unique-login' ) );
            }
        }
    }

    /**
     * Validação no checkout (mesmo sem criação de conta) — bloqueia documento inválido.
     */
    public function validate_checkout( $data, $errors ) {

        $current_user_id = get_current_user_id();
        $email           = isset( $data['billing_email'] ) ? strtolower( $data['billing_email'] ) : '';

        if ( ! empty( $data['billing_cpf'] ) ) {
            $cpf = WC_Document_Unique_Login::normalize( $data['billing_cpf'] );

            if ( ! WC_Document_Unique_Login::is_valid_cpf( $cpf ) ) {
                $errors->add( 'cpf_invalid', __( 'CPF inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } elseif ( $this->is_duplicate( WC_Document_Unique_Login::META_CPF, $cpf, $current_user_id, $email ) ) {
                $errors->add( 'cpf_exists', __( 'Este CPF já está cadastrado.', 'wc-cpf-unique-login' ) );
            }
        }

        if ( ! empty( $data['billing_cnpj'] ) ) {
            $cnpj = WC_Document_Unique_Login::normalize( $data['billing_cnpj'] );

            if ( ! WC_Document_Unique_Login::is_valid_cnpj( $cnpj ) ) {
                $errors->add( 'cnpj_invalid', __( 'CNPJ inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } elseif ( $this->is_duplicate( WC_Document_Unique_Login::META_CNPJ, $cnpj, $current_user_id, $email ) ) {
                $errors->add( 'cnpj_exists', __( 'Este CNPJ já está cadastrado.', 'wc-cpf-unique-login' ) );
            }
        }
    }

    /**
     * Verifica duplicidade ignorando:
     * - O próprio usuário logado
     * - Convidados cujo email é igual ao do dono do documento (mesma pessoa)
     */
    private function is_duplicate( $meta_key, $value, $current_user_id, $email ) {

        $found = WC_Document_Unique_Login::document_exists_for_other_user( $meta_key, $value, $current_user_id );

        if ( ! $found ) {
            return false;
        }

        if ( $email ) {
            $found_user = get_userdata( $found );
            if ( $found_user && strtolower( $found_user->user_email ) === $email ) {
                return false;
            }
        }

        return true;
    }

    public function save_normalized( $customer_id ) {

        if ( ! empty( $_POST['billing_cpf'] ) ) {
            update_user_meta(
                $customer_id,
                WC_Document_Unique_Login::META_CPF,
                WC_Document_Unique_Login::normalize( $_POST['billing_cpf'] )
            );
        }

        if ( ! empty( $_POST['billing_cnpj'] ) ) {
            update_user_meta(
                $customer_id,
                WC_Document_Unique_Login::META_CNPJ,
                WC_Document_Unique_Login::normalize( $_POST['billing_cnpj'] )
            );
        }
    }
}
