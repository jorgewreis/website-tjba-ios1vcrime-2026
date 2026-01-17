<?php
declare(strict_types=1);

final class Security
{
    private const SESSION_TOKEN_KEY = 'token';
    private const SESSION_USER_ID   = 'id_usuario';
    private const SESSION_PROFILE   = 'user_profile';
    private const CSRF_SESSION_KEY  = 'csrf_token';

    private const PERFIS_RANK = [
        'ESTUDANTE' => 1,
        'CONSULTOR' => 2, 
        'CARTORIO'  => 3,
        'AUDIENCIA' => 4,
        'GABINETE'  => 5,
        'ADMIN'     => 6,
    ];

    public static function isAuthenticated(): bool
    {
        return isset(
            $_SESSION[self::SESSION_TOKEN_KEY],
            $_SESSION[self::SESSION_USER_ID]
        ) && is_string($_SESSION[self::SESSION_TOKEN_KEY]);
    }

    public static function requireAuthentication(): void
    {
        if (!self::isAuthenticated()) {
            header('Location: /index.php');
            exit;
        }
    }

    public static function getPerfilRank(string $perfil): int
    {
        return self::PERFIS_RANK[strtoupper($perfil)] ?? 0;
    }

    /**
     * @deprecated Padrão antigo. Use /admin/api/getPerfis.php + frame.php::hasAccessToSection()
     */
    public static function checkAccess(string $requiredProfile): bool
    {
        if (!self::isAuthenticated()) {
            return false;
        }

        $userProfile = $_SESSION[self::SESSION_PROFILE] ?? null;
        $userLevel   = self::getPerfilRank($userProfile);
        $needLevel   = self::getPerfilRank($requiredProfile);

        return $userLevel >= $needLevel;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function generateSecureToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function generateCSRFToken(): string
    {
        if (empty($_SESSION[self::CSRF_SESSION_KEY])) {
            $_SESSION[self::CSRF_SESSION_KEY] = self::generateSecureToken(16);
        }
        return $_SESSION[self::CSRF_SESSION_KEY];
    }

    public static function verifyCSRFToken(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION[self::CSRF_SESSION_KEY])
            && hash_equals($_SESSION[self::CSRF_SESSION_KEY], $token);
    }

    /* ==================== UTILITÁRIOS ==================== */

    public static function sanitize(string $input): string
    {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }

    public static function normalizeCPF(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    public static function validarCPF(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($c = 0; $c < $t; $c++) {
                $soma += (int)$cpf[$c] * (($t + 1) - $c);
            }
            $dig = ((10 * $soma) % 11) % 10;
            if ((int)$cpf[$t] !== $dig) {
                return false;
            }
        }
        return true;
    }

    public static function formatCPF(string $cpf): string
    {
        return substr($cpf, 0, 3) . '.' .
               substr($cpf, 3, 3) . '.' .
               substr($cpf, 6, 3) . '-' .
               substr($cpf, 9, 2);
    }

    public static function generateTempPassword(int $len = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXY23456789';
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    /* ==================== SESSION GETTERS ==================== */

    public static function getUserId(): ?string
    {        
        return $_SESSION[self::SESSION_USER_ID] ?? null;
    }

    public static function getToken(): ?string
    {
        return $_SESSION[self::SESSION_TOKEN_KEY] ?? null;
    }

    /**
     * @deprecated Use /admin/api/getPerfis.php para múltiplos perfis (padrão novo).
     * Esta função mantida para retrocompatibilidade mas retorna apenas um perfil.
     */
    public static function getUserProfile(): ?string
    {
        return $_SESSION[self::SESSION_PROFILE] ?? null;
    }
}

