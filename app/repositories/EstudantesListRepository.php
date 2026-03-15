<?php
declare(strict_types=1);

/**
 * Listagem da tabela estudantes (cadastro de estudantes / usuários externos).
 * Pode usar conexão opcional para outro banco ($connection = banco dos estudantes).
 * Se $connectionStatus for passada (ex.: conexão do banco common), usa para JOIN com tentativas_login e obter status bloqueado; senão status = pendente.
 */
final class EstudantesListRepository
{
    private PDO $connection;
    private ?PDO $connectionStatus;
    private string $table = 'estudantes';

    public function __construct(PDO $connection, ?PDO $connectionStatus = null)
    {
        $this->connection = $connection;
        $this->connectionStatus = $connectionStatus;
    }

    /**
     * Retorna todos os estudantes para a listagem unificada.
     * Campos normalizados: id, nome_completo, tipo=externo, setores='Sala de Audiências' (label para usuário), cargo='Estudante', status, origem='estudantes'.
     *
     * @return array<int, array>
     */
    public function fetchAll(): array
    {
        $sql = 'SELECT e.id, e.nome_completo, e.cpf FROM ' . $this->table . ' e';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bloqueados = [];
        if ($this->connectionStatus !== null) {
            $cpfs = array_filter(array_column($rows, 'cpf'));
            if ($cpfs !== []) {
                $placeholders = implode(',', array_fill(0, count($cpfs), '?'));
                $st = $this->connectionStatus->prepare("SELECT cpf FROM tentativas_login WHERE bloqueado = 1 AND cpf IN ($placeholders)");
                $st->execute(array_values($cpfs));
                $bloqueados = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
            }
        }

        foreach ($rows as $i => $row) {
            $rows[$i]['tipo'] = 'externo';
            $rows[$i]['setores'] = 'Sala de Audiências';
            $rows[$i]['cargo'] = 'Estudante';
            $rows[$i]['status'] = isset($bloqueados[$row['cpf'] ?? '']) ? 'bloqueado' : 'ativo';
            $rows[$i]['origem'] = 'estudantes';
            $rows[$i]['id_usuario'] = $row['id'] ?? '';
        }

        return $rows;
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->table;
        return (int) $this->connection->query($sql)->fetchColumn();
    }
}
