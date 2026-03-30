<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/OficiosGabineteService.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'oficiosgabinete'));
$statusFilter = trim($_GET['status'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$anoFilter = trim($_GET['ano'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $currentPage = max(1, (int) $pageNumberParam);
} else {
    $currentPage = 1;
}

$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new OficiosGabineteService($connection);
    /** @var list<int> $anosComOficios */
    $anosComOficios = $service->listAnosComOficios();
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$anoAtual = (int) date('Y');
$anoFromGet = trim($_GET['ano'] ?? '');
if ($anoFromGet !== '' && ctype_digit($anoFromGet) && in_array((int) $anoFromGet, $anosComOficios, true)) {
    $anoFilter = $anoFromGet;
} elseif ($anosComOficios !== []) {
    $anoFilter = in_array($anoAtual, $anosComOficios, true) ? (string) $anoAtual : (string) $anosComOficios[0];
} else {
    $anoFilter = '';
}

$filters = [
    'status' => $statusFilter,
    'processo' => $processoFilter,
    'ano' => $anoFilter,
];

try {
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

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($processoFilter !== '') {
    $baseQueryParams['processo'] = $processoFilter;
} else {
    if ($statusFilter !== '') {
        $baseQueryParams['status'] = $statusFilter;
    }
    if ($anoFilter !== '') {
        $baseQueryParams['ano'] = $anoFilter;
    }
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

function gabinete_oficios_list_formatar_data_hora(?string $mysqlDt): string
{
    if ($mysqlDt === null || $mysqlDt === '') {
        return '—';
    }
    $t = strtotime($mysqlDt);

    return $t !== false ? date('d/m/Y H:i', $t) : htmlspecialchars($mysqlDt, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Ofícios - Gabinete</title>
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
            <span class="all">Interno - Gabinete - Ofícios -</span>
            <span class="specific">Lista e registro</span>
        </div>
        <h1 class="title-page">Ofícios (Gabinete)</h1>
        <p class="description-page">Registro de ofícios do gabinete (processo e tipo).</p>
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
                    <label for="filter-processo">Número do processo <span id="gab-processo-hint" class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                </div>
                <div class="col">
                    <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                    <input type="text" class="width-600 height-md" id="filter-classe" data-datajud-related="true" placeholder="Preenchido automaticamente pelo DataJud" readonly>
                </div>            
                <div class="col">
                    <label for="gab-oficio-tipo">Tipo de ofício <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-600 height-md" id="gab-oficio-tipo">
                        <option value="">Selecione…</option>
                        <optgroup label="Encaminhamento">
                        <option value="Encaminhando Informações">Encaminhando informações</option>
                        <option value="Encaminhando Mandado de Busca e Apreensao">Encaminhando Mandado de Busca e Apreensão </option>
                        <option value="Encaminhando Mandado de Prisao">Encaminhando Mandado de Prisão</option>
                        <option value="Encaminhando Pecas Processuais">Encaminhando Peças Processuais</option>
                        </optgroup>
                        <optgroup label="Solicitação">
                        <option value="Solicitando Informacoes">Solicitando informações</option>
                        <option value="Solicitando Pecas Processuais">Solicitando peças processuais</option>
                        <option value="Solicitando Providencias">Solicitando providências</option>
                        </optgroup>
                        <optgroup label="Administrativos">
                        <option value="Solicitando Afastamento" data-oficio-grupo="administrativo">Solicitando afastamento</option>
                        <option value="Solicitando Licença Prêmio" data-oficio-grupo="administrativo">Solicitando licença prêmio</option>
                        <option value="Solicitando Férias" data-oficio-grupo="administrativo">Solicitando férias</option>
                        <option value="Solicitando Reembolso" data-oficio-grupo="administrativo">Solicitando reembolso</option>
                        <option value="Outros" data-oficio-grupo="administrativo">Outros</option>
                        </optgroup>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-fit justify-start gap-y-md">
                <button type="button" class="btn btn-primary" id="btn-gab-oficio-registrar">Registrar</button>
                <button type="button" class="btn btn-secondary-outline" id="btn-gab-oficio-limpar">Limpar formulário</button>
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
                    <select class="width-300 height-md" id="filter-ano"<?php echo $anosComOficios === [] ? ' disabled' : ''; ?>>
                        <?php if ($anosComOficios === []): ?>
                        <option value="">—</option>
                        <?php else: ?>
                        <?php foreach ($anosComOficios as $y): ?>
                        <option value="<?php echo (int) $y; ?>"<?php echo $anoFilter === (string) $y ? ' selected' : ''; ?>><?php echo (int) $y; ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
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
                $st = (string) ($row['Status'] ?? '');
                $classeNome = (string) ($row['ClasseNome'] ?? 'Classe não informada');
                $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
            ?>
            <div class="display-flex col col-full register border-bottom" data-oficio-id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col width-200">
                            <span class="minor-info">Ofício</span>
                            <span class="major-info"><?php echo htmlspecialchars($nAno, ENT_QUOTES); ?></span>
                        </div>
                        <div class="col width-500 col-process process-info" title="clique para copiar">
                            <span class="minor-info text-ellipsis" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars((string) ($row['Processo'] ?? '')); ?></span>
                        </div>
                        <div class="col width-600">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['TipoOficio'] ?? '')); ?></span>
                        </div>
                        <div class="col width-500">
                            <?php
                            $nomeCriadorGab = trim((string) ($row['CriadoPorNome'] ?? ''));
                            $nomeCriadorGab = $nomeCriadorGab !== '' ? $nomeCriadorGab : '—';
                            ?>
                            <span class="minor-info"><?php echo htmlspecialchars(gabinete_oficios_list_formatar_data_hora($row['Data'] ?? null)); ?></span>
                            <span class="major-info text-ellipsis" title="<?php echo $nomeCriadorGab !== '—' ? htmlspecialchars($nomeCriadorGab, ENT_QUOTES) : ''; ?>"><?php echo htmlspecialchars($nomeCriadorGab); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-editar" data-oficio-id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-pen-square"></i>
                        </button>
                        <?php if ($st === 'cancelada'): ?>
                        <div class="col">
                            <div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
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
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $currentPage > 1 ? $currentPage - 1 : 1;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($prevParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($nextParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </button>
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

    <script src="/assets/js/modules/datajud/cnj.js"></script>
    <script src="/assets/js/modules/datajud/datajud-cycle-helper.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/datajud/datajud-cycle-helper.js') ?: time(); ?>"></script>
    <script src="/assets/js/modules/datajud/datajud-api.js"></script>
    <script>
        (function () {
            const notify = (type, title, message) => {
                if (typeof showToast === 'function') showToast({ type, variant: 'full', title, message });
            };
            const queuePostReloadToast = (type, title, message) => {
                try {
                    const key = 'cartorio.postReloadToast';
                    const raw = sessionStorage.getItem(key);
                    const fila = raw ? JSON.parse(raw) : [];
                    const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
                    lista.push({ type, title, message });
                    sessionStorage.setItem(key, JSON.stringify(lista));
                } catch (e) {}
            };
            const flushPostReloadToast = () => {
                try {
                    const raw = sessionStorage.getItem('cartorio.postReloadToast');
                    if (!raw) return;
                    sessionStorage.removeItem('cartorio.postReloadToast');
                    const data = JSON.parse(raw);
                    const lista = Array.isArray(data) ? data : (data && data.message ? [data] : []);
                    lista.forEach(function (item) {
                        if (item && item.message) notify(item.type || 'info', item.title || 'Informação', item.message);
                    });
                } catch (e) {}
            };
            flushPostReloadToast();

            const gabineteTipoSelect = document.getElementById('gab-oficio-tipo');
            const gabProcessoHint = document.getElementById('gab-processo-hint');
            const gabProcessoLabel = document.querySelector('label[for="filter-processo"]');

            function gabineteOficioEhAdministrativo() {
                const opt = gabineteTipoSelect?.selectedOptions?.[0];
                return opt && opt.getAttribute('data-oficio-grupo') === 'administrativo';
            }

            function atualizarRegraProcessoGabinete() {
                const admin = gabineteOficioEhAdministrativo();
                if (gabProcessoHint) {
                    gabProcessoHint.textContent = admin ? 'opcional' : '*obrigatório';
                    gabProcessoHint.className = admin ? 'label-hint-automatizado' : 'label-hint-obrigatorio';
                }
                if (gabProcessoLabel) {
                    gabProcessoLabel.title = admin
                        ? 'Para tipos administrativos o número do processo pode ficar em branco.'
                        : '';
                }
            }

            gabineteTipoSelect?.addEventListener('change', atualizarRegraProcessoGabinete);
            atualizarRegraProcessoGabinete();

            document.getElementById('btn-gab-oficio-limpar')?.addEventListener('click', function () {
                const p = document.getElementById('filter-processo');
                const t = document.getElementById('gab-oficio-tipo');
                const cl = document.getElementById('filter-classe');
                if (p) p.value = '';
                if (t) t.selectedIndex = 0;
                if (cl) cl.value = '';
                const hcc = document.getElementById('hidden-classe-codigo');
                const hcd = document.getElementById('hidden-classe-descricao');
                if (hcc) hcc.value = '';
                if (hcd) hcd.value = '';
                atualizarRegraProcessoGabinete();
            });

            document.getElementById('btn-gab-oficio-registrar')?.addEventListener('click', function () {
                const proc = document.getElementById('filter-processo')?.value?.trim() || '';
                const tipo = document.getElementById('gab-oficio-tipo')?.value || '';
                const cc = document.getElementById('hidden-classe-codigo')?.value || '';
                const cd = document.getElementById('hidden-classe-descricao')?.value || '';
                const admin = gabineteOficioEhAdministrativo();
                if (!tipo) {
                    notify('error', 'Validação', 'Selecione o tipo de ofício.');
                    return;
                }
                if (!admin && !proc) {
                    notify('error', 'Validação', 'Preencha o número do processo.');
                    return;
                }
                fetch('/api/gabinete-oficios/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        numero_processo: proc,
                        tipo_oficio: tipo,
                        classe_codigo: admin && !proc ? '' : cc,
                        classe_descricao: admin && !proc ? '' : cd
                    }),
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
                const processo = document.getElementById('search-input')?.value?.trim() ?? '';
                const perPage = document.getElementById('items-per-page')?.value ?? '25';
                const params = new URLSearchParams(window.location.search);
                params.set('page', '<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>');
                params.set('page_number', '1');
                params.set('items_per_page', perPage);
                if (processo) {
                    params.set('processo', processo);
                    params.delete('status');
                    params.delete('ano');
                } else {
                    params.delete('processo');
                    const status = document.getElementById('filter-status')?.value ?? '';
                    const ano = document.getElementById('filter-ano')?.value ?? '';
                    if (status) params.set('status', status); else params.delete('status');
                    if (ano) params.set('ano', ano); else params.delete('ano');
                }
                window.location.href = '?' + params.toString();
            };

            document.getElementById('filter-status')?.addEventListener('change', applyFilters);
            document.getElementById('filter-ano')?.addEventListener('change', applyFilters);
            document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
            document.getElementById('search-input')?.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') applyFilters();
            });

            document.querySelectorAll('.btn-editar[data-oficio-id]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.dataset.oficioId;
                    if (id) window.location.href = '/frame.php?page=oficiosgabineteedit&id=' + encodeURIComponent(id);
                });
            });
        })();
    </script>
</body>
</html>
