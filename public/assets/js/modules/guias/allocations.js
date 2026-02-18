// Event listener para remoção de alocação
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-remover-alocacao');
    if (!btn) return;

    if (!podeAlocar) {
        showToast('warning', 'Atenção', 'Apenas a Diretora de Secretaria pode remover alocações.');
        return;
    }

    const row = btn.closest('.row.table-row');
    const idGuia = row?.dataset?.id || '';
    const userIdRemove = btn.dataset?.userIdRemove || '';

    if (!idGuia || !userIdRemove) {
        showToast('warning', 'Atenção', 'Dados insuficientes para remover alocação.');
        return;
    }

    if (!confirm('Deseja realmente remover esta alocação?')) return;

    let userData = null;
    try {
        const raw = sessionStorage.getItem('UserLogin');
        userData = raw ? JSON.parse(raw) : null;
    } catch (err) {
        userData = null;
    }

    const user = Array.isArray(userData) ? userData[0] : null;
    const params = new URLSearchParams({
        id_guia: idGuia,
        user_id_alocar: userIdRemove,
        user_cpf: String(user?.cpf || '').trim(),
        user_nucleo: String(user?.nucleo || '').trim()
    });

    try {
        const resp = await fetch('../v2026/api/guia-desalocar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const text = await resp.text();
        let data = {};
        try { 
            data = JSON.parse(text); 
        } catch (parseErr) {
            console.error('Erro ao processar resposta:', text);
        }

        const ok = resp.ok && (data.ok === true || data.success === true);
        if (ok) {
            showToastAndReload('success', 'Sucesso', 'Alocação removida com sucesso!');
        } else {
            const errorMsg = data.message || 'Erro desconhecido';
            showToast('error', 'Erro ao remover alocação', errorMsg);
        }
    } catch (err) {
        console.error('Erro na requisição:', err);
        showToast('error', 'Falha na comunicação', 'Não foi possível comunicar com o servidor.');
    }
});

// Abrir página de edição da guia
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.proposal-closed-edit');
    if (!btn) return;

    const row = btn.closest('[data-id]');
    const idGuia = row?.dataset?.id || '';

    if (!idGuia) {
        showToast('warning', 'Atenção', 'ID da guia não encontrado.');
        return;
    }

    window.location.href = `./guias-edit-guia.php?id=${encodeURIComponent(idGuia)}`;
});

// Event listener para auto alocação
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-auto-alocacao');
    if (!btn) return;

    if (!ehServidor) {
        showToast('warning', 'Atenção', 'Apenas servidores podem usar auto alocação.');
        return;
    }

    const row = btn.closest('.row.table-row');
    const idGuia = row?.dataset?.id || '';
    if (!idGuia) {
        showToast('warning', 'Atenção', 'ID da guia não encontrado.');
        return;
    }

    if (!confirm('Deseja se auto alocar a esta guia?')) return;

    let userData = null;
    try {
        const raw = sessionStorage.getItem('UserLogin');
        userData = raw ? JSON.parse(raw) : null;
    } catch (err) {
        userData = null;
    }

    const user = Array.isArray(userData) ? userData[0] : null;
    const params = new URLSearchParams({
        id_guia: idGuia,
        user_cpf: String(user?.cpf || '').trim(),
        user_usuario: String(user?.usuario || '').trim(),
        user_setor: String(user?.setor || '').trim(),
        user_nucleo: String(user?.nucleo || '').trim()
    });

    try {
        const resp = await fetch('../v2026/api/guia-auto-alocar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const text = await resp.text();
        let data = {};
        try { data = JSON.parse(text); } catch {}

        const ok = resp.ok && (data.ok === true || data.success === true);
        if (ok) {
            showToastAndReload('success', 'Sucesso', 'Auto alocação realizada com sucesso!');
        } else {
            showToast('error', 'Erro na auto alocação', (data.message || text || resp.statusText || 'Erro desconhecido').slice(0, 300));
        }
    } catch (err) {
        console.error(err);
        showToast('error', 'Falha na comunicação', 'Não foi possível comunicar com o servidor.');
    }
});

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.action-button.error');
    if (!btn) return;

    if (!podeExcluir) {
        showToast('warning', 'Atenção', 'Você não tem permissão para excluir guias.');
        return;
    }

    const row = btn.closest('.row.table-row');
    const idGuia = row?.dataset?.id || '';
    if (!idGuia) {
        showToast('warning', 'Atenção', 'ID da guia não encontrado.');
        return;
    }

    if (!confirm('Deseja realmente excluir esta guia?')) return;

    let userData = null;
    try {
        const raw = sessionStorage.getItem('UserLogin');
        userData = raw ? JSON.parse(raw) : null;
    } catch (err) {
        userData = null;
    }

    const user = Array.isArray(userData) ? userData[0] : null;
    const params = new URLSearchParams({
        id_guia: idGuia,
        user_cpf: String(user?.cpf || '').trim(),
        user_usuario: String(user?.usuario || '').trim(),
        user_setor: String(user?.setor || '').trim(),
        user_nucleo: String(user?.nucleo || '').trim()
    });

    try {
        const resp = await fetch('../v2026/api/guia-delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const text = await resp.text();
        let data = {};
        try { 
            data = JSON.parse(text); 
        } catch (parseErr) {
            console.error('Erro ao processar resposta:', text);
        }

        const ok = resp.ok && (data.ok === true || data.success === true);
        if (ok) {
            showToastAndReload('success', 'Sucesso', 'Guia excluída com sucesso!');
        } else {
            const errorMsg = data.message || 'Erro desconhecido';
            showToast('error', 'Erro ao excluir guia', errorMsg);
        }
    } catch (err) {
        console.error('Erro na requisição:', err);
        showToast('error', 'Falha na comunicação', 'Não foi possível comunicar com o servidor.');
    }
});

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) return '';
    const total = Math.max(0, Math.floor(Number(seconds)));
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const parts = [];
    if (days > 0) parts.push(`${days}d`);
    if (hours > 0 || days > 0) parts.push(`${hours}h`);
    parts.push(`${minutes}m`);
    return parts.join(' ');
}

