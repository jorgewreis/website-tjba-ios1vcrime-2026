(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function bindBackButton(options) {
        const settings = options || {};
        const button = global.document.getElementById(settings.buttonId || 'voltar-btn');
        if (!button || !settings.href) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            global.location.href = settings.href;
        }, { capture: true });
    }

    function setupRuleButton(options) {
        const settings = options || {};
        global.addEventListener('DOMContentLoaded', function () {
            if (!global.App || !App.User || typeof App.User.setupRuleButton !== 'function') {
                return;
            }
            App.User.setupRuleButton({
                buttonId: settings.buttonId || 'alterar-regra-btn',
                targetPage: settings.targetPage || ''
            });
        });
    }

    function resetClasseFields(options) {
        const settings = options || {};
        const classeInput = global.document.getElementById(settings.classeFieldId || 'filter-classe');
        const codigoInput = global.document.getElementById(settings.codigoFieldId || 'hidden-classe-codigo');
        const descricaoInput = global.document.getElementById(settings.descricaoFieldId || 'hidden-classe-descricao');

        if (classeInput) {
            classeInput.value = '';
            classeInput.readOnly = true;
        }
        if (codigoInput) {
            codigoInput.value = '';
        }
        if (descricaoInput) {
            descricaoInput.value = '';
        }

        if (typeof settings.onReset === 'function') {
            settings.onReset();
        }
    }

    function bindClasseResetOnShortProcess(options) {
        const settings = options || {};
        const input = global.document.getElementById(settings.processInputId || 'filter-processo');
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            const digits = String(input.value || '').replace(/\D/g, '');
            if (digits.length !== 20) {
                resetClasseFields(settings);
            }
        });
    }

    App.CartorioCreateBootstrap = {
        bindBackButton: bindBackButton,
        setupRuleButton: setupRuleButton,
        resetClasseFields: resetClasseFields,
        bindClasseResetOnShortProcess: bindClasseResetOnShortProcess
    };
})(window);
