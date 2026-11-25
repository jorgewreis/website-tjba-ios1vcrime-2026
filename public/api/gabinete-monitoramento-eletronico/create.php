<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/gabinete-monitoramento-eletronico/create.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/services/GabineteMonitoramentoEletronicoService.php';

ApiAccessGuard::requireAccess(['monitoramentogabinetecreate'], true);

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('gabinete-monitoramento-eletronico/create.php: falha de conexão.');
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

function assertCnjFormattedValueMon(array $input, string $key, string $fieldLabel): void
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
    assertCnjFormattedValueMon($parsed, 'numero_processo', 'Número do processo');

    $service = new GabineteMonitoramentoEletronicoService($connection);
    $newId = $service->create($parsed);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Monitoramento eletrônico: ' . get_class($exception));
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
}
