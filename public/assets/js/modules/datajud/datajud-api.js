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

    var classeInputListenerBound = false;



    function getDom() {

        return {

            classeInput: document.getElementById('audiencia-classe')

                || document.getElementById('gab-mon-classe')

                || document.getElementById('filter-classe'),

            processoInput: document.getElementById('audiencia-processo')

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

            d.classeInput.readOnly = false;

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



    function showToastSync(opts) {

        if (typeof window.showToast === 'function') {

            window.showToast(opts);

        } else if (window.App && window.App.Toast && typeof window.App.Toast.show === 'function') {

            window.App.Toast.show(opts);

        }

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

                return;

            }

            var action = res.body && res.body.action;

            if (action === 'updated') {

                showToastSync({ type: 'info', variant: 'simple', message: 'Classe do processo atualizada no sistema.' });

            } else if (action === 'created') {

                showToastSync({ type: 'info', variant: 'simple', message: 'Processo e classe registrados no sistema.' });

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



    function runDatajudSearch(event) {

        if (typeof window.DataJudCycle === 'undefined') {

            console.error('datajud-api.js: DataJudCycle não encontrado. Inclua /assets/js/modules/datajud/datajud-cycle-helper.js antes deste script.');

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

        if (rawDigits.length !== 20) {

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

                    }

                }

            })

            .catch(function () {

                /* leitura BD é opcional */

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



                    var controller = new AbortController();

                    DC.registerFetchController(controller);

                    var tid = setTimeout(function () {

                        DC.setPendingAbortReason('timeout');

                        controller.abort();

                    }, DC.FETCH_TIMEOUT_MS);



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

                            var classe = processo.classe;

                            if (hasClasseUtil(classe)) {

                                DC.finishSuccess(session, domLatest, classe);

                                postSyncClasse(rawDigits, classe);

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

                            console.error('Pesquisa DataJud falhou', error);

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

