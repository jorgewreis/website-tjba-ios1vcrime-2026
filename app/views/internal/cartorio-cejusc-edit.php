<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/CejuscSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
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
    $subtarefasRepo->ensureEnviarJuntarPair($idTarefa);
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição Cejusc - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
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

    <div class="card-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="row filter">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php else: ?>
            <div class="row table header">Informações</div>
            <div class="row filter" style="justify-content: flex-start;">
                <div class="col filter">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-md height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <?php
                    $classeReadonly = ($classeNome !== 'Classe não informada');
                    $classeValue = $classeReadonly ? ($classeCodigo !== '' ? $classeCodigo . ' - ' . $classeNome : $classeNome) : '';
                    $classeClass = 'width-lg height-md' . ($classeReadonly ? ' readonly' : '');
                    ?>
                    <input type="text" id="filter-classe" class="<?php echo htmlspecialchars($classeClass, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($classeValue, ENT_QUOTES); ?>"<?php echo $classeReadonly ? ' readonly' : ' placeholder="Informe a classe processual"'; ?>>
                </div>
                <div class="col filter">
                    <label for="filter-reu">Réu</label>
                    <input type="text" id="filter-reu" class="width-xl height-md readonly" value="<?php echo htmlspecialchars($reuNome, ENT_QUOTES); ?>" readonly>
                </div>
            </div>

            <?php
            $statusCartaLabel = 'Em andamento';
            $statusCartaClass = 'status-current';
            if ($statusAtual === 'concluída') {
                $statusCartaLabel = 'Finalizada';
                $statusCartaClass = 'status-finished';
            } elseif ($statusAtual === 'pendente') {
                $statusCartaLabel = 'Pendente';
                $statusCartaClass = 'status-pending';
            } elseif ($statusAtual === 'cancelada') {
                $statusCartaLabel = 'Cancelada';
                $statusCartaClass = 'status-cancelled';
            }
            $currentSubtarefaTitle = '';
            foreach ($subtarefas as $s) {
                if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                    $currentSubtarefaTitle = $subtarefaLabels[$s['tipo'] ?? ''] ?? $s['tipo'] ?? '';
                    break;
                }
            }
            if ($currentSubtarefaTitle === '' && $statusAtual === 'pendente') {
                $currentSubtarefaTitle = $subtarefaLabels[CejuscSubtarefasRepository::TIPO_ORDEM_1] ?? 'Enviar Decisão';
            } elseif ($currentSubtarefaTitle === '' && $statusAtual === 'concluída') {
                $currentSubtarefaTitle = 'Todas as etapas concluídas';
            } elseif ($currentSubtarefaTitle === '') {
                $currentSubtarefaTitle = $statusAtual;
            }
            $progressStatusesEdit = ['pendente', 'pendente'];
            for ($o = 1; $o <= 2; $o++) {
                if (isset($subtarefasPorOrdem[$o])) {
                    $progressStatusesEdit[$o - 1] = trim((string) ($subtarefasPorOrdem[$o]['status'] ?? 'pendente'));
                }
            }
            $mostrarBarraEdit = ($statusCartaLabel === 'Pendente' || $statusCartaLabel === 'Em andamento');
            $segmentosBarraEdit = [];
            if ($mostrarBarraEdit) {
                $segmentosBarraEdit[] = ['class' => 'concluida', 'title' => 'Tarefa cadastrada'];
                for ($i = 0; $i < 2; $i++) {
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
                            <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo $concluidosBarraEdit; ?>" aria-valuemin="0" aria-valuemax="3" aria-label="Progresso das subtarefas">
                                <?php foreach ($segmentosBarraEdit as $seg): ?>
                                    <div class="status-task-progress-segment <?php echo htmlspecialchars($seg['class'], ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($seg['title'], ENT_QUOTES); ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="width-sm status <?php echo htmlspecialchars($statusCartaClass, ENT_QUOTES); ?>" style="border: 1px solid var(--color-gray-500);"><?php echo htmlspecialchars($statusCartaLabel, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $tarefaCreatedRaw = $tarefa['created_at'] ?? '';
            $tarefaCreatedFormatted = '';
            if ($tarefaCreatedRaw !== '') {
                $dt0 = \DateTime::createFromFormat('Y-m-d H:i:s', $tarefaCreatedRaw);
                $tarefaCreatedFormatted = $dt0 ? $dt0->format('d/m/Y - H:i:s') : $tarefaCreatedRaw;
            }
            $cadastroInfoLinha = 'Tarefa cadastrada em ' . $tarefaCreatedFormatted;
            ?>
            <div class="row timeline finished">
                <div class="row">
                    <div class="circle finished"></div>
                    <div class="col filter subtask-status-info">
                        <span class="minor-info">Tarefa cadastrada</span>
                    </div>
                </div>
                <div class="col">
                    <div class="col filter subtask-date">
                        <span class="minor-info"><?php echo htmlspecialchars($cadastroInfoLinha, ENT_QUOTES); ?></span>
                    </div>
                </div>
            </div>

            <?php for ($ordem = 1; $ordem <= 2; $ordem++): ?>
                <?php
                $tipo = $ordemParaTipo[$ordem] ?? '';
                $label = $subtarefaLabels[$tipo] ?? 'Passo ' . $ordem;
                $st = $subtarefasPorOrdem[$ordem] ?? null;
                $circleClass = '';
                $statusTexto = 'Pendente';
                $dataMovimento = '';
                $minorTexto = $label;
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
                    if ($raw !== '') {
                        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw);
                        $dataMovimento = $dt ? $dt->format('d/m/Y - H:i:s') : $raw;
                    }
                    if ($status === 'concluida') {
                        $circleClass = 'finished';
                        $nomeExibir = $nomeResponsavel !== '' ? $nomeResponsavel : '—';
                        $minorTexto = $label . ' concluído';
                        $infoLinha = 'Concluído por ' . $nomeExibir . ' em ' . $dataMovimento;
                        $dateLabel = $infoLinha;
                    } elseif ($status === 'em_andamento') {
                        $circleClass = 'current';
                        $statusTexto = 'Em andamento';
                        $minorTexto = $label;
                        if ($dataMovimento !== '') {
                            $dateLabel = 'Aguardando movimentação';
                        } else {
                            $dateLabel = 'Aguardando conclusão';
                        }
                    } else {
                        $minorTexto = $label;
                        $dateLabel = 'Aguardando conclusão';
                    }
                }
                if ($infoLinha !== '') {
                    $dateLabel = $infoLinha;
                }
                ?>
                <div class="row <?php if ($circleClass === 'current') { echo 'timeline current'; } elseif ($circleClass === 'finished') { echo 'timeline finished'; } else { echo 'timeline'; } ?>">
                    <div class="row">
                        <?php if ($circleClass !== ''): ?>
                            <div class="circle <?php echo htmlspecialchars($circleClass, ENT_QUOTES); ?>"></div>
                        <?php else: ?>
                            <div class="circle" style="background-color: var(--color-gray-300);"></div>
                        <?php endif; ?>
                        <div class="col filter subtask-status-info">
                            <span class="minor-info"><?php echo htmlspecialchars($minorTexto, ENT_QUOTES); ?></span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="col filter subtask-date">
                            <span class="minor-info"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES); ?></span>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <input type="hidden" id="hidden-carta-id" value="<?php echo htmlspecialchars($idTarefa, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="row buttons">
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <?php if ($errorMessage === ''): ?>
                <?php if ($podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-secondary-outline" id="cancelar-tarefa-btn" type="button" title="Alterar status da tarefa para Cancelada">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-tarefa-btn" type="button" title="Excluir esta tarefa e todas as subtarefas">Excluir tarefa</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="row pagination">
            <div class="col">
                <label id="cadastro-feedback" class="status-placeholder" aria-live="polite"></label>
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
                        if (!confirm('Deseja cancelar esta tarefa? O status será alterado para "Cancelada".')) return;
                        btnCancelar.disabled = true;
                        fetch('/api/cartorio-cejusc/cancel.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentCejuscId })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao cancelar.' });
                                else alert(data.error || 'Erro ao cancelar.');
                                btnCancelar.disabled = false;
                            }
                        })
                        .catch(function () {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                            else alert('Erro de conexão.');
                            btnCancelar.disabled = false;
                        });
                    });
                }

                var btnExcluir = document.getElementById('excluir-tarefa-btn');
                if (btnExcluir && window.__currentCejuscId) {
                    btnExcluir.addEventListener('click', function () {
                        if (!confirm('Tem certeza que deseja excluir esta tarefa e todas as subtarefas? Esta ação não pode ser desfeita.')) return;
                        btnExcluir.disabled = true;
                        fetch('/api/cartorio-cejusc/delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentCejuscId })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.deleted) {
                                window.location.href = '/frame.php?page=cejusc';
                            } else {
                                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao excluir.' });
                                else alert(data.error || 'Erro ao excluir.');
                                btnExcluir.disabled = false;
                            }
                        })
                        .catch(function () {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                            else alert('Erro de conexão.');
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
