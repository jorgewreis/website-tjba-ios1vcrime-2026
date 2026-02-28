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

    public function ensureProcess(string $formattedNumero, string $classeCodigo): void
    {
        $numero = $this->normalizeNumber($formattedNumero);
        $classeCodigo = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $classeCodigo));
        if ($numero === '' || $classeCodigo === '') {
            return;
        }

        $existing = $this->findByNumero($numero);
        if ($existing === null) {
            $this->insert($formattedNumero, $numero, $classeCodigo);
            return;
        }

        if (($existing['classe'] ?? '') !== $classeCodigo) {
            $this->updateClasse($formattedNumero, $numero, $classeCodigo);
        }
    }

    public function findByNumero(string $numero): ?array
    {
        $statement = $this->connection->prepare(
            sprintf('SELECT `id_proc`, `numero`, `classe`, `data_cadastro`, `data_atualiza` FROM `%s` WHERE REPLACE(REPLACE(`numero`, \'-\', \'\'), \'.\', \'\') = :numero LIMIT 1', $this->table)
        );
        $statement->bindValue(':numero', $this->normalizeNumber($numero));
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    private function insert(string $formattedNumero, string $digits, string $classeCodigo): void
    {
        $idProc = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id_proc',
            'PRC',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id_proc`, `numero`, `classe`, `data_cadastro`, `data_atualiza`) VALUES (:id_proc, :numero, :classe, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id_proc', $idProc);
        $statement->bindValue(':numero', $formattedNumero);
        $statement->bindValue(':classe', $classeCodigo);
        $statement->execute();
    }

    private function updateClasse(string $formattedNumero, string $digits, string $classeCodigo): void
    {
        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `classe` = :classe, `numero` = :numero, `data_atualiza` = NOW() WHERE REPLACE(REPLACE(`numero`, \'-\', \'\'), \'.\', \'\') = :digits LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':classe', $classeCodigo);
        $statement->bindValue(':numero', $formattedNumero);
        $statement->bindValue(':digits', $digits);
        $statement->execute();
    }

    private function normalizeNumber(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }
}
