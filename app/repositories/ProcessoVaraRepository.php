<?php
declare(strict_types=1);

final class ProcessoVaraDataJudProtegidaException extends RuntimeException {}

final class ProcessoVaraRepository
{
    public const TAMANHO_MAXIMO = 180;
    private ?bool $disponivel = null;
    private ?bool $dataJudDisponivel = null;

    public function __construct(private PDO $db)
    {
    }

    public function disponivel(): bool
    {
        if ($this->disponivel === null) {
            $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? "SELECT 1 FROM pragma_table_info('processos') WHERE name = 'vara_nome'"
                : "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'processos' AND COLUMN_NAME = 'vara_nome' LIMIT 1";
            $stmt = $this->db->query($sql);
            $this->disponivel = $stmt !== false && $stmt->fetchColumn() !== false;
            if ($stmt) $stmt->closeCursor();
        }
        return $this->disponivel;
    }

    public static function normalizar(mixed $entrada): ?string
    {
        if (!is_string($entrada) || preg_match('/[\x00-\x1F\x7F]/u', $entrada) !== 0
            || mb_strlen(trim($entrada), 'UTF-8') > self::TAMANHO_MAXIMO) {
            throw new InvalidArgumentException('Informe uma Vara com até 180 caracteres, em uma linha.');
        }
        $vara = trim($entrada);
        return $vara === '' ? null : $vara;
    }

    public function porProcesso(string $id): ?string
    {
        if (!$this->disponivel()) return null;
        $stmt = $this->db->prepare('SELECT vara_nome FROM processos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $vara = $stmt->fetchColumn();
        return is_string($vara) && $vara !== '' ? $vara : null;
    }


    public function dataJudDisponivel(): bool
    {
        if ($this->dataJudDisponivel === null) {
            $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? "SELECT COUNT(*) FROM pragma_table_info('processos')
                   WHERE name IN ('vara_nome_datajud','orgao_municipio_ibge_datajud')"
                : "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'processos' AND COLUMN_NAME IN ('vara_nome_datajud','orgao_municipio_ibge_datajud')";
            $stmt = $this->db->query($sql);
            $this->dataJudDisponivel = $stmt !== false && (int) $stmt->fetchColumn() === 2;
            if ($stmt) $stmt->closeCursor();
        }
        return $this->dataJudDisponivel;
    }

    /** @return array{nome:?string,municipio_ibge:?string} */
    public function dadosDataJudPorProcesso(string $id): array
    {
        if (!$this->dataJudDisponivel()) return ['nome'=>null, 'municipio_ibge'=>null];
        $stmt = $this->db->prepare('SELECT vara_nome_datajud, orgao_municipio_ibge_datajud FROM processos WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return ['nome'=>$row['vara_nome_datajud'] ?? null, 'municipio_ibge'=>$row['orgao_municipio_ibge_datajud'] ?? null];
    }

    /** Somente após validar a fonte e bloquear/revalidar o processo na mesma transação. */
    public function registrarDataJud(string $id, array $orgao): bool
    {
        if (!$this->db->inTransaction() || !$this->dataJudDisponivel()) {
            throw new LogicException('Gravação de órgão DataJud sem transação ou schema.');
        }
        $atual = $this->dadosDataJudPorProcesso($id);
        $nome = $orgao['nome'] ?? $atual['nome'];
        $ibge = $orgao['municipio_ibge'] ?? $atual['municipio_ibge'];
        if ($atual === ['nome'=>$nome, 'municipio_ibge'=>$ibge]) return false;
        $stmt = $this->db->prepare('UPDATE processos SET vara_nome_datajud = :nome,
            orgao_municipio_ibge_datajud = :ibge, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        if (!$stmt->execute([':id'=>$id, ':nome'=>$nome, ':ibge'=>$ibge]) || $stmt->rowCount() !== 1) {
            throw new RuntimeException('Órgão julgador DataJud não confirmado.');
        }
        return true;
    }

    /** O chamador deve autorizar o processo sob bloqueio na mesma transação. */
    public function registrar(string $id, ?string $vara): bool
    {
        if (!$this->db->inTransaction() || !$this->disponivel()) {
            throw new LogicException('Gravação de Vara sem transação ou coluna.');
        }
        if ($this->dadosDataJudPorProcesso($id)['nome'] !== null) {
            throw new ProcessoVaraDataJudProtegidaException('A Vara sincronizada com o DataJud não pode ser alterada manualmente.');
        }
        if ($this->porProcesso($id) === $vara) return false;
        $stmt = $this->db->prepare('UPDATE processos SET vara_nome = :vara, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        if (!$stmt->execute([':id' => $id, ':vara' => $vara])) throw new RuntimeException('Vara não confirmada.');
        return $stmt->rowCount() === 1;
    }
}
