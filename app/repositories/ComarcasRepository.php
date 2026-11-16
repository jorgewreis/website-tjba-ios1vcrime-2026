<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ComarcasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'comarcas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function findByTribunalUfNome(string $tribunalId, string $uf, string $nome): ?array
    {
        $uf = strtoupper(trim($uf));
        $nome = trim($nome);
        if ($tribunalId === '' || strlen($uf) !== 2 || $nome === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at` FROM `%s` WHERE `tribunal_id` = :tribunal_id AND `uf` = :uf AND `nome` = :nome LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':tribunal_id', $tribunalId);
        $statement->bindValue(':uf', $uf);
        $statement->bindValue(':nome', $nome);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * Busca a primeira comarca cujo nome ou cidade coincida com o termo (normalizado, sem acento).
     * Retorna ['id', 'uf', 'nome', 'cidade'] ou null.
     */
    public function findFirstByNomeOuCidade(string $nomeOuCidade, string $uf = ''): ?array
    {
        $term = $this->normalizeNomeCidade($nomeOuCidade);
        if ($term === '') {
            return null;
        }

        $uf = strtoupper(trim($uf));
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at` FROM `%s`' . ($uf !== '' ? ' WHERE `uf` = :uf' : ''),
                $this->table
            )
        );
        if ($uf !== '') { $statement->bindValue(':uf', $uf); }
        $statement->execute();

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $nomeNorm = $this->normalizeNomeCidade((string) ($row['nome'] ?? ''));
            $cidadeNorm = $this->normalizeNomeCidade((string) ($row['cidade'] ?? ''));
            if ($nomeNorm === $term || $cidadeNorm === $term) {
                return $row;
            }
        }
        return null;
    }

    public function correspondeIdCidadeUf(string $id, string $cidade, string $uf): bool
    {
        if (trim($id) === '' || trim($cidade) === '' || strlen(trim($uf)) !== 2) return false;
        $statement = $this->connection->prepare(sprintf('SELECT `uf`, `nome`, `cidade` FROM `%s` WHERE `id` = :id', $this->table));
        $statement->bindValue(':id', trim($id));
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false || strtoupper(trim((string) $row['uf'])) !== strtoupper(trim($uf))) return false;
        $term = $this->normalizeNomeCidade($cidade);
        return $term === $this->normalizeNomeCidade((string) $row['nome']) || $term === $this->normalizeNomeCidade((string) ($row['cidade'] ?? ''));
    }

    private function normalizeNomeCidade(string $s): string
    {
        $s = trim($s);
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[àáâãäå]/u', 'a', $s);
        $s = preg_replace('/[èéêë]/u', 'e', $s);
        $s = preg_replace('/[ìíîï]/u', 'i', $s);
        $s = preg_replace('/[òóôõö]/u', 'o', $s);
        $s = preg_replace('/[ùúûü]/u', 'u', $s);
        $s = preg_replace('/[ç]/u', 'c', $s);
        return $s;
    }

    /** Busca por UF e nome da comarca (cidade), sem tribunal. Útil quando há um tribunal padrão por UF. */
    public function findByUfNome(string $uf, string $nome): ?array
    {
        $uf = strtoupper(trim($uf));
        $nome = trim($nome);
        if (strlen($uf) !== 2 || $nome === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at` FROM `%s` WHERE `uf` = :uf AND `nome` = :nome LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':uf', $uf);
        $statement->bindValue(':nome', $nome);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** Retorna o id da comarca (existente ou recém-criada). cidade = nome da comarca quando null; status_cidade = 'Ativa'. */
    public function ensureComarca(string $tribunalId, string $uf, string $nome, ?string $cidade = null, string $statusCidade = 'Ativa'): string
    {
        $uf = strtoupper(trim($uf));
        $nome = trim($nome);
        if ($tribunalId === '' || strlen($uf) !== 2 || $nome === '') {
            throw new InvalidArgumentException('Tribunal, UF (2 caracteres) e nome da comarca são obrigatórios.');
        }

        $existing = $this->findByTribunalUfNome($tribunalId, $uf, $nome);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $cidadeVal = $cidade !== null && trim($cidade) !== '' ? trim($cidade) : $nome;

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'COM',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at`) VALUES (:id, :tribunal_id, :uf, :nome, :cidade, :status_cidade, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':tribunal_id', $tribunalId);
        $statement->bindValue(':uf', $uf);
        $statement->bindValue(':nome', $nome);
        $statement->bindValue(':cidade', $cidadeVal);
        $statement->bindValue(':status_cidade', $statusCidade);
        $statement->execute();

        return $id;
    }
}
