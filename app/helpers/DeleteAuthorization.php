<?php
declare(strict_types=1);

require_once __DIR__ . '/UsuarioPerfisAutorizacao.php';

final class DeleteAuthorization
{
    /** @return string[] */
    public static function perfisPadraoExclusao(): array
    {
        return ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
    }

    public static function usuarioPodeExcluir(?string $userId, ?array $perfisPermitidos = null): bool
    {
        $uid = trim((string) ($userId ?? ''));
        if ($uid === '') {
            return false;
        }

        $permitidos = is_array($perfisPermitidos) && count($perfisPermitidos) > 0
            ? $perfisPermitidos
            : self::perfisPadraoExclusao();

        $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($uid);
        return count(array_intersect($perfisUsuario, $permitidos)) > 0;
    }
}
