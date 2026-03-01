<?php
    // Define o cabeçalho HTTP para UTF-8 antes de qualquer saída
    header('Content-Type: text/html; charset=UTF-8');
    // Define a codificação interna para funções de string multibyte
    mb_internal_encoding('UTF-8');

    // Inclui o arquivo de conexão com o banco de dados
    include './../class/Connect.php';

    // Verifica se a conexão foi inicializada corretamente
    if (!isset($connection) || !($connection instanceof mysqli)) {
        die("Erro: conexão com o banco não foi inicializada.");
    }

    // Define o charset da conexão com o banco de dados para utf8mb4 (suporte completo a Unicode)
    if (!$connection->set_charset('utf8mb4')) {
        die("Erro ao definir charset: " . $connection->error);
    }

    // Nome do banco de dados (assumindo que já foi selecionado em Connect.php)
    // Se o nome do banco de dados já é passado no construtor de mysqli em Connect.php, esta linha é redundante.
    // Caso contrário, mantenha-a, mas o ideal é que Connect.php já selecione o banco.
    $databaseName = 'ios1_users';
    if (!mysqli_select_db($connection, $databaseName)) {
        die("Erro ao selecionar o banco de dados: " . mysqli_error($connection));
    }

    // Parâmetros de filtro e paginação
    $classeFilter = isset($_GET['classe']) ? trim($_GET['classe']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $allowedPerPage = [10, 25, 50, 100];
    $itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
    if (!in_array($itemsPerPage, $allowedPerPage, true)) {
        $itemsPerPage = 10;
    }
    $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

    $filters = [];
    if ($classeFilter !== '') {
        $filters[] = "Classe = '" . $connection->real_escape_string($classeFilter) . "'";
    }
    if ($statusFilter !== '') {
        $filters[] = "Status = '" . $connection->real_escape_string($statusFilter) . "'";
    }

    $whereClause = '';
    if (!empty($filters)) {
        $whereClause = 'WHERE ' . implode(' AND ', $filters);
    }

    $countSql = "SELECT COUNT(*) AS total FROM CartasExpedidas " . $whereClause;
    $countResult = $connection->query($countSql);
    if (!$countResult) {
        die("Erro na contagem de documentos: " . $connection->error);
    }
    $totalDocuments = (int) $countResult->fetch_assoc()['total'];
    $countResult->free();

    $totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
    if ($totalPages > 0 && $currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    if ($totalPages === 0) {
        $currentPage = 1;
    }

    $offset = ($currentPage - 1) * $itemsPerPage;

    $querySql = "SELECT * FROM CartasExpedidas " . $whereClause . " ORDER BY Data DESC LIMIT " . $itemsPerPage . " OFFSET " . $offset;
    $consulta = $connection->query($querySql);
    if (!$consulta) {
        die("Erro na consulta: " . $connection->error);
    }

    $rows = array();
    // Preenche o array $rows com os resultados da consulta principal
    while ($row = $consulta->fetch_assoc()) {
        $rows[] = $row;
    }
    // Libera o resultado da consulta principal
    $consulta->free();

    $displayedCount = count($rows);
    if ($displayedCount > 0) {
        $startItem = $offset + 1;
        $endItem = $offset + $displayedCount;
    } else {
        $startItem = 0;
        $endItem = 0;
    }

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

    // A variável $rows2 não é utilizada no HTML, então pode ser removida se não houver outro uso.
    // $rows2 = array();

    $statusOptions = [
        'aguardando assinatura',
        'aguardando envio',
        'aguardando juntada do termo',
        'aguardando cumprimento',
        'aguardando baixa',
    ];
    $statusCounts = array_fill_keys($statusOptions, 0);
    $statusCountFilters = [];
    if ($classeFilter !== '') {
        $statusCountFilters[] = "Classe = '" . $connection->real_escape_string($classeFilter) . "'";
    }
    $statusWhere = '';
    if (!empty($statusCountFilters)) {
        $statusWhere = 'WHERE ' . implode(' AND ', $statusCountFilters);
    }
    $statusCountSql = "SELECT Status, COUNT(*) AS total FROM CartasExpedidas " . $statusWhere . " GROUP BY Status";
    $statusCountResult = $connection->query($statusCountSql);
    if ($statusCountResult) {
        while ($statusRow = $statusCountResult->fetch_assoc()) {
            if (isset($statusCounts[$statusRow['Status']])) {
                $statusCounts[$statusRow['Status']] = (int) $statusRow['total'];
            }
        }
        $statusCountResult->free();
    }

    $classOptions = [
        'Carta de Ordem',
        'Carta Precatória',
        'Carta Rogatória',
    ];
    $classCounts = array_fill_keys($classOptions, 0);
    $classCountFilters = [];
    if ($statusFilter !== '') {
        $classCountFilters[] = "Status = '" . $connection->real_escape_string($statusFilter) . "'";
    }
    $classWhere = '';
    if (!empty($classCountFilters)) {
        $classWhere = 'WHERE ' . implode(' AND ', $classCountFilters);
    }
    $classCountSql = "SELECT Classe, COUNT(*) AS total FROM CartasExpedidas " . $classWhere . " GROUP BY Classe";
    $classCountResult = $connection->query($classCountSql);
    if ($classCountResult) {
        while ($classRow = $classCountResult->fetch_assoc()) {
            if (isset($classCounts[$classRow['Classe']])) {
                $classCounts[$classRow['Classe']] = (int) $classRow['total'];
            }
        }
        $classCountResult->free();
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta lang="pt-BR">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./../public/assets/css/shared/reset.css">
        <link rel="stylesheet" href="./../public/assets/css/shared/variables.css">
        <link rel="stylesheet" href="./../public/assets/css/shared/page.css">
        <link rel="stylesheet" href="./../public/assets/css/shared/form.css">
        <link rel="stylesheet" href="./../public/assets/css/shared/table.css">
        
    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Internal - Cartório - Cartas Expedidas -</span>
            <span class="specific">Lista de documentos</span>
        </div>
                <h1 class="title-page">Cartas Expedidas</h1>
                <p class="description-page">Controle de cartas precatórias, rogatórias e de ordem expedidas pelo cartório.</p>
                
    </div>

    <div class="card-page">
        <div class="line-filter">
            <div class="block-cell">
                <label for="filter-tipo">Filtrar por classe</label>
                <select class="width-md height-md" id="filter-classe">
                    <option value=""<?php echo $classeFilter === '' ? ' selected' : ''; ?>>Mostrar todas</option>
                    <option value="Carta de Ordem"<?php echo $classeFilter === 'Carta de Ordem' ? ' selected' : ''; ?>>
                        Carta de Ordem (<?php echo htmlspecialchars($classCounts['Carta de Ordem']); ?>)
                    </option>
                    <option value="Carta Precatória"<?php echo $classeFilter === 'Carta Precatória' ? ' selected' : ''; ?>>
                        Carta Precatória (<?php echo htmlspecialchars($classCounts['Carta Precatória']); ?>)
                    </option>
                    <option value="Carta Rogatória"<?php echo $classeFilter === 'Carta Rogatória' ? ' selected' : ''; ?>>
                        Carta Rogatória (<?php echo htmlspecialchars($classCounts['Carta Rogatória']); ?>)
                    </option>                  
                </select>
            </div>
            <div class="block-cell">
                <label for="filter-status">Filtrar por status</label>
                <select class="width-md height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <option value="aguardando assinatura"<?php echo $statusFilter === 'aguardando assinatura' ? ' selected' : ''; ?>>
                        Aguardando assinatura (<?php echo htmlspecialchars($statusCounts['aguardando assinatura']); ?>)
                    </option>
                    <option value="aguardando envio"<?php echo $statusFilter === 'aguardando envio' ? ' selected' : ''; ?>>
                        Aguardando envio (<?php echo htmlspecialchars($statusCounts['aguardando envio']); ?>)
                    </option>
                    <option value="aguardando juntada do termo"<?php echo $statusFilter === 'aguardando juntada do termo' ? ' selected' : ''; ?>>
                        Aguardando juntada do termo (<?php echo htmlspecialchars($statusCounts['aguardando juntada do termo']); ?>)
                    </option>
                    <option value="aguardando cumprimento"<?php echo $statusFilter === 'aguardando cumprimento' ? ' selected' : ''; ?>>
                        Aguardando cumprimento (<?php echo htmlspecialchars($statusCounts['aguardando cumprimento']); ?>)
                    </option>
                    <option value="aguardando baixa"<?php echo $statusFilter === 'aguardando baixa' ? ' selected' : ''; ?>>
                        Aguardando baixa (<?php echo htmlspecialchars($statusCounts['aguardando baixa']); ?>)
                    </option>
                </select>
            </div>
            <div class="block-cell">
                <label for="items-per-page">Pagin.</label>
                <select class="width-ssm height-md" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>
        <div class="line-buttons">
            <select class="width-sm height-md" id="export-type">
                <option value="" selected>Exportar lista</option>
                <option value="pdf">Gerar PDF</option>
                <option value="csv">Gerar CSV</option>
                <option value="print">Imprimir</option>
            </select>
            <button class="btn btn-primary">Novo cadastro</button>
        </div>

        <!-- Tabela -->
        <div class="line-table header">Lista de Documentos</div>
        <?php if (!empty($rows)): // Verifica se há dados para exibir ?>
            <?php foreach ($rows as $row):
                $processo = $row['Processo'];
            ?>

            <!-- register foreach -->
            <div class="line-table" id="<?php echo htmlspecialchars($row['IdCarta']); ?>">
                <div class="block-line">
                    <div class="block-cell process-select">
                        <label class="radio-custom">
                            <input type="radio" name="open-subtask" />
                            <span class="radio-mark"></span>
                        </label>                
                    </div>
                    <div class="block-cell process-info" title="clique para copiar">
                        <span class="minor-info"><?php echo htmlspecialchars($row['Classe']); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars($row['Processo']); ?></span>
                    </div>
                </div>
                <div class="block-cell comarca-info">
                    <span class="minor-info">Comarca de origem</span>
                    <span class="major-info"><?php echo htmlspecialchars($row['Comarca'] . ' - ' . $row['Estado']); ?></span>
                </div>
                <div class="block-cell detalhes-info">
                    <span class="minor-info"><?php echo htmlspecialchars($row['Finalidade']); ?></span>
                    <span class="major-info"><?php echo htmlspecialchars($row['Prazo'] . ' dias'); ?></span>
                </div>
                <div class="block-cell status-task">
                    <div class="width-md status <?php 
                    if ($row['Status'] == 'aguardando cumprimento' || $row['Status'] == 'aguardando juntada de termo') {
                        echo 'status-current';
                    } elseif ($row['Status'] == 'aguardando assinatura' || $row['Status'] == 'aguardando baixa' || $row['Status'] == 'aguardando devolução') {
                        echo 'status-pending';
                    } elseif ($row['Status'] == 'cancelada') {
                        echo 'status-cancelled';
                    } elseif ($row['Status'] == 'concluída') {
                        echo 'status-finished';
                    } ?>
                    "><?php echo htmlspecialchars($row['Status']); ?></div>
                </div>
            </div>

            <?php endforeach; ?>
        <?php else: // Caso não haja dados para exibir ?>
            <div class="line-table">
                <div class="block-line">
                    <div class="block-cell no-data">
                        <span class="major-info">Nenhum documento cadastrado.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Paginação -->
        <div class="line-pagination">
            <span class="pagination-info">
                Mostrando <?php echo htmlspecialchars($startItem); ?>-<?php echo htmlspecialchars($endItem); ?> de <?php echo htmlspecialchars($totalDocuments); ?> documentos
                (<?php echo htmlspecialchars($totalPages); ?> páginas · <?php echo htmlspecialchars($itemsPerPage); ?> itens por página)
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
                        <img src="./../public/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
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
                            <img src="./../public/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas">
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
                        <img src="./../public/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
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
        })();
    </script>

</body>
</html>
