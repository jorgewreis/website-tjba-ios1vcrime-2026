<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
if ($processoId === '') { header('Location: /frame.php?page=consultaprocessos'); exit; }

$connection = Conection::getInstance()->getConnection();
$processosRepo = new ProcessosRepository($connection);
$processo = $processosRepo->findById($processoId);
if ($processo === null) { header('Location: /frame.php?page=consultaprocessos'); exit; }

$tiposValidos = [
    'reu' => 'Réu',
    'indiciado' => 'Indiciado',
    'sentenciado' => 'Sentenciado',
    'requerido' => 'Requerido',
    'vitima' => 'Vítima',
    'requerente' => 'Requerente',
    'testemunha_defesa' => 'Testemunha defesa',
    'testemunha_denuncia' => 'Testemunha denúncia',
    'advogado' => 'Advogado',
    'defensor_publico' => 'Defensor público',
];
$polosValidos = ['ativo' => 'Ativo', 'passivo' => 'Passivo', 'terceiros' => 'Terceiros'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vincular parte ao processo</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Vincular parte</span></div>
        <h1 class="title-page">Vincular parte ao processo <?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?></h1>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($processoId); ?>">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Parte</h2>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="parte-id">ID da parte (pessoa) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md white" id="parte-id" placeholder="ID da pessoa cadastrada (ex: PRT-...)" required>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="tipo_parte">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-200 height-md white" id="tipo_parte" required>
                    <option value="">Selecione</option>
                    <?php foreach ($tiposValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label for="polo">Polo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-200 height-md white" id="polo" required>
                    <option value="">Selecione</option>
                    <?php foreach ($polosValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=consultaprocessosedit&id=<?php echo urlencode($processoId); ?>">Voltar</button>
            <button type="button" class="btn btn-primary" id="vincular-btn">Vincular</button>
        </div>
    </div>
    <script>
        (function () {
            if (!window.App || !App.ProcessosPartesCreate || typeof App.ProcessosPartesCreate.initialize !== 'function') {
                return;
            }

            App.ProcessosPartesCreate.initialize();
        })();
    </script>
</body>
</html>
