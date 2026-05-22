<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/EditaisAlocadosRepository.php';
require_once __DIR__ . '/../../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
require_once __DIR__ . '/../../helpers/EditaisUiCopy.php';
require_once __DIR__ . '/../../services/EditaisService.php';

$connection = Conection::getInstance()->getConnection();

$idEdital = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$edital = null;

if ($idEdital === '') {
    $errorMessage = 'Identificador do edital não informado.';
} else {
    try {
        $service = new EditaisService($connection);
        $edital = $service->find($idEdital);
        if ($edital === null) {
            $errorMessage = 'Edital não encontrado.';
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar o edital: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeNome = 'Classe não informada';
$statusAtual = 'pendente';
$observacoesValue = '';
$tipoEditalValue = '';
$prazoDiasValue = '';
$subtarefas = [];
$subtarefasPorOrdem = [];
$ordemLabels = EditaisUiCopy::mapaLegendaEtapaPorOrdem();
$taskTitleByOrdem = [];
$timelineMinorPendente = [];
foreach (EditaisSubtarefasRepository::ORDENS_ATIVAS as $ordemAtiva) {
    $tipoOrdem = $ordemAtiva === EditaisSubtarefasRepository::ORDEM_PUBLICACAO
        ? EditaisSubtarefasRepository::TIPO_PUBLICACAO
        : EditaisSubtarefasRepository::TIPO_DECURSO;
    $mapa = EditaisUiCopy::mapaTituloTarefaPorTipo();
    $taskTitleByOrdem[$ordemAtiva] = $mapa[$tipoOrdem] ?? '';
    $timelineCfg = EditaisUiCopy::timelineModuleConfig();
    $timelineMinorPendente[$ordemAtiva] = $timelineCfg['subtasks'][$ordemAtiva]['pending_text'] ?? '';
}
$ordemParaTipo = [
    EditaisSubtarefasRepository::ORDEM_PUBLICACAO => EditaisSubtarefasRepository::TIPO_PUBLICACAO,
    EditaisSubtarefasRepository::ORDEM_DECURSO => EditaisSubtarefasRepository::TIPO_DECURSO,
];
$temSubtarefas = false;
$temEmAndamentoOuPendente = false;

if ($edital !== null) {
    $processoNumero = trim((string) ($edital['numero_processo'] ?? ''));
    $classeNome = trim((string) ($edital['classe_nome'] ?? ''));
    if ($classeNome === '') {
        $classeNome = 'Classe não informada';
    }
    $statusAtual = trim((string) ($edital['status'] ?? 'pendente'));
    if ($statusAtual === '') {
        $statusAtual = 'pendente';
    }
    $observacoesValue = trim((string) ($edital['observacoes'] ?? ''));
    $tipoEditalValue = trim((string) ($edital['tipo_edital'] ?? ''));
    $prazoDiasValue = isset($edital['prazo_dias']) ? (int) $edital['prazo_dias'] : 0;

    $subtarefasRepo = new EditaisSubtarefasRepository($connection);
    $subtarefasRepo->ensureSubtarefasAtivas($idEdital);
    $serviceEditalFluxo = new EditaisService($connection);
    $serviceEditalFluxo->tentarFinalizacaoAutomaticaPorDecurso($idEdital);
    $edital = $serviceEditalFluxo->find($idEdital) ?? $edital;
    if ($edital !== null) {
        $statusAtual = trim((string) ($edital['status'] ?? $statusAtual));
    }
    $subtarefas = $subtarefasRepo->findByEditalId($idEdital);
    foreach ($subtarefas as $st) {
        $subtarefasPorOrdem[(int) $st['ordem']] = $st;
    }
    $temSubtarefas = count($subtarefas) > 0;
    foreach ($subtarefas as $st) {
        $s = trim((string) ($st['status'] ?? ''));
        if ($s === 'em_andamento' || $s === 'pendente') {
            $temEmAndamentoOuPendente = true;
            break;
        }
    }
}

$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$userPerfisEdit = [];
$userIdEdit = $_SESSION['id_usuario'] ?? null;
if ($userIdEdit !== null && trim((string) $userIdEdit) !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $userPerfisEdit = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userIdEdit));
}
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;
$podeCancelarTarefa = $userIdEdit !== '';
$tarefaFinalizadaOuCancelada = in_array($statusAtual, ['cancelada', 'concluída'], true);
$perfisGestaoAlocEdital = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestaoAlocEditEdital = count(array_intersect($userPerfisEdit, $perfisGestaoAlocEdital)) > 0;

$alocadosEditEdital = [];
if ($edital !== null) {
    $tipoOrderAloc = ['rule' => 0, 'all' => 1, 'specific' => 2];
    $repoAlocEdital = new EditaisAlocadosRepository($connection);
    $alocadosRawEdital = $repoAlocEdital->findByEditalId($idEdital);
    foreach (array_slice($alocadosRawEdital, 0, 5) as $a) {
        $tipo = trim((string) ($a['tipo'] ?? ''));
        if (!in_array($tipo, ['rule', 'all', 'specific'], true)) {
            $tipo = 'specific';
        }
        $alocadosEditEdital[] = [
            'alocado_id' => trim((string) ($a['id'] ?? '')),
            'iniciais' => $a['iniciais'] ?? '—',
            'nome_completo' => $a['nome_completo'] ?? '',
            'usuario_id' => trim((string) ($a['usuario_id'] ?? '')),
            'tipo' => $tipo,
        ];
    }
    usort($alocadosEditEdital, static function (array $a, array $b) use ($tipoOrderAloc): int {
        return ($tipoOrderAloc[$a['tipo']] ?? 9) <=> ($tipoOrderAloc[$b['tipo']] ?? 9);
    });
}

$timelineData = $edital !== null
    ? cartorioTimelineBuildViewData('editais', [
        'task' => $edital,
        'task_status' => $statusAtual,
        'subtarefas' => $subtarefas,
        'connection' => $connection,
    ])
    : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição Edital - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Editais -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Edital</h1>
        <p class="description-page">Acompanhe expedição, envio ao DJEN, certificação da publicação e decurso do prazo.</p>
    </div>

    <div class="container-full card-page">
        <?php if ($errorMessage !== ''): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($errorMessage, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
        <?php else: ?>
            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Informações</h2></div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" style="justify-content: flex-start;">
                <div class="col filter">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <input type="text" id="filter-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="edit-tipo-edital">Tipo de edital</label>
                    <input type="text" id="edit-tipo-edital" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($tipoEditalValue, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="edit-prazo-dias">Prazo do edital (dias)</label>
                    <input type="text" id="edit-prazo-dias" class="width-300 height-md readonly" value="<?php echo $prazoDiasValue > 0 ? (int) $prazoDiasValue : '—'; ?>" readonly>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="edit-observacoes">Observações</label>
                    <input type="text" id="edit-observacoes" class="width-800 height-md" value="<?php echo htmlspecialchars($observacoesValue, ENT_QUOTES); ?>" placeholder="Opcional">
                </div>
            </div>

            <?php
            $cartorioAlocacaoPrefix = 'editais';
            $cartorioAlocacaoEntityId = $idEdital;
            $cartorioAlocacaoDesalocarEndpoint = '/api/cartorio-editais/desalocar.php';
            $cartorioAlocacaoAlocarEndpoint = '/api/cartorio-editais/alocar.php';
            $cartorioAlocacaoAlocados = $alocadosEditEdital;
            $cartorioAlocacaoBloqueada = $tarefaFinalizadaOuCancelada;
            $cartorioAlocacaoUserId = $userIdEdit !== null ? trim((string) $userIdEdit) : '';
            $cartorioAlocacaoIsGestao = $isGestaoAlocEditEdital;
            include __DIR__ . '/_cartorio_alocacao_direcionada_edit.php';
            ?>

            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-edital-id" value="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-secondary" id="voltar-btn" type="button">Voltar</button>
                <?php if ($errorMessage === '' && $podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-danger-outline" id="cancelar-tarefa-btn" type="button">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($errorMessage === '' && $podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-tarefa-btn" type="button">Excluir tarefa</button>
                <?php endif; ?>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <?php if ($errorMessage === ''): ?>
                    <button class="btn btn-primary" id="salvar-btn" type="button">Salvar alterações</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row pagination">
            <div class="col">
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

    <?php if ($edital !== null): ?>
        <script>
            window.__currentEditalId = <?php echo json_encode($idEdital, JSON_UNESCAPED_UNICODE); ?>;
            window.__initialObservacoes = <?php echo json_encode($observacoesValue, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script>
            (function () {
                if (!window.App || !App.CartorioSimpleEdit || typeof App.CartorioSimpleEdit.initialize !== 'function') {
                    return;
                }

                App.CartorioSimpleEdit.initialize({
                    entityId: window.__currentEditalId,
                    backButtonId: 'voltar-btn',
                    backHref: '/frame.php?page=editais',
                    saveButtonId: 'salvar-btn',
                    observationFieldId: 'edit-observacoes',
                    initialObservation: window.__initialObservacoes || '',
                    updateEndpoint: '/api/cartorio-editais/update.php',
                    cancelButtonId: 'cancelar-tarefa-btn',
                    cancelEndpoint: '/api/cartorio-editais/cancel.php',
                    deleteButtonId: 'excluir-tarefa-btn',
                    deleteEndpoint: '/api/cartorio-editais/delete.php',
                    deleteConfirmKey: 'excluir-edital',
                    deleteConfirmMessage: 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão deste edital.'
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
