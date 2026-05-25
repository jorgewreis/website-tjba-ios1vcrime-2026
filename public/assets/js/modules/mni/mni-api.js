/**
 * Modulo MNI - frontend opt-in.
 *
 * - NAO eh incluido em nenhuma pagina por padrao. Para usar, a tela deve
 *   adicionar o <script src="/assets/js/modules/mni/mni-api.js?v=..."></script>
 *   APOS o bundle do DataJud (datajud-api.js), para que o MNI seja sempre
 *   complementar e nunca substitua o fluxo padrao.
 *
 * - Escuta o evento `cnjValidated` (mesmo gatilho do DataJud) e faz fetch
 *   ao endpoint `/api/mni/consultar-processo.php`. NUNCA bloqueia a UI:
 *   o DataJud continua sendo a fonte primaria de exibicao.
 *
 * - Quando a chamada retorna, dispara:
 *      - `mniProcessoResolved` (sucesso): detail = { processoInputId, shape, meta, source: 'mni' }
 *      - `mniProcessoError`   (falha):    detail = { processoInputId, kind, message, meta }
 *
 * - Pagina interessada em renderizar campos extras (movimentos, partes detalhadas,
 *   documentos) faz `window.addEventListener('mniProcessoResolved', ...)`.
 *
 * - Configuracao (opcional) via `window.__MNI_CLIENT_CONFIG`:
 *      {
 *        endpoint: '/api/mni/consultar-processo.php', // override (default mostrado)
 *        debounceMs: 200,                              // espera apos cnjValidated
 *        requestOptions: { incluir_movimentos: true, incluir_documentos: false }
 *      }
 */
(function (global) {
    'use strict';

    if (global.__MniApiBound) {
        return;
    }
    global.__MniApiBound = true;

    var DEFAULTS = {
        endpoint: '/api/mni/consultar-processo.php',
        debounceMs: 200,
        requestOptions: { incluir_movimentos: true, incluir_documentos: false }
    };

    function getConfig() {
        var custom = (global.__MNI_CLIENT_CONFIG && typeof global.__MNI_CLIENT_CONFIG === 'object')
            ? global.__MNI_CLIENT_CONFIG
            : {};
        return {
            endpoint: typeof custom.endpoint === 'string' && custom.endpoint !== ''
                ? custom.endpoint
                : DEFAULTS.endpoint,
            debounceMs: typeof custom.debounceMs === 'number' && custom.debounceMs >= 0
                ? custom.debounceMs
                : DEFAULTS.debounceMs,
            requestOptions: Object.assign({}, DEFAULTS.requestOptions, custom.requestOptions || {})
        };
    }

    var inflightController = null;
    var lastDigitsRequested = '';
    var debounceTimer = null;

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function dispatchResolved(processoInputId, shape, meta) {
        global.dispatchEvent(new CustomEvent('mniProcessoResolved', {
            detail: {
                processoInputId: processoInputId,
                shape: shape,
                meta: meta || {},
                source: 'mni'
            }
        }));
    }

    function dispatchError(processoInputId, kind, message, meta) {
        global.dispatchEvent(new CustomEvent('mniProcessoError', {
            detail: {
                processoInputId: processoInputId,
                kind: kind || 'unknown',
                message: message || 'Erro na consulta MNI.',
                meta: meta || {}
            }
        }));
    }

    function abortInflight() {
        if (inflightController) {
            try { inflightController.abort(); } catch (e) { /* ignore */ }
            inflightController = null;
        }
    }

    function runMniFetch(processoInputId, digits) {
        if (lastDigitsRequested === digits && inflightController !== null) {
            return;
        }
        abortInflight();

        var cfg = getConfig();
        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        inflightController = controller;
        lastDigitsRequested = digits;

        var payload = {
            numero: digits,
            opts: cfg.requestOptions
        };

        var init = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        };
        if (controller) {
            init.signal = controller.signal;
        }

        fetch(cfg.endpoint, init)
            .then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, status: response.status, body: body || {} };
                });
            })
            .then(function (res) {
                if (lastDigitsRequested !== digits) {
                    return;
                }
                if (!res.ok || !res.body || res.body.error) {
                    dispatchError(
                        processoInputId,
                        (res.body && res.body.kind) ? res.body.kind : 'http_' + res.status,
                        (res.body && res.body.error) ? res.body.error : 'Falha HTTP ' + res.status + ' ao consultar MNI.',
                        (res.body && res.body.meta) || {}
                    );
                    return;
                }
                dispatchResolved(processoInputId, res.body.data || null, res.body.meta || {});
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                dispatchError(processoInputId, 'network', (error && error.message) ? error.message : 'Falha de rede no MNI.', {});
            })
            .finally(function () {
                if (inflightController === controller) {
                    inflightController = null;
                }
            });
    }

    function handleCnjValidated(event) {
        var detail = (event && event.detail) || {};
        var digits = onlyDigits(detail.digits || detail.numero || '');
        if (digits.length !== 20) {
            return;
        }
        var processoInputId = detail.inputId || '';

        var cfg = getConfig();
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        if (cfg.debounceMs > 0) {
            debounceTimer = setTimeout(function () {
                debounceTimer = null;
                runMniFetch(processoInputId, digits);
            }, cfg.debounceMs);
        } else {
            runMniFetch(processoInputId, digits);
        }
    }

    function handleCnjInvalid() {
        abortInflight();
        lastDigitsRequested = '';
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    }

    global.addEventListener('cnjValidated', handleCnjValidated);
    global.addEventListener('cnjInvalid', handleCnjInvalid);

    global.MniApi = {
        consultarAgora: function (digits, processoInputId) {
            var d = onlyDigits(digits);
            if (d.length !== 20) {
                return false;
            }
            runMniFetch(processoInputId || '', d);
            return true;
        },
        cancelar: abortInflight
    };
})(typeof window !== 'undefined' ? window : this);
