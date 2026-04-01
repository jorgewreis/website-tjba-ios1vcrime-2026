<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/OficiosGabineteService.php';
require_once __DIR__ . '/../../repositories/OficiosGabineteRepository.php';

$connection = Conection::getInstance()->getConnection();

$idOficio = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$oficio = null;

if ($idOficio === '') {
    $errorMessage = 'Identificador do ofício não informado.';
} else {
    try {
        $service = new OficiosGabineteService($connection);
        $oficio = $service->find($idOficio);
        if ($oficio === null) {
            $errorMessage = 'Ofício não encontrado.';
        }
    } catch (Throwable $exception) {
        $errorMessage = 'Erro ao carregar o ofício: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeNome = 'Classe não informada';
$statusAtual = '';
$numeroAno = '';
$tipoOficio = '';

if ($oficio !== null) {
    $processoNumero = trim((string) ($oficio['numero_processo'] ?? ''));
    $cn = trim((string) ($oficio['classe_nome'] ?? ''));
    if ($cn !== '') {
        $classeNome = $cn;
    }
    $statusAtual = trim((string) ($oficio['status'] ?? ''));
    $ano = (int) ($oficio['ano'] ?? 0);
    $seq = (int) ($oficio['numero_sequencial'] ?? 0);
    $numeroAno = $seq . '/' . $ano;
    $tipoOficio = trim((string) ($oficio['tipo_oficio'] ?? ''));
}

$repoMain = new OficiosGabineteRepository($connection);
$maxSeq = $oficio !== null ? $repoMain->getMaxSequencialForYear((int) ($oficio['ano'] ?? 0)) : 0;
$seqAtual = $oficio !== null ? (int) ($oficio['numero_sequencial'] ?? 0) : 0;
$podeExcluirNumero = ($oficio !== null && $seqAtual > 0 && $seqAtual === $maxSeq);

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

$statusLabel = $statusAtual === 'finalizada' ? 'Finalizada' : ($statusAtual === 'cancelada' ? 'Cancelada' : ($statusAtual === 'pendente' ? 'Pendente' : $statusAtual));
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Ofícios (Gabinete) — Edição</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Ofícios -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar ofício</h1>
        <p class="description-page">Dados do ofício do gabinete.</p>
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
                    <label for="gab-ro-numero">Ofício (n/ano)</label>
                    <input type="text" id="gab-ro-numero" class="width-300 height-md readonly" value="<?php echo htmlspecialchars($numeroAno, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gab-ro-processo">Processo</label>
                    <input type="text" id="gab-ro-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gab-ro-classe">Classe</label>
                    <input type="text" id="gab-ro-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="gab-ro-tipo">Tipo de ofício</label>
                    <input type="text" id="gab-ro-tipo" class="width-600 height-md" value="<?php echo htmlspecialchars($tipoOficio, ENT_QUOTES); ?>">
                </div>
                <div class="col filter">
                    <label for="gab-ro-status">Status</label>
                    <input type="text" id="gab-ro-status" class="width-300 height-md readonly" value="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <input type="hidden" id="hidden-gab-oficio-id" value="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button class="btn btn-secondary" id="gab-voltar-btn" type="button">Voltar</button>
            <?php if ($errorMessage === '' && $oficio !== null): ?>
                <?php if ($podeCancelarTarefa && !$tarefaCancelada): ?>
                    <button class="btn btn-danger-outline" id="gab-cancelar-oficio-btn" type="button">Cancelar ofício</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="gab-excluir-oficio-btn" type="button">Excluir ofício</button>
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

    <?php if ($oficio !== null && $errorMessage === ''): ?>
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
                    App.Page.queuePostReloadToast(type, title, message, 'cartorio.postReloadToast');
                    return;
                }
                try {
                    const key = 'cartorio.postReloadToast';
                    const raw = sessionStorage.getItem(key);
                    const fila = raw ? JSON.parse(raw) : [];
                    const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
                    lista.push({ type, title, message });
                    sessionStorage.setItem(key, JSON.stringify(lista));
                } catch (e) {}
            };
            const oid = document.getElementById('hidden-gab-oficio-id')?.value || '';

            document.getElementById('gab-voltar-btn')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=oficiosgabinete';
            });

            const requireSecondClick = window.App && App.Page && typeof App.Page.createSecondClickGuard === 'function'
                ? App.Page.createSecondClickGuard()
                : (() => {
                    const pendingConfirmations = {};
                    return (key, message) => {
                        const now = Date.now();
                        const validUntil = Number(pendingConfirmations[key] || 0);
                        if (validUntil > now) {
                            delete pendingConfirmations[key];
                            return true;
                        }
                        pendingConfirmations[key] = now + 5000;
                        notify('info', 'Confirmação', message);
                        return false;
                    };
                })();

            document.getElementById('gab-cancelar-oficio-btn')?.addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                fetch('/api/gabinete-oficios/cancel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: oid }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        queuePostReloadToast('success', 'Sucesso', 'Ofício cancelado.');
                        window.location.href = '/frame.php?page=oficiosgabinete';
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

            document.getElementById('gab-excluir-oficio-btn')?.addEventListener('click', function () {
                if (!requireSecondClick('excluir-oficio-gab', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.')) return;
                const btn = this;
                btn.disabled = true;
                fetch('/api/gabinete-oficios/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: oid }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && (res.j.deleted || res.j.message)) {
                        queuePostReloadToast('success', 'Sucesso', (res.j.message) || 'Ofício excluído.');
                        window.location.href = '/frame.php?page=oficiosgabinete';
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
