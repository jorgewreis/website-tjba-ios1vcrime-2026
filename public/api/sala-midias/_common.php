<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaMidiasService.php';

function salaMidiasCurrentUserId(): string
{
    return isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
}

function salaMidiasReadPayload(): array
{
    $raw = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (is_array($raw)) {
        return $raw;
    }

    return is_array($_POST) ? $_POST : [];
}

function salaMidiasJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}
