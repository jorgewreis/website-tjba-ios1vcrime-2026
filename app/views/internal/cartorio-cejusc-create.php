<?php
declare(strict_types=1);
$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
$cejuscCssV = @filemtime($assetsRoot . '/css/modules/cartorio/cejusc-v2.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo cadastro Cejusc - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/modules/cartorio/cejusc-v2.css?v=<?php echo (int) $cejuscCssV; ?>">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/cartorio-cejusc.js"></script>
</head>
<body class="page-internal cejusc-v2-page cejusc-form-page cejusc-create-page">
    <div class="spacing-top-page"></div>
    <div class="header-page"><div class="location"><span class="all">Interno - Cartório - Cejusc -</span><span class="specific">Cadastro</span></div></div>
    <main class="container-full card-page">
        <div class="card-title"><h1 class="title-page">Novo cadastro Cejusc</h1></div>
        <form id="cejusc-form">
            <div class="cejusc-form-section-title"><h2>Informações do cadastro</h2></div>
            <div class="cejusc-form-grid">
                <div class="col filter cejusc-form-field cejusc-field-process">
                    <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="filter-processo" name="numero_processo" required inputmode="numeric" maxlength="25" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                </div>
            </div>
            <input type="hidden" id="filter-classe" data-datajud-related="true" data-datajud-silent-loading="true">
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
            <div class="cejusc-form-grid">
                <div class="col filter cejusc-form-field cejusc-field-party" id="denunciado-col">
                    <label for="filter-denunciado">Réu <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="filter-denunciado-select" style="display:none" aria-label="Réus vinculados ao processo"></select>
                    <input type="text" id="filter-denunciado" name="nome_parte" placeholder="Digite o nome completo" maxlength="180" aria-label="Nome do réu quando não há réus ou opção Novo cadastro de réu">
                </div>
                <div class="col filter cejusc-form-field cejusc-field-date">
                    <label for="data-envio">Data de envio ao CEJUSC <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" id="data-envio" name="data_envio_cejusc" required value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="card-buttons">
                <button type="button" class="btn btn-primary-outline" onclick="location.href='/frame.php?page=cejusc'">Voltar</button>
                <button class="btn btn-primary" type="submit">Cadastrar</button>
            </div>
        </form>
    </main>
    <div class="footer-page"><div class="location"><span class="all">Aplicativo web desenvolvido integralmente por </span><span class="specific">Jorge Reis Develop</span><span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span></div></div>
    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script>if (window.App && App.CartorioCejusc) App.CartorioCejusc.bindCreate(document.getElementById('cejusc-form'));</script>
</body>
</html>
