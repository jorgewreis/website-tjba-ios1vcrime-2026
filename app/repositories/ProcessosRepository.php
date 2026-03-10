<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProcessosRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'processos')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /** Retorna o id do processo (existente ou recém-criado). numero_processo formatado (ex. CNJ); classe_id opcional (null = classe não informada); data_fato e sigiloso opcionais. */
    public function ensureProcess(string $numeroProcesso, ?string $classeId = null, ?string $dataFato = null, bool $sigiloso = false): string
    {
        $digits = $this->normalizeNumber($numeroProcesso);
        if ($digits === '') {
            throw new InvalidArgumentException('Número do processo é obrigatório.');
        }

        $existing = $this->findByNumero($numeroProcesso);
        if ($existing !== null) {
            $this->updateIfNeeded($existing['id'], $classeId, $dataFato, $sigiloso);
            return (string) $existing['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'PRC',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `numero_processo`, `classe_id`, `data_fato`, `sigiloso`, `created_at`, `updated_at`) VALUES (:id, :numero_processo, :classe_id, :data_fato, :sigiloso, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':numero_processo', $numeroProcesso);
        $statement->bindValue(':classe_id', $classeId !== null && $classeId !== '' ? $classeId : null);
        $statement->bindValue(':data_fato', $dataFato);
        $statement->bindValue(':sigiloso', $sigiloso ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();

        return $id;
    }

    public function findByNumero(string $numero): ?array
    {
        $digits = $this->normalizeNumber($numero);
        if ($digits === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `numero_processo`, `classe_id`, `data_fato`, `sigiloso`, `created_at`, `updated_at` FROM `%s` WHERE REPLACE(REPLACE(`numero_processo`, \'-\', \'\'), \'.\', \'\') = :digits LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':digits', $digits);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function findById(string $id): ?array
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `numero_processo`, `classe_id`, `data_fato`, `sigiloso`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params, $joinClause] = $this->buildListFilter($filters);

        $groupBy = $joinClause !== '' ? ' GROUP BY proc.`id` ' : '';
        $sql = sprintf(
            'SELECT proc.`id`, proc.`numero_processo`, proc.`classe_id`, proc.`data_fato`, proc.`sigiloso`, proc.`created_at`, proc.`updated_at` FROM `%s` proc %s %s %s ORDER BY proc.`numero_processo` ASC LIMIT :limit OFFSET :offset',
            $this->table,
            $joinClause,
            $whereClause ? 'WHERE ' . $whereClause : '',
            $groupBy
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    public function count(array $filters): int
    {
        [$whereClause, $params, $joinClause] = $this->buildListFilter($filters);
        $sql = sprintf('SELECT COUNT(DISTINCT proc.`id`) AS total FROM `%s` proc %s %s', $this->table, $joinClause, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: string} */
    private function buildListFilter(array $filters): array
    {
        $conditions = [];
        $params = [];
        $joinClause = '';

        $numero = trim((string) ($filters['numero'] ?? $filters['numero_processo'] ?? ''));
        if ($numero !== '') {
            $digits = $this->normalizeNumber($numero);
            if ($digits !== '') {
                $conditions[] = 'REPLACE(REPLACE(proc.`numero_processo`, \'-\', \'\'), \'.\', \'\') LIKE :numero';
                $params[':numero'] = '%' . $digits . '%';
            }
        }

        $parteNome = trim((string) ($filters['parte_nome'] ?? $filters['nome'] ?? ''));
        if ($parteNome !== '') {
            $joinClause = ' INNER JOIN `processos_partes` pp ON pp.`processo_id` = proc.`id` INNER JOIN `partes` pt ON pt.`id` = pp.`parte_id` ';
            $conditions[] = 'pt.`nome_completo` LIKE :parte_nome';
            $params[':parte_nome'] = '%' . $parteNome . '%';
        }

        $where = $conditions === [] ? '' : implode(' AND ', $conditions);
        return [$where, $params, $joinClause];
    }

    private function updateIfNeeded(string $id, ?string $classeId, ?string $dataFato, bool $sigiloso): void
    {
        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `classe_id` = :classe_id, `data_fato` = COALESCE(:data_fato, `data_fato`), `sigiloso` = :sigiloso, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':classe_id', $classeId !== null && $classeId !== '' ? $classeId : null);
        $statement->bindValue(':data_fato', $dataFato);
        $statement->bindValue(':sigiloso', $sigiloso ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();
    }

    private function normalizeNumber(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }
}
