(function () {
    var API_ENDPOINT = (typeof window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT !== 'undefined' && window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT)
        ? window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT
        : '/api/datajud/search-processo.php';
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
        });
    }

    function hasClasseUtil(classe) {
        return !!(classe && (classe.codigo || classe.descricao));
    }

    function runDatajudSearch(event) {
        if (typeof window.DataJudCycle === 'undefined') {
            console.error('datajud-api.js: DataJudCycle não encontrado. Inclua /assets/js/modules/datajud/datajud-cycle-helper.js antes deste script.');
            return;
        }

        var DC = window.DataJudCycle;
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

        var session = DC.startLoading(dom);

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
                        DC.finishFailure(session, dom, 'http', {
                            message: (body && body.error) ? body.error : DC.MSG_HTTP_GENERIC,
                        });
                        return;
                    }
                    var domFresh = getDom();
                    if (!domFresh.classeInput || !domFresh.processoInput) {
                        return;
                    }
                    var processo = body && body.data;
                    if (!processo) {
                        DC.finishFailure(session, domFresh, 'partial', {});
                        return;
                    }
                    var classe = processo.classe;
                    if (hasClasseUtil(classe)) {
                        DC.finishSuccess(session, domFresh, classe);
                    } else {
                        DC.finishFailure(session, domFresh, 'partial', {});
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
                            DC.finishFailure(session, dom, 'timeout');
                        }
                        return;
                    }
                    console.error('Pesquisa DataJud falhou', error);
                    var domFresh = getDom();
                    if (!domFresh.classeInput || !domFresh.processoInput) {
                        return;
                    }
                    if (error.isHttp) {
                        DC.finishFailure(session, domFresh, 'http', { message: DC.MSG_HTTP_GENERIC });
                        return;
                    }
                    DC.finishFailure(session, domFresh, 'network');
                })
                .finally(function () {
                    clearTimeout(tid);
                    DC.clearFetchController();
                });
        }

        fetchAttempt(1);
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
