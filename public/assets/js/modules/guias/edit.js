(function mostrarToastQueryParams() {
    const url = new URL(window.location.href);
    const errorParam = url.searchParams.get('error');
    const okParam = url.searchParams.get('ok');

    if (errorParam) {
        showToast('error', 'Erro ao editar', decodeURIComponent(errorParam), 'Revise os campos e tente novamente.');
    } else if (okParam) {
        showToast('success', 'Guia atualizada', 'A guia foi salva com sucesso.', '');
    }
})();

function preencherDadosUsuarioLogado() {
    const inputUserCpf = document.getElementById('input-user-cpf');
    const inputUserUsuario = document.getElementById('input-user-usuario');
    const inputUserSetor = document.getElementById('input-user-setor');
    const inputUserNucleo = document.getElementById('input-user-nucleo');

    if (!inputUserCpf || !inputUserUsuario || !inputUserSetor || !inputUserNucleo) return;

    const raw = sessionStorage.getItem('UserLogin');
    if (!raw) {
        console.warn('UserLogin não encontrado no sessionStorage.');
        return;
    }

    try {
        const arr = JSON.parse(raw);
        const user = Array.isArray(arr) ? arr[0] : null;
        const cpf = user?.cpf || '';
        const usuario = user?.usuario || '';
        const setor = user?.setor || '';
        const nucleo = user?.nucleo || '';

        inputUserCpf.value = String(cpf).trim();
        inputUserUsuario.value = String(usuario).trim();
        inputUserSetor.value = String(setor).trim();
        inputUserNucleo.value = String(nucleo).trim();
    } catch (err) {
        console.warn('Falha ao ler UserLogin do sessionStorage:', err);
    }
}

preencherDadosUsuarioLogado();

const idGuia = document.body?.dataset?.idGuia || document.getElementById('input-id-guia')?.value || '';

const btCancelaGuia = document.getElementById('bt-cancela-guia');
if (btCancelaGuia) {
    btCancelaGuia.addEventListener('click', () => {
        window.location.href = './guias3.php';
    });
}

// Excluir guia
const btExcluirGuia = document.getElementById('bt-excluir-guia');
if (btExcluirGuia) {
    btExcluirGuia.addEventListener('click', async () => {
        if (!idGuia) {
            showToast('error', 'Erro', 'ID da guia não encontrado.');
            return;
        }
        const confirma = confirm('Deseja realmente excluir esta guia? Esta ação não pode ser desfeita.');
        if (!confirma) return;

        const formData = new FormData();
        formData.append('id_guia', idGuia);
        formData.append('user_cpf', document.getElementById('input-user-cpf')?.value || '');
        formData.append('user_usuario', document.getElementById('input-user-usuario')?.value || '');
        formData.append('user_setor', document.getElementById('input-user-setor')?.value || '');
        formData.append('user_nucleo', document.getElementById('input-user-nucleo')?.value || '');

        try {
            const resp = await fetch('../v2026/api/guia-delete.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (data?.ok) {
                showToast('success', 'Guia excluída', 'Redirecionando...');
                setTimeout(() => window.location.href = './guias3.php', 800);
            } else {
                showToast('error', 'Erro ao excluir', data?.message || 'Falha ao excluir guia.');
            }
        } catch (err) {
            console.error(err);
            showToast('error', 'Erro ao excluir', 'Falha inesperada.');
        }
    });
}

// Máscaras simples para CPF e telefone (aplica na digitação)
function maskCPF(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
        .slice(0, 14);
}

function maskPhone(value) {
    return value
        .replace(/\D/g, '')
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2')
        .slice(0, 15);
}

function capitalizarNomeReu(nome) {
    const excecoes = new Set(['de', 'da', 'do', 'das', 'dos', 'e']);
    const palavras = nome.toLowerCase().split(' ');
    return palavras.map((palavra, index) => {
        if (index === 0 || !excecoes.has(palavra)) {
            return palavra.charAt(0).toUpperCase() + palavra.slice(1);
        }
        return palavra;
    }).join(' ');
}

function validarCpfTelefone() {
    const cpfDigits = inputCpf ? normalizarNumero(inputCpf.value) : '';
    const telDigits = inputTel ? normalizarNumero(inputTel.value) : '';

    if (cpfDigits && cpfDigits.length !== 11) {
        showToast('warning', 'CPF inválido', 'Informe um CPF com 11 dígitos.');
        inputCpf?.focus();
        return false;
    }

    if (telDigits && (telDigits.length < 10 || telDigits.length > 11)) {
        showToast('warning', 'Telefone inválido', 'Informe um telefone com DDD (10 ou 11 dígitos).');
        inputTel?.focus();
        return false;
    }

    return true;
}

const inputCpf = document.getElementById('input-cpf');
const inputTel = document.getElementById('input-telefone');
if (inputCpf) {
    inputCpf.addEventListener('input', (e) => {
        e.target.value = maskCPF(e.target.value);
    });
}
if (inputTel) {
    inputTel.addEventListener('input', (e) => {
        e.target.value = maskPhone(e.target.value);
    });
}

const inputClasse = document.getElementById('input-classe-display');
const inputClasseSelect = document.getElementById('input-classe-select');
const inputClasseCod = document.getElementById('input-classe-cod');
const inputClasseDesc = document.getElementById('input-classe-desc');
const inputReuSelect = document.getElementById('input-reu-select');
const inputReuManual = document.getElementById('input-reu-manual');
const inputReuFinal = document.getElementById('input-reu');
const newReuPanel = document.getElementById('new-reu-panel');
const btCadastrarReuManual = document.getElementById('bt-cadastrar-reu-manual');
const btCancelarReuManual = document.getElementById('bt-cancelar-reu-manual');
const reuInfoWrapper = document.getElementById('existing-reu-info');
const reuInfoCpf = document.getElementById('existing-reu-cpf');
const reuInfoTel = document.getElementById('existing-reu-telefone');
const reuInfoSep = document.querySelector('.reu-info-sep');
const processoLoading = document.getElementById('processo-loading');
const processoError = document.getElementById('processo-error');
const btSalvarGuia = document.getElementById('bt-salvar-guia');
const inputProcesso = document.getElementById('input-processo');
const formEditGuia = document.getElementById('form-edit-guia');
const inputStatus = document.getElementById('input-status');
const summaryProcesso = document.getElementById('summary-processo');
const summaryClasse = document.getElementById('summary-classe');
const summaryStatus = document.getElementById('summary-status');
const inputRegime = document.getElementById('input-regime');
const inputTipo = document.getElementById('input-tipo');
const inputVerificacaoSeeu = document.getElementById('input-verificacao-seeu');
const selectServidor = document.getElementById('select-servidor');
const servidorTags = document.getElementById('allocated-users');
const servidorHiddenInputs = document.getElementById('servidor-hidden-inputs');
const btAddServidor = document.getElementById('bt-add-servidor');
const cardAlocacao = document.getElementById('card-alocacao');
const subtasksContainer = document.querySelector('[data-subtasks]');
const cardSubtarefas = (subtasksContainer && subtasksContainer.closest('.info-card')) || document.getElementById('card-subtarefas');
const subtasksContainerEl = subtasksContainer || (cardSubtarefas ? cardSubtarefas.querySelector('[data-subtasks]') : null);
let subtarefasObserver = null;
const selectPriorityAdd = document.getElementById('select-priority-add');
const btnAddPriority = document.getElementById('btn-add-priority');
const priorityTags = document.getElementById('priority-tags');
const priorityHiddenInputs = document.getElementById('priority-hidden-inputs');
let processoValido = true;
let reuCamposLiberados = true;
let snapshotInicial = '';
let monitorAlteracoesRegistrado = false;
let estadoInicialForm = null;
let novoReuAtivo = false;

function normalizarNumero(valor) {
    return String(valor || '').replace(/\D/g, '');
}

function gerarSnapshotFormulario() {
    if (!formEditGuia) return '';
    const data = new FormData(formEditGuia);
    return JSON.stringify(Array.from(data.entries()));
}

