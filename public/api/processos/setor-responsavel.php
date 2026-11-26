<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos/setor-responsavel.php');
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/ProcessResponsibleSectorAdminPolicy.php';
require_once $basePath . '/app/services/ProcessResponsibleSectorService.php';

ApiAccessGuard::requireAccess(['consultaprocessosedit'], true);

$actorId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$profiles = UsuarioPerfisAutorizacao::perfisDoUsuario($actorId);
if (!ProcessResponsibleSectorAdminPolicy::allows($profiles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawPayload = file_get_contents('php://input');
$payload = json_decode(is_string($rawPayload) ? $rawPayload : '', true);
if (!is_array($payload) || trim((string) $rawPayload) === '') {
    $payload = $_POST;
}

try {
    $processId = trim((string) ($payload['processo_id'] ?? ''));
    $sectorRaw = $payload['setor_id'] ?? null;
    $sectorId = is_scalar($sectorRaw) && trim((string) $sectorRaw) !== '' ? trim((string) $sectorRaw) : null;
    $reason = $payload['justificativa'] ?? '';
    if (!is_string($reason) || ($sectorRaw !== null && !is_string($sectorRaw))) {
        throw new InvalidArgumentException('Setor ou justificativa inválidos.');
    }
    $justification = $reason;

    $service = new ProcessResponsibleSectorService(Conection::getInstance()->getConnection());
    $result = $service->assign(
        $processId,
        $sectorId,
        $justification,
        $actorId,
        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    );
    echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ResponsibleProcessNotFound $error) {
    http_response_code(404);
    echo json_encode(['error' => 'Processo não encontrado.'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Falha ao atribuir setor responsável ao processo: ' . get_class($error));
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível registrar a atribuição auditada.'], JSON_UNESCAPED_UNICODE);
}
