<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProdutividadeLogRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'produtividade_log')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function insert(string $lancamentoId, string $usuarioId, string $acao, ?array $detalhes = null): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PLG', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `lancamento_id`, `usuario_id`, `acao`, `detalhes`)
             VALUES (:id, :lancamento_id, :usuario_id, :acao, :detalhes)',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':lancamento_id', $lancamentoId);
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->bindValue(':acao', $acao);
        $statement->bindValue(':detalhes', $detalhes === null ? null : json_encode($detalhes, JSON_UNESCAPED_UNICODE));
        $statement->execute();

        return $id;
    }
}

