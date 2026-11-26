<?php
declare(strict_types=1);

final class SalaAudienciasPautaSequenceLockException extends RuntimeException
{
}

final class SalaAudienciasPautaSequenceLock
{
    private PDO $connection;
    private ?string $lockName = null;
    private bool $acquired = false;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function acquire(int $year, int $timeoutSeconds = 5, bool $schedule = false): void
    {
        if ($this->acquired || $year < 1) {
            throw new InvalidArgumentException('Ano ou estado do bloqueio anual inválido.');
        }

        if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            $this->acquired = true;
            return;
        }

        $database = $this->connection->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || trim($database) === '') {
            throw new RuntimeException('Não foi possível determinar o banco para bloquear o sequencial anual.');
        }

        // GET_LOCK aceita nomes de até 64 caracteres e pertence ao servidor MySQL,
        // por isso o hash inclui banco, tabela e ano.
        $prefix = $schedule ? 'fokus_pauta_ag_' : 'fokus_pauta_seq_';
        $this->lockName = $prefix . substr(
            hash('sha256', strtolower($database) . ':sala_audiencias_pauta:' . ($schedule ? 'agenda' : $year)),
            0,
            48
        );
        $statement = $this->connection->prepare('SELECT GET_LOCK(:lock_name, :timeout_seconds)');
        $statement->bindValue(':lock_name', $this->lockName);
        $statement->bindValue(':timeout_seconds', max(0, min(60, $timeoutSeconds)), PDO::PARAM_INT);
        $statement->execute();

        if ((int) $statement->fetchColumn() !== 1) {
            $this->lockName = null;
            throw new SalaAudienciasPautaSequenceLockException('A pauta está sendo atualizada; tente novamente.');
        }

        $this->acquired = true;
    }

    public function release(): bool
    {
        if (!$this->acquired) {
            return true;
        }

        $this->acquired = false;
        if ($this->lockName === null) {
            return true;
        }

        try {
            $statement = $this->connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->bindValue(':lock_name', $this->lockName);
            $statement->execute();
            $released = (int) $statement->fetchColumn() === 1;
            if (!$released) {
                error_log('Pauta de audiências: MySQL não confirmou a liberação do lock do sequencial anual.');
            }

            return $released;
        } catch (Throwable $exception) {
            error_log('Pauta de audiências: erro ao liberar o lock do sequencial anual (' . get_class($exception) . ').');
            return false;
        } finally {
            $this->lockName = null;
        }
    }
}
