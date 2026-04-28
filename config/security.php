<?php
declare(strict_types=1);

final class Security
{
    private const SESSION_TOKEN_KEY = 'token';
    private const SESSION_USER_ID   = 'id_usuario';
    private const SESSION_PROFILE   = 'user_profile';
    private const SESSION_USER_NAME = 'nome';
    private const SESSION_LAST_ACTIVITY = 'last_activity_at';
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
        if (!self::hasAuthenticatedKeys()) {
            return false;
        }

        return !self::isSessionExpiredByInactivity();
    }

    public static function requireAuthentication(): void
    {
        if (!self::isAuthenticated()) {
            self::redirectToLogin();
        }

        // Renova janela de inatividade a cada requisição autenticada.
        self::touchSessionActivity();
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

    public static function hashAccessToken(string $token): string
    {
        return hash('sha256', $token);
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

    private static function redirectToLogin(): void
    {
        self::clearAuthenticatedSession();
        $loginUrl = '/index.php';
        // HTTP redirect padrão.
        header('Location: ' . $loginUrl);
        // Garantir que o logout rompa o iframe: força topo.
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo "<!DOCTYPE html><html><head><meta http-equiv=\"refresh\" content=\"0;url={$loginUrl}\"></head><body><script>if(window.top){window.top.location.href='{$loginUrl}';}else{window.location.href='{$loginUrl}';}</script></body></html>";
        exit;
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

    public static function normalizeCNPJ(string $cnpj): string
    {
        return preg_replace('/\D+/', '', $cnpj) ?? '';
    }

    /**
     * Valida CNPJ (14 dígitos) e dígitos verificadores.
     */
    public static function validarCNPJ(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += (int) $cnpj[$i] * $pesos1[$i];
        }
        $resto = $soma % 11;
        $dv1 = $resto < 2 ? 0 : 11 - $resto;
        if ((int) $cnpj[12] !== $dv1) {
            return false;
        }

        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += (int) $cnpj[$i] * $pesos2[$i];
        }
        $resto = $soma % 11;
        $dv2 = $resto < 2 ? 0 : 11 - $resto;
        return (int) $cnpj[13] === $dv2;
    }

    public static function formatCNPJ(string $cnpj): string
    {
        return substr($cnpj, 0, 2) . '.' .
               substr($cnpj, 2, 3) . '.' .
               substr($cnpj, 5, 3) . '/' .
               substr($cnpj, 8, 4) . '-' .
               substr($cnpj, 12, 2);
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

    /**
     * @return array{valid:bool,message:?string}
     */
    public static function validatePasswordPolicy(string $password): array
    {
        if ($password === '') {
            return ['valid' => false, 'message' => 'Informe a senha.'];
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return [
                'valid' => false,
                'message' => 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.',
            ];
        }

        if (strlen($password) > PASSWORD_MAX_LENGTH) {
            return [
                'valid' => false,
                'message' => 'A senha deve ter no máximo ' . PASSWORD_MAX_LENGTH . ' caracteres.',
            ];
        }

        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'A senha deve conter ao menos uma letra minúscula.'];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'A senha deve conter ao menos uma letra maiúscula.'];
        }

        if (!preg_match('/\d/', $password)) {
            return ['valid' => false, 'message' => 'A senha deve conter ao menos um número.'];
        }

        if (!preg_match('/[^\w\s]/', $password)) {
            return ['valid' => false, 'message' => 'A senha deve conter ao menos um símbolo.'];
        }

        return ['valid' => true, 'message' => null];
    }

    public static function passwordPolicySummary(): string
    {
        return 'mínimo de ' . PASSWORD_MIN_LENGTH . ' caracteres, com 1 letra maiúscula, 1 minúscula, 1 número e 1 símbolo';
    }

    public static function initializeAuthenticatedSession(string $userId, ?string $profile, string $token, ?string $name = null): void
    {
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_PROFILE] = $profile;
        $_SESSION[self::SESSION_TOKEN_KEY] = $token;
        if ($name !== null && $name !== '') {
            $_SESSION[self::SESSION_USER_NAME] = $name;
        }

        self::touchSessionActivity(true);
    }

    public static function touchSessionActivity(bool $force = false): void
    {
        if (!self::hasAuthenticatedKeys()) {
            return;
        }

        if (!$force && self::isSessionExpiredByInactivity()) {
            return;
        }

        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
    }

    public static function clearAuthenticatedSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset(
                $_SESSION[self::SESSION_USER_ID],
                $_SESSION[self::SESSION_PROFILE],
                $_SESSION[self::SESSION_TOKEN_KEY],
                $_SESSION[self::SESSION_USER_NAME],
                $_SESSION[self::SESSION_LAST_ACTIVITY],
                $_SESSION[self::CSRF_SESSION_KEY],
                $_SESSION['access_suppress_admin_profile']
            );
        }

        if (function_exists('destroySessionData')) {
            destroySessionData();
        }
    }

    public static function isSessionExpiredByInactivity(): bool
    {
        if (!self::hasAuthenticatedKeys()) {
            return false;
        }

        $lastActivity = $_SESSION[self::SESSION_LAST_ACTIVITY] ?? null;
        if (!is_int($lastActivity) && !ctype_digit((string) $lastActivity)) {
            return false;
        }

        return (time() - (int) $lastActivity) > SESSION_IDLE_TIMEOUT;
    }

    public static function getClientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $candidate));
            foreach ($parts as $part) {
                if ($part !== '' && filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        return '0.0.0.0';
    }

    public static function getUserName(): ?string
    {
        return $_SESSION[self::SESSION_USER_NAME] ?? null;
    }

    private static function hasAuthenticatedKeys(): bool
    {
        return isset(
            $_SESSION[self::SESSION_TOKEN_KEY],
            $_SESSION[self::SESSION_USER_ID]
        ) && is_string($_SESSION[self::SESSION_TOKEN_KEY]);
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

