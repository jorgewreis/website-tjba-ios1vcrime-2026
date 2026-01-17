<?php
declare(strict_types=1);

require_once __DIR__ . '/Conection.php';
require_once __DIR__ . '/../config/security.php';

final class Usuario
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conection::getInstance()->getConnection();
    }

    /* ======================================================
       AUTENTICAÇÃO (apenas validação de credenciais)
       ====================================================== */

    public function autenticar(string $cpf, string $senha): array
    {
        $cpfNumerico = Security::normalizeCPF($cpf);

        if (!Security::validarCPF($cpfNumerico) || $senha === '') {
            return ['success' => false];
        }

        $cpfFormatado = Security::formatCPF($cpfNumerico);

        $stmt = $this->pdo->prepare("
            SELECT id_usuario, nome, senha_hash
            FROM usuarios
            WHERE cpf = ?
            LIMIT 1
        ");
        $stmt->execute([$cpfFormatado]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$usuario ||
            !Security::verifyPassword($senha, $usuario['senha_hash'])
        ) {
            return ['success' => false];
        }

        $perfis = $this->getPerfisUsuario($usuario['id_usuario']);
        $perfilPrincipal = $this->perfilPrincipal($perfis);

        return [
            'success'    => true,
            'id_usuario' => $usuario['id_usuario'],
            'nome'       => $usuario['nome'],
            'perfil'     => $perfilPrincipal,
            'perfis'     => $perfis,
        ];
    }

    /**
 * Logout apenas do usuário atual: remove token deste usuário no banco e encerra a sessão local.
 */
    public function logoutLocal(string $idUsuario): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios
                SET token_acesso = NULL,
                    token_expira = NULL
                WHERE id_usuario = ?
            ");
            $stmt->execute([$idUsuario]);

            // Apaga sessão local
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();

            return [
                'success' => true,
                'message' => 'Logout local realizado com sucesso.'
            ];
        } catch (PDOException $e) {
            error_log("Erro no logoutLocal: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao realizar logout local.'
            ];
        }
    }


    /* ======================================================
       PERFIS
       ====================================================== */

    private function getPerfisUsuario(string $idUsuario): array
    {
        $stmt = $this->pdo->prepare("
            SELECT perfil
            FROM usuario_perfil
            WHERE usuario = ?
        ");
        $stmt->execute([$idUsuario]);

        return array_map(
            static fn(string $perfil) => strtoupper(trim($perfil)),
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function perfilPrincipal(array $perfis): ?string
    {
        $melhorPerfil = null;
        $maiorRank    = 0;

        foreach ($perfis as $perfil) {
            $rank = Security::getPerfilRank($perfil);
            if ($rank > $maiorRank) {
                $maiorRank    = $rank;
                $melhorPerfil = $perfil;
            }
        }

        return $melhorPerfil;
    }

    /* ======================================================
       TOKEN (persistência)
       ====================================================== */

    public function atualizarToken(
        string $idUsuario,
        string $token,
        int $horasValidade
    ): void {
        $expira = date('Y-m-d H:i:s', strtotime("+{$horasValidade} hours"));

        $stmt = $this->pdo->prepare("
            UPDATE usuarios
            SET token_acesso = ?, token_expira = ?
            WHERE id_usuario = ?
        ");
        $stmt->execute([$token, $expira, $idUsuario]);
    }

    public function validarToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id_usuario, nome
            FROM usuarios
            WHERE token_acesso = ?
              AND token_expira > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
