<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['guiasedit'], true);

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiJsonResponse.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/services/GuiasService.php';
require_once $basePath . '/app/helpers/DeleteAuthorization.php';

if (!bootstrapRequireTrustedPostRequest()) {
    ApiJsonResponse::send(['error' => 'Requisição inválida.'], 403);
    exit;
}

$connection = Conection::getInstance()->getConnection();
$userId = $_SESSION['id_usuario'] ?? null;
$podeExcluir = DeleteAuthorization::usuarioPodeExcluir(
    $userId !== null ? trim((string) $userId) : null
);
if (!$podeExcluir) {
    ApiJsonResponse::send(['error' => 'Seu perfil não tem permissão para excluir. Apenas admin, chefia, diretora ou magistrada.'], 403);
    exit;
}

$parsed = ApiJsonResponse::readJsonPayload();

$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));

if ($id === '') {
    ApiJsonResponse::send(['error' => 'Identificador da guia é obrigatório.'], 400);
    exit;
}

try {
    $service = new GuiasService($connection);
    $resultado = $service->delete($id);
    ApiJsonResponse::send(is_array($resultado) ? $resultado : ['deleted' => (bool) $resultado], 200);
} catch (\InvalidArgumentException $exception) {
    ApiJsonResponse::send(['error' => $exception->getMessage()], 400);
} catch (\Throwable $exception) {
    ApiJsonResponse::send(['error' => 'Erro ao excluir guia: ' . $exception->getMessage()], 500);
}
