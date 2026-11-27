<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/helpers/ApiAccessGuard.php';
require_once $root . '/app/helpers/Conection.php';
require_once $root . '/app/helpers/ProcessoSetorAuthorization.php';
require_once $root . '/app/helpers/AuditLoggerHelper.php';
require_once $root . '/app/repositories/ProcessoVaraRepository.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
ApiErrorReporter::register('public/api/processos/vara.php');
ApiAccessGuard::requireAccess(['consultaprocessosedit'], true);

$send = static function (int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
};
$db = null;
try {
    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    $id = $payload['processo_id'] ?? null;
    if (!is_string($id) || preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $id) !== 1) {
        throw new InvalidArgumentException('Identificador do processo inválido.');
    }
    $vara = ProcessoVaraRepository::normalizar($payload['vara'] ?? null);
    $actor = (string) ($_SESSION['id_usuario'] ?? '');
    $db = Conection::getInstance()->getConnection();
    if (!$db->beginTransaction()) throw new RuntimeException('Transação indisponível.');
    if (!ProcessoSetorAuthorization::canModifyProcess($db, $actor, $id, true)) {
        $db->rollBack();
        $send(403, ['error' => 'Acesso negado ao processo.']);
        return;
    }
    $repo = new ProcessoVaraRepository($db);
    if (!$repo->disponivel()) {
        $db->rollBack();
        $send(503, ['error' => 'O registro local da Vara ainda está indisponível.']);
        return;
    }
    $alterado = $repo->registrar($id, $vara);
    if ($alterado) AuditLogger::log($db, $actor, 'processo_vara_local_registrada', ['processo_id' => $id]);
    if (!$db->commit()) throw new RuntimeException('Vara não confirmada.');
    $send(200, ['success'=>true, 'alterado'=>$alterado]);
} catch (ProcessoVaraDataJudProtegidaException $e) {
    if ($db !== null && $db->inTransaction()) $db->rollBack();
    $send(409, ['error'=>$e->getMessage()]);
} catch (InvalidArgumentException $e) {
    if ($db !== null && $db->inTransaction()) $db->rollBack();
    $send(400, ['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    if ($db !== null && $db->inTransaction()) $db->rollBack();
    ApiErrorReporter::logUnexpected($e, 'public/api/processos/vara.php');
    $send(500, ['error'=>'Não foi possível registrar a Vara.']);
}
