<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProcessosPartesRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_REU = 'reu';
    public const TIPO_INDICIADO = 'indiciado';
    public const TIPO_SENTENCIADO = 'sentenciado';
    public const TIPO_REQUERIDO = 'requerido';
    public const TIPO_VITIMA = 'vitima';
    public const TIPO_REQUERENTE = 'requerente';
    public const TIPO_TESTEMUNHA_DEFESA = 'testemunha_defesa';
    public const TIPO_TESTEMUNHA_DENUNCIA = 'testemunha_denuncia';
    public const TIPO_ADVOGADO = 'advogado';
    public const TIPO_DEFENSOR_PUBLICO = 'defensor_publico';

    public const POLO_PASSIVO = 'passivo';
    public const POLO_ATIVO = 'ativo';
    public const POLO_TERCEIROS = 'terceiros';

    private const TIPOS_VALIDOS = [
        self::TIPO_REU,
        self::TIPO_INDICIADO,
        self::TIPO_SENTENCIADO,
        self::TIPO_REQUERIDO,
        self::TIPO_VITIMA,
        self::TIPO_REQUERENTE,
        self::TIPO_TESTEMUNHA_DEFESA,
        self::TIPO_TESTEMUNHA_DENUNCIA,
        self::TIPO_ADVOGADO,
        self::TIPO_DEFENSOR_PUBLICO,
    ];

    private const POLOS_VALIDOS = [
        self::POLO_PASSIVO,
        self::POLO_ATIVO,
        self::POLO_TERCEIROS,
    ];

    public function __construct(PDO $connection, string $table = 'processos_partes')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function addParte(string $processoId, string $parteId, string $tipoParte, string $polo, array $prioridades = []): string
    {
        $processoId = trim($processoId);
        $parteId = trim($parteId);
        $tipoParte = trim($tipoParte);
        $polo = trim($polo);

        if ($processoId === '' || $parteId === '') {
            throw new InvalidArgumentException('processo_id e parte_id são obrigatórios.');
        }
        if (!in_array($tipoParte, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException('Tipo de parte inválido.');
        }
        if (!in_array($polo, self::POLOS_VALIDOS, true)) {
            throw new InvalidArgumentException('Polo inválido.');
        }

        $existente = $this->findByProcessoAndParte($processoId, $parteId);
        if ($existente !== null) {
            throw new InvalidArgumentException('Esta pessoa já está vinculada a este processo. Uma pessoa não pode ser cadastrada mais de uma vez no mesmo processo.');
        }

        $flags = $this->normalizePrioridades($prioridades);
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PPR', 30);
        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_id`, `parte_id`, `tipo_parte`, `polo`, `prioridade_mulher`, `prioridade_crianca_adolescente`, `prioridade_idoso`, `prioridade_pessoa_deficiencia`, `created_at`, `updated_at`) VALUES (:id, :processo_id, :parte_id, :tipo_parte, :polo, :prioridade_mulher, :prioridade_crianca_adolescente, :prioridade_idoso, :prioridade_pessoa_deficiencia, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':parte_id', $parteId);
        $statement->bindValue(':tipo_parte', $tipoParte);
        $statement->bindValue(':polo', $polo);
        $statement->bindValue(':prioridade_mulher', $flags['prioridade_mulher'], PDO::PARAM_INT);
        $statement->bindValue(':prioridade_crianca_adolescente', $flags['prioridade_crianca_adolescente'], PDO::PARAM_INT);
        $statement->bindValue(':prioridade_idoso', $flags['prioridade_idoso'], PDO::PARAM_INT);
        $statement->bindValue(':prioridade_pessoa_deficiencia', $flags['prioridade_pessoa_deficiencia'], PDO::PARAM_INT);
        $statement->execute();

        return $id;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT pp.`id`, pp.`processo_id`, pp.`parte_id`, pp.`tipo_parte`, pp.`polo`, pp.`prioridade_mulher`, pp.`prioridade_crianca_adolescente`, pp.`prioridade_idoso`, pp.`prioridade_pessoa_deficiencia`, pp.`created_at`, pp.`updated_at`, p.`nome_completo`, p.`cpf`, proc.`numero_processo` FROM `%s` pp LEFT JOIN `partes` p ON p.`id` = pp.`parte_id` LEFT JOIN `processos` proc ON proc.`id` = pp.`processo_id` WHERE pp.`id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function findByParteId(string $parteId): array
    {
        $parteId = trim($parteId);
        if ($parteId === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT pp.`id`, pp.`processo_id`, pp.`parte_id`, pp.`tipo_parte`, pp.`polo`, pp.`prioridade_mulher`, pp.`prioridade_crianca_adolescente`, pp.`prioridade_idoso`, pp.`prioridade_pessoa_deficiencia`, pp.`created_at`, pp.`updated_at`, proc.`numero_processo`
             FROM `%s` pp
             LEFT JOIN `processos` proc ON proc.`id` = pp.`processo_id`
             WHERE pp.`parte_id` = :parte_id
             ORDER BY proc.`numero_processo` ASC, pp.`created_at` ASC',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':parte_id', $parteId);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    public function findByProcessoId(string $processoId, ?string $tipoParte = null): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT pp.`id`, pp.`processo_id`, pp.`parte_id`, pp.`tipo_parte`, pp.`polo`, pp.`prioridade_mulher`, pp.`prioridade_crianca_adolescente`, pp.`prioridade_idoso`, pp.`prioridade_pessoa_deficiencia`, pp.`created_at`, pp.`updated_at`, p.`cpf`, p.`nome_completo`, p.`sexo`, p.`data_nascimento`
             FROM `%s` pp
             INNER JOIN `partes` p ON p.`id` = pp.`parte_id`
             WHERE pp.`processo_id` = :processo_id',
            $this->table
        );
        if ($tipoParte !== null && trim($tipoParte) !== '') {
            $sql .= ' AND pp.`tipo_parte` = :tipo_parte';
        }
        $sql .= ' ORDER BY p.`nome_completo` ASC';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':processo_id', $processoId);
        if ($tipoParte !== null && trim($tipoParte) !== '') {
            $statement->bindValue(':tipo_parte', trim($tipoParte));
        }
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    /**
     * Nomes distintos de partes vinculadas ao processo com papel de acusado (réu, indiciado, sentenciado).
     *
     * @return list<string>
     */
    public function listNomesReusPorProcessoId(string $processoId): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }
        $tipos = [self::TIPO_REU, self::TIPO_INDICIADO, self::TIPO_SENTENCIADO];
        $inNames = [];
        foreach ($tipos as $i => $_) {
            $inNames[] = ':t' . $i;
        }
        $sql = sprintf(
            'SELECT DISTINCT p.`nome_completo`
             FROM `%s` pp
             INNER JOIN `partes` p ON p.`id` = pp.`parte_id`
             WHERE pp.`processo_id` = :processo_id
             AND pp.`tipo_parte` IN (%s)
             AND TRIM(COALESCE(p.`nome_completo`, \'\')) <> \'\'
             ORDER BY p.`nome_completo` ASC',
            $this->table,
            implode(', ', $inNames)
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':processo_id', $processoId);
        foreach ($tipos as $i => $t) {
            $statement->bindValue(':t' . $i, $t);
        }
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        if ($rows === false) {
            return [];
        }
        $out = [];
        foreach ($rows as $nome) {
            $n = trim((string) $nome);
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $processoIds
     * @return array<string, int> processo_id => total_partes
     */
    public function countByProcessoIds(array $processoIds): array
    {
        $processoIds = array_values(array_filter(array_map(static function ($id) {
            return trim((string) $id);
        }, $processoIds), static function ($id) {
            return $id !== '';
        }));
        if ($processoIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($processoIds as $i => $id) {
            $k = ':p' . $i;
            $placeholders[] = $k;
            $params[$k] = $id;
        }
        $sql = sprintf(
            'SELECT `processo_id`, COUNT(*) AS `total`
             FROM `%s`
             WHERE `processo_id` IN (%s)
             GROUP BY `processo_id`',
            $this->table,
            implode(', ', $placeholders)
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $pid = trim((string) ($row['processo_id'] ?? ''));
            if ($pid !== '') {
                $out[$pid] = (int) ($row['total'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * Réus / acusados vinculados ao processo (polo passivo: réu, indiciado, sentenciado).
     *
     * @return list<array<string, mixed>>
     */
    public function findAcusadosByProcessoId(string $processoId): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }
        $tipos = [self::TIPO_REU, self::TIPO_INDICIADO, self::TIPO_SENTENCIADO];
        $inList = implode(', ', array_fill(0, count($tipos), '?'));
        $sql = sprintf(
            'SELECT pp.`id`, pp.`processo_id`, pp.`parte_id`, pp.`tipo_parte`, pp.`polo`, p.`nome_completo`
             FROM `%s` pp
             INNER JOIN `partes` p ON p.`id` = pp.`parte_id`
             WHERE pp.`processo_id` = ? AND pp.`polo` = ? AND pp.`tipo_parte` IN (%s)
             ORDER BY p.`nome_completo` ASC',
            $this->table,
            $inList
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(1, $processoId);
        $statement->bindValue(2, self::POLO_PASSIVO);
        $i = 3;
        foreach ($tipos as $t) {
            $statement->bindValue($i, $t);
            $i++;
        }
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * Parte menor no cadastro processual: idade &lt; 18 pela data de nascimento ou flag ECA no vínculo.
     */
    public function countMenoresPorIdadeOuFlagNoProcesso(string $processoId): int
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return 0;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` pp
             INNER JOIN `partes` p ON p.`id` = pp.`parte_id`
             WHERE pp.`processo_id` = :processo_id
               AND (
                   pp.`prioridade_crianca_adolescente` = 1
                   OR (
                       p.`data_nascimento` IS NOT NULL
                       AND TIMESTAMPDIFF(YEAR, p.`data_nascimento`, CURDATE()) < 18
                   )
               )',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function delete(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function deleteByProcessoId(string $processoId): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return;
        }

        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `processo_id` = :processo_id', $this->table)
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = [
            'tipo_parte',
            'polo',
            'prioridade_mulher',
            'prioridade_crianca_adolescente',
            'prioridade_idoso',
            'prioridade_pessoa_deficiencia',
        ];
        $set = [];
        $params = [':id' => trim($id)];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $set[] = sprintf('`%s` = :%s', $column, $column);
            if (str_starts_with($column, 'prioridade_')) {
                $params[':' . $column] = (int) ((bool) $payload[$column]);
            } elseif ($column === 'tipo_parte') {
                $val = trim((string) $payload[$column]);
                if (!in_array($val, self::TIPOS_VALIDOS, true)) {
                    throw new InvalidArgumentException('Tipo de parte inválido.');
                }
                $params[':' . $column] = $val;
            } else {
                $params[':' . $column] = trim((string) $payload[$column]);
            }
        }

        if (empty($set)) {
            return false;
        }

        $set[] = '`updated_at` = NOW()';
        $statement = $this->connection->prepare(
            sprintf('UPDATE `%s` SET %s WHERE `id` = :id LIMIT 1', $this->table, implode(', ', $set))
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * Verifica se já existe vínculo da parte com o processo (uma pessoa só pode aparecer uma vez por processo).
     */
    public function findByProcessoAndParte(string $processoId, string $parteId): ?array
    {
        $processoId = trim($processoId);
        $parteId = trim($parteId);
        if ($processoId === '' || $parteId === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `processo_id`, `parte_id`, `tipo_parte`, `polo`, `prioridade_mulher`, `prioridade_crianca_adolescente`, `prioridade_idoso`, `prioridade_pessoa_deficiencia`, `created_at`, `updated_at` FROM `%s` WHERE `processo_id` = :processo_id AND `parte_id` = :parte_id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':parte_id', $parteId);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    private function findOne(string $processoId, string $parteId, string $tipoParte): ?array
    {
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `processo_id`, `parte_id`, `tipo_parte`, `polo`, `prioridade_mulher`, `prioridade_crianca_adolescente`, `prioridade_idoso`, `prioridade_pessoa_deficiencia`, `created_at`, `updated_at` FROM `%s` WHERE `processo_id` = :processo_id AND `parte_id` = :parte_id AND `tipo_parte` = :tipo_parte LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':parte_id', $parteId);
        $statement->bindValue(':tipo_parte', $tipoParte);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    private function normalizePrioridades(array $prioridades): array
    {
        return [
            'prioridade_mulher' => !empty($prioridades['prioridade_mulher']) ? 1 : 0,
            'prioridade_crianca_adolescente' => !empty($prioridades['prioridade_crianca_adolescente']) ? 1 : 0,
            'prioridade_idoso' => !empty($prioridades['prioridade_idoso']) ? 1 : 0,
            'prioridade_pessoa_deficiencia' => !empty($prioridades['prioridade_pessoa_deficiencia']) ? 1 : 0,
        ];
    }
}