function setProcessoLoading(loading) {
    if (!processoLoading) return;
    processoLoading.style.display = loading ? 'flex' : 'none';
}

function setProcessoError(mostrarErro) {
    if (!processoError) return;
    processoError.style.display = mostrarErro ? 'block' : 'none';

    if (inputProcesso) {
        if (mostrarErro) {
            inputProcesso.style.borderColor = 'var(--v-card-error-color, #d32f2f)';
            inputProcesso.setAttribute('aria-invalid', 'true');
        } else {
            inputProcesso.style.borderColor = '';
            inputProcesso.removeAttribute('aria-invalid');
        }
    }
}

function setBotaoSalvarDisabled(disabled) {
    if (!btSalvarGuia) return;
    btSalvarGuia.disabled = disabled;
    if (disabled) {
        btSalvarGuia.style.opacity = '0.5';
        btSalvarGuia.style.cursor = 'not-allowed';
        btSalvarGuia.setAttribute('aria-disabled', 'true');
    } else {
        btSalvarGuia.style.opacity = '';
        btSalvarGuia.style.cursor = '';
        btSalvarGuia.removeAttribute('aria-disabled');
    }
}

function prepararSubtarefasContexto() {
    if (!cardSubtarefas) return;
    if (idGuia && !cardSubtarefas.dataset.id) {
        cardSubtarefas.dataset.id = idGuia;
    }
    if (typeof obterUserIdAtual === 'function') {
        const userId = obterUserIdAtual(cardSubtarefas);
        if (userId && !cardSubtarefas.dataset.userId) {
            cardSubtarefas.dataset.userId = userId;
        }
    }
}

function obterResumoSubtarefasSeDisponivel() {
    if (!cardSubtarefas || !subtasksContainerEl) return null;
    if (subtasksContainerEl.dataset.loaded !== '1') return null;
    if (!subtasksContainerEl.querySelector('.subtask-item')) return null;
    if (typeof obterResumoPaiPorSubtarefas !== 'function') return null;
    return obterResumoPaiPorSubtarefas(cardSubtarefas);
}

function registrarObserverSubtarefas() {
    if (!subtasksContainerEl || subtarefasObserver) return;
    subtarefasObserver = new MutationObserver(() => {
        atualizarStatusPrincipal();
    });
    subtarefasObserver.observe(subtasksContainerEl, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-status']
    });
}

function calcularStatusPrincipal() {
    const resumoSubtarefas = obterResumoSubtarefasSeDisponivel();
    if (resumoSubtarefas?.status) return resumoSubtarefas.status;
    const baseRaw = String(summaryStatus?.dataset?.statusBase || inputStatus?.value || '').toLowerCase();
    if (baseRaw.includes('conclu')) return 'Concluída';
    const regimeOk = inputRegime && inputRegime.value && inputRegime.value !== '0';
    const tipoOk = inputTipo && inputTipo.value && inputTipo.value !== '0';
    return (regimeOk && tipoOk) ? 'Em andamento' : 'Não iniciada';
}

function atualizarStatusPrincipal() {
    if (!summaryStatus) return;
    const label = calcularStatusPrincipal();
    summaryStatus.textContent = label;
    summaryStatus.classList.remove('done', 'in-progress', 'pending');
    if (label === 'Concluída') {
        summaryStatus.classList.add('done');
    } else if (label === 'Em andamento') {
        summaryStatus.classList.add('in-progress');
    } else {
        summaryStatus.classList.add('pending');
    }
}

const TIPOS_GUIA_VALIDOS = [
    'Guia de Execução Definitiva',
    'Guia de Recolhimento Provisória',
    'Guia de Recolhimento Definitiva'
];

function aplicarRegraVerificadoConsta() {
    if (!inputTipo || !inputRegime || !inputVerificacaoSeeu) return;

    const verificacao = String(inputVerificacaoSeeu.value || '').trim();
    const regime = String(inputRegime.value || '').trim();
    const deveAplicar = verificacao === '2';

    const resetOptions = () => {
        Array.from(inputTipo.options).forEach((opt) => {
            if (TIPOS_GUIA_VALIDOS.includes(opt.value)) {
                opt.disabled = false;
            }
        });
    };

    if (!deveAplicar) {
        resetOptions();
        return;
    }

    let tipoDestino = '';
    if (regime === 'Fechado') {
        tipoDestino = 'Guia de Recolhimento Definitiva';
    } else if (regime === 'Semiaberto') {
        tipoDestino = 'Guia de Execução Definitiva';
    } else {
        resetOptions();
        return;
    }

    Array.from(inputTipo.options).forEach((opt) => {
        if (!TIPOS_GUIA_VALIDOS.includes(opt.value)) return;
        opt.disabled = opt.value !== tipoDestino;
    });

    inputTipo.value = tipoDestino;
}

function atualizarResumoGuia(processo, classe, status) {
    const textoProcesso = processo ? String(processo).trim() : '';
    const textoClasse = classe ? String(classe).trim() : '';

    if (summaryProcesso) {
        summaryProcesso.textContent = textoProcesso || 'Processo não informado';
    }
    if (summaryClasse) {
        summaryClasse.textContent = textoClasse || 'Classe não informada';
    }
    atualizarStatusPrincipal();
}

function registrarMonitorAlteracoes() {
    if (!formEditGuia) return;
    if (!monitorAlteracoesRegistrado) {
        const handler = () => {
            const dirty = gerarSnapshotFormulario() !== snapshotInicial;
            setBotaoSalvarDisabled(!dirty || !processoValido);
        };
        formEditGuia.addEventListener('input', handler);
        formEditGuia.addEventListener('change', handler);
        monitorAlteracoesRegistrado = true;
    }
    snapshotInicial = gerarSnapshotFormulario();
    setBotaoSalvarDisabled(true);
}

function validarNumeroProcesso() {
    if (!inputProcesso) return false;

    const rawProcesso = inputProcesso.value.trim();
    const digits = rawProcesso.replace(/\D/g, '');

    if (digits.length === 0) {
        setProcessoError(false);
        processoValido = false;
        setBotaoSalvarDisabled(true);
        return false;
    }

    if (digits.length < 20) {
        setProcessoError(false);
        processoValido = false;
        setBotaoSalvarDisabled(true);
        return false;
    }

    if (digits.length === 20) {
        if (typeof validarDigitoVerificadorCNJ === 'function') {
            const dvOk = validarDigitoVerificadorCNJ(rawProcesso);
            setProcessoError(!dvOk);
            processoValido = dvOk;
            setBotaoSalvarDisabled(!dvOk);
            return dvOk;
        }
    }

    return false;
}

function setClasseEditable(editavel) {
    if (!inputClasse || !inputClasseSelect) return;

    if (editavel) {
        inputClasse.style.display = 'none';
        inputClasseSelect.style.display = '';
        inputClasseSelect.required = true;
        inputClasse.required = false;
    } else {
        inputClasse.style.display = '';
        inputClasseSelect.style.display = 'none';
        inputClasse.required = false;
        inputClasseSelect.required = false;
    }
}

function setReuMode(modo) {
    if (!inputReuSelect || !inputReuManual) return;
    const btAdicionarReu = document.getElementById('bt-adicionar-reu');

    if (!reuCamposLiberados) {
        inputReuSelect.disabled = true;
        if (btAdicionarReu) btAdicionarReu.style.display = 'none';
        if (newReuPanel) newReuPanel.classList.add('is-hidden');
        novoReuAtivo = false;
        atualizarReuFinal();
        return;
    }

    const manual = modo === 'manual';
    novoReuAtivo = manual;
    inputReuSelect.required = !manual;
    inputReuManual.required = manual;

    if (newReuPanel) {
        newReuPanel.classList.toggle('is-hidden', !manual);
    }

    if (btAdicionarReu) {
        btAdicionarReu.style.display = manual ? 'none' : '';
    }

    if (!manual) {
        atualizarDadosReuSelecionado();
    }

    atualizarReuFinal();
}

function dispararAlteracaoFormulario() {
    if (!formEditGuia) return;
    formEditGuia.dispatchEvent(new Event('input', { bubbles: true }));
}

