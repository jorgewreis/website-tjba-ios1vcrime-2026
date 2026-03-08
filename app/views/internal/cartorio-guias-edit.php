<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/GuiasSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
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
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Cálculo da pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedir Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Assinar Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Gerar PDF da documentação',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Enviar documentos',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Juntar termo de envio',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Expedir certidão',
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
];
$timelineMinorPendente = [
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Cálculo da pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedir guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Assinar guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Gerar PDF da documentação',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Enviar documentos',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Juntar termo de envio',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Expedir certidão',
];
$statusCartaLabel = 'Em andamento';
$statusCartaClass = 'status-current';
$progressStatusesEdit = ['pendente', 'pendente', 'pendente', 'pendente', 'pendente', 'pendente', 'pendente'];
$mostrarBarraEdit = false;
$segmentosBarraEdit = [];
$cadastroInfoLinha = '';
$classeCodigo = '';
$classeNome = 'Classe não informada';
$currentSubtarefaTitle = '';
if ($guia !== null) {
    $subtarefasRepo = new GuiasSubtarefasRepository($connection);
    $subtarefasRepo->ensurePrimeiraSubtarefa($idGuia);
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
    for ($o = 1; $o <= 7; $o++) {
        if (isset($subtarefasPorOrdem[$o])) {
            $progressStatusesEdit[$o - 1] = trim((string) ($subtarefasPorOrdem[$o]['status'] ?? 'pendente'));
        }
    }
    $mostrarBarraEdit = in_array($statusCartaLabel, ['Pendente', 'Em andamento'], true);
    if ($mostrarBarraEdit) {
        $segmentosBarraEdit[] = ['class' => 'concluida', 'title' => 'Guia cadastrada'];
        for ($i = 0; $i < 7; $i++) {
            $statusSeg = $progressStatusesEdit[$i] ?? 'pendente';
            $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
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
        $currentSubtarefaTitle = $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_1] ?? 'Cálculo da pena de multa';
    } elseif ($currentSubtarefaTitle === '' && $statusGuia === 'concluída') {
        $currentSubtarefaTitle = 'Todas as etapas concluídas';
    } elseif ($currentSubtarefaTitle === '') {
        $currentSubtarefaTitle = $statusCartaLabel;
    }
}

$perfisExcluir = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
$perfisCancelar = ['SERVIDOR', 'ADMIN', 'DIRETORA', 'MAGISTRADA'];
$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeExcluir = count(array_intersect($userPerfis, $perfisExcluir)) > 0;
$podeCancelar = count(array_intersect($userPerfis, $perfisCancelar)) > 0;
$statusAtual = $guia['status'] ?? '';
$tarefaFinalizadaOuCancelada = in_array($statusAtual, ['cancelada', 'concluída'], true);

