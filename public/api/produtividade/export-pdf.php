<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = produtividadeCurrentUserId();

if ($userId === '') {
    produtividadeRespondError(401, 'Sessão expirada. Faça login novamente.');
    exit;
}

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

    $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Produtividade</title>';
    $html .= '<style>body{font-family:Arial,sans-serif;font-size:11px;color:#1a1a1a;}h1{font-size:18px;margin:0 0 4px;}p{margin:0 0 8px;}table{width:100%;border-collapse:collapse;margin-top:8px;}th,td{border:1px solid #ddd;padding:4px 6px;text-align:left;vertical-align:top;}th{background:#f5f5f5;} .meta{font-size:10px;color:#555;margin-bottom:4px;}</style>';
    $html .= '</head><body>';
    $html .= '<h1>Relatório de produtividade</h1>';
    $html .= '<p class="meta">Gerado em ' . htmlspecialchars(date('d/m/Y H:i:s'), ENT_QUOTES) . ' | Total de registros: ' . count($rows) . ' | Pontos: ' . number_format($sum, 2, ',', '.') . '</p>';
    $html .= '<table><thead><tr>';
    $html .= '<th>Data/hora</th><th>Usuário</th><th>Módulo</th><th>Documento</th><th>Ato</th><th>Tipo</th><th>Status</th><th>Processo</th><th>Qtd</th><th>Pontos</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars((string) ($row['data_hora_ato'] ?? ''), ENT_QUOTES) . '</td>';
        $html .= '<td>' . htmlspecialchars(trim((string) ($row['usuario_nome'] ?? $row['usuario_id'] ?? '')), ENT_QUOTES) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($row['sistema_modulo'] ?? ''), ENT_QUOTES) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($row['tipo_documento'] ?? ''), ENT_QUOTES) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($row['tipo_ato'] ?? ''), ENT_QUOTES) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($row['tipo_lancamento'] ?? ''), ENT_QUOTES) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($row['numero_processo'] ?? ''), ENT_QUOTES) . '</td>';
        $html .= '<td>' . (int) ($row['quantidade'] ?? 0) . '</td>';
        $html .= '<td>' . number_format((float) ($row['pontos_calculados'] ?? 0), 2, ',', '.') . '</td>';
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

