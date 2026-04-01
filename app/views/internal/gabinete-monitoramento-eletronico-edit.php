<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/GabineteMonitoramentoEletronicoService.php';
require_once __DIR__ . '/../../repositories/GabineteMonitoramentoEletronicoRepository.php';

$connection = Conection::getInstance()->getConnection();

$idMon = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$row = null;

if ($idMon === '') {
    $errorMessage = 'Identificador não informado.';
} else {
    try {
        $service = new GabineteMonitoramentoEletronicoService($connection);
        $row = $service->find($idMon);
        if ($row === null) {
            $errorMessage = 'Registro não encontrado.';
        }
    } catch (Throwable $exception) {
        $errorMessage = 'Erro ao carregar: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeNome = 'Classe não informada';
$reuNome = '';
$statusAtual = '';
$dataInicio = '';
$dataFim = '';
$duracaoTxt = '—';

if ($row !== null) {
    $processoNumero = trim((string) ($row['numero_processo'] ?? ''));
    $cn = trim((string) ($row['classe_nome'] ?? ''));
    if ($cn !== '') {
        $classeNome = $cn;
    }
    $reuNome = trim((string) ($row['reu_nome_exibicao'] ?? ''));
    $statusAtual = trim((string) ($row['status'] ?? ''));
    $di = $row['data_inicio'] ?? null;
    $df = $row['data_encerramento'] ?? null;
    if ($di !== null && $di !== '') {
        $dataInicio = (string) $di;
        $ts = strtotime($dataInicio);
        if ($ts !== false) {
            $dataInicio = date('Y-m-d', $ts);
        }
    }
    if ($df !== null && $df !== '') {
        $dataFim = (string) $df;
        $ts = strtotime($dataFim);
        if ($ts !== false) {
            $dataFim = date('Y-m-d', $ts);
        }
    }
    $duracaoTxt = (new GabineteMonitoramentoEletronicoService($connection))->formatDuracaoLabel($statusAtual, $di, $df);
}

$repoMain = new GabineteMonitoramentoEletronicoRepository($connection);
$maxSeq = $row !== null ? $repoMain->getMaxSequencialForYear((int) ($row['ano'] ?? 0)) : 0;
$seqAtual = $row !== null ? (int) ($row['numero_sequencial'] ?? 0) : 0;
$podeExcluirNumero = ($row !== null && $seqAtual > 0 && $seqAtual === $maxSeq);

$userPerfisEdit = [];
$userIdEdit = $_SESSION['id_usuario'] ?? null;
if ($userIdEdit !== null && $userIdEdit !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$userIdEdit]);
    $userPerfisEdit = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
}
/** Botão Excluir: visível apenas para admin/chefia e somente quando for o último número sequencial do ano. */
$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA'];
$perfisPermitidosCancelar = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'];
$isAdminOuChefia = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;
$podeExcluirTarefa = $isAdminOuChefia && $podeExcluirNumero;
$podeCancelarTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosCancelar)) > 0;
$tarefaCancelada = ($statusAtual === 'cancelada');
$tarefaFinalizada = ($statusAtual === 'finalizada');
$podeCancelarTarefa = $podeCancelarTarefa && !$tarefaCancelada && !$tarefaFinalizada;
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Monitoramento Eletrônico - Edição</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Monitoramento Eletrônico -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Monitoramento eletrônico</h1>
        <p class="description-page">Visualização dos dados do registro e das datas de início e encerramento quando concluídas.</p>
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
            <div id="gab-mon-edit-track">
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" style="justify-content: flex-start; flex-wrap: wrap;">
                <div class="col filter">
                    <label for="gab-mon-edit-processo">Processo</label>
                    <input type="text" id="gab-mon-edit-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gab-mon-edit-classe">Classe</label>
                    <input type="text" id="gab-mon-edit-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gab-mon-edit-reu">Réu</label>
                    <input type="text" id="gab-mon-edit-reu" class="width-700 height-md readonly" value="<?php echo htmlspecialchars($reuNome, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" style="justify-content: flex-start; flex-wrap: wrap;">
                <div class="col filter">
                    <label for="gab-mon-edit-data-inicio">Data de início do monitoramento</label>
                    <input type="date" id="gab-mon-edit-data-inicio" class="width-280 height-md readonly" value="<?php echo $dataInicio !== '' ? htmlspecialchars($dataInicio, ENT_QUOTES) : ''; ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gab-mon-edit-data-fim">Data de encerramento</label>
                    <input type="text" id="gab-mon-edit-data-fim" class="width-280 height-md readonly" value="<?php echo $dataFim !== '' ? htmlspecialchars($dataFim, ENT_QUOTES) : '—'; ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gab-mon-edit-duracao">Duração (dias)</label>
                    <input type="text" id="gab-mon-edit-duracao" class="width-200 height-md readonly" value="<?php echo htmlspecialchars($duracaoTxt, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            </div>
            <input type="hidden" id="hidden-gme-id" value="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="gab-mon-voltar-btn">Voltar</button>
            <?php if ($errorMessage === '' && $row !== null): ?>
                <?php if ($podeCancelarTarefa): ?>
                    <button type="button" class="btn btn-danger-outline" id="gab-mon-cancelar-btn" title="Alterar status para Cancelada">Cancelar monitoramento</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button type="button" class="btn btn-danger" id="gab-mon-excluir-btn" title="Exclusão permitida apenas para admin ou chefia e somente se for o último número sequencial do ano">Excluir registro</button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" id="salvar-btn" disabled>Salvar alterações</button>
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

    <?php if ($row !== null && $errorMessage === ''): ?>
    <script>
        (function () {
            const notify = (type, title, message) => {
                if (window.App && App.Page && typeof App.Page.notify === 'function') {
                    App.Page.notify(type, title, message);
                    return;
                }
                if (typeof showToast === 'function') showToast({ type, variant: 'full', title, message });
            };
            const queuePostReloadToast = (type, title, message) => {
                if (window.App && App.Page && typeof App.Page.queuePostReloadToast === 'function') {
                    App.Page.queuePostReloadToast(type, title, message, 'gme.postReloadToast');
                    return;
                }
                try {
                    const key = 'gme.postReloadToast';
                    const raw = sessionStorage.getItem(key);
                    const fila = raw ? JSON.parse(raw) : [];
                    const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
                    lista.push({ type, title, message });
                    sessionStorage.setItem(key, JSON.stringify(lista));
                } catch (e) {}
            };
            const mid = document.getElementById('hidden-gme-id')?.value || '';
            const saveBtn = document.getElementById('salvar-btn');
            const trackRoot = document.getElementById('gab-mon-edit-track');

            function snapshotTrackableValues() {
                if (!trackRoot) return '';
                const els = Array.from(trackRoot.querySelectorAll('input:not([type="hidden"]), select, textarea')).filter(function (el) {
                    return !el.readOnly && !el.disabled;
                });
                return els.map(function (el) {
                    return (el.id || el.name || '') + '\t' + String(el.value);
                }).join('\n');
            }

            let initialTrackSnapshot = snapshotTrackableValues();
            function syncSalvarDisabled() {
                if (!saveBtn) return;
                saveBtn.disabled = snapshotTrackableValues() === initialTrackSnapshot;
            }

            if (trackRoot && saveBtn) {
                Array.from(trackRoot.querySelectorAll('input:not([type="hidden"]), select, textarea')).forEach(function (el) {
                    if (el.readOnly || el.disabled) return;
                    el.addEventListener('input', syncSalvarDisabled);
                    el.addEventListener('change', syncSalvarDisabled);
                });
                syncSalvarDisabled();
            }

            document.getElementById('gab-mon-voltar-btn')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=monitoramentogabinete';
            });

            saveBtn?.addEventListener('click', function () {
                if (saveBtn.disabled) return;
                notify('info', 'Somente leitura', 'Esta tela exibe os dados do registro. Alterações de datas e subtarefas são feitas na lista.');
            });

            const pendingConfirmations = {};
            function requireSecondClick(key, message) {
                const now = Date.now();
                const validUntil = Number(pendingConfirmations[key] || 0);
                if (validUntil > now) {
                    delete pendingConfirmations[key];
                    return true;
                }
                pendingConfirmations[key] = now + 5000;
                notify('info', 'Confirmação', message);
                return false;
            }

            document.getElementById('gab-mon-cancelar-btn')?.addEventListener('click', function () {
                if (!requireSecondClick('gab-mon-cancelar', 'Clique novamente em Cancelar monitoramento em até 5 segundos para confirmar.')) return;
                const btn = this;
                btn.disabled = true;
                fetch('/api/gabinete-monitoramento-eletronico/cancel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: mid }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        queuePostReloadToast('success', 'Sucesso', 'Monitoramento cancelado.');
                        window.location.href = '/frame.php?page=monitoramentogabinete';
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao cancelar.');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    btn.disabled = false;
                });
            });

            document.getElementById('gab-mon-excluir-btn')?.addEventListener('click', function () {
                if (!requireSecondClick('gab-mon-excluir', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.')) return;
                const btn = this;
                btn.disabled = true;
                fetch('/api/gabinete-monitoramento-eletronico/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: mid }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && (res.j.deleted || res.j.message)) {
                        queuePostReloadToast('success', 'Sucesso', (res.j.message) || 'Registro excluído.');
                        window.location.href = '/frame.php?page=monitoramentogabinete';
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao excluir.');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    btn.disabled = false;
                });
            });
        })();
    </script>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
    <script>
        (function () {
            document.getElementById('gab-mon-voltar-btn')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=monitoramentogabinete';
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
