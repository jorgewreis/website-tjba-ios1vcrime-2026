<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

$connection = Conection::getInstance()->getConnection();
$dataMax = date('Y-m-d');
$cartorioAlocacaoPrefix = 'midias';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sala de Audiências - Mídias - Cadastro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Sala de Audiências - Mídias -</span>
        <span class="specific">Cadastro</span>
    </div>
    <h1 class="title-page">Nova tarefa de mídia</h1>
    <p class="description-page">Cadastro de tarefa para fluxo de mídia vinculado à audiência finalizada/realizada.</p>
</div>

<div class="container-full card-page">
    <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
        <h2>Dados da tarefa</h2>
    </div>

    <form id="midias-create-form" class="display-flex col col-full">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="audiencia-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-400 height-md" id="audiencia-processo" data-cnj-input="true" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
            </div>
            <div class="col filter">
                <label for="audiencia-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-600 height-md readonly" id="audiencia-classe" data-datajud-related="true" placeholder="Preenchido automaticamente pelo DataJud" readonly autocomplete="off">
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" value="">
        <input type="hidden" id="hidden-classe-descricao" value="">

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="midias-data-audiencia">Data da audiência <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-300 height-md" id="midias-data-audiencia" max="<?php echo htmlspecialchars($dataMax, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col filter">
                <label for="midias-pauta-id">Audiências realizadas/finalizadas na data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-600 height-md" id="midias-pauta-id">
                    <option value="">Selecione a audiência</option>
                </select>
            </div>
            <div class="col filter">
                <label for="midias-tipo-audiencia">Tipo de audiência <span class="label-hint-automatizado">*automático</span></label>
                <input type="text" class="width-500 height-md readonly" id="midias-tipo-audiencia" readonly>
            </div>
            <div class="col filter">
                <label for="midias-qtd">Qtd de mídias <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="number" min="1" step="1" class="width-200 height-md" id="midias-qtd" value="1">
            </div>
        </div>

        <?php include __DIR__ . '/_cartorio_alocacao_direcionada_create.php'; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=salamidias">Voltar</button>
            <button type="button" class="btn btn-primary" id="btn-midias-cadastrar">Cadastrar</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/_datajud_scripts.php'; ?>
<script src="/assets/js/shared/cartorio-alocacao-direcionada-create.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/cartorio-alocacao-direcionada-create.js') ?: time(); ?>"></script>
<script src="/assets/js/modules/audiencias/midias-create.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/midias-create.js') ?: time(); ?>"></script>
</body>
</html>
