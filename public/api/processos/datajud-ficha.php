<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$root = dirname(__DIR__, 3);
require_once $root . '/app/helpers/ApiAccessGuard.php';
require_once $root . '/app/helpers/ProcessoFicha.php';
require_once $root . '/app/repositories/ProcessosRepository.php';
require_once $root . '/app/services/DataJudService.php';

ApiAccessGuard::requireAccess(['consultaprocessosedit'], true);
ApiErrorReporter::register('public/api/processos/datajud-ficha.php');

$send = static function (array $body, int $status = 200): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};
$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$id = $payload['processo_id'] ?? null;
if (!is_string($id) || preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $id) !== 1) {
    $send(['estado'=>'indisponivel'], 400);
}
try {
    $repo = new ProcessosRepository(Conection::getInstance()->getConnection());
    $proc = $repo->findById($id);
    if ($proc === null) $send(['estado'=>'indisponivel'], 404);
    if (!empty($proc['sigiloso'])) $send(['estado'=>'restrito']);
    $numero = (string) ($proc['numero_processo'] ?? '');
    $config = require $root . '/public/api/datajud/config.php';
    if (empty($config['api_key'])) $send(['estado'=>'erro', 'code'=>'datajud_not_configured'], 503);

    // A leitura é separada da sincronização; nenhum campo local é gravado.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $data = (new DataJudService($config))->searchProcesso($numero);
    $atual = $repo->findById($id);
    if ($atual === null || !empty($atual['sigiloso']) || $numero !== $atual['numero_processo']) {
        $send(['estado'=>'indisponivel']);
    }
    $send(ProcessoFicha::dataJudPublico($data));
} catch (DataJudUpstreamException $erro) {
    // Codes are from a fixed vocabulary. Do not expose upstream bodies or configuration.
    $code = match ($erro->reason) {
        'throttled' => 'datajud_throttled',
        'incomplete' => 'datajud_incomplete',
        'authorization' => 'datajud_authorization',
        'transport' => 'datajud_transport',
        default => 'datajud_unavailable',
    };
    if ($code === 'datajud_throttled') header('Retry-After: 60');
    $send(['estado'=>'erro', 'code'=>$code], $code === 'datajud_authorization' ? 502 : 503);
} catch (DataJudAmbiguousResponseException $erro) {
    $send(['estado'=>'erro', 'code'=>'datajud_ambiguous'], 409);
} catch (InvalidArgumentException $erro) {
    $send(['estado'=>'erro', 'code'=>'datajud_not_queryable'], 422);
} catch (Throwable $erro) {
    ApiErrorReporter::logUnexpected($erro, 'public/api/processos/datajud-ficha.php');
    $send(['estado'=>'erro'], 502);
}
