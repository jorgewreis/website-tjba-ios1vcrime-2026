-- Operação aditiva. O primeiro comando conserva NULL nos registros que já existem.
ALTER TABLE cartas_expedidas
    ADD COLUMN IF NOT EXISTS prioridade_tarefa ENUM('baixo','medio','alto','urgente','critico') NULL DEFAULT NULL;
ALTER TABLE cartas_expedidas ALTER COLUMN prioridade_tarefa SET DEFAULT 'medio';
