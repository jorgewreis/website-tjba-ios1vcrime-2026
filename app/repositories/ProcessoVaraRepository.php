<?php
declare(strict_types=1);

final class ProcessoVaraRepository
{
    public const TAMANHO_MAXIMO = 180;
    private ?bool $disponivel = null;

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

    /** O chamador deve autorizar o processo sob bloqueio na mesma transação. */
    public function registrar(string $id, ?string $vara): bool
    {
        if (!$this->db->inTransaction() || !$this->disponivel()) {
            throw new LogicException('Gravação de Vara sem transação ou coluna.');
        }
        if ($this->porProcesso($id) === $vara) return false;
        $stmt = $this->db->prepare('UPDATE processos SET vara_nome = :vara, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        if (!$stmt->execute([':id' => $id, ':vara' => $vara])) throw new RuntimeException('Vara não confirmada.');
        return $stmt->rowCount() === 1;
    }
}
