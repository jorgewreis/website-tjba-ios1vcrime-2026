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

        cidadeInput.addEventListener('input', function () {
            delete cidadeInput.dataset.ccmBlocked;
        });

        cidadeInput.addEventListener('blur', function () {
            cidadeInput.value = (App.ProperNameHelper && App.ProperNameHelper.formatProper)
                ? App.ProperNameHelper.formatProper(cidadeInput.value, settings.formatMaxLength || 50)
                : helpers.formatProperName(cidadeInput.value, settings.formatMaxLength || 50);
            const cidade = cidadeInput.value.trim();
            if (cidade.length < 2) {
                return;
            }

            const endpoint = (settings.endpoint || '') + encodeURIComponent(cidade)
                + (estadoSelect && estadoSelect.value ? '&uf=' + encodeURIComponent(estadoSelect.value) : '');
            fetch(endpoint, { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.found) {
                        return;
                    }

                    if (data.uf && estadoSelect) {
                        estadoSelect.value = data.uf;
                    }

                    const status = String(data.status_cidade || '').trim();
                    if (settings.blockCcm && (data.is_ccm === true || status.toUpperCase() === 'CCM')) {
                        cidadeInput.dataset.ccmBlocked = 'true';
                        return;
                    }

                    if (status.toLowerCase() === 'agregada' && data.nome) {
                            cidadeInput.value = (App.ProperNameHelper && App.ProperNameHelper.formatProper)
                                ? App.ProperNameHelper.formatProper(data.nome, settings.formatMaxLength || 50)
                                : helpers.formatProperName(data.nome, settings.formatMaxLength || 50);
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
