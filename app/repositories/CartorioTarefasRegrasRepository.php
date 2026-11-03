<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CartorioTarefasRegras.php';

final class CartorioTarefasRegrasRepository
{
    public function __construct(private PDO $db) {}

    public function listar(bool $somenteAtivas = false): array
    {
        $sql = 'SELECT * FROM cartorio_tarefas_regras' . ($somenteAtivas ? ' WHERE ativa = 1' : '') . ' ORDER BY prioridade DESC, id ASC';
        return array_map([$this, 'decodificar'], $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    }

    public function obter(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM cartorio_tarefas_regras WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->decodificar($row);
    }

    /** Salva uma versão e agenda a análise das tarefas existentes. */
    public function salvar(array $regra, ?int $id = null): int
    {
        CartorioTarefasRegras::validar($regra);
        $ativa = !empty($regra['ativa']);
        $this->db->beginTransaction();
        try {
            // Serializa a verificação e a escrita mesmo com dois administradores.
            $lock = 'SELECT id FROM cartorio_tarefas_automacao_lock WHERE id = 1';
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') $lock .= ' FOR UPDATE';
            if ($this->db->query($lock)->fetchColumn() === false) throw new RuntimeException('A migração de automação não foi concluída.');
            $anterior = $id === null ? null : $this->obter($id);
            if ($id !== null && $anterior === null) throw new InvalidArgumentException('Regra não encontrada.');
            if ($ativa) {
                foreach ($this->listar(true) as $existente) {
                    if ((int) $existente['id'] !== $id && CartorioTarefasRegras::conflitantes($regra, $existente)) {
                        throw new InvalidArgumentException('Conflito com a regra ativa: ' . $existente['nome'] . '. Ajuste as condições ou a prioridade.');
                    }
                }
            }
            $params = [trim($regra['nome']), $regra['evento'], $regra['evento_status'] ?? null, $regra['prioridade'], json_encode($regra['condicoes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), json_encode($regra['acoes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $ativa ? 1 : 0];
            if ($id === null) {
                $st = $this->db->prepare('INSERT INTO cartorio_tarefas_regras (nome, evento, evento_status, prioridade, condicoes, acoes, ativa, versao) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                $st->execute($params);
                $id = (int) $this->db->lastInsertId();
                $versao = 1;
            } else {
                $versao = (int) $anterior['versao'] + 1;
                $st = $this->db->prepare('UPDATE cartorio_tarefas_regras SET nome = ?, evento = ?, evento_status = ?, prioridade = ?, condicoes = ?, acoes = ?, ativa = ?, versao = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?');
                $st->execute([...$params, $versao, $id]);
                $st = $this->db->prepare("UPDATE cartorio_tarefas_regra_lotes SET estado = 'cancelado', atualizado_em = CURRENT_TIMESTAMP WHERE regra_id = ? AND estado IN ('pendente', 'processando')");
                $st->execute([$id]);
            }
            if ($ativa) {
                $total = (int) $this->db->query('SELECT COUNT(*) FROM cartorio_tarefas')->fetchColumn();
                $st = $this->db->prepare('INSERT INTO cartorio_tarefas_regra_lotes (regra_id, versao, total) VALUES (?, ?, ?)');
                $st->execute([$id, $versao, $total]);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function lotesRecentes(): array
    {
        return $this->db->query('SELECT l.*, r.nome FROM cartorio_tarefas_regra_lotes l JOIN cartorio_tarefas_regras r ON r.id = l.regra_id ORDER BY l.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function falhasRecentes(): array
    {
        return $this->db->query('SELECT f.*, r.nome FROM cartorio_tarefas_automacao_falhas f LEFT JOIN cartorio_tarefas_regras r ON r.id = f.regra_id ORDER BY f.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function decodificar(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['prioridade'] = (int) $row['prioridade'];
        $row['ativa'] = (bool) $row['ativa'];
        $row['versao'] = (int) $row['versao'];
        $row['condicoes'] = json_decode((string) $row['condicoes'], true, 512, JSON_THROW_ON_ERROR);
        $row['acoes'] = json_decode((string) $row['acoes'], true, 512, JSON_THROW_ON_ERROR);
        return $row;
    }
}
