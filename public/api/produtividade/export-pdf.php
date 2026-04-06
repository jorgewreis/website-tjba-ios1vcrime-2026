<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once dirname(__DIR__, 3) . '/app/helpers/ListExportPresentation.php';
require_once dirname(__DIR__, 3) . '/app/helpers/ListExportFilterSummary.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = produtividadeCurrentUserId();

if ($userId === '') {
    produtividadeRespondError(401, 'Sessão expirada. Faça login novamente.');
    exit;
}

$formatarDataHoraProdutividade = static function (?string $mysql): string {
    $mysql = trim((string) $mysql);
    if ($mysql === '') {
        return '';
    }
    $t = strtotime($mysql);

    return $t !== false ? date('d/m/Y H:i', $t) : $mysql;
};

$filters = [
    'usuario_id' => trim((string) ($_GET['usuario_id'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? 'ativo')),
    'tipo_lancamento' => trim((string) ($_GET['tipo_lancamento'] ?? 'manual')),
    'data_inicio' => trim((string) ($_GET['data_inicio'] ?? '')),
    'data_fim' => trim((string) ($_GET['data_fim'] ?? '')),
];

try {
    $result = $service->listLancamentosForExport($userId, $filters, 2000);
    $rows = $result['rows'] ?? [];
    $sum = (float) ($result['sum_pontos'] ?? 0.0);
    $filterSummary = ListExportFilterSummary::forProdutividadeLancamentos($_GET, $sum);

    $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Lista de lançamentos de produtividade</title>';
    $html .= '<style>body{font-family:Arial,sans-serif;font-size:11px;color:#1a1a1a;}table{width:100%;border-collapse:collapse;margin-top:4px;}th,td{border:1px solid #ddd;padding:4px 6px;text-align:left;vertical-align:top;white-space:pre-line;word-break:break-word;}th{background:#f5f5f5;}</style>';
    $html .= '</head><body>';
    $html .= ListExportPresentation::htmlInstitutionalBlock();
    $html .= ListExportPresentation::htmlReportIntro('Lista de produtividade', $filterSummary, count($rows));
    $html .= '<table><thead><tr>';
    $html .= '<th>Data/hora</th><th>Processo</th><th>Observações</th><th>Usuário</th><th>Pontos</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($formatarDataHoraProdutividade((string) ($row['data_hora_ato'] ?? '')), ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars(trim((string) ($row['numero_processo'] ?? '')), ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars(trim((string) ($row['observacoes'] ?? '')), ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars(trim((string) ($row['usuario_nome'] ?? $row['usuario_id'] ?? '')), ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars(number_format((float) ($row['pontos_calculados'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></body></html>';

    $basePath = dirname(__DIR__, 3);
    $autoloadPath = $basePath . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    $filename = 'produtividade-' . date('Ymd-His') . '.pdf';
    if (class_exists('\\Dompdf\\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $dompdf->output();
        exit;
    }

    produtividadeRespondError(500, 'Gerador de PDF não encontrado. Instale Dompdf para habilitar a exportação.');
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao exportar PDF: ' . $e->getMessage());
}

