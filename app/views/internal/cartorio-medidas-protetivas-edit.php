<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/MedidasProtetivasSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/MedidasProtetivasAlocadosRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
require_once __DIR__ . '/../../helpers/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../../services/MedidasProtetivasService.php';

$connection = Conection::getInstance()->getConnection();

$idMedida = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$medida = null;
$subtarefas = [];
$subtarefasPorOrdem = [];

if ($idMedida === '') {
    $errorMessage = 'Identificador da medida protetiva não informado.';
} else {
    try {
        $service = new MedidasProtetivasService($connection);
        $medida = $service->find($idMedida);
        if ($medida === null) {
            $errorMessage = 'Medida protetiva não encontrada.';
        } else {
            $subtarefasRepo = new MedidasProtetivasSubtarefasRepository($connection);
            $subtarefasRepo->ensureDuasSubtarefas($idMedida);
            $subtarefas = $subtarefasRepo->findByMedidaId($idMedida);
            foreach ($subtarefas as $subtarefa) {
                $subtarefasPorOrdem[(int) ($subtarefa['ordem'] ?? 0)] = $subtarefa;
            }
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar a medida protetiva: ' . $exception->getMessage();
    }
}

$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && trim((string) $userId) !== '') {
    $userPerfis = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userId));
}
$podeExcluirTarefa = count(array_intersect($userPerfis, $perfisPermitidosExcluir)) > 0;
$podeCancelarTarefa = $userId !== '';

$statusAtual = trim((string) ($medida['status'] ?? 'pendente'));
$tarefaFinalizadaOuCancelada = in_array($statusAtual, ['cancelada', 'concluída'], true);
$perfisGestaoAlocMedida = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestaoAlocEditMedida = count(array_intersect($userPerfis, $perfisGestaoAlocMedida)) > 0;

$alocadosEditMedida = [];
if ($medida !== null) {
    $tipoOrderAloc = ['rule' => 0, 'all' => 1, 'specific' => 2];
    $repoAlocMedida = new MedidasProtetivasAlocadosRepository($connection);
    $alocadosRawMedida = $repoAlocMedida->findByMedidaId($idMedida);
    foreach (array_slice($alocadosRawMedida, 0, 5) as $a) {
        $tipo = trim((string) ($a['tipo'] ?? ''));
        if (!in_array($tipo, ['rule', 'all', 'specific'], true)) {
            $tipo = 'specific';
        }
        $alocadosEditMedida[] = [
            'alocado_id' => trim((string) ($a['id'] ?? '')),
            'iniciais' => $a['iniciais'] ?? '—',
            'nome_completo' => $a['nome_completo'] ?? '',
            'usuario_id' => trim((string) ($a['usuario_id'] ?? '')),
            'tipo' => $tipo,
        ];
    }
    usort($alocadosEditMedida, static function (array $a, array $b) use ($tipoOrderAloc): int {
        return ($tipoOrderAloc[$a['tipo']] ?? 9) <=> ($tipoOrderAloc[$b['tipo']] ?? 9);
    });
}

$timelineData = $medida !== null
    ? cartorioTimelineBuildViewData('medidas_protetivas', [
        'task' => $medida,
        'task_status' => $statusAtual,
        'subtarefas' => $subtarefas,
        'connection' => $connection,
    ])
    : [];
