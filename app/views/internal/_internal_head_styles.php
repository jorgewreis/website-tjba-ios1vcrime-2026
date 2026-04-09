<?php
/**
 * CSS padrão das telas internas (iframe), alinhado a public/templates/model-page-list.html.
 * Ordem: reset → color → tema → layout (utilitários flex/row/gap) → page → form → table → toast.
 */
?>
<link rel="stylesheet" href="/assets/css/shared/reset.css">
<link rel="stylesheet" href="/assets/css/shared/color.css">
<?php include __DIR__ . '/_theme_script.php'; ?>
<link rel="stylesheet" href="/assets/css/shared/layout.css">
<link rel="stylesheet" href="/assets/css/shared/page.css">
<link rel="stylesheet" href="/assets/css/shared/form.css">
<link rel="stylesheet" href="/assets/css/shared/table.css">
<link rel="stylesheet" href="/assets/css/shared/toast.css">
<?php
$__navBtn = dirname(__DIR__, 3) . '/public/assets/js/shared/navigate-buttons.js';
$__navBtnV = @filemtime($__navBtn) ?: time();
$__hideDeletePath = dirname(__DIR__, 3) . '/public/assets/js/shared/hide-delete-for-estagiario.js';
$__hideDeleteV = @filemtime($__hideDeletePath) ?: time();
$__userContextPath = dirname(__DIR__, 3) . '/public/assets/js/shared/user-context.js';
$__userContextV = @filemtime($__userContextPath) ?: time();
$__pageHelpersPath = dirname(__DIR__, 3) . '/public/assets/js/shared/page-helpers.js';
$__pageHelpersV = @filemtime($__pageHelpersPath) ?: time();
$__listSearchPath = dirname(__DIR__, 3) . '/public/assets/js/shared/list-search.js';
$__listSearchV = @filemtime($__listSearchPath) ?: time();
$__autoSubmitFiltersPath = dirname(__DIR__, 3) . '/public/assets/js/shared/auto-submit-filters.js';
$__autoSubmitFiltersV = @filemtime($__autoSubmitFiltersPath) ?: time();
$__cartorioTaskListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-task-list.js';
$__cartorioTaskListV = @filemtime($__cartorioTaskListPath) ?: time();
$__oficiosEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/oficios-edit.js';
$__oficiosEditV = @filemtime($__oficiosEditPath) ?: time();
$__oficiosListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/oficios-list.js';
$__oficiosListV = @filemtime($__oficiosListPath) ?: time();
$__gmeListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/gme-list.js';
$__gmeListV = @filemtime($__gmeListPath) ?: time();
$__gmeEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/gme-edit.js';
$__gmeEditV = @filemtime($__gmeEditPath) ?: time();
$__gmeCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/gme-create.js';
$__gmeCreateV = @filemtime($__gmeCreatePath) ?: time();
$__cartorioSimpleEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-simple-edit.js';
$__cartorioSimpleEditV = @filemtime($__cartorioSimpleEditPath) ?: time();
$__cartorioGuiasEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-guias-edit.js';
$__cartorioGuiasEditV = @filemtime($__cartorioGuiasEditPath) ?: time();
$__cartorioCejuscListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-cejusc-list.js';
$__cartorioCejuscListV = @filemtime($__cartorioCejuscListPath) ?: time();
$__cartorioEditaisListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-editais-list.js';
$__cartorioEditaisListV = @filemtime($__cartorioEditaisListPath) ?: time();
$__cartorioMedidasListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-medidas-protetivas-list.js';
$__cartorioMedidasListV = @filemtime($__cartorioMedidasListPath) ?: time();
$__cartorioGuiasListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-guias-list.js';
$__cartorioGuiasListV = @filemtime($__cartorioGuiasListPath) ?: time();
$__cartorioCreateHelpersPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-create-helpers.js';
$__cartorioCreateHelpersV = @filemtime($__cartorioCreateHelpersPath) ?: time();
$__cartorioAlocacaoDirCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-alocacao-direcionada-create.js';
$__cartorioAlocacaoDirCreateV = @filemtime($__cartorioAlocacaoDirCreatePath) ?: time();
$__cartorioAlocacaoDirEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-alocacao-direcionada-edit.js';
$__cartorioAlocacaoDirEditV = @filemtime($__cartorioAlocacaoDirEditPath) ?: time();
$__cartorioCartasExpedidasCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-cartas-expedidas-create.js';
$__cartorioCartasExpedidasCreateV = @filemtime($__cartorioCartasExpedidasCreatePath) ?: time();
$__cartorioCartasRecebidasCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-cartas-recebidas-create.js';
$__cartorioCartasRecebidasCreateV = @filemtime($__cartorioCartasRecebidasCreatePath) ?: time();
$__cartorioEditaisCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-editais-create.js';
$__cartorioEditaisCreateV = @filemtime($__cartorioEditaisCreatePath) ?: time();
$__cartorioGuiasCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-guias-create.js';
$__cartorioGuiasCreateV = @filemtime($__cartorioGuiasCreatePath) ?: time();
$__cartorioMedidasCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-medidas-protetivas-create.js';
$__cartorioMedidasCreateV = @filemtime($__cartorioMedidasCreatePath) ?: time();
$__cartorioCreateBootstrapPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-create-bootstrap.js';
$__cartorioCreateBootstrapV = @filemtime($__cartorioCreateBootstrapPath) ?: time();
$__cartorioCityLookupPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-city-lookup.js';
$__cartorioCityLookupV = @filemtime($__cartorioCityLookupPath) ?: time();
$__cartorioGuiasCreateUiPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-guias-create-ui.js';
$__cartorioGuiasCreateUiV = @filemtime($__cartorioGuiasCreateUiPath) ?: time();
$__cartorioMedidasCreateUiPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-medidas-protetivas-create-ui.js';
$__cartorioMedidasCreateUiV = @filemtime($__cartorioMedidasCreateUiPath) ?: time();
$__recordEditCommonPath = dirname(__DIR__, 3) . '/public/assets/js/shared/record-edit-common.js';
$__recordEditCommonV = @filemtime($__recordEditCommonPath) ?: time();
$__instituicoesPublicasEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/instituicoes-publicas-edit.js';
$__instituicoesPublicasEditV = @filemtime($__instituicoesPublicasEditPath) ?: time();
$__consultaPartesEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/consulta-partes-edit.js';
$__consultaPartesEditV = @filemtime($__consultaPartesEditPath) ?: time();
$__consultaProcessosEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/consulta-processos-edit.js';
$__consultaProcessosEditV = @filemtime($__consultaProcessosEditPath) ?: time();
$__gabineteReusPresosEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/gabinete-reus-presos-edit.js';
$__gabineteReusPresosEditV = @filemtime($__gabineteReusPresosEditPath) ?: time();
$__instituicoesPublicasCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/instituicoes-publicas-create.js';
$__instituicoesPublicasCreateV = @filemtime($__instituicoesPublicasCreatePath) ?: time();
$__partesInstituicoesCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/partes-instituicoes-create.js';
$__partesInstituicoesCreateV = @filemtime($__partesInstituicoesCreatePath) ?: time();
$__partesInstituicoesEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/partes-instituicoes-edit.js';
$__partesInstituicoesEditV = @filemtime($__partesInstituicoesEditPath) ?: time();
$__processosPartesCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/processos-partes-create.js';
$__processosPartesCreateV = @filemtime($__processosPartesCreatePath) ?: time();
$__processosPartesEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/processos-partes-edit.js';
$__processosPartesEditV = @filemtime($__processosPartesEditPath) ?: time();
$__ceapaCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/ceapa-create.js';
$__ceapaCreateV = @filemtime($__ceapaCreatePath) ?: time();
$__ceapaEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/ceapa-edit.js';
$__ceapaEditV = @filemtime($__ceapaEditPath) ?: time();
$__ceapaListPath = dirname(__DIR__, 3) . '/public/assets/js/shared/ceapa-list.js';
$__ceapaListV = @filemtime($__ceapaListPath) ?: time();
$__usuariosCreatePath = dirname(__DIR__, 3) . '/public/assets/js/shared/usuarios-create.js';
$__usuariosCreateV = @filemtime($__usuariosCreatePath) ?: time();
$__usuariosEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/usuarios-edit.js';
$__usuariosEditV = @filemtime($__usuariosEditPath) ?: time();
$__alterarDadosPath = dirname(__DIR__, 3) . '/public/assets/js/shared/alterar-dados-pessoais.js';
$__alterarDadosV = @filemtime($__alterarDadosPath) ?: time();
$__properNameHelperPath = dirname(__DIR__, 3) . '/public/assets/js/shared/proper-name-helper.js';
$__properNameHelperV = @filemtime($__properNameHelperPath) ?: time();
$__cartasRecebidasEditPath = dirname(__DIR__, 3) . '/public/assets/js/shared/cartorio-cartas-recebidas-edit.js';
$__cartasRecebidasEditV = @filemtime($__cartasRecebidasEditPath) ?: time();

