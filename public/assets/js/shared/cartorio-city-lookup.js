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

        let validationTimer = null;
        let activeRequest = null;
        let lastBlockedCity = '';

        function normalizeForCompare(value) {
            return String(value || '').trim().toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/ç/g, 'c').replace(/[^a-z0-9]+/g, '');
        }

        function lookupCity(formatMappedName) {
            if (validationTimer !== null) {
                global.clearTimeout(validationTimer);
                validationTimer = null;
            }
            if (activeRequest) {
                activeRequest.abort();
                activeRequest = null;
            }

            const cidade = cidadeInput.value.trim();
            if (cidade.length < 2) {
                return;
            }

            const cidadeKey = normalizeForCompare(cidade);
            const ufConsultada = estadoSelect ? estadoSelect.value : '';
            const controller = typeof global.AbortController === 'function' ? new global.AbortController() : null;
            activeRequest = controller;
            const endpoint = (settings.endpoint || '') + encodeURIComponent(cidade)
                + (ufConsultada ? '&uf=' + encodeURIComponent(ufConsultada) : '');
            const requestOptions = { credentials: 'same-origin' };
            if (controller) requestOptions.signal = controller.signal;

            global.fetch(endpoint, requestOptions)
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (normalizeForCompare(cidadeInput.value) !== cidadeKey
                        || (estadoSelect && estadoSelect.value !== ufConsultada)) {
                        return;
                    }
                    if (!data.found) {
                        return;
                    }

                    if (data.uf && estadoSelect) {
                        estadoSelect.value = data.uf;
                    }

                    const status = String(data.status_cidade || '').trim();
                    if (settings.blockCcm && (data.is_ccm === true || status.toUpperCase() === 'CCM')) {
                        cidadeInput.dataset.ccmBlocked = 'true';
                        cidadeInput.dataset.ccmCity = cidade;
                        if (lastBlockedCity !== cidadeKey) {
                            helpers.notify(
                                settings.ccmErrorMessage || 'A comarca de ' + cidade + ' já possui Central de Mandados (CCM). Deve ser expedido Mandado em vez de Carta Precatória.',
                                'error'
                            );
                        }
                        lastBlockedCity = cidadeKey;
                        cidadeInput.value = '';
                        return;
                    }

                    delete cidadeInput.dataset.ccmBlocked;
                    lastBlockedCity = '';
                    if (formatMappedName && status.toLowerCase() === 'agregada' && data.nome) {
                        cidadeInput.value = (App.ProperNameHelper && App.ProperNameHelper.formatProper)
                            ? App.ProperNameHelper.formatProper(data.nome, settings.formatMaxLength || 50)
                            : helpers.formatProperName(data.nome, settings.formatMaxLength || 50);
                    }
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    // A validação de cadastro também consulta o endpoint e bloqueia se a busca falhar.
                });
        }

        cidadeInput.addEventListener('input', function () {
            delete cidadeInput.dataset.ccmBlocked;
            delete cidadeInput.dataset.ccmCity;
            lastBlockedCity = '';
            if (!settings.blockCcm) return;
            if (validationTimer !== null) global.clearTimeout(validationTimer);
            validationTimer = global.setTimeout(function () {
                validationTimer = null;
                lookupCity(false);
            }, settings.validationDebounceMs || 450);
        });

        if (estadoSelect) {
            estadoSelect.addEventListener('change', function () {
                delete cidadeInput.dataset.ccmBlocked;
                lastBlockedCity = '';
                if (settings.blockCcm) lookupCity(false);
            });
        }

        cidadeInput.addEventListener('blur', function () {
            cidadeInput.value = (App.ProperNameHelper && App.ProperNameHelper.formatProper)
                ? App.ProperNameHelper.formatProper(cidadeInput.value, settings.formatMaxLength || 50)
                : helpers.formatProperName(cidadeInput.value, settings.formatMaxLength || 50);
            if (settings.blockCcm) return;
            lookupCity(true);
        });
    }

    App.CartorioCityLookup = {
        initialize: initialize
    };
})(window);
