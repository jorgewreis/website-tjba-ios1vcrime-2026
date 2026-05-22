/**
 * Ciclo único de consulta DataJud: lock global de botões (snapshot),
 * estado visual em controles marcados, toast após 500ms, AbortController
 * e detecção de operação obsoleta (nova consulta cancela a anterior).
 */
(function (global) {
    'use strict';

    var SLOW_TOAST_MS = 500;
    /** 1ª tentativa 20s, 2ª 30s, 3ª 40s — ver `getFetchTimeoutMs`. */
    var FETCH_TIMEOUT_MS_BY_ATTEMPT = [20000, 30000, 40000];
    var MAX_FETCH_ATTEMPTS = 3;

    function getFetchTimeoutMs(attemptIndex) {
        var n = Number(attemptIndex);
        if (!n || n < 1 || n !== n) {
            n = 1;
        }
        var i = Math.min(n, FETCH_TIMEOUT_MS_BY_ATTEMPT.length) - 1;
        return FETCH_TIMEOUT_MS_BY_ATTEMPT[i];
    }

    var MSG_SLOW = 'Consultando informações processuais. Isso pode levar alguns segundos.';
    var MSG_NO_RESULT = 'A consulta ao DataJud não retornou resultados a tempo. Tente novamente ou informe a classe manualmente.';
    var MSG_HTTP_GENERIC = 'Não foi possível concluir a consulta ao DataJud.';
    var MSG_EMPTY_CLASSE = 'Processo encontrado sem classe cadastrada no DataJud.';
    /**
     * Única mensagem de toast (info) para sigilo / sem registro público / dados parciais na API pública.
     * Usada em todas as telas (Pauta, Cartório, Gabinete, etc.) para o mesmo cenário.
     */
    var MSG_TOAST_SIGILO_PUBLICO = 'Não há dados públicos completos no DataJud para este processo (sigilo ou indisponível na base). Informe a classe manualmente se necessário.';
    /** Curto para não estourar inputs estreitos (ex.: classe em formulários). */
    var PLACEHOLDER_LOADING = 'Consultando DataJud…';
    var FALLBACK_CLASSE = 'Classe não informada';
    /** Valor reservado da primeira opção do select enquanto a API DataJud carrega (ex.: nomes de partes). */
    var DATAJUD_SELECT_LOADING_VALUE = '__DATAJUD_LOADING__';

    var operationId = 0;
    var activeFetchController = null;
    var slowToastTimer = null;
    var buttonSnapshot = null;
    var pendingAbortReason = null;

    function showDataToast(message, type) { // eslint-disable-line no-unused-vars
        // Fluxo DataJud silencioso por decisão de produto:
        // não exibir toasts de consulta para o usuário.
    }

    function showDataToastError(message) { // eslint-disable-line no-unused-vars
        // Fluxo DataJud silencioso por decisão de produto.
    }

    function snapshotPageButtons() {
        var list = [];
        Array.prototype.forEach.call(document.querySelectorAll('button'), function (btn) {
            if (btn.getAttribute('data-allow-during-loading') === 'true') {
                return;
            }
            list.push({ el: btn, disabled: btn.disabled });
        });
        return list;
    }

    function applyPageLock(snapshot) {
        snapshot.forEach(function (s) {
            s.el.disabled = true;
        });
    }

    function restorePageLock(snapshot) {
        if (!snapshot) {
            return;
        }
        snapshot.forEach(function (s) {
            s.el.disabled = s.disabled;
        });
    }

    function clearSlowToastTimer() {
        if (slowToastTimer) {
            clearTimeout(slowToastTimer);
            slowToastTimer = null;
        }
    }

    function setStatusMessage(statusElement, message, colorVar) {
        if (!statusElement) {
            return;
        }
        statusElement.textContent = message || '';
        if (colorVar) {
            statusElement.style.color = colorVar;
        } else {
            statusElement.style.removeProperty('color');
        }
    }

    /** Permite telas sem campo visível de classe reagirem ao resultado do DataJud. */
    function dispatchDatajudClasseResolved(processoInput, success) {
        if (typeof global.dispatchEvent !== 'function' || !processoInput) {
            return;
        }
        global.dispatchEvent(new CustomEvent('datajudClasseResolved', {
            detail: {
                processoInputId: processoInput.id || '',
                success: success === true,
            },
        }));
    }

    function clearClasseLoadingVisual(classeInput) {
        if (!classeInput) {
            return;
        }
        classeInput.classList.remove('status-warning');
        classeInput.removeAttribute('aria-busy');
        classeInput.style.removeProperty('color');
        classeInput.style.removeProperty('background-color');
    }

    function resetClasseFields(classeInput, classeCodigoField, classeDescricaoField) {
        if (classeInput) {
            clearClasseLoadingVisual(classeInput);
            classeInput.value = '';
            classeInput.removeAttribute('data-datajud-initial');
        }
        if (classeCodigoField) classeCodigoField.value = '';
        if (classeDescricaoField) classeDescricaoField.value = '';
    }

    function getClasseLoadingPlaceholder(attemptIndex) {
        var n = Number(attemptIndex);
        if (!n || n < 1 || n !== n) {
            n = 1;
        }
        if (n <= 1) {
            return PLACEHOLDER_LOADING;
        }
        return 'Tentativa ' + n + ', aguarde...';
    }

    function setClasseLoadingState(classeInput, attemptIndex) {
        if (!classeInput) {
            return;
        }
        classeInput.classList.remove('status-finished');
        classeInput.classList.add('status-warning');
        classeInput.setAttribute('aria-busy', 'true');
        classeInput.readOnly = true;
        var preserve = classeInput.getAttribute('data-preserve-banco') === '1';
        var keepInitial = classeInput.getAttribute('data-datajud-initial') === '1'
            && String(classeInput.value || '').trim() !== '';
        if (!preserve && !keepInitial) {
            classeInput.value = '';
        }
        classeInput.placeholder = getClasseLoadingPlaceholder(attemptIndex || 1);
    }

    function setSelectLoadingState(selectEl) {
        if (!selectEl) {
            return;
        }
        selectEl.classList.remove('status-finished');
        selectEl.classList.add('status-warning');
        selectEl.setAttribute('aria-busy', 'true');
        selectEl.disabled = true;
        selectEl.innerHTML = '<option value="' + DATAJUD_SELECT_LOADING_VALUE + '" selected disabled>' + PLACEHOLDER_LOADING + '</option>';
    }

    function clearSelectLoadingVisual(selectEl) {
        if (!selectEl) {
            return;
        }
        selectEl.classList.remove('status-warning');
        selectEl.removeAttribute('aria-busy');
    }

    global.DataJudCycle = {
        SLOW_TOAST_MS: SLOW_TOAST_MS,
        /** @deprecated Preferir `getFetchTimeoutMs(1)` — mantido como 20000 para compat. */
        FETCH_TIMEOUT_MS: FETCH_TIMEOUT_MS_BY_ATTEMPT[0],
        MAX_FETCH_ATTEMPTS: MAX_FETCH_ATTEMPTS,
        /** Timeout do `fetch` DataJud por número da tentativa (1–3): 20s / 30s / 40s. */
        getFetchTimeoutMs: getFetchTimeoutMs,
        MSG_SLOW: MSG_SLOW,
        MSG_NO_RESULT: MSG_NO_RESULT,
        MSG_HTTP_GENERIC: MSG_HTTP_GENERIC,
        MSG_EMPTY_CLASSE: MSG_EMPTY_CLASSE,
        MSG_TOAST_SIGILO_PUBLICO: MSG_TOAST_SIGILO_PUBLICO,
        PLACEHOLDER_LOADING: PLACEHOLDER_LOADING,
        FALLBACK_CLASSE: FALLBACK_CLASSE,
        DATAJUD_SELECT_LOADING_VALUE: DATAJUD_SELECT_LOADING_VALUE,

        /** Placeholder curto no input de classe; a partir da 2ª tentativa: `Tent. N — DataJud…`. */
        getClasseLoadingPlaceholder: getClasseLoadingPlaceholder,

        /**
         * Atualiza só o placeholder quando há nova tentativa de fetch (sem refazer o restante do estado).
         * @param {HTMLInputElement|null} classeInput
         * @param {number} attemptIndex — 1 = primeira
         * @param {boolean} [bancoPrefilled] — se true, não altera (valor da classe do BD visível)
         */
        updateClasseLoadingAttemptPlaceholder: function (classeInput, attemptIndex, bancoPrefilled) {
            if (!classeInput || bancoPrefilled) {
                return;
            }
            classeInput.placeholder = getClasseLoadingPlaceholder(attemptIndex || 1);
        },

        /** Atualiza texto auxiliar ao lado do rótulo (mesmo padrão do fluxo de classe processual). */
        setFieldStatus: function (statusElement, message, colorVar) {
            setStatusMessage(statusElement, message, colorVar);
        },

        getOperationId: function () {
            return operationId;
        },

        isStaleOperation: function (opId) {
            return opId !== operationId;
        },

        registerFetchController: function (controller) {
            activeFetchController = controller;
        },

        clearFetchController: function () {
            activeFetchController = null;
        },

        setPendingAbortReason: function (reason) {
            pendingAbortReason = reason;
        },

        consumeAbortReason: function () {
            var r = pendingAbortReason;
            pendingAbortReason = null;
            return r;
        },

        /**
         * Cancela consulta anterior: abort do fetch, timer do toast, restaura botões e (opcional) limpa classe/status.
         * @param {Object} [dom] — mesmo formato de getDom(); se omitido, não altera inputs.
         */
        cancelPrevious: function (dom) {
            clearSlowToastTimer();
            pendingAbortReason = 'user';
            if (activeFetchController) {
                try {
                    activeFetchController.abort();
                } catch (e) {
                    /* ignore */
                }
                activeFetchController = null;
            }
            operationId += 1;
            restorePageLock(buttonSnapshot);
            buttonSnapshot = null;
            if (global.__datajudBancoPrefill !== undefined) {
                global.__datajudBancoPrefill = null;
            }
            if (dom && dom.classeInput) {
                clearClasseLoadingVisual(dom.classeInput);
                dom.classeInput.readOnly = true;
                dom.classeInput.placeholder = '';
                dom.classeInput.removeAttribute('data-preserve-banco');
            }
            if (dom && dom.statusElement) {
                setStatusMessage(dom.statusElement, '', null);
            }
            if (dom && dom.processoInput) {
                dispatchDatajudClasseResolved(dom.processoInput, false);
            }
        },

        /**
         * Fluxo padrão (classe): só o input de classe entra em estado de carregamento.
         * @param {Object} dom — retorno de getDom() em datajud-api.js
         * @param {Object} [options]
         * @param {boolean} [options.bancoPrefilled] — se true, classe já veio do BD: `status-finished`, sem lock de botões e sem toast “consultando…” (DataJud segue em background).
         */
        startLoading: function (dom, options) {
            options = options || {};
            var bancoPrefilled = options.bancoPrefilled === true;
            clearSlowToastTimer();
            if (!bancoPrefilled) {
                buttonSnapshot = snapshotPageButtons();
                applyPageLock(buttonSnapshot);
            } else {
                buttonSnapshot = null;
            }
            if (dom.classeInput) {
                if (bancoPrefilled) {
                    clearClasseLoadingVisual(dom.classeInput);
                    dom.classeInput.classList.add('status-finished');
                    dom.classeInput.classList.remove('status-warning');
                    dom.classeInput.removeAttribute('aria-busy');
                    dom.classeInput.placeholder = '';
                } else {
                    setClasseLoadingState(dom.classeInput, 1);
                }
            }
            if (dom.statusElement) {
                setStatusMessage(
                    dom.statusElement,
                    bancoPrefilled
                        ? 'Consultando DataJud para atualização...'
                        : 'Consultando o DataJud...',
                    bancoPrefilled ? 'var(--color-gray-700)' : 'var(--color-warning-active)'
                );
            }
            if (!bancoPrefilled) {
                slowToastTimer = setTimeout(function () {
                    slowToastTimer = null;
                    showDataToast(MSG_SLOW, 'info');
                }, SLOW_TOAST_MS);
            }
            return { buttonSnapshot: buttonSnapshot };
        },

        /**
         * Finaliza ciclo de UI (timer + botões). Chamado antes de aplicar sucesso/falha nos campos.
         */
        endCycleUi: function (session) {
            clearSlowToastTimer();
            restorePageLock(session && session.buttonSnapshot);
            buttonSnapshot = null;
        },

        finishSuccess: function (session, dom, classe) {
            this.endCycleUi(session);
            var classeInput = dom.classeInput;
            var processoInput = dom.processoInput;
            var classeCodigoField = dom.classeCodigoField;
            var classeDescricaoField = dom.classeDescricaoField;
            var statusElement = dom.statusElement;
            if (!classeInput || !processoInput) {
                return;
            }
            clearClasseLoadingVisual(classeInput);
            classeInput.removeAttribute('data-preserve-banco');
            classeInput.removeAttribute('data-datajud-initial');
            if (global.__datajudBancoPrefill !== undefined) {
                global.__datajudBancoPrefill = null;
            }
            var representation = (classe && (classe.descricao || classe.codigo))
                ? String(classe.descricao || classe.codigo || '').trim()
                : '';
            classeInput.value = representation;
            classeInput.readOnly = true;
            classeInput.classList.add('status-finished');
            classeInput.classList.remove('status-warning');
            classeInput.removeAttribute('aria-busy');
            if (classeCodigoField) {
                classeCodigoField.value = classe && classe.codigo ? classe.codigo : '';
            }
            if (classeDescricaoField) {
                classeDescricaoField.value = classe && (classe.descricao || classe.codigo) ? (classe.descricao || classe.codigo || '') : '';
            }
            setStatusMessage(statusElement, '', null);
            dispatchDatajudClasseResolved(processoInput, true);
        },

        finishFailure: function (session, dom, kind, detail) {
            this.endCycleUi(session);
            var classeInput = dom.classeInput;
            var processoInput = dom.processoInput;
            var classeCodigoField = dom.classeCodigoField;
            var classeDescricaoField = dom.classeDescricaoField;
            var statusElement = dom.statusElement;
            if (!classeInput || !processoInput) {
                return;
            }
            clearClasseLoadingVisual(classeInput);
            classeInput.classList.remove('status-finished');
            classeInput.readOnly = true;
            classeInput.placeholder = '';

            var fallbackText = FALLBACK_CLASSE;
            var statusColor = 'var(--color-gray-700)';

            if (kind === 'partial' || kind === 'empty' || kind === 'sigilo') {
                if (global.__datajudBancoPrefill && global.__datajudBancoPrefill.display) {
                    classeInput.value = global.__datajudBancoPrefill.display;
                    classeInput.readOnly = true;
                    classeInput.classList.add('status-finished');
                    if (classeCodigoField) {
                        classeCodigoField.value = global.__datajudBancoPrefill.codigo || '';
                    }
                    if (classeDescricaoField) {
                        classeDescricaoField.value = global.__datajudBancoPrefill.descricao || '';
                    }
                    setStatusMessage(statusElement, '', null);
                } else {
                    classeInput.value = fallbackText;
                    setStatusMessage(statusElement, '', null);
                    if (classeCodigoField) {
                        classeCodigoField.value = '';
                    }
                    if (classeDescricaoField) {
                        classeDescricaoField.value = '';
                    }
                }
            } else {
                if (global.__datajudBancoPrefill && global.__datajudBancoPrefill.display) {
                    classeInput.value = global.__datajudBancoPrefill.display;
                    classeInput.readOnly = true;
                    classeInput.classList.add('status-finished');
                    if (classeCodigoField) {
                        classeCodigoField.value = global.__datajudBancoPrefill.codigo || '';
                    }
                    if (classeDescricaoField) {
                        classeDescricaoField.value = global.__datajudBancoPrefill.descricao || '';
                    }
                    setStatusMessage(statusElement, '', null);
                } else {
                    classeInput.value = fallbackText;
                    setStatusMessage(statusElement, '', null);
                    if (classeCodigoField) {
                        classeCodigoField.value = '';
                    }
                    if (classeDescricaoField) {
                        classeDescricaoField.value = '';
                    }
                }
            }

            var codigoAposFalha = classeCodigoField ? String(classeCodigoField.value || '').trim() : '';
            dispatchDatajudClasseResolved(processoInput, codigoAposFalha !== '');

            var hadBancoPrefill = !!(global.__datajudBancoPrefill && global.__datajudBancoPrefill.display);

            if (hadBancoPrefill) {
                return;
            }

            if (kind === 'timeout') {
                showDataToastError(MSG_NO_RESULT);
            } else if (kind === 'http') {
                showDataToastError(detail && detail.message ? detail.message : MSG_HTTP_GENERIC);
            } else if (kind === 'network') {
                showDataToastError(MSG_HTTP_GENERIC);
            } else if (kind === 'sigilo') {
                showDataToast(MSG_TOAST_SIGILO_PUBLICO, 'info');
            } else if (kind === 'partial') {
                showDataToastError(MSG_EMPTY_CLASSE);
            }
        },

        /**
         * Carregamento da lista de partes (audiências): select com placeholder e classes como o input de classe.
         * @param {HTMLSelectElement} selectEl
         * @param {HTMLElement|null} [statusElement] — span ou label filho para mensagem (ex.: “Consultando o DataJud…”).
         */
        startSelectLoading: function (selectEl, statusElement) {
            clearSlowToastTimer();
            buttonSnapshot = snapshotPageButtons();
            applyPageLock(buttonSnapshot);
            if (selectEl) {
                setSelectLoadingState(selectEl);
            }
            if (statusElement) {
                setStatusMessage(statusElement, 'Consultando o DataJud...', 'var(--color-warning-active)');
            }
            slowToastTimer = setTimeout(function () {
                slowToastTimer = null;
                showDataToast(MSG_SLOW, 'info');
            }, SLOW_TOAST_MS);
            return { buttonSnapshot: buttonSnapshot, statusElement: statusElement || null };
        },

        endSelectCycleUi: function (session) {
            clearSlowToastTimer();
            restorePageLock(session && session.buttonSnapshot);
            buttonSnapshot = null;
            if (session && session.statusElement) {
                setStatusMessage(session.statusElement, '', null);
            }
        },

        clearSelectLoading: function (selectEl) {
            clearSelectLoadingVisual(selectEl);
        },

        /**
         * Reseta campos ligados ao DataJud (processo/classe) em ações de limpar formulário.
         * @param {{processInput?: HTMLElement, classeInput?: HTMLElement, classeCodigo?: HTMLElement, classeDescricao?: HTMLElement}} opts
         */
        resetInputs: function (opts) {
            const o = opts || {};
            if (o.processInput) {
                o.processInput.value = '';
                o.processInput.classList.remove('status-warning');
                o.processInput.removeAttribute('aria-busy');
            }
            resetClasseFields(o.classeInput, o.classeCodigo, o.classeDescricao);
        }
    };
})(typeof window !== 'undefined' ? window : this);
