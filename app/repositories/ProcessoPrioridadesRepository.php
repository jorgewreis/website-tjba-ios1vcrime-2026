<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/ProcessoPrioridadeOrigem.php';

final class ProcessoPrioridadesRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'processo_prioridades')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /** @return list<array{id: string, processo_id: string, codigo: string, origem: string, regra_detalhe: mixed, created_at: string}> */
    public function findByProcessoId(string $processoId): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `processo_id`, `codigo`, `origem`, `regra_detalhe`, `created_at` FROM `%s` WHERE `processo_id` = :processo_id ORDER BY `codigo` ASC',
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param list<string> $processoIds
     * @return array<string, list<array{id: string, processo_id: string, codigo: string, origem: string, regra_detalhe: mixed, created_at: string}>>
     */
    public function findByProcessoIds(array $processoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $processoIds), static function (string $id): bool {
            return $id !== '';
        })));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT `id`, `processo_id`, `codigo`, `origem`, `regra_detalhe`, `created_at` FROM `%s` WHERE `processo_id` IN (%s) ORDER BY `processo_id` ASC, `codigo` ASC',
            $this->table,
            $placeholders
        );
        $statement = $this->connection->prepare($sql);
        foreach ($ids as $i => $id) {
            $statement->bindValue($i + 1, $id);
        }
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $pid = trim((string) ($row['processo_id'] ?? ''));
            if ($pid === '') {
                continue;
            }
            if (!isset($out[$pid])) {
                $out[$pid] = [];
            }
            $out[$pid][] = $row;
        }

        return $out;
    }

    public function findOneByProcessoAndCodigo(string $processoId, string $codigo): ?array
    {
        $processoId = trim($processoId);
        $codigo = trim($codigo);
        if ($processoId === '' || $codigo === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `processo_id`, `codigo`, `origem`, `regra_detalhe`, `created_at` FROM `%s` WHERE `processo_id` = :processo_id AND `codigo` = :codigo LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':codigo', $codigo);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function insert(string $processoId, string $codigo, string $origem, $regraDetalhe = null): string
    {
        $processoId = trim($processoId);
        $codigo = trim($codigo);
        $origem = trim($origem);
        if ($processoId === '' || $codigo === '' || $origem === '') {
            throw new InvalidArgumentException('processo_id, codigo e origem são obrigatórios.');
        }

        $existing = $this->findOneByProcessoAndCodigo($processoId, $codigo);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PRP', 30);
        $json = null;
        if ($regraDetalhe !== null) {
            $json = is_string($regraDetalhe) ? $regraDetalhe : json_encode($regraDetalhe, JSON_UNESCAPED_UNICODE);
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_id`, `codigo`, `origem`, `regra_detalhe`, `created_at`) VALUES (:id, :processo_id, :codigo, :origem, :regra_detalhe, NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':codigo', $codigo);
        $statement->bindValue(':origem', $origem);
        $statement->bindValue(':regra_detalhe', $json);

        $statement->execute();

        return $id;
    }

    /**
     * Insere ou atualiza origem/regra (regra automática prevalece sobre manual no mesmo código).
     *
     * @param mixed|null $regraDetalhe
     */
    public function upsert(string $processoId, string $codigo, string $origem, $regraDetalhe = null): string
    {
        $processoId = trim($processoId);
        $codigo = trim($codigo);
        $origem = trim($origem);
        if ($processoId === '' || $codigo === '' || $origem === '') {
            throw new InvalidArgumentException('processo_id, codigo e origem são obrigatórios.');
        }

        $json = null;
        if ($regraDetalhe !== null) {
            $json = is_string($regraDetalhe) ? $regraDetalhe : json_encode($regraDetalhe, JSON_UNESCAPED_UNICODE);
        }

        $existing = $this->findOneByProcessoAndCodigo($processoId, $codigo);
        if ($existing === null) {
            return $this->insert($processoId, $codigo, $origem, $regraDetalhe);
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `origem` = :origem, `regra_detalhe` = :regra_detalhe WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':origem', $origem);
        $statement->bindValue(':regra_detalhe', $json);
        $statement->bindValue(':id', $existing['id']);
        $statement->execute();

        return (string) $existing['id'];
    }

    public function deleteByProcessoIdAndCodigo(string $processoId, string $codigo): bool
    {
        $processoId = trim($processoId);
        $codigo = trim($codigo);
        if ($processoId === '' || $codigo === '') {
            return false;
        }

        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `processo_id` = :processo_id AND `codigo` = :codigo LIMIT 1', $this->table)
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':codigo', $codigo);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /** Remove só se a origem for exatamente uma das indicadas (ex.: regra automática). */
    public function deleteByProcessoIdCodigoAndOrigens(string $processoId, string $codigo, array $origens): bool
    {
        $processoId = trim($processoId);
        $codigo = trim($codigo);
        $origens = array_values(array_filter(array_map('trim', $origens)));
        if ($processoId === '' || $codigo === '' || $origens === []) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($origens), '?'));
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `processo_id` = ? AND `codigo` = ? AND `origem` IN (%s) LIMIT 1',
            $this->table,
            $placeholders
        );
        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge([$processoId, $codigo], $origens));

        return $statement->rowCount() > 0;
    }

    /** Remove prioridades inseridas manualmente / guias para substituição em lote. */
    public function deleteByProcessoIdAndOrigensCadastroUsuario(string $processoId): int
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return 0;
        }

        $statement = $this->connection->prepare(
            sprintf(
                "DELETE FROM `%s` WHERE `processo_id` = :processo_id AND `origem` IN ('manual', 'cadastro_guias')",
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();

        return $statement->rowCount();
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

    /** @return list<string> distinct processo_id */
    public function listDistinctProcessoIds(): array
    {
        $statement = $this->connection->query(
            sprintf('SELECT DISTINCT `processo_id` FROM `%s` WHERE `processo_id` IS NOT NULL AND `processo_id` != \'\'', $this->table)
        );
        if ($statement === false) {
            return [];
        }
        $ids = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $id = trim((string) ($row['processo_id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
