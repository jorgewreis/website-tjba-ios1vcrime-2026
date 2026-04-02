<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
$connection = Conection::getInstance()->getConnection();
$partes = [];
$processo = null;

if ($processoId !== '') {
    $service = new ConsultaProcessosService($connection);
    $processo = $service->get($processoId);
    if ($processo !== null) {
        $partes = $processo['partes'] ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vínculos processo-parte</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Vínculos processo-parte</span></div>
        <h1 class="title-page">Vínculos processo-parte</h1>
        <p class="description-page">Informe o ID do processo para listar as partes vinculadas.</p>
    </div>
    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Partes do processo</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <?php
                $listExportPage = 'processosparteslist';
                $listExportQuery = ['processo_id' => $processoId];
                include __DIR__ . '/_list_export_buttons.php';
                ?>
            </div>
        </div>

        <form method="get" id="form-filters" class="display-flex col col-full" style="width:100%">
            <input type="hidden" name="page" value="processosparteslist">
            <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col">
                        <label for="processo_id">ID do processo</label>
                        <input type="text" class="width-500 height-md" id="processo_id" name="processo_id" placeholder="Ex: PRC-..." value="<?php echo htmlspecialchars($processoId); ?>">
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-end">
                    <div class="col">
                        <button type="submit" class="btn btn-primary height-md">Consultar</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($processoId !== '' && $processo === null): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Processo não encontrado.</span>
                </div>
            </div>
        <?php elseif (!empty($partes)): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm border-bottom">
                <div class="col process-info">
                    <span class="minor-info">Processo</span>
                    <span class="major-info"><?php echo htmlspecialchars($processo['numero_processo'] ?? $processoId); ?></span>
                </div>
            </div>
            <?php foreach ($partes as $p): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-md">
                        <div class="col width-400">
                            <span class="minor-info">Nome</span>
                            <span class="major-info"><?php echo htmlspecialchars($p['nome_completo'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">CPF</span>
                            <span class="major-info"><?php echo htmlspecialchars($p['cpf'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info"><?php echo htmlspecialchars($p['tipo_parte'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">Polo</span>
                            <span class="major-info"><?php echo htmlspecialchars($p['polo'] ?? '—'); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm">
                        <button type="button" class="btn btn-secondary-outline height-sm" data-nav-href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($p['parte_id'] ?? ''); ?>">Ver pessoa</button>
                        <button type="button" class="btn btn-secondary-outline height-sm" data-nav-href="/frame.php?page=processospartesedit&id=<?php echo urlencode($p['id'] ?? ''); ?>">Editar vínculo</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="display-flex row row-full justify-end gap-y-sm padding-x-sm padding-y-sm border-top">
                <button type="button" class="btn btn-primary height-md" data-nav-href="/frame.php?page=consultaprocessosedit&id=<?php echo urlencode($processoId); ?>">Abrir processo</button>
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processoId); ?>">Vincular parte</button>
            </div>
        <?php elseif ($processoId !== ''): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhuma parte vinculada a este processo.</span>
                </div>
            </div>
            <div class="display-flex row row-full justify-end gap-y-sm padding-x-sm padding-y-sm border-top">
                <button type="button" class="btn btn-primary height-md" data-nav-href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processoId); ?>">Vincular parte</button>
            </div>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Informe o ID do processo e clique em Consultar.</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
