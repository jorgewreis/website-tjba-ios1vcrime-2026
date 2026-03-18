<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
require_once __DIR__ . '/../../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
require_once __DIR__ . '/../../services/CartasRecebidasService.php';

$connection = Conection::getInstance()->getConnection();

$idCarta = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$carta = null;

if ($idCarta === '') {
    $errorMessage = 'Identificador da carta recebida não informado.';
} else {
    try {
        $service = new CartasRecebidasService($connection);
        $carta = $service->find($idCarta);
        if ($carta === null) {
            $errorMessage = 'Carta recebida não encontrada.';
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar a carta recebida: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeCodigo = '';
$classeNome = 'Classe não informada';

$finalidadeValue = '';
$cidadeValue = '';
$ufValue = 'BA';
$prazoAtual = 30;
$statusAtual = 'aguardando assinatura';
$observacoesValue = '';
$comarcaDestinoId = '';

if ($carta !== null) {
    $processoNumero = trim((string) ($carta['numero_processo_origem'] ?? ''));
    $processosRepo = new ProcessosRepository($connection);
    $processoRow = $processosRepo->findById((string) ($carta['processo_origem_id'] ?? ''));
    if ($processoRow !== null) {
        $classeId = trim((string) ($processoRow['classe_id'] ?? ''));
        if ($classeId !== '') {
            $classesRepo = new ClassesRepository($connection);
            $classeRow = $classesRepo->findById($classeId);
            if ($classeRow !== null) {
                $classeNome = trim((string) ($classeRow['nome'] ?? 'Classe não informada'));
                if ($classeNome === '') {
                    $classeNome = 'Classe não informada';
                }
                $classeCodigo = trim((string) ($classeRow['codigo'] ?? ''));
            }
        }
    }

    $finalidadeValue = trim((string) ($carta['finalidade'] ?? ''));
    $cidadeValue = trim((string) ($carta['cidade_origem'] ?? ''));
    $ufValue = strtoupper(trim((string) ($carta['uf_origem'] ?? 'BA')));
    if ($ufValue === '') {
        $ufValue = 'BA';
    }
    $prazoAtual = (int) ($carta['prazo_dias'] ?? 0);
    if ($prazoAtual <= 0) {
        $prazoAtual = 30;
    }
    $statusAtual = trim((string) ($carta['status'] ?? ''));
    if ($statusAtual === '') {
        $statusAtual = 'pendente';
    }
    $observacoesValue = trim((string) ($carta['observacoes'] ?? ''));
    $comarcaDestinoId = trim((string) ($carta['comarca_origem_id'] ?? ''));

    $subtarefasRepo = new CartasRecebidasSubtarefasRepository($connection);
    $subtarefas = $subtarefasRepo->findByCartaId($idCarta);
    $tribunaisRepo = new TribunaisRepository($connection);
    $subtarefasPorOrdem = [];
    foreach ($subtarefas as $st) {
        $subtarefasPorOrdem[(int) $st['ordem']] = $st;
    }
    $subtarefaLabels = [
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Ato ordinatório - Cumprimento',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedição de documento',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Juntada de certidão do oficial de justiça',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Ato ordinatório - Devolução',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Devolução da Carta',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Juntada de termo de envio',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Arquivamento definitivo',
    ];
    $ordemParaTipo = [
        1 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1,
        2 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2,
        3 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3,
        4 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4,
        5 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5,
        6 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6,
        7 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7,
    ];
    $temSubtarefas = count($subtarefas) > 0;
    $temEmAndamentoOuPendente = false;
    foreach ($subtarefas as $st) {
        $s = trim((string) ($st['status'] ?? ''));
        if ($s === 'em_andamento' || $s === 'pendente') {
            $temEmAndamentoOuPendente = true;
            break;
        }
    }
    $timelineMinorPendente = [
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Aguardando ato ordinatório determinando o cumprimento da carta',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando expedição de documento necessário ao cumprimento',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando juntada da certidão do oficial de justiça',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando ato ordinatório determinando a devolução da carta ao juízo de origem',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando envio do processo ao juízo de origem',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando juntada de termo de envio do processo',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Aguardando arquivamento definitivo da carta',
    ];
    $resultadoCumprimentoLabels = [
        'cumprido_integralmente' => 'Cumprido integralmente',
        'cumprido_parcialmente' => 'Cumprido parcialmente',
        'cumprido_negativamente' => 'Cumprido negativamente',
        'nao_cumprido' => 'Não cumprido',
    ];
}

$ruleServidor = '?';
$ruleName = 'ALLOCATE-CARTASRECEBIDAS';
try {
    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $ruleRow = $regrasRepo->findByRegra($ruleName);
    $allocatedUserId = $ruleRow['usuario_id'] ?? null;
    if ($allocatedUserId !== null && $allocatedUserId !== '') {
        $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
        $stmt->execute([$allocatedUserId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u !== false) {
            $nome = trim((string) ($u['nome'] ?? ''));
            $sobrenome = trim((string) ($u['sobrenome'] ?? ''));
            $ruleServidor = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            if ($ruleServidor === '') {
                $ruleServidor = '?';
            }
        }
    }
} catch (\Throwable $exception) {
    // Mantém o valor padrão se houver erro na leitura do banco.
}

$finalidadeOptions = [
    'Intimação para Audiência',
    'Intimação de Decisão Interlocutória',
    'Intimação da Sentença',
    'Citação para apresentar Defesa',
    'Citação para apresentar Alegações Finais',
    'Suspensão Condicional',
    'Outra',
];

$deadlineOptions = ['5 dias', '10 dias', '15 dias', '30 dias', '60 dias', '90 dias'];

$statusOptions = [
    'aguardando assinatura' => 'Aguardando assinatura',
    'aguardando envio' => 'Aguardando envio',
    'aguardando juntada do termo' => 'Aguardando juntada de termo',
    'aguardando cumprimento' => 'Aguardando cumprimento',
    'concluída' => 'Concluída',
    'cancelada' => 'Cancelada',
];

/** Perfis que podem excluir a tarefa e subtarefas (tabela usuario_perfil). */
$perfisPermitidosExcluir = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
$userPerfisEdit = [];
$userIdEdit = $_SESSION['id_usuario'] ?? null;
if ($userIdEdit !== null && $userIdEdit !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$userIdEdit]);
    $userPerfisEdit = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
}
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;

/** Perfis que podem cancelar a tarefa (apenas altera status para Cancelada): servidor, admin, diretora, magistrada. */
$perfisPermitidosCancelar = ['SERVIDOR', 'ADMIN', 'DIRETORA', 'MAGISTRADA'];
$podeCancelarTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosCancelar)) > 0;
$tarefaFinalizadaOuCancelada = in_array($statusAtual ?? '', ['cancelada', 'concluída'], true);
$timelineData = $carta !== null
    ? cartorioTimelineBuildViewData('cartas_recebidas', [
        'task' => $carta,
        'task_status' => $statusAtual,
        'subtarefas' => $subtarefas ?? [],
        'ruleServidor' => $ruleServidor,
        'tribunaisRepo' => $tribunaisRepo ?? null,
    ])
    : [];

