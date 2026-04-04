<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/UsuarioPerfisAutorizacao.php';

/**
 * Verifica se o usuário logado possui perfil ADMIN ou DIRETORA.
 *
 * @return list<string>
 */
function getPerfisUsuarioAtual(PDO $connection): array
{
    unset($connection);
    $userId = Security::getUserId();
    if ($userId === null || $userId === '') {
        return [];
    }

    return UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userId));
}

function usuarioEhAdminOuDiretora(PDO $connection): bool
{
    $perfis = getPerfisUsuarioAtual($connection);
    return count(array_intersect($perfis, ['ADMIN', 'DIRETORA'])) > 0;
}
