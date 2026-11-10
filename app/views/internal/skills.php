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
    <title>Skills</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/skills.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/skills.css') ?: time(); ?>">
</head>
<body class="page-internal skills-page">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Ferramentas -</span>
        <span class="specific">Skills</span>
    </div>
    <h1 class="title-page">Skills</h1>
</div>

<main class="skills-shell">
    <section class="skills-overview" aria-labelledby="skills-overview-title">
        <div>
            <h2 id="skills-overview-title">Ferramentas para seus documentos</h2>
            <p>Converta ou divida arquivos PDF em poucos passos. Escolha uma ferramenta para começar.</p>
        </div>
        <p class="skills-privacy"><span>Seus arquivos são processados no navegador, sem envio ao servidor.</span></p>
    </section>

    <section class="skills-section" aria-labelledby="skills-tools-title">
        <div class="skills-section-header">
            <div><h2 id="skills-tools-title">Ferramentas disponíveis</h2><p>Confira como funciona e acesse a ferramenta desejada.</p></div>
        </div>
        <div class="skills-list">
            <article class="skills-item" aria-labelledby="skills-markdown-title">
                <div class="skills-item-heading">
                    <span class="skills-icon" aria-hidden="true">MD</span>
                    <div><h3 id="skills-markdown-title">PDF para Markdown</h3><p>Converta o texto de um ou mais PDFs em arquivos Markdown (.md).</p></div>
                </div>
                <div class="skills-instructions">
                    <h4>Como usar</h4>
                    <ol>
                        <li>Abra a ferramenta e selecione ou arraste seus PDFs.</li>
                        <li>Acompanhe a conversão e o download automático.</li>
                        <li>Se precisar, baixe novamente cada arquivo ou todos em ZIP.</li>
                    </ol>
                </div>
                <p class="skills-note">Até 20 MB por PDF. Arquivos digitalizados sem texto podem não gerar conteúdo útil.</p>
                <div class="skills-item-footer"><button type="button" class="btn btn-primary height-md" data-nav-href="/frame.php?page=skillspdfmarkdown" aria-label="Abrir ferramenta PDF para Markdown">Abrir ferramenta</button></div>
            </article>
            <article class="skills-item" aria-labelledby="skills-split-title">
                <div class="skills-item-heading">
                    <span class="skills-icon skills-icon-split" aria-hidden="true">PDF</span>
                    <div><h3 id="skills-split-title">Dividir PDF por tamanho</h3><p>Separe um PDF em partes menores de acordo com o tamanho escolhido.</p></div>
                </div>
                <div class="skills-instructions">
                    <h4>Como usar</h4>
                    <ol>
                        <li>Abra a ferramenta e selecione um PDF.</li>
                        <li>Informe o tamanho máximo por parte e inicie a divisão.</li>
                        <li>Baixe as partes individualmente ou em um ZIP.</li>
                    </ol>
                </div>
                <p class="skills-note">A divisão acontece entre páginas; aumente o limite se uma página sozinha for maior que o tamanho escolhido.</p>
                <div class="skills-item-footer"><button type="button" class="btn btn-primary height-md" data-nav-href="/frame.php?page=skillspdfsplit" aria-label="Abrir ferramenta Dividir PDF por tamanho">Abrir ferramenta</button></div>
            </article>
        </div>
    </section>
</main>
</body>
</html>
