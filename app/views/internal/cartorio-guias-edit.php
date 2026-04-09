<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/GuiasSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/GuiasAlocadosRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
require_once __DIR__ . '/../../services/GuiasService.php';

$connection = Conection::getInstance()->getConnection();

$idGuia = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$guia = null;

if ($idGuia === '') {
    $errorMessage = 'Identificador da guia não informado.';
} else {
    try {
        $service = new GuiasService($connection);
        $guia = $service->find($idGuia);
        if ($guia === null) {
            $errorMessage = 'Guia não encontrada.';
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar a guia: ' . $exception->getMessage();
    }
}

$subtarefaLabels = [
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Detalhar data do fato e dias-multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Calcular pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Expedir a Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Assinar a Guia no BNMP',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Juntar a Guia aos autos',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Gerar documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Enviar a Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Juntar o termo de envio',
];

$subtarefas = [];
$subtarefasPorOrdem = [];
$ordemParaTipo = [
    1 => GuiasSubtarefasRepository::TIPO_ORDEM_1,
    2 => GuiasSubtarefasRepository::TIPO_ORDEM_2,
    3 => GuiasSubtarefasRepository::TIPO_ORDEM_3,
    4 => GuiasSubtarefasRepository::TIPO_ORDEM_4,
    5 => GuiasSubtarefasRepository::TIPO_ORDEM_5,
    6 => GuiasSubtarefasRepository::TIPO_ORDEM_6,
    7 => GuiasSubtarefasRepository::TIPO_ORDEM_7,
    8 => GuiasSubtarefasRepository::TIPO_ORDEM_8,
];
$timelineMinorPendente = [
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Aguardando preenchimento da data do fato e da quantidade de dias-multa para conclusão do detalhamento',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando registro do valor da pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando informação da data de expedição da Guia ao cartório',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando assinatura da Juíza na Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando confirmação da juntada dos autos',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando informação da quantidade de documentos gerados em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Aguardando informação do envio da Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Aguardando juntada do termo de envio',
];
$statusCartaLabel = 'Em andamento';
$statusCartaClass = 'status-current';
$progressStatusesEdit = array_fill(0, 8, 'pendente');
$mostrarBarraEdit = false;
$segmentosBarraEdit = [];
$cadastroInfoLinha = '';
$classeCodigo = '';
$classeNome = 'Classe não informada';
$currentSubtarefaTitle = '';
if ($guia !== null) {
    $subtarefasRepo = new GuiasSubtarefasRepository($connection);
    $subtarefasRepo->ensureOitoSubtarefas($idGuia);
    $subtarefas = $subtarefasRepo->findByGuiaId($idGuia);
    foreach ($subtarefas as $st) {
        $subtarefasPorOrdem[(int) $st['ordem']] = $st;
    }
    $statusGuia = trim((string) ($guia['status'] ?? ''));
    if ($statusGuia === 'concluída') {
        $statusCartaLabel = 'Finalizada';
        $statusCartaClass = 'status-finished';
    } elseif ($statusGuia === 'pendente') {
        $statusCartaLabel = 'Pendente';
        $statusCartaClass = 'status-pending';
    } elseif ($statusGuia === 'cancelada') {
        $statusCartaLabel = 'Cancelada';
        $statusCartaClass = 'status-cancelled';
    }
    for ($o = 1; $o <= 8; $o++) {
        if (isset($subtarefasPorOrdem[$o])) {
            $progressStatusesEdit[$o - 1] = trim((string) ($subtarefasPorOrdem[$o]['status'] ?? 'pendente'));
        }
    }
    $mostrarBarraEdit = in_array($statusCartaLabel, ['Pendente', 'Em andamento'], true);
    if ($mostrarBarraEdit) {
        $segmentosBarraEdit[] = ['class' => 'concluida', 'title' => 'Guia cadastrada'];
        for ($i = 0; $i < 8; $i++) {
            $statusSeg = $progressStatusesEdit[$i] ?? 'pendente';
            $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : ($statusSeg === 'cancelada' || $statusSeg === 'pulada' ? $statusSeg : 'pendente'));
            $segmentosBarraEdit[] = ['class' => $segClass, 'title' => $subtarefaLabels[$ordemParaTipo[$i + 1] ?? ''] ?? ''];
        }
    }
    $guiaCreatedRaw = $guia['created_at'] ?? '';
    if ($guiaCreatedRaw !== '') {
        $dt0 = \DateTime::createFromFormat('Y-m-d H:i:s', $guiaCreatedRaw);
        $cadastroInfoLinha = $dt0 ? ('Guia cadastrada em ' . $dt0->format('d/m/Y - H:i:s')) : $guiaCreatedRaw;
    } else {
        $cadastroInfoLinha = 'Guia cadastrada';
    }
    $processoId = trim((string) ($guia['processo_id'] ?? ''));
    if ($processoId !== '') {
        $processosRepo = new ProcessosRepository($connection);
        $processoRow = $processosRepo->findById($processoId);
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
    }
    foreach ($subtarefas as $s) {
        if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
            $currentSubtarefaTitle = $subtarefaLabels[$s['tipo'] ?? ''] ?? $s['tipo'] ?? '';
            break;
        }
    }
    if ($currentSubtarefaTitle === '' && $statusGuia === 'pendente') {
        $currentSubtarefaTitle = $timelineMinorPendente[GuiasSubtarefasRepository::TIPO_ORDEM_1] ?? $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_1];
    } elseif ($currentSubtarefaTitle === '' && $statusGuia === 'concluída') {
        $currentSubtarefaTitle = 'Todas as etapas concluídas';
    } elseif ($currentSubtarefaTitle === '') {
        $currentSubtarefaTitle = $statusCartaLabel;
    }
}

