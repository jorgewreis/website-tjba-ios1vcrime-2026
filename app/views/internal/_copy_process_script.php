<?php
declare(strict_types=1);

$__copyProcessPath = dirname(__DIR__, 3) . '/public/assets/js/shared/copy-process-number.js';
$__copyProcessV = @filemtime($__copyProcessPath) ?: time();
?>
<script src="/assets/js/shared/copy-process-number.js?v=<?php echo (int) $__copyProcessV; ?>"></script>
