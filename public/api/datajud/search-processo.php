<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$config = require __DIR__ . '/config.php';

if (($config['api_key'] ?? '') === '') {
    respond(['error' => 'Integração DataJud não configurada.'], 500);
}

$rawInput = file_get_contents('php://input') ?: '';
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
$numero = trim(preg_replace('/\D+/', '', $numero));

if ($numero === '' || strlen($numero) !== 20) {
    respond(['error' => 'Número do processo inválido. Forneça 20 dígitos sem pontuação.'], 400);
}

require_once dirname(__DIR__, 3) . '/app/services/DataJudService.php';

try {
    $service = new DataJudService($config);
    $data = $service->searchProcesso($numero);
} catch (InvalidArgumentException $e) {
    respond(['error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
    $status = 502;
    if (str_contains($msg, 'não encontrado')) {
        $status = 404;
    } elseif (str_contains($msg, 'Não autorizado')) {
        $status = 502;
    }
    respond(['error' => $msg], $status);
}

$sigiloRestrito = !empty($data['sigilo_restrito']);
$sigiloMensagem = isset($data['sigilo_mensagem']) ? $data['sigilo_mensagem'] : null;
unset($data['sigilo_restrito'], $data['sigilo_mensagem']);

respond([
    'data' => $data,
    'sigilo_restrito' => $sigiloRestrito,
    'sigilo_mensagem' => $sigiloMensagem,
]);

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
