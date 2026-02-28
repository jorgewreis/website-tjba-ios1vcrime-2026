<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CartasExpedidasService.php';

$connection = Conection::getInstance()->getConnection();

$classeFilter = trim($_GET['classe'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
}

$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$filters = [
    'classe' => $classeFilter,
    'status' => $statusFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new CartasExpedidasService($connection);
    $listResult = $service->listCartas($filters, $itemsPerPage, $offset);
} catch (\Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$totalDocuments = (int) ($listResult['total'] ?? 0);

if ($totalDocuments === 0) {
    $totalPages = 0;
    $currentPage = 1;
} else {
    $totalPages = (int) ceil($totalDocuments / $itemsPerPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
}

$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$statusOptions = [
    'aguardando assinatura',
    'aguardando envio',
    'aguardando juntada do termo',
    'aguardando cumprimento',
];
$classOptions = [
    'Carta de Ordem',
    'Carta Precatória',
    'Carta Rogatória',
];

$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$classCounts = array_merge(array_fill_keys($classOptions, 0), $service->getClasseCounts($filters));

$baseQueryParams = [];
if ($classeFilter !== '') {
    $baseQueryParams['classe'] = $classeFilter;
}
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

$paginationButtons = [];
$paginationEllipsis = false;
$trailingPages = [];
if ($totalPages > 0) {
    if ($totalPages <= 5) {
        $paginationButtons = range(1, $totalPages);
    } else {
        $windowStart = max(1, min($currentPage - 2, $totalPages - 4));
        $windowEnd = min($totalPages, $windowStart + 4);
        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $paginationButtons[] = $page;
        }
        if ($windowEnd < $totalPages) {
            $paginationEllipsis = true;
            $extraStart = max($windowEnd + 1, $totalPages - 1);
            for ($page = $extraStart; $page <= $totalPages; $page++) {
                $trailingPages[] = $page;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/variables.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
                
        <!-- IMPORT - FLATICON -->
        <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css'>
        
    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <!-- Mapa de navegação do site -->
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Expedidas -</span>
            <span class="specific">Lista de documentos</span>
        </div>

        <!-- Título e descrição da página -->
        <h1 class="title-page">Cartas Expedidas</h1>
        <p class="description-page">Controle de cartas precatórias, rogatórias e de ordem expedidas pelo cartório.</p>
         
        <!-- Badges de arquivos para download -->
        <a href="/documents/pdf/PJBA - 1VCR - POP - Expedição e Envio de Cartas Precatórias.pdf" target="_blank" rel="noopener noreferrer">
            <div class="badge file">
                <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
                <span>Passo a passo - Expedição de Cartas Precatórias</span>
            </div>
        </a>
    </div>

    <div class="card-page">

        <!-- Filtros -->
        <div class="row filter">
            <div class="col filter">
                <label for="filter-tipo">Filtrar por classe</label>
                <select class="width-md height-md" id="filter-classe">
                    <option value=""<?php echo $classeFilter === '' ? ' selected' : ''; ?>>Mostrar todas</option>
                    <option value="Carta de Ordem"<?php echo $classeFilter === 'Carta de Ordem' ? ' selected' : ''; ?>>
                        Carta de Ordem (<?php echo htmlspecialchars((string) $classCounts['Carta de Ordem']); ?>)
                    </option>
                    <option value="Carta Precatória"<?php echo $classeFilter === 'Carta Precatória' ? ' selected' : ''; ?>>
                        Carta Precatória (<?php echo htmlspecialchars((string) $classCounts['Carta Precatória']); ?>)
                    </option>
                    <option value="Carta Rogatória"<?php echo $classeFilter === 'Carta Rogatória' ? ' selected' : ''; ?>>
                        Carta Rogatória (<?php echo htmlspecialchars((string) $classCounts['Carta Rogatória']); ?>)
                    </option>                  
                </select>
            </div>
            <div class="col filter">
                <label for="filter-status">Filtrar por status</label>
                <select class="width-md height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <option value="aguardando assinatura"<?php echo $statusFilter === 'aguardando assinatura' ? ' selected' : ''; ?>>
                        Aguardando assinatura (<?php echo htmlspecialchars((string) $statusCounts['aguardando assinatura']); ?>)
                    </option>
                    <option value="aguardando envio"<?php echo $statusFilter === 'aguardando envio' ? ' selected' : ''; ?>>
                        Aguardando envio (<?php echo htmlspecialchars((string) $statusCounts['aguardando envio']); ?>)
                    </option>
                    <option value="aguardando juntada do termo"<?php echo $statusFilter === 'aguardando juntada de termo' ? ' selected' : ''; ?>>
                        Aguardando juntada de termo (<?php echo htmlspecialchars((string) $statusCounts['aguardando juntada de termo']); ?>)
                    </option>
                    <option value="aguardando cumprimento"<?php echo $statusFilter === 'aguardando cumprimento' ? ' selected' : ''; ?>>
                        Aguardando cumprimento (<?php echo htmlspecialchars((string) $statusCounts['aguardando cumprimento']); ?>)
                    </option>
                </select>
            </div>
            <div class="col filter">
                <label for="items-per-page">Itens por página</label>
                <select class="width-ssm height-md" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>

        <!-- Ações -->
        <div class="row buttons">
            <select class="width-sm height-md" id="export-type">
                <option value="" selected>Exportar lista</option>
                <option value="pdf">Gerar PDF</option>
                <option value="csv">Gerar CSV</option>
                <option value="print">Imprimir</option>
            </select>
            <button class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
        </div>

        <!-- Tabela -->
        <div class="row table header">Lista de Documentos</div>
        <?php if (!empty($rows)): // Verifica se há dados para exibir ?>
            <?php foreach ($rows as $row):
                $processo = $row['Processo'];
            ?>

            <!-- register foreach -->
            <div class="row table" id="<?php echo htmlspecialchars($row['IdCarta']); ?>">
                <div class="row">
                    <div class="col">
                        <label class="button-table" title="Expandir detalhes">
                            <input type="radio" name="open-subtask" />
                            <i class="fi fi-rr-angle-small-down"></i>
                        </label>                
                    </div>
                    <div class="col filter process-info" title="clique para copiar">
                        <?php
                            $classeNome = $row['ClasseNome'] ?? 'Classe não informada';
                            $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                        ?>
                        <span class="minor-info" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars($row['Processo']); ?></span>
                    </div>
                </div>
                <div class="col filter comarca-info">
                    <span class="minor-info">Comarca de origem</span>
                    <span class="major-info"><?php echo htmlspecialchars($row['Comarca'] . ' - ' . $row['Estado']); ?></span>
                </div>
                <div class="col filter detalhes-info">
                    <span class="minor-info"><?php echo htmlspecialchars($row['Finalidade']); ?></span>
                    <span class="major-info"><?php echo htmlspecialchars($row['Prazo'] . ' dias'); ?></span>
                </div>
                <div class="row">
                    <div class="col button-table" title="Editar">
                        <i class="fi fi-rr-file-edit"></i>
                    </div>
                    <div class="col status-task" title="<?php echo $row['Status'] ?>">
                        <?php 
                        if ($row['Status'] == 'aguardando cumprimento') {
                            echo '<div class="width-sm status status-current">Em andamento</div>';
                        } elseif ($row['Status'] == 'aguardando assinatura' || $row['Status'] == 'aguardando envio' || $row['Status'] == 'aguardando juntada de termo') {
                            echo '<div class="width-sm status status-pending">Pendente</div>';
                        } elseif ($row['Status'] == 'cancelada') {
                            echo '<div class="width-sm status status-cancelled">Cancelada</div>';
                        } elseif ($row['Status'] == 'concluída') {
                            echo '<div class="width-sm status status-finished">Concluída</div>';
                        } else {
                            echo '<div class="width-sm status status-pending">Pendente</div>';
                        }
                    ?>  
                    </div>                
                </div>
            </div>

            <?php endforeach; ?>
        <?php else: // Caso não haja dados para exibir ?>
            <div class="row table">
                <div class="col">
                    <div class="col filter no-data">
                        <span class="major-info">Nenhum documento cadastrado.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Paginação -->
        <div class="row pagination">
            <span class="pagination-info">
                Mostrando <?php echo htmlspecialchars((string) $startItem); ?>-<?php echo htmlspecialchars((string) $endItem); ?> de <?php echo htmlspecialchars((string) $totalDocuments); ?> documentos
                (<?php echo htmlspecialchars((string) $totalPages); ?> páginas  <?php echo htmlspecialchars((string) $itemsPerPage); ?> itens por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                        $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                        $prevParams = $baseQueryParams;
                        $prevParams['page'] = $prevPage;
                        $prevHref = '?' . http_build_query($prevParams);
                        $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                        $nextParams = $baseQueryParams;
                        $nextParams['page'] = $nextPage;
                        $nextHref = '?' . http_build_query($nextParams);
                    ?>
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>"
                       <?php if ($currentPage > 1): ?>href="<?php echo htmlspecialchars($prevHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </a>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php
                            $pageParams = $baseQueryParams;
                            $pageParams['page'] = $page;
                        ?>
                        <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>">
                            <?php echo $page; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <a class="pagination-symbol pagination-ellipsis" aria-hidden="true">
                            <img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas">
                        </a>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php
                                $pageParams = $baseQueryParams;
                                $pageParams['page'] = $page;
                            ?>
                            <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>">
                                <?php echo $page; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>"
                       <?php if ($currentPage < $totalPages): ?>href="<?php echo htmlspecialchars($nextHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </a>
                </div>
            <?php endif; ?>
        </div> 
    </div>

    <!-- Develop Info -->
    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
        (function () {
            const classeSelect = document.getElementById('filter-classe');
            const statusSelect = document.getElementById('filter-status');
            const itemsSelect = document.getElementById('items-per-page');
            const novoCadastroBtn = document.getElementById('novo-cadastro-btn');
            if (!classeSelect || !statusSelect || !itemsSelect) {
                return;
            }

            const applyFilters = function () {
                const url = new URL(window.location.href);
                const params = url.searchParams;
                const setFilter = function (key, value) {
                    if (value.trim() === '') {
                        params.delete(key);
                    } else {
                        params.set(key, value);
                    }
                };

                setFilter('classe', classeSelect.value);
                setFilter('status', statusSelect.value);
                params.set('items_per_page', itemsSelect.value);
                params.set('page', '1');

                window.location.href = url.toString();
            };

            classeSelect.addEventListener('change', applyFilters);
            statusSelect.addEventListener('change', applyFilters);
            itemsSelect.addEventListener('change', applyFilters);

            if (novoCadastroBtn) {
                novoCadastroBtn.addEventListener('click', function () {
                    window.location.href = '/frame.php?page=cartasexpedidascreate';
                });
            }

            // Copiar número do processo ao clicar
            document.querySelectorAll('.process-info .major-info').forEach(function(el) {
                el.addEventListener('click', function() {
                    const text = el.textContent.trim();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text);
                    } else {
                        // fallback para browsers antigos
                        const tempInput = document.createElement('input');
                        tempInput.value = text;
                        document.body.appendChild(tempInput);
                        tempInput.select();
                        document.execCommand('copy');
                        document.body.removeChild(tempInput);
                    }
                });
            });
        })();
    </script>

</body>
</html>