function formatDateTime(raw) {
    if (!raw) return '';
    const iso = String(raw).replace(' ', 'T');
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return raw;
    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const hh = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${dd}/${mm} às ${hh}:${min}`;
}

function obterUserIdAtual(refEl = null) {
    const direct = refEl?.dataset?.userId || refEl?.closest?.('[data-user-id]')?.dataset?.userId;
    if (direct) return String(direct).trim();

    const inputCpf = document.getElementById('input-user-cpf');
    if (inputCpf && inputCpf.value.trim()) return inputCpf.value.trim();

    try {
        const raw = sessionStorage.getItem('UserLogin');
        const data = raw ? JSON.parse(raw) : null;
        const user = Array.isArray(data) ? data[0] : null;
        const cpf = user?.cpf || user?.CPF || '';
        const usuario = user?.usuario || user?.UserNome || '';
        const userId = user?.UserId || user?.userId || '';
        const resolved = String(cpf || usuario || userId).trim();
        return resolved;
    } catch (err) {
        return '';
    }
}

function statusKey(status) {
    const s = String(status || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    if (s.includes('conclu') || s.includes('feito')) return 'done';

    if (
        s === 'em andamento' ||
        s === 'iniciado' ||
        s === 'aguardando assinatura' ||
        s === 'aguardando juntada' ||
        s === 'aguardando protocolo' ||
        s === 'aguardando liberacao nos autos' ||
        s === 'em revisao'
    ) {
        return 'in-progress';
    }

    return 'pending';
}

function normalizeStatus(status) {
    return String(status || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function isConcluidaStatus(status) {
    const s = normalizeStatus(status);
    return s.includes('conclu') || s.includes('feito');
}

function isAguardandoOuAndamentoStatus(status) {
    const s = normalizeStatus(status);
    return [
        'em andamento',
        'aguardando envio',
        'aguardando assinatura',
        'aguardando juntada',
        'aguardando protocolo',
        'aguardando liberacao nos autos',
        'em revisao'
    ].includes(s);
}

function guiaTipoExigeVerificacaoSEEU(guiaTipo) {
    const t = normalizeStatus(guiaTipo);
    return t.includes('guia de recolhimento');
}

function hasValidDateValue(value) {
    const raw = String(value ?? '').trim();
    if (!raw || raw === '0' || raw.startsWith('0000-00-00')) return false;
    const iso = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const date = new Date(iso);
    return !Number.isNaN(date.getTime());
}

function hasPositiveNumericValue(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return false;
    const normalized = raw.replace(',', '.');
    const num = Number(normalized);
    return Number.isFinite(num) && num > 0;
}

function parseNumericValue(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const normalized = raw.replace(',', '.');
    const num = Number(normalized);
    return Number.isFinite(num) ? num : null;
}

function formatCurrencyBRL(value) {
    const num = parseNumericValue(value);
    if (num === null) return null;
    return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function tituloExpedirGuiaPorTipo(item) {
    const tipo = String(item?.guia_tipo || item?.guiaTipo || '').trim();
    if (!tipo) return 'Expedir Guia de Recolhimento';
    return `Expedir ${tipo}`;
}

function tituloSubtarefaTimeline(item) {
    const codigo = String(item?.codigo || '').trim();
    if (codigo === 'expedir-guia') return tituloExpedirGuiaPorTipo(item);
    const map = {
        'calcular-pena-multa': 'Cálculo da pena de multa',
        'assinar-guia': 'Assinar Guia',
        'gerar-pdf': 'Gerar PDF da Guia',
        'enviar-documentos': 'Enviar Documentos',
        'juntar-termo-envio': 'Juntar Termo de Envio',
        'expedir-certidao': 'Expedir Certidão de Cumprimento'
    };
    return map[codigo] || item?.titulo || 'Subtarefa';
}

function textoCertidaoPadrao(item) {
    const tipoGuia = String(item?.guiaTipo || item?.guia_tipo || '[tipodaguia]').trim() || '[tipodaguia]';
    const dataFato = String(item?.dataFato || item?.data_fato || '[datadofato]').trim() || '[datadofato]';
    const valorTotal = String(item?.valorMulta || item?.valor_multa || '[valortotal]').trim() || '[valortotal]';
    const dataEnvioRaw = String(item?.dataEnvio || item?.data_envio || '').trim();
    const dataEnvioObj = dataEnvioRaw ? new Date(String(dataEnvioRaw).replace(' ', 'T')) : null;
    const dataEnvio = dataEnvioObj && !Number.isNaN(dataEnvioObj.getTime())
        ? `${String(dataEnvioObj.getDate()).padStart(2, '0')}/${String(dataEnvioObj.getMonth() + 1).padStart(2, '0')}/${dataEnvioObj.getFullYear()}`
        : '[data]';
    const horaEnvio = dataEnvioObj && !Number.isNaN(dataEnvioObj.getTime())
        ? `${String(dataEnvioObj.getHours()).padStart(2, '0')}:${String(dataEnvioObj.getMinutes()).padStart(2, '0')}`
        : '[hora]';
    const qtdDocs = String(item?.qtdDocs || item?.qtdDocumentos || item?.qtd_documentos || '[qtd-docs]').trim() || '[qtd-docs]';
    const destino = String(item?.destino || item?.destinoGuia || item?.destino_envio || '[destino]').trim() || '[destino]';
    const protocolo = String(item?.protocoloJuntada || item?.protocolo_juntada || item?.protocoloEnvio || item?.protocolo_envio || '[protocolo]').trim() || '[protocolo]';

    return `Certifico para os devidos fins que foi expedida ${tipoGuia}, foi calculada a pena de multa baseada no salário mínimo vigente à época do fato (${dataFato}), resultando em R$ ${valorTotal}. Em ${dataEnvio}, às ${horaEnvio} foi enviada a referida guia juntamente com ${qtdDocs} documentos anexos, ao ${destino} via Malote Digital, e juntado aos autos o comprovante de envio, conforme documento de ID ${protocolo}.`;
}

function aplicarRegraVisibilidadeSubtarefas(container) {
    if (!container) return;

    const items = Array.from(container.querySelectorAll('.subtask-item'));
    if (!items.length) return;

    const expedir = items.find((i) => (i.dataset.codigo || '') === 'expedir-guia');
    const calculo = items.find((i) => (i.dataset.codigo || '') === 'calcular-pena-multa');
    const expedirConcluida = expedir ? isConcluidaStatus(expedir.dataset.status) : false;
    const calculoSempreVisivel = Boolean(calculo) && !expedirConcluida;

    const emAndamento = items.find((i) => isAguardandoOuAndamentoStatus(i.dataset.status));
    const pendente = items.find((i) => normalizeStatus(i.dataset.status) === 'a fazer');
    const principal = emAndamento || pendente || items[0];

    items.forEach((item) => {
        const isPrincipal = item === principal;
        const isCalculoFixo = calculoSempreVisivel && item === calculo;
        const deveExibir = isPrincipal || isCalculoFixo;
        item.classList.toggle('subtask-hidden-by-rule', !deveExibir);
    });
}

function getNextStatusForSubtask(codigo, currentStatus) {
    const normalized = normalizeStatus(currentStatus);

    switch (codigo) {
        case 'calcular-pena-multa':
            return normalized.includes('conclu') ? 'Concluída' : 'Concluída';
        case 'expedir-guia':
            if (normalized === 'a fazer') return 'Em andamento';
            if (normalized === 'em andamento') return 'Concluída';
            if (normalized === 'refazer') return 'Em andamento';
            return 'Em andamento';
        case 'assinar-guia':
            if (normalized === 'a fazer') return 'Aguardando assinatura';
            if (normalized === 'aguardando assinatura') return 'Concluída';
            if (normalized.includes('conclu')) return 'Concluída';
            return 'Aguardando assinatura';
        case 'gerar-pdf':
            if (normalized === 'a fazer') return 'Concluído';
            if (normalized.includes('conclu')) return 'Concluído';
            return 'Concluído';
        case 'enviar-documentos':
            if (normalized === 'aguardando envio' || normalized === 'a fazer') return 'Concluído';
            if (normalized.includes('conclu')) return 'Concluído';
            return 'Concluído';
        case 'juntar-termo-envio':
            if (normalized === 'aguardando juntada') return 'Aguardando protocolo';
            if (normalized === 'aguardando protocolo') return 'Concluída';
            if (normalized === 'aguardando liberacao nos autos') return 'Concluída';
            if (normalized.includes('conclu')) return 'Concluída';
            return 'Aguardando juntada';
        case 'expedir-certidao':
            if (normalized === 'a fazer') return 'Em andamento';
            if (normalized === 'em andamento') return 'Concluída';
            if (normalized.includes('conclu')) return 'Concluída';
            return 'Em andamento';
        default:
            if (normalized === 'a fazer') return 'Em andamento';
            if (normalized === 'em andamento') return 'Concluída';
            if (normalized === 'aguardando assinatura') return 'Em andamento';
            if (normalized === 'aguardando envio') return 'Em andamento';
            if (normalized === 'aguardando juntada') return 'Em andamento';
            if (normalized === 'aguardando protocolo') return 'Em andamento';
            if (normalized === 'aguardando liberacao nos autos') return 'Em andamento';
            if (normalized === 'refazer') return 'Em andamento';
            return 'Em andamento';
    }
}

function atualizarTituloSubtarefa(item) {
    if (!item) return;

    const name = item.querySelector('.subtask-name');
    if (!name) return;

    const status = item.dataset.status || '';
    const codigo = item.dataset.codigo || '';
    const tituloBase = item.dataset.titulo || 'Subtarefa';

    if (status === 'Concluída' || status === 'Concluído') {
        const nomesConcluidos = {
            'calcular-pena-multa': 'Cálculo concluído',
            'expedir-guia': 'Guia expedida',
            'assinar-guia': 'Guia assinada',
            'gerar-pdf': 'PDFs gerados',
            'enviar-documentos': 'Documentos enviados',
            'juntar-termo-envio': 'Termo de envio juntado',
            'expedir-certidao': 'Certidão expedida'
        };
        name.textContent = nomesConcluidos[codigo] || tituloBase;
    } else {
        if (codigo === 'expedir-guia') {
            name.textContent = tituloExpedirGuiaPorTipo({ guia_tipo: item.dataset.guiaTipo });
        } else {
            name.textContent = tituloBase;
        }
    }
}

/**
 * Atualiza a barra de progresso segmentada das subtarefas na linha da guia
 * Cada segmento representa uma subtarefa e sua cor reflete o status
 */
function atualizarBarraProgressoSubtarefas(row) {
    if (!row) return;
    
    const idGuia = row.dataset.id;
    if (!idGuia) return;
    
    const subtasksContainer = row.querySelector('[data-subtasks]');
    if (!subtasksContainer) return;
    
    const subtaskItems = subtasksContainer.querySelectorAll('.subtask-item');
    if (!subtaskItems.length) return;
    
    // Atualizar barra de progresso genérica (se existir)
    let progressContainer = row.querySelector('.proposal-progress');
    if (!progressContainer) {
        const detailsArea = row.querySelector('.proposal-details-left, .proposal-left');
        if (detailsArea) {
            progressContainer = document.createElement('div');
            progressContainer.className = 'progress-bar proposal-progress';
            detailsArea.appendChild(progressContainer);
        }
    }
    
    if (progressContainer) {
        progressContainer.innerHTML = '';
        
        subtaskItems.forEach((item) => {
            const segment = document.createElement('div');
            segment.className = 'progress-segment';
            segment.style.width = `${(100 / subtaskItems.length).toFixed(2)}%`;
            
            const status = item.dataset.status || 'A fazer';
            const key = statusKey(status);
            segment.classList.add(key);
            
            progressContainer.appendChild(segment);
        });
    }
    
    // Atualizar barra de progresso do status-with-subtask (Model 2 - closed-row)
    const statusBar = row.querySelector('.status-with-subtask.model-bar');
    if (statusBar) {
        // Contar items por status
        const isConcluida = (status) => {
            const s = String(status || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            return s.includes('conclu') || s.includes('feito');
        };
        
        const isEmAndamento = (status) => {
            const s = String(status || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            return (
                s.includes('andamento') ||
                s.includes('progresso') ||
                s.includes('aguardando') ||
                s.includes('revisao')
            );
        };
        
        const done = Array.from(subtaskItems).filter(item => isConcluida(item.dataset.status)).length;
        const inProgress = Array.from(subtaskItems).filter(item => isEmAndamento(item.dataset.status) && !isConcluida(item.dataset.status)).length;
        const total = subtaskItems.length;
        
        const donePct = total > 0 ? (done * 100 / total).toFixed(0) : 0;
        const inProgressPct = total > 0 ? (inProgress * 100 / total).toFixed(0) : 0;
        
        statusBar.style.setProperty('--progress-done', `${donePct}%`);
        statusBar.style.setProperty('--progress-in-progress', `${inProgressPct}%`);
        statusBar.style.setProperty('--progress-total', `${Math.max(1, total)}`);
    }
}

const SUBTASK_CATALOG = [
    { codigo: 'calcular-pena-multa', titulo: 'Cálculo da pena de multa' },
    { codigo: 'expedir-guia', titulo: 'Expedir Guia' },
    { codigo: 'assinar-guia', titulo: 'Assinar Guia' },
    { codigo: 'gerar-pdf', titulo: 'Gerar PDF da documentação' },
    { codigo: 'enviar-documentos', titulo: 'Enviar documentos' },
    { codigo: 'juntar-termo-envio', titulo: 'Juntar termo de envio' },
    { codigo: 'expedir-certidao', titulo: 'Expedir certidão' }
];

const SUBTASK_CATALOG_SET = new Set(SUBTASK_CATALOG.map((item) => item.codigo));

function mergeSubtaskCatalog(items) {
    const list = Array.isArray(items) ? items : [];
    if (SUBTASK_CATALOG.length === 0) return list.slice();

    const byCode = new Map();
    list.forEach((item) => {
        const code = String(item?.codigo || '').trim();
        if (!code) return;
        if (!byCode.has(code)) {
            byCode.set(code, item);
        }
    });

    const base = list.find((item) => item && (item.guia_tipo || item.processo || item.denunciado || item.regime)) || {};
    const baseInfo = {
        guia_tipo: base.guia_tipo || '',
        processo: base.processo || '',
        denunciado: base.denunciado || '',
        data_fato: base.data_fato || '',
        dias_multa: base.dias_multa ?? null,
        regime: base.regime || '',
        reu_cpf: base.reu_cpf || '',
        processo_classe: base.processo_classe || ''
    };

    const merged = SUBTASK_CATALOG.map((catalogItem) => {
        const existing = byCode.get(catalogItem.codigo);
        if (existing) return existing;
        return {
            id: '',
            codigo: catalogItem.codigo,
            titulo: catalogItem.titulo,
            status: 'Não cadastrada',
            created_at: '',
            started_at: '',
            completed_at: '',
            is_placeholder: true,
            ...baseInfo
        };
    });

    const extras = list.filter((item) => {
        const code = String(item?.codigo || '').trim();
        return code && !SUBTASK_CATALOG_SET.has(code);
    });

    return merged.concat(extras);
}

function ordenarSubtarefasPorCatalogo(items) {
    if (!Array.isArray(items) || items.length === 0) return items.slice();

    const catalogOrder = SUBTASK_CATALOG.map((entry) => entry.codigo);
    const buckets = new Map();
    const extras = [];

    items.forEach((item) => {
        const codigo = String(item?.codigo || item?.codigo || '').trim();
        if (catalogOrder.includes(codigo)) {
            if (!buckets.has(codigo)) buckets.set(codigo, []);
            buckets.get(codigo).push(item);
        } else {
            extras.push(item);
        }
    });

    const ordered = [];
    catalogOrder.forEach((codigo) => {
        if (buckets.has(codigo)) {
            ordered.push(...buckets.get(codigo));
        }
    });

    ordered.push(...extras);
    return ordered;
}

function applySubtaskDataset(target, item) {
    if (!target || !item) return;

    target.dataset.titulo = item.titulo || 'Subtarefa';
    target.dataset.id = item.id || '';
    target.dataset.codigo = item.codigo || '';
    target.dataset.status = item.status || '';
    target.dataset.created = item.created_at || '';
    target.dataset.started = item.started_at || '';
    target.dataset.completed = item.completed_at || '';
    target.dataset.completedBy = item.completed_by || '';
    target.dataset.avgSeconds = item.avg_seconds_90d || '0';
    target.dataset.durationSeconds = item.duration_seconds !== null ? item.duration_seconds : '';

    if (item.responsavel) target.dataset.responsavel = item.responsavel;
    if (item.prazo_dias) target.dataset.prazoDias = item.prazo_dias;
    if (item.data_prazo) target.dataset.dataPrazo = item.data_prazo;
    if (item.valor_multa !== null && item.valor_multa !== undefined) target.dataset.valorMulta = String(item.valor_multa);
    if (item.data_fato !== null && item.data_fato !== undefined) target.dataset.dataFato = String(item.data_fato);
    if (item.valor_unitario !== null && item.valor_unitario !== undefined) target.dataset.valorUnitario = String(item.valor_unitario);
    if (item.salario_minimo_vigente !== null && item.salario_minimo_vigente !== undefined) target.dataset.salarioMinimo = String(item.salario_minimo_vigente);
    if (item.base_calculo_dias !== null && item.base_calculo_dias !== undefined) target.dataset.baseCalculoDias = String(item.base_calculo_dias);
    if (item.checkbox_reu_provisoria) target.dataset.reuProvisoria = '1';
    if (item.verificacao_seeu) target.dataset.verificacaoSeeu = item.verificacao_seeu;
    if (item.destino_guia) target.dataset.destinoGuia = item.destino_guia;
    if (item.destino_envio) target.dataset.destino = item.destino_envio;
    if (item.destino) target.dataset.destino = item.destino;
    if (item.motivo_refazer) target.dataset.motivoRefazer = item.motivo_refazer;
    if (item.motivo) target.dataset.motivoRefazer = item.motivo;
    if (item.motivo_erro) target.dataset.motivoRefazer = item.motivo_erro;
    if (item.data_assinatura) target.dataset.dataAssinatura = item.data_assinatura;
    if (item.qtd_documentos) {
        target.dataset.qtdDocumentos = item.qtd_documentos;
        target.dataset.qtdDocs = item.qtd_documentos;
    }
    if (item.path_storage) target.dataset.pathStorage = item.path_storage;
    if (item.status_geracao) target.dataset.statusGeracao = item.status_geracao;
    if (item.protocolo_envio) target.dataset.protocoloEnvio = item.protocolo_envio;
    if (item.protocolo_juntada) target.dataset.protocoloJuntada = item.protocolo_juntada;
    if (item.data_envio) target.dataset.dataEnvio = item.data_envio;
    if (item.data_juntada) target.dataset.dataJuntada = item.data_juntada;
    if (item.estado_liberacao) target.dataset.estadoLiberacao = item.estado_liberacao;
    if (item.texto_certidao) target.dataset.textoCertidao = item.texto_certidao;

    if (item.guia_tipo) target.dataset.guiaTipo = item.guia_tipo;
    if (item.processo) target.dataset.processo = item.processo;
    if (item.denunciado) {
        target.dataset.denunciado = item.denunciado;
        target.dataset.reuNome = item.denunciado;
    }
    if (item.reu_cpf) target.dataset.reuCpf = item.reu_cpf;
    if (item.processo_classe) target.dataset.processoClasse = item.processo_classe;
    if (item.regime) target.dataset.regime = item.regime;
    if (item.dias_multa !== null && item.dias_multa !== undefined) target.dataset.diasMulta = String(item.dias_multa);
}

function renderSubtasks(container, items) {
    const readOnly = container?.dataset?.subtasksReadonly === '1';
    const list = document.createElement('div');
    list.className = readOnly ? 'timeline' : 'subtask-list';
    list.setAttribute('data-live-timeline', '');
    if (readOnly) list.dataset.readonly = '1';

    if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'subtask-empty';
        empty.textContent = 'Nenhuma subtarefa cadastrada.';
        list.appendChild(empty);
        container.replaceChildren(list);
        return;
    }

    const orderedItems = ordenarSubtarefasPorCatalogo(items);
    orderedItems.forEach((item) => {
        if (readOnly) {
            const timelineItem = document.createElement('div');
            timelineItem.className = 'timeline-item';

            const key = statusKey(item.status);
            const isPlaceholder = Boolean(item.is_placeholder) || String(item.id || '').trim() === '';
            applySubtaskDataset(timelineItem, item);
            if (isPlaceholder) {
                timelineItem.dataset.placeholder = '1';
            }

            const dot = document.createElement('span');
            dot.className = `timeline-dot ${key}`;
            if (isPlaceholder) {
                dot.classList.add('inactive');
            }

            const subtask = document.createElement('div');
            subtask.className = `subtask-container${isPlaceholder ? ' subtask-container--empty' : ''}`;

            const header = document.createElement('div');
            header.className = 'subtask-header';

            const title = document.createElement('div');
            title.className = 'subtask-title';
            title.textContent = tituloSubtarefaTimeline(item);

            const actions = document.createElement('div');
            actions.className = 'subtask-header-actions';

            const badge = document.createElement('div');
            badge.className = 'subtask-status-badge';
            if (key === 'done') badge.classList.add('done');
            if (key === 'pending') badge.classList.add('pending');
            const statusRaw = item.status || '';
            const statusNorm = normalizeStatus(statusRaw || 'A fazer');
            const badgeLabel = isPlaceholder
                ? 'Não cadastrada'
                : (isConcluidaStatus(statusRaw) ? 'Concluída' : (statusNorm === 'a fazer' ? 'Pendente' : 'Em andamento'));
            badge.textContent = badgeLabel;

            actions.appendChild(badge);

            const isConcluida = isConcluidaStatus(statusRaw);
            const isEmAndamento = statusNorm === 'em andamento' || statusNorm.startsWith('aguardando');
            const actionClass = isConcluida ? 'status-success' : (isEmAndamento ? 'status-alert' : 'status-info');

            if (isPlaceholder) {
                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = `subtask-btn ${actionClass}`;
                addBtn.title = 'Adicionar subtarefa';
                addBtn.setAttribute('aria-label', 'Adicionar subtarefa');
                addBtn.dataset.subtarefaAction = 'create-subtask';
                addBtn.dataset.subtarefaCodigo = item.codigo || '';
                addBtn.innerHTML = '<i class="fi fi-rr-plus"></i>';
                actions.appendChild(addBtn);
            } else if (isConcluida || isEmAndamento) {
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = `subtask-btn ${actionClass}`;
                deleteBtn.title = 'Excluir subtarefa';
                deleteBtn.setAttribute('aria-label', 'Excluir subtarefa');
                deleteBtn.dataset.subtarefaAction = 'delete-subtask';
                deleteBtn.dataset.subtarefaId = item.id || '';
                deleteBtn.innerHTML = '<i class="fi fi-rr-trash"></i>';

                const resetBtn = document.createElement('button');
                resetBtn.type = 'button';
                resetBtn.className = `subtask-btn ${actionClass}`;
                resetBtn.title = 'Reiniciar subtarefa';
                resetBtn.setAttribute('aria-label', 'Reiniciar subtarefa');
                resetBtn.dataset.subtarefaAction = 'reset-subtask';
                resetBtn.dataset.subtarefaId = item.id || '';
                resetBtn.innerHTML = '<i class="fi fi-rr-refresh"></i>';

                actions.appendChild(deleteBtn);
                actions.appendChild(resetBtn);
            } else {
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = `subtask-btn ${actionClass}`;
                deleteBtn.title = 'Excluir subtarefa';
                deleteBtn.setAttribute('aria-label', 'Excluir subtarefa');
                deleteBtn.dataset.subtarefaAction = 'delete-subtask';
                deleteBtn.dataset.subtarefaId = item.id || '';
                deleteBtn.innerHTML = '<i class="fi fi-rr-trash"></i>';
                actions.appendChild(deleteBtn);
            }
            header.appendChild(title);
            header.appendChild(actions);

            const body = document.createElement('div');
            body.className = 'subtask-body';

            const detailsContainer = document.createElement('div');
            detailsContainer.setAttribute('data-role', 'specific-details');
            body.appendChild(detailsContainer);

            subtask.appendChild(header);
            subtask.appendChild(body);

            timelineItem.appendChild(dot);
            timelineItem.appendChild(subtask);

            list.appendChild(timelineItem);
            return;
        }

        const key = statusKey(item.status);
        const subtask = document.createElement('div');
        subtask.className = `subtask-item subtask-container${item.is_delayed ? ' delay' : ''}`;
        
        // Adicionar dados ao elemento para updateTimelineMetrics processar
        applySubtaskDataset(subtask, item);

        const dot = document.createElement('span');
        dot.className = `subtask-dot ${key}`;

        const meta = document.createElement('div');
        meta.className = 'subtask-meta';

        const name = document.createElement('span');
        name.className = 'subtask-name subtask-title';
        
        // Mudar nome da subtarefa quando concluída
        const status = item.status || '';
        const codigo = item.codigo || '';
        let nomeExibir = item.titulo || 'Subtarefa';
        
        if (codigo === 'expedir-guia') {
            nomeExibir = tituloExpedirGuiaPorTipo(item);
        }

        if (status === 'Concluída' || status === 'Concluído') {
            const nomesConcluidos = {
                'calcular-pena-multa': 'Cálculo concluído',
                'expedir-guia': 'Guia expedida',
                'assinar-guia': 'Guia assinada',
                'gerar-pdf': 'PDFs gerados',
                'enviar-documentos': 'Documentos enviados',
                'juntar-termo-envio': 'Termo de envio juntado',
                'expedir-certidao': 'Certidão expedida'
            };
            nomeExibir = nomesConcluidos[codigo] || nomeExibir;
        }
        
        name.textContent = nomeExibir;

        const extra = document.createElement('span');
        extra.className = 'subtask-extra mono';
        extra.dataset.role = 'fallback';
        extra.style.display = 'none';
        extra.textContent = '';

        const header = document.createElement('div');
        header.className = 'subtask-card-header subtask-header';

        const statusBadge = document.createElement('span');
        statusBadge.className = 'subtask-status-badge subtask-status-badge-dynamic';
        if (key === 'done') statusBadge.classList.add('done');
        if (key === 'pending') statusBadge.classList.add('pending');
        statusBadge.textContent = item.status || 'A fazer';

        header.appendChild(name);
        header.appendChild(statusBadge);
        
        // Container para detalhes específicos (será preenchido por updateTimelineMetrics)
        const detailsContainer = document.createElement('div');
        detailsContainer.setAttribute('data-role', 'specific-details');
        meta.appendChild(detailsContainer);
        
        meta.appendChild(extra);

        const actions = document.createElement('div');
        actions.className = 'subtask-actions subtask-button-group';

        const body = document.createElement('div');
        body.className = 'subtask-card-body subtask-body';

        const footer = document.createElement('div');
        footer.className = 'subtask-card-footer subtask-button-group';

        const statusBtn = document.createElement('button');
        statusBtn.type = 'button';
        statusBtn.className = `subtask-status-btn ${key}`;
        statusBtn.textContent = item.status || 'A fazer';
        statusBtn.dataset.subtarefaAction = 'update-status';
        statusBtn.dataset.subtarefaId = item.id || '';
        statusBtn.dataset.currentStatus = item.status || '';
        
        const isConcluidaRender = item.status === 'Concluída' || item.status === 'Concluído';
        const isCalculo = (item.codigo || '') === 'calcular-pena-multa';
        const isPlaceholder = Boolean(item.is_placeholder) || String(item.id || '').trim() === '';

        if (isPlaceholder) {
            subtask.dataset.placeholder = '1';
            statusBadge.textContent = item.status || 'Não cadastrada';
            statusBtn.textContent = readOnly ? 'Não cadastrada' : 'Criar subtarefa';
            if (!readOnly) {
                statusBtn.classList.add('action-purple');
                statusBtn.dataset.subtarefaAction = 'create-subtask';
                statusBtn.dataset.subtarefaCodigo = item.codigo || '';
                statusBtn.dataset.subtarefaId = '';
                statusBtn.dataset.currentStatus = item.status || 'Não cadastrada';
            } else {
                statusBtn.disabled = true;
            }

            const placeholderNote = document.createElement('span');
            placeholderNote.className = 'subtask-extra';
            placeholderNote.textContent = 'Subtarefa ainda não adicionada à linha do tempo.';
            detailsContainer.appendChild(placeholderNote);

            if (!readOnly) actions.appendChild(statusBtn);
            body.appendChild(meta);
            if (!readOnly) footer.appendChild(actions);

            subtask.appendChild(dot);
            subtask.appendChild(header);
            subtask.appendChild(body);
            if (!readOnly) subtask.appendChild(footer);

            list.appendChild(subtask);
            return;
        }

        // Ocultar subtarefa de cálculo concluída sem valores calculados
        const semCalculoConcluido = (
            isCalculo &&
            isConcluidaRender &&
            !hasPositiveNumericValue(item.valor_multa || item.valorMulta) &&
            !hasPositiveNumericValue(item.valor_unitario || item.valorUnitario)
        );
        if (semCalculoConcluido) {
            return; // não renderiza esta subtarefa
        }

        // Desabilitar botão se tarefa estiver concluída
        if (isConcluidaRender) {
            statusBtn.disabled = true;
            statusBtn.style.cursor = 'not-allowed';
            statusBtn.style.opacity = '0.6';
        } else {
            const statusNorm = normalizeStatus(item.status);
            if (codigo === 'calcular-pena-multa') {
                const temDadosCalculo = hasValidDateValue(item.data_fato || item.dataFato) && hasPositiveNumericValue(item.valor_multa || item.valorMulta);
                statusBtn.textContent = temDadosCalculo ? 'Confirmar cálculo' : 'Calcular';
                statusBtn.classList.add('action-success');

                if (!temDadosCalculo) {
                    const cancelarBtn = document.createElement('button');
                    cancelarBtn.type = 'button';
                    cancelarBtn.className = 'subtask-aux-btn';
                    cancelarBtn.textContent = 'Sem pena de multa';
                    cancelarBtn.dataset.subtarefaAction = 'cancelar-multa';
                    cancelarBtn.dataset.subtarefaId = item.id || '';
                    actions.appendChild(cancelarBtn);
                }
            } else if (codigo === 'expedir-guia') {
                statusBtn.textContent = 'Expedir guia';
                statusBtn.classList.add('action-success');
            } else if (codigo === 'assinar-guia' && statusNorm === 'aguardando assinatura') {
                statusBtn.textContent = 'Guia assinada';
                statusBtn.classList.add('action-success');
            } else if (codigo === 'gerar-pdf') {
                statusBtn.textContent = 'Gerar PDF agora';
                statusBtn.classList.add('action-success');
            } else if (codigo === 'enviar-documentos') {
                statusBtn.textContent = 'Enviar agora';
                statusBtn.classList.add('action-success');
            } else if (codigo === 'juntar-termo-envio') {
                if (statusNorm === 'aguardando juntada') {
                    statusBtn.textContent = 'Termo juntado';
                    statusBtn.classList.add('action-purple');
                } else if (statusNorm === 'aguardando protocolo' || statusNorm === 'aguardando liberacao nos autos') {
                    statusBtn.textContent = 'Confirmar protocolo';
                    statusBtn.classList.add('action-success');
                }
            } else if (codigo === 'expedir-certidao') {
                statusBtn.textContent = 'Certidão expedida';
                statusBtn.classList.add('action-success');
            }
        }

        if (!readOnly) actions.appendChild(statusBtn);

        if (item.is_delayed) {
            const warning = document.createElement('span');
            warning.className = 'subtask-warning';
            warning.textContent = 'Atraso acima da média';
            actions.appendChild(warning);
        }

        body.appendChild(meta);

        if (!readOnly && !isConcluidaRender) {
            footer.appendChild(actions);
        }

        subtask.appendChild(dot);
        subtask.appendChild(header);
        subtask.appendChild(body);
        if (!readOnly && !isConcluidaRender) subtask.appendChild(footer);

        list.appendChild(subtask);
    });

    container.replaceChildren(list);

    const row = container.closest('.row.table-row[data-id]');
    if (row) {
        aplicarRegraVisibilidadeSubtarefas(list);
    }
    
    // Chamar updateTimelineMetrics para preencher os detalhes específicos
    if (typeof updateTimelineMetrics === 'function') {
        setTimeout(() => updateTimelineMetrics(), 150);
    }
    
    // Atualizar barra de progresso segmentada
    if (row) {
        setTimeout(() => atualizarBarraProgressoSubtarefas(row), 200);
    }
}

async function carregarSubtarefas(row) {
    const container = row.querySelector('[data-subtasks]');
    if (!container) return;
    if (container.dataset.loaded === '1') return;

    container.dataset.loaded = '1';
    container.innerHTML = '<div class="subtask-list"><div class="subtask-loading">Carregando subtarefas...</div></div>';

    const idGuia = row.dataset.id || '';
    if (!idGuia) return;

    try {
        const resp = await fetch(`../v2026/api/guia-subtarefas.php?id_guia=${encodeURIComponent(idGuia)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const text = await resp.text();
        
        let data = {};
        try { 
            data = JSON.parse(text); 
        } catch (parseErr) {
            console.error('Erro ao fazer parse do JSON:', parseErr);
            throw new Error('Resposta não é um JSON válido');
        }

        if (!resp.ok) {
            throw new Error(`Erro HTTP ${resp.status}: ${data.message || 'Falha na requisição'}`);
        }
        
        if (data.ok !== true) {
            throw new Error(data.message || 'API retornou ok=false');
        }

        const rawItems = Array.isArray(data.items) ? data.items : [];
        const isTableRow = row.classList.contains('row') && row.classList.contains('table-row');
        const mergedItems = isTableRow ? rawItems : mergeSubtaskCatalog(rawItems);
        renderSubtasks(container, mergedItems);
        const resumo = obterResumoPaiPorSubtarefas(row);
        aplicarResumoPai(row, resumo);
    } catch (err) {
        console.error('❌ Erro ao carregar subtarefas:', err);
        container.dataset.loaded = '0';
        container.innerHTML = '<div class="subtask-list"><div class="subtask-error">Não foi possível carregar as subtarefas.</div></div>';
    }
}

 (function configurarCheckboxSelecaoUnica() {
     const getCheckboxes = () => Array.from(document.querySelectorAll('.row.table-row[data-id]:not(.proposal-parent-completed) input.check'));

    const atualizaEstados = () => {
        const checkboxes = getCheckboxes();
        const checked = checkboxes.find((cb) => cb.checked) || null;

        checkboxes.forEach((checkbox) => {
            const row = checkbox.closest('.row.table-row[data-id]');
            if (!row) return;

            if (row.classList.contains('proposal-parent-completed')) {
                checkbox.checked = false;
                checkbox.disabled = true;
                return;
            }

            const locked = Boolean(checked) && checkbox !== checked;
            row.classList.toggle('table-row--locked', locked);
            row.classList.toggle('table-row--active', checkbox === checked);
            checkbox.disabled = locked;
        });
    };

    document.addEventListener('change', (event) => {
        const checkbox = event.target instanceof HTMLInputElement && event.target.classList.contains('check')
            ? event.target
            : null;
        if (!checkbox) return;

        if (checkbox.checked) {
            getCheckboxes().forEach((otherCheckbox) => {
                if (otherCheckbox === checkbox) return;
                otherCheckbox.checked = false;
            });

            const row = checkbox.closest('.row.table-row[data-id]');
            if (row) carregarSubtarefas(row);
        }

        atualizaEstados();
    });

    atualizaEstados();
})();

