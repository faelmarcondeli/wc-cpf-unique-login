<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Block {

    const META_KEY = 'wc_login_blocked';

    public function __construct() {

        add_action( 'show_user_profile', [ $this, 'render_block_field' ] );
        add_action( 'edit_user_profile', [ $this, 'render_block_field' ] );

        add_action( 'personal_options_update', [ $this, 'save_block_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_block_field' ] );

        add_filter( 'authenticate', [ $this, 'block_login_if_flagged' ], 30, 3 );

        add_action( 'wp_login_failed', [ $this, 'redirect_blocked_user' ], 10, 2 );

        add_action( 'woocommerce_before_customer_login_form', [ $this, 'maybe_show_block_message' ] );
        add_action( 'login_message', [ $this, 'login_page_block_message' ] );
    }

    public function render_block_field( $user ) {

        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $blocked = (bool) get_user_meta( $user->ID, self::META_KEY, true );
        ?>
        <h2><?php esc_html_e( 'Restrição de Acesso', 'wc-cpf-unique-login' ); ?></h2>
        <table class="form-table">
            <tr>
                <th>
                    <label for="wc_login_blocked">
                        <?php esc_html_e( 'Bloquear Login do Cliente', 'wc-cpf-unique-login' ); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="wc_login_blocked"
                            id="wc_login_blocked"
                            value="1"
                            <?php checked( $blocked ); ?>
                        />
                        <?php esc_html_e( 'Marcar este cliente como impossibilitado de realizar login no site', 'wc-cpf-unique-login' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Ao marcar esta opção, o cliente não conseguirá acessar o site e verá uma mensagem de contato.', 'wc-cpf-unique-login' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
        wp_nonce_field( 'wc_login_blocked_nonce_action', 'wc_login_blocked_nonce' );
    }

    public function save_block_field( $user_id ) {

        if ( ! isset( $_POST['wc_login_blocked_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_login_blocked_nonce'] ) ), 'wc_login_blocked_nonce_action' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $blocked = isset( $_POST['wc_login_blocked'] ) ? '1' : '0';
        update_user_meta( $user_id, self::META_KEY, $blocked );
    }

    public function block_login_if_flagged( $user, $username, $password ) {

        if ( ! ( $user instanceof WP_User ) ) {
            return $user;
        }

        if ( get_user_meta( $user->ID, self::META_KEY, true ) === '1' ) {
            return new WP_Error(
                'wc_login_blocked',
                $this->get_block_message()
            );
        }

        return $user;
    }

    public function redirect_blocked_user( $username, $error ) {

        if ( ! ( $error instanceof WP_Error ) ) {
            return;
        }

        if ( ! $error->has_errors() ) {
            return;
        }

        if ( ! in_array( 'wc_login_blocked', $error->get_error_codes(), true ) ) {
            return;
        }

        $redirect = add_query_arg( 'wc_blocked', '1', wp_login_url() );

        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $account_page = wc_get_page_permalink( 'myaccount' );
            if ( $account_page ) {
                $redirect = add_query_arg( 'wc_blocked', '1', $account_page );
            }
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public function maybe_show_block_message() {

        if ( isset( $_GET['wc_blocked'] ) && $_GET['wc_blocked'] === '1' ) {
            wc_print_notice( $this->get_block_message(), 'error' );
        }
    }

    public function login_page_block_message( $message ) {

        if ( isset( $_GET['wc_blocked'] ) && $_GET['wc_blocked'] === '1' ) {
            $message .= '<div id="login_error">' . esc_html( $this->get_block_message() ) . '</div>';
        }

        return $message;
    }

    private function get_block_message() {
        return __( 'Cliente impossibilitado de realizar login em nosso site, por favor entre em contato através do fale conosco', 'wc-cpf-unique-login' );
    }
}
