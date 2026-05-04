<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/security.php';

/**
 * Quando o usuário tem ADMIN cumulativo com outro perfil, pode ativar "modo operacional"
 * (sessão) que remove ADMIN das verificações de autorização — menu, frame e APIs alinhados.
 */
final class EffectiveAccessProfiles
{
    public const SESSION_SUPPRESS_ADMIN = 'access_suppress_admin_profile';

    /** Definido quando o usuário alterna Admin/Operacional via API; ausente = ainda não escolheu (usa padrão operacional). */
    public const SESSION_ADMIN_MODE_EXPLICIT = 'access_admin_mode_explicit';

    public static function isAdminSuppressed(): bool
    {
        return !empty($_SESSION[self::SESSION_SUPPRESS_ADMIN]);
    }

    public static function setAdminSuppressed(bool $suppress): void
    {
        if ($suppress) {
            $_SESSION[self::SESSION_SUPPRESS_ADMIN] = true;
        } else {
            unset($_SESSION[self::SESSION_SUPPRESS_ADMIN]);
        }
    }

    /**
     * Aplica supressão de ADMIN na sessão apenas quando a lista consultada é a do usuário logado
     * (evita afetar checagens sobre outros IDs no mesmo request).
     *
     * @param list<string> $upperPerfisDb valores já normalizados em maiúsculas (como em Usuario::listarPerfisUsuario)
     * @return list<string>
     */
    public static function applyForUser(string $consultedUserId, array $upperPerfisDb): array
    {
        $sessionId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
        $consultedUserId = trim($consultedUserId);
        if ($sessionId === '' || $consultedUserId === '' || strcasecmp($sessionId, $consultedUserId) !== 0) {
            return $upperPerfisDb;
        }
        if (!self::isAdminSuppressed()) {
            return $upperPerfisDb;
        }

        return array_values(array_filter($upperPerfisDb, static function ($p) {
            return strtoupper(trim((string) $p)) !== 'ADMIN';
        }));
    }

    /**
     * Exige perfil ADMIN no cadastro e pelo menos outro perfil distinto (após colapsar DIRETORA/MAGISTRADA em CHEFIA).
     *
     * @param list<string> $upperPerfisDb
     */
    public static function canOfferAdminModeToggle(array $upperPerfisDb): bool
    {
        $hasAdmin = false;
        $unique = [];
        foreach ($upperPerfisDb as $p) {
            $u = strtoupper(trim((string) $p));
            if ($u === 'ADMIN') {
                $hasAdmin = true;
            }
            if ($u === 'DIRETORA' || $u === 'MAGISTRADA') {
                $u = 'CHEFIA';
            }
            if ($u !== '') {
                $unique[$u] = true;
            }
        }

        return $hasAdmin && count($unique) >= 2;
    }

    /**
     * Para quem tem ADMIN + outro perfil: modo operacional (ADMIN suprimido) até o usuário alternar explicitamente.
     *
     * @param list<string> $upperPerfisDb
     */
    public static function applyDefaultOperacionalIfCumulative(array $upperPerfisDb): void
    {
        if (!self::canOfferAdminModeToggle($upperPerfisDb)) {
            return;
        }
        if (!empty($_SESSION[self::SESSION_ADMIN_MODE_EXPLICIT])) {
            return;
        }
        self::setAdminSuppressed(true);
    }
}
