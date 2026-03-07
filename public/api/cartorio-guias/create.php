<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GuiasService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

// #region agent log
$logPath = dirname(__DIR__, 3) . '/debug-64730a.log';
$logEntry = json_encode([
    'sessionId' => '64730a',
    'location' => 'create.php:parsed',
    'message' => 'API received payload',
    'data' => [
        'payloadLen' => strlen($payload),
        'parsedIsArray' => is_array($parsed),
        'keys' => is_array($parsed) ? array_keys($parsed) : [],
        'hasNumeroProcesso' => is_array($parsed) && isset($parsed['numero_processo']),
        'hasClasseCodigo' => is_array($parsed) && isset($parsed['classe_codigo']),
    ],
    'timestamp' => round(microtime(true) * 1000),
    'hypothesisId' => 'C',
]) . "\n";
@file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
// #endregion

$usuarioCadastroId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($usuarioCadastroId !== '') {
    $parsed['usuario_cadastro_id'] = $usuarioCadastroId;
}

try {
    $service = new GuiasService($connection);
    $newId = $service->create($parsed);
    // #region agent log
    $logPath = dirname(__DIR__, 3) . '/debug-64730a.log';
    @file_put_contents($logPath, json_encode(['sessionId' => '64730a', 'location' => 'create.php:success', 'message' => 'create returned id', 'data' => ['newId' => $newId], 'timestamp' => round(microtime(true) * 1000), 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    // #region agent log
    $logPath = dirname(__DIR__, 3) . '/debug-64730a.log';
    @file_put_contents($logPath, json_encode(['sessionId' => '64730a', 'location' => 'create.php:catchInvalid', 'message' => $exception->getMessage(), 'data' => ['class' => get_class($exception)], 'timestamp' => round(microtime(true) * 1000), 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    // #region agent log
    $logPath = dirname(__DIR__, 3) . '/debug-64730a.log';
    @file_put_contents($logPath, json_encode(['sessionId' => '64730a', 'location' => 'create.php:catchThrowable', 'message' => $exception->getMessage(), 'data' => ['class' => get_class($exception), 'file' => $exception->getFile(), 'line' => $exception->getLine()], 'timestamp' => round(microtime(true) * 1000), 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar guia: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
