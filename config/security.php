<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/ApiErrorReporter.php';

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

        if (self::isSessionExpiredByInactivity()) {
            return false;
        }

        try {
            require_once __DIR__ . '/../app/helpers/Conection.php';
            $pdo = Conection::getInstance()->getConnection();
            $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND token_acesso = ? AND token_expira > NOW() AND COALESCE(cadastro_ativo, 1) = 1 LIMIT 1');
            $token = (string) $_SESSION[self::SESSION_TOKEN_KEY];
            $stmt->execute([(string) $_SESSION[self::SESSION_USER_ID], self::hashAccessToken($token)]);
            if ($stmt->fetchColumn() === false) {
                self::clearAuthenticatedSession();
                return false;
            }
        } catch (Throwable $exception) {
            ApiErrorReporter::logUnexpected($exception, 'config/security.php');
            return false;
        }

        // Mantém a janela de inatividade alinhada também para endpoints
        // que validam sessão via isAuthenticated() (ex.: APIs JSON).
        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();

        return true;
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
     * Perfil único gravado em sessão (`user_profile`) após login ou sincronização na home.
     * Se existir ADMIN cumulado com outro perfil reconhecido, nunca retorna ADMIN por padrão:
     * escolhe o de maior rank entre os demais (empate: ordem alfabética do código do perfil).
     *
     * @param list<string> $perfisMaiusculos como em `Usuario::listarPerfisUsuario`
     */
    public static function resolveDefaultSessionProfile(array $perfisMaiusculos): ?string
    {
        $unicos = [];
        foreach ($perfisMaiusculos as $p) {
            $u = strtoupper(trim((string) $p));
            if ($u !== '') {
                $unicos[$u] = $u;
            }
        }
        $lista = array_values($unicos);
        if ($lista === []) {
            return null;
        }
        $temAdmin = in_array('ADMIN', $lista, true);
        $pool = $temAdmin
            ? array_values(array_filter($lista, static function (string $x): bool {
                return $x !== 'ADMIN';
            }))
            : $lista;
        if ($pool === []) {
            return 'ADMIN';
        }
        usort($pool, static function (string $a, string $b): int {
            $ra = self::getPerfilRank($a);
            $rb = self::getPerfilRank($b);
            if ($ra !== $rb) {
                return $rb <=> $ra;
            }

            return strcasecmp($a, $b);
        });

        return $pool[0];
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

        // Não usar header('Location') aqui: dentro de iframe o redirect permanece no frame.
        // A navegação deve ocorrer no topo da janela para evitar login "embutido" no layout interno.
        if (!headers_sent()) {
            http_response_code(401);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (!headers_sent()) {
            // Mantido por segurança caso algum header acima falhe silenciosamente.
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
                $_SESSION['access_suppress_admin_profile'],
                $_SESSION['access_admin_mode_explicit']
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
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        // Apenas endereços explícitos de proxies sob administração local.
        $trusted = array_filter(array_map('trim', explode(',', (string) getenv('TRUSTED_PROXIES'))));
        if (!in_array($remote, $trusted, true)) {
            return $remote;
        }

        $chain = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        $chain[] = $remote;
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $ip = $chain[$i];
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return $remote;
            }
            if (!in_array($ip, $trusted, true)) {
                return $ip;
            }
        }
        return $remote;
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

