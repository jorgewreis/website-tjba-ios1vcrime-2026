<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
?>
<?php
require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';
$tiposAudienciaPermitidos = [
    'Acordo de não persecução penal (ANPP)',
    'Admonitória',
    'Apresentação',
    'Audiência Concentrada',
    'Audiência Preliminar',
    'Círculo de Construção de Paz',
    'Coleta de DNA',
    'Conciliação ou mediação',
    'Custódia',
    'Depoimento Especial',
    'Entrevista',
    'Instrução e julgamento',
    'Inspeção Judicial',
    'Justificação',
    'Plenário virtual',
    'Pública',
    'Saneamento',
    'Sessão do Tribunal de Júri',
    'Sessões colegiadas',
    'Suspensão Condicional do Processo',
];
$dataMinimaCadastro = date('Y-m-d');
$horariosGrade = SalaAudienciasPautaService::horariosGradeCadastroAudiencia();
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Pauta de Audiências - Cadastro</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal page-operational-form">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Audiências -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Nova audiência</h1>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Dados da audiência</h2>
        </div>

        <form id="audiencia-create-form" class="display-flex col col-full">
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="audiencia-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-400 height-md" id="audiencia-processo" data-cnj-input="true" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                </div>
                <div class="col filter">
                    <label for="audiencia-classe">Classe processual <span class="label-hint-automatizado">*automatizado</span> <span class="label-hint-consulta-datajud" id="processo-ap-info" aria-live="polite"></span></label>
                    <input type="text" class="width-600 height-md" id="audiencia-classe" data-datajud-related="true" placeholder="Preenchido automaticamente pelo DataJud" readonly autocomplete="off">
                </div>
            </div>
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo" value="">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao" value="">

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="audiencia-tipo">Tipo de audiência <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="audiencia-tipo">
                        <option value="" selected disabled>Selecione o tipo</option>
                        <?php foreach ($tiposAudienciaPermitidos as $tipoAudienciaPermitido): ?>
                            <option value="<?php echo htmlspecialchars($tipoAudienciaPermitido, ENT_QUOTES); ?>"><?php echo htmlspecialchars($tipoAudienciaPermitido, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="audiencia-data">Data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-300 height-md" id="audiencia-data" min="<?php echo htmlspecialchars($dataMinimaCadastro, ENT_QUOTES); ?>" required>
                </div>
                <div class="col filter">
                    <label for="audiencia-hora">Hora <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="audiencia-hora" required>
                        <option value="" selected disabled>Selecione a hora</option>
                        <?php foreach ($horariosGrade as $hm): ?>
                            <option value="<?php echo htmlspecialchars($hm, ENT_QUOTES); ?>"><?php echo htmlspecialchars($hm, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="audiencia-sala">Localização <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="audiencia-sala">
                        <option value="" disabled>Selecione a localização</option>
                        <option value="presencial">Presencial - Fórum</option>
                        <option value="lifesize">Virtual - Lifesize</option>
                        <option value="audin" selected>Virtual - Audin</option>
                    </select>
                </div>
                <input type="hidden" id="audiencia-status" value="designada">
            </div>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="btn-audiencia-voltar" data-allow-during-loading="true">Voltar</button>
                <button type="button" class="btn btn-primary" id="btn-audiencia-cadastrar">Cadastrar</button>
            </div>
        </form>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
        window.AUDIENCIA_HORARIOS_GRADE = <?php echo json_encode($horariosGrade, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script src="/assets/js/modules/audiencias/pauta-create.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-create.js') ?: time(); ?>"></script>
</body>
</html>
