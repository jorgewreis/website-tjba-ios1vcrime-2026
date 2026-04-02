<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProdutividadeRegrasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'produtividade_regras')
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

        $sql = sprintf(
            'SELECT `id`, `tipo_ato`, `tipo_documento`, `sistema_modulo`, `pontos_por_ato`, `pontos_multiplicador`, `ativo`, `observacoes`, `criado_por`, `criado_em`, `atualizado_em`
             FROM `%s` WHERE `id` = :id LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findAtivaById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT `id`, `tipo_ato`, `tipo_documento`, `sistema_modulo`, `pontos_por_ato`, `pontos_multiplicador`, `ativo`, `observacoes`, `criado_por`, `criado_em`, `atualizado_em`
             FROM `%s` WHERE `id` = :id AND `ativo` = 1 LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchList(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(`tipo_ato` LIKE :q OR `tipo_documento` LIKE :q OR `sistema_modulo` LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $ativo = $filters['ativo'] ?? null;
        if ($ativo !== null && $ativo !== '') {
            $where[] = '`ativo` = :ativo';
            $params[':ativo'] = ((int) $ativo) === 1 ? 1 : 0;
        }

        $sql = sprintf(
            'SELECT `id`, `tipo_ato`, `tipo_documento`, `sistema_modulo`, `pontos_por_ato`, `pontos_multiplicador`, `ativo`, `observacoes`, `criado_por`, `criado_em`, `atualizado_em`
             FROM `%s`
             %s
             ORDER BY `ativo` DESC, `tipo_ato` ASC, `criado_em` DESC
             LIMIT :limit OFFSET :offset',
            $this->table,
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : ''
        );

        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(`tipo_ato` LIKE :q OR `tipo_documento` LIKE :q OR `sistema_modulo` LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $ativo = $filters['ativo'] ?? null;
        if ($ativo !== null && $ativo !== '') {
            $where[] = '`ativo` = :ativo';
            $params[':ativo'] = ((int) $ativo) === 1 ? 1 : 0;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` %s',
            $this->table,
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array{id:string,label:string,tipo_ato:string,tipo_documento:string,sistema_modulo:string,pontos_por_ato:string,pontos_multiplicador:string}>
     */
    public function searchAtivas(string $query, int $limit = 10): array
    {
        $query = trim($query);
        $limit = max(1, min(20, $limit));
        $sql = sprintf(
            'SELECT `id`, `tipo_ato`, `tipo_documento`, `sistema_modulo`, `pontos_por_ato`, `pontos_multiplicador`
             FROM `%s`
             WHERE `ativo` = 1
               AND (
                    :q = \'\'
                    OR `tipo_ato` LIKE :likeQ
                    OR `tipo_documento` LIKE :likeQ
                    OR `sistema_modulo` LIKE :likeQ
                    OR CONCAT(
                        COALESCE(`tipo_ato`, \'\'),
                        \' de \',
                        COALESCE(NULLIF(`tipo_documento`, \'\'), \'documento\'),
                        \' no \',
                        COALESCE(NULLIF(`sistema_modulo`, \'\'), \'sistema não informado\')
                    ) LIKE :likeQ
               )
             ORDER BY `tipo_ato` ASC, `sistema_modulo` ASC, `tipo_documento` ASC
             LIMIT :limit',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':q', $query);
        $statement->bindValue(':likeQ', '%' . $query . '%');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $tipoAto = trim((string) ($row['tipo_ato'] ?? ''));
            $tipoDoc = trim((string) ($row['tipo_documento'] ?? ''));
            $modulo = trim((string) ($row['sistema_modulo'] ?? ''));
            $label = $tipoAto;
            if ($tipoDoc !== '' && $modulo !== '') {
                $label .= ' de ' . $tipoDoc . ' no ' . $modulo;
            } elseif ($tipoDoc !== '') {
                $label .= ' de ' . $tipoDoc;
            } elseif ($modulo !== '') {
                $label .= ' no ' . $modulo;
            }
            $out[] = [
                'id' => (string) ($row['id'] ?? ''),
                'label' => $label,
                'tipo_ato' => $tipoAto,
                'tipo_documento' => $tipoDoc,
                'sistema_modulo' => $modulo,
                'pontos_por_ato' => (string) ($row['pontos_por_ato'] ?? '0.00'),
                'pontos_multiplicador' => (string) ($row['pontos_multiplicador'] ?? '1.00'),
            ];
        }

        return $out;
    }

    /**
     * @return array{tipos_ato:list<string>,tipos_documento:list<string>,sistemas_modulo:list<string>}
     */
    public function listDistinctCatalogValues(): array
    {
        $tiposAto = $this->fetchDistinctStringColumn('tipo_ato');
        $tiposDocumento = $this->fetchDistinctStringColumn('tipo_documento');
        $sistemasModulo = $this->fetchDistinctStringColumn('sistema_modulo');

        return [
            'tipos_ato' => $tiposAto,
            'tipos_documento' => $tiposDocumento,
            'sistemas_modulo' => $sistemasModulo,
        ];
    }

    /**
     * @return list<string>
     */
    private function fetchDistinctStringColumn(string $column): array
    {
        $column = IdGenerator::validateIdentifier($column);
        $sql = sprintf(
            'SELECT DISTINCT TRIM(`%s`) AS `v`
             FROM `%s`
             WHERE `%s` IS NOT NULL AND TRIM(`%s`) <> \'\'
             ORDER BY `v` ASC',
            $column,
            $this->table,
            $column,
            $column
        );
        $statement = $this->connection->query($sql);
        if ($statement === false) {
            return [];
        }
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['v'] ?? ''));
            if ($value !== '' && !in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    public function insert(array $row): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PRG', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `tipo_ato`, `tipo_documento`, `sistema_modulo`, `pontos_por_ato`, `pontos_multiplicador`, `ativo`, `observacoes`, `criado_por`)
             VALUES (:id, :tipo_ato, :tipo_documento, :sistema_modulo, :pontos_por_ato, :pontos_multiplicador, :ativo, :observacoes, :criado_por)',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':tipo_ato', $row['tipo_ato']);
        $statement->bindValue(':tipo_documento', $row['tipo_documento']);
        $statement->bindValue(':sistema_modulo', $row['sistema_modulo']);
        $statement->bindValue(':pontos_por_ato', $row['pontos_por_ato']);
        $statement->bindValue(':pontos_multiplicador', $row['pontos_multiplicador']);
        $statement->bindValue(':ativo', ((int) ($row['ativo'] ?? 1)) === 1 ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->bindValue(':criado_por', $row['criado_por'] ?? null);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $row): bool
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `tipo_ato` = :tipo_ato,
                 `tipo_documento` = :tipo_documento,
                 `sistema_modulo` = :sistema_modulo,
                 `pontos_por_ato` = :pontos_por_ato,
                 `pontos_multiplicador` = :pontos_multiplicador,
                 `ativo` = :ativo,
                 `observacoes` = :observacoes
             WHERE `id` = :id
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':tipo_ato', $row['tipo_ato']);
        $statement->bindValue(':tipo_documento', $row['tipo_documento']);
        $statement->bindValue(':sistema_modulo', $row['sistema_modulo']);
        $statement->bindValue(':pontos_por_ato', $row['pontos_por_ato']);
        $statement->bindValue(':pontos_multiplicador', $row['pontos_multiplicador']);
        $statement->bindValue(':ativo', ((int) ($row['ativo'] ?? 1)) === 1 ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function setAtivo(string $id, bool $ativo): bool
    {
        $sql = sprintf('UPDATE `%s` SET `ativo` = :ativo WHERE `id` = :id LIMIT 1', $this->table);
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':ativo', $ativo ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() > 0;
    }
}

