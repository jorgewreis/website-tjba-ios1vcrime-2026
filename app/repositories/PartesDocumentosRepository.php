<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/ParteDocumentoHelper.php';
require_once __DIR__ . '/../helpers/ParteDocumentoLock.php';

final class PartesDocumentosRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'partes_documentos')
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
                'SELECT `id`, `parte_id`, `tipo`, `numero`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function findByParteId(string $parteId): array
    {
        $parteId = trim($parteId);
        if ($parteId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `parte_id`, `tipo`, `numero`, `created_at`, `updated_at` FROM `%s` WHERE `parte_id` = :parte_id ORDER BY FIELD(`tipo`, \'CPF\', \'RG\', \'CIN\', \'CAD\', \'MAT\', \'CNH\', \'OAB\'), `created_at` ASC',
                $this->table
            )
        );
        $statement->bindValue(':parte_id', $parteId);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findParteIdByTipoNumero(string $tipo, string $numero, ?string $excluirParteId = null): ?string
    {
        $tipo = ParteDocumentoHelper::normalizarTipo($tipo);
        $numero = ParteDocumentoHelper::normalizarNumero($tipo, $numero);
        if ($tipo === '' || $numero === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT `parte_id` FROM `%s` WHERE `tipo` = :tipo AND `numero` = :numero',
            $this->table
        );
        if ($excluirParteId !== null && trim($excluirParteId) !== '') {
            $sql .= ' AND `parte_id` != :excluir_parte_id';
        }
        $sql .= ' LIMIT 1';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':tipo', $tipo);
        $statement->bindValue(':numero', $numero);
        if ($excluirParteId !== null && trim($excluirParteId) !== '') {
            $statement->bindValue(':excluir_parte_id', trim($excluirParteId));
        }
        $statement->execute();

        $parteId = $statement->fetchColumn();

        return $parteId === false ? null : (string) $parteId;
    }

    public function insert(array $row): string
    {
        $parteId = trim((string) ($row['parte_id'] ?? ''));
        $tipo = ParteDocumentoHelper::normalizarTipo((string) ($row['tipo'] ?? ''));
        $numero = ParteDocumentoHelper::normalizarNumero($tipo, (string) ($row['numero'] ?? ''));

        if ($parteId === '') {
            throw new InvalidArgumentException('parte_id é obrigatório.');
        }
        if (!ParteDocumentoHelper::isTipoValido($tipo)) {
            throw new InvalidArgumentException('Tipo de documento inválido.');
        }
        ParteDocumentoHelper::validarNumero($tipo, $numero);

        return ParteDocumentoLock::run($this->connection, $tipo, $numero, function () use ($row, $parteId, $tipo, $numero): string {
            $existenteOutraParte = $this->findParteIdByTipoNumero($tipo, $numero);
            if ($existenteOutraParte !== null && $existenteOutraParte !== $parteId) {
                throw new InvalidArgumentException('Já existe outra pessoa cadastrada com este documento.');
            }

            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'DOC', 30);
            }

            $statement = $this->connection->prepare(
                sprintf(
                    'INSERT INTO `%s` (`id`, `parte_id`, `tipo`, `numero`, `created_at`, `updated_at`) VALUES (:id, :parte_id, :tipo, :numero, NOW(), NOW())',
                    $this->table
                )
            );
            $statement->bindValue(':id', $id);
            $statement->bindValue(':parte_id', $parteId);
            $statement->bindValue(':tipo', $tipo);
            $statement->bindValue(':numero', $numero);
            $statement->execute();

            return $id;
        });
    }

    public function delete(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        $row = $this->findById($id);
        if ($row === null) {
            return false;
        }
        $type = ParteDocumentoHelper::normalizarTipo((string) ($row['tipo'] ?? ''));
        $number = ParteDocumentoHelper::normalizarNumero($type, (string) ($row['numero'] ?? ''));

        return ParteDocumentoLock::run($this->connection, $type, $number, function () use ($id, $type, $number): bool {
            $current = $this->findById($id);
            if ($current === null) {
                return false;
            }
            if (ParteDocumentoHelper::normalizarTipo((string) ($current['tipo'] ?? '')) !== $type
                || ParteDocumentoHelper::normalizarNumero($type, (string) ($current['numero'] ?? '')) !== $number) {
                throw new RuntimeException('Documento alterado durante a exclusão; tente novamente.');
            }
            $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
            $statement->bindValue(':id', $id);
            $statement->execute();
            return $statement->rowCount() > 0;
        });
    }
}
