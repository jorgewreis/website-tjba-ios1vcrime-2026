/**
 * Ciclo único de consulta DataJud: lock global de botões (snapshot),
 * estado visual em controles marcados, toast após 500ms, AbortController
 * e detecção de operação obsoleta (nova consulta cancela a anterior).
 */
(function (global) {
    'use strict';

    var SLOW_TOAST_MS = 500;
    var FETCH_TIMEOUT_MS = 10000;
    var MAX_FETCH_ATTEMPTS = 2;

    var MSG_SLOW = 'Consultando informações processuais. Isso pode levar alguns segundos.';
    var MSG_NO_RESULT = 'A consulta ao DataJud não retornou resultados a tempo. Tente novamente ou informe os dados manualmente.';
    var MSG_HTTP_GENERIC = 'Não foi possível concluir a consulta ao DataJud.';
    var MSG_EMPTY_CLASSE = 'Processo encontrado sem classe cadastrada no DataJud.';
    var PLACEHOLDER_LOADING = 'Consultando informações processuais...';
    var FALLBACK_CLASSE = 'Classe não informada';
    /** Valor reservado da primeira opção do select enquanto a API DataJud carrega (ex.: nomes de partes). */
    var DATAJUD_SELECT_LOADING_VALUE = '__DATAJUD_LOADING__';

    var operationId = 0;
    var activeFetchController = null;
    var slowToastTimer = null;
    var buttonSnapshot = null;
    var pendingAbortReason = null;

    function showDataToast(message, type) {
        type = type || 'info';
        if (typeof global.showToast === 'function') {
            global.showToast({ type: type, variant: 'simple', message: message });
        } else if (global.App && global.App.Toast && typeof global.App.Toast.show === 'function') {
            global.App.Toast.show({ type: type, variant: 'simple', message: message });
        }
    }

    function showDataToastError(message) {
        showDataToast(message, 'error');
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

    function clearClasseLoadingVisual(classeInput) {
        if (!classeInput) {
            return;
        }
        classeInput.classList.remove('status-warning');
        classeInput.removeAttribute('aria-busy');
        classeInput.style.removeProperty('color');
        classeInput.style.removeProperty('background-color');
    }

    function setClasseLoadingState(classeInput) {
        if (!classeInput) {
            return;
        }
        classeInput.classList.remove('status-finished');
        classeInput.classList.add('status-warning');
        classeInput.setAttribute('aria-busy', 'true');
        classeInput.readOnly = true;
        classeInput.value = '';
        classeInput.placeholder = PLACEHOLDER_LOADING;
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
        FETCH_TIMEOUT_MS: FETCH_TIMEOUT_MS,
        MAX_FETCH_ATTEMPTS: MAX_FETCH_ATTEMPTS,
        MSG_SLOW: MSG_SLOW,
        MSG_NO_RESULT: MSG_NO_RESULT,
        MSG_HTTP_GENERIC: MSG_HTTP_GENERIC,
        MSG_EMPTY_CLASSE: MSG_EMPTY_CLASSE,
        PLACEHOLDER_LOADING: PLACEHOLDER_LOADING,
        FALLBACK_CLASSE: FALLBACK_CLASSE,
        DATAJUD_SELECT_LOADING_VALUE: DATAJUD_SELECT_LOADING_VALUE,

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
            if (dom && dom.classeInput) {
                clearClasseLoadingVisual(dom.classeInput);
                dom.classeInput.readOnly = false;
                dom.classeInput.placeholder = '';
            }
            if (dom && dom.statusElement) {
                setStatusMessage(dom.statusElement, '', null);
            }
        },

        /**
         * Fluxo padrão (classe): só o input de classe entra em estado de carregamento.
         * @param {Object} dom — retorno de getDom() em datajud-api.js
         */
        startLoading: function (dom) {
            clearSlowToastTimer();
            buttonSnapshot = snapshotPageButtons();
            applyPageLock(buttonSnapshot);
            if (dom.classeInput) {
                setClasseLoadingState(dom.classeInput);
            }
            if (dom.statusElement) {
                setStatusMessage(dom.statusElement, 'Consultando o DataJud...', 'var(--color-warning-active)');
            }
            var self = this;
            slowToastTimer = setTimeout(function () {
                slowToastTimer = null;
                showDataToast(MSG_SLOW, 'info');
            }, SLOW_TOAST_MS);
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
            var representation = (classe && (classe.descricao || classe.codigo)) ? String(classe.descricao || classe.codigo || '') : 'Classe';
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
            setStatusMessage(statusElement, '*Classe obtida via DataJud', 'var(--color-success-active)');
            if (processoInput.id === 'gab-mon-processo' || processoInput.id === 'filter-processo' || processoInput.id === 'audiencia-processo') {
                global.dispatchEvent(new CustomEvent('gabineteMonDatajudClassePronta', {
                    detail: { numeroProcesso: processoInput.value },
                }));
            }
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
            classeInput.readOnly = false;
            classeInput.placeholder = '';

            var fallbackText = FALLBACK_CLASSE;
            var statusColor = 'var(--color-gray-700)';

            if (kind === 'partial' || kind === 'empty') {
                classeInput.value = fallbackText;
                setStatusMessage(statusElement, MSG_EMPTY_CLASSE, statusColor);
            } else {
                classeInput.value = fallbackText;
                setStatusMessage(statusElement, 'Classe', statusColor);
            }

            if (classeCodigoField) {
                classeCodigoField.value = '';
            }
            if (classeDescricaoField) {
                classeDescricaoField.value = '';
            }

            if (kind === 'timeout') {
                showDataToast(MSG_NO_RESULT, 'info');
            } else if (kind === 'http') {
                showDataToastError(detail && detail.message ? detail.message : MSG_HTTP_GENERIC);
            } else if (kind === 'network') {
                showDataToastError(MSG_HTTP_GENERIC);
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
        }
    };
})(typeof window !== 'undefined' ? window : this);
