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
<body class="page-internal">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Admin - Skills -</span>
        <span class="specific">PDF para Markdown</span>
    </div>
    <h1 class="title-page">PDF para Markdown</h1>
    <p class="description-page">Converta arquivos PDF para Markdown diretamente no navegador.</p>
</div>

<div class="container-full card-page">
    <div class="skills-toolbar">
        <button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=skills">Voltar para Skills</button>
        <button type="button" class="btn btn-primary-outline height-md" id="btn-skills-pdf-add">Adicionar PDFs</button>
        <button type="button" class="btn btn-primary height-md" id="btn-skills-pdf-process">Converter para Markdown</button>
        <button type="button" class="btn btn-secondary height-md" id="btn-skills-pdf-zip">Baixar todos (ZIP)</button>
    </div>

    <input id="skills-pdf-input" type="file" accept="application/pdf" multiple hidden>

    <button type="button" class="skills-dropzone" id="skills-pdf-dropzone" title="Clique ou arraste arquivos PDF">
        <span class="skills-dropzone-title">Clique ou arraste PDFs aqui</span>
        <span class="skills-dropzone-subtitle">Os arquivos ficam no navegador; não há envio para o servidor.</span>
    </button>

    <div class="skills-pdf-list-wrap">
        <div class="skills-pdf-list-head">
            <strong>Arquivos</strong>
            <span id="skills-pdf-counter">0 arquivo(s)</span>
        </div>
        <div id="skills-pdf-list" class="skills-pdf-list"></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
<script src="/assets/js/modules/skills/pdf-markdown.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/skills/pdf-markdown.js') ?: time(); ?>" defer></script>
</body>
</html>
