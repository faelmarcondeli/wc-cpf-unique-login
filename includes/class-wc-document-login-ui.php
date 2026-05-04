<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Login_UI {

    const LOGIN_LABEL = 'CPF, CNPJ ou e-mail';

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

        $args['label']       = self::LOGIN_LABEL;
        $args['placeholder'] = self::LOGIN_LABEL;

        return $args;
    }

    public function change_wp_login_text( $translated, $text, $domain ) {

        if ( $domain !== 'woocommerce' ) {
            return $translated;
        }

        $normalized = strtolower( trim( $text ) );

        if ( strpos( $normalized, 'username' ) !== false && strpos( $normalized, 'email' ) !== false ) {
            return self::LOGIN_LABEL;
        }

        if ( strpos( $normalized, 'nome de usu' ) !== false && strpos( $normalized, 'mail' ) !== false ) {
            return self::LOGIN_LABEL;
        }

        return $translated;
    }

    public function inject_login_field_script() {

        if ( self::$script_injected ) {
            return;
        }

        self::$script_injected = true;
        $label = esc_js( self::LOGIN_LABEL );
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var loginLabel = <?php echo wp_json_encode( self::LOGIN_LABEL ); ?>;
            var fields = document.querySelectorAll('#username, .woocommerce-form-login #username');
            fields.forEach(function(field) {
                field.setAttribute('placeholder', loginLabel);
                var label = field.closest('.form-row, .woocommerce-form-row');
                if (label) {
                    var labelEl = label.querySelector('label[for="username"]');
                    if (labelEl) {
                        var required = labelEl.querySelector('.required');
                        labelEl.textContent = loginLabel + ' ';
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
