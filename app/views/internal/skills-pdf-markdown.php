<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
Security::requireAuthentication();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Skills - PDF para Markdown</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/skills-pdf-markdown.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/skills-pdf-markdown.css') ?: time(); ?>">
</head>
<body class="page-internal skills-tool-page">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Ferramentas - Skills -</span>
        <span class="specific">PDF para Markdown</span>
    </div>
    <h1 class="title-page">PDF para Markdown</h1>
</div>

<main class="container-full card-page skills-converter">
    <div class="skills-toolbar">
        <button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=skills">Voltar para Skills</button>
        <button type="button" class="btn btn-danger-outline height-md" id="btn-skills-pdf-cancel" hidden>Cancelar conversões</button>
        <button type="button" class="btn btn-secondary-outline height-md" id="btn-skills-pdf-zip" disabled>Baixar todos (ZIP)</button>
    </div>

    <div class="skills-intro">
        <h2>Converta seus PDFs em Markdown</h2>
        <p>Selecione um ou mais arquivos. A conversão e o download começam automaticamente.</p>
    </div>
    <input id="skills-pdf-input" type="file" accept="application/pdf,.pdf" multiple hidden>
    <button type="button" class="skills-dropzone" id="skills-pdf-dropzone" aria-describedby="skills-pdf-help">
        <span class="skills-dropzone-icon" aria-hidden="true">PDF</span>
        <span class="skills-dropzone-title">Selecione ou arraste seus PDFs</span>
        <span class="skills-dropzone-subtitle">Clique para escolher arquivos de até 20 MB cada</span>
    </button>
    <p id="skills-pdf-help" class="skills-privacy">Os arquivos são processados no navegador e não são enviados ao servidor. PDFs digitalizados sem texto podem não produzir conteúdo útil. Se o download automático não iniciar, use “Baixar .md” ou permita downloads automáticos no navegador.</p>

    <section class="skills-pdf-list-wrap" aria-labelledby="skills-pdf-list-title">
        <div class="skills-pdf-list-head">
            <div><h2 id="skills-pdf-list-title">Seus arquivos</h2><p>Acompanhe a conversão e baixe novamente quando precisar.</p></div>
            <span id="skills-pdf-counter">0 arquivos</span>
        </div>
        <div id="skills-pdf-announcement" class="skills-announcement" role="status" aria-live="polite"></div>
        <div id="skills-pdf-list" class="skills-pdf-list"></div>
    </section>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
<script src="/assets/js/modules/skills/pdf-markdown.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/skills/pdf-markdown.js') ?: time(); ?>" defer></script>
</body>
</html>
