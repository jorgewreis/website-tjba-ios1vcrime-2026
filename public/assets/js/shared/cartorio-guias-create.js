(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const helpers = App.CartorioCreateHelpers || null;

    function initialize(config) {
        if (!helpers) {
            return;
        }

        const settings = config || {};
        const button = global.document.getElementById('cadastrar-btn');
        const gate = helpers.initCreateButtonGate({ button: button, initialLabel: 'Novo cadastro', readyLabel: 'Cadastrar' });
        const feedback = helpers.notify;
        const processoInput = global.document.getElementById('filter-processo');

        if (!button) {
            return;
        }

        processoInput?.addEventListener('input', function () { gate.setWaiting(); });
        global.addEventListener('cnjInvalid', function (event) {
            const id = event && event.detail ? event.detail.inputId : '';
            if (!id || id === 'filter-processo') gate.setError();
        });
        global.addEventListener('datajudClasseResolved', function (event) {
            const detail = event && event.detail ? event.detail : {};
            if (!detail.processoInputId || detail.processoInputId === 'filter-processo') gate.setReady();
        });

        let alocState = null;
        if (settings.alocacaoPrefix && App.CartorioAlocacaoDirecionadaCreate && typeof App.CartorioAlocacaoDirecionadaCreate.init === 'function') {
            alocState = App.CartorioAlocacaoDirecionadaCreate.init(settings.alocacaoPrefix);
        }

        function getDenunciadoPayload() {
            const denunciadoSelect = global.document.getElementById('filter-denunciado-select');
            const useSelect = denunciadoSelect
                && denunciadoSelect.style.display !== 'none'
                && denunciadoSelect.value !== ''
                && denunciadoSelect.value !== 'outro'
                && denunciadoSelect.value !== '__novo__';

            if (denunciadoSelect && denunciadoSelect.style.display !== 'none' && denunciadoSelect.value === '') {
                return { reu_id: '', denunciado: '' };
            }
            if (useSelect) {
                const option = denunciadoSelect.options[denunciadoSelect.selectedIndex];
                return {
                    reu_id: denunciadoSelect.value,
                    denunciado: option ? (option.dataset.nomeParte || option.textContent) : ''
                };
            }

            return {
                reu_id: '',
                denunciado: App.ProperNameHelper && App.ProperNameHelper.formatProper
                    ? App.ProperNameHelper.formatProper(helpers.getValue('filter-denunciado'), 255)
                    : helpers.formatProperName(helpers.getValue('filter-denunciado'), 255)
            };
        }

        const pendingStorageKey = 'cartorio.guias.pendingCreate';
        async function doCreate(payload) {
            if (alocState && typeof alocState.getIds === 'function') {
                const extra = alocState.getIds();
                if (extra && extra.length) {
                    payload.alocar_usuario_ids = extra;
                }
            }
            try {
                if (!global.crypto || !global.crypto.subtle || !global.sessionStorage) throw new Error('Envio seguro indisponível nesta sessão.');
                const actor = String(settings.actorFingerprint || '');
                if (!/^[a-f0-9]{64}$/.test(actor)) throw new Error('Sessão inválida para envio seguro. Recarregue a página.');
                payload.actor_fingerprint = actor;
                const bytes = new TextEncoder().encode(JSON.stringify(payload));
                const digest = await global.crypto.subtle.digest('SHA-256', bytes);
                const signature = Array.from(new Uint8Array(digest), b => b.toString(16).padStart(2, '0')).join('');
                let pending = null;
                const stored = global.sessionStorage.getItem(pendingStorageKey);
                if (stored) {
                    try { pending = JSON.parse(stored); } catch (e) { throw new Error('Não foi possível recuperar o envio anterior com segurança.'); }
                    if (!pending || !/^[a-f0-9]{64}$/.test(pending.key || '') || !/^[a-f0-9]{64}$/.test(pending.signature || '') || !/^[a-f0-9]{64}$/.test(pending.actor || '')) throw new Error('Não foi possível recuperar o envio anterior com segurança.');
                }
                if (pending && (pending.signature !== signature || pending.actor !== actor)) {
                    if (!global.confirm('Há um envio anterior com resultado incerto e dados diferentes. Iniciar outro envio pode criar uma guia adicional. Deseja continuar?')) {
                        gate.setInitial();
                        return;
                    }
                    pending = null;
                }
                if (!pending) {
                    const random = new Uint8Array(32);
                    global.crypto.getRandomValues(random);
                    pending = { key: Array.from(random, b => b.toString(16).padStart(2, '0')).join(''), signature: signature, actor: actor };
                }
                const serialized = JSON.stringify(pending);
                global.sessionStorage.setItem(pendingStorageKey, serialized);
                if (global.sessionStorage.getItem(pendingStorageKey) !== serialized) throw new Error('Não foi possível guardar o envio seguro nesta sessão.');
                payload.idempotency_key = pending.key;
            } catch (error) {
                feedback(error.message || 'Envio seguro indisponível nesta sessão.', 'error');
                gate.setInitial();
                return;
            }
            feedback('Enviando dados...', 'info', 'Informação');
            fetch(settings.createEndpoint || '/api/cartorio-guias/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (response.status === 410 && global.confirm('O envio anterior foi confirmado, mas a guia foi removida. Deseja descartar essa pendência e iniciar um novo envio?')) {
                            global.sessionStorage.removeItem(pendingStorageKey);
                            gate.setInitial();
                            return { error: 'Pendência descartada. Confira os dados e envie novamente.' };
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (!data.id) {
                        throw new Error(data.error || 'Não foi possível cadastrar.');
                    }
                    try { global.sessionStorage.removeItem(pendingStorageKey); } catch (e) { /* A reserva permanece segura no servidor. */ }

                    if (data.valor_multa_calculado) {
                        feedback('Cálculo realizado automaticamente pelo sistema. Valor da pena de multa: ' + data.valor_multa_calculado + '.', 'success', 'Cálculo automático');
                    } else {
                        feedback('Guia cadastrada com sucesso.', 'success');
                    }

                    gate.setInitial();
                    global.location.href = settings.successRedirect || '/frame.php?page=guias';
                })
                .catch(function (error) {
                    feedback(error && error.message ? error.message : 'Erro de conexão.', 'error');
                    gate.setInitial();
                });
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            if (button.disabled) {
                return;
            }

            global.document.getElementById('filter-processo')?.blur();
            global.document.getElementById('filter-denunciado')?.blur();

            const processo = helpers.normalizeProcessValue(helpers.getValue('filter-processo'), {
                inputId: 'filter-processo',
                buttonGate: gate,
                feedback: feedback,
                requiredMessage: 'Informe o número do processo.'
            });
            if (!processo.ok) {
                return;
            }

            const classeVal = helpers.getValue('filter-classe');
            if (classeVal === 'Aguarde...') {
                gate.setWaiting();
                feedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                return;
            }

            const denunciadoPayload = getDenunciadoPayload();
            if (!denunciadoPayload.denunciado) {
                feedback('Informe o denunciado/réu (selecione na lista ou digite em "Outro").', 'error');
                gate.setError();
                return;
            }

            const cpf = denunciadoPayload.reu_id ? '' : String(global.document.getElementById('filter-cpf')?.value || '').trim();
            const confirmedNew = !!global.document.getElementById('confirmar-nova-pessoa')?.checked;
            if (!denunciadoPayload.reu_id && !cpf && !confirmedNew) {
                feedback('Selecione uma pessoa ou confirme o cadastro de uma nova pessoa.', 'error');
                gate.setError();
                return;
            }

            const regime = helpers.getValue('filter-regime');
            const dataFato = helpers.getValue('filter-data-fato');
            const diasMultaValue = global.document.getElementById('filter-dias-multa')?.value;
            const diasMulta = diasMultaValue !== '' && typeof diasMultaValue !== 'undefined'
                ? parseInt(diasMultaValue, 10)
                : null;

            if (dataFato) {
                const hoje = new Date();
                hoje.setHours(0, 0, 0, 0);
                const dtFato = new Date(dataFato);
                if (dtFato >= hoje) {
                    feedback('A data do fato deve ser anterior à data de hoje.', 'error');
                    gate.setError();
                    return;
                }
            }

            if (regime && ['Aberto', 'Semiaberto', 'Fechado'].indexOf(regime) === -1) {
                feedback('Regime inválido. Selecione Aberto, Semiaberto ou Fechado.', 'error');
                gate.setError();
                return;
            }

            const payload = Object.assign({
                numero_processo: processo.value,
                denunciado: denunciadoPayload.denunciado,
                tipo: helpers.getValue('filter-tipo') || 'Guia - Tipo não informado'
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            if (denunciadoPayload.reu_id) {
                payload.reu_id = denunciadoPayload.reu_id;
            }
            if (cpf) payload.cpf = cpf;
            if (!denunciadoPayload.reu_id && !cpf) payload.confirmar_nova_pessoa = true;
            if (regime) {
                payload.regime = regime;
            }
            if (dataFato) {
                payload.data_fato = dataFato;
            }
            if (diasMulta !== null && !isNaN(diasMulta)) {
                payload.dias_multa = diasMulta;
            }

            gate.setSubmitting();
            feedback('Verificando duplicidade...', 'info', 'Informação');

            fetch(settings.duplicateEndpoint || '/api/cartorio-guias/check-duplicate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    numero_processo: processo.value,
                    denunciado: denunciadoPayload.denunciado,
                    tipo: payload.tipo
                })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.exists && !global.confirm('Já existe uma Guia cadastrada com o mesmo tipo e mesmo réu. Deseja cadastrar assim mesmo?')) {
                        gate.setInitial();
                        return;
                    }
                    doCreate(payload);
                })
                .catch(function () {
                    feedback('Erro ao verificar duplicidade. Tente novamente.', 'error');
                    gate.setInitial();
                });
        });
    }

    App.CartorioGuiasCreate = {
        initialize: initialize
    };
})(window);
