<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/MedidasProtetivasSubtarefasRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
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

$perfisPermitidosExcluir = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
$perfisPermitidosCancelar = ['SERVIDOR', 'ADMIN', 'DIRETORA', 'MAGISTRADA'];
$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeExcluirTarefa = count(array_intersect($userPerfis, $perfisPermitidosExcluir)) > 0;
$podeCancelarTarefa = count(array_intersect($userPerfis, $perfisPermitidosCancelar)) > 0;

$statusAtual = trim((string) ($medida['status'] ?? 'pendente'));
$tarefaFinalizadaOuCancelada = in_array($statusAtual, ['cancelada', 'concluída'], true);
$timelineData = $medida !== null
    ? cartorioTimelineBuildViewData('medidas_protetivas', [
        'task' => $medida,
        'task_status' => $statusAtual,
        'subtarefas' => $subtarefas,
    ])
    : [];
$subtarefaLabels = [
    MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_1 => 'Cadastro de partes no PJe',
    MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedição de mandados de intimação',
];
$timelineMinorPendente = [
    1 => 'Aguardando cadastro das partes no PJe',
    2 => 'Aguardando expedição de mandados de intimação',
];
$ordemParaTipo = [
    1 => MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_1,
    2 => MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_2,
];
$prioridadeLabels = [
    'mulher' => 'Mulher',
    'crianca_adolescente' => 'Criança/Adolescente',
    'idoso' => 'Idoso',
    'pessoa_deficiencia' => 'Pessoa com deficiência',
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

    <div class="card-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php else: ?>
            <div class="row table subtitle left padding-tb-lg padding-lr-md border-bottom">Informações</div>
            <div class="row table left padding-tb-md padding-lr-md row-gap-10 full-line">
                <div class="col">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-200 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['numero_processo'] ?? ''), ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col">
                    <label for="filter-classe">Classe</label>
                    <input type="text" id="filter-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['classe_nome'] ?? 'Classe não informada'), ENT_QUOTES); ?>" readonly>
                </div>
            </div>

            <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Réu</div>
            <div class="readonly-list">
                <div class="row table left padding-tb-md padding-lr-md border-bottom row-gap-10 full-line">
                    <div class="col filter">
                        <label>Nome completo</label>
                        <input type="text" class="width-600 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['nome_completo'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="col filter">
                        <label>CPF</label>
                        <input type="text" class="width-200 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['cpf'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                </div>
                <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                    <div class="col filter">
                        <label>Sexo</label>
                        <input type="text" class="width-200 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['sexo'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="col filter">
                        <label>Data de nascimento</label>
                        <input type="text" class="width-200 height-md readonly" value="<?php echo htmlspecialchars((string) ($medida['reu']['data_nascimento'] ?? ''), ENT_QUOTES); ?>" readonly>
                    </div>
                </div>
            </div>

            <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Vítimas</div>
            <?php if (!empty($medida['vitimas'])): ?>
                <?php foreach ($medida['vitimas'] as $vitima): ?>
                    <div class="readonly-list">
                        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                            <div class="col filter">
                                <label>Nome completo</label>
                                <input type="text" class="width-600 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['nome_completo'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                            <div class="col filter">
                                <label>CPF</label>
                                <input type="text" class="width-200 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['cpf'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                        </div>
                        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                            <div class="col filter">
                                <label>Sexo</label>
                                <input type="text" class="width-200 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['sexo'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                            <div class="col filter">
                                <label>Data de nascimento</label>
                                <input type="text" class="width-200 height-md readonly" value="<?php echo htmlspecialchars((string) ($vitima['data_nascimento'] ?? ''), ENT_QUOTES); ?>" readonly>
                            </div>
                        </div>                        
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                    <div class="col">
                        <label class="status status-primary-light padding-lr-lg">Nenhuma vítima informada no cadastro inicial.</label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row table between padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label>Prioridades do processo</label>
                    <div class="badge-line">
                        <?php if (!empty($medida['prioridades_processo'])): ?>
                            <?php foreach ($medida['prioridades_processo'] as $prioridade): ?>
                                <span class="status status-primary-light padding-lr-lg"><?php echo htmlspecialchars($prioridadeLabels[(string) ($prioridade['prioridade'] ?? '')] ?? (string) ($prioridade['prioridade'] ?? 'Prioridade'), ENT_QUOTES); ?></span>
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
                            <button class="btn btn-secondary-outline" href="/frame.php?page=cejuscedit&id=<?php echo urlencode((string) $medida['cejusc_tarefa_id']); ?>">Abrir tarefa Cejusc</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label for="edit-observacoes">Observações</label>
                    <input type="text" id="edit-observacoes" class="width-600 height-md" value="<?php echo htmlspecialchars((string) ($medida['observacoes'] ?? ''), ENT_QUOTES); ?>" placeholder="Opcional">
                </div>
            </div>

            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-medida-id" value="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <?php if ($errorMessage === ''): ?>
                <button class="btn btn-primary" id="salvar-btn" type="button">Salvar alterações</button>
                <?php if ($podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-secondary-outline" id="cancelar-tarefa-btn" type="button">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-tarefa-btn" type="button">Excluir tarefa</button>
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

    <?php if ($medida !== null): ?>
        <script>
            window.__currentMedidaId = <?php echo json_encode($idMedida, JSON_UNESCAPED_UNICODE); ?>;
            window.__initialObservacoes = <?php echo json_encode((string) ($medida['observacoes'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script>
            (function () {
                var voltarBtn = document.getElementById('voltar-btn');
                if (voltarBtn) {
                    voltarBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        window.location.href = '/frame.php?page=medidasprotetivas';
                    }, { capture: true });
                }

                var salvarBtn = document.getElementById('salvar-btn');
                var observacoesEl = document.getElementById('edit-observacoes');
                function updateSalvarState() {
                    if (!salvarBtn) return;
                    var current = (observacoesEl && observacoesEl.value) ? observacoesEl.value.trim() : '';
                    salvarBtn.disabled = current === (window.__initialObservacoes || '');
                }
                observacoesEl?.addEventListener('input', updateSalvarState);
                updateSalvarState();

                salvarBtn?.addEventListener('click', function () {
                    if (salvarBtn.disabled) return;
                    var payload = { id: window.__currentMedidaId, observacoes: (observacoesEl?.value || '').trim() };
                    salvarBtn.disabled = true;
                    fetch('/api/cartorio-medidas-protetivas/update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (typeof showToast === 'function') {
                                showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Alterações salvas.' });
                            }
                            window.__initialObservacoes = payload.observacoes;
                            updateSalvarState();
                            return;
                        }
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao salvar.' });
                        }
                        salvarBtn.disabled = false;
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        }
                        salvarBtn.disabled = false;
                    });
                });

                var btnCancelar = document.getElementById('cancelar-tarefa-btn');
                btnCancelar?.addEventListener('click', function () {
                    btnCancelar.disabled = true;
                    fetch('/api/cartorio-medidas-protetivas/cancel.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: window.__currentMedidaId })
                    })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao cancelar.' });
                        }
                        btnCancelar.disabled = false;
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        }
                        btnCancelar.disabled = false;
                    });
                });

                var btnExcluir = document.getElementById('excluir-tarefa-btn');
                btnExcluir?.addEventListener('click', function () {
                    if (!confirm('Tem certeza que deseja excluir esta medida protetiva e suas subtarefas?')) return;
                    btnExcluir.disabled = true;
                    fetch('/api/cartorio-medidas-protetivas/delete.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: window.__currentMedidaId })
                    })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.deleted) {
                            window.location.href = '/frame.php?page=medidasprotetivas';
                            return;
                        }
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao excluir.' });
                        }
                        btnExcluir.disabled = false;
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        }
                        btnExcluir.disabled = false;
                    });
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
