(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
            return;
        }
        if (typeof global.showToast === 'function') {
            global.showToast({ type: type || 'info', title: title || 'Informação', message: message || '' });
        }
    }

    function queuePostReloadToast(type, title, message, storageKey) {
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(type, title, message, storageKey);
            return;
        }
        if (common && typeof common.queuePostReloadToast === 'function') {
            common.queuePostReloadToast(type, title, message, storageKey);
        }
    }

    function flushPostReloadToast(storageKey) {
        if (App.Page && typeof App.Page.flushPostReloadToast === 'function') {
            App.Page.flushPostReloadToast(storageKey, { type: 'success', title: 'Sucesso' });
        }
    }

    function postJson(url, payload) {
        if (common && typeof common.postJson === 'function') {
            return common.postJson(url, payload);
        }
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload || {})
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        });
    }

    function normalizeAssuntos(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        const unique = {};
        list.forEach(function (item) {
            if (!item || typeof item !== 'object') {
                return;
            }
            const id = String(item.id || item.assunto_id || '').trim();
            const codigo = String(item.codigo || item.assunto_codigo || '').trim();
            const descricao = String(item.descricao || item.assunto_descricao || '').trim();
            const origem = String(item.origem || 'manual').trim();
            if (!id) {
                return;
            }
            unique[id] = { id: id, codigo: codigo, descricao: descricao, origem: origem };
        });
        return Object.keys(unique).map(function (id) { return unique[id]; })
            .sort(function (a, b) {
                const ka = (a.codigo || '') + ' ' + (a.descricao || '');
                const kb = (b.codigo || '') + ' ' + (b.descricao || '');
                return ka.localeCompare(kb, 'pt-BR');
            });
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderAssuntos(container, assuntos) {
        if (!container) {
            return;
        }

        const lista = normalizeAssuntos(assuntos);
        if (lista.length === 0) {
            container.innerHTML = '<div class="col no-data"><span class="major-info">Nenhum assunto vinculado.</span></div>';
            return;
        }

        container.innerHTML = lista.map(function (item) {
            const texto = item.codigo
                ? (item.descricao && item.descricao !== item.codigo ? (item.codigo + ' - ' + item.descricao) : item.codigo)
                : item.descricao;
            const origem = item.origem === 'datajud' ? 'DataJud' : 'Manual';
            return [
                '<div class="display-flex row row-full justify-between align-center border-bottom padding-y-sm" data-assunto-id="' + item.id + '">',
                '  <div class="display-flex col" style="width:auto; flex:1 1 auto;">',
                '    <span class="major-info">' + escapeHtml(texto) + '</span>',
                '    <span class="minor-info">Origem: ' + escapeHtml(origem) + '</span>',
                '  </div>',
                '  <button type="button" class="btn btn-secondary-outline height-sm btn-assunto-desvincular" data-assunto-id="' + item.id + '">Desvincular</button>',
                '</div>'
            ].join('');
        }).join('');
    }

    function initialize(config) {
        const settings = config || {};
        const storageKey = settings.storageKey || 'cartorio.postReloadToast';
        const processoId = String(global.PROCESSO_ASSUNTOS_PROCESSO_ID || '').trim();
        const findAssuntoEndpoint = String(global.PROCESSO_ASSUNTO_FIND_ENDPOINT || '/api/assuntos/find-by-codigo.php');
        const linkAssuntoEndpoint = String(global.PROCESSO_ASSUNTO_LINK_ENDPOINT || '/api/processos/assunto-link.php');
        const unlinkAssuntoEndpoint = String(global.PROCESSO_ASSUNTO_UNLINK_ENDPOINT || '/api/processos/assunto-unlink.php');

        const codigoInput = global.document.getElementById('processo-assunto-codigo');
        const descricaoInput = global.document.getElementById('processo-assunto-descricao');
        const btnVincularAssunto = global.document.getElementById('btn-processo-assunto-vincular');
        const assuntosList = global.document.getElementById('processo-assuntos-list');
        let assuntosState = normalizeAssuntos(global.PROCESSO_ASSUNTOS_INITIAL || []);

        const BUSCA_ASSUNTO_DEBOUNCE_MS = 1000;
        let buscaAssuntoTimer = null;
        let ultimoCodigoConsultado = '';

        function clearBuscaAssuntoTimer() {
            if (buscaAssuntoTimer !== null) {
                global.clearTimeout(buscaAssuntoTimer);
                buscaAssuntoTimer = null;
            }
        }

        function resetAssuntoLookup() {
            ultimoCodigoConsultado = '';
            if (descricaoInput) {
                descricaoInput.value = '';
                descricaoInput.readOnly = false;
                descricaoInput.placeholder = 'Informe a descrição se o código não estiver no catálogo';
            }
        }

        function aplicarAssuntoEncontrado(assunto) {
            if (!descricaoInput) {
                return;
            }
            descricaoInput.value = String(assunto.descricao || '').trim();
            descricaoInput.readOnly = true;
            descricaoInput.placeholder = 'Descrição carregada do catálogo';
        }

        function aplicarAssuntoNaoEncontrado() {
            if (!descricaoInput) {
                return;
            }
            descricaoInput.value = '';
            descricaoInput.readOnly = false;
            descricaoInput.placeholder = 'Código não cadastrado — informe a descrição para vincular';
        }

        function buscarAssuntoPorCodigo(options) {
            const opts = options || {};
            const exibirToast = opts.exibirToast !== false;
            const codigo = codigoInput ? String(codigoInput.value || '').trim() : '';
            if (codigo === '') {
                resetAssuntoLookup();
                return Promise.resolve();
            }
            if (codigo === ultimoCodigoConsultado && descricaoInput && descricaoInput.readOnly) {
                return Promise.resolve();
            }

            return fetch(findAssuntoEndpoint + '?codigo=' + encodeURIComponent(codigo), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            }).then(function (result) {
                if (!result.ok || (result.body && result.body.error)) {
                    if (exibirToast) {
                        notify('error', 'Erro', (result.body && result.body.error) ? result.body.error : 'Erro ao consultar assunto.');
                    }
                    return;
                }
                ultimoCodigoConsultado = codigo;
                const assunto = result.body ? result.body.assunto : null;
                if (assunto) {
                    aplicarAssuntoEncontrado(assunto);
                    if (exibirToast) {
                        notify('info', 'Assunto encontrado', 'Descrição carregada para vinculação.');
                    }
                    return;
                }
                aplicarAssuntoNaoEncontrado();
                if (exibirToast) {
                    notify('info', 'Assunto não encontrado', 'Informe a descrição para vincular manualmente.');
                }
            }).catch(function () {
                if (exibirToast) {
                    notify('error', 'Erro', 'Erro de conexão ao consultar assunto.');
                }
            });
        }

        function scheduleBuscarAssunto() {
            clearBuscaAssuntoTimer();
            const codigo = codigoInput ? String(codigoInput.value || '').trim() : '';
            if (codigo === '') {
                resetAssuntoLookup();
                return;
            }
            if (codigo !== ultimoCodigoConsultado) {
                if (descricaoInput) {
                    descricaoInput.readOnly = false;
                }
            }
            buscaAssuntoTimer = global.setTimeout(function () {
                buscaAssuntoTimer = null;
                buscarAssuntoPorCodigo({ exibirToast: false });
            }, BUSCA_ASSUNTO_DEBOUNCE_MS);
        }

        flushPostReloadToast(storageKey);
        renderAssuntos(assuntosList, assuntosState);

        global.document.querySelectorAll('.btn-desvincular').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                const processoId = button.getAttribute('data-processo-id');
                if (!id || !processoId) {
                    return;
                }
                button.disabled = true;
                postJson('/api/processos-partes/delete.php', { id: id }).then(function (result) {
                    if (result.data && result.data.deleted) {
                        queuePostReloadToast('success', 'Sucesso', 'Vínculo removido com sucesso.', storageKey);
                        global.location.reload();
                        return;
                    }
                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
                    button.disabled = false;
                }).catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    button.disabled = false;
                });
            });
        });

        if (codigoInput) {
            codigoInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    clearBuscaAssuntoTimer();
                    buscarAssuntoPorCodigo({ exibirToast: true });
                }
            });
            codigoInput.addEventListener('input', function () {
                const codigo = String(codigoInput.value || '').trim();
                if (codigo === '') {
                    resetAssuntoLookup();
                    return;
                }
                scheduleBuscarAssunto();
            });
        }

        if (btnVincularAssunto && codigoInput && processoId) {
            btnVincularAssunto.addEventListener('click', function () {
                const codigo = String(codigoInput.value || '').trim();
                const descricao = descricaoInput ? String(descricaoInput.value || '').trim() : '';
                if (!codigo) {
                    notify('error', 'Código obrigatório', 'Informe o código do assunto para vincular.');
                    codigoInput.focus();
                    return;
                }

                btnVincularAssunto.disabled = true;
                postJson(linkAssuntoEndpoint, {
                    processo_id: processoId,
                    codigo: codigo,
                    descricao: descricao
                }).then(function (result) {
                    if (!result.data || result.data.error) {
                        notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao vincular assunto.');
                        return;
                    }

                    const assunto = result.data.assunto || null;
                    if (assunto && assunto.id) {
                        assuntosState.push({
                            id: String(assunto.id),
                            codigo: String(assunto.codigo || codigo),
                            descricao: String(assunto.descricao || descricao || codigo),
                            origem: 'manual'
                        });
                        assuntosState = normalizeAssuntos(assuntosState);
                        renderAssuntos(assuntosList, assuntosState);
                    }
                    codigoInput.value = '';
                    resetAssuntoLookup();
                    if (descricaoInput) {
                        descricaoInput.readOnly = true;
                        descricaoInput.placeholder = 'Preenchida automaticamente se o código existir no catálogo';
                    }
                    notify('success', 'Sucesso', 'Assunto vinculado ao processo.');
                }).catch(function () {
                    notify('error', 'Erro', 'Erro de conexão ao vincular assunto.');
                }).finally(function () {
                    btnVincularAssunto.disabled = false;
                });
            });
        }

        if (assuntosList && processoId) {
            assuntosList.addEventListener('click', function (event) {
                const target = event.target;
                if (!target || !(target instanceof HTMLElement) || !target.classList.contains('btn-assunto-desvincular')) {
                    return;
                }
                const assuntoId = String(target.getAttribute('data-assunto-id') || '').trim();
                if (!assuntoId) {
                    return;
                }
                target.disabled = true;
                postJson(unlinkAssuntoEndpoint, {
                    processo_id: processoId,
                    assunto_id: assuntoId
                }).then(function (result) {
                    if (!result.data || result.data.error) {
                        notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao desvincular assunto.');
                        target.disabled = false;
                        return;
                    }
                    assuntosState = assuntosState.filter(function (item) { return item.id !== assuntoId; });
                    renderAssuntos(assuntosList, assuntosState);
                    notify('success', 'Sucesso', 'Assunto desvinculado do processo.');
                }).catch(function () {
                    notify('error', 'Erro', 'Erro de conexão ao desvincular assunto.');
                    target.disabled = false;
                });
            });
        }
    }

    App.ConsultaProcessosEdit = {
        initialize: initialize
    };
})(window);
