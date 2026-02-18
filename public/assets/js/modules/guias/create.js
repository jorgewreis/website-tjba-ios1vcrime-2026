(function mostrarToastQueryParams() {
    const url = new URL(window.location.href);
    const errorParam = url.searchParams.get('error');
    const okParam = url.searchParams.get('ok');

    if (errorParam) {
        showToast('error', 'Erro ao cadastrar', decodeURIComponent(errorParam), 'Revise os campos e tente novamente.');
    } else if (okParam) {
        showToast('success', 'Guia cadastrada', 'A guia foi cadastrada com sucesso.', 'Você pode continuar o preenchimento quando desejar.');
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

// Botão cancelar
const btCancelaGuia = document.getElementById('bt-cancela-guia');
if (btCancelaGuia) {
    btCancelaGuia.addEventListener('click', () => {
        window.location.href = './guias3.php';
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
const processoLoading = document.getElementById('processo-loading');
const processoError = document.getElementById('processo-error');
const btCadastrarGuia = document.getElementById('bt-cadastra-guia');
let processoValido = false;

// Bloqueia o botão de cadastrar inicialmente
setBotaoCadastrarDisabled(true);

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

function setBotaoCadastrarDisabled(disabled) {
    if (!btCadastrarGuia) return;
    btCadastrarGuia.disabled = disabled;
    if (disabled) {
        btCadastrarGuia.style.opacity = '0.5';
        btCadastrarGuia.style.cursor = 'not-allowed';
        btCadastrarGuia.setAttribute('aria-disabled', 'true');
    } else {
        btCadastrarGuia.style.opacity = '';
        btCadastrarGuia.style.cursor = '';
        btCadastrarGuia.removeAttribute('aria-disabled');
    }
}

function validarNumeroProcesso() {
    if (!inputProcesso) return false;

    const rawProcesso = inputProcesso.value.trim();
    const digits = rawProcesso.replace(/\D/g, '');

    if (digits.length === 0) {
        setProcessoError(false);
        processoValido = false;
        setBotaoCadastrarDisabled(true);
        reuCamposLiberados = false;
        setReuMode('manual');
        return false;
    }

    if (digits.length < 20) {
        setProcessoError(false);
        processoValido = false;
        setBotaoCadastrarDisabled(true);
        reuCamposLiberados = false;
        setReuMode('manual');
        return false;
    }

    if (digits.length === 20) {
        if (typeof validarDigitoVerificadorCNJ === 'function') {
            const dvOk = validarDigitoVerificadorCNJ(rawProcesso);
            setProcessoError(!dvOk);
            processoValido = dvOk;
            setBotaoCadastrarDisabled(!dvOk);
            reuCamposLiberados = dvOk;
            if (!dvOk) setReuMode('manual');
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
    if (!reuCamposLiberados) {
        inputReuSelect.style.display = 'none';
        inputReuManual.style.display = 'none';
        inputReuSelect.required = false;
        inputReuManual.required = false;
        const btAdicionarReu = document.getElementById('bt-adicionar-reu');
        if (btAdicionarReu) btAdicionarReu.style.display = 'none';
        atualizarReuFinal();
        return;
    }
    const manual = modo === 'manual';
    inputReuSelect.style.display = manual ? 'none' : '';
    inputReuManual.style.display = manual ? '' : 'none';
    inputReuSelect.required = !manual;
    inputReuManual.required = manual;
    if (manual) {
        inputReuSelect.value = '';
    } else {
        inputReuManual.value = '';
    }

    const btAdicionarReu = document.getElementById('bt-adicionar-reu');
    if (btAdicionarReu && manual) {
        btAdicionarReu.style.display = 'none';
    }

    atualizarReuFinal();
}

function atualizarReuFinal() {
    if (!inputReuFinal) return;
    if (inputReuManual && inputReuManual.style.display !== 'none') {
        inputReuFinal.value = inputReuManual.value.trim();
    } else if (inputReuSelect) {
        inputReuFinal.value = inputReuSelect.value.trim();
    }
}

// Armazena informações dos réus incluindo statusPrisao
let reusData = [];
let reuCamposLiberados = false;

function preencherReus(reus) {
    if (!inputReuSelect) return;
    inputReuSelect.innerHTML = '<option value="">Selecione o réu</option>';

    reusData = [];

    reus.forEach((reu) => {
        const nome = typeof reu === 'string' ? reu : (reu.nome || '');
        const statusPrisao = typeof reu === 'object' ? (parseInt(reu.statusPrisao) || 0) : 0;

        if (nome) {
            const opt = document.createElement('option');
            opt.value = nome;
            opt.textContent = nome;
            opt.dataset.statusprisao = statusPrisao;
            inputReuSelect.appendChild(opt);

            reusData.push({
                nome: nome,
                statusPrisao: statusPrisao
            });
        }
    });

    const btAdicionarReu = document.getElementById('bt-adicionar-reu');
    if (btAdicionarReu) {
        btAdicionarReu.style.display = (reus.length > 0 && reuCamposLiberados) ? '' : 'none';
    }
}

function marcarPrioridades(prioridades) {
    const checks = document.querySelectorAll('input[type="checkbox"][name="prioridades[]"]');
    if (!checks) return;
    const alvo = new Set((prioridades || []).map((p) => String(p).trim().toLowerCase()));
    checks.forEach((chk) => {
        const valor = String(chk.value || '').trim().toLowerCase();
        if (valor === 'urgente') return;
        if (valor === 'reu-preso') return;
        chk.checked = alvo.has(valor);
    });
}

// Função para controlar checkbox de réu preso baseado no statusPrisao
function controlarCheckboxReuPreso() {
    const checkboxReuPreso = document.querySelector('input[type="checkbox"][value="reu-preso"]');
    if (!checkboxReuPreso) return;

    const reuSelecionado = inputReuSelect && inputReuSelect.style.display !== 'none'
        ? inputReuSelect.value
        : null;

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
            const text = await resp.text();
            console.error('Falha no lookup (HTTP):', resp.status, text);
            throw new Error(`HTTP ${resp.status}`);
        }

        let data = null;
        try {
            data = await resp.json();
        } catch (parseErr) {
            const text = await resp.text();
            console.error('Resposta inválida (JSON):', text);
            throw parseErr;
        }

        if (!data || data.ok !== true) {
            setClasseEditable(true);
            if (inputClasseCod) inputClasseCod.value = '';
            if (inputClasseDesc) inputClasseDesc.value = '';
            if (inputClasse) inputClasse.value = '';
            reuCamposLiberados = true;
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
        reuCamposLiberados = true;
        if (reus.length > 0) {
            preencherReus(reus);
            setReuMode('select');
        } else {
            preencherReus([]);
            setReuMode('manual');
        }

        const prioridades = Array.isArray(data.prioridades) ? data.prioridades : [];
        marcarPrioridades(prioridades);

        controlarCheckboxReuPreso();
    } catch (err) {
        console.error('Falha ao consultar processo:', err);
        setClasseEditable(true);
        reuCamposLiberados = true;
        setReuMode('manual');
    } finally {
        setProcessoLoading(false);
    }
}

// Controle de visibilidade da lista de servidores
const radiosAlocacao = document.querySelectorAll('input[name="alocacao"]');
const containerServidores = document.getElementById('container-servidores');
function atualizarContainerServidores() {
    if (!containerServidores) return;
    const selecionado = document.querySelector('input[name="alocacao"]:checked');
    const valor = selecionado ? selecionado.value : '';
    containerServidores.style.display = (valor === 'manual' || valor === 'sorteio') ? 'block' : 'none';
}
if (radiosAlocacao && containerServidores) {
    radiosAlocacao.forEach(radio => {
        radio.addEventListener('change', atualizarContainerServidores);
    });
    atualizarContainerServidores();
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

function aplicarRegrasAlocacao() {
    const perfis = obterPerfisUsuario();
    const permitidos = new Set(['admin', 'diretora', 'magistrada']);
    const podeEscolher = perfis.some((p) => permitidos.has(p));
    const ehServidor = perfis.includes('servidor');
    const somenteServidor = ehServidor && perfis.length === 1;

    const radioNaoAlocar = document.querySelector('input[name="alocacao"][value="nao-alocar"]');
    const radioSorteio = document.querySelector('input[name="alocacao"][value="sorteio"]');
    const radioManual = document.querySelector('input[name="alocacao"][value="manual"]');
    const fieldsetAlocacao = document.querySelector('fieldset[aria-label="Alocação de usuários"]');

    if (podeEscolher) {
        if (fieldsetAlocacao) fieldsetAlocacao.style.display = '';
        return;
    }

    if (fieldsetAlocacao) fieldsetAlocacao.style.display = 'none';
    if (containerServidores) containerServidores.style.display = 'none';

    if (somenteServidor) {
        if (radioManual) radioManual.checked = true;
        const userId = (document.getElementById('input-user-cpf')?.value || '').trim();
        if (userId) {
            const checkbox = document.querySelector(`input[name="servidores[]"][value="${userId}"]`);
            if (checkbox) {
                checkbox.checked = true;
            } else {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'servidores[]';
                hidden.value = userId;
                document.getElementById('form-cad-guia')?.appendChild(hidden);
            }
        }
    } else {
        if (radioNaoAlocar) radioNaoAlocar.checked = true;
    }

    atualizarContainerServidores();
}

aplicarRegrasAlocacao();

// Controle automático do checkbox "calcular-pena-multa"
const inputDataFato = document.getElementById('input-data-fato');
const inputDiasMulta = document.getElementById('input-dias-multa');
const checkCalcularPena = document.getElementById('check-calcular-pena-multa');
const inputProcesso = document.getElementById('input-processo');
const formCadGuia = document.getElementById('form-cad-guia');

function sincronizarHiddenCalcularPena() {
    if (!formCadGuia || !checkCalcularPena) return;

    let hidden = document.getElementById('hidden-calcular-pena-multa');
    const precisaHidden = checkCalcularPena.checked && checkCalcularPena.disabled;

    if (precisaHidden) {
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = 'hidden-calcular-pena-multa';
            hidden.name = 'subtarefas[]';
            hidden.value = 'calcular-pena-multa';
            formCadGuia.appendChild(hidden);
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
    } else {
        checkCalcularPena.checked = false;
        checkCalcularPena.disabled = false;
    }

    sincronizarHiddenCalcularPena();
}

// Validação: ano da data do fato não pode ser maior que ano do processo
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

// Garante estado inicial correto ao abrir a página
atualizarCheckCalcularPena();

if (inputProcesso) {
    // Validação em tempo real do dígito verificador
    inputProcesso.addEventListener('input', validarNumeroProcesso);
    inputProcesso.addEventListener('blur', validarNumeroProcesso);

    // Busca informações do processo (só executa se o número for válido)
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
    });
}

if (inputReuSelect) {
    inputReuSelect.addEventListener('change', () => {
        atualizarReuFinal();
        controlarCheckboxReuPreso();
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

// Botão adicionar outro réu (troca select por input)
const btAdicionarReu = document.getElementById('bt-adicionar-reu');
if (btAdicionarReu) {
    btAdicionarReu.addEventListener('click', () => {
        setReuMode('manual');
        if (inputReuManual) {
            inputReuManual.focus();
        }
    });
}

// Validação no submit
const form = document.getElementById('form-cad-guia');
if (form) {
    form.addEventListener('submit', (evt) => {
        sincronizarHiddenCalcularPena();
        const processo = document.getElementById('input-processo');
        const tipo = document.getElementById('input-tipo');
        atualizarReuFinal();
        const reuHidden = document.getElementById('input-reu');
        const reuSelect = document.getElementById('input-reu-select');
        const reuManual = document.getElementById('input-reu-manual');

        let invalid = null;
        if (!processo || !processo.value.trim()) invalid = processo;
        else if (!processoValido) {
            evt.preventDefault();
            showToast('error', 'Processo inválido', 'Verifique o dígito verificador.');
            processo.focus();
            return false;
        }
        else if (!tipo || !tipo.value.trim()) invalid = tipo;

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
            if (reuManual && reuManual.style.display !== 'none') invalid = reuManual;
            else if (reuSelect) invalid = reuSelect;
            else invalid = reuHidden;
        }

        if (invalid) {
            evt.preventDefault();
            showToast('warning', 'Atenção', 'Por favor, preencha todos os campos obrigatórios.');
            invalid.focus();
            return false;
        }

        if (inputCpf) inputCpf.value = inputCpf.value.replace(/\D/g, '');
        if (inputTel) inputTel.value = inputTel.value.replace(/\D/g, '');

        return true;
    });
}
