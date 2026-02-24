<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Login_UI {

    private static $script_injected = false;

    public function __construct() {

        add_filter( 'woocommerce_form_field_args', [ $this, 'change_login_field_text' ], 10, 3 );

        add_filter( 'gettext', [ $this, 'change_wp_login_text' ], 20, 3 );

        add_action( 'woocommerce_login_form', [ $this, 'inject_login_field_script' ] );
        add_action( 'woocommerce_before_checkout_form', [ $this, 'inject_login_field_script' ] );
    }

    public function change_login_field_text( $args, $key, $value ) {

        if ( $key !== 'username' ) {
            return $args;
        }

        if ( ! is_account_page() && ! is_checkout() ) {
            return $args;
        }

        $args['label']       = __( 'CPF, CNPJ ou e-mail', 'woocommerce' );
        $args['placeholder'] = __( 'CPF, CNPJ ou e-mail', 'woocommerce' );

        return $args;
    }

    public function change_wp_login_text( $translated, $text, $domain ) {

        if ( $domain !== 'woocommerce' ) {
            return $translated;
        }

        $login_texts = [
            'Nome de usuário ou e-mail',
            'Username or email address',
            'Username or Email Address',
            'Username or email',
            'Username or email address.',
        ];

        if ( in_array( $text, $login_texts, true ) ) {
            return 'CPF, CNPJ ou e-mail';
        }

        return $translated;
    }

    public function inject_login_field_script() {

        if ( self::$script_injected ) {
            return;
        }

        self::$script_injected = true;
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var fields = document.querySelectorAll('#username, .woocommerce-form-login #username');
            fields.forEach(function(field) {
                field.setAttribute('placeholder', 'CPF, CNPJ ou e-mail');
                var label = field.closest('.form-row, .woocommerce-form-row');
                if (label) {
                    var labelEl = label.querySelector('label[for="username"]');
                    if (labelEl) {
                        var required = labelEl.querySelector('.required');
                        labelEl.textContent = 'CPF, CNPJ ou e-mail ';
                        if (required) {
                            labelEl.appendChild(required);
                        }
                    }
                }
            });
        });
        </script>
        <?php
    }
}
