<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Conection.php';
require_once __DIR__ . '/../../config/security.php';

final class Usuario
{
    private const LAST_ACCESS_SESSION_KEY = 'last_access_touch';
    private const LAST_ACCESS_REFRESH_SECONDS = 300;

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
            SELECT id_usuario, nome, senha_hash, COALESCE(cadastro_ativo, 1) AS cadastro_ativo
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

        if (empty($usuario['cadastro_ativo'])) {
            return ['success' => false, 'inactive' => true, 'message' => 'Seu cadastro está pendente de validação. Entre em contato com a diretoria ou o administrador do sistema.'];
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
     * Logout apenas do usuário atual: remove o token persistido do usuário.
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
        return Security::resolveDefaultSessionProfile($perfis);
    }

    public function listarPerfisUsuario(string $idUsuario): array
    {
        $idUsuario = trim($idUsuario);

        if ($idUsuario === '') {
            return [];
        }

        return $this->getPerfisUsuario($idUsuario);
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
        $tokenHash = Security::hashAccessToken($token);

        $stmt = $this->pdo->prepare("
            UPDATE usuarios
            SET token_acesso = ?, token_expira = ?
            WHERE id_usuario = ?
        ");
        $stmt->execute([$tokenHash, $expira, $idUsuario]);
    }

    public function registrarUltimoAcesso(string $idUsuario): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE usuarios
            SET ultimo_acesso = NOW()
            WHERE id_usuario = ?
        ");
        $stmt->execute([$idUsuario]);
        $this->markLastAccessRefresh($idUsuario);
    }

