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
<body class="page-internal">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Ferramentas -</span>
        <span class="specific">Skills</span>
    </div>
    <h1 class="title-page">Skills</h1>
    <p class="description-page">Acesse atalhos de tarefas agênticas do ambiente PJe com um clique.</p>
</div>

<div class="container-full card-page">
    <div class="skills-list">
        <div class="skills-item">
            <div class="skills-item-content">
                <h2 class="skills-item-title">PDF para Markdown</h2>
                <p class="skills-item-description">Converte um ou mais PDFs para arquivos Markdown em processamento local no navegador.</p>
            </div>
            <button
                type="button"
                class="btn btn-primary height-md"
                data-nav-href="/frame.php?page=skillspdfmarkdown"
                aria-label="Abrir ferramenta PDF para Markdown"
                title="Abrir ferramenta PDF para Markdown">
                Abrir
            </button>
        </div>
    </div>
</div>
</body>
</html>
