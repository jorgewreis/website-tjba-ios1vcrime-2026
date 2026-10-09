<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/ParteDocumentoHelper.php';
require_once __DIR__ . '/../../services/PessoasService.php';
require_once __DIR__ . '/../../repositories/PartesTelefonesRepository.php';
require_once __DIR__ . '/../../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../../../config/security.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'consultapartes'));
$nomeFilter = trim($_GET['nome'] ?? '');
$cpfFilter = trim($_GET['cpf'] ?? '');
$instituicaoIdFilter = trim($_GET['instituicao_id'] ?? '');
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : (isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1);

$filtrou = ($nomeFilter !== '' || $cpfFilter !== '' || $instituicaoIdFilter !== '');

$rows = [];
$totalDocuments = 0;
$totalPages = 0;
$startItem = 0;
$endItem = 0;

if ($filtrou) {
    $filters = ['nome' => $nomeFilter, 'cpf' => $cpfFilter, 'instituicao_id' => $instituicaoIdFilter];
    $offset = ($currentPage - 1) * $itemsPerPage;

    try {
        $service = new PessoasService($connection);
        $listResult = $service->list($filters, $itemsPerPage, $offset);
        $rows = $listResult['rows'] ?? [];
        $totalDocuments = (int) ($listResult['total'] ?? 0);

        $telefonesRepo = new PartesTelefonesRepository($connection);
        $vinculosInstRepo = new PartesInstituicoesRepository($connection);
        foreach ($rows as $i => $row) {
            $parteId = (string) ($row['id'] ?? $row['ID'] ?? '');
            if ($parteId !== '') {
                $rows[$i]['telefones'] = $telefonesRepo->findByParteId($parteId);
                $vinculos = $vinculosInstRepo->findByParteId($parteId, true);
                $rows[$i]['instituicoes'] = array_map(function (array $v): array {
                    return [
                        'nome' => $v['instituicao_nome'] ?? '',
                        'sigla' => $v['instituicao_sigla'] ?? '',
                        'cargo_funcao' => $v['cargo_funcao'] ?? '',
                    ];
                }, $vinculos);
            } else {
                $rows[$i]['telefones'] = [];
                $rows[$i]['instituicoes'] = [];
            }
        }
    } catch (Throwable $e) {
        die('Erro ao carregar os dados: ' . $e->getMessage());
    }

    $totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $itemsPerPage;
    $displayedCount = count($rows);
    $startItem = $displayedCount > 0 ? $offset + 1 : 0;
    $endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
}

$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($nomeFilter !== '') $baseQueryParams['nome'] = $nomeFilter;
if ($cpfFilter !== '') $baseQueryParams['cpf'] = $cpfFilter;
if ($instituicaoIdFilter !== '') $baseQueryParams['instituicao_id'] = $instituicaoIdFilter;

$paginationButtons = [];
if ($totalPages > 0) {
    $paginationButtons = $totalPages <= 5 ? range(1, $totalPages) : array_slice(range(1, $totalPages), max(0, $currentPage - 3), 5);
    if ($totalPages > 5 && $currentPage <= 3) {
        $paginationButtons = array_merge(range(1, 4), [$totalPages]);
    }
}