// ============================================================================
// CONFIGURAÇÃO DO TOGGLE BUTTON DA CLOSED-ROW
// ============================================================================

(function configurarToggleClosedRow() {
    const getClosedRowsWithId = () => {
        const rows = [];
        document.querySelectorAll('.row.table-row[data-id]').forEach(rowContainer => {
            const closedRow = rowContainer.querySelector('.proposal-closed-row');
            if (closedRow) {
                rows.push({ container: rowContainer, closedRow: closedRow });
            }
        });
        return rows;
    };

    const atualizaEstadosToggle = () => {
        const rows = getClosedRowsWithId();
        const expandedRow = rows.find(r => r.container.classList.contains('table-row--active')) || null;

        rows.forEach(({ container, closedRow }) => {
            const isCompleted = container.classList.contains('proposal-parent-completed');

            const toggle = closedRow.querySelector('.proposal-closed-toggle');
            if (toggle) {
                toggle.classList.toggle('expanded', !isCompleted && container.classList.contains('table-row--active'));
            }

            const locked = Boolean(expandedRow) && container !== expandedRow.container;
            container.classList.toggle('table-row--locked', locked);
            closedRow.classList.toggle('is-locked', locked);
        });
    };

    // Event listener para click no toggle button
    document.addEventListener('click', (event) => {
        const toggleButton = event.target.closest('.proposal-closed-toggle');
        if (!toggleButton) return;

        const closedRow = toggleButton.closest('.proposal-closed-row');
        if (!closedRow) return;

        const container = closedRow.closest('.row.table-row[data-id]');
        if (!container || container.closest('.proposal-parent-completed')) {
            return;
        }

        const isExpanded = container.classList.contains('table-row--active');

        if (!isExpanded) {
            // Fechar todas as outras linhas
            getClosedRowsWithId().forEach(({ container: otherContainer }) => {
                if (otherContainer !== container && !otherContainer.closest('.proposal-parent-completed')) {
                    otherContainer.classList.remove('table-row--active');
                }
            });

            // Abrir esta linha e carregar subtarefas
            container.classList.add('table-row--active');
            carregarSubtarefas(container);
        } else {
            // Fechar esta linha
            container.classList.remove('table-row--active');
        }

        atualizaEstadosToggle();
    });

    // Inicializar estados
    atualizaEstadosToggle();
})();

// ============================================================================
// FUNÇÕES DE TIMELINE E INTERAÇÃO COM SUBTAREFAS
// ============================================================================

/** Formata segundos em formato legível: "Xd Xh Xm" ou "Xm" ou "Xs" */
function formatDuration(seconds) {
    const totalSeconds = Math.max(0, Math.floor(seconds));
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);

    const parts = [];
    if (days > 0) parts.push(`${days}d`);
    if (hours > 0) parts.push(`${hours}h`);
    if (minutes > 0) parts.push(`${minutes}m`);

    return parts.length > 0 ? parts.join(' ') : '0s';
}

/** Formata data ISO para formato legível */
function formatDateTime(dateString, includeSeconds) {
    if (!dateString || dateString === '') return '';

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const mins = String(date.getMinutes()).padStart(2, '0');
        const secs = String(date.getSeconds()).padStart(2, '0');

        if (includeSeconds) {
            return `${day}/${month}/${year} às ${hours}:${mins}:${secs}`;
        }
        return `${day}/${month}/${year} às ${hours}:${mins}`;
    } catch (err) {
        console.error('Erro ao formatar data:', dateString, err);
        return dateString;
    }
}