function atualizarBotaoPrioridade() {
    if (!selectPriorityAdd || !btnAddPriority) return;
    const hasValue = String(selectPriorityAdd.value || '').trim() !== '';
    btnAddPriority.disabled = !hasValue;
    btnAddPriority.style.opacity = hasValue ? '' : '0.6';
    btnAddPriority.style.cursor = hasValue ? '' : 'not-allowed';
}

function resetarSelectPrioridade() {
    if (!selectPriorityAdd) return;
    selectPriorityAdd.selectedIndex = 0;
    atualizarBotaoPrioridade();
}

function obterPrioridadesAtuais() {
    if (!priorityHiddenInputs) return [];
    const items = priorityHiddenInputs.querySelectorAll('[data-priority-hidden]');
    return Array.from(items)
        .map((el) => String(el.value || '').trim())
        .filter((v) => v !== '');
}

function obterServidoresAtuais() {
    if (!servidorHiddenInputs) return [];
    const items = Array.from(servidorHiddenInputs.querySelectorAll('[data-servidor-hidden]'));
    return items.map((el) => {
        const id = String(el.value || '').trim();
        const tag = servidorTags?.querySelector(`[data-user="${CSS.escape(id)}"]`);
        const name = String(tag?.dataset?.name || obterNomeServidorPorId(id) || '').trim();
        return { id, name };
    }).filter((item) => item.id !== '');
}

function capturarEstadoFormulario() {
    const regimeVal = inputRegime ? String(inputRegime.value || '').trim() : '';
    const tipoVal = inputTipo ? String(inputTipo.value || '').trim() : '';
    const observacoes = document.getElementById('input-observacoes');
    const verificacaoSeeu = inputVerificacaoSeeu ? String(inputVerificacaoSeeu.value || '').trim() : '';
    const prioridades = obterPrioridadesAtuais().slice().sort();
    const servidores = obterServidoresAtuais().slice().sort((a, b) => a.id.localeCompare(b.id));

    const cpfVal = inputCpf ? normalizarNumero(inputCpf.value) : '';
    const telVal = inputTel ? normalizarNumero(inputTel.value) : '';

    return {
        regime: regimeVal === '0' ? '' : regimeVal,
        tipo: tipoVal === '0' ? '' : tipoVal,
        reu: inputReuFinal ? String(inputReuFinal.value || '').trim() : '',
        cpf: cpfVal,
        telefone: telVal,
        data_fato: inputDataFato ? String(inputDataFato.value || '').trim() : '',
        dias_multa: inputDiasMulta ? String(inputDiasMulta.value || '').trim() : '',
        observacoes: observacoes ? String(observacoes.value || '').trim() : '',
        verificacao_seeu: verificacaoSeeu,
        prioridades,
        servidores
    };
}

function formatarValorCampo(valor) {
    return valor && String(valor).trim() !== '' ? String(valor).trim() : '—';
}

function formatarVerificacaoSeeu(valor) {
    const v = String(valor || '').trim();
    if (v === '2') return 'Verificado - consta';
    if (v === '1') return 'Verificado - não consta';
    if (v === '0') return 'Não verificado';
    if (v === 'verificado-nao-consta') return 'Verificado - não consta';
    if (v === 'verificado-consta') return 'Verificado - consta';
    if (v === 'nao-verificado' || v === '') return 'Não verificado';
    return v;
}

function obterLabelPrioridade(cod) {
    if (cod === 'reu-preso') return 'Réu preso';
    if (!selectPriorityAdd) return cod;
    const opt = selectPriorityAdd.querySelector(`option[value="${CSS.escape(cod)}"]`);
    return opt ? opt.textContent.trim() : cod;
}

function gerarResumoAlteracoes() {
    if (!estadoInicialForm) return null;
    const atual = capturarEstadoFormulario();
    const changes = [];

    if (estadoInicialForm.regime !== atual.regime) {
        changes.push(`Regime: ${formatarValorCampo(estadoInicialForm.regime)} → ${formatarValorCampo(atual.regime)}`);
    }
    if (estadoInicialForm.tipo !== atual.tipo) {
        changes.push(`Tipo: ${formatarValorCampo(estadoInicialForm.tipo)} → ${formatarValorCampo(atual.tipo)}`);
    }
    if (estadoInicialForm.reu !== atual.reu) {
        changes.push(`Réu: ${formatarValorCampo(estadoInicialForm.reu)} → ${formatarValorCampo(atual.reu)}`);
    }
    if (estadoInicialForm.cpf !== atual.cpf) {
        changes.push(`CPF atualizado`);
    }
    if (estadoInicialForm.telefone !== atual.telefone) {
        changes.push(`Telefone atualizado`);
    }
    if (estadoInicialForm.data_fato !== atual.data_fato) {
        changes.push(`Data do fato: ${formatarValorCampo(estadoInicialForm.data_fato)} → ${formatarValorCampo(atual.data_fato)}`);
    }
    if (estadoInicialForm.dias_multa !== atual.dias_multa) {
        changes.push(`Dias-multa: ${formatarValorCampo(estadoInicialForm.dias_multa)} → ${formatarValorCampo(atual.dias_multa)}`);
    }
    if (estadoInicialForm.observacoes !== atual.observacoes) {
        changes.push(`Observações atualizadas`);
    }
    if (estadoInicialForm.verificacao_seeu !== atual.verificacao_seeu) {
        changes.push(`Consulta SEEU: ${formatarVerificacaoSeeu(estadoInicialForm.verificacao_seeu)} → ${formatarVerificacaoSeeu(atual.verificacao_seeu)}`);
    }

    const prevPrior = new Set(estadoInicialForm.prioridades);
    const nowPrior = new Set(atual.prioridades);
    const added = atual.prioridades.filter((p) => !prevPrior.has(p));
    const removed = estadoInicialForm.prioridades.filter((p) => !nowPrior.has(p));
    if (added.length || removed.length) {
        const parts = [];
        if (added.length) parts.push(`+${added.map(obterLabelPrioridade).join(', ')}`);
        if (removed.length) parts.push(`-${removed.map(obterLabelPrioridade).join(', ')}`);
        changes.push(`Prioridades: ${parts.join(' ')}`);
    }

    const prevServ = new Map(estadoInicialForm.servidores.map((s) => [s.id, s.name]));
    const nowServ = new Map(atual.servidores.map((s) => [s.id, s.name]));
    const addedServ = atual.servidores.filter((s) => !prevServ.has(s.id));
    const removedServ = estadoInicialForm.servidores.filter((s) => !nowServ.has(s.id));
    if (addedServ.length || removedServ.length) {
        const parts = [];
        if (addedServ.length) parts.push(`+${addedServ.map((s) => s.name || s.id).join(', ')}`);
        if (removedServ.length) parts.push(`-${removedServ.map((s) => s.name || s.id).join(', ')}`);
        changes.push(`Servidores: ${parts.join(' ')}`);
    }

    if (!changes.length) return null;
    return `Alterações: ${changes.join('; ')}`;
}
function atualizarOpcoesSelectPrioridade() {
    if (!selectPriorityAdd) return;
    const atuais = new Set(obterPrioridadesAtuais());
    const options = Array.from(selectPriorityAdd.options);

    options.forEach((opt) => {
        if (!opt.value) {
            opt.disabled = true;
            opt.hidden = false;
            return;
        }
        if (opt.value === 'reu-preso') {
            opt.disabled = true;
            opt.hidden = true;
            return;
        }
        const usado = atuais.has(opt.value);
        opt.disabled = usado;
        opt.hidden = usado;
    });

    const selected = selectPriorityAdd.options[selectPriorityAdd.selectedIndex];
    if (!selectPriorityAdd.value || (selected && selected.disabled)) {
        selectPriorityAdd.selectedIndex = 0;
    }

    atualizarBotaoPrioridade();
}

