<?php
/**
 * Plugin Name: WooCommerce CPF/CNPJ Único + Login por Documento
 * Description: CPF e CNPJ únicos, login por documento, AJAX e bloqueio após compra.
 * Version: 1.3.0
 * Author: Rafael Moreno
 * Text Domain: wc-cpf-unique-login
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_DOC_UL_VERSION', '1.4.0' );
define( 'WC_DOC_UL_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_DOC_UL_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', function () {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O plugin WooCommerce CPF/CNPJ Único + Login por Documento requer o WooCommerce ativo.', 'wc-cpf-unique-login' );
            echo '</p></div>';
        });
        return;
    }

    require_once WC_DOC_UL_PATH . 'includes/class-wc-document-unique-login.php';
    WC_Document_Unique_Login::init();
});
