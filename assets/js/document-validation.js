jQuery(function ($) {

    var _lastAlerted = {};
    var _lastValue   = {};

    function validateDocument($field, type) {

        var value = $field.val();
        if (!value) return;

        // Se o valor não mudou desde a última vez que disparou o alerta, ignora
        if (_lastAlerted[type] === value) return;

        // Se o valor não mudou desde a última requisição ainda em andamento, ignora
        if (_lastValue[type] === value) return;

        _lastValue[type] = value;

        $.post(wcDocUL.ajax_url, {
            action: 'wc_validate_document',
            nonce:  wcDocUL.nonce,
            document: value,
            type:   type
        }, function (res) {

            // Se o valor do campo mudou enquanto a requisição estava em andamento, ignora
            if ($field.val() !== value) return;

            if (res.success && res.data.exists) {
                $field.addClass('woocommerce-invalid');

                // Registra o valor alertado para não repetir o alerta
                if (_lastAlerted[type] !== value) {
                    _lastAlerted[type] = value;
                    alert(type.toUpperCase() + ' já cadastrado.');
                }

            } else {
                $field.removeClass('woocommerce-invalid');

                // Reseta o controle se o usuário corrigiu o valor
                if (_lastAlerted[type] === value) {
                    delete _lastAlerted[type];
                }
                delete _lastValue[type];
            }
        });
    }

    $('#billing_cpf').on('blur', function () {
        validateDocument($(this), 'cpf');
    });

    $('#billing_cnpj').on('blur', function () {
        validateDocument($(this), 'cnpj');
    });

    // Reseta controles se o usuário editar o campo novamente
    $('#billing_cpf, #billing_cnpj').on('input', function () {
        var type = this.id === 'billing_cpf' ? 'cpf' : 'cnpj';
        delete _lastAlerted[type];
        delete _lastValue[type];
        $(this).removeClass('woocommerce-invalid');
    });
});
