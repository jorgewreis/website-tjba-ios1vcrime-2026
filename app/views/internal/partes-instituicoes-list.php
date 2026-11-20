<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';
require_once __DIR__ . '/../../repositories/InstituicoesPublicasRepository.php';
require_once __DIR__ . '/../../helpers/InstituicoesConsultaPolicy.php';

$parteId = trim((string) ($_GET['parte_id'] ?? ''));
$instituicaoId = trim((string) ($_GET['instituicao_id'] ?? ''));

$connection = Conection::getInstance()->getConnection();
$repo = new PartesInstituicoesRepository($connection);
$onlyActiveInstitutions = InstituicoesConsultaPolicy::onlyActiveForUser((string) ($_SESSION['id_usuario'] ?? ''));
$rows = [];
$parteNome = '';
$instituicaoNome = '';

if ($parteId !== '') {
    $partesRepo = new PartesRepository($connection);
    $p = $partesRepo->findById($parteId);
    $parteNome = $p['nome_completo'] ?? $parteId;
    $rows = $repo->findByParteId($parteId, null, $onlyActiveInstitutions);
} elseif ($instituicaoId !== '') {
    $instRepo = new InstituicoesPublicasRepository($connection);
    $inst = $instRepo->findById($instituicaoId, $onlyActiveInstitutions);
    $instituicaoNome = $inst['nome'] ?? $instituicaoId;
    $rows = $repo->findByInstituicaoId($instituicaoId, null, $onlyActiveInstitutions);
}
$itemsPerPage = 15;
$totalRows = count($rows);
$totalPages = max(1, (int) ceil($totalRows / $itemsPerPage));
$currentPage = min($totalPages, max(1, (int) ($_GET['page_number'] ?? 1)));
$rowsPage = array_slice($rows, ($currentPage - 1) * $itemsPerPage, $itemsPerPage);
$startItem = $totalRows > 0 ? (($currentPage - 1) * $itemsPerPage) + 1 : 0;
$endItem = min($currentPage * $itemsPerPage, $totalRows);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vínculos pessoa-instituição</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Vínculos pessoa-instituição</span></div>
    </div>
    <div class="container-full card-page">

        <div class="card-title">
            <h1 class="title-page">Vínculos pessoa-instituição</h1>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <?php
                $listExportPage = 'partesinstituicoeslist';
                $listExportQuery = array_filter(['parte_id' => $parteId, 'instituicao_id' => $instituicaoId], static fn ($v) => $v !== '');
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                <button type="button" class="btn btn-primary height-md" data-nav-href="/frame.php?page=partesinstituicoescreate">Novo vínculo</button>
            </div>
        </div>

        <form method="get" id="form-filters" class="display-flex col col-full" style="width:100%" data-auto-submit-filters>
            <input type="hidden" name="page" value="partesinstituicoeslist">
            <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col">
                        <label for="parte_id">ID da pessoa (parte)</label>
                        <input type="text" class="height-md list-search-input" id="parte_id" name="parte_id" placeholder="Ex: PRT-..." value="<?php echo htmlspecialchars($parteId); ?>">
                    </div>
                    <div class="col">
                        <label for="instituicao_id">ID da instituição</label>
                        <input type="text" class="height-md list-search-input" id="instituicao_id" name="instituicao_id" placeholder="Ex: INS-..." value="<?php echo htmlspecialchars($instituicaoId); ?>">
                    </div>
                </div>
            </div>
        </form>

        <?php if ($parteId === '' && $instituicaoId === ''): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Informe o ID da pessoa ou da instituição; a lista atualiza ao alterar os campos.</span>
                </div>
            </div>
        <?php elseif (!empty($rowsPage)): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm border-bottom">
                <span class="minor-info"><?php echo $parteId !== '' ? 'Instituições vinculadas a ' . htmlspecialchars($parteNome) : 'Pessoas vinculadas a ' . htmlspecialchars($instituicaoNome); ?></span>
            </div>
            <?php foreach ($rowsPage as $r): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-md">
                        <?php if ($instituicaoId !== ''): ?>
                        <div class="col width-400">
                            <span class="minor-info">Pessoa</span>
                            <span class="major-info"><?php echo htmlspecialchars($r['parte_nome'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">CPF</span>
                            <span class="major-info"><?php echo htmlspecialchars($r['parte_cpf'] ?? '—'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($parteId !== ''): ?>
                        <div class="col width-400">
                            <span class="minor-info">Instituição</span>
                            <span class="major-info"><?php echo htmlspecialchars($r['instituicao_nome'] ?? $r['instituicao_sigla'] ?? '—'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="col width-300">
                            <span class="minor-info">Cargo/Função</span>
                            <span class="major-info"><?php echo htmlspecialchars($r['cargo_funcao'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">Data inclusão</span>
                            <span class="major-info"><?php echo htmlspecialchars($r['data_inclusao'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">Ativo</span>
                            <span class="major-info"><?php echo !empty($r['ativo']) ? 'Sim' : 'Não'; ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm">
                        <button type="button" class="btn btn-secondary-outline height-sm" data-nav-href="/frame.php?page=partesinstituicoesedit&id=<?php echo urlencode($r['id'] ?? ''); ?>">Editar</button>
                        <?php if ($parteId !== ''): ?>
                            <button type="button" class="btn btn-secondary-outline height-sm" data-nav-href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($r['parte_id'] ?? ''); ?>">Ver pessoa</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhum vínculo encontrado.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($parteId !== '' || $instituicaoId !== ''): ?>
            <?php
            $paginationQuery = ['page' => 'partesinstituicoeslist'];
            if ($parteId !== '') $paginationQuery['parte_id'] = $parteId;
            if ($instituicaoId !== '') $paginationQuery['instituicao_id'] = $instituicaoId;
            ?>
            <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
                <span class="pagination-info">
                    Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalRows; ?> <?php echo $totalRows === 1 ? 'registro' : 'registros'; ?>
                    (<?php echo $totalPages; ?> <?php echo $totalPages === 1 ? 'página' : 'páginas'; ?>, <?php echo $itemsPerPage; ?> itens por página)
                </span>
                <div class="pagination-controls">
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" href="/frame.php?<?php echo htmlspecialchars(http_build_query($paginationQuery + ['page_number' => max(1, $currentPage - 1)]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Página anterior"<?php echo $currentPage <= 1 ? ' aria-disabled="true"' : ''; ?>>‹</a>
                    <?php
                    $paginationStart = max(1, min($currentPage - 2, $totalPages - 4));
                    $paginationEnd = min($totalPages, $paginationStart + 4);
                    for ($pageButton = $paginationStart; $pageButton <= $paginationEnd; $pageButton++):
                    ?>
                        <?php if ($pageButton === $currentPage): ?>
                            <span class="pagination-number active" aria-current="page"><?php echo $pageButton; ?></span>
                        <?php else: ?>
                            <a class="pagination-number" href="/frame.php?<?php echo htmlspecialchars(http_build_query($paginationQuery + ['page_number' => $pageButton]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $pageButton; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" href="/frame.php?<?php echo htmlspecialchars(http_build_query($paginationQuery + ['page_number' => min($totalPages, $currentPage + 1)]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Próxima página"<?php echo $currentPage >= $totalPages ? ' aria-disabled="true"' : ''; ?>>›</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
