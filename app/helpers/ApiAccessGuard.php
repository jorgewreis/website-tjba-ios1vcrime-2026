<?php
declare(strict_types=1);

require_once __DIR__ . '/BootstrapHelper.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../access/AccessEvaluator.php';
require_once __DIR__ . '/UsuarioPerfisAutorizacao.php';

/** Protege endpoints JSON usando as mesmas regras de acesso das páginas. */
final class ApiAccessGuard
{
    /** @param list<string> $pages */
    public static function requireAccess(array $pages, bool $write = false): void
    {
        if (!Security::isAuthenticated()) {
            self::reject(401, 'Sessão expirada ou não autenticada.');
        }

        if ($write && !bootstrapRequireTrustedPostRequest()) {
            self::reject(403, 'Requisição inválida.');
        }

        try {
            $userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
            $profiles = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
            foreach ($pages as $page) {
                if (AccessEvaluator::canAccessPage($page, $profiles)) {
                    return;
                }
            }
        } catch (Throwable $exception) {
            error_log('Falha ao verificar autorização de API: ' . get_class($exception));
            self::reject(500, 'Não foi possível verificar o acesso.');
        }

        self::reject(403, 'Acesso negado.');
    }

    private static function reject(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