function atualizarMensagemSemPrioridade() {
    if (!priorityTags) return;
    const existing = priorityTags.querySelector('.tag-group-empty');
    const hasTags = priorityTags.querySelector('.priority-tag');
    if (hasTags) {
        if (existing) existing.remove();
        return;
    }
    if (!existing) {
        const span = document.createElement('span');
        span.className = 'tag-group-empty';
        span.textContent = 'Nenhuma prioridade cadastrada para este processo.';
        priorityTags.appendChild(span);
    }
}

function setPrioridadeCheckbox(prioridade, checked) {
    const selector = `input[type="checkbox"][name="prioridades[]"][value="${CSS.escape(prioridade)}"]`;
    const checkbox = document.querySelector(selector);
    if (checkbox) checkbox.checked = checked;
}

function ajustarTipoPorRegime(opts = {}) {
    if (!inputRegime || !inputTipo) return;
    const force = Boolean(opts.force);

    const regime = String(inputRegime.value || '');
    const tipoExec = 'Guia de Execução Definitiva';
    const tipoRecProv = 'Guia de Recolhimento Provisória';
    const tipoRecDef = 'Guia de Recolhimento Definitiva';

    const setDisabled = (value, disabled) => {
        const opt = inputTipo.querySelector(`option[value="${CSS.escape(value)}"]`);
        if (opt) opt.disabled = disabled;
    };

    [tipoExec, tipoRecProv, tipoRecDef].forEach((t) => setDisabled(t, false));

    if (regime === 'Semiaberto') {
        setDisabled(tipoRecDef, true);
    } else if (regime === 'Fechado') {
        setDisabled(tipoExec, true);
    }

    if (regime === 'Aberto' && force) {
        inputTipo.value = tipoExec;
    }

    const selected = inputTipo.options[inputTipo.selectedIndex];
    const isMainSelected = [tipoExec, tipoRecProv, tipoRecDef].includes(inputTipo.value);
    if (!inputTipo.value || inputTipo.value === '0' || (selected && selected.disabled && isMainSelected)) {
        const fallback = [tipoExec, tipoRecProv, tipoRecDef].find((t) => {
            const opt = inputTipo.querySelector(`option[value="${CSS.escape(t)}"]`);
            return opt && !opt.disabled;
        });
        if (fallback) inputTipo.value = fallback;
    }
}

function criarTagPrioridade(prioridade, label) {
    const tagClassMap = {
        'reu-preso': 'tag-pill--error',
        'violencia-domestica': 'tag-pill--error',
        'urgente': 'tag-pill--error',
        'deficiencia': 'tag-pill--purple'
    };
    const tagClass = tagClassMap[prioridade] || 'tag-pill--locked';
    const span = document.createElement('span');
    span.className = `tag-pill ${tagClass} priority-tag`;
    span.dataset.priority = prioridade;
    if (prioridade === 'reu-preso') {
        span.textContent = label;
    } else {
        span.innerHTML = `${label} <button type="button" class="priority-remove" aria-label="Remover ${label}">&times;</button>`;
    }
    return span;
}


function atualizarReuInfoHints() {
    if (!reuInfoWrapper || !reuInfoCpf || !reuInfoTel || !reuInfoSep) return;
    const cpfTxt = String(reuInfoCpf.textContent || '').trim();
    const telTxt = String(reuInfoTel.textContent || '').trim();
    const cpfVazio = cpfTxt === '' || cpfTxt === '—';
    const telVazio = telTxt === '' || telTxt === '—';

    const cpfBlock = reuInfoCpf.closest('.reu-info');
    const telBlock = reuInfoTel.closest('.reu-info');

    if (cpfBlock) cpfBlock.style.display = cpfVazio ? 'none' : '';
    if (telBlock) telBlock.style.display = telVazio ? 'none' : '';
    reuInfoSep.style.display = (!cpfVazio && !telVazio) ? '' : 'none';
    reuInfoWrapper.style.display = (cpfVazio && telVazio) ? 'none' : '';
}

function atualizarReuFinal() {
    if (!inputReuFinal) return;
    if (novoReuAtivo && inputReuManual) {
        inputReuFinal.value = inputReuManual.value.trim();
    } else if (inputReuSelect) {
        inputReuFinal.value = inputReuSelect.value.trim();
    }
}

function atualizarDadosReuSelecionado() {
    if (!inputReuSelect || !reuInfoCpf || !reuInfoTel) return;
    if (novoReuAtivo) return;

    const selected = inputReuSelect.options[inputReuSelect.selectedIndex];
    const cpfRaw = selected?.dataset?.cpf ? String(selected.dataset.cpf) : '';
    const telRaw = selected?.dataset?.telefone ? String(selected.dataset.telefone) : '';
    const cpfMasked = cpfRaw ? maskCPF(cpfRaw) : '—';
    const telMasked = telRaw ? maskPhone(telRaw) : '—';

    reuInfoCpf.textContent = cpfMasked;
    reuInfoTel.textContent = telMasked;

    if (inputCpf) inputCpf.value = cpfRaw ? maskCPF(cpfRaw) : '';
    if (inputTel) inputTel.value = telRaw ? maskPhone(telRaw) : '';

    atualizarReuInfoHints();
}

let reusData = [];

function preencherReus(reus) {
    if (!inputReuSelect) return;
    inputReuSelect.innerHTML = '<option value="" selected disabled>Selecione o réu</option>';

    reusData = [];

    reus.forEach((reu) => {
        const nome = typeof reu === 'string' ? reu : (reu.nome || '');
        const statusPrisao = typeof reu === 'object' ? (parseInt(reu.statusPrisao) || 0) : 0;
        const cpf = typeof reu === 'object' ? String(reu.cpf || '').trim() : '';
        const telefone = typeof reu === 'object' ? String(reu.telefone || '').trim() : '';

        if (nome) {
            const opt = document.createElement('option');
            opt.value = nome;
            opt.textContent = nome;
            opt.dataset.statusprisao = statusPrisao;
            if (cpf) opt.dataset.cpf = cpf;
            if (telefone) opt.dataset.telefone = telefone;
            inputReuSelect.appendChild(opt);

            reusData.push({
                nome: nome,
                statusPrisao: statusPrisao,
                cpf: cpf,
                telefone: telefone
            });
        }
    });

    const btAdicionarReu = document.getElementById('bt-adicionar-reu');
    if (btAdicionarReu) {
        btAdicionarReu.style.display = reuCamposLiberados ? '' : 'none';
    }
}

function marcarPrioridades(prioridades) {
    const checks = document.querySelectorAll('input[type="checkbox"][name="prioridades[]"]');
    if (!checks) return;
    const alvo = new Set((prioridades || []).map((p) => String(p).trim().toLowerCase()));
    checks.forEach((chk) => {
        const valor = String(chk.value || '').trim().toLowerCase();
        chk.checked = alvo.has(valor);
    });
}

function controlarCheckboxReuPreso() {
    const checkboxReuPreso = document.querySelector('input[type="checkbox"][value="reu-preso"]');
    if (!checkboxReuPreso) return;

    if (novoReuAtivo) {
        checkboxReuPreso.checked = false;
        checkboxReuPreso.disabled = false;
        const label = checkboxReuPreso.closest('label');
        if (label) {
            label.style.opacity = '';
            label.style.cursor = '';
            label.title = '';
        }
        return;
    }

    const reuSelecionado = inputReuSelect ? inputReuSelect.value : null;

    if (reuSelecionado) {
        const reuInfo = reusData.find(r => r.nome === reuSelecionado);

        if (reuInfo && reuInfo.statusPrisao === 1) {
            checkboxReuPreso.checked = true;
            checkboxReuPreso.disabled = true;

            const label = checkboxReuPreso.closest('label');
            if (label) {
                label.style.opacity = '0.6';
                label.style.cursor = 'not-allowed';
                label.title = 'Marcado automaticamente - Réu consta como preso no sistema';
            }
            return;
        }
    }

    checkboxReuPreso.disabled = false;
    const label = checkboxReuPreso.closest('label');
    if (label) {
        label.style.opacity = '';
        label.style.cursor = '';
        label.title = '';
    }
}

