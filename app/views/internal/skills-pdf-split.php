<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
Security::requireAuthentication();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Skills - Dividir PDF por tamanho</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/skills-pdf-markdown.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/skills-pdf-markdown.css') ?: time(); ?>">
</head>
<body class="page-internal skills-tool-page">
<div class="spacing-top-page"></div>
<div class="header-page">
    <div class="location"><span class="all">Interno - Ferramentas - Skills -</span> <span class="specific">Dividir PDF por tamanho</span></div>
    <h1 class="title-page">Dividir PDF por tamanho</h1>
</div>
<main class="container-full card-page skills-converter">
    <div class="skills-toolbar"><button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=skills">Voltar para Skills</button></div>
    <div class="skills-intro">
        <h2>Divida um PDF em partes menores</h2>
        <p>Escolha o arquivo e o tamanho máximo de cada parte. A divisão acontece entre páginas.</p>
    </div>
    <form id="split-form">
        <div class="skills-split-fields">
            <div><label for="split-file">Arquivo PDF</label><input id="split-file" type="file" accept="application/pdf,.pdf" required aria-describedby="split-help"></div>
            <div><label for="split-size">Tamanho máximo por parte (MB)</label><input id="split-size" type="number" min="0.1" max="500" step="0.1" value="20" required aria-describedby="split-help"></div>
        </div>
        <p id="split-help" class="skills-privacy">O PDF é processado no seu navegador, sem envio ao servidor. Se uma página sozinha exceder o limite, aumente o tamanho máximo. PDFs protegidos por senha não são compatíveis. Formulários interativos e assinaturas digitais podem não ser preservados nas partes.</p>
        <button class="btn btn-primary height-md" id="split-submit" type="submit">Dividir PDF</button>
    </form>
    <section class="skills-pdf-list-wrap skills-split-results" aria-labelledby="split-result-title">
        <div class="skills-pdf-list-head"><h2 id="split-result-title">Resultado</h2><button type="button" id="split-download-all" class="btn btn-secondary-outline height-md" disabled>Baixar todas as partes (ZIP)</button></div>
        <p id="split-status" class="skills-announcement" role="status" aria-live="polite">Selecione um PDF para começar.</p>
        <div id="split-progress-wrap" class="skills-progress" hidden><progress id="split-progress" max="100" aria-label="Progresso da divisão"></progress><span id="split-progress-text">Preparando…</span></div>
        <div id="split-results" class="skills-pdf-list"></div>
    </section>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
<script src="/assets/js/modules/skills/pdf-split.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/skills/pdf-split.js') ?: time(); ?>" defer></script>
</body>
</html>
