<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/ReuManager.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/repositories/ProcessosPartesRepository.php';

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

$reus = [];
$nomesIncluidos = [];

try {
    $reuManager = new ReuManager($connection);
    $lista = $reuManager->listarReusPorProcesso($numeroFormatado);
    foreach ($lista as $row) {
        $id = $row['id_reu'] ?? '';
        $nome = trim($row['nome'] ?? '');
        $reus[] = ['id' => $id, 'nome' => $nome];
        if ($nome !== '') {
            $nomesIncluidos[strtolower($nome)] = true;
        }
    }

    $processosRepo = new ProcessosRepository($connection);
    $processoPartesRepo = new ProcessosPartesRepository($connection);
    $processo = $processosRepo->findByNumero($numeroFormatado);
    if ($processo !== null) {
        $partesProcesso = $processoPartesRepo->findByProcessoId((string) $processo['id']);
        $tiposPassivos = ['reu', 'indiciado', 'sentenciado', 'requerido'];
        foreach ($partesProcesso as $pp) {
            $tipo = trim((string) ($pp['tipo_parte'] ?? ''));
            $nomeCompleto = trim((string) ($pp['nome_completo'] ?? ''));
            if ($nomeCompleto === '') {
                continue;
            }
            if (in_array($tipo, $tiposPassivos, true)) {
                $key = strtolower($nomeCompleto);
                if (!isset($nomesIncluidos[$key])) {
                    $reus[] = ['id' => $pp['parte_id'] ?? '', 'nome' => $nomeCompleto];
                    $nomesIncluidos[$key] = true;
                }
            }
        }
    }

    echo json_encode(['reus' => $reus], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar réus.', 'reus' => []], JSON_UNESCAPED_UNICODE);
}
