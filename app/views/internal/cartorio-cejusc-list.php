<?php
declare(strict_types=1);
require_once __DIR__ . '/../../services/CejuscRegistrosService.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';
require_once __DIR__ . '/../../helpers/DeleteAuthorization.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';

$connection = Conection::getInstance()->getConnection();
$itemsPerPage = 15;
if (!in_array($itemsPerPage, [15], true)) $itemsPerPage = 15;
$currentPage = max(1, (int) ($_GET['page_number'] ?? 1));
$filters = [];
try {
    $result = (new CejuscRegistrosService($connection))->list($filters, $itemsPerPage, ($currentPage - 1) * $itemsPerPage);
} catch (Throwable $e) {
    $result = ['rows' => [], 'total' => 0];
}
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, is_array($result['rows'] ?? null) ? $result['rows'] : []);
$processoIdsCejusc = [];
foreach ($rows as $rowCejusc) {
    $processoIdCejusc = trim((string) ($rowCejusc['bloco_processo_id_resolvido'] ?? ''));
    if ($processoIdCejusc !== '') {
        $processoIdsCejusc[] = $processoIdCejusc;
    }
}
$cejuscVitimasPorProcesso = (new ProcessosPartesRepository($connection))->listNomesVitimasByProcessoIds($processoIdsCejusc);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
$podeRemoverRegistroCejusc = DeleteAuthorization::usuarioPodeExcluirComoServidorOuChefia(trim((string) ($_SESSION['id_usuario'] ?? '')));
$total = (int) ($result['total'] ?? 0);
$totalPages = max(1, (int) ceil($total / $itemsPerPage));
if ($totalPages > 0 && $currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $itemsPerPage;
$startItem = $total > 0 ? $offset + 1 : 0;
$endItem = $total > 0 ? min($offset + count($rows), $total) : 0;
$paginationButtons = [];
if ($totalPages > 0) {
    $paginationButtons = $totalPages <= 5 ? range(1, $totalPages) : array_slice(range(1, $totalPages), max(0, $currentPage - 3), 5);
    if ($totalPages > 5 && $currentPage <= 3) {
        $paginationButtons = array_merge(range(1, 4), [$totalPages]);
    }
}
$exportMonths = [];
$monthCursor = new DateTimeImmutable('first day of this month');
$monthNames = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
for ($i = 0; $i < 3; $i++) {
    $monthDate = $monthCursor->modify('-' . $i . ' months');
    $monthValue = $monthDate->format('Y-m');
    $exportMonths[] = [
        'value' => $monthValue,
        'label' => ($monthNames[(int) $monthDate->format('n')] ?? $monthDate->format('m')) . '/' . $monthDate->format('Y'),
        'href' => '/api/internal-list-export/pdf.php?' . http_build_query(['page' => 'cejusc', 'mes_envio' => $monthValue]),
    ];
}
function cejusc_url(array $extra = []): string { return '/frame.php?' . http_build_query(array_merge(['page' => 'cejusc'], $extra)); }
function cejusc_date(string $value): string { $t = strtotime($value); return $t === false ? $value : date('d/m/Y', $t); }
function cejusc_resumo_nomes(array $nomes, string $singularOutro = 'outro', string $pluralOutros = 'outros'): string
{
    $nomes = array_values(array_filter(array_map(static function ($nome): string {
        return trim((string) $nome);
    }, $nomes), static function (string $nome): bool {
        return $nome !== '';
    }));
    $total = count($nomes);
    if ($total === 0) return '';
    if ($total === 1) return $nomes[0];
    if ($total === 2) return $nomes[0] . ' e ' . $singularOutro;
    return $nomes[0] . ' e ' . $pluralOutros . ' (' . ($total - 1) . ')';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CEJUSC - Primeira Vara Crime de Ilhéus</title>
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
    <body class="page-internal">
        <div class="spacing-top-page"></div>

        <div class="header-page">
            <div class="location">
                <span class="all">Interno - Cartório - Cejusc -</span>
                <span class="specific">Lista</span>
            </div>
        </div>

        <main class="container-full card-page">
            <div class="card-title">
                <div class="display-flex col col-fit justify-start">
                    <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Cejusc</h1>
                    <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Controle dos encaminhamentos enviados ao Centro Judiciário de Solução de Conflitos.</p>
                </div>
                <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">
                    <select id="cejusc-export-pdf" class="list-export-select width-300 height-md white" aria-label="Gerar relatório CEJUSC em PDF">
                        <option value="" selected disabled>Gerar relatório PDF</option>
                        <?php foreach ($exportMonths as $exportMonth): ?>
                            <option value="<?php echo htmlspecialchars('pdf-' . $exportMonth['value'], ENT_QUOTES); ?>" data-export-href="<?php echo htmlspecialchars($exportMonth['href'], ENT_QUOTES); ?>">PDF - <?php echo htmlspecialchars($exportMonth['label'], ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button class="btn btn-primary width-300" type="button" onclick="location.href='/frame.php?page=cejusccreate'">Novo cadastro</button>
                </div>
            </div>



            <div class="display-flex col col-full register">
                <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom" style="background-color: var(--color-primary-bg-light);">
                    <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm"><span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;"><?php echo $total === 0 ? 'Nenhum registro encontrado' : htmlspecialchars((string) $total . ($total === 1 ? ' registro encontrado' : ' registros encontrados'), ENT_QUOTES); ?></span></div>
                </div>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $registroMetadata = RegistroMetadataHelper::fromListRow($row, 'cejusc', $registroListViewerContext, $connection);
                    $registroMetadata['show_delete'] = $podeRemoverRegistroCejusc;
                    $registroMetadata['delete_endpoint'] = '/api/cartorio-cejusc/delete.php';
                    $registroMetadata['delete_label'] = 'Arquivar';
                    $processoIdBlocoCejusc = trim((string) ($row['bloco_processo_id_resolvido'] ?? ''));
                    ?>
                    <?php
                    $historicoLinhasCejusc = is_array($registroMetadata['historico_linhas'] ?? null) ? $registroMetadata['historico_linhas'] : [];
                    $vitimaResumoCejusc = cejusc_resumo_nomes($cejuscVitimasPorProcesso[$processoIdBlocoCejusc] ?? [], 'outra', 'outras');
                    $dataEnvioCejuscRaw = trim((string) ($row['DataEnvioCejusc'] ?? $row['data_envio_cejusc'] ?? ''));
                    $dataEnvioCejuscTexto = $dataEnvioCejuscRaw !== '' ? cejusc_date($dataEnvioCejuscRaw) : 'Data de envio não informada';
                    ?>
                    <div class="display-flex col col-full register register-cejusc-list" data-registro-id="<?php echo htmlspecialchars((string) $row['id'], ENT_QUOTES); ?>"<?php echo $processoIdBlocoCejusc !== '' ? ' data-processo-id="' . htmlspecialchars($processoIdBlocoCejusc, ENT_QUOTES) . '"' : ''; ?> id="<?php echo htmlspecialchars((string) $row['id'], ENT_QUOTES); ?>">
                        <div class="register-line">
                            <div class="left">
                                <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>

                                <div class="container-processo container-sm cejusc-document-info">
                                    <div class="container-line">
                                        <div class="left">
                                            <div class="line-content box-number">
                                                <span class="content-number major-info" style="text-transform: uppercase;"><?php echo htmlspecialchars((string) $row['nome_parte'], ENT_QUOTES); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="container-line">
                                        <div class="line-content double-line">
                                            <?php if ($vitimaResumoCejusc !== ''): ?>
                                                <span class="content-assuntos">Vítima: <strong><?php echo htmlspecialchars($vitimaResumoCejusc, ENT_QUOTES); ?></strong></span>
                                            <?php endif; ?>
                                            <span class="content-classe">- <?php echo htmlspecialchars($dataEnvioCejuscTexto, ENT_QUOTES); ?> | Enviado ao CEJUSC</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="right">
                                <div class="container-processo container-sm cejusc-actions">
                                    <div class="container-line">
                                        <div class="left">
                                            <div class="line-cube box-open open cejusc-action-disabled" title="Editar" aria-label="Editar">
                                                <img class="cube-icon" src="/assets/icons/Pencil-1--Streamline-Ultimate.png" alt="">
                                            </div>
                                            <div class="line-content cejusc-action-disabled">
                                                <span class="content-classe">Editar</span>
                                            </div>
                                        </div>
                                        <div class="right">
                                            <div class="container-line cejusc-action-archive">
                                                <div class="line-content cejusc-action-disabled">
                                                    <span class="content-classe">Arquivar</span>
                                                </div>
                                                <div class="line-cube box-open open cejusc-action-disabled" title="Arquivar" aria-label="Arquivar">
                                                    <img class="cube-icon" src="/assets/icons/Archive--Streamline-Ultimate.png" alt="">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="container-line"></div>
                                </div>

                                <div class="container-historical container-sm">
                                    <div class="container-line">
                                        <div class="line-content double-line">
                                            <span class="content-classe">Histórico de movimentações</span>
                                            <?php if ($historicoLinhasCejusc === []): ?>
                                                <span class="content-assuntos over">Nenhuma movimentação registrada</span>
                                            <?php else: ?>
                                                <?php foreach ($historicoLinhasCejusc as $histCejusc):
                                                    if (!is_array($histCejusc)) {
                                                        continue;
                                                    }
                                                    $historicoDataCejusc = trim((string) ($histCejusc['data_hora'] ?? ''));
                                                    $historicoRotuloCejusc = trim((string) ($histCejusc['rotulo'] ?? ''));
                                                    $historicoAutorCejusc = trim((string) ($histCejusc['autor'] ?? ''));
                                                    $historicoClasseCejusc = trim((string) ($histCejusc['content_class'] ?? ''));
                                                    if ($historicoDataCejusc === '' || $historicoRotuloCejusc === '') {
                                                        continue;
                                                    }
                                                    $historicoTextoCejusc = '- ' . $historicoDataCejusc . ' | ' . $historicoRotuloCejusc;
                                                    if ($historicoAutorCejusc !== '') {
                                                        $historicoTextoCejusc .= ' por ' . $historicoAutorCejusc;
                                                    }
                                                ?>
                                                    <span class="content-assuntos<?php echo $historicoClasseCejusc !== '' ? ' ' . htmlspecialchars($historicoClasseCejusc, ENT_QUOTES) : ''; ?>"><?php echo htmlspecialchars($historicoTextoCejusc, ENT_QUOTES); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
                <span class="pagination-info">Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $total; ?> registros (<?php echo (int) $totalPages; ?> <?php echo $totalPages === 1 ? 'página' : 'páginas'; ?>, <?php echo (int) $itemsPerPage; ?> itens por página)</span>
                <?php if ($totalPages > 0): ?>
                    <div class="pagination-controls">
                        <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars(cejusc_url(['items_per_page' => $itemsPerPage, 'page_number' => max(1, $currentPage - 1)]), ENT_QUOTES); ?>" aria-label="Página anterior"><i class="fi fi-rr-angle-small-left"></i></a>
                        <?php foreach ($paginationButtons as $pageButton): ?>
                            <?php if ($pageButton === $currentPage): ?>
                                <span class="pagination-number active"><?php echo (int) $pageButton; ?></span>
                            <?php else: ?>
                                <a class="pagination-number" href="<?php echo htmlspecialchars(cejusc_url(['items_per_page' => $itemsPerPage, 'page_number' => $pageButton]), ENT_QUOTES); ?>"><?php echo (int) $pageButton; ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars(cejusc_url(['items_per_page' => $itemsPerPage, 'page_number' => min($totalPages, $currentPage + 1)]), ENT_QUOTES); ?>" aria-label="Próxima página"><i class="fi fi-rr-angle-small-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <div class="footer-page"><div class="location"><span class="all">Aplicativo web desenvolvido integralmente por </span><span class="specific">Jorge Reis Develop</span><span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span></div></div>
        <script>
            (function () {
                if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
                    App.RegistroMetadataList.bind({
                        deleteEndpoint: '/api/cartorio-cejusc/delete.php',
                        deleteFallback: {
                            type: 'success',
                            title: 'Sucesso',
                            message: 'Registro CEJUSC removido.'
                        }
                    });
                }
            })();
        </script>
    </body>
</html>
