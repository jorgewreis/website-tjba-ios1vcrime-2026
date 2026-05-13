<?php
declare(strict_types=1);

$__toastJsPath = dirname(__DIR__, 3) . '/public/assets/js/shared/toast.js';
$__toastJsV = @filemtime($__toastJsPath) ?: time();
$__toastHelperPath = dirname(__DIR__, 3) . '/public/assets/js/shared/toast-helper.js';
$__toastHelperV = @filemtime($__toastHelperPath) ?: time();
?>
<script src="/assets/js/shared/toast.js?v=<?php echo (int) $__toastJsV; ?>"></script>
<script src="/assets/js/shared/toast-helper.js?v=<?php echo (int) $__toastHelperV; ?>"></script>
