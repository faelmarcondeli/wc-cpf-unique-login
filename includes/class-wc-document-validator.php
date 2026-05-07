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

        if ( ! empty( $_POST['billing_cpf'] ) ) {
            $cpf = WC_Document_Unique_Login::normalize( $_POST['billing_cpf'] );

            if ( ! WC_Document_Unique_Login::is_valid_cpf( $cpf ) ) {
                $errors->add( 'cpf_invalid', __( 'CPF inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } else {
                $found_user_id = WC_Document_Unique_Login::get_user_by_document(
                    WC_Document_Unique_Login::META_CPF, $cpf
                );

                if ( $found_user_id ) {
                    $found_user = get_userdata( $found_user_id );
                    if ( ! $found_user || strtolower( $found_user->user_email ) !== strtolower( $email ) ) {
                        $errors->add( 'cpf_exists', __( 'Este CPF já está cadastrado.', 'wc-cpf-unique-login' ) );
                    }
                }
            }
        }

        if ( ! empty( $_POST['billing_cnpj'] ) ) {
            $cnpj = WC_Document_Unique_Login::normalize( $_POST['billing_cnpj'] );

            if ( ! WC_Document_Unique_Login::is_valid_cnpj( $cnpj ) ) {
                $errors->add( 'cnpj_invalid', __( 'CNPJ inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } else {
                $found_user_id = WC_Document_Unique_Login::get_user_by_document(
                    WC_Document_Unique_Login::META_CNPJ, $cnpj
                );

                if ( $found_user_id ) {
                    $found_user = get_userdata( $found_user_id );
                    if ( ! $found_user || strtolower( $found_user->user_email ) !== strtolower( $email ) ) {
                        $errors->add( 'cnpj_exists', __( 'Este CNPJ já está cadastrado.', 'wc-cpf-unique-login' ) );
                    }
                }
            }
        }
    }

    /**
     * Validação no checkout (mesmo sem criação de conta) — bloqueia documento inválido.
     */
    public function validate_checkout( $data, $errors ) {

        if ( ! empty( $data['billing_cpf'] ) ) {
            $cpf = WC_Document_Unique_Login::normalize( $data['billing_cpf'] );
            if ( ! WC_Document_Unique_Login::is_valid_cpf( $cpf ) ) {
                $errors->add( 'cpf_invalid', __( 'CPF inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            }
        }

        if ( ! empty( $data['billing_cnpj'] ) ) {
            $cnpj = WC_Document_Unique_Login::normalize( $data['billing_cnpj'] );
            if ( ! WC_Document_Unique_Login::is_valid_cnpj( $cnpj ) ) {
                $errors->add( 'cnpj_invalid', __( 'CNPJ inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            }
        }
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