$__currentUserPerfis = (isset($userPerfils) && is_array($userPerfils)) ? $userPerfils : [];
$__isEstagiarioProfile = false;
$__normalizedPerfis = [];
foreach ($__currentUserPerfis as $__perfilRaw) {
    $__perfil = strtoupper(trim((string) $__perfilRaw));
    if ($__perfil !== '') {
        $__normalizedPerfis[] = $__perfil;
    }
    if ($__perfil === 'ESTAGIARIO' || strpos($__perfil, 'ESTAGIARIO-') === 0) {
        $__isEstagiarioProfile = true;
    }
}
$__currentPage = trim((string) ($_GET['page'] ?? ''));
$__isEditPage = $__currentPage !== '' && (bool) preg_match('/edit$/i', $__currentPage);
$__canDeleteOnThisEditPage = false;
$__sessionUserId = trim((string) (($userId ?? null) ?: ($_SESSION['id_usuario'] ?? '')));
$__sessionUserName = trim((string) ($_SESSION['nome'] ?? ''));
if ($__isEditPage && $__currentPage === 'reuspresosedit') {
    $__canDeleteOnThisEditPage = in_array('ESTAGIARIO-GABINETE', $__normalizedPerfis, true)
        || in_array('SERVIDOR-GABINETE', $__normalizedPerfis, true);
}
?>
<script src="/assets/js/shared/navigate-buttons.js?v=<?php echo (int) $__navBtnV; ?>" defer></script>
<script>
window.__APP_CONTEXT = Object.assign({}, window.__APP_CONTEXT || {}, {
    initialPerfis: <?php echo json_encode(array_values($__normalizedPerfis), JSON_UNESCAPED_UNICODE); ?>,
    fallbackPerfis: [],
    allowedRulePerfis: ['ADMIN', 'DIRETORA', 'MAGISTRADA'],
    currentUser: {
        id: <?php echo json_encode($__sessionUserId, JSON_UNESCAPED_UNICODE); ?>,
        nome: <?php echo json_encode($__sessionUserName, JSON_UNESCAPED_UNICODE); ?>,
        usuario: <?php echo json_encode($__sessionUserName, JSON_UNESCAPED_UNICODE); ?>
    }
});
</script>
<script src="/assets/js/shared/user-context.js?v=<?php echo (int) $__userContextV; ?>"></script>
<script src="/assets/js/shared/page-helpers.js?v=<?php echo (int) $__pageHelpersV; ?>"></script>
<script src="/assets/js/shared/list-search.js?v=<?php echo (int) $__listSearchV; ?>"></script>
<script src="/assets/js/shared/auto-submit-filters.js?v=<?php echo (int) $__autoSubmitFiltersV; ?>" defer></script>
<script src="/assets/js/shared/cartorio-task-list.js?v=<?php echo (int) $__cartorioTaskListV; ?>"></script>
<script src="/assets/js/shared/oficios-edit.js?v=<?php echo (int) $__oficiosEditV; ?>"></script>
<script src="/assets/js/shared/oficios-list.js?v=<?php echo (int) $__oficiosListV; ?>"></script>
<script src="/assets/js/shared/gme-list.js?v=<?php echo (int) $__gmeListV; ?>"></script>
<script src="/assets/js/shared/gme-edit.js?v=<?php echo (int) $__gmeEditV; ?>"></script>
<script src="/assets/js/shared/gme-create.js?v=<?php echo (int) $__gmeCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-simple-edit.js?v=<?php echo (int) $__cartorioSimpleEditV; ?>"></script>
<script src="/assets/js/shared/cartorio-guias-edit.js?v=<?php echo (int) $__cartorioGuiasEditV; ?>"></script>
<script src="/assets/js/shared/cartorio-cejusc-list.js?v=<?php echo (int) $__cartorioCejuscListV; ?>"></script>
<script src="/assets/js/shared/cartorio-editais-list.js?v=<?php echo (int) $__cartorioEditaisListV; ?>"></script>
<script src="/assets/js/shared/cartorio-medidas-protetivas-list.js?v=<?php echo (int) $__cartorioMedidasListV; ?>"></script>
<script src="/assets/js/shared/cartorio-guias-list.js?v=<?php echo (int) $__cartorioGuiasListV; ?>"></script>
<script src="/assets/js/shared/cartorio-create-helpers.js?v=<?php echo (int) $__cartorioCreateHelpersV; ?>"></script>
<script src="/assets/js/shared/cartorio-alocacao-direcionada-create.js?v=<?php echo (int) $__cartorioAlocacaoDirCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-alocacao-direcionada-edit.js?v=<?php echo (int) $__cartorioAlocacaoDirEditV; ?>"></script>
<script src="/assets/js/shared/cartorio-create-bootstrap.js?v=<?php echo (int) $__cartorioCreateBootstrapV; ?>"></script>
<script src="/assets/js/shared/cartorio-city-lookup.js?v=<?php echo (int) $__cartorioCityLookupV; ?>"></script>
<script src="/assets/js/shared/cartorio-cartas-expedidas-create.js?v=<?php echo (int) $__cartorioCartasExpedidasCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-cartas-recebidas-create.js?v=<?php echo (int) $__cartorioCartasRecebidasCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-editais-create.js?v=<?php echo (int) $__cartorioEditaisCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-guias-create.js?v=<?php echo (int) $__cartorioGuiasCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-medidas-protetivas-create.js?v=<?php echo (int) $__cartorioMedidasCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-guias-create-ui.js?v=<?php echo (int) $__cartorioGuiasCreateUiV; ?>"></script>
<script src="/assets/js/shared/cartorio-medidas-protetivas-create-ui.js?v=<?php echo (int) $__cartorioMedidasCreateUiV; ?>"></script>
<script src="/assets/js/shared/record-edit-common.js?v=<?php echo (int) $__recordEditCommonV; ?>"></script>
<script src="/assets/js/shared/instituicoes-publicas-edit.js?v=<?php echo (int) $__instituicoesPublicasEditV; ?>"></script>
<script src="/assets/js/shared/consulta-partes-edit.js?v=<?php echo (int) $__consultaPartesEditV; ?>"></script>
<script src="/assets/js/shared/consulta-processos-edit.js?v=<?php echo (int) $__consultaProcessosEditV; ?>"></script>
<script src="/assets/js/shared/gabinete-reus-presos-edit.js?v=<?php echo (int) $__gabineteReusPresosEditV; ?>"></script>
<script src="/assets/js/shared/instituicoes-publicas-create.js?v=<?php echo (int) $__instituicoesPublicasCreateV; ?>"></script>
<script src="/assets/js/shared/partes-instituicoes-create.js?v=<?php echo (int) $__partesInstituicoesCreateV; ?>"></script>
<script src="/assets/js/shared/partes-instituicoes-edit.js?v=<?php echo (int) $__partesInstituicoesEditV; ?>"></script>
<script src="/assets/js/shared/processos-partes-create.js?v=<?php echo (int) $__processosPartesCreateV; ?>"></script>
<script src="/assets/js/shared/processos-partes-edit.js?v=<?php echo (int) $__processosPartesEditV; ?>"></script>
<script src="/assets/js/shared/ceapa-create.js?v=<?php echo (int) $__ceapaCreateV; ?>"></script>
<script src="/assets/js/shared/ceapa-edit.js?v=<?php echo (int) $__ceapaEditV; ?>"></script>
<script src="/assets/js/shared/ceapa-list.js?v=<?php echo (int) $__ceapaListV; ?>"></script>
<script src="/assets/js/shared/usuarios-create.js?v=<?php echo (int) $__usuariosCreateV; ?>"></script>
<script src="/assets/js/shared/usuarios-edit.js?v=<?php echo (int) $__usuariosEditV; ?>"></script>
<script src="/assets/js/shared/alterar-dados-pessoais.js?v=<?php echo (int) $__alterarDadosV; ?>"></script>
<script src="/assets/js/shared/proper-name-helper.js?v=<?php echo (int) $__properNameHelperV; ?>"></script>
<script src="/assets/js/shared/cartorio-cartas-recebidas-edit.js?v=<?php echo (int) $__cartasRecebidasEditV; ?>"></script>
<script>window.__IS_ESTAGIARIO_PROFILE = <?php echo $__isEstagiarioProfile ? 'true' : 'false'; ?>;</script>
<script>window.__ALLOW_DELETE_ON_EDIT_PAGE = <?php echo $__canDeleteOnThisEditPage ? 'true' : 'false'; ?>;</script>
<?php if ($__isEstagiarioProfile && $__isEditPage && !$__canDeleteOnThisEditPage): ?>
<style>
    button[id*="excluir"],
    button[id*="delete"],
    button[class*="excluir"],
    button[class*="delete"],
    button[data-action*="excluir"],
    button[data-action*="delete"],
    [data-parte-remove],
    .btn-del-end,
    .btn-del-tel,
    .btn-del-setor {
        display: none !important;
        visibility: hidden !important;
    }
</style>
<?php endif; ?>
<script src="/assets/js/shared/hide-delete-for-estagiario.js?v=<?php echo (int) $__hideDeleteV; ?>"></script>
