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

    /** Admin ou chefia (painel admin, gabinete, sala de mídias, etc.). */
    public static function perfisAdminOuChefia(): array
    {
        return ['ADMIN', 'CHEFIA'];
    }

    /** Alias semântico: mesmo conjunto que {@see perfisAdminOuChefia()}. */
    public static function perfisGabineteExclusao(): array
    {
        return self::perfisAdminOuChefia();
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
