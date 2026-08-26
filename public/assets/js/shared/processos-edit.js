(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const POST_RELOAD_TOAST_KEY = 'consultaprocessosedit.postReloadToast';

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
            return;
        }

        if (typeof global.showToast === 'function') {
            global.showToast({
                type: type || 'info',
                variant: 'full',
                title: title || 'Informação',
                message: message || ''
            });
        }
    }

    function queuePostReloadToast(type, title, message) {
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(type, title, message, POST_RELOAD_TOAST_KEY);
            return;
        }

        try {
            const raw = global.sessionStorage.getItem(POST_RELOAD_TOAST_KEY);
            const current = raw ? JSON.parse(raw) : [];
            const list = Array.isArray(current) ? current : (current && current.message ? [current] : []);
            list.push({ type: type, title: title, message: message });
            global.sessionStorage.setItem(POST_RELOAD_TOAST_KEY, JSON.stringify(list));
        } catch (error) {
            // noop
        }
    }

    function flushPostReloadToast() {
        if (App.Page && typeof App.Page.flushPostReloadToast === 'function') {
            App.Page.flushPostReloadToast(POST_RELOAD_TOAST_KEY, { type: 'success', title: 'Classe atualizada' });
        }
    }

    function resetEstado() {
        const resultado = global.document.getElementById('proc-classe-resultado');
        const blocoExistente = global.document.getElementById('proc-classe-bloco-existente');
        const blocoNovo = global.document.getElementById('proc-classe-bloco-novo');
        const inputExistente = global.document.getElementById('proc-classe-nome-existente');
        const inputNovo = global.document.getElementById('proc-classe-nome-novo');

        if (resultado) {
            resultado.style.display = 'none';
        }
        if (blocoExistente) {
            blocoExistente.style.display = 'none';
        }
        if (blocoNovo) {
            blocoNovo.style.display = 'none';
        }
        if (inputExistente) {
            inputExistente.value = '';
        }
        if (inputNovo) {
            inputNovo.value = '';
        }
    }

    function setBusy(button, busy) {
        if (!button) {
            return;
        }
        if (busy) {
            button.dataset.originalLabel = button.dataset.originalLabel || button.textContent || '';
            button.disabled = true;
            button.textContent = 'Aguarde...';
        } else {
            button.disabled = false;
            if (button.dataset.originalLabel) {
                button.textContent = button.dataset.originalLabel;
            }
        }
    }

    var BUSCA_CLASSE_DEBOUNCE_MS = 1000;
    var buscaClasseTimer = null;

    function clearBuscaClasseTimer() {
        if (buscaClasseTimer !== null) {
            global.clearTimeout(buscaClasseTimer);
            buscaClasseTimer = null;
        }
    }

    function scheduleBuscarClasse() {
        clearBuscaClasseTimer();
        const inputCodigo = global.document.getElementById('proc-classe-codigo');
        const codigo = inputCodigo ? String(inputCodigo.value || '').trim() : '';
        if (codigo === '') {
            return;
        }
        buscaClasseTimer = global.setTimeout(function () {
            buscaClasseTimer = null;
            buscarClasse();
        }, BUSCA_CLASSE_DEBOUNCE_MS);
    }

    function buscarClasse() {
        const inputCodigo = global.document.getElementById('proc-classe-codigo');
        const btnBuscar = global.document.getElementById('btn-proc-classe-buscar');
        const codigo = inputCodigo ? String(inputCodigo.value || '').trim() : '';

        if (codigo === '') {
            notify('error', 'Código obrigatório', 'Informe o código da classe para consultar.');
            if (inputCodigo) {
                inputCodigo.focus();
            }
            return;
        }

        resetEstado();
        setBusy(btnBuscar, true);

        global.fetch('/api/classes/find-by-codigo.php?codigo=' + encodeURIComponent(codigo), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, data: json };
                });
            })
            .then(function (result) {
                setBusy(btnBuscar, false);

                if (!result.ok) {
                    const msg = (result.data && result.data.error) ? result.data.error : 'Não foi possível consultar a classe.';
                    notify('error', 'Erro', msg);
                    return;
                }

                const resultado = global.document.getElementById('proc-classe-resultado');
                if (resultado) {
                    resultado.style.display = '';
                }

                if (result.data && result.data.classe) {
                    const blocoExistente = global.document.getElementById('proc-classe-bloco-existente');
                    const inputExistente = global.document.getElementById('proc-classe-nome-existente');
                    if (inputExistente) {
                        inputExistente.value = String(result.data.classe.nome || '');
                    }
                    if (blocoExistente) {
                        blocoExistente.style.display = '';
                    }
                    notify('info', 'Classe encontrada', 'Confirme para vincular ao processo.');
                } else {
                    const blocoNovo = global.document.getElementById('proc-classe-bloco-novo');
                    if (blocoNovo) {
                        blocoNovo.style.display = '';
                    }
                    const inputNovo = global.document.getElementById('proc-classe-nome-novo');
                    if (inputNovo) {
                        inputNovo.focus();
                    }
                    notify('info', 'Classe não encontrada', 'Informe o nome para cadastrar e vincular ao processo.');
                }
            })
            .catch(function () {
                setBusy(btnBuscar, false);
                notify('error', 'Erro', 'Falha de comunicação ao consultar a classe.');
            });
    }

    function enviarAtualizacao(payload, button, mensagemSucesso) {
        const processoId = (global.document.getElementById('proc-id') || {}).value || '';
        if (!processoId) {
            notify('error', 'Processo inválido', 'Identificador do processo não encontrado.');
            return;
        }

        setBusy(button, true);
        const body = Object.assign({ processo_id: processoId }, payload || {});

        global.fetch('/api/processos/update-classe.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, data: json };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    setBusy(button, false);
                    const msg = (result.data && result.data.error) ? result.data.error : 'Não foi possível atualizar a classe do processo.';
                    notify('error', 'Erro', msg);
                    return;
                }

                queuePostReloadToast('success', 'Classe atualizada', mensagemSucesso || 'Classe vinculada ao processo com sucesso.');
                global.location.reload();
            })
            .catch(function () {
                setBusy(button, false);
                notify('error', 'Erro', 'Falha de comunicação ao atualizar a classe.');
            });
    }

    function vincularExistente() {
        const inputCodigo = global.document.getElementById('proc-classe-codigo');
        const codigo = inputCodigo ? String(inputCodigo.value || '').trim() : '';
        if (codigo === '') {
            notify('error', 'Código obrigatório', 'Informe o código da classe.');
            return;
        }

        const button = global.document.getElementById('btn-proc-classe-vincular');
        enviarAtualizacao({ codigo: codigo }, button, 'Classe vinculada ao processo.');
    }

    function cadastrarNova() {
        const inputCodigo = global.document.getElementById('proc-classe-codigo');
        const inputNome = global.document.getElementById('proc-classe-nome-novo');
        const codigo = inputCodigo ? String(inputCodigo.value || '').trim() : '';
        const nome = inputNome ? String(inputNome.value || '').trim() : '';

        if (codigo === '') {
            notify('error', 'Código obrigatório', 'Informe o código da classe.');
            return;
        }
        if (nome === '') {
            notify('error', 'Nome obrigatório', 'Informe o nome da classe para cadastrar.');
            if (inputNome) {
                inputNome.focus();
            }
            return;
        }

        const button = global.document.getElementById('btn-proc-classe-cadastrar');
        enviarAtualizacao({ codigo: codigo, nome: nome }, button, 'Classe cadastrada e vinculada ao processo.');
    }

    function initialize() {
        flushPostReloadToast();

        global.document.getElementById('btn-proc-classe-buscar')?.addEventListener('click', buscarClasse);
        global.document.getElementById('btn-proc-classe-vincular')?.addEventListener('click', vincularExistente);
        global.document.getElementById('btn-proc-classe-cadastrar')?.addEventListener('click', cadastrarNova);

        const inputCodigo = global.document.getElementById('proc-classe-codigo');
        if (inputCodigo) {
            inputCodigo.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    clearBuscaClasseTimer();
                    buscarClasse();
                }
            });
            inputCodigo.addEventListener('input', function () {
                resetEstado();
                scheduleBuscarClasse();
            });
        }

        const inputNomeNovo = global.document.getElementById('proc-classe-nome-novo');
        if (inputNomeNovo) {
            inputNomeNovo.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    cadastrarNova();
                }
            });
        }
    }

    App.ProcessosEdit = App.ProcessosEdit || {};
    App.ProcessosEdit.initialize = initialize;
}(window));
