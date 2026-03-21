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

$numeroAno = '';
$processoNumero = '';
$classeNome = 'Classe não informada';
$reuNome = '';
$statusAtual = '';
$statusLabel = '';
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
    $ano = (int) ($row['ano'] ?? 0);
    $seq = (int) ($row['numero_sequencial'] ?? 0);
    $numeroAno = $seq . '/' . $ano;
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
    $mapSt = [
        'pendente' => 'Pendente',
        'em_andamento' => 'Em andamento',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ];
    $statusLabel = $mapSt[$statusAtual] ?? $statusAtual;

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
$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA'];
$perfisPermitidosCancelar = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'];
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0 && $podeExcluirNumero;
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
        <p class="description-page">Dados do registro e datas de início e encerramento.</p>
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
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" style="justify-content: flex-start; flex-wrap: wrap;">
                <div class="col filter">
                    <label for="gme-ed-numero">Monitoramento (n/ano)</label>
                    <input type="text" id="gme-ed-numero" class="width-300 height-md readonly" value="<?php echo htmlspecialchars($numeroAno, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gme-ed-processo">Processo</label>
                    <input type="text" id="gme-ed-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gme-ed-classe">Classe</label>
                    <input type="text" id="gme-ed-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gme-ed-reu">Réu</label>
                    <input type="text" id="gme-ed-reu" class="width-500 height-md readonly" value="<?php echo htmlspecialchars($reuNome, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" style="justify-content: flex-start; flex-wrap: wrap;">
                <div class="col filter">
                    <label for="gme-ed-status">Status</label>
                    <input type="text" id="gme-ed-status" class="width-300 height-md readonly" value="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gme-ed-di">Data de início</label>
                    <input type="text" id="gme-ed-di" class="width-280 height-md readonly" value="<?php echo $dataInicio !== '' ? htmlspecialchars($dataInicio, ENT_QUOTES) : '—'; ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gme-ed-df">Data de encerramento</label>
                    <input type="text" id="gme-ed-df" class="width-280 height-md readonly" value="<?php echo $dataFim !== '' ? htmlspecialchars($dataFim, ENT_QUOTES) : '—'; ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gme-ed-dur">Duração</label>
                    <input type="text" id="gme-ed-dur" class="width-280 height-md readonly" value="<?php echo htmlspecialchars($duracaoTxt, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <input type="hidden" id="hidden-gme-id" value="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button class="btn btn-secondary" id="gme-voltar-btn" type="button">Voltar</button>
            <?php if ($errorMessage === '' && $row !== null): ?>
                <?php if ($podeCancelarTarefa): ?>
                    <button class="btn btn-danger-outline" id="gme-cancelar-btn" type="button">Cancelar monitoramento</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="gme-excluir-btn" type="button">Excluir registro</button>
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

    <?php if ($row !== null && $errorMessage === ''): ?>
    <script>
        (function () {
            const notify = (type, title, message) => {
                if (typeof showToast === 'function') showToast({ type, variant: 'full', title, message });
            };
            const queuePostReloadToast = (type, title, message) => {
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

            document.getElementById('gme-voltar-btn')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=monitoramentogabinete';
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

            document.getElementById('gme-cancelar-btn')?.addEventListener('click', function () {
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

            document.getElementById('gme-excluir-btn')?.addEventListener('click', function () {
                if (!requireSecondClick('gme-excluir', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.')) return;
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
</body>
</html>
