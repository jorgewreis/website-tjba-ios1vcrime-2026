<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teste MNI - Consulta Processual</title>
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <style>
        .mni-test-table td:first-child {
            width: 260px;
            font-weight: 600;
        }
        .mni-test-pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 1.5;
        }
    </style>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno -</span>
            <span class="specific">Teste MNI</span>
        </div>
        <h1 class="title-page">Consulta Processual (MNI)</h1>
        <p class="description-page">Página de teste sem menu para consulta por número CNJ.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Consulta por Número CNJ</h2>
        </div>
        <div class="padding-x-md padding-y-md">
            <form id="mni-test-form" class="display-flex row row-full align-end gap-sm">
                <div class="display-flex col col-full">
                    <label for="mni-cnj-input">Número do processo (CNJ)</label>
                    <input
                        id="mni-cnj-input"
                        name="numero"
                        type="text"
                        class="input"
                        data-cnj-input="true"
                        autocomplete="off"
                        placeholder="0000000-00.0000.0.00.0000"
                    >
                </div>
                <button id="mni-submit-btn" type="submit" class="btn btn-primary height-md">Consultar dados</button>
            </form>

            <div id="mni-result-wrapper" class="margin-top-md" hidden>
                <table class="table mni-test-table">
                    <tbody id="mni-result-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="/assets/js/shared/toast.js"></script>
    <script src="/assets/js/modules/mni/cnj.js"></script>
    <script src="/assets/js/modules/mni/mni-api.js"></script>
</body>
</html>
