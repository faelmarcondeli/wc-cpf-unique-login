<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Unique_Login {

    const META_CPF  = 'billing_cpf';
    const META_CNPJ = 'billing_cnpj';

    public static function init() {

        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-login-ui.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-validator.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-auth.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-ajax.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-lock.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-block.php';
        require_once WC_DOC_UL_PATH . 'includes/class-wc-document-password-reset.php';

        new WC_Document_Login_UI();
        new WC_Document_Validator();
        new WC_Document_Auth();
        new WC_Document_Ajax();
        new WC_Document_Lock();
        new WC_Document_Block();
        new WC_Document_Password_Reset();

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function enqueue_scripts() {

        if ( ! is_account_page() && ! is_checkout() ) {
            return;
        }

        wp_enqueue_script(
            'wc-document-validation',
            WC_DOC_UL_URL . 'assets/js/document-validation.js',
            [ 'jquery' ],
            WC_DOC_UL_VERSION,
            true
        );

        wp_localize_script( 'wc-document-validation', 'wcDocUL', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wc_doc_ul' ),
        ]);

        // CSS inline para garantir que o feedback visual apareça mesmo se o tema sobrescrever
        $css = '
            input.wc-doc-invalid,
            input.woocommerce-invalid#billing_cpf,
            input.woocommerce-invalid#billing_cnpj {
                border: 2px solid #d63638 !important;
                background-color: #fff5f5 !important;
            }
            input.wc-doc-valid,
            input.woocommerce-validated#billing_cpf,
            input.woocommerce-validated#billing_cnpj {
                border: 2px solid #00a32a !important;
            }
        ';
        wp_add_inline_style( 'woocommerce-general', $css );
    }

    public static function normalize( $value ) {
        return preg_replace( '/[^0-9]/', '', $value );
    }

    /**
     * Valida CPF (11 dígitos) usando o algoritmo dos dígitos verificadores.
     */
    public static function is_valid_cpf( $cpf ) {

        $cpf = self::normalize( $cpf );

        if ( strlen( $cpf ) !== 11 ) {
            return false;
        }

        if ( preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
            return false;
        }

        for ( $t = 9; $t < 11; $t++ ) {
            $sum = 0;
            for ( $i = 0; $i < $t; $i++ ) {
                $sum += (int) $cpf[ $i ] * ( ( $t + 1 ) - $i );
            }
            $digit = ( ( 10 * $sum ) % 11 ) % 10;
            if ( (int) $cpf[ $t ] !== $digit ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida CNPJ (14 dígitos) usando o algoritmo dos dígitos verificadores.
     */
    public static function is_valid_cnpj( $cnpj ) {

        $cnpj = self::normalize( $cnpj );

        if ( strlen( $cnpj ) !== 14 ) {
            return false;
        }

        if ( preg_match( '/^(\d)\1{13}$/', $cnpj ) ) {
            return false;
        }

        $weights1 = [ 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ];
        $weights2 = [ 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ];

        $sum = 0;
        for ( $i = 0; $i < 12; $i++ ) {
            $sum += (int) $cnpj[ $i ] * $weights1[ $i ];
        }
        $digit1 = ( $sum % 11 < 2 ) ? 0 : 11 - ( $sum % 11 );

        if ( (int) $cnpj[12] !== $digit1 ) {
            return false;
        }

        $sum = 0;
        for ( $i = 0; $i < 13; $i++ ) {
            $sum += (int) $cnpj[ $i ] * $weights2[ $i ];
        }
        $digit2 = ( $sum % 11 < 2 ) ? 0 : 11 - ( $sum % 11 );

        return (int) $cnpj[13] === $digit2;
    }

    public static function get_user_by_document( $meta_key, $value ) {
        global $wpdb;

        $value = preg_replace( '/[^0-9]/', '', $value );

        if ( empty( $value ) ) {
            return null;
        }

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND REPLACE(REPLACE(REPLACE(meta_value, '.', ''), '-', ''), '/', '') = %s
             LIMIT 1",
            $meta_key,
            $value
        ) );
    }

    /**
     * Retorna TODOS os IDs de usuários que possuem o documento (qualquer formatação).
     * Útil para detectar ambiguidade causada por duplicatas legadas.
     *
     * @return int[]
     */
    public static function get_all_users_by_document( $meta_key, $value ) {
        global $wpdb;

        $value = preg_replace( '/[^0-9]/', '', $value );
        if ( empty( $value ) ) {
            return [];
        }

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND REPLACE(REPLACE(REPLACE(meta_value, '.', ''), '-', ''), '/', '') = %s",
            $meta_key,
            $value
        ) );

        return array_map( 'intval', (array) $ids );
    }

    /**
     * Verifica se o usuário informado já tem esse documento salvo (em qualquer formatação).
     */
    public static function user_owns_document( $user_id, $meta_key, $value ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return false;
        }
        $value = preg_replace( '/[^0-9]/', '', $value );
        if ( empty( $value ) ) {
            return false;
        }
        $own = get_user_meta( $user_id, $meta_key, true );
        if ( empty( $own ) ) {
            return false;
        }
        return preg_replace( '/[^0-9]/', '', $own ) === $value;
    }

    /**
     * Regra única de duplicidade usada em todo o plugin (registro, checkout, AJAX).
     *
     * Não considera duplicado se:
     *   1. O usuário logado já é dono desse documento.
     *   2. O email informado bate com o do dono do documento (mesma pessoa em guest).
     *
     * @return bool true se for considerado duplicado.
     */
    public static function is_duplicate_document( $meta_key, $value, $current_user_id = 0, $email = '' ) {

        $current_user_id = (int) $current_user_id;

        // 1. Próprio usuário já é dono: nunca é duplicidade.
        if ( self::user_owns_document( $current_user_id, $meta_key, $value ) ) {
            return false;
        }

        // 2. Existe em algum OUTRO usuário?
        $found = self::document_exists_for_other_user( $meta_key, $value, $current_user_id );
        if ( ! $found ) {
            return false;
        }

        // 3. Convidado/usuário com email igual ao dono: mesma pessoa, libera.
        $email = strtolower( trim( $email ) );
        if ( $email ) {
            $found_user = get_userdata( $found );
            if ( $found_user && strtolower( $found_user->user_email ) === $email ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica se o documento pertence a algum usuário DIFERENTE do informado.
     * Robusto contra dados legados duplicados no banco.
     */
    public static function document_exists_for_other_user( $meta_key, $value, $exclude_user_id = 0 ) {
        global $wpdb;

        $value = preg_replace( '/[^0-9]/', '', $value );
        if ( empty( $value ) ) {
            return false;
        }

        $exclude_user_id = (int) $exclude_user_id;

        if ( $exclude_user_id > 0 ) {
            $found = $wpdb->get_var( $wpdb->prepare(
                "SELECT user_id
                 FROM {$wpdb->usermeta}
                 WHERE meta_key = %s
                   AND user_id != %d
                   AND REPLACE(REPLACE(REPLACE(meta_value, '.', ''), '-', ''), '/', '') = %s
                 LIMIT 1",
                $meta_key,
                $exclude_user_id,
                $value
            ) );
        } else {
            $found = $wpdb->get_var( $wpdb->prepare(
                "SELECT user_id
                 FROM {$wpdb->usermeta}
                 WHERE meta_key = %s
                   AND REPLACE(REPLACE(REPLACE(meta_value, '.', ''), '-', ''), '/', '') = %s
                 LIMIT 1",
                $meta_key,
                $value
            ) );
        }

        return ! empty( $found ) ? (int) $found : false;
    }
}
