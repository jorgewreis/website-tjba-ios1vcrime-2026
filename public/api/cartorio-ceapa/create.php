<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/CeapaService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}
$usuarioCadastroId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($usuarioCadastroId !== '') {
    $parsed['usuario_cadastro_id'] = $usuarioCadastroId;
}

/**
 * Preenche / mascara `numero_processo_origem` quando houver 20 dígitos (evita 400 se o cliente enviar só dígitos).
 */
function ceapaNormalizeNumeroProcessoOrigemInPayload(array &$parsed): void
{
    $raw = trim((string) ($parsed['numero_processo_origem'] ?? ''));
    if ($raw === '') {
        $raw = trim((string) ($parsed['processo'] ?? ''));
        if ($raw !== '') {
            $parsed['numero_processo_origem'] = $raw;
        }
    }
    if (!array_key_exists('numero_processo_origem', $parsed)) {
        return;
    }
    $value = trim((string) $parsed['numero_processo_origem']);
    if ($value === '') {
        return;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if (strlen($digits) !== 20) {
        return;
    }
    $parsed['numero_processo_origem'] = sprintf(
        '%s-%s.%s.%s.%s.%s',
        substr($digits, 0, 7),
        substr($digits, 7, 2),
        substr($digits, 9, 4),
        substr($digits, 13, 1),
        substr($digits, 14, 2),
        substr($digits, 16, 4)
    );
}

function assertCnjFormattedValueCeapa(array $input, string $key, string $fieldLabel): void
{
    if (!array_key_exists($key, $input)) {
        return;
    }
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        return;
    }
    if (!preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', $value)) {
        throw new InvalidArgumentException($fieldLabel . ' deve ser enviado no formato CNJ completo (0000000-00.0000.0.00.0000).');
    }
}

try {
    ceapaNormalizeNumeroProcessoOrigemInPayload($parsed);
    assertCnjFormattedValueCeapa($parsed, 'numero_processo_origem', 'Número do processo');

    $service = new CeapaService($connection);
    $newId = $service->create($parsed);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar cadastro CEAPA: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
