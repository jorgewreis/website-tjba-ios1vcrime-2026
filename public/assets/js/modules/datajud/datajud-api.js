(function () {

    var API_ENDPOINT = (typeof window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT !== 'undefined' && window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT)

        ? window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT

        : '/api/datajud/search-processo.php';

    var CLASSE_READ_URL = (typeof window.PROCESSO_CLASSE_READ_ENDPOINT !== 'undefined' && window.PROCESSO_CLASSE_READ_ENDPOINT)

        ? window.PROCESSO_CLASSE_READ_ENDPOINT

        : '/api/processos/classe-read.php';

    var CLASSE_SYNC_URL = (typeof window.PROCESSO_CLASSE_SYNC_ENDPOINT !== 'undefined' && window.PROCESSO_CLASSE_SYNC_ENDPOINT)

        ? window.PROCESSO_CLASSE_SYNC_ENDPOINT

        : '/api/processos/classe-sync.php';

    var ASSUNTO_SYNC_URL = (typeof window.PROCESSO_ASSUNTO_SYNC_ENDPOINT !== 'undefined' && window.PROCESSO_ASSUNTO_SYNC_ENDPOINT)

        ? window.PROCESSO_ASSUNTO_SYNC_ENDPOINT

        : '/api/processos/assunto-sync.php';

    var classeInputListenerBound = false;



    function getDom() {

        return {

            classeInput: document.getElementById('audiencia-classe')

                || document.getElementById('audiencia-edit-classe')

                || document.getElementById('midias-edit-classe')

                || document.getElementById('gab-mon-classe')

                || document.getElementById('filter-classe'),

            processoInput: document.getElementById('audiencia-processo')

                || document.getElementById('audiencia-edit-processo')

                || document.getElementById('midias-edit-processo')

                || document.getElementById('gab-mon-processo')

                || document.getElementById('filter-processo'),

            classeCodigoField: document.getElementById('hidden-classe-codigo'),

            classeDescricaoField: document.getElementById('hidden-classe-descricao'),

            statusElement: document.getElementById('audiencia-classe-status')

                || document.getElementById('processo-ap-info'),

        };

    }



    function bindClasseInputListenerOnce(dom) {

        if (classeInputListenerBound || !dom.classeInput) {

            return;

        }

        classeInputListenerBound = true;

        dom.classeInput.addEventListener('input', function () {

            var d = getDom();

            if (d.classeCodigoField) {

                d.classeCodigoField.value = '';

            }

            if (d.classeDescricaoField) {

                d.classeDescricaoField.value = '';

            }

            d.classeInput.readOnly = true;

            d.classeInput.removeAttribute('data-preserve-banco');

            d.classeInput.removeAttribute('data-datajud-initial');

            if (typeof window !== 'undefined') {

                window.__datajudBancoPrefill = null;

            }

        });

    }



    function hasClasseUtil(classe) {

        return !!(classe && (classe.codigo || classe.descricao));

    }



    function getClasseNaoInformadaMarker() {

        return (window.DataJudCycle && window.DataJudCycle.FALLBACK_CLASSE)

            ? String(window.DataJudCycle.FALLBACK_CLASSE)

            : 'Classe não informada';

    }



    function normalizeClassePlaceholderCompare(s) {

        return (s != null ? String(s).trim().toLocaleLowerCase('pt-BR') : '');

    }



    /** Igual a “sem classe” no fluxo DataJud: não preenche o input nem ativa bancoPrefilled (comparação sem diferenciar maiúsculas/minúsculas). */

    function isClasseNaoInformadaText(s) {

        var t = normalizeClassePlaceholderCompare(s);

        var marker = normalizeClassePlaceholderCompare(getClasseNaoInformadaMarker());

        return t !== '' && t === marker;

    }



    function showToastSync(opts) { // eslint-disable-line no-unused-vars
        // Fluxo DataJud silencioso por decisão de produto.
    }



    /**

     * Persiste classe + vínculo no BD após sucesso do DataJud (idempotente no servidor).

     * Retries limitados em falha de rede ou 5xx (decisão de produto #7).

     */

    var SYNC_MAX_ATTEMPTS = 3;

    var SYNC_RETRY_DELAY_MS = 400;

    function postSyncClasse(digits, classe) {

        if (window.DATAJUD_SKIP_CLASSE_SYNC === true) {

            return;

        }

        if (!classe) {

            return;

        }

        var codigo = (classe.codigo != null ? String(classe.codigo) : '').trim();

        var desc = (classe.descricao != null ? String(classe.descricao) : '').trim();

        if (codigo === '' && desc !== '') {

            codigo = desc;

        }

        if (codigo === '') {

            return;

        }

        var payloadJson = JSON.stringify({ numero: digits, codigo: codigo, descricao: desc });

        function delay(ms) {

            return new Promise(function (resolve) { setTimeout(resolve, ms); });

        }

        function showSyncResult(res) {

            if (!res.ok) {
                console.warn('[DataJud][Sync] Falha ao sincronizar classe no sistema local.', {
                    status: res.status,
                    body: res.body || null
                });

                return;

            }

            var action = res.body && res.body.action;

            if (action === 'updated') {
                console.info('[DataJud][Sync] Classe do processo atualizada no sistema local.');

            } else if (action === 'created') {
                console.info('[DataJud][Sync] Processo e classe registrados no sistema local.');
            }

        }

        function parseJsonBody(text) {

            var t = (text != null ? String(text) : '').trim();

            if (t === '') {

                return null;

            }

            try {

                return JSON.parse(t);

            } catch (e) {

                return null;

            }

        }

        function attempt(tryNum) {

            return fetch(CLASSE_SYNC_URL, {

                method: 'POST',

                credentials: 'same-origin',

                headers: { 'Content-Type': 'application/json' },

                body: payloadJson,

            })

                .then(function (response) {

                    return response.text().then(function (text) {

                        return { ok: response.ok, status: response.status, body: parseJsonBody(text) };

                    });

                })

                .then(function (res) {

                    if (res.ok) {

                        showSyncResult(res);

                        return;

                    }

                    var serverRetry = res.status >= 500 && res.status < 600 && tryNum < SYNC_MAX_ATTEMPTS;

                    if (serverRetry) {

                        return delay(SYNC_RETRY_DELAY_MS * tryNum).then(function () { return attempt(tryNum + 1); });

                    }

                    showSyncResult(res);

                })

                .catch(function () {

                    if (tryNum < SYNC_MAX_ATTEMPTS) {

                        return delay(SYNC_RETRY_DELAY_MS * tryNum).then(function () { return attempt(tryNum + 1); });

                    }

                });

        }

        attempt(1);

    }

    function normalizeAssuntosForSync(assuntos) {

        if (!Array.isArray(assuntos) || assuntos.length === 0) {

            return [];

        }

        var normalizedMap = {};
        assuntos.forEach(function (item) {

            if (!item) {

                return;

            }

            var codigo = '';
            var descricao = '';
            if (typeof item === 'string') {

                descricao = String(item).trim();

            } else if (typeof item === 'object') {

                codigo = item.codigo != null ? String(item.codigo).trim() : '';
                if (codigo === '') {
                    codigo = item.codigoAssunto != null ? String(item.codigoAssunto).trim() : '';
                }
                if (codigo === '') {
                    codigo = item.numAssunto != null ? String(item.numAssunto).trim() : '';
                }
                descricao = item.descricao != null ? String(item.descricao).trim() : '';
                if (descricao === '') {
                    descricao = item.descricaoAssunto != null ? String(item.descricaoAssunto).trim() : '';
                }
                if (descricao === '') {
                    descricao = item.nome != null ? String(item.nome).trim() : '';
                }

            }

            if (codigo === '') {

                return;

            }

            if (descricao === '') {

                descricao = codigo;

            }

            normalizedMap[codigo] = { codigo: codigo, descricao: descricao };

        });

        return Object.keys(normalizedMap).map(function (k) { return normalizedMap[k]; });

    }

    function postSyncAssuntos(digits, assuntos) {

        var normalized = normalizeAssuntosForSync(assuntos);
        if (normalized.length === 0) {

            return;

        }

        var payloadJson = JSON.stringify({ numero: digits, assuntos: normalized });

        function delay(ms) {

            return new Promise(function (resolve) { setTimeout(resolve, ms); });

        }

        function parseJsonBody(text) {

            var t = (text != null ? String(text) : '').trim();
            if (t === '') {

                return null;

            }
            try {

                return JSON.parse(t);

            } catch (e) {

                return null;

            }

        }

        function attempt(tryNum) {

            return fetch(ASSUNTO_SYNC_URL, {

                method: 'POST',

                credentials: 'same-origin',

                headers: { 'Content-Type': 'application/json' },

                body: payloadJson,

            })

                .then(function (response) {

                    return response.text().then(function (text) {

                        return { ok: response.ok, status: response.status, body: parseJsonBody(text) };

                    });

                })

                .then(function (res) {

                    if (res.ok) {

                        return;

                    }

                    var serverRetry = res.status >= 500 && res.status < 600 && tryNum < SYNC_MAX_ATTEMPTS;
                    if (serverRetry) {

                        return delay(SYNC_RETRY_DELAY_MS * tryNum).then(function () { return attempt(tryNum + 1); });

                    }

                    console.warn('[DataJud][Sync] Falha ao sincronizar assuntos no sistema local.', {
                        status: res.status,
                        body: res.body || null
                    });

                })

                .catch(function () {

                    if (tryNum < SYNC_MAX_ATTEMPTS) {

                        return delay(SYNC_RETRY_DELAY_MS * tryNum).then(function () { return attempt(tryNum + 1); });

                    }

                });

        }

        attempt(1);

    }



    function runDatajudSearch(event) {

        if (typeof window.DataJudCycle === 'undefined') {

            console.error('[DataJud][Init] DataJudCycle não encontrado. Inclua /assets/js/modules/datajud/datajud-cycle-helper.js antes deste script.');

            return;

        }



        var DC = window.DataJudCycle;

        var globalObj = typeof window !== 'undefined' ? window : {};

        var dom = getDom();

        var classeInput = dom.classeInput;

        var processoInput = dom.processoInput;



        if (!classeInput || !processoInput) {

            return;

        }



        bindClasseInputListenerOnce(dom);



        var detail = event && event.detail ? event.detail : {};

        var rawDigits = String(detail.digits != null ? detail.digits : '').replace(/\D/g, '');

        if (typeof window.normalizeAndValidateCNJValue === 'function') {
            var cnjCheck = window.normalizeAndValidateCNJValue(processoInput.value, { toastOnInvalid: false });
            if (!cnjCheck.ok) {
                processoInput.value = '';
                delete processoInput.dataset.lastCnjDispatchedDigits;
                if (typeof window.dispatchEvent === 'function') {
                    window.dispatchEvent(new CustomEvent('cnjInvalid', {
                        detail: { inputId: processoInput.id || '' },
                    }));
                }
                return;
            }
            if (cnjCheck.formatted) {
                processoInput.value = cnjCheck.formatted;
            }
            rawDigits = cnjCheck.digits || rawDigits;
        }

        if (rawDigits.length !== 20) {

            return;

        }

        if (typeof window.validarCNJ === 'function' && !window.validarCNJ(rawDigits)) {
            processoInput.value = '';
            delete processoInput.dataset.lastCnjDispatchedDigits;
            if (typeof window.dispatchEvent === 'function') {
                window.dispatchEvent(new CustomEvent('cnjInvalid', {
                    detail: { inputId: processoInput.id || '' },
                }));
            }
            return;
        }



        DC.cancelPrevious(dom);

        var myOpId = DC.getOperationId();

        var prevDigits = globalObj.__datajudLastValidatedDigits;

        if (prevDigits !== undefined && prevDigits !== rawDigits && classeInput) {

            classeInput.removeAttribute('data-datajud-initial');

        }

        globalObj.__datajudLastValidatedDigits = rawDigits;



        globalObj.__datajudBancoPrefill = null;

        classeInput.removeAttribute('data-preserve-banco');



        fetch(CLASSE_READ_URL + '?numero=' + encodeURIComponent(rawDigits), {

            credentials: 'same-origin',

        })

            .then(function (response) {

                return response.json().then(function (body) {

                    return { ok: response.ok, body: body };

                });

            })

            .then(function (res) {

                if (DC.isStaleOperation(myOpId)) {

                    return;

                }

                var domFresh = getDom();

                if (!res.ok || !res.body || res.body.error) {

                    return;

                }

                var data = res.body;

                if (data.classe && data.classe.codigo) {

                    var c = data.classe;

                    var display = (c.nome && String(c.nome).trim() !== '') ? String(c.nome).trim() : String(c.codigo).trim();

                    if (isClasseNaoInformadaText(display)) {

                        if (domFresh.classeInput) {

                            domFresh.classeInput.removeAttribute('data-datajud-initial');

                            domFresh.classeInput.removeAttribute('data-preserve-banco');

                            domFresh.classeInput.value = '';

                        }

                        if (domFresh.classeCodigoField) {

                            domFresh.classeCodigoField.value = '';

                        }

                        if (domFresh.classeDescricaoField) {

                            domFresh.classeDescricaoField.value = '';

                        }

                        globalObj.__datajudBancoPrefill = null;

                        return;

                    }

                    globalObj.__datajudBancoPrefill = {

                        codigo: String(c.codigo || '').trim(),

                        descricao: String(c.nome || '').trim(),

                        display: display,

                    };

                    if (domFresh.classeInput) {

                        domFresh.classeInput.setAttribute('data-preserve-banco', '1');

                        domFresh.classeInput.removeAttribute('data-datajud-initial');

                        domFresh.classeInput.value = display;

                        domFresh.classeInput.readOnly = true;

                        if (domFresh.classeCodigoField) {

                            domFresh.classeCodigoField.value = c.codigo || '';

                        }

                        if (domFresh.classeDescricaoField) {

                            domFresh.classeDescricaoField.value = c.nome || '';

                        }

                        if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function' && domFresh.processoInput) {

                            window.dispatchEvent(new CustomEvent('datajudClasseResolved', {

                                detail: { processoInputId: domFresh.processoInput.id || '', success: true },

                            }));

                        }

                    }

                }

            })

            .catch(function () {

                /* leitura BD é opcional; log técnico para diagnóstico sem impactar UX */
                console.warn('[DataJud][ClasseLocal] Falha na leitura opcional de classe no BD local.');

            })

            .finally(function () {

                if (DC.isStaleOperation(myOpId)) {

                    return;

                }

                var domFresh = getDom();

                if (!domFresh.classeInput || !domFresh.processoInput) {

                    return;

                }

                var hadBancoPrefill = !!(globalObj.__datajudBancoPrefill && globalObj.__datajudBancoPrefill.display);

                var session = DC.startLoading(domFresh, { bancoPrefilled: hadBancoPrefill });



                function fetchAttempt(attemptIndex) {

                    if (DC.isStaleOperation(myOpId)) {

                        return Promise.resolve();

                    }

                    if (typeof DC.updateClasseLoadingAttemptPlaceholder === 'function') {

                        DC.updateClasseLoadingAttemptPlaceholder(domFresh.classeInput, attemptIndex, hadBancoPrefill);

                    }



                    var controller = new AbortController();

                    DC.registerFetchController(controller);

                    var tid = setTimeout(function () {

                        DC.setPendingAbortReason('timeout');

                        controller.abort();

                    }, typeof DC.getFetchTimeoutMs === 'function' ? DC.getFetchTimeoutMs(attemptIndex) : 10000);



                    return fetch(API_ENDPOINT, {

                        method: 'POST',

                        headers: { 'Content-Type': 'application/json' },

                        body: JSON.stringify({ numero: rawDigits }),

                        signal: controller.signal,

                        credentials: 'same-origin',

                    })

                        .then(function (response) {

                            var contentType = (response.headers.get('Content-Type') || '').toLowerCase();

                            if (contentType.indexOf('application/json') !== -1) {

                                return response.json().then(function (body) {

                                    return { status: response.status, body: body };

                                });

                            }

                            return response.text().then(function () {

                                if (response.ok) {

                                    throw new Error('Resposta inválida do servidor.');

                                }

                                var err = new Error('HTTP');

                                err.isHttp = true;

                                err.status = response.status;

                                throw err;

                            });

                        })

                        .then(function (result) {

                            if (DC.isStaleOperation(myOpId)) {

                                return;

                            }

                            var status = result.status;

                            var body = result.body;

                            if (status >= 400 || (body && body.error)) {

                                DC.finishFailure(session, domFresh, 'http', {

                                    message: (body && body.error) ? body.error : DC.MSG_HTTP_GENERIC,

                                });

                                return;

                            }

                            var domLatest = getDom();

                            if (!domLatest.classeInput || !domLatest.processoInput) {

                                return;

                            }

                            var processo = body && body.data;

                            if (!processo) {

                                DC.finishFailure(session, domLatest, 'partial', {});

                                return;

                            }

                            var indisp = processo.indisponivel_na_base_publica === true
                                || (body && body.indisponivel_na_base_publica === true);

                            var classe = processo.classe;
                            var assuntos = processo.assuntos || [];

                            if (indisp) {

                                DC.finishFailure(session, domLatest, 'sigilo', {});

                                return;

                            }

                            postSyncAssuntos(rawDigits, assuntos);

                            if (hasClasseUtil(classe)) {

                                DC.finishSuccess(session, domLatest, classe);

                                postSyncClasse(rawDigits, classe);

                            } else if (body && body.sigilo_restrito) {

                                DC.finishFailure(session, domLatest, 'sigilo', {});

                            } else {

                                DC.finishFailure(session, domLatest, 'partial', {});

                            }

                        })

                        .catch(function (error) {

                            if (DC.isStaleOperation(myOpId)) {

                                return;

                            }

                            if (error.name === 'AbortError') {

                                var reason = DC.consumeAbortReason();

                                if (reason === 'user') {

                                    return;

                                }

                                if (reason === 'timeout' && attemptIndex < DC.MAX_FETCH_ATTEMPTS) {

                                    return fetchAttempt(attemptIndex + 1);

                                }

                                if (reason === 'timeout') {

                                    DC.finishFailure(session, domFresh, 'timeout');

                                }

                                return;

                            }

                            console.error('[DataJud][Busca] Pesquisa DataJud falhou.', error);

                            var domLatest2 = getDom();

                            if (!domLatest2.classeInput || !domLatest2.processoInput) {

                                return;

                            }

                            if (error.isHttp) {

                                DC.finishFailure(session, domLatest2, 'http', { message: DC.MSG_HTTP_GENERIC });

                                return;

                            }

                            DC.finishFailure(session, domLatest2, 'network');

                        })

                        .finally(function () {

                            clearTimeout(tid);

                            DC.clearFetchController();

                        });

                }



                fetchAttempt(1);

            });

    }



    window.addEventListener('cnjValidated', runDatajudSearch);



    function initDatajudApi() {

        var dom = getDom();

        if (dom.classeInput && dom.processoInput) {

            bindClasseInputListenerOnce(dom);

        }

    }



    if (document.readyState === 'loading') {

        document.addEventListener('DOMContentLoaded', initDatajudApi);

    } else {

        setTimeout(initDatajudApi, 0);

    }

})();

