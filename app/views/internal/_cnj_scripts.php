<?php
declare(strict_types=1);

$cnjAsset = __DIR__ . '/../../../public/assets/js/modules/datajud/cnj.js';
$cnjM = @filemtime($cnjAsset) ?: time();
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
