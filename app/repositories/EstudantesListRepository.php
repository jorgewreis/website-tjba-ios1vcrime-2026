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
        $sql = 'SELECT e.id, e.nome_completo, e.cpf, e.ultimo_login FROM ' . $this->table . ' e';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $usuariosByCpf = [];
        $cpfs = array_filter(array_map(static function ($row): string {
            return trim((string) ($row['cpf'] ?? ''));
        }, $rows));
        if ($cpfs !== []) {
            $placeholders = implode(',', array_fill(0, count($cpfs), '?'));
            $stUsuarios = $this->connectionStatus
                ? $this->connectionStatus->prepare("SELECT id_usuario, cpf, ultimo_acesso FROM usuarios WHERE cpf IN ($placeholders)")
                : $this->connection->prepare("SELECT id_usuario, cpf, ultimo_acesso FROM usuarios WHERE cpf IN ($placeholders)");
            $stUsuarios->execute(array_values($cpfs));
            foreach ($stUsuarios->fetchAll(PDO::FETCH_ASSOC) as $uRow) {
                $cpfKey = trim((string) ($uRow['cpf'] ?? ''));
                if ($cpfKey !== '') {
                    $usuariosByCpf[$cpfKey] = [
                        'id_usuario' => trim((string) ($uRow['id_usuario'] ?? '')),
                        'ultimo_acesso' => $uRow['ultimo_acesso'] ?? null,
                    ];
                }
            }
        }

        $bloqueados = [];
        if ($this->connectionStatus !== null) {
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
            $cpfKey = trim((string) ($row['cpf'] ?? ''));
            $uMeta = $usuariosByCpf[$cpfKey] ?? null;
            $idUsuario = '';
            $ultimoUsuario = null;
            if (is_array($uMeta)) {
                $idUsuario = $uMeta['id_usuario'] ?? '';
                $ultimoUsuario = $uMeta['ultimo_acesso'] ?? null;
            }
            $rows[$i]['id_usuario'] = $idUsuario !== '' ? $idUsuario : ($row['id'] ?? '');
            $ultimoEstudante = $row['ultimo_login'] ?? null;
            $uaStr = $ultimoUsuario !== null && $ultimoUsuario !== '' ? trim((string) $ultimoUsuario) : null;
            $elStr = is_string($ultimoEstudante) ? trim($ultimoEstudante) : ($ultimoEstudante !== null && $ultimoEstudante !== '' ? trim((string) $ultimoEstudante) : null);
            $rows[$i]['ultimo_acesso'] = self::pickLatestAccess($elStr !== '' ? $elStr : null, $uaStr !== '' ? $uaStr : null);
        }

        return $rows;
    }

    private static function pickLatestAccess(?string $a, ?string $b): ?string
    {
        $a = $a !== null ? trim($a) : '';
        $b = $b !== null ? trim($b) : '';
        if ($a === '' && $b === '') {
            return null;
        }
        if ($a === '') {
            return $b !== '' ? $b : null;
        }
        if ($b === '') {
            return $a;
        }
        $ta = strtotime($a);
        $tb = strtotime($b);
        if ($ta === false) {
            return $b;
        }
        if ($tb === false) {
            return $a;
        }

        return $ta >= $tb ? $a : $b;
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->table;
        return (int) $this->connection->query($sql)->fetchColumn();
    }
}
