(function () {
    function initDatajudApi() {
        var API_ENDPOINT = (typeof window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT !== 'undefined' && window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT)
            ? window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT
            : '/api/datajud/search-processo.php';
        // Gabinete (monitoramento) usa gab-mon-*; demais telas usam filter-* — priorizar gab-mon para não colidir com outros ids.
        var classeInput = document.getElementById('gab-mon-classe') || document.getElementById('filter-classe');
        var processoInput = document.getElementById('gab-mon-processo') || document.getElementById('filter-processo');
        var classeCodigoField = document.getElementById('hidden-classe-codigo');
        var classeDescricaoField = document.getElementById('hidden-classe-descricao');
        var statusElement = document.getElementById('processo-ap-info');
        var activeController = null;

        if (!classeInput || !processoInput) {
            return;
        }

        window.addEventListener('cnjValidated', function (event) {
            var detail = event && event.detail ? event.detail : {};
            var rawDigits = String(detail.digits != null ? detail.digits : '').replace(/\D/g, '');
            if (rawDigits.length !== 20) {
                return;
            }

            if (activeController) {
                activeController.abort();
            }

            activeController = new AbortController();
            prepareConsultingState();

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
                    applyClasseFromResponse(processo.classe);
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    console.error('Pesquisa DataJud falhou', error);
                    handleClasseFallback();
                    setStatusMessage('Classe');
                    if (statusElement) statusElement.style.color = 'var(--color-gray-700)';
                })
                .finally(function () {
                    activeController = null;
                });
        });

        function prepareConsultingState() {
            classeInput.classList.remove('status-finished');
            setClasseField('Aguarde...', false, 'var(--warning-color-active)', 'var(--warning-color-bg-light)');
            setStatusMessage('Consultando o DataJud...');
            if (statusElement) statusElement.style.color = 'var(--warning-color-active)';
            toggleInputs(true);
        }

        function applyClasseFromResponse(classe) {
            var hasClasse = classe && (classe.codigo || classe.descricao);
            if (hasClasse) {
                applyClasseResult(classe);
                setStatusMessage('*Classe obtida via DataJud');
                if (statusElement) statusElement.style.color = 'var(--success-color)';
            } else {
                handleClasseFallback();
                setStatusMessage('Classe');
                if (statusElement) statusElement.style.color = 'var(--color-gray-700)';
            }
        }

        function applyClasseResult(classe) {
            var representation = (classe.descricao || classe.codigo || 'Classe');
            setClasseField(representation, true, '', '');
            classeInput.classList.add('status-finished');
            classeInput.readOnly = true;
            toggleInputs(false);
            if (classeCodigoField) {
                classeCodigoField.value = classe.codigo || '';
            }
            if (classeDescricaoField) {
                classeDescricaoField.value = classe.descricao || classe.codigo || '';
            }
            if (processoInput.id === 'gab-mon-processo') {
                window.dispatchEvent(new CustomEvent('gabineteMonDatajudClassePronta', {
                    detail: { numeroProcesso: processoInput.value },
                }));
            }
        }

        function handleClasseFallback() {
            classeInput.classList.remove('status-finished');
            setClasseField('Classe não informada', false, '', '');
            classeInput.readOnly = false;
            toggleInputs(false);
            setStatusMessage('');
            if (classeCodigoField) {
                classeCodigoField.value = '';
            }
            if (classeDescricaoField) {
                classeDescricaoField.value = '';
            }
        }

        classeInput.addEventListener('input', function () {
            if (classeCodigoField) {
                classeCodigoField.value = '';
            }
            if (classeDescricaoField) {
                classeDescricaoField.value = '';
            }
            classeInput.readOnly = false;
        });

        function setClasseField(value, readOnly, textColor, backgroundColor) {
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

        function toggleInputs(disabled) {
            processoInput.disabled = disabled;
            classeInput.disabled = disabled;
        }

        function setStatusMessage(message) {
            if (!statusElement) {
                return;
            }
            statusElement.textContent = message || '';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDatajudApi);
    } else {
        initDatajudApi();
    }
})();
