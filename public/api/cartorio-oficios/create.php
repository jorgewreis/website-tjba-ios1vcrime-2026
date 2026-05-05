<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
require_once $basePath . '/app/services/OficiosCartorioService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

function assertCnjFormattedValue(array $input, string $key, string $fieldLabel): void
{
    if (!array_key_exists($key, $input)) {
        return;
    }
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        return;
    }
    if (!CnjValidator::isFormatted($value)) {
        throw new InvalidArgumentException($fieldLabel . ' deve ser enviado no formato CNJ completo (0000000-00.0000.0.00.0000).');
    }
    if (!CnjValidator::isValid($value)) {
        throw new InvalidArgumentException($fieldLabel . ' inválido. Verifique o dígito verificador e tente novamente.');
    }
}

try {
    assertCnjFormattedValue($parsed, 'numero_processo', 'Número do processo');

    $parsed['criado_por'] = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

    $service = new OficiosCartorioService($connection);
    $newId = $service->create($parsed);
    $row = $service->find($newId);
    $numeroOficio = null;
    if (is_array($row)) {
        $seq = isset($row['numero_sequencial']) ? (int) $row['numero_sequencial'] : 0;
        $ano = isset($row['ano']) ? (int) $row['ano'] : 0;
        if ($seq > 0 && $ano > 0) {
            $numeroOficio = $seq . '/' . $ano;
        }
    }
    http_response_code(201);
    echo json_encode(['id' => $newId, 'numero_oficio' => $numeroOficio], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao registrar ofício: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
