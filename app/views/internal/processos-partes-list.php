<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';
require_once __DIR__ . '/../../helpers/ClasseProcessualListagemHelper.php';

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
$itemsPerPage = 15;
$totalPartes = count($partes);
$totalPages = max(1, (int) ceil($totalPartes / $itemsPerPage));
$currentPage = min($totalPages, max(1, (int) ($_GET['page_number'] ?? 1)));
$partesPage = array_slice($partes, ($currentPage - 1) * $itemsPerPage, $itemsPerPage);
$startItem = $totalPartes > 0 ? (($currentPage - 1) * $itemsPerPage) + 1 : 0;
$endItem = min($currentPage * $itemsPerPage, $totalPartes);
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
            
        </div>

        <form method="get" id="form-filters" class="display-flex col col-full" style="width:100%" data-auto-submit-filters>
            <input type="hidden" name="page" value="processosparteslist">
            <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col">
                        <label for="processo_id">ID do processo</label>
                        <input type="text" class="width-500 height-md" id="processo_id" name="processo_id" placeholder="Ex: PRC-..." value="<?php echo htmlspecialchars($processoId); ?>">
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
        <?php elseif (!empty($partesPage)): ?>
            <?php
            $classeRawPp = (string) ($processo['classe_nome'] ?? '');
            $classePpTxt = ClasseProcessualListagemHelper::textoListagem($classeRawPp);
            $classePpClass = ClasseProcessualListagemHelper::classesSpanMinor($classeRawPp);
            $numeroPp = (string) ($processo['numero_processo'] ?? $processoId);
            ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm border-bottom">
                <div class="col width-500 col-process process-info" title="Clique para copiar">
                    <span class="<?php echo htmlspecialchars($classePpClass, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($classePpTxt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($classePpTxt, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="major-info"><?php echo htmlspecialchars($numeroPp, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <?php foreach ($partesPage as $p): ?>
            <div class="display-flex col col-full register border-bottom">
                frame.php?page=consultapartesedit&id=<?php echo urlencode($p['parte_id'] ?? ''); ?>">Ver pessoa</button>
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

        <?php if ($processoId !== '' && $processo !== null): ?>
            <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
                <span class="pagination-info">
                    Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalPartes; ?> <?php echo $totalPartes === 1 ? 'registro' : 'registros'; ?>
                    (<?php echo $totalPages; ?> <?php echo $totalPages === 1 ? 'página' : 'páginas'; ?>, <?php echo $itemsPerPage; ?> itens por página)
                </span>
                <div class="pagination-controls">
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" href="/frame.php?page=processosparteslist&amp;processo_id=<?php echo urlencode($processoId); ?>&amp;page_number=<?php echo max(1, $currentPage - 1); ?>" aria-label="Página anterior"<?php echo $currentPage <= 1 ? ' aria-disabled="true"' : ''; ?>>‹</a>
                    <?php
                    $paginationStart = max(1, min($currentPage - 2, $totalPages - 4));
                    $paginationEnd = min($totalPages, $paginationStart + 4);
                    for ($pageButton = $paginationStart; $pageButton <= $paginationEnd; $pageButton++):
                    ?>
                        <?php if ($pageButton === $currentPage): ?>
                            <span class="pagination-number active" aria-current="page"><?php echo $pageButton; ?></span>
                        <?php else: ?>
                            <a class="pagination-number" href="/frame.php?page=processosparteslist&amp;processo_id=<?php echo urlencode($processoId); ?>&amp;page_number=<?php echo $pageButton; ?>"><?php echo $pageButton; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" href="/frame.php?page=processosparteslist&amp;processo_id=<?php echo urlencode($processoId); ?>&amp;page_number=<?php echo min($totalPages, $currentPage + 1); ?>" aria-label="Próxima página"<?php echo $currentPage >= $totalPages ? ' aria-disabled="true"' : ''; ?>>›</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
