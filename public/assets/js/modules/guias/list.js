// Integração dos botões do bloco original de UI da subtarefa de pena de multa
document.addEventListener('click', async function(e) {
    // (Removido botão calcular - cálculo será automático)

    // Botão Sem pena de multa
    const btnSemMulta = e.target.closest('.btn-sem-pena-multa, .btn-pular-etapa');
    if (btnSemMulta) {
        const card = btnSemMulta.closest('.subtask-item[data-codigo="calcular-pena-multa"]');
        if (!card) return;
        const subtaskId = parseInt(card.dataset.id, 10);
        if (!subtaskId) return;
        btnSemMulta.disabled = true;
        const userId = (typeof obterUserIdAtual === 'function' ? obterUserIdAtual(card) : '') || 'sistema';
        try {
            if (typeof atualizarStatusSubtarefa === 'function') {
                await atualizarStatusSubtarefa(subtaskId, 'Concluída', userId, async () => {
                    card.classList.add('done');
                    card.style.display = 'none';
                }, (err) => {
                    console.error('Erro ao concluir subtarefa sem pena de multa', err);
                    const msg = err && err.message ? err.message : err;
                    alert('Erro ao encerrar subtarefa: ' + msg);
                });
            } else {
                card.classList.add('done');
                card.style.display = 'none';
            }
        } catch (err) {
            alert('Erro ao encerrar subtarefa: ' + (err.message || err));
        } finally {
            btnSemMulta.disabled = false;
        }
        return;
    }
});

