<?php
/**
 * Tema no iframe: color + script. Use em páginas que não carregam _internal_head_styles (ex.: dashboard).
 * Para listagens/formulários internos, prefira _internal_head_styles.php (inclui layout.css).
 */
?>
<link rel="stylesheet" href="/assets/css/shared/color.css">
<?php include __DIR__ . '/_theme_script.php'; ?>
