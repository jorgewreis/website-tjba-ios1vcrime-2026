<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CartorioTarefasRepository
{
    public const TIPOS = ['expedicao' => 'Expedir', 'assinatura' => 'Assinar', 'envio' => 'Enviar', 'juntada' => 'Juntar', 'arquivar' => 'Arquivar', 'desentranhar' => 'Desentranhar'];
    public const DOCUMENTOS = ['Certidão', 'Ofício', 'Mandado', 'Carta Precatória', 'Guia', 'Edital', 'Ato Ordinatório', 'Petição', 'Resumo', 'Peças processuais', 'Laudo', 'Inquérito Policial', 'Medidas Protetivas'];
    public const LOCAIS = ['Cartório', 'Gabinete', 'Sala de Audiências'];

    public function __construct(private PDO $db) {}

    public function cadastrar(string $tipo, string $documento, string $local, string $usuario): string
    {
        if (!isset(self::TIPOS[$tipo]) || !in_array($documento, self::DOCUMENTOS, true) || !in_array($local, self::LOCAIS, true) || !in_array($usuario, array_column($this->listarUsuariosInternos(), 'id'), true)) {
            throw new InvalidArgumentException('Selecione um valor válido em cada campo.');
        }
        $id = IdGenerator::generateUniquePrefixedId($this->db, 'cartorio_tarefas', 'id', 'TAR');
        $this->db->beginTransaction();
        try {
            $task = $this->db->prepare('INSERT INTO cartorio_tarefas (id, titulo, tipo, tipo_documento, local_tarefa, status) VALUES (?, ?, ?, ?, ?, ?)');
            $task->execute([$id, self::TIPOS[$tipo] . ' ' . $documento, $tipo, $documento, $local, 'a_fazer']);
            $allocation = $this->db->prepare('INSERT INTO cartorio_tarefas_servidores (tarefa_id, usuario_id) VALUES (?, ?)');
            $allocation->execute([$id, $usuario]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function listarUsuariosInternos(): array
    {
        $sql = "SELECT DISTINCT u.id_usuario AS id, u.nome, u.sobrenome
                FROM usuarios u INNER JOIN usuario_perfil p ON p.usuario = u.id_usuario
                WHERE UPPER(TRIM(p.perfil)) IN ('ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA', 'SERVIDOR', 'ESTAGIARIO', 'CARTORIO', 'GABINETE', 'AUDIENCIA')
                   OR UPPER(TRIM(p.perfil)) IN ('SERVIDOR-CARTORIO', 'SERVIDOR-GABINETE', 'SERVIDOR-AUDIENCIAS', 'ESTAGIARIO-CARTORIO', 'ESTAGIARIO-GABINETE', 'ESTAGIARIO-AUDIENCIAS')
                ORDER BY u.nome, u.sobrenome";
        $users = [];
        foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) $row['nome'] . ' ' . (string) $row['sobrenome']);
            if ((string) $row['id'] !== '' && $name !== '') $users[] = ['id' => (string) $row['id'], 'name' => $name];
        }
        return $users;
    }

    public function listar(array $filters, int $limit, int $offset, string $ordem = 'desc'): array
    {
        $where = [];
        $params = [];
        if ($filters['status'] === 'todos_ativos') {
            $where[] = "t.status <> 'finalizada'";
        } elseif ($filters['status'] !== '') {
            $where[] = 't.status = :status';
            $params[':status'] = $filters['status'];
        }
        if ($filters['tipo'] !== '') {
            $where[] = 't.tipo = :tipo';
            $params[':tipo'] = $filters['tipo'];
        }
        if ($filters['servidor'] !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM cartorio_tarefas_servidores ts WHERE ts.tarefa_id = t.id AND ts.usuario_id = :servidor)';
            $params[':servidor'] = $filters['servidor'];
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM cartorio_tarefas t' . $clause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = min($offset, max(0, (int) ceil($total / $limit) - 1) * $limit);
        $direction = $ordem === 'asc' ? 'ASC' : 'DESC';
        $query = $this->db->prepare('SELECT t.id, t.titulo, t.numero_processo, t.status, t.tipo FROM cartorio_tarefas t' . $clause . " ORDER BY CASE WHEN t.numero_processo IS NULL OR TRIM(t.numero_processo) = '' THEN 1 ELSE 0 END, t.numero_processo $direction, t.id $direction LIMIT :limite OFFSET :inicio");
        foreach ($params as $key => $value) $query->bindValue($key, $value);
        $query->bindValue(':limite', $limit, PDO::PARAM_INT);
        $query->bindValue(':inicio', $offset, PDO::PARAM_INT);
        $query->execute();
        return ['rows' => $query->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'offset' => $offset];
    }
}
