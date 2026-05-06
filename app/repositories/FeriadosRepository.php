<?php
declare(strict_types=1);

final class FeriadosRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'feriados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function purgeExpiradosUnicosOuMoveis(\DateTimeInterface $hoje): int
    {
        $sql = sprintf(
            "DELETE FROM `%s` WHERE `ativo` = 1 AND `regra_data` IN ('unica', 'movel') AND `data_fim` < :hoje",
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':hoje', $hoje->format('Y-m-d'));
        $stmt->execute();

        return (int) $stmt->rowCount();
    }

    public function isDiaNaoUtil(\DateTimeInterface $data): bool
    {
        $date = $data->format('Y-m-d');
        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE `ativo` = 1 AND :date BETWEEN `data_inicio` AND `data_fim` LIMIT 1',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':date', $date);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Retorna o feriado/dia não útil ativo que inclui a data fornecida, ou null se não houver.
     *
     * @return array{id:string,tipo_feriado:string,data_inicio:string,data_fim:string,regra_data:string,descricao:?string,ativo:int}|null
     */
    public function getByDate(\DateTimeInterface $data): ?array
    {
        $date = $data->format('Y-m-d');
        $sql = sprintf(
            'SELECT `id`, `tipo_feriado`, `data_inicio`, `data_fim`, `regra_data`, `descricao`, `ativo`
             FROM `%s` WHERE `ativo` = 1 AND :date BETWEEN `data_inicio` AND `data_fim` LIMIT 1',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':date', $date);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Datas cobertas por feriados/dias não úteis ativos no intervalo [start, end] (inclusive).
     * Chave do array: Y-m-d; valor: true.
     *
     * @return array<string, true>
     */
    public function datasNaoUteisNoIntervalo(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $sql = sprintf(
            'SELECT `data_inicio`, `data_fim` FROM `%s`
             WHERE `ativo` = 1
               AND `data_inicio` <= :end AND `data_fim` >= :start',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':start', $start->format('Y-m-d'));
        $stmt->bindValue(':end', $end->format('Y-m-d'));
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $startDt = new DateTimeImmutable($start->format('Y-m-d'));
        $endDt = new DateTimeImmutable($end->format('Y-m-d'));
        $out = [];

        foreach ($rows as $row) {
            $ini = DateTimeImmutable::createFromFormat('Y-m-d', trim((string) ($row['data_inicio'] ?? '')));
            $fim = DateTimeImmutable::createFromFormat('Y-m-d', trim((string) ($row['data_fim'] ?? '')));
            if (!$ini instanceof DateTimeImmutable || !$fim instanceof DateTimeImmutable) {
                continue;
            }
            if ($ini < $startDt) {
                $ini = $startDt;
            }
            if ($fim > $endDt) {
                $fim = $endDt;
            }
            if ($ini > $fim) {
                continue;
            }
            for ($d = $ini; $d <= $fim; $d = $d->modify('+1 day')) {
                $out[$d->format('Y-m-d')] = true;
            }
        }

        return $out;
    }

    /**
     * @param array{tipo_feriado?: string, ativo?: int|null, q?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function fetchListPaginated(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];

        $tipo = trim((string) ($filters['tipo_feriado'] ?? ''));
        if ($tipo !== '') {
            $where[] = '`tipo_feriado` = :tipo';
            $params['tipo'] = $tipo;
        }

        if (array_key_exists('ativo', $filters) && $filters['ativo'] !== null && $filters['ativo'] !== '') {
            $where[] = '`ativo` = :ativo';
            $params['ativo'] = (int) (bool) $filters['ativo'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '`descricao` LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);
        $from = sprintf('FROM `%s` WHERE %s', $this->table, $whereSql);

        $countStmt = $this->connection->prepare("SELECT COUNT(*) {$from}");
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = sprintf(
            'SELECT `id`, `tipo_feriado`, `data_inicio`, `data_fim`, `regra_data`, `descricao`, `ativo`, `created_at`, `updated_at` %s ORDER BY `data_inicio` DESC, `id` DESC LIMIT :lim OFFSET :off',
            $from
        );
        $stmt = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => is_array($rows) ? $rows : [], 'total' => $total];
    }

    public function findById(string $id): ?array
    {
        $sql = sprintf(
            'SELECT `id`, `tipo_feriado`, `data_inicio`, `data_fim`, `regra_data`, `descricao`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `id` = ? LIMIT 1',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array{tipo_feriado: string, data_inicio: string, data_fim: string, regra_data: string, descricao: ?string, ativo: int} $data
     */
    public function insert(string $id, array $data): void
    {
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `tipo_feriado`, `data_inicio`, `data_fim`, `regra_data`, `descricao`, `ativo`) VALUES (:id, :tipo_feriado, :data_inicio, :data_fim, :regra_data, :descricao, :ativo)',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':tipo_feriado' => $data['tipo_feriado'],
            ':data_inicio' => $data['data_inicio'],
            ':data_fim' => $data['data_fim'],
            ':regra_data' => $data['regra_data'],
            ':descricao' => $data['descricao'],
            ':ativo' => $data['ativo'],
        ]);
    }

    /**
     * @param array{tipo_feriado: string, data_inicio: string, data_fim: string, regra_data: string, descricao: ?string, ativo: int} $data
     */
    public function update(string $id, array $data): int
    {
        $sql = sprintf(
            'UPDATE `%s` SET `tipo_feriado` = :tipo_feriado, `data_inicio` = :data_inicio, `data_fim` = :data_fim, `regra_data` = :regra_data, `descricao` = :descricao, `ativo` = :ativo WHERE `id` = :id',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':tipo_feriado' => $data['tipo_feriado'],
            ':data_inicio' => $data['data_inicio'],
            ':data_fim' => $data['data_fim'],
            ':regra_data' => $data['regra_data'],
            ':descricao' => $data['descricao'],
            ':ativo' => $data['ativo'],
        ]);

        return $stmt->rowCount();
    }

    public function deleteById(string $id): int
    {
        $stmt = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = ?', $this->table));
        $stmt->execute([$id]);

        return $stmt->rowCount();
    }
}

