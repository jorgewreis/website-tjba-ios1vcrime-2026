<?php
declare(strict_types=1);

require_once __DIR__ . '/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../access/ProfileNormalizer.php';

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

    public static function usuarioPodeExcluirComoServidorOuChefia(?string $userId): bool
    {
        $uid = trim((string) ($userId ?? ''));
        if ($uid === '') {
            return false;
        }

        return self::perfisIncluemServidorOuChefia(UsuarioPerfisAutorizacao::perfisDoUsuario($uid));
    }

    /**
     * Servidor operacional ou chefia. Não libera estagiário.
     *
     * @param array<int, mixed> $perfis
     */
    public static function perfisIncluemServidorOuChefia(array $perfis): bool
    {
        $normalizados = ProfileNormalizer::normalize($perfis);
        foreach ($normalizados as $perfil) {
            $p = strtoupper(trim((string) $perfil));
            if ($p === 'CHEFIA') {
                return true;
            }
            if ($p === 'ESTAGIARIO' || strpos($p, 'ESTAGIARIO') !== false) {
                continue;
            }
            if ($p === 'SERVIDOR' || strpos($p, 'SERVIDOR') !== false) {
                return true;
            }
        }

        return false;
    }
}
