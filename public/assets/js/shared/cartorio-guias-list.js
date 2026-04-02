(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function normalizeTipo(value) {
        return String(value || '').trim().toLowerCase();
    }

    function buildFilterNavigator(routePage) {
        return function applyFilters() {
            const tipo = global.document.getElementById('filter-tipo')?.value || '';
            const status = global.document.getElementById('filter-status')?.value || '';
            const itemsPerPage = global.document.getElementById('items-per-page')?.value || '25';
            const processo = global.document.getElementById('search-input')?.value?.trim() || '';
            const params = new URLSearchParams(global.location.search);

            params.set('page', routePage);
            params.set('page_number', '1');
            params.set('items_per_page', itemsPerPage);

            if (tipo) {
                params.set('tipo', tipo);
            } else {
                params.delete('tipo');
            }

            if (status) {
                params.set('status', status);
            } else {
                params.delete('status');
            }

            if (processo) {
                params.set('processo', processo);
            } else {
                params.delete('processo');
            }

            global.location.href = '?' + params.toString();
        };
    }

    function buildSuccessMessage(subtarefaTipo) {
        const messages = {
            detalhamento_informacoes: 'Detalhamento concluído: data do fato e dias-multa informados.',
            calculo_pena_multa: 'Cálculo da pena de multa registrado.',
            expedicao_guia: 'Expedição da Guia ao cartório registrada.',
            aguardando_assinatura: 'Assinatura da Juíza na Guia registrada.',
            juntada_automatica: 'Juntada dos autos registrada (automática ou manual).',
            gerar_pdf: 'Geração dos documentos em PDF registrada.',
            envio_guia: 'Envio da Guia registrado (canal e destinatário).',
            juntada_termo_envio: 'Juntada do termo de envio aos autos registrada.'
        };

        return messages[subtarefaTipo] || 'Subtarefa concluída.';
    }

    function collectSpecificData(block) {
        const data = {};

        const dataFato = block.querySelector('.subtask-data-fato');
        const diasMulta = block.querySelector('.subtask-dias-multa');
        const valorMulta = block.querySelector('.subtask-valor-multa');
        const tipoGuia = block.querySelector('.subtask-tipo-guia');
        const tipoJuntada = block.querySelector('.subtask-tipo-juntada');
        const qtdDocs = block.querySelector('.subtask-qtd-docs');
        const tipoEnvio = block.querySelector('.subtask-tipo-envio');
        const destinatario = block.querySelector('.subtask-destinatario');

        if (dataFato && dataFato.value && dataFato.value.trim()) {
            data.data_fato = dataFato.value.trim();
        }
        if (diasMulta && diasMulta.value !== '' && parseInt(diasMulta.value, 10) >= 0) {
            data.dias_multa = parseInt(diasMulta.value, 10);
        }
        if (valorMulta && valorMulta.value && valorMulta.value.trim()) {
            data.valor_multa = valorMulta.value.trim();
        }
        if (tipoGuia && tipoGuia.value && tipoGuia.value.trim()) {
            data.tipo_guia = tipoGuia.value.trim();
        }
        if (tipoJuntada && tipoJuntada.value && tipoJuntada.value.trim()) {
            data.tipo_juntada = tipoJuntada.value.trim();
        }
        if (qtdDocs && qtdDocs.value !== '') {
            const value = parseInt(qtdDocs.value, 10);
            if (value >= 4) {
                data.qtd_docs = value;
            }
        }
        if (tipoEnvio && tipoEnvio.value && tipoEnvio.value.trim()) {
            data.tipo_envio = tipoEnvio.value.trim();
        }
        if (destinatario && destinatario.value && destinatario.value.trim()) {
            data.destinatario = destinatario.value.trim();
        }

        return data;
    }

    function validateSpecificData(subtarefaTipo, block, data, notify) {
        const tipo = normalizeTipo(subtarefaTipo);

        if (['detalhamento_informacoes', 'informar_detalhes', 'detalhamento'].includes(tipo)) {
            const dataFatoValor = (data.data_fato || '').trim();
            if (dataFatoValor !== '') {
                const hoje = new Date();
                hoje.setHours(0, 0, 0, 0);
                const limiteMinimo = new Date(hoje);
                limiteMinimo.setDate(limiteMinimo.getDate() - 10);
                const dataFatoDate = new Date(dataFatoValor + 'T00:00:00');

                if (isNaN(dataFatoDate.getTime()) || dataFatoDate > hoje || dataFatoDate < limiteMinimo) {
                    notify('error', 'Atenção', 'A data do fato deve estar dentro dos últimos 10 dias e não pode ser futura.');
                    return false;
                }
            }
        }

        if (['juntada_automatica', 'confirmar_juntada', 'juntada_autos', 'juntada_guia_autos', 'juntada_nos_autos'].includes(tipo) && !data.tipo_juntada) {
            notify('error', 'Atenção', 'Selecione o tipo de juntada (automática ou manual).');
            return false;
        }

        if (['expedicao_guia', 'expedir_guia', 'expedicao'].includes(tipo) && !data.tipo_guia) {
            notify('error', 'Atenção', 'Selecione o tipo da guia expedida.');
            return false;
        }

        if (['gerar_pdf', 'geracao_pdf', 'pdf'].includes(tipo)) {
            const qtd = parseInt(block.querySelector('.subtask-qtd-docs')?.value || '0', 10);
            if (qtd < 4) {
                notify('error', 'Atenção', 'Informe no mínimo 4 documentos.');
                return false;
            }
            data.qtd_docs = qtd;
        }

        if (['envio_guia', 'enviar_guia', 'envio'].includes(tipo) && (!data.tipo_envio || !data.destinatario)) {
            notify('error', 'Atenção', 'Selecione o tipo de envio e o destinatário.');
            return false;
        }

        return true;
    }

    function bindConcludeButtons(ctx, endpoint) {
        global.document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (button) {
            button.addEventListener('click', function () {
                const subtarefaId = button.getAttribute('data-subtarefa-id');
                if (!subtarefaId) {
                    return;
                }

                const block = button.closest('div.subtask[data-subtarefa-id]');
                const subtarefaTipo = normalizeTipo(button.getAttribute('data-subtarefa-tipo') || block?.getAttribute('data-subtarefa-tipo'));
                const specificData = block ? collectSpecificData(block) : {};

                if (block && !validateSpecificData(subtarefaTipo, block, specificData, ctx.notify)) {
                    return;
                }

                const payload = { id: subtarefaId, subtarefa_id: subtarefaId };
                if (Object.keys(specificData).length > 0) {
                    payload.dados_especificos = specificData;
                }

                button.disabled = true;

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data && !result.data.error) {
                            ctx.queuePostReloadToast('success', 'Sucesso', buildSuccessMessage(subtarefaTipo));
                            if (result.data.tarefa_principal_concluida && result.data.tarefa_principal_mensagem) {
                                ctx.queuePostReloadToast('success', 'Sucesso', result.data.tarefa_principal_mensagem);
                            }
                            global.location.reload();
                            return;
                        }

                        ctx.notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao concluir subtarefa.');
                        button.disabled = false;
                    })
                    .catch(function () {
                        ctx.notify('error', 'Erro', 'Erro ao concluir subtarefa.');
                        button.disabled = false;
                    });
            });
        });
    }

    function bindCancelButtons(ctx, endpoint) {
        global.document.querySelectorAll('.btn-cancelar-subtarefa').forEach(function (button) {
            button.addEventListener('click', function () {
                const subtarefaId = button.getAttribute('data-subtarefa-id');
                if (!subtarefaId) {
                    return;
                }

                if (!global.confirm('Deseja cancelar a subtarefa "Detalhamento de informações"? A próxima etapa será disponibilizada.')) {
                    return;
                }

                button.disabled = true;

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subtarefa_id: subtarefaId })
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data && !result.data.error) {
                            ctx.queuePostReloadToast('success', 'Sucesso', 'Detalhamento cancelado: etapa de data do fato e dias-multa não será concluída.');
                            global.location.reload();
                            return;
                        }

                        ctx.notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao cancelar.');
                        button.disabled = false;
                    })
                    .catch(function () {
                        ctx.notify('error', 'Erro', 'Erro de conexão.');
                        button.disabled = false;
                    });
            });
        });
    }

    function bindCreateButtons(ctx, endpoint) {
        global.document.querySelectorAll('.btn-criar-subtarefa').forEach(function (button) {
            button.addEventListener('click', function () {
                const guiaId = button.getAttribute('data-guia-id');
                if (!guiaId) {
                    return;
                }

                button.disabled = true;

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: guiaId })
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data && !result.data.error) {
                            ctx.queuePostReloadToast('success', 'Sucesso', 'Primeira subtarefa criada: detalhamento (data do fato e dias-multa) disponível para conclusão.');
                            global.location.reload();
                            return;
                        }

                        ctx.notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao criar subtarefa.');
                        button.disabled = false;
                    })
                    .catch(function () {
                        ctx.notify('error', 'Erro', 'Erro ao criar subtarefa.');
                        button.disabled = false;
                    });
            });
        });
    }

    function initialize(config) {
        if (!App.CartorioTaskList || typeof App.CartorioTaskList.initialize !== 'function') {
            return null;
        }

        const settings = config || {};
        return App.CartorioTaskList.initialize({
            storageKey: settings.storageKey || 'cartorio.postReloadToast',
            newButtonId: 'novo-cadastro-btn',
            newHref: settings.newHref || '/frame.php?page=guiascreate',
            editButtonSelector: '.btn-editar[data-edit-id]',
            getEditId: function (button) {
                return button.getAttribute('data-edit-id') || '';
            },
            editHrefBuilder: function (itemId) {
                return (settings.editBaseHref || '/frame.php?page=guiasedit&id=') + encodeURIComponent(itemId);
            },
            filters: {
                triggerIds: ['filter-tipo', 'filter-status', 'items-per-page'],
                searchInputId: 'search-input',
                searchMode: 'debounce',
                searchDelay: 800,
                apply: buildFilterNavigator(settings.routePage || 'guias')
            },
            onReady: function (ctx) {
                bindConcludeButtons(ctx, settings.concludeEndpoint || '/api/cartorio-guias/concluir-subtarefa.php');
                bindCancelButtons(ctx, settings.cancelEndpoint || '/api/cartorio-guias/cancelar-subtarefa.php');
                bindCreateButtons(ctx, settings.createEndpoint || '/api/cartorio-guias/criar-primeira-subtarefa.php');

                if (typeof settings.onReady === 'function') {
                    settings.onReady(ctx);
                }
            }
        });
    }

    App.CartorioGuiasList = {
        initialize: initialize
    };
})(window);
