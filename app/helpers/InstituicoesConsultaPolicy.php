<?php
declare(strict_types=1);

require_once __DIR__ . '/UserAccessHelper.php';
require_once __DIR__ . '/../access/ProfileNormalizer.php';
require_once __DIR__ . '/UsuarioPerfisAutorizacao.php';

/** Limite para CONSULTOR externo; perfis internos efetivos conservam seu escopo. */
final class InstituicoesConsultaPolicy
{
    public static function onlyActiveForProfiles(array $profiles): bool
    {
        return UserAccessHelper::isUsuarioExterno($profiles)
            && ProfileNormalizer::hasConsultor(ProfileNormalizer::normalize($profiles));
    }

    public static function onlyActiveForUser(string $userId): bool
    {
        if (trim($userId) === '') throw new RuntimeException('Usuário ausente para consulta.');
        $profiles = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
        if ($profiles === []) throw new RuntimeException('Perfis indisponíveis para consulta.');
        return self::onlyActiveForProfiles($profiles);
    }
}
