<?php
declare(strict_types=1);

final class SalaAudienciasTiposRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'sala_audiencias_tipos')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function listAtivos(): array
    {
        $sql = sprintf('SELECT `id_tipo`, `codigo`, `nome`, `ordem` FROM `%s` WHERE `ativo` = 1 ORDER BY `ordem` ASC, `nome` ASC', $this->table);
        $st = $this->connection->query($sql);
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    }
}