$tiposOptions = [
    'Guia de Execução Definitiva',
    'Guia de Recolhimento Provisória',
    'Guia de Recolhimento Definitiva',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição de Guia - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno - Cartório - Guias -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Guia</h1>
        <p class="description-page">Altere os dados e acompanhe as subtarefas.</p>
    </div>

    <div class="card-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="row filter">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
            <div class="row buttons">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            </div>
        <?php else: ?>
            <div class="row table header">Dados da guia</div>
            <div class="row filter" style="justify-content: flex-start;">
                <div class="col filter">
                    <label for="edit-processo">Processo</label>
                    <input type="text" id="edit-processo" class="width-md height-md readonly" value="<?php echo htmlspecialchars($guia['numero_processo'] ?? '', ENT_QUOTES); ?>" readonly>
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
                    <label for="edit-denunciado">Denunciado</label>
                    <input type="text" id="edit-denunciado" class="width-xl height-md readonly" value="<?php echo htmlspecialchars($guia['denunciado'] ?? '', ENT_QUOTES); ?>" maxlength="255" readonly>
                </div>
            </div>
            <div class="row filter">
                <div class="col filter">
                    <label for="edit-tipo">Tipo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="edit-tipo" class="width-lg height-md">
                        <?php foreach ($tiposOptions as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo ($guia['tipo'] ?? '') === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="edit-data-fato">Data do fato</label>
                    <input type="date" id="edit-data-fato" class="width-md height-md" value="<?php echo htmlspecialchars($guia['data_fato'] ?? '', ENT_QUOTES); ?>" max="">
                </div>
            </div>
            <div class="row filter">
                <div class="col filter">
                    <label for="edit-regime">Regime</label>
                    <select id="edit-regime" class="width-md height-md">
                        <option value=""<?php echo (($guia['regime'] ?? '') === '') ? ' selected' : ''; ?>>Selecione (opcional)</option>
                        <option value="Aberto"<?php echo (($guia['regime'] ?? '') === 'Aberto') ? ' selected' : ''; ?>>Aberto</option>
                        <option value="Semiaberto"<?php echo (($guia['regime'] ?? '') === 'Semiaberto') ? ' selected' : ''; ?>>Semiaberto</option>
                        <option value="Fechado"<?php echo (($guia['regime'] ?? '') === 'Fechado') ? ' selected' : ''; ?>>Fechado</option>
                    </select>
                </div>                
                <div class="col filter">
                    <label for="edit-dias-multa">Dias-multa</label>
                    <input type="number" id="edit-dias-multa" class="width-sm height-md" min="0" value="<?php echo isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : ''; ?>">
                </div>
            </div>

            <div class="row timeline-header">
                <div class="col filter">
                    <span class="header-timeline">Subtarefas - Timeline</span>
                </div>
                <div class="col filter" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                    <?php if ($mostrarBarraEdit): ?>
                        <div class="width-sm status <?php echo htmlspecialchars($statusCartaClass, ENT_QUOTES); ?> status-with-progress" style="border: 1px solid var(--color-gray-500);">
                            <span class="status-task-label"><?php echo htmlspecialchars($statusCartaLabel, ENT_QUOTES); ?></span>
                            <?php $concluidosBarraEdit = 1 + count(array_filter($progressStatusesEdit, fn($x) => $x === 'concluida')); ?>
                            <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo $concluidosBarraEdit; ?>" aria-valuemin="0" aria-valuemax="8" aria-label="Progresso das subtarefas">
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
            <?php if ($guia !== null): ?>
            <div class="row timeline finished">
                <div class="row">
                    <div class="circle finished"></div>
                    <div class="col filter subtask-status-info">
                        <span class="minor-info">Guia cadastrada</span>
                    </div>
                </div>
                <div class="col">
                    <div class="col filter subtask-date">
                        <span class="minor-info"><?php echo htmlspecialchars($cadastroInfoLinha !== '' ? $cadastroInfoLinha : 'Guia cadastrada', ENT_QUOTES); ?></span>
                    </div>
                </div>
            </div>
            <?php for ($ordem = 1; $ordem <= 7; $ordem++): ?>
                <?php
                $tipo = $ordemParaTipo[$ordem] ?? '';
                $label = $subtarefaLabels[$tipo] ?? 'Passo ' . $ordem;
                $st = $subtarefasPorOrdem[$ordem] ?? null;
                $circleClass = '';
                $minorTexto = $timelineMinorPendente[$tipo] ?? $label;
                $dateLabel = 'Aguardando conclusão';
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
                        $dateLabel = $dataMovimento !== '' ? 'Concluído em ' . $dataMovimento : 'Concluído';
                    } elseif ($status === 'em_andamento') {
                        $circleClass = 'current';
                        $dateLabel = $dataMovimento !== '' ? 'Aguardando movimentação desde ' . $dataMovimento : 'Aguardando movimentação';
                    } else {
                        $dateLabel = 'Aguardando conclusão';
                    }
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
            <?php endif; ?>

            <div class="row buttons">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="button" class="btn btn-primary" id="salvar-btn" disabled>Salvar alterações</button>
                <?php if ($podeCancelar && !$tarefaFinalizadaOuCancelada): ?>
                    <button type="button" class="btn btn-primary-outline" id="cancelar-tarefa-btn">Cancelar tarefa</button>
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
            dias_multa: <?php echo json_encode(isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : '', JSON_UNESCAPED_UNICODE); ?>
        };
        </script>
    <?php endif; ?>
    <script>
    (function () {
        var idGuia = <?php echo json_encode($idGuia, JSON_UNESCAPED_UNICODE); ?>;

        document.getElementById('voltar-btn')?.addEventListener('click', function () {
            window.location.href = '/frame.php?page=guias';
        });

        (function () {
            var dataFato = document.getElementById('edit-data-fato');
            if (dataFato) {
                var today = new Date();
                today.setDate(today.getDate() - 1);
                dataFato.setAttribute('max', today.toISOString().split('T')[0]);
            }
        })();

        if (!idGuia) return;

        function showToastMsg(type, title, message) {
            if (typeof showToast === 'function') {
                showToast({ type: type, variant: 'full', title: title, message: message });
            }
        }

        var salvarBtn = document.getElementById('salvar-btn');
        var initialData = window.__initialGuiaData || {};
        function getCurrentState() {
            var tipoEl = document.getElementById('edit-tipo');
            var regimeEl = document.getElementById('edit-regime');
            var dataFatoEl = document.getElementById('edit-data-fato');
            var diasMultaEl = document.getElementById('edit-dias-multa');
            var diasVal = (diasMultaEl && diasMultaEl.value !== '') ? parseInt(diasMultaEl.value, 10) : '';
            if (diasVal !== '' && isNaN(diasVal)) diasVal = '';
            return {
                tipo: tipoEl ? (tipoEl.value || '').trim() : '',
                regime: regimeEl ? (regimeEl.value || '').trim() : '',
                data_fato: dataFatoEl ? (dataFatoEl.value || '').trim() : '',
                dias_multa: diasVal
            };
        }
        function hasChanges() {
            var cur = getCurrentState();
            if ((initialData.tipo || '') !== (cur.tipo || '')) return true;
            if ((initialData.regime || '') !== (cur.regime || '')) return true;
            if ((initialData.data_fato || '') !== (cur.data_fato || '')) return true;
            if (String(initialData.dias_multa ?? '') !== String(cur.dias_multa ?? '')) return true;
            return false;
        }
        function updateButtonState() {
            if (salvarBtn) salvarBtn.disabled = !hasChanges();
        }
        ['edit-tipo', 'edit-regime', 'edit-data-fato', 'edit-dias-multa'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updateButtonState);
                el.addEventListener('change', updateButtonState);
            }
        });
        updateButtonState();

        salvarBtn?.addEventListener('click', function () {
            var regimeVal = document.getElementById('edit-regime')?.value?.trim() || '';
            var dataFatoVal = document.getElementById('edit-data-fato')?.value || '';
            if (regimeVal && ['Aberto', 'Semiaberto', 'Fechado'].indexOf(regimeVal) === -1) {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'simple', message: 'Regime inválido. Selecione Aberto, Semiaberto ou Fechado.' });
                return;
            }
            if (dataFatoVal) {
                var hoje = new Date();
                hoje.setHours(0, 0, 0, 0);
                if (new Date(dataFatoVal) >= hoje) {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'simple', message: 'A data do fato deve ser anterior à data de hoje.' });
                    return;
                }
            }
            var payload = {
                id: idGuia,
                numero_processo: document.getElementById('edit-processo')?.value?.trim() || '',
                denunciado: document.getElementById('edit-denunciado')?.value?.trim() || '',
                tipo: document.getElementById('edit-tipo')?.value || 'Guia - Tipo não informado',
                regime: regimeVal,
                data_fato: dataFatoVal,
                dias_multa: document.getElementById('edit-dias-multa')?.value || ''
            };
            var diasVal = document.getElementById('edit-dias-multa')?.value;
            if (diasVal !== '' && !isNaN(parseInt(diasVal, 10))) payload.dias_multa = parseInt(diasVal, 10);

            salvarBtn.disabled = true;
            fetch('/api/cartorio-guias/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    showToastMsg('error', 'Erro', data.error);
                    updateButtonState();
                } else {
                    showToastMsg('success', 'Sucesso', 'Guia atualizada.');
                    window.location.href = '/frame.php?page=guias';
                }
            })
            .catch(function () {
                showToastMsg('error', 'Erro', 'Erro ao salvar.');
                updateButtonState();
            });
        });

        document.getElementById('cancelar-tarefa-btn')?.addEventListener('click', function () {
            if (!confirm('Deseja realmente cancelar esta guia?')) return;
            var btn = this;
            btn.disabled = true;
            fetch('/api/cartorio-guias/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idGuia })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    showToastMsg('error', 'Erro', data.error);
                    btn.disabled = false;
                } else {
                    showToastMsg('success', 'Sucesso', 'Guia cancelada.');
                    window.location.href = '/frame.php?page=guias';
                }
            })
            .catch(function () {
                showToastMsg('error', 'Erro', 'Erro ao cancelar.');
                btn.disabled = false;
            });
        });

        document.getElementById('excluir-btn')?.addEventListener('click', function () {
            if (!confirm('Deseja realmente excluir esta guia? Esta ação não pode ser desfeita.')) return;
            var btn = this;
            btn.disabled = true;
            fetch('/api/cartorio-guias/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idGuia })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    showToastMsg('error', 'Erro', data.error);
                    btn.disabled = false;
                } else {
                    showToastMsg('success', 'Sucesso', 'Guia excluída.');
                    window.location.href = '/frame.php?page=guias';
                }
            })
            .catch(function () {
                showToastMsg('error', 'Erro', 'Erro ao excluir.');
                btn.disabled = false;
            });
        });

    })();
    </script>
</body>
</html>