function formatarMoeda(valor) {
    if (valor === null || valor === undefined || Number.isNaN(valor)) {
        return '0,00';
    }
    return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function montarResumoCalculoHtml({ dataFato = '-', salarioMinimo = 0, baseDias = 0, valorUnitario = 0, valorMulta = 0 }) {
    return `
        <div class="subtask-name">Cálculo concluído</div>
        <div class="subtask-fields">
            <div><b>Data do fato:</b> ${dataFato || '-'} · <b>Salário mínimo:</b> R$ ${formatarMoeda(salarioMinimo)}</div>
            <div><b>Base:</b> ${baseDias || 0} dias-multa · <b>Valor unitário:</b> R$ ${formatarMoeda(valorUnitario)}</div>
            <div><b>Total:</b> <strong>R$ ${formatarMoeda(valorMulta)}</strong></div>
            <div style="color: #2e7d32; font-size: 12px; margin-top: 4px;">Calculado automaticamente pelo sistema</div>
        </div>
    `;
}

function aplicarResumoCalculo(card, dados) {
    card.innerHTML = montarResumoCalculoHtml(dados);
    card.dataset.salarioMinimo = String(dados.salarioMinimo || '');
    card.dataset.baseCalculoDias = String(dados.baseDias || '');
    card.dataset.valorUnitario = String(dados.valorUnitario || '');
    card.dataset.valorMulta = String(dados.valorMulta || '');
    card.dataset.dataFato = String(dados.dataFato || '');
    card.classList.add('done');
}
// Toasts e parâmetros de URL
// Função para renderizar campos editáveis na subtarefa de cálculo da pena de multa
// Hook para inserir campos editáveis na subtarefa de pena de multa ao expandir

// Força a injeção dos campos de edição SEM depender de evento customizado

// Hook para renderizar campos editáveis ao expandir a guia
document.addEventListener('click', async function(e) {
    const toggle = e.target.closest('.proposal-closed-toggle');
    if (!toggle) return;
    const row = toggle.closest('.row.table-row');
    if (!row) return;
    const subtasksContainer = row.querySelector('[data-subtasks]');
    if (!subtasksContainer) return;
    // Evita duplicidade
    if (subtasksContainer.dataset.loaded === '1') return;
    subtasksContainer.dataset.loaded = '1';
    // Busca subtarefas via API
    const idGuia = row.dataset.id;
    if (!idGuia) return;
    try {
        const resp = await fetch(`../v2026/api/guia-subtarefas.php?id_guia=${encodeURIComponent(idGuia)}`);
        const data = await resp.json();
        if (!data.ok || !Array.isArray(data.items)) return;
        const list = document.createElement('div');
        list.className = 'subtask-list';
        // Renderizar cada subtarefa
        data.items.forEach((item) => {
            if (item.codigo === 'calcular-pena-multa') {
                const card = document.createElement('div');
                card.className = 'subtask-item';
                card.dataset.codigo = 'calcular-pena-multa';
                card.dataset.id = item.id;
                const temCalculo = !!(item.valor_multa > 0 && item.salario_minimo_vigente > 0 && item.base_calculo_dias > 0);
                if (!temCalculo) {
                    // Exibe inputs para edição
                    card.innerHTML = `
                        <div class="subtask-name">Cálculo da pena de multa</div>
                        <div class="subtask-fields">
                            <label>Data do fato <input type="date" class="input-data-fato" value="${item.data_fato || ''}"></label>
                            <label>Dias-multa <input type="number" class="input-dias-multa" min="0" step="1" value="${item.dias_multa || ''}"></label>
                            <button type="button" class="btn-pular-etapa">Pular etapa</button>
                            <span class="pena-multa-resultado"></span>
                        </div>
                    `;
                    // Cálculo automático ao preencher campos
                    const inputData = card.querySelector('.input-data-fato');
                    const inputDias = card.querySelector('.input-dias-multa');
                    const resultado = card.querySelector('.pena-multa-resultado');
                    async function calcular() {
                            const data = inputData.value;
                            const dias = parseInt(inputDias.value, 10);
                            if (!data || !dias) {
                                resultado.textContent = '';
                                return;
                            }
                            try {
                                const salario = await (typeof window !== 'undefined' && typeof window.atualizarSalarioMinimoAutomatico === 'function'
                                    ? window.atualizarSalarioMinimoAutomatico(data)
                                    : null);
                                if (!salario) {
                                    resultado.textContent = 'Salário mínimo não encontrado';
                                    return;
                                }
                                const valorUnitario = salario / 30;
                                const valorMulta = valorUnitario * dias;
                                resultado.innerHTML = `<b>Valor da multa:</b> R$ ${valorMulta.toLocaleString('pt-BR', {minimumFractionDigits:2})}`;
                                const userId = (typeof obterUserIdAtual === 'function' ? obterUserIdAtual(card) : '') || 'sistema';
                                const payload = {
                                    calculo: {
                                        data_fato: data,
                                        dias_multa: dias,
                                        salario_minimo_vigente: salario,
                                        valor_unitario: valorUnitario,
                                        valor_multa: valorMulta
                                    }
                                };
                                if (typeof atualizarStatusSubtarefa === 'function') {
                                    await atualizarStatusSubtarefa(item.id, 'Concluída', userId, async () => {
                                        // manter UI atualizada via resumo
                                    }, (err) => {
                                        console.error('Erro ao concluir subtarefa de pena de multa:', err);
                                        resultado.textContent = 'Erro no cálculo';
                                    }, payload);
                                } else {
                                    console.warn('atualizarStatusSubtarefa indisponível; calc não persistido');
                                }
                                aplicarResumoCalculo(card, {
                                    dataFato: data,
                                    salarioMinimo: salario,
                                    baseDias: dias,
                                    valorUnitario,
                                    valorMulta
                                });
                            } catch (err) {
                                console.error('Erro ao calcular pena de multa:', err);
                                resultado.textContent = 'Erro no cálculo';
                            }
                    }
                    inputData.addEventListener('change', calcular);
                    inputDias.addEventListener('change', calcular);
                } else {
                    aplicarResumoCalculo(card, {
                        dataFato: item.data_fato,
                        salarioMinimo: item.salario_minimo_vigente,
                        baseDias: item.base_calculo_dias,
                        valorUnitario: item.valor_unitario,
                        valorMulta: item.valor_multa
                    });
                }
                list.appendChild(card);
            }
            // Outras subtarefas podem ser renderizadas aqui...
        });
        subtasksContainer.innerHTML = '';
        subtasksContainer.appendChild(list);
    } catch (err) {
        subtasksContainer.innerHTML = '<div class="subtask-empty">Erro ao carregar subtarefas.</div>';
    }
});
function showToastsFromUrlParams() {
    const url = new URL(window.location.href);
    const ok = url.searchParams.get('ok');
    const updated = url.searchParams.get('updated');
    const pontos = url.searchParams.get('prod_pontos');
    const procAuto = url.searchParams.get('proc_auto');
    const reuAuto = url.searchParams.get('reu_auto');

    let hasToast = false;

    if (ok === '1') {
        showToast('success', 'Guia cadastrada', 'A guia foi cadastrada com sucesso.', 'Você pode localizá-la na lista de guias.');
        hasToast = true;
    }

    if (updated === '1') {
        const resumo = sessionStorage.getItem('guias_update_summary') || 'Guia atualizada com sucesso.';
        const detalhes = sessionStorage.getItem('guias_update_details') || '';
        showToast('success', 'Guia atualizada', resumo, detalhes);
        sessionStorage.removeItem('guias_update_summary');
        sessionStorage.removeItem('guias_update_details');
        hasToast = true;
    }

    if (pontos !== null && pontos !== '') {
        const pontosNum = Number(pontos);
        const label = isNaN(pontosNum) ? String(pontos) : String(pontosNum);
        const plural = label === '1' ? 'ponto' : 'pontos';
        showToast('productivity', 'Produtividade registrada', `+${label} ${plural} no seu registro.`, 'Esse registro foi contabilizado automaticamente.');
        hasToast = true;
    }

    if (procAuto === '1') {
        showToast('info', 'Processo cadastrado', 'O processo foi cadastrado automaticamente.', 'Os dados foram salvos no banco de dados.');
        hasToast = true;
    }

    if (reuAuto === '1') {
        showToast('info', 'Réu cadastrado', 'O réu foi cadastrado automaticamente.', 'Os dados foram salvos no banco de dados.');
        hasToast = true;
    }

    if (hasToast) {
        url.searchParams.delete('ok');
        url.searchParams.delete('updated');
        url.searchParams.delete('prod_pontos');
        url.searchParams.delete('proc_auto');
        url.searchParams.delete('reu_auto');
        window.history.replaceState({}, document.title, url.toString());
    }
}

showToastsFromUrlParams();

// Setar cookie com CPF do usuário logado para o PHP poder buscar guias alocadas
(function() {
    try {
        const raw = sessionStorage.getItem('UserLogin');
        if (raw) {
            const arr = JSON.parse(raw);
            const user = Array.isArray(arr) ? arr[0] : null;
            const cpf = String(user?.cpf || '').trim();
            if (cpf) {
                const hasCookie = document.cookie.split(';').some((c) => c.trim().startsWith('user_cpf_logged='));
                // Setar cookie que expira em 1 hora
                const expires = new Date();
                expires.setTime(expires.getTime() + (60 * 60 * 1000));
                document.cookie = `user_cpf_logged=${cpf}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;

                // Se o cookie não existia, recarrega uma vez para o PHP ler o valor
                if (!hasCookie) {
                    const reloadKey = 'user_cpf_cookie_reload';
                    if (sessionStorage.getItem(reloadKey) !== '1') {
                        sessionStorage.setItem(reloadKey, '1');
                        location.reload();
                    }
                } else {
                    sessionStorage.removeItem('user_cpf_cookie_reload');
                }
            }
        }
    } catch (e) {
        console.error('Erro ao setar cookie do usuário:', e);
    }
})();

// Paginação, filtros e navegação
(function () {
    const perPageSelect = document.querySelector('.list-qtd');
    const pager = document.querySelector('.pagination-buttons');

    function setUrlParams(params) {
        const url = new URL(window.location.href);
        Object.entries(params).forEach(([k, v]) => {
            if (v === null || v === undefined || v === '') {
                url.searchParams.delete(k);
            } else {
                url.searchParams.set(k, String(v));
            }
        });
        window.location.href = url.toString();
    }

    // Ajusta select conforme URL
    if (perPageSelect) {
        const url = new URL(window.location.href);
        const per = url.searchParams.get('per_page');
        if (per) perPageSelect.value = per;

        perPageSelect.addEventListener('change', () => {
            setUrlParams({ per_page: perPageSelect.value, page: 1 });
        });
    }

    // Filters: status and tipo
    const statusSelect = document.querySelector('#filter-status');
    const tipoSelect = document.querySelector('#filter-tipo');

    function submitFilters() {
        const params = {
            page: 1,
            per_page: perPageSelect ? perPageSelect.value : undefined,
            status: statusSelect ? statusSelect.value : undefined,
            tipo: tipoSelect ? tipoSelect.value : undefined
        };
        setUrlParams(params);
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', submitFilters);
    }
    if (tipoSelect) {
        tipoSelect.addEventListener('change', submitFilters);
    }

    // Cliques na paginação
    if (pager) {
        pager.addEventListener('click', (e) => {
            const btn = e.target.closest('.pagination-button');
            if (!btn) return;

            if (btn.classList.contains('disabled')) return;
            const targetPage = btn.getAttribute('data-page');
            if (!targetPage) return;

            const per = pager.getAttribute('data-per-page') || (perPageSelect ? perPageSelect.value : '25');
            setUrlParams({ page: targetPage, per_page: per });
        });
    }

    // Botão nova guia
    const btNovaGuia = document.getElementById('bt-nova-guia');
    if (btNovaGuia) {
        btNovaGuia.addEventListener('click', () => {
            window.location.href = './guias-create-guia.php';
        });
    }
})();
