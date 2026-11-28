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
    <title>PDF Intelligence Engine (experimental)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/skills-pdf-intelligence.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/skills-pdf-intelligence.css') ?: time(); ?>">
</head>
<body class="page-internal skills-tool-page skills-intelligence-page">
<div class="spacing-top-page"></div>
<div class="header-page">
    <div class="location"><span class="all">Interno - Ferramentas - Skills -</span><span class="specific">PDF Intelligence experimental</span></div>
    <h1 class="title-page">PDF Intelligence Engine <span>Experimental</span></h1>
</div>

<main class="container-full card-page intelligence-shell">
    <div class="intelligence-toolbar">
        <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=skills">Voltar para Skills</button>
        <div class="intelligence-toolbar-actions">
            <button type="button" class="btn btn-danger-outline" id="pdfi-cancel" hidden>Cancelar</button>
            <button type="button" class="btn btn-secondary-outline" id="pdfi-zip" disabled>Baixar todos (ZIP)</button>
        </div>
    </div>

    <section class="intelligence-intro" aria-labelledby="pdfi-intro-title">
        <h2 id="pdfi-intro-title">Extração documental em avaliação</h2>
        <p>Esta versão experimental mantém seus arquivos no navegador e não substitui a ferramenta PDF para Markdown.</p>
        <p>PDF.js e OCR local em português brasileiro ficam no dispositivo. OCR pode errar nomes, identificadores, datas e valores; confira sempre o resultado contra a página original.</p>
    </section>

    <fieldset class="pdfi-modes">
        <legend>Modalidade</legend>
        <label><input type="radio" name="pdfi-mode" value="fast" checked> Rápida <small>PDF.js, texto nativo</small></label>
        <label><input type="radio" name="pdfi-mode" value="ocr"> OCR <small>Tesseract.js local, português</small></label>
        <label><input type="radio" name="pdfi-mode" value="hybrid"> Híbrida inteligente <small>seleção por página</small></label>
        <label class="pdfi-disabled-mode"><input type="radio" name="pdfi-mode" value="oxr" disabled> OXR avançada <small>indisponível: instância privada não configurada</small></label>
    </fieldset>

    <input id="pdfi-input" type="file" accept="application/pdf,.pdf" multiple hidden>
    <button type="button" class="pdfi-dropzone" id="pdfi-dropzone" aria-describedby="pdfi-privacy">
        <span class="pdfi-dropzone-icon" aria-hidden="true">PDF</span>
        <span class="pdfi-dropzone-title">Selecione ou arraste PDFs</span>
        <span class="pdfi-dropzone-hint">Até 20 MB por arquivo. Vários arquivos são processados em sequência.</span>
    </button>
    <p id="pdfi-privacy" class="pdfi-privacy">O PDF é lido com PDF.js no navegador. No modo OCR, páginas selecionadas são rasterizadas e reconhecidas pelo Tesseract.js local; nenhum conteúdo do PDF é enviado a uma API externa. Os assets de PDF.js e ZIP continuam sendo carregados do CDN já utilizado pela ferramenta original.</p>

    <div class="pdfi-overall" id="pdfi-overall" hidden>
        <label for="pdfi-progress">Progresso geral</label>
        <progress id="pdfi-progress" max="100" value="0"></progress>
        <span id="pdfi-progress-label">0%</span>
    </div>
    <div id="pdfi-announcement" class="pdfi-announcement" role="status" aria-live="polite"></div>

    <section class="pdfi-files" aria-labelledby="pdfi-files-title">
        <div class="pdfi-section-head"><div><h2 id="pdfi-files-title">Arquivos</h2><p>Progresso por documento e por página.</p></div><span id="pdfi-count">0 arquivos</span></div>
        <div id="pdfi-list" class="pdfi-list"><p class="pdfi-empty">Os PDFs adicionados aparecerão aqui.</p></div>
    </section>

    <section id="pdfi-results" class="pdfi-results" aria-labelledby="pdfi-results-title" hidden>
        <div class="pdfi-section-head"><div><h2 id="pdfi-results-title">Prévia e qualidade</h2><p id="pdfi-result-file"></p></div><div class="pdfi-downloads"><button type="button" class="btn btn-secondary-outline" id="pdfi-download-md">Baixar .md</button><button type="button" class="btn btn-secondary-outline" id="pdfi-download-json">Baixar JSON</button></div></div>
        <dl class="pdfi-report" id="pdfi-report"></dl>
        <div id="pdfi-warnings" class="pdfi-warnings" hidden></div>
        <div class="pdfi-compare">
            <section aria-labelledby="pdfi-original-title"><h3 id="pdfi-original-title">PDF original</h3><iframe id="pdfi-original" title="Prévia do PDF original"></iframe></section>
            <section aria-labelledby="pdfi-markdown-title"><h3 id="pdfi-markdown-title">Markdown normalizado</h3><pre id="pdfi-markdown"></pre></section>
        </div>
    </section>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
<script src="/assets/ocr/tesseract/7.0.0/tesseract.min.js" defer></script>
<script src="/assets/js/modules/skills/pdf-intelligence-core.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/skills/pdf-intelligence-core.js') ?: time(); ?>" defer></script>
<script src="/assets/js/modules/skills/pdf-intelligence-pdfjs.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/skills/pdf-intelligence-pdfjs.js') ?: time(); ?>" defer></script>
<script src="/assets/js/modules/skills/pdf-intelligence-tesseract.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/skills/pdf-intelligence-tesseract.js') ?: time(); ?>" defer></script>
<script src="/assets/js/modules/skills/pdf-intelligence.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/skills/pdf-intelligence.js') ?: time(); ?>" defer></script>
</body>
</html>
