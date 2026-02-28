<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class ClassesRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'classes')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function ensureClass(string $cod, string $descricao): void
    {
        $cod = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $cod));
        if ($cod === '') {
            return;
        }

        if ($this->findByCode($cod) !== null) {
            return;
        }

        $descricao = trim($descricao);
        $descricao = $descricao === '' ? $cod : TextFormatter::formatProperName($descricao, 150);
        if ($descricao === '') {
            $descricao = $cod;
        }

        $idClasse = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id_classe',
            'CLS',
            30
        );

        $statement = $this->connection->prepare(
            sprintf('INSERT INTO `%s` (`id_classe`, `cod`, `descricao`) VALUES (:id_classe, :cod, :descricao)', $this->table)
        );
        $statement->bindValue(':id_classe', $idClasse);
        $statement->bindValue(':cod', $cod);
        $statement->bindValue(':descricao', $descricao);
        $statement->execute();
    }

    public function findByCode(string $cod): ?array
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $cod));
        if ($codigo === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id_classe`, `cod`, `descricao` FROM `%s` WHERE `cod` = :cod LIMIT 1', $this->table)
        );
        $statement->bindValue(':cod', $codigo);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }
}
