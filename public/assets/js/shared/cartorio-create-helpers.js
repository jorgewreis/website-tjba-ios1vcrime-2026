(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(message, type, customTitle) {
        const kind = type === 'error' ? 'error' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'success'));
        const title = customTitle || (kind === 'error' ? 'Erro' : (kind === 'warning' ? 'Atenção' : (kind === 'info' ? 'Informação' : 'Sucesso')));

        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(kind, title, message || '');
            return;
        }

        if (typeof global.showToast === 'function') {
            global.showToast({
                type: kind,
                variant: 'full',
                title: title,
                message: message || ''
            });
        }
    }

    function getValue(id) {
        return global.document.getElementById(id)?.value?.trim() ?? '';
    }

    function formatProperName(value, maxLength) {
        if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            return App.ProperNameHelper.formatProper(value, maxLength || 255);
        }
        if (typeof global.TextFormatter !== 'undefined' && typeof global.TextFormatter.formatProperName === 'function') {
            return global.TextFormatter.formatProperName(value, maxLength || 255);
        }
        return value || '';
    }

    function buildClassePayload(options) {
        const settings = options || {};
        const payload = {};
        const classeCodigo = getValue(settings.codigoFieldId || 'hidden-classe-codigo');
        const classeDescricao = getValue(settings.descricaoFieldId || 'hidden-classe-descricao');
        const classeVal = String(settings.classeValue || '').trim();

        if (classeCodigo) {
            payload.classe_codigo = classeCodigo;
        }

        if (classeDescricao) {
            payload.classe_descricao = classeDescricao;
            return payload;
        }

        if (classeVal && classeVal !== 'Classe não informada' && classeVal !== 'Aguarde...') {
            payload.classe_descricao = classeVal;
            if (!payload.classe_codigo) {
                payload.classe_codigo = classeVal.replace(/\s+/g, '').substring(0, 20);
            }
        }

        return payload;
    }

    function onlyDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function normalizeProcessValue(rawValue, options) {
        const settings = options || {};
        const input = settings.inputId ? global.document.getElementById(settings.inputId) : null;
        const feedback = typeof settings.feedback === 'function' ? settings.feedback : function () {};
        const gate = settings.buttonGate || null;
        const requiredMessage = settings.requiredMessage || 'Informe o número do processo.';
        const invalidMessage = settings.invalidMessage || 'O número do processo está incompleto ou inválido (20 dígitos).';
        const raw = String(rawValue || '').trim();

        if (!raw) {
            if (gate && typeof gate.setError === 'function') {
                gate.setError();
            }
            feedback(requiredMessage, 'error');
            return { ok: false, value: '' };
        }

        if (input && typeof global.normalizeAndValidateCNJValue === 'function') {
            const normalized = global.normalizeAndValidateCNJValue(raw, { toastOnInvalid: true });
            if (!normalized.ok) {
                if (gate && typeof gate.setError === 'function') {
                    gate.setError();
                }
                input.value = '';
                input.focus();
                return { ok: false, value: '' };
            }
            input.value = normalized.formatted;
            if (gate && typeof gate.setWaiting === 'function') {
                gate.setWaiting();
            }
            return { ok: true, value: normalized.formatted };
        }

        const digits = onlyDigits(raw);
        if (digits.length !== 20) {
            if (gate && typeof gate.setError === 'function') {
                gate.setError();
            }
            feedback(invalidMessage, 'error');
            if (input) {
                input.focus();
            }
            return { ok: false, value: '' };
        }

        if (gate && typeof gate.setWaiting === 'function') {
            gate.setWaiting();
        }
        return { ok: true, value: raw };
    }

    function initCreateButtonGate(options) {
        const settings = options || {};
        const button = settings.button || null;
        const initialLabel = String(settings.initialLabel || 'Novo cadastro');
        const readyLabel = String(settings.readyLabel || 'Cadastrar');
        if (!button) {
            return {
                setInitial: function () {},
                setWaiting: function () {},
                setError: function () {},
                setReady: function () {},
                setSubmitting: function () {},
                setBlocked: function () {}
            };
        }

        const apply = function (label, disabled) {
            button.disabled = disabled === true;
            button.textContent = label;
        };

        const api = {
            setInitial: function () {
                apply(initialLabel, false);
            },
            setWaiting: function () {
                apply('Aguarde...', true);
            },
            setError: function () {
                apply('Erro', true);
            },
            setReady: function () {
                apply(readyLabel, false);
            },
            setSubmitting: function () {
                apply('Aguarde...', true);
            },
            setBlocked: function () {
                apply('Aguarde...', true);
            }
        };

        api.setInitial();
        return api;
    }

    App.CartorioCreateHelpers = {
        notify: notify,
        getValue: getValue,
        formatProperName: formatProperName,
        buildClassePayload: buildClassePayload,
        onlyDigits: onlyDigits,
        normalizeProcessValue: normalizeProcessValue,
        initCreateButtonGate: initCreateButtonGate
    };
})(window);
