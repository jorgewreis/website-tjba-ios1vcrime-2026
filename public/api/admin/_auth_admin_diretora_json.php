<?php
declare(strict_types=1);

/**
 * Incluído por APIs admin que exigem perfil ADMIN ou DIRETORA.
 * Define JSON; inicia sessão; responde 401/403 e encerra se não autorizado.
 *
 * @return PDO Conexão ativa
 */
function requireAdminOuDiretoraJson(string $basePath): PDO
{
    header('Content-Type: application/json; charset=UTF-8');

    require_once $basePath . '/config/config.php';
    require_once $basePath . '/config/security.php';
    require_once $basePath . '/app/helpers/Conection.php';
    require_once $basePath . '/app/helpers/AdminDiretoraHelper.php';

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!Security::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connection = Conection::getInstance()->getConnection();

    if (!usuarioEhAdminOuDiretora($connection)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $connection;
}
