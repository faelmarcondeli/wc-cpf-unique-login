jQuery(function ($) {

    // Estado por tipo: 'valid' | 'invalid' | 'exists' | 'unknown'
    var _state = { cpf: 'unknown', cnpj: 'unknown' };
    var _lastValidatedValue = {};
    var _alertedValue = {};

    function fieldSelector(type) {
        return type === 'cpf' ? '#billing_cpf' : '#billing_cnpj';
    }

    function labelFor(type) {
        return type.toUpperCase();
    }

    function messageFor(state, type) {
        if (state === 'invalid') {
            return labelFor(type) + ' inválido. Verifique os dígitos digitados.';
        }
        if (state === 'exists') {
            return labelFor(type) + ' já cadastrado.';
        }
        return '';
    }

    /**
     * Faz a validação AJAX. Retorna uma Promise que resolve com o estado.
     */
    function validateDocumentAjax(value, type) {

        return $.ajax({
            url: wcDocUL.ajax_url,
            method: 'POST',
            data: {
                action: 'wc_validate_document',
                nonce:  wcDocUL.nonce,
                document: value,
                type:   type
            }
        }).then(function (res) {

            if (!res || !res.success) {
                return 'unknown';
            }

            var data = res.data || {};

            if (data.valid === false) return 'invalid';
            if (data.exists === true) return 'exists';
            return 'valid';
        }, function () {
            return 'unknown';
        });
    }

    /**
     * Atualiza visual e estado interno após validação.
     */
    function applyValidationResult($field, type, state, value, opts) {

        opts = opts || {};
        _state[type] = state;
        _lastValidatedValue[type] = value;

        if (state === 'invalid' || state === 'exists') {
            $field.addClass('woocommerce-invalid').removeClass('woocommerce-validated');

            if (opts.alert && _alertedValue[type] !== value) {
                _alertedValue[type] = value;
                window.alert(messageFor(state, type));
            }
        } else if (state === 'valid') {
            $field.removeClass('woocommerce-invalid').addClass('woocommerce-validated');
            delete _alertedValue[type];
        } else {
            $field.removeClass('woocommerce-invalid woocommerce-validated');
        }
    }

    /**
     * Valida o campo via AJAX (usado no blur). Mostra alerta se inválido.
     */
    function validateOnBlur($field, type) {

        var value = $field.val();
        if (!value) {
            _state[type] = 'unknown';
            return;
        }

        // Se já validou esse mesmo valor, não revalida
        if (_lastValidatedValue[type] === value && _state[type] !== 'unknown') {
            if ((_state[type] === 'invalid' || _state[type] === 'exists') && _alertedValue[type] !== value) {
                _alertedValue[type] = value;
                window.alert(messageFor(_state[type], type));
            }
            return;
        }

        validateDocumentAjax(value, type).then(function (state) {
            // Se o usuário mudou o valor durante a requisição, ignora
            if ($field.val() !== value) return;
            applyValidationResult($field, type, state, value, { alert: true });
        });
    }

    /**
     * Valida todos os campos de documento presentes no formulário antes do submit.
     * Retorna uma Promise que resolve com true (pode enviar) ou false (bloqueado).
     */
    function validateBeforeSubmit($form) {

        var promises = [];
        var hasError = false;
        var firstErrorMessage = '';

        ['cpf', 'cnpj'].forEach(function (type) {
            var $field = $form.find(fieldSelector(type));
            if (!$field.length) return;

            var value = $field.val();
            if (!value) return;

            // Se já temos resultado para esse valor, usa
            if (_lastValidatedValue[type] === value && _state[type] !== 'unknown') {
                if (_state[type] === 'invalid' || _state[type] === 'exists') {
                    hasError = true;
                    if (!firstErrorMessage) firstErrorMessage = messageFor(_state[type], type);
                    $field.addClass('woocommerce-invalid');
                }
                return;
            }

            // Caso contrário, valida agora
            promises.push(
                validateDocumentAjax(value, type).then(function (state) {
                    applyValidationResult($field, type, state, value);
                    if (state === 'invalid' || state === 'exists') {
                        hasError = true;
                        if (!firstErrorMessage) firstErrorMessage = messageFor(state, type);
                    }
                })
            );
        });

        return $.when.apply($, promises).then(function () {
            if (hasError) {
                window.alert(firstErrorMessage);
                return false;
            }
            return true;
        });
    }

    // ---- Listeners de blur (validação em tempo real) ----
    $(document).on('blur', '#billing_cpf', function () {
        validateOnBlur($(this), 'cpf');
    });

    $(document).on('blur', '#billing_cnpj', function () {
        validateOnBlur($(this), 'cnpj');
    });

    // ---- Reset ao editar o campo ----
    $(document).on('input', '#billing_cpf, #billing_cnpj', function () {
        var type = this.id === 'billing_cpf' ? 'cpf' : 'cnpj';
        _state[type] = 'unknown';
        delete _lastValidatedValue[type];
        delete _alertedValue[type];
        $(this).removeClass('woocommerce-invalid woocommerce-validated');
    });

    // ---- Bloqueio de submit nos formulários do WooCommerce ----
    // Cobre: registro (My Account), edição de conta, e qualquer form com campos billing_cpf/cnpj
    var formSelectors = [
        'form.woocommerce-form-register',
        'form.register',
        'form.edit-account',
        'form.woocommerce-EditAccountForm',
        'form.woocommerce-form-edit-account',
        'form.checkout',
        'form.woocommerce-checkout'
    ].join(', ');

    $(document).on('submit', formSelectors, function (e) {

        var $form = $(this);

        // Marca o formulário para evitar loop
        if ($form.data('wcDocSubmitting')) {
            return;
        }

        var $cpf  = $form.find('#billing_cpf');
        var $cnpj = $form.find('#billing_cnpj');

        if ((!$cpf.length || !$cpf.val()) && (!$cnpj.length || !$cnpj.val())) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        validateBeforeSubmit($form).then(function (canSubmit) {
            if (canSubmit) {
                $form.data('wcDocSubmitting', true);
                $form.trigger('submit');
            }
        });
    });

    // ---- Bloqueio do botão "Finalizar compra" do checkout ----
    // O WooCommerce checkout não usa submit normal — usa AJAX via 'checkout_place_order'.
    $(document).on('checkout_place_order', function () {

        var $form = $('form.checkout');
        if (!$form.length) return true;

        var $cpf  = $form.find('#billing_cpf');
        var $cnpj = $form.find('#billing_cnpj');

        if ((!$cpf.length || !$cpf.val()) && (!$cnpj.length || !$cnpj.val())) {
            return true;
        }

        // Se já validamos os valores atuais e estão OK, libera
        var allChecked = true;
        ['cpf', 'cnpj'].forEach(function (type) {
            var $field = $form.find(fieldSelector(type));
            if (!$field.length || !$field.val()) return;
            if (_lastValidatedValue[type] !== $field.val() || _state[type] === 'unknown') {
                allChecked = false;
            }
            if (_state[type] === 'invalid' || _state[type] === 'exists') {
                allChecked = false;
            }
        });

        if (allChecked) return true;

        // Caso contrário, dispara validação assíncrona e bloqueia esse submit
        validateBeforeSubmit($form).then(function (canSubmit) {
            if (canSubmit) {
                $form.data('wcDocSubmitting', true);
                $('#place_order').trigger('click');
            }
        });

        return false;
    });
});
