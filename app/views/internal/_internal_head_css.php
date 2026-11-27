<?php
/** Folhas de estilo compartilhadas pelas páginas internas. */
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@100..800&family=Syne+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/shared/reset.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/reset.css') ?: time(); ?>">
<link rel="stylesheet" href="/assets/css/shared/color.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/color.css') ?: time(); ?>">
<?php include __DIR__ . '/_theme_script.php'; ?>
<link rel="stylesheet" href="/assets/css/shared/layout.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/layout.css') ?: time(); ?>">
<link rel="stylesheet" href="/assets/css/shared/page.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/page.css') ?: time(); ?>">
<link rel="stylesheet" href="/assets/css/shared/form.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/form.css') ?: time(); ?>">
<link rel="stylesheet" href="/assets/css/shared/table.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/table.css') ?: time(); ?>">
<link rel="stylesheet" href="/assets/css/shared/toast.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/toast.css') ?: time(); ?>">
<?php require_once __DIR__ . '/_processo_ficha_modal_assets.php'; ?>
