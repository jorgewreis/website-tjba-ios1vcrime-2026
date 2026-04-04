<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/EffectiveAccessProfiles.php';

/**
 * Perfis do usuário atual aplicando modo operacional (supressão de ADMIN), para checagens de autorização.
 */
final class UsuarioPerfisAutorizacao
{
    /**
     * @return list<string>
     */
    public static function perfisDoUsuario(string $userId): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            return [];
        }
        $usuario = new Usuario();
        $raw = $usuario->listarPerfisUsuario($userId);

        return EffectiveAccessProfiles::applyForUser($userId, is_array($raw) ? $raw : []);
    }
}
