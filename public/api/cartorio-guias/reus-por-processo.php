<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/ReuManager.php';

$connection = Conection::getInstance()->getConnection();

$numero = '';
$input = file_get_contents('php://input');
if ($input !== false && $input !== '') {
    $parsed = json_decode($input, true);
    if (is_array($parsed)) {
        $numero = trim((string) ($parsed['numero'] ?? $parsed['numero_processo'] ?? ''));
    }
}
if ($numero === '' && isset($_GET['numero'])) {
    $numero = trim((string) $_GET['numero']);
}

$digits = preg_replace('/\D+/', '', $numero);
if (strlen($digits) !== 20) {
    echo json_encode(['reus' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$numeroFormatado = sprintf(
    '%s-%s.%s.%s.%s.%s',
    substr($digits, 0, 7),
    substr($digits, 7, 2),
    substr($digits, 9, 4),
    substr($digits, 13, 1),
    substr($digits, 14, 2),
    substr($digits, 16, 4)
);

try {
    $reuManager = new ReuManager($connection);
    $lista = $reuManager->listarReusPorProcesso($numeroFormatado);
    $reus = [];
    foreach ($lista as $row) {
        $reus[] = [
            'id' => $row['id_reu'] ?? '',
            'nome' => $row['nome'] ?? '',
        ];
    }
    echo json_encode(['reus' => $reus], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar réus.', 'reus' => []], JSON_UNESCAPED_UNICODE);
}
