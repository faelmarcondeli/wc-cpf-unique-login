jQuery(function ($) {

    var _lastAlerted = {};
    var _lastValue   = {};

    function validateDocument($field, type) {

        var value = $field.val();
        if (!value) return;

        // Evita repetir alerta para o mesmo valor
        if (_lastAlerted[type] === value) return;

        // Evita disparar a mesma requisição duas vezes seguidas
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

            if (!res.success) return;

            var data = res.data || {};
            var label = type.toUpperCase();

            // 1. Documento inválido (dígitos verificadores incorretos)
            if (data.valid === false) {
                $field.addClass('woocommerce-invalid');
                if (_lastAlerted[type] !== value) {
                    _lastAlerted[type] = value;
                    alert(label + ' inválido. Verifique os dígitos digitados.');
                }
                return;
            }

            // 2. Documento já cadastrado por outro usuário
            if (data.exists) {
                $field.addClass('woocommerce-invalid');
                if (_lastAlerted[type] !== value) {
                    _lastAlerted[type] = value;
                    alert(label + ' já cadastrado.');
                }
                return;
            }

            // 3. Documento válido e disponível
            $field.removeClass('woocommerce-invalid');
            if (_lastAlerted[type] === value) {
                delete _lastAlerted[type];
            }
            delete _lastValue[type];
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
