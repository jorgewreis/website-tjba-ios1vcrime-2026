<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/OficiosCartorioService.php';
require_once __DIR__ . '/../../repositories/OficiosCartorioRepository.php';
require_once __DIR__ . '/../../repositories/OficiosCartorioSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();

$idOficio = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$oficio = null;

if ($idOficio === '') {
    $errorMessage = 'Identificador do ofício não informado.';
} else {
    try {
        $service = new OficiosCartorioService($connection);
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
$formaLabel = '';
$meioLabel = '';
$subtarefas = [];

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
    $forma = (string) ($oficio['forma_cumprimento'] ?? '');
    $formaLabel = $forma === 'ccm' ? 'por Oficial de Justiça (CCM)' : 'pelo Cartório';
    $meio = (string) ($oficio['meio_envio'] ?? '');
    $mapMeio = ['email' => 'E-mail', 'malote_digital' => 'Malote digital', 'pessoalmente' => 'Pessoalmente'];
    $meioLabel = $meio !== '' ? ($mapMeio[$meio] ?? $meio) : '—';
    $repoSt = new OficiosCartorioSubtarefasRepository($connection);
    $subtarefas = $repoSt->findByOficioId($idOficio);
}

$repoMain = new OficiosCartorioRepository($connection);
$maxSeq = $oficio !== null ? $repoMain->getMaxSequencialForYear((int) ($oficio['ano'] ?? 0)) : 0;
$seqAtual = $oficio !== null ? (int) ($oficio['numero_sequencial'] ?? 0) : 0;
$podeExcluirNumero = ($oficio !== null && $seqAtual > 0 && $seqAtual === $maxSeq);

$userPerfisEdit = [];
$userIdEdit = $_SESSION['id_usuario'] ?? null;
if ($userIdEdit !== null && trim((string) $userIdEdit) !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $userPerfisEdit = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userIdEdit));
}
$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA'];
$perfisPermitidosCancelar = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'];
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0 && $podeExcluirNumero;
$podeCancelarTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosCancelar)) > 0;
$tarefaFinalizadaOuCancelada = in_array($statusAtual, ['finalizada', 'cancelada'], true);

$subLabels = [
    OficiosCartorioSubtarefasRepository::TIPO_ENVIAR => 'Enviar ofício',
    OficiosCartorioSubtarefasRepository::TIPO_JUNTAR => 'Juntar termo de envio',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Ofícios (Cartório) — Edição</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Ofícios -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar ofício</h1>
        <p class="description-page">Consulta dos dados do ofício. Cancelar ou excluir conforme permissão e regras.</p>
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
                    <label for="ro-oficio-numero">Ofício (n/ano)</label>
                    <input type="text" id="ro-oficio-numero" class="width-300 height-md readonly" value="<?php echo htmlspecialchars($numeroAno, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="ro-processo">Processo</label>
                    <input type="text" id="ro-processo" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="ro-classe">Classe</label>
                    <input type="text" id="ro-classe" class="width-600 height-md readonly" value="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="ro-forma">Forma de cumprimento</label>
                    <input type="text" id="ro-forma" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($formaLabel, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="ro-meio">Meio de envio</label>
                    <input type="text" id="ro-meio" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($meioLabel, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="ro-status">Status</label>
                    <input type="text" id="ro-status" class="width-300 height-md readonly" value="<?php echo htmlspecialchars($statusAtual, ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <?php if (!empty($subtarefas)): ?>
            <div class="display-flex row row-full align-center padding-x-md padding-y-sm border-bottom"><h3 style="margin:0;font-size:1rem;">Subtarefas</h3></div>
            <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-sm">
                <?php foreach ($subtarefas as $st): ?>
                    <div class="minor-info"><?php echo htmlspecialchars($subLabels[(string) ($st['tipo'] ?? '')] ?? (string) ($st['tipo'] ?? '')); ?> — <?php echo htmlspecialchars((string) ($st['status'] ?? '')); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <input type="hidden" id="hidden-oficio-id" value="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button class="btn btn-secondary" id="voltar-btn" type="button">Voltar</button>
            <?php if ($errorMessage === '' && $oficio !== null): ?>
                <?php if ($podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-danger-outline" id="cancelar-oficio-btn" type="button" title="Cancelar ofício (remove subtarefas)">Cancelar ofício</button>
                <?php endif; ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-oficio-btn" type="button" title="Excluir somente se for o último número do ano">Excluir ofício</button>
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
            if (!window.App || !App.OficiosEdit || typeof App.OficiosEdit.initialize !== 'function') {
                return;
            }

            App.OficiosEdit.initialize({
                idFieldId: 'hidden-oficio-id',
                backButtonId: 'voltar-btn',
                cancelButtonId: 'cancelar-oficio-btn',
                deleteButtonId: 'excluir-oficio-btn',
                cancelEndpoint: '/api/cartorio-oficios/cancel.php',
                deleteEndpoint: '/api/cartorio-oficios/delete.php',
                listHref: '/frame.php?page=oficioscartorio',
                storageKey: 'cartorio.postReloadToast',
                deleteConfirmKey: 'excluir-oficio-cartorio',
                deleteConfirmMessage: 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.'
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
