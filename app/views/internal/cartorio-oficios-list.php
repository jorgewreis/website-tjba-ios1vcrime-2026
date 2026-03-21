<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/OficiosCartorioService.php';
require_once __DIR__ . '/../../repositories/OficiosCartorioSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'oficioscartorio'));
$statusFilter = trim($_GET['status'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$anoFilter = trim($_GET['ano'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
}

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $currentPage = max(1, (int) $pageNumberParam);
} else {
    $currentPage = 1;
}

$filters = [
    'status' => $statusFilter,
    'processo' => $processoFilter,
    'ano' => $anoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new OficiosCartorioService($connection);
    $listResult = $service->listOficios($filters, $itemsPerPage, $offset);
} catch (Throwable $exception) {
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

$documentsLabel = $totalDocuments === 1 ? 'ofício' : 'ofícios';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptionsUi = ['pendente', 'finalizada', 'cancelada'];
$statusCountsRaw = $service->getStatusCounts($filters);
$statusCounts = array_merge(array_fill_keys($statusOptionsUi, 0), $statusCountsRaw);

$subtarefaLabels = [
    OficiosCartorioSubtarefasRepository::TIPO_ENVIAR => 'Enviar ofício',
    OficiosCartorioSubtarefasRepository::TIPO_JUNTAR => 'Juntar termo de envio',
];
$buttonTitleByTipo = [
    OficiosCartorioSubtarefasRepository::TIPO_ENVIAR => 'Informar envio do ofício',
    OficiosCartorioSubtarefasRepository::TIPO_JUNTAR => 'Informar juntada do termo',
];

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
if ($processoFilter !== '') {
    $baseQueryParams['processo'] = $processoFilter;
}
if ($anoFilter !== '') {
    $baseQueryParams['ano'] = $anoFilter;
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

$anoAtual = (int) date('Y');

/**
 * @param array<string,mixed> $row
 */
function cartorio_oficios_label_cumprimento(array $row): string
{
    $forma = (string) ($row['FormaCumprimento'] ?? '');
    if ($forma === 'ccm') {
        return 'CCM';
    }
    $meio = (string) ($row['MeioEnvio'] ?? '');
    $map = [
        'email' => 'E-mail',
        'malote_digital' => 'Malote digital',
        'pessoalmente' => 'Pessoalmente',
    ];
    return 'Cartório — ' . ($map[$meio] ?? $meio);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Ofícios - Cartório - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
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
            <span class="all">Interno - Cartório - Ofícios -</span>
            <span class="specific">Lista e registro</span>
        </div>
        <h1 class="title-page">Ofícios</h1>
        <p class="description-page">Registro de ofícios expedidos pelo cartório, com cumprimento via CCM ou envio pelo próprio cartório.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Lista de ofícios</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
                <select class="width-300 height-md" id="export-type" disabled>
                    <option value="" selected>Exportar lista</option>
                    <option value="pdf">Gerar PDF</option>
                    <option value="csv">Gerar CSV</option>
                    <option value="print">Imprimir</option>
                </select>
            </div>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <h3 class="title-section" style="margin:0;font-size:1rem;font-weight:600;">Registrar ofício</h3>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-400 height-md" id="filter-processo" name="processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                </div>
                <div class="col">
                    <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                    <input type="text" class="width-600 height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
                </div>
                <div class="col">
                    <label for="oficio-tipo">Tipo de ofício <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-600 height-md" id="oficio-tipo" name="tipo_oficio">
                        <option value="">Selecione…</option>
                        <option value="Ofício requisitório">Ofício requisitório</option>
                        <option value="Ofício de comunicação">Ofício de comunicação</option>
                        <option value="Ofício de encaminhamento">Ofício de encaminhamento</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="col">
                    <label for="oficio-forma-cumprimento">Forma de cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="oficio-forma-cumprimento" name="forma_cumprimento">
                        <option value="">Selecione…</option>
                        <option value="ccm">por Oficial de Justiça (CCM)</option>
                        <option value="cartorio">pelo Cartório</option>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" id="oficio-meio-envio-row" style="gap:12px;">
                <div class="col">
                    <label for="oficio-meio-envio">Meio de envio</label>
                    <select class="width-400 height-md" id="oficio-meio-envio" name="meio_envio">
                        <option value="">Selecione…</option>
                        <option value="email">E-mail</option>
                        <option value="malote_digital">Malote digital</option>
                        <option value="pessoalmente">Pessoalmente</option>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-fit justify-start gap-y-md">
                <button type="button" class="btn btn-primary" id="btn-oficio-registrar">Registrar</button>
                <button type="button" class="btn btn-secondary-outline" id="btn-oficio-limpar">Limpar formulário</button>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                <div class="col">
                    <label for="filter-status">Filtrar por status</label>
                    <select class="width-500 height-md" id="filter-status">
                        <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                        <option value="finalizada"<?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['finalizada'] ?? 0); ?>)</option>
                        <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-ano">Ano do ofício</label>
                    <select class="width-300 height-md" id="filter-ano">
                        <option value=""<?php echo $anoFilter === '' ? ' selected' : ''; ?>>Todos</option>
                        <?php for ($y = $anoAtual; $y >= $anoAtual - 5; $y--): ?>
                        <option value="<?php echo $y; ?>"<?php echo $anoFilter === (string) $y ? ' selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="col">
                    <label for="items-per-page">Itens por página</label>
                    <select class="width-300 height-md" id="items-per-page">
                        <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                        <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                        <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                        <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $idOficio = (string) ($row['IdOficio'] ?? '');
                $numSeq = (int) ($row['NumeroSeq'] ?? 0);
                $ano = (int) ($row['Ano'] ?? 0);
                $nAno = $numSeq . '/' . $ano;
                $subtarefas = $row['Subtarefas'] ?? [];
                $allSt = $row['SubtarefasAll'] ?? [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $formaC = (string) ($row['FormaCumprimento'] ?? '');
                $tarefaConcluida = in_array($statusTarefa, ['finalizada'], true);
                $tarefaCancelada = ($statusTarefa === 'cancelada');
                $mostrarBarraStatus = ($statusTarefa === 'pendente' && $formaC === 'cartorio');
                $progressStatuses = ['pendente', 'pendente'];
                foreach ($allSt as $s) {
                    $o = (int) ($s['ordem'] ?? 0);
                    if ($o >= 1 && $o <= 2) {
                        $stSeg = (string) ($s['status'] ?? 'pendente');
                        $progressStatuses[$o - 1] = $stSeg === 'concluida' ? 'concluida' : ($stSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                    }
                }
                $currentSubtarefaTitle = '';
                foreach ($allSt as $s) {
                    if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                        $tipoS = (string) ($s['tipo'] ?? '');
                        $currentSubtarefaTitle = $subtarefaLabels[$tipoS] ?? $tipoS;
                        break;
                    }
                }
                if ($currentSubtarefaTitle === '' && $statusTarefa === 'pendente' && $formaC === 'cartorio') {
                    $currentSubtarefaTitle = $subtarefaLabels[OficiosCartorioSubtarefasRepository::TIPO_ENVIAR] ?? '';
                }
                $segmentosBarra = [];
                if ($mostrarBarraStatus) {
                    for ($i = 0; $i < 2; $i++) {
                        $statusSeg = $progressStatuses[$i] ?? 'pendente';
                        if ($statusSeg === 'concluida') {
                            $segClass = 'concluida';
                        } elseif ($statusSeg === 'em_andamento') {
                            $segClass = 'em_andamento';
                        } else {
                            $segClass = 'pendente';
                        }
                        $ordemSeg = $i + 1;
                        $titleSeg = $ordemSeg === 1 ? ($subtarefaLabels[OficiosCartorioSubtarefasRepository::TIPO_ENVIAR] ?? '') : ($subtarefaLabels[OficiosCartorioSubtarefasRepository::TIPO_JUNTAR] ?? '');
                        $segmentosBarra[] = ['class' => $segClass, 'title' => $titleSeg];
                    }
                }
                $classeNome = (string) ($row['ClasseNome'] ?? 'Classe não informada');
                $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                $expandir = !$tarefaConcluida && !$tarefaCancelada && $formaC === 'cartorio';
            ?>
            <div class="display-flex col col-full register border-bottom" data-oficio-id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col">
                            <?php if ($expandir): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" aria-expanded="false" />
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                            <?php else: ?>
                                <span class="button-table" style="visibility:hidden;" aria-hidden="true"><i class="fi fi-rr-angle-small-down"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">Ofício</span>
                            <span class="major-info"><?php echo htmlspecialchars($nAno, ENT_QUOTES); ?></span>
                        </div>
                        <div class="col width-500 col-process process-info" title="clique para copiar">
                            <span class="minor-info text-ellipsis" title="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars((string) ($row['Processo'] ?? '')); ?></span>
                        </div>
                        <div class="col width-400">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['TipoOficio'] ?? '')); ?></span>
                        </div>
                        <div class="col width-280">
                            <span class="minor-info">Cumprimento</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars(cartorio_oficios_label_cumprimento($row)); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="col button-table button-edit" data-oficio-id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-file-edit"></i>
                        </div>
                        <?php if ($tarefaCancelada): ?>
                        <div class="col">
                            <div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>
                        </div>
                        <?php elseif ($tarefaConcluida): ?>
                        <?php elseif ($mostrarBarraStatus): ?>
                        <div class="col status-task" title="<?php echo htmlspecialchars($currentSubtarefaTitle); ?>">
                            <div class="width-300 status status-pending status-with-progress">
                                <span class="status-task-label width-300">Pendente</span>
                                <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo (int) count(array_filter($progressStatuses, function ($x) { return $x === 'concluida'; })); ?>" aria-valuemin="0" aria-valuemax="2">
                                    <?php foreach ($segmentosBarra as $seg): ?>
                                    <div class="status-task-progress-segment <?php echo htmlspecialchars($seg['class']); ?>" title="<?php echo htmlspecialchars($seg['title']); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($expandir): ?>
                <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st):
                        $stId = (string) ($st['id'] ?? '');
                        $stTipo = (string) ($st['tipo'] ?? '');
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                    ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end align-center">
                            <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa-oficio" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($stLabel, ENT_QUOTES); ?>">
                                <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                        <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top">
                            <span class="minor-info">Nenhuma sub-tarefa pendente.</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhum ofício cadastrado.</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $prevPage;
                    $prevHref = '?' . http_build_query($prevParams);
                    $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $nextPage;
                    $nextHref = '?' . http_build_query($nextParams);
                    ?>
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>href="<?php echo htmlspecialchars($prevHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </a>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $page; ?></a>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <a class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></a>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $page; ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>href="<?php echo htmlspecialchars($nextHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script>
        (function () {
            const notify = (type, title, message) => {
                if (typeof showToast === 'function') {
                    showToast({ type, variant: 'full', title, message });
                }
            };
            const POST_RELOAD_TOAST_KEY = 'cartorio.postReloadToast';
            const queuePostReloadToast = (type, title, message) => {
                try {
                    const raw = sessionStorage.getItem(POST_RELOAD_TOAST_KEY);
                    const fila = raw ? JSON.parse(raw) : [];
                    const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
                    lista.push({ type, title, message });
                    sessionStorage.setItem(POST_RELOAD_TOAST_KEY, JSON.stringify(lista));
                } catch (e) {}
            };
            const flushPostReloadToast = () => {
                try {
                    const raw = sessionStorage.getItem(POST_RELOAD_TOAST_KEY);
                    if (!raw) return;
                    sessionStorage.removeItem(POST_RELOAD_TOAST_KEY);
                    const data = JSON.parse(raw);
                    const lista = Array.isArray(data) ? data : (data && data.message ? [data] : []);
                    lista.forEach(function (item) {
                        if (item && item.message) notify(item.type || 'info', item.title || 'Informação', item.message);
                    });
                } catch (e) {}
            };
            flushPostReloadToast();

            const formaSel = document.getElementById('oficio-forma-cumprimento');
            const meioRow = document.getElementById('oficio-meio-envio-row');
            function toggleMeioEnvio() {
                if (!meioRow || !formaSel) return;
                const v = formaSel.value;
                meioRow.style.display = (v === 'cartorio') ? '' : 'none';
            }
            formaSel?.addEventListener('change', toggleMeioEnvio);
            toggleMeioEnvio();

            document.getElementById('btn-oficio-limpar')?.addEventListener('click', function () {
                const p = document.getElementById('filter-processo');
                const t = document.getElementById('oficio-tipo');
                const m = document.getElementById('oficio-meio-envio');
                const fc = document.getElementById('oficio-forma-cumprimento');
                if (p) p.value = '';
                if (t) t.selectedIndex = 0;
                if (m) m.selectedIndex = 0;
                if (fc) fc.selectedIndex = 0;
                const hcc = document.getElementById('hidden-classe-codigo');
                const hcd = document.getElementById('hidden-classe-descricao');
                const cl = document.getElementById('filter-classe');
                if (hcc) hcc.value = '';
                if (hcd) hcd.value = '';
                if (cl) cl.value = '';
                toggleMeioEnvio();
            });

            document.getElementById('btn-oficio-registrar')?.addEventListener('click', function () {
                const proc = document.getElementById('filter-processo')?.value?.trim() || '';
                const tipo = document.getElementById('oficio-tipo')?.value || '';
                const forma = document.getElementById('oficio-forma-cumprimento')?.value || '';
                const meio = document.getElementById('oficio-meio-envio')?.value || '';
                const cc = document.getElementById('hidden-classe-codigo')?.value || '';
                const cd = document.getElementById('hidden-classe-descricao')?.value || '';
                if (!proc || !tipo || !forma) {
                    notify('error', 'Validação', 'Preencha processo, tipo e forma de cumprimento.');
                    return;
                }
                if (forma === 'cartorio' && !meio) {
                    notify('error', 'Validação', 'Selecione o meio de envio.');
                    return;
                }
                const body = {
                    numero_processo: proc,
                    tipo_oficio: tipo,
                    forma_cumprimento: forma,
                    meio_envio: forma === 'cartorio' ? meio : '',
                    classe_codigo: cc,
                    classe_descricao: cd
                };
                fetch('/api/cartorio-oficios/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.id) {
                        queuePostReloadToast('success', 'Sucesso', 'Ofício registrado com sucesso.');
                        window.location.reload();
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao registrar.');
                    }
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                });
            });

            const applyFilters = function () {
                const status = document.getElementById('filter-status')?.value ?? '';
                const processo = document.getElementById('search-input')?.value?.trim() ?? '';
                const ano = document.getElementById('filter-ano')?.value ?? '';
                const perPage = document.getElementById('items-per-page')?.value ?? '10';
                const params = new URLSearchParams(window.location.search);
                params.set('page', '<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>');
                params.set('page_number', '1');
                params.set('items_per_page', perPage);
                if (status) params.set('status', status); else params.delete('status');
                if (processo) params.set('processo', processo); else params.delete('processo');
                if (ano) params.set('ano', ano); else params.delete('ano');
                window.location.href = '?' + params.toString();
            };

            document.getElementById('filter-status')?.addEventListener('change', applyFilters);
            document.getElementById('filter-ano')?.addEventListener('change', applyFilters);
            document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
            document.getElementById('search-input')?.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') applyFilters();
            });

            document.querySelectorAll('.button-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.dataset.oficioId;
                    if (id) window.location.href = '/frame.php?page=oficioscartorioedit&id=' + encodeURIComponent(id);
                });
            });

            document.querySelectorAll('input[name="open-subtask"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const oid = this.value;
                    const targetId = 'subtask-' + oid;
                    document.querySelectorAll('.col.subtask').forEach(function (row) {
                        const isTarget = row.id === targetId;
                        row.classList.toggle('subtask-collapsed', !isTarget);
                        row.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });
                });
            });

            document.querySelectorAll('.btn-concluir-subtarefa-oficio').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const subtarefaId = this.getAttribute('data-subtarefa-id');
                    if (!subtarefaId) return;
                    this.disabled = true;
                    fetch('/api/cartorio-oficios/concluir-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ subtarefa_id: subtarefaId }),
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            queuePostReloadToast('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                            window.location.reload();
                        } else {
                            notify('error', 'Erro', data.error || 'Erro ao concluir.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        notify('error', 'Erro', 'Erro de conexão.');
                        btn.disabled = false;
                    });
                });
            });
        })();
    </script>
</body>
</html>
