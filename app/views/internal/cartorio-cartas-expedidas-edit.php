<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/CartasExpedidasAlocadosRepository.php';
require_once __DIR__ . '/../../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
require_once __DIR__ . '/../../services/CartasExpedidasService.php';

$connection = Conection::getInstance()->getConnection();

$idCarta = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$carta = null;

if ($idCarta === '') {
    $errorMessage = 'Identificador da carta expedida não informado.';
} else {
    try {
        $service = new CartasExpedidasService($connection);
        $carta = $service->find($idCarta);
        if ($carta === null) {
            $errorMessage = 'Carta expedida não encontrada.';
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar a carta expedida: ' . $exception->getMessage();
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
    $processosRepo = new ProcessosRepository($connection);
    $processoRow = $processosRepo->findById((string) ($carta['processo_origem_id'] ?? ''));
    if ($processoRow !== null) {
        $processoNumero = trim((string) ($processoRow['numero_processo'] ?? ''));
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
    $cidadeValue = trim((string) ($carta['cidade_destino'] ?? ''));
    $ufValue = strtoupper(trim((string) ($carta['uf_destino'] ?? 'BA')));
    if ($ufValue === '') {
        $ufValue = 'BA';
    }
    $prazoAtual = (int) ($carta['prazo_dias'] ?? 0);
    if ($prazoAtual <= 0) {
        $prazoAtual = 30;
    }
    $statusAtual = trim((string) ($carta['status'] ?? ''));
    if ($statusAtual === '') {
        $statusAtual = 'aguardando assinatura';
    }
    $observacoesValue = trim((string) ($carta['observacoes'] ?? ''));
    $comarcaDestinoId = trim((string) ($carta['comarca_destino_id'] ?? ''));

    $subtarefasRepo = new CartasExpedidasSubtarefasRepository($connection);
    $subtarefasRepo->ensureInformacoesDevolucaoPair($idCarta);
    $subtarefas = $subtarefasRepo->findByCartaId($idCarta);
    $tribunaisRepo = new TribunaisRepository($connection);
    $subtarefasPorOrdem = [];
    foreach ($subtarefas as $st) {
        $subtarefasPorOrdem[(int) $st['ordem']] = $st;
    }
    $subtarefaLabels = [
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_1 => 'Aguardando assinatura',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_2 => 'Gerar PDF',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando envio',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_4 => 'Juntada de termo de envio',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando cumprimento',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_6 => 'Devolução',
    ];
    $taskTitleByTipo = [
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_1 => 'Carta aguardando assinatura',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando impressão de documentos em PDF',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando envio de Carta',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando comprovante de envio',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando cumprimento',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando cumprimento',
    ];
    $ordemParaTipo = [
        1 => CartasExpedidasSubtarefasRepository::TIPO_ORDEM_1,
        2 => CartasExpedidasSubtarefasRepository::TIPO_ORDEM_2,
        3 => CartasExpedidasSubtarefasRepository::TIPO_ORDEM_3,
        4 => CartasExpedidasSubtarefasRepository::TIPO_ORDEM_4,
        5 => CartasExpedidasSubtarefasRepository::TIPO_ORDEM_5,
        6 => CartasExpedidasSubtarefasRepository::TIPO_ORDEM_6,
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
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_1 => 'Aguardando assinatura pela juíza',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando impressão de documentos em PDF',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando envio de documentos',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando juntada de termo de envio',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando juntada de informações',
        CartasExpedidasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando devolução da Carta',
    ];
}

$ruleServidor = '?';
$ruleName = 'ALLOCATE-CARTASEXPEDIDAS';
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
$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$userPerfisEdit = [];
$userIdEdit = $_SESSION['id_usuario'] ?? null;
if ($userIdEdit !== null && trim((string) $userIdEdit) !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $userPerfisEdit = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userIdEdit));
}
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;

/** Perfis que podem cancelar a tarefa (apenas altera status para Cancelada): servidor, admin, diretora, magistrada. */
$podeCancelarTarefa = $userIdEdit !== '';
$tarefaFinalizadaOuCancelada = in_array($statusAtual ?? '', ['cancelada', 'concluída'], true);
$perfisGestaoAlocCartExp = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestaoAlocEditCartExp = count(array_intersect($userPerfisEdit, $perfisGestaoAlocCartExp)) > 0;

$alocadosEditCartExp = [];
if ($carta !== null) {
    $tipoOrderAloc = ['rule' => 0, 'all' => 1, 'specific' => 2];
    $repoAlocCartExp = new CartasExpedidasAlocadosRepository($connection);
    $alocadosRawCartExp = $repoAlocCartExp->findByCartaId($idCarta);
    foreach (array_slice($alocadosRawCartExp, 0, 5) as $a) {
        $tipo = trim((string) ($a['tipo'] ?? ''));
        if (!in_array($tipo, ['rule', 'all', 'specific'], true)) {
            $tipo = 'specific';
        }
        $alocadosEditCartExp[] = [
            'alocado_id' => trim((string) ($a['id'] ?? '')),
            'iniciais' => $a['iniciais'] ?? '—',
            'nome_completo' => $a['nome_completo'] ?? '',
            'usuario_id' => trim((string) ($a['usuario_id'] ?? '')),
            'tipo' => $tipo,
        ];
    }
    usort($alocadosEditCartExp, static function (array $a, array $b) use ($tipoOrderAloc): int {
        return ($tipoOrderAloc[$a['tipo']] ?? 9) <=> ($tipoOrderAloc[$b['tipo']] ?? 9);
    });
}

$timelineData = $carta !== null
    ? cartorioTimelineBuildViewData('cartas_expedidas', [
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
        <title>Edição de Carta Expedida - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Internal - Cartório - Cartas Expedidas -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Carta Expedida</h1>
        <p class="description-page">Altere os dados cadastrados e salve para atualizar a carta expedida.</p>
        <button type="button" class="badge file" data-nav-href="/documents/pdf/PJBA - 1VCR - POP - Expedição e Envio de Cartas Precatórias.pdf" data-nav-target="_blank">
            <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
            <span>Passo a passo - Expedição de Cartas Precatórias</span>
        </button>
    </div>

    <div class="container-full card-page">
        <?php if ($errorMessage !== ''): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($errorMessage, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
        <?php else: ?>
            
            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Informações principais</h2></div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col">
                    <label for="filter-classe">Classe</label>
                    <?php
                    $classeReadonly = ($classeNome !== 'Classe não informada');
                    $classeValue = $classeReadonly ? $classeNome : '';
                    $classeClass = 'width-600 height-md readonly';
                    ?>
                    <input type="text" id="filter-classe" class="<?php echo htmlspecialchars($classeClass, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($classeValue, ENT_QUOTES); ?>" placeholder="Sem classe vinculada — cadastre em Consultar processo &gt; Editar processo" readonly>
                </div>
                <div class="col">
                    <label for="filter-tipo">Finalidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="filter-tipo" required>
                        <option value="" disabled>Selecione aqui</option>
                        <?php foreach ($finalidadeOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo $finalidadeValue === $option ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="filter-cidade">Cidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-600 height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="120" value="<?php echo htmlspecialchars($cidadeValue, ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label for="filter-estado">Estado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="filter-estado" required>
                        <?php foreach ($stateOptions as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>"<?php echo $ufValue === $code ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-prazo">Prazo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="filter-prazo" required>
                        <?php foreach ($deadlineOptions as $option): ?>
                            <?php $digits = preg_replace('/\D+/', '', $option); ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo (int) $digits === $prazoAtual ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php
            $cartorioAlocacaoPrefix = 'cartas-exp';
            $cartorioAlocacaoEntityId = $idCarta;
            $cartorioAlocacaoDesalocarEndpoint = '/api/cartorio-cartas-expedidas/desalocar.php';
            $cartorioAlocacaoAlocarEndpoint = '/api/cartorio-cartas-expedidas/alocar.php';
            $cartorioAlocacaoAlocados = $alocadosEditCartExp;
            $cartorioAlocacaoBloqueada = $tarefaFinalizadaOuCancelada;
            $cartorioAlocacaoUserId = $userIdEdit !== null ? trim((string) $userIdEdit) : '';
            $cartorioAlocacaoIsGestao = $isGestaoAlocEditCartExp;
            include __DIR__ . '/_cartorio_alocacao_direcionada_edit.php';
            ?>

            <!-- Timeline de subtarefas -->
            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-carta-id" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-comarca-id" value="<?php echo htmlspecialchars($comarcaDestinoId, ENT_QUOTES); ?>">

        <?php endif; ?>
        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-secondary" id="voltar-btn" type="button">Voltar</button>
                <?php if ($errorMessage === '' && $podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-danger-outline" id="cancelar-tarefa-btn" type="button" title="Alterar status da tarefa para Cancelada (não exclui dados)">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($errorMessage === '' && $podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-tarefa-btn" type="button" title="Excluir esta tarefa e todas as subtarefas">Excluir tarefa</button>
                <?php endif; ?>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <?php if ($errorMessage === ''): ?>
                    <button class="btn btn-primary" id="salvar-btn" type="button" disabled>Salvar alterações</button>
                <?php endif; ?>
            </div>
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
        <script>
            (() => {
                const endpoint = '/api/cartorio-cartas-expedidas/update.php';
                const salvarBtn = document.getElementById('salvar-btn');
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
                    if (window.App && App.Page && typeof App.Page.notify === 'function') {
                        App.Page.notify(type === 'error' ? 'error' : 'success', type === 'error' ? 'Erro' : 'Sucesso', message);
                        return;
                    }
                    if (typeof showToast === 'function') {
                        showToast({ type: type === 'error' ? 'error' : 'success', variant: 'full', title: type === 'error' ? 'Erro' : 'Sucesso', message });
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
                    fetch('/api/cartorio-cartas-expedidas/buscar-cidade.php?cidade=' + encodeURIComponent(cidade))
                        .then((response) => response.json())
                        .then((data) => {
                            if (!data || !data.found) return;
                            if (data.uf && estadoSelect) {
                                estadoSelect.value = data.uf;
                            }
                            const status = (data.status_cidade || '').trim();
                            if (status.toUpperCase() === 'CCM') {
                                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'Comarca com Central de Mandados' });
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
                    if (normalizeCidadeForCompare(payloadState.cidade) === 'ilheus') {
                        return showFeedback('O cadastro não é permitido para a cidade de Ilhéus (comarca de origem).', 'error');
                    }
                    if (!hiddenComarcaInput.value.trim()) {
                        return showFeedback('Selecione uma cidade válida para que a comarca seja identificada.', 'error');
                    }

                    const payload = {
                        id: window.__currentCartaId,
                        finalidade: payloadState.finalidade,
                        prazo_dias: parseInt(payloadState.prazo, 10) || 0,
                        cidade_destino: formatCidade(payloadState.cidade),
                        uf_destino: payloadState.estado,
                        comarca_destino_id: hiddenComarcaInput.value.trim(),
                    };
                    if (statusSelect) payload.status = payloadState.status || '';
                    if (observacoesInput) payload.observacoes = payloadState.observacoes || '';

                    salvarBtn.disabled = true;
                    if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'Atualizando dados...' });
                    else showFeedback('Atualizando dados...', 'success');

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.error || 'Falha ao atualizar a carta expedida.');
                        }
                        window.location.href = '/frame.php?page=cartasexpedidas';
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
                            const res = await fetch('/api/cartorio-cartas-expedidas/cancel.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: window.__currentCartaId })
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.success) {
                                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'Tarefa cancelada com sucesso.' });
                                window.setTimeout(() => window.location.reload(), 250);
                                return;
                            }
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao cancelar a tarefa.' });
                        } catch (e) {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão ao cancelar.' });
                        }
                        btnCancelarTarefa.disabled = false;
                    });
                }

                const btnExcluirTarefa = document.getElementById('excluir-tarefa-btn');
                if (btnExcluirTarefa && window.__currentCartaId) {
                    const pageTools = window.App && App.Page ? App.Page : null;
                    const requireSecondClick = pageTools && typeof pageTools.createSecondClickGuard === 'function'
                        ? pageTools.createSecondClickGuard()
                        : (() => {
                            const pendingConfirmations = {};
                            return (key, message) => {
                                const now = Date.now();
                                const validUntil = Number(pendingConfirmations[key] || 0);
                                if (validUntil > now) {
                                    delete pendingConfirmations[key];
                                    return true;
                                }
                                pendingConfirmations[key] = now + 5000;
                                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Confirmação', message: message });
                                return false;
                            };
                        })();
                    const queuePostReloadToast = (type, title, message) => {
                        if (pageTools && typeof pageTools.queuePostReloadToast === 'function') {
                            pageTools.queuePostReloadToast(type, title, message, 'cartorio.postReloadToast');
                            return;
                        }
                        try {
                            const key = 'cartorio.postReloadToast';
                            const raw = sessionStorage.getItem(key);
                            const fila = raw ? JSON.parse(raw) : [];
                            const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
                            lista.push({ type, title, message });
                            sessionStorage.setItem(key, JSON.stringify(lista));
                        } catch (e) {}
                    };
                    btnExcluirTarefa.addEventListener('click', async () => {
                        if (!requireSecondClick('excluir-carta-expedida', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão desta tarefa.')) {
                            return;
                        }
                        btnExcluirTarefa.disabled = true;
                        try {
                            const res = await fetch('/api/cartorio-cartas-expedidas/delete.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: window.__currentCartaId })
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.deleted) {
                                (Array.isArray(data.subtarefas_toasts) ? data.subtarefas_toasts : []).forEach((msg) => {
                                    queuePostReloadToast('info', 'Subtarefa excluída', msg);
                                });
                                if (data.tarefa_toast) {
                                    queuePostReloadToast('success', 'Sucesso', data.tarefa_toast);
                                }
                                window.location.href = '/frame.php?page=cartasexpedidas';
                                return;
                            }
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao excluir a tarefa.' });
                        } catch (e) {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão ao excluir.' });
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
                window.location.href = '/frame.php?page=cartasexpedidas';
            }, { capture: true });
        })();
    </script>

</body>
</html>