$stateOptions = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins',
];
?>
<!DOCTYPE html>
<html>
    <head>
        <meta lang="pt-BR">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição de Carta Recebida - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Internal - Cartório - Cartas Recebidas -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Carta Recebida</h1>
        <p class="description-page">Altere os dados cadastrados e salve para atualizar a carta recebida.</p>
        <a href="/documents/pdf/PJBA - 1VCR - POP - Expedição e Envio de Cartas Precatórias.pdf" target="_blank" rel="noopener noreferrer">
            <div class="badge file">
                <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
                <span>Passo a passo - Expedição de Cartas Precatórias</span>
            </div>
        </a>
    </div>

    <div class="card-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php else: ?>
            <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Informações obrigatórias</div>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-md height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <?php
                    $classeReadonly = ($classeNome !== 'Classe não informada');
                    $classeValue = $classeReadonly ? $classeNome : '';
                    $classeClass = 'width-xl height-md' . ($classeReadonly ? ' readonly' : '');
                    ?>
                    <input type="text" id="filter-classe" class="<?php echo htmlspecialchars($classeClass, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($classeValue, ENT_QUOTES); ?>"<?php echo $classeReadonly ? ' readonly' : ' placeholder="Informe a classe processual"'; ?>>
                </div>
                <div class="col filter">
                    <label for="filter-tipo">Finalidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-lg height-md" id="filter-tipo" required>
                        <option value="" disabled>Selecione aqui</option>
                        <?php foreach ($finalidadeOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo $finalidadeValue === $option ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label for="filter-cidade">Cidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-xl height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="120" value="<?php echo htmlspecialchars($cidadeValue, ENT_QUOTES); ?>" required>
                </div>
                <div class="col filter">
                    <label for="filter-estado">Estado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-md height-md" id="filter-estado" required>
                        <?php foreach ($stateOptions as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>"<?php echo $ufValue === $code ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="filter-prazo">Prazo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-sm height-md" id="filter-prazo" required>
                        <?php foreach ($deadlineOptions as $option): ?>
                            <?php $digits = preg_replace('/\D+/', '', $option); ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo (int) $digits === $prazoAtual ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <!-- <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label for="filter-observacoes">Observações</label>
                    <textarea id="filter-observacoes" class="width-xl" rows="4"><?php echo htmlspecialchars($observacoesValue, ENT_QUOTES); ?></textarea>
                </div>
            </div> -->

            <!-- Timeline de subtarefas -->
            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-carta-id" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-comarca-id" value="<?php echo htmlspecialchars($comarcaDestinoId, ENT_QUOTES); ?>">

        <?php endif; ?>
        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <?php if ($errorMessage === ''): ?>
                <button class="btn btn-primary" id="salvar-btn" type="button" disabled>Salvar alterações</button>
                <?php if ($podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-secondary-outline" id="cancelar-tarefa-btn" type="button" title="Alterar status da tarefa para Cancelada (não exclui dados)">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-tarefa-btn" type="button" title="Excluir esta tarefa e todas as subtarefas">Excluir tarefa</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php if ($carta !== null): ?>
        <script>
            window.__initialCartaData = {
                finalidade: <?php echo json_encode($finalidadeValue, JSON_UNESCAPED_UNICODE); ?>,
                prazoDias: <?php echo json_encode($prazoAtual, JSON_UNESCAPED_UNICODE); ?>,
                cidade: <?php echo json_encode($cidadeValue, JSON_UNESCAPED_UNICODE); ?>,
                estado: <?php echo json_encode($ufValue, JSON_UNESCAPED_UNICODE); ?>,
                status: <?php echo json_encode($statusAtual, JSON_UNESCAPED_UNICODE); ?>,
                observacoes: <?php echo json_encode($observacoesValue, JSON_UNESCAPED_UNICODE); ?>
            };
            window.__currentCartaId = <?php echo json_encode($idCarta, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script src="/assets/js/modules/cartorio/cartas-recebidas/text-formatter.js"></script>
        <script>
            (() => {
                const endpoint = '/api/cartorio-cartas-recebidas/update.php';
                const salvarBtn = document.getElementById('salvar-btn');
                const feedback = document.getElementById('cadastro-feedback');
                const cidadeInput = document.getElementById('filter-cidade');
                const estadoSelect = document.getElementById('filter-estado');
                const finalidadeSelect = document.getElementById('filter-tipo');
                const prazoSelect = document.getElementById('filter-prazo');
                const statusSelect = document.getElementById('filter-status');
                const observacoesInput = document.getElementById('filter-observacoes');
                const hiddenComarcaInput = document.getElementById('hidden-comarca-id');
                const initialData = window.__initialCartaData || {};

                if (!salvarBtn || !finalidadeSelect || !prazoSelect || !cidadeInput || !estadoSelect) {
                    return;
                }

                const showFeedback = (message, type = 'success') => {
                    if (typeof showToast === 'function') {
                        showToast({ type: type === 'error' ? 'error' : 'success', variant: 'full', title: type === 'error' ? 'Erro' : 'Sucesso', message });
                    } else if (feedback) {
                        const variant = type === 'error' ? 'status-cancelled' : 'status-finished';
                        feedback.innerHTML = `<label class="status ${variant}">${message}</label>`;
                    }
                };

                const sanitizePrazo = (value) => {
                    const digits = (value || '').replace(/\D+/g, '');
                    if (digits === '') {
                        return '0';
                    }
                    return digits.slice(0, 3);
                };

                const normalizeCidadeForCompare = (value) => {
                    return (value || '').trim().toLowerCase()
                        .normalize('NFD').replace(/\p{M}/gu, '')
                        .replace(/ç/g, 'c');
                };

                const formatCidade = (value) => {
                    if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                        return TextFormatter.formatProperName(value, 120);
                    }
                    return value;
                };

                const getCurrentState = () => {
                    const state = {
                        finalidade: (finalidadeSelect.value || '').trim(),
                        prazo: sanitizePrazo(prazoSelect.value),
                        cidade: cidadeInput.value.trim(),
                        estado: (estadoSelect.value || '').trim(),
                    };
                    if (statusSelect) state.status = (statusSelect.value || '').trim();
                    if (observacoesInput) state.observacoes = observacoesInput.value.trim();
                    return state;
                };

                const hasChanges = () => {
                    const current = getCurrentState();
                    if (initialData.finalidade !== current.finalidade) return true;
                    if ((initialData.prazoDias || '0').toString() !== current.prazo) return true;
                    if (normalizeCidadeForCompare(initialData.cidade) !== normalizeCidadeForCompare(current.cidade)) return true;
                    if ((initialData.estado || '') !== current.estado) return true;
                    if (statusSelect && (initialData.status || '') !== (current.status || '')) return true;
                    if (observacoesInput && (initialData.observacoes || '') !== (current.observacoes || '')) return true;
                    return false;
                };

                const updateButtonState = () => {
                    salvarBtn.disabled = !hasChanges();
                };

                const formElements = [finalidadeSelect, prazoSelect, cidadeInput, estadoSelect];
                if (statusSelect) formElements.push(statusSelect);
                if (observacoesInput) formElements.push(observacoesInput);
                ['input', 'change'].forEach((eventName) => {
                    formElements.forEach((element) => {
                        if (element) element.addEventListener(eventName, updateButtonState);
                    });
                });

                const applyCidadeFormatting = () => {
                    cidadeInput.value = formatCidade(cidadeInput.value);
                };

                const fetchCidadeInfo = () => {
                    const cidade = cidadeInput.value.trim();
                    if (cidade.length < 2) return;
                    fetch('/api/cartorio-cartas-recebidas/buscar-cidade.php?cidade=' + encodeURIComponent(cidade))
                        .then((response) => response.json())
                        .then((data) => {
                            if (!data || !data.found) return;
                            if (data.uf && estadoSelect) {
                                estadoSelect.value = data.uf;
                            }
                            const status = (data.status_cidade || '').trim();
                            if (status.toUpperCase() === 'CCM') {
                                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Comarca com Central de Mandados' });
                                else alert('Comarca com Central de Mandados');
                                cidadeInput.value = '';
                                if (hiddenComarcaInput) hiddenComarcaInput.value = '';
                            } else {
                                if (status.toLowerCase() === 'agregada' && data.nome) {
                                    cidadeInput.value = formatCidade(data.nome);
                                }
                                if (data.id && hiddenComarcaInput) hiddenComarcaInput.value = data.id;
                            }
                        })
                        .catch(() => {});
                };

                cidadeInput.addEventListener('blur', () => {
                    applyCidadeFormatting();
                    fetchCidadeInfo();
                });

                salvarBtn.addEventListener('click', async (event) => {
                    event.preventDefault();
                    if (salvarBtn.disabled) return;
                    const payloadState = getCurrentState();
                    if (payloadState.finalidade === '' || payloadState.cidade === '' || payloadState.estado === '') {
                        return showFeedback('Finalidade, cidade e estado são obrigatórios.', 'error');
                    }
                    if (!hiddenComarcaInput.value.trim()) {
                        return showFeedback('Selecione uma cidade válida para que a comarca seja identificada.', 'error');
                    }

                    const payload = {
                        id: window.__currentCartaId,
                        finalidade: payloadState.finalidade,
                        prazo_dias: parseInt(payloadState.prazo, 10) || 0,
                        cidade_origem: formatCidade(payloadState.cidade),
                        uf_origem: payloadState.estado,
                        comarca_origem_id: hiddenComarcaInput.value.trim(),
                    };
                    if (statusSelect) payload.status = payloadState.status || '';
                    if (observacoesInput) payload.observacoes = payloadState.observacoes || '';

                    salvarBtn.disabled = true;
                    if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Atualizando dados...' });
                    else showFeedback('Atualizando dados...', 'success');

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.error || 'Falha ao atualizar a carta recebida.');
                        }
                        window.location.href = '/frame.php?page=cartasrecebidas';
                    } catch (error) {
                        showFeedback(error.message || 'Erro ao salvar.', 'error');
                        salvarBtn.disabled = false;
                    }
                });

                updateButtonState();

                const btnCancelarTarefa = document.getElementById('cancelar-tarefa-btn');
                if (btnCancelarTarefa && window.__currentCartaId) {
                    btnCancelarTarefa.addEventListener('click', async () => {
                        btnCancelarTarefa.disabled = true;
                        try {
                            const res = await fetch('/api/cartorio-cartas-recebidas/cancel.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: window.__currentCartaId })
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.success) {
                                window.location.reload();
                                return;
                            }
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao cancelar a tarefa.' });
                            else alert(data.error || 'Erro ao cancelar a tarefa.');
                        } catch (e) {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão ao cancelar.' });
                            else alert('Erro de conexão ao cancelar.');
                        }
                        btnCancelarTarefa.disabled = false;
                    });
                }

                const btnExcluirTarefa = document.getElementById('excluir-tarefa-btn');
                if (btnExcluirTarefa && window.__currentCartaId) {
                    btnExcluirTarefa.addEventListener('click', async () => {
                        if (!confirm('Tem certeza que deseja excluir esta tarefa e todas as subtarefas? Esta ação não pode ser desfeita.')) {
                            return;
                        }
                        btnExcluirTarefa.disabled = true;
                        try {
                            const res = await fetch('/api/cartorio-cartas-recebidas/delete.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: window.__currentCartaId })
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.deleted) {
                                window.location.href = '/frame.php?page=cartasrecebidas';
                                return;
                            }
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao excluir a tarefa.' });
                            else alert(data.error || 'Erro ao excluir a tarefa.');
                        } catch (e) {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão ao excluir.' });
                            else alert('Erro de conexão ao excluir.');
                        }
                        btnExcluirTarefa.disabled = false;
                    });
                }
            })();
        </script>
    <?php endif; ?>

    <script>
        (() => {
            const btn = document.getElementById('voltar-btn');
            if (!btn) {
                return;
            }
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                window.location.href = '/frame.php?page=cartasrecebidas';
            }, { capture: true });
        })();
    </script>

</body>
</html>
