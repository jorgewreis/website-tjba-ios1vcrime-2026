<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ResponsibleProcessNotFound extends RuntimeException {}

final class ProcessResponsibleSectorService
{
    public function __construct(private PDO $connection) {}

    /**
     * @return array{process_id:string,previous_sector_id:?string,sector_id:?string,changed:bool}
     */
    public function assign(
        string $processId,
        ?string $sectorId,
        string $justification,
        string $actorId,
        string $remoteAddress
    ): array {
        $processId = trim($processId);
        $sectorId = $sectorId !== null ? trim($sectorId) : null;
        $actorId = trim($actorId);
        $justification = trim($justification);

        if ($processId === '' || strlen($processId) > 30) {
            throw new InvalidArgumentException('Identificador do processo inválido.');
        }
        if ($sectorId === '') {
            $sectorId = null;
        }
        if ($sectorId !== null && strlen($sectorId) > 30) {
            throw new InvalidArgumentException('Identificador do setor inválido.');
        }
        if ($actorId === '' || strlen($actorId) > 30) {
            throw new InvalidArgumentException('Identidade do operador inválida.');
        }
        $reasonLength = function_exists('mb_strlen') ? mb_strlen($justification, 'UTF-8') : strlen($justification);
        if ($justification === '' || $reasonLength > 500) {
            throw new InvalidArgumentException('Informe justificativa de até 500 caracteres.');
        }

        $remoteAddress = trim($remoteAddress);
        if ($remoteAddress === '' || strlen($remoteAddress) > 45) {
            $remoteAddress = '0.0.0.0';
        }

        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = null;
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        } else {
            $savepoint = 'process_sector_' . bin2hex(random_bytes(8));
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $driver = (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
            $lockClause = $driver === 'mysql' ? ' FOR UPDATE' : '';
            $processStatement = $this->connection->prepare(
                'SELECT `id`, `id_setor_responsavel` FROM `processos` WHERE `id` = :id LIMIT 1' . $lockClause
            );
            $processStatement->execute([':id' => $processId]);
            $process = $processStatement->fetch(PDO::FETCH_ASSOC);
            if ($process === false) {
                throw new ResponsibleProcessNotFound('Processo não encontrado.');
            }

            if ($sectorId !== null) {
                $sectorStatement = $this->connection->prepare(
                    'SELECT `id_setor` FROM `setores` WHERE `id_setor` = :id AND `ativo` = 1 LIMIT 1' . $lockClause
                );
                $sectorStatement->execute([':id' => $sectorId]);
                if ($sectorStatement->fetchColumn() === false) {
                    throw new InvalidArgumentException('Selecione um setor ativo existente.');
                }
            }

            $previous = trim((string) ($process['id_setor_responsavel'] ?? ''));
            $previous = $previous !== '' ? $previous : null;
            if ($previous === $sectorId) {
                $result = [
                    'process_id' => $processId,
                    'previous_sector_id' => $previous,
                    'sector_id' => $sectorId,
                    'changed' => false,
                ];
            } else {
                $update = $this->connection->prepare(
                    'UPDATE `processos` SET `id_setor_responsavel` = :sector_id, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id'
                );
                $update->execute([':sector_id' => $sectorId, ':id' => $processId]);

                $before = json_encode(
                    ['id_setor_responsavel' => $previous],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                $after = json_encode(
                    ['id_setor_responsavel' => $sectorId, 'justificativa' => $justification],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                $logId = IdGenerator::generateUniquePrefixedId(
                    $this->connection,
                    'logs_sistema',
                    'id_log_sistema',
                    'LGS',
                    30
                );
                $audit = $this->connection->prepare(
                    'INSERT INTO `logs_sistema` (`id_log_sistema`, `usuario`, `modulo`, `acao`, `tabela_afetada`, `registro_id`, `dados_anteriores`, `dados_novos`, `ip`, `data_hora`) '
                    . 'VALUES (:id, :actor, :module, :action, :table_name, :record_id, :before_data, :after_data, :ip, CURRENT_TIMESTAMP)'
                );
                $audit->execute([
                    ':id' => $logId,
                    ':actor' => $actorId,
                    ':module' => 'processos',
                    ':action' => 'setor-responsavel',
                    ':table_name' => 'processos',
                    ':record_id' => $processId,
                    ':before_data' => $before,
                    ':after_data' => $after,
                    ':ip' => $remoteAddress,
                ]);

                $result = [
                    'process_id' => $processId,
                    'previous_sector_id' => $previous,
                    'sector_id' => $sectorId,
                    'changed' => true,
                ];
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            } else {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $error) {
            try {
                if ($ownsTransaction) {
                    if ($this->connection->inTransaction()) {
                        $this->connection->rollBack();
                    }
                } elseif ($this->connection->inTransaction()) {
                    $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable $rollbackError) {
                error_log('Falha ao reverter atribuição de setor responsável (' . get_class($rollbackError) . ').');
                throw new RuntimeException('Falha ao reverter a atribuição auditada.', 0, $error);
            }
            throw $error;
        }
    }
}
