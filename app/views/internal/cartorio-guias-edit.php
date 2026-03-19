<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/GuiasSubtarefasRepository.php';
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
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Detalhamento: data do fato e dias-multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Cálculo da pena de multa (valor em R$)',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Expedição da Guia ao cartório',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Assinatura da Juíza na Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Juntada dos autos (automática ou manual)',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Geração dos documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Envio da Guia (canal e destinatário)',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Juntada do termo de envio aos autos',
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
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando registro do valor da pena de multa (cálculo automático pelo sistema ou informado manualmente)',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando informação da data de expedição da Guia ao cartório',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando assinatura da Juíza na Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando confirmação da juntada dos autos (informar se automática ou manual)',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando informação da quantidade de documentos gerados em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Aguardando informação do envio da Guia (tipo de envio e destinatário)',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Aguardando juntada do comprovante/termo de envio da Guia aos autos',
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

    <div class="card-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
            <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            </div>
        <?php else: ?>
            <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Dados da guia</div>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" style="justify-content: flex-start;">
                <div class="col filter">
                    <label for="edit-processo">Processo</label>
                    <input type="text" id="edit-processo" class="width-200 height-md readonly" value="<?php echo htmlspecialchars($guia['numero_processo'] ?? '', ENT_QUOTES); ?>" readonly>
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
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
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
                    <input type="date" id="edit-data-fato" class="width-200 height-md" value="<?php echo htmlspecialchars($guia['data_fato'] ?? '', ENT_QUOTES); ?>" max="">
                </div>
            </div>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
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
                    <input type="number" id="edit-dias-multa" class="width-100 height-md" min="0" value="<?php echo isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : ''; ?>">
                </div>
            </div>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col filter">
                    <label for="edit-observacoes">Observações</label>
                    <input type="text" id="edit-observacoes" class="width-600 height-md" value="<?php echo htmlspecialchars((string) ($guia['observacoes'] ?? ''), ENT_QUOTES); ?>" placeholder="Opcional">
                </div>
            </div>

            <?php if ($guia !== null): ?>
                <?php include __DIR__ . '/_cartorio_timeline.php'; ?>
            <?php endif; ?>

            <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
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
            dias_multa: <?php echo json_encode(isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : '', JSON_UNESCAPED_UNICODE); ?>,
            observacoes: <?php echo json_encode((string) ($guia['observacoes'] ?? ''), JSON_UNESCAPED_UNICODE); ?>
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

        var salvarBtn = document.getElementById('salvar-btn');
        var initialData = window.__initialGuiaData || {};
        function getCurrentState() {
            var tipoEl = document.getElementById('edit-tipo');
            var regimeEl = document.getElementById('edit-regime');
            var dataFatoEl = document.getElementById('edit-data-fato');
            var diasMultaEl = document.getElementById('edit-dias-multa');
            var observacoesEl = document.getElementById('edit-observacoes');
            var diasVal = (diasMultaEl && diasMultaEl.value !== '') ? parseInt(diasMultaEl.value, 10) : '';
            if (diasVal !== '' && isNaN(diasVal)) diasVal = '';
            return {
                tipo: tipoEl ? (tipoEl.value || '').trim() : '',
                regime: regimeEl ? (regimeEl.value || '').trim() : '',
                data_fato: dataFatoEl ? (dataFatoEl.value || '').trim() : '',
                dias_multa: diasVal,
                observacoes: observacoesEl ? (observacoesEl.value || '').trim() : ''
            };
        }
        function hasChanges() {
            var cur = getCurrentState();
            if ((initialData.tipo || '') !== (cur.tipo || '')) return true;
            if ((initialData.regime || '') !== (cur.regime || '')) return true;
            if ((initialData.data_fato || '') !== (cur.data_fato || '')) return true;
            if (String(initialData.dias_multa ?? '') !== String(cur.dias_multa ?? '')) return true;
            if ((initialData.observacoes || '') !== (cur.observacoes || '')) return true;
            return false;
        }
        function updateButtonState() {
            if (salvarBtn) salvarBtn.disabled = !hasChanges();
        }
        ['edit-tipo', 'edit-regime', 'edit-data-fato', 'edit-dias-multa', 'edit-observacoes'].forEach(function (id) {
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
                dias_multa: document.getElementById('edit-dias-multa')?.value || '',
                observacoes: document.getElementById('edit-observacoes')?.value?.trim() || ''
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
                    showToastMsg('success', 'Sucesso', 'Alterações salvas.');
                    if (data.valor_multa_calculado) {
                        showToastMsg('success', 'Cálculo automático', 'Cálculo realizado automaticamente pelo sistema. Valor da pena de multa: ' + data.valor_multa_calculado + '.');
                    }
                    window.location.href = '/frame.php?page=guias';
                }
            })
            .catch(function () {
                showToastMsg('error', 'Erro', 'Erro ao salvar.');
                updateButtonState();
            });
        });

        document.getElementById('cancelar-tarefa-btn')?.addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            fetch('/api/cartorio-guias/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idGuia })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error || data.deleted === false) {
                    showToastMsg('error', 'Erro', data.error || 'Erro ao excluir.');
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
                    (Array.isArray(data.subtarefas_toasts) ? data.subtarefas_toasts : []).forEach(function (msg) {
                        queuePostReloadToast('info', 'Subtarefa excluída', msg);
                    });
                    if (data.tarefa_toast) {
                        queuePostReloadToast('success', 'Sucesso', data.tarefa_toast);
                    }
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
