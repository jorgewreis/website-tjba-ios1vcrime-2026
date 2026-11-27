<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/ProcessoNivelSigilo.php';

final class ProcessoNivelSigiloRepository
{
    private ?bool $disponivel = null;

    public function __construct(private PDO $db)
    {
    }

    public function disponivel(): bool
    {
        if ($this->disponivel === null) {
            $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? "SELECT 1 FROM pragma_table_info('processos') WHERE name = 'nivel_sigilo'"
                : "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'processos' AND COLUMN_NAME = 'nivel_sigilo' LIMIT 1";
            $stmt = $this->db->query($sql);
            $this->disponivel = $stmt !== false && $stmt->fetchColumn() !== false;
        }
        return $this->disponivel;
    }

    /**
     * @param list<string> $ids
     * @return array<string, int>
     */
    public function porIds(array $ids): array
    {
        if ($ids === [] || !$this->disponivel()) return [];
        $niveis = [];
        foreach (array_chunk($ids, 200) as $lote) {
            $marcadores = implode(',', array_fill(0, count($lote), '?'));
            $stmt = $this->db->prepare(
                "SELECT id, nivel_sigilo FROM processos WHERE sigiloso <> 0
                 AND nivel_sigilo IS NOT NULL AND id IN ($marcadores)"
            );
            $stmt->execute($lote);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $nivel = ProcessoNivelSigilo::classificado($row['nivel_sigilo']);
                if ($nivel !== null) $niveis[(string) $row['id']] = $nivel;
            }
        }
        return $niveis;
    }

    /** O chamador deve autorizar e bloquear o registro na mesma transação. */
    public function registrar(string $id, int $nivel): bool
    {
        if (!$this->db->inTransaction() || ProcessoNivelSigilo::classificado($nivel) === null) {
            throw new LogicException('Nível inválido ou transação ausente.');
        }
        $stmt = $this->db->prepare(
            'UPDATE processos SET nivel_sigilo = :nivel WHERE id = :id AND sigiloso <> 0
             AND (nivel_sigilo IS NULL OR nivel_sigilo <> :comparacao)'
        );
        $stmt->bindValue(':nivel', $nivel, PDO::PARAM_INT);
        $stmt->bindValue(':comparacao', $nivel, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()) throw new RuntimeException('Falha ao registrar nível.');
        return $stmt->rowCount() === 1;
    }
}
