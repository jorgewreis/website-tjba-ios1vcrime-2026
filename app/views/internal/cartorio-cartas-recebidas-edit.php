<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
require_once __DIR__ . '/../../repositories/TribunaisRepository.php';
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

$dataRecebimentoValue = '';
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
    $dataRecebimentoValue = trim((string) ($carta['data_recebimento'] ?? ''));
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
    $subtarefasRepo->ensureDevolucaoArquivamentoPair($idCarta);
    $subtarefas = $subtarefasRepo->findByCartaId($idCarta);
    $tribunaisRepo = new TribunaisRepository($connection);
    $subtarefasPorOrdem = [];
    foreach ($subtarefas as $st) {
        $subtarefasPorOrdem[(int) $st['ordem']] = $st;
    }
    $subtarefaLabels = [
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Conferência inicial',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedição de mandado',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Certificação',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'PDF',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Devolução à origem',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Arquivamento',
    ];
    $ordemParaTipo = [
        1 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1,
        2 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2,
        3 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3,
        4 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4,
        5 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5,
        6 => CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6,
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
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Conferência inicial da carta recebida',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedição de mandado',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Certificação',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Geração de PDF',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Devolução à origem',
        CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Arquivamento',
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
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/variables.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
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
            <div class="row filter">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php else: ?>
            <div class="row table header">Informações obrigatórias</div>
            <div class="row filter">
                <div class="col filter">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-md height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <input type="text" id="filter-classe" class="width-lg height-md readonly" value="<?php echo htmlspecialchars($classeCodigo !== '' ? ($classeCodigo . ' - ' . $classeNome) : $classeNome, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-tipo">Finalidade</label>
                    <select class="width-lg height-md" id="filter-tipo" required>
                        <option value="" disabled>Selecione aqui</option>
                        <?php foreach ($finalidadeOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo $finalidadeValue === $option ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <div class="row filter">
                <div class="col filter">
                    <label for="filter-cidade">Cidade</label>
                    <input type="text" class="width-xl height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="120" value="<?php echo htmlspecialchars($cidadeValue, ENT_QUOTES); ?>" required>
                </div>
                <div class="col filter">
                    <label for="filter-estado">Estado</label>
                    <select class="width-md height-md" id="filter-estado" required>
                        <?php foreach ($stateOptions as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>"<?php echo $ufValue === $code ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="filter-prazo">Prazo cumprimento</label>
                    <select class="width-sm height-md" id="filter-prazo" required>
                        <?php foreach ($deadlineOptions as $option): ?>
                            <?php $digits = preg_replace('/\D+/', '', $option); ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo (int) $digits === $prazoAtual ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <!-- <div class="row filter">
                <div class="col filter">
                    <label for="filter-observacoes">Observações</label>
                    <textarea id="filter-observacoes" class="width-xl" rows="4"><?php echo htmlspecialchars($observacoesValue, ENT_QUOTES); ?></textarea>
                </div>
            </div> -->

            <!-- Timeline de subtarefas -->
            <?php
            $statusCartaLabel = 'Em andamento';
            $statusCartaClass = 'status-current';
            if ($statusAtual === 'concluída') {
                $statusCartaLabel = 'Finalizada';
                $statusCartaClass = 'status-finished';
            } elseif ($statusAtual === 'pendente') {
                $statusCartaLabel = 'Pendente';
                $statusCartaClass = 'status-pending';
            }
            $currentSubtarefaTitle = '';
            foreach ($subtarefas as $s) {
                if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                    $currentSubtarefaTitle = $subtarefaLabels[$s['tipo'] ?? ''] ?? $s['tipo'] ?? '';
                    break;
                }
            }
            if ($currentSubtarefaTitle === '' && $statusAtual === 'pendente') {
                $currentSubtarefaTitle = $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1] ?? 'Conferência inicial';
            } elseif ($currentSubtarefaTitle === '' && $statusAtual === 'concluída') {
                $currentSubtarefaTitle = 'Todas as etapas concluídas';
            } elseif ($currentSubtarefaTitle === '') {
                $currentSubtarefaTitle = $statusAtual;
            }
            $progressStatusesEdit = ['pendente', 'pendente', 'pendente', 'pendente', 'pendente', 'pendente'];
            for ($o = 1; $o <= 6; $o++) {
                if (isset($subtarefasPorOrdem[$o])) {
                    $progressStatusesEdit[$o - 1] = trim((string) ($subtarefasPorOrdem[$o]['status'] ?? 'pendente'));
                }
            }
            $mostrarBarraEdit = ($statusCartaLabel === 'Pendente' || $statusCartaLabel === 'Em andamento');
            $segmentosBarraEdit = [];
            if ($mostrarBarraEdit) {
                $segmentosBarraEdit[] = ['class' => 'concluida', 'title' => 'Carta recebida e cadastrada'];
                for ($i = 0; $i < 6; $i++) {
                    $statusSeg = $progressStatusesEdit[$i] ?? 'pendente';
                    $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                    $segmentosBarraEdit[] = ['class' => $segClass, 'title' => $subtarefaLabels[$ordemParaTipo[$i + 1] ?? ''] ?? ''];
                }
            }
            ?>
            <div class="row table header">Subtarefas - Timeline</div>
            <div class="row buttons">
                <div class="col filter" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                    <?php if ($mostrarBarraEdit): ?>
                        <div class="width-sm status <?php echo htmlspecialchars($statusCartaClass, ENT_QUOTES); ?> status-with-progress" style="border: 1px solid var(--color-gray-500);">
                            <span class="status-task-label"><?php echo htmlspecialchars($statusCartaLabel, ENT_QUOTES); ?></span>
                            <?php $concluidosBarraEdit = 1 + count(array_filter($progressStatusesEdit, fn($x) => $x === 'concluida')); ?>
                            <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo $concluidosBarraEdit; ?>" aria-valuemin="0" aria-valuemax="7" aria-label="Progresso das subtarefas">
                                <?php foreach ($segmentosBarraEdit as $seg): ?>
                                    <div class="status-task-progress-segment <?php echo htmlspecialchars($seg['class'], ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($seg['title'], ENT_QUOTES); ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="width-sm status <?php echo htmlspecialchars($statusCartaClass, ENT_QUOTES); ?>" style="border: 1px solid var(--color-gray-500);"><?php echo htmlspecialchars($statusCartaLabel, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!$temSubtarefas): ?>
                    <div class="col filter">
                        <button type="button" class="col button-table button-subtask" id="btn-add-subtarefa" title="Adicionar subtarefa" aria-label="Adicionar subtarefa">
                            <i class="fi fi-rr-add"></i>
                        </button>
                    </div>
                <?php elseif ($temEmAndamentoOuPendente): ?>
                    <div class="col filter">
                        <button type="button" class="col button-table button-subtask" id="btn-refresh-subtarefas" title="Atualizar timeline" aria-label="Atualizar timeline">
                            <i class="fi fi-rr-refresh"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $cartaCreatedRaw = $carta['created_at'] ?? '';
            $cartaCreatedFormatted = '';
            if ($cartaCreatedRaw !== '') {
                $dt0 = \DateTime::createFromFormat('Y-m-d H:i:s', $cartaCreatedRaw);
                $cartaCreatedFormatted = $dt0 ? $dt0->format('d/m/Y - H:i:s') : $cartaCreatedRaw;
            }
            $cadastroInfoLinha = 'Documento cadastrado por ' . htmlspecialchars($ruleServidor, ENT_QUOTES) . ' em ' . $cartaCreatedFormatted;
            ?>
            <div class="row timeline finished">
                <div class="row">
                    <div class="circle finished"></div>
                    <div class="col filter subtask-status-info">
                        <span class="minor-info">Carta recebida e cadastrada</span>
                    </div>
                </div>
                <div class="col">
                    <div class="col filter subtask-date">
                        <span class="minor-info"><?php echo $cadastroInfoLinha; ?></span>
                    </div>
                </div>
            </div>
            <?php for ($ordem = 1; $ordem <= 6; $ordem++): ?>
                <?php
                $tipo = $ordemParaTipo[$ordem] ?? '';
                $label = $subtarefaLabels[$tipo] ?? 'Passo ' . $ordem;
                $st = $subtarefasPorOrdem[$ordem] ?? null;
                $circleClass = '';
                $statusTexto = 'Pendente';
                $dataMovimento = '';
            $minorTexto = $timelineMinorPendente[$tipo] ?? $label;
            $majorTexto = '';
            $metodoEnvioTexto = '';
            $dateLabel = 'Aguardando movimentação';
                $dadosEspecificos = [];
                if ($st !== null && !empty(trim((string) ($st['dados_especificos'] ?? '')))) {
                    $dadosEspecificos = json_decode((string) $st['dados_especificos'], true);
                    if (!is_array($dadosEspecificos)) {
                        $dadosEspecificos = [];
                    }
                }
                $nomeResponsavel = isset($dadosEspecificos['nome']) ? trim((string) $dadosEspecificos['nome']) : (isset($dadosEspecificos['responsavel']) ? trim((string) $dadosEspecificos['responsavel']) : '');
                $infoLinha = '';
                if ($st !== null) {
                    $status = trim((string) ($st['status'] ?? ''));
                    $raw = $st['updated_at'] ?? $st['created_at'] ?? '';
                    $dataMovimento = '';
                    if ($raw !== '') {
                        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw);
                        $dataMovimento = $dt ? $dt->format('d/m/Y - H:i:s') : $raw;
                    }
                    if ($status === 'concluida') {
                        $circleClass = 'finished';
                        $nomeExibir = $nomeResponsavel !== '' ? $nomeResponsavel : '—';
                        if ($tipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1) {
                            $minorTexto = 'Conferência inicial realizada';
                            $majorTexto = '';
                            $infoLinha = 'Documento assinado pela Magistrada em ' . $dataMovimento;
                        } elseif ($tipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2) {
                            $qtd = (int) ($dadosEspecificos['qtd_docs'] ?? $dadosEspecificos['qtd_docs_gerados'] ?? 1);
                            if ($qtd < 1) {
                                $qtd = 1;
                            }
                            $minorTexto = $qtd === 1 ? '1 documento gerado em PDF' : $qtd . ' documentos gerados em PDF';
                            $majorTexto = '';
                            $infoLinha = 'Documentos gerados por ' . $nomeExibir . ' em ' . $dataMovimento;
                        } elseif ($tipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3) {
                            $minorTexto = 'Expedição de mandado para';
                            $tribunalDestino = trim((string) ($dadosEspecificos['tribunal_destino'] ?? $dadosEspecificos['tribunal_nome'] ?? ''));
                            if ($tribunalDestino === '' && isset($carta['uf_origem'])) {
                                $uf = strtoupper(trim((string) $carta['uf_origem']));
                                if ($uf !== '') {
                                    $sigla = $uf === 'DF' ? 'TJDFT' : 'TJ' . $uf;
                                    $tribunalRow = $tribunaisRepo->findBySigla($sigla);
                                    $nomeTribunal = '';
                                    if ($tribunalRow !== null) {
                                        $nomeTribunal = trim((string) ($tribunalRow['nome'] ?? ''));
                                    }
                                    if ($nomeTribunal === '') {
                                        $nomeTribunal = 'Tribunal de Justiça do Estado - ' . $uf;
                                    }
                                    $tribunalDestino = $sigla . ' - ' . $nomeTribunal;
                                }
                            }
                            $majorTexto = $tribunalDestino !== '' ? $tribunalDestino : '—';
                            $infoLinha = 'Documento enviado por ' . $nomeExibir . ' em ' . $dataMovimento;
                        } elseif ($tipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4) {
                            $minorTexto = 'Comprovante de termo de envio juntado e liberado nos autos';
                            $majorTexto = '';
                            $metodoEnvioTexto = '';
                            $metodoEnvioRegistrado = '';
                            if (isset($subtarefasPorOrdem[3])) {
                                $dadosEnvio = json_decode((string) ($subtarefasPorOrdem[3]['dados_especificos'] ?? ''), true);
                                if (is_array($dadosEnvio)) {
                                    $metodoEnvioRegistrado = trim((string) ($dadosEnvio['metodo_envio'] ?? ''));
                                }
                            }
                            if ($metodoEnvioRegistrado !== '') {
                                $preposicaoMetodo = strcasecmp($metodoEnvioRegistrado, 'PJe') === 0 ? 'pelo' : 'por';
                                $displayMetodo = strcasecmp($metodoEnvioRegistrado, 'Email') === 0 ? 'email' : $metodoEnvioRegistrado;
                                $metodoEnvioTexto = 'Enviado ' . $preposicaoMetodo . ' ' . $displayMetodo;
                            }
                            $infoLinha = 'Documento juntado por ' . $nomeExibir . ' em ' . $dataMovimento;
                        } elseif ($tipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5) {
                            $minorTexto = 'Informações complementares informadas';
                            $numeroDestino = trim((string) ($dadosEspecificos['numero_processo_destino'] ?? $carta['numero_processo_destino'] ?? ''));
                            $varaDestino = trim((string) ($dadosEspecificos['vara_destino'] ?? $carta['vara_destino_nome'] ?? ''));
                            $majorTexto = ($numeroDestino !== '' && $varaDestino !== '') ? $numeroDestino . ' - ' . $varaDestino : ($numeroDestino ?: $varaDestino ?: '—');
                            $infoLinha = 'Documento juntado por ' . $nomeExibir . ' em ' . $dataMovimento;
                        } elseif ($tipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6) {
                            $statusDevolucao = trim((string) ($dadosEspecificos['resultado_devolucao'] ?? $dadosEspecificos['status_devolucao'] ?? ''));
                            if ($statusDevolucao === 'cumprida_positivamente') {
                                $minorTexto = 'Carta devolvida cumprida positivamente';
                            } elseif ($statusDevolucao === 'cumprida_parcialmente') {
                                $minorTexto = 'Carta devolvida cumprida parcialmente';
                            } elseif ($statusDevolucao === 'cumprida_negativamente') {
                                $minorTexto = 'Carta devolvida cumprida negativamente';
                            } elseif ($statusDevolucao === 'devolvida_sem_cumprimento') {
                                $minorTexto = 'Carta devolvida sem cumprimento';
                            } elseif ($statusDevolucao !== '') {
                                $minorTexto = 'Carta devolvida com status ' . $statusDevolucao;
                            } else {
                                $minorTexto = 'Carta devolvida';
                            }
                            $majorTexto = '';
                            $infoLinha = 'Carta juntada por ' . $nomeExibir . ' em ' . $dataMovimento;
                        } else {
                            $minorTexto = $label;
                            $infoLinha = 'Movimentação realizada em ' . $dataMovimento;
                        }
                        $dateLabel = $infoLinha;
                    } elseif ($status === 'em_andamento') {
                        $circleClass = 'current';
                        $statusTexto = 'Em andamento';
                        $minorTexto = $timelineMinorPendente[$tipo] ?? $label;
                        $majorTexto = $statusTexto;
                        if ($dataMovimento !== '') {
                            $dateLabel = 'Aguardando movimentação';
                        } else {
                            $raw = $st['created_at'] ?? $st['updated_at'] ?? '';
                            if ($raw !== '') {
                                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw);
                                $dataMovimento = $dt ? $dt->format('d/m/Y - H:i:s') : $raw;
                                $dateLabel = 'Aguardando movimentação desde ' . $dataMovimento;
                            } else {
                                $dateLabel = 'Aguardando movimentação';
                            }
                        }
                    } else {
                        $statusTexto = 'Pendente';
                        $minorTexto = $timelineMinorPendente[$tipo] ?? $label;
                        $majorTexto = $statusTexto;
                        $dateLabel = 'Aguardando conclusão';
                    }
                } else {
                    $minorTexto = $timelineMinorPendente[$tipo] ?? $label;
                    $majorTexto = $statusTexto;
                    $dateLabel = 'Aguardando conclusão';
                }
                if ($infoLinha !== '') {
                    $dateLabel = $infoLinha;
                }
                ?>
                <div class="row <?php if ($circleClass === 'current') { echo 'timeline current'; } else if ($circleClass === 'finished') { echo 'timeline finished'; } else { echo 'timeline'; } ?>">
                    <div class="row">
                        <?php if ($circleClass !== ''): ?>
                            <div class="circle <?php echo htmlspecialchars($circleClass, ENT_QUOTES); ?>"></div>
                        <?php else: ?>
                            <div class="circle" style="background-color: var(--color-gray-300);"></div>
                        <?php endif; ?>
                        <div class="col filter subtask-status-info">
                            <span class="minor-info"><?php echo htmlspecialchars($minorTexto, ENT_QUOTES); ?></span>
                            <?php if ($majorTexto !== ''): ?>
                                <span class="major-info"><?php echo htmlspecialchars($majorTexto, ENT_QUOTES); ?></span>
                            <?php endif; ?>
                            <?php if ($metodoEnvioTexto !== ''): ?>
                                <span class="major-info"><?php echo htmlspecialchars($metodoEnvioTexto, ENT_QUOTES); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="col filter subtask-date">
                            <span class="minor-info"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES); ?></span>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <input type="hidden" id="hidden-carta-id" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-comarca-id" value="<?php echo htmlspecialchars($comarcaDestinoId, ENT_QUOTES); ?>">

        <?php endif; ?>
        <div class="row buttons">
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <?php if ($errorMessage === ''): ?>
                <button class="btn btn-primary" id="salvar-btn" type="button" disabled>Salvar</button>
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
                    if (!feedback) return;
                    const variant = type === 'error' ? 'status-cancelled' : 'status-finished';
                    feedback.innerHTML = `<label class="status ${variant}">${message}</label>`;
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
                                alert('Comarca com Central de Mandados');
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
                    showFeedback('Atualizando dados...', 'success');

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

                const btnAddSubtarefa = document.getElementById('btn-add-subtarefa');
                if (btnAddSubtarefa) {
                    btnAddSubtarefa.addEventListener('click', async () => {
                        const cartaId = window.__currentCartaId;
                        if (!cartaId) return;
                        btnAddSubtarefa.disabled = true;
                        try {
                            const response = await fetch('/api/cartorio-cartas-recebidas/criar-primeira-subtarefa.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ carta_id: cartaId }),
                            });
                            const data = await response.json();
                            if (!response.ok || !data.success) {
                                throw new Error(data.error || 'Falha ao criar subtarefa.');
                            }
                            window.location.reload();
                        } catch (err) {
                            alert(err.message || 'Erro ao adicionar subtarefa.');
                            btnAddSubtarefa.disabled = false;
                        }
                    });
                }

                const btnRefreshSubtarefas = document.getElementById('btn-refresh-subtarefas');
                if (btnRefreshSubtarefas) {
                    btnRefreshSubtarefas.addEventListener('click', () => {
                        window.location.reload();
                    });
                }

                const btnCancelarTarefa = document.getElementById('cancelar-tarefa-btn');
                if (btnCancelarTarefa && window.__currentCartaId) {
                    btnCancelarTarefa.addEventListener('click', async () => {
                        if (!confirm('Deseja cancelar esta tarefa? O status será alterado para "Cancelada". A tarefa e as subtarefas permanecerão no sistema.')) {
                            return;
                        }
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
                            alert(data.error || 'Erro ao cancelar a tarefa.');
                        } catch (e) {
                            alert('Erro de conexão ao cancelar.');
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
                            alert(data.error || 'Erro ao excluir a tarefa.');
                        } catch (e) {
                            alert('Erro de conexão ao excluir.');
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
