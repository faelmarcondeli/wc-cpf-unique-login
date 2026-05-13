jQuery(function ($) {

    // ====================================================================
    // ALGORITMOS DE VALIDAÇÃO (mesmos do PHP, replicados no front)
    // ====================================================================

    function onlyDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function isValidCPF(cpf) {
        cpf = onlyDigits(cpf);
        if (cpf.length !== 11) return false;
        if (/^(\d)\1{10}$/.test(cpf)) return false;

        for (var t = 9; t < 11; t++) {
            var sum = 0;
            for (var i = 0; i < t; i++) {
                sum += parseInt(cpf.charAt(i), 10) * ((t + 1) - i);
            }
            var digit = ((10 * sum) % 11) % 10;
            if (parseInt(cpf.charAt(t), 10) !== digit) return false;
        }
        return true;
    }

    function isValidCNPJ(cnpj) {
        cnpj = onlyDigits(cnpj);
        if (cnpj.length !== 14) return false;
        if (/^(\d)\1{13}$/.test(cnpj)) return false;

        var w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        var w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        var sum = 0;
        for (var i = 0; i < 12; i++) sum += parseInt(cnpj.charAt(i), 10) * w1[i];
        var d1 = (sum % 11 < 2) ? 0 : 11 - (sum % 11);
        if (parseInt(cnpj.charAt(12), 10) !== d1) return false;

        sum = 0;
        for (var j = 0; j < 13; j++) sum += parseInt(cnpj.charAt(j), 10) * w2[j];
        var d2 = (sum % 11 < 2) ? 0 : 11 - (sum % 11);
        return parseInt(cnpj.charAt(13), 10) === d2;
    }

    function isValidDoc(value, type) {
        return type === 'cnpj' ? isValidCNPJ(value) : isValidCPF(value);
    }

    // ====================================================================
    // MÁSCARAS DE FORMATAÇÃO
    // ====================================================================

    function maskCPF(value) {
        value = onlyDigits(value).slice(0, 11);
        if (value.length > 9) {
            return value.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        }
        if (value.length > 6) {
            return value.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        }
        if (value.length > 3) {
            return value.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        }
        return value;
    }

    function maskCNPJ(value) {
        value = onlyDigits(value).slice(0, 14);
        if (value.length > 12) {
            return value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
        }
        if (value.length > 8) {
            return value.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
        }
        if (value.length > 5) {
            return value.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
        }
        if (value.length > 2) {
            return value.replace(/(\d{2})(\d{1,3})/, '$1.$2');
        }
        return value;
    }

    function applyMask(value, type) {
        return type === 'cnpj' ? maskCNPJ(value) : maskCPF(value);
    }

    function expectedLength(type) {
        return type === 'cnpj' ? 14 : 11;
    }

    // ====================================================================
    // ESTADO E AJAX (verificação de duplicidade)
    // ====================================================================

    var _state = { cpf: 'unknown', cnpj: 'unknown' };
    var _lastValidatedValue = {};
    var _alertedValue = {};
    var _ajaxTimer = {};

    // Seletor amplo: pega por ID, name e atributos comuns dos plugins brasileiros
    function fieldSelector(type) {
        if (type === 'cpf') {
            return '#billing_cpf, input[name="billing_cpf"], input[name="_billing_cpf"], input.cpf';
        }
        return '#billing_cnpj, input[name="billing_cnpj"], input[name="_billing_cnpj"], input.cnpj';
    }

    // Seletor combinado para os listeners genéricos
    var ALL_DOC_FIELDS = [
        '#billing_cpf', 'input[name="billing_cpf"]', 'input[name="_billing_cpf"]', 'input.cpf',
        '#billing_cnpj', 'input[name="billing_cnpj"]', 'input[name="_billing_cnpj"]', 'input.cnpj'
    ].join(', ');

    function detectType($field) {
        var name = ($field.attr('name') || '').toLowerCase();
        var id   = ($field.attr('id') || '').toLowerCase();
        var cls  = ($field.attr('class') || '').toLowerCase();
        if (name.indexOf('cnpj') !== -1 || id.indexOf('cnpj') !== -1 || cls.indexOf('cnpj') !== -1) {
            return 'cnpj';
        }
        return 'cpf';
    }

    function labelFor(type) {
        return type.toUpperCase();
    }

    function messageFor(state, type) {
        if (state === 'invalid') return labelFor(type) + ' inválido. Verifique os dígitos digitados.';
        if (state === 'exists')  return labelFor(type) + ' já cadastrado.';
        return '';
    }

    function setVisual($field, state) {
        $field.removeClass('woocommerce-invalid woocommerce-validated wc-doc-invalid wc-doc-valid');
        if (state === 'invalid' || state === 'exists') {
            $field.addClass('woocommerce-invalid wc-doc-invalid');
        } else if (state === 'valid') {
            $field.addClass('woocommerce-validated wc-doc-valid');
        }
    }

    function checkExistsAjax(value, type) {
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
            if (!res || !res.success) return 'unknown';
            var data = res.data || {};
            if (data.valid === false) return 'invalid';
            if (data.exists === true) return 'exists';
            return 'valid';
        }, function () {
            return 'unknown';
        });
    }

    // ====================================================================
    // VALIDAÇÃO EM TEMPO REAL (digitação)
    // ====================================================================

    function validateRealTime($field, type) {

        var raw = onlyDigits($field.val());

        // Vazio: limpa estado
        if (!raw) {
            _state[type] = 'unknown';
            setVisual($field, 'unknown');
            return;
        }

        // Comprimento incompleto: ainda digitando, sem feedback negativo
        if (raw.length < expectedLength(type)) {
            _state[type] = 'unknown';
            $field.removeClass('woocommerce-invalid woocommerce-validated');
            return;
        }

        // Comprimento completo: valida algoritmo localmente (instantâneo)
        if (!isValidDoc(raw, type)) {
            _state[type] = 'invalid';
            _lastValidatedValue[type] = raw;
            setVisual($field, 'invalid');
            return;
        }

        // Algoritmo OK: marca como válido e dispara checagem AJAX de duplicidade (debounced)
        _state[type] = 'valid';
        _lastValidatedValue[type] = raw;
        setVisual($field, 'valid');

        if (_ajaxTimer[type]) clearTimeout(_ajaxTimer[type]);
        _ajaxTimer[type] = setTimeout(function () {
            checkExistsAjax(raw, type).then(function (state) {
                if (onlyDigits($field.val()) !== raw) return;
                _state[type] = state;
                _lastValidatedValue[type] = raw;
                setVisual($field, state);
            });
        }, 400);
    }

    // ====================================================================
    // BLUR: alerta se inválido/duplicado
    // ====================================================================

    function alertIfNeeded($field, type) {
        var raw = onlyDigits($field.val());
        if (!raw) return;

        if (raw.length !== expectedLength(type)) {
            _state[type] = 'invalid';
            setVisual($field, 'invalid');
            if (_alertedValue[type] !== raw) {
                _alertedValue[type] = raw;
                window.alert(labelFor(type) + ' deve ter ' + expectedLength(type) + ' dígitos.');
            }
            return;
        }

        var state = _state[type];
        if ((state === 'invalid' || state === 'exists') && _alertedValue[type] !== raw) {
            _alertedValue[type] = raw;
            window.alert(messageFor(state, type));
        }
    }

    // ====================================================================
    // VALIDAÇÃO SÍNCRONA NO SUBMIT (algoritmo + cache de duplicidade)
    // ====================================================================
    // Não usa AJAX no submit — o servidor PHP faz a validação final.
    // O AJAX já roda em tempo real conforme o usuário digita.

    function validateFormSync($form) {
        var firstError = '';

        ['cpf', 'cnpj'].forEach(function (type) {
            if (firstError) return;

            var $field = $form.find(fieldSelector(type));
            if (!$field.length) return;

            var raw = onlyDigits($field.val());
            if (!raw) return;

            if (raw.length !== expectedLength(type)) {
                setVisual($field, 'invalid');
                firstError = labelFor(type) + ' deve ter ' + expectedLength(type) + ' dígitos.';
                return;
            }

            if (!isValidDoc(raw, type)) {
                setVisual($field, 'invalid');
                firstError = messageFor('invalid', type);
                return;
            }

            // Só bloqueia por "exists" se a checagem AJAX já confirmou para ESSE valor
            if (_state[type] === 'exists' && _lastValidatedValue[type] === raw) {
                setVisual($field, 'exists');
                firstError = messageFor('exists', type);
                return;
            }
        });

        return firstError;
    }

    // ====================================================================
    // LISTENERS
    // ====================================================================

    // Aplica máscara, filtra não-dígitos e valida em tempo real
    $(document).on('input', ALL_DOC_FIELDS, function () {
        var $field  = $(this);
        var type    = detectType($field);
        var oldVal  = $field.val();
        var newVal  = applyMask(oldVal, type);

        if (newVal !== oldVal) {
            $field.val(newVal);
        }

        delete _alertedValue[type];
        validateRealTime($field, type);
    });

    // Bloqueia teclas não permitidas (keypress - fallback para browsers antigos)
    $(document).on('keypress', ALL_DOC_FIELDS, function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.which === 0 || e.which === 8) return;

        var ch = String.fromCharCode(e.which);
        if (!/[0-9]/.test(ch)) {
            e.preventDefault();
        }
    });

    // beforeinput - bloqueio mais robusto e moderno (intercepta antes de modificar o valor)
    document.addEventListener('beforeinput', function (e) {
        var t = e.target;
        if (!t || !t.matches) return;
        if (!t.matches(ALL_DOC_FIELDS)) return;
        if (e.inputType && e.inputType.indexOf('insert') === 0 && e.data) {
            if (!/^\d+$/.test(e.data)) {
                e.preventDefault();
            }
        }
    }, true);

    // Garante que ao colar conteúdo, a máscara é aplicada
    $(document).on('paste', ALL_DOC_FIELDS, function () {
        var $field = $(this);
        setTimeout(function () { $field.trigger('input'); }, 0);
    });

    // No blur, alerta se necessário
    $(document).on('blur', ALL_DOC_FIELDS, function () {
        var $field = $(this);
        alertIfNeeded($field, detectType($field));
    });

    // Quando o WooCommerce re-renderiza o checkout (mudança de CEP, país, frete...),
    // re-aplica máscara e validação nos campos que aparecerem.
    $(document.body).on('updated_checkout init_checkout country_to_state_changed updated_shipping_method', function () {
        $(ALL_DOC_FIELDS).each(function () {
            var $field = $(this);
            var type   = detectType($field);
            var oldVal = $field.val();
            var newVal = applyMask(oldVal, type);
            if (newVal !== oldVal) $field.val(newVal);
            validateRealTime($field, type);
        });
    });

    // Aplica atributos de teclado numérico e revalida campo
    function setupField(field) {
        var $field = $(field);
        if ($field.data('wcDocSetup')) return;
        $field.data('wcDocSetup', true);

        var type = detectType($field);
        $field.attr('inputmode', 'numeric');
        $field.attr('autocomplete', 'off');
        $field.attr('maxlength', type === 'cnpj' ? 18 : 14);

        if ($field.val()) {
            var newVal = applyMask($field.val(), type);
            if (newVal !== $field.val()) $field.val(newVal);
            validateRealTime($field, type);
        }
    }

    function setupAllFields() {
        $(ALL_DOC_FIELDS).each(function () { setupField(this); });
    }

    // Validação inicial em campos pré-preenchidos
    $(function () { setupAllFields(); });

    // MutationObserver: detecta campos adicionados dinamicamente (block checkout, AJAX, etc)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                if (!added || !added.length) continue;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.nodeType !== 1) continue;
                    if (node.matches && node.matches(ALL_DOC_FIELDS)) {
                        setupField(node);
                    }
                    if (node.querySelectorAll) {
                        var nested = node.querySelectorAll(ALL_DOC_FIELDS);
                        for (var k = 0; k < nested.length; k++) {
                            setupField(nested[k]);
                        }
                    }
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Bloqueio de submit dos formulários do WooCommerce
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

        var $cpf  = $form.find(fieldSelector('cpf'));
        var $cnpj = $form.find(fieldSelector('cnpj'));

        // Sem campos de documento ou ambos vazios: não interfere com o submit
        if ((!$cpf.length || !$cpf.val()) && (!$cnpj.length || !$cnpj.val())) return;

        var firstError = validateFormSync($form);

        if (firstError) {
            e.preventDefault();
            e.stopImmediatePropagation();
            window.alert(firstError);
            return false;
        }
        // Se passou: deixa o submit seguir normalmente (fluxo nativo + outros plugins)
    });

    // Bloqueio do checkout AJAX do WooCommerce
    $(document).on('checkout_place_order', function () {

        var $form = $('form.checkout');
        if (!$form.length) return true;

        var $cpf  = $form.find(fieldSelector('cpf'));
        var $cnpj = $form.find(fieldSelector('cnpj'));

        if ((!$cpf.length || !$cpf.val()) && (!$cnpj.length || !$cnpj.val())) return true;

        var firstError = validateFormSync($form);

        if (firstError) {
            window.alert(firstError);
            return false;
        }

        return true;
    });
});
