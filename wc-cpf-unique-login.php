<?php
/**
 * Plugin Name: WooCommerce CPF/CNPJ Único + Login por Documento
 * Description: CPF e CNPJ únicos, login por documento, AJAX e bloqueio após compra.
 * Version: 1.2.0
 * Author: Rafael Moreno
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_DOC_UL_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_DOC_UL_URL', plugin_dir_url( __FILE__ ) );

require_once WC_DOC_UL_PATH . 'includes/class-wc-document-unique-login.php';

add_action( 'plugins_loaded', function () {
    WC_Document_Unique_Login::init();
});