    public function validarToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $tokenHash = Security::hashAccessToken($token);
        $stmt = $this->pdo->prepare("
            SELECT id_usuario, nome, token_acesso
            FROM usuarios
            WHERE (token_acesso = ? OR token_acesso = ?)
              AND token_expira > NOW()
              AND COALESCE(cadastro_ativo, 1) = 1
            LIMIT 1
        ");
        $stmt->execute([$tokenHash, $token]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($usuario !== null) {
            $idUsuario = trim((string) ($usuario['id_usuario'] ?? ''));
            $storedToken = (string) ($usuario['token_acesso'] ?? '');

            if ($storedToken === $token) {
                $upgradeStmt = $this->pdo->prepare("
                    UPDATE usuarios
                    SET token_acesso = ?
                    WHERE id_usuario = ?
                      AND token_acesso = ?
                ");
                $upgradeStmt->execute([$tokenHash, $idUsuario, $token]);
            }

            if ($idUsuario !== '' && $this->shouldRefreshLastAccess($idUsuario)) {
                $this->registrarUltimoAcesso($idUsuario);
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                Security::touchSessionActivity();
            }
        }

        return $usuario;
    }

    /**
     * Altera a senha do usuário. Exige senha atual correta.
     * Nova senha: política centralizada em Security::validatePasswordPolicy().
     *
     * @return array{success: bool, message?: string}
     */
    public function alterarSenha(string $idUsuario, string $senhaAtual, string $novaSenha): array
    {
        $idUsuario = trim($idUsuario);
        if ($idUsuario === '') {
            return ['success' => false, 'message' => 'Usuário não identificado.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT senha_hash FROM usuarios WHERE id_usuario = ? LIMIT 1
        ");
        $stmt->execute([$idUsuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['senha_hash'])) {
            return ['success' => false, 'message' => 'Usuário não encontrado.'];
        }

        if (!Security::verifyPassword($senhaAtual, $row['senha_hash'])) {
            return ['success' => false, 'message' => 'Senha atual incorreta.'];
        }

        $policy = Security::validatePasswordPolicy($novaSenha);
        if (($policy['valid'] ?? false) !== true) {
            return ['success' => false, 'message' => $policy['message'] ?? 'Senha inválida.'];
        }

        if (Security::verifyPassword($novaSenha, $row['senha_hash'])) {
            return ['success' => false, 'message' => 'A nova senha deve ser diferente da senha atual.'];
        }

        $checkHistory = PASSWORD_HISTORY_ON_CHANGE_ENABLED
            && (!function_exists('isPasswordHistoryCheckEnabled') || isPasswordHistoryCheckEnabled());
        if ($checkHistory && $this->passwordMatchesStoredHistory($idUsuario, $novaSenha)) {
            return ['success' => false, 'message' => 'A nova senha não pode repetir nenhuma das últimas senhas utilizadas.'];
        }

        $novoHash = Security::hashPassword($novaSenha);
        try {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios SET senha_hash = ?, updated_at = NOW() WHERE id_usuario = ?
            ");
            $stmt->execute([$novoHash, $idUsuario]);
        } catch (\PDOException $e) {
            if (stripos($e->getMessage(), 'updated_at') !== false || stripos($e->getMessage(), 'Unknown column') !== false) {
                $stmt = $this->pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id_usuario = ?");
                $stmt->execute([$novoHash, $idUsuario]);
            } else {
                throw $e;
            }
        }

        if ($stmt->rowCount() < 1) {
            return ['success' => false, 'message' => 'Não foi possível atualizar a senha. Tente novamente.'];
        }

        return ['success' => true];
    }

    /**
     * Retorna os dados pessoais do usuário para exibição/edição (sem senha_hash).
     * Retorna null se não encontrado.
     */
    public function getDadosPessoais(string $idUsuario): ?array
    {
        $idUsuario = trim($idUsuario);
        if ($idUsuario === '') {
            return null;
        }
        $fields = [
            'id_usuario',
            'nome',
            'sobrenome',
            'cpf',
            'email',
            'telefone',
            'cargo',
            'instituicao',
            'ultimo_acesso',
            'updated_at',
        ];
        if ($this->hasUsuariosColumn('sexo')) {
            $fields[] = 'sexo';
        }
        $stmt = $this->pdo->prepare(
            'SELECT ' . implode(', ', $fields) . ' FROM usuarios WHERE id_usuario = ? LIMIT 1'
        );
        $stmt->execute([$idUsuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return $row;
    }

    /**
     * Atualiza dados pessoais permitidos (email, telefone, cargo, instituicao/setor).
     * Nome e sobrenome não podem ser alterados por esta função. CPF e id_usuario também não.
     *
     * @return array{success: bool, message?: string}
     */
    public function atualizarDadosPessoais(string $idUsuario, array $dados): array
    {
        $idUsuario = trim($idUsuario);
        if ($idUsuario === '') {
            return ['success' => false, 'message' => 'Usuário não identificado.'];
        }

        $allowed = ['email', 'telefone', 'cargo', 'instituicao'];
        if ($this->hasUsuariosColumn('sexo')) {
            $allowed[] = 'sexo';
        }
        $set = [];
        $params = [':id' => $idUsuario];
        foreach ($allowed as $campo) {
            if (!array_key_exists($campo, $dados)) {
                continue;
            }
            $valor = trim((string) $dados[$campo]);
            $set[] = "`{$campo}` = :{$campo}";
            $params[":{$campo}"] = $valor === '' ? null : $valor;
        }
        if (empty($set)) {
            return ['success' => false, 'message' => 'Nenhum dado permitido para atualização.'];
        }

        $setWithUpdated = array_merge($set, ['`updated_at` = NOW()']);
        $sqlWithUpdated = 'UPDATE usuarios SET ' . implode(', ', $setWithUpdated) . ' WHERE id_usuario = :id LIMIT 1';
        $sqlWithoutUpdated = 'UPDATE usuarios SET ' . implode(', ', $set) . ' WHERE id_usuario = :id LIMIT 1';

        try {
            $stmt = $this->pdo->prepare($sqlWithUpdated);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            // rowCount pode ser 0 quando valores não mudam; ainda assim o UPDATE foi aplicado.
            return ['success' => true];
        } catch (\PDOException $e) {
            if (stripos($e->getMessage(), 'updated_at') !== false || stripos($e->getMessage(), 'Unknown column') !== false) {
                $stmt = $this->pdo->prepare($sqlWithoutUpdated);
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->execute();
                return ['success' => true];
            }
            throw $e;
        }
    }

    private function hasUsuariosColumn(string $column): bool
    {
        $column = trim($column);
        if ($column === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = :column"
        );
        $stmt->bindValue(':column', $column);
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function shouldRefreshLastAccess(string $idUsuario): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }

        $registry = $_SESSION[self::LAST_ACCESS_SESSION_KEY] ?? [];
        if (!is_array($registry)) {
            return true;
        }

        $lastTouch = isset($registry[$idUsuario]) ? (int) $registry[$idUsuario] : 0;
        return $lastTouch <= 0 || (time() - $lastTouch) >= self::LAST_ACCESS_REFRESH_SECONDS;
    }

    /**
     * Verifica se a candidata coincide com algum hash em `historico_senha` (últimas N trocas).
     * A comparação com a senha atual já foi feita em alterarSenha() antes desta chamada.
     */
    private function passwordMatchesStoredHistory(string $idUsuario, string $novaSenha, int $historyLimit = 5): bool
    {
        try {
            $stmtHistorico = $this->pdo->prepare('
                SELECT senha_hash
                  FROM historico_senha
                 WHERE usuario = ?
              ORDER BY data_alteracao DESC
                 LIMIT ' . max(1, $historyLimit)
            );
            $stmtHistorico->execute([$idUsuario]);
            $historico = $stmtHistorico->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return false;
        }

        foreach ($historico as $hash) {
            if (is_string($hash) && $hash !== '' && Security::verifyPassword($novaSenha, $hash)) {
                return true;
            }
        }

        return false;
    }

    private function markLastAccessRefresh(string $idUsuario): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!isset($_SESSION[self::LAST_ACCESS_SESSION_KEY]) || !is_array($_SESSION[self::LAST_ACCESS_SESSION_KEY])) {
            $_SESSION[self::LAST_ACCESS_SESSION_KEY] = [];
        }

        $_SESSION[self::LAST_ACCESS_SESSION_KEY][$idUsuario] = time();
    }
}
