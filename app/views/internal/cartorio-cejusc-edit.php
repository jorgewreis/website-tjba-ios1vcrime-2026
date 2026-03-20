<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/CejuscSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
require_once __DIR__ . '/../../services/CejuscService.php';

$connection = Conection::getInstance()->getConnection();

$idTarefa = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$tarefa = null;

if ($idTarefa === '') {
    $errorMessage = 'Identificador da tarefa Cejusc não informado.';
} else {
    try {
        $service = new CejuscService($connection);
        $tarefa = $service->find($idTarefa);
        if ($tarefa === null) {
            $errorMessage = 'Tarefa Cejusc não encontrada.';
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar a tarefa: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeCodigo = '';
$classeNome = 'Classe não informada';
$reuNome = '';
$statusAtual = 'pendente';
$subtarefas = [];
$subtarefasPorOrdem = [];
$subtarefaLabels = [
    CejuscSubtarefasRepository::TIPO_ORDEM_1 => 'Enviar Decisão',
    CejuscSubtarefasRepository::TIPO_ORDEM_2 => 'Juntar termo de envio',
];
$ordemParaTipo = [
    1 => CejuscSubtarefasRepository::TIPO_ORDEM_1,
    2 => CejuscSubtarefasRepository::TIPO_ORDEM_2,
];
$temSubtarefas = false;
$temEmAndamentoOuPendente = false;
$ruleServidor = '?';
$ruleName = 'ALLOCATE-CEJUSC';

if ($tarefa !== null) {
    $processoNumero = trim((string) ($tarefa['numero_processo_origem'] ?? ''));
    $processosRepo = new ProcessosRepository($connection);
    $processoRow = $processosRepo->findById((string) ($tarefa['processo_origem_id'] ?? ''));
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
    $reuNome = trim((string) ($tarefa['reu_nome'] ?? ''));
    $statusAtual = trim((string) ($tarefa['status'] ?? 'pendente'));
    if ($statusAtual === '') {
        $statusAtual = 'pendente';
    }

    $subtarefasRepo = new CejuscSubtarefasRepository($connection);
    $subtarefasRepo->ensureFluxoCompleto($idTarefa);
    $subtarefas = $subtarefasRepo->findByCejuscId($idTarefa);
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
    } catch (\Throwable $e) {
        // mantém padrão
    }
}

$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA'];
$perfisPermitidosCancelar = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'];
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
$timelineData = $tarefa !== null
    ? cartorioTimelineBuildViewData('cejusc', [
        'task' => $tarefa,
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
        <title>Edição Cejusc - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno - Cejusc -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Tarefa Cejusc</h1>
        <p class="description-page">Acompanhe o envio da decisão ao Cejusc e a juntada do comprovante.</p>
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
                    <?php
                    $classeReadonly = ($classeNome !== 'Classe não informada');
                    $classeValue = $classeReadonly ? $classeNome : '';
                    $classeClass = 'width-600 height-md' . ($classeReadonly ? ' readonly' : '');
                    ?>
                    <input type="text" id="filter-classe" class="<?php echo htmlspecialchars($classeClass, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($classeValue, ENT_QUOTES); ?>"<?php echo $classeReadonly ? ' readonly' : ' placeholder="Preenchido automaticamente pelo DataJud"'; ?>>
                </div>
                <div class="col filter">
                    <label for="filter-reu">Réu</label>
                    <input type="text" id="filter-reu" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($reuNome, ENT_QUOTES); ?>" readonly>
                </div>
            </div>

            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-carta-id" value="<?php echo htmlspecialchars($idTarefa, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <?php if ($errorMessage === ''): ?>
                <?php if ($podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-danger-outline" id="cancelar-tarefa-btn" type="button" title="Alterar status da tarefa para Cancelada">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-tarefa-btn" type="button" title="Excluir esta tarefa e todas as subtarefas">Excluir tarefa</button>
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

    <?php if ($tarefa !== null): ?>
        <script>
            window.__currentCejuscId = <?php echo json_encode($idTarefa, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script>
            (function () {
                var pendingConfirmations = {};
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
                function requireSecondClick(key, message) {
                    var now = Date.now();
                    var validUntil = Number(pendingConfirmations[key] || 0);
                    if (validUntil > now) {
                        delete pendingConfirmations[key];
                        return true;
                    }
                    pendingConfirmations[key] = now + 5000;
                    if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Confirmação', message: message });
                    return false;
                }
                var btn = document.getElementById('voltar-btn');
                if (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        window.location.href = '/frame.php?page=cejusc';
                    }, { capture: true });
                }

                var btnCancelar = document.getElementById('cancelar-tarefa-btn');
                if (btnCancelar && window.__currentCejuscId) {
                    btnCancelar.addEventListener('click', function () {
                        btnCancelar.disabled = true;
                        fetch('/api/cartorio-cejusc/cancel.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentCejuscId })
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
                if (btnExcluir && window.__currentCejuscId) {
                    btnExcluir.addEventListener('click', function () {
                        if (!requireSecondClick('excluir-cejusc', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão desta tarefa.')) return;
                        btnExcluir.disabled = true;
                        fetch('/api/cartorio-cejusc/delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentCejuscId })
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
                                window.location.href = '/frame.php?page=cejusc';
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

    <script>
        (function () {
            var btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.location.href = '/frame.php?page=cejusc';
                }, { capture: true });
            }
        })();
    </script>
</body>
</html>