$documentsLabel = $totalDocuments === 1 ? 'registro' : 'registros';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Consulta Partes - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <style>
            .result-nome {
                width: 420px;
            }
                .result-nome .major-info {
                    display: inline-block;
                    max-width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

            .result-tel {
                width: 220px;
            }
                .result-telefone .major-info {
                    display: inline-block;
                    max-width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

            .result-instituicoes {
                max-width: 420px;
            }
                .result-instituicoes .major-info {
                    display: inline-block;
                    max-width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
        </style>
        <script src="/assets/js/shared/cpf.js"></script>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas -</span>
            <span class="specific">Consulta Partes</span>
        </div>
        <h1 class="title-page">Consulta Partes</h1>
        <p class="description-page">Pesquisa por pessoa: nome ou CPF. Informe ao menos um critério e clique em Consultar.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Lista de partes</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <button type="button" class="btn btn-primary height-md" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <form method="get" id="form-filters" class="display-flex col col-full" style="width:100%" data-auto-submit-filters>
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col">
                        <label for="filter-nome">Nome</label>
                        <input type="text" class="width-600 height-md" id="filter-nome" name="nome" placeholder="Digite o nome completo" value="<?php echo htmlspecialchars($nomeFilter); ?>">
                    </div>
                    <div class="col">
                        <label for="filter-cpf">CPF (documento)</label>
                        <input type="text" class="width-400 height-md" id="filter-cpf" name="cpf" placeholder="000.000.000-00" value="<?php echo htmlspecialchars($cpfFilter); ?>">
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-end">
                    <?php
                    $listExportPage = $routePage;
                    $listExportQuery = $baseQueryParams;
                    include __DIR__ . '/_list_export_buttons.php';
                    ?>

                </div>
            </div>
        </form>

        <?php if (!$filtrou): ?>
        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Informe ao menos um critério (nome ou CPF); a lista atualiza ao digitar (com pausa).</span>
            </div>
        </div>

        <?php elseif (!empty($rows)): ?>
            <?php foreach ($rows as $row): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm">
                        <div class="col result-nome">
                            <span class="minor-info">Nome</span>
                            <span class="major-info"><?php
                                $cargosExib = [];
                                foreach ($row['instituicoes'] ?? [] as $instituicaoRow) {
                                    $cargo = trim((string) ($instituicaoRow['cargo_funcao'] ?? ''));
                                    if ($cargo !== '' && !in_array($cargo, $cargosExib, true)) {
                                        $cargosExib[] = $cargo;
                                    }
                                }
                                $cargoStr = implode(' · ', $cargosExib);
                                $docsExib = [];
                                foreach ($row['documentos'] ?? [] as $docRow) {
                                    $tDoc = ParteDocumentoHelper::normalizarTipo((string) ($docRow['tipo'] ?? ''));
                                    $nDoc = ParteDocumentoHelper::formatarExibicao($tDoc, (string) ($docRow['numero'] ?? ''));
                                    if ($tDoc !== '' && $nDoc !== '') {
                                        $docsExib[] = $tDoc . ': ' . $nDoc;
                                    }
                                }
                                if ($docsExib === []) {
                                    $cpfCell = $row['cpf'] ?? '';
                                    if ($cpfCell !== '') {
                                        $docsExib[] = 'CPF: ' . (strlen($cpfCell) === 11 ? Security::formatCPF($cpfCell) : $cpfCell);
                                    }
                                }
                                $docsStr = implode(' · ', $docsExib);
                                $nomeExib = (string) ($row['nome_completo'] ?? '');
                                if ($cargoStr !== '') {
                                    $nomeExib = $cargoStr . ' ' . $nomeExib;
                                }
                                if ($docsStr !== '') {
                                    $nomeExib .= ' (' . $docsStr . ')';
                                }
                                echo htmlspecialchars($nomeExib);
                            ?></span>
                        </div>
                        <div class="col result-tel">
                            <span class="minor-info">Contatos</span>
                            <span class="major-info">
                                <?php
                                $telefones = $row['telefones'] ?? [];
                                if (empty($telefones)) {
                                    echo '-';
                                } else {
                                    $partesTel = array_map(function ($t) {
                                        $tipo = trim($t['tipo'] ?? '');
                                        $numero = trim($t['numero'] ?? '');
                                        return $tipo !== '' ? htmlspecialchars($tipo) . ': ' . htmlspecialchars($numero) : htmlspecialchars($numero);
                                    }, $telefones);
                                    echo implode(' · ', $partesTel);
                                }
                                ?>
                            </span>
                        </div>
                        <div class="col result-instituicoes">
                            <span class="minor-info">Instituições vinculadas</span>
                            <span class="major-info">
                                <?php
                                $instituicoes = $row['instituicoes'] ?? [];
                                if (empty($instituicoes)) {
                                    echo '-';
                                } else {
                                    $partesInst = array_map(function ($i) {
                                        $n = htmlspecialchars(trim($i['nome'] ?? ''));                                        return $n;
                                    }, $instituicoes);
                                    echo implode(' · ', $partesInst);
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end">
                        <div class="col part-edit">
                            <button type="button" class="btn btn-editar" title="Editar" data-edit-id="<?php echo htmlspecialchars($row['id'] ?? ''); ?>"><i class="fi fi-rr-pen-square"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
                <div class="display-flex col col-full register border-bottom">
                    <div class="display-flex row row-full padding-x-sm padding-y-sm">
                        <span class="major-info">Nenhuma parte encontrada para os critérios informados.</span>
                    </div>
                </div>
            <?php endif; ?>

        <?php if ($filtrou): ?>
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                <?php if ($totalPages > 0): ?>(<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)<?php endif; ?>
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevParams = $baseQueryParams; $prevParams['page_number'] = max(1, $currentPage - 1);
                    $nextParams = $baseQueryParams; $nextParams['page_number'] = min($totalPages, $currentPage + 1);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($prevParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>‹</button>
                    <?php foreach ($paginationButtons as $p): $qp = $baseQueryParams; $qp['page_number'] = $p; ?>
                        <button type="button" class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" <?php if ($p !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($qp), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $p; ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($nextParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>›</button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
    (function() {
        var filterNome = document.getElementById('filter-nome');
        if (filterNome && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            filterNome.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 255);
            });
        }
        if (document.getElementById('filter-cpf') && typeof App !== 'undefined' && App.CPF && App.CPF.init) {
            App.CPF.init('#filter-cpf');
        }
        document.getElementById('novo-cadastro-btn').addEventListener('click', function() {
            window.location.href = '/frame.php?page=consultapartescreate';
        });
        document.querySelectorAll('button[data-edit-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-edit-id');
                if (id) window.location.href = '/frame.php?page=consultapartesedit&id=' + encodeURIComponent(id);
            });
        });
    })();
    </script>
</body>
</html>
