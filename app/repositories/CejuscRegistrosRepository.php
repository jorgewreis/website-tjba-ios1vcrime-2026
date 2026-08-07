<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CejuscRegistrosRepository
{
    public function __construct(private PDO $connection, private string $table = 'cejusc_registros')
    {
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filters);
        $sql = "SELECT `id`, `numero_processo`, `classe_codigo`, `classe_descricao`, `nome_parte`, `data_envio_cejusc`, `criado_por`, `atualizado_por`, `created_at`, `updated_at`
                FROM `{$this->table}`" . ($where !== '' ? " WHERE {$where}" : '') .
            ' ORDER BY `data_envio_cejusc` DESC, `created_at` DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM `{$this->table}`" . ($where !== '' ? " WHERE {$where}" : ''));
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function find(string $id): ?array
    {
        $stmt = $this->connection->prepare("SELECT * FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => trim($id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insert(array $data): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'CJR');
        $stmt = $this->connection->prepare("INSERT INTO `{$this->table}`
            (`id`, `numero_processo`, `classe_codigo`, `classe_descricao`, `nome_parte`, `data_envio_cejusc`, `criado_por`, `atualizado_por`)
            VALUES (:id, :processo, :classe_codigo, :classe_descricao, :parte, :data, :criado_por, :atualizado_por)");
        $stmt->execute([
            ':id' => $id,
            ':processo' => $data['numero_processo'],
            ':classe_codigo' => $data['classe_codigo'] ?? null,
            ':classe_descricao' => $data['classe_descricao'] ?? null,
            ':parte' => $data['nome_parte'],
            ':data' => $data['data_envio_cejusc'],
            ':criado_por' => $data['usuario_id'] ?? null,
            ':atualizado_por' => $data['usuario_id'] ?? null,
        ]);
        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->connection->prepare("UPDATE `{$this->table}` SET
            `numero_processo` = :processo, `classe_codigo` = :classe_codigo, `classe_descricao` = :classe_descricao,
            `nome_parte` = :parte, `data_envio_cejusc` = :data,
            `atualizado_por` = :usuario, `updated_at` = NOW() WHERE `id` = :id LIMIT 1");
        $stmt->execute([
            ':id' => $id,
            ':processo' => $data['numero_processo'],
            ':classe_codigo' => $data['classe_codigo'] ?? null,
            ':classe_descricao' => $data['classe_descricao'] ?? null,
            ':parte' => $data['nome_parte'],
            ':data' => $data['data_envio_cejusc'],
            ':usuario' => $data['usuario_id'] ?? null,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function where(array $filters): array
    {
        $clauses = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $clauses[] = '(`numero_processo` LIKE :q OR `nome_parte` LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $month = trim((string) ($filters['mes_envio'] ?? ''));
        if ($month !== '') {
            $clauses[] = '`data_envio_cejusc` >= :month_start AND `data_envio_cejusc` < :month_end';
            $params[':month_start'] = $month . '-01';
            $params[':month_end'] = date('Y-m-d', strtotime($month . '-01 +1 month'));
        }
        return [implode(' AND ', $clauses), $params];
    }
}
