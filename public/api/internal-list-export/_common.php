<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/helpers/ListExportHelper.php';
require_once $basePath . '/app/helpers/InternalListExportHandlers.php';

/**
 * @return array{userId:string,listPage:string,pdo:PDO}
 */
function internal_list_export_bootstrap(): array
{
    if (!Security::isAuthenticated()) {
        internal_list_export_http_error(401, 'Sessão expirada. Faça login novamente.');
    }

    $userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($userId === '') {
        internal_list_export_http_error(401, 'Sessão expirada. Faça login novamente.');
    }

    $listPage = trim((string) ($_GET['page'] ?? ''));
    if ($listPage === '') {
        internal_list_export_http_error(400, 'Parâmetro page ausente.');
    }

    $userPerfils = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);

    if (!AccessEvaluator::canAccessPage($listPage, $userPerfils)) {
        internal_list_export_http_error(403, 'Acesso negado.');
    }

    // Conta externa com perfil de estudante não pode exportar listas.
    if (UserAccessHelper::isUsuarioExterno($userPerfils)
        && ProfileNormalizer::hasEstudante(ProfileNormalizer::normalize($userPerfils))) {
        internal_list_export_http_error(403, 'Acesso negado.');
    }

    $pdo = Conection::getInstance()->getConnection();

    return [
        'userId' => $userId,
        'listPage' => $listPage,
        'pdo' => $pdo,
    ];
}

function internal_list_export_http_error(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}
