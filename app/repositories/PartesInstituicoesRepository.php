<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class PartesInstituicoesRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'partes_instituicoes')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `parte_id`, `instituicao_id`, `setor_id`, `profissao`, `cargo_funcao`, `data_inclusao`, `data_inicio`, `data_fim`, `ativo`, `observacoes`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function findByParteId(string $parteId, ?bool $ativo = null): array
    {
        $parteId = trim($parteId);
        if ($parteId === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT pi.`id`, pi.`parte_id`, pi.`instituicao_id`, pi.`setor_id`, pi.`profissao`, pi.`cargo_funcao`, pi.`data_inclusao`, pi.`data_inicio`, pi.`data_fim`, pi.`ativo`, pi.`observacoes`, pi.`created_at`, pi.`updated_at`, i.`nome` AS instituicao_nome, i.`nome_fantasia` AS instituicao_sigla FROM `%s` pi LEFT JOIN `instituicoes_publicas` i ON i.`id` = pi.`instituicao_id` WHERE pi.`parte_id` = :parte_id',
            $this->table
        );
        $params = [':parte_id' => $parteId];
        if ($ativo !== null) {
            $sql .= ' AND pi.`ativo` = :ativo';
            $params[':ativo'] = $ativo ? 1 : 0;
        }
        $sql .= ' ORDER BY pi.`data_inclusao` DESC, pi.`created_at` DESC';

        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function findByInstituicaoId(string $instituicaoId, ?bool $ativo = null): array
    {
        $instituicaoId = trim($instituicaoId);
        if ($instituicaoId === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT pi.`id`, pi.`parte_id`, pi.`instituicao_id`, pi.`setor_id`, pi.`profissao`, pi.`cargo_funcao`, pi.`data_inclusao`, pi.`data_inicio`, pi.`data_fim`, pi.`ativo`, pi.`observacoes`, pi.`created_at`, pi.`updated_at`, p.`nome_completo` AS parte_nome, p.`cpf` AS parte_cpf FROM `%s` pi LEFT JOIN `partes` p ON p.`id` = pi.`parte_id` WHERE pi.`instituicao_id` = :instituicao_id',
            $this->table
        );
        $params = [':instituicao_id' => $instituicaoId];
        if ($ativo !== null) {
            $sql .= ' AND pi.`ativo` = :ativo';
            $params[':ativo'] = $ativo ? 1 : 0;
        }
        $sql .= ' ORDER BY pi.`data_inclusao` DESC, p.`nome_completo` ASC';

        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $row): string
    {
        $parteId = trim((string) ($row['parte_id'] ?? ''));
        $instituicaoId = trim((string) ($row['instituicao_id'] ?? ''));
        $dataInclusao = trim((string) ($row['data_inclusao'] ?? ''));
        if ($parteId === '' || $instituicaoId === '' || $dataInclusao === '') {
            throw new InvalidArgumentException('parte_id, instituicao_id e data_inclusao são obrigatórios.');
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PIN', 30);
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `parte_id`, `instituicao_id`, `setor_id`, `profissao`, `cargo_funcao`, `data_inclusao`, `data_inicio`, `data_fim`, `ativo`, `observacoes`, `created_at`, `updated_at`) VALUES (:id, :parte_id, :instituicao_id, :setor_id, :profissao, :cargo_funcao, :data_inclusao, :data_inicio, :data_fim, :ativo, :observacoes, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':parte_id', $parteId);
        $statement->bindValue(':instituicao_id', $instituicaoId);
        $statement->bindValue(':setor_id', trim((string) ($row['setor_id'] ?? '')) ?: null);
        $statement->bindValue(':profissao', TextFormatter::formatProperName((string) ($row['profissao'] ?? ''), 120) ?: null);
        $statement->bindValue(':cargo_funcao', TextFormatter::formatProperName((string) ($row['cargo_funcao'] ?? ''), 120) ?: null);
        $statement->bindValue(':data_inclusao', $dataInclusao);
        $statement->bindValue(':data_inicio', trim((string) ($row['data_inicio'] ?? '')) ?: null);
        $statement->bindValue(':data_fim', trim((string) ($row['data_fim'] ?? '')) ?: null);
        $statement->bindValue(':ativo', isset($row['ativo']) ? (int) (bool) $row['ativo'] : 1, PDO::PARAM_INT);
        $statement->bindValue(':observacoes', trim((string) ($row['observacoes'] ?? '')) ?: null);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['setor_id', 'profissao', 'cargo_funcao', 'data_inclusao', 'data_inicio', 'data_fim', 'ativo', 'observacoes'];
        $set = [];
        $params = [':id' => trim($id)];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $value = $payload[$column];
            if ($column === 'ativo') {
                $value = (int) (bool) $value;
            } elseif (in_array($column, ['profissao', 'cargo_funcao'], true)) {
                $value = TextFormatter::formatProperName((string) $value, 120);
                $value = $value === '' ? null : $value;
            } elseif ($column === 'setor_id') {
                $value = trim((string) $value);
                $value = $value === '' ? null : $value;
            } else {
                $value = trim((string) $value);
                $value = $value === '' && in_array($column, ['data_inicio', 'data_fim', 'observacoes'], true) ? null : $value;
            }
            $set[] = sprintf('`%s` = :%s', $column, $column);
            $params[':' . $column] = $value;
        }

        if (empty($set)) {
            return false;
        }

        $set[] = '`updated_at` = NOW()';
        $statement = $this->connection->prepare(
            sprintf('UPDATE `%s` SET %s WHERE `id` = :id LIMIT 1', $this->table, implode(', ', $set))
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }
}