function autocompletarProcessoTJBA() {
    if (!inputProcesso) return false;
    const rawProcesso = inputProcesso.value.trim();
    const digits = rawProcesso.replace(/\D/g, '');

    if (digits.length === 13) {
        const formatted = digits.slice(0, 7) + '-' +
                          digits.slice(7, 9) + '.' +
                          digits.slice(9, 13) + '.8.05.0103';
        inputProcesso.value = formatted;
        return true;
    }

    return false;
}

async function buscarProcessoInfo() {
    if (!inputProcesso) return;

    autocompletarProcessoTJBA();

    const rawProcesso = inputProcesso.value.trim();
    const digits = rawProcesso.replace(/\D/g, '');
    if (digits.length !== 20) return;

    if (!processoValido) {
        return;
    }

    setProcessoLoading(true);
    try {
        const lookupUrl = new URL('../v2026/api/processo-lookup.php', window.location.href);
        lookupUrl.searchParams.set('processo', rawProcesso);

        const resp = await fetch(lookupUrl.toString(), {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin'
        });

        if (!resp.ok) {
            throw new Error(`HTTP ${resp.status}`);
        }

        const data = await resp.json();

        if (!data || data.ok !== true) {
            setClasseEditable(true);
            if (inputClasseCod) inputClasseCod.value = '';
            if (inputClasseDesc) inputClasseDesc.value = '';
            if (inputClasse) inputClasse.value = '';
            setReuMode('manual');
            return;
        }

        if (data.foundClasse && data.classe) {
            if (inputClasse) inputClasse.value = data.classe.descricao || '';
            if (inputClasseCod) inputClasseCod.value = data.classe.codigo || '';
            if (inputClasseDesc) inputClasseDesc.value = data.classe.descricao || '';
            setClasseEditable(false);
        } else {
            if (inputClasse) inputClasse.value = '';
            if (inputClasseCod) inputClasseCod.value = '';
            if (inputClasseDesc) inputClasseDesc.value = '';
            if (inputClasseSelect) inputClasseSelect.value = '';
            setClasseEditable(true);
        }

        const reus = Array.isArray(data.reus) ? data.reus : [];
        if (reus.length > 0) {
            preencherReus(reus);
            setReuMode('select');
            atualizarDadosReuSelecionado();
        } else {
            preencherReus([]);
            setReuMode('manual');
        }

        const prioridades = Array.isArray(data.prioridades) ? data.prioridades : [];
        marcarPrioridades(prioridades);
        atualizarOpcoesSelectPrioridade();

        controlarCheckboxReuPreso();
    } catch (err) {
        console.error('Falha ao consultar processo:', err);
        setClasseEditable(true);
        setReuMode('manual');
    } finally {
        setProcessoLoading(false);
    }
}

// Alocação manual (tags)
function obterNomeServidorPorId(id) {
    if (!selectServidor) return id;
    const opt = selectServidor.querySelector(`option[value="${id}"]`);
    return opt ? opt.textContent : id;
}

function atualizarBotaoServidor() {
    if (!selectServidor || !btAddServidor) return;
    const hasValue = String(selectServidor.value || '').trim() !== '';
    btAddServidor.disabled = !hasValue;
    btAddServidor.style.opacity = hasValue ? '' : '0.6';
    btAddServidor.style.cursor = hasValue ? '' : 'not-allowed';
}

function atualizarOpcoesSelectServidor() {
    if (!selectServidor) return;
    const atuais = new Set(obterServidoresAtuais().map((s) => s.id));
    const options = Array.from(selectServidor.options);

    options.forEach((opt) => {
        if (!opt.value) {
            opt.disabled = true;
            opt.hidden = false;
            return;
        }
        const usado = atuais.has(opt.value);
        opt.disabled = usado;
        opt.hidden = usado;
    });

    const selected = selectServidor.options[selectServidor.selectedIndex];
    if (!selectServidor.value || (selected && selected.disabled)) {
        selectServidor.selectedIndex = 0;
    }

    atualizarBotaoServidor();
}

function atualizarMensagemSemServidor() {
    if (!servidorTags) return;
    const existing = servidorTags.querySelector('.tag-group-empty');
    const hasTags = servidorTags.querySelector('.server-tag');
    if (hasTags) {
        if (existing) existing.remove();
        return;
    }
    if (!existing) {
        const span = document.createElement('span');
        span.className = 'tag-group-empty';
        span.textContent = 'Nenhum servidor alocado para esta guia.';
        servidorTags.appendChild(span);
    }
}

function criarTagServidor(id, nome, locked = false) {
    const span = document.createElement('span');
    span.className = `tag-pill tag-pill--user server-tag${locked ? ' is-locked' : ''}`;
    span.dataset.user = id;
    span.dataset.name = nome;
    span.dataset.locked = locked ? '1' : '0';
    if (locked) {
        span.title = 'Servidor possui tarefa em andamento ou concluída';
        span.textContent = nome;
    } else {
        span.innerHTML = `${nome} <button type="button" class="server-remove" aria-label="Remover ${nome}">×</button>`;
    }
    return span;
}

function adicionarServidor(id, locked = false) {
    if (!id || !servidorTags || !servidorHiddenInputs) return;
    if (servidorTags.querySelector(`[data-user="${id}"]`)) return;
    const nome = obterNomeServidorPorId(id);
    const tag = criarTagServidor(id, nome, locked);
    servidorTags.appendChild(tag);

    if (!servidorHiddenInputs.querySelector(`[data-servidor-hidden="${CSS.escape(id)}"]`)) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'servidores[]';
        hidden.value = id;
        hidden.dataset.servidorHidden = id;
        servidorHiddenInputs.appendChild(hidden);
    }

    atualizarMensagemSemServidor();
    atualizarOpcoesSelectServidor();
}

if (servidorTags) {
    servidorTags.addEventListener('click', (e) => {
        const btn = e.target.closest('.server-remove');
        if (!btn) return;
        const tag = btn.closest('.server-tag');
        const id = tag?.dataset?.user || '';
        if (!id) return;
        if (tag?.dataset?.locked === '1') {
            showToast('warning', 'Atenção', 'Servidor possui tarefa em andamento ou concluída e não pode ser removido.');
            return;
        }
        if (tag) tag.remove();
        if (servidorHiddenInputs) {
            const hidden = servidorHiddenInputs.querySelector(`[data-servidor-hidden="${CSS.escape(id)}"]`);
            if (hidden) hidden.remove();
        }
        atualizarMensagemSemServidor();
        atualizarOpcoesSelectServidor();
        dispararAlteracaoFormulario();
    });
}

if (btAddServidor && selectServidor) {
    btAddServidor.addEventListener('click', () => {
        if (btAddServidor.disabled) return;
        const id = selectServidor.value;
        if (!id) return;
        adicionarServidor(id);
        dispararAlteracaoFormulario();
    });
}

function obterPerfisUsuario() {
    try {
        const raw = sessionStorage.getItem('UserLogin');
        if (!raw) return [];
        const arr = JSON.parse(raw);
        const user = Array.isArray(arr) ? arr[0] : null;
        const nucleo = String(user?.nucleo || '').toLowerCase();
        return nucleo
            .split(',')
            .map((p) => p.trim())
            .filter((p) => p !== '');
    } catch (e) {
        return [];
    }
}

function controlarBotaoExcluir() {
    if (!btExcluirGuia) return;
    const perfis = obterPerfisUsuario();
    const permitidos = new Set(['admin', 'diretora', 'magistrada']);
    const autorizado = perfis.some((p) => permitidos.has(p));
    btExcluirGuia.style.display = autorizado ? '' : 'none';
}

function controlarVisibilidadeAlocacao() {
    if (!cardAlocacao) return;
    const perfis = obterPerfisUsuario();
    const permitidos = new Set(['admin', 'diretora', 'magistrada']);
    const autorizado = perfis.some((p) => permitidos.has(p));
    cardAlocacao.style.display = autorizado ? '' : 'none';
}

