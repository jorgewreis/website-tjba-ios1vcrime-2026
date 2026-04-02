(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const helpers = App.CartorioCreateHelpers || null;

    function initialize(options) {
        const settings = options || {};
        const cidadeInput = global.document.getElementById(settings.cidadeInputId || 'filter-cidade');
        const estadoSelect = global.document.getElementById(settings.estadoSelectId || 'filter-estado');

        if (!cidadeInput || !helpers) {
            return;
        }

        if (settings.maxLength) {
            cidadeInput.setAttribute('maxlength', String(settings.maxLength));
        }

        cidadeInput.addEventListener('blur', function () {
            cidadeInput.value = helpers.formatProperName(cidadeInput.value, settings.formatMaxLength || 50);
            const cidade = cidadeInput.value.trim();
            if (cidade.length < 2) {
                return;
            }

            fetch((settings.endpoint || '') + encodeURIComponent(cidade))
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.found) {
                        return;
                    }

                    if (data.uf && estadoSelect) {
                        estadoSelect.value = data.uf;
                    }

                    const status = String(data.status_cidade || '').trim();
                    if (settings.blockCcm && status.toUpperCase() === 'CCM') {
                        helpers.notify('Comarca com Central de Mandados', 'info', 'Informação');
                        cidadeInput.value = '';
                        return;
                    }

                    if (status.toLowerCase() === 'agregada' && data.nome) {
                        cidadeInput.value = helpers.formatProperName(data.nome, settings.formatMaxLength || 50);
                    }
                })
                .catch(function () {
                    // noop
                });
        });
    }

    App.CartorioCityLookup = {
        initialize: initialize
    };
})(window);
