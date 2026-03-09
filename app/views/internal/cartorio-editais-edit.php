<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
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
$decursoInfo = ['data_limite_formatada' => '', 'prazo_cumprido' => false];
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
$buttonTitleByOrdem = [
    1 => 'Informar expedição do edital',
    2 => 'Informar envio para publicação',
    3 => 'Informar certificação da publicação',
    4 => 'Informar certificação de decurso do prazo',
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

    $decursoInfo = $service->getDecursoInfo($idEdital);

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição Edital - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno - Editais -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Edital</h1>
        <p class="description-page">Acompanhe expedição, envio ao DJEN, certificação da publicação e decurso do prazo.</p>
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
                    <input type="text" id="filter-classe" class="width-xl height-md readonly" value="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <div class="row filter">
                <div class="col filter">
                    <label for="edit-tipo-edital">Tipo de edital</label>
                    <input type="text" id="edit-tipo-edital" class="width-lg height-md readonly" value="<?php echo htmlspecialchars($tipoEditalValue, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="edit-prazo-dias">Prazo do edital (dias)</label>
                    <input type="text" id="edit-prazo-dias" class="width-sm height-md readonly" value="<?php echo $prazoDiasValue > 0 ? (int) $prazoDiasValue : '—'; ?>" readonly>
                </div>
            </div>
            <div class="row filter">
                <div class="col filter">
                    <label for="edit-observacoes">Observações</label>
                    <input type="text" id="edit-observacoes" class="width-xl height-md" value="<?php echo htmlspecialchars($observacoesValue, ENT_QUOTES); ?>" placeholder="Opcional">
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
                $currentSubtarefaTitle = $subtarefaLabels[EditaisSubtarefasRepository::TIPO_ORDEM_1] ?? 'Expedição de Edital';
            } elseif ($currentSubtarefaTitle === '' && $statusAtual === 'concluída') {
                $currentSubtarefaTitle = 'Todas as etapas concluídas';
            } elseif ($currentSubtarefaTitle === '') {
                $currentSubtarefaTitle = $statusAtual;
            }
            $progressStatusesEdit = ['pendente', 'pendente', 'pendente', 'pendente'];
            for ($o = 1; $o <= 4; $o++) {
                if (isset($subtarefasPorOrdem[$o])) {
                    $progressStatusesEdit[$o - 1] = trim((string) ($subtarefasPorOrdem[$o]['status'] ?? 'pendente'));
                }
            }
            $mostrarBarraEdit = ($statusCartaLabel === 'Pendente' || $statusCartaLabel === 'Em andamento');
            $segmentosBarraEdit = [];
            if ($mostrarBarraEdit) {
                $segmentosBarraEdit[] = ['class' => 'concluida', 'title' => 'Edital cadastrado'];
                for ($i = 0; $i < 4; $i++) {
                    $statusSeg = $progressStatusesEdit[$i] ?? 'pendente';
                    $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                    $segmentosBarraEdit[] = ['class' => $segClass, 'title' => $subtarefaLabels[$ordemParaTipo[$i + 1] ?? ''] ?? ''];
                }
            }
            ?>
            <div class="row timeline-header">
                <div class="col filter">
                    <span class="header-timeline">Subtarefas - Timeline</span>
                </div>
                <div class="col filter" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                    <?php if ($mostrarBarraEdit): ?>
                        <div class="width-sm status <?php echo htmlspecialchars($statusCartaClass, ENT_QUOTES); ?> status-with-progress" style="border: 1px solid var(--color-gray-500);">
                            <span class="status-task-label"><?php echo htmlspecialchars($statusCartaLabel, ENT_QUOTES); ?></span>
                            <?php $concluidosBarraEdit = 1 + count(array_filter($progressStatusesEdit, fn($x) => $x === 'concluida')); ?>
                            <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo $concluidosBarraEdit; ?>" aria-valuemin="0" aria-valuemax="5" aria-label="Progresso das subtarefas">
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
            $editalCreatedRaw = $edital['created_at'] ?? '';
            $editalCreatedFormatted = '';
            if ($editalCreatedRaw !== '') {
                $dt0 = \DateTime::createFromFormat('Y-m-d H:i:s', $editalCreatedRaw);
                $editalCreatedFormatted = $dt0 ? $dt0->format('d/m/Y - H:i') : $editalCreatedRaw;
            }
            $cadastroInfoLinha = 'Edital cadastrado em ' . $editalCreatedFormatted;
            ?>
            <div class="row timeline finished">
                <div class="row">
                    <div class="circle finished"></div>
                    <div class="col filter subtask-status-info">
                        <span class="minor-info">Edital cadastrado</span>
                    </div>
                </div>
                <div class="col">
                    <div class="col filter subtask-date">
                        <span class="minor-info"><?php echo htmlspecialchars($cadastroInfoLinha !== '' ? $cadastroInfoLinha : 'Edital cadastrado', ENT_QUOTES); ?></span>
                    </div>
                </div>
            </div>

            <?php for ($ordem = 1; $ordem <= 4; $ordem++): ?>
                <?php
                $tipo = $ordemParaTipo[$ordem] ?? '';
                $label = $subtarefaLabels[$tipo] ?? 'Passo ' . $ordem;
                $st = $subtarefasPorOrdem[$ordem] ?? null;
                $circleClass = '';
                $minorTexto = $timelineMinorPendente[$ordem] ?? $label;
                $dateLabel = 'Aguardando conclusão';
                $infoLinha = '';
                $stId = $st['id'] ?? '';
                $dadosEspecificos = [];
                if ($st !== null && !empty(trim((string) ($st['dados_especificos'] ?? '')))) {
                    $dadosEspecificos = json_decode((string) $st['dados_especificos'], true);
                    if (!is_array($dadosEspecificos)) {
                        $dadosEspecificos = [];
                    }
                }
                $nomeUsuario = trim((string) ($dadosEspecificos['nome'] ?? ''));
                $dataPublicacao = trim((string) ($dadosEspecificos['data_publicacao'] ?? ''));
                if ($st !== null) {
                    $status = trim((string) ($st['status'] ?? ''));
                    $raw = $st['updated_at'] ?? $st['created_at'] ?? '';
                    $dataMovimento = '';
                    if ($raw !== '') {
                        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw);
                        $dataMovimento = $dt ? $dt->format('d/m/Y - H:i') : $raw;
                    }
                    if ($status === 'concluida') {
                        $circleClass = 'finished';
                        if ($ordem === 1) {
                            $minorTexto = 'Edital expedido';
                            $infoLinha = $nomeUsuario !== '' && $dataMovimento !== '' ? 'Expedição realizada por ' . $nomeUsuario . ' em ' . $dataMovimento : ($dataMovimento !== '' ? $dataMovimento : 'Concluído');
                        } elseif ($ordem === 2) {
                            $minorTexto = 'Edital enviado para publicação no DJEN';
                            $infoLinha = $nomeUsuario !== '' && $dataMovimento !== '' ? 'Edital enviado por ' . $nomeUsuario . ' em ' . $dataMovimento : ($dataMovimento !== '' ? $dataMovimento : 'Concluído');
                        } elseif ($ordem === 3) {
                            $dtPub = $dataPublicacao !== '' ? \DateTime::createFromFormat('Y-m-d', $dataPublicacao) : null;
                            $dataPubExibir = $dtPub ? $dtPub->format('d/m/Y') : ($dataPublicacao !== '' ? $dataPublicacao : $dataMovimento);
                            $minorTexto = 'Publicação do edital realizada em ' . $dataPubExibir;
                            $infoLinha = $nomeUsuario !== '' && $dataMovimento !== '' ? 'Certificação realizada por ' . $nomeUsuario . ' em ' . $dataMovimento : ($dataMovimento !== '' ? $dataMovimento : 'Concluído');
                        } else {
                            $minorTexto = 'Decurso do prazo do edital certificado';
                            $infoLinha = $nomeUsuario !== '' && $dataMovimento !== '' ? 'Certificação realizada por ' . $nomeUsuario . ' em ' . $dataMovimento : ($dataMovimento !== '' ? $dataMovimento : 'Concluído');
                        }
                        $dateLabel = $infoLinha;
                    } elseif ($status === 'em_andamento') {
                        $circleClass = 'current';
                        $minorTexto = $timelineMinorPendente[$ordem] ?? $label;
                        $dateLabel = 'Aguardando movimentação';
                    } else {
                        $minorTexto = $timelineMinorPendente[$ordem] ?? $label;
                        $dateLabel = 'Aguardando conclusão';
                    }
                }
                ?>
                <div class="row timeline <?php echo $circleClass !== '' ? $circleClass : ''; ?>">
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
                        <?php if ($st !== null && trim((string) ($st['status'] ?? '')) === 'em_andamento'): ?>
                            <?php
                            $podeInformarOrdem4 = true;
                            $mensagemDecurso = '';
                            if ($ordem === 4) {
                                $podeInformarOrdem4 = $decursoInfo['prazo_cumprido'];
                                $mensagemDecurso = $decursoInfo['data_limite_formatada'] !== '' && !$podeInformarOrdem4
                                    ? 'Disponível após ' . $decursoInfo['data_limite_formatada'] . ' (decurso do prazo)'
                                    : '';
                            }
                            ?>
                            <div class="col filter" style="margin-top: 0.5rem;">
                                <?php if ($ordem === 3): ?>
                                    <label for="edit-data-publicacao-<?php echo $ordem; ?>">Data da publicação</label>
                                    <input type="date" id="edit-data-publicacao-<?php echo $ordem; ?>" class="width-md height-md" data-ordem="<?php echo $ordem; ?>" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">
                                <?php endif; ?>
                                <?php if ($ordem === 4 && $mensagemDecurso !== ''): ?>
                                    <span class="minor-info" style="display: block; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($mensagemDecurso, ENT_QUOTES); ?></span>
                                <?php endif; ?>
                                <button type="button" class="btn btn-primary btn-informar-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-ordem="<?php echo $ordem; ?>" style="margin-top: 0.25rem;" <?php if ($ordem === 4 && !$podeInformarOrdem4): ?>disabled title="<?php echo htmlspecialchars($mensagemDecurso, ENT_QUOTES); ?>"<?php endif; ?>><?php echo htmlspecialchars($buttonTitleByOrdem[$ordem] ?? 'Informar', ENT_QUOTES); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>

            <input type="hidden" id="hidden-edital-id" value="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="row buttons">
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

    <?php if ($edital !== null): ?>
        <script>
            window.__currentEditalId = <?php echo json_encode($idEdital, JSON_UNESCAPED_UNICODE); ?>;
            window.__initialObservacoes = <?php echo json_encode($observacoesValue, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script>
            (function () {
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
                        if (!confirm('Deseja cancelar este edital? O status será alterado para "Cancelada".')) return;
                        btnCancelar.disabled = true;
                        fetch('/api/cartorio-editais/cancel.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentEditalId })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) window.location.reload();
                            else {
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
                    btnExcluir.addEventListener('click', function () {
                        if (!confirm('Tem certeza que deseja excluir este edital e todas as subtarefas? Esta ação não pode ser desfeita.')) return;
                        btnExcluir.disabled = true;
                        fetch('/api/cartorio-editais/delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: window.__currentEditalId })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.deleted) {
                                window.location.href = '/frame.php?page=editais';
                            } else {
                                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao excluir.' });
                                else alert(data.error || 'Erro ao excluir.');
                                btnExcluir.disabled = false;
                            }
                        })
                        .catch(function () {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                            btnExcluir.disabled = false;
                        });
                    });
                }

                document.querySelectorAll('.btn-informar-subtarefa').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var subtarefaId = this.getAttribute('data-subtarefa-id');
                        var ordem = parseInt(this.getAttribute('data-ordem') || '0', 10);
                        if (!subtarefaId) return;
                        var payload = { subtarefa_id: subtarefaId };
                        if (ordem === 3) {
                            var dataPubEl = document.getElementById('edit-data-publicacao-3');
                            var dataPub = (dataPubEl && dataPubEl.value) ? dataPubEl.value.trim() : '';
                            if (!dataPub) {
                                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Campo obrigatório', message: 'Informe a data da publicação.' });
                                return;
                            }
                            payload.dados_especificos = { data_publicacao: dataPub };
                        }
                        btn.disabled = true;
                        fetch('/api/cartorio-editais/concluir-subtarefa.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) window.location.reload();
                            else {
                                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao concluir.' });
                                btn.disabled = false;
                            }
                        })
                        .catch(function () {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                            btn.disabled = false;
                        });
                    });
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
