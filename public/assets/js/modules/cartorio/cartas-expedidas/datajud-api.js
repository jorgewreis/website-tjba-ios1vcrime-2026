(function () {
    const API_ENDPOINT = (typeof window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT !== 'undefined' && window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT)
        ? window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT
        : '/api/datajud/search-processo.php';
    const classeInput = document.getElementById('filter-classe');
    const processoInput = document.getElementById('filter-processo');
    const classeCodigoField = document.getElementById('hidden-classe-codigo');
    const classeDescricaoField = document.getElementById('hidden-classe-descricao');
    const statusElement = document.getElementById('processo-ap-info');
    let activeController = null;
    let pendingClasseTimeout = null;

    if (!classeInput || !processoInput) {
        return;
    }

    window.addEventListener('cnjValidated', function (event) {
        const rawDigits = (event.detail?.digits ?? '').replace(/\D/g, '');
        if (rawDigits.length !== 20) {
            return;
        }

        if (activeController) {
            activeController.abort();
        }

        clearClasseTimeout();
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
                const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
                if (contentType.indexOf('application/json') !== -1) {
                    return response.json().then(function (body) {
                        return { status: response.status, body: body };
                    });
                }
                return response.text().then(function (text) {
                    if (response.ok) {
                        throw new Error('Resposta inválida do servidor.');
                    }
                    throw new Error('Serviço de consulta ao DataJud indisponível. Tente mais tarde.');
                });
            })
            .then(function (result) {
                const status = result.status;
                const body = result.body;
                if (status >= 400 || (body && body.error)) {
                    throw new Error((body && body.error) || 'Erro ao consultar o DataJud.');
                }
                const processo = body && body.data;
                if (!processo) {
                    throw new Error('Dados do processo ausentes no DataJud.');
                }
                scheduleClasseUpdate(processo.classe);
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    return;
                }
                console.error('Pesquisa DataJud falhou', error);
                handleClasseFallback();
            })
            .finally(() => {
                activeController = null;
            });
    });

    function prepareConsultingState() {
        setClasseField('Aguarde...', false, 'var(--warning-color-active)', 'var(--warning-color-bg-light)');
        setStatusMessage('Consultando o DataJud...');
        statusElement.style.color = 'var(--warning-color-active)';
        toggleInputs(true);
    }

    function scheduleClasseUpdate(classe) {
        clearClasseTimeout();
        pendingClasseTimeout = setTimeout(() => {
            if (classe?.codigo || classe?.descricao) {
                applyClasseResult(classe);
                setStatusMessage('*Classe obtida via DataJud');
                statusElement.style.color = 'var(--success-color)';
            } else {
                handleClasseFallback();
                setStatusMessage('Classe');
                statusElement.style.color = 'var(--color-gray-700)';
            }
        }, 2000);
    }

    function applyClasseResult(classe) {
        const representation = classe.descricao || classe.codigo || 'Classe';
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
    }

    function handleClasseFallback() {
        clearClasseTimeout();
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

    if (classeInput) {
        classeInput.addEventListener('input', () => {
            if (classeCodigoField) {
                classeCodigoField.value = '';
            }
            if (classeDescricaoField) {
                classeDescricaoField.value = '';
            }
            classeInput.readOnly = false;
        });
    }

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

    function clearClasseTimeout() {
        if (pendingClasseTimeout) {
            clearTimeout(pendingClasseTimeout);
            pendingClasseTimeout = null;
        }
    }

    function setStatusMessage(message) {
        if (!statusElement) {
            return;
        }
        statusElement.textContent = message || '';
    }
})();
