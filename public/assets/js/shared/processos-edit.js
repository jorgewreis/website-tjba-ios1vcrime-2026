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

    function iniciarClasse() {
        const input = global.document.getElementById('proc-classe-codigo');
        const nome = global.document.getElementById('proc-classe-nome-existente');
        const salvar = global.document.getElementById('btn-proc-classe-vincular');
        const status = global.document.getElementById('proc-classe-status');
        const id = global.document.getElementById('proc-id')?.value;
        if (!input || !nome || !salvar || !status || !id) return;
        let timer, consulta = 0, codigoVerificado = '', gravando = false;
        async function buscar() {
            global.clearTimeout(timer);
            const codigo = input.value.trim(), atual = ++consulta;
            codigoVerificado = '';
            salvar.disabled = true;
            nome.textContent = 'Informe o código para consultar.';
            if (!/^[0-9]{1,20}$/.test(codigo)) {
                status.textContent = codigo ? 'Informe um código numérico.' : '';
                return;
            }
            status.textContent = 'Consultando catálogo…';
            try {
                const resposta = await global.fetch('/api/classes/find-by-codigo.php?codigo=' + encodeURIComponent(codigo),
                    {credentials:'same-origin', headers:{Accept:'application/json'}});
                const dados = await resposta.json();
                if (atual !== consulta || input.value.trim() !== codigo) return;
                if (!resposta.ok) throw new Error('Catálogo indisponível.');
                if (!dados.classe?.nome) {
                    status.textContent = 'Código de classe não encontrado no catálogo.';
                    return;
                }
                nome.textContent = dados.classe.nome;
                codigoVerificado = codigo;
                salvar.disabled = gravando;
                status.textContent = '';
            } catch (erro) {
                if (atual === consulta) status.textContent = 'Não foi possível consultar o catálogo de classes.';
            }
        }
        input.addEventListener('input', () => {
            ++consulta;
            global.clearTimeout(timer);
            nome.textContent = 'Informe o código para consultar.';
            status.textContent = '';
            salvar.disabled = true;
            codigoVerificado = '';
            timer = global.setTimeout(buscar, 1000);
        });
        input.addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault(); buscar();
        });
        salvar.addEventListener('click', async () => {
            if (gravando || !codigoVerificado || codigoVerificado !== input.value.trim()) return;
            gravando = true;
            setBusy(salvar, true);
            try {
                const resposta = await global.fetch('/api/processos/update-classe.php', {
                    method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({processo_id:id, codigo:codigoVerificado})
                });
                const dados = await resposta.json();
                if (!resposta.ok || dados.success !== true) throw new Error(dados.error || 'Classe não atualizada.');
                queuePostReloadToast('success', 'Classe atualizada', 'Classe vinculada ao processo.');
                global.location.reload();
            } catch (erro) {
                status.textContent = erro.message || 'Não foi possível atualizar a classe.';
            } finally {
                gravando = false;
                setBusy(salvar, false);
                salvar.disabled = !codigoVerificado || codigoVerificado !== input.value.trim();
            }
        });
    }

    async function sincronizarFichaDataJud() {
        const button = global.document.getElementById('btn-proc-datajud-sync');
        const status = global.document.getElementById('proc-datajud-sync-status');
        if (!button || !status || button.disabled) return;
        setBusy(button, true);
        status.textContent = 'Consultando o DataJud…';
        let reloading = false;
        try {
            const response = await global.fetch('/api/processos/datajud-sync.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json; charset=UTF-8' },
                body: JSON.stringify({
                    origem: 'ficha',
                    numero: button.dataset.numero,
                    processo_id: button.dataset.processId
                })
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.success !== true) {
                status.textContent = typeof result.error === 'string'
                    ? result.error : 'Não foi possível confirmar a sincronização. Confira a ficha antes de tentar novamente.';
                return;
            }
            const changed = [result.classe, result.assuntos].some(item =>
                item && (item.action === 'updated' || item.action === 'created'));
            status.textContent = result.message || 'Nenhuma alteração necessária.';
            if (changed) {
                queuePostReloadToast('success', 'DataJud', status.textContent);
                global.location.reload();
                reloading = true;
            }
        } catch (error) {
            status.textContent = 'Falha na comunicação. Confira os dados da ficha antes de tentar novamente.';
        } finally {
            if (!reloading) setBusy(button, false);
        }
    }

    function iniciarVaraLocal() {
        const form = global.document.getElementById('processo-vara-form');
        if (!form) return;
        const campo = form.querySelector('#processo-vara-local');
        const botao = form.querySelector('button[type="submit"]');
        const status = form.querySelector('#processo-vara-status');
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (botao.disabled) return;
            setBusy(botao, true);
            status.textContent = 'Salvando Vara local…';
            try {
                const resposta = await global.fetch('/api/processos/vara.php', {
                    method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({processo_id:form.dataset.processId, vara:campo.value})
                });
                const dados = await resposta.json();
                if (!resposta.ok || dados.success !== true) throw new Error(dados.error || 'Vara não confirmada.');
                global.location.reload();
            } catch (erro) {
                status.textContent = erro.message || 'Não foi possível salvar a Vara.';
                setBusy(botao, false);
            }
        });
    }

    function initialize() {
        flushPostReloadToast();

        global.document.getElementById('btn-proc-datajud-sync')?.addEventListener('click', sincronizarFichaDataJud);
        iniciarClasse();
        iniciarVaraLocal();
    }

    App.ProcessosEdit = App.ProcessosEdit || {};
    App.ProcessosEdit.initialize = initialize;
}(window));
