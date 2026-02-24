<?php

defined( 'ABSPATH' ) || exit;

class WC_Document_Lock {

    public function __construct() {
        add_filter( 'woocommerce_customer_meta_fields', [ $this, 'lock_fields' ] );
    }

    public function lock_fields( $fields ) {

        $user_id = get_current_user_id();
        if ( ! $user_id ) return $fields;

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => 1,
            'status'      => [ 'processing', 'completed' ],
        ]);

        if ( empty( $orders ) ) return $fields;

        foreach ( [ WC_Document_Unique_Login::META_CPF, WC_Document_Unique_Login::META_CNPJ ] as $key ) {
            if ( isset( $fields['billing']['fields'][ $key ] ) ) {
                $fields['billing']['fields'][ $key ]['custom_attributes']['readonly'] = 'readonly';
            }
        }

        return $fields;
    }
}
