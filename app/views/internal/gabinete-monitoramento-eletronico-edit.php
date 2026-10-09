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
if ($userIdEdit !== null && trim((string) $userIdEdit) !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $userPerfisEdit = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userIdEdit));
}
/** Botão Excluir: internos com perfil composto; somente último número sequencial do ano. */
$perfisPermitidosOperacao = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'];
$podeOperarInterno = count(array_intersect($userPerfisEdit, $perfisPermitidosOperacao)) > 0;
$podeExcluirTarefa = $podeOperarInterno && $podeExcluirNumero;
$podeCancelarTarefa = $podeOperarInterno;
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
            <span class="all">Interno - Monitoramento Eletrônico -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Monitoramento eletrônico</h1>
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
                    <button type="button" class="btn btn-danger" id="gab-mon-excluir-btn" title="Exclusão permitida somente se for o último número sequencial do ano">Excluir registro</button>
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
            if (!window.App || !App.GmeEdit || typeof App.GmeEdit.initialize !== 'function') {
                return;
            }

            App.GmeEdit.initialize({
                idFieldId: 'hidden-gme-id',
                backButtonId: 'gab-mon-voltar-btn',
                saveButtonId: 'salvar-btn',
                trackRootId: 'gab-mon-edit-track',
                cancelButtonId: 'gab-mon-cancelar-btn',
                deleteButtonId: 'gab-mon-excluir-btn',
                cancelEndpoint: '/api/gabinete-monitoramento-eletronico/cancel.php',
                deleteEndpoint: '/api/gabinete-monitoramento-eletronico/delete.php',
                listHref: '/frame.php?page=monitoramentogabinete',
                storageKey: 'gme.postReloadToast',
                cancelSuccessMessage: 'Monitoramento cancelado.',
                deleteConfirmKey: 'gme.monitoring.delete.confirm',
                deleteConfirmMessage: 'Clique novamente para confirmar a exclusão definitiva do monitoramento.',
                deleteSuccessMessage: 'Monitoramento excluído.'
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
