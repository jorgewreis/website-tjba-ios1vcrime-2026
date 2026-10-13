<?php
declare(strict_types=1);

$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
$editaisCssV = @filemtime($assetsRoot . '/css/modules/cartorio/editais-v2.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Editais - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
        <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="/assets/css/modules/cartorio/editais-v2.css?v=<?php echo (int) $editaisCssV; ?>">

        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal editais-v2-page editais-create-page">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Editais -</span>
            <span class="specific">Cadastro</span>
        </div>
    </div>

    <div class="container-full card-page">
        <div class="card-title"><h1 class="title-page">Cadastrar Edital</h1></div>
        <div class="editais-form-section-title">Informações do edital</div>
        <div class="editais-form-grid">
            <div class="col filter editais-form-field">
                <label for="filter-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required autocomplete="off">
            </div>
            <div class="col filter editais-form-field">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" id="filter-classe" data-datajud-related="true" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <div class="editais-form-grid">
            <div class="col filter editais-form-field">
                <label for="filter-tipo-edital">Tipo de edital <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select id="filter-tipo-edital" required>
                    <option value="">Selecione</option>
                    <option value="Citação">Citação</option>
                    <option value="Intimação">Intimação</option>
                    <option value="Notificação">Notificação</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter editais-form-field">
                <label for="filter-prazo-dias">Prazo do edital (dias) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="number" id="filter-prazo-dias" min="1" placeholder="Ex.: 15" required title="Prazo em dias a partir da data de publicação">
            </div>
        </div>

        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-primary-outline" id="voltar-btn" type="button" data-allow-during-loading="true">Voltar</button>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <button class="btn btn-primary btn-with-spinner disabled" id="cadastrar-btn" type="button" disabled aria-disabled="true"><span class="btn-icon-text">Cadastrar</span><span class="btn-icon hidden"><i class="fi fi-rr-spinner" aria-hidden="true"></i></span></button>
            </div>
        </div>

        
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script>
        (function () {
            if (window.App && App.CartorioCreateBootstrap) {
                App.CartorioCreateBootstrap.bindBackButton({ href: '/frame.php?page=editais' });
            }
            if (!window.App || !App.CartorioEditaisCreate || typeof App.CartorioEditaisCreate.initialize !== 'function') {
                return;
            }

            App.CartorioEditaisCreate.initialize();
        })();
    </script>
</body>
</html>
