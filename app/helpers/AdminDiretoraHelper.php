<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/security.php';

/**
 * Verifica se o usuário logado possui perfil ADMIN ou DIRETORA.
 *
 * @return list<string>
 */
function getPerfisUsuarioAtual(PDO $connection): array
{
    $userId = Security::getUserId();
    if ($userId === null || $userId === '') {
        return [];
    }
    $stmt = $connection->prepare(
        'SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($rows)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $rows)));
}

function usuarioEhAdminOuDiretora(PDO $connection): bool
{
    $perfis = getPerfisUsuarioAtual($connection);
    return count(array_intersect($perfis, ['ADMIN', 'DIRETORA'])) > 0;
}