$perfisExcluir = ['ADMIN', 'CHEFIA'];
$perfisCancelar = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'];
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && trim((string) $userId) !== '') {
    $userPerfis = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userId));
}
$podeExcluir = count(array_intersect($userPerfis, $perfisExcluir)) > 0;
$podeCancelar = count(array_intersect($userPerfis, $perfisCancelar)) > 0;
$perfisGestaoAloc = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestaoAlocEdit = count(array_intersect($userPerfis, $perfisGestaoAloc)) > 0;
$statusAtual = $guia['status'] ?? '';
$tarefaFinalizadaOuCancelada = in_array($statusAtual, ['cancelada', 'concluída'], true);

$alocadosEdit = [];
if ($guia !== null) {
    $tipoOrderAloc = ['rule' => 0, 'all' => 1, 'specific' => 2];
    $repoAlocGuia = new GuiasAlocadosRepository($connection);
    $alocadosRawGuia = $repoAlocGuia->findByGuiaId($idGuia);
    foreach (array_slice($alocadosRawGuia, 0, 5) as $a) {
        $tipo = trim((string) ($a['tipo'] ?? ''));
        if (!in_array($tipo, ['rule', 'all', 'specific'], true)) {
            $tipo = 'specific';
        }
        $alocadosEdit[] = [
            'alocado_id' => trim((string) ($a['id'] ?? '')),
            'iniciais' => $a['iniciais'] ?? '—',
            'nome_completo' => $a['nome_completo'] ?? '',
            'usuario_id' => trim((string) ($a['usuario_id'] ?? '')),
            'tipo' => $tipo,
        ];
    }
    usort($alocadosEdit, static function (array $a, array $b) use ($tipoOrderAloc): int {
        return ($tipoOrderAloc[$a['tipo']] ?? 9) <=> ($tipoOrderAloc[$b['tipo']] ?? 9);
    });
}
$timelineData = $guia !== null
    ? cartorioTimelineBuildViewData('guias', [
        'task' => $guia,
        'task_status' => $statusAtual,
        'subtarefas' => $subtarefas,
    ])
    : [];

