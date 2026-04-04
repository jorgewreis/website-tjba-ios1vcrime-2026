<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/ProdutividadeService.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';

/**
 * @return array<string, mixed>
 */
function produtividadeReadPayload(): array
{
    $payload = file_get_contents('php://input') ?: '';
    $parsed = json_decode($payload, true);
    if (!is_array($parsed)) {
        $parsed = $_POST;
    }

    return is_array($parsed) ? $parsed : [];
}

function produtividadeCurrentUserId(): string
{
    return isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
}

function produtividadeIsGestao(PDO $connection, string $userId): bool
{
    $userId = trim($userId);
    if ($userId === '') {
        return false;
    }

    $rows = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    foreach ($rows as $perfil) {
        $p = strtoupper(trim((string) $perfil));
        if ($p === 'ADMIN' || $p === 'CHEFIA' || $p === 'DIRETORA' || $p === 'MAGISTRADA') {
            return true;
        }
    }

    return false;
}

function produtividadeRespondError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
}

