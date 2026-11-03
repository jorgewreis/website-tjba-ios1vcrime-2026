<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../repositories/CartorioTarefasRepository.php';
require_once __DIR__ . '/../repositories/CartorioTarefasRegrasRepository.php';

/** Processamento transacional de eventos; cada regra é uma unidade independente. */
final class CartorioTarefasAutomacaoService
{
    private CartorioTarefasRegrasRepository $regras;
    public function __construct(private PDO $db)
    {
        $this->regras = new CartorioTarefasRegrasRepository($db);
    }

    public function registrarEvento(string $tarefaId, string $evento, ?string $origemId = null, ?string $status = null, array $caminho = []): void
    {
        if (!in_array($evento, CartorioTarefasRegras::EVENTOS, true)) throw new InvalidArgumentException('Evento inválido.');
        $st = $this->db->prepare('INSERT INTO cartorio_tarefas_automacao_eventos (tarefa_id, evento, origem_id, status_evento, caminho) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$tarefaId, $evento, $origemId, $status, json_encode($caminho, JSON_THROW_ON_ERROR)]);
    }

    /** Processa um evento existente e registra falhas sem desfazer a ação do usuário. */
    public function processarEvento(int $eventoId): void
    {
        $st = $this->db->prepare('SELECT * FROM cartorio_tarefas_automacao_eventos WHERE id = ?');
        $st->execute([$eventoId]);
        $evento = $st->fetch(PDO::FETCH_ASSOC);
        if (!$evento) return;
        $tarefa = $this->tarefa((string) $evento['tarefa_id']);
        if (!$tarefa) {
            $this->apagarEvento($eventoId);
            return;
        }
        $regras = array_values(array_filter($this->regras->listar(true), static function (array $regra) use ($evento, $tarefa): bool {
            return $regra['evento'] === $evento['evento']
                && ($regra['evento'] !== 'status_vinculada' || $regra['evento_status'] === $evento['status_evento'])
                && CartorioTarefasCondicoes::corresponde($regra['condicoes'], $tarefa);
        }));
        $vencedoras = [];
        foreach (['alocar', 'alterar_status', 'definir_prazo'] as $tipo) {
            $vencedoras[$tipo] = CartorioTarefasRegras::vencedora($regras, $tarefa, $tipo)['id'] ?? null;
        }
        foreach ($regras as $regra) $this->executarRegra($evento, $regra, $vencedoras);
        $this->apagarEvento($eventoId);
    }

    private function executarRegra(array $evento, array $regra, array $vencedoras): void
    {
        $this->db->beginTransaction();
        try {
            $lock = 'SELECT id FROM cartorio_tarefas_automacao_eventos WHERE id = ?';
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') $lock .= ' FOR UPDATE';
            $st = $this->db->prepare($lock);
            $st->execute([$evento['id']]);
            if (!$st->fetchColumn()) { $this->db->rollBack(); return; }
            $st = $this->db->prepare('SELECT 1 FROM cartorio_tarefas_automacao_eventos_regras WHERE evento_id = ? AND regra_id = ?');
            $st->execute([$evento['id'], $regra['id']]);
            if ($st->fetchColumn()) { $this->db->rollBack(); return; }
            if ($evento['evento'] === 'finalizacao' && in_array('criar_tarefa', array_column($regra['acoes'], 'tipo'), true)) {
                $st = $this->db->prepare('SELECT 1 FROM cartorio_tarefas_automacao_criacoes WHERE tarefa_origem_id = ? AND regra_id = ? LIMIT 1');
                $st->execute([$evento['tarefa_id'], $regra['id']]);
                if ($st->fetchColumn()) {
                    $this->marcarEventoRegra((int) $evento['id'], (int) $regra['id']);
                    $this->db->commit();
                    return;
                }
            }
            $caminho = json_decode((string) ($evento['caminho'] ?: '[]'), true, 512, JSON_THROW_ON_ERROR);
            $chave = $evento['tarefa_id'] . ':' . $evento['evento'] . ':' . $regra['id'];
            if (count($caminho) >= 32 || in_array($chave, $caminho, true)) throw new RuntimeException('Ciclo de automação interrompido.');
            $caminho[] = $chave;
            $criadas = [];
            foreach ($regra['acoes'] as $acao) {
                $tipo = $acao['tipo'];
                $alvo = $acao['alvo'] ?? 'original';
                if ($alvo === 'original' && $tipo !== 'criar_tarefa' && ($vencedoras[$tipo] ?? null) !== $regra['id']) continue;
                $tarefaId = $alvo === 'original' ? (string) $evento['tarefa_id'] : ($criadas[$alvo] ?? null);
                if (!$tarefaId) throw new RuntimeException('A tarefa criada para esta ação não está disponível.');
                if ($tipo === 'criar_tarefa') {
                    $criadas[$acao['id']] = $this->criarTarefa($tarefaId, $regra, $acao, $caminho);
                } elseif ($tipo === 'alocar') {
                    $this->alocar($tarefaId, $acao['usuario_id'], $regra, $evento, $caminho);
                } elseif ($tipo === 'alterar_status') {
                    $this->alterarStatus($tarefaId, $acao['status'], $caminho);
                } elseif ($tipo === 'definir_prazo') {
                    $this->definirPrazo($tarefaId, $regra['id'], $acao);
                }
            }
            $this->marcarEventoRegra((int) $evento['id'], (int) $regra['id']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->db->beginTransaction();
            try {
                $this->registrarFalha((int) $regra['id'], (string) $evento['tarefa_id'], (string) $evento['evento'], $e->getMessage(), false);
                $this->marcarEventoRegra((int) $evento['id'], (int) $regra['id']);
                $this->db->commit();
            } catch (Throwable $inner) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $inner;
            }
        }
    }

    private function criarTarefa(string $origemId, array $regra, array $acao, array $caminho): string
    {
        $st = $this->db->prepare('SELECT tarefa_criada_id FROM cartorio_tarefas_automacao_criacoes WHERE tarefa_origem_id = ? AND regra_id = ? AND acao_id = ?');
        $st->execute([$origemId, $regra['id'], $acao['id']]);
        $existente = $st->fetchColumn();
        if ($existente !== false) return (string) $existente;
        $origem = $this->tarefa($origemId);
        if (!$origem) throw new RuntimeException('Tarefa de origem não encontrada.');
        $tipo = $acao['tipo_tarefa'];
        $documento = $acao['tipo_documento'];
        $especificacao = $acao['especificacao_documento'] ?? '';
        $local = $acao['local_tarefa'];
        if (!isset(CartorioTarefasRepository::TIPOS[$tipo]) || !in_array($documento, CartorioTarefasRepository::DOCUMENTOS, true) || ($especificacao !== '' && !in_array($especificacao, CartorioTarefasRepository::ESPECIFICACOES, true)) || !in_array($local, CartorioTarefasRepository::LOCAIS, true)) {
            throw new RuntimeException('Os dados configurados para a tarefa criada não são válidos.');
        }
        $id = IdGenerator::generateUniquePrefixedId($this->db, 'cartorio_tarefas', 'id', 'TAR');
        $st = $this->db->prepare('INSERT INTO cartorio_tarefas (id, titulo, numero_processo, tipo, tipo_documento, especificacao_documento, local_tarefa, status, sequencia_id, origem_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$id, CartorioTarefasRepository::TIPOS[$tipo] . ' ' . $documento, $origem['numero_processo'], $tipo, $documento, $especificacao !== '' ? $especificacao : null, $local, 'a_fazer', $origem['sequencia_id'] ?: $origemId, $origemId]);
        $st = $this->db->prepare('INSERT INTO cartorio_tarefas_automacao_criacoes (tarefa_origem_id, regra_id, acao_id, tarefa_criada_id) VALUES (?, ?, ?, ?)');
        $st->execute([$origemId, $regra['id'], $acao['id'], $id]);
        $this->registrarEvento($id, 'criacao', $origemId, null, $caminho);
        return $id;
    }

    private function alocar(string $tarefaId, string $usuarioId, array $regra, array $evento, array $caminho): void
    {
        $st = $this->db->prepare('SELECT 1 FROM cartorio_tarefas_servidores WHERE tarefa_id = ?');
        $st->execute([$tarefaId]);
        if ($st->fetchColumn()) return;
        $st = $this->db->prepare("SELECT 1 FROM usuarios u JOIN usuario_perfil p ON p.usuario = u.id_usuario WHERE u.id_usuario = ? AND COALESCE(u.cadastro_ativo, 1) = 1 AND UPPER(TRIM(p.perfil)) IN ('ADMIN','CHEFIA','DIRETORA','MAGISTRADA','SERVIDOR','ESTAGIARIO','CARTORIO','GABINETE','AUDIENCIA','SERVIDOR-CARTORIO','SERVIDOR-GABINETE','SERVIDOR-AUDIENCIAS','ESTAGIARIO-CARTORIO','ESTAGIARIO-GABINETE','ESTAGIARIO-AUDIENCIAS') LIMIT 1");
        $st->execute([$usuarioId]);
        if (!$st->fetchColumn()) {
            $this->registrarFalha((int) $regra['id'], $tarefaId, (string) $evento['evento'], 'O usuário configurado está inativo ou não pertence à equipe interna.', false);
            return;
        }
        $st = $this->db->prepare('INSERT INTO cartorio_tarefas_servidores (tarefa_id, usuario_id) VALUES (?, ?)');
        $st->execute([$tarefaId, $usuarioId]);
        $st = $this->db->prepare('UPDATE cartorio_tarefas SET alocado_em = CURRENT_TIMESTAMP WHERE id = ?');
        $st->execute([$tarefaId]);
        $this->registrarEvento($tarefaId, 'alocacao', null, null, $caminho);
        $this->recalcularPrazo($tarefaId);
    }

    private function alterarStatus(string $id, string $status, array $caminho): void
    {
        $tarefa = $this->tarefa($id);
        if (!$tarefa) throw new RuntimeException('Tarefa não encontrada.');
        if ($tarefa['status'] === $status) return;
        $st = $this->db->prepare("UPDATE cartorio_tarefas SET status = ?, finalizado_em = CASE WHEN ? = 'finalizada' THEN CURRENT_TIMESTAMP ELSE NULL END, primeiro_andamento_em = CASE WHEN ? = 'em_andamento' THEN COALESCE(primeiro_andamento_em, CURRENT_TIMESTAMP) ELSE primeiro_andamento_em END WHERE id = ?");
        $st->execute([$status, $status, $status, $id]);
        if ($status === 'finalizada') $this->registrarEvento($id, 'finalizacao', null, $status, $caminho);
        $raiz = $tarefa['sequencia_id'] ?: $id;
        $st = $this->db->prepare('SELECT id FROM cartorio_tarefas WHERE (sequencia_id = ? OR id = ?) AND id <> ?');
        $st->execute([$raiz, $raiz, $id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $vinculada) $this->registrarEvento((string) $vinculada, 'status_vinculada', $id, $status, $caminho);
        $this->recalcularPrazo($id);
    }

    private function definirPrazo(string $id, int $regraId, array $acao): void
    {
        $st = $this->db->prepare('UPDATE cartorio_tarefas SET prazo_regra_id = ?, prazo_inicio = ?, prazo_dias = ?, prazo_alerta_dias = ?, prazo_em = NULL, prazo_evento_em = NULL WHERE id = ?');
        $st->execute([$regraId, $acao['inicio'], $acao['dias'], $acao['alerta_dias'], $id]);
        $this->recalcularPrazo($id);
    }

    public function recalcularPrazo(string $id): void
    {
        $tarefa = $this->tarefa($id);
        if (!$tarefa || !$tarefa['prazo_inicio'] || !$tarefa['prazo_dias']) return;
        $inicio = null;
        if ($tarefa['prazo_inicio'] === 'criacao') $inicio = $tarefa['criado_em'];
        if ($tarefa['prazo_inicio'] === 'andamento') $inicio = $tarefa['primeiro_andamento_em'] ?: ($tarefa['status'] === 'em_andamento' ? $tarefa['criado_em'] : null);
        if ($tarefa['prazo_inicio'] === 'alocacao') {
            $st = $this->db->prepare('SELECT 1 FROM cartorio_tarefas_servidores WHERE tarefa_id = ?');
            $st->execute([$id]);
            if ($st->fetchColumn()) $inicio = $tarefa['alocado_em'] ?: $tarefa['criado_em'];
        }
        if ($tarefa['prazo_inicio'] === 'finalizacao_anterior' && $tarefa['origem_id']) {
            $anterior = $this->tarefa((string) $tarefa['origem_id']);
            if ($anterior && $anterior['status'] === 'finalizada') $inicio = $anterior['finalizado_em'] ?: $anterior['criado_em'];
        }
        if (!$inicio) return;
        $data = (new DateTimeImmutable((string) $inicio, new DateTimeZone('America/Bahia')))->setTime(23, 59)->modify('+' . (int) $tarefa['prazo_dias'] . ' days')->format('Y-m-d H:i:s');
        $st = $this->db->prepare('UPDATE cartorio_tarefas SET prazo_evento_em = CASE WHEN prazo_em IS NULL OR prazo_em <> ? THEN NULL ELSE prazo_evento_em END, prazo_em = ? WHERE id = ?');
        $st->execute([$data, $data, $id]);
    }

    private function tarefa(string $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM cartorio_tarefas WHERE id = ?');
        $st->execute([$id]);
        $tarefa = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tarefa) return null;
        $tarefa['especificacao_documento'] = $tarefa['especificacao_documento'] ?? '';
        return $tarefa;
    }

    private function registrarFalha(?int $regraId, ?string $tarefaId, string $evento, string $mensagem, bool $podeTentar): void
    {
        $st = $this->db->prepare('INSERT INTO cartorio_tarefas_automacao_falhas (regra_id, tarefa_id, evento, detalhes, pode_tentar) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$regraId, $tarefaId, $evento, mb_substr($mensagem, 0, 2000), $podeTentar ? 1 : 0]);
    }

    private function marcarEventoRegra(int $eventoId, int $regraId): void
    {
        $st = $this->db->prepare('INSERT INTO cartorio_tarefas_automacao_eventos_regras (evento_id, regra_id) VALUES (?, ?)');
        $st->execute([$eventoId, $regraId]);
    }

    private function apagarEvento(int $id): void
    {
        $st = $this->db->prepare('DELETE FROM cartorio_tarefas_automacao_eventos WHERE id = ?');
        $st->execute([$id]);
    }
}
