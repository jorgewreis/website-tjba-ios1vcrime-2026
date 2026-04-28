<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/OficiosCartorioService.php';
require_once __DIR__ . '/../../repositories/OficiosCartorioRepository.php';
require_once __DIR__ . '/../../repositories/OficiosCartorioSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/OficiosCartorioAlocadosRepository.php';
require_once __DIR__ . '/../../helpers/CartorioAlocacaoChipHelper.php';

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
$perfisGestaoLstEdit = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestaoEdit = count(array_intersect($userPerfisEdit, $perfisGestaoLstEdit)) > 0;

$alocadosEdit = [];
if ($oficio !== null) {
    $repoAloc = new OficiosCartorioAlocadosRepository($connection);
    $alocadosRaw = $repoAloc->findByOficioId($idOficio);
    foreach (array_slice(CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($alocadosRaw), 0, 5) as $a) {
        $alocadosEdit[] = [
            'alocado_id' => trim((string) ($a['id'] ?? '')),
            'iniciais' => $a['iniciais'] ?? '—',
            'nome_completo' => $a['nome_completo'] ?? '',
            'usuario_id' => trim((string) ($a['usuario_id'] ?? '')),
            'tipo' => trim((string) ($a['tipo'] ?? '')),
        ];
    }
}

$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$perfisPermitidosCancelar = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
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
        <p class="description-page">Consulta dos dados do ofício e das alocações (por regra e cadastrante, quando aplicável). Cancelar ou excluir conforme permissão e regras.</p>
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

            <div class="display-flex row row-full align-center padding-x-md padding-y-sm border-bottom"><h3 style="margin:0;font-size:1rem;">Alocações</h3></div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-sm align-center flex-wrap">
                <div class="display-flex row row-fit flex-wrap gap-y-sm allocate-chips-row allocate-users">
                <?php if (!empty($alocadosEdit)): ?>
                    <?php foreach ($alocadosEdit as $alocado):
                        $chipAl = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                            'entity_id' => (string) ($idOficio ?? ''),
                            'origem' => 'oficios.edit',
                        ]);
                        if ($chipAl === null) {
                            continue;
                        }
                        $tipoAl = $chipAl['tipo'];
                        $iniciaisAl = htmlspecialchars($chipAl['iniciais'], ENT_QUOTES);
                        $chipTitleAl = $chipAl['title'];
                        $chipAriaAl = $chipAl['aria_label'];
                        $classeTipoAl = $chipAl['classe_tipo'];
                        $alocadoIdStrAl = htmlspecialchars($chipAl['alocado_id'], ENT_QUOTES);
                        $usuarioIdStrAl = $chipAl['usuario_id'];
                        $uidEditCmp = trim((string) ($userIdEdit ?? ''));
                        $podeRemoverAl = $isGestaoEdit || ($tipoAl === 'specific' && $usuarioIdStrAl === $uidEditCmp);
                        ?>
                    <span class="user-allocate-wrap">
                        <span class="user-allocate<?php echo $classeTipoAl; ?>" title="<?php echo htmlspecialchars($chipTitleAl, ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars($chipAriaAl, ENT_QUOTES); ?>"><?php echo $iniciaisAl; ?></span>
                        <?php if ($podeRemoverAl): ?>
                        <button type="button"
                            class="user-allocate-remove js-desalocar"
                            data-alocado-id="<?php echo $alocadoIdStrAl; ?>"
                            data-tipo="<?php echo htmlspecialchars($tipoAl, ENT_QUOTES); ?>"
                            data-endpoint="/api/cartorio-oficios/desalocar.php"
                            title="Remover alocação"
                            aria-label="Remover alocação">×</button>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="minor-info">Nenhuma alocação registrada.</span>
                <?php endif; ?>
                </div>
            </div>

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
                numeroOficio: <?php echo json_encode($numeroAno, JSON_UNESCAPED_UNICODE); ?>,
                storageKey: 'cartorio.postReloadToast',
                deleteConfirmKey: 'excluir-oficio-cartorio',
                deleteConfirmMessage: 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.',
                enableDesalocar: true
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
