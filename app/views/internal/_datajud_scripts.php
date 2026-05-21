<?php
declare(strict_types=1);
/**
 * Módulo único de consulta DataJud no front: cnj.js → datajud-cycle-helper → datajud-api.
 * Mesma ordem e cache-bust em todas as telas que disparam classe processual via API pública.
 */
$djBase = __DIR__ . '/../../../public/assets/js/modules/datajud';
$cnjM = @filemtime($djBase . '/cnj.js') ?: time();
$cycleM = @filemtime($djBase . '/datajud-cycle-helper.js') ?: time();
$apiM = @filemtime($djBase . '/datajud-api.js') ?: time();
?>
<script src="/assets/js/modules/datajud/cnj.js?v=<?php echo $cnjM; ?>"></script>
<script>
(function () {
    function bootCnjBindings() {
        if (typeof window.bindAllCNJInputs === 'function') {
            window.bindAllCNJInputs(document);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCnjBindings);
    } else {
        bootCnjBindings();
    }
})();
</script>
<script src="/assets/js/modules/datajud/datajud-cycle-helper.js?v=<?php echo $cycleM; ?>"></script>
<script src="/assets/js/modules/datajud/datajud-api.js?v=<?php echo $apiM; ?>"></script>
