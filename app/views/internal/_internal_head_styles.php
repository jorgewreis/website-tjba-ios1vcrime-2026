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

$__currentUserPerfis = (isset($userPerfils) && is_array($userPerfils)) ? $userPerfils : [];
$__isEstagiarioProfile = false;
foreach ($__currentUserPerfis as $__perfilRaw) {
    $__perfil = strtoupper(trim((string) $__perfilRaw));
    if ($__perfil === 'ESTAGIARIO' || strpos($__perfil, 'ESTAGIARIO-') === 0) {
        $__isEstagiarioProfile = true;
        break;
    }
}
$__currentPage = trim((string) ($_GET['page'] ?? ''));
$__isEditPage = $__currentPage !== '' && (bool) preg_match('/edit$/i', $__currentPage);
?>
<script src="/assets/js/shared/navigate-buttons.js?v=<?php echo (int) $__navBtnV; ?>" defer></script>
<script>window.__IS_ESTAGIARIO_PROFILE = <?php echo $__isEstagiarioProfile ? 'true' : 'false'; ?>;</script>
<?php if ($__isEstagiarioProfile && $__isEditPage): ?>
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
