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
            $task = $this->db->prepare('INSERT INTO cartorio_tarefas (id, titulo, numero_processo, tipo, tipo_documento, especificacao_documento, local_tarefa, status, alocado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $task->execute([$id, self::TIPOS[$tipo] . ' ' . $documento, $numeroProcesso, $tipo, $documento, $especificacao !== '' ? $especificacao : null, $local, 'a_fazer']);
            $allocation = $this->db->prepare('INSERT INTO cartorio_tarefas_servidores (tarefa_id, usuario_id) VALUES (?, ?)');
            $allocation->execute([$id, $usuario]);
            require_once __DIR__ . '/../services/CartorioTarefasAutomacaoService.php';
            $automacao = new CartorioTarefasAutomacaoService($this->db);
            $automacao->registrarEvento($id, 'criacao');
            $automacao->registrarEvento($id, 'alocacao');
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
                WHERE COALESCE(u.cadastro_ativo, 1) = 1 AND (UPPER(TRIM(p.perfil)) IN ('ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA', 'SERVIDOR', 'ESTAGIARIO', 'CARTORIO', 'GABINETE', 'AUDIENCIA')
                   OR UPPER(TRIM(p.perfil)) IN ('SERVIDOR-CARTORIO', 'SERVIDOR-GABINETE', 'SERVIDOR-AUDIENCIAS', 'ESTAGIARIO-CARTORIO', 'ESTAGIARIO-GABINETE', 'ESTAGIARIO-AUDIENCIAS'))
                ORDER BY u.nome, u.sobrenome";
        $users = [];
        foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) $row['nome'] . ' ' . (string) $row['sobrenome']);
            if ((string) $row['id'] !== '' && $name !== '') $users[] = ['id' => (string) $row['id'], 'name' => $name];
        }
        return $users;
    }

    public function alterarStatus(string $id, string $status, string $usuarioRestrito = ''): void
    {
        if (!preg_match('/^TAR-[A-F0-9]{26}$/', $id) || !isset(self::STATUS[$status])) {
            throw new InvalidArgumentException('Tarefa ou status inválido.');
        }
        require_once __DIR__ . '/../services/CartorioTarefasAutomacaoService.php';
        (new CartorioTarefasAutomacaoService($this->db))->alterarStatusManual($id, $status, $usuarioRestrito);
    }

    public function listar(array $filters, int $limit, int $offset, string $usuarioAtual): array
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
        if (!empty($filters['usuario_proprio'])) {
            $where[] = 'EXISTS (SELECT 1 FROM cartorio_tarefas_servidores proprios WHERE proprios.tarefa_id = t.id AND proprios.usuario_id = :usuario_proprio)';
            $params[':usuario_proprio'] = $filters['usuario_proprio'];
        }
        if (!empty($filters['prazo'])) {
            [$prazoWhere, $prazoParams] = $this->filtroPrazo((string) $filters['prazo'], (string) ($filters['prazo_usuario'] ?? ''));
            $where[] = $prazoWhere;
            $params += $prazoParams;
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM cartorio_tarefas t' . $clause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = min($offset, max(0, (int) ceil($total / $limit) - 1) * $limit);
        $sqlite = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $nome = $sqlite
            ? "TRIM(COALESCE(u.nome, '') || ' ' || COALESCE(u.sobrenome, ''))"
            : "TRIM(CONCAT(COALESCE(u.nome, ''), ' ', COALESCE(u.sobrenome, '')))";
        $fim = "CASE WHEN t.status = 'finalizada' THEN t.finalizado_em ELSE CURRENT_TIMESTAMP END";
        $tempo = $sqlite
            ? "(strftime('%s', $fim) - strftime('%s', t.criado_em))"
            : "TIMESTAMPDIFF(SECOND, t.criado_em, $fim)";
        $query = $this->db->prepare('SELECT t.id, t.titulo, t.numero_processo, t.status, t.tipo, t.tipo_documento, t.especificacao_documento, t.local_tarefa, t.criado_em, t.finalizado_em FROM cartorio_tarefas t'
            . " LEFT JOIN (SELECT ts.tarefa_id, MIN($nome) AS nome_ordem, MAX(CASE WHEN ts.usuario_id = :usuario_atual THEN 1 ELSE 0 END) AS usuario_logado FROM cartorio_tarefas_servidores ts JOIN usuarios u ON u.id_usuario = ts.usuario_id GROUP BY ts.tarefa_id) alocacao ON alocacao.tarefa_id = t.id"
            . $clause
            . " ORDER BY CASE WHEN alocacao.usuario_logado = 1 THEN 0 WHEN alocacao.nome_ordem IS NOT NULL THEN 1 ELSE 2 END,"
            . " CASE WHEN alocacao.usuario_logado = 1 THEN '' ELSE alocacao.nome_ordem END ASC,"
            . " CASE t.status WHEN 'a_fazer' THEN 0 WHEN 'em_andamento' THEN 1 WHEN 'com_pendencias' THEN 2 WHEN 'finalizada' THEN 3 ELSE 4 END,"
            . " CASE WHEN t.criado_em IS NULL OR (t.status = 'finalizada' AND t.finalizado_em IS NULL) THEN 1 ELSE 0 END, $tempo DESC,"
            . " CASE WHEN t.numero_processo IS NULL OR TRIM(t.numero_processo) = '' THEN 1 ELSE 0 END, t.numero_processo ASC, t.titulo ASC, COALESCE(t.especificacao_documento, '') ASC, t.id ASC LIMIT :limite OFFSET :inicio");
        foreach ($params as $key => $value) $query->bindValue($key, $value);
        $query->bindValue(':usuario_atual', $usuarioAtual);
        $query->bindValue(':limite', $limit, PDO::PARAM_INT);
        $query->bindValue(':inicio', $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $ids = array_column($rows, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $allocated = $this->db->prepare("SELECT ts.tarefa_id, ts.usuario_id, u.nome, u.sobrenome FROM cartorio_tarefas_servidores ts JOIN usuarios u ON u.id_usuario = ts.usuario_id WHERE ts.tarefa_id IN ($marks) ORDER BY u.nome, u.sobrenome");
            $allocated->execute($ids);
            $names = [];
            $userIds = [];
            foreach ($allocated->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $parts = preg_split('/\s+/u', trim((string) $user['nome'] . ' ' . (string) $user['sobrenome']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($parts) $names[$user['tarefa_id']][] = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $parts[0];
                $userIds[$user['tarefa_id']][] = (string) $user['usuario_id'];
            }
            foreach ($rows as &$row) {
                $row['alocados'] = $names[$row['id']] ?? [];
                $row['alocados_ids'] = $userIds[$row['id']] ?? [];
            }
            unset($row);
        }
        return ['rows' => $rows, 'total' => $total, 'offset' => $offset];
    }

    public function contarPrazos(string $tipo, string $usuarioRestrito = ''): int
    {
        [$where, $params] = $this->filtroPrazo($tipo, $usuarioRestrito);
        $st = $this->db->prepare('SELECT COUNT(*) FROM cartorio_tarefas t WHERE ' . $where);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    private function filtroPrazo(string $tipo, string $usuarioRestrito): array
    {
        if (!in_array($tipo, ['proximo', 'vencido'], true)) throw new InvalidArgumentException('Filtro de prazo inválido.');
        $agora = (new DateTimeImmutable('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
        $where = "t.status <> 'finalizada' AND t.prazo_em IS NOT NULL";
        $params = [':prazo_agora' => $agora];
        if ($tipo === 'vencido') {
            $where .= ' AND t.prazo_em <= :prazo_agora';
        } else {
            $sqlite = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $inicio = $sqlite ? "datetime(t.prazo_em, '-' || COALESCE(t.prazo_alerta_dias, 0) || ' days')" : 'DATE_SUB(t.prazo_em, INTERVAL COALESCE(t.prazo_alerta_dias, 0) DAY)';
            $where .= " AND t.prazo_em > :prazo_agora AND $inicio <= :prazo_alerta_agora";
            $params[':prazo_alerta_agora'] = $agora;
        }
        if ($usuarioRestrito !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM cartorio_tarefas_servidores tp WHERE tp.tarefa_id = t.id AND tp.usuario_id = :prazo_usuario)';
            $params[':prazo_usuario'] = $usuarioRestrito;
        }
        return [$where, $params];
    }
}
