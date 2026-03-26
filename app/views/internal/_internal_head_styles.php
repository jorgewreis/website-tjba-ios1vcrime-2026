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
?>
<script src="/assets/js/shared/navigate-buttons.js?v=<?php echo (int) $__navBtnV; ?>" defer></script>
<script>window.__IS_ESTAGIARIO_PROFILE = <?php echo $__isEstagiarioProfile ? 'true' : 'false'; ?>;</script>
<script src="/assets/js/shared/hide-delete-for-estagiario.js?v=<?php echo (int) $__hideDeleteV; ?>" defer></script>
