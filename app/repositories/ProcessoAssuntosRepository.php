<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/ProcessoAssuntoOrigem.php';

final class ProcessoAssuntosRepository
{
    private PDO $connection;
    private string $table;
    private string $assuntosTable;

    public function __construct(PDO $connection, string $table = 'processo_assuntos', string $assuntosTable = 'assuntos')
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->assuntosTable = $assuntosTable;
    }

    /**
     * @return list<array{
     *   id: string,
     *   processo_id: string,
     *   assunto_id: string,
     *   origem: string,
     *   created_at: string,
     *   updated_at: ?string,
     *   assunto_codigo: string,
     *   assunto_descricao: string
     * }>
     */
    public function findByProcessoId(string $processoId): array
    {
        $processoId = trim((string) $processoId);
        if ($processoId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT pa.`id`, pa.`processo_id`, pa.`assunto_id`, pa.`origem`, pa.`created_at`, pa.`updated_at`, a.`codigo` AS assunto_codigo, a.`descricao` AS assunto_descricao
                 FROM `%s` pa
                 INNER JOIN `%s` a ON a.`id` = pa.`assunto_id`
                 WHERE pa.`processo_id` = :processo_id
                 ORDER BY a.`codigo` ASC, a.`descricao` ASC',
                $this->table,
                $this->assuntosTable
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    /**
     * @param list<string> $processoIds
     * @return array<string, list<array{
     *   id: string,
     *   processo_id: string,
     *   assunto_id: string,
     *   origem: string,
     *   created_at: string,
     *   updated_at: ?string,
     *   assunto_codigo: string,
     *   assunto_descricao: string
     * }>>
     */
    public function findByProcessoIds(array $processoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $processoIds), static fn(string $id): bool => $id !== '')));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT pa.`id`, pa.`processo_id`, pa.`assunto_id`, pa.`origem`, pa.`created_at`, pa.`updated_at`, a.`codigo` AS assunto_codigo, a.`descricao` AS assunto_descricao
                 FROM `%s` pa
                 INNER JOIN `%s` a ON a.`id` = pa.`assunto_id`
                 WHERE pa.`processo_id` IN (%s)
                 ORDER BY pa.`processo_id` ASC, a.`codigo` ASC, a.`descricao` ASC',
                $this->table,
                $this->assuntosTable,
                $placeholders
            )
        );
        foreach ($ids as $i => $id) {
            $statement->bindValue($i + 1, $id);
        }
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false || $rows === []) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $pid = trim((string) ($row['processo_id'] ?? ''));
            if ($pid === '') {
                continue;
            }
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [];
            }
            $grouped[$pid][] = $row;
        }

        return $grouped;
    }

    /**
     * Cria ou atualiza vínculo idempotente.
     * @return bool true quando inseriu vínculo novo; false quando vínculo já existia
     */
    public function upsert(string $processoId, string $assuntoId, string $origem = ProcessoAssuntoOrigem::MANUAL): bool
    {
        $processoId = trim((string) $processoId);
        $assuntoId = trim((string) $assuntoId);
        $origem = trim((string) $origem);
        if ($processoId === '' || $assuntoId === '') {
            throw new InvalidArgumentException('processo_id e assunto_id são obrigatórios.');
        }
        if ($origem === '') {
            $origem = ProcessoAssuntoOrigem::MANUAL;
        }

        $existing = $this->findOneByProcessoAndAssunto($processoId, $assuntoId);
        if ($existing !== null) {
            $statement = $this->connection->prepare(
                sprintf(
                    'UPDATE `%s` SET `origem` = :origem, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                    $this->table
                )
            );
            $statement->bindValue(':id', (string) $existing['id']);
            $statement->bindValue(':origem', $origem);
            $statement->execute();

            return false;
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'PAS',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_id`, `assunto_id`, `origem`, `created_at`, `updated_at`) VALUES (:id, :processo_id, :assunto_id, :origem, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':assunto_id', $assuntoId);
        $statement->bindValue(':origem', $origem);
        $statement->execute();

        return true;
    }

    public function deleteByProcessoAndAssunto(string $processoId, string $assuntoId): void
    {
        $processoId = trim((string) $processoId);
        $assuntoId = trim((string) $assuntoId);
        if ($processoId === '' || $assuntoId === '') {
            throw new InvalidArgumentException('processo_id e assunto_id são obrigatórios.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'DELETE FROM `%s` WHERE `processo_id` = :processo_id AND `assunto_id` = :assunto_id',
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':assunto_id', $assuntoId);
        $statement->execute();
    }

    public function findOneByProcessoAndAssunto(string $processoId, string $assuntoId): ?array
    {
        $processoId = trim((string) $processoId);
        $assuntoId = trim((string) $assuntoId);
        if ($processoId === '' || $assuntoId === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `processo_id`, `assunto_id`, `origem`, `created_at`, `updated_at`
                 FROM `%s`
                 WHERE `processo_id` = :processo_id AND `assunto_id` = :assunto_id
                 LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':assunto_id', $assuntoId);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * Reconciliacao idempotente da origem DataJud:
     * - adiciona vínculos faltantes com origem datajud;
     * - remove vínculos datajud que não vieram no lote atual;
     * - preserva vínculos manuais.
     *
     * @param list<string> $assuntoIdsDataJud
     * @return array{inserted:int, removed:int, unchanged:int}
     */
    public function syncDataJudAssuntos(string $processoId, array $assuntoIdsDataJud): array
    {
        $processoId = trim((string) $processoId);
        if ($processoId === '') {
            throw new InvalidArgumentException('processo_id é obrigatório.');
        }

        $novosIds = array_values(array_unique(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $assuntoIdsDataJud), static fn(string $id): bool => $id !== '')));

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `assunto_id`, `origem` FROM `%s` WHERE `processo_id` = :processo_id',
                $this->table
            )
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();
        $existingRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($existingRows === false) {
            $existingRows = [];
        }

        $existingAll = [];
        $existingDataJud = [];
        foreach ($existingRows as $row) {
            $aid = trim((string) ($row['assunto_id'] ?? ''));
            if ($aid !== '') {
                $existingAll[$aid] = true;
            }
            $origem = trim((string) ($row['origem'] ?? ''));
            if ($origem !== ProcessoAssuntoOrigem::DATAJUD) {
                continue;
            }
            if ($aid !== '') {
                $existingDataJud[$aid] = true;
            }
        }

        $novosMap = [];
        foreach ($novosIds as $id) {
            $novosMap[$id] = true;
        }

        // Vínculo manual existente satisfaz a presença do assunto, mas mantém
        // sua origem. upsert() alteraria essa origem para datajud.
        $toInsert = array_values(array_diff($novosIds, array_keys($existingAll)));
        $toRemove = array_values(array_diff(array_keys($existingDataJud), $novosIds));

        $inserted = 0;
        foreach ($toInsert as $assuntoId) {
            $this->upsert($processoId, $assuntoId, ProcessoAssuntoOrigem::DATAJUD);
            $inserted++;
        }

        $removed = 0;
        if ($toRemove !== []) {
            $placeholders = implode(', ', array_fill(0, count($toRemove), '?'));
            $delete = $this->connection->prepare(
                sprintf(
                    'DELETE FROM `%s` WHERE `processo_id` = ? AND `origem` = ? AND `assunto_id` IN (%s)',
                    $this->table,
                    $placeholders
                )
            );
            $delete->bindValue(1, $processoId);
            $delete->bindValue(2, ProcessoAssuntoOrigem::DATAJUD);
            foreach ($toRemove as $i => $assuntoId) {
                $delete->bindValue($i + 3, $assuntoId);
            }
            $delete->execute();
            $removed = $delete->rowCount();
        }

        $unchanged = count($novosIds) - $inserted;
        if ($unchanged < 0) {
            $unchanged = 0;
        }

        return ['inserted' => $inserted, 'removed' => $removed, 'unchanged' => $unchanged];
    }
}
