<?php
declare(strict_types=1);

/**
 * Listagem de usuários na fonte única `usuarios` (internos e externos).
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

    /**
     * Perfis que caracterizam vínculo operacional interno, conforme
     * UserAccessHelper e docs/dashboard-acesso-interno-externo.md.
     * ESTUDANTE/CONSULTOR, isoladamente, não são perfis internos.
     */
    private const PERFIS_INTERNOS_SQL = "'ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA', 'SERVIDOR', 'ESTAGIARIO', 'CARTORIO', 'GABINETE', 'AUDIENCIA',
        'ESTAGIARIO-CARTORIO', 'ESTAGIARIO-GABINETE', 'ESTAGIARIO-AUDIENCIAS',
        'SERVIDOR-CARTORIO', 'SERVIDOR-GABINETE', 'SERVIDOR-AUDIENCIAS'";

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /** @return array<int, array> */
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

    /**
     * Lista usuários externos com perfil ESTUDANTE.
     *
     * @return array<int, array>
     */
    public function listExternos(?string $perfilFilter = null): array
    {
        $perfilCondition = $perfilFilter === 'estudante'
            ? "AND v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE UPPER(TRIM(perfil)) = 'ESTUDANTE')"
            : '';
        $sql = "SELECT v.id_usuario, v.nome, v.sobrenome, v.nome_completo, v.cpf, v.email, v.cargo,
                CASE WHEN EXISTS (SELECT 1 FROM usuario_perfil up_student WHERE up_student.usuario = v.id_usuario AND UPPER(TRIM(up_student.perfil)) = 'ESTUDANTE')
                     THEN 'Estudante' ELSE 'Consultor' END AS cargo_externo,
                v.instituicao, v.telefone, v.ultimo_acesso, v.perfis,
                'Sala de Audiências' AS setores,
                COALESCE(tl.bloqueado, 0) AS bloqueado_login,
                COALESCE(u.cadastro_ativo, 1) AS cadastro_ativo
                FROM v_usuarios_completo v
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                LEFT JOIN tentativas_login tl ON v.cpf = tl.cpf AND tl.bloqueado = 1
                WHERE v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE UPPER(TRIM(perfil)) IN ('ESTUDANTE', 'CONSULTOR'))
                " . $perfilCondition . "
                AND NOT EXISTS (
                    SELECT 1 FROM usuario_perfil up_interno
                    WHERE up_interno.usuario = v.id_usuario
                    AND UPPER(TRIM(up_interno.perfil)) IN (" . self::PERFIS_INTERNOS_SQL . ")
                )
                ORDER BY v.nome_completo ASC";
        $statement = $this->connection->prepare($sql);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $i => $row) {
            $rows[$i]['status'] = $this->deriveStatus($row);
            $rows[$i]['tipo'] = 'externo';
            $rows[$i]['origem'] = 'usuarios';
            if (trim((string) ($rows[$i]['cargo'] ?? '')) === '') {
                $rows[$i]['cargo'] = (string) ($rows[$i]['cargo_externo'] ?? 'Externo');
            }
            unset($rows[$i]['cargo_externo']);
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

    public function countExternos(?string $perfilFilter = null): int
    {
        $perfilCondition = $perfilFilter === 'estudante'
            ? "AND v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE UPPER(TRIM(perfil)) = 'ESTUDANTE')"
            : '';
        $sql = "SELECT COUNT(DISTINCT v.id_usuario) AS total
                FROM v_usuarios_completo v
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                WHERE v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE UPPER(TRIM(perfil)) IN ('ESTUDANTE', 'CONSULTOR'))
                " . $perfilCondition . "
                AND NOT EXISTS (
                    SELECT 1 FROM usuario_perfil up_interno
                    WHERE up_interno.usuario = v.id_usuario
                    AND UPPER(TRIM(up_interno.perfil)) IN (" . self::PERFIS_INTERNOS_SQL . ")
                )";
        $statement = $this->connection->prepare($sql);
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    public function countEstudantes(): int
    {
        $sql = "SELECT COUNT(DISTINCT v.id_usuario) AS total
                FROM v_usuarios_completo v
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                WHERE v.id_usuario IN (
                    SELECT usuario FROM usuario_perfil
                    WHERE UPPER(TRIM(perfil)) = 'ESTUDANTE'
                )";
        $statement = $this->connection->prepare($sql);
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function listSemPerfil(): array
    {
        $sql = "SELECT u.id_usuario, u.nome, u.sobrenome,
                       TRIM(CONCAT_WS(' ', u.nome, u.sobrenome)) AS nome_completo,
                       u.cpf, u.email, u.cargo, u.instituicao, u.telefone, u.ultimo_acesso,
                       u.instituicao AS setores, COALESCE(u.cadastro_ativo, 1) AS cadastro_ativo,
                       EXISTS(SELECT 1 FROM tentativas_login tl WHERE tl.cpf = u.cpf AND tl.bloqueado = 1) AS bloqueado_login
                  FROM usuarios u
                 WHERE NOT EXISTS (SELECT 1 FROM usuario_perfil up WHERE up.usuario = u.id_usuario AND up.perfil IS NOT NULL AND TRIM(up.perfil) <> '')
                 ORDER BY u.nome, u.sobrenome";
        $rows = $this->connection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['status'] = $this->deriveStatus($row);
            $row['tipo'] = 'sem_perfil';
            $row['origem'] = 'usuarios';
        }
        unset($row);
        return $rows;
    }

    public function countSemPerfil(): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios u WHERE NOT EXISTS (SELECT 1 FROM usuario_perfil up WHERE up.usuario = u.id_usuario AND up.perfil IS NOT NULL AND TRIM(up.perfil) <> '')";
        return (int) $this->connection->query($sql)->fetchColumn();
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

        $sql = "SELECT u.id_usuario AS usuario, up.perfil
                FROM usuario_perfil up
                INNER JOIN usuarios u ON u.id_usuario = up.usuario
                WHERE EXISTS (
                    SELECT 1 FROM v_usuarios_completo v
                    WHERE v.id_usuario = u.id_usuario
                )
                AND EXISTS (
                    SELECT 1 FROM usuario_perfil up_interno
                    WHERE up_interno.usuario = up.usuario
                    AND UPPER(TRIM(up_interno.perfil)) IN (" . self::PERFIS_INTERNOS_SQL . ")
                )
                AND UPPER(TRIM(up.perfil)) <> 'ESTUDANTE'";
        $stmt = $this->connection->query($sql);
        $usuarioBuckets = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $uid = trim((string) ($row['usuario'] ?? ''));
            $perfil = strtoupper(trim((string) ($row['perfil'] ?? '')));
            if ($uid === '' || $perfil === '') {
                continue;
            }
            if (!isset($usuarioBuckets[$uid])) {
                $usuarioBuckets[$uid] = [
                    'estagiario' => false,
                    'servidor' => false,
                    'diretora' => false,
                    'magistrada' => false,
                    'admin' => false,
                ];
            }
            if (in_array($perfil, ['ESTAGIARIO', 'ESTAGIARIO-CARTORIO', 'ESTAGIARIO-GABINETE', 'ESTAGIARIO-AUDIENCIAS'], true)) {
                $usuarioBuckets[$uid]['estagiario'] = true;
            }
            if (in_array($perfil, ['SERVIDOR', 'SERVIDOR-CARTORIO', 'SERVIDOR-GABINETE', 'SERVIDOR-AUDIENCIAS'], true)) {
                $usuarioBuckets[$uid]['servidor'] = true;
            }
            if ($perfil === 'DIRETORA') {
                $usuarioBuckets[$uid]['diretora'] = true;
            }
            if ($perfil === 'MAGISTRADA') {
                $usuarioBuckets[$uid]['magistrada'] = true;
            }
            if ($perfil === 'ADMIN') {
                $usuarioBuckets[$uid]['admin'] = true;
            }
        }
        foreach ($usuarioBuckets as $b) {
            foreach ($b as $k => $on) {
                if ($on && isset($result[$k])) {
                    $result[$k]++;
                }
            }
        }
        return $result;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildServidoresFilter(?string $perfilFilter): array
    {
        $conditions = ["(
            EXISTS (
                SELECT 1 FROM usuario_perfil up_interno
                WHERE up_interno.usuario = v.id_usuario
                AND UPPER(TRIM(up_interno.perfil)) IN (" . self::PERFIS_INTERNOS_SQL . ")
            )
            OR (
                EXISTS (
                    SELECT 1 FROM usuario_perfil up_any
                    WHERE up_any.usuario = v.id_usuario
                    AND up_any.perfil IS NOT NULL
                    AND TRIM(up_any.perfil) <> ''
                )
                AND NOT EXISTS (
                    SELECT 1 FROM usuario_perfil up_externo
                    WHERE up_externo.usuario = v.id_usuario
                    AND UPPER(TRIM(up_externo.perfil)) IN ('ESTUDANTE', 'CONSULTOR')
                )
            )
        )"];
        $params = [];

        $perfilId = $perfilFilter !== null && $perfilFilter !== '' ? (self::PERFIL_MAP[$perfilFilter] ?? null) : null;
        if ($perfilId !== null) {
            if ($perfilFilter === 'estagiario') {
                $conditions[] = "v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE UPPER(TRIM(perfil)) IN ('ESTAGIARIO', 'ESTAGIARIO-CARTORIO', 'ESTAGIARIO-GABINETE', 'ESTAGIARIO-AUDIENCIAS'))";
            } elseif ($perfilFilter === 'servidor') {
                $conditions[] = "v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE UPPER(TRIM(perfil)) IN ('SERVIDOR', 'SERVIDOR-CARTORIO', 'SERVIDOR-GABINETE', 'SERVIDOR-AUDIENCIAS'))";
            } else {
                $conditions[] = 'v.id_usuario IN (SELECT usuario FROM usuario_perfil WHERE UPPER(TRIM(perfil)) = :perfil_filter)';
                $params[':perfil_filter'] = $perfilId;
            }
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
        return 'ativo';
    }

}
