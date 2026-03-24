<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Pauta de Audiências - Lista</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Sala de Audiências - Pauta -</span>
            <span class="specific">Lista</span>
        </div>
        <h1 class="title-page">Pauta de Audiências</h1>
        <p class="description-page">Lista de audiências cadastradas com filtros por período, tipo e status.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start"><h2>Lista de audiências</h2></div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo, parte ou local..." autocomplete="off">
                <select class="width-300 height-md" id="export-type" disabled>
                    <option value="" selected>Exportar lista</option>
                    <option value="pdf">Gerar PDF</option>
                    <option value="csv">Gerar CSV</option>
                </select>
                <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Nova audiência</button>
            </div>
        </div>

        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                <div class="col">
                    <label for="filter-periodo">Período</label>
                    <select class="width-400 height-md" id="filter-periodo">
                        <option value="" selected>Todos os períodos</option>
                        <option value="hoje">Hoje (6)</option>
                        <option value="semana">Próximos 7 dias (24)</option>
                        <option value="mes">Este mês (88)</option>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-tipo">Tipo de audiência</label>
                    <select class="width-500 height-md" id="filter-tipo">
                        <option value="" selected>Mostrar todos</option>
                        <option value="instrucao">Instrução e Julgamento (37)</option>
                        <option value="custodia">Custódia (15)</option>
                        <option value="conciliacao">Conciliação (8)</option>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-status">Status</label>
                    <select class="width-300 height-md" id="filter-status">
                        <option value="" selected>Mostrar todos</option>
                        <option value="agendada">Agendada (52)</option>
                        <option value="realizada">Realizada (20)</option>
                        <option value="cancelada">Cancelada (2)</option>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="col">
                    <label for="items-per-page">Itens por página</label>
                    <select class="width-300 height-md" id="items-per-page">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="display-flex col col-full register border-bottom">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                    <div class="col width-500 col-process process-info" title="clique para copiar">
                        <span class="minor-info">Processo</span>
                        <span class="major-info">0800234-11.2026.8.05.0103</span>
                    </div>
                    <div class="col width-300">
                        <span class="minor-info">Tipo</span>
                        <span class="major-info">Instrução e Julgamento</span>
                    </div>
                    <div class="col width-300">
                        <span class="minor-info">Data e hora</span>
                        <span class="major-info">26/03/2026 09:00</span>
                    </div>
                    <div class="col width-300">
                        <span class="minor-info">Localização</span>
                        <span class="major-info">Lifesize</span>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <div class="col button-table button-edit" data-audiencia-edit="AUD-001" title="Editar audiência"><i class="fi fi-rr-file-edit"></i></div>
                    <div class="col button-table button-edit" data-parte-edit="PARTE-001" title="Editar parte"><i class="fi fi-rr-user-pen"></i></div>
                    <div class="col"><div class="width-300 padding-x-lg status status-pending">Agendada</div></div>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">Mostrando 1-1 de 1 audiência (1 página, 25 itens por página)</span>
            <div class="pagination-controls">
                <a class="pagination-symbol disabled" aria-disabled="true"><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></a>
                <a class="pagination-number active" href="#">1</a>
                <a class="pagination-symbol disabled" aria-disabled="true"><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></a>
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

    <script src="/assets/js/modules/audiencias/pauta-list.js"></script>
</body>
</html>
