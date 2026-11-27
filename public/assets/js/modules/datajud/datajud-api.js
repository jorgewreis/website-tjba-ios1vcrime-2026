(function () {

    var CLASSE_READ_URL = (typeof window.PROCESSO_CLASSE_READ_ENDPOINT !== 'undefined' && window.PROCESSO_CLASSE_READ_ENDPOINT)

        ? window.PROCESSO_CLASSE_READ_ENDPOINT

        : '/api/processos/classe-read.php';

    var classeInputListenerBound = false;



    function getDom() {

        return {

            classeInput: document.getElementById('audiencia-classe')

                || document.getElementById('audiencia-edit-classe')

                || document.getElementById('gab-mon-classe')

                || document.getElementById('filter-classe'),

            processoInput: document.getElementById('audiencia-processo')

                || document.getElementById('audiencia-edit-processo')

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



    function runLocalClassRead(event) {

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



        // Validar o CNJ só preenche dados do banco local; nunca chama ou grava via DataJud.
        var localController = new AbortController();
        var localTimer;
        var localRead = Promise.race([
            fetch(CLASSE_READ_URL + '?numero=' + encodeURIComponent(rawDigits), {
                credentials: 'same-origin',
                signal: localController.signal,
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            }),
            new Promise(function (_, reject) {
                localTimer = setTimeout(function () {
                    localController.abort();
                    reject(new Error('Tempo limite da leitura local de classe.'));
                }, 10000);
            }),
        ]);

        localRead

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
                clearTimeout(localTimer);
            });
    }

    window.addEventListener('cnjValidated', runLocalClassRead);



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

