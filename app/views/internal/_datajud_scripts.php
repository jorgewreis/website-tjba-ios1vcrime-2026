<?php
declare(strict_types=1);
/**
 * Validação CNJ e preenchimento a partir do cadastro local; não consulta a API pública.
 */
$djBase = __DIR__ . '/../../../public/assets/js/modules/datajud';
$cycleM = @filemtime($djBase . '/datajud-cycle-helper.js') ?: time();
$apiM = @filemtime($djBase . '/datajud-api.js') ?: time();
?>
<?php include __DIR__ . '/_cnj_scripts.php'; ?>
<script src="/assets/js/modules/datajud/datajud-cycle-helper.js?v=<?php echo $cycleM; ?>"></script>
<script src="/assets/js/modules/datajud/datajud-api.js?v=<?php echo $apiM; ?>"></script>
