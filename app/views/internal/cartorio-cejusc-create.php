<?php
declare(strict_types=1);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Novo cadastro CEJUSC</title><?php include __DIR__ . '/_internal_head_styles.php'; ?><?php include __DIR__ . '/_toast_script.php'; ?><script src="/assets/js/shared/cartorio-cejusc.js"></script></head>
<body class="page-internal"><div class="spacing-top-page"></div><div class="header-page"><div class="location"><span class="all">Interno - Cartório - Cejusc -</span><span class="specific">Cadastro</span></div><h1 class="title-page">Novo cadastro CEJUSC</h1><p class="description-page">Informe os dados do encaminhamento ao CEJUSC.</p></div>
<div class="container-full card-page">
    <form id="cejusc-form">
        <div class="display-flex row row-full align-center justify-start padding-x-md padding-y-md border-bottom">
            <h2>Informações obrigatórias</h2>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input class="width-500 height-md" id="processo" name="numero_processo" required inputmode="numeric" maxlength="25" placeholder="0000000-00.0000.0.00.0000">
            </div>
            <div class="col filter">
                <label for="parte">Nome da parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input class="width-600 height-md" id="parte" name="nome_parte" required maxlength="180" autocomplete="name">
            </div>
            <div class="col filter">
                <label for="data-envio">Data de envio ao CEJUSC <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input class="width-250 height-md" type="date" id="data-envio" name="data_envio_cejusc" required value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>
        <div class="card-buttons">
            <button type="button" class="btn btn-secondary" onclick="location.href='/frame.php?page=cejusc'">Voltar</button>
            <button class="btn btn-primary" type="submit">Cadastrar</button>
        </div>
    </form>
</div>
<div class="footer-page"><div class="location"><span class="all">Aplicativo web desenvolvido integralmente por </span><span class="specific">Jorge Reis Develop</span><span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span></div></div>
<script>if (window.App && App.CartorioCejusc) App.CartorioCejusc.bindCreate(document.getElementById('cejusc-form'));</script></body></html>
