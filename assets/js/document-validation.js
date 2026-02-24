jQuery(function ($) {

    function validateDocument($field, type) {

        const value = $field.val();
        if (!value) return;

        $.post(wcDocUL.ajax_url, {
            action: 'wc_validate_document',
            nonce: wcDocUL.nonce,
            document: value,
            type: type
        }, function (res) {

            if (res.success && res.data.exists) {
                $field.addClass('woocommerce-invalid');
                alert(type.toUpperCase() + ' já cadastrado.');
            }
        });
    }

    $('#billing_cpf').on('blur', function () {
        validateDocument($(this), 'cpf');
    });

    $('#billing_cnpj').on('blur', function () {
        validateDocument($(this), 'cnpj');
    });
});
