<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/datajud/search-processo.php');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
// O painel interno também inclui os perfis internos legados sem setor composto.
ApiAccessGuard::requireAccess(['dashboard'], true);

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

require_once dirname(__DIR__, 3) . '/app/helpers/CnjValidator.php';
require_once dirname(__DIR__, 3) . '/app/services/DataJudService.php';

try {
    $numero = CnjValidator::digits20OrThrow($numero);
} catch (InvalidArgumentException $e) {
    respond(['error' => $e->getMessage()], 400);
}

try {
    $service = new DataJudService($config);
    $data = $service->searchProcesso($numero);
} catch (InvalidArgumentException $e) {
    respond(['error' => $e->getMessage()], 400);
} catch (DataJudAmbiguousResponseException $e) {
    respond(['error' => 'O DataJud retornou mais de um registro para este processo; confira os dados manualmente.'], 409);
} catch (DataJudUpstreamException $e) {
    $e->respondForSync();
    exit;
} catch (RuntimeException $e) {
    $message = ApiErrorReporter::genericMessage($e, 'public/api/datajud/search-processo.php');
    respond(['error' => $message], 502);
}

$sigiloRestrito = !empty($data['sigilo_restrito']);
$sigiloMensagem = isset($data['sigilo_mensagem']) ? $data['sigilo_mensagem'] : null;
$indisponivelPublico = !empty($data['indisponivel_na_base_publica']);
unset($data['sigilo_restrito'], $data['sigilo_mensagem']);

respond([
    'data' => $data,
    'sigilo_restrito' => $sigiloRestrito,
    'sigilo_mensagem' => $sigiloMensagem,
    'indisponivel_na_base_publica' => $indisponivelPublico,
]);

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