function aplicarRegrasAlocacao() {
    const perfis = obterPerfisUsuario();
    const permitidos = new Set(['admin', 'diretora', 'magistrada']);
    const podeEscolher = perfis.some((p) => permitidos.has(p));
    const ehServidor = perfis.includes('servidor');
    const somenteServidor = ehServidor && perfis.length === 1;

    if (!podeEscolher) {
        if (btAddServidor) btAddServidor.style.display = 'none';
        if (selectServidor) selectServidor.disabled = true;

        if (somenteServidor) {
            const userId = (document.getElementById('input-user-cpf')?.value || '').trim();
            if (userId) adicionarServidor(userId, true);
        }
    }
}

// Controle automático do checkbox "calcular-pena-multa"
// Renderização dinâmica do bloco da subtarefa 'Cálculo da pena de multa' na edição
function renderBlocoCalculoPenaMultaEdit(item) {
    const temCalculo = !!(item.valor_multa > 0 && item.salario_minimo_vigente > 0 && item.base_calculo_dias > 0);
    if (!temCalculo) {
        return `
            <div class="subtask-fields">
                <label>Data do fato <input type="date" class="input-data-fato" value="${item.data_fato || ''}"></label>
                <label>Dias-multa <input type="number" class="input-dias-multa" min="0" step="1" value="${item.dias_multa || ''}"></label>
                <button type="button" class="btn-pular-etapa">Pular etapa</button>
                <span class="pena-multa-resultado"></span>
            </div>
        `;
    } else {
        return `
            <div class="subtask-fields">
                <div><b>Data do fato:</b> ${item.data_fato || '-'} · <b>Salário mínimo:</b> R$ ${(item.salario_minimo_vigente || 0).toLocaleString('pt-BR', {minimumFractionDigits:2})}</div>
                <div><b>Base:</b> ${item.base_calculo_dias} dias-multa · <b>Valor unitário:</b> R$ ${(item.valor_unitario || 0).toLocaleString('pt-BR', {minimumFractionDigits:2})}</div>
                <div><b>Total:</b> <strong>R$ ${(item.valor_multa || 0).toLocaleString('pt-BR', {minimumFractionDigits:2})}</strong></div>
                <div style="color: #2e7d32; font-size: 12px; margin-top: 4px;">Calculado automaticamente pelo sistema</div>
            </div>
        `;
    }
}
const inputDataFato = document.getElementById('input-data-fato');
const inputDiasMulta = document.getElementById('input-dias-multa');
const checkCalcularPena = document.getElementById('check-calcular-pena-multa');
const btnPularEtapa = document.getElementById('btn-pular-etapa');
const valorMultaSpan = (() => {
    let el = document.getElementById('valor-multa-span');
    if (!el) {
        el = document.createElement('span');
        el.id = 'valor-multa-span';
        el.style.marginLeft = '12px';
        el.style.fontWeight = 'bold';
        const ref = inputDiasMulta?.parentElement;
        if (ref) ref.appendChild(el);
    }
    return el;
})();

function sincronizarHiddenCalcularPena() {
    if (!formEditGuia || !checkCalcularPena) return;

    let hidden = document.getElementById('hidden-calcular-pena-multa');
    const precisaHidden = checkCalcularPena.checked && checkCalcularPena.disabled;

    if (precisaHidden) {
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = 'hidden-calcular-pena-multa';
            hidden.name = 'subtarefas[]';
            hidden.value = 'calcular-pena-multa';
            formEditGuia.appendChild(hidden);
        }
    } else if (hidden) {
        hidden.remove();
    }
}

function atualizarCheckCalcularPena() {
    if (!inputDataFato || !inputDiasMulta || !checkCalcularPena) return;
    const dataPreenchida = inputDataFato.value.trim() !== '';
    const multaPreenchida = inputDiasMulta.value.trim() !== '';
    if (dataPreenchida && multaPreenchida) {
        checkCalcularPena.checked = true;
        checkCalcularPena.disabled = true;
        if (btnPularEtapa) btnPularEtapa.style.display = 'none';
        calcularPenaMultaAutomatico();
    } else {
        checkCalcularPena.checked = false;
        checkCalcularPena.disabled = false;
        if (btnPularEtapa) btnPularEtapa.style.display = '';
        valorMultaSpan.textContent = '';
    }
    sincronizarHiddenCalcularPena();
}

async function calcularPenaMultaAutomatico() {
    const dias = parseNumericValue(inputDiasMulta.value);
    const data = (inputDataFato?.value || '').trim();
    if (!dias || dias <= 0 || !data) {
        valorMultaSpan.textContent = '';
        return;
    }
    // Buscar salário mínimo via API
    let salario = 0;
    try {
        const resp = await fetch(`../v2026/api/salario-minimo.php?data=${encodeURIComponent(data)}`);
        const json = await resp.json();
        salario = parseFloat(json?.salario_minimo || 0);
    } catch (e) { salario = 0; }
    if (!salario || salario <= 0) {
        valorMultaSpan.textContent = 'Salário mínimo não encontrado';
        return;
    }
    const valorUnitario = salario / 30;
    const valorMulta = valorUnitario * dias;
    valorMultaSpan.textContent = `Valor da multa: R$ ${valorMulta.toLocaleString('pt-BR', {minimumFractionDigits:2})}`;
    // Atualizar backend/status
    const calcularItem = document.querySelector('[data-subtasks] .subtask-item[data-codigo="calcular-pena-multa"]');
    if (!calcularItem) return;
    const subtaskId = parseInt(calcularItem.dataset.id, 10);
    if (!subtaskId) return;
    const userId = obterUserIdAtual() || 'sistema';
    const extraPayload = {
        calculo: {
            data_fato: data,
            dias_multa: dias,
            salario_minimo_vigente: salario,
            valor_unitario: valorUnitario,
            valor_multa: valorMulta
        }
    };
    const container = calcularItem.closest('[data-subtasks]');
    const row = container?.closest('[data-id]');
    await atualizarStatusSubtarefa(subtaskId, 'Concluída', userId,
        async () => {
            if (row) {
                if (container) container.dataset.loaded = '0';
                await carregarSubtarefas(row);
            }
        },
        (err) => {
            console.error('Erro ao calcular pena de multa automaticamente', err);
        },
        extraPayload
    );
}

if (inputDataFato) {
    inputDataFato.addEventListener('change', () => {
        const data = (inputDataFato.value || '').trim();
        if (data && typeof window.atualizarSalarioMinimoAutomatico === 'function') {
            window.atualizarSalarioMinimoAutomatico(data);
        }
        atualizarCheckCalcularPena();
    });
}

if (inputDiasMulta) {
    inputDiasMulta.addEventListener('change', async () => {
        const dias = parseNumericValue(inputDiasMulta.value);
        const data = (inputDataFato?.value || '').trim();
        const calcularItem = document.querySelector('[data-subtasks] .subtask-item[data-codigo="calcular-pena-multa"]');
        if (!dias || dias <= 0 || !data || !calcularItem) return;

        let salario = parseNumericValue(calcularItem.dataset.salarioMinimo);
        if (!salario) {
            salario = await window.atualizarSalarioMinimoAutomatico(data);
        }
        if (!salario || salario <= 0) return;

        const valorUnitario = salario / 30;
        const valorMulta = valorUnitario * dias;

        const subtaskId = parseInt(calcularItem.dataset.id, 10);
        if (!subtaskId) return;

        const userId = obterUserIdAtual() || 'sistema';
        const extraPayload = {
            calculo: {
                data_fato: data,
                dias_multa: dias,
                salario_minimo_vigente: salario,
                valor_unitario: valorUnitario,
                valor_multa: valorMulta
            }
        };

        const container = calcularItem.closest('[data-subtasks]');
        const row = container?.closest('[data-id]');

        await atualizarStatusSubtarefa(subtaskId, 'Concluída', userId,
            async () => {
                if (row) {
                    if (container) container.dataset.loaded = '0';
                    await carregarSubtarefas(row);
                }
            },
            (err) => {
                console.error('Erro ao calcular pena de multa automaticamente', err);
            },
            extraPayload
        );
        atualizarCheckCalcularPena();
    });
}

