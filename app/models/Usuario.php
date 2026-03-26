<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Conection.php';
require_once __DIR__ . '/../helpers/AvatarCatalog.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../services/ExternalUsersCleanupService.php';

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
        $cleanupService = new ExternalUsersCleanupService($this->pdo);
        $cleanupService->purgeInactiveStudents();

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

        $stmt = $this->pdo->prepare("
            UPDATE usuarios
            SET token_acesso = ?, token_expira = ?
            WHERE id_usuario = ?
        ");
        $stmt->execute([$token, $expira, $idUsuario]);
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
        $stmt = $this->pdo->prepare("
            SELECT id_usuario, nome
            FROM usuarios
            WHERE token_acesso = ?
              AND token_expira > NOW()
              AND COALESCE(cadastro_ativo, 1) = 1
            LIMIT 1
        ");
        $stmt->execute([$token]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($usuario !== null) {
            $idUsuario = trim((string) ($usuario['id_usuario'] ?? ''));
            if ($idUsuario !== '' && $this->shouldRefreshLastAccess($idUsuario)) {
                $this->registrarUltimoAcesso($idUsuario);
            }
        }

        return $usuario;
    }

    /**
     * Altera a senha do usuário. Exige senha atual correta.
     * Nova senha: mínimo 8 caracteres.
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

        $novaSenha = trim($novaSenha);
        if (strlen($novaSenha) < 8) {
            return ['success' => false, 'message' => 'A nova senha deve ter no mínimo 8 caracteres.'];
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
        if ($this->hasUsuariosColumn('avatar_slug')) {
            $fields[] = 'avatar_slug';
        }

        $stmt = $this->pdo->prepare(
            'SELECT ' . implode(', ', $fields) . ' FROM usuarios WHERE id_usuario = ? LIMIT 1'
        );
        $stmt->execute([$idUsuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $slug = trim((string) ($row['avatar_slug'] ?? ''));
        $row['avatar_slug'] = AvatarCatalog::normalizeStoredSlug($slug);
        return $row;
    }

    /**
     * Modo navbar: imagem 3d ou iniciais (externos sempre iniciais).
     *
     * @return array{mode: string, initials: string, url: ?string}
     */
    public function getAvatarNavbarDisplay(string $idUsuario, bool $isUsuarioExterno, string $nome, string $sobrenome): array
    {
        $initials = AvatarCatalog::initialsFromNames($nome, $sobrenome);
        if ($isUsuarioExterno) {
            return ['mode' => 'initials', 'initials' => $initials, 'url' => null];
        }
        $dados = $this->getDadosPessoais($idUsuario);
        if ($dados === null) {
            return ['mode' => 'initials', 'initials' => $initials, 'url' => null];
        }
        $slug = AvatarCatalog::normalizeStoredSlug(trim((string) ($dados['avatar_slug'] ?? '')));
        if (AvatarCatalog::isInitialsSlug($slug)) {
            return ['mode' => 'initials', 'initials' => $initials, 'url' => null];
        }
        $url = AvatarCatalog::urlForSlug($slug);
        if ($url !== '') {
            return ['mode' => 'image', 'initials' => $initials, 'url' => $url];
        }
        return ['mode' => 'initials', 'initials' => $initials, 'url' => null];
    }

    /**
     * Persiste avatar por slug (apenas valores permitidos pelo catálogo).
     *
     * @return array{success: bool, message?: string}
     */
    public function atualizarAvatarSlug(string $idUsuario, string $slug): array
    {
        $idUsuario = trim($idUsuario);
        if ($idUsuario === '') {
            return ['success' => false, 'message' => 'Usuário não identificado.'];
        }
        $slug = AvatarCatalog::normalizeStoredSlug(trim($slug));
        if (!AvatarCatalog::isAllowedSlug($slug)) {
            return ['success' => false, 'message' => 'Avatar inválido.'];
        }

        try {
            $stmt = $this->pdo->prepare('UPDATE usuarios SET `avatar_slug` = :slug, `updated_at` = NOW() WHERE id_usuario = :id LIMIT 1');
            $stmt->bindValue(':slug', $slug);
            $stmt->bindValue(':id', $idUsuario);
            $stmt->execute();
            return ['success' => true];
        } catch (\PDOException $e) {
            if (stripos($e->getMessage(), 'avatar_slug') !== false || stripos($e->getMessage(), 'Unknown column') !== false) {
                return ['success' => false, 'message' => 'Avatar não disponível nesta instalação. Execute a migração do banco.'];
            }
            throw $e;
        }
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
