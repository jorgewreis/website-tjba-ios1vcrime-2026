<?php
declare(strict_types=1);

/**
 * Listagem de servidores internos (tabela usuarios + perfis/setores).
 * Apenas usuários com perfil interno (exclui quem tem só ESTUDANTE).
 * Estudantes vêm da tabela estudantes (EstudantesListRepository).
 */
final class UsuariosListRepository
{
    private PDO $connection;

    /** Mapeamento valor do filtro (form) para id_perfil na base */
    public const PERFIL_MAP = [
        'estagiario' => 'ESTAGIARIO',
        'servidor'   => 'SERVIDOR',
        'diretora'   => 'DIRETORA',
        'magistrada' => 'MAGISTRADA',
        'admin'      => 'ADMIN',
        'estudante'  => 'ESTUDANTE',
    ];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lista apenas servidores internos (usuarios com perfil diferente de só ESTUDANTE).
     * Retorna todos os registros filtrados (sem LIMIT); paginação é feita no serviço após merge com estudantes.
     *
     * @return array<int, array>
     */
    public function listServidores(?string $perfilFilter): array
    {
        [$whereClause, $params] = $this->buildServidoresFilter($perfilFilter);

        $sql = "SELECT v.id_usuario, v.nome, v.sobrenome, v.nome_completo, v.cpf, v.email, v.cargo, v.instituicao, v.telefone, v.ultimo_acesso, v.perfis,
                COALESCE(NULLIF(TRIM(v.setores), ''), NULLIF(TRIM(v.instituicao), ''), '') AS setores,
                COALESCE(tl.bloqueado, 0) AS bloqueado_login,
                COALESCE(u.cadastro_ativo, 1) AS cadastro_ativo
                FROM v_usuarios_completo v
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                LEFT JOIN tentativas_login tl ON v.cpf = tl.cpf AND tl.bloqueado = 1
                " . $whereClause . "
                ORDER BY v.nome_completo ASC";

        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $i => $row) {
            $rows[$i]['status'] = $this->deriveStatus($row);
            $rows[$i]['tipo'] = 'interno';
            $rows[$i]['origem'] = 'usuarios';
        }

        return $rows;
    }

    public function countServidores(?string $perfilFilter): int
    {
        [$whereClause, $params] = $this->buildServidoresFilter($perfilFilter);
        $sql = 'SELECT COUNT(DISTINCT v.id_usuario) AS total
                FROM v_usuarios_completo v
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                LEFT JOIN tentativas_login tl ON v.cpf = tl.cpf AND tl.bloqueado = 1
                ' . $whereClause;
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    /**
     * Contagem de servidores por perfil (para exibir nos filtros).
     * Chaves: estagiario, servidor, diretora, magistrada, admin.
     *
     * @return array<string, int>
     */
    public function countServidoresByPerfil(): array
    {
        $reverseMap = array_flip(self::PERFIL_MAP);
        unset($reverseMap['ESTUDANTE']);
        $result = array_fill_keys(array_values($reverseMap), 0);

        $sql = "SELECT up.perfil, COUNT(DISTINCT up.usuario) AS cnt
                FROM usuario_perfil up
                WHERE up.usuario IN (SELECT usuario FROM usuario_perfil WHERE perfil != 'ESTUDANTE')
                AND up.perfil != 'ESTUDANTE'
                GROUP BY up.perfil";
        $stmt = $this->connection->query($sql);
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $perfil = trim((string) ($row['perfil'] ?? ''));
            $key = $reverseMap[$perfil] ?? strtolower($perfil);
            if (isset($result[$key])) {
                $result[$key] = (int) ($row['cnt'] ?? 0);
            }
        }
        return $result;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildServidoresFilter(?string $perfilFilter): array
    {
        $conditions = ["v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE perfil != 'ESTUDANTE')"];
        $params = [];

        $perfilId = $perfilFilter !== null && $perfilFilter !== '' ? (self::PERFIL_MAP[$perfilFilter] ?? null) : null;
        if ($perfilId !== null) {
            $conditions[] = 'v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE perfil = :perfil_filter)';
            $params[':perfil_filter'] = $perfilId;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }

    private function deriveStatus(array $row): string
    {
        if (empty($row['cadastro_ativo'])) {
            return 'pendente';
        }
        if (!empty($row['bloqueado_login'])) {
            return 'bloqueado';
        }
        $ultimoAcesso = $row['ultimo_acesso'] ?? null;
        if ($ultimoAcesso === null || $ultimoAcesso === '') {
            return 'pendente';
        }
        return 'ativo';
    }

}
