/**
 * Copia o número do processo para a área de transferência:
 * - clique em .process-info .major-info.
 * - sincronização DataJud no botão .js-processo-datajud-sync.
 * Toast de sucesso via showToast (iframe) ou App.Toast quando disponível.
 */
(function () {
    'use strict';

    var POST_RELOAD_TOAST_KEY = 'cartorio.postReloadToast';

    function showSuccessToast(text) {
        var payload = {
            type: 'success',
            variant: 'full',
            title: 'Sucesso',
            message: 'O número ' + text + ' foi copiado com sucesso.'
        };
        if (typeof window.showToast === 'function') {
            window.showToast(payload);
            return;
        }
        if (window.top && window.top !== window) {
            try {
                window.top.postMessage({ type: 'APP_TOAST', payload: payload }, '*');
                return;
            } catch (err) {
                /* continua */
            }
        }
        if (window.parent && window.parent !== window) {
            try {
                window.parent.postMessage({ type: 'APP_TOAST', payload: payload }, '*');
                return;
            } catch (err) {
                /* continua */
            }
        }
        if (window.App && window.App.Toast && typeof window.App.Toast.show === 'function') {
            window.App.Toast.show(payload);
        }
    }

    function notify(type, title, message) {
        var payload = {
            type: type || 'info',
            variant: type === 'error' ? 'full' : 'simple',
            title: title || 'Informação',
            message: message || ''
        };
        if (window.App && window.App.Page && typeof window.App.Page.notify === 'function') {
            window.App.Page.notify(payload.type, payload.title, payload.message);
            return;
        }
        if (typeof window.showToast === 'function') {
            window.showToast(payload);
        }
    }

    function queuePostReloadToast(type, title, message) {
        if (window.App && window.App.Page && typeof window.App.Page.queuePostReloadToast === 'function') {
            window.App.Page.queuePostReloadToast(type, title, message, POST_RELOAD_TOAST_KEY);
            return;
        }
        try {
            var current = JSON.parse(window.sessionStorage.getItem(POST_RELOAD_TOAST_KEY) || '[]');
            if (!Array.isArray(current)) current = [];
            current.push({ type: type || 'info', title: title || 'Informação', message: message || '' });
            window.sessionStorage.setItem(POST_RELOAD_TOAST_KEY, JSON.stringify(current));
        } catch (err) {
            /* toast pós-reload é opcional */
        }
    }

    function copyToClipboard(text, done) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fallbackCopy(text, done);
            });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (done) done();
        } catch (err) {
            if (done) done();
        }
        document.body.removeChild(textarea);
    }

    function handleCopyEvent(e, container) {
        if (!container) return;
        if (container.disabled || container.getAttribute('aria-disabled') === 'true') return;
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        var major = container.querySelector('.major-info');
        var text = (container.getAttribute('data-processo-numero') || (major ? major.textContent : '') || '').trim();
        if (!text) return;
        copyToClipboard(text, function () {
            showSuccessToast(text);
        });
    }

    function setSyncBusy(button, busy) {
        if (busy) {
            button.dataset.syncBusy = '1';
            button.setAttribute('aria-disabled', 'true');
            button.style.pointerEvents = 'none';
            button.style.opacity = '0.6';
            return;
        }
        delete button.dataset.syncBusy;
        button.removeAttribute('aria-disabled');
        button.style.pointerEvents = '';
        button.style.opacity = '';
    }

    function syncProcessoDataJud(e, button) {
        if (!button) return;
        if (button.dataset.syncBusy === '1') return;
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        var numero = String(button.getAttribute('data-processo-numero') || '').trim();
        if (numero === '') {
            notify('error', 'Erro', 'Número do processo não informado para sincronização.');
            return;
        }

        setSyncBusy(button, true);
        function postSync(url) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ numero: numero })
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            });
        }

        postSync('/api/processos/datajud-sync.php')
            .then(function (result) {
                if (!result.body || result.body.code !== 'acesso_setorial_obrigatorio') {
                    return result;
                }

                // A rota combinada só grava assuntos com acesso ao setor responsável.
                // A rota exclusiva de classe continua coberta pela política REI-76.
                return postSync('/api/processos/classe-sync.php').then(function (classResult) {
                    classResult.classOnly = true;
                    return classResult;
                });
            })
            .then(function (result) {
                if (!result.ok || !result.body || result.body.error) {
                    notify('error', 'Erro', (result.body && result.body.error) ? result.body.error : 'Erro ao sincronizar dados do processo.');
                    setSyncBusy(button, false);
                    return;
                }
                queuePostReloadToast(
                    'success',
                    'Sucesso',
                    result.classOnly
                        ? 'Classe sincronizada. Os assuntos não foram atualizados porque a operação exige acesso ao setor responsável.'
                        : String(result.body.message || 'Dados do processo sincronizados com sucesso.')
                );
                window.location.reload();
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão ao sincronizar dados do processo.');
                setSyncBusy(button, false);
            });
    }

    document.addEventListener('click', function (e) {
        var syncButton = e.target && e.target.closest ? e.target.closest('.js-processo-datajud-sync') : null;
        if (syncButton) {
            syncProcessoDataJud(e, syncButton);
            return;
        }

        var container = e.target && e.target.closest ? e.target.closest('.process-info') : null;
        handleCopyEvent(e, container);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;

        var syncButton = e.target && e.target.closest ? e.target.closest('.js-processo-datajud-sync') : null;
        if (syncButton) {
            syncProcessoDataJud(e, syncButton);
            return;
        }

        var container = e.target && e.target.closest ? e.target.closest('.process-info') : null;
        handleCopyEvent(e, container);
    });

    if (window.App && window.App.Page && typeof window.App.Page.flushPostReloadToast === 'function') {
        window.App.Page.flushPostReloadToast(POST_RELOAD_TOAST_KEY);
    }
})();