$subtarefaLabels = [
    MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_1 => 'Cadastro de partes no PJe',
    MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedição de mandados de intimação',
    MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_3 => 'Cadastro no BNMP',
    MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_4 => 'Certidão de Cumprimento',
];
$timelineMinorPendente = [
    1 => 'Aguardando cadastro das partes no PJe',
    2 => 'Aguardando expedição de mandados de intimação',
    3 => 'Aguardando cadastro no BNMP',
    4 => 'Aguardando expedição da certidão de cumprimento',
];
$ordemParaTipo = [
    1 => MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_1,
    2 => MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_2,
    3 => MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_3,
    4 => MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_4,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Medidas Protetivas - Edição - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <style>
            .readonly-list {
                width: 100%;
            }
            .readonly-list + .readonly-list {
                margin-top: 1rem;
            }
            .badge-line {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-top: 0.5rem;
            }
        </style>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Medidas Protetivas -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Medida Protetiva</h1>
        <p class="description-page">Acompanhe o cadastro das partes no PJe, a expedição dos mandados e o vínculo automático com o Cejusc.</p>
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
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['numero_processo'] ?? ''), ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col">
                    <label for="filter-classe">Classe</label>
                    <input type="text" id="filter-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['classe_nome'] ?? 'Classe não informada'), ENT_QUOTES); ?>" readonly>
                </div>
            </div>

            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Réu</h2></div>
            <div class="readonly-list">
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col filter">
                        <label>Nome completo</label>
                        <input type="text" class="width-600 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['nome_completo'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="col filter">
                        <label>CPF</label>
                        <input type="text" class="width-400 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['cpf'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                </div>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col filter">
                        <label>Sexo</label>
                        <input type="text" class="width-300 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['sexo'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="col filter">
                        <label>Data de nascimento</label>
                        <input type="text" class="width-300 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['data_nascimento'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                </div>
            </div>

            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Vítimas</h2></div>
            <?php if (!empty($medida['vitimas'])): ?>
                <?php foreach ($medida['vitimas'] as $vitima): ?>
                    <div class="readonly-list">
                        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                            <div class="col filter">
                                <label>Nome completo</label>
                                <input type="text" class="width-600 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['nome_completo'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                            <div class="col filter">
                                <label>CPF</label>
                                <input type="text" class="width-300 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['cpf'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                        </div>
                        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                            <div class="col filter">
                                <label>Sexo</label>
                                <input type="text" class="width-300 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['sexo'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                            <div class="col filter">
                                <label>Data de nascimento</label>
                                <input type="text" class="width-300 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['data_nascimento'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                        </div>                        
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label class="status status-primary-light padding-lr-lg">Nenhuma vítima informada no cadastro inicial.</label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="display-flex row row-full justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label>Prioridades do processo</label>
                    <div class="badge-line">
                        <?php if (!empty($medida['prioridades_processo'])): ?>
                            <?php foreach ($medida['prioridades_processo'] as $prioridade): ?>
                                <?php
                                $rawPrior = trim((string) ($prioridade['codigo'] ?? $prioridade['prioridade'] ?? ''));
                                $codigoPrior = PrioridadesProcessuaisCatalog::normalizeToCodigo($rawPrior) ?? $rawPrior;
                                $textoPrior = PrioridadesProcessuaisCatalog::isValidCodigo($codigoPrior)
                                    ? PrioridadesProcessuaisCatalog::labelForCodigo($codigoPrior)
                                    : ($rawPrior !== '' ? $rawPrior : 'Prioridade');
                                ?>
                                <span class="status status-primary-light padding-lr-lg"><?php echo htmlspecialchars($textoPrior, ENT_QUOTES); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="minor-info">Processo sem prioridade processual cadastrada.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($medida['cejusc_tarefa_id'])): ?>
                    <div class="col filter">
                        <label>Cejusc</label>
                        <div class="badge-line">
                            <button type="button" class="btn btn-secondary-outline" id="abrir-cejusc-btn" data-cejusc-id="<?php echo htmlspecialchars((string) $medida['cejusc_tarefa_id'], ENT_QUOTES); ?>">Abrir tarefa Cejusc</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="edit-observacoes">Observações</label>
                    <input type="text" id="edit-observacoes" class="width-800 height-md" value="<?php echo htmlspecialchars((string) ($medida['observacoes'] ?? ''), ENT_QUOTES); ?>" placeholder="Opcional">
                </div>
            </div>

            <?php
            $cartorioAlocacaoPrefix = 'medidas';
            $cartorioAlocacaoEntityId = $idMedida;
            $cartorioAlocacaoDesalocarEndpoint = '/api/cartorio-medidas-protetivas/desalocar.php';
            $cartorioAlocacaoAlocarEndpoint = '/api/cartorio-medidas-protetivas/alocar.php';
            $cartorioAlocacaoAlocados = $alocadosEditMedida;
            $cartorioAlocacaoBloqueada = $tarefaFinalizadaOuCancelada;
            $cartorioAlocacaoUserId = $userId !== null ? trim((string) $userId) : '';
            $cartorioAlocacaoIsGestao = $isGestaoAlocEditMedida;
            include __DIR__ . '/_cartorio_alocacao_direcionada_edit.php';
            ?>

            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-medida-id" value="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>">
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
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php if ($medida !== null): ?>
        <script>
            window.__currentMedidaId = <?php echo json_encode($idMedida, JSON_UNESCAPED_UNICODE); ?>;
            window.__initialObservacoes = <?php echo json_encode((string) ($medida['observacoes'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script>
            (function () {
                if (!window.App || !App.CartorioSimpleEdit || typeof App.CartorioSimpleEdit.initialize !== 'function') {
                    return;
                }

                App.CartorioSimpleEdit.initialize({
                    entityId: window.__currentMedidaId,
                    backButtonId: 'voltar-btn',
                    backHref: '/frame.php?page=medidasprotetivas',
                    linkedButtonId: 'abrir-cejusc-btn',
                    linkedHrefBuilder: function (button) {
                        var cejuscId = (button.getAttribute('data-cejusc-id') || '').trim();
                        return cejuscId ? ('/frame.php?page=cejuscedit&id=' + encodeURIComponent(cejuscId)) : '';
                    },
                    saveButtonId: 'salvar-btn',
                    observationFieldId: 'edit-observacoes',
                    initialObservation: window.__initialObservacoes || '',
                    updateEndpoint: '/api/cartorio-medidas-protetivas/update.php',
                    cancelButtonId: 'cancelar-tarefa-btn',
                    cancelEndpoint: '/api/cartorio-medidas-protetivas/cancel.php',
                    deleteButtonId: 'excluir-tarefa-btn',
                    deleteEndpoint: '/api/cartorio-medidas-protetivas/delete.php',
                    deleteConfirmKey: 'excluir-medida-protetiva',
                    deleteConfirmMessage: 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão desta medida protetiva.'
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
