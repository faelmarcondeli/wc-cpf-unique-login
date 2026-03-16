<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Block {

    const META_KEY = 'wc_login_blocked';

    public function __construct() {

        // Admin: exibir campo no perfil do usuário
        add_action( 'show_user_profile', [ $this, 'render_block_field' ] );
        add_action( 'edit_user_profile', [ $this, 'render_block_field' ] );

        // Admin: salvar campo ao atualizar perfil
        add_action( 'personal_options_update', [ $this, 'save_block_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_block_field' ] );

        // Autenticação: bloquear login se marcado
        add_filter( 'authenticate', [ $this, 'block_login_if_flagged' ], 30, 3 );

        // Redirecionar com parâmetro quando login falhar por bloqueio
        add_action( 'wp_login_failed', [ $this, 'redirect_blocked_user' ], 10, 2 );

        // Frontend: exibir mensagem na página de login/conta
        add_action( 'woocommerce_before_customer_login_form', [ $this, 'maybe_show_block_message' ] );
        add_action( 'login_message', [ $this, 'login_page_block_message' ] );
    }

    /**
     * Renderiza o campo de bloqueio na edição do usuário no wp-admin.
     */
    public function render_block_field( $user ) {

        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $blocked = (bool) get_user_meta( $user->ID, self::META_KEY, true );
        ?>
        <h2><?php esc_html_e( 'Restrição de Acesso', 'woocommerce' ); ?></h2>
        <table class="form-table">
            <tr>
                <th>
                    <label for="wc_login_blocked">
                        <?php esc_html_e( 'Bloquear Login do Cliente', 'woocommerce' ); ?>
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
                        <?php esc_html_e( 'Marcar este cliente como impossibilitado de realizar login no site', 'woocommerce' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Ao marcar esta opção, o cliente não conseguirá acessar o site e verá uma mensagem de contato.', 'woocommerce' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
        wp_nonce_field( 'wc_login_blocked_nonce_action', 'wc_login_blocked_nonce' );
    }

    /**
     * Salva o campo de bloqueio ao atualizar o perfil do usuário.
     */
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

    /**
     * Bloqueia o login retornando WP_Error se o usuário estiver marcado.
     */
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

    /**
     * Redireciona de volta à página de login com ?wc_blocked=1 quando o usuário está bloqueado.
     */
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

    /**
     * Exibe a mensagem de bloqueio na página de login do WooCommerce (My Account).
     * Só exibe se o parâmetro de erro estiver presente na URL.
     */
    public function maybe_show_block_message() {

        if ( isset( $_GET['wc_blocked'] ) && $_GET['wc_blocked'] === '1' ) {
            echo '<div class="message-container container alert-color medium-text-center">';
            echo esc_html( $this->get_block_message() );
            echo '</div>';
        }
    }

    /**
     * Exibe mensagem na página de login padrão do WordPress.
     */
    public function login_page_block_message( $message ) {

        if ( isset( $_GET['wc_blocked'] ) && $_GET['wc_blocked'] === '1' ) {
            $message .= '<div class="message-container container alert-color medium-text-center" style="margin-bottom:16px;">';
            $message .= esc_html( $this->get_block_message() );
            $message .= '</div>';
        }

        return $message;
    }

    /**
     * Retorna o texto da mensagem de bloqueio.
     */
    private function get_block_message() {
        return __( 'Cliente impossibilitado de realizar login em nosso site, por favor entre em contato conosco através do fale conosco', 'woocommerce' );
    }
}
