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
    public function findFirstByNomeOuCidade(string $nomeOuCidade, ?string $uf = null, ?string $tribunalId = null): ?array
    {
        $term = $this->normalizeNomeCidade($nomeOuCidade);
        if ($term === '') {
            return null;
        }

        $uf = $uf === null ? '' : strtoupper(trim($uf));
        if ($uf !== '' && !preg_match('/^[A-Z]{2}$/D', $uf)) {
            return null;
        }
        $tribunalId = $tribunalId === null ? '' : trim($tribunalId);
        $where = [];
        if ($uf !== '') $where[] = '`uf` = :uf';
        if ($tribunalId !== '') $where[] = '`tribunal_id` = :tribunal';
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at` FROM `%s`%s',
                $this->table,
                $where === [] ? '' : ' WHERE ' . implode(' AND ', $where)
            )
        );
        if ($uf !== '') $statement->bindValue(':uf', $uf);
        if ($tribunalId !== '') $statement->bindValue(':tribunal', $tribunalId);
        $statement->execute();

        $matchesCidade = [];
        $matchesNome = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $nomeNorm = $this->normalizeNomeCidade((string) ($row['nome'] ?? ''));
            $cidadeNorm = $this->normalizeNomeCidade((string) ($row['cidade'] ?? ''));
            if ($uf === '' && ($nomeNorm === $term || $cidadeNorm === $term)) {
                return $row;
            }
            if ($cidadeNorm === $term) $matchesCidade[] = $row;
            elseif ($nomeNorm === $term) $matchesNome[] = $row;
        }
        $matches = $matchesCidade !== [] ? $matchesCidade : $matchesNome;
        return count($matches) === 1 ? $matches[0] : null;
    }

    /** Resolve uma cidade agregada à sua sede, sem escolher entre sedes ambíguas. */
    public function resolverSede(array $row, bool $bloquear = false): ?array
    {
        if (mb_strtolower(trim((string) ($row['status_cidade'] ?? '')), 'UTF-8') !== 'agregada') return $row;
        $nome = $this->normalizeNomeCidade((string) ($row['nome'] ?? ''));
        $tribunal = trim((string) ($row['tribunal_id'] ?? ''));
        $uf = strtoupper(trim((string) ($row['uf'] ?? '')));
        if ($nome === '' || $tribunal === '' || !preg_match('/^[A-Z]{2}$/D', $uf)) return null;
        $statement = $this->connection->prepare(sprintf(
            'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade` FROM `%s` WHERE `tribunal_id` = :tribunal AND `uf` = :uf',
            $this->table
        ));
        $statement->execute([':tribunal' => $tribunal, ':uf' => $uf]);
        $candidates = [];
        while (($candidate = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (mb_strtolower(trim((string) ($candidate['status_cidade'] ?? '')), 'UTF-8') !== 'agregada'
                && $this->normalizeNomeCidade((string) ($candidate['nome'] ?? '')) === $nome
                && $this->normalizeNomeCidade((string) ($candidate['cidade'] ?? '')) === $nome) $candidates[] = $candidate;
        }
        if (count($candidates) !== 1) return null;
        if (!$bloquear) return $candidates[0];
        $lock = $this->connection->prepare(sprintf(
            'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade` FROM `%s` WHERE `id` = :id LIMIT 1 FOR UPDATE',
            $this->table
        ));
        $lock->execute([':id' => $candidates[0]['id']]);
        $candidate = $lock->fetch(PDO::FETCH_ASSOC);
        if ($candidate === false || (string) ($candidate['tribunal_id'] ?? '') !== $tribunal
            || strtoupper(trim((string) ($candidate['uf'] ?? ''))) !== $uf
            || mb_strtolower(trim((string) ($candidate['status_cidade'] ?? '')), 'UTF-8') === 'agregada'
            || $this->normalizeNomeCidade((string) ($candidate['nome'] ?? '')) !== $nome
            || $this->normalizeNomeCidade((string) ($candidate['cidade'] ?? '')) !== $nome) return null;
        return $candidate;
    }

    public function existeNomeOuCidade(string $nomeOuCidade, string $uf, ?string $tribunalId = null): bool
    {
        $term = $this->normalizeNomeCidade($nomeOuCidade);
        $uf = strtoupper(trim($uf));
        if ($term === '' || !preg_match('/^[A-Z]{2}$/D', $uf)) return false;
        $tribunalId = $tribunalId === null ? '' : trim($tribunalId);
        $statement = $this->connection->prepare(sprintf('SELECT `nome`, `cidade` FROM `%s` WHERE `uf` = :uf%s', $this->table, $tribunalId === '' ? '' : ' AND `tribunal_id` = :tribunal'));
        $params = [':uf' => $uf];
        if ($tribunalId !== '') $params[':tribunal'] = $tribunalId;
        $statement->execute($params);
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($this->normalizeNomeCidade((string) ($row['nome'] ?? '')) === $term || $this->normalizeNomeCidade((string) ($row['cidade'] ?? '')) === $term) return true;
        }
        return false;
    }

    public function resolverCidadeUf(string $id, string $cidade, string $uf, bool $bloquear = false): ?array
    {
        $cidade = $this->normalizeNomeCidade($cidade);
        $uf = strtoupper(trim($uf));
        if (trim($id) === '' || $cidade === '' || !preg_match('/^[A-Z]{2}$/D', $uf)) return null;
        $statement = $this->connection->prepare(sprintf(
            'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade` FROM `%s` WHERE `id` = :id LIMIT 1%s',
            $this->table,
            $bloquear ? ' FOR UPDATE' : ''
        ));
        $statement->bindValue(':id', trim($id));
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false || strtoupper(trim((string) $row['uf'])) !== $uf
            || ($this->normalizeNomeCidade((string) ($row['nome'] ?? '')) !== $cidade && $this->normalizeNomeCidade((string) ($row['cidade'] ?? '')) !== $cidade)) return null;
        return $this->resolverSede($row, $bloquear);
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

    /** Resolve uma localidade para a comarca-sede sem escolher entre candidatos ambíguos. */
    public function resolveSedePorCidadeUf(string $cidade, string $uf): ?array
    {
        $uf = strtoupper(trim($uf));
        $term = $this->normalizeNomeCidade($cidade);
        if (strlen($uf) !== 2 || $term === '') return null;
        $statement = $this->connection->prepare(sprintf('SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade` FROM `%s` WHERE `uf` = :uf', $this->table));
        $statement->execute([':uf' => $uf]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $cities = array_values(array_filter($rows, fn(array $row): bool => $this->normalizeNomeCidade((string) ($row['cidade'] ?? '')) === $term));
        $matches = $cities ?: array_values(array_filter($rows, fn(array $row): bool => $this->normalizeNomeCidade((string) ($row['nome'] ?? '')) === $term));
        if ($matches === []) return null;
        if (count($matches) !== 1) throw new InvalidArgumentException('Localidade ambígua para a UF informada.');
        return $this->sedeDaLocalidade($matches[0], $rows);
    }

    /** Valida a seleção recebida e devolve sempre o registro canônico da sede. */
    public function resolveSedePorIdCidadeUf(string $id, string $cidade, string $uf): ?array
    {
        if (trim($id) === '' || trim($cidade) === '' || strlen(trim($uf)) !== 2) return null;
        $lock = $this->connection->inTransaction() && $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->connection->prepare(sprintf('SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade` FROM `%s` WHERE `uf` = :uf%s', $this->table, $lock));
        $statement->execute([':uf' => strtoupper(trim($uf))]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $selected = array_values(array_filter($rows, fn(array $row): bool => (string) $row['id'] === trim($id)));
        if (count($selected) !== 1) return null;
        $term = $this->normalizeNomeCidade($cidade);
        if (!in_array($term, [$this->normalizeNomeCidade((string) $selected[0]['nome']), $this->normalizeNomeCidade((string) ($selected[0]['cidade'] ?? ''))], true)) return null;
        return $this->sedeDaLocalidade($selected[0], $rows);
    }

    private function sedeDaLocalidade(array $localidade, array $rows): array
    {
        $nome = $this->normalizeNomeCidade((string) ($localidade['nome'] ?? ''));
        $tribunal = (string) ($localidade['tribunal_id'] ?? '');
        if ($nome === '' || $tribunal === '') throw new InvalidArgumentException('Comarca-sede indisponível para a localidade.');
        $sedes = array_values(array_filter($rows, function (array $row) use ($nome, $tribunal): bool {
            return (string) ($row['tribunal_id'] ?? '') === $tribunal
                && $this->normalizeNomeCidade((string) ($row['nome'] ?? '')) === $nome
                && $this->normalizeNomeCidade((string) ($row['cidade'] ?? '')) === $nome
                && $this->normalizeNomeCidade((string) ($row['status_cidade'] ?? '')) !== 'agregada';
        }));
        if (count($sedes) !== 1) throw new InvalidArgumentException('Comarca-sede ausente ou ambígua para a localidade.');
        return $sedes[0];
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
