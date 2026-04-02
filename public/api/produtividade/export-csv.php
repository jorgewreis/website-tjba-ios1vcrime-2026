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
    $result = $service->listLancamentosForExport($userId, $filters, 5000);
    $rows = $result['rows'] ?? [];

    $filename = 'produtividade-' . date('Ymd-His') . '.csv';
    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Não foi possível gerar o arquivo CSV.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, [
        'Data/hora',
        'Usuário',
        'Setor/Módulo',
        'Tipo documento',
        'Tipo ato',
        'Tipo lançamento',
        'Status',
        'Processo',
        'Classe',
        'Quantidade',
        'Pontos',
        'Observações',
    ], ';');

    foreach ($rows as $row) {
        fputcsv($output, [
            (string) ($row['data_hora_ato'] ?? ''),
            trim((string) ($row['usuario_nome'] ?? $row['usuario_id'] ?? '')),
            (string) ($row['sistema_modulo'] ?? ''),
            (string) ($row['tipo_documento'] ?? ''),
            (string) ($row['tipo_ato'] ?? ''),
            (string) ($row['tipo_lancamento'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['numero_processo'] ?? ''),
            (string) ($row['classe_processo'] ?? ''),
            (string) ($row['quantidade'] ?? ''),
            number_format((float) ($row['pontos_calculados'] ?? 0), 2, ',', '.'),
            (string) ($row['observacoes'] ?? ''),
        ], ';');
    }

    fclose($output);
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao exportar CSV: ' . $e->getMessage());
}

