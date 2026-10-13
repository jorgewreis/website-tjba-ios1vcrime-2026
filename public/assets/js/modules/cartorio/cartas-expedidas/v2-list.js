(function (window) {
    'use strict';

    function copyToClipboard(text, payload) {
        function done() {
            if (typeof window.showToast === 'function') {
                window.showToast(payload);
            } else if (window.App && App.Page && typeof App.Page.notify === 'function') {
                App.Page.notify(payload.type, payload.title, payload.message);
            }
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(done);
        } else {
            done();
        }
    }

    function copyProcessNumber(numero) {
        var text = String(numero || '').trim();
        if (text === '') {
            return;
        }
        copyToClipboard(text, {
            type: 'success',
            title: 'Sucesso',
            message: 'O número ' + text + ' foi copiado com sucesso.'
        });
    }

    function copyWhatsappText(texto) {
        var text = String(texto || '').trim();
        if (text === '') {
            return;
        }
        copyToClipboard(text, {
            type: 'success',
            title: 'Sucesso',
            message: 'Texto para WhatsApp copiado com sucesso.'
        });
    }

    function bindCopyProcesso() {
        document.querySelectorAll('.js-v2-copy-processo').forEach(function (btn) {
            function handleCopy(event) {
                event.preventDefault();
                var numero = btn.getAttribute('data-processo-numero') || '';
                copyProcessNumber(numero);
            }

            btn.addEventListener('click', handleCopy);
            btn.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                handleCopy(event);
            });
        });
    }

    function bindCopyWhatsapp() {
        document.querySelectorAll('.js-v2-copy-whatsapp').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                var texto = btn.getAttribute('data-whatsapp-text') || '';
                copyWhatsappText(texto);
            });
        });
    }

    function bindSyncProcessoDataJud(ctx) {
        var notify = ctx.notify;
        var queuePostReloadToast = ctx.queuePostReloadToast;

        document.querySelectorAll('.js-v2-sync-processo').forEach(function (btn) {
            function setBusy(isBusy) {
                if (isBusy) {
                    btn.dataset.syncBusy = '1';
                    btn.setAttribute('aria-disabled', 'true');
                    btn.style.pointerEvents = 'none';
                    btn.style.opacity = '0.6';
                    return;
                }
                delete btn.dataset.syncBusy;
                btn.removeAttribute('aria-disabled');
                btn.style.pointerEvents = '';
                btn.style.opacity = '';
            }

            function handleSync(event) {
                event.preventDefault();
                if (btn.dataset.syncBusy === '1') {
                    return;
                }

                var numero = String(btn.getAttribute('data-processo-numero') || '').trim();
                if (numero === '') {
                    notify('error', 'Erro', 'Número do processo não informado para sincronização.');
                    return;
                }

                setBusy(true);
                fetch('/api/cartorio-cartas-expedidas/sync-processo-datajud.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ numero: numero })
                })
                    .then(function (response) {
                        return response.json().then(function (body) {
                            return { ok: response.ok, body: body };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok || !result.body || result.body.error) {
                            notify('error', 'Erro', (result.body && result.body.error) ? result.body.error : 'Erro ao sincronizar dados do processo.');
                            setBusy(false);
                            return;
                        }

                        queuePostReloadToast(
                            'success',
                            'Sucesso',
                            String(result.body.message || 'Dados do processo sincronizados com sucesso.')
                        );
                        window.location.reload();
                    })
                    .catch(function () {
                        notify('error', 'Erro', 'Erro de conexão ao sincronizar dados do processo.');
                        setBusy(false);
                    });
            }

            btn.addEventListener('click', handleSync);
            btn.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                handleSync(event);
            });
        });
    }

    function bindContinuarSubtarefa(ctx) {
        var notify = ctx.notify;
        var queuePostReloadToast = ctx.queuePostReloadToast;

        document.querySelectorAll('.js-continuar-subtarefa').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var subtarefaId = btn.getAttribute('data-subtarefa-id');
                if (!subtarefaId) {
                    return;
                }
                btn.disabled = true;
                fetch('/api/cartorio-cartas-expedidas/concluir-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        subtarefa_id: subtarefaId,
                        dados_especificos: null
                    })
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            queuePostReloadToast('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                            if (data.tarefa_principal_concluida && data.tarefa_principal_mensagem) {
                                queuePostReloadToast('success', 'Sucesso', data.tarefa_principal_mensagem);
                            }
                            window.location.reload();
                        } else {
                            notify('error', 'Erro', data.error || 'Erro ao concluir subtarefa.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        notify('error', 'Erro', 'Erro de conexão.');
                        btn.disabled = false;
                    });
            });
        });
    }

    function bindNovoCadastroV2(href, delayMs) {
        var btn = document.getElementById('novo-cadastro-btn');
        if (!btn || !href) {
            return;
        }

        var label = btn.querySelector('.btn-icon-text');
        var iconWrap = btn.querySelector('.btn-icon');
        var waitMs = typeof delayMs === 'number' && delayMs >= 0 ? delayMs : 1500;
        var navigating = false;

        btn.addEventListener('click', function () {
            if (navigating || btn.disabled || btn.classList.contains('disabled')) {
                return;
            }
            navigating = true;
            btn.disabled = true;
            btn.classList.add('disabled');
            btn.setAttribute('aria-disabled', 'true');

            if (label) {
                label.textContent = 'Aguarde...';
            }
            if (iconWrap) {
                iconWrap.classList.remove('hidden');
                iconWrap.classList.add('rotating');
            }

            window.setTimeout(function () {
                window.location.href = href;
            }, waitMs);
        });
    }

    function bindExportSelect() {
        var sel = document.getElementById('list-export-v2');
        if (!sel) {
            return;
        }
        sel.addEventListener('change', function () {
            var v = String(sel.value || '');
            if (v === 'print') {
                window.print();
                sel.selectedIndex = 0;
                return;
            }
            var href = '';
            if (v === 'csv') {
                href = String(sel.getAttribute('data-csv-href') || '').trim();
            } else if (v === 'pdf') {
                href = String(sel.getAttribute('data-pdf-href') || '').trim();
            }
            if (href !== '') {
                window.open(href, '_blank', 'noopener,noreferrer');
            }
            sel.selectedIndex = 0;
        });
    }

    function initialize(config) {
        bindCopyProcesso();
        bindCopyWhatsapp();
        bindExportSelect();
        bindNovoCadastroV2('/frame.php?page=cartasexpedidascreate', 1000);

        if (window.CartasExpedidasSearch && typeof window.CartasExpedidasSearch.initializeProcessSearch === 'function') {
            window.CartasExpedidasSearch.initializeProcessSearch({
                inputId: 'search-input',
                filters: [
                    { id: 'filter-tipo', param: 'finalidade' },
                    { id: 'filter-status', param: 'status' }
                ],
                minSearchDigits: 3,
                debounceDelay: 1000,
                initialProcessFilter: config.initialProcessFilter || ''
            });
        }

        if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
            App.RegistroMetadataList.bind({
                storageKey: 'cartorio.postReloadToast',
                cancelEndpoint: '/api/cartorio-cartas-expedidas/cancel.php',
                cancelToastKey: 'cartorio.cartas_expedidas.cancel.success',
                cancelFallback: { type: 'success', title: 'Sucesso', message: 'Carta cancelada.' }
            });
        }

        if (!window.App || !App.CartorioTaskList || typeof App.CartorioTaskList.initialize !== 'function') {
            return;
        }

        App.CartorioTaskList.initialize({
            storageKey: 'cartorio.postReloadToast',
            enableDesalocar: false,
            onReady: function (ctx) {
                bindSyncProcessoDataJud(ctx);
                bindContinuarSubtarefa(ctx);
                if(App.CartasReiniciar)App.CartasReiniciar.bind(ctx);
                if (window.App && App.Page && typeof App.Page.flushPostReloadToast === 'function') {
                    App.Page.flushPostReloadToast('cartorio.postReloadToast');
                }
            }
        });
    }

    window.CartasExpedidasV2List = {
        initialize: initialize
    };
})(window);
