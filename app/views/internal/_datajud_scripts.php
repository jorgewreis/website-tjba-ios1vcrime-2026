<?php
declare(strict_types=1);
/**
 * Módulo único de consulta DataJud no front: cnj.js → datajud-cycle-helper → datajud-api.
 * Mesma ordem e cache-bust em todas as telas que disparam classe processual via API pública.
 */
$djBase = __DIR__ . '/../../../public/assets/js/modules/datajud';
$cycleM = @filemtime($djBase . '/datajud-cycle-helper.js') ?: time();
$apiM = @filemtime($djBase . '/datajud-api.js') ?: time();
?>
<?php include __DIR__ . '/_cnj_scripts.php'; ?>
<script src="/assets/js/modules/datajud/datajud-cycle-helper.js?v=<?php echo $cycleM; ?>"></script>
<script src="/assets/js/modules/datajud/datajud-api.js?v=<?php echo $apiM; ?>"></script>
