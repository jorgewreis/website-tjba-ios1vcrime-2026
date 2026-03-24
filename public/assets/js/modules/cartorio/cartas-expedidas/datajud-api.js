(function () {
    var API_ENDPOINT = (typeof window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT !== 'undefined' && window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT)
        ? window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT
        : '/api/datajud/search-processo.php';
    var activeController = null;
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

    function setStatusMessage(statusElement, message) {
        if (!statusElement) {
            return;
        }
        statusElement.textContent = message || '';
    }

    function setClasseField(classeInput, value, readOnly, textColor, backgroundColor) {
        classeInput.value = value;
        classeInput.readOnly = readOnly;
        if (textColor) {
            classeInput.style.color = textColor;
        } else {
            classeInput.style.removeProperty('color');
        }
        if (backgroundColor) {
            classeInput.style.backgroundColor = backgroundColor;
        } else {
            classeInput.style.removeProperty('background-color');
        }
    }

    function toggleInputs(processoInput, classeInput, disabled) {
        processoInput.disabled = disabled;
        classeInput.disabled = disabled;
    }

    function prepareConsultingState(dom) {
        var classeInput = dom.classeInput;
        var processoInput = dom.processoInput;
        var statusElement = dom.statusElement;
        classeInput.classList.remove('status-finished');
        setClasseField(classeInput, 'Aguarde...', false, 'var(--warning-color-active)', 'var(--warning-color-bg-light)');
        setStatusMessage(statusElement, 'Consultando o DataJud...');
        if (statusElement) statusElement.style.color = 'var(--warning-color-active)';
        toggleInputs(processoInput, classeInput, true);
    }

    function applyClasseResult(dom, classe) {
        var classeInput = dom.classeInput;
        var processoInput = dom.processoInput;
        var classeCodigoField = dom.classeCodigoField;
        var classeDescricaoField = dom.classeDescricaoField;
        var statusElement = dom.statusElement;
        var representation = (classe.descricao || classe.codigo || 'Classe');
        setClasseField(classeInput, representation, true, '', '');
        classeInput.classList.add('status-finished');
        classeInput.readOnly = true;
        toggleInputs(processoInput, classeInput, false);
        if (classeCodigoField) {
            classeCodigoField.value = classe.codigo || '';
        }
        if (classeDescricaoField) {
            classeDescricaoField.value = classe.descricao || classe.codigo || '';
        }
        setStatusMessage(statusElement, '*Classe obtida via DataJud');
        if (statusElement) statusElement.style.color = 'var(--success-color)';
        if (processoInput.id === 'gab-mon-processo' || processoInput.id === 'filter-processo' || processoInput.id === 'audiencia-processo') {
            window.dispatchEvent(new CustomEvent('gabineteMonDatajudClassePronta', {
                detail: { numeroProcesso: processoInput.value },
            }));
        }
    }

    function handleClasseFallback(dom) {
        var classeInput = dom.classeInput;
        var processoInput = dom.processoInput;
        var classeCodigoField = dom.classeCodigoField;
        var classeDescricaoField = dom.classeDescricaoField;
        var statusElement = dom.statusElement;
        classeInput.classList.remove('status-finished');
        setClasseField(classeInput, 'Classe não informada', false, '', '');
        classeInput.readOnly = false;
        toggleInputs(processoInput, classeInput, false);
        setStatusMessage(statusElement, '');
        if (classeCodigoField) {
            classeCodigoField.value = '';
        }
        if (classeDescricaoField) {
            classeDescricaoField.value = '';
        }
        if (statusElement) statusElement.style.color = 'var(--color-gray-700)';
    }

    function applyClasseFromResponse(dom, classe) {
        var hasClasse = classe && (classe.codigo || classe.descricao);
        if (hasClasse) {
            applyClasseResult(dom, classe);
        } else {
            handleClasseFallback(dom);
            setStatusMessage(dom.statusElement, 'Classe');
        }
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

    function runDatajudSearch(event) {
        var dom = getDom();
        var classeInput = dom.classeInput;
        var processoInput = dom.processoInput;
        var statusElement = dom.statusElement;

        if (!classeInput || !processoInput) {
            return;
        }

        bindClasseInputListenerOnce(dom);

        var detail = event && event.detail ? event.detail : {};
        var rawDigits = String(detail.digits != null ? detail.digits : '').replace(/\D/g, '');
        if (rawDigits.length !== 20) {
            return;
        }

        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();
        prepareConsultingState(dom);

        fetch(API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ numero: rawDigits }),
            signal: activeController.signal,
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
                    throw new Error('Serviço de consulta ao DataJud indisponível. Tente mais tarde.');
                });
            })
            .then(function (result) {
                var status = result.status;
                var body = result.body;
                if (status >= 400 || (body && body.error)) {
                    throw new Error((body && body.error) || 'Erro ao consultar o DataJud.');
                }
                var processo = body && body.data;
                if (!processo) {
                    throw new Error('Dados do processo ausentes no DataJud.');
                }
                var domFresh = getDom();
                if (!domFresh.classeInput || !domFresh.processoInput) {
                    return;
                }
                applyClasseFromResponse(domFresh, processo.classe);
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                console.error('Pesquisa DataJud falhou', error);
                var domFresh = getDom();
                if (!domFresh.classeInput || !domFresh.processoInput) {
                    return;
                }
                handleClasseFallback(domFresh);
                setStatusMessage(domFresh.statusElement, 'Classe');
                if (domFresh.statusElement) domFresh.statusElement.style.color = 'var(--color-gray-700)';
            })
            .finally(function () {
                activeController = null;
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
