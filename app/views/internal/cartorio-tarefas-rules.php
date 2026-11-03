<?php
declare(strict_types=1);
$assetRoot = dirname(__DIR__, 3) . '/public/assets/css/shared/';
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regras de automação - Primeira Vara Crime de Ilhéus</title>
    <?php foreach (['reset', 'color', 'layout', 'page', 'form', 'table', 'toast'] as $css): ?>
    <link rel="stylesheet" href="/assets/css/shared/<?= $css ?>.css?v=<?= $escape((string) (@hash_file('sha256', $assetRoot . $css . '.css') ?: '1')) ?>">
    <?php endforeach; ?>
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>
<div class="header-page">
    <div class="location"><span class="all">FokusLaw - Tarefas -</span><span class="specific">Regras de automação</span></div>
    <div class="card-title"><div class="display-flex col col-full justify-start"><h1 class="title-page" style="color: var(--color-primary-active);">Regras de automação</h1></div></div>
</div>
<main class="container-full card-page">
    <div class="card-subtitle"><div class="display-flex row row-full justify-start"><h2>Configuração de regras</h2></div></div>
    <div class="display-flex row row-full justify-start align-center gap-y-md padding-x-lg padding-y-md border-bottom">
        <button type="button" class="btn btn-primary" disabled>Nova regra</button>
    </div>
    <div class="display-flex row row-full justify-start align-center gap-y-md padding-x-lg padding-y-md">
        <button type="button" class="btn btn-secondary" onclick="location.href='/frame.php?page=tarefas'">Voltar</button>
    </div>
</main>
</body>
</html>
