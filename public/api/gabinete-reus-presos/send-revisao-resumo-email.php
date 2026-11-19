<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/gabinete-reus-presos/send-revisao-resumo-email.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['reuspresosedit'], true);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/services/GabineteReusPresosRevisaoEmailService.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisUsuario = $userId !== '' ? UsuarioPerfisAutorizacao::perfisDoUsuario($userId) : [];
if (count(array_intersect($perfisUsuario, ['ADMIN', 'MAGISTRADA'])) === 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Sem permissão para enviar o resumo por e-mail. Apenas admin ou magistrada.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

try {
    $service = new GabineteReusPresosRevisaoEmailService($connection);
    $result = $service->sendResumo();
    if (!$result['success']) {
        $status = str_contains(strtolower((string) $result['message']), 'destinatário') ? 422 : 500;
        http_response_code($status);
    }

    echo json_encode([
        'success' => (bool) $result['success'],
        'sent' => (bool) $result['sent'],
        'count' => (int) $result['count'],
        'message' => (string) $result['message'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    ApiErrorReporter::logUnexpected($exception, 'public/api/gabinete-reus-presos/send-revisao-resumo-email.php');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao enviar resumo.',
    ], JSON_UNESCAPED_UNICODE);
}
