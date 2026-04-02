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
$__fallbackPerfis = [];
$__fallbackPerfil = trim((string) ($_SESSION['user_profile'] ?? ''));
if ($__fallbackPerfil !== '') {
    $__fallbackPerfis[] = $__fallbackPerfil;
}
if ($__isEditPage && $__currentPage === 'reuspresosedit') {
    $__canDeleteOnThisEditPage = in_array('ESTAGIARIO-GABINETE', $__normalizedPerfis, true)
        || in_array('SERVIDOR-GABINETE', $__normalizedPerfis, true);
}
?>
<script src="/assets/js/shared/navigate-buttons.js?v=<?php echo (int) $__navBtnV; ?>" defer></script>
<script>
window.__APP_CONTEXT = Object.assign({}, window.__APP_CONTEXT || {}, {
    initialPerfis: <?php echo json_encode(array_values($__normalizedPerfis), JSON_UNESCAPED_UNICODE); ?>,
    fallbackPerfis: <?php echo json_encode(array_values($__fallbackPerfis), JSON_UNESCAPED_UNICODE); ?>,
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
<script src="/assets/js/shared/cartorio-task-list.js?v=<?php echo (int) $__cartorioTaskListV; ?>"></script>
<script src="/assets/js/shared/oficios-edit.js?v=<?php echo (int) $__oficiosEditV; ?>"></script>
<script src="/assets/js/shared/oficios-list.js?v=<?php echo (int) $__oficiosListV; ?>"></script>
<script src="/assets/js/shared/gme-list.js?v=<?php echo (int) $__gmeListV; ?>"></script>
<script src="/assets/js/shared/gme-edit.js?v=<?php echo (int) $__gmeEditV; ?>"></script>
<script src="/assets/js/shared/gme-create.js?v=<?php echo (int) $__gmeCreateV; ?>"></script>
<script src="/assets/js/shared/cartorio-simple-edit.js?v=<?php echo (int) $__cartorioSimpleEditV; ?>"></script>
<script src="/assets/js/shared/cartorio-guias-edit.js?v=<?php echo (int) $__cartorioGuiasEditV; ?>"></script>
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
