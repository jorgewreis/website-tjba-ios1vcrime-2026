<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teste PJe Público - Consulta Processual</title>
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <style>
        .pje-publico-test-table td:first-child {
            width: 260px;
            font-weight: 600;
        }

        .pje-publico-test-pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 1.5;
        }

        .pje-publico-test-options {
            min-height: 40px;
        }
    </style>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno -</span>
            <span class="specific">Teste PJe Público</span>
        </div>
        <h1 class="title-page">Consulta Processual Pública (PJe)</h1>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Consulta por Número CNJ</h2>
        </div>
        <div class="padding-x-md padding-y-md">
            <form id="pje-publico-test-form" class="display-flex row row-full align-end gap-sm">
                <div class="display-flex col col-full">
                    <label for="pje-publico-cnj-input">Número do processo (CNJ)</label>
                    <input
                        id="pje-publico-cnj-input"
                        name="numero"
                        type="text"
                        class="input"
                        data-cnj-input="true"
                        autocomplete="off"
                        placeholder="0000000-00.0000.0.00.0000"
                    >
                </div>
                <label class="display-flex row align-center gap-xs pje-publico-test-options">
                    <input id="pje-publico-raw-input" type="checkbox" value="1">
                    <span>Incluir diagnóstico raw</span>
                </label>
                <button id="pje-publico-submit-btn" type="submit" class="btn btn-primary height-md">Consultar dados</button>
                <button id="pje-publico-healthcheck-btn" type="button" class="btn btn-primary-outline height-md">Testar conectividade</button>
            </form>

            <div id="pje-publico-result-wrapper" class="margin-top-md" hidden>
                <table class="table pje-publico-test-table">
                    <tbody id="pje-publico-result-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="/assets/js/modules/pje-publico/cnj.js"></script>
    <script src="/assets/js/modules/pje-publico/pje-publico-api.js"></script>
</body>
</html>
