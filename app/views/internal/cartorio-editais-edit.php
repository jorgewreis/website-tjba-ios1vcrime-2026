<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
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
$subtarefaLabels = [
    EditaisSubtarefasRepository::TIPO_ORDEM_1 => 'Expedição de Edital',
    EditaisSubtarefasRepository::TIPO_ORDEM_2 => 'Envio para publicação no DJEN',
    EditaisSubtarefasRepository::TIPO_ORDEM_3 => 'Certificação da publicação',
    EditaisSubtarefasRepository::TIPO_ORDEM_4 => 'Certificação de decurso do prazo',
];
$taskTitleByOrdem = [
    1 => 'Aguardando expedição do edital',
    2 => 'Aguardando envio para publicação',
    3 => 'Aguardando certificação da publicação do edital',
    4 => 'Aguardando certificação de decurso do prazo do edital',
];
$timelineMinorPendente = [
    1 => 'Aguardando expedição do edital',
    2 => 'Aguardando envio do edital para publicação no DJEN',
    3 => 'Aguardando certificação da publicação do edital',
    4 => 'Aguardando certificação de decurso do prazo do edital',
];
$ordemParaTipo = [
    1 => EditaisSubtarefasRepository::TIPO_ORDEM_1,
    2 => EditaisSubtarefasRepository::TIPO_ORDEM_2,
    3 => EditaisSubtarefasRepository::TIPO_ORDEM_3,
    4 => EditaisSubtarefasRepository::TIPO_ORDEM_4,
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
    $subtarefasRepo->ensureQuatroSubtarefas($idEdital);
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

$perfisPermitidosExcluir = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
$perfisPermitidosCancelar = ['SERVIDOR', 'ADMIN', 'DIRETORA', 'MAGISTRADA'];
$userPerfisEdit = [];
$userIdEdit = $_SESSION['id_usuario'] ?? null;
if ($userIdEdit !== null && $userIdEdit !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$userIdEdit]);
    $userPerfisEdit = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
}
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;
$podeCancelarTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosCancelar)) > 0;
$tarefaFinalizadaOuCancelada = in_array($statusAtual, ['cancelada', 'concluída'], true);
$timelineData = $edital !== null
    ? cartorioTimelineBuildViewData('editais', [
        'task' => $edital,
        'task_status' => $statusAtual,
        'subtarefas' => $subtarefas,
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

    <div class="card-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php else: ?>
            <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Informações</div>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" style="justify-content: flex-start;">
                <div class="col filter">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-200 height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <input type="text" id="filter-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label for="edit-tipo-edital">Tipo de edital</label>
                    <input type="text" id="edit-tipo-edital" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($tipoEditalValue, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="edit-prazo-dias">Prazo do edital (dias)</label>
                    <input type="text" id="edit-prazo-dias" class="width-100 height-md readonly" value="<?php echo $prazoDiasValue > 0 ? (int) $prazoDiasValue : '—'; ?>" readonly>
                </div>
            </div>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label for="edit-observacoes">Observações</label>
                    <input type="text" id="edit-observacoes" class="width-600 height-md" value="<?php echo htmlspecialchars($observacoesValue, ENT_QUOTES); ?>" placeholder="Opcional">
                </div>
            </div>

            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-edital-id" value="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>">
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
                var pendingConfirmations = {};
                function requireSecondClick(key, message) {
                    var now = Date.now();
                    var validUntil = Number(pendingConfirmations[key] || 0);
                    if (validUntil > now) {
                        delete pendingConfirmations[key];
                        return true;
                    }
                    pendingConfirmations[key] = now + 5000;
                    if (typeof showToast === 'function') {
                        showToast({ type: 'info', variant: 'full', title: 'Confirmação', message: message });
                    }
                    return false;
                }
                var voltarBtn = document.getElementById('voltar-btn');
                if (voltarBtn) voltarBtn.addEventListener('click', function (e) { e.preventDefault(); window.location.href = '/frame.php?page=editais'; }, { capture: true });

                var salvarBtn = document.getElementById('salvar-btn');
                var observacoesEl = document.getElementById('edit-observacoes');
                function updateSalvarState() {
                    if (!salvarBtn) return;
                    var current = (observacoesEl && observacoesEl.value) ? observacoesEl.value.trim() : '';
                    salvarBtn.disabled = (current === (window.__initialObservacoes || ''));
                }
                if (observacoesEl) observacoesEl.addEventListener('input', updateSalvarState);
                updateSalvarState();

                salvarBtn && salvarBtn.addEventListener('click', function () {
                    if (salvarBtn.disabled) return;
                    var payload = { id: window.__currentEditalId, observacoes: (observacoesEl && observacoesEl.value) ? observacoesEl.value.trim() : '' };
                    salvarBtn.disabled = true;
                    fetch('/api/cartorio-editais/update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Alterações salvas.' });
                            window.__initialObservacoes = payload.observacoes;
                            updateSalvarState();
                        } else {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao salvar.' });
                            salvarBtn.disabled = false;
                        }
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        salvarBtn.disabled = false;
                    });
                });

                var btnCancelar = document.getElementById('cancelar-tarefa-btn');
                if (btnCancelar && window.__currentEditalId) {
                    btnCancelar.addEventListener('click', function () {
                        btnCancelar.disabled = true;
                        fetch('/api/cartorio-editais/cancel.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentEditalId })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'Tarefa cancelada com sucesso.' });
                                window.setTimeout(function () { window.location.reload(); }, 250);
                            } else {
                                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao cancelar.' });
                                btnCancelar.disabled = false;
                            }
                        })
                        .catch(function () {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                            btnCancelar.disabled = false;
                        });
                    });
                }

                var btnExcluir = document.getElementById('excluir-tarefa-btn');
                if (btnExcluir && window.__currentEditalId) {
                    function queuePostReloadToast(type, title, message) {
                        try {
                            var key = 'cartorio.postReloadToast';
                            var raw = sessionStorage.getItem(key);
                            var fila = raw ? JSON.parse(raw) : [];
                            var lista = Array.isArray(fila) ? fila : ((fila && fila.message) ? [fila] : []);
                            lista.push({ type: type, title: title, message: message });
                            sessionStorage.setItem(key, JSON.stringify(lista));
                        } catch (e) {}
                    }
                    btnExcluir.addEventListener('click', function () {
                        if (!requireSecondClick('excluir-edital', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão deste edital.')) return;
                        btnExcluir.disabled = true;
                        fetch('/api/cartorio-editais/delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentEditalId })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.deleted) {
                                (Array.isArray(data.subtarefas_toasts) ? data.subtarefas_toasts : []).forEach(function (msg) {
                                    queuePostReloadToast('info', 'Subtarefa excluída', msg);
                                });
                                if (data.tarefa_toast) {
                                    queuePostReloadToast('success', 'Sucesso', data.tarefa_toast);
                                }
                                window.location.href = '/frame.php?page=editais';
                            } else {
                                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao excluir.' });
                                btnExcluir.disabled = false;
                            }
                        })
                        .catch(function () {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                            btnExcluir.disabled = false;
                        });
                    });
                }

            })();
        </script>
    <?php endif; ?>
</body>
</html>