function getActionLabelForSubtask(item) {
    if (!item) return item?.dataset?.status || 'A fazer';

    const codigo = item.dataset.codigo || '';
    const statusRaw = item.dataset.status || 'A fazer';
    const status = normalizeStatus(statusRaw);
    const concluida = isConcluidaStatus(statusRaw);

    if (concluida) {
        return statusRaw === 'Aguardando liberação nos autos' ? 'Aguardando liberação' : statusRaw;
    }

    if (codigo === 'calcular-pena-multa') {
        const temDadosCalculo = hasValidDateValue(item.dataset.dataFato) && hasPositiveNumericValue(item.dataset.valorMulta);
        return temDadosCalculo ? 'Confirmar cálculo' : 'Calcular';
    }
    if (codigo === 'expedir-guia') return 'Expedir guia';
    if (codigo === 'assinar-guia' && status === 'aguardando assinatura') return 'Guia assinada';
    if (codigo === 'gerar-pdf') return 'Gerar PDF agora';
    if (codigo === 'enviar-documentos') return 'Enviar agora';
    if (codigo === 'juntar-termo-envio') {
        if (status === 'aguardando juntada') return 'Termo juntado';
        if (status === 'aguardando protocolo' || status === 'aguardando liberacao nos autos') return 'Confirmar protocolo';
    }
    if (codigo === 'expedir-certidao') return 'Certidão expedida';

    return statusRaw === 'Aguardando liberação nos autos' ? 'Aguardando liberação' : statusRaw;
}

