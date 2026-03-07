<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/GuiasSubtarefasRepository.php';
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
if ($guia !== null) {
    $subtarefasRepo = new GuiasSubtarefasRepository($connection);
    $subtarefasRepo->ensurePrimeiraSubtarefa($idGuia);
    $subtarefas = $subtarefasRepo->findByGuiaId($idGuia);
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
    'Guia - Tipo não informado',
    'Guia de Execução Definitiva',
    'Guia de Recolhimento Provisória',
    'Guia de Recolhimento Definitiva',
    'Guia (corrigir)',
    'Guia (reenviar)',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição de Guia - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/variables.css">
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
            <div class="row filter">
                <div class="col filter">
                    <label for="edit-processo">Processo</label>
                    <input type="text" id="edit-processo" class="width-md height-md" value="<?php echo htmlspecialchars($guia['numero_processo'] ?? '', ENT_QUOTES); ?>">
                </div>
                <div class="col filter">
                    <label for="edit-denunciado">Denunciado</label>
                    <input type="text" id="edit-denunciado" class="width-xl height-md" value="<?php echo htmlspecialchars($guia['denunciado'] ?? '', ENT_QUOTES); ?>" maxlength="255">
                </div>
            </div>
            <div class="row filter">
                <div class="col filter">
                    <label for="edit-tipo">Tipo</label>
                    <select id="edit-tipo" class="width-lg height-md">
                        <?php foreach ($tiposOptions as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo ($guia['tipo'] ?? '') === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <label for="edit-data-fato">Data do fato</label>
                    <input type="date" id="edit-data-fato" class="width-md height-md" value="<?php echo htmlspecialchars($guia['data_fato'] ?? '', ENT_QUOTES); ?>" max="">
                </div>
                <div class="col filter">
                    <label for="edit-dias-multa">Dias-multa</label>
                    <input type="number" id="edit-dias-multa" class="width-sm height-md" min="0" value="<?php echo isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : ''; ?>">
                </div>
            </div>

            <div class="row table header">Subtarefas</div>
            <?php if (empty($subtarefas)): ?>
                <div class="row filter">
                    <div class="col">
                        <span class="minor-info">Nenhuma subtarefa. </span>
                        <button type="button" class="btn btn-secondary" id="btn-criar-subtarefa">Criar primeira subtarefa</button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($subtarefas as $st):
                    $stId = $st['id'] ?? '';
                    $stTipo = $st['tipo'] ?? '';
                    $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                    $stStatus = $st['status'] ?? 'pendente';
                    $concluida = $stStatus === 'concluida';
                ?>
                <div class="col full-line" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" style="border-top: 1px solid var(--color-gray-400);">
                    <div class="row subtask-info">
                        <div class="col task-info">
                            <span class="minor-info"><?php echo htmlspecialchars($stLabel); ?></span>
                            <span class="status <?php echo $concluida ? 'status-finished' : 'status-pending'; ?>"><?php echo $concluida ? 'Concluída' : 'Pendente'; ?></span>
                        </div>
                        <?php if (!$concluida): ?>
                        <div class="row subtask-buttons">
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Concluir</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="row buttons">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="button" class="btn btn-primary" id="salvar-btn">Salvar alterações</button>
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

        document.getElementById('salvar-btn')?.addEventListener('click', function () {
            var btn = this;
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

            btn.disabled = true;
            fetch('/api/cartorio-guias/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    showToastMsg('error', 'Erro', data.error);
                    btn.disabled = false;
                } else {
                    showToastMsg('success', 'Sucesso', 'Guia atualizada.');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                showToastMsg('error', 'Erro', 'Erro ao salvar.');
                btn.disabled = false;
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

        document.getElementById('btn-criar-subtarefa')?.addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            fetch('/api/cartorio-guias/criar-primeira-subtarefa.php', {
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
                    showToastMsg('success', 'Sucesso', 'Subtarefa criada.');
                    window.location.reload();
                }
            })
            .catch(function () {
                showToastMsg('error', 'Erro', 'Erro ao criar subtarefa.');
                btn.disabled = false;
            });
        });

        document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var subtarefaId = this.getAttribute('data-subtarefa-id');
                if (!subtarefaId) return;
                var btnEl = this;
                btnEl.disabled = true;
                fetch('/api/cartorio-guias/concluir-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: subtarefaId })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) {
                        showToastMsg('error', 'Erro', data.error);
                        btnEl.disabled = false;
                    } else {
                        showToastMsg('success', 'Sucesso', 'Subtarefa concluída.');
                        window.location.reload();
                    }
                })
                .catch(function () {
                    showToastMsg('error', 'Erro', 'Erro ao concluir.');
                    btnEl.disabled = false;
                });
            });
        });
    })();
    </script>
</body>
</html>