// Botão "Pular etapa" (Sem pena de multa)
if (btnPularEtapa) {
    btnPularEtapa.addEventListener('click', async () => {
        const calcularItem = document.querySelector('[data-subtasks] .subtask-item[data-codigo="calcular-pena-multa"]');
        if (!calcularItem) return;
        const subtaskId = parseInt(calcularItem.dataset.id, 10);
        if (!subtaskId) return;
        const userId = obterUserIdAtual() || 'sistema';
        await atualizarStatusSubtarefa(subtaskId, 'Concluída', userId, async () => {
            calcularItem.style.display = 'none';
        });
        if (btnPularEtapa) btnPularEtapa.style.display = 'none';
        if (inputDataFato) inputDataFato.value = '';
        if (inputDiasMulta) inputDiasMulta.value = '';
        valorMultaSpan.textContent = '';
        atualizarCheckCalcularPena();
    });
}
function validarAnoDataFato() {
    if (!inputDataFato || !inputProcesso) return;

    const dataFato = inputDataFato.value;
    const numeroProcesso = inputProcesso.value.trim();

    if (dataFato && numeroProcesso) {
        const anoDataFato = parseInt(dataFato.split('-')[0]);
        const matchAnoProcesso = numeroProcesso.match(/\d{4}\.\d/);

        if (matchAnoProcesso) {
            const anoProcesso = parseInt(matchAnoProcesso[0].substring(0, 4));

            if (anoDataFato > anoProcesso) {
                showToast('warning', 'Atenção', 'O ano da data do fato não pode ser superior ao ano do processo.');
                inputDataFato.value = '';
                return false;
            }
        }
    }
    return true;
}

if (inputDataFato) {
    inputDataFato.addEventListener('input', atualizarCheckCalcularPena);
    inputDataFato.addEventListener('change', () => {
        if (validarAnoDataFato()) {
            atualizarCheckCalcularPena();
        }
    });
}
if (inputDiasMulta) {
    inputDiasMulta.addEventListener('input', atualizarCheckCalcularPena);
    inputDiasMulta.addEventListener('change', atualizarCheckCalcularPena);
}

if (inputProcesso) {
    inputProcesso.addEventListener('input', validarNumeroProcesso);
    inputProcesso.addEventListener('blur', validarNumeroProcesso);
    inputProcesso.addEventListener('change', buscarProcessoInfo);
    inputProcesso.addEventListener('blur', buscarProcessoInfo);
}

if (inputClasseSelect) {
    inputClasseSelect.addEventListener('change', () => {
        const selectedOption = inputClasseSelect.options[inputClasseSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            if (inputClasseCod) inputClasseCod.value = selectedOption.value;
            if (inputClasseDesc) inputClasseDesc.value = selectedOption.dataset.desc || selectedOption.text;
        } else {
            if (inputClasseCod) inputClasseCod.value = '';
            if (inputClasseDesc) inputClasseDesc.value = '';
        }
        atualizarResumoGuia(inputProcesso?.value || '', inputClasse?.value || '', inputStatus?.value || '');
    });
}

if (inputRegime) {
    inputRegime.addEventListener('change', () => {
        ajustarTipoPorRegime({ force: true });
        aplicarRegraVerificadoConsta();
        atualizarStatusPrincipal();
    });
}

if (inputTipo) {
    inputTipo.addEventListener('change', atualizarStatusPrincipal);
}

if (inputReuSelect) {
    inputReuSelect.addEventListener('change', () => {
        atualizarReuFinal();
        atualizarDadosReuSelecionado();
        controlarCheckboxReuPreso();
    });
}

if (inputVerificacaoSeeu) {
    inputVerificacaoSeeu.addEventListener('change', () => {
        aplicarRegraVerificadoConsta();
    });
}
if (inputReuManual) {
    inputReuManual.addEventListener('input', atualizarReuFinal);
    inputReuManual.addEventListener('blur', () => {
        const valorAtual = inputReuManual.value.trim();
        if (valorAtual !== '') {
            inputReuManual.value = capitalizarNomeReu(valorAtual);
            atualizarReuFinal();
        }
        controlarCheckboxReuPreso();
    });
}

const btAdicionarReu = document.getElementById('bt-adicionar-reu');
if (btAdicionarReu) {
    btAdicionarReu.addEventListener('click', () => {
        setReuMode('manual');
        if (inputReuManual) {
            inputReuManual.value = '';
        }
        if (inputCpf) inputCpf.value = '';
        if (inputTel) inputTel.value = '';
        controlarCheckboxReuPreso();
        if (inputReuManual) {
            inputReuManual.focus();
        }
    });
}

if (btCadastrarReuManual) {
    btCadastrarReuManual.addEventListener('click', () => {
        if (!inputReuManual) return;
        setReuMode('manual');

        const nome = inputReuManual.value.trim();
        if (!nome) {
            showToast('warning', 'Atenção', 'Informe o nome do réu para cadastrar.');
            inputReuManual.focus();
            return;
        }

        inputReuManual.value = capitalizarNomeReu(nome);

        if (!validarCpfTelefone()) {
            return;
        }

        atualizarReuFinal();
        if (reuInfoCpf) reuInfoCpf.textContent = inputCpf?.value || '—';
        if (reuInfoTel) reuInfoTel.textContent = inputTel?.value || '—';
        atualizarReuInfoHints();
        controlarCheckboxReuPreso();
        dispararAlteracaoFormulario();
    });
}

if (btCancelarReuManual) {
    btCancelarReuManual.addEventListener('click', () => {
        setReuMode('select');
        atualizarReuFinal();
        controlarCheckboxReuPreso();
        atualizarDadosReuSelecionado();
        dispararAlteracaoFormulario();
    });
}

// Validação no submit
if (formEditGuia) {
    formEditGuia.addEventListener('submit', (evt) => {
        sincronizarHiddenCalcularPena();
        const processo = document.getElementById('input-processo');
        const tipo = document.getElementById('input-tipo');
        const regime = document.getElementById('input-regime');
        atualizarReuFinal();
        const reuHidden = document.getElementById('input-reu');
        const reuSelect = document.getElementById('input-reu-select');
        const reuManual = document.getElementById('input-reu-manual');
        const usandoReuManual = novoReuAtivo;

        let invalid = null;
        if (!processo || !processo.value.trim()) invalid = processo;
        else if (!processoValido) {
            evt.preventDefault();
            showToast('error', 'Processo inválido', 'Verifique o dígito verificador.');
            processo.focus();
            return false;
        }
        else if (!tipo || !tipo.value.trim() || tipo.value === '0') invalid = tipo;

        const classeCod = document.getElementById('input-classe-cod');
        if (!classeCod || !classeCod.value.trim()) {
            showToast('warning', 'Atenção', 'Por favor, selecione uma classe processual.');
            if (inputClasseSelect && inputClasseSelect.style.display !== 'none') {
                inputClasseSelect.focus();
            }
            evt.preventDefault();
            return false;
        }
        else if (!reuHidden || !reuHidden.value.trim()) {
            if (usandoReuManual && reuManual) invalid = reuManual;
            else if (reuSelect) invalid = reuSelect;
            else invalid = reuHidden;
        }

        if (usandoReuManual && !validarCpfTelefone()) {
            evt.preventDefault();
            return false;
        }

        const selectedTipo = tipo ? tipo.options[tipo.selectedIndex] : null;
        if (selectedTipo && selectedTipo.disabled) {
            evt.preventDefault();
            showToast('warning', 'Atenção', 'Por favor, altere o tipo da guia para uma opção válida.');
            tipo.focus();
            return false;
        }

        if (invalid) {
            evt.preventDefault();
            showToast('warning', 'Atenção', 'Por favor, preencha todos os campos obrigatórios.');
            invalid.focus();
            return false;
        }

        if (regime && regime.value === '0') regime.value = '';
        if (inputCpf) inputCpf.value = inputCpf.value.replace(/\D/g, '');
        if (inputTel) inputTel.value = inputTel.value.replace(/\D/g, '');

        const resumo = gerarResumoAlteracoes();
        if (resumo) {
            sessionStorage.setItem('guias_update_summary', resumo);
        } else {
            sessionStorage.removeItem('guias_update_summary');
        }
        sessionStorage.removeItem('guias_update_details');

        return true;
    });
}