/** Atualiza métricas de timeline: duração, atraso, informações de conclusão */
function updateTimelineMetrics() {
    const containers = document.querySelectorAll('[data-live-timeline]');
    if (containers.length === 0) return;

    const parseDateSafe = (raw) => {
        if (!raw) return null;

        const asString = String(raw).trim();
        if (!asString) return null;

        const iso = asString.includes('T') ? asString : asString.replace(' ', 'T');
        const date = new Date(iso);
        if (!Number.isNaN(date.getTime())) return date;

        const fallback = new Date(asString);
        if (!Number.isNaN(fallback.getTime())) return fallback;

        return null;
    };

    const formatDateTimeDash = (raw) => {
        if (!raw) return '';
        const iso = String(raw).replace(' ', 'T');
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) return raw;
        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const yyyy = date.getFullYear();
        const hh = String(date.getHours()).padStart(2, '0');
        const min = String(date.getMinutes()).padStart(2, '0');
        const ss = String(date.getSeconds()).padStart(2, '0');
        return `${dd}/${mm}/${yyyy} - ${hh}:${min}:${ss}`;
    };

    const formatDateOnly = (raw) => {
        const date = parseDateSafe(raw);
        if (!date) return '';
        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const yyyy = date.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    };

    const formatDateTimeFull = (raw) => {
        const date = parseDateSafe(raw);
        if (!date) return '';
        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const yyyy = date.getFullYear();
        const hh = String(date.getHours()).padStart(2, '0');
        const min = String(date.getMinutes()).padStart(2, '0');
        return `${dd}/${mm}/${yyyy} às ${hh}:${min}`;
    };

    containers.forEach((container) => {
        const readOnly = container.dataset.readonly === '1';
        const items = readOnly
            ? container.querySelectorAll('.timeline-item')
            : container.querySelectorAll('.subtask-item');

        items.forEach((item) => {
            const statusRaw = item.dataset.status || '';
            const codigo = item.dataset.codigo || '';
            const isPlaceholder = item.dataset.placeholder === '1';
            
            // Normaliza status removendo acentos para comparação
            const status = statusRaw.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();

            // Datas
            const createdRaw = item.dataset.created || '';
            const startedRaw = item.dataset.started || '';
            const completedRaw = item.dataset.completed || '';

            let createdAt = null;
            let startedAt = null;
            let completedAt = null;

            try {
                createdAt = parseDateSafe(createdRaw);
                startedAt = parseDateSafe(startedRaw);
                completedAt = parseDateSafe(completedRaw);
            } catch (e) {
                console.warn('Erro ao parsear data:', e);
            }

            const actionsContainer = item.querySelector('.subtask-actions');
            if (actionsContainer) {
                actionsContainer.querySelectorAll('.subtask-whatsapp-btn, .subtask-copy-certidao-btn').forEach((btn) => {
                    btn.remove();
                });
            }

            // ========== RENDERIZAR DADOS ESPECÍFICOS POR TIPO ==========
            const detailsContainer = item.querySelector('[data-role="specific-details"]');
            if (readOnly) {
                if (!detailsContainer) return;

                detailsContainer.innerHTML = '';

                const createInfoLine = (label, value, options = {}) => {
                    const line = document.createElement('div');
                    line.className = 'subtask-info-line';

                    const labelEl = document.createElement('span');
                    labelEl.className = 'subtask-info-label';
                    labelEl.textContent = label;

                    if (options.compactLabel) {
                        labelEl.style.minWidth = 'auto';
                        labelEl.style.flex = '0 0 auto';
                    }

                    const valueEl = document.createElement('span');
                    valueEl.className = `subtask-info-value${options.monoValue ? ' mono' : ''}`;
                    valueEl.textContent = value;

                    if (options.valueStyle && typeof options.valueStyle === 'object') {
                        Object.assign(valueEl.style, options.valueStyle);
                    }

                    if (options.noWrap) {
                        line.style.flexWrap = 'nowrap';
                        labelEl.style.whiteSpace = 'nowrap';
                        valueEl.style.whiteSpace = 'nowrap';
                    }

                    line.appendChild(labelEl);
                    line.appendChild(valueEl);
                    return line;
                };

                const placeholderNotes = {
                    'assinar-guia': 'Subtarefa ainda não adicionada à linha do tempo.',
                    'gerar-pdf': 'Adicionar subtarefa para liberar a geração do PDF.',
                    'enviar-documentos': 'Subtarefa aguardando criação para envio.',
                    'juntar-termo-envio': 'Subtarefa precisa ser criada após envio dos documentos.',
                    'expedir-certidao': 'Subtarefa final para emissão da certidão.'
                };

                if (isPlaceholder) {
                    const note = placeholderNotes[codigo] || 'Subtarefa ainda não adicionada à linha do tempo.';
                    detailsContainer.appendChild(createInfoLine('Observação:', note));
                    return;
                }

                const responsavel = item.dataset.responsavel || '';
                const prazoDias = item.dataset.prazoDias || '';
                const dataPrazo = item.dataset.dataPrazo || '';
                const dataFato = item.dataset.dataFato || '';
                const diasMulta = item.dataset.diasMulta || item.dataset.baseCalculoDias || '';
                const valorMulta = item.dataset.valorMulta || '';
                const valorMultaFmt = formatCurrencyBRL(valorMulta);
                const inicio = formatDateTimeFull(startedRaw || createdRaw);
                const concluidaEm = formatDateTimeFull(completedRaw);

                switch (codigo) {
                    case 'calcular-pena-multa':
                        detailsContainer.appendChild(createInfoLine('Data do fato:', formatDateTimeFull(dataFato) || 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Qtd. dias-multa:', diasMulta ? `${diasMulta} dias` : 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Cálculo final:', valorMultaFmt ? `R$ ${valorMultaFmt}` : 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Concluída em:', concluidaEm || 'Não informado'));
                        break;
                    case 'expedir-guia':
                        detailsContainer.appendChild(createInfoLine('Responsável:', responsavel || 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Prazo:', prazoDias ? `${prazoDias} dias` : 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Início:', inicio || 'Não informado'));
                        break;
                    default: {
                        let added = false;
                        if (responsavel) {
                            detailsContainer.appendChild(createInfoLine('Responsável:', responsavel));
                            added = true;
                        }
                        if (dataPrazo) {
                            detailsContainer.appendChild(createInfoLine('Previsto para:', formatDateOnly(dataPrazo) || dataPrazo));
                            added = true;
                        }
                        if (inicio) {
                            detailsContainer.appendChild(createInfoLine('Início:', inicio));
                            added = true;
                        }
                        if (!added) {
                            detailsContainer.appendChild(createInfoLine('Observação:', 'Subtarefa ainda não adicionada à linha do tempo.'));
                        }
                        break;
                    }
                }

                return;
            }

            if (isPlaceholder) {
                if (detailsContainer) {
                    detailsContainer.innerHTML = '';
                    const note = document.createElement('span');
                    note.className = 'subtask-extra';
                    note.textContent = 'Subtarefa ainda não adicionada à linha do tempo.';
                    detailsContainer.appendChild(note);
                }

                const badge = item.querySelector('.subtask-status-badge-dynamic');
                if (badge) {
                    badge.textContent = 'Não cadastrada';
                    badge.classList.remove('done');
                    badge.classList.add('pending');
                }

                const statusBtn = item.querySelector('.subtask-status-btn');
                if (statusBtn) {
                    if (readOnly) {
                        statusBtn.remove();
                    } else {
                        statusBtn.textContent = 'Criar subtarefa';
                        statusBtn.dataset.currentStatus = 'Não cadastrada';
                        statusBtn.className = 'subtask-status-btn action-purple';
                    }
                }
                return;
            }
            
            if (detailsContainer) {
                // Limpar detalhes anteriores
                detailsContainer.innerHTML = '';
                
                // Helper functions
                const createDetail = (text) => {
                    const span = document.createElement('span');
                    span.className = 'subtask-extra mono';
                    span.textContent = text;
                    return span;
                };
                
                const createDetailHTML = (html) => {
                    const span = document.createElement('span');
                    span.className = 'subtask-extra mono';
                    span.innerHTML = html;
                    return span;
                };

                const createDetailWithRole = (text, role) => {
                    const span = createDetail(text);
                    span.dataset.role = role;
                    return span;
                };

                const createDetailWithClass = (text, className) => {
                    const span = createDetail(text);
                    span.className = className;
                    return span;
                };

                const createInfoLine = (label, value, options = {}) => {
                    const line = document.createElement('div');
                    line.className = 'subtask-info-line';

                    const labelEl = document.createElement('span');
                    labelEl.className = 'subtask-info-label';
                    labelEl.textContent = label;

                    if (options.compactLabel) {
                        labelEl.style.minWidth = 'auto';
                        labelEl.style.flex = '0 0 auto';
                    }

                    const valueEl = document.createElement('span');
                    valueEl.className = `subtask-info-value${options.monoValue ? ' mono' : ''}`;
                    valueEl.textContent = value;

                    if (options.valueStyle && typeof options.valueStyle === 'object') {
                        Object.assign(valueEl.style, options.valueStyle);
                    }

                    if (options.noWrap) {
                        line.style.flexWrap = 'nowrap';
                        labelEl.style.whiteSpace = 'nowrap';
                        valueEl.style.whiteSpace = 'nowrap';
                    }

                    line.appendChild(labelEl);
                    line.appendChild(valueEl);
                    return line;
                };

                const isConcluido = status === 'concluida' || status === 'concluido';
                const isAguardandoLiberacao = status === 'aguardando liberacao nos autos';
                const isEmRevisao = status === 'em revisao';
                const isEmAndamento = status === 'em andamento';
                const isRefazer = status === 'refazer';
                
                switch(codigo) {
                    case 'calcular-pena-multa':
                        const dataFato = item.dataset.dataFato || '';
                        const diasMulta = item.dataset.diasMulta || item.dataset.baseCalculoDias || '';
                        const salarioMinimo = item.dataset.salarioMinimo || '';
                        const valorUnitario = item.dataset.valorUnitario || '';
                        const valorMulta = item.dataset.valorMulta || '';

                        const temDadosMinimos = hasValidDateValue(dataFato) && hasPositiveNumericValue(diasMulta);
                        const temCalculoPronto = temDadosMinimos && hasPositiveNumericValue(valorMulta);

                        if (!temDadosMinimos) {
                            const dataInputGroup = document.createElement('div');
                            dataInputGroup.className = 'subtask-input-group';
                            const dataInputLabel = document.createElement('label');
                            dataInputLabel.className = 'subtask-input-label';
                            dataInputLabel.textContent = 'Data do fato';
                            const dataInput = document.createElement('input');
                            dataInput.type = 'date';
                            dataInput.className = 'subtask-input';
                            dataInput.dataset.role = 'data-fato';
                            dataInput.style.maxWidth = '300px';
                            dataInputGroup.appendChild(dataInputLabel);
                            dataInputGroup.appendChild(dataInput);
                            detailsContainer.appendChild(dataInputGroup);

                            const diasInputGroup = document.createElement('div');
                            diasInputGroup.className = 'subtask-input-group';
                            const diasInputLabel = document.createElement('label');
                            diasInputLabel.className = 'subtask-input-label';
                            diasInputLabel.textContent = 'Quantidade de dias-multa da condenação';
                            const diasInput = document.createElement('input');
                            diasInput.type = 'number';
                            diasInput.min = '1';
                            diasInput.className = 'subtask-input';
                            diasInput.dataset.role = 'dias-multa';
                            diasInput.placeholder = 'Ex: 30';
                            diasInput.style.maxWidth = '150px';
                            diasInputGroup.appendChild(diasInputLabel);
                            diasInputGroup.appendChild(diasInput);
                            detailsContainer.appendChild(diasInputGroup);

                            const note = document.createElement('span');
                            note.className = 'subtask-extra';
                            note.style.fontSize = '11px';
                            note.style.color = 'var(--color-muted)';
                            note.style.marginTop = '10px';
                            note.textContent = 'Preencha os dados acima para calcular a pena de multa. O sistema utilizará 1/30 do salário mínimo vigente à época do fato como base de cálculo.';
                            detailsContainer.appendChild(note);
                            break;
                        }

                        const diasMultaNum = parseNumericValue(diasMulta);
                        const salarioFmt = formatCurrencyBRL(salarioMinimo);
                        const valorUnitarioFmtDireto = formatCurrencyBRL(valorUnitario);
                        const salarioNum = parseNumericValue(salarioMinimo);
                        const valorMultaNum = parseNumericValue(valorMulta);
                        const valorUnitarioCalculadoPorSalario = (valorUnitarioFmtDireto === null && salarioNum !== null && salarioNum > 0)
                            ? formatCurrencyBRL((salarioNum / 30).toFixed(2))
                            : null;
                        const valorUnitarioCalculadoPorMulta = (
                            valorUnitarioFmtDireto === null &&
                            valorUnitarioCalculadoPorSalario === null &&
                            valorMultaNum !== null &&
                            diasMultaNum !== null &&
                            diasMultaNum > 0
                        )
                            ? formatCurrencyBRL((valorMultaNum / diasMultaNum).toFixed(2))
                            : null;
                        const valorUnitarioFmt = valorUnitarioFmtDireto || valorUnitarioCalculadoPorSalario || valorUnitarioCalculadoPorMulta;
                        const valorMultaFmt = formatCurrencyBRL(valorMulta);

                        detailsContainer.appendChild(createInfoLine('Data do fato:', formatDateTime(dataFato) || 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Qtd. dias-multa:', diasMultaNum !== null && diasMultaNum > 0 ? `${diasMultaNum} dias` : 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Salário mínimo:', salarioFmt ? `R$ ${salarioFmt}` : 'Não informado'));
                        detailsContainer.appendChild(createInfoLine('Base de cálculo (1/30 SM):', valorUnitarioFmt ? `R$ ${valorUnitarioFmt}` : 'Não informado'));
                        const calcExpression = (diasMultaNum || diasMulta)
                            ? `${diasMultaNum || diasMulta} × R$ ${valorUnitarioFmt || 'N/I'}`
                            : '';

                        const isSemCalculo = !temCalculoPronto && !hasPositiveNumericValue(valorUnitarioFmtDireto);

                        detailsContainer.appendChild(
                            createInfoLine(
                                `Valor da multa${calcExpression ? ` (${calcExpression})` : ''}:`,
                                valorMultaFmt ? `R$ ${valorMultaFmt}` : 'Não informado',
                                {
                                    monoValue: true,
                                    compactLabel: true,
                                    noWrap: true,
                                    valueStyle: temCalculoPronto
                                        ? {
                                            fontSize: '14px',
                                            fontWeight: '600',
                                            color: 'var(--v-card-alert-text)'
                                        }
                                        : {
                                            color: 'var(--color-muted)'
                                        }
                                }
                            )
                        );

                        const baseNote = document.createElement('span');
                        baseNote.className = 'subtask-extra';
                        baseNote.style.fontSize = '11px';
                        baseNote.style.color = 'var(--color-muted)';
                        baseNote.textContent = 'Base de cálculo: 1/30 do salário mínimo vigente na data do fato. Valor da multa = dias-multa × base de cálculo.';
                        detailsContainer.appendChild(baseNote);

                        if (isSemCalculo && (item.dataset.status === 'Concluída' || item.dataset.status === 'Concluído')) {
                            const fallback = document.createElement('span');
                            fallback.className = 'subtask-extra';
                            fallback.style.fontSize = '12px';
                            fallback.style.color = 'var(--color-muted)';
                            fallback.textContent = 'Cálculo não realizado — nenhum valor foi registrado.';
                            detailsContainer.replaceChildren(fallback);
                            name.textContent = 'Cálculo não realizado';
                        }
                        if (item.dataset.auto === 'true') {
                            const autoNote = document.createElement('span');
                            autoNote.className = 'subtask-extra mono';
                            autoNote.style.fontSize = '10px';
                            autoNote.style.color = 'var(--v-card-success-text)';
                            autoNote.textContent = '✓ Tarefa criada automaticamente após validação dos dados da guia';
                            detailsContainer.appendChild(autoNote);
                        }
                        break;
                    
                    case 'expedir-guia':
                        const responsavel = item.dataset.responsavel || '';
                        const prazoDias = item.dataset.prazoDias || '';
                        const guiaTipo = item.dataset.guiaTipo || '';
                        const exibeVerificacaoSEEU = guiaTipoExigeVerificacaoSEEU(guiaTipo);
                        const motivoRefazer = item.dataset.motivoRefazer || '';
                        
                        if (isConcluido) {
                            detailsContainer.appendChild(createDetailWithRole('', 'duration-info'));
                            detailsContainer.appendChild(createDetailWithRole('', 'done-info'));
                            break;
                        }

                        if (responsavel) {
                            detailsContainer.appendChild(createInfoLine('Responsável:', responsavel));
                        }
                        if (prazoDias) {
                            const prazoSpan = createDetailWithRole('', 'prazo');
                            prazoSpan.className = 'subtask-info-line';
                            prazoSpan.appendChild(Object.assign(document.createElement('span'), { className: 'subtask-info-label', textContent: 'Prazo:' }));
                            prazoSpan.appendChild(Object.assign(document.createElement('span'), { className: 'subtask-info-value', textContent: `${prazoDias} dias` }));
                            detailsContainer.appendChild(prazoSpan);
                        }

                        if (exibeVerificacaoSEEU) {
                            const selectWrap = document.createElement('div');
                            selectWrap.className = 'subtask-input-group';

                            const selectLabel = document.createElement('label');
                            selectLabel.className = 'subtask-input-label';
                            selectLabel.textContent = 'Consulta no SEEU / guia provisória';

                            const select = document.createElement('select');
                            select.className = 'subtask-input';
                            select.dataset.role = 'verificacao-seeu';
                            select.style.maxWidth = '400px';

                            const legacyRaw = String(item.dataset.verificacaoSeeu || '').trim();
                            let currentKey = '';
                            if (legacyRaw === 'ja-existe-vep') {
                                currentKey = 'verificado-consta';
                            } else if (legacyRaw === 'nenhuma-vep') {
                                currentKey = 'verificado-nao-consta';
                            } else if (legacyRaw !== '') {
                                currentKey = legacyRaw;
                            }
                            if (!currentKey) {
                                currentKey = item.dataset.reuProvisoria === '1' ? 'verificado-consta' : 'nao-verificado';
                            }

                            const options = [
                                { value: '0', label: 'Não verificado', key: 'nao-verificado' },
                                { value: '1', label: 'Verificado - não consta', key: 'verificado-nao-consta' },
                                { value: '2', label: 'Verificado - consta', key: 'verificado-consta' }
                            ];

                            options.forEach((opt) => {
                                const o = document.createElement('option');
                                o.value = opt.value;
                                o.textContent = opt.label;
                                o.dataset.key = opt.key;
                                if (currentKey === opt.key || currentKey === opt.value) o.selected = true;
                                select.appendChild(o);
                            });

                            selectWrap.appendChild(selectLabel);
                            selectWrap.appendChild(select);
                            detailsContainer.appendChild(selectWrap);

                            const note = document.createElement('span');
                            note.className = 'subtask-extra';
                            note.style.fontSize = '10px';
                            note.style.color = 'var(--color-muted)';
                            note.textContent = 'Verifique no sistema SEEU se já existe alguma Guia de Recolhimento referente ao réu e ao processo em questão cadastrada ou em andamento em alguma Vara de Execuções Penais.';
                            detailsContainer.appendChild(note);
                        }

                        if (isRefazer && motivoRefazer) {
                            const motivoSpan = createDetail(`Motivo: ${motivoRefazer}`);
                            motivoSpan.style.color = 'var(--v-card-error-text)';
                            detailsContainer.appendChild(motivoSpan);
                        }

                        if (item.dataset.auto === 'true') {
                            const autoNote = document.createElement('span');
                            autoNote.className = 'subtask-extra mono';
                            autoNote.style.fontSize = '10px';
                            autoNote.style.color = 'var(--v-card-success-text)';
                            autoNote.textContent = `✓ Tarefa criada automaticamente após conclusão de "Cálculo da pena de multa"`;
                            detailsContainer.appendChild(autoNote);
                        }

                        detailsContainer.appendChild(createDetailWithRole('', 'metrics'));
                        break;
                    
                    case 'assinar-guia':
                        const responsavelAssinar = item.dataset.responsavel || '';
                        const statusAssinar = item.dataset.status || '';
                        const statusAssinarNorm = statusAssinar
                            .toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .trim();
                        
                        if (isConcluido) {
                            detailsContainer.appendChild(createDetailWithRole('', 'duration-info'));
                            detailsContainer.appendChild(createDetailWithRole('', 'done-info'));
                            break;
                        }

                        const guiaTipoAssinar = item.dataset.guiaTipo || '';
                        const reuNomeAssinar = item.dataset.reuNome || item.dataset.denunciado || '';

                        if (guiaTipoAssinar) {
                            detailsContainer.appendChild(createInfoLine('Tipo:', guiaTipoAssinar));
                        }
                        if (reuNomeAssinar) {
                            detailsContainer.appendChild(createInfoLine('Réu:', reuNomeAssinar));
                        }
                        if (responsavelAssinar) {
                            detailsContainer.appendChild(createInfoLine('Responsável:', responsavelAssinar));
                        }

                        if (statusAssinarNorm === 'aguardando assinatura') {
                            detailsContainer.appendChild(createDetailWithRole('', 'metrics'));

                            const createdDate = createdAt && !Number.isNaN(createdAt.getTime())
                                ? formatDateTimeDash(createdRaw)
                                : '';

                            if (createdDate) {
                                detailsContainer.appendChild(
                                    createDetail(`Aguardando assinatura desde ${createdDate}`)
                                );
                            }
                        }
                        
                        // Adicionar botão WhatsApp se status é "Aguardando assinatura"
                        if (statusAssinar === 'Aguardando assinatura') {
                            const processo = item.dataset.processo || '';
                            const guiaTipo = item.dataset.guiaTipo || '';
                            const reuNome = item.dataset.reuNome || item.dataset.denunciado || '';
                            
                            const actions = item.querySelector('.subtask-actions');
                            if (actions && processo && guiaTipo && reuNome) {
                                const whatsappBtn = document.createElement('button');
                                whatsappBtn.className = 'subtask-whatsapp-btn';
                                whatsappBtn.type = 'button';
                                whatsappBtn.title = 'Copiar mensagem para enviar à juíza';
                                whatsappBtn.innerHTML = '<i class="fi fi-rr-copy"></i> Copiar mensagem';
                                whatsappBtn.onclick = () => {
                                    const mensagem = `${processo} - ${guiaTipo} de ${reuNome} *aguardando assinatura* no BNMP`;
                                    navigator.clipboard.writeText(mensagem).then(() => {
                                        whatsappBtn.innerHTML = '<i class="fi fi-rr-check"></i> Copiado!';
                                        setTimeout(() => {
                                            whatsappBtn.innerHTML = '<i class="fi fi-rr-copy"></i> Copiar mensagem';
                                        }, 2000);
                                    });
                                };
                                actions.appendChild(whatsappBtn);
                            }
                        }
                        break;
                    
                    case 'gerar-pdf':
                        const qtdDocs = item.dataset.qtdDocumentos || '';
                        const statusPDF = item.dataset.status || '';
                        const statusPDFNorm = statusPDF
                            .toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .trim();
                        const responsavelPDF = item.dataset.responsavel || '';
                        
                        if (responsavelPDF) {
                            detailsContainer.appendChild(createInfoLine('Responsável:', responsavelPDF));
                        }

                        if (statusPDFNorm === 'concluido' || statusPDFNorm === 'concluida') {
                            detailsContainer.appendChild(createDetailWithRole('', 'qtd-info'));
                            detailsContainer.appendChild(createDetailWithRole('', 'duration-info'));
                            detailsContainer.appendChild(createDetailWithRole('', 'done-info'));
                            break;
                        }

                        if (statusPDFNorm === 'em revisao' || statusPDFNorm === 'em andamento') {
                            detailsContainer.appendChild(createDetailWithRole('', 'metrics'));
                            break;
                        }
                        
                        const inputGroup = document.createElement('div');
                        inputGroup.className = 'subtask-input-group';

                        const inputLabel = document.createElement('label');
                        inputLabel.className = 'subtask-input-label';
                        
                        const labelText = document.createElement('span');
                        labelText.textContent = 'Quantidade de documentos';
                        
                        const input = document.createElement('input');
                        input.type = 'number';
                        input.min = '1';
                        input.max = '99';
                        input.value = qtdDocs || '3';
                        input.className = 'subtask-input';
                        input.style.maxWidth = '100px';
                        input.dataset.role = 'qtd-docs';
                        
                        inputLabel.appendChild(labelText);
                        inputGroup.appendChild(inputLabel);
                        inputGroup.appendChild(input);
                        detailsContainer.appendChild(inputGroup);
                        
                        if (item.dataset.auto === 'true') {
                            const autoNote = document.createElement('span');
                            autoNote.className = 'subtask-extra mono';
                            autoNote.style.fontSize = '10px';
                            autoNote.style.color = 'var(--v-card-success-text)';
                            autoNote.textContent = '✓ Tarefa criada automaticamente após conclusão de "Assinar Guia"';
                            detailsContainer.appendChild(autoNote);
                        }
                        break;
                    
                    case 'enviar-documentos':
                        const statusEnvio = item.dataset.status || '';
                        const responsavelEnvio = item.dataset.responsavel || '';
                        const destinoDataset = item.dataset.destino || item.dataset.destinoGuia || '';
                        const checkboxReuProvExpedir = item.dataset.reuProvisoria === '1';
                        const destinoEnvio = destinoDataset || (checkboxReuProvExpedir ? 'VEP' : 'SEEU');
                        
                        detailsContainer.appendChild(createInfoLine('Via:', 'Malote Digital'));
                        
                        if (statusEnvio === 'Concluído' || statusEnvio === 'Concluída') {
                            const destinoSpan = document.createElement('span');
                            detailsContainer.appendChild(createInfoLine('Destino:', destinoEnvio === 'VEP' ? 'Enviado à VEP' : 'Enviado ao SEEU', {
                                monoValue: true,
                                valueStyle: {
                                    fontWeight: '600',
                                    color: 'var(--v-card-success-text)'
                                }
                            }));
                            detailsContainer.appendChild(createDetailWithRole('', 'duration-info'));
                            detailsContainer.appendChild(createDetailWithRole('', 'done-info'));
                        } else {
                            const destinoSpan = document.createElement('span');
                            destinoSpan.className = 'subtask-info-value mono';
                            destinoSpan.style.fontWeight = '600';
                            destinoSpan.style.color = 'var(--v-card-info-text)';
                            if (destinoEnvio === 'VEP') {
                                destinoSpan.textContent = 'Enviar à VEP (Vara de Execução Penal)';
                            } else {
                                destinoSpan.textContent = 'Enviar ao SEEU (Sistema Eletrônico de Execução Unificado)';
                            }
                            const destinoLine = document.createElement('div');
                            destinoLine.className = 'subtask-info-line';
                            destinoLine.appendChild(Object.assign(document.createElement('span'), { className: 'subtask-info-label', textContent: 'Destino:' }));
                            destinoLine.appendChild(destinoSpan);
                            detailsContainer.appendChild(destinoLine);
                        }
                        
                        if (responsavelEnvio && !isConcluido) {
                            detailsContainer.appendChild(createInfoLine('Responsável:', responsavelEnvio));
                        }
                        break;
                    
                    case 'juntar-termo-envio':
                        const statusJuntar = item.dataset.status || '';
                        const statusJuntarNorm = statusJuntar
                            .toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .trim();
                        const responsavelJuntar = item.dataset.responsavel || '';
                        
                        if (statusJuntarNorm === 'concluida' || statusJuntarNorm === 'concluido') {
                            detailsContainer.appendChild(createDetailWithRole('', 'duration-info'));
                            detailsContainer.appendChild(createDetailWithRole('', 'done-info'));
                            break;
                        }

                        if (statusJuntarNorm === 'aguardando protocolo') {
                            detailsContainer.appendChild(createInfoLine('Status:', 'Termo juntado, liberado nos autos'));
                            if (item.dataset.dataJuntada) {
                                detailsContainer.appendChild(createInfoLine('Data juntada:', formatDateTime(item.dataset.dataJuntada, true)));
                            }
                            const protocoloLabel = document.createElement('label');
                            protocoloLabel.className = 'subtask-extra mono subtask-inline-field';
                            protocoloLabel.textContent = 'Protocolo de juntada';
                            const protocoloInput = document.createElement('input');
                            protocoloInput.type = 'text';
                            protocoloInput.className = 'subtask-inline-input';
                            protocoloInput.dataset.role = 'protocolo-juntada';
                            protocoloInput.placeholder = 'Ex: 2025000123456';
                            protocoloInput.value = item.dataset.protocoloJuntada || item.dataset.protocoloEnvio || '';
                            protocoloInput.style.maxWidth = '250px';
                            protocoloLabel.appendChild(protocoloInput);
                            detailsContainer.appendChild(protocoloLabel);
                            detailsContainer.appendChild(createDetailWithRole('', 'metrics'));
                            break;
                        }

                        if (statusJuntarNorm === 'aguardando liberacao nos autos') {
                            detailsContainer.appendChild(createInfoLine('Status:', 'Termo juntado, aguardando liberação nos autos'));
                            if (responsavelJuntar) {
                                detailsContainer.appendChild(createInfoLine('Responsável:', responsavelJuntar));
                            }
                            detailsContainer.appendChild(createDetailWithRole('', 'metrics'));
                            const warningSpan = createDetailWithClass('', 'subtask-extra mono delay-warning');
                            warningSpan.dataset.role = 'warning';
                            detailsContainer.appendChild(warningSpan);
                            if (actionsContainer) {
                                actionsContainer.querySelectorAll('.subtask-warning').forEach((el) => el.remove());
                            }
                            break;
                        }
                        
                        if (responsavelJuntar) {
                            detailsContainer.appendChild(createInfoLine('Responsável:', responsavelJuntar));
                        }

                        if (statusJuntarNorm === 'aguardando juntada') {
                            detailsContainer.appendChild(createInfoLine('Status:', 'Termo enviado ao cartório, aguardando juntada nos autos'));
                            if (item.dataset.dataEnvio || createdRaw) {
                                detailsContainer.appendChild(createInfoLine('Data envio:', formatDateTime(item.dataset.dataEnvio || createdRaw, true)));
                            }
                        }
                        break;
                    
                    case 'expedir-certidao':
                        const statusCertidao = item.dataset.status || '';
                        const statusCertidaoNorm = statusCertidao
                            .toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .trim();
                        const processo = item.dataset.processo || '';
                        const reuNome = item.dataset.reuNome || item.dataset.denunciado || '';
                        const responsavelCertidao = item.dataset.responsavel || '';
                        const textoCertidao = item.dataset.textoCertidao || item.dataset.certidaoTexto || textoCertidaoPadrao(item.dataset);
                        
                        if (statusCertidaoNorm === 'concluido' || statusCertidaoNorm === 'concluida') {
                            detailsContainer.appendChild(createDetail('Certidão de cumprimento registrada no sistema'));
                            detailsContainer.appendChild(createDetailWithRole('', 'duration-info'));
                            detailsContainer.appendChild(createDetailWithRole('', 'done-info'));
                        } else {
                            if (processo) {
                                detailsContainer.appendChild(createInfoLine('Processo:', processo, { monoValue: true }));
                            }
                            if (reuNome) {
                                detailsContainer.appendChild(createInfoLine('Réu:', reuNome));
                            }
                            if (responsavelCertidao) {
                                detailsContainer.appendChild(createInfoLine('Responsável:', responsavelCertidao));
                            }
                            if (statusCertidaoNorm === 'em andamento') {
                                detailsContainer.appendChild(createDetailWithRole('', 'metrics'));
                            }
                            
                            if (textoCertidao) {
                                const certidaoGroup = document.createElement('div');
                                certidaoGroup.className = 'subtask-input-group';

                                const certidaoLabel = document.createElement('label');
                                certidaoLabel.className = 'subtask-input-label';
                                certidaoLabel.textContent = 'Certidão (Preenchimento Automático)';

                                const certidaoArea = document.createElement('textarea');
                                certidaoArea.className = 'subtask-input subtask-input-multiline';
                                certidaoArea.disabled = true;
                                certidaoArea.value = textoCertidao;

                                certidaoGroup.appendChild(certidaoLabel);
                                certidaoGroup.appendChild(certidaoArea);
                                detailsContainer.appendChild(certidaoGroup);

                                const actions = item.querySelector('.subtask-actions');
                                if (actions) {
                                    const copyBtn = document.createElement('button');
                                    copyBtn.className = 'subtask-copy-certidao-btn';
                                    copyBtn.type = 'button';
                                    copyBtn.title = 'Copiar texto da certidão';
                                    copyBtn.innerHTML = '<i class="fi fi-rr-copy"></i> Copiar';
                                    copyBtn.onclick = () => {
                                        navigator.clipboard.writeText(textoCertidao).then(() => {
                                            copyBtn.innerHTML = '<i class="fi fi-rr-check"></i> Copiado!';
                                            setTimeout(() => {
                                                copyBtn.innerHTML = '<i class="fi fi-rr-copy"></i> Copiar';
                                            }, 2000);
                                        });
                                    };
                                    actions.appendChild(copyBtn);
                                }
                            }
                        }
                        break;
                }

                const fallback = item.querySelector('[data-role="fallback"]');
                if (fallback) {
                    if (detailsContainer.childElementCount === 0) {
                        fallback.textContent = statusRaw || 'A fazer';
                        fallback.style.display = '';
                    } else {
                        fallback.textContent = '';
                        fallback.style.display = 'none';
                    }
                }
            }

            if (readOnly) {
                const details = detailsContainer;
                if (!details) return;
                details.innerHTML = '';

                const addLine = (label, value) => {
                    if (!value) return;
                    details.appendChild(createInfoLine(label, value));
                };

                const formatDateOnly = (raw) => {
                    if (!raw) return '';
                    const iso = String(raw).replace(' ', 'T');
                    const date = new Date(iso);
                    if (Number.isNaN(date.getTime())) return raw;
                    const dd = String(date.getDate()).padStart(2, '0');
                    const mm = String(date.getMonth() + 1).padStart(2, '0');
                    const yyyy = date.getFullYear();
                    return `${dd}/${mm}/${yyyy}`;
                };

                const completedAt = item.dataset.completed || '';
                const startedAt = item.dataset.started || '';
                const createdAt = item.dataset.created || '';
                const responsavel = item.dataset.responsavel || '';
                const prazoDias = item.dataset.prazoDias || '';
                const dataPrazo = item.dataset.dataPrazo || '';
                const valorMulta = item.dataset.valorMulta || '';
                const diasMulta = item.dataset.diasMulta || item.dataset.baseCalculoDias || '';

                if (isPlaceholder) {
                    const placeholderNotas = {
                        'assinar-guia': 'Subtarefa ainda não adicionada à linha do tempo.',
                        'gerar-pdf': 'Adicionar subtarefa para liberar a geração do PDF.',
                        'enviar-documentos': 'Subtarefa aguardando criação para envio.',
                        'juntar-termo-envio': 'Subtarefa precisa ser criada após envio dos documentos.',
                        'expedir-certidao': 'Subtarefa final para emissão da certidão.'
                    };
                    addLine('Observação:', placeholderNotas[codigo] || 'Subtarefa ainda não adicionada à linha do tempo.');
                    return;
                }

                let conclusaoJaInformada = false;

                switch (codigo) {
                    case 'calcular-pena-multa':
                        addLine('Data do fato:', item.dataset.dataFato ? formatDateTime(item.dataset.dataFato) : 'Não informado');
                        addLine('Qtd. dias-multa:', diasMulta ? `${diasMulta} dias` : 'Não informado');
                        addLine('Cálculo final:', valorMulta ? `R$ ${formatCurrencyBRL(valorMulta)}` : 'Não informado');
                        addLine('Concluída em:', completedAt ? formatDateTime(completedAt) : '—');
                        conclusaoJaInformada = true;
                        break;
                    case 'expedir-guia':
                        addLine('Responsável:', responsavel);
                        addLine('Prazo:', prazoDias ? `${prazoDias} dias` : '');
                        addLine('Início:', startedAt ? formatDateTime(startedAt) : (createdAt ? formatDateTime(createdAt) : ''));
                        break;
                    case 'assinar-guia':
                        addLine('Responsável:', responsavel);
                        addLine('Início:', startedAt ? formatDateTime(startedAt) : (createdAt ? formatDateTime(createdAt) : ''));
                        break;
                    case 'gerar-pdf':
                        addLine('Quantidade de documentos:', item.dataset.qtdDocumentos || item.dataset.qtdDocs || '');
                        addLine('Início:', startedAt ? formatDateTime(startedAt) : (createdAt ? formatDateTime(createdAt) : ''));
                        break;
                    case 'enviar-documentos':
                        addLine('Via:', 'Malote Digital');
                        addLine('Destino:', item.dataset.destino || item.dataset.destinoGuia || '');
                        addLine('Início:', startedAt ? formatDateTime(startedAt) : (createdAt ? formatDateTime(createdAt) : ''));
                        break;
                    case 'juntar-termo-envio':
                        addLine('Responsável:', responsavel);
                        addLine('Previsto para:', dataPrazo ? formatDateOnly(dataPrazo) : '');
                        break;
                    case 'expedir-certidao':
                        addLine('Responsável:', responsavel);
                        addLine('Previsto para:', dataPrazo ? formatDateOnly(dataPrazo) : '');
                        break;
                    default:
                        addLine('Status:', statusRaw || 'A fazer');
                        break;
                }

                if (completedAt && !conclusaoJaInformada) {
                    addLine('Concluída em:', formatDateTime(completedAt));
                    if (item.dataset.completedBy) {
                        addLine('Concluída por:', item.dataset.completedBy);
                    }
                    if (item.dataset.durationSeconds) {
                        addLine('Duração:', formatDuration(item.dataset.durationSeconds));
                    }
                }
            }

            const statusBtn = item.querySelector('.subtask-status-btn');
            if (statusBtn) {
                const label = getActionLabelForSubtask(item);
                statusBtn.textContent = label;
                statusBtn.dataset.currentStatus = statusRaw || 'A fazer';
                statusBtn.className = `subtask-status-btn ${statusKey(statusRaw)}`;

                const badge = item.querySelector('.subtask-status-badge-dynamic');
                if (badge) {
                    badge.textContent = statusRaw || 'A fazer';
                    badge.classList.remove('done', 'pending');
                    const keyBadge = statusKey(statusRaw);
                    if (keyBadge === 'done') badge.classList.add('done');
                    if (keyBadge === 'pending') badge.classList.add('pending');
                }

                const codigoBtn = item.dataset.codigo || '';
                const statusNormBtn = normalizeStatus(statusRaw);

                if (!isConcluidaStatus(statusRaw)) {
                    if (
                        codigoBtn === 'calcular-pena-multa' ||
                        codigoBtn === 'expedir-guia' ||
                        codigoBtn === 'assinar-guia' ||
                        codigoBtn === 'gerar-pdf' ||
                        codigoBtn === 'enviar-documentos' ||
                        codigoBtn === 'expedir-certidao' ||
                        (codigoBtn === 'juntar-termo-envio' && (statusNormBtn === 'aguardando protocolo' || statusNormBtn === 'aguardando liberacao nos autos'))
                    ) {
                        statusBtn.classList.add('action-success');
                    }

                    if (codigoBtn === 'juntar-termo-envio' && statusNormBtn === 'aguardando juntada') {
                        statusBtn.classList.add('action-purple');
                    }
                }

                if (status === 'refazer') {
                    statusBtn.style.background = 'var(--v-card-error-bg)';
                    statusBtn.style.color = 'var(--v-card-error-text)';
                    statusBtn.style.borderColor = 'var(--v-card-error-color)';
                } else {
                    statusBtn.style.background = '';
                    statusBtn.style.color = '';
                    statusBtn.style.borderColor = '';
                }

                if (status === 'concluida' || status === 'concluido') {
                    statusBtn.disabled = true;
                    statusBtn.style.cursor = 'not-allowed';
                    statusBtn.style.opacity = '0.6';
                } else {
                    statusBtn.disabled = false;
                    statusBtn.style.cursor = '';
                    statusBtn.style.opacity = '';
                }
            }

            const dot = item.querySelector('.subtask-dot');
            if (dot) {
                dot.className = `subtask-dot ${statusKey(statusRaw)}`;
            }

            // ========== ATRASO ==========
            const avgSeconds = item.dataset.avgSeconds ? parseInt(item.dataset.avgSeconds) : 0;
            const isInProgressStatus =
                status === 'em andamento' ||
                status === 'aguardando assinatura' ||
                status === 'aguardando juntada' ||
                status === 'aguardando protocolo' ||
                status === 'aguardando liberacao nos autos' ||
                status === 'em revisao';
            const metrics = item.querySelector('[data-role="metrics"]');
            const warning = item.querySelector('[data-role="warning"]');

            if (item.dataset.auto === 'true') {
                if (metrics) metrics.textContent = '';
                if (warning) warning.textContent = '';
                item.classList.remove('delay');
            } else if (isInProgressStatus && (((codigo === 'expedir-guia' || codigo === 'assinar-guia') && createdAt && !Number.isNaN(createdAt.getTime())) || (startedAt && !Number.isNaN(startedAt.getTime())))) {
                const referenceStart = (codigo === 'expedir-guia' || codigo === 'assinar-guia') ? createdAt : startedAt;
                const durationSeconds = Math.max(0, Math.floor((new Date() - referenceStart) / 1000));
                const threshold = avgSeconds ? Math.round(avgSeconds * 1.2) : 0;
                const delayed = threshold > 0 && durationSeconds > threshold;

                item.classList.toggle('delay', delayed);

                if (metrics) {
                    const metricLabelByCodigo = {
                        'expedir-guia': 'Tempo médio de expedição',
                        'assinar-guia': 'Tempo médio',
                        'gerar-pdf': 'Tempo médio',
                        'juntar-termo-envio': 'Tempo médio',
                        'expedir-certidao': 'Tempo médio'
                    };
                    const metricLabel = metricLabelByCodigo[codigo] || 'Tempo médio';

                    if (avgSeconds) {
                        const avgLabel = formatDuration(avgSeconds);
                        const durationLabel = formatDuration(durationSeconds);
                        metrics.textContent = `${metricLabel}: ${avgLabel} | Tempo atual: ${durationLabel}`;
                    } else {
                        metrics.textContent = `Tempo atual: ${formatDuration(durationSeconds)}`;
                    }
                }

                if (warning) {
                    warning.textContent = delayed ? 'Atraso acima da média (+20%)' : '';
                }
            } else {
                item.classList.remove('delay');
                if (metrics) metrics.textContent = '';
                if (warning) warning.textContent = '';
            }

            // Ocultar prazo quando em andamento e atrasado
            const prazoEl = item.querySelector('[data-role="prazo"]');
            if (prazoEl) {
                if (isInProgressStatus && item.classList.contains('delay')) {
                    prazoEl.style.display = 'none';
                } else {
                    prazoEl.style.display = '';
                }
            }

            // ========== INFORMAÇÕES DE CONCLUSÃO ==========
            const doneInfo = item.querySelector('[data-role="done-info"]');
            const durationInfo = item.querySelector('[data-role="duration-info"]');
            const qtdInfo = item.querySelector('[data-role="qtd-info"]');

            if ((status === 'concluida' || status === 'concluido') && completedAt && !Number.isNaN(completedAt.getTime())) {
                // Renderizar quantidade de documentos (se existir)
                if (qtdInfo) {
                    const qtdDocs = item.dataset.qtdDocs || item.dataset.qtdDocumentos || '';
                    if (qtdDocs !== '') {
                        const qtd = parseInt(qtdDocs);
                        const plural = qtd !== 1;
                        qtdInfo.textContent = `${qtdDocs} documento${plural ? 's' : ''} gerado${plural ? 's' : ''}`;
                    }
                }

                // Calcular tempo gasto para conclusão
                const durationStart =
                    (startedAt && !Number.isNaN(startedAt.getTime()))
                        ? startedAt
                        : ((codigo === 'assinar-guia' || codigo === 'gerar-pdf') && createdAt && !Number.isNaN(createdAt.getTime())
                            ? createdAt
                            : null);

                if (durationInfo && durationStart) {
                    const durationSeconds = Math.max(0, Math.floor((completedAt - durationStart) / 1000));
                    durationInfo.textContent = `Tempo gasto: ${formatDuration(durationSeconds)}`;
                }

                // Mostrar informação de conclusão
                if (doneInfo) {
                    const completedBy = item.dataset.completedBy || item.dataset.responsavel || '';
                    doneInfo.textContent = `Concluído em ${formatDateTime(completedRaw)}`;
                    if (completedBy) {
                        doneInfo.textContent += ` por ${completedBy}`;
                    }
                }
            }
        });

        const row = container.closest('.row.table-row[data-id]');
        if (row) {
            aplicarRegraVisibilidadeSubtarefas(container);
            atualizarResumoPaiDaLinha(row);
        }
    });
}

// Executar updateTimelineMetrics quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.row.table-row[data-id]').forEach((row) => {
        const statusElement = row.querySelector('.proposal-status-bar, .proposal-status, [data-status], .bg-label-info, .bg-label-alert, .bg-label-success, .bg-label-error');
        if (!statusElement) return;
        aplicarModoLinhaConcluida(row, statusElement.textContent || '');
    });

    updateTimelineMetrics();
    atualizarStatusGuiasCarregadas();
    carregarSubtarefasTodasGuias();
});

