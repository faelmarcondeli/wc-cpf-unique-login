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

        $current_user_id    = get_current_user_id();
        $email              = isset( $data['billing_email'] ) ? strtolower( $data['billing_email'] ) : '';
        $is_creating_account = ! empty( $data['createaccount'] );

        if ( ! empty( $data['billing_cpf'] ) ) {
            $cpf = WC_Document_Unique_Login::normalize( $data['billing_cpf'] );

            if ( ! WC_Document_Unique_Login::is_valid_cpf( $cpf ) ) {
                $errors->add( 'cpf_invalid', __( 'CPF inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } else {
                $found = WC_Document_Unique_Login::get_user_by_document( WC_Document_Unique_Login::META_CPF, $cpf );
                if ( $found && $this->is_duplicate_for_checkout( $found, $current_user_id, $email, $is_creating_account ) ) {
                    $errors->add( 'cpf_exists', __( 'Este CPF já está cadastrado.', 'wc-cpf-unique-login' ) );
                }
            }
        }

        if ( ! empty( $data['billing_cnpj'] ) ) {
            $cnpj = WC_Document_Unique_Login::normalize( $data['billing_cnpj'] );

            if ( ! WC_Document_Unique_Login::is_valid_cnpj( $cnpj ) ) {
                $errors->add( 'cnpj_invalid', __( 'CNPJ inválido. Verifique os dígitos digitados.', 'wc-cpf-unique-login' ) );
            } else {
                $found = WC_Document_Unique_Login::get_user_by_document( WC_Document_Unique_Login::META_CNPJ, $cnpj );
                if ( $found && $this->is_duplicate_for_checkout( $found, $current_user_id, $email, $is_creating_account ) ) {
                    $errors->add( 'cnpj_exists', __( 'Este CNPJ já está cadastrado.', 'wc-cpf-unique-login' ) );
                }
            }
        }
    }

    /**
     * Decide se o documento encontrado caracteriza duplicidade no checkout.
     * - Usuário logado comprando com seu próprio doc: OK
     * - Convidado comprando com email igual ao do dono do doc: OK (mesma pessoa)
     * - Caso contrário: duplicidade
     */
    private function is_duplicate_for_checkout( $found_user_id, $current_user_id, $email, $is_creating_account ) {

        if ( $current_user_id && (int) $found_user_id === (int) $current_user_id ) {
            return false;
        }

        if ( ! $is_creating_account && $email ) {
            $found_user = get_userdata( $found_user_id );
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
