<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

$routePage = trim((string) ($_GET['page'] ?? 'monitoramentogabinetecreate'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Monitoramento Eletrônico (Gabinete) — Cadastro</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Monitoramento Eletrônico -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Novo monitoramento eletrônico</h1>
        <p class="description-page">Vincule o registro ao processo em contexto e ao réu. Processo e classe são definidos pelo sistema (DataJud).</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Dados do registro</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-400 height-md" id="filter-processo" name="processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                </div>
                <div class="col">
                    <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                    <input type="text" class="width-600 height-md" id="filter-classe" data-datajud-related="true" name="classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
                </div>
            </div>
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Réu</h2>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" id="gab-mon-reu-row-principal">
                <div class="col filter">
                    <label for="gab-mon-reu-select">Réu / Denunciado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-800 height-md" id="gab-mon-reu-select" name="reu_id" style="display: none;" aria-label="Réus vinculados ao processo ou nome livre"></select>
                    <input type="text" class="width-800 height-md" id="gab-mon-reu-nome" name="reu_nome_livre" placeholder="Digite o nome completo" maxlength="255" autocomplete="off" aria-label="Nome do réu quando não há réus na lista ou opção Outro">
                </div>
            </div>
            <p class="minor-info" id="gab-mon-reu-hint" style="margin:0;">Com processo válido, o sistema carrega os réus vinculados; se não houver cadastro, informe o nome completo.</p>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="btn-gab-mon-voltar" data-allow-during-loading="true">Voltar</button>
                <button type="button" class="btn btn-primary" id="btn-gab-mon-registrar">Registrar</button>
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
            if (!window.App || !App.GmeCreate || typeof App.GmeCreate.initialize !== 'function') {
                return;
            }

            App.GmeCreate.initialize({
                storageKey: 'gme.postReloadToast',
                backButtonId: 'btn-gab-mon-voltar',
                registerButtonId: 'btn-gab-mon-registrar',
                listHref: '/frame.php?page=monitoramentogabinete',
                reusEndpoint: '/api/gabinete-monitoramento-eletronico/reus-partes.php',
                searchPeopleEndpoint: '/api/pessoas/search-by-name.php',
                createEndpoint: '/api/gabinete-monitoramento-eletronico/create.php'
            });
        })();
    </script>
</body>
</html>