// Event listener: botão WhatsApp/copy para "Assinar Guia"
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.subtask-whatsapp-btn');
    if (!btn) return;

    const item = btn.closest('.subtask-item');
    if (!item) return;

    const processo = item.dataset.processo || '';
    const guiaTipo = item.dataset.guiaTipo || '';
    const reuNome = item.dataset.reuNome || '';

    const mensagem = `${processo} - ${guiaTipo} de ${reuNome} *aguardando assinatura* no BNMP`;

    // Copiar para clipboard
    navigator.clipboard.writeText(mensagem).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fi fi-rr-check"></i> Copiado!';
        btn.style.background = 'rgba(37, 211, 102, 0.25)';

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
        }, 2000);
    }).catch((err) => {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar mensagem. Tente novamente.');
    });
});

// Atualizar subtarefa "Expedir Guia" ao alterar verificação SEEU
document.addEventListener('change', (e) => {
    const select = e.target.closest('select[data-role="verificacao-seeu"]');
    if (!select) return;

    const item = select.closest('.subtask-item');
    const row = item ? item.closest('.row.table-row[data-id]') : null;
    const idSubtarefa = item ? parseInt(item.dataset.id) : null;
    const userId = obterUserIdAtual(select) || 'sistema';
    const valor = select.value;
    const selectedOption = select.selectedOptions?.[0] || null;
    const selectedKey = selectedOption?.dataset?.key || '';
    const reuProvisoria = String(valor) === '2';
    const destinoGuia = reuProvisoria ? 'VEP' : 'SEEU';

    if (!idSubtarefa) return;

    // Atualiza status para Em andamento e salva a seleção
    atualizarStatusSubtarefa(
        idSubtarefa,
        'Em andamento',
        userId,
        async () => {
            // Atualizar dataset localmente
            item.dataset.status = 'Em andamento';
            item.dataset.verificacaoSeeu = valor;
            item.dataset.reuProvisoria = reuProvisoria ? '1' : '';
            item.dataset.destinoGuia = destinoGuia;
            const badge = item.querySelector('.subtask-status-badge-dynamic');
            if (badge) badge.textContent = 'Em andamento';
            const btn = item.querySelector('.subtask-status-btn');
            if (btn) {
                btn.textContent = 'Expedir guia';
                btn.dataset.currentStatus = 'Em andamento';
            }

            if (row) {
                // Forçar reload das subtarefas para garantir sincronização
                const container = row.querySelector('[data-subtasks]');
                if (container) container.dataset.loaded = '0';
                await carregarSubtarefas(row);
                const resumo = obterResumoPaiPorSubtarefas(row);
                aplicarResumoPai(row, resumo);
                const idGuia = parseInt(row.dataset.id || '0');
                if (idGuia) await atualizarStatusGuiaPrincipal(idGuia, resumo.status, userId);
            }
        },
        () => {},
        {
            checkbox_reu_provisoria: reuProvisoria ? 1 : 0,
            destino_guia: destinoGuia,
            verificacao_seeu: valor
        }
    );
});