function preencherFormularioGuia(payload) {
    const guia = payload?.guia || {};
    if (!guia || !guia.id_guia) {
        showToast('error', 'Erro', 'Dados da guia não encontrados.');
        setBotaoSalvarDisabled(true);
        return;
    }

    if (inputProcesso) {
        inputProcesso.value = guia.processo || '';
        processoValido = true;
        setProcessoError(false);
        setBotaoSalvarDisabled(false);
    }
    if (inputClasse) inputClasse.value = guia.classe_desc || '';
    if (inputClasseCod) inputClasseCod.value = guia.classe_cod || '';
    if (inputClasseDesc) inputClasseDesc.value = guia.classe_desc || '';
    if (guia.classe_cod) {
        const opt = inputClasseSelect?.querySelector(`option[value="${guia.classe_cod}"]`);
        if (opt) opt.selected = true;
        setClasseEditable(false);
    } else {
        setClasseEditable(true);
    }
    const selectTipo = document.getElementById('input-tipo');
    if (selectTipo) selectTipo.value = guia.tipo || '';

    // Reus
    const reus = Array.isArray(payload?.reus) ? payload.reus : [];
    reuCamposLiberados = true;
    if (reus.length > 0) {
        preencherReus(reus);
        setReuMode('select');
        if (inputReuSelect) inputReuSelect.value = guia.denunciado || '';
        atualizarDadosReuSelecionado();
    } else {
        setReuMode('manual');
        if (inputReuManual) inputReuManual.value = guia.denunciado || '';
    }
    atualizarReuFinal();
    controlarCheckboxReuPreso();

    marcarPrioridades(payload?.prioridades || []);

    if (inputStatus) inputStatus.value = guia.status || '';
    atualizarResumoGuia(guia.processo, guia.classe_desc || guia.classe_cod || '', guia.status || '');
    if (summaryStatus) {
        summaryStatus.dataset.statusBase = guia.status || '';
    }

    if (inputRegime) inputRegime.value = guia.regime || '';

    if (inputDataFato) inputDataFato.value = guia.data_fato ? guia.data_fato.split(' ')[0] : '';
    if (inputDiasMulta) inputDiasMulta.value = guia.dias_multa || '';
    if (inputCpf) inputCpf.value = guia.cpf ? maskCPF(String(guia.cpf)) : '';
    if (inputTel) inputTel.value = guia.telefone ? maskPhone(String(guia.telefone)) : '';
    if (reuInfoCpf) reuInfoCpf.textContent = guia.cpf ? maskCPF(String(guia.cpf)) : '—';
    if (reuInfoTel) reuInfoTel.textContent = guia.telefone ? maskPhone(String(guia.telefone)) : '—';
    const obs = document.getElementById('input-observacoes');
    if (obs) obs.value = guia.observacoes || '';

    if (Array.isArray(payload?.servidores) && payload.servidores.length > 0) {
        payload.servidores.forEach((srvId) => adicionarServidor(srvId));
    }

    aplicarRegrasAlocacao();
    controlarVisibilidadeAlocacao();
    atualizarMensagemSemServidor();
    atualizarOpcoesSelectServidor();
    atualizarCheckCalcularPena();
    atualizarReuInfoHints();
    atualizarMensagemSemPrioridade();
    atualizarOpcoesSelectPrioridade();
    ajustarTipoPorRegime();
    atualizarStatusPrincipal();
    aplicarRegraVerificadoConsta();
    estadoInicialForm = capturarEstadoFormulario();
    registrarMonitorAlteracoes();
}

async function carregarGuia() {
    if (!idGuia) {
        showToast('error', 'Erro', 'ID da guia não informado.');
        setBotaoSalvarDisabled(true);
        return;
    }
    setProcessoLoading(true);
    try {
        const url = new URL('../v2026/api/guia-get.php', window.location.href);
        url.searchParams.set('id', idGuia);
        const resp = await fetch(url.toString(), { method: 'GET', cache: 'no-store', credentials: 'same-origin' });
        const raw = await resp.text();
        let data = null;
        try {
            data = JSON.parse(raw);
        } catch (parseErr) {
            console.error('Resposta não é JSON válido:', raw);
            showToast('error', 'Erro', 'Não foi possível carregar a guia (resposta inválida).');
            setBotaoSalvarDisabled(true);
            return;
        }

        if (resp.ok && data?.ok) {
            preencherFormularioGuia(data);
        } else {
            const msg = data?.message || raw || 'Não foi possível carregar a guia.';
            showToast('error', 'Erro', String(msg).slice(0, 200));
            setBotaoSalvarDisabled(true);
        }
    } catch (err) {
        console.error(err);
        showToast('error', 'Erro', 'Falha ao carregar dados da guia.');
        setBotaoSalvarDisabled(true);
    } finally {
        setProcessoLoading(false);
        registrarMonitorAlteracoes();
    }
}

carregarGuia();
aplicarRegrasAlocacao();
controlarVisibilidadeAlocacao();
controlarBotaoExcluir();
atualizarReuInfoHints();
atualizarBotaoPrioridade();
atualizarMensagemSemPrioridade();
atualizarOpcoesSelectPrioridade();
atualizarBotaoServidor();
atualizarMensagemSemServidor();
atualizarOpcoesSelectServidor();
ajustarTipoPorRegime();
atualizarStatusPrincipal();

if (cardSubtarefas && typeof carregarSubtarefas === 'function') {
    prepararSubtarefasContexto();
    const promise = carregarSubtarefas(cardSubtarefas);
    if (promise && typeof promise.then === 'function') {
        promise.then(() => {
            atualizarStatusPrincipal();
            if (inputDataFato && inputDataFato.value && typeof window.atualizarSalarioMinimoAutomatico === 'function') {
                window.atualizarSalarioMinimoAutomatico(inputDataFato.value).catch(() => {});
            }
        }).catch(() => {});
    }
    registrarObserverSubtarefas();
}

if (selectPriorityAdd) {
    selectPriorityAdd.addEventListener('change', atualizarBotaoPrioridade);
}

if (selectServidor) {
    selectServidor.addEventListener('change', atualizarBotaoServidor);
}

if (btnAddPriority) {
    btnAddPriority.addEventListener('click', () => {
        if (btnAddPriority.disabled) return;
        const prioridade = String(selectPriorityAdd?.value || '').trim();
        if (!prioridade) return;

        const prioridadesAtuais = obterPrioridadesAtuais();
        if (prioridadesAtuais.includes(prioridade)) {
            resetarSelectPrioridade();
            return;
        }

        if (priorityHiddenInputs) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'prioridades[]';
            hidden.value = prioridade;
            hidden.dataset.priorityHidden = prioridade;
            priorityHiddenInputs.appendChild(hidden);
        }

        if (priorityTags && selectPriorityAdd) {
            const label = selectPriorityAdd.options[selectPriorityAdd.selectedIndex]?.text || prioridade;
            priorityTags.appendChild(criarTagPrioridade(prioridade, label));
        }

        setPrioridadeCheckbox(prioridade, true);
        atualizarMensagemSemPrioridade();
        atualizarOpcoesSelectPrioridade();
        dispararAlteracaoFormulario();
    });
}

if (priorityTags) {
    priorityTags.addEventListener('click', (event) => {
        const btn = event.target.closest('.priority-remove');
        if (!btn) return;
        const tag = btn.closest('.priority-tag');
        const prioridade = tag?.dataset?.priority || '';
        if (!prioridade) return;
        if (prioridade === 'reu-preso') return;

        if (tag) tag.remove();
        if (priorityHiddenInputs) {
            const hidden = priorityHiddenInputs.querySelector(`[data-priority-hidden="${CSS.escape(prioridade)}"]`);
            if (hidden) hidden.remove();
        }
        setPrioridadeCheckbox(prioridade, false);
        atualizarMensagemSemPrioridade();
        atualizarOpcoesSelectPrioridade();
        dispararAlteracaoFormulario();
    });
}
