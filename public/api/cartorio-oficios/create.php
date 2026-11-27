<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-oficios/create.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['oficioscartorioedit'], true);

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
    $created = $service->createWithNumber($parsed);
    http_response_code(201);
    echo json_encode($created, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao registrar ofício: ' . ApiErrorReporter::genericMessage($exception, 'public/api/cartorio-oficios/create.php')], JSON_UNESCAPED_UNICODE);
}