// Event listener: botão copia certidão
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.subtask-copy-certidao-btn');
    if (!btn) return;

    const item = btn.closest('.subtask-item');
    if (!item) return;

    const textoCertidao = item.dataset.certidaoTexto;
    if (!textoCertidao) {
        alert('Texto da certidão não disponível.');
        return;
    }

    // Copiar para clipboard
    navigator.clipboard.writeText(textoCertidao).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fi fi-rr-check"></i> Copiado!';
        btn.style.background = 'rgba(37, 211, 102, 0.25)';

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
        }, 2000);
    }).catch((err) => {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar certidão. Tente novamente.');
    });
});

/**
 * Calcula o status da guia baseado nos status de suas subtarefas
 * @param {HTMLElement} row - Linha da guia na tabela
 * @returns {string} - Status calculado da guia
 */
function calcularStatusGuiaPorSubtarefas(row) {
    if (!row) return 'Não iniciada';
    
    const subtasksContainer = row.querySelector('[data-subtasks]');
    if (!subtasksContainer) return 'Não iniciada';
    
    const subtaskItems = subtasksContainer.querySelectorAll('.subtask-item');
    if (!subtaskItems.length) return 'Não iniciada';
    
    const statuses = Array.from(subtaskItems).map(item => item.dataset.status || 'A fazer');
    
    // Contar status
    const total = statuses.length;
    const concluidas = statuses.filter(s => s === 'Concluída' || s === 'Concluído').length;
    const emAndamento = statuses.filter(s => 
        s === 'Em andamento' || 
        s === 'Aguardando assinatura' || 
        s === 'Aguardando juntada' ||
        s === 'Aguardando protocolo' ||
        s === 'Aguardando liberação nos autos'
    ).length;
    
    // Lógica de determinação de status
    if (concluidas === total) {
        return 'Concluída';
    } else if (emAndamento > 0 || concluidas > 0) {
        return 'Em andamento';
    } else {
        return 'Não iniciada';
    }
}

function aplicarStatusVisualGuia(row, statusGuia) {
    if (!row) return;

    const statusContainer = row.querySelector('.proposal-closed-status');
    if (!statusContainer) return;

    const normalizado = normalizeStatus(statusGuia);
    const isConcluida = normalizado.includes('conclu');
    const isEmAndamento = normalizado.includes('andamento');

    if (isConcluida) {
        statusContainer.innerHTML = `<span class="proposal-status done" data-status="${statusGuia}">${statusGuia}</span>`;
    } else if (isEmAndamento) {
        statusContainer.innerHTML = `<span class="status-with-subtask model-bar" style="--progress-done: 0%; --progress-in-progress: 0%; --progress-total: 1;" data-status="${statusGuia}"><span class="status-text">${statusGuia}</span></span>`;
    } else {
        statusContainer.innerHTML = `<span class="proposal-status bg-label-info" data-status="${statusGuia}">${statusGuia}</span>`;
    }

    atualizarBarraProgressoSubtarefas(row);
}

function obterResumoPaiPorSubtarefas(row) {
    if (!row) {
        return { status: 'Não iniciada', texto: 'Não iniciada' };
    }

    const subtasksContainer = row.querySelector('[data-subtasks]');
    if (!subtasksContainer) {
        return { status: 'Não iniciada', texto: 'Não iniciada' };
    }

    const subtaskItems = Array.from(subtasksContainer.querySelectorAll('.subtask-item'));
    if (!subtaskItems.length) {
        return { status: 'Não iniciada', texto: 'Não iniciada' };
    }

    const isConcluida = (status) => {
        const s = String(status || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        return s.includes('conclu') || s.includes('feito');
    };

    const todasConcluidas = subtaskItems.every((item) => isConcluida(item.dataset.status));
    if (todasConcluidas) {
        return { status: 'Concluída', texto: 'Concluída' };
    }

    const atual = subtaskItems.find((item) => !isConcluida(item.dataset.status));
    if (!atual) {
        return { status: 'Em andamento', texto: 'Em andamento' };
    }

    const statusAtual = String(atual.dataset.status || 'A fazer').trim();
    const statusNorm = statusAtual
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    if (statusNorm === 'a fazer' || statusNorm === 'nao criada') {
        return { status: 'Não iniciada', texto: 'Não iniciada' };
    }

    return { status: 'Em andamento', texto: 'Em andamento' };
}

function aplicarResumoPai(row, resumo) {
    if (!row || !resumo) return;

    aplicarStatusVisualGuia(row, resumo.status);

    aplicarModoLinhaConcluida(row, resumo.status);
}

function atualizarResumoPaiDaLinha(row) {
    if (!row) return;
    const resumo = obterResumoPaiPorSubtarefas(row);
    aplicarResumoPai(row, resumo);
    atualizarBarraProgressoSubtarefas(row);
    aplicarModoLinhaConcluida(row, resumo.status);
}

function atualizarStatusGuiasCarregadas() {
    document.querySelectorAll('.row.table-row[data-id]').forEach((row) => {
        const container = row.querySelector('[data-subtasks]');
        if (!container || container.dataset.loaded !== '1') return;
        const resumo = obterResumoPaiPorSubtarefas(row);
        aplicarResumoPai(row, resumo);
    });
}

async function carregarSubtarefasTodasGuias() {
    const rows = Array.from(document.querySelectorAll('.row.table-row[data-id]'));
    for (const row of rows) {
        const container = row.querySelector('[data-subtasks]');
        if (!container) continue;

        if (container.dataset.loaded !== '1') {
            await carregarSubtarefas(row);
        }

        atualizarBarraProgressoSubtarefas(row);
        const resumo = obterResumoPaiPorSubtarefas(row);
        aplicarResumoPai(row, resumo);
    }
}

/**
 * Atualiza o status da guia principal baseado nos status das subtarefas
 * @param {number} idGuia - ID da guia
 * @param {string} novoStatus - Novo status calculado
 * @param {string} userId - ID do usuário
 */
async function atualizarStatusGuiaPrincipal(idGuia, novoStatus, userId) {
    try {
        const response = await fetch('/v2026/api/guia-update-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_guia: idGuia,
                novo_status: novoStatus,
                user_id: userId
            })
        });

        const data = await response.json();

        if (data.sucesso) {
            
            // Atualizar visualmente o status na linha da tabela
            const row = document.querySelector(`.row.table-row[data-id="${idGuia}"]`);
            if (row) {
                aplicarStatusVisualGuia(row, novoStatus);
            }
            
            return true;
        } else {
            console.warn('Erro ao atualizar status da guia:', data.mensagem);
            return false;
        }

    } catch (error) {
        console.error('Erro na requisição de atualização de status da guia:', error);
        return false;
    }
}

async function autoAlocarUsuarioParaGuia(idGuia) {
    if (!idGuia) return false;
    const cpf = document.getElementById('input-user-cpf')?.value?.trim() || '';
    const usuario = document.getElementById('input-user-usuario')?.value?.trim() || '';
    const setor = document.getElementById('input-user-setor')?.value?.trim() || '';
    const nucleo = document.getElementById('input-user-nucleo')?.value?.trim() || '';

    if (!cpf || !usuario || !setor || !nucleo) return false;

    const formData = new FormData();
    formData.append('id_guia', idGuia);
    formData.append('user_cpf', cpf);
    formData.append('user_usuario', usuario);
    formData.append('user_setor', setor);
    formData.append('user_nucleo', nucleo);

    try {
        const response = await fetch('../v2026/api/guia-auto-alocar.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            return response.status === 409;
        }

        const data = await response.json().catch(() => null);
        return data && (data.ok === true || data.success === true);
    } catch (err) {
        console.warn('Falha ao auto alocar usuário:', err);
        return false;
    }
}

async function atualizarSalarioMinimoAutomatico(dataFato) {
    if (!dataFato) return null;
    const container = document.querySelector('[data-subtasks]');
    const calcularItem = container?.querySelector('.subtask-item[data-codigo="calcular-pena-multa"]');
    const idGuia = document.getElementById('input-id-guia')?.value || document.body?.dataset?.idGuia || '';
    if (!calcularItem?.dataset?.id && !idGuia) return null;

    const payload = {
        data_fato: dataFato
    };
    if (calcularItem?.dataset?.id) {
        payload.id_subtarefa = parseInt(calcularItem.dataset.id, 10);
    } else if (idGuia) {
        payload.id_guia = idGuia;
    }

    try {
        const response = await fetch('/v2026/api/guia-subtarefa-salario.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            console.warn('Salário mínimo: resposta inesperada', response.status);
            return null;
        }

        const data = await response.json();
        if (!data || data.ok !== true) {
            console.warn('Não foi possível obter o salário mínimo:', data?.message || 'sem erro explicitado');
            return null;
        }

        const valor = parseNumericValue(data.valor);
        if (valor !== null) {
            calcularItem.dataset.salarioMinimo = String(valor);
            calcularItem.dataset.dataFato = String(dataFato);
        }

        return valor;
    } catch (err) {
        console.warn('Erro ao buscar salário mínimo:', err);
        return null;
    }
}

if (typeof window !== 'undefined') {
    window.atualizarSalarioMinimoAutomatico = atualizarSalarioMinimoAutomatico;
}

/**
 * Atualiza o status de uma subtarefa via API
 * Mostra toasts de feedback e cria automaticamente próximas tarefas
 * 
 * @param {number} idSubtarefa - ID da subtarefa
 * @param {string} novoStatus - Novo status ('A fazer', 'Em andamento', 'Concluída')
 * @param {string} userId - ID do usuário executando ação
 * @param {Function} onSuccess - Callback após sucesso
 * @param {Function} onError - Callback em caso de erro
 */
async function atualizarStatusSubtarefa(idSubtarefa, novoStatus, userId, onSuccess = null, onError = null, extraPayload = null) {
    try {
        const requestBody = {
            id_subtarefa: idSubtarefa,
            novo_status: novoStatus,
            user_id: userId
        };

        if (extraPayload && typeof extraPayload === 'object') {
            Object.assign(requestBody, extraPayload);
        }

        const response = await fetch('/v2026/api/guia-subtarefa-update-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });

        // Verificar se a resposta é JSON válido
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não-JSON recebida:', text.substring(0, 500));
            throw new Error('API retornou resposta inválida. Verifique os logs do servidor.');
        }

        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            const text = await response.text();
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Resposta recebida:', text.substring(0, 500));
            throw new Error('Erro ao processar resposta da API');
        }

        if (data.sucesso) {
            // Toast de sucesso para atualização
            if (window.Toast) {
                const statusMsg = novoStatus === 'Concluída' ? 'concluída' : 
                                  novoStatus === 'Em andamento' ? 'iniciada' : 'atualizada';
                
                Toast.show({
                    type: 'success',
                    title: 'Subtarefa atualizada',
                    message: `Status alterado para: ${novoStatus}`,
                    details: `A subtarefa foi ${statusMsg} com sucesso.`
                });
            }

            // Toast para tarefa criada automaticamente (cascata)
            if (data.tarefa_criada_automaticamente) {
                const tarefa = data.tarefa_criada_automaticamente;
                if (window.Toast) {
                    Toast.show({
                        type: 'info',
                        title: 'Nova tarefa criada',
                        message: tarefa.titulo,
                        details: `Status inicial: ${tarefa.status}. A próxima etapa foi adicionada automaticamente.`,
                        duration: 7000
                    });
                }
            }

            // Toast para tarefa existente atualizada automaticamente (cascata)
            if (data.tarefa_atualizada_automaticamente) {
                const tarefa = data.tarefa_atualizada_automaticamente;
                if (window.Toast) {
                    Toast.show({
                        type: 'info',
                        title: 'Subtarefa ajustada automaticamente',
                        message: tarefa.titulo,
                        details: `Status alterado de ${tarefa.status_anterior} para ${tarefa.status_novo} automaticamente.`,
                        duration: 7000
                    });
                }
            }

            // Callback de sucesso
            if (typeof onSuccess === 'function') {
                onSuccess(data);
            }

            // Recarregar timeline para refletir mudanças
            if (typeof updateTimelineMetrics === 'function') {
                setTimeout(() => updateTimelineMetrics(), 500);
            }

        } else {
            throw new Error(data.mensagem || 'Erro ao atualizar status');
        }

    } catch (error) {
        console.error('Erro ao atualizar status da subtarefa:', error);
        
        if (window.Toast) {
            Toast.show({
                type: 'error',
                title: 'Erro ao atualizar',
                message: error.message || 'Não foi possível atualizar o status da subtarefa',
                details: 'Tente novamente ou contate o suporte.'
            });
        }

        // Callback de erro
        if (typeof onError === 'function') {
            onError(error);
        }
    }
}

