<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/CnjValidator.php';

final class CartorioTarefasRepository
{
    public const TIPOS = ['expedicao' => 'Expedir', 'assinatura' => 'Assinar', 'envio' => 'Enviar', 'juntada' => 'Juntar', 'arquivar' => 'Arquivar', 'desentranhar' => 'Desentranhar'];
    public const DOCUMENTOS = ['Certidão', 'Ofício', 'Mandado', 'Carta Precatória', 'Guia', 'Edital', 'Ato Ordinatório', 'Petição', 'Resumo', 'Peças processuais', 'Laudo', 'Inquérito Policial', 'Medidas Protetivas'];
    public const ESPECIFICACOES = ['Informando Baixa', 'Informando Condenação', 'Para intimação da Sentença', 'Para intimação da Decisão Interlocutória', 'Para intimação da Decisão de Medida Protetiva', 'Para Defesa Preliminar', 'Para comparecimento em Audiência'];
    public const LOCAIS = ['Cartório', 'Gabinete', 'Sala de Audiências'];
    public const STATUS = ['a_fazer' => 'A fazer', 'em_andamento' => 'Em andamento', 'com_pendencias' => 'Pendente', 'finalizada' => 'Finalizada'];

    public function __construct(private PDO $db) {}

    public function cadastrar(string $tipo, string $documento, string $local, string $usuario, string $numeroProcesso, string $especificacao = ''): string
    {
        $numeroProcesso = trim($numeroProcesso);
        if (!CnjValidator::isFormatted($numeroProcesso) && !preg_match('/^\d{20}$/', $numeroProcesso)) {
            throw new InvalidArgumentException('Informe o número do processo completo no formato CNJ.');
        }
        $digits = CnjValidator::digits20OrThrow($numeroProcesso);
        $numeroProcesso = substr($digits, 0, 7) . '-' . substr($digits, 7, 2) . '.' . substr($digits, 9, 4) . '.' . substr($digits, 13, 1) . '.' . substr($digits, 14, 2) . '.' . substr($digits, 16, 4);
        if (!isset(self::TIPOS[$tipo]) || !in_array($documento, self::DOCUMENTOS, true) || ($especificacao !== '' && !in_array($especificacao, self::ESPECIFICACOES, true)) || !in_array($local, self::LOCAIS, true) || !in_array($usuario, array_column($this->listarUsuariosInternos(), 'id'), true)) {
            throw new InvalidArgumentException('Selecione um valor válido em cada campo.');
        }
        $id = IdGenerator::generateUniquePrefixedId($this->db, 'cartorio_tarefas', 'id', 'TAR');
        $this->db->beginTransaction();
        try {
            $task = $this->db->prepare('INSERT INTO cartorio_tarefas (id, titulo, numero_processo, tipo, tipo_documento, especificacao_documento, local_tarefa, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $task->execute([$id, self::TIPOS[$tipo] . ' ' . $documento, $numeroProcesso, $tipo, $documento, $especificacao !== '' ? $especificacao : null, $local, 'a_fazer']);
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

    public function alterarStatus(string $id, string $status): void
    {
        if (!preg_match('/^TAR-[A-F0-9]{26}$/', $id) || !isset(self::STATUS[$status])) {
            throw new InvalidArgumentException('Tarefa ou status inválido.');
        }
        $statement = $this->db->prepare("UPDATE cartorio_tarefas SET finalizado_em = CASE WHEN :final = 'finalizada' THEN COALESCE(finalizado_em, CURRENT_TIMESTAMP) ELSE NULL END, status = :status WHERE id = :id");
        $statement->execute([':final' => $status, ':status' => $status, ':id' => $id]);
        if ($statement->rowCount() === 0) {
            $check = $this->db->prepare('SELECT 1 FROM cartorio_tarefas WHERE id = ?');
            $check->execute([$id]);
            if ($check->fetchColumn() === false) throw new InvalidArgumentException('Tarefa não encontrada.');
        }
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
        $query = $this->db->prepare('SELECT t.id, t.titulo, t.numero_processo, t.status, t.tipo, t.tipo_documento, t.especificacao_documento, t.local_tarefa, t.criado_em, t.finalizado_em FROM cartorio_tarefas t' . $clause . " ORDER BY CASE WHEN t.numero_processo IS NULL OR TRIM(t.numero_processo) = '' THEN 1 ELSE 0 END, t.numero_processo $direction, t.id $direction LIMIT :limite OFFSET :inicio");
        foreach ($params as $key => $value) $query->bindValue($key, $value);
        $query->bindValue(':limite', $limit, PDO::PARAM_INT);
        $query->bindValue(':inicio', $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $ids = array_column($rows, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $allocated = $this->db->prepare("SELECT ts.tarefa_id, u.nome, u.sobrenome FROM cartorio_tarefas_servidores ts JOIN usuarios u ON u.id_usuario = ts.usuario_id WHERE ts.tarefa_id IN ($marks) ORDER BY u.nome, u.sobrenome");
            $allocated->execute($ids);
            $names = [];
            foreach ($allocated->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $parts = preg_split('/\s+/u', trim((string) $user['nome'] . ' ' . (string) $user['sobrenome']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($parts) $names[$user['tarefa_id']][] = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $parts[0];
            }
            foreach ($rows as &$row) $row['alocados'] = $names[$row['id']] ?? [];
            unset($row);
        }
        return ['rows' => $rows, 'total' => $total, 'offset' => $offset];
    }
}