$tiposOptions = [
    'Guia de Execução Definitiva',
    'Guia de Recolhimento Provisória',
    'Guia de Recolhimento Definitiva',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição de Guia - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Guias -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Guia</h1>
        <p class="description-page">Altere os dados e acompanhe as subtarefas.</p>
    </div>

    <div class="container-full card-page">
        <?php if ($errorMessage !== ''): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($errorMessage, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            </div>
        <?php else: ?>
            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados da guia</h2></div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" style="justify-content: flex-start;">
                <div class="col filter">
                    <label for="edit-processo">Processo</label>
                    <input type="text" id="edit-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($guia['numero_processo'] ?? '', ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <?php
                    $classeReadonly = ($classeNome !== 'Classe não informada');
                    $classeValue = $classeReadonly ? $classeNome : '';
                    $classeClass = 'width-600 height-md' . ($classeReadonly ? ' readonly' : '');
                    ?>
                    <input type="text" id="filter-classe" class="<?php echo htmlspecialchars($classeClass, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($classeValue, ENT_QUOTES); ?>"<?php echo $classeReadonly ? ' readonly' : ' placeholder="Preenchido automaticamente pelo DataJud"'; ?>>
                </div>
                <div class="col filter">
                    <label for="edit-denunciado">Denunciado</label>
                    <input type="text" id="edit-denunciado" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($guia['denunciado'] ?? '', ENT_QUOTES); ?>" maxlength="255" readonly>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="edit-tipo">Tipo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="edit-tipo" class="width-400 height-md">
                        <?php foreach ($tiposOptions as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo ($guia['tipo'] ?? '') === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="edit-data-fato">Data do fato</label>
                    <input type="date" id="edit-data-fato" class="width-300 height-md" value="<?php echo htmlspecialchars($guia['data_fato'] ?? '', ENT_QUOTES); ?>" max="">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="edit-regime">Regime</label>
                    <select id="edit-regime" class="width-200 height-md">
                        <option value=""<?php echo (($guia['regime'] ?? '') === '') ? ' selected' : ''; ?>>Selecione (opcional)</option>
                        <option value="Aberto"<?php echo (($guia['regime'] ?? '') === 'Aberto') ? ' selected' : ''; ?>>Aberto</option>
                        <option value="Semiaberto"<?php echo (($guia['regime'] ?? '') === 'Semiaberto') ? ' selected' : ''; ?>>Semiaberto</option>
                        <option value="Fechado"<?php echo (($guia['regime'] ?? '') === 'Fechado') ? ' selected' : ''; ?>>Fechado</option>
                    </select>
                </div>                
                <div class="col filter">
                    <label for="edit-dias-multa">Dias-multa</label>
                    <input type="number" id="edit-dias-multa" class="width-300 height-md" min="0" value="<?php echo isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : ''; ?>">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="edit-observacoes">Observações</label>
                    <input type="text" id="edit-observacoes" class="width-800 height-md" value="<?php echo htmlspecialchars((string) ($guia['observacoes'] ?? ''), ENT_QUOTES); ?>" placeholder="Opcional">
                </div>
            </div>

            <?php if ($guia !== null): ?>
                <?php
                $cartorioAlocacaoPrefix = 'guias';
                $cartorioAlocacaoEntityId = $idGuia;
                $cartorioAlocacaoDesalocarEndpoint = '/api/cartorio-guias/desalocar.php';
                $cartorioAlocacaoAlocarEndpoint = '/api/cartorio-guias/alocar.php';
                $cartorioAlocacaoAlocados = $alocadosEdit;
                $cartorioAlocacaoBloqueada = $tarefaFinalizadaOuCancelada;
                $cartorioAlocacaoUserId = $userId !== null ? trim((string) $userId) : '';
                $cartorioAlocacaoIsGestao = $isGestaoAlocEdit;
                include __DIR__ . '/_cartorio_alocacao_direcionada_edit.php';
                ?>
            <?php endif; ?>

            <?php if ($guia !== null): ?>
                <?php include __DIR__ . '/_cartorio_timeline.php'; ?>
            <?php endif; ?>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="button" class="btn btn-primary" id="salvar-btn" disabled>Salvar alterações</button>
                <?php if ($podeCancelar && !$tarefaFinalizadaOuCancelada): ?>
                    <button type="button" class="btn btn-danger-outline" id="cancelar-tarefa-btn">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($podeExcluir): ?>
                    <button type="button" class="btn btn-danger" id="excluir-btn">Excluir guia</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php if ($guia !== null): ?>
        <script>
        window.__initialGuiaData = {
            tipo: <?php echo json_encode($guia['tipo'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
            regime: <?php echo json_encode($guia['regime'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
            data_fato: <?php echo json_encode($guia['data_fato'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
            dias_multa: <?php echo json_encode(isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : '', JSON_UNESCAPED_UNICODE); ?>,
            observacoes: <?php echo json_encode((string) ($guia['observacoes'] ?? ''), JSON_UNESCAPED_UNICODE); ?>
        };
        </script>
    <?php endif; ?>
    <script>
    (function () {
        var idGuia = <?php echo json_encode($idGuia, JSON_UNESCAPED_UNICODE); ?>;

        if (!window.App || !App.CartorioGuiasEdit || typeof App.CartorioGuiasEdit.initialize !== 'function') {
            return;
        }

        App.CartorioGuiasEdit.initialize({
            entityId: idGuia,
            initialData: window.__initialGuiaData || {},
            backButtonId: 'voltar-btn',
            backHref: '/frame.php?page=guias',
            saveButtonId: 'salvar-btn',
            cancelButtonId: 'cancelar-tarefa-btn',
            deleteButtonId: 'excluir-btn',
            updateEndpoint: '/api/cartorio-guias/update.php',
            cancelEndpoint: '/api/cartorio-guias/cancel.php',
            deleteEndpoint: '/api/cartorio-guias/delete.php',
            deleteConfirmKey: 'excluir-guia',
            deleteConfirmMessage: 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão da guia.'
        });

    })();
    </script>
</body>
</html>