async function criarSubtarefa(idGuia, codigo, userId) {
    const payload = {
        id_guia: idGuia,
        codigo: codigo,
        user_id: userId
    };

    const response = await fetch('/v2026/api/guia-subtarefa-create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const contentType = response.headers.get('content-type') || '';
    let data = null;
    if (contentType.includes('application/json')) {
        data = await response.json();
    } else {
        const text = await response.text();
        throw new Error(text || 'Resposta inválida ao criar subtarefa.');
    }

    if (!response.ok || !(data?.ok === true || data?.success === true)) {
        throw new Error(data?.message || 'Não foi possível criar a subtarefa.');
    }

    return data;
}

async function excluirSubtarefa(idSubtarefa, userId) {
    const response = await fetch('/v2026/api/guia-subtarefa-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_subtarefa: idSubtarefa, user_id: userId })
    });
    const data = await response.json();
    if (!response.ok || !(data?.ok === true || data?.success === true)) {
        throw new Error(data?.message || 'Não foi possível excluir a subtarefa.');
    }
    return data;
}

async function reiniciarSubtarefa(idSubtarefa, userId) {
    const response = await fetch('/v2026/api/guia-subtarefa-reset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_subtarefa: idSubtarefa, user_id: userId })
    });
    const data = await response.json();
    if (!response.ok || !(data?.ok === true || data?.success === true)) {
        throw new Error(data?.message || 'Não foi possível reiniciar a subtarefa.');
    }
    return data;
}

function localizarContextoGuia(btn) {
    if (!btn) return { row: null, container: null };
    const containerDirect = btn.closest('[data-subtasks]');
    let row = containerDirect?.closest('[data-id]') ?? null;

    if (!row) {
        let candidate = btn.closest('[data-id]');
        while (candidate) {
            if (candidate.querySelector('[data-subtasks]')) {
                row = candidate;
                break;
            }
            candidate = candidate.parentElement ? candidate.parentElement.closest('[data-id]') : null;
        }
    }

    const container = containerDirect || (row ? row.querySelector('[data-subtasks]') : null);
    return { row, container };
}

/**
 * Event listener para botões de mudança de status nas subtarefas
 * Exemplo de uso no HTML:
 * <button data-subtarefa-action="iniciar" data-subtarefa-id="123" data-user-id="jorge.reis">
 *   Iniciar
 * </button>
 */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-subtarefa-action]');
    if (!btn) return;

    const action = btn.dataset.subtarefaAction;
    const userId = obterUserIdAtual(btn) || 'sistema';

    if (action === 'create-subtask') {
        const item = btn.closest('.subtask-item');
        const { row, container } = localizarContextoGuia(btn);
        const hiddenId = document.getElementById('input-id-guia')?.value || document.body?.dataset?.idGuia || '';
        const idGuia = (row?.dataset?.id || item?.dataset?.idGuia || container?.dataset?.idGuia || hiddenId).trim();
        const codigo = (btn.dataset.subtarefaCodigo || item?.dataset?.codigo || '').trim();

        if (!idGuia || !codigo) {
            showToast('warning', 'Atenção', 'Não foi possível identificar a guia ou a subtarefa.');
            return;
        }

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fi fi-rr-spinner"></i> Criando...';

        criarSubtarefa(idGuia, codigo, userId)
            .then(async () => {
                showToast('success', 'Subtarefa criada', 'A subtarefa foi criada com sucesso.');
                if (row) {
                    if (container) container.dataset.loaded = '0';
                    await carregarSubtarefas(row);
                    const resumo = obterResumoPaiPorSubtarefas(row);
                    aplicarResumoPai(row, resumo);
                }
            })
            .catch((err) => {
                console.error('Erro ao criar subtarefa:', err);
                showToast('error', 'Erro ao criar subtarefa', err.message || 'Falha ao criar subtarefa.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });

        return;
    }

    if (action === 'delete-subtask') {
        const idSubtarefa = parseInt(btn.dataset.subtarefaId);
        if (!idSubtarefa) return;
        if (!confirm('Deseja realmente excluir esta subtarefa?')) return;
        const { row, container } = localizarContextoGuia(btn);

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fi fi-rr-spinner"></i>';

        excluirSubtarefa(idSubtarefa, userId)
            .then(async () => {
                showToast('success', 'Subtarefa excluída', 'A subtarefa foi removida.');
                if (row) {
                    if (container) container.dataset.loaded = '0';
                    await carregarSubtarefas(row);
                }
                if (typeof window.carregarGuia === 'function') {
                    window.carregarGuia();
                }
            })
            .catch((err) => {
                console.error('Erro ao excluir subtarefa:', err);
                showToast('error', 'Erro ao excluir subtarefa', err.message || 'Falha ao excluir subtarefa.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        return;
    }

    if (action === 'reset-subtask') {
        const idSubtarefa = parseInt(btn.dataset.subtarefaId);
        if (!idSubtarefa) return;
        if (!confirm('Deseja reiniciar esta subtarefa? As informações serão apagadas.')) return;
        const { row, container } = localizarContextoGuia(btn);

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fi fi-rr-spinner"></i>';

            reiniciarSubtarefa(idSubtarefa, userId)
                .then(async () => {
                    showToast('success', 'Subtarefa reiniciada', 'As informações foram apagadas.');
                    if (row) {
                        if (container) container.dataset.loaded = '0';
                        await carregarSubtarefas(row);
                        if (typeof window.carregarGuia === 'function') {
                            window.carregarGuia();
                        }
                    }
                })
            .catch((err) => {
                console.error('Erro ao reiniciar subtarefa:', err);
                showToast('error', 'Erro ao reiniciar subtarefa', err.message || 'Falha ao reiniciar subtarefa.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        return;
    }

    const idSubtarefa = parseInt(btn.dataset.subtarefaId);

    if (!idSubtarefa) {
        console.error('ID da subtarefa não encontrado');
        return;
    }

    // Determinar o novo status
    let novoStatus;
    let extraPayload = null;

    if (action === 'update-status') {
        // Para ação genérica, determinar o próximo status baseado no status atual
        const currentItem = btn.closest('.subtask-item');
        const codigo = currentItem?.dataset?.codigo || '';
        const currentStatus = btn.dataset.currentStatus || currentItem?.dataset?.status || 'A fazer';

        if (codigo === 'calcular-pena-multa') {
            const inputDataFato = currentItem?.querySelector('[data-role="data-fato"]')?.value || '';
            const inputDiasMulta = currentItem?.querySelector('[data-role="dias-multa"]')?.value || '';

            const dataFatoAtual = hasValidDateValue(inputDataFato)
                ? inputDataFato
                : (currentItem?.dataset?.dataFato || '');
            const diasMultaAtual = hasPositiveNumericValue(inputDiasMulta)
                ? parseNumericValue(inputDiasMulta)
                : parseNumericValue(currentItem?.dataset?.diasMulta || currentItem?.dataset?.baseCalculoDias);

            if (!hasValidDateValue(dataFatoAtual) || !(diasMultaAtual !== null && diasMultaAtual > 0)) {
                showToast('warning', 'Dados pendentes', 'Preencha data do fato e quantidade de dias-multa para realizar o cálculo.');
                return;
            }

            const salarioAtual = parseNumericValue(currentItem?.dataset?.salarioMinimo);
            const valorUnitarioAtual = parseNumericValue(currentItem?.dataset?.valorUnitario)
                ?? ((salarioAtual !== null && salarioAtual > 0) ? (salarioAtual / 30) : null);
            const valorMultaAtual = parseNumericValue(currentItem?.dataset?.valorMulta)
                ?? ((valorUnitarioAtual !== null && valorUnitarioAtual > 0) ? (valorUnitarioAtual * diasMultaAtual) : null);

            currentItem.dataset.dataFato = dataFatoAtual;
            currentItem.dataset.diasMulta = String(diasMultaAtual);
            if (valorUnitarioAtual !== null) currentItem.dataset.valorUnitario = String(valorUnitarioAtual.toFixed(2));
            if (valorMultaAtual !== null) currentItem.dataset.valorMulta = String(valorMultaAtual.toFixed(2));

            extraPayload = {
                calculo: {
                    data_fato: dataFatoAtual,
                    dias_multa: diasMultaAtual,
                    salario_minimo_vigente: salarioAtual,
                    valor_unitario: valorUnitarioAtual,
                    valor_multa: valorMultaAtual
                }
            };

            const temDadosCalculo = hasValidDateValue(dataFatoAtual) && (valorMultaAtual !== null && valorMultaAtual > 0);
            novoStatus = temDadosCalculo ? 'Concluída' : 'Em andamento';

            if (!temDadosCalculo) {
                showToast('info', 'Cálculo parcial salvo', 'Dados básicos foram salvos. Informe salário/base para confirmar o cálculo.');
            }
        } else {
            novoStatus = getNextStatusForSubtask(codigo, currentStatus);
        }
    } else {
        // Mapear ações específicas para status
        const statusMap = {
            'iniciar': 'Em andamento',
            'concluir': 'Concluída',
            'pendente': 'A fazer',
            'pausar': 'Pausada',
            'cancelar-multa': 'Concluída'
        };
        novoStatus = statusMap[action];
        if (!novoStatus) {
            console.error('Ação desconhecida:', action);
            return;
        }

        if (action === 'cancelar-multa') {
            const confirmar = confirm('Confirmar cancelamento desta etapa por não haver pena de multa na condenação?');
            if (!confirmar) return;
        }
    }

    // Validação de sequência: verificar se tarefa anterior está concluída
    const currentItem = btn.closest('.subtask-item');
    if (currentItem && (novoStatus === 'Em andamento' || novoStatus === 'Concluída' || novoStatus === 'Concluído')) {
        const allItems = Array.from(currentItem.parentElement.querySelectorAll('.subtask-item'));
        const currentIndex = allItems.indexOf(currentItem);
        
        if (currentIndex > 0) {
            const previousItem = allItems[currentIndex - 1];
            const previousStatusRaw = previousItem.dataset.status || 'A fazer';
            const previousStatus = previousStatusRaw
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
            
            // Verificar se tarefa anterior ainda está "A fazer" ou pendente
            const statusPendentes = [
                'a fazer',
                'aguardando assinatura',
                'aguardando envio',
                'aguardando juntada',
                'aguardando protocolo',
                'aguardando liberacao nos autos',
                'refazer'
            ];
            if (statusPendentes.includes(previousStatus)) {
                showToast('warning', 'Ação bloqueada', 
                    'Você precisa movimentar a tarefa anterior antes de avançar esta.', 
                    'Complete as tarefas na ordem sequencial.');
                return;
            }
        }
    }

    // Desabilitar botão durante requisição
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fi fi-rr-spinner"></i> Processando...';

    // Atualizar status
    atualizarStatusSubtarefa(idSubtarefa, novoStatus, userId, 
        async (data) => {
            // Sucesso: atualizar visualmente de forma instantânea
            
            const item = btn.closest('.subtask-item');
            const row = item ? item.closest('.row.table-row[data-id]') : null;
            
            if (item) {
                // 1. Atualizar dataset do item
                item.dataset.status = novoStatus;
                
                // 2. Atualizar o dot (indicador visual)
                const dot = item.querySelector('.subtask-dot');
                if (dot) {
                    dot.className = 'subtask-dot';
                    dot.classList.add(statusKey(novoStatus));
                }
                
                // 3. Atualizar o botão de status
                btn.dataset.currentStatus = novoStatus;
                btn.textContent = novoStatus;
                btn.className = 'subtask-status-btn';
                btn.classList.add(statusKey(novoStatus));

                // 3.1 Atualizar título e detalhes imediatamente
                atualizarTituloSubtarefa(item);
                if (typeof updateTimelineMetrics === 'function') {
                    updateTimelineMetrics();
                }

                if (row) {
                    atualizarResumoPaiDaLinha(row);
                }
                
                // Desabilitar botão se tarefa foi concluída
                if (novoStatus === 'Concluída' || novoStatus === 'Concluído') {
                    btn.disabled = true;
                    btn.style.cursor = 'not-allowed';
                    btn.style.opacity = '0.6';
                }
                
                // 4. Recarregar subtarefas para obter dados atualizados (timestamps, etc)
                if (row) {
                    const idGuia = parseInt(row.dataset.id);
                    const container = row.querySelector('[data-subtasks]');
                    
                    if (container && idGuia) {
                        // Marcar como não carregado para forçar reload
                        container.dataset.loaded = '0';
                        
                        // Recarregar subtarefas
                        await carregarSubtarefas(row);
                        
                        // Atualizar barra de progresso e resumo do pai
                        setTimeout(() => {
                            atualizarResumoPaiDaLinha(row);
                            
                            // Forçar re-renderização de métricas
                            if (typeof updateTimelineMetrics === 'function') {
                                updateTimelineMetrics();
                            }
                        }, 300);
                        
                        // Calcular e atualizar status da guia principal
                        const resumo = obterResumoPaiPorSubtarefas(row);
                        await atualizarStatusGuiaPrincipal(idGuia, resumo.status, userId);
                        autoAlocarUsuarioParaGuia(row.dataset.id || String(idGuia)).catch(() => {});
                        aplicarResumoPai(row, resumo);
                        
                        // Toast informativo sobre atualização da guia
                        if (window.Toast) {
                            Toast.show({
                                type: 'info',
                                title: 'Guia atualizada',
                                message: `Status da guia: ${resumo.status}`,
                                details: 'Status atualizado automaticamente baseado nas subtarefas.',
                                duration: 4000
                            });
                        }
                    }
                }
            }

            // Restaurar estado do botão mantendo novo texto
            if (novoStatus !== 'Concluída' && novoStatus !== 'Concluído') {
                btn.disabled = false;
                btn.style.cursor = '';
                btn.style.opacity = '';
            }
        },
        (error) => {
            // Erro: restaurar botão
            btn.disabled = false;
            btn.innerHTML = originalText;
        },
        extraPayload
    );
});
